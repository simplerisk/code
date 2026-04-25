<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/***********************************************
 * FUNCTION: WORKFLOW ACTION — FLAG FOR REVIEW  *
 ***********************************************/
function workflow_action_flag_for_review(array $inputs, array $context): array
{
    $dry_run    = (bool)($inputs['_dry_run'] ?? false);
    $audit_id   = (int)($inputs['audit_id']   ?? 0);
    $control_id = (int)($inputs['control_id'] ?? 0);
    $reason     = trim($inputs['reason']      ?? '');

    if ($audit_id <= 0 && $control_id <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'flag_for_review: audit_id or control_id is required.'];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: flag_for_review audit_id={$audit_id} control_id={$control_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'audit_id' => $audit_id, 'control_id' => $control_id], 'error' => null];
    }

    $flagged_at = date('Y-m-d H:i:s');
    $db         = db_open();
    $affected   = 0;

    if ($audit_id > 0)
    {
        $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `status` = 'In Review' WHERE `id` = :id");
        $stmt->bindParam(':id', $audit_id, PDO::PARAM_INT);
        $stmt->execute();
        $affected += $stmt->rowCount();
    }

    if ($control_id > 0)
    {
        $stmt = $db->prepare("UPDATE `framework_controls` SET `status` = 1 WHERE `id` = :id");
        $stmt->bindParam(':id', $control_id, PDO::PARAM_INT);
        $stmt->execute();
        $affected += $stmt->rowCount();
    }

    db_close($db);

    if ($affected === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'flag_for_review: No matching records found.'];
    }

    write_debug_log("WORKFLOW: flag_for_review audit_id={$audit_id} control_id={$control_id}", 'info');
    return ['status' => 'success', 'output' => ['audit_id' => $audit_id, 'control_id' => $control_id, 'reason' => $reason], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — ASSIGN AUDIT    *
 ***********************************************/
function workflow_action_assign_audit(array $inputs, array $context): array
{
    $dry_run      = (bool)($inputs['_dry_run']      ?? false);
    $framework_id = (int)($inputs['framework_id']   ?? 0);
    $auditor      = (int)($inputs['auditor']         ?? 0);
    $due_date     = trim($inputs['due_date']         ?? date('Y-m-d', strtotime('+90 days')));

    if ($framework_id <= 0 || $auditor <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'assign_audit: framework_id and auditor are required.'];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: assign_audit framework_id={$framework_id} auditor={$auditor}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'framework_id' => $framework_id, 'auditor' => $auditor], 'error' => null];
    }

    $db   = db_open();
    $stmt = $db->prepare("UPDATE `frameworks` SET `status` = 1 WHERE `value` = :id");
    $stmt->bindParam(':id', $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    $affected = $stmt->rowCount();
    db_close($db);

    if ($affected === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => "assign_audit: Framework ID {$framework_id} not found."];
    }

    write_debug_log("WORKFLOW: assign_audit framework_id={$framework_id} auditor={$auditor}", 'info');
    return ['status' => 'success', 'output' => ['framework_id' => $framework_id, 'auditor' => $auditor, 'due_date' => $due_date], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — ADD AUDIT COMMENT
 * Adds a comment to a compliance audit.
 * Reads audit_id from workflow context.
 * Comment text supports {{variable}} substitution
 * (resolved by executor before reaching here).
 ***********************************************/
function workflow_action_add_audit_comment(array $inputs, array $context): array
{
    $dry_run  = (bool)($inputs['_dry_run'] ?? false);
    $audit_id = (int)($context['audit_id'] ?? 0);
    $comment  = trim($inputs['comment'] ?? '');
    $author   = (int)($inputs['author']  ?? 0);

    if ($audit_id <= 0 || $comment === '') {
        return ['status' => 'failed', 'output' => [], 'error' => 'add_audit_comment: audit_id (from context) and comment are required.'];
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: add_audit_comment audit_id={$audit_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'audit_id' => $audit_id], 'error' => null];
    }

    // Fall back to the first admin user when no author is specified
    if ($author <= 0) {
        $db_tmp = db_open();
        $s = $db_tmp->prepare("SELECT `value` FROM `user` WHERE `admin` = 1 ORDER BY `value` LIMIT 1");
        $s->execute();
        $row    = $s->fetch(PDO::FETCH_ASSOC);
        $author = $row ? (int)$row['value'] : 1;
        db_close($db_tmp);
    }

    $encrypted_comment = try_encrypt($comment);

    $db   = db_open();
    $stmt = $db->prepare("INSERT INTO `framework_control_test_comments` (`test_audit_id`, `user`, `comment`) VALUES (:aid, :uid, :comment)");
    $stmt->bindParam(':aid',     $audit_id,          PDO::PARAM_INT);
    $stmt->bindParam(':uid',     $author,            PDO::PARAM_INT);
    $stmt->bindParam(':comment', $encrypted_comment, PDO::PARAM_STR);
    $stmt->execute();
    db_close($db);

    write_debug_log("WORKFLOW: add_audit_comment audit_id={$audit_id} author={$author}", 'info');
    return ['status' => 'success', 'output' => ['audit_id' => $audit_id, 'author' => $author], 'error' => null];
}
