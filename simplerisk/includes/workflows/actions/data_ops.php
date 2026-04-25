<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/***********************************************
 * FUNCTION: WORKFLOW ACTION — HTTP REQUEST    *
 ***********************************************/
function workflow_action_http_request(array $inputs, array $context): array
{
    $dry_run      = (bool)($inputs['_dry_run'] ?? false);
    $url          = trim($inputs['url']     ?? '');
    $method       = strtoupper(trim($inputs['method'] ?? 'GET'));
    $headers      = $inputs['headers'] ?? '';
    $body         = $inputs['body']    ?? '';
    $timeout      = max(1, (int)($inputs['timeout'] ?? 10));
    $response_var = trim($inputs['response_variable'] ?? '');

    if (empty($url)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'http_request: url is required.'];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['status' => 'failed', 'output' => [], 'error' => "http_request: Invalid URL '{$url}'."];
    }

    if (!in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
        return ['status' => 'failed', 'output' => [], 'error' => "http_request: Only http and https URLs are permitted."];
    }

    // Parse optional JSON headers
    $parsed_headers = [];
    if (!empty($headers)) {
        $header_array = json_decode($headers, true);
        if (is_array($header_array)) {
            foreach ($header_array as $k => $v) {
                $parsed_headers[] = "{$k}: {$v}";
            }
        }
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: http_request method={$method} url={$url}", 'info');
        return [
            'status' => 'success',
            'output' => ['dry_run' => true, 'url' => $url, 'method' => $method],
            'error'  => null,
        ];
    }

    $http_options = ['method' => $method, 'header' => $parsed_headers, 'timeout' => $timeout];
    $validate_ssl = (get_setting('ssl_certificate_check_external') == 1);
    $response     = fetch_url_content("curl", $http_options, $validate_ssl, $url, $body ?: '');
    $return_code   = $response['return_code'] ?? 0;
    $response_body = is_string($response['response'] ?? '') ? ($response['response'] ?? '') : '';

    // Attempt to decode JSON response body
    $decoded_body = json_decode($response_body, true);
    $output_body  = (json_last_error() === JSON_ERROR_NONE) ? $decoded_body : $response_body;

    $output = [
        'http_status' => $return_code,
        'body'        => $output_body,
    ];

    // Store as named variable if requested
    if (!empty($response_var)) {
        $output[$response_var] = $output_body;
    }

    $success = ($return_code >= 200 && $return_code < 300);
    write_debug_log("WORKFLOW: http_request to {$url} returned HTTP {$return_code}", $success ? 'info' : 'error');

    return [
        'status' => $success ? 'success' : 'failed',
        'output' => $output,
        'error'  => $success ? null : "http_request: HTTP {$return_code} response from {$url}",
    ];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — LOG AUDIT TRAIL *
 ***********************************************/
function workflow_action_log_audit_trail(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $message = strip_tags(trim($inputs['message'] ?? ''));

    if (empty($message)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'log_audit_trail: message is required.'];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: log_audit_trail message=" . substr($message, 0, 100), 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'message' => $message], 'error' => null];
    }

    // Write to SimpleRisk audit log using the workflow system user (ID 0 = system)
    $risk_id = (int)($context['risk_id'] ?? 0);
    $user_id = 0; // System-generated log entry

    write_log($risk_id > 0 ? ($risk_id + 1000) : 0, $user_id, "[Workflow] " . $message);

    write_debug_log("WORKFLOW: log_audit_trail recorded.", 'info');
    return ['status' => 'success', 'output' => ['message' => $message], 'error' => null];
}
