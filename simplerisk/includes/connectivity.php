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
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: URL: {$url}");
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: HTTP Options:");
    write_debug_log($http_options);

    // If validate_ssl is true
    if ($validate_ssl)
    {
        write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Validating SSL certificates");
    }
    else write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: SSL certificate validation is disabled");

    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Parameters");
    write_debug_log($parameters);

    // Call the proper function based on the specified connection
    switch ($connection)
    {
        case "curl":
            write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Fetching URL content via curl");
            $results = fetch_url_content_via_curl($http_options, $validate_ssl, $url, $parameters);
            break;
        case "stream":
            write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content]: Fetching URL content via stream");
            $results = fetch_url_content_via_stream($http_options, $validate_ssl, $url, $parameters);
            break;
        default:
            $results = false;
    }

    // Return the results
    return $results;
}

function fetch_url_content_via_curl($http_options, $validate_ssl, $url, $parameters)
{
    // Get the http request method
    $request_method = $http_options['method'];

    // Get the http header
    $header = $http_options['header'];

    // Initialize a curl request
    $ch = curl_init();

    // Set the curl header
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    // Configure the curl for proxy if one exists
    configure_curl_proxy($ch);

    // Follow Location headers that the server sends
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Return the transfer as a string of the return value instead of outputting it directly
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // If a timeout is set
    if (isset($http_options['timeout'])) {
        // If the timeout is set to 0
        if ($http_options['timeout'] === 0) {
            // Allow this to run for an unlimited amount of time
            curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        } // If the timeout is set to 1, this is an asynchronous call
        else if ($http_options['timeout'] === 1) {
            // Set a 1 second timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        } // Otherwise
        else {
            // Set the specified timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, $http_options['timeout']);
        }
    }
    // If a timeout is not set use the default value and expect the result returned
    else curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Do not include the header in the output
    curl_setopt($ch, CURLOPT_HEADER, false);

    // If we are supposed to validate SSL certificates
    if ($validate_ssl)
    {
        // Verify the SSL host and peer
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    }
    else
    {
        // Do not verify the SSL host and peer
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    // Time out after 1 second of trying to connect
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

    // Turn the parameters into an HTTP query
    $fields = http_build_query($parameters, '', '&');

    // If this is a POST request
    if ($request_method == "POST")
    {
        // Set the POST curl options
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }

    // Add the fields to the URL regardless of GET or POST
    $url .= "?" . $fields;

    // Set the URL
    curl_setopt($ch, CURLOPT_URL,$url);

    // Make the curl request
    $response = curl_exec($ch);

    // Get the return code
    $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_curl]: Return code: {$return_code}");
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_curl]:  Response: {$response}");

    // If there was a curl error
    if(curl_errno($ch))
    {
        write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_curl]: Curl Error: " . curl_error($ch));
    }

    // Close the curl session
    curl_close($ch);

    // Create an array with the response and return code
    $result = [
      'return_code' => (int)$return_code,
      'response' => $response,
    ];

    // Return the result
    return $result;
}

function fetch_url_content_via_stream($http_options, $validate_ssl, $url, $parameters)
{
    // Create the default options array
    $opts = [];

    // Add the HTTP options to the options array
    $opts['http']['method'] = (isset($http_options['method']) ? $http_options['method'] : "GET");
    $opts['http']['timeout'] = (isset($http_options['timeout']) ? $http_options['timeout'] : 600);

    // Create an empty header string
    $opts['http']['header'] = "";

    // If a header is sent
    if (isset($http_options['header']))
    {
        // Create the header as a string
        $header = $http_options['header'];
        foreach ($header as $option)
        {
            $opts['http']['header'] .= $option . "\r\n";
        }
    }

    // If we are supposed to validate SSL certificates
    if ($validate_ssl)
    {
        // Verify the SSL host and peer
        $opts['ssl'] = array(
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        );
    }
    else
    {
        // Do not verify the SSL host and peer
        $opts['ssl'] = array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        );
    }

    // If there are parameters provided
    if (!empty($parameters))
    {
        // Turn the parameters into an HTTP query
        $opts['http']['content'] = http_build_query($parameters);
    }


    // If proxy web requests is set
    if (get_setting("proxy_web_requests"))
    {
        write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_stream]: Proxy web requests is enabled");

        // Get the proxy configuration
        $proxy_verify_ssl_certificate = get_setting("proxy_verify_ssl_certificate");
        $proxy_host = get_setting("proxy_host");
        $proxy_port = get_setting("proxy_port");
        $proxy_authenticated = get_setting("proxy_authenticated");

        // Add the proxy to the HTTP context
        $opts['http']['proxy'] = "tcp://$proxy_host:$proxy_port";
        $opts['http']['ignore_errors'] = true;
        $opts['http']['request_fulluri'] = true;

        // If we want to turn off ssl verification
        if (!$proxy_verify_ssl_certificate)
        {
            $opts['ssl']['verify_peer'] = false;
            $opts['ssl']['verify_peer_name'] = false;
            $opts['ssl']['allow_self_signed'] = true;
        }

        // If this is an authenticated proxy
        if ($proxy_authenticated)
        {
            write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_stream]: We are using an authenticated proxy");

            // Create the BASE64 encoded credentials
            $proxy_user = get_setting("proxy_user");
            $proxy_pass = get_setting("proxy_pass");
            $auth = base64_encode("$proxy_user:$proxy_pass");

            // If a HTTP header is already set
            if (isset($opts['http']['header']))
            {
                // Append the proxy authentication to the header
                $opts['http']['header'] .= "\r\nProxy-Authorization: Basic $auth";
            }
            // Otherwise add the authenticated header to the http_context
            else $opts['http']['header'] = "Proxy-Authorization: Basic $auth";
        }
    }

    // Create the stream context
    $context = stream_context_create($opts);

    // Fetch the data
    $response = file_get_contents($url, false, $context);

    // If a response was provided
    if ($response != false)
    {
        // Get the return code
        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        $return_code = (int)$match[1];
    }
    else $return_code = false;

    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_stream]: Return code: {$return_code}");
    write_debug_log("CONNECTIVITY: FUNCTION[fetch_url_content_via_stream]:  Response: {$response}");

    // Create an array with the response and return code
    $result = [
        'return_code' => $return_code,
        'response' => $response,
    ];

    // Return the result
    return $result;
}


?>