<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/extras.php'));
require_once(realpath(__DIR__ . '/connectivity.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/*******************************************
 * FUNCTION: EXTRA INSTALL CORE VERSION REFUSAL *
 *******************************************/
/**
 * Version-gate for an Extra download. Returns null when the download may
 * proceed, or a download_extra() error envelope when Core is too old to receive
 * the newest build of the Extra.
 *
 * The decision itself is pure and lives in extra_install_blocked_by_core_version()
 * in licensing.php, which documents why the rule exists and why it fails open;
 * this wrapper only supplies the three versions it compares.
 *
 * latest_version() is called with $force_refresh = false so the Install button
 * reads the value the version-check job already cached in the
 * latest_version_data setting instead of adding a releases.xml round-trip to
 * every click. It returns '' when nothing is cached and the fetch fails, which
 * the pure helper treats as "unknown".
 *
 * @param string $name Extra short name.
 *
 * @return array|null Error envelope when refused, null when allowed.
 */
function extra_install_core_version_refusal($name) {
    require_once(realpath(__DIR__ . '/licensing.php'));

    $latest      = latest_version('app', false);
    $app_version = (string)current_version('app');

    // The version the licensing service says it will serve, from the cached
    // /license/check response. When present, this turns the coarse "is my
    // release the newest" proxy into the precise "does the build I would receive
    // support my release" — which is a different, and less restrictive, answer.
    // Only fetch the feed when it can actually change the outcome.
    $served_version = get_download_version_for_extra((string)$name);
    $compatibility  = ($served_version !== null) ? extra_compatibility_versions() : null;

    $decision = extra_install_decision(
        (string)$name,
        $app_version,
        (string)current_version('db'),
        is_string($latest) ? $latest : '',
        $served_version,
        $compatibility
    );

    if ($decision === 'allow') {
        return null;
    }

    if ($decision === 'version_incompatible') {
        return build_extra_error_envelope($decision, $name, _lang_raw('ExtraVersionIncompatibleWithApplication', array(
            'extra'         => $name,
            'extra_version' => (string)$served_version,
            'app_version'   => $app_version,
        )));
    }

    // 'core_out_of_date' and 'pending_migration' share the message: from the
    // operator's side both mean "bring SimpleRisk fully up to date first", and
    // the existing key already says exactly that in every locale.
    return build_extra_error_envelope($decision, $name, _lang_raw('ApplicationNeedsToBeUpgradeToLatestVersionToUpgradeExtras', array()));
}

/**************************************************
 * FUNCTION: EXTRA DOWNLOAD VERSION REFUSAL       *
 **************************************************/
/**
 * Post-extract verification. Given the directory a downloaded Extra was
 * extracted to, decide whether the build it contains may be installed on this
 * Core. Returns null to proceed, or a download_extra() error envelope.
 *
 * This is the check that does not rely on a prediction. The pre-download gate
 * reasons about which build the server *would* send; this reads the version out
 * of the package the server *did* send and asks the compatibility feed whether
 * this release supports it.
 *
 * It fails CLOSED. An unreadable version, absent compatibility data, or a
 * version the feed does not recognise all refuse the install. That is the
 * opposite of the pre-download gate's fail-open, and the asymmetry is
 * deliberate: failing open before the download only risks a wasted request,
 * while failing open here installs code that may fatal on every page rendering
 * the feature. extra_compatibility_versions() caches into a setting refreshed
 * every 24h by core_version_check, so "no data at all" is rare enough for
 * closed to be the affordable default.
 *
 * @param string        $name            Extra short name.
 * @param string        $source_dir      Directory the package was extracted to.
 * @param int           $http_status     Status of the download, for the envelope.
 * @param string|null   $header_version  X-Extra-Version from the download response,
 *                                       when the service supplied one.
 * @param callable|null $compat_provider Test seam: fn(bool $force): ?array returning
 *                                       the compatibility map. Production callers pass
 *                                       null and get extra_compatibility_versions().
 *                                       Mirrors licensing_register_with_retry()'s
 *                                       $transport seam — without it the
 *                                       refresh-once-then-re-decide path below can only
 *                                       be exercised against the live updates service,
 *                                       which is exactly the branch most worth testing.
 *
 * @return array|null Error envelope when refused, null when allowed.
 */
function extra_download_version_refusal($name, $source_dir, $http_status = 0, $header_version = null, ?callable $compat_provider = null) {
    require_once(realpath(__DIR__ . '/licensing.php'));

    if ($compat_provider === null) {
        $compat_provider = function (bool $force) {
            return extra_compatibility_versions($force);
        };
    }

    // Read the version from the source text. Never include the file: it
    // redeclares every function the live Extra already defined, which is an
    // unrecoverable fatal mid-install.
    $index_file = rtrim($source_dir, '/') . '/index.php';
    $version    = is_readable($index_file)
        ? parse_extra_version_from_source(@file_get_contents($index_file))
        : null;

    $app_version   = (string)current_version('app');
    $compatibility = $compat_provider(false);

    $verdict = extra_download_verdict($version, $header_version, $compatibility, (string)$name, $app_version);

    // 'compatibility_stale' means the feed has data but no entry for this build.
    // The usual cause is a cache older than what we were just sent, so refresh
    // once and re-ask rather than refusing a newly published build until the 24h
    // job catches up. Only this genuinely ambiguous case pays a network call.
    if ($verdict === 'compatibility_stale') {
        write_debug_log("download_extra: '{$name}' {$version} is absent from the cached compatibility data; refreshing the feed", 'info');
        $compatibility = $compat_provider(true);
        $verdict = extra_download_verdict($version, $header_version, $compatibility, (string)$name, $app_version);
        // Still stale after a fresh fetch: the feed genuinely does not know this
        // build. Fail closed, reported as unknown rather than incompatible --
        // we have no basis for the stronger claim.
        if ($verdict === 'compatibility_stale') {
            $verdict = 'compatibility_unknown';
        }
    }

    if ($verdict === 'allow') {
        return null;
    }

    if ($verdict === 'extra_version_unreadable') {
        write_debug_log("download_extra: could not read a version from the downloaded '{$name}' Extra — refusing to install", 'error');
        return build_extra_error_envelope($verdict, $name, _lang_raw('ExtraVersionCouldNotBeVerified', array()), $http_status);
    }

    if ($verdict === 'version_mismatch') {
        write_debug_log("download_extra: '{$name}' package declares {$version} but X-Extra-Version said {$header_version} — refusing to install", 'error');
        return build_extra_error_envelope($verdict, $name, _lang_raw('ExtraVersionCouldNotBeVerified', array()), $http_status);
    }

    if ($verdict === 'compatibility_unknown') {
        write_debug_log("download_extra: no usable Extra compatibility data — refusing to install '{$name}' {$version}", 'warning');
        return build_extra_error_envelope($verdict, $name, _lang_raw('ExtraCompatibilityDataUnavailable', array()), $http_status);
    }

    // version_incompatible. Log which versions this release does support, so a
    // support conversation can tell "wrong build" from "no build exists". This is
    // diagnostic only -- it is NOT in the operator-facing message, because the
    // service serves only the newest build and naming another version would imply
    // it can be requested.
    $usable = extra_versions_for_app(is_array($compatibility) ? $compatibility : [], $name, $app_version);
    $newest = $usable[0] ?? '';

    write_debug_log(
        "download_extra: '{$name}' {$version} is not compatible with SimpleRisk {$app_version} — refusing to install"
        . ($newest !== '' ? " (this release supports {$newest})" : ''),
        'notice'
    );

    return build_extra_error_envelope($verdict, $name, _lang_raw('ExtraVersionIncompatibleWithApplication', array(
        'extra'         => $name,
        'extra_version' => (string)$version,
        'app_version'   => $app_version,
    )), $http_status);
}

/**
 * Build a download_extra() error envelope. One shape, one place.
 *
 * Every caller MUST distinguish success from failure with is_string($result): the
 * envelope is a non-empty array and therefore truthy, so a boolean check is broken.
 *
 * @return array{error: string, extra_name: string, reason: ?string, retry_after_seconds: ?int, http_status: int}
 */
function build_extra_error_envelope($error, $name, $reason = null, $http_status = 0, $retry_after_seconds = null) {
    return [
        'error'               => $error,
        'extra_name'          => $name,
        'reason'              => $reason,
        'retry_after_seconds' => $retry_after_seconds,
        'http_status'         => $http_status,
    ];
}

/****************************
 * FUNCTION: DOWNLOAD EXTRA *
 ****************************/
/**
 * Download a SimpleRisk Extra package from the licensing service.
 *
 * @param string $name            Short Extra name (must be in available_extra_short_names()).
 * @param bool   $streamed_response  When true, fetches via stream transport (used by the
 *                                   one-click upgrade path that writes output to the browser
 *                                   in real time); false uses curl (default).
 * @param bool   $skip_version_check When true, bypasses the Core-version gate. ONLY for
 *                                   callers running inside an upgrade that has already
 *                                   swapped the application files in this same request —
 *                                   see the gate below. Never set this on an
 *                                   operator-initiated install.
 *
 * @return string|array Raw tgz bytes (string) on HTTP 200. On any error, an
 *                      associative array {error, extra_name, reason,
 *                      retry_after_seconds, http_status}. Callers MUST check
 *                      is_string($result) to distinguish success from failure.
 *                      A boolean truthy check is broken since the error array
 *                      is non-empty and therefore truthy.
 */
function download_extra($name, $streamed_response = false, $skip_version_check = false) {
    require_once(realpath(__DIR__ . '/licensing.php'));

    $instance_id      = get_setting('instance_id');
    $services_api_key = get_setting('services_api_key');

    // Defense-in-depth: refuse to ship a name Core doesn't recognize.
    if (!in_array($name, available_extra_short_names(), true)) {
        write_debug_log("download_extra: unknown Extra '{$name}'", 'warning');
        return ['error' => 'invalid_extra_name', 'extra_name' => $name, 'reason' => null, 'retry_after_seconds' => null, 'http_status' => 0];
    }

    // "Core first, then Extras". The licensing service always ships the newest
    // build of an Extra and has no version field to tailor it with, so this is
    // the only place the ordering can be enforced — see
    // extra_install_blocked_by_core_version() in licensing.php.
    //
    // $skip_version_check exists for one caller: the Core-driven one-click
    // upgrade (core_upgrade_extras(), reached from one_click_upgrade() in
    // includes/api.php). It runs in the SAME request that already extracted the
    // new bundle, and APP_VERSION is a constant that cannot be redefined, so
    // current_version('app') still reads the pre-upgrade release and this gate
    // would refuse every Extra the step exists to upgrade. That is exactly the
    // trap the Upgrade Extra documents at extras/upgrade/index.php's Step 5.
    if (!$skip_version_check) {
        $refusal = extra_install_core_version_refusal($name);
        if ($refusal !== null) {
            write_debug_log("download_extra: refusing '{$name}' — Core is not on the latest release", 'notice');
            return $refusal;
        }
    }

    $parameters = [
        'instance_id'      => $instance_id,
        'services_api_key' => $services_api_key,
        'extra_name'       => $name,
    ];

    $http_options = [
        'method' => 'POST',
        'header' => ['Content-Type: application/x-www-form-urlencoded'],
        // The licensing service describes the package it is sending in X-SHA256
        // and X-Extra-Version. Reading them lets us reject a bad or incompatible
        // download before a byte reaches the filesystem. Opt-in, so no other
        // caller's return shape changes; curl only, which is the transport every
        // path that verifies uses (see below).
        'capture_headers' => true,
    ];

    // SSL validation is on by default; only disabled when the setting is explicitly '0'.
    $validate_ssl = ssl_external_verify_enabled();

    $response = fetch_url_content(
        $streamed_response ? 'stream' : 'curl',
        $http_options, $validate_ssl,
        licensing_url('/download-extra'), $parameters
    );

    if (!is_array($response)) {
        write_debug_log("download_extra: unable to reach licensing service", 'warning');
        return ['error' => 'transport', 'extra_name' => $name, 'reason' => null, 'retry_after_seconds' => null, 'http_status' => 0];
    }

    $code = (int)($response['return_code'] ?? 0);
    $body = (string)($response['response'] ?? '');

    // Response headers are present only on the curl transport (the stream
    // transport does not capture them). That is sufficient: the stream transport
    // is used by the one-click path, which passes $skip_version_check and is
    // covered by the bundle's own verification instead.
    $headers        = is_array($response['headers'] ?? null) ? $response['headers'] : [];
    $header_sha256  = isset($headers['x-sha256']) ? (string)$headers['x-sha256'] : null;
    // One format gate, shared with every other version this code trusts, rather
    // than a second copy of the pattern here.
    $candidate      = isset($headers['x-extra-version']) ? trim((string)$headers['x-extra-version']) : '';
    $header_version = is_release_identifier($candidate) ? $candidate : null;

    if ($code === 200) {
        // Integrity check before install. A tampered or truncated package must
        // never reach a caller that would extract/install it.
        //
        // Two hashes, deliberately not interchangeable: the one cached from a
        // previous /license/check is independent of this response and is the real
        // integrity check, while X-SHA256 rides along with the body and can only
        // catch corruption. extra_download_hash_verdict() prefers the former,
        // falls back to the latter (better than the previous behaviour of
        // skipping verification entirely on an unknown hash), and refuses when
        // the two disagree.
        $expected_sha256 = get_download_sha256_for_extra($name);
        $hash_verdict    = extra_download_hash_verdict($expected_sha256, $header_sha256, $body);

        if ($hash_verdict === 'source_conflict') {
            write_debug_log("download_extra: X-SHA256 disagrees with the licensed hash for '{$name}' — refusing to install", 'error');
            return ['error' => 'integrity', 'extra_name' => $name, 'reason' => _lang_raw('ExtraIntegrityCheckFailed', array()), 'retry_after_seconds' => null, 'http_status' => $code];
        }

        if ($hash_verdict === 'unverified') {
            write_debug_log("download_extra: no SHA-256 available for '{$name}' from either the license cache or X-SHA256; integrity unverified", 'warning');
        }

        if ($hash_verdict === 'corrupt') {
            write_debug_log("download_extra: SHA-256 mismatch for '{$name}' — refusing to install", 'error');
            return ['error' => 'integrity', 'extra_name' => $name, 'reason' => _lang_raw('ExtraIntegrityCheckFailed', array()), 'retry_after_seconds' => null, 'http_status' => $code];
        }

        // Earliest possible compatibility refusal: X-Extra-Version tells us what
        // the service sent, so an incompatible build is rejected here, before any
        // temp file, gunzip or extraction happens. The authoritative check is
        // still the one on the extracted package below -- this header is what the
        // server claims, not what will run -- but when they agree there is no
        // reason to touch the filesystem at all.
        if (!$skip_version_check && $header_version !== null) {
            $app_version = (string)current_version('app');
            // Same shared verdict the post-extract check uses, so the two layers
            // cannot drift. The header stands in for the package version here
            // because the package does not exist on disk yet; passing null as the
            // header avoids comparing the value to itself.
            $header_verdict = extra_download_verdict(
                $header_version, null, extra_compatibility_versions(), (string)$name, $app_version
            );

            // ONLY a definite incompatibility refuses here. Every other verdict
            // (unknown or stale feed) falls through to the post-extract check,
            // which fails closed -- this layer exists to save filesystem work on a
            // clear no, not to become a second closed gate on missing data.
            if ($header_verdict === 'version_incompatible') {
                write_debug_log("download_extra: X-Extra-Version {$header_version} for '{$name}' is not compatible with SimpleRisk {$app_version} — refusing before extraction", 'notice');
                return build_extra_error_envelope('version_incompatible', $name, _lang_raw('ExtraVersionIncompatibleWithApplication', array(
                    'extra'         => $name,
                    'extra_version' => $header_version,
                    'app_version'   => $app_version,
                )), $code);
            }
        }

        // Install the verified package to simplerisk/extras/<name>/. download_extra()'s
        // contract is "string return == installed" — every caller (the Configure Hub
        // Install endpoint, core_upgrade_extras, the post-registration upgrade) treats a
        // string as success, so the bytes must be extracted onto disk here, not returned raw.
        $extras_dir = realpath(__DIR__ . '/../extras');
        if ($extras_dir === false) {
            $simplerisk_dir = realpath(__DIR__ . '/../');
            if ($simplerisk_dir !== false && is_writeable($simplerisk_dir) && mkdir($simplerisk_dir . '/extras')) {
                $extras_dir = $simplerisk_dir . '/extras';
            }
        }
        if ($extras_dir === false || !is_writeable($extras_dir)) {
            write_debug_log("download_extra: extras directory not writeable; cannot install '{$name}'", 'error');
            return ['error' => 'install_failed', 'extra_name' => $name, 'reason' => _lang_raw('ExtraInstallWriteFailed', array()), 'retry_after_seconds' => null, 'http_status' => $code];
        }

        // Use a private, unpredictable per-invocation working directory (0700) instead of
        // fixed paths in the shared temp dir — avoids symlink/TOCTOU attacks on a multi-tenant
        // host and collisions between concurrent installs of the same Extra.
        $work   = sys_get_temp_dir() . '/sr_extra_' . bin2hex(random_bytes(8));
        $tgz    = $work . '/' . $name . '.tar.gz';
        $tar    = $work . '/' . $name . '.tar';
        $source = $work . '/' . $name;
        $dest   = $extras_dir . '/' . $name;
        $copied = false;
        // Whether recurse_copy() was reached at all. Distinct from $copied, which
        // means it also SUCCEEDED. The catch needs the difference: only a copy
        // that started can have left $dest half-written.
        $copy_started = false;
        try {
            if (!mkdir($work, 0700, true)) {
                throw new \RuntimeException('could not create work dir');
            }
            // Write the verified tgz, gunzip it to a .tar, extract, then copy the tree in.
            if (file_put_contents($tgz, $body) === false) {
                throw new \RuntimeException('write tgz failed');
            }
            $in = gzopen($tgz, 'rb');
            if ($in === false) {
                throw new \RuntimeException('gzopen failed');
            }
            $out = fopen($tar, 'wb');
            if ($out === false) {
                gzclose($in);
                throw new \RuntimeException('fopen tar failed');
            }
            while (!gzeof($in)) {
                $chunk = gzread($in, 4096);
                if ($chunk === false || fwrite($out, $chunk) === false) {
                    fclose($out);
                    gzclose($in);
                    throw new \RuntimeException('decompress/write failed');
                }
            }
            fclose($out);
            gzclose($in);

            $phar = new PharData($tar);
            $phar->extractTo($work, null, true);

            // Verify what the server actually SENT, not what we predicted it
            // would send. The pre-download gate reasons about versions; this
            // reads the version out of the extracted package and checks it
            // against the compatibility feed, which is the only check that
            // catches a build this Core cannot run whatever the prediction was.
            //
            // Deliberately BEFORE recurse_copy(): a rejected package leaves the
            // installed Extra completely untouched, so there is no rollback to
            // get wrong. And deliberately a `return`, not a `throw` -- the catch
            // below calls delete_dir($dest) when the copy has not happened, and
            // at this point $dest is the customer's working Extra.
            if (!$skip_version_check) {
                $version_refusal = extra_download_version_refusal($name, $source, $code, $header_version);
                if ($version_refusal !== null) {
                    delete_dir($work);
                    return $version_refusal;
                }
            }

            // From here on $dest may be partially overwritten, which is what the
            // catch below needs to know. Set BEFORE the call, not after.
            $copy_started = true;
            if (!recurse_copy($source, $dest)) {
                throw new \RuntimeException('copy into extras directory failed');
            }
            $copied = true;
        } catch (\Throwable $e) {
            write_debug_log("download_extra: install of '{$name}' failed: " . normalize_log_value($e->getMessage()), 'error');
            delete_dir($work);
            // Remove a half-written Extra so a partial copy isn't later mistaken
            // for installed -- but ONLY when the copy actually began.
            //
            // This used to key on !$copied alone, which is true for every failure
            // in this block, including the ones that happen before $dest is
            // touched at all (mkdir, writing the tgz, gunzip, extractTo). Since
            // download_extra() is also the REINSTALL path, $dest is usually the
            // customer's working Extra: a failed download therefore deleted a
            // perfectly functioning install and left them with nothing. Now a
            // failure that never reached the copy leaves $dest exactly as it was.
            if ($copy_started && !$copied) {
                delete_dir($dest);
            }
            return ['error' => 'install_failed', 'extra_name' => $name, 'reason' => _lang_raw('ExtraInstallExtractFailed', array()), 'retry_after_seconds' => null, 'http_status' => $code];
        }

        // Clean up the temp artifacts; the Extra is now on disk.
        delete_dir($work);

        if (!is_dir($extras_dir . '/' . $name)) {
            write_debug_log("download_extra: '{$name}' did not land on disk after extract", 'error');
            return ['error' => 'install_failed', 'extra_name' => $name, 'reason' => _lang_raw('ExtraInstallExtractFailed', array()), 'retry_after_seconds' => null, 'http_status' => $code];
        }

        // Success: return the bytes per the existing "string == installed" contract.
        return $body;
    }

    $err = parse_download_extra_error($code, $body);
    write_debug_log("download_extra failed: {$err['error']} for '{$name}' (HTTP {$code})", 'warning');
    return $err;
}

/**************************
 * FUNCTION: RECURSE COPY *
 **************************/
function recurse_copy($src, $dst) {
    // Get the source directory
    $dir = opendir($src);
    $result = ($dir === false ? false : true);

    // If the source exists
    if ($result !== false){
        // If the destination does not exist
        if (!is_dir($dst))
        {
            // Create the destination
            $result = @mkdir($dst);
        }

        // If the destination exists
        if ($result === true){
            // Iterate through the source directory
            while(false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' ) && $result) {
                    // If it is a directory
                    if ( is_dir($src . '/' . $file) ) {
                        // Recursive copy the files in it
                        $result = recurse_copy($src . '/' . $file,$dst . '/' . $file);
                    }
                    // Otherwise, just copy the files
                    else {
                        $result = copy($src . '/' . $file,$dst . '/' . $file);
                    }
                }
            }
            // Close the directory
            closedir($dir);
        }
    }
    // Return a success or failure
    return $result;
}


/**
 * Just a small helper function to be able to have the exact same json response format we have everywhere else
 * without using the rest of the logic the json_response() function have.
 *
 *
 * @param int $status Status code of the response
 * @param string $status_message Status message
 * @param array $data Additional data
 * @return array The response as an array in the format of ['status' => $status, 'status_message' => $status_message, 'data' => $data]
 */
 function create_json_response_array($status, $status_message, $data=array()) {
     return ['status' => $status, 'status_message' => $status_message, 'data' => $data];
 }

/***************************
 * FUNCTION: JSON RESPONSE *
 ***************************/
function json_response($status, $status_message, $data=array())
{
	// HTTP Header
	header("HTTP/1.1 $status");
	header("Content-Type: application/json");

	// Response
	$response = create_json_response_array($status, $status_message, $data);

	// JSON Response fixing any invalid utf8 characters
	$json_response = json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);

	// Display the response
	echo $json_response;
    exit;
}

/******************************************
 * FUNCTION: CALL EXTRA API FUNCTIONALITY *
 ******************************************/
function call_extra_api_functionality($extra, $functionality, $target) {

    $uri = "";

    if ($extra === 'upgrade') {
        if ($functionality === 'upgrade') {
            $uri .= 'upgrade/';
            switch($target) {
                case 'app':
                    $uri .= 'app';
                    break;
                case 'core_app':
                    $uri .= 'simplerisk/app';
                    break;
                case 'core_db':
                    $uri .= 'simplerisk/db';
                    break;
                default: // return false on invalid target
                    return false;
            }
        } elseif ($functionality === 'backup') {
            $uri .= 'backup/';
            switch($target) {
                case 'app':
                    $uri .= 'app';
                    break;
                case 'db':
                    $uri .= 'db';
                    break;
                default: // return false on invalid target
                    return false;
            }
        } elseif ($functionality === 'version') {
            $uri .= 'version';
            switch($target) {
                case 'app':
                    $uri .= '/app';
                    break;
            }
        } else {
            // return false on invalid functionality
            return false;
        }
    } else {
        if ($functionality === 'upgrade') {
            $uri .= 'upgrade/';
            switch($target) {
                case 'app':
                    $uri .= 'app';
                    break;
                case 'db':
                    $uri .= 'db';
                    break;
                default: // return false on invalid target
                    return false;
            }
        } else {
            // extras other than the 'upgrade' only have the upgrade functionality
            return false;
        }
    }

    $url = build_url("api", $extra, $uri);

    // If SSL certificate checks are enabled
    if (get_setting('ssl_certificate_check_simplerisk') == 1)
    {
        // Verify the SSL host and peer
        $validate_ssl = true;
    }
    else
    {
        // Do not verify the SSL host and peer
        $validate_ssl = false;
    }

    // Auth for the loopback call is the forwarded session cookie. This function is
    // reached only from one_click_upgrade(), which the "Upgrade SimpleRisk" button
    // drives with a session cookie — so csrf-magic is always loaded here (via
    // is_session_authenticated()) and csrf_get_tokens() is available. The
    // function_exists() guard below is defensive for a hypothetical non-session
    // caller; there is none today (no API-key or step-less caller invokes
    // one_click_upgrade()), and if one appeared the empty token would fail closed
    // (403) rather than downgrade to GET.
    $base_header = [
        "Cookie: " . session_name() . "=" . session_id(),
        "Content-Type: application/json",
        "Accept: application/json",
    ];

    // Single place that issues the loopback request, so method/timeout stay in sync.
    $do_call = function (string $method, array $extra_headers = []) use ($base_header, $validate_ssl, $url) {
        return fetch_url_content("stream", [
            'method'        => $method,
            'header'        => array_merge($base_header, $extra_headers),
            'ignore_errors' => true,
            'timeout'       => 600,
        ], $validate_ssl, $url);
    };

    // Read-only version lookups stay GET (no state change, no CSRF token needed).
    if ($functionality === 'version') {
        $result = $do_call('GET');
        return is_array($result) ? [$result['return_code'], json_decode($result['response'], true)] : [0, null];
    }

    // SR-1912: the state-changing backup/upgrade endpoints are POST now, so
    // csrf-magic validates a token. Send a session-derived CSRF token and POST.
    // csrf_get_tokens() is stateless (derived from session_id()) so it is safe to
    // call after session_write_close().
    $csrf_token = function_exists('csrf_get_tokens') ? csrf_get_tokens() : '';
    $result = $do_call('POST', ["CSRF-TOKEN: " . $csrf_token]);

    // The GET fallback covers exactly ONE skew direction: this (post-fix) Core
    // caller reaching an Extra whose routes are still GET-only — a POST to a
    // GET-only Leaf route returns 404 (Leaf has no 405 handler; 405 is
    // future-proofing). The REVERSE skew — the Extra self-upgrading to POST-only
    // routes mid-script while this in-memory caller is still the old GET-only code
    // — cannot be self-healed here (PHP can't hot-swap the loaded caller). That
    // reverse case only arises for a step-less caller of one_click_upgrade(); the
    // "Upgrade SimpleRisk" button's two-phase split (download the Extra in phase 1,
    // then reload before backup/upgrade) avoids it, and no other caller exists.
    if (is_array($result) && in_array((int)$result['return_code'], [404, 405], true)) {
        $result = $do_call('GET');
    }

    if (!is_array($result)) {
        return [0, null];
    }
    return [$result['return_code'], json_decode($result['response'], true)];
}

/******************************************
 * FUNCTION: CALL SIMPLERISK API ENDPOINT *
 ******************************************/
function call_simplerisk_api_endpoint($endpoint, $method = "GET", $system_token = false, $timeout = 600)
{
    // If no system token was provided
    if (!$system_token)
    {
        // Try to use a cookie for authentication
        $authentication = "Cookie: " . session_name() . "=" . session_id();
    }
    // If a system token was provided
    else
    {
        // Send the token for authentication
        $authentication = "X-SYSTEM-TOKEN: {$system_token}";
    }

    $url = build_url($endpoint);
    //error_log("URL: " . json_encode($url));
    $http_options = [
        'method' => $method,
        'header' => [
            $authentication,
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        'ignore_errors' => true,
        'timeout' => $timeout,
    ];

    // If SSL certificate checks are enabled
    if (get_setting('ssl_certificate_check_simplerisk') == 1)
    {
        // Verify the SSL host and peer
        $validate_ssl = true;
    }
    else
    {
        // Do not verify the SSL host and peer
        $validate_ssl = false;
    }

    //error_log("url: " . json_encode($url));
    //error_log("context: " . json_encode($context));
    $result = fetch_url_content("stream", $http_options, $validate_ssl, $url);
    //error_log("header: " . json_encode($http_response_header));
    //error_log("result: " . json_encode($result));

    if (!is_array($result)) {
        return null;
    }

    // If we got a successful result
    if ($result['return_code'] == 200)
    {
        // Return the data array
        // @phan-suppress-next-line PhanTypeArraySuspiciousNullable -- json_decode of valid 200 response should be array; null gracefully degrades
        return json_decode($result['response'], true)['data'];
    }
    // Otherwise return an empty array
    else return [];
}

/******************************
 * FUNCTION: GET SYSTEM TOKEN *
 ******************************/
function get_system_token()
{
    // Generate a 100 character system token
    $token = generate_token(100);

    // Open a database connection
    $db = db_open();

    // Insert the token into the system_tokens table
    $stmt = $db->prepare("INSERT IGNORE INTO `system_tokens` (`token`) VALUES (:token);");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Return the token
    return $token;
}

/********************************
 * FUNCTION: CHECK SYSTEM TOKEN *
 ********************************/
function check_system_token()
{
    // Get the HTTP Headers for the request
    $headers = getallheaders();

    // If a system token was provided
    if (isset($headers['X-SYSTEM-TOKEN']))
    {
        // Open a database connection
        $db = db_open();

        // Delete system tokens over a minute old
        $stmt = $db->prepare("DELETE FROM `system_tokens` WHERE timestamp < (NOW() - INTERVAL 1 MINUTE);");
        $stmt->execute();

        // Atomically consume the token by deleting it in a single operation.
        // If the DELETE affects exactly one row, the token was valid; if zero rows
        // are affected it was already used or never existed. This eliminates the
        // SELECT/DELETE race that would otherwise allow two concurrent requests to
        // both pass the SELECT before either DELETE executes.
        $stmt = $db->prepare("DELETE FROM `system_tokens` WHERE token=:token;");
        $stmt->bindParam(":token", $headers['X-SYSTEM-TOKEN'], PDO::PARAM_STR);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        // If we deleted a matching token
        if ($stmt->rowCount() > 0)
        {
            // Return true
            return true;
        }
    }

    // If we get back to this point, return false
    return false;
}

?>