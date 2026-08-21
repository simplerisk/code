<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * SimpleRisk licensing client, and the single home for "may this Extra be
 * installed here" logic regardless of which upstream supplies the data.
 *
 * Talks to the SimpleRisk licensing service (default
 * https://licensing.simplerisk.com) via four endpoints:
 *   POST /register, POST /instance/update,
 *   POST /license/check, POST /download-extra
 *
 * It also resolves the UPDATES service base URL (updates_url(), mirroring
 * licensing_url()) and owns the Extra/Core version-compatibility vocabulary and
 * decisions built on that service's extra_compatibility.xml — the eligibility
 * question spans both services, so both halves live together rather than being
 * split by which host answered.
 *
 * What does NOT belong here: transport policy. This module is shared widely --
 * admin/register.php, includes/extras.php, the license-check job, and the v2 API
 * itself all load it for the decisions above -- so which HTTP status a particular
 * endpoint returns lives with that endpoint, not here.
 *
 * Define LICENSING_URL / UPDATES_URL in config.php to point at non-production
 * services (test deploys do this via scripts/bundles-test-installation.sh).
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
 * The version of $extra_name the licensing service says it will serve, from the
 * cached /license/check response, or null when the server did not tell us.
 *
 * Knowing this BEFORE the download lets the pre-download gate ask the precise
 * question ("is the build I would receive compatible with my release?") rather
 * than the coarse proxy ("is my release the newest?").
 *
 * DO NOT read this as a way to obtain an older build. The licensing service
 * serves only the newest build of an Extra: /download-extra takes instance_id,
 * services_api_key and extra_name, with no version field, so there is no way to
 * request a specific version and nothing here tries to. This value informs a
 * yes/no decision about the one build on offer, and that is all.
 *
 * Nor is the precise form currently more permissive. It would only differ from
 * the coarse proxy if an Extra's newest build mapped to an older release, and for
 * all 18 published Extras the newest build maps to the newest release — so today
 * the two arms agree on every case. What the precise form buys is an accurate
 * refusal (it can name the build that was actually going to be sent) and correct
 * behaviour if serving ever changes. It is not an unlock, and a Core behind the
 * newest release should still expect to upgrade first.
 *
 * The /license/check body IS the cache and entries[] are persisted verbatim, so
 * a field the server adds arrives here with no parser change. Several key names
 * are accepted because the client must not break if the server names it
 * differently than expected, and an unrecognised shape degrades to null, which
 * every caller treats as "fall back to the coarse check" rather than as an error.
 *
 * Only a well-formed release identifier is returned: this value is compared
 * against feed data and reaches log lines and user-facing messages.
 */
function get_download_version_for_extra(string $extra_name): ?string
{
    return select_download_version_from_entries(get_cached_license_entries(), $extra_name);
}

/**
 * Is $value a SimpleRisk release identifier (YYYYMMDD-NNN)? Pure.
 *
 * The single format gate for every version that arrives from the licensing or
 * updates services. Those values are compared against feed data and reach log
 * lines, alerts and API responses, so a malformed or hostile one must be rejected
 * at the boundary rather than sanitized at each use.
 *
 * @param mixed $value
 */
function is_release_identifier($value): bool
{
    // \z, not $. PCRE's $ also matches immediately BEFORE a trailing newline, so
    // /^\d{8}-\d{3}$/ accepts "20260811-001\n" — and these values arrive from an
    // HTTP response header and from server-supplied JSON, then get interpolated
    // into log lines. A newline that survives the gate forges a log entry. \z
    // anchors at the true end of the subject.
    return is_string($value) && preg_match('/^\d{8}-\d{3}\z/', $value) === 1;
}

/**
 * Pick the version of $extra_name out of a /license/check entries[] array. Pure,
 * so the entry-matching and key-fallback behaviour is testable without a DB.
 *
 * Stops at the first entry matching $extra_name — the server sends one entry per
 * Extra — and returns null if that entry carries no usable version, rather than
 * continuing on to a later entry for a different Extra.
 *
 * Three key names are accepted so the client does not break if the server names
 * the field differently than expected; an unrecognised shape yields null, which
 * every caller treats as "fall back to the coarse check" rather than an error.
 *
 * @param array<int, mixed> $entries
 */
function select_download_version_from_entries(array $entries, string $extra_name): ?string
{
    foreach ($entries as $entry) {
        if (!is_array($entry) || ($entry['extra_name'] ?? null) !== $extra_name) {
            continue;
        }
        foreach (['download_version', 'extra_version', 'version'] as $key) {
            if (is_release_identifier($entry[$key] ?? null)) {
                return (string)$entry[$key];
            }
        }
        return null;
    }
    return null;
}

/**
 * Is this Extra the recovery mechanism?
 *
 * The Upgrade Extra is exempt from every compatibility judgement, at every
 * stage. It is what a customer uses to get back to a supported state, so
 * refusing it on compatibility grounds strands exactly the instance that most
 * needs it.
 *
 * This exists because the rule was spelled as a bare string literal in three
 * independent decisions and one of them was simply missing it -- the
 * post-extract check -- which shipped a real defect: the licensing service
 * serves ONE build per Extra, the served Upgrade build routinely predates the
 * Core it lands on, and the feed then judged it incompatible. The three
 * decisions stay separate (they run at genuinely different, independently
 * reachable stages), but the one fact that varies now has a single home.
 */
function extra_is_recovery_mechanism(string $name): bool
{
    return $name === 'upgrade';
}

/**
 * The verdict on a build we have been handed, given what the package declares,
 * what the response header claimed, and what the compatibility feed knows. Pure.
 *
 * Returns one of:
 *   'allow'                    — the feed confirms this build supports this release
 *   'extra_version_unreadable' — the package declares no readable version
 *   'version_mismatch'         — package and header disagree
 *   'compatibility_unknown'    — no feed data at all
 *   'compatibility_stale'      — feed has data but no entry for this build
 *   'version_incompatible'     — the feed says this build does not support this release
 *
 * 'compatibility_stale' is deliberately distinct from 'compatibility_unknown' and
 * from 'version_incompatible'. Its usual cause is a cache older than the build we
 * were just sent, so the caller can refresh the feed once and ask again instead of
 * refusing a newly published build until the 24h job catches up. Keeping the
 * retry decision in the caller keeps this function pure and the retry testable.
 *
 * Order matters: an unreadable version cannot be compared to anything, and a
 * package/header disagreement means the response did not describe its own payload,
 * so neither value is trustworthy enough to judge compatibility with.
 *
 * @param array<string, array<string, list<string>>>|null $compat
 */
function extra_download_verdict(
    ?string $package_version,
    ?string $header_version,
    ?array $compat,
    string $extra,
    string $app_version
): string {
    if (!is_release_identifier($package_version)) {
        return 'extra_version_unreadable';
    }

    if ($header_version !== null && $header_version !== $package_version) {
        return 'version_mismatch';
    }

    // The recovery mechanism always goes through -- the same carve-out the
    // PRE-download gate makes, and for the same reason. It was missing here, and
    // that is not a theoretical gap: the licensing service serves ONE build of
    // each Extra, and the Upgrade Extra's build routinely predates the Core it is
    // being installed onto (an in-development or freshly-cut release is newer
    // than the last published Upgrade build). The compatibility feed then judges
    // it incompatible and the post-extract check refused it -- stranding the
    // customer, because the Upgrade Extra is precisely what they would use to get
    // back to a supported state.
    //
    // Only the COMPATIBILITY judgement is waived. The integrity checks above
    // still apply: an unreadable version, or a package whose version disagrees
    // with the X-Extra-Version header, is a corrupt download -- and installing a
    // corrupt recovery mechanism is worse than refusing it. The X-SHA256 body
    // check is separate and lives in download_extra(); it runs unconditionally,
    // for every Extra including this one.
    if (extra_is_recovery_mechanism($extra)) {
        return 'allow';
    }

    if ($compat === null) {
        return 'compatibility_unknown';
    }

    $supported = extra_version_supported_by_app($compat, $extra, $package_version, $app_version);

    if ($supported === true) {
        return 'allow';
    }
    if ($supported === null) {
        return 'compatibility_stale';
    }
    return 'version_incompatible';
}

/**
 * The single decision for "may this Extra be downloaded onto this Core?",
 * covering both the precise and the coarse path. Pure.
 *
 * Returns one of:
 *   'allow'                    — proceed with the download
 *   'pending_migration'        — the schema has not caught up with the files
 *   'version_incompatible'     — the build we would receive does not support this release
 *   'core_out_of_date'         — coarse answer: this release is not the newest
 *
 * Order matters. The Upgrade Extra is exempt from everything because it is what
 * makes an out-of-date instance current. A pending migration blocks regardless
 * of which build we would receive, because the newest Extra expects the newest
 * schema. Only then is the version question asked, preferring the precise form
 * when the licensing service told us what it will serve AND the feed can judge
 * it; anything less definite falls back to the coarse comparison, which fails
 * open when the release feed is unknown.
 *
 * @param array<string, array<string, list<string>>>|null $compat         Feed data, or null when unavailable.
 * @param string|null                                     $served_version Version the service will send, or null when unknown.
 */
function extra_install_decision(
    string $name,
    string $app_version,
    string $db_version,
    string $latest_app_version,
    ?string $served_version = null,
    ?array $compat = null
): string {
    if (extra_is_recovery_mechanism($name)) {
        return 'allow';
    }

    if ($app_version !== $db_version) {
        return 'pending_migration';
    }

    // Precise: we know the build and the feed can judge it. This is the arm that
    // lets a release behind the newest still install an Extra whose current
    // build supports it (a mid-release respin belongs to the earlier release).
    if ($served_version !== null && $compat !== null) {
        $supported = extra_version_supported_by_app($compat, $name, $served_version, $app_version);
        if ($supported === true) {
            return 'allow';
        }
        if ($supported === false) {
            return 'version_incompatible';
        }
        // null — the feed has no entry for that build. Not definite, so fall
        // through to the coarse check rather than refusing on missing data;
        // the post-extract verification fails closed on this case anyway.
    }

    // Coarse: reuse the published helper so the two cannot drift.
    return extra_install_blocked_by_core_version($name, $app_version, $db_version, $latest_app_version)
        ? 'core_out_of_date'
        : 'allow';
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
 * Decide the integrity outcome for a downloaded Extra given the two hashes we
 * may hold for it. Pure.
 *
 * Returns 'ok', 'source_conflict', 'corrupt', or 'unverified'.
 *
 * THE TWO SOURCES ARE NOT INTERCHANGEABLE, and this is the important part:
 *
 * - $cached_sha256 comes from a PREVIOUS, SEPARATE /license/check request. That
 *   independence is what makes it a real integrity check: whoever can alter the
 *   download body cannot retroactively alter a hash we already hold.
 * - $header_sha256 is the X-SHA256 header of the SAME response that carried the
 *   body. Anything able to rewrite the body can rewrite its header too, so this
 *   detects TRUNCATION AND CORRUPTION but provides no guarantee against a
 *   tampered response.
 *
 * So the header is a strict improvement on the previous behaviour -- an unknown
 * cached hash meant skipping verification entirely -- but it is NOT a substitute
 * for the cached one. Do not "simplify" this by dropping the cached hash because
 * the header is always present; that would quietly convert an integrity check
 * into a corruption check.
 *
 * When both are present they must agree. A disagreement means the response does
 * not describe the package the licensing service recorded, which is refused
 * rather than resolved by preferring one.
 */
function extra_download_hash_verdict(?string $cached_sha256, ?string $header_sha256, string $bytes): string
{
    $normalize = static function (?string $h): ?string {
        if (!is_string($h)) {
            return null;
        }
        $h = strtolower(trim($h));
        // \z, not $ — see is_release_identifier(): $ would also accept a trailing
        // newline, and this value comes off the wire in the X-SHA256 header.
        return preg_match('/^[0-9a-f]{64}\z/', $h) === 1 ? $h : null;
    };

    $cached = $normalize($cached_sha256);
    $header = $normalize($header_sha256);
    $actual = hash('sha256', $bytes);

    if ($cached !== null && $header !== null && !hash_equals($cached, $header)) {
        return 'source_conflict';
    }

    // Prefer the independent hash; fall back to the header only to catch
    // corruption when we have nothing better.
    $expected = $cached ?? $header;

    if ($expected === null) {
        return 'unverified';
    }

    return hash_equals($expected, $actual) ? 'ok' : 'corrupt';
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
 * Decide whether an Extra download must be refused because Core is not on the
 * latest release. Pure — no DB, no network, no globals.
 *
 * "Core first, then Extras" is a real constraint, not a preference. The
 * licensing service always ships the NEWEST build of an Extra: POST
 * /download-extra carries instance_id, services_api_key and extra_name only,
 * with no version field, so the server cannot tailor the package to an older
 * Core. When an Extra starts calling Core code — a new class, a new helper —
 * that Core predates, the result is a fatal on every page that renders the
 * affected feature. This function is the only place that constraint is
 * enforced.
 *
 * The gate existed before the /download-extra rewrite (e4259b7736) and was
 * dropped along with the legacy XML parser it sat next to.
 *
 * Three deliberate decisions:
 *
 * 1. The Upgrade Extra is NEVER blocked. It is the mechanism that brings Core
 *    to the latest release, so refusing it would leave an out-of-date instance
 *    permanently unable to become up to date, and it is the recovery path when
 *    the installed copy is damaged.
 *
 * 2. A pending schema migration (app != db) blocks on its own, with no
 *    reference to the feed. The newest Extra expects the newest schema.
 *
 * 3. $latest_app_version === '' means the releases feed was unreachable or
 *    unparseable, and the remote half of the check then fails OPEN. Refusing
 *    every install because our own network call failed would take a working
 *    feature offline on every customer behind a broken egress path. The
 *    consequence is that this is a policy control, not a guarantee: an Extra
 *    that begins calling new Core code still needs its own guard for the case
 *    where the feed was down when the operator pressed Install.
 *
 * 4. Only a Core strictly BEHIND the newest release is blocked, never one merely
 *    different from it. An in-development or pre-release build is AHEAD of the
 *    published feed — this repository's APP_VERSION was 20260811-001 while
 *    releases.xml still advertised 20260519-001 — and an equality test would
 *    have refused every Extra install on every dev, CI and RC instance. The
 *    check this restores had the same property for a different reason: it asked
 *    upgrade_path.xml for the next hop FROM the running version, and an
 *    unpublished version has no entry, so it too failed open. Release
 *    identifiers are fixed-width YYYYMMDD-NNN, so string comparison is
 *    chronological.
 *
 * @param string $name               Extra short name.
 * @param string $app_version        APP_VERSION of the running files.
 * @param string $db_version         db_version recorded in the settings table.
 * @param string $latest_app_version Newest published release; '' when unknown.
 *
 * @return bool True when the download must be refused.
 */
function extra_install_blocked_by_core_version(
    string $name,
    string $app_version,
    string $db_version,
    string $latest_app_version
): bool {
    // (1) The recovery mechanism always goes through.
    if (extra_is_recovery_mechanism($name)) {
        return false;
    }

    // (2) Local, feed-independent: the schema has not caught up with the files.
    if ($app_version !== $db_version) {
        return true;
    }

    // (3) Feed unknown — nothing further can be decided, so allow.
    if ($latest_app_version === '') {
        return false;
    }

    // (4) Strictly behind only. Never block a build that is ahead of the feed.
    return strcmp($app_version, $latest_app_version) < 0;
}

/**
 * Resolve a URL on the updates service, mirroring licensing_url().
 *
 * UPDATES_URL is an OVERRIDE, defined only by scripts/bundles-test-installation.sh
 * so a test deploy can point at updates-test. A production instance never has it,
 * which makes the default the LIVE path — and that default must be the service we
 * operate. It was raw.githubusercontent.com, a host we do not control and which
 * rate-limits (429/503), for every feed on every production instance, including
 * the releases.xml that authorises a release bundle.
 *
 * NOT usable at five of the eight feed-reading sites, and the reason matters:
 *
 *  - simplerisk/extras/upgrade/index.php (x2) and extras/management/index.php
 *    are Extras. The Upgrade Extra is downloaded FIRST and runs against a Core
 *    OLDER than itself, so a bare call to a Core helper that release does not
 *    define is a fatal mid-upgrade, for the population least able to recover
 *    from one. They keep their own literals on purpose.
 *  - simplerisk/includes/install.php (x2) is the pre-install bootstrap, which
 *    runs before functions.php can be loaded at all (the same constraint behind
 *    its installer_log() carve-out).
 *
 * So do not "fix the inconsistency" by routing those five through here.
 */
function updates_url(string $path = ''): string
{
    $base = defined('UPDATES_URL') ? UPDATES_URL : 'https://updates.simplerisk.com';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * The refusal codes that mean "this instance is not in a state to accept the
 * build on offer", as opposed to "something went wrong fetching it".
 *
 * Defined here, beside the two functions that produce them
 * (extra_install_decision() and extra_download_verdict()), so the classification
 * cannot drift from the codes themselves. The v2 install endpoint consults this
 * rather than restating the list.
 *
 * @return list<string>
 */
function extra_install_precondition_errors(): array
{
    return [
        'core_out_of_date',         // this release is not the newest
        'pending_migration',        // the schema has not caught up with the files
        'version_incompatible',     // the build does not support this release
        'compatibility_unknown',    // no compatibility data to judge it with
        'extra_version_unreadable', // the package declares no readable version
    ];
}

/*
 * The HTTP-status mapping for these codes lives with the endpoint that uses it,
 * in api/v2/includes/api.php beside api_v2_admin_extras_install(). This module is
 * a service client loaded by non-API callers (register.php, extras.php, the
 * license-check job); which status code a JSON API returns is transport policy
 * and does not belong here. The vocabulary above stays, so the classification
 * still cannot drift from the codes themselves.
 */


/**
 * Parse the updates service's extra_compatibility.xml into
 *   [extra_short_name => [extra_version => [compatible app version, ...]]]
 *
 * Pure. Returns null for any body that cannot be used, matching
 * parse_releases_feed(): a 200 does not guarantee a usable string (a
 * mid-transfer timeout can yield an empty body), so every unusable input must
 * degrade rather than throw.
 *
 * The feed is the reverse index of releases.xml's per-release <extras> list.
 * One release maps to many versions of the same Extra -- a mid-release respin
 * gets a later version number while still belonging to the earlier release
 * (assessments 20231110-001 belongs to app 20231103-001, and there are 83 such
 * entries) -- while each Extra version maps to exactly one release. That
 * asymmetry is the whole reason this feed is required: "is the Extra version
 * newer than APP_VERSION" would reject every one of those legitimate respins,
 * so compatibility cannot be inferred from the version numbers alone.
 *
 * The feed shape is:
 *   <extras>
 *     <api>
 *       <extra version="20260811-001"><appversion>20260811-001</appversion></extra>
 *
 * Multiple <appversion> children are accepted even though the published feed
 * currently has none, so a future many-to-many does not need a parser change.
 *
 * @param mixed $body Raw response body.
 *
 * @return array<string, array<string, list<string>>>|null
 */
function parse_extra_compatibility_feed($body): ?array
{
    if (!is_string($body) || $body === '') {
        return null;
    }
    $xml = @simplexml_load_string($body);
    if ($xml === false) {
        return null;
    }

    $out = [];
    foreach ($xml as $extra_name => $extra_node) {
        $name = (string)$extra_name;
        if ($name === '') {
            continue;
        }
        foreach ($extra_node->extra as $entry) {
            $version = isset($entry['version']) ? (string)$entry['version'] : '';
            if ($version === '') {
                continue;
            }
            $apps = [];
            foreach ($entry->appversion as $app) {
                $app = trim((string)$app);
                if ($app !== '') {
                    $apps[] = $app;
                }
            }
            if ($apps === []) {
                continue;
            }
            // Later duplicates merge rather than overwrite; the feed is
            // generated, but a repeated entry must not silently drop versions.
            $existing = $out[$name][$version] ?? [];
            $out[$name][$version] = array_values(array_unique(array_merge($existing, $apps)));
        }
    }

    // An empty map means the body parsed but carried nothing we can use. Treat
    // it as unusable so callers apply their no-data policy instead of reading it
    // as "this Extra is not compatible with anything".
    return $out === [] ? null : $out;
}

/**
 * Read an Extra's version out of its index.php source.
 *
 * Pure -- takes the source text, not a path. The caller reads the file, because
 * the file must NEVER be included to get this value: a freshly downloaded Extra
 * redeclares every function the live copy already defined, which is an
 * unrecoverable fatal in the middle of an install. The constant is also not
 * derivable from the short name (import-export defines
 * IMPORTEXPORT_EXTRA_VERSION and complianceforgescf defines
 * COMPLIANCEFORGE_SCF_EXTRA_VERSION), so this matches whatever *_EXTRA_VERSION
 * the file declares rather than constructing the name.
 *
 * The value is required to look like a release identifier. That rejects a
 * malformed or hostile package before its version string reaches a log line, an
 * alert or an API response, and catches a truncated download that happens to
 * still contain the define.
 *
 * @param mixed $source Contents of the Extra's index.php.
 *
 * @return string|null The version, or null when absent or malformed.
 */
function parse_extra_version_from_source($source): ?string
{
    if (!is_string($source) || $source === '') {
        return null;
    }
    if (!preg_match(
        "/define\s*\(\s*'[A-Z0-9_]*_EXTRA_VERSION'\s*,\s*'(\d{8}-\d{3})'\s*\)/",
        $source,
        $m
    )) {
        return null;
    }
    return $m[1];
}

/**
 * Is $extra_version of $extra compatible with the running $app_version?
 *
 * Pure. Returns null for "the feed has no entry for this Extra at this
 * version", which is a genuinely different answer from false and must stay
 * distinguishable: the caller's no-data policy decides what to do, and
 * collapsing unknown into incompatible here would hide that decision.
 *
 * @param array<string, array<string, list<string>>> $compat From parse_extra_compatibility_feed().
 *
 * @return bool|null
 */
function extra_version_supported_by_app(
    array $compat,
    string $extra,
    string $extra_version,
    string $app_version
): ?bool {
    if (!isset($compat[$extra][$extra_version])) {
        return null;
    }
    return in_array($app_version, $compat[$extra][$extra_version], true);
}

/**
 * Every version of $extra the feed says works with $app_version, newest first.
 *
 * Pure. Used to tell the operator which version their release actually supports
 * instead of only that the one they were sent does not.
 *
 * @param array<string, array<string, list<string>>> $compat From parse_extra_compatibility_feed().
 *
 * @return list<string>
 */
function extra_versions_for_app(array $compat, string $extra, string $app_version): array
{
    $versions = [];
    foreach ($compat[$extra] ?? [] as $version => $apps) {
        if (in_array($app_version, $apps, true)) {
            $versions[] = (string)$version;
        }
    }
    rsort($versions, SORT_STRING);
    return $versions;
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
