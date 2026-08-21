<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// The Plan-A HITL proposal registry and the Core write path this capability
// dispatches into on approval.
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/ai_proposals.php'));
require_once(realpath(__DIR__ . '/compliance.php'));

/**
 * Registers every ai_proposals capability that Core GRC write paths expose
 * for AI-generated proposals. Idempotent — ai_proposal_register_capability()
 * overwrites the same registry key, so calling this on every request (as the
 * v2 proposal endpoints do) is safe.
 */
function register_ai_proposal_capabilities(): void
{
    ai_proposal_register_capability('control_test_generation', 'define_tests', 'ai_apply_control_test_proposal');
}

/**
 * Apply handler for the control_test_generation capability: on approval,
 * writes the proposed test via the existing Core write path
 * (add_framework_control_test()). This is the FIRST real GRC-writing apply
 * handler registered on the Plan-A ai_proposals framework — it must fail
 * closed (return false, no write) on any invalid payload, and it must never
 * write framework_control_tests except through add_framework_control_test().
 *
 * The proposal's single target control is passed both as the scalar
 * `$framework_control_id` (min/primary control, kept for legacy readers)
 * and, explicitly, via the `controls:` named argument so
 * add_framework_control_test() writes the `test_control_map` M:N junction
 * row for it — the row the junction-based graph walkers
 * (get_test_connectivity_for_control() / get_control_connectivity_for_test()
 * in entity_graph.php) require to make the created test visible.
 */
/**
 * The single source of truth for the "a proposed cadence-in-days IS an interval
 * schedule" rule: a positive test_frequency maps to schedule_type 'interval',
 * anything else to null (manual). Shared by ai_apply_control_test_proposal()
 * (the direct-approve path) and build_ai_suggestion_row() (compliance_grid.php,
 * which exposes the resulting schedule_type to the Review & edit prefill), so the
 * grid chip, the applied test, and the modal prefill can't drift.
 */
function ai_control_test_schedule_type($test_frequency): ?string
{
    return (is_numeric($test_frequency) && (int)$test_frequency > 0) ? 'interval' : null;
}

function ai_apply_control_test_proposal(array $proposal): bool
{
    $p = $proposal['proposed_payload'] ?? null;
    if (!is_array($p)) {
        write_debug_log("control_test apply: missing/invalid payload for proposal " . ($proposal['id'] ?? '?'), 'warning');
        return false;
    }

    foreach (['name', 'objective', 'test_steps', 'expected_results'] as $field) {
        if (!isset($p[$field]) || !is_string($p[$field]) || $p[$field] === '') {
            write_debug_log("control_test apply: missing/invalid '{$field}' for proposal " . ($proposal['id'] ?? '?'), 'warning');
            return false;
        }
    }

    $target_id = (int)($proposal['target_id'] ?? 0);
    if ($target_id <= 0) {
        write_debug_log("control_test apply: missing/invalid target_id for proposal " . ($proposal['id'] ?? '?'), 'warning');
        return false;
    }

    $test_frequency = (int)($p['test_frequency'] ?? 0);

    // A proposed cadence-in-days IS an interval schedule (positive frequency →
    // 'interval', else manual). Shared with the grid row + Review & edit prefill
    // via ai_control_test_schedule_type() so the three can't drift.
    $schedule_type = ai_control_test_schedule_type($test_frequency);

    // sample / required_evidence are optional in the payload (the parser
    // defaults them to ''); pass them through so an approved AI test carries the
    // model's sampling approach and evidence guidance.
    $sample            = is_string($p['sample'] ?? null) ? $p['sample'] : '';
    $required_evidence = is_string($p['required_evidence'] ?? null) ? $p['required_evidence'] : '';

    // The approving reviewer becomes the test's tester.
    $tester = (int)($_SESSION['uid'] ?? 0);

    $test_id = add_framework_control_test(
        $tester,
        $test_frequency,
        $p['name'],
        $p['objective'],
        $p['test_steps'],
        0,
        $p['expected_results'],
        $target_id,
        schedule_type: $schedule_type,
        sample: $sample,
        required_evidence: $required_evidence,
        controls: [$target_id]
    );

    return is_numeric($test_id) && (int)$test_id > 0;
}
