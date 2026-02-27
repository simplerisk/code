<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

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
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: URL: {$url}", "info");
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: HTTP Options:", "debug");
    write_debug_log($http_options);

    // If validate_ssl is true
    if ($validate_ssl)
    {
        write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Validating SSL certificates", "debug");
    }
    else write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: SSL certificate validation is disabled", "debug");

    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Parameters", "debug");
    write_debug_log($parameters, "debug");

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

function fetch_url_content_via_curl($http_options, $validate_ssl, $url, $parameters, $max_retries = 3)
{
    $request_method = $http_options['method'] ?? 'GET';
    $header = $http_options['header'] ?? [];
    $timeout = $http_options['timeout'] ?? 600;

    $response = false;
    $attempt = 0;
    $return_code = false;

    while ($attempt < $max_retries && $response === false) {
        $ch = curl_init();

        // Common curl options
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // slightly longer for network retries
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        // SSL validation
        if ($validate_ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        // Configure proxy if needed
        configure_curl_proxy($ch);

        // Encode parameters only if it's an array and method is POST
        if (strtoupper($request_method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);

            if (is_array($parameters)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters, '', '&'));
            } else {
                // already a string (e.g., QPS XML)
                curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
            }

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
            curl_close($ch);
            sleep(1); // small delay before retry
        } else {
            curl_close($ch);
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

    return [
        'return_code' => $return_code,
        'response' => $response,
    ];
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

    // POST/PUT parameters
    if (!empty($parameters)) {
        $opts['http']['content'] = http_build_query($parameters);
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

    // Determine HTTP response code if available
    if (!empty($http_response_header) && preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match)) {
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