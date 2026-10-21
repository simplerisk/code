<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/** Dependency-free asset discovery rules: range parsing, the config.php allowlist, the run state machine and run visibility. Keep it that way: unit tests load this file alone. */

// The discovery probe helpers (port validator) -- equally dependency-free.
require_once(realpath(__DIR__ . '/assets_discovery_probe.php'));

function assets_parse_discovery_range(string $input, int $max = 65536): array
{
    $fail = fn(string $e) => ['ok' => false, 'error' => $e, 'start' => 0, 'end' => 0, 'count' => 0];
    $input = trim($input);
    $ip = fn(string $s) => filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? sprintf('%u', ip2long($s)) : null;

    if (preg_match('/^([0-9.]+)\/(\d{1,2})$/', $input, $m)) {
        $base = $ip($m[1]);
        $bits = (int)$m[2];
        if ($base === null || $bits > 32) return $fail('invalid');
        $count = 1 << (32 - $bits);
        if ($count > $max) return $fail('too_large');
        $start = ((int)$base) & (~($count - 1) & 0xFFFFFFFF);
        return ['ok' => true, 'error' => null, 'start' => $start, 'end' => $start + $count - 1, 'count' => $count];
    }
    if (preg_match('/^([0-9.]+)\s*-\s*([0-9.]+)$/', $input, $m)) {
        $s = $ip($m[1]); $e = $ip($m[2]);
        if ($s === null || $e === null || (int)$e < (int)$s) return $fail('invalid');
        $count = (int)$e - (int)$s + 1;
        if ($count > $max) return $fail('too_large');
        return ['ok' => true, 'error' => null, 'start' => (int)$s, 'end' => (int)$e, 'count' => $count];
    }
    $s = $ip($input);
    if ($s === null) return $fail('invalid');
    return ['ok' => true, 'error' => null, 'start' => (int)$s, 'end' => (int)$s, 'count' => 1];
}

/* =========================================================================
 * Background asset discovery (Task 11): constants and pure helpers. The
 * DB/queue side is includes/assets_discovery.php.
 * ========================================================================= */

/** Queue task type (jobs/index.php key and job file name). */
const ASSETS_DISCOVERY_TASK_TYPE = 'core_asset_discovery';
/** Hosts between progress checkpoints / cancellation checks. */
const ASSETS_DISCOVERY_CHUNK = 16;
/** Wall-clock seconds one queue task scans before handing over to a continuation. */
const ASSETS_DISCOVERY_SLICE_SECONDS = 120;
/** Queued + running runs one user may have at a time. */
const ASSETS_DISCOVERY_MAX_ACTIVE_PER_USER = 3;
/** Largest range a run may cover (assets_parse_discovery_range()'s limit). */
const ASSETS_DISCOVERY_MAX_HOSTS = 65536;
/**
 * Queue priority for runs AND their continuation slices: 0, the default most
 * queue work uses (core_workflow_execute, core_workflow_action_execute, ...).
 * The worker picks `ORDER BY priority DESC, created_at ASC`, so at 0 discovery
 * slices take turns with other default-priority tasks by age -- including the
 * asset.created workflow events a scan itself fires. Anything higher would
 * starve that work until every discovery run finished.
 */
const ASSETS_DISCOVERY_PRIORITY = 0;
/** Queued + running runs across the whole instance. */
const ASSETS_DISCOVERY_MAX_ACTIVE_TOTAL = 10;
/** A queued/running run with no live queue task this long after creation is healed to failed. */
const ASSETS_DISCOVERY_STUCK_GRACE_SECONDS = 300;

/**
 * IPv4 blocks discovery never scans (inclusive integer bounds): "this"
 * network 0.0.0.0/8, loopback 127.0.0.0/8 (every 127.x answers a local ping,
 * so a /16 would mint 65,536 junk assets), link-local 169.254.0.0/16
 * (including the 169.254.169.254 cloud metadata address), multicast
 * 224.0.0.0/4 and reserved 240.0.0.0/4 (including 255.255.255.255). Private
 * RFC 1918 space, 100.64.0.0/10 and public ranges stay allowed: discovering
 * an internal network is the feature.
 *
 * @return array<int,array{0:int,1:int}>
 */
function assets_discovery_reserved_blocks(): array
{
    return [
        [0x00000000, 0x00FFFFFF],   // 0.0.0.0/8
        [0x7F000000, 0x7FFFFFFF],   // 127.0.0.0/8
        [0xA9FE0000, 0xA9FEFFFF],   // 169.254.0.0/16
        [0xE0000000, 0xFFFFFFFF],   // 224.0.0.0/4 + 240.0.0.0/4
    ];
}

/** Whether [$start, $end] touches any reserved block. Pure. */
function assets_discovery_range_is_reserved(int $start, int $end): bool
{
    foreach (assets_discovery_reserved_blocks() as [$lo, $hi]) {
        if ($start <= $hi && $end >= $lo) {
            return true;
        }
    }
    return false;
}

/**
 * Parses the config.php allowlist of discovery targets
 * ($asset_discovery_allowed_ranges): a list of bare IPv4 addresses, IPv4
 * CIDR blocks and/or hostnames. Pure.
 *
 * Fail closed: anything that is not an array, or an array without one usable
 * IPv4 entry, is "not configured" and allows nothing. Hostname entries are
 * kept (normalised) but never authorize an address -- matching never
 * resolves DNS, and discovery ranges are IPv4 literals, so a hostname entry
 * cannot match one (the same no-DNS rule as the AI provider allowlist).
 * Anything else (IPv6, a start-end range, a bad prefix, a non-string) is
 * reported in `invalid` for the caller to log, and ignored.
 *
 * @param mixed $raw
 * `hint` says why a present list reads as not configured, for the admin log:
 * 'not_array' (set, but not an array) or 'no_usable_entries' (only hostnames
 * and/or unusable entries); null when absent, empty or configured.
 * Duplicate entries (also after normalising a CIDR) collapse to one.
 *
 * @return array{configured:bool,ranges:array<int,array{0:int,1:int}>,entries:string[],hosts:string[],invalid:string[],hint:?string}
 */
function assets_discovery_parse_allowlist($raw): array
{
    $out = ['configured' => false, 'ranges' => [], 'entries' => [], 'hosts' => [], 'invalid' => [], 'hint' => null];
    if (!is_array($raw)) {
        $out['hint'] = $raw === null ? null : 'not_array';
        return $out;
    }
    foreach ($raw as $entry) {
        if (!is_string($entry)) {
            $out['invalid'][] = is_scalar($entry) ? (string)$entry : '(' . gettype($entry) . ')';
            continue;
        }
        $e = trim($entry);
        if (filter_var($e, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $n = ip2long($e);
            if (!in_array($e, $out['entries'], true)) {
                $out['ranges'][] = [$n, $n];
                $out['entries'][] = $e;
            }
        } elseif (preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', $e, $m) && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (int)$m[2] <= 32) {
            $bits = (int)$m[2];
            $mask = $bits === 0 ? 0 : (0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF;
            $lo = ip2long($m[1]) & $mask;
            $label = long2ip($lo) . '/' . $bits;
            if (!in_array($label, $out['entries'], true)) {
                $out['ranges'][] = [$lo, $lo | (~$mask & 0xFFFFFFFF)];
                $out['entries'][] = $label;
            }
        } elseif (strlen($e) <= 253 && preg_match('/^(?=.*[a-z])(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.?$/i', $e)) {
            $out['hosts'][] = strtolower(rtrim($e, '.'));
        } else {
            $out['invalid'][] = $e;
        }
    }
    $out['configured'] = $out['ranges'] !== [];
    if (!$out['configured'] && $raw !== []) {
        $out['hint'] = 'no_usable_entries';
    }
    return $out;
}

/**
 * Whether every address in [$start, $end] lies inside the union of $ranges.
 * Interval arithmetic over the sorted entries (never enumerates addresses).
 * Pure.
 *
 * @param array<int,array{0:int,1:int}> $ranges
 */
function assets_discovery_range_within(int $start, int $end, array $ranges): bool
{
    usort($ranges, static fn($a, $b) => $a[0] <=> $b[0]);
    $cursor = $start;
    foreach ($ranges as [$lo, $hi]) {
        if ($hi < $cursor) {
            continue;
        }
        if ($lo > $cursor) {
            return false;   // sorted: nothing later covers $cursor
        }
        if ($hi >= $end) {
            return true;
        }
        $cursor = $hi + 1;
    }
    return false;
}

/**
 * The allowlist verdict for a (reserved-checked) range: null when allowed,
 * 'DiscoveryNotConfigured' when the allowlist has no usable entry, or
 * 'DiscoveryRangeNotAllowed' when some address falls outside it. Pure.
 *
 * @param array{configured:bool,ranges:array<int,array{0:int,1:int}>} $allow from assets_discovery_parse_allowlist()
 */
function assets_discovery_allowlist_error(int $start, int $end, array $allow): ?string
{
    if (empty($allow['configured'])) {
        return 'DiscoveryNotConfigured';
    }
    return assets_discovery_range_within($start, $end, $allow['ranges']) ? null : 'DiscoveryRangeNotAllowed';
}

/**
 * The full target check, in precedence order: the reserved-range rule first
 * (it wins even when the allowlist names a reserved block), then the
 * allowlist. Returns null or the lang-keyed error code. Pure.
 */
function assets_discovery_target_error(int $start, int $end, array $allow): ?string
{
    if (assets_discovery_range_is_reserved($start, $end)) {
        return 'DiscoveryRangeReserved';
    }
    return assets_discovery_allowlist_error($start, $end, $allow);
}

/** The run failure code (error_text) for a target error from assets_discovery_target_error(). */
function assets_discovery_allowlist_run_code(string $error): string
{
    static $map = ['DiscoveryNotConfigured' => 'not_configured', 'DiscoveryRangeNotAllowed' => 'range_not_allowed'];
    return $map[$error] ?? 'invalid_range';
}

/**
 * The language key for a run's stored failure code (error_text holds a short
 * code, never raw exception text). Unknown codes read as a generic scan error.
 */
function assets_discovery_error_lang_key(string $code): string
{
    static $map = [
        'scan_error' => 'DiscoveryErrorScan',
        'worker_lost' => 'DiscoveryErrorWorkerLost',
        'requester_inactive' => 'DiscoveryErrorRequesterInactive',
        'requester_not_permitted' => 'DiscoveryErrorRequesterNotPermitted',
        'invalid_range' => 'DiscoveryRangeInvalid',
        'probe_unavailable' => 'DiscoveryErrorProbeUnavailable',
        'tcp_probe_unreliable' => 'DiscoveryErrorTcpUnreliable',
        'not_configured' => 'DiscoveryNotConfigured',
        'range_not_allowed' => 'DiscoveryRangeNotAllowed',
    ];
    return $map[$code] ?? 'DiscoveryErrorScan';
}

/**
 * Status transitions a run may make. Terminal states (completed, failed,
 * cancelled) have no outgoing edges, so they are immutable.
 *
 *   queued  -> running    the job picked the run up
 *   queued  -> cancelled  cancelled before it started
 *   queued  -> failed     retries exhausted (or the run cannot be processed)
 *   running -> running    a continuation slice or a retry resumes the scan
 *   running -> queued     an attempt threw; waiting for the worker's retry
 *   running -> completed | failed | cancelled
 *
 * @return array<string,string[]>
 */
function assets_discovery_transitions(): array
{
    return [
        'queued' => ['running', 'cancelled', 'failed'],
        'running' => ['running', 'queued', 'completed', 'failed', 'cancelled'],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ];
}

function assets_discovery_can_transition(string $from, string $to): bool
{
    return in_array($to, assets_discovery_transitions()[$from] ?? [], true);
}

/**
 * The states a run may move to $to from (the WHERE status IN (...) list of
 * the guarded UPDATE that performs the move).
 *
 * @return string[]
 */
function assets_discovery_sources_for(string $to): array
{
    $from = [];
    foreach (assets_discovery_transitions() as $state => $targets) {
        if (in_array($to, $targets, true)) {
            $from[] = $state;
        }
    }
    return $from;
}

function assets_discovery_is_terminal(string $status): bool
{
    return in_array($status, ['completed', 'failed', 'cancelled'], true);
}

/**
 * Validates a POST /assets/discovery-runs body. Pure.
 *
 * Only `range`, `resolve_names`, `team_ids` and `tcp_ports` (an optional
 * comma-separated port list for the TCP probe; blank = the admin's default)
 * are read. Anything else --
 * notably `add_as_verified` / `verified` -- is ignored: the verified state of
 * discovered assets is decided at enqueue from the requester's permissions.
 *
 * @param array<string,mixed> $body
 * @return array{ok:bool,status:int,error:?string,range:string,start:int,end:int,count:int,resolve_names:bool,team_ids:int[],tcp_ports:?string}
 */
function assets_discovery_validate_request(array $body): array
{
    $out = ['ok' => false, 'status' => 422, 'error' => null, 'range' => '', 'start' => 0, 'end' => 0, 'count' => 0, 'resolve_names' => true, 'team_ids' => [], 'tcp_ports' => null];

    $range = $body['range'] ?? null;
    if (!is_string($range) || trim($range) === '' || strlen($range) > 100) {
        $out['error'] = 'DiscoveryRangeInvalid';
        return $out;
    }
    $range = trim($range);
    $parsed = assets_parse_discovery_range($range, ASSETS_DISCOVERY_MAX_HOSTS);
    if (!$parsed['ok']) {
        $out['error'] = $parsed['error'] === 'too_large' ? 'DiscoveryRangeTooLarge' : 'DiscoveryRangeInvalid';
        return $out;
    }
    if (assets_discovery_range_is_reserved((int)$parsed['start'], (int)$parsed['end'])) {
        $out['error'] = 'DiscoveryRangeReserved';
        return $out;
    }

    $resolve = $body['resolve_names'] ?? true;
    if (is_string($resolve)) {
        $resolve = strtolower(trim($resolve));
        if (in_array($resolve, ['1', 'true', 'on', 'yes'], true)) {
            $resolve = true;
        } elseif (in_array($resolve, ['0', 'false', 'off', 'no', ''], true)) {
            $resolve = false;
        }
    } elseif (is_int($resolve) && ($resolve === 0 || $resolve === 1)) {
        $resolve = (bool)$resolve;
    }
    if (!is_bool($resolve)) {
        $out['error'] = 'DiscoveryResolveNamesInvalid';
        return $out;
    }

    $teams = $body['team_ids'] ?? [];
    if ($teams === null || $teams === '') {
        $teams = [];
    }
    if (!is_array($teams) || count($teams) > 500) {
        $out['error'] = 'DiscoveryTeamsInvalid';
        return $out;
    }
    $team_ids = [];
    foreach ($teams as $t) {
        if (is_int($t) || (is_string($t) && ctype_digit($t))) {
            $t = (int)$t;
            if ($t > 0) {
                $team_ids[$t] = $t;
                continue;
            }
        }
        $out['error'] = 'DiscoveryTeamsInvalid';
        return $out;
    }

    // Optional per-run TCP ports (stored normalised; used only when the run
    // ends up probing with TCP connects).
    $ports = $body['tcp_ports'] ?? null;
    $tcp_ports = null;
    if ($ports !== null && !(is_string($ports) && trim($ports) === '')) {
        $checked = is_string($ports) ? assets_discovery_validate_tcp_ports($ports) : ['ok' => false];
        if (!$checked['ok']) {
            $out['error'] = 'DiscoveryPortsInvalid';
            return $out;
        }
        $tcp_ports = assets_discovery_tcp_ports_csv($checked['ports']);
    }

    return [
        'ok' => true, 'status' => 201, 'error' => null,
        'range' => $range, 'start' => $parsed['start'], 'end' => $parsed['end'], 'count' => $parsed['count'],
        'resolve_names' => $resolve, 'team_ids' => array_values($team_ids), 'tcp_ports' => $tcp_ports,
    ];
}

/**
 * The name a discovered host is stored under. Pure.
 *
 * A reverse lookup answer is attacker-influenced (whoever controls the PTR
 * record), so it is used only when it looks like a host name; otherwise, and
 * when there is no answer or the lookup echoed the address back, the name is
 * the IP address itself (the legacy discovery's behaviour).
 */
function assets_discovery_asset_name(string $ip, $resolved): string
{
    if (!is_string($resolved)) {
        return $ip;
    }
    $resolved = rtrim(trim($resolved), '.');
    if ($resolved === '' || $resolved === $ip || strlen($resolved) > 200
        || !preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9_.-]*[A-Za-z0-9])?$/', $resolved)) {
        return $ip;
    }
    return $resolved;
}

/**
 * Whether the caller may see a run: admins and (without Team Separation)
 * every discovery user see all runs; a non-admin under Team Separation sees
 * only their own.
 */
function assets_discovery_run_visible(array $run, int $uid, bool $is_admin, bool $separation): bool
{
    return $is_admin || !$separation || (int)$run['created_by'] === $uid;
}

/** Only the requester or an admin may cancel a run. */
function assets_discovery_run_cancellable_by(array $run, int $uid, bool $is_admin): bool
{
    return $is_admin || (int)$run['created_by'] === $uid;
}
