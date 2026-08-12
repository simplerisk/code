<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * SimpleRisk licensing client.
 *
 * Talks to the SimpleRisk licensing service (default
 * https://licensing.simplerisk.com) via four endpoints:
 *   POST /register, POST /instance/update,
 *   POST /license/check, POST /download-extra
 *
 * Define LICENSING_URL in config.php to point at a non-production
 * licensing service (test deploys do this via
 * scripts/bundles-test-installation.sh).
 */

/**
 * Resolve the licensing-service base URL plus an optional path suffix.
 * Pure helper — no side effects.
 *
 * @param string $path Path suffix beginning with '/' (or '' for the bare base).
 */
function licensing_url(string $path = ''): string
{
    $base = defined('LICENSING_URL') ? LICENSING_URL : 'https://licensing.simplerisk.com';
    return $base . $path;
}

/**
 * Test seam — pure tests inject a fake cache value via this setter
 * without going through the DB. In production, get_cached_license_entries()
 * reads from settings.license_check_response via get_setting().
 *
 * @internal
 */
function licensing_set_test_cache(?string $raw_json): void
{
    if (!defined('SIMPLERISK_PHPUNIT_RUNNING')) {
        throw new \LogicException(
            'licensing_set_test_cache() must not be called outside of PHPUnit; '
            . 'it mutes the production licensing cache and is intended only for tests.'
        );
    }
    $GLOBALS['__licensing_test_cache'] = $raw_json;
}

/**
 * @internal — used by get_cached_license_entries() / _enforcement_level()
 * so tests can swap the source. Production path reads get_setting().
 */
function licensing_read_cache_raw(): ?string
{
    if (array_key_exists('__licensing_test_cache', $GLOBALS)) {
        return $GLOBALS['__licensing_test_cache'];
    }
    // Read uncached ($cached = false). license_check() rewrites this row in place
    // after a /license/check refresh, and callers must see the updated value
    // within the same request. get_setting()'s default $GLOBALS cache would
    // otherwise pin a copy read earlier in the request — and not every writer
    // refreshes that global (e.g. reset_registration deletes the row with raw
    // SQL). Entitlement reads are infrequent and correctness-critical, so always
    // hit the DB rather than trust the per-request cache.
    $value = function_exists('get_setting') ? get_setting('license_check_response', false, false) : false;
    return $value === false ? null : (string)$value;
}

/**
 * Return the cached entries[] from the most recent /license/check response,
 * filtered to only the Extras Core knows about (available_extra_short_names()).
 * Returns [] when the cache is missing.
 *
 * The forward-compat filter at this boundary means downstream callers
 * iterating entries[] never see server-only items (e.g. "management").
 */
function get_cached_license_entries(): array
{
    // Lazy-load extras.php here (not at module level) so that licensing.php
    // can be included in contexts where extras.php's full include chain
    // (bootstrap → config → functions → services → extras) is not yet
    // available (e.g. the installer, early bootstrap).
    require_once(realpath(__DIR__ . '/extras.php'));

    $raw = licensing_read_cache_raw();
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['entries']) || !is_array($decoded['entries'])) {
        return [];
    }
    $known = function_exists('available_extra_short_names')
        ? available_extra_short_names()
        : [];
    return array_values(array_filter(
        $decoded['entries'],
        fn($e) => is_array($e)
            && isset($e['extra_name'])
            && in_array($e['extra_name'], $known, true)
    ));
}

/**
 * Return the enforcement_level from the most recent /license/check response.
 * Returns 'unknown' when the cache is missing or malformed.
 *
 * Known values: 'normal', 'lock_extras', 'remove_extras', 'anonymous'.
 */
function get_cached_enforcement_level(): string
{
    $raw = licensing_read_cache_raw();
    if ($raw === null || $raw === '') {
        return 'unknown';
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['enforcement_level'])) {
        return 'unknown';
    }
    $level = (string)$decoded['enforcement_level'];
    // Defense-in-depth: normalize unrecognized values to 'unknown' on
    // the read path, matching what parse_license_check_response() does
    // on the write path. Protects against test seams, future migrations,
    // or partial cache writes that bypass the parser.
    $known = ['normal', 'lock_extras', 'remove_extras', 'anonymous'];
    return in_array($level, $known, true) ? $level : 'unknown';
}

/**
 * True iff a license_check_daily() / license_check() result represents a
 * refresh that actually landed — i.e. the licensing service returned a
 * server-authoritative enforcement level and the local cache was rewritten.
 *
 * On a transport failure or non-200, license_check() deliberately leaves the
 * prior cache untouched and returns parse_license_check_response('') defaults,
 * whose enforcement_level is 'unknown'. Callers (e.g. the admin "refresh now"
 * endpoint) use this to distinguish "cache updated" from "nothing happened,
 * try again" rather than reporting a false success. Pure helper.
 *
 * @param mixed $result The return value of license_check_daily().
 */
function license_refresh_landed($result): bool
{
    $level = is_array($result) ? ($result['enforcement_level'] ?? 'unknown') : 'unknown';
    return in_array($level, ['normal', 'lock_extras', 'remove_extras', 'anonymous'], true);
}

/**
 * Classify a single cached license entry for display:
 *   'licensed'   — effective for this instance (active paid OR free Extra).
 *   'expired'    — has a license record but is not currently effective.
 *   'unlicensed' — no entry, or an entry with no license record.
 * Pure helper. Accepts null for "no entry exists".
 */
function classify_license_entry(?array $entry): string
{
    if ($entry !== null && ($entry['effective'] ?? false) === true) {
        return 'licensed';
    }
    if ($entry !== null && isset($entry['license']) && is_array($entry['license'])) {
        return 'expired';
    }
    return 'unlicensed';
}

/**
 * Build the per-Extra license overview the Licenses page renders. Joins the
 * list of available Extras with the cached license entries and the resolved
 * (already-localized) description strings. Pure helper — no DB, no network.
 *
 * @param array $available_extras  available_extras() — [{short_name, long_name}, ...]
 * @param array $cached_entries    get_cached_license_entries() — license cache rows
 * @param array $descriptions      [short_name => localized description string]
 * @return array  one row per available Extra:
 *   [short_name, name, description, classification, is_free, status, start_date, end_date]
 */
function build_license_overview(array $available_extras, array $cached_entries, array $descriptions): array
{
    $by_name = [];
    foreach ($cached_entries as $entry) {
        if (isset($entry['extra_name'])) {
            $by_name[$entry['extra_name']] = $entry;
        }
    }

    $overview = [];
    foreach ($available_extras as $extra) {
        $short_name = $extra['short_name'] ?? '';
        if ($short_name === '') {
            continue;
        }
        $entry   = $by_name[$short_name] ?? null;
        $license = (is_array($entry) && isset($entry['license']) && is_array($entry['license']))
            ? $entry['license'] : null;

        $overview[] = [
            'short_name'     => $short_name,
            'name'           => $extra['long_name'] ?? $short_name,
            'description'    => $descriptions[$short_name] ?? ($extra['long_name'] ?? ''),
            'classification' => classify_license_entry($entry),
            'is_free'        => is_array($entry) ? (bool)($entry['is_free'] ?? false) : false,
            'status'         => $license['status'] ?? null,
            'start_date'     => $license['start_date'] ?? null,
            'end_date'       => $license['end_date'] ?? null,
        ];
    }
    return $overview;
}

/**
 * True iff the licensing service's last-seen response considers this Extra
 * licensed/effective for this instance. Accepts the filesystem-dir name
 * (the only namespace SimpleRisk uses on the wire and on disk).
 * Returns false for any unknown name.
 */
function get_effective_for_extra(string $extra_name): bool
{
    foreach (get_cached_license_entries() as $entry) {
        if (($entry['extra_name'] ?? null) === $extra_name) {
            return (bool)($entry['effective'] ?? false);
        }
    }
    return false;
}

/**
 * The current published version of this Extra, per the most recent
 * /license/check response. Present only for Extras the licensing service
 * returns (licensed + free); returns null for anything else (e.g. unlicensed
 * Extras, which have no cache entry), so callers fall back to the
 * releases.xml fetch via latest_version(). Accepts the Core-canonical short
 * name — the same namespace get_cached_license_entries() filters to.
 */
function get_current_version_for_extra(string $extra_name): ?string
{
    foreach (get_cached_license_entries() as $entry) {
        if (($entry['extra_name'] ?? null) === $extra_name) {
            return $entry['current_version'] ?? null;
        }
    }
    return null;
}

/**
 * The expected SHA-256 of this Extra's download package, per the most recent
 * /license/check response. Present only for entitled Extras (the only ones you
 * can download); returns null for anything else. Used to verify a /download-extra
 * payload before it is installed.
 */
function get_download_sha256_for_extra(string $extra_name): ?string
{
    foreach (get_cached_license_entries() as $entry) {
        if (($entry['extra_name'] ?? null) === $extra_name) {
            return $entry['download_sha256'] ?? null;
        }
    }
    return null;
}

/**
 * Map a license end date to the single currently-due expiration notification
 * event: '90','60','45','30','15','10','7','3','2','1', 'expired', or null
 * (91 or more days remaining, or an unparseable date). Pure helper.
 *
 * The 90-day window is inclusive of ~91 days: the guard is
 * `remaining < (threshold + 1) * 86400`, so '90' fires while remaining is
 * under 91 days and null is returned only at 91 days or more.
 *
 * "Most-recent crossed only": returns the smallest threshold T (in days) whose
 * window the license currently sits in, so a license first seen at 50 days left
 * yields '60' (never '90'), and a daily run emits each threshold once as the
 * remaining days cross it.
 */
function license_notification_event(string $end_date, int $now_ts): ?string
{
    $end_ts = strtotime($end_date);
    if ($end_ts === false) {
        return null;
    }
    if ($now_ts >= $end_ts) {
        return 'expired';
    }
    $remaining = $end_ts - $now_ts;
    foreach ([1, 2, 3, 7, 10, 15, 30, 45, 60, 90] as $threshold) {
        if ($remaining < ($threshold + 1) * 86400) {
            return (string)$threshold;
        }
    }
    return null;
}

/**
 * Queue license-expiration notifications for all admins, based on the cached
 * /license/check entries. For each entry with a paid license (a `license`
 * object with an `end_date`), fire one notification for the currently-due
 * threshold event (see license_notification_event()), keyed by a deterministic
 * external_guid so each (license, threshold) fires exactly once. Free/Unlimited
 * Extras (no `license`) are skipped. A notification failure is logged and never
 * propagated — this runs off the back of the license-check job.
 */
function queue_license_expiration_notifications(?PDO $db = null): void
{
    require_once(realpath(__DIR__ . '/notifications.php'));
    require_once(realpath(__DIR__ . '/extras.php'));

    // short_name => long_name for the display label.
    $labels = [];
    foreach (available_extras() as $extra) {
        $labels[$extra['short_name']] = $extra['long_name'];
    }

    // Absolute link to the Licenses page from simplerisk_base_url. If it isn't a
    // safe http(s) link, send the notification without a link rather than have
    // create_notification() reject the whole thing.
    $base_url = function_exists('get_setting') ? (string)get_setting('simplerisk_base_url') : '';
    $link = $base_url !== '' ? rtrim($base_url, '/') . '/admin/licenses.php' : null;
    if ($link !== null && !is_safe_notification_link($link)) {
        $link = null;
    }

    $now = time();

    foreach (get_cached_license_entries() as $entry) {
        $license = $entry['license'] ?? null;
        if (!is_array($license)) {
            continue; // free/Unlimited Extras have no license object
        }
        $end_date = (string)($license['end_date'] ?? '');
        if ($end_date === '') {
            continue;
        }

        $event = license_notification_event($end_date, $now);
        if ($event === null) {
            continue; // nothing due (>90 days out or unparseable)
        }

        $extra_name = (string)($entry['extra_name'] ?? '');
        $label      = $labels[$extra_name] ?? $extra_name;
        $license_id = (string)($license['license_id'] ?? $end_date);
        $guid       = "license:{$license_id}:{$event}";

        if ($event === 'expired') {
            $title = _lang_raw('LicenseExpiredTitle', ['extra' => $label]);
            $body  = _lang_raw('LicenseExpiredBody', ['extra' => $label, 'date' => $end_date]);
        } else {
            $end_ts = strtotime($end_date);
            $days   = $end_ts !== false ? (int)ceil(($end_ts - $now) / 86400) : (int)$event;
            $title  = _lang_raw('LicenseExpiringSoonTitle', ['extra' => $label]);
            $body   = _lang_raw('LicenseExpiringSoonBody', ['extra' => $label, 'date' => $end_date, 'days' => $days]);
        }

        try {
            create_notification(
                source:        'license',
                title:         $title,
                body:          $body,
                link:          $link,
                audience_type: 'all_admin',
                audience_id:   null,
                created_by:    null,
                expires_at:    null,
                external_guid: $guid,
                db:            $db
            );
        } catch (\Throwable $e) {
            if (function_exists('write_debug_log')) {
                write_debug_log("queue_license_expiration_notifications: failed for '{$extra_name}' ({$event}): " . $e->getMessage(), 'warning');
            }
        }
    }
}

/**
 * True iff a downloaded Extra package is acceptable to install: either there is
 * no expected SHA-256 to check against (forward-compat / older server — skip
 * rather than block), or the bytes hash to exactly the expected value. Pure
 * helper so the integrity decision is unit-testable without a network fetch.
 */
function extra_download_is_intact(?string $expected_sha256, string $bytes): bool
{
    if ($expected_sha256 === null || $expected_sha256 === '') {
        return true;
    }
    // hash('sha256', …) returns lowercase hex; lowercase the expected value too so a
    // server-advertised uppercase/mixed-case SHA-256 verifies identically here and in
    // the Upgrade Extra's twin (upgrade_download_is_intact), which already lowercases.
    return hash_equals(strtolower($expected_sha256), hash('sha256', $bytes));
}

/**
 * Parse a /license/check response body. Pure helper. Returns a normalized
 * structure with safe defaults on malformed input:
 *   ['enforcement_level' => 'unknown'|'normal'|'lock_extras'|'remove_extras'|'anonymous',
 *    'entries' => [...],
 *    'mode' => 'authenticated'|'anonymous'|'unknown',
 *    'ping_processed' => bool]
 *
 * Unknown enforcement_level values normalize to 'unknown' so the caller
 * doesn't act on a value it can't interpret.
 */
function parse_license_check_response(string $body): array
{
    $valid_levels = ['normal', 'lock_extras', 'remove_extras', 'anonymous'];
    $valid_modes  = ['authenticated', 'anonymous'];

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return [
            'enforcement_level' => 'unknown',
            'entries' => [],
            'mode' => 'unknown',
            'ping_processed' => false,
        ];
    }

    $level = $decoded['enforcement_level'] ?? 'unknown';
    if (!in_array($level, $valid_levels, true)) {
        $level = 'unknown';
    }

    $mode = $decoded['mode'] ?? 'unknown';
    if (!in_array($mode, $valid_modes, true)) {
        $mode = 'unknown';
    }

    $entries = $decoded['entries'] ?? [];
    if (!is_array($entries)) {
        $entries = [];
    }

    return [
        'enforcement_level' => $level,
        'entries' => $entries,
        'mode' => $mode,
        'ping_processed' => !empty($decoded['ping_processed']),
    ];
}

/**
 * Build the per-Extra subobject of the /license/check payload. Pure helper.
 *
 * Every name in $extra_names gets an entry, unconditionally. That is the whole
 * point of this helper: the payload is derived from available_extra_short_names()
 * rather than from a second, hand-maintained list of Extras. The legacy flat ping
 * this replaced enumerated each Extra's three fields by hand, and the Workflows
 * Extra shipped without ever being added to it — so it reported as absent on every
 * instance running that code. Deriving the list makes that failure unrepresentable.
 *
 * installed/enabled are cast to real booleans because the licensing service
 * coerces them with a STRICT in_array against [1, '1', true, 'true', 'yes'].
 * A truthy-but-unlisted value ('TRUE', 'Yes', 'y', 1.0, '01') silently reads
 * as false there, so a loose value here would be dropped rather than rejected.
 *
 * @param string[] $extra_names  Extra short names, i.e. available_extra_short_names().
 * @param callable $is_installed fn(string $name): bool  — Extra present on disk.
 * @param callable $is_enabled   fn(string $name): bool  — Extra activated.
 * @param callable $version_of   fn(string $name): ?string — called only when installed.
 * @return array<string, array{installed: bool, enabled: bool, version: ?string}>
 */
function build_license_check_extras(
    array $extra_names,
    callable $is_installed,
    callable $is_enabled,
    callable $version_of
): array {
    $extras = [];
    foreach ($extra_names as $name) {
        $installed = (bool)$is_installed($name);
        $extras[$name] = [
            'installed' => $installed,
            'enabled'   => (bool)$is_enabled($name),
            // Not installed means there is no version to report. null clears the
            // corresponding property on the licensing side rather than pinning it
            // to a stale value from a previous check-in.
            'version'   => $installed ? $version_of($name) : null,
        ];
    }
    return $extras;
}

/**
 * Assemble the JSON body for POST /license/check. Pure helper.
 * Passes services_api_key only when non-null (anonymous installs omit it).
 *
 * @param array<string, mixed> $metadata The 'metadata' subobject (app_version,
 *     db_version, timezone, risks, users, last_login, metrics).
 * @param array<string, array<string, mixed>> $extras Map of filesystem-dir
 *     name => {installed, enabled, version}.
 */
function build_license_check_payload(
    string $instance_id,
    ?string $services_api_key,
    array $metadata,
    array $extras
): array {
    $payload = ['instance_id' => $instance_id];
    if ($services_api_key !== null && $services_api_key !== '') {
        $payload['services_api_key'] = $services_api_key;
    }
    $payload['metadata'] = $metadata;
    $payload['extras']   = $extras;
    return $payload;
}

/**
 * Make a POST /license/check request and persist the parsed response into
 * settings.license_check_response. Returns the parsed response array (the
 * shape produced by parse_license_check_response()).
 *
 * Side-effecting wrapper — pure logic is in the builder + parser.
 *
 * @param string                            $instance_id      Local instance id.
 * @param ?string                           $services_api_key Local api key or null for anonymous.
 * @param array<string, mixed>              $metadata
 * @param array<string, array<string, mixed>> $extras
 */
function license_check(
    string $instance_id,
    ?string $services_api_key,
    array $metadata,
    array $extras
): array {
    $payload = build_license_check_payload($instance_id, $services_api_key, $metadata, $extras);

    $http_options = [
        'method' => 'POST',
        'header' => ['Content-Type: application/json'],
        // JSON_INVALID_UTF8_SUBSTITUTE: a stray non-UTF-8 byte in a field must not make
        // json_encode() return false (which would send an empty body and surface as an
        // indistinguishable transport failure). Substitute the bad byte and send the request.
        'content' => json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE),
        'timeout' => 5,
    ];

    $validate_ssl = (!function_exists('ssl_external_verify_enabled') || ssl_external_verify_enabled());

    $response = fetch_url_content(
        'curl', $http_options, $validate_ssl, licensing_url('/license/check'), null
    );

    if (!is_array($response) || ($response['return_code'] ?? 0) !== 200) {
        if (function_exists('write_debug_log')) {
            $code = is_array($response) ? ($response['return_code'] ?? 0) : 0;
            write_debug_log(
                "license_check: non-200 HTTP from {$payload['instance_id']} ({$code}) — keeping prior cache",
                'warning'
            );
        }
        // Do NOT overwrite the cache. A transient network timeout or
        // 5-second connection failure must not wipe 24 hours of good
        // entitlements. The prior cache (if any) stays in force until
        // the next successful /license/check response.
        //
        // We still call apply_enforcement_level() so the session flags
        // reflect the last-known cached state rather than whatever value
        // they happened to hold before this request.
        apply_enforcement_level();
        return parse_license_check_response('');
    }

    $body   = (string)($response['response'] ?? '');
    $parsed = parse_license_check_response($body);

    // Persist a normalized subset of the response so the cache shape is
    // stable: enforcement_level, entries, mode, plus a checked_at
    // timestamp the cron uses to gate "next refresh due" decisions.
    // Server-added fields we don't yet parse are intentionally dropped
    // here; if future code needs them, extend parse_license_check_response()
    // and store the additional fields in this same cache row.
    $cache_value = json_encode([
        'enforcement_level' => $parsed['enforcement_level'],
        'entries'           => $parsed['entries'],
        'mode'              => $parsed['mode'],
        'checked_at'        => date('Y-m-d H:i:s'),
    ]);
    if (function_exists('update_or_insert_setting')) {
        update_or_insert_setting('license_check_response', $cache_value);
    }
    apply_enforcement_level();

    return $parsed;
}

/**
 * Parse a /download-extra 4xx JSON error envelope. Pure helper.
 *
 * Returns:
 *   ['error' => 'unauthorized'|'not_licensed'|'invalid_extra_name'|'rate_limited'|'unknown',
 *    'extra_name' => string|null,
 *    'reason' => string|null,
 *    'retry_after_seconds' => int|null]
 */
function parse_download_extra_error(int $http_status, string $body): array
{
    $out = [
        'error' => 'unknown',
        'extra_name' => null,
        'reason' => null,
        'retry_after_seconds' => null,
        'http_status' => $http_status,
    ];
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || !isset($decoded['error'])) {
        return $out;
    }
    $known = ['unauthorized', 'not_licensed', 'invalid_extra_name', 'rate_limited'];
    if (in_array($decoded['error'], $known, true)) {
        $out['error'] = $decoded['error'];
    }
    if (isset($decoded['extra_name'])) {
        $out['extra_name'] = (string)$decoded['extra_name'];
    }
    if (isset($decoded['reason'])) {
        $out['reason'] = (string)$decoded['reason'];
    }
    if (isset($decoded['retry_after_seconds']) && is_numeric($decoded['retry_after_seconds'])) {
        $out['retry_after_seconds'] = (int)$decoded['retry_after_seconds'];
    }
    return $out;
}

/**
 * POST /register with 409-collision retry. Returns a result envelope:
 *   ['ok' => bool, 'instance_id' => string|null,
 *    'services_api_key' => string|null,
 *    'error' => string|null]
 *
 * The $transport callable lets the unit tests inject a stubbed HTTP layer:
 *   fn(array $payload): array{return_code:int, response:string}
 *
 * The instance_id is available inside $payload['instance_id']; a separate
 * leading parameter is unnecessary and was removed to match licensing_instance_update().
 *
 * Production callers pass null for $transport and the helper falls back to
 * fetch_url_content() against licensing_url('/register').
 *
 * @param int $max_retries Cap on attempts (default 3).
 */
function licensing_register_with_retry(
    array $registrant_fields,
    ?callable $transport = null,
    int $max_retries = 3
): array {
    if ($transport === null) {
        $transport = function (array $payload): array {
            $validate_ssl = (!function_exists('ssl_external_verify_enabled') || ssl_external_verify_enabled());
            $r = fetch_url_content('curl', [
                'method' => 'POST',
                'header' => ['Content-Type: application/json'],
                // Substitute invalid UTF-8 (e.g. a stray byte in a registrant field) rather
                // than letting json_encode() return false and send an empty body.
                'content' => json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE),
                'timeout' => 5,
            ], $validate_ssl, licensing_url('/register'), null);
            return is_array($r) ? $r : ['return_code' => 0, 'response' => ''];
        };
    }

    $last_error = null;
    for ($i = 0; $i < $max_retries; $i++) {
        $instance_id = function_exists('generate_token') ? generate_token(50) : bin2hex(random_bytes(25));
        $payload = array_merge(['instance_id' => $instance_id], $registrant_fields);
        $response = $transport($payload);
        $code = $response['return_code'] ?? 0;
        $body = $response['response'] ?? '';

        if ($code === 200) {
            $decoded = json_decode($body, true);
            return [
                'ok' => true,
                'instance_id' => $decoded['instance_id'] ?? $instance_id,
                'services_api_key' => $decoded['services_api_key'] ?? null,
                'error' => null,
            ];
        }
        if ($code === 409) {
            $last_error = 'instance_id_in_use';
            continue; // regenerate id, retry
        }
        // Any other code: stop and surface
        $decoded = json_decode($body, true);
        return [
            'ok' => false,
            'instance_id' => null,
            'services_api_key' => null,
            'error' => is_array($decoded) ? ($decoded['error'] ?? 'unknown') : 'transport',
        ];
    }
    return ['ok' => false, 'instance_id' => null, 'services_api_key' => null, 'error' => $last_error];
}

/**
 * Apply the cached enforcement_level to the session so existing UI that
 * reads $_SESSION['license_check'] reflects the licensing service's current
 * policy.
 *
 * Server-authoritative semantics: the cache's enforcement_level is the
 * truth. No client-side grace period or auto-deactivation.
 *
 * Session-flag mapping:
 *   enforcement_level → $_SESSION['license_check']
 *   'normal'          → 'pass'
 *   'lock_extras'     → 'lock'
 *   'remove_extras'   → 'fail'
 *   'anonymous'       → 'anonymous'
 *   (anything else)   → 'unknown'
 *
 * Call this from any path that needs the session flag fresh (login,
 * post-registration warm, the daily license-check job — the cache write site).
 *
 * Note: this no longer sets $_SESSION['support']. Phone-support entitlement is
 * now a paid-Extra question answered at render time by has_paid_extra()
 * (extras.php), not a function of enforcement_level.
 */
function apply_enforcement_level(): void
{
    // No-op when called outside an active HTTP session (e.g. cron-job
    // context). Writing to $_SESSION without an active session produces
    // a PHP notice and may create orphan session files under the session
    // save path. The session flag is only meaningful for interactive
    // request paths, so we bail early here.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $level = get_cached_enforcement_level();
    switch ($level) {
        case 'normal':
            $_SESSION['license_check'] = 'pass';
            break;
        case 'lock_extras':
            $_SESSION['license_check'] = 'lock';
            break;
        case 'remove_extras':
            $_SESSION['license_check'] = 'fail';
            break;
        case 'anonymous':
            $_SESSION['license_check'] = 'anonymous';
            break;
        default:
            $_SESSION['license_check'] = 'unknown';
            break;
    }
}

/**
 * POST /instance/update to refresh the registrant identity (name, email,
 * company, etc.) on an already-registered instance. Returns a result
 * envelope:
 *   ['ok' => bool, 'error' => string|null]
 *
 * The $transport callable lets unit tests inject a stubbed HTTP layer:
 *   fn(array $payload): array{return_code:int, response:string}
 *
 * Production callers pass null for $transport and the helper falls back
 * to fetch_url_content() against licensing_url('/instance/update').
 *
 * @param array<string, mixed> $payload Must include instance_id and
 *     services_api_key for auth. Caller is responsible for populating
 *     these (they're stored locally, not derived here).
 */
function licensing_instance_update(array $payload, ?callable $transport = null): array
{
    if ($transport === null) {
        $transport = function (array $body): array {
            $validate_ssl = (!function_exists('ssl_external_verify_enabled') || ssl_external_verify_enabled());
            $r = fetch_url_content('curl', [
                'method'  => 'POST',
                'header'  => ['Content-Type: application/json'],
                // Substitute invalid UTF-8 rather than letting json_encode() return false
                // and send an empty body on a bad byte in the instance-update payload.
                'content' => json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE),
                'timeout' => 5,
            ], $validate_ssl, licensing_url('/instance/update'), null);
            return is_array($r) ? $r : ['return_code' => 0, 'response' => ''];
        };
    }

    $response = $transport($payload);
    $code = $response['return_code'] ?? 0;

    if ($code === 200) {
        return ['ok' => true, 'error' => null];
    }

    $decoded = json_decode($response['response'] ?? '', true);
    return [
        'ok' => false,
        'error' => is_array($decoded) ? ($decoded['error'] ?? 'unknown') : 'transport',
    ];
}

/**
 * Clear the test cache seam, restoring production-path behavior on
 * subsequent reads. Use this in tearDown() to prevent the seam from
 * leaking across test classes.
 *
 * Note the difference from licensing_set_test_cache(null): setting to
 * null injects null as the seam value (`get_cached_*` then sees an
 * empty cache via the seam). clearing makes `licensing_read_cache_raw()`
 * fall through to the production get_setting() path.
 *
 * @internal
 */
function licensing_clear_test_cache(): void
{
    unset($GLOBALS['__licensing_test_cache']);
}
