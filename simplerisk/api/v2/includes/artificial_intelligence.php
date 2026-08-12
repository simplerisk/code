<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/artificial_intelligence.php'));

require_once(language_file());

/***************************************
 * FUNCTION: API V2 AI RECOMMENDATIONS *
 * *************************************/
function api_v2_ai_recommendations()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // If an AI provider is configured (a key, or a keyless-local provider — SR-1931)
    if (ai_provider_is_configured())
    {
        // Generate the AI message context
        $context_content = generate_ai_business_context();

        // Ask AI for recommendations
        $advice = ai_get_recommendations($context_content);

        // If we received advice
        if ($advice != false)
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";
            $data = [$advice];
        }
        // If we did not receive advice
        else
        {
            // Set the status
            $status_code = 503;
            $status_message = "There was an issue retrieving a result from the AI provider.  Check the debug logs for more information.";
            $data = null;
        }
    }
    // If no AI provider is configured
    else
    {
        // Set the status
        $status_code = 503;
        $status_message = "An AI provider needs to be configured to use this functionality.";
        $data = null;
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*****************************************
 * FUNCTION: API V2 AI CAPABILITIES GET  *
 * Returns the AI capabilities catalog   *
 * projection consumed by the catalog UI.*
 *****************************************/
function api_v2_ai_capabilities_get()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Return the resolved capability catalog
    api_v2_json_result(200, "SUCCESS", ai_capabilities_for_display());
}

/*******************************************
 * FUNCTION: API V2 AI CAPABILITY PATCH    *
 * Toggles a single AI capability on/off.  *
 *******************************************/
function api_v2_ai_capability_patch($id)
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Look up the capability in the registry
    $registry = ai_capability_registry();
    if (!isset($registry[$id])) {
        api_v2_json_result(404, "NOT FOUND: Unknown AI capability.", null);
        return;
    }
    $row = $registry[$id];

    // Extra-tier capabilities require the AI Extra to be active
    if (($row['tier'] ?? 'core') === 'extra' && !artificial_intelligence_extra()) {
        api_v2_json_result(409, "CONFLICT: This capability requires the AI Extra to be active.", null);
        return;
    }

    // Always-on capabilities cannot be toggled
    if (!empty($row['always_on'])) {
        api_v2_json_result(409, "CONFLICT: This capability cannot be disabled.", null);
        return;
    }

    // Parse the JSON request body
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !array_key_exists('enabled', $body)) {
        api_v2_json_result(400, "BAD REQUEST: 'enabled' boolean is required.", null);
        return;
    }
    $enabled = filter_var($body['enabled'], FILTER_VALIDATE_BOOLEAN);

    // Persist the toggle
    update_setting($row['config_key'], $enabled ? '1' : '0');

    api_v2_json_result(200, "SUCCESS", ['id' => $id, 'enabled' => $enabled]);
}

/*********************************************************
 * FUNCTION: API V2 AI PROVIDER URL CHECK                *
 * Validates a candidate provider URL against the SSRF   *
 * allow-list (is_safe_ai_provider_url) so the provider  *
 * config UI can warn inline before Save. Read-only: it  *
 * parses + allow-list-checks only, never makes an       *
 * outbound request to the URL.                          *
 *********************************************************/
function api_v2_ai_provider_url_check()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Read the candidate URL from the query string
    $url = isset($_GET['url']) ? trim((string) $_GET['url']) : '';
    if ($url === '') {
        api_v2_json_result(400, "BAD REQUEST: 'url' query parameter is required.", null);
        return;
    }

    // Report the parsed host/scheme alongside the allow-list verdict so the UI
    // can name the offending host in its warning.
    api_v2_json_result(200, "SUCCESS", [
        'url'     => $url,
        'host'    => strtolower((string) parse_url($url, PHP_URL_HOST)),
        'scheme'  => strtolower((string) parse_url($url, PHP_URL_SCHEME)),
        'allowed' => is_safe_ai_provider_url($url),
    ]);
}

/*********************************************************
 * FUNCTION: API V2 AI CONTEXT PATCH                     *
 * Auto-save endpoint for the Context Questions tab.     *
 * Persists the supplied answers, stamps the save time,  *
 * and returns the refreshed "Last saved" label.         *
 *********************************************************/
function api_v2_ai_context_patch()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Parse the JSON request body
    $body    = json_decode(file_get_contents('php://input'), true);
    $answers = (is_array($body) && isset($body['answers']) && is_array($body['answers'])) ? $body['answers'] : null;
    if ($answers === null) {
        api_v2_json_result(400, "BAD REQUEST: 'answers' object is required.", null);
        return;
    }

    // Persist (unknown keys are ignored inside save_ai_context_answers)
    $ts = save_ai_context_answers($answers);

    api_v2_json_result(200, "SUCCESS", [
        'last_saved'       => $ts,
        'last_saved_label' => ai_context_last_saved_label($ts),
    ]);
}

/*********************************************************
 * FUNCTION: API V2 AI CONTEXT QUESTIONS GET              *
 * Returns the structured Context Profile (Spec 1        *
 * Primitive A): the three fact classes — asked (schema-  *
 * declared questionnaire answers), derived (read live,   *
 * e.g. frameworks_in_use), and authoritative (read       *
 * through from existing settings, e.g. risk appetite).   *
 *********************************************************/
function api_v2_ai_context_questions_get()
{
    // Check that this is an admin user
    api_v2_check_admin();

    api_v2_json_result(200, "SUCCESS", resolve_ai_context_profile());
}

/*********************************************************
 * FUNCTION: API V2 AI CONTEXT GET                       *
 * Returns the normalized GRC context bundle for one     *
 * entity. Authorization is the 4-layer model:           *
 * authentication is already enforced by the router;     *
 * here we enforce L2 (focal-type domain permission ->   *
 * 403). L3/L4 scoping of neighbors happens inside        *
 * ai_get_context() using the caller's session.          *
 *                                                         *
 * Query params: depth (1-2, clamped inside               *
 * ai_get_context()), expand (raise the neighbor cap for  *
 * one node type), limit (the raised cap, clamped inside  *
 * ai_context_apply_clustering()). Malformed values        *
 * degrade rather than error: a non-numeric depth floors  *
 * to 1, an unrecognized expand type is simply never       *
 * matched during clustering.                              *
 *********************************************************/
function api_v2_ai_context_get($type = null, $id = null)
{
    require_once(realpath(__DIR__ . '/../../../includes/ai_context_graph.php'));

    $type  = (string)$type;
    $rawId = (string)$id;

    $types = ai_context_node_types();
    if (!isset($types[$type])) {
        api_v2_json_result(404, "NOT FOUND: Unknown context entity type.", null);
        return;
    }

    // vulnerability ids are composite (<platform>_<digits>) because each
    // vulnmgmt platform table has its own auto-increment id. Every other
    // type takes a plain integer.
    $platform = null;
    if ($type === 'vulnerability') {
        $parsed = ai_context_parse_node_id('vulnerability_id_' . $rawId);
        if ($parsed === null) {
            api_v2_json_result(400, "BAD REQUEST: A vulnerability id must be of the form <platform>_<id>.", null);
            return;
        }
        $numericId = (int)$parsed['id'];
        $platform  = $parsed['platform'];
    } else {
        $numericId = (int)$rawId;
        // context_profile is a SINGLETON focal (org-level config, no backing
        // record) — id 0 is valid for it. Every other type requires a
        // positive record id.
        if ($type !== 'context_profile' && $numericId <= 0) {
            api_v2_json_result(400, "BAD REQUEST: A positive integer id is required.", null);
            return;
        }
    }

    // L2: focal-type domain permission (403 if the caller can't see this
    // domain). context_profile is gated by the 'any' sentinel instead of a
    // single named permission: any caller holding at least one graph domain
    // permission (or an admin) may read it.
    $perm = $types[$type]['permission'];
    if ($perm === 'any') {
        if (!ai_context_caller_can_read_any_domain()) {
            api_v2_json_result(403, "FORBIDDEN: The user does not have the required permission to perform this action.", null);
            return;
        }
    } else {
        api_v2_check_permission($perm);
    }

    // Depth is clamped inside ai_get_context(); a non-numeric value casts to
    // 0 and then floors to 1 rather than erroring, so a malformed query
    // string still returns data.
    $depth = isset($_GET['depth']) ? (int)$_GET['depth'] : 1;

    // expand/limit raise the per-type neighbor cap for ONE type. An unknown
    // type is simply never matched during clustering — not an error, so a
    // stale client cannot break the page.
    $expand = isset($_GET['expand']) ? (string)$_GET['expand'] : null;
    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']     : null;

    // L3/L4 scoping of neighbors is applied inside ai_get_context() from the
    // live session; pass the current wall-clock for provenance.
    $bundle = ai_get_context($type, $numericId, $depth, [
        'now'      => time(),
        'platform' => $platform,
        'expand'   => $expand,
        'limit'    => $limit,
    ]);
    if ($bundle === null) {
        api_v2_json_result(404, "NOT FOUND: No such entity.", null);
        return;
    }
    api_v2_json_result(200, "SUCCESS", $bundle);
}

/*********************************************************
 * FUNCTION: API V2 AI PROPOSALS GET                      *
 * Lists pending AI proposals for one target entity.      *
 * Authentication is enforced by the router; results are  *
 * filtered to only the proposals the caller is authorized*
 * to review via ai_proposal_can_review().                *
 *********************************************************/
function api_v2_ai_proposals_get()
{
    require_once(realpath(__DIR__ . '/../../../includes/ai_proposals.php'));
    require_once(realpath(__DIR__ . '/../../../includes/ai_proposal_capabilities.php'));
    register_ai_proposal_capabilities();
    $target_type = (string)($_GET['target_type'] ?? '');
    $target_id   = (int)($_GET['target_id'] ?? 0);
    if ($target_type === '' || $target_id <= 0) {
        api_v2_json_result(400, "BAD REQUEST: target_type and a positive target_id are required.", null);
        return;
    }
    $proposals = get_ai_proposals($target_type, $target_id, 'pending');
    // Only expose proposals the caller is authorized to review.
    $visible = array_values(array_filter($proposals, 'ai_proposal_can_review'));
    api_v2_json_result(200, "SUCCESS", ['proposals' => $visible]);
}

/*********************************************************
 * FUNCTION: API V2 AI PROPOSAL PATCH                     *
 * Approves or rejects a single AI proposal. Authentication*
 * is enforced by the router; the per-proposal permission  *
 * is enforced inside approve/reject via                   *
 * ai_proposal_can_review() (-> 403).                       *
 *********************************************************/
function api_v2_ai_proposal_patch($id = null)
{
    require_once(realpath(__DIR__ . '/../../../includes/ai_proposals.php'));
    require_once(realpath(__DIR__ . '/../../../includes/ai_proposal_capabilities.php'));
    register_ai_proposal_capabilities();
    $id = (int)$id;
    if ($id <= 0) {
        api_v2_json_result(400, "BAD REQUEST: a positive proposal id is required.", null);
        return;
    }
    $body = json_decode(file_get_contents('php://input'), true);
    $decision = is_array($body) ? ($body['decision'] ?? '') : '';
    if ($decision !== 'approve' && $decision !== 'reject') {
        api_v2_json_result(400, "BAD REQUEST: 'decision' must be 'approve' or 'reject'.", null);
        return;
    }
    $reviewer = (int)($_SESSION['uid'] ?? 0);
    $res = $decision === 'approve'
        ? approve_ai_proposal($id, $reviewer)
        : reject_ai_proposal($id, $reviewer);
    if (!$res['ok']) {
        $code = match ($res['error']) {
            'not_found'  => 404,
            'forbidden'  => 403,
            'not_pending' => 409,
            default      => 422,
        };
        api_v2_json_result($code, "ERROR: " . $res['error'], null);
        return;
    }
    api_v2_json_result(200, "SUCCESS", ['id' => $id, 'decision' => $decision]);
}

/*********************************************************
 * FUNCTION: API V2 AI CONTEXT SEARCH GET                *
 * Free-text entity lookup backing the Connectivity      *
 * Visualizer's focal picker. Authentication is enforced *
 * by the router; per-type domain permission (L2/L3) and *
 * record visibility (L4) are enforced inside            *
 * ai_context_search_entities() from the live session.   *
 *********************************************************/
function api_v2_ai_context_search_get()
{
    require_once(realpath(__DIR__ . '/../../../includes/ai_context_graph.php'));

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    if (mb_strlen($q) < 2) {
        api_v2_json_result(400, "BAD REQUEST: A search query of at least 2 characters is required.", null);
        return;
    }

    // Entry gate: a caller holding no graph domain permission at all gets a
    // 403, not an empty 200 — an empty 200 would still confirm the endpoint
    // exists and is reachable.
    if (!ai_context_caller_can_read_any_domain()) {
        api_v2_json_result(403, "FORBIDDEN: The user does not have the required permission to perform this action.", null);
        return;
    }

    $types = [];
    if (!empty($_GET['types'])) {
        $types = array_values(array_filter(array_map('trim', explode(',', (string)$_GET['types']))));
    }
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;

    $results = ai_context_search_entities($q, $types, $limit);

    api_v2_json_result(200, "SUCCESS", ['results' => $results]);
}

/*********************************************************
 * FUNCTION: API V2 AI CONTEXT ENTITY COUNTS GET          *
 * Level-1 tile counts for the Connectivity Explorer      *
 * canvas launchpad's empty state. Authentication is      *
 * enforced by the router; the entry gate (at least one   *
 * graph domain permission) is checked here, and the      *
 * per-type domain gate (L2/L3) plus the L4 record-level   *
 * visibility count are both enforced inside               *
 * ai_context_browse_counts() from the live session.       *
 *********************************************************/
function api_v2_ai_context_entity_counts_get()
{
    require_once(realpath(__DIR__ . '/../../../includes/ai_context_graph.php'));

    // Entry gate: a caller holding no graph domain permission at all gets a
    // 403, not an empty 200 -- matching /ai/context/search's entry gate.
    if (!ai_context_caller_can_read_any_domain()) {
        api_v2_json_result(403, "FORBIDDEN: The user does not have the required permission to perform this action.", null);
        return;
    }

    api_v2_json_result(200, "SUCCESS", ai_context_browse_counts());
}

/*********************************************************
 * FUNCTION: API V2 AI CONTEXT ENTITIES GET               *
 * Level-2 browsable entity list for the Connectivity     *
 * Explorer canvas launchpad: one page of a single         *
 * browsable type, optionally filtered by substring.       *
 * Authentication is enforced by the router; the per-type  *
 * domain permission (L2/L3) is enforced here (403, not a  *
 * 200 with an empty list, which would confirm the type     *
 * exists); L4 record-level visibility is enforced inside   *
 * ai_context_browse_entities() from the live session,      *
 * AFTER the substring filter is applied -- never before,    *
 * or the filter box would become a visibility oracle.       *
 *********************************************************/
function api_v2_ai_context_entities_get()
{
    require_once(realpath(__DIR__ . '/../../../includes/ai_context_graph.php'));

    $type = isset($_GET['type']) ? (string)$_GET['type'] : '';
    $types = ai_context_browsable_types();
    if ($type === '' || !isset($types[$type])) {
        // 400, not 404: the browsable type list is public knowledge from
        // GET /ai/context/entity-counts, so refusing an unknown/non-browsable
        // type here discloses nothing that endpoint doesn't already.
        api_v2_json_result(400, "BAD REQUEST: 'type' must be one of the browsable entity types.", null);
        return;
    }

    // L2/L3: the caller must hold this type's domain permission, or the
    // request is refused outright (403, exits) -- never a 200 with an empty
    // list, which would still confirm the type exists.
    api_v2_check_permission(ai_context_type_permission($type));

    $filter = isset($_GET['filter']) ? (string)$_GET['filter'] : '';
    // Pagination is by opaque continuation cursor, not by a caller-computed
    // offset: an integer offset that the caller could difference across pages
    // would itself disclose how many records L4 hid between two visible ones.
    // Anything unparseable degrades to "first page" inside
    // ai_context_browse_decode_cursor(); the value is never trusted for
    // anything but where to resume.
    $cursor = isset($_GET['cursor']) && is_string($_GET['cursor']) ? $_GET['cursor'] : null;
    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 50;

    $page = ai_context_browse_entities($type, $filter, $cursor, $limit);
    api_v2_json_result(200, "SUCCESS", $page);
}

?>
