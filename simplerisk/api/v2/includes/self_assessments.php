<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/self_assessments.php'));
// promote_pending_risk_to_risk() / delete_pending_risk() live here — require it
// directly rather than lean on self_assessments.php's transitive include.
require_once(realpath(__DIR__ . '/../../../includes/assessments.php'));

require_once(language_file());

/*******************************************
 * FUNCTION: API V2 SELF-ASSESSMENT        *
 * READ JSON BODY                          *
 *                                          *
 * Reads a JSON request body. Falls back   *
 * to $_POST for form-encoded / test        *
 * clients that don't send JSON.           *
 *******************************************/
function api_v2_self_assessment_read_json_body(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    if (is_array($body)) {
        return $body;
    }
    return $_POST ?: [];
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT FRAMEWORKS        *
 *                                                     *
 * Lists SCF frameworks available for self-assessment,*
 * with question counts. scope=enabled is restricted  *
 * to governance users, and the `enabled` flag itself  *
 * is suppressed from scope=all responses for users     *
 * without governance permission.                      *
 *****************************************************/
function api_v2_self_assessment_frameworks()
{
    api_v2_check_permission('assessments');

    if (!self_assessment_scf_installed()) {
        json_response(409, "SCF is not installed.", null);
    }

    $scope = get_param('GET', 'scope', 'all') === 'enabled' ? 'enabled' : 'all';
    $can_gov = check_permission('governance');

    if ($scope === 'enabled' && !$can_gov) {
        json_response(403, "FORBIDDEN: Governance permission required for enabled scope.", null);
    }

    // Non-governance users may only see enabled/disabled status via the
    // scope=enabled request itself (which is gated above) — the helper strips
    // the `enabled` flag from the scope=all listing so it doesn't leak.
    $frameworks = self_assessment_visible_frameworks(
        get_self_assessment_frameworks($scope),
        $can_gov
    );

    json_response(200, "SUCCESS", ['frameworks' => $frameworks]);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENTS LIST             *
 *                                                     *
 * Lists all self-assessment runs.                    *
 *****************************************************/
function api_v2_self_assessments_list()
{
    api_v2_check_permission('assessments');

    json_response(200, "SUCCESS", ['self_assessments' => get_self_assessments()]);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT CREATE            *
 *                                                     *
 * Starts a self-assessment run for a framework.      *
 *****************************************************/
function api_v2_self_assessment_create()
{
    api_v2_check_permission('assessments');

    if (!self_assessment_scf_installed()) {
        json_response(409, "SCF is not installed.", null);
    }

    $body = api_v2_self_assessment_read_json_body();
    $scf_source_id = (int)($body['scf_source_id'] ?? 0);
    $name = trim((string)($body['name'] ?? ''));

    if ($scf_source_id <= 0) {
        json_response(400, "Missing scf_source_id.", null);
    }

    if (empty(get_self_assessment_framework_controls($scf_source_id))) {
        json_response(400, "Invalid framework or no controls.", null);
    }

    $id = create_self_assessment($scf_source_id, $name, (int)($_SESSION['uid'] ?? 0));

    if (!$id) {
        json_response(500, "Failed to create self-assessment.", null);
    }

    json_response(201, "SUCCESS", get_self_assessment_with_controls($id));
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT GET               *
 *                                                     *
 * Gets a self-assessment run, its framework controls,*
 * and any responses recorded so far.                 *
 *****************************************************/
function api_v2_self_assessment_get($id = null)
{
    api_v2_check_permission('assessments');

    $data = get_self_assessment_with_controls((int)($id ?? 0));

    if (!$data) {
        json_response(404, "NOT FOUND", null);
    }

    json_response(200, "SUCCESS", $data);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT SAVE RESPONSES    *
 *                                                     *
 * Saves (upserts) responses for a self-assessment     *
 * run that has not yet been completed.                *
 *****************************************************/
function api_v2_self_assessment_save_responses($id = null)
{
    api_v2_check_permission('assessments');

    $id = (int)($id ?? 0);
    $sa = get_self_assessment($id);

    if (!$sa) {
        json_response(404, "NOT FOUND", null);
    }

    if ($sa['status'] === 'completed') {
        json_response(409, "Assessment already completed.", null);
    }

    $body = api_v2_self_assessment_read_json_body();
    $responses = $body['responses'] ?? [];

    if (!is_array($responses)) {
        json_response(400, "Invalid responses.", null);
    }

    $saved = save_self_assessment_responses($id, $responses);

    json_response(200, "SUCCESS", ['saved' => $saved]);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT COMPLETE          *
 *                                                     *
 * Completes a self-assessment run, generating pending*
 * risks from any failed controls.                    *
 *****************************************************/
function api_v2_self_assessment_complete($id = null)
{
    api_v2_check_permission('assessments');

    $id = (int)($id ?? 0);
    $sa = get_self_assessment($id);

    if (!$sa) {
        json_response(404, "NOT FOUND", null);
    }

    if ($sa['status'] === 'completed') {
        json_response(409, "Already completed.", null);
    }

    $generated = complete_self_assessment($id);

    json_response(200, "SUCCESS", ['pending_risks' => $generated]);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT DELETE            *
 *                                                     *
 * Deletes a self-assessment run and its responses.   *
 *****************************************************/
function api_v2_self_assessment_delete($id = null)
{
    api_v2_check_permission('assessments');

    $id = (int)($id ?? 0);

    if (!get_self_assessment($id)) {
        json_response(404, "NOT FOUND", null);
    }

    delete_self_assessment($id);

    json_response(200, "SUCCESS", ['deleted' => $id]);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT CONTROL RESULTS   *
 *                                                     *
 * Lists answered control responses across completed  *
 * self-assessments (Failed Controls tab), paginated  *
 * and filterable by response status.                  *
 *****************************************************/
function api_v2_self_assessment_control_results()
{
    api_v2_check_permission('assessments');

    $status = get_param('GET', 'status', 'fail');
    if (!in_array($status, ['pass', 'fail', 'na', 'all'], true)) {
        $status = 'fail';
    }

    $page = (int)get_param('GET', 'page', 1);
    if ($page < 1) {
        $page = 1;
    }

    $per_page = (int)get_param('GET', 'per_page', 25);
    if ($per_page < 1) {
        $per_page = 25;
    }
    if ($per_page > 200) {
        $per_page = 200;
    }

    $offset = ($page - 1) * $per_page;
    $result = get_self_assessment_control_results($status, $offset, $per_page);

    json_response(200, "SUCCESS", [
        'control_results' => $result['rows'],
        'total'           => $result['total'],
        'page'            => $page,
        'per_page'        => $per_page,
    ]);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT PENDING RISKS     *
 *                                                     *
 * Lists pending risks generated by self-assessments. *
 *****************************************************/
function api_v2_self_assessment_pending_risks()
{
    api_v2_check_permission('assessments');

    json_response(200, "SUCCESS", ['pending_risks' => get_self_assessment_pending_risks()]);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT PUSH RISK         *
 *                                                     *
 * Pushes a self-assessment-generated pending risk to *
 * a real risk. Requires both the assessments          *
 * permission (to act on the pending risk) and the     *
 * submit_risks permission (to create the risk itself).*
 *****************************************************/
function api_v2_self_assessment_push_risk($id = null)
{
    api_v2_check_permission('assessments');
    api_v2_check_permission('submit_risks');

    $body = api_v2_self_assessment_read_json_body();

    $result = promote_pending_risk_to_risk((int)($id ?? 0), [
        'owner'               => (int)($body['owner'] ?? 0),
        'submission_date'     => $body['submission_date'] ?? false,
        'notes'               => (string)($body['notes'] ?? ''),
        'assets_asset_groups' => (string)($body['assets_asset_groups'] ?? ''),
        'scoring'             => is_array($body['scoring'] ?? null) ? $body['scoring'] : [],
    ]);

    if (!$result['success']) {
        json_response($result['code'] ?? 400, $result['message'], null);
    }

    json_response(200, "SUCCESS", ['risk_id' => $result['risk_id']]);
}

/*****************************************************
 * FUNCTION: API V2 SELF-ASSESSMENT DELETE            *
 * PENDING RISK                                       *
 *                                                     *
 * Deletes a self-assessment-generated pending risk    *
 * without pushing it to a real risk.                  *
 *****************************************************/
function api_v2_self_assessment_delete_pending_risk($id = null)
{
    api_v2_check_permission('assessments');

    delete_pending_risk((int)($id ?? 0));

    json_response(200, "SUCCESS", ['deleted' => (int)($id ?? 0)]);
}

?>
