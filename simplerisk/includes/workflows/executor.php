<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/***********************************************
 * FUNCTION: EXECUTE WORKFLOW EXECUTION        *
 * Main entry point called by the job runner.  *
 * Walks the workflow JSON definition and      *
 * dispatches each action node.                *
 ***********************************************/
function execute_workflow_execution(int $execution_id, PDO $db, bool $inline = false): bool
{
    // Fetch the execution and its workflow definition
    $stmt = $db->prepare("
        SELECT e.*, d.`definition`, d.`name` as workflow_name
        FROM `workflow_executions` e
        JOIN `workflow_definitions` d ON e.`workflow_id` = d.`id`
        WHERE e.`id` = :id
    ");
    $stmt->bindParam(':id', $execution_id, PDO::PARAM_INT);
    $stmt->execute();
    $execution = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$execution)
    {
        write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} not found.", 'error');
        return false;
    }

    $context    = json_decode($execution['context'],    true) ?: [];
    $definition = json_decode($execution['definition'], true) ?: [];
    $dry_run    = (bool)($context['dry_run'] ?? false);

    if (empty($definition['nodes']))
    {
        write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} has an empty or invalid definition.", 'error');
        update_workflow_execution_status($execution_id, 'failed', $db);
        return false;
    }

    write_debug_log("WORKFLOW EXECUTOR: Starting execution #{$execution_id} (workflow: '{$execution['workflow_name']}', dry_run: " . ($dry_run ? 'yes' : 'no') . ")", 'info');

    // Mark as in_progress and record start time
    $stmt = $db->prepare("UPDATE `workflow_executions` SET `status` = 'in_progress', `started_at` = NOW() WHERE `id` = :id AND `started_at` IS NULL");
    $stmt->bindParam(':id', $execution_id, PDO::PARAM_INT);
    $stmt->execute();

    update_workflow_execution_status($execution_id, 'in_progress', $db);

    // Load previous step outputs (needed when resuming after a Delay)
    $step_outputs = get_workflow_step_outputs($execution_id, $db);

    // Determine the starting node
    $current_node_id = $execution['current_node'] ?: find_workflow_start_node($definition);

    if (!$current_node_id)
    {
        write_debug_log("WORKFLOW EXECUTOR: No start node found in execution #{$execution_id}.", 'error');
        update_workflow_execution_status($execution_id, 'failed', $db);
        return false;
    }

    $max_steps  = 100;
    $step_count = 0;

    while ($current_node_id !== null && $step_count < $max_steps)
    {
        $step_count++;
        $node = $definition['nodes'][$current_node_id] ?? null;

        if (!$node)
        {
            write_debug_log("WORKFLOW EXECUTOR: Node '{$current_node_id}' not found in execution #{$execution_id}.", 'error');
            update_workflow_execution_status($execution_id, 'failed', $db);
            return false;
        }

        $node_type = $node['type'] ?? 'unknown';

        // ── Trigger node: skip to first action ──────────────────────────
        if ($node_type === 'trigger')
        {
            $current_node_id = $node['output'] ?? null;
            continue;
        }

        // ── Delay node ───────────────────────────────────────────────────
        if ($node_type === 'delay')
        {
            $inputs     = resolve_node_inputs($node['inputs'] ?? [], $context, $step_outputs);
            $resume_at  = calculate_workflow_resume_at($inputs);
            $resume_str = $resume_at->format('Y-m-d H:i:s');

            $stmt = $db->prepare("
                UPDATE `workflow_executions`
                SET `current_node` = :node, `resume_at` = :resume_at
                WHERE `id` = :id
            ");
            $stmt->bindParam(':node',      $current_node_id, PDO::PARAM_STR);
            $stmt->bindParam(':resume_at', $resume_str,      PDO::PARAM_STR);
            $stmt->bindParam(':id',        $execution_id,    PDO::PARAM_INT);
            $stmt->execute();

            record_workflow_step($execution_id, $current_node_id, 'delay', 'success', $inputs,
                ['resume_at' => $resume_str], null, $dry_run, $db);

            write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} suspended until {$resume_str} (delay node).", 'info');
            // Return true — job runner will re-queue when resume_at arrives
            return true;
        }

        // ── Branch node ──────────────────────────────────────────────────
        if ($node_type === 'branch')
        {
            $condition = [
                'field'    => resolve_workflow_variables($node['condition']['field']    ?? '', $context, $step_outputs),
                'operator' => $node['condition']['operator'] ?? '=',
                'value'    => resolve_workflow_variables((string)($node['condition']['value'] ?? ''), $context, $step_outputs),
            ];

            $branch_result   = evaluate_workflow_condition($condition, $context, $step_outputs);
            $next_node_id    = $branch_result ? ($node['output_true'] ?? null) : ($node['output_false'] ?? null);

            record_workflow_step($execution_id, $current_node_id, 'branch', 'success',
                ['condition' => $condition],
                ['result' => $branch_result, 'next_node' => $next_node_id],
                null, $dry_run, $db);

            write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} branch evaluated to " . ($branch_result ? 'true' : 'false') . ", next node: " . ($next_node_id ?? 'null'), 'info');
            $current_node_id = $next_node_id;
            continue;
        }

        // ── Wait-for-condition node ──────────────────────────────────────
        if ($node_type === 'wait_for_condition')
        {
            $inputs    = resolve_node_inputs($node['inputs'] ?? [], $context, $step_outputs);
            $condition = [
                'field'    => $inputs['field']    ?? '',
                'operator' => $inputs['operator'] ?? '=',
                'value'    => $inputs['value']    ?? '',
            ];

            // Re-evaluate the condition fresh from DB context if possible
            if (evaluate_workflow_condition($condition, $context, $step_outputs))
            {
                record_workflow_step($execution_id, $current_node_id, 'wait_for_condition', 'success',
                    $inputs, ['condition_met' => true], null, $dry_run, $db);
                $current_node_id = $node['output'] ?? null;
            }
            else
            {
                // Check timeout
                $timeout_hours  = (int)($inputs['timeout'] ?? 24);
                $started_at     = strtotime($execution['started_at'] ?: 'now');
                $elapsed_hours  = (time() - $started_at) / 3600;

                if ($elapsed_hours >= $timeout_hours)
                {
                    record_workflow_step($execution_id, $current_node_id, 'wait_for_condition', 'failed',
                        $inputs, ['condition_met' => false, 'timed_out' => true], 'Timeout reached', $dry_run, $db);

                    $on_error = $node['on_error'] ?? 'stop';
                    if ($on_error === 'stop') {
                        update_workflow_execution_status($execution_id, 'failed', $db);
                        return false;
                    }
                    $current_node_id = $node['output'] ?? null;
                }
                else
                {
                    // Re-schedule to check again in 15 minutes
                    $resume_at = (new DateTime())->modify('+15 minutes')->format('Y-m-d H:i:s');
                    $stmt = $db->prepare("UPDATE `workflow_executions` SET `current_node` = :node, `resume_at` = :resume_at WHERE `id` = :id");
                    $stmt->bindParam(':node',      $current_node_id, PDO::PARAM_STR);
                    $stmt->bindParam(':resume_at', $resume_at,       PDO::PARAM_STR);
                    $stmt->bindParam(':id',        $execution_id,    PDO::PARAM_INT);
                    $stmt->execute();

                    write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} waiting for condition; rechecking at {$resume_at}.", 'info');
                    return true;
                }
            }
            continue;
        }

        // ── Action node ──────────────────────────────────────────────────
        if ($node_type === 'action')
        {
            $action_type = $node['action'] ?? 'unknown';
            $raw_inputs  = $node['inputs'] ?? [];
            $inputs      = resolve_node_inputs($raw_inputs, $context, $step_outputs);
            $inputs['_dry_run'] = $dry_run;

            // When running inline (sync workflow dispatch), check if this action should be queued
            $action_catalog = get_workflow_action_catalog();
            $is_sync_action = (bool)($action_catalog[$action_type]['sync'] ?? true);

            if ($inline && !$is_sync_action && !$dry_run)
            {
                unset($inputs['_dry_run']);
                write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} queuing async action '{$action_type}' on node '{$current_node_id}'.", 'info');

                $step_id = record_workflow_step($execution_id, $current_node_id, $action_type, 'queued',
                    $inputs, [], null, false, $db);

                require_once(realpath(__DIR__ . '/../queues.php'));
                queue_task($db, 'core_workflow_action_execute', [
                    'execution_id' => $execution_id,
                    'step_id'      => $step_id,
                    'action_type'  => $action_type,
                    'inputs'       => $inputs,
                    'context'      => $context,
                ]);

                // Async actions produce no output for downstream resolution
                $step_outputs[$current_node_id] = [];
                $current_node_id = $node['output'] ?? null;

                $stmt = $db->prepare("UPDATE `workflow_executions` SET `current_node` = :node WHERE `id` = :id");
                $stmt->bindParam(':node', $current_node_id, PDO::PARAM_STR);
                $stmt->bindParam(':id',   $execution_id,   PDO::PARAM_INT);
                $stmt->execute();

                continue;
            }

            write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} running action '{$action_type}' on node '{$current_node_id}'.", 'info');

            $result = execute_workflow_action($action_type, $inputs, $context);

            $step_status = $result['status'] ?? 'failed';
            $output      = $result['output'] ?? [];
            $error       = $result['error']  ?? null;

            // Strip internal _dry_run key before recording
            unset($inputs['_dry_run']);

            record_workflow_step($execution_id, $current_node_id, $action_type, $step_status,
                $inputs, $output, $error, $dry_run, $db);

            // Preserve output for downstream variable resolution
            $step_outputs[$current_node_id] = $output;

            if ($step_status === 'failed')
            {
                $on_error = $node['on_error'] ?? 'stop';
                write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} action '{$action_type}' failed. on_error={$on_error}.", 'error');

                if ($on_error === 'stop') {
                    update_workflow_execution_status($execution_id, 'failed', $db);
                    return false;
                }
                // on_error = 'continue' — fall through to next node
            }

            $current_node_id = $node['output'] ?? null;

            // Persist current node position in case of unexpected interruption
            $stmt = $db->prepare("UPDATE `workflow_executions` SET `current_node` = :node WHERE `id` = :id");
            $stmt->bindParam(':node', $current_node_id, PDO::PARAM_STR);
            $stmt->bindParam(':id',   $execution_id,   PDO::PARAM_INT);
            $stmt->execute();

            continue;
        }

        // Unknown node type — treat as fatal
        write_debug_log("WORKFLOW EXECUTOR: Unknown node type '{$node_type}' in execution #{$execution_id}.", 'error');
        update_workflow_execution_status($execution_id, 'failed', $db);
        return false;
    }

    if ($step_count >= $max_steps)
    {
        write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} exceeded maximum step limit ({$max_steps}).", 'error');
        update_workflow_execution_status($execution_id, 'failed', $db);
        return false;
    }

    // All nodes processed — mark as completed
    $stmt = $db->prepare("
        UPDATE `workflow_executions`
        SET `status` = 'completed', `completed_at` = NOW(), `current_node` = NULL, `resume_at` = NULL
        WHERE `id` = :id
    ");
    $stmt->bindParam(':id', $execution_id, PDO::PARAM_INT);
    $stmt->execute();

    write_debug_log("WORKFLOW EXECUTOR: Execution #{$execution_id} completed successfully.", 'info');
    return true;
}

/***********************************************
 * FUNCTION: FIND WORKFLOW START NODE          *
 ***********************************************/
function find_workflow_start_node(array $definition): ?string
{
    foreach ($definition['nodes'] as $node_id => $node)
    {
        if (($node['type'] ?? '') === 'trigger') {
            return $node_id;
        }
    }
    return null;
}

/***********************************************
 * FUNCTION: GET WORKFLOW STEP OUTPUTS         *
 * Returns an associative array of node_id     *
 * => output for all completed steps in an     *
 * execution (used when resuming).             *
 ***********************************************/
function get_workflow_step_outputs(int $execution_id, PDO $db): array
{
    $stmt = $db->prepare("
        SELECT `node_id`, `output`
        FROM `workflow_execution_steps`
        WHERE `execution_id` = :execution_id AND `status` = 'success'
        ORDER BY `id` ASC
    ");
    $stmt->bindParam(':execution_id', $execution_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $outputs = [];
    foreach ($rows as $row) {
        $outputs[$row['node_id']] = json_decode($row['output'], true) ?: [];
    }
    return $outputs;
}

/***********************************************
 * FUNCTION: RECORD WORKFLOW STEP              *
 ***********************************************/
function record_workflow_step(
    int    $execution_id,
    string $node_id,
    string $node_type,
    string $status,
    array  $input,
    array  $output,
    ?string $error,
    bool   $dry_run,
    PDO    $db
): int {
    $input_json  = json_encode($input);
    $output_json = json_encode($output);
    $dry_run_int = $dry_run ? 1 : 0;

    $stmt = $db->prepare("
        INSERT INTO `workflow_execution_steps`
            (`execution_id`, `node_id`, `node_type`, `status`, `dry_run`, `input`, `output`, `error`, `executed_at`)
        VALUES
            (:execution_id, :node_id, :node_type, :status, :dry_run, :input, :output, :error, NOW())
    ");
    $stmt->bindParam(':execution_id', $execution_id, PDO::PARAM_INT);
    $stmt->bindParam(':node_id',      $node_id,      PDO::PARAM_STR);
    $stmt->bindParam(':node_type',    $node_type,    PDO::PARAM_STR);
    $stmt->bindParam(':status',       $status,       PDO::PARAM_STR);
    $stmt->bindParam(':dry_run',      $dry_run_int,  PDO::PARAM_INT);
    $stmt->bindParam(':input',        $input_json,   PDO::PARAM_STR);
    $stmt->bindParam(':output',       $output_json,  PDO::PARAM_STR);
    $stmt->bindParam(':error',        $error,        PDO::PARAM_STR);
    $stmt->execute();

    return (int)$db->lastInsertId();
}

/***********************************************
 * FUNCTION: UPDATE WORKFLOW EXECUTION STATUS  *
 ***********************************************/
function update_workflow_execution_status(int $execution_id, string $status, PDO $db): void
{
    $stmt = $db->prepare("UPDATE `workflow_executions` SET `status` = :status WHERE `id` = :id");
    $stmt->bindParam(':status', $status,       PDO::PARAM_STR);
    $stmt->bindParam(':id',     $execution_id, PDO::PARAM_INT);
    $stmt->execute();
}

/***********************************************
 * FUNCTION: CALCULATE WORKFLOW RESUME AT      *
 ***********************************************/
function calculate_workflow_resume_at(array $inputs): DateTime
{
    $duration = max(1, (int)($inputs['duration'] ?? 1));
    $unit     = $inputs['unit'] ?? 'hours';

    $dt = new DateTime();
    switch ($unit)
    {
        case 'minutes': $dt->modify("+{$duration} minutes"); break;
        case 'days':    $dt->modify("+{$duration} days");    break;
        default:        $dt->modify("+{$duration} hours");   break;
    }
    return $dt;
}
