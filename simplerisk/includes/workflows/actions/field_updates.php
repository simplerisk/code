<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Assert that a column name derived from an internal field map is a valid SQL
 * identifier before it is interpolated into a query.
 *
 * Column names cannot be bound via PDO parameters, so this check is the
 * correct defense-in-depth layer. All values produced by the field_map lookups
 * in this file already satisfy this constraint; the check makes that invariant
 * mechanically enforced rather than implicit.
 */
function _wf_safe_column(string $col): bool
{
    return (bool)preg_match('/^[a-z][a-z0-9_]*$/i', $col);
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE FIELD
 * Unified dispatcher: routes to the correct
 * per-entity handler based on field prefix.
 *   asset__*      → assets table (asset_id)
 *   control__*    → framework_controls (control_id)
 *   exception__*  → document_exceptions (exception_id)
 *   mitigation__* → mitigations (mitigation_id via risk_id)
 *   review__*     → mgmt_reviews (review_id via risk_id)
 *   risk__*       → risks junction tables (risk_id)
 *   test__*       → framework_control_tests (test_id)
 *   (plain)       → risks table direct columns (risk_id)
 ***********************************************/
function workflow_action_update_field(array $inputs, array $context): array
{
    $field = trim($inputs['field'] ?? '');

    if (strncmp($field, 'asset__', 7) === 0) {
        return workflow_action_update_asset_field($inputs, $context);
    }
    if (strncmp($field, 'control__', 9) === 0) {
        return workflow_action_update_control_field($inputs, $context);
    }
    if (strncmp($field, 'framework__', 11) === 0) {
        return workflow_action_update_framework_field($inputs, $context);
    }
    if (strncmp($field, 'document__', 10) === 0) {
        return workflow_action_update_document_field($inputs, $context);
    }
    if (strncmp($field, 'exception__', 11) === 0) {
        return workflow_action_update_exception_field($inputs, $context);
    }
    if (strncmp($field, 'mitigation__', 12) === 0) {
        return workflow_action_update_mitigation_field($inputs, $context);
    }
    if (strncmp($field, 'review__', 8) === 0) {
        return workflow_action_update_review_field($inputs, $context);
    }
    if (strncmp($field, 'test__', 6) === 0) {
        return workflow_action_update_test_field($inputs, $context);
    }
    if (strncmp($field, 'audit__', 7) === 0) {
        return workflow_action_update_audit_field($inputs, $context);
    }

    // Default: risk field (plain column name or risk__ junction prefix)
    return workflow_action_update_risk_field($inputs, $context);
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE FRAMEWORK FIELD
 * Updates a single field on a frameworks record.
 * Reads framework_id from the workflow context.
 * Note: name and description are stored encrypted.
 ***********************************************/
function workflow_action_update_framework_field(array $inputs, array $context): array
{
    $dry_run      = (bool)($inputs['_dry_run'] ?? false);
    $framework_id = (int)($context['framework_id'] ?? 0);
    $field        = trim($inputs['field'] ?? '');
    $value        = $inputs['value'] ?? '';

    // Map namespaced field keys → actual column names
    $field_map = [
        'framework__name'        => 'name',
        'framework__description' => 'description',
        'framework__parent'      => 'parent',
    ];

    if ($framework_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_framework_field: framework_id (from context) and field are required.'];
    }

    if (!isset($field_map[$field])) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_framework_field: Field '{$field}' is not an updatable field."];
    }

    $column = $field_map[$field];

    if (!_wf_safe_column($column)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_framework_field: Internal error — invalid column identifier.'];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: update_framework_field framework_id={$framework_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'framework_id' => $framework_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    // Encrypt name and description before storing
    if ($column === 'name' || $column === 'description') {
        $value = try_encrypt($value);
    }

    $db   = db_open();
    $stmt = $db->prepare("UPDATE `frameworks` SET `{$column}` = :value WHERE `value` = :id");
    $stmt->bindValue(':value', $value);
    $stmt->bindParam(':id', $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    $affected = $stmt->rowCount();
    db_close($db);

    if ($affected === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_framework_field: Framework ID {$framework_id} not found."];
    }

    write_debug_log("WORKFLOW: update_framework_field framework_id={$framework_id} field={$field}", 'info');
    return ['status' => 'success', 'output' => ['framework_id' => $framework_id, 'field' => $field], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE ASSET FIELD
 * Updates a single field on an asset record.
 * Reads asset_id from the workflow context.
 ***********************************************/
function workflow_action_update_asset_field(array $inputs, array $context): array
{
    $dry_run  = (bool)($inputs['_dry_run'] ?? false);
    $asset_id = (int)($context['asset_id'] ?? 0);
    $field    = trim($inputs['field']  ?? '');
    $value    = $inputs['value'] ?? '';

    // Direct columns — stored encrypted
    $encrypted_fields = [
        'asset__name'    => 'name',
        'asset__ip'      => 'ip',
        'asset__details' => 'details',
    ];

    // Direct columns — stored plain
    $plain_fields = [
        'asset__location' => 'location',
        'asset__value'    => 'value',
        'asset__verified' => 'verified',
        // teams is a comma-separated list of IDs stored directly on the row
        'asset__teams'    => 'teams',
    ];

    $is_risks_junction = ($field === 'asset__associated_risks');

    $all_allowed = array_merge(array_keys($encrypted_fields), array_keys($plain_fields), ['asset__associated_risks']);

    if ($asset_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_asset_field: asset_id (from context) and field are required.'];
    }

    if (!in_array($field, $all_allowed, true)) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_asset_field: Field '{$field}' is not an updatable field."];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: update_asset_field asset_id={$asset_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'asset_id' => $asset_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db = db_open();

    if ($is_risks_junction)
    {
        // Replace all risk associations for this asset
        $risk_ids = array_values(array_filter(array_map('intval', explode(',', $value))));
        $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE `asset_id` = :aid");
        $stmt->bindParam(':aid', $asset_id, PDO::PARAM_INT);
        $stmt->execute();
        if (!empty($risk_ids)) {
            $ins = $db->prepare("INSERT INTO `risks_to_assets` (`asset_id`, `risk_id`) VALUES (:aid, :rid)");
            foreach ($risk_ids as $rid) {
                $ins->bindValue(':aid', $asset_id, PDO::PARAM_INT);
                $ins->bindValue(':rid', $rid,      PDO::PARAM_INT);
                $ins->execute();
            }
        }
    }
    elseif (isset($encrypted_fields[$field]))
    {
        $column    = $encrypted_fields[$field];
        if (!_wf_safe_column($column)) { db_close($db); return ['status' => 'failed', 'output' => [], 'error' => 'update_asset_field: Internal error — invalid column identifier.']; }
        $encrypted = try_encrypt($value);
        $stmt = $db->prepare("UPDATE `assets` SET `{$column}` = :value WHERE `id` = :id");
        $stmt->bindValue(':value', $encrypted);
        $stmt->bindParam(':id', $asset_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_asset_field: Asset ID {$asset_id} not found."];
        }
    }
    else
    {
        // asset__teams: value arrives as comma-separated IDs from the multiselect
        $column = $plain_fields[$field];
        if (!_wf_safe_column($column)) { db_close($db); return ['status' => 'failed', 'output' => [], 'error' => 'update_asset_field: Internal error — invalid column identifier.']; }
        $stmt = $db->prepare("UPDATE `assets` SET `{$column}` = :value WHERE `id` = :id");
        $stmt->bindValue(':value', $value);
        $stmt->bindParam(':id', $asset_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_asset_field: Asset ID {$asset_id} not found."];
        }
    }

    db_close($db);

    write_debug_log("WORKFLOW: update_asset_field asset_id={$asset_id} field={$field}", 'info');
    return ['status' => 'success', 'output' => ['asset_id' => $asset_id, 'field' => $field, 'value' => $value], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE CONTROL FIELD
 * Updates a single field on a framework_controls record.
 * Reads control_id from the workflow context.
 ***********************************************/
function workflow_action_update_control_field(array $inputs, array $context): array
{
    $dry_run    = (bool)($inputs['_dry_run'] ?? false);
    $control_id = (int)($context['control_id'] ?? 0);
    $field      = trim($inputs['field']  ?? '');
    $value      = $inputs['value'] ?? '';

    // Map namespaced field keys → actual column names
    // 'control_type' is handled separately via the junction table.
    $field_map = [
        'control__short_name'            => 'short_name',
        'control__long_name'             => 'long_name',
        'control__description'           => 'description',
        'control__supplemental_guidance' => 'supplemental_guidance',
        'control__control_owner'         => 'control_owner',
        'control__control_class'         => 'control_class',
        'control__control_phase'         => 'control_phase',
        'control__control_number'        => 'control_number',
        'control__control_maturity'      => 'control_maturity',
        'control__desired_maturity'      => 'desired_maturity',
        'control__control_priority'      => 'control_priority',
        'control__family'                => 'family',
        'control__control_status'        => 'control_status',
        'control__mitigation_percent'    => 'mitigation_percent',
    ];

    if ($control_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_control_field: control_id (from context) and field are required.'];
    }

    $is_junction = ($field === 'control__control_type');

    if (!$is_junction && !isset($field_map[$field])) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_control_field: Field '{$field}' is not an updatable field."];
    }

    // Validate and clamp mitigation_percent to 0–100
    if ($field === 'control__mitigation_percent')
    {
        $value = max(0, min(100, (int)$value));
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: update_control_field control_id={$control_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'control_id' => $control_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db = db_open();

    if ($is_junction)
    {
        // Replace control type: clear existing mapping and insert the new one
        $type_id = (int)$value;
        $stmt = $db->prepare("DELETE FROM `framework_control_type_mappings` WHERE `control_id` = :id");
        $stmt->bindParam(':id', $control_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($type_id > 0) {
            $stmt = $db->prepare("INSERT INTO `framework_control_type_mappings` (`control_id`, `control_type_id`) VALUES (:id, :type_id)");
            $stmt->bindParam(':id',      $control_id, PDO::PARAM_INT);
            $stmt->bindParam(':type_id', $type_id,    PDO::PARAM_INT);
            $stmt->execute();
        }
    }
    else
    {
        $column = $field_map[$field];
        if (!_wf_safe_column($column)) { db_close($db); return ['status' => 'failed', 'output' => [], 'error' => 'update_control_field: Internal error — invalid column identifier.']; }
        $stmt = $db->prepare("UPDATE `framework_controls` SET `{$column}` = :value WHERE `id` = :id");
        $stmt->bindValue(':value', $value);
        $stmt->bindParam(':id', $control_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_control_field: Control ID {$control_id} not found."];
        }
    }

    db_close($db);

    write_debug_log("WORKFLOW: update_control_field control_id={$control_id} field={$field} value={$value}", 'info');
    return ['status' => 'success', 'output' => ['control_id' => $control_id, 'field' => $field, 'value' => $value], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE TEST FIELD
 * Updates a single field on a framework_control_tests record.
 * Reads test_id from the workflow context.
 ***********************************************/
function workflow_action_update_test_field(array $inputs, array $context): array
{
    $dry_run = (bool)($inputs['_dry_run'] ?? false);
    $test_id = (int)($context['test_id'] ?? 0);
    $field   = trim($inputs['field']  ?? '');
    $value   = $inputs['value'] ?? '';

    if ($test_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_test_field: test_id (from context) and field are required.'];
    }

    // ── Teams junction table ────────────────────────────────────────────
    if ($field === 'test__teams') {
        $team_ids = array_values(array_filter(array_map('intval', explode(',', $value))));

        if ($dry_run) {
            write_debug_log("WORKFLOW DRY-RUN: update_test_field test_id={$test_id} field={$field} value={$value}", 'info');
            return ['status' => 'success', 'output' => ['dry_run' => true, 'test_id' => $test_id, 'field' => $field, 'value' => $value], 'error' => null];
        }

        $db = db_open();
        $stmt = $db->prepare("DELETE FROM `items_to_teams` WHERE `item_id` = :id AND `type` = 'test'");
        $stmt->bindParam(':id', $test_id, PDO::PARAM_INT);
        $stmt->execute();
        if (!empty($team_ids)) {
            $ins = $db->prepare("INSERT INTO `items_to_teams` (`item_id`, `type`, `team_id`) VALUES (:id, 'test', :tid)");
            foreach ($team_ids as $tid) {
                $ins->bindParam(':id',  $test_id, PDO::PARAM_INT);
                $ins->bindParam(':tid', $tid,     PDO::PARAM_INT);
                $ins->execute();
            }
        }
        db_close($db);

        write_debug_log("WORKFLOW: update_test_field test_id={$test_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['test_id' => $test_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    // ── Auto-audit initiation (derived from audit_initiation_offset nullability) ──
    if ($field === 'test__auto_audit_initiation') {
        // '1' = enable (set offset to 0 if currently null), '0' = disable (set offset to null)
        $enable = ((string)$value === '1');

        if ($dry_run) {
            write_debug_log("WORKFLOW DRY-RUN: update_test_field test_id={$test_id} field={$field} value={$value}", 'info');
            return ['status' => 'success', 'output' => ['dry_run' => true, 'test_id' => $test_id, 'field' => $field, 'value' => $value], 'error' => null];
        }

        $db = db_open();
        if ($enable) {
            // Only set to 0 if currently NULL (don't overwrite an existing offset)
            $stmt = $db->prepare("UPDATE `framework_control_tests` SET `audit_initiation_offset` = 0 WHERE `id` = :id AND `audit_initiation_offset` IS NULL");
        } else {
            $stmt = $db->prepare("UPDATE `framework_control_tests` SET `audit_initiation_offset` = NULL WHERE `id` = :id");
        }
        $stmt->bindParam(':id', $test_id, PDO::PARAM_INT);
        $stmt->execute();
        db_close($db);

        write_debug_log("WORKFLOW: update_test_field test_id={$test_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['test_id' => $test_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    // ── Direct column map ───────────────────────────────────────────────
    $field_map = [
        'test__name'                    => 'name',
        'test__tester'                  => 'tester',
        'test__additional_stakeholders' => 'additional_stakeholders',
        'test__test_frequency'          => 'test_frequency',
        'test__last_date'               => 'last_date',
        'test__audit_initiation_offset' => 'audit_initiation_offset',
        'test__objective'               => 'objective',
        'test__test_steps'              => 'test_steps',
        'test__approximate_time'        => 'approximate_time',
        'test__expected_results'        => 'expected_results',
    ];

    if (!isset($field_map[$field])) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_test_field: Field '{$field}' is not an updatable field."];
    }

    $column = $field_map[$field];

    if (!_wf_safe_column($column)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_test_field: Internal error — invalid column identifier.'];
    }

    // Date conversion
    if ($field === 'test__last_date' && !empty($value)) {
        $value = get_standard_date_from_default_format($value);
        if ($value === '0000-00-00') $value = null;
    }

    // additional_stakeholders: comma-separated user IDs from multiselect
    if ($field === 'test__additional_stakeholders') {
        $ids   = array_values(array_filter(array_map('intval', explode(',', $value))));
        $value = implode(',', $ids);
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: update_test_field test_id={$test_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'test_id' => $test_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db   = db_open();
    $stmt = $db->prepare("UPDATE `framework_control_tests` SET `{$column}` = :value WHERE `id` = :id");
    $stmt->bindValue(':value', $value);
    $stmt->bindParam(':id', $test_id, PDO::PARAM_INT);
    $stmt->execute();
    $affected = $stmt->rowCount();
    db_close($db);

    if ($affected === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_test_field: Test ID {$test_id} not found."];
    }

    write_debug_log("WORKFLOW: update_test_field test_id={$test_id} field={$field} value={$value}", 'info');
    return ['status' => 'success', 'output' => ['test_id' => $test_id, 'field' => $field, 'value' => $value], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE AUDIT FIELD
 * Updates a single field on a framework_control_test_audits
 * or framework_control_test_results record.
 * Reads audit_id from the workflow context.
 * Teams are stored in items_to_teams (type='audit').
 ***********************************************/
function workflow_action_update_audit_field(array $inputs, array $context): array
{
    $dry_run  = (bool)($inputs['_dry_run'] ?? false);
    $audit_id = (int)($context['audit_id'] ?? 0);
    $field    = trim($inputs['field'] ?? '');
    $value    = $inputs['value'] ?? '';

    if ($audit_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_audit_field: audit_id (from context) and field are required.'];
    }

    // ── Teams junction table ────────────────────────────────────────────
    if ($field === 'audit__teams') {
        $team_ids = array_values(array_filter(array_map('intval', explode(',', $value))));

        if ($dry_run) {
            write_debug_log("WORKFLOW DRY-RUN: update_audit_field audit_id={$audit_id} field={$field} value={$value}", 'info');
            return ['status' => 'success', 'output' => ['dry_run' => true, 'audit_id' => $audit_id, 'field' => $field, 'value' => $value], 'error' => null];
        }

        $db = db_open();
        $stmt = $db->prepare("DELETE FROM `items_to_teams` WHERE `item_id` = :id AND `type` = 'audit'");
        $stmt->bindParam(':id', $audit_id, PDO::PARAM_INT);
        $stmt->execute();
        if (!empty($team_ids)) {
            $ins = $db->prepare("INSERT INTO `items_to_teams` (`item_id`, `type`, `team_id`) VALUES (:id, 'audit', :tid)");
            foreach ($team_ids as $tid) {
                $ins->bindParam(':id',  $audit_id, PDO::PARAM_INT);
                $ins->bindParam(':tid', $tid,      PDO::PARAM_INT);
                $ins->execute();
            }
        }
        db_close($db);

        write_debug_log("WORKFLOW: update_audit_field audit_id={$audit_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['audit_id' => $audit_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    // ── audit_audit fields (framework_control_test_audits columns) ──────
    $audit_columns = [
        'audit__status' => 'status',
        'audit__tester' => 'tester',
    ];

    // ── Result fields (framework_control_test_results columns) ──────────
    $result_columns = [
        'audit__test_result' => 'test_result',
        'audit__test_date'   => 'test_date',
        'audit__summary'     => 'summary',
    ];

    if (!isset($audit_columns[$field]) && !isset($result_columns[$field])) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_audit_field: Field '{$field}' is not an updatable field."];
    }

    // Date conversion for test_date
    if ($field === 'audit__test_date' && !empty($value)) {
        $value = get_standard_date_from_default_format($value);
        if ($value === '0000-00-00') $value = null;
    }

    if ($dry_run) {
        write_debug_log("WORKFLOW DRY-RUN: update_audit_field audit_id={$audit_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'audit_id' => $audit_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db = db_open();

    if (isset($audit_columns[$field])) {
        $column = $audit_columns[$field];
        if (!_wf_safe_column($column)) { db_close($db); return ['status' => 'failed', 'output' => [], 'error' => 'update_audit_field: Internal error — invalid column identifier.']; }
        $stmt = $db->prepare("UPDATE `framework_control_test_audits` SET `{$column}` = :value WHERE `id` = :id");
        $stmt->bindValue(':value', $value);
        $stmt->bindParam(':id', $audit_id, PDO::PARAM_INT);
        $stmt->execute();
        $affected = $stmt->rowCount();
    } else {
        $column = $result_columns[$field];
        if (!_wf_safe_column($column)) { db_close($db); return ['status' => 'failed', 'output' => [], 'error' => 'update_audit_field: Internal error — invalid column identifier.']; }
        // Upsert: insert row if it doesn't exist yet, then update
        $stmt = $db->prepare("INSERT IGNORE INTO `framework_control_test_results` (`test_audit_id`) VALUES (:id)");
        $stmt->bindParam(':id', $audit_id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `framework_control_test_results` SET `{$column}` = :value WHERE `test_audit_id` = :id");
        $stmt->bindValue(':value', $value);
        $stmt->bindParam(':id', $audit_id, PDO::PARAM_INT);
        $stmt->execute();
        $affected = $stmt->rowCount();
    }

    db_close($db);

    if ($affected === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_audit_field: Audit ID {$audit_id} not found."];
    }

    write_debug_log("WORKFLOW: update_audit_field audit_id={$audit_id} field={$field} value={$value}", 'info');
    return ['status' => 'success', 'output' => ['audit_id' => $audit_id, 'field' => $field, 'value' => $value], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE DOCUMENT FIELD
 * Updates a single field on a documents record.
 * Reads document_id from the workflow context.
 * Frameworks and controls are stored in junction
 * tables; all others are direct column updates.
 ***********************************************/
function workflow_action_update_document_field(array $inputs, array $context): array
{
    $dry_run     = (bool)($inputs['_dry_run'] ?? false);
    $document_id = (int)($context['document_id'] ?? 0);
    $field       = trim($inputs['field'] ?? '');
    $value       = $inputs['value'] ?? '';

    // Date fields — stored as Y-m-d; convert from user's display format
    static $date_fields = [
        'document__creation_date',
        'document__last_review_date',
        'document__next_review_date',
        'document__approval_date',
    ];
    if (in_array($field, $date_fields, true) && !empty($value)) {
        $value = get_standard_date_from_default_format($value);
        if ($value === '0000-00-00') $value = null;
    }

    // Direct-column fields
    $field_map = [
        'document__document_type'           => 'document_type',
        'document__document_name'           => 'document_name',
        'document__additional_stakeholders' => 'additional_stakeholders',
        'document__document_owner'          => 'document_owner',
        'document__team_ids'                => 'team_ids',
        'document__creation_date'           => 'creation_date',
        'document__last_review_date'        => 'last_review_date',
        'document__review_frequency'        => 'review_frequency',
        'document__next_review_date'        => 'next_review_date',
        'document__approval_date'           => 'approval_date',
        'document__approver'                => 'approver',
        'document__parent'                  => 'parent',
        'document__document_status'         => 'document_status',
    ];

    $is_framework_junction = ($field === 'document__document_frameworks');
    $is_control_junction   = ($field === 'document__document_controls');

    if ($document_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_document_field: document_id (from context) and field are required.'];
    }

    if (!$is_framework_junction && !$is_control_junction && !isset($field_map[$field])) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_document_field: Field '{$field}' is not an updatable field."];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: update_document_field document_id={$document_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'document_id' => $document_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db = db_open();

    if ($is_framework_junction)
    {
        $framework_ids = array_values(array_filter(array_map('intval', explode(',', $value))));
        $stmt = $db->prepare("DELETE FROM `document_framework_mappings` WHERE `document_id` = :doc_id");
        $stmt->bindParam(':doc_id', $document_id, PDO::PARAM_INT);
        $stmt->execute();
        if (!empty($framework_ids)) {
            $ins = $db->prepare("INSERT INTO `document_framework_mappings` (`document_id`, `framework_id`) VALUES (:doc_id, :fw_id)");
            foreach ($framework_ids as $fw_id) {
                $ins->bindValue(':doc_id', $document_id, PDO::PARAM_INT);
                $ins->bindValue(':fw_id',  $fw_id,       PDO::PARAM_INT);
                $ins->execute();
            }
        }
    }
    elseif ($is_control_junction)
    {
        $control_ids = array_values(array_filter(array_map('intval', explode(',', $value))));
        $stmt = $db->prepare("DELETE FROM `document_control_mappings` WHERE `document_id` = :doc_id");
        $stmt->bindParam(':doc_id', $document_id, PDO::PARAM_INT);
        $stmt->execute();
        if (!empty($control_ids)) {
            $ins = $db->prepare("INSERT INTO `document_control_mappings` (`document_id`, `control_id`, `selected`) VALUES (:doc_id, :ctrl_id, 1)");
            foreach ($control_ids as $ctrl_id) {
                $ins->bindValue(':doc_id',  $document_id, PDO::PARAM_INT);
                $ins->bindValue(':ctrl_id', $ctrl_id,     PDO::PARAM_INT);
                $ins->execute();
            }
        }
    }
    else
    {
        $column = $field_map[$field];
        if (!_wf_safe_column($column)) { db_close($db); return ['status' => 'failed', 'output' => [], 'error' => 'update_document_field: Internal error — invalid column identifier.']; }
        $stmt = $db->prepare("UPDATE `documents` SET `{$column}` = :value WHERE `id` = :id");
        $stmt->bindValue(':value', $value);
        $stmt->bindParam(':id', $document_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            db_close($db);
            return ['status' => 'failed', 'output' => [], 'error' => "update_document_field: Document ID {$document_id} not found."];
        }
    }

    db_close($db);

    write_debug_log("WORKFLOW: update_document_field document_id={$document_id} field={$field} value={$value}", 'info');
    return ['status' => 'success', 'output' => ['document_id' => $document_id, 'field' => $field, 'value' => $value], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — UPDATE EXCEPTION FIELD
 * Updates a single field on a document_exceptions record.
 ***********************************************/
function workflow_action_update_exception_field(array $inputs, array $context): array
{
    $dry_run      = (bool)($inputs['_dry_run'] ?? false);
    $exception_id = (int)($context['exception_id'] ?? 0);
    $field        = trim($inputs['field'] ?? '');
    $value        = $inputs['value'] ?? '';

    // Date fields — stored as Y-m-d; convert from user's display format
    static $date_fields = [
        'exception__creation_date',
        'exception__next_review_date',
        'exception__approval_date',
    ];
    if (in_array($field, $date_fields, true) && !empty($value)) {
        $value = get_standard_date_from_default_format($value);
        if ($value === '0000-00-00') $value = null;
    }

    $field_map = [
        'exception__name'                    => 'name',
        'exception__status'                  => 'status',
        'exception__policy'                  => 'policy_document_id',
        'exception__framework'               => 'framework_id',
        'exception__control'                 => 'control_framework_id',
        'exception__associated_risks'        => 'associated_risks',
        'exception__owner'                   => 'owner',
        'exception__additional_stakeholders' => 'additional_stakeholders',
        'exception__creation_date'           => 'creation_date',
        'exception__review_frequency'        => 'review_frequency',
        'exception__next_review_date'        => 'next_review_date',
        'exception__approval_date'           => 'approval_date',
        'exception__approver'                => 'approver',
        'exception__description'             => 'description',
        'exception__justification'           => 'justification',
    ];

    if ($exception_id <= 0 || empty($field)) {
        return ['status' => 'failed', 'output' => [], 'error' => 'update_exception_field: exception_id (from context) and field are required.'];
    }

    if (!isset($field_map[$field])) {
        return ['status' => 'failed', 'output' => [], 'error' => "update_exception_field: Field '{$field}' is not an updatable field."];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: update_exception_field exception_id={$exception_id} field={$field} value={$value}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'exception_id' => $exception_id, 'field' => $field, 'value' => $value], 'error' => null];
    }

    $db     = db_open();
    $column = $field_map[$field];

    if (!_wf_safe_column($column)) { db_close($db); return ['status' => 'failed', 'output' => [], 'error' => 'update_exception_field: Internal error — invalid column identifier.']; }

    $stmt = $db->prepare("UPDATE `document_exceptions` SET `{$column}` = :value WHERE `value` = :id");
    $stmt->bindValue(':value', $value);
    $stmt->bindParam(':id', $exception_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        db_close($db);
        return ['status' => 'failed', 'output' => [], 'error' => "update_exception_field: Exception ID {$exception_id} not found."];
    }

    db_close($db);

    write_debug_log("WORKFLOW: update_exception_field exception_id={$exception_id} field={$field} value={$value}", 'info');
    return ['status' => 'success', 'output' => ['exception_id' => $exception_id, 'field' => $field, 'value' => $value], 'error' => null];
}
