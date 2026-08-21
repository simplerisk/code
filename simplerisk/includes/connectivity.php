<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/******
 * FUNCTION: REDACT SECRETS FOR LOG
 * Returns a copy of $data with credential values masked, for safe debug logging.
 * Masks the values of known secret keys in arrays (recursively) and inside any
 * JSON-string request body (e.g. the 'content' field of an HTTP options array).
 * Never mutates the caller's data.
 */
function redact_secrets_for_log($data)
{
    static $secret_keys = ['services_api_key', 'api_key', 'proxy_pass', 'proxy_password', 'password'];

    if (is_array($data)) {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $secret_keys, true)) {
                $out[$key] = '***REDACTED***';
            } else {
                $out[$key] = redact_secrets_for_log($value);
            }
        }
        return $out;
    }

    if (is_string($data) && $data !== '') {
        // Mask a whole HTTP header line whose name is sensitive. Header entries in an
        // http_options 'header' array are POSITIONAL strings ("Cookie: ...",
        // "CSRF-TOKEN: ...", "Authorization: ...") stored under integer keys, so the
        // key-based redaction above never sees them (SR-1912). Keep the header name
        // for debug context; redact its value.
        if (preg_match('/^\s*(Cookie|Set-Cookie|CSRF-TOKEN|X-CSRF-TOKEN|Authorization|Proxy-Authorization|X-API-KEY)\s*:/i', $data)) {
            return preg_replace('/^(\s*[A-Za-z][A-Za-z0-9-]*\s*:\s*).*$/s', '${1}***REDACTED***', $data);
        }
        // Mask secrets embedded in a JSON or form-encoded string body. (The array-key
        // redaction above is the primary control — it masks before json_encode; this is
        // best-effort defense in depth for an already-serialized body.)
        foreach ($secret_keys as $sk) {
            // "key":"value"  (JSON) — value matcher consumes \" escapes so a value
            // containing an escaped quote can't leak its tail past the redaction.
            $data = preg_replace('/("' . preg_quote($sk, '/') . '"\s*:\s*")(?:\\\\.|[^"\\\\])*(")/i', '${1}***REDACTED***${2}', $data);
            // key=value  (form-encoded)
            $data = preg_replace('/(\b' . preg_quote($sk, '/') . '=)[^&\s]*/i', '${1}***REDACTED***', $data);
        }
    }

    return $data;
}

/******
 * FUNCTION: FETCH URL CONTENT
 * @param $connection
 * @param $http_options
 * @param $validate_ssl
 * @param $url
 * @param $parameters
 * @return $results
 */
function fetch_url_content($connection = "curl", $http_options = [], $validate_ssl = true, $url = null, $parameters = [])
{
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: URL: {$url}", "debug");
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: HTTP Options:", "debug");
    // Redact credentials (services_api_key etc.) before logging — the licensing client
    // sends them in the request body / parameters, and debug logs must never leak secrets.
    write_debug_log(redact_secrets_for_log($http_options), "debug");

    // If validate_ssl is true
    if ($validate_ssl)
    {
        write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Validating SSL certificates", "debug");
    }
    else write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: SSL certificate validation is disabled", "debug");

    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Parameters", "debug");
    write_debug_log(redact_secrets_for_log($parameters), "debug");

    // Call the proper function based on the specified connection
    switch ($connection)
    {
        case "curl":
            write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Fetching URL content via curl", "debug");
            $results = fetch_url_content_via_curl($http_options, $validate_ssl, $url, $parameters);
            break;
        case "stream":
            write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Fetching URL content via stream", "debug");
            $results = fetch_url_content_via_stream($http_options, $validate_ssl, $url, $parameters);
            break;
        default:
            $results = false;
    }

    // Return the results
    return $results;
}

/**
 * Resolve the request body for a POST/PUT transport. A raw, pre-encoded body
 * supplied via $http_options['content'] (e.g. a JSON payload) takes precedence
 * and is returned verbatim — the caller is responsible for the matching
 * Content-Type header. Otherwise an array $parameters is form-encoded and a
 * string $parameters is returned as-is (e.g. QPS XML). Pure helper so the
 * body-selection contract is unit-testable without making a network call.
 *
 * Regression guard: the licensing client sends its JSON body via
 * $http_options['content']; before this was honored, the body was dropped and
 * the licensing service rejected every request as malformed.
 */
function resolve_request_body($http_options, $parameters)
{
    if (isset($http_options['content'])) {
        return $http_options['content'];
    }
    if (is_array($parameters)) {
        return http_build_query($parameters, '', '&');
    }
    return $parameters;
}

/**
 * Fold one raw response-header line into an accumulating name => value map.
 * Pure — no curl handle, no I/O — so the parsing is unit-testable while the
 * CURLOPT_HEADERFUNCTION closure that feeds it stays trivial.
 *
 * Names are lowercased so callers can look up a header without guessing the
 * casing the server used (HTTP header names are case-insensitive).
 *
 * A status line resets the map. CURLOPT_FOLLOWLOCATION is on by default and every
 * redirect hop emits its own header block, so without the reset a header present
 * only on an intermediate 302 would survive into the final response's headers.
 *
 * @param array<string, string> $headers Accumulated headers so far.
 * @param string                $line    One raw header line, CRLF included.
 *
 * @return array<string, string>
 */
function parse_curl_header_line(array $headers, string $line): array
{
    if (stripos($line, 'HTTP/') === 0) {
        return [];
    }

    // limit 2 splits on the FIRST colon only, so a value that itself contains a
    // colon (a URL, a timestamp) is preserved intact.
    $parts = explode(':', $line, 2);
    if (count($parts) !== 2) {
        // The blank CRLF that terminates the block, or a malformed line.
        return $headers;
    }

    $name = strtolower(trim($parts[0]));
    if ($name === '') {
        return $headers;
    }

    $headers[$name] = trim($parts[1]);
    return $headers;
}

function fetch_url_content_via_curl($http_options, $validate_ssl, $url, $parameters, $max_retries = 3)
{
    $request_method = $http_options['method'] ?? 'GET';
    $header = $http_options['header'] ?? [];
    $timeout = $http_options['timeout'] ?? 600;

    $response = false;
    $attempt = 0;
    $return_code = false;

    // Opt-in response-header capture. Off by default so every existing caller's
    // return shape is unchanged: the 'headers' key appears only when asked for.
    // Used by download_extra() to read the licensing service's X-SHA256 and
    // X-Extra-Version before it writes a byte to disk.
    $capture_headers = !empty($http_options['capture_headers']);
    $captured_headers = [];

    while ($attempt < $max_retries && $response === false) {
        $ch = curl_init();

        // Collect response headers into a lowercased name => value map. Reset per
        // attempt so a retry does not inherit a failed attempt's headers, and
        // cleared on a redirect so only the final response's headers survive
        // (CURLOPT_FOLLOWLOCATION is on by default, and each hop emits a block).
        if ($capture_headers) {
            $captured_headers = [];
            // The closure stays thin on purpose: it can only run inside a live
            // transfer, so the parsing it would otherwise contain lives in
            // parse_curl_header_line(), which is pure and unit-tested. curl
            // requires the byte count back, which is the only work left here.
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$captured_headers) {
                $captured_headers = parse_curl_header_line($captured_headers, $line);
                return strlen($line);
            });
        }

        // Common curl options
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        // Restrict to http/https on both the initial request and any redirect, so a
        // response can never redirect the transfer onto file://, gopher://, etc.
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        // Redirect-following defaults to on (existing callers rely on it); an SSRF-
        // sensitive caller (e.g. the workflow HTTP Request action) passes
        // 'follow_redirects' => false so an allowed host can't 302 onto an internal one.
        $follow_redirects = array_key_exists('follow_redirects', $http_options)
            ? (bool)$http_options['follow_redirects'] : true;
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow_redirects);
        // Optional IP pin ("host:port:ip" entries) so curl connects to a pre-validated
        // IP instead of re-resolving DNS at connect time (DNS-rebinding defence).
        if (!empty($http_options['resolve']) && is_array($http_options['resolve'])) {
            curl_setopt($ch, CURLOPT_RESOLVE, $http_options['resolve']);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // slightly longer for network retries
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        // SSL validation
        if ($validate_ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $ca_bundle = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
            if ($ca_bundle) {
                curl_setopt($ch, CURLOPT_CAINFO, $ca_bundle);
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        // Configure proxy if needed
        configure_curl_proxy($ch);

        // Encode parameters only if method is POST. A raw body in
        // $http_options['content'] takes precedence over $parameters
        // (see resolve_request_body()).
        if (strtoupper($request_method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, resolve_request_body($http_options, $parameters));
        } else {
            // GET request
            if (is_array($parameters) && !empty($parameters)) {
                $url .= '?' . http_build_query($parameters);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        // Set URL for POST too
        if (strtoupper($request_method) === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        // Execute the request
        $response = @curl_exec($ch);
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            write_debug_log("CONNECTIVITY: Attempt " . ($attempt+1) . " failed for URL: $url. Curl Error: " . curl_error($ch), "warning");
            $response = false;
            $attempt++;
            sleep(1); // small delay before retry
        } else {
            break;
        }
    }

    // If response is still false, return empty array to avoid breaking foreach
    if ($response === false) {
        write_debug_log("CONNECTIVITY: Failed to fetch URL after $max_retries attempts: $url", "error");
        $response = [];
    }

    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_curl]: Return code: {$return_code}", "debug");
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_curl]: Response: " . (is_array($response) ? '[]' : $response), "debug");

    $result = [
        'return_code' => $return_code,
        'response' => $response,
    ];

    if ($capture_headers) {
        $result['headers'] = $captured_headers;
    }

    return $result;
}

function fetch_url_content_via_stream($http_options, $validate_ssl, $url, $parameters, $max_retries = 3)
{
    $opts = [];

    // HTTP options
    $opts['http']['method'] = $http_options['method'] ?? 'GET';
    $opts['http']['timeout'] = $http_options['timeout'] ?? 600;
    $opts['http']['ignore_errors'] = true;
    $opts['http']['header'] = '';

    if (!empty($http_options['header']) && is_array($http_options['header'])) {
        foreach ($http_options['header'] as $option) {
            $opts['http']['header'] .= $option . "\r\n";
        }
    }

    // SSL options
    $opts['ssl'] = [
        'verify_peer' => $validate_ssl,
        'verify_peer_name' => $validate_ssl,
        'allow_self_signed' => !$validate_ssl,
    ];
    if ($validate_ssl) {
        $ca_bundle = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if ($ca_bundle) {
            $opts['ssl']['cafile'] = $ca_bundle;
        }
    }

    // POST/PUT body. A raw, pre-encoded body supplied via
    // $http_options['content'] (e.g. JSON) is sent verbatim and takes
    // precedence over $parameters; otherwise form-encode $parameters.
    // Shares resolve_request_body() with the curl transport so both honor
    // the same body contract.
    if (isset($http_options['content']) || !empty($parameters)) {
        $opts['http']['content'] = resolve_request_body($http_options, $parameters);
    }

    // Proxy settings
    if (get_setting("proxy_web_requests")) {
        $proxy_host = get_setting("proxy_host");
        $proxy_port = get_setting("proxy_port");
        $opts['http']['proxy'] = "tcp://$proxy_host:$proxy_port";
        $opts['http']['request_fulluri'] = true;

        if (!get_setting("proxy_verify_ssl_certificate")) {
            $opts['ssl']['verify_peer'] = false;
            $opts['ssl']['verify_peer_name'] = false;
            $opts['ssl']['allow_self_signed'] = true;
        }

        if (get_setting("proxy_authenticated")) {
            $auth = base64_encode(get_setting("proxy_user") . ":" . get_setting("proxy_pass"));
            $opts['http']['header'] .= "Proxy-Authorization: Basic $auth\r\n";
        }
    }

    $context = stream_context_create($opts);

    $response = false;
    $attempt = 0;
    $return_code = false;

    // Retry loop
    while ($attempt < $max_retries && $response === false) {
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $attempt++;
            write_debug_log("CONNECTIVITY: Attempt {$attempt} failed for URL: $url", "warning");
            sleep(1); // small delay before retry
        }
    }

    // Determine HTTP response code if available. PHP 8.5 deprecated the
    // predefined $http_response_header magic variable in favor of
    // http_get_last_response_headers() (added in PHP 8.4). Prefer the new
    // function when available; fall back to the magic variable on PHP < 8.4.
    // Phan runs against the PHP 8.3 floor and does not know about the new
    // function (added in 8.4), so the function_exists() and call_user_func()
    // lines are suppressed individually.
    // @phan-suppress-next-line PhanUndeclaredFunctionInCallable
    if (function_exists('http_get_last_response_headers')) {
        // @phan-suppress-next-line PhanUndeclaredFunctionInCallable
        $response_headers = call_user_func('http_get_last_response_headers');
    } else {
        $response_headers = $http_response_header ?? null;
    }
    if (!empty($response_headers) && preg_match('{HTTP\/\S*\s(\d{3})}', $response_headers[0], $match)) {
        $return_code = (int)$match[1];
    }

    // Ensure we always return an array for downstream foreach()
    if ($response === false) {
        write_debug_log("CONNECTIVITY: Failed to fetch URL: $url", "error");
        $response = [];
    }

    return [
        'return_code' => $return_code,
        'response' => $response,
    ];
}

?>