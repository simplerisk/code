<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * /assets/discovery-runs (asset management redesign, Task 11): start, list,
 * read and cancel background asset discovery runs, and report which probe
 * method a run would use (/assets/discovery-runs/capabilities). Every route needs the
 * `asset` permission plus asset_discovery (assets_endpoint_required_permission()).
 * The scan itself runs in the core_asset_discovery queue job.
 */

require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/services.php'));
require_once(realpath(__DIR__ . '/../../../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../../../includes/assets.php'));
require_once(realpath(__DIR__ . '/../../../includes/assets_discovery_rules.php'));
require_once(realpath(__DIR__ . '/../../../includes/assets_write_rules.php'));
require_once(realpath(__DIR__ . '/../../../includes/assets_discovery.php'));
require_once(realpath(__DIR__ . '/../../../includes/assets_discovery_probe.php'));

require_once(language_file());

/**
 * The base `asset` permission plus the one narrow permission the route needs.
 * Answers 403 and returns false when the caller lacks either.
 */
function assets_discovery_api_gate(string $endpoint_key): bool
{
    global $escaper, $lang;
    if (!check_permission('asset') || !has_permission(assets_endpoint_required_permission($endpoint_key))) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), null);
        return false;
    }
    return true;
}

/** 422/404/409/429 with a lang-keyed message and a machine-readable code. */
function assets_discovery_api_error(int $status, string $code, array $params = [], array $extra = []): void
{
    global $escaper;
    json_response($status, $escaper->escapeHtml(_lang_raw($code, $params)), array_merge(['error' => $code], $extra));
}

// POST /assets/discovery-runs  {range, resolve_names?, team_ids?}
function assets_discovery_runs_create_API()
{
    if (!assets_discovery_api_gate('discovery_create')) {
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    // Validation, then the config.php allowlist (the same
    // assets_discovery_target_error() the worker uses).
    $checked = assets_discovery_create_request_error(is_array($body) ? $body : []);
    if ($checked['error'] !== null) {
        assets_discovery_api_error($checked['error']['status'], $checked['error']['code'], $checked['error']['params'], $checked['error']['extra']);
        return;
    }
    $req = $checked['req'];
    if ($req === null) {
        assets_discovery_api_error(422, 'DiscoveryRunQueueFailed');
        return;
    }

    $team_error = assets_discovery_check_teams($req['team_ids']);
    if ($team_error !== null) {
        assets_discovery_api_error(422, $team_error);
        return;
    }

    $uid = (int)$_SESSION['uid'];
    $db = db_open();
    try {
        // A run the queue lost must not hold a slot.
        assets_discovery_heal_stuck_runs($db);
        $cap = assets_discovery_cap_error($db, $uid);
        if ($cap !== null) {
            assets_discovery_api_error(429, $cap['code'], ['max' => $cap['max']], ['max' => $cap['max']]);
            return;
        }

        // Spec D2: decided here from the requester's own permission and the
        // auto-verify setting -- never from the request body.
        $add_as_verified = asset_new_verified_for_current_user();

        $run_id = assets_discovery_create_run($db, $req, $uid, $add_as_verified);
        if (!$run_id) {
            write_debug_log("Asset discovery: queueing a run for user #{$uid} failed.", 'error');
            assets_discovery_api_error(500, 'DiscoveryRunQueueFailed');
            return;
        }
        write_debug_log("Asset discovery: user #{$uid} queued run #{$run_id} ({$req['count']} addresses).", 'info');

        global $escaper, $lang;
        $run = assets_discovery_get_run_with_user($db, $run_id);
        assets_discovery_write_audit('DiscoveryRunStartedLog', $run ?? ['id' => $run_id, 'range_text' => $req['range'], 'total_hosts' => $req['count']]);
        json_response(201, $escaper->escapeHtml($lang['DiscoveryRunQueued']), ['run' => assets_discovery_run_payload($run)]);
    } finally {
        db_close($db);
    }
}

// GET /assets/discovery-runs?page=&per_page=  -- recent runs, newest first
function assets_discovery_runs_list_API()
{
    if (!assets_discovery_api_gate('discovery_list')) {
        return;
    }
    $db = db_open();
    assets_discovery_heal_stuck_runs($db);
    $data = assets_discovery_list_runs(
        $db,
        (int)$_SESSION['uid'],
        (bool)is_admin(),
        (bool)team_separation_extra(),
        get_param('GET', 'page', 1),
        get_param('GET', 'per_page', 10)
    );
    db_close($db);
    json_response(200, "SUCCESS", $data);
}

/**
 * The run the caller may see, or null after answering 404 (a run outside the
 * caller's scope is indistinguishable from a missing one).
 *
 * @return array<string,mixed>|null
 */
function assets_discovery_api_visible_run(PDO $db, $id): ?array
{
    $run_id = (int)$id;
    $run = $run_id > 0 ? assets_discovery_get_run_with_user($db, $run_id) : null;
    if ($run === null || !assets_discovery_run_visible($run, (int)$_SESSION['uid'], (bool)is_admin(), (bool)team_separation_extra())) {
        db_close($db);
        assets_discovery_api_error(404, 'DiscoveryRunNotFound');
        return null;
    }
    return $run;
}

// GET /assets/discovery-runs/capabilities  -- the probe method a run would use
function assets_discovery_capabilities_API()
{
    if (!assets_discovery_api_gate('discovery_capabilities')) {
        return;
    }
    $db = db_open();
    try {
        $data = assets_discovery_capabilities_payload($db, (bool)is_admin());
    } finally {
        db_close($db);
    }
    json_response(200, "SUCCESS", $data);
}

// GET /assets/discovery-runs/{id}
function assets_discovery_run_get_API($id = null)
{
    if (!assets_discovery_api_gate('discovery_list')) {
        return;
    }
    $db = db_open();
    assets_discovery_heal_stuck_runs($db);
    $run = assets_discovery_api_visible_run($db, $id);
    if ($run === null) {
        return;
    }
    db_close($db);
    json_response(200, "SUCCESS", ['run' => assets_discovery_run_payload($run)]);
}

// DELETE /assets/discovery-runs/{id}  -- cancel a queued or running run
function assets_discovery_run_cancel_API($id = null)
{
    global $escaper, $lang;
    if (!assets_discovery_api_gate('discovery_cancel')) {
        return;
    }
    $db = db_open();
    $run = assets_discovery_api_visible_run($db, $id);
    if ($run === null) {
        return;
    }
    if (!assets_discovery_run_cancellable_by($run, (int)$_SESSION['uid'], (bool)is_admin())) {
        db_close($db);
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), null);
        return;
    }
    if (!assets_discovery_cancel_run($db, (int)$run['id'])) {
        $now = assets_discovery_get_run_with_user($db, (int)$run['id']);
        db_close($db);
        assets_discovery_api_error(409, 'DiscoveryRunAlreadyFinished', [], ['run' => $now ? assets_discovery_run_payload($now) : null]);
        return;
    }
    write_debug_log("Asset discovery: user #" . (int)$_SESSION['uid'] . " cancelled run #" . (int)$run['id'] . ".", 'info');
    assets_discovery_write_audit('DiscoveryRunCancelledLog', $run);
    $now = assets_discovery_get_run_with_user($db, (int)$run['id']);
    db_close($db);
    json_response(200, $escaper->escapeHtml($lang['DiscoveryRunCancelled']), ['run' => assets_discovery_run_payload($now)]);
}
