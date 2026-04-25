<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/***********************************************
 * FUNCTION: WORKFLOW ACTION — ASSIGN CONTROL OWNER
 ***********************************************/
function workflow_action_assign_control_owner(array $inputs, array $context): array
{
    $dry_run    = (bool)($inputs['_dry_run'] ?? false);
    $control_id = (int)($inputs['control_id'] ?? 0);
    $user_id    = (int)($inputs['user_id']    ?? 0);

    if ($control_id <= 0 || $user_id <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'assign_control_owner: control_id and user_id are required.'];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: assign_control_owner control_id={$control_id} user_id={$user_id}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'control_id' => $control_id, 'user_id' => $user_id], 'error' => null];
    }

    $db   = db_open();
    $stmt = $db->prepare("UPDATE `framework_controls` SET `control_owner` = :owner WHERE `id` = :id");
    $stmt->bindParam(':owner', $user_id,    PDO::PARAM_INT);
    $stmt->bindParam(':id',    $control_id, PDO::PARAM_INT);
    $stmt->execute();
    $affected = $stmt->rowCount();
    db_close($db);

    if ($affected === 0) {
        return ['status' => 'failed', 'output' => [], 'error' => "assign_control_owner: Control ID {$control_id} not found."];
    }

    write_debug_log("WORKFLOW: assign_control_owner control_id={$control_id} user_id={$user_id}", 'info');
    return ['status' => 'success', 'output' => ['control_id' => $control_id, 'user_id' => $user_id], 'error' => null];
}

/***********************************************
 * FUNCTION: WORKFLOW ACTION — CREATE TEST TASK *
 ***********************************************/
function workflow_action_create_test_task(array $inputs, array $context): array
{
    $dry_run    = (bool)($inputs['_dry_run'] ?? false);
    $control_id = (int)($inputs['control_id'] ?? 0);
    $tester     = (int)($inputs['tester']     ?? 0);
    $due_date   = trim($inputs['due_date']    ?? date('Y-m-d', strtotime('+30 days')));

    if ($control_id <= 0 || $tester <= 0) {
        return ['status' => 'failed', 'output' => [], 'error' => 'create_test_task: control_id and tester are required.'];
    }

    if ($dry_run)
    {
        write_debug_log("WORKFLOW DRY-RUN: create_test_task control_id={$control_id} tester={$tester}", 'info');
        return ['status' => 'success', 'output' => ['dry_run' => true, 'control_id' => $control_id, 'tester' => $tester], 'error' => null];
    }

    $created_at = date('Y-m-d H:i:s');
    $db   = db_open();
    $stmt = $db->prepare("
        INSERT INTO `framework_control_tests`
            (`framework_control_id`, `tester`, `test_frequency`, `last_date`, `next_date`, `created_at`)
        VALUES
            (:control_id, :tester, 365, NULL, :due_date, :created_at)
    ");
    $stmt->bindParam(':control_id', $control_id, PDO::PARAM_INT);
    $stmt->bindParam(':tester',     $tester,     PDO::PARAM_INT);
    $stmt->bindParam(':due_date',   $due_date,   PDO::PARAM_STR);
    $stmt->bindParam(':created_at', $created_at, PDO::PARAM_STR);
    $stmt->execute();
    $test_id = (int)$db->lastInsertId();
    db_close($db);

    write_debug_log("WORKFLOW: create_test_task control_id={$control_id} test_id={$test_id}", 'info');
    return ['status' => 'success', 'output' => ['control_id' => $control_id, 'test_id' => $test_id, 'due_date' => $due_date], 'error' => null];
}
