<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * SimpleRisk update channel resolver.
 *
 * Decides which set of upstream services (updates, licensing, bundles) this
 * instance talks to: Production or the Release Candidate (RC) channel.
 *
 * Resolution order, first match wins:
 *   1. Explicit UPDATES_URL / LICENSING_URL / BUNDLES_URL constants (each
 *      applies to its own service only; the other services follow the channel).
 *   2. SIMPLERISK_UPDATE_CHANNEL constant ('production' | 'rc').
 *   3. The admin's settings.update_channel value.
 *   4. Production.
 * Any constant from steps 1-2 "locks" the channel selector in the UI.
 *
 * This file is deliberately dependency-free: no require/include, no $lang, no
 * direct DB access. It is loaded by includes/install.php (pre-install, when
 * functions.php is not loaded) and is mirrored into the Upgrade Extra so that
 * Extra can resolve the same hosts against an older Core. Keep it that way.
 *
 * Production bundles stay on the legacy S3 public/bundles path: as of
 * 2026-10-19 bundles.simplerisk.com serves only the latest release, and the
 * Upgrade Extra may need an older one (the newest release this PHP can run).
 * Each bundles base carries its own path prefix, so callers always append
 * "/<file_name>.tgz". The Extra's copy of this file
 * (extras/upgrade/includes/update_channel_fallback.php) must change with it.
 */

/**
 * The allowlist's own key equal to $candidate, or null. The returned string is
 * taken from the allowlist, never from $candidate, so a value read from the
 * database or config.php cannot reach a URL it did not select from the fixed
 * table (and Phan's taint analysis, which cannot see an isset() guard, can see
 * that it does not).
 *
 * @param array<string, mixed> $allowlist
 */
function update_channel_allowlisted(array $allowlist, string $candidate): ?string
{
    foreach (array_keys($allowlist) as $key) {
        if ($key === $candidate) {
            return $key;
        }
    }
    return null;
}

/**
 * Pure resolver. No globals, no DB.
 *
 * @param array         $constants Keys UPDATES_URL, LICENSING_URL, BUNDLES_URL,
 *                                 SIMPLERISK_UPDATE_CHANNEL; an absent key means
 *                                 the constant is not defined.
 * @param mixed         $setting   Raw settings.update_channel value, or false/null.
 * @param callable|null $log       Receives (string $message, string $level).
 * @return array{channel:string,locked:bool,source:string,custom:bool,urls:array{updates:string,licensing:string,bundles:string}}
 *
 * The hosts are a fixed table or operator-set config.php constants; the stored
 * channel only selects a row of that table. Nothing request-controlled becomes
 * part of a URL, so the result is not tainted by the settings read behind it.
 *
 * @return-taint none
 */
function update_channel_resolve(array $constants, $setting, ?callable $log = null): array
{
    $hosts = [
        'production' => [
            'updates' => 'https://updates.simplerisk.com',
            'licensing' => 'https://licensing.simplerisk.com',
            'bundles' => 'https://simplerisk-downloads.s3.amazonaws.com/public/bundles',
        ],
        'rc' => [
            'updates' => 'https://updates-test.simplerisk.com',
            'licensing' => 'https://licensing-test.simplerisk.com',
            'bundles' => 'https://bundles-test.simplerisk.com',
        ],
    ];

    // Channel: constant, then setting, then production.
    $channel = 'production';
    $locked = false;
    $source = 'default';

    if (array_key_exists('SIMPLERISK_UPDATE_CHANNEL', $constants)) {
        $value = $constants['SIMPLERISK_UPDATE_CHANNEL'];
        $recognised = is_string($value) ? update_channel_allowlisted($hosts, $value) : null;
        if ($recognised !== null) {
            $channel = $recognised;
            $locked = true;
            $source = 'channel_constant';
        } elseif ($log !== null) {
            $shown = is_scalar($value) ? (string)$value : gettype($value);
            $log("Ignoring unrecognised SIMPLERISK_UPDATE_CHANNEL value '" . $shown . "'; expected 'production' or 'rc'.", 'notice');
        }
    }

    $recognised = ($source === 'default' && is_string($setting)) ? update_channel_allowlisted($hosts, $setting) : null;
    if ($recognised !== null) {
        $channel = $recognised;
        $source = 'setting';
    }

    // Per-service URL overrides.
    $urls = $hosts[$channel];
    $custom = false;
    foreach (['updates' => 'UPDATES_URL', 'licensing' => 'LICENSING_URL', 'bundles' => 'BUNDLES_URL'] as $service => $name) {
        if (isset($constants[$name]) && is_string($constants[$name]) && trim($constants[$name]) !== '') {
            $urls[$service] = rtrim(trim($constants[$name]), '/');
            $custom = true;
        }
    }
    if ($custom) {
        $locked = true;
        $source = 'url_constant';
    }

    return [
        'channel' => $channel,
        'locked' => $locked,
        'source' => $source,
        'custom' => $custom,
        'urls' => $urls,
    ];
}

/**
 * Display-safe form of an override URL: scheme, host, port and path are kept,
 * any "user:pass@" userinfo is dropped. For what is SHOWN or RETURNED only;
 * the URL actually requested is never passed through this. A value that is
 * not an absolute scheme://authority URL yields a fixed placeholder rather
 * than echoing the raw string.
 */
function update_channel_redact_url($url): string
{
    if (!is_string($url) || !preg_match('~^([A-Za-z][A-Za-z0-9+.\-]*://)([^/?#]*)(.*)$~s', trim($url), $m)) {
        return '[invalid URL]';
    }

    $authority = $m[2];
    $at = strrpos($authority, '@');
    if ($at !== false) {
        $authority = substr($authority, $at + 1);
    }
    if ($authority === '') {
        return '[invalid URL]';
    }

    return $m[1] . $authority . $m[3];
}

/**
 * The resolver state with every URL made display-safe (see
 * update_channel_redact_url()). Use for API responses and page output.
 */
function update_channel_redact_state(array $state): array
{
    if (isset($state['urls']) && is_array($state['urls'])) {
        foreach ($state['urls'] as $service => $url) {
            $state['urls'][$service] = update_channel_redact_url($url);
        }
    }
    return $state;
}

/**
 * Process-static pinned channel storage. Pass the new value to set it
 * (including null to clear); call with no arguments to read it.
 */
function update_channel_pinned(bool $set = false, ?string $channel = null): ?string
{
    static $pinned = null;
    if ($set) {
        $pinned = $channel;
    }
    return $pinned;
}

/**
 * Pin a channel for the rest of this process (null clears). Used to test or
 * pre-check a channel before it is saved. A pin acts at the setting level:
 * URL constants and SIMPLERISK_UPDATE_CHANNEL still win over it.
 */
function update_channel_pin(?string $channel): void
{
    update_channel_pinned(true, $channel);
}

/**
 * Resolve the channel for the running process: gathers defined constants,
 * reads the saved setting when get_setting() exists, and honours a pin.
 *
 * The hosts are a fixed table or operator-set config.php constants; the stored
 * channel only selects a row of that table. Nothing request-controlled becomes
 * part of a URL, so the result is not tainted by the settings read behind it.
 *
 * @return-taint none
 */
function update_channel_current(): array
{
    $constants = [];
    foreach (['UPDATES_URL', 'LICENSING_URL', 'BUNDLES_URL', 'SIMPLERISK_UPDATE_CHANNEL'] as $name) {
        if (defined($name)) {
            $constants[$name] = constant($name);
        }
    }

    $setting = update_channel_pinned();
    if ($setting === null) {
        // get_setting() is absent in the installer, where there is no DB: treat as unset.
        $setting = function_exists('get_setting') ? get_setting('update_channel') : false;
    }

    $log = function_exists('write_debug_log')
        ? static function (string $message, string $level): void {
            write_debug_log($message, $level);
        }
        : null;

    return update_channel_resolve($constants, $setting, $log);
}

/**
 * Base URL of a service (updates|licensing|bundles) joined with an optional
 * path using exactly one '/'. With no path, returns the base with no trailing slash.
 */
function update_channel_service_url(string $service, string $path = ''): string
{
    $urls = update_channel_current()['urls'];
    $base = $urls[$service] ?? $urls['updates'];
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}
