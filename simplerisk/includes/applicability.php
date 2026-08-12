<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// db_open() / db_close() / write_log() / write_debug_log() / get_name_by_value()
// and $escaper all live here. Required directly rather than relied on
// transitively, so this file keeps working from any entry point that includes it.
require_once(realpath(__DIR__ . '/functions.php'));

/******************************************************************************
 * CONTROL APPLICABILITY — the domain layer behind the Statement of
 * Applicability (spec §5.2–§5.4a).
 *
 * ONE RULE GOVERNS THIS ENTIRE FILE:
 *
 *   APPLICABLE IS THE DEFAULT, AND THE DEFAULT IS NEVER STORED.
 *
 * A row in `framework_control_applicability` exists only when someone has
 * something to say beyond the default. The absence of a row is not "unknown" or
 * "not yet decided" — it IS the answer, and it means applicable, justified by the
 * framework's own inclusion statement. Three consequences follow, and every
 * function below is shaped by them:
 *
 *   1. resolve_applicability(null) is 'applicable', not '' and not null.
 *   2. "Reset this control to the framework default" is a DELETE of its row,
 *      never an UPDATE to some empty value.
 *   3. Nothing is ever backfilled. A framework with 1,535 mapped controls, no
 *      exclusions and no per-control inclusion notes holds ZERO rows here.
 *
 * Storing the default anyway would materialise 1,535 rows per framework, demote
 * exclusion from an explicit audited act to an edit, and — because those rows
 * would then be the source of truth — hand every routine SCF rebuild something
 * to destroy.
 *
 * WHAT CHANGED IN TASK 4 (spec §4), and what deliberately did not. 'applicable'
 * is now a STORABLE state, because an applicable control may carry its own
 * justification — prose, one or more inclusion reasons from the taxonomy, or
 * both — instead of printing the framework's boilerplate 90 times over. The
 * storage contract:
 *
 *   no row                                 applicable, framework-default justification
 *   state = 'applicable'                   applicable, with THIS narrative and/or reason(s)
 *   state = 'not_applicable'/'inherited'   excluded / inherited, as before
 *   DELETE the row                         reset to the framework default
 *
 * Both original invariants survive: absence of a row still means applicable, and
 * DELETE still resets. Setting a control applicable with NOTHING to say still
 * deletes its row — an applicable row with no narrative and no reason would be a
 * second, emptier way of spelling the absence that already means applicable.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ABSENT IS NOT EMPTY. Read this before touching the writer.
 * ─────────────────────────────────────────────────────────────────────────────
 * This branch has fixed NINE separate bugs where a field the caller never
 * mentioned was treated as an instruction to clear it, and the settled
 * convention across all of them is:
 *
 *   an explicit '' (or an explicit empty array) CLEARS
 *   an absent / null field PRESERVES what is stored
 *
 * set_applicability() therefore takes `?array $reason_ids`, `?string $narrative`
 * and `?string $provider`, where NULL means "I am saying nothing about this" and
 * `[]` / `''` mean "remove what is there". `isset([])` is TRUE, so an empty array
 * must never be allowed to read as "no instruction" anywhere between the request
 * parser and the SQL. The rule is enforced by applicability_effective_fields(),
 * which is pure and directly tested.
 *
 * PRESERVATION IS PER-STATE. A re-decision preserves only from a stored row in
 * the SAME state: "No such technology" is not an inclusion justification, and an
 * exclusion reason is not even offered for the applicable state. That is the same
 * anti-drag rule the writer has always applied to `provider`.
 *
 * These are CORE tables (created unconditionally by
 * migrate_control_applicability_schema() in includes/upgrade.php), so no
 * table_exists() / is_extra_installed() guard is required to query them.
 *
 * AUTHORIZATION IS THE CALLER'S JOB. Like the rest of includes/governance.php,
 * nothing here checks a permission; the API layer gates writes on
 * `modify_frameworks` and reads on `governance` before calling in.
 ******************************************************************************/

/**
 * The state a control is in when no row exists for it. Still the name of the
 * absence — a control with no row is applicable — but since Task 4 it is also a
 * value a row may carry, for an applicable control that has its own
 * justification to record.
 */
const APPLICABILITY_DEFAULT_STATE = 'applicable';

/**
 * The two states that DEVIATE from the default. What makes them different from
 * 'applicable' is not that they are stored (all three can be) but that they
 * carry obligations: a deviation must be justified in prose, an exclusion must
 * name a reason, an inheritance must name a provider.
 */
const APPLICABILITY_DEVIATION_STATES = ['not_applicable', 'inherited'];

/**
 * Every state a row may carry. Mirrors the `state` ENUM exactly; if the schema
 * ever gains a fourth, this list and the ENUM change together.
 *
 * 'applicable' leads the list because it is the default, and because
 * applicability_requestable_states() hands this straight to the API's state
 * vocabulary, where the default belongs first.
 */
const APPLICABILITY_STORABLE_STATES = ['applicable', 'not_applicable', 'inherited'];

/**
 * `narrative` is TEXT and `provider` is VARCHAR(200). MySQL measures TEXT in
 * BYTES, so the narrative guard uses strlen(), not mb_strlen() — a narrative of
 * 40,000 multibyte characters is well under any character count and still too
 * long for the column. Without the guard, MySQL's strict mode answers with a raw
 * PDO exception that the API would surface verbatim.
 */
const APPLICABILITY_NARRATIVE_MAX_BYTES = 65535;
const APPLICABILITY_PROVIDER_MAX_BYTES  = 200;

/****************************************************
 * FUNCTION: APPLICABILITY REQUESTABLE STATES       *
 ****************************************************
 * The three states a CLIENT may name — the two deviations plus the default.
 * Identical to APPLICABILITY_STORABLE_STATES since Task 4 made 'applicable'
 * storable, and kept as its own function because the two answer different
 * questions: this one is a client vocabulary, that one mirrors the ENUM. Asking
 * for 'applicable' with nothing to record still CLEARS the row
 * (set_applicability()), and filtering by it selects the controls resolving to
 * the default.
 *
 * Every caller that offers a state vocabulary reads THIS — the POST body parser
 * (parse_applicability_set_request(), api/v2/includes/applicability.php) and the
 * controls table's applicability facet (parse_controls_table_request(),
 * api/v2/includes/governance_controls.php) — so the write path and the read
 * path cannot come to disagree about which states exist.
 *
 * Pure.
 *
 * @return string[]
 */
function applicability_requestable_states(): array {

    return APPLICABILITY_STORABLE_STATES;
}

/****************************************************
 * FUNCTION: RESOLVE APPLICABILITY                  *
 ****************************************************
 * Answers "what is this control's applicability?" from the decision row, or from
 * the absence of one.
 *
 * Pure: no DB, no globals, no output. Pass the row from get_applicability_map()
 * (or null when the map has no entry for the control) and this is the whole
 * resolution rule — callers must not re-derive it, or the page and the SoA would
 * each own a copy of the default.
 *
 * A stored state is returned as-is: the ENUM is the guarantee that it is one of
 * the two deviations, and silently folding an unexpected value into 'applicable'
 * here would hide schema drift rather than surface it.
 */
function resolve_applicability(?array $row): string {

    if ($row === null || empty($row['state'])) {
        return APPLICABILITY_DEFAULT_STATE;
    }

    return (string)$row['state'];
}

/****************************************************
 * FUNCTION: ASSERT STORABLE APPLICABILITY STATE    *
 ****************************************************
 * Refuses anything the `state` ENUM has no member for.
 *
 * It accepted only the two deviations until Task 4; 'applicable' is storable now
 * that an applicable control may carry its own justification. What it does NOT
 * mean is that an applicable control gets a row by default — a request with
 * nothing to record still deletes (set_applicability()).
 *
 * Pure. Throws InvalidArgumentException so the API layer can turn a bad request
 * into a 400 instead of letting MySQL answer with an out-of-range ENUM error.
 */
function assert_storable_applicability_state(string $state): void {

    if (!in_array($state, APPLICABILITY_STORABLE_STATES, true)) {
        // The rejected value is deliberately NOT echoed back. These messages reach
        // the API response, and the page shows an API message in a toast — which
        // renders HTML by default (see CLAUDE.md) — so reflecting an unvalidated
        // string here would be an XSS sink. Naming the ALLOWED values instead is
        // both safe and more useful to an API consumer than repeating their typo.
        throw new InvalidArgumentException(_lang(
            'ApplicabilityErrUnknownState',
            ['states' => implode(', ', APPLICABILITY_STORABLE_STATES)]
        ));
    }
}

/****************************************************
 * FUNCTION: ASSERT APPLICABILITY NARRATIVE         *
 ****************************************************
 * Every DEVIATION must be justified in prose. This is what makes an unjustified
 * exclusion impossible BY CONSTRUCTION (spec §5.4) — there is no state in which a
 * control is excluded without a stated reason, so nothing downstream has to
 * detect one or report it.
 *
 * THE RULE APPLIES TO THE TWO DEVIATIONS ONLY, and Task 4 did not change that.
 * An applicable control's narrative is OPTIONAL (spec §4): a taxonomy reason
 * alone is a complete answer, and forcing prose onto every applicable control
 * produces filler that makes the document worse rather than better. A control
 * with nothing to say carries no row at all, and the framework's
 * `default_inclusion_justification` fills its cell.
 *
 * NULL and '' are the same input here — both mean "no narrative". Which of them
 * gets STORED is applicability_effective_fields()' business, not this guard's.
 *
 * Pure.
 */
function assert_applicability_narrative(string $state, ?string $narrative): void {

    assert_storable_applicability_state($state);

    // $state is one of the three literals by the time it gets here — the guard
    // above already refused everything else — so interpolating it reflects nothing.
    if (in_array($state, APPLICABILITY_DEVIATION_STATES, true) && trim((string)$narrative) === '') {
        throw new InvalidArgumentException(
            _lang('ApplicabilityErrNarrativeRequired', ['state' => $state])
        );
    }

    if (strlen(trim((string)$narrative)) > APPLICABILITY_NARRATIVE_MAX_BYTES) {
        throw new InvalidArgumentException(_lang(
            'ApplicabilityErrNarrativeTooLong',
            ['bytes' => APPLICABILITY_NARRATIVE_MAX_BYTES]
        ));
    }
}

/****************************************************
 * FUNCTION: ASSERT APPLICABILITY REQUIREMENTS      *
 ****************************************************
 * The complete write-time contract for one stored decision (spec §5.3, §4):
 *
 *   not_applicable  ->  reason(s) (picklist) + narrative
 *   inherited       ->  provider             + narrative
 *   applicable      ->  nothing is required; a reason, prose, or both
 *
 * Picklist AND narrative for an exclusion: a picklist alone is uselessly vague,
 * and a narrative alone yields 93 differently-worded justifications that auditors
 * notice. A provider for an inheritance: "inherited" asserts that somebody else
 * performs the control, and a row that cannot name them is not an inheritance
 * claim — it is an unattributed gap.
 *
 * Reasons are optional for 'inherited' (the provider carries the meaning there)
 * and for 'applicable' (prose alone is a complete answer, and so is neither —
 * a control with neither simply carries no row).
 *
 * THIS IS CALLED WITH THE EFFECTIVE VALUES, not the request's. A caller that
 * omits the narrative on a control that already has one is not writing an
 * unjustified exclusion, so set_applicability() resolves absent-vs-empty FIRST
 * (applicability_effective_fields()) and asserts against the result.
 *
 * Pure — it deliberately does NOT check that the reasons exist or that they are
 * offered for $state; that needs the database and lives in set_applicability(),
 * which validates them against applicability_reasons_applies_to().
 *
 * set_applicability() calls this, and so does the API's request validation, so
 * the two provably agree about what a well-formed decision is instead of each
 * carrying its own copy of the rules.
 *
 * @param int[]|null $reason_ids The EFFECTIVE reasons — never the raw request.
 */
function assert_applicability_requirements(string $state, ?array $reason_ids, ?string $narrative, string $provider): void {

    assert_applicability_narrative($state, $narrative);

    if ($state === 'not_applicable' && empty($reason_ids)) {
        throw new InvalidArgumentException(_lang('ApplicabilityErrReasonRequired'));
    }

    if ($state === 'inherited' && trim($provider) === '') {
        throw new InvalidArgumentException(_lang('ApplicabilityErrProviderRequired'));
    }

    if (strlen(trim($provider)) > APPLICABILITY_PROVIDER_MAX_BYTES) {
        throw new InvalidArgumentException(_lang(
            'ApplicabilityErrProviderTooLong',
            ['bytes' => APPLICABILITY_PROVIDER_MAX_BYTES]
        ));
    }
}

/****************************************************
 * FUNCTION: GET APPLICABILITY REASONS              *
 ****************************************************
 * The configurable reason list, optionally scoped to the state it is offered for.
 *
 * `applies_to` is why "Performed by a third party" appears when marking a control
 * INHERITED and not when excluding one — the two states are distinct precisely so
 * that a cloud-hosted organisation is not recorded as having *excluded* the
 * datacenter perimeter controls a provider performs for it.
 *
 * The table is customer-extendable (it follows the `control_class` option-table
 * pattern), so callers must read the list rather than hardcode the seeded ids.
 *
 * The scope is checked against applicability_requestable_states() — the three
 * states a client may name — and NOT against APPLICABILITY_STORABLE_STATES. The
 * taxonomy has three sides: 'applicable' now carries the four standard INCLUSION
 * reasons (spec §4), and scoping by the storable-states list would silently drop
 * through to the unfiltered branch and offer every exclusion reason as a reason
 * to INCLUDE a control.
 *
 * @param string|null $applies_to 'applicable', 'not_applicable', 'inherited', or
 *                                null for all.
 * @return array<int, array{value:int, name:string, applies_to:string}>
 */
function get_applicability_reasons(?string $applies_to = null): array {

    $db = db_open();

    if ($applies_to !== null && in_array($applies_to, applicability_requestable_states(), true)) {
        $stmt = $db->prepare(
            "SELECT `value`, `name`, `applies_to` FROM `control_applicability_reason`
             WHERE `applies_to` = :applies_to ORDER BY `value` ASC;"
        );
        $stmt->execute([':applies_to' => $applies_to]);
    } else {
        $stmt = $db->prepare(
            "SELECT `value`, `name`, `applies_to` FROM `control_applicability_reason` ORDER BY `value` ASC;"
        );
        $stmt->execute();
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    return array_map(static function ($row) {
        return [
            'value'      => (int)$row['value'],
            'name'       => (string)$row['name'],
            'applies_to' => (string)$row['applies_to'],
        ];
    }, $rows);
}

/****************************************************
 * FUNCTION: APPLICABILITY REASON APPLIES TO        *
 ****************************************************
 * The state a reason is offered for, or null when no such reason exists.
 *
 * Returning null for "not found" rather than falling back to a default is what
 * lets set_applicability() tell an unknown id apart from a mismatched one.
 */
function applicability_reason_applies_to(int $reason_id): ?string {

    $db   = db_open();
    $stmt = $db->prepare("SELECT `applies_to` FROM `control_applicability_reason` WHERE `value` = :value LIMIT 1;");
    $stmt->execute([':value' => $reason_id]);
    $applies_to = $stmt->fetchColumn();

    db_close($db);

    return $applies_to === false ? null : (string)$applies_to;
}

/****************************************************
 * FUNCTION: APPLICABILITY NORMALIZE REASON IDS     *
 ****************************************************
 * A caller's reason list, reduced to positive ints, de-duplicated and sorted.
 *
 * Sorted so that a stored set and a requested set compare as equal regardless of
 * the order a form serialised its checkboxes in — the join table's composite
 * PRIMARY KEY makes the pair unordered anyway, and a read that came back in a
 * different order than it went in would look like a change to every diff.
 *
 * NULL IS NOT AN EMPTY LIST and never becomes one here: an absent field means
 * "no instruction" and must stay distinguishable from `[]`, which means "remove
 * every reason". Callers pass NULL straight through rather than through this.
 *
 * Pure.
 *
 * @return int[]
 */
function applicability_normalize_reason_ids(array $reason_ids): array {

    $ids = array_values(array_unique(array_filter(
        array_map('intval', $reason_ids),
        static fn($id) => $id > 0
    )));

    sort($ids);

    return $ids;
}

/****************************************************
 * FUNCTION: APPLICABILITY REASONS APPLIES TO       *
 ****************************************************
 * The state each of several reasons is offered for, keyed by reason id, in ONE
 * query. Ids that do not exist are ABSENT from the result rather than mapped to
 * null, which is what lets the caller name exactly which ones it could not find.
 *
 * The batch form exists because reasons are multi-select: validating a
 * five-reason selection through applicability_reason_applies_to() would be five
 * connections, and the write path is already a bulk operation.
 *
 * @param int[] $reason_ids
 * @return array<int, string>
 */
function applicability_reasons_applies_to(PDO $db, array $reason_ids): array {

    $ids = applicability_normalize_reason_ids($reason_ids);

    if (empty($ids)) {
        return [];
    }

    // int-cast then bound: only the placeholder ARITY reaches the SQL string.
    $stmt = $db->prepare(
        "SELECT `value`, `applies_to` FROM `control_applicability_reason`
         WHERE `value` IN (" . implode(',', array_fill(0, count($ids), '?')) . ");"
    );
    $stmt->execute($ids);

    $out = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['value']] = (string)$row['applies_to'];
    }

    return $out;
}

/****************************************************
 * FUNCTION: ASSERT APPLICABILITY REASONS OFFERED   *
 ****************************************************
 * Every reason in the selection must EXIST and must be offered for the state
 * being written.
 *
 * The reason list is scoped by `applies_to` in the UI; enforcing it here too is
 * what stops a client from storing "Excluded because: performed by a third
 * party", or "Included because: no such asset or technology" — exactly the
 * factually-wrong SoA rows the three-sided taxonomy exists to prevent. A
 * mismatched reason is REFUSED, never silently dropped: dropping it would write
 * a decision the user did not make and report success.
 *
 * @param int[] $reason_ids Already normalized.
 * @throws InvalidArgumentException naming the offending ids.
 */
function applicability_assert_reasons_offered_for(PDO $db, array $reason_ids, string $state): void {

    if (empty($reason_ids)) {
        return;
    }

    $applies_to = applicability_reasons_applies_to($db, $reason_ids);

    $missing = array_values(array_diff($reason_ids, array_keys($applies_to)));

    if (!empty($missing)) {
        // Validated ints only — nothing of the caller's text is echoed back.
        throw new InvalidArgumentException(_lang(
            'ApplicabilityErrReasonsNotFound',
            ['ids' => implode(', ', $missing)]
        ));
    }

    $mismatched = [];

    foreach ($reason_ids as $reason_id) {
        if ($applies_to[$reason_id] !== $state) {
            $mismatched[] = $reason_id;
        }
    }

    if (!empty($mismatched)) {
        throw new InvalidArgumentException(_lang(
            'ApplicabilityErrReasonsNotOffered',
            ['ids' => implode(', ', $mismatched)]
        ));
    }
}

/****************************************************
 * FUNCTION: APPLICABILITY EFFECTIVE FIELDS         *
 ****************************************************
 * ABSENT IS NOT EMPTY — the whole rule, in one pure function, for ONE control.
 *
 *   $reason_ids === null   say nothing  ->  keep what is stored
 *   $reason_ids === []     clear        ->  remove every reason
 *   $narrative  === null   say nothing  ->  keep what is stored
 *   $narrative  === ''     clear        ->  remove the prose
 *
 * `isset([])` is TRUE and `empty('')` is TRUE, which is precisely how the nine
 * absent-treated-as-clear bugs on this branch happened: a truthiness test cannot
 * tell "no instruction" from "remove it". Every decision here is an identity
 * check against null, never a truthiness test, and the request parser upstream
 * uses array_key_exists() for the same reason.
 *
 * PRESERVATION IS PER-STATE. $stored is only a source of defaults when it is in
 * the SAME state being written. A control moving from 'not_applicable' to
 * 'applicable' starts empty, because "No such technology" is not an inclusion
 * justification and an exclusion reason is not offered for the applicable state
 * at all — the same anti-drag rule the writer has always applied to `provider`.
 *
 * '' NEVER REACHES THE COLUMN as a narrative. A whitespace-only narrative is the
 * absence of one, and `narrative` is nullable so that "never answered" and
 * "deliberately blank" cannot be confused; storing '' would make them identical.
 *
 * Pure: no DB, no globals, no output.
 *
 * @param  array|null $stored      The stored decision row, or null when there is none.
 * @param  string     $state       The state being written.
 * @param  int[]|null $reason_ids  Normalized request reasons; null = absent.
 * @param  string|null $narrative  Request narrative; null = absent.
 * @param  string|null $provider   Request provider; null = absent.
 * @return array{reason_ids:int[], narrative:?string, provider:string}
 */
function applicability_effective_fields(?array $stored, string $state, ?array $reason_ids,
                                        ?string $narrative, ?string $provider): array {

    // THE NULL CHECK LIVES IN THE `if`, not in a `$same_state` flag the three
    // reads then trust. Both express the same condition, but only this shape is
    // one Phan can follow: a flag defers the non-nullness to a variable, and the
    // subsequent `$stored['...']` reads then look like accesses on a `?array`.
    $base_reasons   = [];
    $base_narrative = null;
    $base_provider  = '';

    if ($stored !== null && (string)($stored['state'] ?? '') === $state) {
        $base_reasons   = applicability_normalize_reason_ids((array)($stored['reason_ids'] ?? []));
        $base_narrative = ($stored['narrative'] ?? null) !== null ? (string)$stored['narrative'] : null;
        $base_provider  = (string)($stored['provider'] ?? '');
    }

    $effective_reasons   = $reason_ids === null ? $base_reasons   : applicability_normalize_reason_ids($reason_ids);
    $effective_narrative = $narrative  === null ? $base_narrative : $narrative;
    $effective_provider  = $provider   === null ? $base_provider  : $provider;

    if ($effective_narrative !== null) {
        $effective_narrative = trim($effective_narrative);

        if ($effective_narrative === '') {
            $effective_narrative = null;
        }
    }

    // An excluded or applicable control carries no provider. Normalising here —
    // rather than trusting the client to send the right emptiness — is what stops
    // a re-decision from dragging the superseded state's provider onto the new
    // one. The reasons need no equivalent: they are validated against the state's
    // own `applies_to`, which is a stricter version of the same guarantee.
    $effective_provider = ($state === 'inherited') ? trim($effective_provider) : '';

    return [
        'reason_ids' => $effective_reasons,
        'narrative'  => $effective_narrative,
        'provider'   => $effective_provider,
    ];
}

/****************************************************
 * FUNCTION: APPLICABILITY IS EMPTY DECISION        *
 ****************************************************
 * True when an APPLICABLE control's effective decision says nothing the absence
 * of a row does not already say — no reasons and no prose.
 *
 * This is the predicate behind delete-to-reset. It answers only for the default
 * state: a deviation is never empty (its narrative is mandatory), and treating
 * one as empty would delete an exclusion the user just recorded.
 *
 * Pure.
 *
 * @param array{reason_ids:int[], narrative:?string} $fields
 */
function applicability_is_empty_decision(string $state, array $fields): bool {

    if ($state !== APPLICABILITY_DEFAULT_STATE) {
        return false;
    }

    return empty($fields['reason_ids']) && ($fields['narrative'] ?? null) === null;
}

/****************************************************
 * FUNCTION: APPLICABILITY STORED FIELDS            *
 ****************************************************
 * What is currently stored for a selection, in the shape
 * applicability_effective_fields() reads — state, narrative, provider and the
 * reason ids, and NOTHING else.
 *
 * Separate from applicability_decision_rows() ON PURPOSE, and not a duplicate of
 * it: that function decorates every row with get_name_by_value('user', …), which
 * OPENS ITS OWN CONNECTION PER ROW. On a 5,000-control bulk set (the documented
 * cap) where the selection already holds decisions, using it here would cost
 * 5,000 connections to fetch a display name the writer never looks at. The read
 * path wants the name; the write path wants the values.
 *
 * @param  int[] $control_ids
 * @return array<int, array{state:string, narrative:?string, provider:string, reason_ids:int[]}>
 */
function applicability_stored_fields(PDO $db, int $framework, array $control_ids): array {

    $ids = array_values(array_unique(array_map('intval', $control_ids)));

    if (empty($ids)) {
        return [];
    }

    // int-cast then bound: only the placeholder ARITY reaches the SQL string.
    $stmt = $db->prepare(
        "SELECT `id`, `control_id`, `state`, `narrative`, `provider`
         FROM `framework_control_applicability`
         WHERE `framework` = ? AND `control_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ");"
    );
    $stmt->execute(array_merge([$framework], $ids));

    $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $reasons = applicability_reason_rows($db, array_map(static fn($r) => (int)$r['id'], $rows));

    $out = [];

    foreach ($rows as $row) {
        $out[(int)$row['control_id']] = [
            'state'      => (string)$row['state'],
            // NULL preserved — see applicability_decision_rows().
            'narrative'  => $row['narrative'] === null ? null : (string)$row['narrative'],
            'provider'   => (string)$row['provider'],
            'reason_ids' => array_map(static fn($r) => (int)$r['value'], $reasons[(int)$row['id']] ?? []),
        ];
    }

    return $out;
}

/****************************************************
 * FUNCTION: APPLICABILITY DECISION ROWS            *
 ****************************************************
 * The ONE query behind both public read functions. get_applicability_map() and
 * get_framework_applicability_map() answer different questions about the same
 * data and are cross-read by the SoA, so they share a query rather than each
 * carrying a copy that merely ought to match.
 *
 * $control_ids === null means "every decision in this framework".
 *
 * TWO QUERIES, NEVER N+1. The reasons are multi-select and live in
 * `framework_control_applicability_reasons`, so they are fetched for the whole
 * result set at once (applicability_reason_rows()) and stitched on in PHP. A
 * GROUP_CONCAT would be one query but would hand every caller a string to split,
 * and the SoA needs the names as a list.
 *
 * @return array<int, array<string, mixed>> keyed by control id
 */
function applicability_decision_rows(PDO $db, int $framework, ?array $control_ids): array {

    $sql = "SELECT a.`id`, a.`framework`, a.`control_id`, a.`state`, a.`narrative`,
                   a.`provider`, a.`decided_by`, a.`decided_at`
            FROM `framework_control_applicability` a
            WHERE a.`framework` = ?";

    $params = [$framework];

    if ($control_ids !== null) {
        // int-cast then bound: only the placeholder ARITY is interpolated into
        // the SQL string, never a value.
        $ids = array_values(array_unique(array_map('intval', $control_ids)));
        $sql .= " AND a.`control_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
        $params = array_merge($params, $ids);
    }

    $stmt = $db->prepare($sql . " ORDER BY a.`control_id` ASC;");
    $stmt->execute($params);

    $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $reasons = applicability_reason_rows($db, array_map(static fn($r) => (int)$r['id'], $rows));

    $out = [];

    foreach ($rows as $row) {

        $mine = $reasons[(int)$row['id']] ?? [];

        $out[(int)$row['control_id']] = [
            'id'              => (int)$row['id'],
            'framework'       => (int)$row['framework'],
            'control_id'      => (int)$row['control_id'],
            'state'           => (string)$row['state'],
            // MULTI-SELECT since Task 4. Two parallel lists rather than one list
            // of pairs, because every consumer wants exactly one of them: the
            // writer round-trips the ids, and the SoA prints the names.
            'reason_ids'      => array_map(static fn($r) => (int)$r['value'], $mine),
            'reason_names'    => array_map(static fn($r) => (string)$r['name'], $mine),
            // NULL IS PRESERVED, never flattened to ''. The column is nullable so
            // that "no narrative was ever given" (an applicable control justified
            // by its reasons alone) stays distinguishable from a deliberately
            // blank one — the absent-vs-empty distinction this whole file turns
            // on. A caller that wants a string uses `?? ''`.
            //
            // Plain text, stored and returned RAW — never HTML. A caller that
            // puts it on a page must render it as text (.text() / textContent),
            // the same contract the controls table's short_name / long_name
            // already carry.
            'narrative'       => $row['narrative'] === null ? null : (string)$row['narrative'],
            'provider'        => (string)$row['provider'],
            'decided_by'      => (int)$row['decided_by'],
            'decided_by_name' => get_name_by_value('user', (int)$row['decided_by'], ''),
            'decided_at'      => (string)$row['decided_at'],
        ];
    }

    return $out;
}

/****************************************************
 * FUNCTION: APPLICABILITY REASON ROWS              *
 ****************************************************
 * The reasons attached to several decisions, keyed by applicability id, in ONE
 * query.
 *
 * A decision with no reasons is ABSENT from the result — the caller defaults it
 * to an empty list, exactly as it defaults a control with no decision to
 * 'applicable'.
 *
 * `control_applicability_reason` is LEFT JOINed rather than INNER JOINed so a
 * join row whose reason has since been deleted from the customer-extendable
 * taxonomy still appears, with its id and an empty name, instead of vanishing
 * from the decision it justifies.
 *
 * @param  int[] $applicability_ids
 * @return array<int, array<int, array{value:int, name:string}>>
 */
function applicability_reason_rows(PDO $db, array $applicability_ids): array {

    $ids = array_values(array_unique(array_filter(array_map('intval', $applicability_ids), static fn($id) => $id > 0)));

    if (empty($ids)) {
        return [];
    }

    // int-cast then bound: only the placeholder ARITY reaches the SQL string.
    $stmt = $db->prepare(
        "SELECT ar.`applicability_id`, ar.`reason_id`, r.`name`
         FROM `framework_control_applicability_reasons` ar
         LEFT JOIN `control_applicability_reason` r ON ar.`reason_id` = r.`value`
         WHERE ar.`applicability_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
         ORDER BY ar.`applicability_id` ASC, ar.`reason_id` ASC;"
    );
    $stmt->execute($ids);

    $out = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['applicability_id']][] = [
            'value' => (int)$row['reason_id'],
            'name'  => $row['name'] === null ? '' : (string)$row['name'],
        ];
    }

    return $out;
}

/****************************************************
 * FUNCTION: GET APPLICABILITY MAP                  *
 ****************************************************
 * The stored decisions for a specific set of controls within ONE framework,
 * keyed by control id.
 *
 * Controls with no deviation are ABSENT from the map — that absence is the
 * answer, and resolve_applicability(null) turns it into 'applicable'. Callers
 * must therefore read `$map[$id] ?? null` through resolve_applicability() rather
 * than treating a missing key as an error.
 *
 * An empty selection reads nothing, so no query runs.
 *
 * @return array<int, array<string, mixed>>
 */
function get_applicability_map(int $framework, array $control_ids): array {

    if (empty($control_ids)) {
        return [];
    }

    $db  = db_open();
    $map = applicability_decision_rows($db, $framework, $control_ids);
    db_close($db);

    return $map;
}

/****************************************************
 * FUNCTION: GET FRAMEWORK APPLICABILITY MAP        *
 ****************************************************
 * Every stored decision in a framework, keyed by control id.
 *
 * Cheap by design: only deviations are stored, so this reads the exceptions, not
 * the catalogue. A framework with no exclusions returns an empty array — and that
 * is the correct, complete answer.
 *
 * Includes DORMANT decisions, whose control is no longer mapped into the
 * framework. That is deliberate: an auditor may still ask about last year's
 * exclusion, so a decision goes dormant rather than being deleted.
 *
 * @return array<int, array<string, mixed>>
 */
function get_framework_applicability_map(int $framework): array {

    $db  = db_open();
    $map = applicability_decision_rows($db, $framework, null);
    db_close($db);

    return $map;
}

/****************************************************
 * FUNCTION: SET APPLICABILITY                      *
 ****************************************************
 * Records (or clears) the applicability of a set of controls within ONE
 * framework. Bulk is the primary interaction, not a convenience (spec §5.6) —
 * nobody decides 1,535 SCF controls one row at a time.
 *
 * $state === 'applicable' WITH NOTHING TO RECORD deletes the rows rather than
 * storing them. That is not a shortcut: applicable is the default, and a stored
 * applicable row carrying no reason and no prose would be a second, emptier way
 * of spelling the absence that already means applicable. An applicable control
 * WITH a justification does get a row — that is what Task 4 added.
 *
 * ABSENT IS NOT EMPTY. `$reason_ids`, `$narrative` and `$provider` are all
 * nullable, and NULL means "the caller said nothing about this field", which
 * PRESERVES whatever is stored. `[]` and `''` are instructions to CLEAR. See
 * applicability_effective_fields(), which owns that resolution and is pure.
 *
 * ALL-OR-NOTHING. Every argument is validated, every framework/control id is
 * confirmed to exist, and every control's EFFECTIVE decision is resolved and
 * asserted, BEFORE anything is written, inside a transaction. A partly applied
 * bulk set would leave the user guessing which half landed — and because this
 * table deliberately carries no foreign keys (a decision must outlive the
 * mapping rebuilds a cascade would destroy it in), a row written against a typo'd
 * id would be a permanent orphan that no page shows and no cascade cleans up.
 *
 * Writes ONE audit-trail entry per call, not one per control: per-control
 * attribution is already carried by each row's own decided_by / decided_at, so a
 * 1,535-control SCF exclusion does not need 1,535 audit rows to be accountable.
 *
 * @param  int         $framework   Framework id the decision is scoped to.
 * @param  int[]       $control_ids Controls in the selection. Duplicates collapse.
 * @param  string      $state       'applicable', 'not_applicable' or 'inherited'.
 * @param  int[]|null  $reason_ids  Required for 'not_applicable'; optional otherwise.
 *                                  NULL preserves the stored reasons; [] clears them.
 * @param  string|null $narrative   Required for both deviations, optional for
 *                                  'applicable'. NULL preserves; '' clears.
 * @param  string|null $provider    Required for 'inherited'; forced empty otherwise.
 *                                  NULL preserves; '' clears.
 * @param  int         $user_id     The user the decision is attributed to.
 * @return int                      Controls whose decision was written, plus rows removed.
 * @throws InvalidArgumentException on any invalid argument. Nothing is written.
 */
function set_applicability(int $framework, array $control_ids, string $state,
                           ?array $reason_ids, ?string $narrative, ?string $provider, int $user_id): int {

    global $escaper;

    // Collapse a selection that names the same control more than once: three
    // mentions of one control are still one decision, and counting them
    // separately would report "3 controls set" for a single row.
    $ids = array_values(array_unique(array_filter(array_map('intval', $control_ids), static fn($id) => $id > 0)));

    if (empty($ids)) {
        return 0;
    }

    assert_storable_applicability_state($state);

    // NULL stays NULL — it is "no instruction", and normalising it into [] here
    // would be the absent-treated-as-clear bug this file is shaped to prevent.
    $requested_reasons = $reason_ids === null ? null : applicability_normalize_reason_ids($reason_ids);

    $db = db_open();

    try {
        applicability_assert_targets_exist($db, $framework, $ids);

        // The reasons the caller SUPPLIED are checked once for the whole call —
        // they are the same for every control in the selection. Preserved reasons
        // are not re-checked: they were validated against this same state when
        // they were written.
        applicability_assert_reasons_offered_for($db, $requested_reasons ?? [], $state);

        // WHAT IS ALREADY STORED, for the whole selection, in two queries — the
        // other half of absent-vs-empty. Without it an omitted field has nothing
        // to preserve and would silently blank a justification.
        $stored = applicability_stored_fields($db, $framework, $ids);

        // EVERY control resolved and asserted BEFORE the first write, so a
        // selection that is invalid for its 900th control writes nothing for the
        // first 899 either.
        $writes  = [];
        $deletes = [];

        foreach ($ids as $control_id) {

            $fields = applicability_effective_fields(
                $stored[$control_id] ?? null,
                $state,
                $requested_reasons,
                $narrative,
                $provider
            );

            if (applicability_is_empty_decision($state, $fields)) {
                $deletes[] = $control_id;
                continue;
            }

            assert_applicability_requirements($state, $fields['reason_ids'], $fields['narrative'], $fields['provider']);

            $writes[$control_id] = $fields;
        }

        $db->beginTransaction();

        $removed = 0;

        if (!empty($deletes)) {
            $stmt = $db->prepare(
                "DELETE FROM `framework_control_applicability`
                 WHERE `framework` = ? AND `control_id` IN (" . implode(',', array_fill(0, count($deletes), '?')) . ");"
            );
            $stmt->execute(array_merge([$framework], $deletes));
            // The join-table rows go with them via ON DELETE CASCADE.
            $removed = $stmt->rowCount();
        }

        if (!empty($writes)) {

            $stmt = $db->prepare(
                "INSERT INTO `framework_control_applicability`
                    (`framework`, `control_id`, `state`, `narrative`, `provider`, `decided_by`, `decided_at`)
                 VALUES (:framework, :control_id, :state, :narrative, :provider, :decided_by, NOW())
                 ON DUPLICATE KEY UPDATE
                    `state`      = VALUES(`state`),
                    `narrative`  = VALUES(`narrative`),
                    `provider`   = VALUES(`provider`),
                    `decided_by` = VALUES(`decided_by`),
                    `decided_at` = VALUES(`decided_at`);"
            );

            foreach ($writes as $control_id => $fields) {
                $stmt->execute([
                    ':framework'  => $framework,
                    ':control_id' => $control_id,
                    ':state'      => $state,
                    // Bound as NULL, not '' — see applicability_effective_fields().
                    ':narrative'  => $fields['narrative'],
                    ':provider'   => $fields['provider'],
                    ':decided_by' => $user_id,
                ]);
            }

            applicability_write_reasons($db, $framework, $writes);
        }

        // Every id in $writes now holds the requested decision, whether its write
        // was an insert or an update — rowCount() would report 2 for an update
        // and 0 for a no-change re-save, neither of which is the number the
        // caller asked about. Deletions ARE counted by rowCount(), because a
        // clear that removed nothing changed nothing.
        $affected = count($writes) + $removed;

        $db->commit();

        $username = applicability_username($db, $user_id);

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        db_close($db);

        throw $e;
    }

    db_close($db);

    // A clear that removed nothing changed nothing. Writing "Applicability was
    // reset to applicable for 0 control(s)" would put an event in the audit trail
    // that never happened — and because the page clears optimistically on every
    // deselect, those no-op entries would quickly outnumber the real decisions an
    // auditor came to read.
    if ($affected === 0) {

        write_debug_log(
            "Applicability '{$state}' request for framework {$framework} changed nothing.",
            'debug'
        );

        return $affected;
    }

    // `frameworks`.`name` is one of the encrypted-name tables, so read it through
    // get_name_by_value() rather than selecting it directly.
    $framework_name = $escaper->escapeHtml(get_name_by_value('frameworks', $framework, ''));
    $actor          = $escaper->escapeHtml($username);
    $written        = count($writes);

    // ONE call can now do BOTH, because the delete-to-reset decision is made per
    // control: a bulk "applicable" over a mixed selection stores a justification
    // for the controls that have one and resets the rest. The entry says what
    // actually happened rather than picking whichever half was larger.
    $parts = [];

    if ($written > 0) {
        $parts[] = "set to \"" . $escaper->escapeHtml($state) . "\" for {$written} control(s)";
    }

    if ($removed > 0) {
        $parts[] = "reset to the framework default for {$removed} control(s)";
    }

    $message = "Applicability was " . implode(' and ', $parts) .
               " in framework \"{$framework_name}\" by username \"{$actor}\".";

    // The +1000 offset is write_log()'s convention for a non-risk subject; it
    // subtracts 1000 back out before storing.
    write_log((int)$framework + 1000, $user_id, $message, 'framework');

    write_debug_log(
        "Applicability set to '{$state}' for {$affected} control(s) in framework {$framework}.",
        'info'
    );

    return $affected;
}

/****************************************************
 * FUNCTION: APPLICABILITY WRITE REASONS            *
 ****************************************************
 * Replaces each written decision's reasons with its EFFECTIVE set.
 *
 * A full replace rather than a diff, and that is safe precisely because the set
 * handed in is already effective: applicability_effective_fields() has folded
 * "the caller said nothing" into "what is stored", so a delete-then-insert can
 * never lose a reason the caller merely failed to mention. A diff would need the
 * same information and would be a second place for the absent-vs-empty rule to
 * live.
 *
 * Runs INSIDE the caller's transaction — the rows are gone between the DELETE
 * and the INSERT, and a failure between them must roll both back.
 *
 * The applicability ids are re-read rather than taken from lastInsertId(), which
 * reports the id of an INSERT only: an ON DUPLICATE KEY UPDATE that updated an
 * existing row would hand back the wrong id, or 0, and the reasons would land on
 * somebody else's decision.
 *
 * @param array<int, array{reason_ids:int[]}> $writes keyed by control id
 */
function applicability_write_reasons(PDO $db, int $framework, array $writes): void {

    if (empty($writes)) {
        return;
    }

    $control_ids = array_map('intval', array_keys($writes));

    $stmt = $db->prepare(
        "SELECT `id`, `control_id` FROM `framework_control_applicability`
         WHERE `framework` = ? AND `control_id` IN (" . implode(',', array_fill(0, count($control_ids), '?')) . ");"
    );
    $stmt->execute(array_merge([$framework], $control_ids));

    $applicability_ids = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $applicability_ids[(int)$row['control_id']] = (int)$row['id'];
    }

    $clear  = $db->prepare("DELETE FROM `framework_control_applicability_reasons` WHERE `applicability_id` = :a;");
    // The composite PRIMARY KEY makes a duplicate pair impossible by
    // construction, so nothing here needs to dedupe defensively.
    $insert = $db->prepare(
        "INSERT INTO `framework_control_applicability_reasons` (`applicability_id`, `reason_id`)
         VALUES (:a, :r);"
    );

    foreach ($writes as $control_id => $fields) {

        $applicability_id = $applicability_ids[(int)$control_id] ?? 0;

        if ($applicability_id <= 0) {
            // Unreachable: the row was just written inside this transaction. If
            // it ever happens, writing reasons against id 0 would orphan them.
            throw new RuntimeException("The applicability row for control {$control_id} could not be read back.");
        }

        $clear->execute([':a' => $applicability_id]);

        foreach ($fields['reason_ids'] as $reason_id) {
            $insert->execute([':a' => $applicability_id, ':r' => (int)$reason_id]);
        }
    }
}

/****************************************************
 * FUNCTION: APPLICABILITY ASSERT TARGETS EXIST     *
 ****************************************************
 * Confirms the framework and every control in the selection exist before a
 * decision is written against them.
 *
 * This check is not belt-and-braces — it is the ONLY thing standing in for the
 * foreign keys `framework_control_applicability` deliberately does not have. The
 * table has no FKs because a decision must survive the mapping rebuilds a cascade
 * would destroy it in; the cost of that choice is that a bad id here writes a
 * permanent orphan instead of erroring.
 *
 * Soft-deleted controls are refused for WRITES only. Reads still return a
 * decision whose control was later deleted — that is the dormant-not-deleted rule.
 *
 * @throws InvalidArgumentException naming the ids that do not exist.
 */
function applicability_assert_targets_exist(PDO $db, int $framework, array $control_ids): void {

    $stmt = $db->prepare("SELECT COUNT(*) FROM `frameworks` WHERE `value` = :framework;");
    $stmt->execute([':framework' => $framework]);

    if ((int)$stmt->fetchColumn() === 0) {
        throw new InvalidArgumentException(
            _lang('ApplicabilityErrFrameworkNotFound', ['framework' => $framework])
        );
    }

    if (empty($control_ids)) {
        return;
    }

    $stmt = $db->prepare(
        "SELECT `id` FROM `framework_controls`
         WHERE `deleted` = 0 AND `id` IN (" . implode(',', array_fill(0, count($control_ids), '?')) . ");"
    );
    $stmt->execute($control_ids);

    $found   = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
    $missing = array_values(array_diff($control_ids, $found));

    if (!empty($missing)) {
        throw new InvalidArgumentException(_lang(
            'ApplicabilityErrControlsNotFound',
            ['ids' => implode(', ', $missing)]
        ));
    }
}

/****************************************************
 * FUNCTION: APPLICABILITY USERNAME                 *
 ****************************************************
 * The username the audit-trail entry attributes the decision to.
 *
 * Read from the user id the caller passed rather than from $_SESSION['user'], so
 * the entry names whoever the decision was actually recorded for — the two differ
 * for an API-key caller, and a background caller has no session at all.
 */
function applicability_username(PDO $db, int $user_id): string {

    $stmt = $db->prepare("SELECT `username` FROM `user` WHERE `value` = :value LIMIT 1;");
    $stmt->execute([':value' => $user_id]);
    $username = $stmt->fetchColumn();

    return $username === false ? 'unknown' : (string)$username;
}

?>
