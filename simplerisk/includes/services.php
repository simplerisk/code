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
 *
 * @return string|array Raw tgz bytes (string) on HTTP 200. On any error, an
 *                      associative array {error, extra_name, reason,
 *                      retry_after_seconds, http_status}. Callers MUST check
 *                      is_string($result) to distinguish success from failure.
 *                      A boolean truthy check is broken since the error array
 *                      is non-empty and therefore truthy.
 */
function download_extra($name, $streamed_response = false) {
    require_once(realpath(__DIR__ . '/licensing.php'));

    $instance_id      = get_setting('instance_id');
    $services_api_key = get_setting('services_api_key');

    // Defense-in-depth: refuse to ship a name Core doesn't recognize.
    if (!in_array($name, available_extra_short_names(), true)) {
        write_debug_log("download_extra: unknown Extra '{$name}'", 'warning');
        return ['error' => 'invalid_extra_name', 'extra_name' => $name, 'reason' => null, 'retry_after_seconds' => null, 'http_status' => 0];
    }

    $parameters = [
        'instance_id'      => $instance_id,
        'services_api_key' => $services_api_key,
        'extra_name'       => $name,
    ];

    $http_options = [
        'method' => 'POST',
        'header' => ['Content-Type: application/x-www-form-urlencoded'],
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

    if ($code === 200) {
        // Integrity check before install: verify the downloaded bytes hash to
        // the SHA-256 the licensing service advertised for this Extra (cached
        // from /license/check). A tampered or truncated package must never reach
        // a caller that would extract/install it. When no expected hash is known
        // (forward-compat / older server), skip rather than block.
        $expected_sha256 = get_download_sha256_for_extra($name);
        if (!extra_download_is_intact($expected_sha256, $body)) {
            write_debug_log("download_extra: SHA-256 mismatch for '{$name}' — refusing to install", 'error');
            return ['error' => 'integrity', 'extra_name' => $name, 'reason' => _lang('ExtraIntegrityCheckFailed', array(), false), 'retry_after_seconds' => null, 'http_status' => $code];
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
            return ['error' => 'install_failed', 'extra_name' => $name, 'reason' => _lang('ExtraInstallWriteFailed', array(), false), 'retry_after_seconds' => null, 'http_status' => $code];
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
            if (!recurse_copy($source, $dest)) {
                throw new \RuntimeException('copy into extras directory failed');
            }
            $copied = true;
        } catch (\Throwable $e) {
            write_debug_log("download_extra: install of '{$name}' failed: " . normalize_log_value($e->getMessage()), 'error');
            delete_dir($work);
            // Remove a half-written Extra so a partial copy isn't later mistaken for installed.
            if (!$copied) {
                delete_dir($dest);
            }
            return ['error' => 'install_failed', 'extra_name' => $name, 'reason' => _lang('ExtraInstallExtractFailed', array(), false), 'retry_after_seconds' => null, 'http_status' => $code];
        }

        // Clean up the temp artifacts; the Extra is now on disk.
        delete_dir($work);

        if (!is_dir($extras_dir . '/' . $name)) {
            write_debug_log("download_extra: '{$name}' did not land on disk after extract", 'error');
            return ['error' => 'install_failed', 'extra_name' => $name, 'reason' => _lang('ExtraInstallExtractFailed', array(), false), 'retry_after_seconds' => null, 'http_status' => $code];
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