<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Background asset discovery (asset management redesign, Phase A, Task 11;
 * spec section 7).
 *
 * A discovery run is a row in `asset_discovery_runs`. POST
 * /api/v2/assets/discovery-runs validates the range, stores the run
 * `queued` with the verified state computed from the REQUESTER's permissions
 * (spec decision D2 -- never from the request body) and enqueues one
 * `core_asset_discovery` queue task carrying the run id. The job
 * (includes/jobs/core_asset_discovery.php) calls assets_discovery_queue_check(),
 * which scans the range in time slices:
 *
 *   - The first slice detects which probe method works in the worker
 *     (includes/assets_discovery_probe.php: ping socket, raw socket, the ping
 *     command or TCP connects) and records it on the run with its TCP ports;
 *     continuations reuse it, and a run whose recorded method is no longer
 *     available fails with `probe_unavailable` instead of switching method.
 *   - Addresses are iterated as integers; long2ip() forms the host string only
 *     at probe time (ping_check() passes it through escapeshellarg()).
 *   - Progress (hosts_scanned / live_hosts / new_assets) is checkpointed after
 *     every batch (ASSETS_DISCOVERY_CHUNK hosts for per-host probing,
 *     ASSETS_DISCOVERY_SOCKET_CHUNK for the batch methods), and the run's
 *     status is re-read at each checkpoint so a cancel (DELETE .../{id} sets
 *     `cancelled`) stops the scan.
 *   - A slice ends after ASSETS_DISCOVERY_SLICE_SECONDS. The run stays
 *     `running`, a continuation task is enqueued and the current task
 *     completes, so one large range never holds the single queue worker (and
 *     every other background job) for hours.
 *
 * Retry / idempotence: a scan resumes from the run's checkpointed
 * hosts_scanned, so a retried or continued attempt re-pings at most one chunk.
 * Addresses that already exist as assets (by IP or by the generated name) are
 * skipped before add_asset() is called, so a resumed attempt never duplicates
 * or overwrites an asset. When an attempt throws, the run goes back to
 * `queued` with error_text recorded and queue_check returns false: the
 * worker's handle_queue_task_failure() owns the backoff retries, and the job's
 * on_terminal_failure hook marks the run `failed` only once those retries are
 * exhausted (writing-queue-jobs Rule 2: the job never sets its own task
 * `failed`).
 */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/queues.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/assets_discovery_rules.php'));
require_once(realpath(__DIR__ . '/assets_discovery_probe.php'));

// Constants and the pure helpers (request validation, status transitions,
// asset naming, visibility) live in assets_discovery_rules.php.

/**
 * Whether the discovery job is registered (jobs/index.php). The Manage
 * assets page shows Discover assets only when it is.
 */
function assets_discovery_job_registered(): bool
{
    $list = include(realpath(__DIR__ . '/jobs/index.php'));
    return is_array($list) && !empty($list[ASSETS_DISCOVERY_TASK_TYPE]);
}

/** @return array<string,mixed>|null the raw run row */
function assets_discovery_get_run(PDO $db, int $run_id): ?array
{
    $stmt = $db->prepare("SELECT * FROM `asset_discovery_runs` WHERE `id` = :id");
    $stmt->bindValue(':id', $run_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Moves a run to $to when its current status allows it. Returns whether the
 * run is now in $to because of this call (a guarded UPDATE, so a concurrent
 * cancel is never overwritten).
 *
 * @param array<string,scalar|null> $set extra columns to write (column => value)
 * @param bool $finish also stamp finished_at = NOW()
 */
function assets_discovery_transition(PDO $db, int $run_id, string $to, array $set = [], bool $finish = false): bool
{
    static $columns = ['total_hosts', 'error_text'];
    $from = assets_discovery_sources_for($to);
    if (!$from) {
        return false;
    }
    $assign = ['`status` = ?'];
    $params = [$to];
    foreach ($set as $col => $value) {
        if (in_array($col, $columns, true)) {
            $assign[] = "`{$col}` = ?";
            $params[] = $value;
        }
    }
    if ($finish) {
        $assign[] = '`finished_at` = NOW()';
    }
    $in = implode(',', array_fill(0, count($from), '?'));
    $stmt = $db->prepare("UPDATE `asset_discovery_runs` SET " . implode(', ', $assign) . " WHERE `id` = ? AND `status` IN ({$in})");
    $stmt->execute(array_merge($params, [$run_id], $from));
    if ($stmt->rowCount() > 0) {
        return true;
    }
    // rowCount() is 0 when the row matched but nothing changed (running ->
    // running with the same values), so confirm from the row itself.
    $run = assets_discovery_get_run($db, $run_id);
    return $run !== null && $run['status'] === $to && in_array($to, $from, true);
}

/**
 * IP addresses and lower-cased names of every existing asset, for the skip
 * check. Uses try_decrypt_or_null() so a row that cannot be decrypted is
 * ignored quietly instead of raising the decrypt-failure alert.
 *
 * @return array{ips:array<string,true>,names:array<string,true>}
 */
function assets_discovery_existing_index(PDO $db): array
{
    $ips = [];
    $names = [];
    $stmt = $db->prepare("SELECT `ip`, `name` FROM `assets`");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ip = try_decrypt_or_null($row['ip']);
        $name = try_decrypt_or_null($row['name']);
        if (is_string($ip) && $ip !== '') {
            $ips[trim($ip)] = true;
        }
        if (is_string($name) && $name !== '') {
            $names[strtolower(trim($name))] = true;
        }
    }
    return ['ips' => $ips, 'names' => $names];
}

/** Default pinger: one ICMP echo through ping_check() (escapeshellarg'd). */
function assets_discovery_default_pinger(string $ip): bool
{
    return ping_check($ip);
}

/** Default resolver: a reverse lookup, false when there is no answer. */
function assets_discovery_default_resolver(string $ip)
{
    $name = @gethostbyaddr($ip);
    return is_string($name) ? $name : false;
}

/* =========================================================================
 * The config.php target allowlist
 * ========================================================================= */

/**
 * The system administrator's allowlist of discovery targets
 * ($asset_discovery_allowed_ranges in config.php), parsed. Fail closed: unset
 * or empty allows nothing. Unusable entries are logged once per process.
 *
 * @return array{configured:bool,ranges:array<int,array{0:int,1:int}>,entries:string[],hosts:string[],invalid:string[]}
 */
function assets_discovery_allowlist(): array
{
    static $warned = false, $hinted = false;
    $allow = assets_discovery_parse_allowlist($GLOBALS['asset_discovery_allowed_ranges'] ?? null);
    // Why a list that IS set still reads as "not configured" (once per process).
    if ($allow['hint'] !== null && !$hinted) {
        $hinted = true;
        write_debug_log($allow['hint'] === 'not_array'
            ? "Asset discovery: \$asset_discovery_allowed_ranges in config.php is not an array, so no discovery target is allowed. Use a list such as ['10.20.0.0/16', '192.168.50.0/24']."
            : "Asset discovery: \$asset_discovery_allowed_ranges in config.php has no IPv4 address or CIDR entry (hostnames never match a discovery range), so no discovery target is allowed.", 'warning');
    }
    if ($allow['invalid'] && !$warned) {
        $warned = true;
        write_debug_log("Asset discovery: ignoring " . count($allow['invalid']) . " unusable \$asset_discovery_allowed_ranges entr" . (count($allow['invalid']) === 1 ? 'y' : 'ies') . " in config.php (use IPv4 addresses or CIDR blocks): " . implode(', ', array_map(static fn($e) => substr($e, 0, 60), $allow['invalid'])), 'warning');
    }
    return $allow;
}

/* =========================================================================
 * Probe method settings and the worker's detected capabilities
 * ========================================================================= */

/** Setting: force a probe method (auto | icmp_dgram | icmp_raw | ping_binary | tcp). */
const ASSETS_DISCOVERY_SETTING_PROBE_METHOD = 'asset_discovery_probe_method';
/** Setting: the default TCP ports for the TCP probe (admin-editable). */
const ASSETS_DISCOVERY_SETTING_TCP_PORTS = 'asset_discovery_tcp_ports';
/** Setting: the queue worker's latest capability detection (JSON). */
const ASSETS_DISCOVERY_SETTING_WORKER_CAPS = 'asset_discovery_worker_capabilities';

/**
 * The forced probe method from the setting, or 'auto' (also for anything not
 * on the whitelist). Read uncached: the queue worker is long-running.
 */
function assets_discovery_configured_probe_method(?PDO $db = null): string
{
    $value = get_setting(ASSETS_DISCOVERY_SETTING_PROBE_METHOD, 'auto', false, $db);
    return is_string($value) && in_array($value, ASSETS_DISCOVERY_PROBE_METHODS, true) ? $value : 'auto';
}

/**
 * The admin save of the default discovery TCP ports (Settings > Preferences).
 * $posted is the submitted field, or null when the form did not send it.
 *  - null: nothing happens;
 *  - blank: back to the built-in default -- the row is removed (so a later
 *    change of the default applies too) and one 'asset_settings' audit entry
 *    is written, only when a row existed;
 *  - invalid (assets_discovery_validate_tcp_ports()): refused, nothing changes,
 *    error is the lang key to show;
 *  - valid: stored normalised, when it differs from the current value.
 *
 * @return array{changed:bool, error:?string}
 */
function assets_discovery_save_default_tcp_ports(?string $posted): array
{
    if ($posted === null) {
        return ['changed' => false, 'error' => null];
    }

    if (trim($posted) === '') {
        if (get_setting('asset_discovery_tcp_ports', null, false) === null) {
            return ['changed' => false, 'error' => null];
        }
        delete_setting('asset_discovery_tcp_ports');
        // Entity-less (stored id 0): its own log type, never 'asset', so it
        // cannot show in asset #1000's audit trail.
        write_log(1000, $_SESSION['uid'] ?? 0, _lang('DiscoveryDefaultTcpPortsResetLog', ['user' => $_SESSION['user'] ?? '']), 'asset_settings');
        return ['changed' => true, 'error' => null];
    }

    $validated = assets_discovery_validate_tcp_ports($posted);
    if (!$validated['ok']) {
        return ['changed' => false, 'error' => 'DiscoveryPortsInvalid'];
    }
    $csv = assets_discovery_tcp_ports_csv($validated['ports']);
    if ($csv == get_setting('asset_discovery_tcp_ports', ASSETS_DISCOVERY_DEFAULT_TCP_PORTS, false)) {
        return ['changed' => false, 'error' => null];
    }
    return ['changed' => (bool)update_setting('asset_discovery_tcp_ports', $csv), 'error' => null];
}

/**
 * The admin's default TCP ports (validated). An unusable stored value falls
 * back to the built-in default.
 *
 * @return int[]
 */
function assets_discovery_configured_tcp_ports(?PDO $db = null): array
{
    $value = get_setting(ASSETS_DISCOVERY_SETTING_TCP_PORTS, ASSETS_DISCOVERY_DEFAULT_TCP_PORTS, false, $db);
    $checked = assets_discovery_validate_tcp_ports(is_string($value) ? $value : '');
    if (!$checked['ok']) {
        write_debug_log("Asset discovery: the " . ASSETS_DISCOVERY_SETTING_TCP_PORTS . " setting is not a valid port list; using the default ports.", 'warning');
        $checked = assets_discovery_validate_tcp_ports(ASSETS_DISCOVERY_DEFAULT_TCP_PORTS);
    }
    return $checked['ports'];
}

/**
 * Stores the worker's detection so the Discover dialog can show what the
 * worker (not the web server) can do. Written with update_or_insert_setting()
 * rather than update_setting(): this is machine state refreshed on every run,
 * not an administrator's change, so it must not add an audit-trail line each
 * time.
 *
 * @param array<string,mixed> $caps from assets_discovery_detect_capabilities()
 */
function assets_discovery_record_worker_capabilities(PDO $db, array $caps, ?int $now = null): void
{
    $record = [
        'icmp_dgram' => !empty($caps['icmp_dgram']),
        'icmp_raw' => !empty($caps['icmp_raw']),
        'ping_binary' => !empty($caps['ping_binary']),
        'tcp' => true,
        'reasons' => array_values(array_filter((array)($caps['reasons'] ?? []), 'is_string')),
        'detected_at' => $now ?? time(),
    ];
    $json = (string)json_encode($record);
    update_or_insert_setting(ASSETS_DISCOVERY_SETTING_WORKER_CAPS, $json, $db);
}

/**
 * The worker's recorded detection when it is recent (at most
 * ASSETS_DISCOVERY_WORKER_CAPS_MAX_AGE old), else null.
 *
 * @return array{icmp_dgram:bool,icmp_raw:bool,ping_binary:bool,tcp:true,reasons:string[],detected_at:int}|null
 */
function assets_discovery_worker_capabilities(PDO $db, ?int $now = null): ?array
{
    $raw = get_setting(ASSETS_DISCOVERY_SETTING_WORKER_CAPS, '', false, $db);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['detected_at']) || !is_int($data['detected_at'])) {
        return null;
    }
    $now = $now ?? time();
    if ($data['detected_at'] > $now + 300 || $now - $data['detected_at'] > ASSETS_DISCOVERY_WORKER_CAPS_MAX_AGE) {
        return null;
    }
    return [
        'icmp_dgram' => !empty($data['icmp_dgram']),
        'icmp_raw' => !empty($data['icmp_raw']),
        'ping_binary' => !empty($data['ping_binary']),
        'tcp' => true,
        'reasons' => array_values(array_filter((array)($data['reasons'] ?? []), 'is_string')),
        'detected_at' => $data['detected_at'],
    ];
}

/**
 * GET /assets/discovery-runs/capabilities: the probe method discovery would
 * use. The worker's own recorded detection when there is a recent one
 * (source `worker`), else a detection in the web server's process (source
 * `web`) -- which can differ from the worker's (another user, other groups or
 * capabilities), so the dialog says so. Raw values; labels are translated.
 *
 * @param array<string,mixed>|null $web_caps injectable for tests
 * @return array<string,mixed>
 */
function assets_discovery_capabilities_payload(PDO $db, bool $can_edit_ports, ?array $web_caps = null, ?int $now = null): array
{
    global $lang;
    $allow = assets_discovery_allowlist();
    $worker = assets_discovery_worker_capabilities($db, $now);
    $caps = $worker ?? ($web_caps ?? assets_discovery_detect_capabilities());
    $forced = assets_discovery_configured_probe_method($db);
    $method = assets_discovery_pick_method($caps, $forced);
    $available = array_values(array_filter(ASSETS_DISCOVERY_PROBE_METHODS, fn($m) => assets_discovery_method_available($caps, $m)));
    $key = assets_discovery_probe_method_lang_key($method);
    return [
        'method' => $method,
        'method_label' => (string)($lang[$key] ?? $method),
        'methods_available' => $available,
        'forced_method' => $forced,
        // Environment diagnostics only for those who can act on them.
        'reasons' => $can_edit_ports ? array_values((array)($caps['reasons'] ?? [])) : [],
        'source' => $worker !== null ? 'worker' : 'web',
        'detected_at' => $worker !== null ? date('Y-m-d H:i:s', $worker['detected_at']) : '',
        'tcp_ports' => assets_discovery_tcp_ports_csv(assets_discovery_configured_tcp_ports($db)),
        'tcp_ports_max' => ASSETS_DISCOVERY_MAX_TCP_PORTS,
        'can_edit_ports' => $can_edit_ports,
        // The config.php allowlist (operator configuration, not a secret;
        // this endpoint already requires asset_discovery). Not configured =
        // discovery cannot start.
        'configured' => $allow['configured'],
        'allowed_ranges' => $allow['entries'],
    ];
}

/**
 * Settles the probe method of a run that is starting or resuming. A run that
 * already has one keeps it (a resumed run never switches method); otherwise
 * the method is picked from $caps and the forcing setting and recorded on the
 * run together with its effective TCP ports.
 *
 * @param array<string,mixed> $run  the run row
 * @param array<string,mixed> $caps from assets_discovery_detect_capabilities()
 * A recorded method that is unavailable is reported with `transient` true
 * when the cause is a passing socket error (EMFILE, ENOBUFS, ...) -- worth a
 * retry -- and false when it is structural (missing privilege or binary,
 * unknown method).
 *
 * @return array{ok:bool,method:string,ports:int[],transient:bool}  ok false = the recorded method is unavailable
 */
function assets_discovery_settle_probe_method(PDO $db, array $run, array $caps): array
{
    $run_id = (int)$run['id'];
    $stored_ports = (string)($run['tcp_ports'] ?? '');
    $checked = $stored_ports !== '' ? assets_discovery_validate_tcp_ports($stored_ports) : ['ok' => false];
    $ports = $checked['ok'] ? $checked['ports'] : assets_discovery_configured_tcp_ports($db);

    $recorded = (string)($run['probe_method'] ?? '');
    if ($recorded !== '') {
        $ok = assets_discovery_method_available($caps, $recorded);
        $transient = !$ok && assets_discovery_probe_failure_is_transient($recorded, (array)($caps['reasons'] ?? []));
        return ['ok' => $ok, 'method' => $recorded, 'ports' => $ports, 'transient' => $transient];
    }

    $forced = assets_discovery_configured_probe_method($db);
    $method = assets_discovery_pick_method($caps, $forced);
    if ($forced !== 'auto' && $method !== $forced) {
        write_debug_log("Asset discovery: the forced probe method {$forced} is not available to the worker; run #{$run_id} uses {$method} instead.", 'warning');
    }
    // Recorded once (the first slice); tcp_ports keeps a per-run override.
    $stmt = $db->prepare("UPDATE `asset_discovery_runs` SET `probe_method` = ?, `tcp_ports` = COALESCE(NULLIF(`tcp_ports`, ''), ?) WHERE `id` = ? AND `probe_method` IS NULL");
    $stmt->execute([$method, $method === 'tcp' ? assets_discovery_tcp_ports_csv($ports) : null, $run_id]);
    if ($stmt->rowCount() === 0) {
        // Someone else recorded a method first (a concurrent attempt): that
        // one is the run's method.
        $now = assets_discovery_get_run($db, $run_id);
        if ($now !== null && (string)($now['probe_method'] ?? '') !== '') {
            return assets_discovery_settle_probe_method($db, $now, $caps);
        }
    }
    write_debug_log("Asset discovery: run #{$run_id} probes with {$method}.", 'info');
    return ['ok' => true, 'method' => $method, 'ports' => $ports, 'transient' => false];
}

/**
 * Scans (a slice of) one run. The job's core loop; every transport is
 * injectable so tests never touch the network.
 *
 * Probing: an injected per-host $pinger (callable(string ip):bool) is used as
 * is, ASSETS_DISCOVERY_CHUNK hosts per batch; an injected batch $prober
 * (callable(int[] ips, string method, int[] ports):array<int,bool>) gets
 * ASSETS_DISCOVERY_SOCKET_CHUNK hosts per call. Without either, the run's
 * probe method decides: ping_binary pings one host at a time through
 * ping_check(), the others go through assets_discovery_probe_hosts().
 * $caps (a detection result) replaces the real detection.
 *
 * Returns true when the attempt finished cleanly: the run completed, was
 * cancelled, failed for good (unusable range, probe method gone, TCP answers
 * that cannot be trusted), was already terminal, or the time slice ran out
 * with the run still `running` (the caller enqueues a continuation). Returns
 * false when the recorded probe method's socket could not be opened for a
 * transient reason (the run goes back to `queued`; the worker retries). Exceptions from the transports, the
 * resolver or the database propagate to the caller.
 *
 * @param (callable(string):bool)|null $pinger
 * @param (callable(string):(string|false))|null $resolver
 * @param int $time_budget seconds for this slice; 0 = no limit
 * @param (callable(int[],string,int[]):array<int,bool>)|null $prober
 * @param array<string,mixed>|null $caps
 */
function assets_discovery_run_scan(PDO $db, int $run_id, ?callable $pinger = null, ?callable $resolver = null, int $time_budget = 0, ?callable $prober = null, ?array $caps = null): bool
{
    $resolver = $resolver ?? 'assets_discovery_default_resolver';

    $run = assets_discovery_get_run($db, $run_id);
    if ($run === null) {
        write_debug_log("Asset discovery: run #{$run_id} no longer exists; nothing to do.", 'notice');
        return true;
    }
    if (assets_discovery_is_terminal((string)$run['status'])) {
        write_debug_log("Asset discovery: run #{$run_id} is already {$run['status']}; nothing to do.", 'debug');
        return true;
    }

    $parsed = assets_parse_discovery_range((string)$run['range_text'], ASSETS_DISCOVERY_MAX_HOSTS);
    // Reserved ranges are refused by assets_discovery_target_error() below
    // (which maps them to invalid_range too); only an unparsable row here.
    if (!$parsed['ok']) {
        // Validated at enqueue, so this is a corrupted row: not retryable.
        write_debug_log("Asset discovery: run #{$run_id} has an unusable range; marking it failed.", 'error');
        assets_discovery_transition($db, $run_id, 'failed', ['error_text' => 'invalid_range'], true);
        return true;
    }

    // The config.php allowlist, checked at every slice through the same
    // assets_discovery_target_error() the API uses: a run queued before the
    // allowlist was narrowed (or removed) must not keep scanning.
    $target_error = assets_discovery_target_error((int)$parsed['start'], (int)$parsed['end'], assets_discovery_allowlist());
    if ($target_error !== null) {
        $code = assets_discovery_allowlist_run_code($target_error);
        if ($code === 'invalid_range') {
            // Reserved ranges are refused at enqueue: a corrupted row.
            write_debug_log("Asset discovery: run #{$run_id} has a reserved range; marking it failed.", 'error');
        } else {
            write_debug_log("Asset discovery: run #{$run_id} is not covered by the config.php discovery allowlist ({$code}); marking it failed.", 'warning');
        }
        assets_discovery_transition($db, $run_id, 'failed', ['error_text' => $code], true);
        return true;
    }

    // Start (or resume) the run. started_at keeps the first attempt's time.
    if (!assets_discovery_transition($db, $run_id, 'running', ['total_hosts' => (int)$parsed['count'], 'error_text' => null])) {
        write_debug_log("Asset discovery: run #{$run_id} could not start (cancelled meanwhile).", 'info');
        return true;
    }
    $db->prepare("UPDATE `asset_discovery_runs` SET `started_at` = NOW() WHERE `id` = ? AND `started_at` IS NULL")->execute([$run_id]);

    // The probe method: detected in THIS process (the worker), recorded on the
    // run the first time, reused by every continuation.
    $detected = $caps === null;
    $caps = $caps ?? assets_discovery_detect_capabilities();
    if ($detected && $pinger === null && $prober === null) {
        assets_discovery_record_worker_capabilities($db, $caps);
    }
    $probe = assets_discovery_settle_probe_method($db, $run, $caps);
    if (!$probe['ok'] && $probe['transient']) {
        // A passing socket error (out of descriptors or buffers): back to
        // queued with the reason, and false so the worker's backoff retries
        // the task. The run fails only when the retries run out
        // (on_terminal_failure keeps this code).
        write_debug_log("Asset discovery: run #{$run_id} could not open its {$probe['method']} socket this time; the attempt will be retried.", 'warning');
        assets_discovery_transition($db, $run_id, 'queued', ['error_text' => 'probe_unavailable']);
        return false;
    }
    if (!$probe['ok']) {
        // Retrying in the same worker cannot bring the method back.
        write_debug_log("Asset discovery: run #{$run_id} was started with the {$probe['method']} probe method, which this worker can no longer use; marking it failed.", 'error');
        assets_discovery_transition($db, $run_id, 'failed', ['error_text' => 'probe_unavailable'], true);
        return true;
    }
    $method = $probe['method'];
    $ports = $probe['ports'];

    // Batch transport. A per-host pinger (injected, or the ping command) runs
    // host by host in small chunks; the socket methods probe a whole batch.
    if ($pinger === null && $prober === null && $method === 'ping_binary') {
        $pinger = 'assets_discovery_default_pinger';
    }
    if ($pinger !== null) {
        $chunk = ASSETS_DISCOVERY_CHUNK;
        $batch = static function (array $ips) use ($pinger): array {
            $alive = [];
            foreach ($ips as $n) {
                $alive[$n] = (bool)$pinger(long2ip($n));
            }
            return $alive;
        };
    } else {
        $chunk = ASSETS_DISCOVERY_SOCKET_CHUNK;
        $batch = $prober !== null
            ? static fn(array $ips): array => $prober($ips, $method, $ports)
            : static fn(array $ips): array => assets_discovery_probe_hosts($ips, $method, ['ports' => $ports]);
    }

    // TCP connects prove little on a network that answers for everyone (a
    // transparent proxy, a firewall that resets every connect): each slice
    // first probes a canary that is never a real host, and each batch that
    // comes back almost entirely alive trips a breaker. Either way the run
    // fails instead of minting an asset per address.
    $tcp_guard = $method === 'tcp' && $pinger === null;
    $unreliable = static function () use ($db, $run_id): bool {
        write_debug_log("Asset discovery: run #{$run_id} stopped: TCP connects are answered for addresses that cannot be live hosts, so the results would be meaningless.", 'warning');
        assets_discovery_transition($db, $run_id, 'failed', ['error_text' => 'tcp_probe_unreliable'], true);
        return true;
    };
    if ($tcp_guard && !empty($batch([ASSETS_DISCOVERY_TCP_CANARY])[ASSETS_DISCOVERY_TCP_CANARY])) {
        return $unreliable();
    }

    $started = time();
    $scanned = min((int)$run['hosts_scanned'], (int)$parsed['count']);
    $live = (int)$run['live_hosts'];
    $new = (int)$run['new_assets'];
    $resolve = (int)$run['resolve_names'] === 1;
    $verified = (int)$run['add_as_verified'] === 1;
    $teams = implode(',', array_filter(array_map('intval', explode(',', (string)$run['team_ids']))));
    $valuation = get_default_asset_valuation();
    $existing = assets_discovery_existing_index($db);

    $checkpoint = $db->prepare("UPDATE `asset_discovery_runs` SET `hosts_scanned` = ?, `live_hosts` = ?, `new_assets` = ? WHERE `id` = ? AND `status` = 'running'");
    $status_of = $db->prepare("SELECT `status` FROM `asset_discovery_runs` WHERE `id` = ?");

    $n = (int)$parsed['start'] + $scanned;
    $end = (int)$parsed['end'];
    while ($n <= $end) {
        $ips = range($n, min($end, $n + $chunk - 1));
        $alive = $batch($ips);
        if ($tcp_guard && assets_discovery_tcp_batch_unreliable(count(array_filter($alive)), count($ips))) {
            return $unreliable();
        }
        foreach ($ips as $addr) {
            if (!empty($alive[$addr])) {
                $ip = long2ip($addr);
                $live++;
                $name = assets_discovery_asset_name($ip, $resolve ? $resolver($ip) : false);
                if (!isset($existing['ips'][$ip]) && !isset($existing['names'][strtolower($name)])) {
                    // Explicit verified value from the run (imported: true makes
                    // add_asset() keep it exactly instead of re-deriving it from
                    // the worker's session).
                    $asset_id = add_asset($ip, $name, $valuation, '', $teams, '', '', $verified, [], [], true);
                    if ($asset_id) {
                        $new++;
                    } else {
                        write_debug_log("Asset discovery: run #{$run_id} could not add the asset for a live host.", 'warning');
                    }
                }
                // Either way the address and name now count as taken.
                $existing['ips'][$ip] = true;
                $existing['names'][strtolower($name)] = true;
            }
            $scanned++;
        }
        $n += count($ips);

        // Checkpoint after every batch: progress, then cancellation.
        $checkpoint->execute([$scanned, $live, $new, $run_id]);
        $status_of->execute([$run_id]);
        $status = (string)$status_of->fetchColumn();
        $status_of->closeCursor();
        if ($status !== 'running') {
            write_debug_log("Asset discovery: run #{$run_id} stopped at {$scanned} hosts ({$status}).", 'info');
            return true;
        }
        if ($n <= $end && $time_budget > 0 && (time() - $started) >= $time_budget) {
            write_debug_log("Asset discovery: run #{$run_id} slice ended at {$scanned} of {$parsed['count']} hosts.", 'debug');
            return true;
        }
    }

    if (assets_discovery_transition($db, $run_id, 'completed', [], true)) {
        write_debug_log("Asset discovery: run #{$run_id} completed ({$method}): {$scanned} hosts scanned, {$live} live, {$new} new assets.", 'info');
    }
    return true;
}

/**
 * The job's queue_check body: one attempt at one run's task.
 *
 * Returns true when the attempt finished (the worker marks the task
 * completed); false when it failed (the worker's handle_queue_task_failure()
 * schedules the retry). Never touches the task's own status.
 */
function assets_discovery_queue_check(array $task, PDO $db, ?callable $pinger = null, ?callable $resolver = null, int $time_budget = ASSETS_DISCOVERY_SLICE_SECONDS, ?callable $prober = null, ?array $caps = null): bool
{
    $payload = json_decode($task['payload'] ?? '{}', true);
    $run_id = (int)($payload['run_id'] ?? 0);
    if ($run_id < 1) {
        // A malformed task can never succeed; completing it avoids five
        // pointless retries.
        write_debug_log("Asset discovery: task #" . (int)($task['id'] ?? 0) . " has no run id; ignoring it.", 'error');
        return true;
    }

    $run = assets_discovery_get_run($db, $run_id);
    if ($run === null || assets_discovery_is_terminal((string)$run['status'])) {
        return true;
    }

    try {
        // Run as the requester so audit entries and workflow events name the
        // person who started the discovery. A disabled or locked-out
        // requester's run does not continue.
        $invalid = new \stdClass();
        $not_permitted = new \stdClass();
        $result = run_as_user_for_queue((int)$run['created_by'], $db, function () use ($db, $run_id, $pinger, $resolver, $time_budget, $prober, $caps, $not_permitted) {
            // The requester's permissions are re-read for every slice: a user
            // who lost asset or asset_discovery since queueing the run does
            // not keep scanning on the old grant.
            if (!check_permission('asset') || !asset_user_can('discovery')) {
                return $not_permitted;
            }
            return assets_discovery_run_scan($db, $run_id, $pinger, $resolver, $time_budget, $prober, $caps);
        }, $invalid);

        if ($result === $invalid) {
            assets_discovery_transition($db, $run_id, 'failed', ['error_text' => 'requester_inactive'], true);
            return true;
        }
        if ($result === $not_permitted) {
            // An expected operational condition that ends the run: notice.
            write_debug_log("Asset discovery: run #{$run_id} stopped; its requester no longer holds asset discovery.", 'notice');
            assets_discovery_transition($db, $run_id, 'failed', ['error_text' => 'requester_not_permitted'], true);
            return true;
        }
        if ($result !== true) {
            return false;
        }

        // The slice ran out with the run still running: hand over to a
        // continuation task so the worker can serve other jobs in between.
        $after = assets_discovery_get_run($db, $run_id);
        if ($after !== null && $after['status'] === 'running') {
            if (!queue_task($db, ASSETS_DISCOVERY_TASK_TYPE, ['run_id' => $run_id], ASSETS_DISCOVERY_PRIORITY)) {
                // Retrying this task resumes from the checkpoint instead.
                return false;
            }
        }
        return true;
    } catch (\Throwable $e) {
        write_debug_log("Asset discovery: run #{$run_id} attempt failed: " . $e->getMessage(), 'error');
        // Back to queued (awaiting the worker's retry). error_text holds a
        // short code, never the exception text (that is only in the log);
        // the API maps it to a translated reason. The run becomes `failed`
        // only in on_terminal_failure.
        assets_discovery_transition($db, $run_id, 'queued', ['error_text' => 'scan_error']);
        return false;
    }
}

/**
 * The job's on_terminal_failure body: the worker exhausted the task's retries.
 */
function assets_discovery_on_terminal_failure(array $task, PDO $db, string $error): void
{
    $payload = json_decode($task['payload'] ?? '{}', true);
    $run_id = (int)($payload['run_id'] ?? 0);
    if ($run_id < 1) {
        return;
    }
    $run = assets_discovery_get_run($db, $run_id);
    $code = ($run !== null && !empty($run['error_text'])) ? (string)$run['error_text'] : 'scan_error';
    if (assets_discovery_transition($db, $run_id, 'failed', ['error_text' => $code], true)) {
        write_debug_log("Asset discovery: run #{$run_id} failed after its retries were exhausted ({$error}).", 'error');
    }
}

/* =========================================================================
 * API-side helpers (enqueue, list, cancel)
 * ========================================================================= */

/**
 * The request checks POST /assets/discovery-runs makes before it touches the
 * database: body validation (range, reserved addresses, size, ports) and then
 * the system administrator's config.php allowlist (fail closed: none set =
 * nothing allowed). Returns the validated request, or the refusal the route
 * answers -- status, lang code and the lang params / extra data keys.
 *
 * @param array<string,mixed> $body
 * @return array{req:?array, error:?array{status:int, code:string, params:array, extra:array}}
 */
function assets_discovery_create_request_error(array $body): array
{
    $req = assets_discovery_validate_request($body);
    if (!$req['ok']) {
        // `max` is the limit the error is about (hosts, or TCP ports).
        $max = $req['error'] === 'DiscoveryPortsInvalid' ? ASSETS_DISCOVERY_MAX_TCP_PORTS : ASSETS_DISCOVERY_MAX_HOSTS;
        return ['req' => null, 'error' => ['status' => 422, 'code' => (string)$req['error'], 'params' => ['max' => number_format($max)], 'extra' => ['max' => $max]]];
    }

    $target_error = assets_discovery_target_error((int)$req['start'], (int)$req['end'], assets_discovery_allowlist());
    if ($target_error !== null) {
        return ['req' => null, 'error' => ['status' => 422, 'code' => $target_error, 'params' => [], 'extra' => []]];
    }

    return ['req' => $req, 'error' => null];
}

/**
 * Checks team ids a caller wants discovered assets assigned to: every team
 * must exist, and under Team Separation a non-admin may only use teams they
 * belong to. Returns null when fine, else 'DiscoveryTeamsInvalid'.
 *
 * @param int[] $team_ids
 */
function assets_discovery_check_teams(array $team_ids): ?string
{
    if (!$team_ids) {
        return null;
    }
    $db = db_open();
    $in = implode(',', array_fill(0, count($team_ids), '?'));
    $stmt = $db->prepare("SELECT `value` FROM `team` WHERE `value` IN ({$in})");
    $stmt->execute($team_ids);
    $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    db_close($db);
    if (array_diff($team_ids, $existing)) {
        return 'DiscoveryTeamsInvalid';
    }
    if (team_separation_extra() && !is_admin()) {
        $mine = array_map('intval', get_user_teams((int)($_SESSION['uid'] ?? 0)));
        if (array_diff($team_ids, $mine)) {
            return 'DiscoveryTeamsInvalid';
        }
    }
    return null;
}

/** Queued + running runs of one user, or of the whole instance when $uid is null. */
function assets_discovery_active_count(PDO $db, ?int $uid = null): int
{
    if ($uid === null) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM `asset_discovery_runs` WHERE `status` IN ('queued', 'running')");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM `asset_discovery_runs` WHERE `created_by` = ? AND `status` IN ('queued', 'running')");
        $stmt->execute([$uid]);
    }
    return (int)$stmt->fetchColumn();
}

/**
 * Which concurrency cap a new run by $uid would break: the per-user cap
 * (DiscoveryTooManyActiveRuns), the instance-wide cap
 * (DiscoveryTooManyActiveRunsInstance), or null. Callers heal stuck runs
 * first (assets_discovery_heal_stuck_runs) so a lost run never holds a slot.
 *
 * @return array{code:string,max:int}|null
 */
function assets_discovery_cap_error(PDO $db, int $uid): ?array
{
    if (assets_discovery_active_count($db, $uid) >= ASSETS_DISCOVERY_MAX_ACTIVE_PER_USER) {
        return ['code' => 'DiscoveryTooManyActiveRuns', 'max' => ASSETS_DISCOVERY_MAX_ACTIVE_PER_USER];
    }
    if (assets_discovery_active_count($db) >= ASSETS_DISCOVERY_MAX_ACTIVE_TOTAL) {
        return ['code' => 'DiscoveryTooManyActiveRunsInstance', 'max' => ASSETS_DISCOVERY_MAX_ACTIVE_TOTAL];
    }
    return null;
}

/**
 * Heals runs the queue has lost: a queued/running run older than the grace
 * period with no pending or in-progress core_asset_discovery task carrying
 * its id (an old worker without the job definition dropped it, the task was
 * purged, ...) moves to `failed` with the worker_lost code. Otherwise such a
 * run would hold its requester's slot and keep the page polling forever.
 * Called on read (list, single get) and before the caps are checked. While a
 * run is being scanned its task is still `pending` (the worker does not claim
 * tasks), and between slices the continuation is pending, so a live run
 * always has a task. Returns how many runs were healed.
 */
function assets_discovery_heal_stuck_runs(PDO $db, int $grace_seconds = ASSETS_DISCOVERY_STUCK_GRACE_SECONDS): int
{
    $stmt = $db->prepare("
        SELECT `r`.`id`
        FROM `asset_discovery_runs` r
        WHERE `r`.`status` IN ('queued', 'running')
          AND `r`.`created_at` < (NOW() - INTERVAL ? SECOND)
          AND NOT EXISTS (
              SELECT 1 FROM `queue_tasks` q
              WHERE `q`.`task_type` = ?
                AND `q`.`status` IN ('pending', 'in_progress')
                AND CAST(JSON_EXTRACT(`q`.`payload`, '$.run_id') AS UNSIGNED) = `r`.`id`
          )
    ");
    $stmt->bindValue(1, max(0, $grace_seconds), PDO::PARAM_INT);
    $stmt->bindValue(2, ASSETS_DISCOVERY_TASK_TYPE, PDO::PARAM_STR);
    $stmt->execute();
    $healed = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $run_id) {
        if (assets_discovery_transition($db, $run_id, 'failed', ['error_text' => 'worker_lost'], true)) {
            write_debug_log("Asset discovery: run #{$run_id} has no live queue task; marking it failed.", 'warning');
            $healed++;
        }
    }
    return $healed;
}

/**
 * Inserts a queued run and enqueues its task. Returns the run id, or 0 when
 * the task could not be queued (the run row is removed again).
 *
 * @param array{range:string,resolve_names:bool,team_ids:int[],count:int,tcp_ports?:?string} $req  a validated request
 */
function assets_discovery_create_run(PDO $db, array $req, int $uid, bool $add_as_verified): int
{
    $stmt = $db->prepare("
        INSERT INTO `asset_discovery_runs` (`range_text`, `resolve_names`, `add_as_verified`, `team_ids`, `created_by`, `status`, `total_hosts`, `tcp_ports`, `created_at`)
        VALUES (?, ?, ?, ?, ?, 'queued', ?, ?, NOW())
    ");
    $tcp_ports = isset($req['tcp_ports']) && $req['tcp_ports'] !== '' ? (string)$req['tcp_ports'] : null;
    $stmt->execute([$req['range'], $req['resolve_names'] ? 1 : 0, $add_as_verified ? 1 : 0, implode(',', $req['team_ids']), $uid, (int)$req['count'], $tcp_ports]);
    $run_id = (int)$db->lastInsertId();

    if (!queue_task($db, ASSETS_DISCOVERY_TASK_TYPE, ['run_id' => $run_id], ASSETS_DISCOVERY_PRIORITY)) {
        $db->prepare("DELETE FROM `asset_discovery_runs` WHERE `id` = ?")->execute([$run_id]);
        return 0;
    }
    return $run_id;
}

/** The translated reason for a stored failure code (raw text; the client inserts it as text). */
function assets_discovery_error_text(string $code): string
{
    global $lang;
    $key = assets_discovery_error_lang_key($code);
    return (string)($lang[$key] ?? $key);
}

/**
 * API shape of a run (raw values; the client inserts them as text).
 *
 * @param array<string,mixed> $row a run row, optionally with `created_by_name`
 * @return array<string,mixed>
 */
function assets_discovery_run_payload(array $row): array
{
    global $lang;
    $dt = static fn($v) => $v ? (string)format_datetime($v, '', 'H:i') : '';
    $method = (string)($row['probe_method'] ?? '');
    $method_key = assets_discovery_probe_method_lang_key($method);
    return [
        'id' => (int)$row['id'],
        'range' => (string)$row['range_text'],
        'resolve_names' => (int)$row['resolve_names'] === 1,
        'add_as_verified' => (int)$row['add_as_verified'],
        'team_ids' => array_values(array_filter(array_map('intval', explode(',', (string)$row['team_ids'])))),
        'status' => (string)$row['status'],
        'total_hosts' => (int)$row['total_hosts'],
        'hosts_scanned' => (int)$row['hosts_scanned'],
        'live_hosts' => (int)$row['live_hosts'],
        'new_assets' => (int)$row['new_assets'],
        // The probe method the worker used ('' until the run starts) and the
        // run's TCP ports (per-run override, or the default a TCP run used).
        'probe_method' => $method,
        'probe_method_label' => $method_key !== '' ? (string)($lang[$method_key] ?? $method) : '',
        'tcp_ports' => (string)($row['tcp_ports'] ?? ''),
        // A translated reason for the stored code (never raw exception text).
        'error' => $row['status'] === 'failed' ? assets_discovery_error_text((string)($row['error_text'] ?? '')) : '',
        'created_by' => (int)$row['created_by'],
        'created_by_name' => (string)($row['created_by_name'] ?? ''),
        'created_at' => (string)$row['created_at'],
        'started_at' => (string)($row['started_at'] ?? ''),
        'finished_at' => (string)($row['finished_at'] ?? ''),
        'created_display' => $dt($row['created_at']),
        'started_display' => $dt($row['started_at'] ?? null),
        'finished_display' => $dt($row['finished_at'] ?? null),
    ];
}

/**
 * Recent runs the caller may see, newest first.
 *
 * @return array{runs:array<int,array<string,mixed>>,total:int,active:int,page:int,per_page:int}
 */
function assets_discovery_list_runs(PDO $db, int $uid, bool $is_admin, bool $separation, $page, $per_page): array
{
    $page = max(1, (int)$page);
    $per_page = (int)$per_page;
    $per_page = $per_page < 1 ? 10 : min($per_page, 50);

    $where = '';
    $params = [];
    if (!$is_admin && $separation) {
        $where = 'WHERE `r`.`created_by` = ?';
        $params[] = $uid;
    }

    $stmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(`r`.`status` IN ('queued', 'running')), 0) FROM `asset_discovery_runs` r {$where}");
    $stmt->execute($params);
    [$total, $active] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));

    $offset = ($page - 1) * $per_page;
    $stmt = $db->prepare("
        SELECT `r`.*, `u`.`name` AS created_by_name
        FROM `asset_discovery_runs` r
            LEFT JOIN `user` u ON `u`.`value` = `r`.`created_by`
        {$where}
        ORDER BY `r`.`id` DESC
        LIMIT {$per_page} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $runs = array_map('assets_discovery_run_payload', $stmt->fetchAll(PDO::FETCH_ASSOC));

    return ['runs' => $runs, 'total' => $total, 'active' => $active, 'page' => $page, 'per_page' => $per_page];
}

/**
 * Cancels a queued or running run. Returns false when the run is already
 * terminal (or gone). A running scan notices at its next checkpoint.
 */
function assets_discovery_cancel_run(PDO $db, int $run_id): bool
{
    return assets_discovery_transition($db, $run_id, 'cancelled', [], true);
}

/**
 * Audit-trail line for starting or cancelling a discovery run. A run is not
 * one asset, so it is logged against no entity (id 1000 -> stored 0, the
 * convention permissions.php uses for its user logs) under its own log type,
 * 'asset_discovery': with log type 'asset' it would appear in asset #1000's
 * own audit trail (write_log() stores that asset as 0 too).
 * _lang() escapes the parameters; do not pre-escape them.
 *
 * @param array<string,mixed> $run a run row (range_text, id, total_hosts)
 */
function assets_discovery_write_audit(string $lang_key, array $run): void
{
    $message = _lang($lang_key, [
        'id' => (int)($run['id'] ?? 0),
        'range' => (string)($run['range_text'] ?? ''),
        'count' => number_format((int)($run['total_hosts'] ?? 0)),
        'user' => $_SESSION['user'] ?? '',
    ]);
    write_log(1000, $_SESSION['uid'] ?? 0, $message, 'asset_discovery');
}

/** One run with its requester's name, or null. */
function assets_discovery_get_run_with_user(PDO $db, int $run_id): ?array
{
    $stmt = $db->prepare("SELECT `r`.*, `u`.`name` AS created_by_name FROM `asset_discovery_runs` r LEFT JOIN `user` u ON `u`.`value` = `r`.`created_by` WHERE `r`.`id` = ?");
    $stmt->execute([$run_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
