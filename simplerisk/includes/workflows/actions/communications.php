<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SEND EMAIL      *
 *                                             *
 * Inputs:                                     *
 *   to          — comma-separated list of:    *
 *                 raw email, numeric user ID, *
 *                 'risk_owner', or            *
 *                 'risk_submitter'            *
 *   template_id — (optional) load subject +  *
 *                 body from saved template.   *
 *                 Only resolvable when the    *
 *                 Workflows Extra's template  *
 *                 table exists.               *
 *   subject     — subject line; supports     *
 *                 {{variables}}              *
 *   body        — HTML body; supports        *
 *                 {{variables}}              *
 ***********************************************/
function workflow_action_send_email(array $inputs, array $context): array
{
    $dry_run     = (bool)($inputs['_dry_run'] ?? false);
    $to_raw      = trim($inputs['to']           ?? '');
    $subject_raw = trim($inputs['subject']      ?? '');
    $body_raw    = trim($inputs['body']         ?? '');
    $template_id = (int)($inputs['template_id'] ?? 0);

    // If subject or body are empty and a template is selected, load from DB.
    // workflow_email_templates is created only by the Workflows Extra.
    if ($template_id > 0 && (empty($subject_raw) || empty($body_raw)) && table_exists('workflow_email_templates'))
    {
        $db   = db_open();
        $stmt = $db->prepare("SELECT `subject`, `body` FROM `workflow_email_templates` WHERE `id` = :id LIMIT 1");
        $stmt->bindParam(':id', $template_id, PDO::PARAM_INT);
        $stmt->execute();
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
        db_close($db);

        if ($tpl) {
            if (empty($subject_raw)) $subject_raw = $tpl['subject'];
            if (empty($body_raw))    $body_raw    = $tpl['body'];
        }
    }

    if (empty($to_raw) || empty($subject_raw) || empty($body_raw))
    {
        return ['status' => 'failed', 'output' => [], 'error' => 'send_email: to, subject, and body are required.'];
    }

    // Enrich context with display-friendly values before variable resolution
    $enriched = workflow_enrich_email_context($context);

    // Resolve {{variables}} in subject and body
    $subject = resolve_workflow_variables($subject_raw, $enriched, []);
    $body    = resolve_workflow_variables($body_raw,    $enriched, []);

    // Resolve recipients
    $recipients = workflow_resolve_email_recipients($to_raw, $enriched);

    if (empty($recipients))
    {
        return ['status' => 'failed', 'output' => [], 'error' => "send_email: Could not resolve any recipients from '{$to_raw}'."];
    }

    if ($dry_run)
    {
        $addresses = array_column($recipients, 'email');
        write_debug_log("WORKFLOW DRY-RUN: send_email to=" . implode(',', $addresses) . " subject={$subject}", 'info');
        return [
            'status' => 'success',
            'output' => ['dry_run' => true, 'to' => $addresses, 'subject' => $subject],
            'error'  => null,
        ];
    }

    require_once(realpath(__DIR__ . '/../../mail.php'));
    $db = db_open();

    foreach ($recipients as $r) {
        send_email($db, $r['name'], $r['email'], $subject, $body);
        write_debug_log("WORKFLOW: send_email queued to {$r['email']}", 'info');
    }

    db_close($db);

    return [
        'status' => 'success',
        'output' => ['to' => array_column($recipients, 'email'), 'subject' => $subject, 'count' => count($recipients)],
        'error'  => null,
    ];
}

/***********************************************
 * HELPER: WORKFLOW ENRICH EMAIL CONTEXT       *
 * All enrichment is now handled centrally by  *
 * enrich_workflow_context() before any action *
 * runs, so the context is already complete.   *
 ***********************************************/
function workflow_enrich_email_context(array $context): array
{
    return $context;
}

/***********************************************
 * HELPER: WORKFLOW RESOLVE EMAIL RECIPIENTS   *
 * Resolves a comma-separated 'to' string into *
 * an array of ['name', 'email'] pairs.        *
 *                                             *
 * Supported token formats:                    *
 *   risk_owner      — context['owner'] user   *
 *   risk_submitter  — context['submitted_by'] *
 *   <integer>       — user ID lookup          *
 *   user@email.com  — literal email           *
 ***********************************************/
function workflow_resolve_email_recipients(string $to_raw, array $context): array
{
    $recipients = [];

    foreach (array_map('trim', explode(',', $to_raw)) as $token)
    {
        if ($token === '') continue;

        if ($token === 'risk_owner') {
            $r = workflow_email_user_by_id((int)($context['owner'] ?? 0));
            if ($r) $recipients[] = $r;
            continue;
        }

        if ($token === 'risk_submitter') {
            $r = workflow_email_user_by_id((int)($context['submitted_by'] ?? 0));
            if ($r) $recipients[] = $r;
            continue;
        }

        if (is_numeric($token)) {
            $r = workflow_email_user_by_id((int)$token);
            if ($r) $recipients[] = $r;
            continue;
        }

        if (filter_var($token, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['name' => '', 'email' => $token];
        }
    }

    // Deduplicate by lowercase email address
    $seen   = [];
    $unique = [];
    foreach ($recipients as $r) {
        $key = strtolower($r['email']);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[]   = $r;
        }
    }

    return $unique;
}

/***********************************************
 * HELPER: WORKFLOW EMAIL USER BY ID           *
 ***********************************************/
function workflow_email_user_by_id(int $uid): ?array
{
    if ($uid <= 0) return null;

    $db   = db_open();
    $stmt = $db->prepare("SELECT `name`, `email` FROM `user` WHERE `value` = :uid AND `enabled` = 1 LIMIT 1");
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);

    if (!$row) return null;

    $email = try_decrypt($row['email']);
    return filter_var($email, FILTER_VALIDATE_EMAIL)
        ? ['name' => try_decrypt($row['name']), 'email' => $email]
        : null;
}

/***********************************************
 * HELPER: WORKFLOW GUARD OUTBOUND URL         *
 * Shared SR-1720 SSRF guard for every         *
 * Communications action that makes an         *
 * outbound HTTP call (send_webhook/_slack/    *
 * _teams). Resolves the host once and blocks  *
 * loopback/private/reserved/cloud-metadata    *
 * destinations unless explicitly named in     *
 * config.php's                                *
 * $workflow_http_allowed_internal_targets —   *
 * the same allow-list workflow_action_http_   *
 * request() uses, so one config knob covers   *
 * every workflow outbound-request action.     *
 * Returns the resolved target, or null when   *
 * disallowed (the caller returns failed).     *
 ***********************************************/
function workflow_guard_outbound_url(string $url, string $action): ?array
{
    $allowed_internal = (isset($GLOBALS['workflow_http_allowed_internal_targets']) && is_array($GLOBALS['workflow_http_allowed_internal_targets']))
        ? $GLOBALS['workflow_http_allowed_internal_targets'] : [];
    $target = safe_outbound_request_target($url, $allowed_internal);
    if ($target === null) {
        // Log only scheme+host, never the full URL: for send_slack/send_teams this
        // is an incoming-webhook URL, which is itself a bearer credential (anyone
        // holding it can post to the channel) — logging it verbatim at 'warning'
        // would leak that secret into the debug log.
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: '(unknown scheme)';
        $host   = parse_url($url, PHP_URL_HOST) ?: '(unparseable host)';
        write_debug_log("WORKFLOW: {$action} blocked a disallowed URL (scheme={$scheme}, host={$host})", 'warning');
    }
    return $target;
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SEND WEBHOOK   *
 ***********************************************/
function workflow_action_send_webhook(array $inputs, array $context): array
{
    $dry_run      = (bool)($inputs['_dry_run'] ?? false);
    $url          = trim($inputs['url']          ?? '');
    $method       = strtoupper(trim($inputs['method'] ?? 'POST'));
    $content_type = trim($inputs['content_type'] ?? 'application/json');
    $headers      = $inputs['headers'] ?? '';
    $body         = $inputs['body']    ?? '';

    if (empty($url)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'send_webhook: url is required.'];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['status' => 'failed', 'output' => [], 'error' => "send_webhook: Invalid URL '{$url}'."];
    }

    if (!in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
        return ['status' => 'failed', 'output' => [], 'error' => "send_webhook: Only http and https URLs are permitted."];
    }

    // SR-1720 SSRF guard — resolve once so a dry-run also reports a disallowed
    // URL, then pin the validated IP on the real request below.
    $target = workflow_guard_outbound_url($url, 'send_webhook');
    if ($target === null) {
        return ['status' => 'failed', 'output' => [], 'error' => _lang_raw('WorkflowHttpRequestDisallowedURL', ['action' => 'send_webhook', 'url' => $url])];
    }

    // Build headers: content-type first, then any additional headers from JSON input
    $parsed_headers = [];
    if (!empty($content_type)) {
        $parsed_headers[] = "Content-Type: {$content_type}";
    }
    if (!empty($headers)) {
        $header_array = json_decode($headers, true);
        if (is_array($header_array)) {
            foreach ($header_array as $k => $v) {
                // Strip CR/LF from both key and value to prevent header injection
                // regardless of the underlying transport layer.
                $k = str_replace(["\r", "\n"], '', (string)$k);
                $v = str_replace(["\r", "\n"], '', (string)$v);
                if ($k !== '') {
                    $parsed_headers[] = "{$k}: {$v}";
                }
            }
        }
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: send_webhook method={$method} url={$url}", 'info');
        return [
            'status' => 'success',
            'output' => ['dry_run' => true, 'url' => $url, 'method' => $method],
            'error'  => null,
        ];
    }

    $http_options = ['method' => $method, 'header' => $parsed_headers, 'follow_redirects' => false];
    // Pin the validated IP (hostname targets only; a literal-IP host has no pin) so
    // an allowed host can't be pivoted to an internal address via DNS-rebinding.
    if (!empty($target['resolve'])) {
        $http_options['resolve'] = [$target['resolve']];
    }
    $validate_ssl = ssl_external_verify_enabled();
    $response     = fetch_url_content("curl", $http_options, $validate_ssl, $url, $body ?: '');
    if (!is_array($response)) {
        $response = [];
    }

    $return_code = $response['return_code'] ?? 0;

    if ($return_code < 200 || $return_code >= 300) {
        return [
            'status' => 'failed',
            'output' => ['http_status' => $return_code],
            'error'  => "send_webhook: HTTP {$return_code} response from {$url}",
        ];
    }

    write_debug_log("WORKFLOW: send_webhook to {$url} returned HTTP {$return_code}", 'info');
    return [
        'status' => 'success',
        'output' => ['http_status' => $return_code, 'url' => $url],
        'error'  => null,
    ];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SEND SLACK      *
 ***********************************************/
function workflow_action_send_slack(array $inputs, array $context): array
{
    $dry_run     = (bool)($inputs['_dry_run'] ?? false);
    $webhook_url = trim($inputs['webhook_url'] ?? '');
    $message     = trim($inputs['message']     ?? '');

    if (empty($webhook_url) || empty($message)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'send_slack: webhook_url and message are required.'];
    }

    if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        return ['status' => 'failed', 'output' => [], 'error' => "send_slack: Invalid webhook URL."];
    }

    if (!in_array(strtolower(parse_url($webhook_url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
        return ['status' => 'failed', 'output' => [], 'error' => "send_slack: Only http and https URLs are permitted."];
    }

    // SR-1720 SSRF guard — see workflow_guard_outbound_url() above. Capture the
    // resolved target (not just the null-check) so the real request below can
    // pin the validated IP the same way send_webhook/http_request do.
    $target = workflow_guard_outbound_url($webhook_url, 'send_slack');
    if ($target === null) {
        return ['status' => 'failed', 'output' => [], 'error' => _lang_raw('WorkflowHttpRequestDisallowedURL', ['action' => 'send_slack', 'url' => $webhook_url])];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: send_slack message=" . substr($message, 0, 50), 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'message' => $message], 'error' => null];
    }

    $payload      = json_encode(['text' => $message]);
    $http_options = ['method' => 'POST', 'header' => ['Content-Type: application/json'], 'follow_redirects' => false];
    // Pin the validated IP (hostname targets only; a literal-IP host has no pin) so
    // an allowed host can't be pivoted to an internal address via DNS-rebinding.
    if (!empty($target['resolve'])) {
        $http_options['resolve'] = [$target['resolve']];
    }
    $validate_ssl = ssl_external_verify_enabled();
    $response     = fetch_url_content("curl", $http_options, $validate_ssl, $webhook_url, $payload);
    if (!is_array($response)) {
        $response = [];
    }
    $return_code  = $response['return_code'] ?? 0;

    if ($return_code < 200 || $return_code >= 300) {
        return ['status' => 'failed', 'output' => ['http_status' => $return_code], 'error' => "send_slack: HTTP {$return_code}"];
    }

    write_debug_log("WORKFLOW: send_slack delivered message.", 'info');
    return ['status' => 'success', 'output' => ['http_status' => $return_code], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SEND TEAMS      *
 ***********************************************/
function workflow_action_send_teams(array $inputs, array $context): array
{
    $dry_run     = (bool)($inputs['_dry_run'] ?? false);
    $webhook_url = trim($inputs['webhook_url'] ?? '');
    $message     = trim($inputs['message']     ?? '');

    if (empty($webhook_url) || empty($message)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'send_teams: webhook_url and message are required.'];
    }

    if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        return ['status' => 'failed', 'output' => [], 'error' => "send_teams: Invalid webhook URL."];
    }

    if (!in_array(strtolower(parse_url($webhook_url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
        return ['status' => 'failed', 'output' => [], 'error' => "send_teams: Only http and https URLs are permitted."];
    }

    // SR-1720 SSRF guard — see workflow_guard_outbound_url() above. Capture the
    // resolved target (not just the null-check) so the real request below can
    // pin the validated IP the same way send_webhook/http_request do.
    $target = workflow_guard_outbound_url($webhook_url, 'send_teams');
    if ($target === null) {
        return ['status' => 'failed', 'output' => [], 'error' => _lang_raw('WorkflowHttpRequestDisallowedURL', ['action' => 'send_teams', 'url' => $webhook_url])];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: send_teams message=" . substr($message, 0, 50), 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'message' => $message], 'error' => null];
    }

    // Teams incoming webhook uses a simple JSON payload
    $payload = json_encode([
        '@type'      => 'MessageCard',
        '@context'   => 'http://schema.org/extensions',
        'text'       => $message,
    ]);

    $http_options = ['method' => 'POST', 'header' => ['Content-Type: application/json'], 'follow_redirects' => false];
    // Pin the validated IP (hostname targets only; a literal-IP host has no pin) so
    // an allowed host can't be pivoted to an internal address via DNS-rebinding.
    if (!empty($target['resolve'])) {
        $http_options['resolve'] = [$target['resolve']];
    }
    $validate_ssl = ssl_external_verify_enabled();
    $response     = fetch_url_content("curl", $http_options, $validate_ssl, $webhook_url, $payload);
    if (!is_array($response)) {
        $response = [];
    }
    $return_code  = $response['return_code'] ?? 0;

    if ($return_code < 200 || $return_code >= 300) {
        return ['status' => 'failed', 'output' => ['http_status' => $return_code], 'error' => "send_teams: HTTP {$return_code}"];
    }

    write_debug_log("WORKFLOW: send_teams delivered message.", 'info');
    return ['status' => 'success', 'output' => ['http_status' => $return_code], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION - SEND NOTIFICATION
 *
 * Inputs:
 *   audience_type - one of: user, team, role, all_admin, all_user
 *   audience_id   - for user/team/role: a comma-separated list of the selected
 *                   user/team/role IDs (multi-select). Ignored for the two
 *                   "all" types. IDs are stored, not pre-resolved to users, so
 *                   membership changes are reflected at fire time.
 *   title         - notification title; supports {{variables}}
 *   body          - notification body;  supports {{variables}}
 *   link          - optional URL;       supports {{variables}}
 ***********************************************/
function workflow_action_send_notification(array $inputs, array $context): array
{
    $dry_run       = (bool)($inputs['_dry_run'] ?? false);
    $audience_type = trim((string)($inputs['audience_type'] ?? ''));
    $audience_id   = $inputs['audience_id'] ?? null;
    $title_raw     = trim((string)($inputs['title'] ?? ''));
    $body_raw      = trim((string)($inputs['body']  ?? ''));
    $link_raw      = trim((string)($inputs['link']  ?? ''));

    if (!in_array($audience_type, ['user', 'team', 'role', 'all_admin', 'all_user'], true)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'send_notification: invalid audience_type'];
    }
    if ($title_raw === '' || $body_raw === '') {
        return ['status' => 'failed', 'output' => [], 'error' => 'send_notification: title and body are required'];
    }

    // Parse the selected audience IDs (comma-separated, multi-select). Not
    // required for the two "all" types, which target a fixed population.
    $needs_ids = in_array($audience_type, ['user', 'team', 'role'], true);
    $audience_ids = [];
    if ($needs_ids) {
        foreach (explode(',', (string)$audience_id) as $piece) {
            $id = (int)trim($piece);
            if ($id > 0) $audience_ids[] = $id;
        }
        $audience_ids = array_values(array_unique($audience_ids));
        if (empty($audience_ids)) {
            return ['status' => 'failed', 'output' => [], 'error' => 'send_notification: no audience selected'];
        }
    }

    $enriched = workflow_enrich_email_context($context);
    $title    = resolve_workflow_variables($title_raw, $enriched, []);
    $body     = resolve_workflow_variables($body_raw,  $enriched, []);
    $link     = $link_raw !== '' ? resolve_workflow_variables($link_raw, $enriched, []) : null;

    // Sanitize body HTML (mirrors ingest_remote_feed_item's defense-in-depth).
    // Mandatory, not conditional: purify_html() is always loaded via the
    // functions.php → workflows.php runtime chain, so the sanitization must
    // never silently fail open.
    $body = purify_html($body);

    if ($dry_run) {
        $audience_desc = $needs_ids ? implode(',', $audience_ids) : '(all)';
        write_debug_log("WORKFLOW DRY-RUN: send_notification audience={$audience_type}:{$audience_desc} title={$title}", 'info');
        return ['status' => 'success',
                'output' => ['dry_run' => true, 'audience_type' => $audience_type, 'title' => $title],
                'error'  => null];
    }

    require_once(realpath(__DIR__ . '/../../notifications.php'));

    $created_by = (int)($context['_actor_user_id'] ?? 0) ?: null;

    if ($needs_ids) {
        // Resolve every selected user/team/role ID to its users, union and
        // de-duplicate into a single recipient set, then deliver one
        // notification so a user on multiple selected teams/roles receives it
        // exactly once.
        $db = db_open();
        $user_ids = [];
        foreach ($audience_ids as $id) {
            $user_ids = array_merge($user_ids, resolve_audience($audience_type, $id, $db));
        }
        $user_ids = array_values(array_unique($user_ids));

        $result = create_notification_for_user_ids(
            source:        'workflow',
            title:         $title,
            body:          $body,
            link:          $link,
            user_ids:      $user_ids,
            created_by:    $created_by,
            expires_at:    null,
            external_guid: null,
            db:            $db
        );
        db_close($db);
    } else {
        // all_admin / all_user — fixed population resolved by create_notification.
        $result = create_notification(
            source:        'workflow',
            title:         $title,
            body:          $body,
            link:          $link,
            audience_type: $audience_type,
            audience_id:   null,
            created_by:    $created_by,
            expires_at:    null,
            external_guid: null
        );
    }

    if ($result['recipient_count'] === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => "send_notification: audience resolved to zero recipients"];
    }

    return ['status' => 'success',
            'output' => ['recipient_count' => $result['recipient_count']],
            'error'  => null];
}
