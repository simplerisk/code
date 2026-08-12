<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Declared directly (not just relied on transitively via api/v2/index.php's
// require order) so this file keeps working even if that order ever changes.
// api_v2_check_permission() / api_v2_json_result() live in api.php;
// get_framework_controls_by_filter() / get_framework_controls_count() and
// $escaper live in functions.php / governance.php.
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/governance.php'));
// resolve_applicability() / get_framework_applicability_map() /
// applicability_requestable_states() and the APPLICABILITY_* constants. Required
// directly rather than relied on transitively, per CLAUDE.md's cross-file
// reachability rule.
require_once(realpath(__DIR__ . '/../../../includes/applicability.php'));

// Sort allowlist. controls_table_sort() below sorts in memory with usort() --
// the value never reaches SQL -- but it still indexes directly into the row
// array, so an unlisted value falls back to the default rather than looking
// up a field that doesn't exist on the shaped rows. (The SQL injection
// boundary for this endpoint is in get_framework_controls_by_filter(), which
// int-casts every filter value it builds a WHERE clause from.)
const CONTROLS_TABLE_SORTS = [
    'control_number', 'short_name', 'family_name',
    'control_owner_name', 'control_maturity', 'control_status',
];

const CONTROLS_TABLE_STATUSES = ['pass', 'fail', 'not_tested'];

// Applicability is a real multi-select facet on the filter sheet now (Task 14),
// so it parses as a CSV of state tokens exactly like `status` and `maturity` do.
// The token list itself is applicability_requestable_states()
// (includes/applicability.php) -- the same three strings the POST
// /governance/applicability body parser accepts -- rather than a second copy
// here, so the facet can never offer a state the domain layer does not know.
//
// `excluded` is the ONE legacy spelling, and unlike maturity's `below_target`
// it expands to TWO tokens: the insights band's Excluded tile counts every
// stored DEVIATION (its subtitle is literally "Not applicable or inherited"),
// and both deviations are what "excluded from this framework's scope" means.
// The tile itself now emits the canonical pair; the alias exists for bookmarks
// and any URL already in the wild, so the two vocabularies cannot disagree
// about what they select.
const CONTROLS_TABLE_APPLICABILITY_ALIASES = ['excluded' => ['not_applicable', 'inherited']];

// Maturity is now a real multi-select facet on the filter sheet (Task 34), so
// it parses as a CSV of bucket tokens exactly like `status` does. The token
// list itself is control_maturity_bucket_tokens() (includes/governance.php) --
// the same three strings governance_maturity_gap_table() takes for the
// dashboard's Below/At/Above Maturity widgets -- rather than a second copy
// here.
//
// `below_target` is the ONE legacy spelling: the Below-target KPI tile linked
// to ?maturity=below_target before this facet existed, so it is aliased to
// `below` rather than dropped. The tile itself now emits the canonical token;
// the alias exists for bookmarks and any URL already in the wild, so the two
// vocabularies can never disagree about what they select.
const CONTROLS_TABLE_MATURITY_ALIASES = ['below_target' => 'below'];

/**
 * The ONE framework applicability is answered within for this request, or null
 * when there isn't one.
 *
 * Applicability is inherently per-framework -- the same control excluded from
 * ISO 27001 is not thereby excluded from PCI DSS -- so outside exactly one
 * framework's scope there is no single honest answer to give a row, a filter,
 * or a KPI tile. Three things key off that fact (the row shape, the facet, and
 * the response flag the client renders its column from), so it is derived ONCE
 * here rather than re-expressed at each of them.
 *
 * The -1 "Unassigned" sentinel every id facet accepts is not a framework, and a
 * control belonging to no framework has no per-framework applicability, so it
 * resolves to null too.
 *
 * Pure: no DB, no globals, no output.
 */
function controls_table_applicability_framework(array $framework_ids): ?int {
    if (count($framework_ids) !== 1) return null;
    $framework = (int)$framework_ids[0];
    return $framework > 0 ? $framework : null;
}

/**
 * Normalize a controls-table request. Pure: no DB, no globals, no output.
 */
function parse_controls_table_request(array $get): array {
    $csv_ints = static function ($v): array {
        if ($v === null || $v === '') return [];
        return array_values(array_map('intval', array_filter(
            explode(',', (string)$v), static fn($s) => $s !== '' && is_numeric(trim($s))
        )));
    };

    $status = [];
    foreach (explode(',', (string)($get['status'] ?? '')) as $s) {
        $s = trim($s);
        if (in_array($s, CONTROLS_TABLE_STATUSES, true)) $status[] = $s;
    }

    // Maturity: CSV of bucket tokens, legacy spellings folded onto their
    // canonical token first, then allowlisted and de-duplicated -- so
    // ?maturity=below_target,below selects the "below" bucket once, not twice.
    $maturity = [];
    foreach (explode(',', (string)($get['maturity'] ?? '')) as $m) {
        $m = trim($m);
        if (isset(CONTROLS_TABLE_MATURITY_ALIASES[$m])) $m = CONTROLS_TABLE_MATURITY_ALIASES[$m];
        if (in_array($m, control_maturity_bucket_tokens(), true) && !in_array($m, $maturity, true)) $maturity[] = $m;
    }

    $framework = $csv_ints($get['framework'] ?? null);

    // Applicability: CSV of state tokens, legacy `excluded` expanded to the two
    // deviations first, then allowlisted and de-duplicated -- so
    // ?applicability=excluded,inherited selects "inherited" once, not twice.
    //
    // DROPPED ENTIRELY unless exactly one framework is scoped. That is not a
    // convenience: absent a framework there is no state to compare against, so
    // the honest options are to refuse the request or to not filter, and the
    // facet is only OFFERED when scoped (governance-frameworks.js) -- the only
    // way to arrive here is a hand-typed URL. Dropping it in the PARSER rather
    // than at the call site keeps the decision in one pure, testable place and
    // makes the parsed request say plainly what will be filtered on.
    $applicability = [];
    if (controls_table_applicability_framework($framework) !== null) {
        foreach (explode(',', (string)($get['applicability'] ?? '')) as $a) {
            $a = trim($a);
            foreach (CONTROLS_TABLE_APPLICABILITY_ALIASES[$a] ?? [$a] as $token) {
                if (in_array($token, applicability_requestable_states(), true)
                    && !in_array($token, $applicability, true)) {
                    $applicability[] = $token;
                }
            }
        }
    }

    $sort = (string)($get['sort'] ?? '');
    if (!in_array($sort, CONTROLS_TABLE_SORTS, true)) $sort = 'control_number';

    $length = isset($get['length']) ? (int)$get['length'] : 25;
    $length = max(1, min(200, $length));

    return [
        'start'       => max(0, (int)($get['start'] ?? 0)),
        'length'      => $length,
        'sort'        => $sort,
        'dir'         => strtolower((string)($get['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        'framework'   => $framework,
        'family'      => $csv_ints($get['family']      ?? null),
        'owner'       => $csv_ints($get['owner']       ?? null),
        'control_class'=> $csv_ints($get['class']      ?? null),
        'phase'       => $csv_ints($get['phase']       ?? null),
        'priority'    => $csv_ints($get['priority']    ?? null),
        'type'        => $csv_ints($get['type']        ?? null),
        'status'      => $status,
        'maturity'    => $maturity,
        'applicability' => $applicability,
        'text'        => trim((string)($get['text'] ?? '')),
    ];
}

/**
 * get_framework_controls_by_filter() (includes/governance.php) treats the
 * string "all" -- not an empty array -- as "no restriction on this facet".
 * Passing it an empty array instead hits the falsy-array branch of its
 * if/elseif/else chain and degrades to "AND 0", which filters out every
 * row. parse_controls_table_request() normalizes "no filter" to [] (the
 * natural shape for a client to send), so translate back to the sentinel
 * the filter helper actually expects before calling it.
 */
function controls_table_filter_arg(array $ids) {
    return empty($ids) ? 'all' : $ids;
}

/**
 * Clamps a requested page offset onto the last page that actually exists for
 * $filtered rows at $length per page.
 *
 * The client cannot know how many rows the CURRENT filter matches until the
 * response comes back, so it can legitimately ask for an offset past the end:
 * a bookmarked ?start=1525, or a filter narrowed while the user sat on page
 * 40. array_slice() answers that with an empty page while `filtered` still
 * reports a non-zero count, which renders as "Showing 1001-1025 of 30" over an
 * empty table. Clamping here -- and echoing the clamped value back as the
 * response's own `start` -- is what makes the rendered rows and the summary
 * text derive from ONE offset instead of two that can disagree.
 *
 * Pure: no DB, no globals, no output.
 */
function controls_table_clamp_start(int $start, int $length, int $filtered): int {
    if ($start <= 0 || $filtered <= 0 || $length <= 0) return 0;
    if ($start < $filtered) return $start;
    return ((int)ceil($filtered / $length) - 1) * $length;
}

/**
 * Resolves a filter map into the control ROWS it names, through the controls
 * table's own pipeline.
 *
 * THIS IS THE PIPELINE. controls_table_filtered_control_ids() below is a thin
 * projection of it, and the Statement of Applicability (build_soa_rows(),
 * includes/soa.php) reads the rows directly -- so a bulk write, a bulk delete,
 * the grid, and the SoA all resolve their population here, once, instead of in
 * four places that ought to agree.
 *
 * The rows are the raw get_framework_controls_by_filter() shape (see
 * controls_table_shape_row() for what the endpoint then sends to the client),
 * distinct by that function's own `GROUP BY t1.id` and already free of
 * soft-deleted controls via its `t1.deleted=0`. The GROUP BY is not incidental:
 * `framework_control_mappings` is unique on (control_id, framework,
 * reference_name), so one control legitimately carries several mapping rows
 * within a single framework, and a consumer that joined that table itself would
 * see the same control several times over. See RULE 2 in includes/soa.php for
 * what that measured.
 *
 * The whole point is that this runs the CONTROLS TABLE's own pipeline --
 * parse_controls_table_request() -> get_framework_controls_by_filter() ->
 * controls_table_apply_maturity_applicability() -- rather than a second query
 * that merely ought to match it. The count the bulk bar displayed came from
 * `filtered` on api_v2_governance_controls_table() below; resolving a bulk
 * WRITE through the same three calls is what makes "the bar says 1,535 and
 * 1,535 rows are acted on" structural instead of a coincidence. A private copy
 * of that WHERE clause is exactly the count-vs-grid disagreement Tasks
 * 29 / 37 / 40 spent a day removing from this page.
 *
 * It lives HERE, next to the endpoint whose pipeline it borrows, rather than
 * inside any one caller: POST /governance/applicability (Task 15) and POST
 * /governance/controls/bulk-delete (Task 54) both escalate, and the moment a
 * second caller copied it there would be two resolvers that "should" agree.
 *
 * THE FRAMEWORK ARGUMENT WINS, WHEN THERE IS ONE. A positive $framework is
 * ASSIGNED over `$filters['framework']`, so a client cannot resolve ids out of
 * framework B and have them acted on as framework A's; it also keeps the
 * applicability facet in force, since parse_controls_table_request() only
 * honours that facet when exactly one framework is scoped.
 *
 * $framework is NULLABLE because not every escalation is per-framework.
 * Applicability is (the same control excluded from ISO 27001 is not thereby
 * excluded from PCI DSS), so it always passes one. Deleting a control is not
 * -- it removes the control from every framework it is mapped to -- so the
 * controls table's unscoped "All frameworks" view is a legitimate population
 * to escalate over, and there the client's own framework facet is passed
 * through as sent. Nothing is trusted either way: every value is re-parsed and
 * allowlisted by parse_controls_table_request() before it reaches SQL.
 *
 * The applicability decision map is derived exactly as the table endpoint
 * derives it -- via controls_table_applicability_framework() on the PARSED
 * framework list -- so a scoped and an unscoped resolution differ in the same
 * one place the table itself differs.
 *
 * Soft-deleted controls are already excluded by
 * get_framework_controls_by_filter()'s own `t1.deleted=0`, and its `GROUP BY
 * t1.id` means one row per control even for a control carrying several mapping
 * rows in this framework -- so the ids come back distinct without a second pass.
 *
 * @return array<int, array<string, mixed>> Distinct control rows, in the
 *         filter's own order.
 */
function controls_table_filtered_controls(?int $framework, array $filters): array {

    // Not merged, ASSIGNED: whatever framework the client put in its filter map
    // is discarded in favour of the one the write is scoped to. Skipped when
    // the write is not framework-scoped at all -- see the note above.
    if ($framework !== null && $framework > 0) {
        $filters['framework'] = (string)$framework;
    }

    $req = parse_controls_table_request($filters);

    $applicability_framework = controls_table_applicability_framework($req['framework']);
    $decisions = $applicability_framework === null ? [] : get_framework_applicability_map($applicability_framework);

    $controls = get_framework_controls_by_filter(
        controls_table_filter_arg($req['control_class']),
        controls_table_filter_arg($req['phase']),
        controls_table_filter_arg($req['owner']),
        controls_table_filter_arg($req['family']),
        controls_table_filter_arg($req['framework']),
        controls_table_filter_arg($req['priority']),
        controls_table_filter_arg($req['type']),
        controls_table_filter_arg(controls_table_status_to_db($req['status'])),
        $req['text']
    );

    return controls_table_apply_maturity_applicability(
        $controls, $req['maturity'], $req['applicability'], $decisions
    );
}

/**
 * Resolves an escalated "Select all N" selection into the control ids it names.
 *
 * A thin projection of controls_table_filtered_controls() above -- deliberately
 * thin, so the ids a bulk write acts on and the rows the SoA prints are the same
 * resolution and not two that merely start from the same arguments.
 *
 * Kept as its own function rather than inlined at its two call sites (POST
 * /governance/applicability, POST /governance/controls/bulk-delete): those
 * callers want ids, and having them each map the rows themselves would be two
 * places to get the distinctness wrong.
 *
 * @return int[] Distinct control ids, in the filter's own order.
 */
function controls_table_filtered_control_ids(?int $framework, array $filters): array {

    return array_values(array_unique(array_map(
        static fn($c) => (int)$c['id'],
        controls_table_filtered_controls($framework, $filters)
    )));
}

function api_v2_governance_controls_table() {
    api_v2_check_permission("governance");

    $req = parse_controls_table_request($_GET);

    // The one framework applicability is answered within, or null. Resolved
    // once, from the same helper the parser used to decide whether the
    // applicability facet applies, and then used for all three of: the filter,
    // the per-row record, and the flag the client renders its column from.
    $applicability_framework = controls_table_applicability_framework($req['framework']);

    // Every DEVIATION in that framework, keyed by control id. The whole-framework
    // read rather than a per-page one: only deviations are stored, so this reads
    // the exceptions and not the 1,535-row catalogue, and the FILTER below has to
    // see every matching row -- not just the page -- to page and count correctly.
    // Controls with no row are absent, which IS the answer; resolve_applicability()
    // turns that absence into 'applicable' for both readers below.
    $decisions = $applicability_framework === null ? [] : get_framework_applicability_map($applicability_framework);

    // Existing filter helper keeps today's semantics (including the -1
    // "Unassigned" sentinel and the ~18-field free-text match).
    $controls = get_framework_controls_by_filter(
        controls_table_filter_arg($req['control_class']),
        controls_table_filter_arg($req['phase']),
        controls_table_filter_arg($req['owner']),
        controls_table_filter_arg($req['family']),
        controls_table_filter_arg($req['framework']),
        controls_table_filter_arg($req['priority']),
        controls_table_filter_arg($req['type']),
        controls_table_filter_arg(controls_table_status_to_db($req['status'])),
        $req['text']
    );

    // Two more filters the insights band's tiles drill through with.
    $controls = controls_table_apply_maturity_applicability($controls, $req['maturity'], $req['applicability'], $decisions);

    $filtered = count($controls);
    $controls = controls_table_sort($controls, $req['sort'], $req['dir']);
    // The EFFECTIVE offset -- never the requested one -- is what the slice and
    // the response both use, so a client that asked for a page which no longer
    // exists gets the last real page plus the offset it was actually served,
    // rather than an empty table under a confident "Showing 1001-1025 of 30".
    $start = controls_table_clamp_start($req['start'], $req['length'], $filtered);
    $page = array_slice($controls, $start, $req['length']);

    $rows = array_map('controls_table_shape_row', $page);
    if ($applicability_framework !== null) {
        $rows = controls_table_attach_applicability($rows, $decisions);
    }

    api_v2_json_result(200, "SUCCESS", [
        'rows'     => $rows,
        'start'    => $start,
        'length'   => $req['length'],
        'total'    => get_framework_controls_count(),
        'filtered' => $filtered,
        // Whether applicability is answerable for this request at all. Echoed
        // rather than left for the client to re-derive from its own framework
        // selection: the SERVER decides which rows carry an applicability
        // record, so the client showing the column must key off that same
        // decision -- the same reason GET /governance/applicability echoes
        // `default_state` instead of letting the client invent one.
        'applicability_scoped' => $applicability_framework !== null,
    ]);
}

/**
 * Applies the maturity-bucket facet and the applicability tile filter to the
 * raw get_framework_controls_by_filter() rows, IN MEMORY rather than folded
 * into that function's own WHERE-clause builder -- that function also backs
 * the published getFrameworkControlsDatatable() API contract (v1 + v2 +
 * swagger), so its SQL shape is frozen; a filter this endpoint alone needs
 * stays local to it instead, the same way controls_table_sort() above already
 * post-processes in PHP rather than SQL.
 *
 * Pure: no DB, no globals, no output -- testable directly against fixture
 * rows (mirrors controls_table_sort()'s own pure-function shape).
 *
 * $maturity and $applicability are already-validated, already-de-aliased arrays
 * of tokens (either possibly empty) from parse_controls_table_request(). Neither
 * needs re-validating here.
 *
 * The bucket comparison is control_maturity_bucket() (includes/governance.php)
 * -- the SAME function get_control_maturity_counts() buckets by -- so the
 * filter sheet's count chip and the rows this returns cannot disagree.
 *
 * $decisions is the framework's stored applicability rows keyed by control id
 * (get_framework_applicability_map(), includes/applicability.php). It is passed
 * IN rather than read here so this function stays pure, and every state is
 * resolved through resolve_applicability() -- the one place the "no row means
 * applicable" rule lives -- rather than by testing for a key's presence, which
 * would be a second copy of that rule.
 */
function controls_table_apply_maturity_applicability(array $controls, array $maturity, array $applicability, array $decisions = []): array {
    if (!empty($maturity)) {
        $controls = array_values(array_filter($controls, static function ($c) use ($maturity) {
            $bucket = control_maturity_bucket(
                $c['control_maturity'] ?? null,
                $c['desired_maturity'] ?? null
            );
            return $bucket !== '' && in_array($bucket, $maturity, true);
        }));
    }
    if (!empty($applicability)) {
        $controls = array_values(array_filter($controls, static function ($c) use ($applicability, $decisions) {
            return in_array(resolve_applicability($decisions[(int)$c['id']] ?? null), $applicability, true);
        }));
    }
    return $controls;
}

/**
 * Attaches each row's applicability record, resolved from the framework's
 * stored decisions.
 *
 * Called ONLY when exactly one framework is scoped
 * (controls_table_applicability_framework()) -- outside that scope the fields
 * are absent entirely rather than present-and-null, so a client cannot mistake
 * "not answerable here" for "applicable".
 *
 * resolve_applicability() is what turns a missing decision into 'applicable':
 * absence IS the answer, and re-deriving that here would give the page a second
 * copy of the default (includes/applicability.php's opening note).
 *
 * `narrative` and `provider` are PLAIN TEXT returned raw -- the same contract
 * short_name / long_name already carry in controls_table_shape_row(). They are
 * user-authored, so the renderer MUST insert them with .text()/textContent and
 * never .html(). They are deliberately NOT purified: purifying plain text would
 * mangle a justification containing "<" or "&" into markup entities.
 *
 * `applicability_by` is the decider's NAME, not the id -- the drawer renders it
 * as text and has no user list on the page to look an id up in, the same rule
 * controls_table_shape_row() applies to its own display names.
 *
 * Pure: no DB, no globals, no output.
 */
function controls_table_attach_applicability(array $rows, array $decisions): array {
    foreach ($rows as &$row) {
        $decision = $decisions[(int)$row['id']] ?? null;
        $row['applicability']           = resolve_applicability($decision);
        // JOINED FOR THE FLAT FIELD the drawer already prints as one label, with
        // the list beside it for a caller that wants to itemise. Reasons became
        // MULTI-select in Task 4; null (not '') when there are none, so the
        // drawer's "print it only when present" test still works.
        $row['applicability_reason']    = empty($decision['reason_names'])
                                              ? null
                                              : implode('; ', $decision['reason_names']);
        $row['applicability_reasons']   = $decision['reason_names'] ?? [];
        $row['applicability_reason_ids'] = $decision['reason_ids'] ?? [];
        $row['applicability_narrative'] = $decision['narrative'] ?? null;
        $row['applicability_provider']  = $decision['provider'] ?? null;
        $row['applicability_by']        = $decision['decided_by_name'] ?? null;
        $row['applicability_at']        = $decision['decided_at'] ?? null;
    }
    unset($row);
    return $rows;
}

/**
 * Map the 3 UI status tokens onto the DB's control_status values.
 *
 * The map itself lives in control_status_token_map() (includes/governance.php,
 * required at the top of this file) rather than here, so this filter and the
 * filter sheet's status count chips -- get_control_status_counts(), in that
 * same file -- can only ever read ONE definition of what "pass" means. A chip
 * whose count came from a second copy of these literals would silently drift
 * from the rows the filter actually returns.
 */
function controls_table_status_to_db(array $tokens): array {
    $map = control_status_token_map();
    return array_values(array_map(static fn($t) => $map[$t], $tokens));
}

/**
 * Send facet IDs for anything the client FILTERS on -- the page already renders
 * those option lists, so repeating ~1,500 labels is payload for nothing and a
 * second source of truth. Display NAMES are sent alongside only for the fields
 * that are RENDERED as text and have no option list on the page to look them up
 * in: the drawer's read-only fields (Task 6), and the two maturity levels the
 * Maturity column's pills are labelled with (Task 30). Maturity names are
 * admin-renameable (admin/custom_names.php -> `control_maturity`), so a
 * number->name map baked into the page's JS would go stale silently.
 *
 * Rich text is purified here, once, on the server. The drawer inserts it with
 * .html(), so it must never carry raw stored markup.
 */
function controls_table_shape_row(array $c): array {
    global $escaper;
    return [
        'id'                  => (int)$c['id'],
        'control_number'      => $c['control_number'],
        'short_name'          => $c['short_name'],
        'long_name'           => $c['long_name'],
        // --- facet ids (filtering) ---
        'family'              => (int)$c['family'],
        'control_class'       => (int)$c['control_class'],
        'control_phase'       => (int)$c['control_phase'],
        'control_priority'    => (int)$c['control_priority'],
        'control_owner'       => (int)$c['control_owner'],
        'control_type_ids'    => array_values(array_filter(explode(',', (string)$c['control_type_ids']))),
        'frameworks'          => array_values(array_filter(explode(',', (string)$c['framework_ids']))),
        // --- display names (drawer + table cells) ---
        'family_name'         => $c['family_short_name'],
        'control_class_name'  => $c['control_class_name'],
        'control_phase_name'  => $c['control_phase_name'],
        'control_priority_name' => $c['control_priority_name'],
        'control_owner_name'  => $c['control_owner_name'],
        'control_type_names'  => array_values(array_filter(explode(',', (string)($c['control_type_names'] ?? '')))),
        // --- state ---
        'control_status'      => $c['control_status'] === null ? 2 : (int)$c['control_status'],
        'control_maturity'    => (int)$c['control_maturity'],
        'desired_maturity'    => (int)$c['desired_maturity'],
        // Level LABELS for the Maturity column's pills. LEFT JOINed off the
        // control_maturity lookup, so an out-of-scale value yields NULL -- fall
        // back to the number, exactly as the governance dashboard's gap table
        // does (governance_maturity_gap_table(), includes/reporting.php).
        'control_maturity_name' => (string)($c['control_maturity_name'] ?? $c['control_maturity']),
        'desired_maturity_name' => (string)($c['desired_maturity_name'] ?? $c['desired_maturity']),
        'mitigation_percent'  => (int)$c['mitigation_percent'],
        // --- rich text, purified once here ---
        'description_purified'            => $escaper->purifyHtml((string)$c['description']),
        'supplemental_guidance_purified'  => $escaper->purifyHtml((string)$c['supplemental_guidance']),
    ];
}

function controls_table_sort(array $rows, string $sort, string $dir): array {
    // controls_table_sort() runs BEFORE controls_table_shape_row(), against the
    // raw get_framework_controls_by_filter() rows -- most CONTROLS_TABLE_SORTS
    // tokens are also the raw column name, but "family_name" is the shaped-row
    // name; the raw column is "family_short_name". Alias it so the Family
    // column sort isn't silently a no-op.
    $key = $sort === 'family_name' ? 'family_short_name' : $sort;

    usort($rows, static function ($a, $b) use ($key, $dir) {
        $av = $a[$key] ?? ''; $bv = $b[$key] ?? '';
        $c = is_numeric($av) && is_numeric($bv) ? ($av <=> $bv) : strcasecmp((string)$av, (string)$bv);
        return $dir === 'desc' ? -$c : $c;
    });
    return $rows;
}

/******************************************************************************
 * BULK CONTROL DELETE (Task 54)
 *
 *   POST /governance/controls/bulk-delete
 *
 * ONE endpoint answers both halves of a destructive confirmation: what WOULD
 * happen, and then what DID. See the note on `confirm` below -- the preview and
 * the write are the same handler with an early return, not two endpoints that
 * ought to agree.
 *
 * WHY POST AND NOT DELETE. There is no addressable resource here. The request
 * names either a list of ids or a FILTER, which the server resolves; DELETE
 * /governance/controls/{id} already covers the single-resource case, and a
 * "delete everything matching this" is an action, not a resource (the
 * building-api-endpoints skill's RPC form, `POST /<area>/<verb>`). Nor can it
 * be a DELETE with a body: the filter map is a nested object, and a DELETE body
 * is not reliably carried by proxies or by jQuery.
 ******************************************************************************/

/**
 * Upper bound on one bulk delete. DELIBERATELY LOWER than the sibling
 * APPLICABILITY_MAX_SELECTION (5000), for two independent reasons.
 *
 * PRODUCT. The largest population anyone can legitimately bulk-delete is the
 * controls of one framework, and the largest catalogue SimpleRisk ships against
 * is the SCF at 1,535. 2,000 clears that with headroom and still refuses a
 * request that could only have come from a script. Applicability can afford a
 * looser bound because it is reversible; the hard-delete half of this one is
 * not.
 *
 * MEASURED. delete_framework_controls_batch() costs ~2.5 ms/control on an
 * instance with no workflows (measured on 100 / 500 / 1,500 / 3,000-control
 * batches: 3.21 / 2.72 / 2.63 / 2.31 ms per control, linear), so the cap is ~5 s
 * of work. The dominant per-control cost is its post-commit
 * trigger_workflow_event('control.deleted') -- about 10 ms per MATCHING enabled
 * workflow per control (measured at 327 ms/control against a box carrying 33
 * seeded control.deleted workflows). At 2,000 the request still finishes inside
 * a normal request budget with a workflow or two configured; 5,000 would not,
 * and the dispatch loop runs AFTER the commit, so a timeout there would leave
 * the delete done while the caller was told it failed.
 */
const CONTROLS_BULK_DELETE_MAX = 2000;

/**
 * The ONE size rule for a bulk delete selection, applied to both ways of naming
 * one. An explicit `control_ids` list is checked by the parser; an escalated
 * `all_filtered` set is checked after resolution, because its size is not known
 * until then. Expressing that rule twice is how the two come to disagree about
 * which requests are refused, so it is expressed once.
 *
 * BOTH ENDS ARE A REFUSAL. Zero is a 400, not a cheerful "0 deleted": it means
 * the UI let the user act on nothing (or a filter narrowed under them), and a
 * silent success hides that instead of surfacing it. The upper bound is
 * CONTROLS_BULK_DELETE_MAX; see the note on that constant for why it is lower
 * than the reversible sibling's.
 *
 * $from_filter only chooses the wording. A filtered selection did not "name"
 * anything -- the user named a filter, and telling them their LIST is too long
 * would describe a request they did not make.
 *
 * Pure: no DB, no globals, no output.
 *
 * @throws InvalidArgumentException with a message safe to return to the caller.
 */
function assert_controls_bulk_delete_size(array $control_ids, bool $from_filter): void {

    if (empty($control_ids)) {
        throw new InvalidArgumentException($from_filter
            ? 'The current filters match no controls.'
            : 'At least one control id is required.');
    }

    if (count($control_ids) > CONTROLS_BULK_DELETE_MAX) {
        throw new InvalidArgumentException($from_filter
            ? 'The current filters match more than ' . CONTROLS_BULK_DELETE_MAX
              . ' controls. Narrow the filter and delete in smaller batches.'
            : 'A selection may name at most ' . CONTROLS_BULK_DELETE_MAX . ' controls.');
    }
}

/**
 * Normalizes and validates the SHAPE of a POST /governance/controls/bulk-delete
 * body. Pure: no DB, no globals, no output.
 *
 * TWO WAYS TO NAME A SELECTION, and exactly one per request -- the same
 * contract parse_applicability_set_request() established, because the bulk bar
 * that drives both has the same problem: the control table pages, so the client
 * only ever holds ONE page, and "Select all 1,535" cannot be expressed as a
 * list of ids it never fetched.
 *
 *   control_ids: [..]                    the rows the client is holding
 *   all_filtered: true, filters: {..}    every row the CURRENT filter matches
 *
 * Sending both is REFUSED rather than resolved by precedence: they name two
 * different populations, and on a DELETE, silently discarding one of them is
 * the difference between removing 25 controls and removing 1,535.
 *
 * `framework` IS OPTIONAL HERE, unlike applicability. Applicability is
 * per-framework -- a control excluded from ISO 27001 is not thereby excluded
 * from PCI DSS -- so a decision with no framework to belong to is meaningless.
 * Deleting a control is the opposite: it removes the control from EVERY
 * framework it is mapped to, which is exactly what the modal's consequence line
 * says. The controls table's unscoped "All frameworks" view is therefore a
 * legitimate population to delete from, and the bulk bar renders Delete there
 * (it withholds Set applicability). When a framework IS scoped it is still
 * assigned over the client's filter map by controls_table_filtered_control_ids().
 *
 * @throws InvalidArgumentException with a message safe to return to the caller.
 */
function parse_controls_bulk_delete_request(array $body): array {

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

        assert_controls_bulk_delete_size($control_ids, false);
    }

    $framework = (int)($body['framework'] ?? 0);

    return [
        'framework'    => $framework > 0 ? $framework : null,
        'control_ids'  => $control_ids,
        'all_filtered' => $all_filtered,
        'filters'      => $filters,
        // THE INTERLOCK. Absent or false means PREVIEW: resolve the set, report
        // the split, write nothing. Only an explicit true deletes.
        //
        // The default is the SAFE direction on purpose. A `dry_run` flag would
        // have defaulted the other way, so a client that dropped the flag on its
        // confirmation request would silently delete 1,535 controls with no
        // confirmation shown at all. Requiring the destructive branch to be named
        // makes the resolve-then-confirm shape structural rather than a client
        // convention.
        'confirm'      => filter_var($body['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];
}

/****************************************************
 * FUNCTION: API V2 GOVERNANCE CONTROLS BULK DELETE *
 ****************************************************
 * POST /governance/controls/bulk-delete
 *
 * PERMISSIONS. `delete_controls` is the permission that actually gates deleting
 * a control -- traced to deleteControlById() (includes/api.php), the
 * single-control path this endpoint is the bulk form of, which checks it and
 * nothing else. `governance` is checked as well because that is what gates
 * READING the controls this resolves over (api_v2_governance_controls_table()
 * above), and an escalated selection is a read before it is a write. Both, not
 * either: this is a bulk destructive endpoint, and it is the wrong place to be
 * the one handler that gates on less than its siblings. The sibling bulk
 * api_v2_governance_documents_delete() already pairs `governance` with
 * `delete_documentation` the same way.
 *
 * THE COUNT AND THE WRITE ARE ONE COMPUTATION. The preview branch and the
 * commit branch share the parser, the resolver, the cap and the split, and
 * differ only in whether they return before delete_framework_controls_batch().
 * That is the whole reason this is one route with a `confirm` flag rather than
 * a /preview sibling: a separate preview endpoint would be a second copy of the
 * authorization gate, the resolution and the split -- four more places for the
 * confirmation to start disagreeing with what the delete does.
 *
 * AUDIT. delete_framework_controls_batch() already writes ONE audit_log entry
 * per control, against that control's own id -- which is what makes an
 * individual control's audit trail complete, and is not something the bulk path
 * should collapse into a single "1,535 controls deleted" row that no control's
 * trail would show. What the per-control entries cannot say is that they were
 * ONE action, so that is what is added here: a single operational log line
 * naming the actor, the population, the resolved count and the split. One
 * findable line beats 1,535 identical ones.
 *
 * IT GOES OUT AT `notice`, NOT `warning`. The pull is to reach for `warning`
 * because the operation is irreversible and consequential -- but the level
 * rubric grades by what the line MEANS to whoever is triaging, not by how much
 * the operation matters. This delete is authorized (both permission gates ran
 * above), successful and expected; `warning` is reserved for access denials and
 * unexpected-but-non-fatal conditions, i.e. things that might be wrong. A
 * significant, infrequent, deliberate operation is exactly the rubric's `notice`
 * band, alongside "backup completed" and "framework installed". Logging it as a
 * warning would bury real anomalies under routine administration.
 */
function api_v2_governance_controls_bulk_delete() {

    api_v2_check_permission("governance");
    api_v2_check_permission("delete_controls");

    $body = api_v2_controls_read_json_body();

    try {
        $req = parse_controls_bulk_delete_request($body);

        $control_ids = $req['control_ids'];

        // The escalated case: the client named a FILTER, not a list, because the
        // table pages and it only ever held one page. Resolved through the
        // controls table's own pipeline, so the set deleted is by construction
        // the set the bulk bar counted.
        if ($req['all_filtered']) {

            $control_ids = controls_table_filtered_control_ids($req['framework'], $req['filters']);

            // The SAME size rule the parser applied to an explicit list, applied
            // here because a filter's size is not knowable until it resolves.
            assert_controls_bulk_delete_size($control_ids, true);
        }

        // What deleting this set MEANS, computed from the same classification
        // the writer will use (framework_controls_delete_classification(), one
        // definition of the soft/hard rule). Computed on BOTH branches, not just
        // the preview -- the response always reports the split, so a client that
        // never showed a confirmation still cannot claim more than happened.
        $split = framework_controls_delete_split($control_ids);

    } catch (InvalidArgumentException $e) {

        // The message is composed from validated ids and fixed strings, never
        // from raw request text, so it is safe to hand back to the caller.
        api_v2_json_result(400, "BAD REQUEST: " . $e->getMessage(), ['error' => 'invalid_request']);
        return;
    }

    $data = [
        'framework'    => $req['framework'],
        'all_filtered' => $req['all_filtered'],
        // The RESOLVED count, never the size of anything the client sent.
        'resolved'     => $split['named'],
        // The controls that actually exist. `resolved` can exceed this when a
        // client holds a stale id; those are reported as `missing` rather than
        // counted into either half, because nothing is retained or removed for
        // them.
        'found'        => $split['found'],
        'soft_delete'  => $split['soft'],
        'hard_delete'  => $split['hard'],
        'missing'      => $split['missing'],
        'deleted'      => false,
    ];

    if (!$req['confirm']) {
        // PREVIEW. Nothing has been written. 200 rather than a 4xx: the request
        // was answered exactly as asked.
        api_v2_json_result(200, "SUCCESS", $data);
        return;
    }

    // The measured worst case is ~10 ms per control per matching
    // control.deleted workflow, on top of ~2.5 ms of delete; at the 2,000-row
    // cap that can exceed the default 30 s. The dispatch loop runs AFTER the
    // transaction commits, so timing out there would leave the delete done and
    // the caller told it failed. Matches the bounded-bulk-operation precedent in
    // includes/api.php and admin/settings_backups.php.
    set_time_limit(600);

    delete_framework_controls_batch($control_ids);

    $data['deleted'] = true;

    write_debug_log(sprintf(
        "BULK CONTROL DELETE: user '%s' (uid %d) deleted %d control(s) via %s -- %d retained with test history, %d permanently removed, %d already absent.",
        $_SESSION['user'] ?? '',
        (int)($_SESSION['uid'] ?? 0),
        $split['found'],
        $req['all_filtered'] ? 'a filtered selection' : 'an explicit selection',
        $split['soft'],
        $split['hard'],
        $split['missing']
    ), 'notice');

    api_v2_json_result(200, "SUCCESS", $data);
}

/****************************************************
 * FUNCTION: API V2 CONTROLS READ JSON BODY         *
 ****************************************************
 * Reads a JSON request body, falling back to $_POST for form-encoded clients.
 * Mirrors api_v2_applicability_read_json_body().
 */
function api_v2_controls_read_json_body(): array {

    $body = json_decode(file_get_contents('php://input'), true);

    if (is_array($body)) {
        return $body;
    }

    return $_POST ?: [];
}
