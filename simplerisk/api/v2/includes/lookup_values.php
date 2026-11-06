<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * API v2 handlers for the lookup value lists managed from Admin > Add and
 * Remove Values (SR-2475). Admin only: every handler's first statement is
 * api_v2_check_admin(), reads and usage counts included.
 */

require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/lookup_values.php'));
require_once(realpath(__DIR__ . '/api.php'));
// parse_non_post_body_into_post() lives here; index.php loads it first today, but a consumer declares its own require.
require_once(realpath(__DIR__ . '/../../../includes/api.php'));

/**
 * Sends an error and stops. The text comes from the language file.
 */
function api_v2_lookup_values_fail(int $status_code, string $lang_key, ?array $params = null)
{
    global $lang;
    $message = $params === null ? ($lang[$lang_key] ?? $lang_key) : _lang($lang_key, $params);
    api_v2_json_result($status_code, $message, null);
    exit;
}

/**
 * Resolves the list from the route, or answers 404 for one that is not managed.
 */
function api_v2_lookup_values_require_list($list): string
{
    if (!lookup_value_list_exists($list)) {
        api_v2_lookup_values_fail(404, 'LookupListNotFound');
    }
    return $list;
}

/**
 * Resolves the id from the route as an int (before any comparison against a
 * protected id), or answers 400.
 */
function api_v2_lookup_values_require_id($id): int
{
    $parsed = lookup_value_parse_id($id);
    if ($parsed === null) {
        api_v2_lookup_values_fail(400, 'YouNeedToSpecifyAnIdParameter');
    }
    return $parsed;
}

/**
 * Validates a request name with the page's existing validator and the list's limit.
 */
function api_v2_lookup_values_require_name(string $list, string $action): string
{
    // The validator also wants an id for 'update'; the route id is validated separately by
    // api_v2_lookup_values_require_id(), so a placeholder satisfies it here.
    $validation = validate_add_remove_values_input(
        ['action' => $action, 'name' => get_param("POST", "name", null), 'id' => '1'],
        lookup_value_name_limit($list)
    );

    if (!$validation['valid']) {
        $error = $validation['errors'][0];
        api_v2_lookup_values_fail(400, $error['key'], $error['params']);
    }
    return $validation['name'];
}

/**
 * GET /admin/lookup-values/lists
 */
function api_v2_lookup_values_lists()
{
    api_v2_check_admin();

    global $lang;
    $groups = [];
    foreach (lookup_value_group_order() as $group) {
        $groups[] = ['group' => $group, 'name' => $lang[$group] ?? $group];
    }

    api_v2_json_result(200, "SUCCESS", [
        'groups' => $groups,
        'lists' => lookup_value_lists_summary(in_array(get_param("GET", "include_values", ""), ['1', 1, 'true', true], true)),
    ]);
}

/**
 * GET /admin/lookup-values/lists/{list}/values
 */
function api_v2_lookup_values_list_values($list)
{
    api_v2_check_admin();

    $list = api_v2_lookup_values_require_list($list);

    api_v2_json_result(200, "SUCCESS", [
        'list' => $list,
        'length_limit' => lookup_value_name_limit($list),
        'values' => lookup_value_values_with_usage($list),
    ]);
}

/**
 * POST /admin/lookup-values/lists/{list}/values
 */
function api_v2_lookup_values_add($list)
{
    api_v2_check_admin();

    global $lang;
    $list = api_v2_lookup_values_require_list($list);
    $name = api_v2_lookup_values_require_name($list, 'add');

    if (lookup_value_name_taken($list, $name)) {
        api_v2_lookup_values_fail(409, 'LookupValueAlreadyExists');
    }

    $id = lookup_value_add($list, $name);
    if ($id === false) {
        api_v2_lookup_values_fail(500, 'FailedToAddNewItem');
    }

    api_v2_json_result(201, $lang['ANewItemWasAddedSuccessfully'], ['id' => $id, 'name' => $name]);
}

/**
 * PATCH /admin/lookup-values/lists/{list}/values/{id}
 */
function api_v2_lookup_values_rename($list, $id)
{
    api_v2_check_admin();

    // PATCH bodies are not parsed into $_POST by PHP, so form-encoded bodies need this; see
    // api_v2_data_integrity_issue_apply(). A JSON body is also read by get_param() directly.
    parse_non_post_body_into_post();

    global $lang;
    $list = api_v2_lookup_values_require_list($list);
    $id = api_v2_lookup_values_require_id($id);
    $name = api_v2_lookup_values_require_name($list, 'update');

    $current = lookup_value_get_value($list, $id);
    if ($current === null) {
        api_v2_lookup_values_fail(404, 'LookupValueNotFound');
    }

    $protection = lookup_value_rename_protection($list, $id);
    if ($protection !== null) {
        api_v2_lookup_values_fail(409, $protection);
    }

    if (lookup_value_name_taken($list, $name, $id)) {
        api_v2_lookup_values_fail(409, 'LookupValueAlreadyExists');
    }

    if (!lookup_value_rename($list, $id, $current['name'], $name)) {
        api_v2_lookup_values_fail(500, 'FailedToUpdateItem');
    }

    api_v2_json_result(200, $lang['AnItemWasUpdatedSuccessfully'], ['id' => $id, 'name' => $name]);
}

/**
 * DELETE /admin/lookup-values/lists/{list}/values/{id}
 * Optional body field "reassign_to": the id of another value of the same list
 * that takes over the records that use the deleted value.
 */
function api_v2_lookup_values_delete($list, $id)
{
    api_v2_check_admin();

    // DELETE bodies are not parsed into $_POST by PHP either.
    parse_non_post_body_into_post();

    global $lang;
    $list = api_v2_lookup_values_require_list($list);
    $id = api_v2_lookup_values_require_id($id);

    if (lookup_value_get_value($list, $id) === null) {
        api_v2_lookup_values_fail(404, 'LookupValueNotFound');
    }

    $protection = lookup_value_delete_protection($list, $id, lookup_value_protection_context());
    if ($protection !== null) {
        api_v2_lookup_values_fail(409, $protection);
    }

    $reassign_to = null;
    $requested = get_param("POST", "reassign_to", null);
    if ($requested !== null && $requested !== '' && $requested !== 0 && $requested !== '0') {
        $reassign_to = lookup_value_parse_id($requested);
        if ($reassign_to === null || $reassign_to === $id || lookup_value_get_value($list, $reassign_to) === null) {
            api_v2_lookup_values_fail(400, 'LookupReassignTargetInvalid');
        }
    }

    $moved = 0;
    if ($reassign_to !== null) {
        try {
            $moved = lookup_value_reassign($list, $id, $reassign_to);
        } catch (Throwable $e) {
            api_v2_lookup_values_fail(500, 'FailedToDeleteItem');
        }
    }

    // The records were moved above and the value is deleted here, so a failure at this point leaves
    // the records on the target and the value in place: nothing is lost and a retry is safe.
    try {
        $deleted = lookup_value_delete($list, $id);
    } catch (Throwable $e) {
        write_debug_log('Deleting a lookup value failed: ' . $e->getMessage(), 'error');
        $deleted = false;
    }
    if (!$deleted) {
        api_v2_lookup_values_fail(500, 'FailedToDeleteItem');
    }

    api_v2_json_result(200, $lang['AnItemWasDeletedSuccessfully'], ['id' => $id, 'reassigned' => $moved]);
}
