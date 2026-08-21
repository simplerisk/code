<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Declared directly rather than relied on transitively via api/v2/index.php's
// require order, so this file keeps working if that order ever changes.
// api_v2_check_permission() / api_v2_json_result() live in api.php; the whole
// applicability domain (resolve_applicability(), get_applicability_map(),
// set_applicability(), the assert_* validators and their constants) lives in
// includes/applicability.php.
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/applicability.php'));
// controls_table_filtered_control_ids() -- the controls table's OWN request
// pipeline, wrapped so an escalated "Select all N" resolves to exactly the rows
// the table was showing. It lives beside that endpoint rather than here because
// the bulk-delete endpoint escalates through it too (Task 54); two copies of it
// would be two resolvers that merely ought to agree. Required directly per
// CLAUDE.md's cross-file reachability rule; governance_controls.php does not
// require THIS file, so there is no cycle.
require_once(realpath(__DIR__ . '/governance_controls.php'));

/******************************************************************************
 * CONTROL APPLICABILITY API (spec §5, §6)
 *
 * Three endpoints:
 *
 *   GET  /governance/applicability/reasons   the configurable reason picklist
 *   GET  /governance/applicability           the stored decisions for a framework
 *   POST /governance/applicability           set (or clear) a bulk selection
 *
 * PERMISSIONS (spec D10): reads are gated on `governance` — the same gate the
 * SoA itself uses — and the write on `modify_frameworks`, because scoping a
 * control out of a framework is a framework-scoping decision. No new permission
 * ships in v1.
 *
 * WHY POST FOR THE WRITE, not PATCH or DELETE. One call sets a whole SELECTION,
 * and the same call can insert, update, or delete rows depending on `state` —
 * "applicable" is the default and is cleared by removing the row, not by storing
 * a value. There is no single addressable `/applicability/{id}` resource for a
 * bulk decision to PATCH, and bulk-set is the primary interaction rather than a
 * convenience (§5.6): nobody decides 1,535 SCF controls one row at a time.
 *
 * THE RESPONSE CARRIES DEVIATIONS ONLY. A control with no row is applicable, so
 * the read returns the exceptions, not the catalogue — `default_state` is echoed
 * back in the payload so a client cannot invent its own default for the
 * (overwhelmingly common) controls that are simply absent from `decisions`.
 ******************************************************************************/

/**
 * Upper bound on one bulk selection. The full SCF catalogue is 1,535 controls, so
 * this is roughly triple the largest legitimate request; it exists so an
 * arbitrarily long `control_ids` array cannot turn one request into a million
 * prepared-statement executions.
 */
const APPLICABILITY_MAX_SELECTION = 5000;

/****************************************************
 * FUNCTION: PARSE APPLICABILITY SET REQUEST        *
 ****************************************************
 * Normalizes and validates the SHAPE of a POST /governance/applicability body.
 *
 * Pure: no DB, no globals, no output. It answers "is this a well-formed request?"
 * only. Whether the deviation itself is well-formed (narrative present, reason
 * required for an exclusion, provider required for an inheritance) is the domain
 * layer's contract and is enforced by assert_applicability_requirements() inside
 * set_applicability() — so there is exactly ONE definition of a valid deviation,
 * not one here and a second one that ought to match.
 *
 * TWO WAYS TO NAME A SELECTION, and exactly one per request:
 *
 *   control_ids: [..]                    the rows the client is holding
 *   all_filtered: true, filters: {..}    every row the CURRENT filter matches
 *
 * The second exists because the control table PAGES (3ca89461c1). The client
 * only ever holds one page, so "Select all 1,535" cannot be expressed as a list
 * of ids it never fetched — and a bulk bar that says 1,535 while writing 25
 * would be lying about what it is about to do. `filters` is the same
 * $_GET-shaped map GET /governance/controls/table takes, resolved through that
 * endpoint's OWN parser (controls_table_filtered_control_ids()), so the set
 * written is by construction the set the table was showing.
 *
 * Sending both is REFUSED rather than resolved by precedence: they name two
 * different populations, and silently discarding one of them is exactly the
 * surprise this whole shape exists to prevent.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ABSENT IS NOT EMPTY, and this parser is where the distinction is born.
 * ─────────────────────────────────────────────────────────────────────────────
 * `reason_ids`, `narrative` and `provider` come back as NULL when the body did
 * not mention them, and as `[]` / `''` when it explicitly sent an empty value.
 * The domain layer PRESERVES the stored value for the first and CLEARS it for
 * the second, so the two must stay distinguishable all the way down.
 *
 * The test is array_key_exists() (plus an explicit null check), NEVER isset() or
 * empty(): `isset($body['reason_ids'])` is FALSE for an explicit null, and
 * `empty($body['reason_ids'])` is TRUE for the explicit empty array that means
 * "remove every reason". A truthiness test here would silently turn "remove
 * every reason" into "say nothing", and a user who unticked their last reason
 * would watch it come back.
 *
 * An explicit JSON null is read as ABSENT rather than as a clear, because that
 * is what a client omitting an optional field usually serialises, and the
 * conservative reading of an ambiguous input is the one that keeps data.
 *
 * @throws InvalidArgumentException with a message safe to return to the caller.
 */
function parse_applicability_set_request(array $body): array {

    $framework = (int)($body['framework'] ?? 0);

    if ($framework <= 0) {
        throw new InvalidArgumentException('A framework id is required.');
    }

    // Only a literal boolean true (or its JSON-ish string/int spellings) escalates.
    $all_filtered = filter_var($body['all_filtered'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $raw_ids = $body['control_ids'] ?? null;
    $has_ids = is_array($raw_ids) && !empty($raw_ids);

    if ($all_filtered && $has_ids) {
        throw new InvalidArgumentException(
            'Name either control_ids or all_filtered, not both.'
        );
    }

    $control_ids = [];
    $filters     = [];

    if ($all_filtered) {

        // A $_GET-shaped map of CSV strings. Every value is re-parsed and
        // allowlisted by parse_controls_table_request() before it reaches SQL,
        // so nothing here is trusted beyond "it is a map".
        $filters = is_array($body['filters'] ?? null) ? $body['filters'] : [];

    } else {

        if (!is_array($raw_ids)) {
            throw new InvalidArgumentException('control_ids must be an array of control ids.');
        }

        $control_ids = array_values(array_unique(array_filter(
            array_map('intval', $raw_ids),
            static fn($id) => $id > 0
        )));

        // An empty selection is refused rather than answered with a cheerful
        // "0 updated": it means the UI let the user act on nothing, and a silent
        // success would hide that instead of surfacing it.
        if (empty($control_ids)) {
            throw new InvalidArgumentException('At least one control id is required.');
        }

        if (count($control_ids) > APPLICABILITY_MAX_SELECTION) {
            throw new InvalidArgumentException(
                'A selection may name at most ' . APPLICABILITY_MAX_SELECTION . ' controls.'
            );
        }
    }

    $state = (string)($body['state'] ?? '');

    // The three states a CLIENT may request. Only two of them are stored —
    // 'applicable' is the default, and asking for it clears the rows. Read from
    // the domain layer rather than composed here, so this parser and the
    // controls table's applicability facet offer the identical vocabulary
    // (applicability_requestable_states(), includes/applicability.php).
    $requestable = applicability_requestable_states();

    if (!in_array($state, $requestable, true)) {
        // The submitted value is deliberately NOT echoed back: this message
        // becomes the API's status_message, and the page shows an API message in
        // a toast, which renders HTML by default (see CLAUDE.md). Naming the
        // allowed values is safe and more useful than repeating the typo.
        throw new InvalidArgumentException(
            'Unknown applicability state. Expected one of: ' . implode(', ', $requestable) . '.'
        );
    }

    // MULTI-SELECT. A scalar is refused rather than wrapped into a one-element
    // list: `reason_ids: 4` is a client that has not been updated, and quietly
    // accepting it would leave that client believing it can only ever send one.
    $reason_ids = null;

    if (array_key_exists('reason_ids', $body) && $body['reason_ids'] !== null) {

        if (!is_array($body['reason_ids'])) {
            throw new InvalidArgumentException('reason_ids must be an array of reason ids.');
        }

        // [] SURVIVES AS []. It is an instruction to remove every reason, and
        // collapsing it to null here would make that instruction unsendable.
        $reason_ids = applicability_normalize_reason_ids($body['reason_ids']);
    }

    return [
        'framework'    => $framework,
        'control_ids'  => $control_ids,
        'all_filtered' => $all_filtered,
        'filters'      => $filters,
        'state'        => $state,
        // NULL = the body said nothing = preserve. See the note above.
        'reason_ids'   => $reason_ids,
        'narrative'    => applicability_optional_text($body, 'narrative'),
        'provider'     => applicability_optional_text($body, 'provider'),
    ];
}

/****************************************************
 * FUNCTION: APPLICABILITY OPTIONAL TEXT            *
 ****************************************************
 * One optional free-text field, read so that ABSENT and EMPTY stay different.
 *
 *   key missing, or present and null  ->  null  ("say nothing", preserve)
 *   key present with any string       ->  that string ('' clears)
 *
 * `?? ''` is what this replaces, and it is the bug: it turns "say nothing" into
 * "clear it", which on a justification inside a controlled document is silent
 * data loss rather than a cosmetic default.
 *
 * A non-string scalar is cast rather than refused — a form-encoded client sends
 * everything as a string anyway, and a number in a narrative is a value, not an
 * attack.
 *
 * Pure.
 */
function applicability_optional_text(array $body, string $key): ?string {

    if (!array_key_exists($key, $body) || $body[$key] === null) {
        return null;
    }

    if (is_array($body[$key])) {
        throw new InvalidArgumentException("{$key} must be a string.");
    }

    return (string)$body[$key];
}

/****************************************************
 * FUNCTION: API V2 APPLICABILITY READ JSON BODY    *
 ****************************************************
 * Reads a JSON request body, falling back to $_POST for form-encoded clients.
 * Mirrors api_v2_self_assessment_read_json_body().
 */
function api_v2_applicability_read_json_body(): array {

    $body = json_decode(file_get_contents('php://input'), true);

    if (is_array($body)) {
        return $body;
    }

    return $_POST ?: [];
}

/****************************************************
 * FUNCTION: API V2 GOVERNANCE APPLICABILITY GET    *
 ****************************************************
 * GET /governance/applicability?framework=<id>[&control_ids=1,2,3]
 *
 * Returns the stored decisions for one framework. Controls that carry no
 * deviation are ABSENT from `decisions` — that absence is the answer, which is
 * why `default_state` travels with the payload.
 *
 * `control_ids` scopes the read to the page of controls currently on screen;
 * omitting it returns every decision in the framework, which is cheap because
 * only deviations are stored.
 */
function api_v2_governance_applicability_get() {

    api_v2_check_permission("governance");

    $framework = (int)($_GET['framework'] ?? 0);

    if ($framework <= 0) {
        api_v2_json_result(400, "BAD REQUEST: A framework id is required.", ['error' => 'framework_required']);
        return;
    }

    $control_ids = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string)($_GET['control_ids'] ?? ''))),
        static fn($id) => $id > 0
    )));

    if (count($control_ids) > APPLICABILITY_MAX_SELECTION) {
        api_v2_json_result(
            400,
            "BAD REQUEST: A selection may name at most " . APPLICABILITY_MAX_SELECTION . " controls.",
            ['error' => 'selection_too_large']
        );
        return;
    }

    $decisions = empty($control_ids)
        ? get_framework_applicability_map($framework)
        : get_applicability_map($framework, $control_ids);

    api_v2_json_result(200, "SUCCESS", [
        'framework'     => $framework,
        // Echoed so the client resolves absent controls the same way the server
        // does, instead of hardcoding a second copy of the default.
        'default_state' => APPLICABILITY_DEFAULT_STATE,
        // A LIST, not a control_id-keyed object: json_encode() would turn the
        // integer keys into string properties and the shape would depend on
        // whether the ids happened to be sequential from zero.
        'decisions'     => array_values($decisions),
    ]);
}

/****************************************************
 * FUNCTION: API V2 GOVERNANCE APPLICABILITY SET    *
 ****************************************************
 * POST /governance/applicability
 *
 * Sets the applicability of a selection of controls within one framework — or,
 * for state 'applicable' with nothing to record, clears it back to the framework
 * default.
 *
 * A field the body omits PRESERVES what is stored; an explicitly empty one
 * clears it. So "put these controls back in scope" is
 * `{state: 'applicable', narrative: '', reason_ids: []}` — a bare
 * `{state: 'applicable'}` says nothing about the justification and therefore
 * leaves a stored one alone.
 *
 * Every InvalidArgumentException the domain layer raises becomes a 400 with a
 * machine-readable `error` token, so the page can localize the message itself.
 * Without this the `narrative` column (TEXT NOT NULL, no default) would answer an
 * unjustified exclusion with a raw PDO error surfaced through the API.
 */
function api_v2_governance_applicability_set() {

    api_v2_check_permission("modify_frameworks");

    $body = api_v2_applicability_read_json_body();

    try {
        $req = parse_applicability_set_request($body);

        $control_ids = $req['control_ids'];

        // The escalated case: the client named a FILTER, not a list, because the
        // table pages and it only ever held one page. Resolving it here — through
        // the controls table's own pipeline — is what lets the bulk bar's "Select
        // all N" write N rows instead of the 25 the client could enumerate.
        if ($req['all_filtered']) {

            $control_ids = controls_table_filtered_control_ids($req['framework'], $req['filters']);

            // Same refusal an empty control_ids gets, for the same reason: the UI
            // let the user act on nothing, and a cheerful "0 updated" would hide
            // that rather than surface it.
            if (empty($control_ids)) {
                throw new InvalidArgumentException('The current filters match no controls.');
            }

            if (count($control_ids) > APPLICABILITY_MAX_SELECTION) {
                throw new InvalidArgumentException(
                    'A selection may name at most ' . APPLICABILITY_MAX_SELECTION . ' controls.'
                );
            }
        }

        $updated = set_applicability(
            $req['framework'],
            $control_ids,
            $req['state'],
            $req['reason_ids'],
            $req['narrative'],
            $req['provider'],
            (int)($_SESSION['uid'] ?? 0)
        );

    } catch (InvalidArgumentException $e) {

        // The message is composed from validated ids and fixed strings, never
        // from raw request text, so it is safe to hand back to the caller.
        api_v2_json_result(400, "BAD REQUEST: " . $e->getMessage(), ['error' => 'invalid_request']);
        return;
    }

    api_v2_json_result(200, "SUCCESS", [
        'framework' => $req['framework'],
        'state'     => $req['state'],
        // How many controls the decision was applied to — the number the page
        // puts in its toast. For an escalated selection this is the RESOLVED
        // count, not the size of anything the client sent, which is the only way
        // the toast can honestly answer "did it do what the bar said?".
        'selected'  => count($control_ids),
        'updated'   => $updated,
    ]);
}

/************************************************************
 * FUNCTION: API V2 GOVERNANCE APPLICABILITY REASONS        *
 ************************************************************
 * GET /governance/applicability/reasons[?applies_to=not_applicable|inherited]
 *
 * The configurable reason picklist. `applies_to` is what keeps "Performed by a
 * third party" out of the exclusion list — a cloud-hosted organisation does not
 * *exclude* the datacenter perimeter controls its provider performs, and offering
 * that reason as an exclusion would make the SoA factually wrong (§5.3).
 *
 * An unrecognised `applies_to` returns the full list rather than an error: the
 * parameter narrows a picklist, and a typo that emptied it would look to the user
 * like a broken form rather than a bad request.
 */
function api_v2_governance_applicability_reasons() {

    api_v2_check_permission("governance");

    $applies_to = trim((string)($_GET['applies_to'] ?? ''));

    // Checked against the three states a client may NAME, not against the two that
    // get a row: the taxonomy's third side ('applicable') carries the inclusion
    // reasons, and narrowing by the storable-states list would fall through to the
    // unfiltered branch and offer every exclusion reason as a reason to include.
    if (!in_array($applies_to, applicability_requestable_states(), true)) {
        $applies_to = null;
    }

    api_v2_json_result(200, "SUCCESS", [
        'reasons' => get_applicability_reasons($applies_to),
    ]);
}

?>
