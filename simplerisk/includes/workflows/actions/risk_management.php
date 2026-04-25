<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/***********************************************
 * FUNCTION: WORKFLOW ACTION — ASSIGN RISK OWNER
 ***********************************************/
function workflow_action_assign_risk_owner(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $risk_id = (int)($context['risk_id'] ?? 0);
    $user_id = (int)($inputs['user_id'] ?? 0);

    if ($risk_id <= 0 || $user_id <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'assign_risk_owner: risk_id (from context) and user_id are required.'];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: assign_risk_owner risk_id={$risk_id} user_id={$user_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id, 'user_id' => $user_id], 'error' => null];
    }

    $db   = db_open();
    $stmt = $db->prepare("UPDATE `risks` SET `owner` = :owner WHERE `id` = :id");
    $stmt->bindParam(':owner', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':id',    $risk_id, PDO::PARAM_INT);
    $stmt->execute();
    $affected = $stmt->rowCount();
    db_close($db);

    if ($affected === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => "assign_risk_owner: Risk ID {$risk_id} not found."];
    }

    write_debug_log("WORKFLOW: assign_risk_owner risk_id={$risk_id} user_id={$user_id}", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id, 'user_id' => $user_id], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE RISK FIELD
 ***********************************************/
function workflow_action_update_risk_field(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $risk_id = (int)($context['risk_id'] ?? 0);
    $field   = trim($inputs['field']   ?? '');
    $value   = $inputs['value'] ?? '';

    // Direct columns on the risks table (no encryption)
    $direct_fields = [
        'status', 'source', 'category', 'owner', 'manager', 'project_id',
        'reference_id', 'regulation', 'control_number', 'submission_date',
    ];

    // Direct columns that are stored encrypted
    $encrypted_fields = ['subject', 'assessment', 'notes'];

    // Date fields — convert from user display format before storage
    static $date_fields = ['submission_date'];

    // Junction tables keyed by field name (risk__ prefix stripped by caller)
    $junction_map = [
        'risk__location'                => ['table' => 'risk_to_location',              'fk' => 'location_id',      'single' => true],
        'risk__team'                    => ['table' => 'risk_to_team',                  'fk' => 'team_id',          'single' => false],
        'risk__technology'              => ['table' => 'risk_to_technology',             'fk' => 'technology_id',    'single' => false],
        'risk__additional_stakeholders' => ['table' => 'risk_to_additional_stakeholder', 'fk' => 'user_id',          'single' => false],
        'risk__risk_mapping'            => ['table' => 'risk_catalog_mappings',          'fk' => 'risk_catalog_id',  'single' => false],
        'risk__threat_mapping'          => ['table' => 'threat_catalog_mappings',        'fk' => 'threat_catalog_id','single' => false],
        'risk__affected_assets'         => ['table' => 'risks_to_assets',               'fk' => 'asset_id',         'single' => false],
    ];

    $all_allowed = array_merge($direct_fields, $encrypted_fields, array_keys($junction_map));

    if ($risk_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_risk_field: risk_id (from context) and field are required.'];
    }

    if (!in_array($field, $all_allowed, true)) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_risk_field: Field '{$field}' is not an updatable field."];
    }

    // Convert date fields from display format to Y-m-d
    if (in_array($field, $date_fields, true) && !empty($value)) {
        $value = get_standard_date_from_default_format($value);
        if ($value === '0000-00-00') $value = null;
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: update_risk_field risk_id={$risk_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db = db_open();

    if (in_array($field, $direct_fields, true))
    {
        $stmt = $db->prepare("UPDATE `risks` SET `{$field}` = :value WHERE `id` = :id");
        $stmt->bindValue(':value', $value);
        $stmt->bindParam(':id', $risk_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_risk_field: Risk ID {$risk_id} not found."];
        }
    }
    elseif (in_array($field, $encrypted_fields, true))
    {
        $encrypted = try_encrypt($value);
        $stmt = $db->prepare("UPDATE `risks` SET `{$field}` = :value WHERE `id` = :id");
        $stmt->bindValue(':value', $encrypted);
        $stmt->bindParam(':id', $risk_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_risk_field: Risk ID {$risk_id} not found."];
        }
    }
    else
    {
        // Junction table: delete existing rows then re-insert
        $jt  = $junction_map[$field];
        $ids = array_values(array_filter(array_map('intval', explode(',', $value))));

        $stmt = $db->prepare("DELETE FROM `{$jt['table']}` WHERE `risk_id` = :rid");
        $stmt->bindParam(':rid', $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        if (!empty($ids)) {
            // For single-value fields use only the first ID
            if ($jt['single']) $ids = [$ids[0]];
            $ins = $db->prepare("INSERT INTO `{$jt['table']}` (`risk_id`, `{$jt['fk']}`) VALUES (:rid, :fid)");
            foreach ($ids as $fid) {
                $ins->bindValue(':rid', $risk_id, PDO::PARAM_INT);
                $ins->bindValue(':fid', $fid,     PDO::PARAM_INT);
                $ins->execute();
            }
        }
    }

    db_close($db);

    write_debug_log("WORKFLOW: update_risk_field risk_id={$risk_id} field={$field}", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id, 'field' => $field, 'value' => $value], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE MITIGATION FIELD
 * Updates a single field on the mitigations record
 * for the risk_id in context (most recent mitigation).
 ***********************************************/
function workflow_action_update_mitigation_field(array $inputs, array $context): array
{
    $dry_run      = (bool)($inputs['_dry_run'] ?? false);
    $risk_id      = (int)($context['risk_id']      ?? 0);
    $mitigation_id = (int)($context['mitigation_id'] ?? 0);
    $field        = trim($inputs['field'] ?? '');
    $value        = $inputs['value'] ?? '';

    static $date_fields = ['mitigation__submission_date', 'mitigation__planning_date'];

    $field_map = [
        'mitigation__submission_date'        => 'submission_date',
        'mitigation__planning_date'          => 'planning_date',
        'mitigation__planning_strategy'      => 'planning_strategy',
        'mitigation__mitigation_effort'      => 'mitigation_effort',
        'mitigation__mitigation_cost'        => 'mitigation_cost',
        'mitigation__mitigation_owner'       => 'mitigation_owner',
        'mitigation__mitigation_percent'     => 'mitigation_percent',
        'mitigation__current_solution'       => 'current_solution',
        'mitigation__security_requirements'  => 'security_requirements',
        'mitigation__security_recommendations' => 'security_recommendations',
    ];

    $is_team_junction = ($field === 'mitigation__mitigation_team');

    if ($risk_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_mitigation_field: risk_id (from context) and field are required.'];
    }

    if (!$is_team_junction && !isset($field_map[$field])) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_mitigation_field: Field '{$field}' is not an updatable field."];
    }

    if (in_array($field, $date_fields, true) && !empty($value)) {
        $value = get_standard_date_from_default_format($value);
        if ($value === '0000-00-00') $value = null;
    }

    if ($field === 'mitigation__mitigation_percent') {
        $value = max(0, min(100, (int)$value));
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: update_mitigation_field risk_id={$risk_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db = db_open();

    // Resolve mitigation ID: prefer context, fall back to most recent for this risk
    if ($mitigation_id <= 0) {
        $s = $db->prepare("SELECT `id` FROM `mitigations` WHERE `risk_id` = :rid ORDER BY `submission_date` DESC LIMIT 1");
        $s->bindParam(':rid', $risk_id, PDO::PARAM_INT);
        $s->execute();
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_mitigation_field: No mitigation found for risk ID {$risk_id}."];
        }
        $mitigation_id = (int)$row['id'];
    }

    if ($is_team_junction)
    {
        $team_ids = array_values(array_filter(array_map('intval', explode(',', $value))));
        $stmt = $db->prepare("DELETE FROM `mitigation_to_team` WHERE `mitigation_id` = :mid");
        $stmt->bindParam(':mid', $mitigation_id, PDO::PARAM_INT);
        $stmt->execute();
        if (!empty($team_ids)) {
            $ins = $db->prepare("INSERT INTO `mitigation_to_team` (`mitigation_id`, `team_id`) VALUES (:mid, :tid)");
            foreach ($team_ids as $tid) {
                $ins->bindValue(':mid', $mitigation_id, PDO::PARAM_INT);
                $ins->bindValue(':tid', $tid,           PDO::PARAM_INT);
                $ins->execute();
            }
        }
    }
    else
    {
        $column = $field_map[$field];
        $stmt = $db->prepare("UPDATE `mitigations` SET `{$column}` = :value WHERE `id` = :id");
        $stmt->bindValue(':value', $value);
        $stmt->bindParam(':id', $mitigation_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_mitigation_field: Mitigation ID {$mitigation_id} not found."];
        }
    }

    db_close($db);

    write_debug_log("WORKFLOW: update_mitigation_field mitigation_id={$mitigation_id} field={$field}", 'info');
    return ['status' => 'success', 'output' => ['mitigation_id' => $mitigation_id, 'field' => $field, 'value' => $value], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE REVIEW FIELD
 * Updates a single field on the mgmt_reviews record
 * for the review_id in context (or most recent review).
 ***********************************************/
function workflow_action_update_review_field(array $inputs, array $context): array
{
    $dry_run   = (bool)($inputs['_dry_run'] ?? false);
    $risk_id   = (int)($context['risk_id']   ?? 0);
    $review_id = (int)($context['review_id'] ?? 0);
    $field     = trim($inputs['field'] ?? '');
    $value     = $inputs['value'] ?? '';

    static $date_fields = ['review__submission_date', 'review__next_review'];

    $field_map = [
        'review__submission_date' => 'submission_date',
        'review__reviewer'        => 'reviewer',
        'review__review'          => 'review',
        'review__next_step'       => 'next_step',
        'review__next_review'     => 'next_review',
        'review__comments'        => 'comments',
    ];

    if ($risk_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_review_field: risk_id (from context) and field are required.'];
    }

    if (!isset($field_map[$field])) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_review_field: Field '{$field}' is not an updatable field."];
    }

    if (in_array($field, $date_fields, true) && !empty($value)) {
        $value = get_standard_date_from_default_format($value);
        if ($value === '0000-00-00') $value = null;
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: update_review_field risk_id={$risk_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db = db_open();

    // Resolve review ID: prefer context, fall back to most recent for this risk
    if ($review_id <= 0) {
        $s = $db->prepare("SELECT `id` FROM `mgmt_reviews` WHERE `risk_id` = :rid ORDER BY `submission_date` DESC LIMIT 1");
        $s->bindParam(':rid', $risk_id, PDO::PARAM_INT);
        $s->execute();
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_review_field: No management review found for risk ID {$risk_id}."];
        }
        $review_id = (int)$row['id'];
    }

    $column = $field_map[$field];
    $stmt   = $db->prepare("UPDATE `mgmt_reviews` SET `{$column}` = :value WHERE `id` = :id");
    $stmt->bindValue(':value', $value);
    $stmt->bindParam(':id', $review_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        db_close($db);
        return ['status' => 'failed', 'output' => [], 'error' => "update_review_field: Review ID {$review_id} not found."];
    }

    db_close($db);

    write_debug_log("WORKFLOW: update_review_field review_id={$review_id} field={$field}", 'info');
    return ['status' => 'success', 'output' => ['review_id' => $review_id, 'field' => $field, 'value' => $value], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SET RISK STATUS  *
 ***********************************************/
function workflow_action_set_risk_status(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $risk_id = (int)($context['risk_id'] ?? 0);
    $status  = trim($inputs['status']  ?? '');

    $allowed_statuses = ['New', 'Mgmt Reviewed', 'In Review', 'Closed'];

    if ($risk_id <= 0 || empty($status)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'set_risk_status: risk_id (from context) and status are required.'];
    }

    if (!in_array($status, $allowed_statuses, true)) {
        return ['status' => 'failed', 'output' => [], 'error' => "set_risk_status: '{$status}' is not a valid status."];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: set_risk_status risk_id={$risk_id} status={$status}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id, 'status' => $status], 'error' => null];
    }

    $db   = db_open();
    $stmt = $db->prepare("UPDATE `risks` SET `status` = :status WHERE `id` = :id");
    $stmt->bindParam(':status', $status,  PDO::PARAM_STR);
    $stmt->bindParam(':id',     $risk_id, PDO::PARAM_INT);
    $stmt->execute();
    $affected = $stmt->rowCount();
    db_close($db);

    if ($affected === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => "set_risk_status: Risk ID {$risk_id} not found."];
    }

    write_debug_log("WORKFLOW: set_risk_status risk_id={$risk_id} status={$status}", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id, 'status' => $status], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SUBMIT MITIGATION
 ***********************************************/
function workflow_action_submit_mitigation(array $inputs, array $context): array
{
    $dry_run  = (bool)($inputs['_dry_run'] ?? false);
    $risk_id  = (int)($context['risk_id'] ?? 0);
    $strategy = (int)($inputs['strategy'] ?? 0);
    $owner    = (int)($inputs['owner']    ?? 0);
    $cost     = (int)($inputs['cost']     ?? 0);

    if ($risk_id <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'submit_mitigation: risk_id (from context) is required.'];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: submit_mitigation risk_id={$risk_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id], 'error' => null];
    }

    $submission_date = date('Y-m-d');
    $db   = db_open();
    $stmt = $db->prepare("
        INSERT INTO `mitigations`
            (`risk_id`, `submission_date`, `planning_strategy`, `mitigation_owner`, `mitigation_cost`)
        VALUES
            (:risk_id, :submission_date, :strategy, :owner, :cost)
    ");
    $stmt->bindParam(':risk_id',         $risk_id,         PDO::PARAM_INT);
    $stmt->bindParam(':submission_date', $submission_date, PDO::PARAM_STR);
    $stmt->bindParam(':strategy',        $strategy,        PDO::PARAM_INT);
    $stmt->bindParam(':owner',           $owner,           PDO::PARAM_INT);
    $stmt->bindParam(':cost',            $cost,            PDO::PARAM_INT);
    $stmt->execute();
    $mitigation_id = (int)$db->lastInsertId();

    // Update risk status to 'Mgmt Reviewed' after mitigation
    $stmt = $db->prepare("UPDATE `risks` SET `status` = 'Mgmt Reviewed', `mitigation_id` = :mid WHERE `id` = :rid");
    $stmt->bindParam(':mid', $mitigation_id, PDO::PARAM_INT);
    $stmt->bindParam(':rid', $risk_id,       PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);

    write_debug_log("WORKFLOW: submit_mitigation risk_id={$risk_id} mitigation_id={$mitigation_id}", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id, 'mitigation_id' => $mitigation_id], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SUBMIT REVIEW   *
 ***********************************************/
function workflow_action_submit_review(array $inputs, array $context): array
{
    $dry_run  = (bool)($inputs['_dry_run'] ?? false);
    $risk_id  = (int)($context['risk_id'] ?? 0);
    $reviewer = (int)($inputs['reviewer'] ?? 0);
    $decision = trim($inputs['decision']  ?? '');
    $notes    = trim($inputs['notes']     ?? '');

    $allowed_decisions = ['Approve', 'Reject', 'Accept', 'Avoid', 'Transfer', 'Discuss'];

    if ($risk_id <= 0 || $reviewer <= 0 || empty($decision)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'submit_review: risk_id (from context), reviewer, and decision are required.'];
    }

    if (!in_array($decision, $allowed_decisions, true)) {
        return ['status' => 'failed', 'output' => [], 'error' => "submit_review: '{$decision}' is not a valid decision."];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: submit_review risk_id={$risk_id} decision={$decision}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id, 'decision' => $decision], 'error' => null];
    }

    $submission_date = date('Y-m-d');
    $db   = db_open();
    $stmt = $db->prepare("
        INSERT INTO `mgmt_reviews`
            (`risk_id`, `submission_date`, `reviewer`, `review`, `next_review`, `comments`)
        VALUES
            (:risk_id, :submission_date, :reviewer, :decision, :next_review, :notes)
    ");
    $next_review = date('Y-m-d', strtotime('+30 days'));
    $stmt->bindParam(':risk_id',         $risk_id,         PDO::PARAM_INT);
    $stmt->bindParam(':submission_date', $submission_date, PDO::PARAM_STR);
    $stmt->bindParam(':reviewer',        $reviewer,        PDO::PARAM_INT);
    $stmt->bindParam(':decision',        $decision,        PDO::PARAM_STR);
    $stmt->bindParam(':next_review',     $next_review,     PDO::PARAM_STR);
    $stmt->bindParam(':notes',           $notes,           PDO::PARAM_STR);
    $stmt->execute();
    $review_id = (int)$db->lastInsertId();

    $stmt = $db->prepare("UPDATE `risks` SET `status` = 'Mgmt Reviewed', `review_date` = :rd WHERE `id` = :id");
    $stmt->bindParam(':rd', $submission_date, PDO::PARAM_STR);
    $stmt->bindParam(':id', $risk_id,         PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);

    write_debug_log("WORKFLOW: submit_review risk_id={$risk_id} review_id={$review_id}", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id, 'review_id' => $review_id, 'decision' => $decision], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — SUBMIT RISK     *
 * Universal: available to all trigger types.  *
 * Creates a new risk from workflow context.   *
 ***********************************************/
function workflow_action_submit_risk(array $inputs, array $context): array
{
    $dry_run  = (bool)($inputs['_dry_run'] ?? false);
    $subject  = trim($inputs['subject']  ?? '');
    $owner    = (int)($inputs['owner']   ?? 0);
    $source   = (int)($inputs['source']  ?? 0);
    $category = (int)($inputs['category'] ?? 0);
    $notes    = trim($inputs['notes']    ?? '');

    if (empty($subject)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'submit_risk: subject is required.'];
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: submit_risk subject={$subject}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'subject' => $subject], 'error' => null];
    }

    $db   = db_open();
    $stmt = $db->prepare("
        INSERT INTO `risks`
            (`subject`, `owner`, `source`, `category`, `notes`,
             `status`, `submitted_by`, `submission_date`)
        VALUES
            (:subject, :owner, :source, :category, :notes,
             'New', 0, NOW())
    ");
    $stmt->bindParam(':subject',  $subject,  PDO::PARAM_STR);
    $stmt->bindParam(':owner',    $owner,    PDO::PARAM_INT);
    $stmt->bindParam(':source',   $source,   PDO::PARAM_INT);
    $stmt->bindParam(':category', $category, PDO::PARAM_INT);
    $stmt->bindParam(':notes',    $notes,    PDO::PARAM_STR);
    $stmt->execute();
    $risk_id = (int)$db->lastInsertId();
    db_close($db);

    // Seed default scoring row
    $db   = db_open();
    $stmt = $db->prepare("INSERT IGNORE INTO `risk_scoring` (`id`, `scoring_method`, `calculated_risk`) VALUES (:id, 5, 0)");
    $stmt->bindParam(':id', $risk_id, PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);

    write_log($risk_id + 1000, 0, "[Workflow] Risk '{$subject}' submitted automatically by a workflow.");
    write_debug_log("WORKFLOW: submit_risk created risk_id={$risk_id} subject={$subject}", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id, 'subject' => $subject], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — ADD RISK COMMENT
 * Adds a comment to a risk.
 * Reads risk_id from workflow context.
 * Comment text supports {{variable}} substitution
 * (resolved by executor before reaching here).
 ***********************************************/
function workflow_action_add_risk_comment(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $risk_id = (int)($context['risk_id'] ?? 0);
    $comment = trim($inputs['comment'] ?? '');
    $author  = (int)($inputs['author']  ?? 0);

    if ($risk_id <= 0 || $comment === '') {
        return ['status' => 'failed', 'output' => [], 'error' => 'add_risk_comment: risk_id (from context) and comment are required.'];
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: add_risk_comment risk_id={$risk_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'risk_id' => $risk_id], 'error' => null];
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

    $now             = date('Y-m-d H:i:s');
    $encrypted_comment = try_encrypt($comment);

    $db   = db_open();
    $stmt = $db->prepare("INSERT INTO `comments` (`risk_id`, `user`, `comment`, `date`) VALUES (:rid, :uid, :comment, :date)");
    $stmt->bindParam(':rid',     $risk_id,           PDO::PARAM_INT);
    $stmt->bindParam(':uid',     $author,            PDO::PARAM_INT);
    $stmt->bindParam(':comment', $encrypted_comment, PDO::PARAM_STR);
    $stmt->bindParam(':date',    $now,               PDO::PARAM_STR);
    $stmt->execute();

    // Keep last_update in sync
    $stmt = $db->prepare("UPDATE `risks` SET `last_update` = :date WHERE `id` = :id");
    $stmt->bindParam(':date', $now,     PDO::PARAM_STR);
    $stmt->bindParam(':id',   $risk_id, PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);

    write_debug_log("WORKFLOW: add_risk_comment risk_id={$risk_id} author={$author}", 'info');
    return ['status' => 'success', 'output' => ['risk_id' => $risk_id, 'author' => $author], 'error' => null];
}

