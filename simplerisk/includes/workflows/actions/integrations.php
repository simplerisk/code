<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/***********************************************
 * FUNCTION: WORKFLOW ACTION — NOTIFY NEW RISK *
 * Stub: only executes if the Notification     *
 * Extra is installed and activated.           *
 ***********************************************/
function workflow_action_notify_new_risk(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $risk_id = (int)($context['risk_id'] ?? 0);

    if ($risk_id <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'notify_new_risk: risk_id is required in context.'];
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: notify_new_risk risk_id={$risk_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id], 'error' => null];
    }

    // Check if the Notification Extra is installed
    $notification_dir = realpath(__DIR__ . '/../../../../extras/notification');
    if (!$notification_dir || !is_dir($notification_dir)) {
        write_debug_log("WORKFLOW: notify_new_risk skipped — Notification Extra not installed.", 'debug');
        return ['status' => 'skipped', 'output' => ['reason' => 'Notification Extra not installed.'], 'error' => null];
    }

    require_once($notification_dir . '/index.php');

    if (!notification_extra()) {
        write_debug_log("WORKFLOW: notify_new_risk skipped — Notification Extra not activated.", 'debug');
        return ['status' => 'skipped', 'output' => ['reason' => 'Notification Extra not activated.'], 'error' => null];
    }

    notify_new_risk($risk_id);

    write_debug_log("WORKFLOW: notify_new_risk executed for risk_id={$risk_id}.", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SYNC JIRA ISSUE *
 * Stub: only executes if the JIRA Extra is    *
 * installed and activated. Pushes the current *
 * risk field values to the linked JIRA issue  *
 * (if one exists).                            *
 ***********************************************/
function workflow_action_sync_jira_issue(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $risk_id = (int)($context['risk_id'] ?? 0);

    if ($risk_id <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'sync_jira_issue: risk_id is required in context.'];
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: sync_jira_issue risk_id={$risk_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id], 'error' => null];
    }

    // Check if the JIRA Extra is installed
    $jira_dir = realpath(__DIR__ . '/../../../../extras/jira');
    if (!$jira_dir || !is_dir($jira_dir)) {
        write_debug_log("WORKFLOW: sync_jira_issue skipped — JIRA Extra not installed.", 'debug');
        return ['status' => 'skipped', 'output' => ['reason' => 'JIRA Extra not installed.'], 'error' => null];
    }

    require_once($jira_dir . '/index.php');

    if (!jira_extra()) {
        write_debug_log("WORKFLOW: sync_jira_issue skipped — JIRA Extra not activated.", 'debug');
        return ['status' => 'skipped', 'output' => ['reason' => 'JIRA Extra not activated.'], 'error' => null];
    }

    jira_synchronize_risk($risk_id);

    write_debug_log("WORKFLOW: sync_jira_issue executed for risk_id={$risk_id}.", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — CREATE JIRA     *
 * ISSUE                                       *
 * Stub: only executes if the JIRA Extra is    *
 * installed, activated, and configured to     *
 * auto-create issues on new risks.            *
 ***********************************************/
function workflow_action_create_jira_issue(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $risk_id = (int)($context['risk_id'] ?? 0);

    if ($risk_id <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'create_jira_issue: risk_id is required in context.'];
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: create_jira_issue risk_id={$risk_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id], 'error' => null];
    }

    // Check if the JIRA Extra is installed
    $jira_dir = realpath(__DIR__ . '/../../../../extras/jira');
    if (!$jira_dir || !is_dir($jira_dir)) {
        write_debug_log("WORKFLOW: create_jira_issue skipped — JIRA Extra not installed.", 'debug');
        return ['status' => 'skipped', 'output' => ['reason' => 'JIRA Extra not installed.'], 'error' => null];
    }

    require_once($jira_dir . '/index.php');

    if (!jira_extra()) {
        write_debug_log("WORKFLOW: create_jira_issue skipped — JIRA Extra not activated.", 'debug');
        return ['status' => 'skipped', 'output' => ['reason' => 'JIRA Extra not activated.'], 'error' => null];
    }

    if (get_setting('JiraCreateIssueOnNewRisk') != 1) {
        write_debug_log("WORKFLOW: create_jira_issue skipped — JiraCreateIssueOnNewRisk setting is disabled.", 'debug');
        return ['status' => 'skipped', 'output' => ['reason' => 'JiraCreateIssueOnNewRisk setting is disabled.'], 'error' => null];
    }

    CreateIssueForRisk($risk_id);

    write_debug_log("WORKFLOW: create_jira_issue executed for risk_id={$risk_id}.", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id], 'error' => null];
}
