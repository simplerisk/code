<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Grid data helpers for the Define Tests redesign (§7 state pill, last-result
 * column). This file is intentionally small and focused: it holds only the
 * last-result derivation used by the Define Tests grid, not the full
 * compliance.php surface.
 *
 * NOTE: this file deliberately does NOT require_once includes/functions.php
 * at the top so that last_result_state_family() stays loadable standalone
 * (no DB dependency). get_tests_last_results() calls db_open()/db_close(),
 * which are only defined once functions.php is loaded — callers of this
 * file are expected to already have functions.php in their require chain
 * (as every other includes/*.php consumer of db_open() does).
 */

// ai_control_test_schedule_type() — the shared "a positive cadence IS an
// interval schedule" rule the suggestion-row builder consumes (and the apply
// path in ai_proposal_capabilities.php owns). Required once at file scope rather
// than per suggestion row; the no-require note above is specifically about the
// large functions.php, not this small helper.
require_once(realpath(__DIR__ . '/ai_proposal_capabilities.php'));

/******************************************************************
 * FUNCTION: LAST RESULT STATE FAMILY                              *
 * Pure mapping from a framework_control_test_results.test_result  *
 * value to the Bootstrap-style state family used to color the §7  *
 * last-result pill on the Define Tests grid. No DB access.        *
 ******************************************************************/
function last_result_state_family(?string $result): string
{
    return match ($result) {
        'Pass' => 'success',
        'Fail' => 'danger',
        'Inconclusive' => 'warning',
        default => 'neutral',
    };
}

/******************************************************************
 * FUNCTION: AUDIT HISTORY LINK PAGE                               *
 * Pure: which compliance page an audit should deep-link to from   *
 * the History modal (Define Tests grid).                          *
 *                                                                 *
 * "Truly closed" -- and therefore read-only -- requires BOTH a     *
 * closed status AND a settled approval_state. A status=closed      *
 * audit still sitting in approval_state='pending' (Phase 3b:       *
 * awaiting an approver's sign-off) is still editable, so it must   *
 * link to the editor, not the read-only past-audit view. Same rule *
 * as the recent-failures links in includes/reporting.php and the   *
 * Active/Past split in get_framework_control_test_audits() -- kept *
 * here as one pure function so the three can't drift.              *
 ******************************************************************/
function audit_history_link_page(?string $status, ?string $approval_state, ?string $closed_status): string
{
    $is_truly_closed = ((string) $status === (string) $closed_status)
        && in_array((string) ($approval_state ?: 'none'), ['none', 'approved'], true);

    return $is_truly_closed ? 'view_test.php' : 'testing.php';
}

/**
 * Pure: is a test "failing"? True iff its newest audit result maps to the
 * danger state family (Fail). Shared by the Define Tests grid's Failing
 * quick-filter (build_tests_grid(), below) and the Failing KPI tile count
 * (Task 3) so the two definitions can never diverge -- both must call this
 * function rather than re-deriving the danger/Fail check independently.
 */
function test_last_result_is_failing($state_family)
{
    return $state_family === 'danger';
}

/**
 * Pure: is a test "passing"? True iff its newest audit result maps to the
 * success state family (Pass). Shared by the Define Tests grid's Passing
 * quick-filter (build_tests_grid(), below) and the Passing KPI tile count
 * (Task 3) so the two definitions can never diverge -- both must call this
 * function rather than re-deriving the success/Pass check independently.
 */
function test_last_result_is_passing($state_family): bool
{
    return $state_family === 'success';
}

/**
 * Pure: is a test overdue for its next scheduled window? True iff it has a
 * real next_date that is strictly before today. (Grid-scope overdue — decoupled
 * from audit status / closed_audit_status; the row's schedule already
 * determined whether next_date is meaningful.)
 *
 * A MISSING next_date is not an overdue one, and "missing" has three spellings
 * here. `framework_control_tests`.`next_date` is DATE NOT NULL with no default,
 * so a test that has never been scheduled carries MySQL's zero date rather than
 * NULL or ''. '0000-00-00' is non-empty AND sorts before every real date, so it
 * satisfied both halves of the old `!empty($next_date) && $next_date < today`
 * check — every unscheduled test wore a loud Overdue badge, and the Overdue KPI
 * tile (count_overdue_tests(), below) counted them all. A schedule that states
 * no opinion must not have one invented for it.
 *
 * The guard lives HERE rather than at each of the four call sites so the grid
 * rows, the two KPI tiles and the quick-filter counts cannot drift, and so it
 * matches the semantics soa_test_is_overdue() (includes/soa.php) had already
 * committed to.
 *
 * strpos(..., '0000-00-00') === 0 rather than equality: it also catches the
 * DATETIME-precision '0000-00-00 00:00:00', which means the same thing.
 */
function is_test_overdue($next_date)
{
    $next_date = trim((string)($next_date ?? ''));

    if ($next_date === '' || strpos($next_date, '0000-00-00') === 0) {
        return false;
    }

    return $next_date < date('Y-m-d');
}

/**
 * Pure: is a test inside its audit-initiation lead-in window ("due soon")?
 * True iff it is NOT overdue, its schedule is automated (schedule_type
 * 'interval'/'calendar' -- a manual test has no audit_initiation_offset
 * concept), it has a next_date that is today or later, and today falls
 * on/after next_date minus the offset. Same threshold formula
 * get_tests_to_auto_initiate() (includes/compliance.php) uses, but not the
 * same window -- that function drops the future-date guard below for its
 * catch-up pass, so it can also fire on a next_date already in the past.
 *
 * Shared by the Define Tests grid's Due soon quick-filter (build_tests_grid(),
 * below) and the Due soon KPI tile count (count_due_soon_tests(), Task 3) so
 * the two definitions can never diverge -- both must call this function
 * rather than re-deriving the lead-in-window check independently.
 */
/******************************************************************
 * FUNCTION: TEST DAYS UNTIL                                        *
 * Whole days from today to $next_date: positive in the future,     *
 * 0 today, negative once it's past. Null when there's no date.     *
 *                                                                  *
 * Computed HERE, on the same clock as is_test_overdue() and        *
 * is_test_due_soon(), because the pill's words and the pill's      *
 * state have to come from one answer. The client used to derive    *
 * this itself from the browser's timezone while the server decided *
 * overdue/due_soon from its own: straddle midnight between the two *
 * and the server said "due soon" while the browser counted -1,     *
 * rendering "Due in -1 days" in the yellow Due Soon pill. Already- *
 * overdue tests looked fine, which is what made it read as a       *
 * transition-only glitch rather than two clocks disagreeing.       *
 *                                                                  *
 * $today is injectable so the behaviour is testable without        *
 * waiting for a particular date.                                   *
 ******************************************************************/
function test_days_until($next_date, ?string $today = null)
{
    if (empty($next_date) || $next_date === '0000-00-00') {
        return null;
    }

    $target = strtotime($next_date.' 00:00:00');
    $base = strtotime(($today ?: date('Y-m-d')).' 00:00:00');

    if ($target === false || $base === false) {
        return null;
    }

    // Whole days between two midnights: no DST half-days, no rounding drift.
    return (int) round(($target - $base) / 86400);
}

function is_test_due_soon($next_date, ?string $schedule_type, $audit_initiation_offset, bool $overdue): bool
{
    $today = date('Y-m-d');

    if ($overdue || !in_array($schedule_type, ['interval', 'calendar'], true) || empty($next_date) || $next_date < $today) {
        return false;
    }

    // Clamp a stray negative offset so it can't flip the "-N days"
    // arithmetic below into "+N days".
    $offset = max(0, (int) ($audit_initiation_offset ?? 0));
    $lead_in_start = date('Y-m-d', strtotime($next_date.' -'.$offset.' days'));

    return $lead_in_start <= $today;
}

/******************************************************************
 * FUNCTION: GET TESTS LAST RESULTS                                 *
 * Given a list of framework_control_tests.id values, returns the   *
 * last RECORDED result per test:                                   *
 *   [test_id => ['result' => ?string, 'date' => ?string,            *
 *                'summary' => ?string, 'result_id' => ?int,         *
 *                'audit_id' => ?int]]                                *
 *                                                                    *
 * The last three identify the record the verdict came from, and     *
 * exist because "which row IS the last recorded result" must have   *
 * exactly ONE definition in this codebase (the NOT EXISTS below).   *
 * The Statement of Applicability needs the result's own summary,    *
 * and needs to reach that result's linked risks                      *
 * (framework_control_test_results_to_risks.test_results_id) and its  *
 * audit's uploaded evidence (compliance_files, ref_type             *
 * 'test_audit'). Re-deriving "newest audit that actually recorded a *
 * result" a second time to get them is exactly the duplication this *
 * function was extracted to prevent, so it hands back the identity  *
 * along with the verdict. They are ADDITIVE -- the grid and the KPI *
 * tiles read 'result'/'date' and are unaffected.                    *
 *                                                                    *
 * "Last recorded" is the newest framework_control_test_audits row  *
 * that has a paired, non-empty framework_control_test_results       *
 * .test_result (INNER JOIN on results.test_audit_id = audits.id),  *
 * newest by created_at with the highest id breaking ties. An audit *
 * that is in flight -- initiated but not yet resulted -- is         *
 * deliberately skipped: it belongs to Next Due, not Last Result,   *
 * and counting it would report a test that passed last quarter as  *
 * "Not Tested" (and drop it from the Passing/Failing tiles this     *
 * function also feeds). 'result' is that row's test_result; 'date' *
 * is the result's own test_date (when the test was PERFORMED),     *
 * falling back to the audit's created_at when test_date is empty    *
 * or a zero date.                                                   *
 *                                                                    *
 * Every requested test_id is present in the returned array. A      *
 * test_id with no audits, or whose only audits are still in flight *
 * (no recorded result), comes back with every value null.          *
 ******************************************************************/
function get_tests_last_results(array $test_ids): array
{
    // Seed every requested id with the "no data" default so callers never
    // need an isset() check.
    $last_results = [];
    foreach ($test_ids as $test_id) {
        $last_results[(int) $test_id] = [
            'result'    => null,
            'date'      => null,
            'summary'   => null,
            'result_id' => null,
            'audit_id'  => null,
        ];
    }

    if (empty($test_ids)) {
        return $last_results;
    }

    // Open the database connection
    $db = db_open();

    // Build bound placeholders for the id list rather than interpolating it.
    $params = [];
    $placeholders = [];
    foreach (array_values($test_ids) as $i => $test_id) {
        $key = ":id{$i}";
        $placeholders[] = $key;
        $params[$key] = (int) $test_id;
    }
    $id_list = implode(',', $placeholders);

    // The newest audit that actually RECORDED a result -- not simply the newest
    // audit. Those differ the moment a new audit is initiated: the open one has
    // no result row yet, and taking it (LEFT JOIN, NULL result) reported a test
    // that passed last quarter as "Not Tested". That wasn't only a wrong cell --
    // this same function feeds the Passing/Failing tiles and the quick counts,
    // so initiating an audit silently removed the test from the program's
    // status counts until someone finished it.
    //
    // An in-progress audit belongs to Next Due, not to Last Result: the last
    // result is the last thing that was actually determined.
    //
    // INNER JOIN (not LEFT) is what enforces "has a result"; the NOT EXISTS then
    // picks the newest among those, using the same created_at/id tiebreak as
    // before so ordering is unchanged for tests without an open audit.
    $stmt = $db->prepare("
        SELECT
            audits.test_id,
            audits.id AS audit_id,
            results.id AS result_id,
            results.test_result,
            results.summary,
            results.test_date,
            audits.created_at
        FROM `framework_control_test_audits` audits
            INNER JOIN `framework_control_test_results` results
                ON results.test_audit_id = audits.id
                AND results.test_result IS NOT NULL
                AND results.test_result <> ''
        WHERE audits.test_id IN ({$id_list})
            AND NOT EXISTS (
                SELECT 1
                FROM `framework_control_test_audits` newer
                    INNER JOIN `framework_control_test_results` newer_results
                        ON newer_results.test_audit_id = newer.id
                        AND newer_results.test_result IS NOT NULL
                        AND newer_results.test_result <> ''
                WHERE newer.test_id = audits.test_id
                    AND (
                        newer.created_at > audits.created_at
                        OR (newer.created_at = audits.created_at AND newer.id > audits.id)
                    )
            )
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }

    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // 'date' is when the test was PERFORMED (the result's own test_date),
        // not when its audit row was created -- that is what the grid's Last
        // Test Date column means, and it keeps that column and Last Result
        // reading off the same record so they cannot disagree.
        $performed = (string) ($row['test_date'] ?? '');
        if ($performed === '' || strpos($performed, '0000-00-00') === 0) {
            $performed = $row['created_at'];
        }

        $last_results[(int) $row['test_id']] = [
            'result' => $row['test_result'],
            'date' => $performed,
            // The record the verdict came from. 'summary' is the analyst's
            // write-up of THAT result, not of any later in-flight audit.
            'summary' => $row['summary'],
            'result_id' => (int) $row['result_id'],
            'audit_id' => (int) $row['audit_id'],
        ];
    }

    // Close the database connection
    db_close($db);

    return $last_results;
}

/******************************************************************
 * FUNCTION: GET TEST AUDIT HISTORY                                 *
 * Every audit ever run for one test, newest first -- the Define    *
 * Tests grid's History modal.                                      *
 *                                                                  *
 * Deliberately unfiltered by status: the currently-open audit is    *
 * part of "how has this test performed" (it's the run in flight),   *
 * and audit_history_link_page() already routes an open audit to the *
 * editor rather than the read-only view. This is the only query in  *
 * the codebase that lists audits BY TEST --                         *
 * get_framework_control_test_audits() filters by control/framework/ *
 * result and splits Active vs Past, neither of which is a history.  *
 *                                                                  *
 * One row per audit: the results join is 1:1 in practice (one row   *
 * created per audit at initiation, includes/compliance.php), and a  *
 * duplicate there would duplicate a history line rather than        *
 * corrupt one, so no GROUP BY is imposed on the hot path.           *
 ******************************************************************/
function get_test_audit_history(int $test_id): array
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            audits.id,
            audits.status,
            audits.approval_state,
            audits.last_date,
            audits.created_at,
            results.test_result,
            results.test_date,
            tester.name AS tester_name,
            status_lookup.name AS status_name
        FROM `framework_control_test_audits` audits
            LEFT JOIN `framework_control_test_results` results ON results.test_audit_id = audits.id
            LEFT JOIN `user` tester ON tester.value = audits.tester
            LEFT JOIN `test_status` status_lookup ON status_lookup.value = audits.status
        WHERE audits.test_id = :test_id
        ORDER BY audits.created_at DESC, audits.id DESC
    ");
    $stmt->bindValue(':test_id', $test_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    $closed_status = get_setting('closed_audit_status');

    $history = [];
    foreach ($rows as $row) {
        // The date the run actually happened: the result's test_date when a
        // result was recorded, else the audit's own last_date, else the row's
        // creation stamp. A zero date ('0000-00-00') is not a date -- treat it
        // as absent and fall through, the same way the schedule code does.
        $date = '';
        foreach ([$row['test_date'], $row['last_date'], $row['created_at']] as $candidate) {
            if (!empty($candidate) && strpos((string) $candidate, '0000-00-00') !== 0) {
                $date = (string) $candidate;
                break;
            }
        }

        $history[] = [
            'audit_id' => (int) $row['id'],
            'date' => $date ? format_date($date) : '',
            'result' => $row['test_result'] ?: null,
            // Lets the modal reuse the grid's .sr-state-pill classes instead of
            // re-deriving Pass/Fail/Inconclusive colouring client-side.
            'result_family' => last_result_state_family($row['test_result']),
            'tester_name' => $row['tester_name'] ?: '',
            'status_name' => $row['status_name'] ?: '',
            'approval_state' => (string) ($row['approval_state'] ?: 'none'),
            'link' => '../compliance/'
                . audit_history_link_page($row['status'], $row['approval_state'], $closed_status)
                . '?id=' . (int) $row['id'],
        ];
    }

    return $history;
}

/******************************************************************
 * FUNCTION: BUILD GRID SEARCH                                      *
 * Pure helper: builds a parametrized SQL fragment for the Define   *
 * Tests grid's free-text search box. No DB access.                 *
 *                                                                    *
 * ALIAS CONTRACT (binding for Task 4's grid query):                *
 *   - `t`  = the framework_control_tests table (the tests grid)    *
 *   - `fc` = the framework_controls table (the test's control)     *
 * Task 4's query MUST alias these tables exactly `t` and `fc` for   *
 * the returned SQL fragment to bind correctly.                      *
 *                                                                    *
 * Matches (all case-insensitive substring, i.e. LIKE '%term%'):     *
 *   - t.name              — the test's own name                    *
 *   - fc.control_number   — the control's number                   *
 *   - fc.short_name       — the control's short name                *
 *   - fc.long_name        — the control's long/display name (added  *
 *     for Task 6: the client's search box is the only "find a       *
 *     control" affordance left once the legacy long-name filter     *
 *     text field was retired, so it must cover the same field)      *
 *   - framework_control_mappings.reference_name / .reference_text  *
 *     for any mapping row whose control_id = fc.id — so a user can  *
 *     find a test via any framework's mapped control ID/text, not   *
 *     just the SimpleRisk-native control identity.                  *
 *                                                                    *
 * Returns ['sql' => string, 'params' => array<string,string>]. An   *
 * empty/whitespace-only term returns ['sql' => '', 'params' => []]  *
 * so callers can safely concatenate/skip the fragment without a     *
 * conditional. Every placeholder name is distinct (:gsq0..:gsq5) so *
 * PDO binding is unambiguous; every param value is '%'.$term.'%'.   *
 ******************************************************************/
function build_grid_search(string $term): array
{
    if (trim($term) === '') {
        return ['sql' => '', 'params' => []];
    }

    $like = '%'.$term.'%';

    $params = [
        ':gsq0' => $like,
        ':gsq1' => $like,
        ':gsq2' => $like,
        ':gsq3' => $like,
        ':gsq4' => $like,
        ':gsq5' => $like,
        ':gsq6' => $like,
        ':gsq7' => $like,
    ];

    // Control owner is stored as a user id, so matching a typed NAME needs the
    // user table -- an EXISTS rather than a join so a control with no owner
    // (control_owner = 0) still matches on its other fields instead of being
    // dropped by an inner join.
    //
    // fc.description is a blob and is NOT one of the Encryption Extra's covered
    // fields (its catalog in extras/encryption/index.php lists assets, risks,
    // mitigations, comments and friends -- not framework_controls), so LIKE
    // matches real text here even on an encrypted instance.
    $sql = '(t.name LIKE :gsq0 OR fc.control_number LIKE :gsq1 OR fc.short_name LIKE :gsq2'
        .' OR fc.long_name LIKE :gsq5'
        .' OR fc.description LIKE :gsq7'
        .' OR EXISTS (SELECT 1 FROM user cu WHERE cu.value = fc.control_owner AND cu.name LIKE :gsq6)'
        .' OR EXISTS (SELECT 1 FROM framework_control_mappings m WHERE m.control_id = fc.id'
        .' AND (m.reference_name LIKE :gsq3 OR m.reference_text LIKE :gsq4)))';

    return ['sql' => $sql, 'params' => $params];
}

/******************************************************************
 * FUNCTION: PARSE GRID REQUEST                                     *
 * Pure normalizer for the Define Tests grid's POST body (Task 4).  *
 * No DB access -- unit-testable in isolation.                      *
 *                                                                    *
 * Normalizes:                                                       *
 *   - framework[] / family[]  -> arrays of positive ints (bad/empty *
 *     entries dropped)                                              *
 *   - search                  -> trimmed string, "" if absent       *
 *   - coverage                -> one of 'with'|'all'|'gaps',        *
 *     defaulting to 'all' for anything else                         *
 *   - schedule / tag          -> trimmed non-empty string or null   *
 *   - retired                 -> tri-state 'active'|'all'|          *
 *     'retired_only' (default 'active'); a legacy truthy            *
 *     quick.show_retired with no explicit `retired` maps to 'all'   *
 *   - quick{}                 -> every one of mine/overdue/failing/ *
 *     manual/untested/show_retired/due_soon coerced to bool,        *
 *     defaulting false when absent                                  *
 *   - start / length          -> non-negative ints; length may be   *
 *     -1 (meaning "all"); malformed values fall back to the         *
 *     defaults (start=0, length=10)                                 *
 ******************************************************************/
function parse_grid_request(array $body): array
{
    $to_int_list = static function ($raw): array {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $value) {
            $int = (int) $value;
            if ($int > 0) {
                $out[] = $int;
            }
        }
        return array_values($out);
    };

    $framework = $to_int_list($body['framework'] ?? []);
    $family = $to_int_list($body['family'] ?? []);

    $search = isset($body['search']) && is_scalar($body['search']) ? trim((string) $body['search']) : '';

    $coverage = $body['coverage'] ?? 'all';
    if (!in_array($coverage, ['with', 'all', 'gaps'], true)) {
        $coverage = 'all';
    }

    $schedule = isset($body['schedule']) && is_scalar($body['schedule']) ? trim((string) $body['schedule']) : '';
    if ($schedule === '' || !in_array($schedule, ['manual', 'interval', 'calendar'], true)) {
        $schedule = null;
    }

    $tag = isset($body['tag']) && is_scalar($body['tag']) ? trim((string) $body['tag']) : '';
    $tag = $tag === '' ? null : $tag;

    // Tester filter: a user id, or null for "All testers". Replaces the
    // self-only "My tests" quick chip in the UI -- quick.mine is still parsed
    // below for API back-compat.
    $tester = isset($body['tester']) && is_numeric($body['tester']) ? (int) $body['tester'] : 0;
    $tester = $tester > 0 ? $tester : null;

    // Retired/show mode: 'active' (default) | 'all' | 'retired_only', plus the
    // AI-suggested review mode 'ai_suggested' (fourth `show` option; suggestion
    // rows only). Anything else is coerced to null here and resolved (with
    // legacy quick.show_retired back-compat) once $quick is built below.
    $retired = in_array($body['retired'] ?? '', ['active', 'all', 'retired_only', 'ai_suggested'], true) ? (string) $body['retired'] : null;

    $quick_raw = is_array($body['quick'] ?? null) ? $body['quick'] : [];
    $quick = [
        'mine' => !empty($quick_raw['mine']),
        'overdue' => !empty($quick_raw['overdue']),
        'due_soon' => !empty($quick_raw['due_soon']),
        'failing' => !empty($quick_raw['failing']),
        'passing' => !empty($quick_raw['passing']),
        // The result dimension needs all four states to back an
        // "All results" filter -- passing/failing alone can't express the
        // Inconclusive and Not-tested rows the grid already renders.
        // 'scheduled' == on track: has a real schedule and is neither late nor
        // inside the lead-in window -- the same state the due pill labels
        // "Scheduled · <date>".
        'scheduled' => !empty($quick_raw['scheduled']),
        'inconclusive' => !empty($quick_raw['inconclusive']),
        'not_tested' => !empty($quick_raw['not_tested']),
        'manual' => !empty($quick_raw['manual']),
        'untested' => !empty($quick_raw['untested']),
        'show_retired' => !empty($quick_raw['show_retired']),
    ];

    // BACK-COMPAT: an absent `retired` mode with a truthy legacy
    // quick.show_retired means "show active + retired" ('all'); otherwise
    // default to active-only.
    if ($retired === null) {
        $retired = !empty($quick['show_retired']) ? 'all' : 'active';
    }

    $start = isset($body['start']) && is_numeric($body['start']) ? (int) $body['start'] : 0;
    if ($start < 0) {
        $start = 0;
    }

    $length = isset($body['length']) && is_numeric($body['length']) ? (int) $body['length'] : 10;
    if ($length !== -1 && $length < 1) {
        $length = 10;
    }

    // Sort column, allow-listed by NAME rather than passed through: these keys
    // map to fixed expressions in sort_tests_flat(), so nothing a caller sends
    // reaches an ORDER BY. An unknown key means "no sort", which is the grouped
    // view -- the grid's default.
    $sort_raw = isset($body['sort']) && is_scalar($body['sort']) ? trim((string) $body['sort']) : '';
    $sort = in_array($sort_raw, ['id', 'name', 'tester', 'schedule', 'last_date', 'last_result', 'next_due'], true) ? $sort_raw : '';
    $dir = (isset($body['dir']) && strtolower((string) $body['dir']) === 'desc') ? 'desc' : 'asc';

    return [
        'sort' => $sort,
        'dir' => $dir,
        'framework' => $framework,
        'family' => $family,
        'search' => $search,
        'coverage' => $coverage,
        'schedule' => $schedule,
        'tag' => $tag,
        'tester' => $tester,
        'retired' => $retired,
        'quick' => $quick,
        'start' => $start,
        'length' => $length,
    ];
}

/******************************************************************
 * FUNCTION: FORMAT TEST SCHEDULE SUMMARY                           *
 * Pure formatter: renders a short, translated human summary of a   *
 * test's schedule (manual / interval / calendar) for the Define    *
 * Tests grid. No DB access -- reads only the passed-in test row    *
 * and the already-loaded $lang array (falls back to English        *
 * literals if $lang isn't populated, e.g. under unit tests).       *
 ******************************************************************/
function format_test_schedule_summary(array $test): string
{
    global $lang;

    $type = $test['schedule_type'] ?? 'manual';

    if ($type === 'calendar') {
        $interval = (int) ($test['cadence_interval'] ?? 1);
        $unit = $test['cadence_unit'] ?? 'month';
        $unit_word = match ($unit) {
            'day' => $interval === 1 ? ($lang['Day'] ?? 'Day') : ($lang['days'] ?? 'days'),
            'week' => $interval === 1 ? ($lang['Week'] ?? 'Week') : ($lang['Weeks'] ?? 'Weeks'),
            'month' => $interval === 1 ? ($lang['Month'] ?? 'Month') : ($lang['Months'] ?? 'Months'),
            'year' => $interval === 1 ? ($lang['Year'] ?? 'Year') : ($lang['Years'] ?? 'Years'),
            default => $unit,
        };

        return format_every_n_units($interval, $unit_word);
    }

    if ($type === 'interval') {
        $frequency = (int) ($test['test_frequency'] ?? 0);
        $unit_word = $frequency === 1 ? ($lang['day'] ?? 'day') : ($lang['days'] ?? 'days');

        return format_every_n_units($frequency, $unit_word);
    }

    return $lang['ScheduleManual'] ?? 'Manual';
}

/******************************************************************
 * FUNCTION: FORMAT EVERY N UNITS (internal helper)                 *
 * Pure string composition for format_test_schedule_summary(). Kept *
 * separate so the {$interval}/{$unit} substitution has one place   *
 * to live regardless of whether $lang['ScheduleSummaryEvery'] is   *
 * populated (unit tests run without the language file loaded).     *
 ******************************************************************/
function format_every_n_units(int $interval, string $unit_word): string
{
    global $lang;

    $template = $lang['ScheduleSummaryEvery'] ?? 'Every {$interval} {$unit}';

    return str_replace(['{$interval}', '{$unit}'], [(string) $interval, $unit_word], $template);
}

/******************************************************************
 * FUNCTION: CONTROL ROSTER DESCRIPTION                             *
 * A control's description as one line of plain text, for the       *
 * picker's hover.                                                  *
 *                                                                  *
 * Descriptions are rich text. The picker shows this in a native    *
 * title tooltip, which renders text and nothing else -- so tags    *
 * are stripped and entities decoded HERE rather than shipping      *
 * markup the client would have to strip (or, worse, render).       *
 *                                                                  *
 * Capped because a tooltip is a glance, not a document, and        *
 * because this ships for every control in the catalogue: the SCF   *
 * import is ~1,500 of them. Whitespace is collapsed so a           *
 * description authored as paragraphs doesn't arrive as a tooltip    *
 * full of blank lines.                                             *
 ******************************************************************/
function control_roster_description($description): string
{
    if (!is_string($description) || $description === '') {
        return '';
    }

    // <br>/</p> become spaces first -- strip_tags() alone would run the last
    // word of one paragraph into the first word of the next.
    $text = preg_replace('/<(br\s*\/?|\/p|\/div|\/li)>/i', ' ', $description);
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));

    if ($text === '') {
        return '';
    }

    return mb_strlen($text) > 300 ? (mb_substr($text, 0, 300).'…') : $text;
}

/******************************************************************
 * FUNCTION: SHAPE CONTROL ROSTER                                   *
 * Pure shaper for GET /compliance/control_roster: turns the two    *
 * queries (control rows, and the control->framework mapping rows)  *
 * into the payload the picker consumes.                            *
 *                                                                  *
 * Pure and extracted so the parts that are easy to get wrong are   *
 * testable without a database: a control mapped to the same        *
 * framework more than once (one mapping row per REFERENCE, so this *
 * is the norm, not an edge case) must not report that framework    *
 * twice, and `frameworks` must serialise as a JSON array -- the    *
 * dedupe naturally produces an id-KEYED map, which json_encode     *
 * would emit as an object and the client indexes positionally.     *
 *                                                                  *
 * @param array $rows         id/control_number/short_name/family/description
 * @param array $mapping_rows control_id/framework pairs
 ******************************************************************/
function shape_control_roster(array $rows, array $mapping_rows): array
{
    $frameworks_by_control = [];
    foreach ($mapping_rows as $mapping) {
        $control_id = (int) ($mapping['control_id'] ?? 0);
        $framework_id = (int) ($mapping['framework'] ?? 0);
        if ($control_id <= 0 || $framework_id <= 0) {
            continue;
        }
        if (!isset($frameworks_by_control[$control_id])) {
            $frameworks_by_control[$control_id] = [];
        }
        $frameworks_by_control[$control_id][$framework_id] = true;
    }

    return array_map(static function ($row) use ($frameworks_by_control) {
        $id = (int) ($row['id'] ?? 0);

        return [
            'id' => $id,
            'control_number' => $row['control_number'] ?? '',
            'short_name' => $row['short_name'] ?? '',
            'description' => control_roster_description($row['description'] ?? ''),
            'family' => (int) ($row['family'] ?? 0),
            'frameworks' => isset($frameworks_by_control[$id])
                ? array_values(array_map('intval', array_keys($frameworks_by_control[$id])))
                : [],
        ];
    }, $rows);
}

/******************************************************************
 * FUNCTION: TESTS GRID PREDICATES (internal)                       *
 * Shared control/test "active" predicates used by build_tests_grid *
 * AND every count_*() band helper below (Task 3). Extracted to a   *
 * single place so a change to either predicate is written ONCE and *
 * both the grid and its KPI tiles inherit it identically.          *
 *                                                                    *
 * TEAM SEPARATION lives next door, in test_team_scope_predicate()  *
 * and the test_in_scope_predicate() that carries it -- NOT here.   *
 * These two predicates answer "is this row active", which is the   *
 * same question for every user; visibility is a different question *
 * with a different answer per user.                                *
 ******************************************************************/
function tests_grid_predicates(bool $show_retired = false): array
{
    return [
        'controls_where' => 'fc.deleted = 0',
        'retired_predicate' => $show_retired ? '1=1' : 't.retired_at IS NULL',
    ];
}

/******************************************************************
 * FUNCTION: TESTS GRID RETIRED PREDICATE (internal)               *
 * Maps the tri-state Define Tests "Show" filter mode to the SQL   *
 * retired_at predicate for a given table alias:                   *
 *   'active'        -> <alias>.retired_at IS NULL   (default)     *
 *   'all'           -> 1=1                                         *
 *   'retired_only'  -> <alias>.retired_at IS NOT NULL             *
 *   'ai_suggested'  -> 1=0  (no REAL tests; the grid emits only    *
 *                     AI suggestion rows in this mode)            *
 * Alias is a caller-controlled literal ('t' at candidate level,   *
 * 'ft' at test level) -- never request-derived -- so the returned *
 * fragment is safe to interpolate into the query. Unknown modes   *
 * fall back to active-only. This deliberately does NOT touch      *
 * tests_grid_predicates() above, which the count_*() band helpers *
 * call and which must stay active-only regardless of the grid's   *
 * Show select.                                                     *
 ******************************************************************/
function tests_grid_retired_predicate(string $mode, string $alias): string
{
    return match ($mode) {
        'all' => '1=1',
        'retired_only' => "{$alias}.retired_at IS NOT NULL",
        // Suggestion-only view: exclude EVERY real test so the fan returns
        // nothing; build_tests_grid() then appends the AI suggestion rows.
        'ai_suggested' => '1=0',
        default => "{$alias}.retired_at IS NULL",
    };
}

/******************************************************************
 * FUNCTION: BUILD AI SUGGESTION ROW (internal)                     *
 * Shapes ONE pending `control_test_generation` ai_proposals row    *
 * (as returned by get_ai_proposals('control', $id) -- payload      *
 * already JSON-decoded) into a grid `tests[]` row tagged           *
 * kind='suggestion'. Returns null for anything that is not a       *
 * pending, control-targeted control_test_generation proposal with  *
 * a usable name (fails closed so a malformed proposal never        *
 * renders).                                                        *
 *                                                                  *
 * The payload strings (name/objective/test_steps/expected_results) *
 * are AI/customer text -- they are passed through UNESCAPED here,  *
 * IDENTICALLY to how the sibling real-test rows carry ft.name /    *
 * tester_name (build_tests_grid() Step 3). Escaping happens once   *
 * at the client render sink, the same single-escape contract the   *
 * real rows follow.                                                *
 ******************************************************************/
/******************************************************************
 * FUNCTION: IS RENDERABLE CONTROL-TEST PROPOSAL (internal)         *
 * The single validity predicate shared by build_ai_suggestion_row()*
 * (which shapes a suggestion row), count_pending_control_test_     *
 * suggestions() (the toolbar count), and control_ids_with_pending_ *
 * test_suggestions() (candidate-control resolution). A proposal is *
 * renderable iff it is a pending, control-targeted                 *
 * control_test_generation proposal whose decoded payload carries a *
 * non-empty string `name`. Keeping the count, the candidate set,   *
 * and the rendered rows on ONE predicate is what guarantees        *
 * count == rendered rows (design §4) and stops an empty suggestion *
 * card: a malformed proposal is uniformly dropped everywhere.      *
 ******************************************************************/
function is_renderable_control_test_proposal(array $proposal): bool
{
    if (($proposal['status'] ?? '') !== 'pending') {
        return false;
    }
    if (($proposal['capability'] ?? '') !== 'control_test_generation') {
        return false;
    }
    if (($proposal['target_type'] ?? '') !== 'control') {
        return false;
    }

    $payload = $proposal['proposed_payload'] ?? null;
    if (!is_array($payload)) {
        return false;
    }

    $name = $payload['name'] ?? '';

    return is_string($name) && $name !== '';
}

function build_ai_suggestion_row(array $proposal): ?array
{
    // Fail closed on any proposal the shared validity predicate rejects, so a
    // malformed proposal never renders (and never inflates the count above the
    // rendered-row total -- the counter uses the same predicate).
    if (!is_renderable_control_test_proposal($proposal)) {
        return null;
    }

    $payload = $proposal['proposed_payload'];
    $name = $payload['name'];

    $string_field = static fn ($v): string => is_string($v) ? $v : '';

    $frequency = (isset($payload['test_frequency']) && is_numeric($payload['test_frequency']))
        ? (int) $payload['test_frequency']
        : null;

    // The one source of truth for "a positive cadence IS an interval schedule"
    // (ai_control_test_schedule_type, required at file scope), shared with the
    // apply path so the chip, the applied test, and the prefill can't drift.
    $schedule_type = ai_control_test_schedule_type($frequency);

    // A cadence in days renders exactly like an interval-scheduled test's
    // chip -- reuse the same formatter so the suggestion's schedule reads
    // identically to a real test's. No cadence => empty (manual-ish).
    $schedule_summary = ($schedule_type === 'interval')
        ? format_test_schedule_summary(['schedule_type' => 'interval', 'test_frequency' => $frequency])
        : '';

    return [
        'kind' => 'suggestion',
        'proposal_id' => (int) ($proposal['id'] ?? 0),
        'id' => null,
        'name' => $name,
        // objective/test_steps/expected_results/sample/required_evidence are
        // model-generated and may carry HTML (test_steps/required_evidence are
        // asked for as <ol>/<ul> lists). purify_html() before exposing them so
        // the one browser HTML sink they reach -- the Review & edit prefill's
        // hugerte setContent() -- receives already-sanitized markup (the grid
        // suggestion row itself shows only name + schedule, and the read-only
        // procedure strips every tag via htmlToLines()/.text(), so this is
        // defense-in-depth for the editor path). Apply-time add_framework_control_test()
        // purifies again on write.
        'objective' => purify_html($string_field($payload['objective'] ?? null)),
        'test_steps' => purify_html($string_field($payload['test_steps'] ?? null)),
        'expected_results' => purify_html($string_field($payload['expected_results'] ?? null)),
        // sample / required_evidence are optional in the payload (older proposals
        // predate them); default to '' so the Review & edit prefill can set them
        // unconditionally.
        'sample' => purify_html($string_field($payload['sample'] ?? null)),
        'required_evidence' => purify_html($string_field($payload['required_evidence'] ?? null)),
        'test_frequency' => $frequency,
        // The prefill consumes this instead of re-deriving the interval rule.
        'schedule_type' => $schedule_type,
        'schedule_summary' => $schedule_summary,
        'control_count' => 1,
        'tester_name' => null,
        'retired' => false,
    ];
}

/******************************************************************
 * FUNCTION: RENDERABLE CONTROL-TEST PROPOSALS (internal)           *
 * Every pending `control_test_generation` proposal that would      *
 * actually render as a suggestion row -- i.e. those                *
 * is_renderable_control_test_proposal() accepts. The candidate     *
 * resolver and the toolbar counter both derive from THIS, so the   *
 * count, the candidate control set, and the rows build_tests_grid  *
 * emits can never disagree (a malformed proposal is dropped by the *
 * same predicate the row builder uses). Payload is JSON-decoded    *
 * exactly as get_ai_proposals() decodes it, so the shared          *
 * predicate sees the same shape at count time and at render time.  *
 * Optionally scoped to a control-id list ($control_ids) via a     *
 * bound IN (...) list -- the same batching pattern                 *
 * get_tests_control_counts() uses. build_tests_grid() passes its   *
 * build set so it can pull EVERY control's pending suggestions in  *
 * ONE query instead of one get_ai_proposals() call per control (an *
 * N+1 storm when a test-level filter widens the build set to the   *
 * full candidate catalog). An empty list short-circuits; null      *
 * (the default) fetches every control -- what the                  *
 * count_pending_control_test_suggestions() and candidate-resolver  *
 * callers rely on. Ordered ap.id DESC so a per-control grouping of  *
 * the result matches get_ai_proposals()'s newest-first ordering    *
 * (the count/candidate callers are order-independent).             *
 *                                                                  *
 * @param int[]|null $control_ids restrict to these controls, or    *
 *                                null for every control.           *
 * @return array<int, array> renderable proposal rows (payload      *
 *                           decoded to an array).                  *
 ******************************************************************/
function renderable_control_test_proposals(?array $control_ids = null): array
{
    $scope_sql = '';
    $params = [];
    if ($control_ids !== null) {
        $control_ids = array_values(array_unique(array_map('intval', $control_ids)));
        if (!$control_ids) {
            return [];
        }
        $placeholders = [];
        foreach ($control_ids as $i => $cid) {
            $key = ":rctp{$i}";
            $placeholders[] = $key;
            $params[$key] = $cid;
        }
        $scope_sql = ' AND ap.target_id IN ('.implode(',', $placeholders).')';
    }

    $db = db_open();
    $stmt = $db->prepare("
        SELECT *
        FROM `ai_proposals` ap
        WHERE ap.capability = 'control_test_generation'
            AND ap.target_type = 'control'
            AND ap.status = 'pending'{$scope_sql}
        ORDER BY ap.id DESC
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $renderable = [];
    foreach ($rows as $row) {
        $row['proposed_payload'] = json_decode($row['proposed_payload'] ?? 'null', true);
        if (is_renderable_control_test_proposal($row)) {
            $renderable[] = $row;
        }
    }

    return $renderable;
}

/******************************************************************
 * FUNCTION: CONTROL IDS WITH PENDING CONTROL-TEST SUGGESTIONS      *
 * The DISTINCT framework_controls ids that carry at least one      *
 * RENDERABLE pending `control_test_generation` proposal. Used by  *
 * resolve_candidate_control_ids() to keep a test-less control in   *
 * the grid when it only has suggestions (ai_suggested mode). A     *
 * control whose ONLY proposal is malformed is excluded here, so    *
 * ai_suggested mode never surfaces an empty suggestion card.       *
 *                                                                  *
 * @return int[]                                                    *
 ******************************************************************/
function control_ids_with_pending_test_suggestions(): array
{
    $ids = [];
    foreach (renderable_control_test_proposals() as $proposal) {
        $ids[(int) ($proposal['target_id'] ?? 0)] = true;
    }

    return array_keys($ids);
}

/******************************************************************
 * FUNCTION: COUNT PENDING CONTROL-TEST SUGGESTIONS                 *
 * The number of pending `control_test_generation` proposals that  *
 * would actually RENDER -- the ai_suggested `show` filter count.  *
 * Counts only renderable proposals (same validity predicate the   *
 * row builder uses) so the count matches the rows the grid emits. *
 * Gated by the caller on                                          *
 * ai_capability_enabled('control_test_generation').               *
 ******************************************************************/
function count_pending_control_test_suggestions(): int
{
    return count(renderable_control_test_proposals());
}

/******************************************************************
 * FUNCTION: TEST CONTROL PAIRS SQL (internal)                       *
 * Phase 4a common tests: a common test maps to N controls via the   *
 * `test_control_map` junction (Task 1/2). Two raw-SQL writers --    *
 * workflow_action_create_test_task() (includes/workflows/actions/   *
 * governance.php) and update_control_test_from_import() (import-    *
 * export) -- INSERT/UPDATE `framework_control_tests`.               *
 * `framework_control_id` directly and never write a `test_control_  *
 * map` row. Task 1's backfill only covered rows that existed at     *
 * migration time, so a test created by those paths AFTER the        *
 * migration has a valid scalar `framework_control_id` but ZERO map  *
 * rows. If the grid/coverage queries below read `test_control_map`  *
 * alone, such a test would be invisible in the grid and uncounted   *
 * in coverage -- a silent regression.                               *
 *                                                                    *
 * This helper returns a derived-table SQL fragment yielding one row *
 * per (test_id, framework_control_id) pair a test covers: every     *
 * `test_control_map` row, UNION the scalar `framework_control_id`   *
 * for any test that has NO map row (fallback-inclusive). It has NO  *
 * bound parameters -- it is pure SQL, safe to inline verbatim as a  *
 * subquery. Factored into ONE helper so resolve_candidate_control_  *
 * ids() and build_tests_grid() Step-2b use the IDENTICAL fragment   *
 * and can never drift (the Phase-2 count-agreement rule).           *
 *                                                                    *
 * Do NOT modify the two raw-SQL writers to close this gap -- this   *
 * fallback covers them (and any future raw writer) defensively;     *
 * rerouting those writers to also write the map is a logged         *
 * Phase-4b follow-up.                                                *
 ******************************************************************/
function test_control_pairs_sql(): string
{
    return "(
        SELECT `test_id`, `framework_control_id` FROM `test_control_map`
        UNION
        SELECT ft2.`id` AS `test_id`, ft2.`framework_control_id`
        FROM `framework_control_tests` ft2
            LEFT JOIN `test_control_map` tcm2 ON tcm2.`test_id` = ft2.`id`
        WHERE tcm2.`test_id` IS NULL
            AND ft2.`framework_control_id` IS NOT NULL
            AND ft2.`framework_control_id` <> 0
    )";
}

/******************************************************************
 * FUNCTION: GET TESTS CONTROL COUNTS                                *
 * Returns [test_id => number of DISTINCT non-deleted controls the   *
 * test maps to] for the given test ids. Drives the grid's           *
 * "common test" badge (a test applied to more than one control).    *
 *                                                                    *
 * Counts over test_control_pairs_sql() -- the junction UNION the     *
 * scalar fallback -- so a test written by a raw writer that never    *
 * populated `test_control_map` still reports its single control      *
 * instead of zero.                                                   *
 *                                                                    *
 * Excludes soft-deleted controls so this agrees with                 *
 * count_common_tests(), which defines "common" as >1 non-deleted     *
 * mapped control -- the band's common tile and the per-row badge     *
 * must never disagree about which tests are common.                  *
 *                                                                    *
 * The count is deliberately the test's FULL breadth, independent of  *
 * the caller's active filters: "applied to 4 controls" is a property *
 * of the test, not of the current view.                              *
 ******************************************************************/
function get_tests_control_counts(array $test_ids): array
{
    $test_ids = array_values(array_unique(array_map('intval', $test_ids)));
    if (!$test_ids) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($test_ids as $index => $test_id) {
        $key = ':tcc'.$index;
        $placeholders[] = $key;
        $params[$key] = $test_id;
    }
    $in_list = implode(',', $placeholders);
    $pairs_sql = test_control_pairs_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT p.`test_id`, COUNT(DISTINCT p.`framework_control_id`) AS control_count
        FROM {$pairs_sql} p
            JOIN `framework_controls` fc ON fc.`id` = p.`framework_control_id` AND fc.`deleted` = 0
        WHERE p.`test_id` IN ({$in_list})
        GROUP BY p.`test_id`
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $counts = [];
    foreach ($rows as $row) {
        $counts[(int) $row['test_id']] = (int) $row['control_count'];
    }

    return $counts;
}

/******************************************************************
 * FUNCTION: RESOLVE CANDIDATE CONTROL IDS (internal)                *
 * Step 1 of build_tests_grid(): resolves the ordered list of        *
 * `framework_controls`.id candidates for a normalized filter set    *
 * (the parse_grid_request() shape). Reads framework/family/search/  *
 * coverage, quick.untested, and the retired filter mode; ignores every     *
 * test-level quick filter (mine/failing/overdue/due_soon/manual)    *
 * and schedule/tag, which apply only after enrichment inside        *
 * build_tests_grid() itself (see the "TEST-LEVEL quick filter"      *
 * comment there).                                                    *
 *                                                                    *
 * Shared verbatim by build_tests_grid() (Step 1) and                *
 * count_controls_with_tests() / count_coverage_gap_controls()       *
 * below, so "which controls match" can never drift between the     *
 * grid's coverage='with'|'gaps' filter and its KPI tiles -- both    *
 * call this function rather than re-deriving the WHERE/HAVING.      *
 *                                                                    *
 * @return int[] candidate framework_controls ids, grid-ordered      *
 ******************************************************************/
function resolve_candidate_control_ids(array $filters): array
{
    // Belt-and-suspenders reachability (CLAUDE.md): declare the AI helpers this
    // function's ai_suggested branch depends on, even though the api/v2 grid
    // request already loads them transitively.
    require_once __DIR__ . '/artificial_intelligence.php';
    require_once __DIR__ . '/ai_proposals.php';

    $retired_mode = $filters['retired'] ?? 'active';
    $predicates = tests_grid_predicates(false);
    $retired_predicate = tests_grid_retired_predicate($retired_mode, 't');

    // ai_suggested (suggestion-only) view: candidates are the controls that
    // carry a pending control_test_generation proposal AND match the other
    // (framework/family/search) filters. Their real tests are excluded by the
    // '1=0' retired predicate above, so without this restriction coverage='all'
    // would return the whole catalog as empty groups. Server-side gate: when
    // the capability is off there are NO suggestions, so the mode resolves to
    // an empty candidate set (the grid stays byte-identical to today).
    $suggestion_only = ($retired_mode === 'ai_suggested');
    if ($suggestion_only && !ai_capability_enabled('control_test_generation')) {
        return [];
    }
    // Coverage ('with' / 'gaps', and the Untested Controls tile that reads it)
    // counts the tests THIS USER can see: a control whose only tests are
    // invisible to them is a coverage gap from where they sit, and counting it
    // as covered would both contradict the grid below and disclose that tests
    // they can't see exist.
    $test_team_scope = test_team_scope_predicate('t');

    $params = [];
    $where = [$predicates['controls_where']];

    if (!empty($filters['family'])) {
        $placeholders = [];
        foreach (array_values($filters['family']) as $i => $family_id) {
            $key = ":fam{$i}";
            $placeholders[] = $key;
            $params[$key] = (int) $family_id;
        }
        $where[] = 'fc.family IN ('.implode(',', $placeholders).')';
    }

    if (!empty($filters['framework'])) {
        $placeholders = [];
        foreach (array_values($filters['framework']) as $i => $framework_id) {
            $key = ":fwk{$i}";
            $placeholders[] = $key;
            $params[$key] = (int) $framework_id;
        }
        $where[] = 'EXISTS (SELECT 1 FROM `framework_control_mappings` fcmf'
            .' WHERE fcmf.control_id = fc.id AND fcmf.framework IN ('.implode(',', $placeholders).'))';
    }

    $search = build_grid_search($filters['search'] ?? '');
    if ($search['sql'] !== '') {
        $where[] = $search['sql'];
        $params = array_merge($params, $search['params']);
    }

    // ai_suggested mode: narrow to the controls that actually have a pending
    // suggestion. In-clause of an int list built from a trusted subquery (no
    // user input) -- inline as literals via an int-mapped list.
    if ($suggestion_only) {
        $suggestion_control_ids = control_ids_with_pending_test_suggestions();
        if (empty($suggestion_control_ids)) {
            return [];
        }
        $int_ids = implode(',', array_map('intval', $suggestion_control_ids));
        $where[] = "fc.id IN ({$int_ids})";
    }

    $where_sql = implode(' AND ', $where);

    $coverage = $filters['coverage'] ?? 'all';
    $untested = !empty($filters['quick']['untested']);
    $having = '';
    // Coverage counts REAL tests, which the '1=0' predicate hides in
    // ai_suggested mode -- applying a coverage HAVING there would wrongly drop
    // every suggestion-only control. The mode is suggestion-scoped, not
    // coverage-scoped, so skip the HAVING entirely.
    if ($suggestion_only) {
        $having = '';
    } elseif ($coverage === 'gaps' || $untested) {
        $having = 'HAVING COUNT(t.id) = 0';
    } elseif ($coverage === 'with') {
        $having = 'HAVING COUNT(t.id) > 0';
    }

    // Fallback-inclusive (test_id, framework_control_id) pair source -- see
    // test_control_pairs_sql() docblock above. Shared verbatim with
    // build_tests_grid() Step-2b so coverage here and the grid's fanned
    // test rows can never disagree on which controls a test covers.
    $pairs_sql = test_control_pairs_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT fc.id
        FROM `framework_controls` fc
            LEFT JOIN {$pairs_sql} tcm ON tcm.framework_control_id = fc.id
            LEFT JOIN `framework_control_tests` t ON t.id = tcm.test_id AND ({$retired_predicate})
                AND ({$test_team_scope})
        WHERE {$where_sql}
        GROUP BY fc.id
        {$having}
        ORDER BY fc.control_number, fc.id
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $candidate_ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    db_close($db);

    return $candidate_ids;
}

/******************************************************************
 * FUNCTION: BUILD TESTS GRID                                       *
 * DB-backed orchestrator behind POST /api/v2/compliance/tests_grid *
 * (Phase 1, Task 4). Takes an already-normalized filter array (the *
 * output of parse_grid_request()) and returns:                     *
 *   ['controls' => [...page of control cards...],                  *
 *    'recordsTotal' => int,    // all non-deleted controls          *
 *    'recordsFiltered' => int] // controls matching every filter,   *
 *                               // before pagination                *
 *                                                                    *
 * Query shape:                                                      *
 *   1. A candidate-id query joins `framework_controls` fc to        *
 *      `framework_control_tests` t (aliases bind build_grid_search()'s*
 *      fragment) to resolve framework/family/search/coverage        *
 *      filtering down to a DISTINCT, ordered list of control ids.   *
 *      GROUP BY fc.id + COUNT(t.id) in the HAVING clause implements *
 *      coverage='gaps'|'with' (t is joined ON retired_at IS NULL    *
 *      unless the retired filter mode is 'all'/'retired_only', so    *
 *      the count only ever reflects active tests).                  *
 *   2. Whether a TEST-LEVEL quick/schedule/tag filter is active     *
 *      (quick.mine/failing/overdue/due_soon/manual, `schedule`,     *
 *      `tag`) decides which of two paths runs from here:            *
 *        - HOT PATH (no test-level filter): the Step 1 candidate    *
 *          list already IS the final filtered set -- nothing        *
 *          downstream can remove a control from it. recordsFiltered *
 *          is set from its count, the id list is paginated          *
 *          (start/length) immediately, and only the page's controls *
 *          get enriched below -- this avoids full-catalog            *
 *          enrichment (last-result subqueries, schedule/overdue/    *
 *          due_soon math, tag GROUP_CONCAT) on every page load.     *
 *        - FILTERED PATH (a test-level filter is active): unchanged *
 *          -- which controls survive can't be known until every     *
 *          candidate is enriched and PHP-filtered, so this path     *
 *          enriches the whole candidate set and paginates last      *
 *          (Step 4), exactly as before.                              *
 *      Two follow-up queries (scoped to just the relevant ids, no   *
 *      search predicate) fetch the full control rows and the full   *
 *      test rows -- kept separate from step 1, in BOTH paths, so    *
 *      the search predicate's row-elimination on the LEFT JOIN      *
 *      can't corrupt aggregates like framework_count/test_count (a  *
 *      classic LEFT JOIN + WHERE gotcha: filtering the joined       *
 *      table's columns in WHERE turns it into an inner join for     *
 *      that row, silently dropping sibling rows needed for a        *
 *      correct COUNT/GROUP_CONCAT).                                 *
 *   3. Each test is enriched with last_result / last_result_family  *
 *      via get_tests_last_results() (Task 2), an overdue flag       *
 *      via is_test_overdue() (this file, pure), and a due_soon      *
 *      flag (automated tests only, not overdue, today inside the    *
 *      [next_date - audit_initiation_offset, next_date] lead-in     *
 *      window). That threshold -- next_date minus offset <= today   *
 *      -- is the same THRESHOLD FORMULA get_tests_to_auto_initiate()*
 *      uses (includes/compliance.php), but not the same window      *
 *      (that function drops the future-date guard for its catch-up  *
 *      pass); quick.mine/failing/overdue/due_soon/manual, `schedule`,*
 *      and `tag` are then applied in PHP as per-test filters        *
 *      (filtered path only) that trim each control's tests[] -- a   *
 *      control drops out of the result entirely if a test-level     *
 *      filter is active and none of its tests match.                *
 *   4. Pagination (start/length): already applied to the id list on *
 *      the hot path (see Step 2); applied to the final control list *
 *      here on the filtered path.                                   *
 ******************************************************************/
function build_tests_grid(array $filters): array
{
    // Belt-and-suspenders reachability (CLAUDE.md): the suggestion synthesis
    // below calls ai_capability_enabled() (artificial_intelligence.php) and
    // operates on the ai_proposals table (whose helpers live in
    // ai_proposals.php); declare their defining files directly even though the
    // api/v2 grid request loads them transitively. require_once makes a
    // duplicate include a no-op.
    require_once __DIR__ . '/artificial_intelligence.php';
    require_once __DIR__ . '/ai_proposals.php';

    $retired_mode = $filters['retired'] ?? 'active';

    // The single AI gate (§3): every suggestion surface -- rows AND the
    // ai_suggested count -- is behind ai_capability_enabled(). When off, the
    // grid is byte-identical to today. Suggestions are emitted only in the
    // ai_suggested (suggestions-only) and all (interleaved) modes.
    $ai_gen_enabled = ai_capability_enabled('control_test_generation');
    $emit_suggestions = $ai_gen_enabled && in_array($retired_mode, ['ai_suggested', 'all'], true);

    // ---- recordsTotal: all non-deleted controls, ignoring every filter ----
    $db = db_open();
    $stmt = $db->prepare('SELECT COUNT(*) FROM `framework_controls` fc WHERE fc.deleted = 0');
    $stmt->execute();
    $records_total = (int) $stmt->fetchColumn();
    db_close($db);

    // ---- Step 1: candidate control ids. Extracted to resolve_candidate_control_ids()
    // (above) so count_controls_with_tests() / count_coverage_gap_controls() (Task 3)
    // share this exact WHERE/HAVING query and can never disagree with the grid on
    // which controls match a given coverage/framework/family/search filter set. ----
    $candidate_ids = resolve_candidate_control_ids($filters);

    if (empty($candidate_ids)) {
        return ['controls' => [], 'recordsTotal' => $records_total, 'recordsFiltered' => 0, 'total_tests' => count_active_tests(), 'overdue_tests' => count_overdue_tests(), 'quick_counts' => get_define_tests_quick_counts(), 'tester_options' => get_define_tests_tester_options(), 'filter_counts' => get_define_tests_filter_counts()];
    }

    // ---- Determine whether a TEST-LEVEL quick/schedule/tag filter is
    // active. These filters run in PHP *after* enrichment (Step 3) because
    // whether a test matches depends on enriched fields (last_result,
    // overdue, due_soon) that don't exist until Step 3 runs -- so when one
    // is active, which CONTROLS survive can't be known until every
    // candidate is enriched. coverage/framework/family/search and
    // quick.untested are candidate-level (already folded into Step 1's SQL
    // via WHERE/HAVING) and never require this branch; the retired filter
    // mode is also candidate/join-level (it drives the retired_at predicate
    // used to build the candidate/join set -- it never removes a candidate
    // control), so it doesn't require this branch either. ----
    $quick = $filters['quick'] ?? [];
    $schedule_filter = $filters['schedule'] ?? null;
    $tag_filter = $filters['tag'] ?? null;
    $current_uid = (int) ($_SESSION['uid'] ?? 0);

    $tester_filter = $filters['tester'] ?? null;

    // A sort orders TESTS across the whole result set, so like any test-level
    // filter it has to see every candidate's tests before it can decide what
    // lands on page one. Forcing the filtered path here is what stops the hot
    // path from paginating controls out from under it.
    $sort = (string) ($filters['sort'] ?? '');

    $test_level_filter_active = !empty($quick['mine']) || !empty($quick['failing'])
        || !empty($quick['passing']) || !empty($quick['inconclusive']) || !empty($quick['not_tested'])
        || !empty($quick['overdue']) || !empty($quick['due_soon']) || !empty($quick['scheduled']) || !empty($quick['manual'])
        || $schedule_filter !== null || $tag_filter !== null || $tester_filter !== null
        || $sort !== '';

    // Seeded here, not just inside the branches. Both paths do assign it -- the
    // hot path below, the filtered path after its PHP filtering in Step 4 -- but
    // they're two separate conditionals on the same flag, which static analysis
    // can't correlate (PhanPossiblyUndeclaredVariable). The pre-filter candidate
    // count is also the honest default: it's what the filtered path starts from
    // before Step 4 narrows it.
    $records_filtered = count($candidate_ids);

    if ($test_level_filter_active) {
        // ---- Filtered path: enrich every candidate, apply the PHP
        // filters, then paginate the survivors (Step 4) -- unchanged. ----
        $ids_to_build = $candidate_ids;
        $enrich_ids = $candidate_ids;
    } else {
        // ---- Hot path (B'): no test-level filter means the Step 1
        // candidate list IS the final filtered set -- nothing downstream
        // can remove a control from it. Paginate the id list now and only
        // enrich the page's controls, instead of the whole catalog. ----
        $records_filtered = count($candidate_ids);

        $start = max(0, (int) ($filters['start'] ?? 0));
        $length = (int) ($filters['length'] ?? 10);
        $page_ids = ($length === -1)
            ? array_slice($candidate_ids, $start)
            : array_slice($candidate_ids, $start, max(0, $length));

        if (empty($page_ids)) {
            db_close($db);

            return ['controls' => [], 'recordsTotal' => $records_total, 'recordsFiltered' => $records_filtered, 'total_tests' => count_active_tests(), 'overdue_tests' => count_overdue_tests(), 'quick_counts' => get_define_tests_quick_counts(), 'tester_options' => get_define_tests_tester_options(), 'filter_counts' => get_define_tests_filter_counts()];
        }

        $ids_to_build = $page_ids;
        $enrich_ids = $page_ids;
    }

    $id_placeholders = [];
    $id_params = [];
    foreach ($enrich_ids as $i => $id) {
        $key = ":cid{$i}";
        $id_placeholders[] = $key;
        $id_params[$key] = $id;
    }
    $id_list_sql = implode(',', $id_placeholders);

    // ---- Step 2a: full control rows for the enrich ids (search-independent) ----
    $stmt = $db->prepare("
        SELECT
            fc.id, fc.control_number, fc.short_name, fc.long_name, owner.name AS owner_name,
            (SELECT COUNT(DISTINCT fcm2.framework)
                FROM `framework_control_mappings` fcm2
                    INNER JOIN `frameworks` fw2 ON fw2.value = fcm2.framework AND fw2.status = 1
                WHERE fcm2.control_id = fc.id) AS framework_count
        FROM `framework_controls` fc
            LEFT JOIN `user` owner ON fc.control_owner = owner.value
        WHERE fc.id IN ({$id_list_sql})
    ");
    foreach ($id_params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    $controls_by_id = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $controls_by_id[(int) $row['id']] = $row;
    }

    // ---- Step 2b: full test rows for the enrich ids (search-independent).
    // Joined through test_control_pairs_sql() (fallback-inclusive
    // test_control_map, Phase 4a) rather than ft.framework_control_id
    // directly, so a common test mapped to multiple candidate controls
    // yields one row PER mapped control (fanning it into every bucket
    // below) while a test with no map row still surfaces once, under its
    // scalar framework_control_id (the raw-writer fallback -- see
    // test_control_pairs_sql() docblock). ----
    $test_retired_predicate = tests_grid_retired_predicate($retired_mode, 'ft');
    // Team Separation: the rows a user may see. Restores what the pre-redesign
    // grid did per row with is_user_allowed_to_access() -- see
    // test_team_scope_predicate(). Applied here AND on the candidate-id query's
    // test join above, so a control whose only tests are invisible is counted
    // as having none rather than rendering as an empty group.
    $test_team_scope = test_team_scope_predicate('ft');
    $pairs_sql = test_control_pairs_sql();
    $stmt = $db->prepare("
        SELECT
            ft.id, tcm.framework_control_id, ft.name, ft.tester, tu.name AS tester_name,
            ft.schedule_type, ft.cadence_unit, ft.cadence_interval, ft.test_frequency,
            ft.next_date, ft.last_date, ft.approximate_time, ft.retired_at, ft.audit_initiation_offset,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag SEPARATOR '|') AS tags
        FROM `framework_control_tests` ft
            JOIN {$pairs_sql} tcm ON tcm.test_id = ft.id
            LEFT JOIN `user` tu ON ft.tester = tu.value
            LEFT JOIN `tags_taggees` tt ON tt.taggee_id = ft.id AND tt.type = 'test'
            LEFT JOIN `tags` tg ON tg.id = tt.tag_id
        WHERE tcm.framework_control_id IN ({$id_list_sql}) AND ({$test_retired_predicate}) AND ({$test_team_scope})
        GROUP BY ft.id, tcm.framework_control_id
        ORDER BY ft.name
    ");
    foreach ($id_params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $test_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    // ---- Step 3: enrich (last result via Task 2's helper, overdue, schedule summary) ----
    // A common test fanned into K controls (Step 2b) appears K times in
    // $test_rows -- array_unique() collapses it back to one id before the
    // last-result lookup so get_tests_last_results() doesn't do (and bind)
    // redundant work; the array stays keyed by test id there regardless, so
    // this is a performance dedupe, not a correctness fix.
    $test_ids = array_values(array_unique(array_map(static fn ($row) => (int) $row['id'], $test_rows)));
    $last_results = get_tests_last_results($test_ids);
    // Full (filter-independent) control breadth per test -- drives the
    // "common test" badge. One batched lookup for the whole page of tests,
    // same shape as get_tests_last_results() above.
    $control_counts = get_tests_control_counts($test_ids);

    $tests_by_control = [];
    foreach ($test_rows as $row) {
        $test_id = (int) $row['id'];
        $control_id = (int) $row['framework_control_id'];

        // Grid-scope overdue: is_test_overdue() (this file) is a pure
        // next_date-vs-today comparison, deliberately decoupled from
        // test_audit_is_overdue() (functions.php), which is shaped for an
        // *audit* row (next_date + a workflow status compared against the
        // closed_audit_status setting). There's no live audit in scope at
        // grid level, so the grid doesn't need that audit-shaped helper or
        // its closed_audit_status assumption.
        $overdue = is_test_overdue($row['next_date']);

        $last = $last_results[$test_id] ?? ['result' => null, 'date' => null];

        // due_soon: the audit-initiation LEAD-IN window is open. Delegated to
        // is_test_due_soon() (this file, pure -- see its docblock above) so
        // the grid's quick.due_soon filter and count_due_soon_tests() (Task 3)
        // share one definition and can't drift.
        $schedule_type = $row['schedule_type'] ?? 'manual';
        $due_soon = is_test_due_soon($row['next_date'], $schedule_type, $row['audit_initiation_offset'] ?? 0, $overdue);

        $tests_by_control[$control_id][] = [
            'id' => $test_id,
            'name' => $row['name'],
            'tester_name' => $row['tester_name'],
            // Public schedule_type -- the Define Tests grid (Task 6) needs this to
            // pick the "Manual" state on the next-due pill (a manual test still
            // carries a next_date, so overdue/due-soon math alone can't tell the
            // two apart). Distinct from the internal-only '_schedule_type' below,
            // which is stripped before the response is returned.
            'schedule_type' => $row['schedule_type'] ?? 'manual',
            'schedule_summary' => format_test_schedule_summary($row),
            'next_date' => $row['next_date'],
            // When the test was last PERFORMED. Prefer the date on the last
            // recorded result, so this column and Last Result read off the same
            // record and cannot contradict each other. The definition's own
            // last_date is the fallback: it is the INTERVAL schedule's anchor
            // and is user-editable, so on a migrated instance it may be the only
            // evidence a test ever ran.
            'last_date' => $last['date'] ?: $row['last_date'],
            'overdue' => $overdue,
            'due_soon' => $due_soon,
            // Sent, not derived client-side -- see test_days_until().
            'days_until' => test_days_until($row['next_date']),
            'last_result' => $last['result'],
            'last_result_family' => last_result_state_family($last['result']),
            'retired' => $row['retired_at'] !== null,
            // How many controls this test validates. >1 == a common test.
            // Defaults to 1: a test being rendered under a control maps to at
            // least that control, even if the counting join found nothing.
            'control_count' => $control_counts[$test_id] ?? 1,
            'approximate_time' => (int) $row['approximate_time'],
            'tags' => $row['tags'] ? explode('|', $row['tags']) : [],
            // Internal-only, stripped before the control card is returned --
            // used below to apply the quick.manual/schedule/quick.mine filters.
            '_schedule_type' => $row['schedule_type'] ?? 'manual',
            '_tester' => (int) $row['tester'],
        ];
    }

    // ---- AI suggestion rows: batch-fetch the pending control_test_generation
    // proposals for the WHOLE build set in ONE query, keyed control_id =>
    // [renderable proposals]. This replaces a per-control get_ai_proposals()
    // call inside the loop below -- an N+1 storm on the filtered/sort path,
    // where $ids_to_build is the full candidate catalog rather than a page
    // slice. renderable_control_test_proposals() pre-applies the same
    // is_renderable_control_test_proposal() validity predicate the count and
    // row builder use (count-agreement preserved) and orders ap.id DESC, so
    // each control's group is newest-first exactly as get_ai_proposals() was.
    // Only fetched when suggestions are actually emitted, so the disabled path
    // issues no extra query and stays byte-identical to today.
    $suggestions_by_control = [];
    if ($emit_suggestions) {
        foreach (renderable_control_test_proposals($ids_to_build) as $proposal) {
            $suggestions_by_control[(int) ($proposal['target_id'] ?? 0)][] = $proposal;
        }
    }

    // ---- Build each control card, applying the test-level quick/schedule/
    // tag filters (filtered path only) to trim each control's tests[]. ----
    $final_controls = [];
    foreach ($ids_to_build as $control_id) {
        if (!isset($controls_by_id[$control_id])) {
            continue;
        }

        $tests = $tests_by_control[$control_id] ?? [];

        if ($test_level_filter_active) {
            $tests = array_values(array_filter($tests, static function ($test) use ($quick, $schedule_filter, $tag_filter, $tester_filter, $current_uid) {
                if (!empty($quick['mine']) && $test['_tester'] !== $current_uid) {
                    return false;
                }
                if ($tester_filter !== null && $test['_tester'] !== $tester_filter) {
                    return false;
                }
                if (!empty($quick['failing']) && !test_last_result_is_failing($test['last_result_family'])) {
                    return false;
                }
                if (!empty($quick['passing']) && !test_last_result_is_passing($test['last_result_family'])) {
                    return false;
                }
                if (!empty($quick['inconclusive']) && ($test['last_result_family'] ?? '') !== 'warning') {
                    return false;
                }
                // 'neutral' is the family for a test with no recorded result --
                // last_result_state_family(null) (this file).
                if (!empty($quick['not_tested']) && ($test['last_result_family'] ?? '') !== 'neutral') {
                    return false;
                }
                if (!empty($quick['overdue']) && !$test['overdue']) {
                    return false;
                }
                if (!empty($quick['due_soon']) && !$test['due_soon']) {
                    return false;
                }
                if (!empty($quick['scheduled'])
                    && ($test['_schedule_type'] === 'manual' || $test['overdue'] || !empty($test['due_soon']))) {
                    return false;
                }
                if (!empty($quick['manual']) && $test['_schedule_type'] !== 'manual') {
                    return false;
                }
                if ($schedule_filter !== null && $test['_schedule_type'] !== $schedule_filter) {
                    return false;
                }
                if ($tag_filter !== null && !in_array($tag_filter, $test['tags'], true)) {
                    return false;
                }

                return true;
            }));

            // A test-level filter is active and nothing in this control matched it --
            // the control itself no longer belongs in a filtered grid.
            if (empty($tests)) {
                continue;
            }
        }

        $tests = array_map(static function ($test) {
            unset($test['_schedule_type'], $test['_tester']);

            return $test;
        }, $tests);

        // ---- AI suggestion rows (§4-6): append one kind='suggestion' row per
        // pending control_test_generation proposal on this control. Gated on
        // the capability; only in ai_suggested/all modes. Appended AFTER the
        // real-row test-level filtering + strip above so suggestions (which
        // have no last_result/tester/schedule_type) never flow through those
        // real-test-shaped predicates. The pending proposals for this control
        // are pulled from the batched $suggestions_by_control map above (one
        // query for the whole build set) rather than a per-control
        // get_ai_proposals() call; build_ai_suggestion_row() still shapes each
        // row and fails closed on any proposal that isn't a well-formed
        // control_test_generation suggestion (a no-op here since the map is
        // already validity-filtered -- kept so the row shape is unchanged). ----
        if ($emit_suggestions) {
            foreach ($suggestions_by_control[$control_id] ?? [] as $proposal) {
                $suggestion_row = build_ai_suggestion_row($proposal);
                if ($suggestion_row !== null) {
                    $tests[] = $suggestion_row;
                }
            }
        }

        // Defense-in-depth against an empty suggestion card: in ai_suggested
        // (suggestions-only) mode the real-test fan is hidden (1=0), so a card
        // with no suggestion rows is empty. control_ids_with_pending_test_
        // suggestions() already excludes controls whose only proposal is
        // malformed, so a candidate normally yields >=1 renderable row; this
        // guard makes the count-agreement guarantee hold even if the two ever
        // drift, dropping the control rather than rendering an empty card.
        if ($retired_mode === 'ai_suggested' && empty($tests)) {
            continue;
        }

        $control_row = $controls_by_id[$control_id];
        $final_controls[] = [
            'id' => $control_id,
            'control_number' => $control_row['control_number'],
            'short_name' => $control_row['short_name'],
            // Added for Task 6 alongside the long_name search match above --
            // rendered as secondary group-row text so the control's full
            // display name is still findable/visible now that the legacy
            // page's dedicated long-name filter field is gone.
            'long_name' => $control_row['long_name'],
            'owner_name' => $control_row['owner_name'],
            'framework_count' => (int) $control_row['framework_count'],
            'test_count' => count($tests),
            'tests' => $tests,
        ];
    }

    // ---- Step 4: paginate. Filtered path slices the (small, post-filter)
    // final list here, same as before B'. Hot path already paginated the id
    // list before Step 2/3, so $final_controls IS the page -- $records_filtered
    // was already set to the pre-slice candidate count above. ----
    if ($test_level_filter_active) {
        $records_filtered = count($final_controls);

        $start = max(0, (int) ($filters['start'] ?? 0));
        $length = (int) ($filters['length'] ?? 10);
        $page = ($length === -1) ? array_slice($final_controls, $start) : array_slice($final_controls, $start, max(0, $length));
    } else {
        $page = $final_controls;
    }

    // Sorting replaces the grouped payload with a flat, test-paginated one.
    // Done AFTER the control-level slice above is skipped (sort forces the
    // enrich-all path), so every candidate's tests are present to order.
    $flat = null;
    if ($sort !== '') {
        $flat = sort_tests_flat(
            $final_controls,
            $sort,
            (string) ($filters['dir'] ?? 'asc'),
            max(0, (int) ($filters['start'] ?? 0)),
            (int) ($filters['length'] ?? 10)
        );
        $records_filtered = $flat['recordsFiltered'];
    }

    // One pass over the active test set answers the title pills AND every
    // filter's count.
    $quick_counts = get_define_tests_quick_counts();

    return [
        // Flat mode ships `tests` and an empty `controls`, so a client that
        // only knows the grouped shape renders nothing rather than the wrong
        // thing.
        'sorted' => $sort !== '',
        'tests' => $flat ? $flat['tests'] : [],
        'controls' => $flat ? [] : $page,
        'recordsTotal' => $records_total,
        'recordsFiltered' => $records_filtered,
        // Global test-level totals for the toolbar title pills (same numbers as
        // the insights band's Total Tests / Overdue tiles). Distinct test counts
        // (not per-control), so a common test counts once. Refreshed on every
        // grid load so the pills stay current after an AJAX add/delete.
        // Derived from the single quick-counts pass below rather than from
        // count_active_tests()/count_overdue_tests(): those re-run the same
        // full scan of the active test set, and this handler is called on every
        // keystroke in the search box, not just on page load.
        'total_tests' => $quick_counts['total'],
        'overdue_tests' => $quick_counts['overdue'],
        'quick_counts' => $quick_counts,
        // Roster for the Tester filter -- org-hierarchy scoped; see the function.
        'tester_options' => get_define_tests_tester_options(),
        'filter_counts' => get_define_tests_filter_counts(),
    ];
}

/******************************************************************
 * BAND COUNT HELPERS (Phase 2, Task 3)                             *
 * Six int-returning counts behind the Define Tests insights band's *
 * KPI tiles (Total / Coverage gaps / Overdue / Due soon / Failing). *
 *                                                                    *
 * COUNT-AGREEMENT: every helper below is derived from the SAME      *
 * predicate/query build_tests_grid() uses for the corresponding     *
 * quick-filter or coverage mode -- never a second, independently    *
 * written definition of "overdue"/"due soon"/"failing"/"gap":       *
 *   - count_controls_with_tests() / count_coverage_gap_controls()   *
 *     call resolve_candidate_control_ids() (above), the exact Step 1*
 *     query build_tests_grid() runs for coverage='with'|'gaps'.     *
 *   - count_active_tests() / count_overdue_tests() /                *
 *     count_due_soon_tests() / count_failing_tests() all read the   *
 *     same active-test population via fetch_active_test_schedule_   *
 *     rows() (below) -- itself built from tests_grid_predicates(),  *
 *     the same fc.deleted=0 / t.retired_at predicates build_tests_  *
 *     grid() applies -- and then reuse is_test_overdue(),           *
 *     is_test_due_soon(), and get_tests_last_results() +            *
 *     last_result_state_family() + test_last_result_is_failing(),   *
 *     the exact pure/DB helpers build_tests_grid()'s Step 3 uses to *
 *     enrich and PHP-filter each test row.                          *
 *                                                                    *
 * TEAM SEPARATION: every one of these counts through                *
 * fetch_active_test_schedule_rows() / resolve_candidate_control_ids*
 * (), which carry the scoping, so a tile counts exactly the tests   *
 * its reader can open in the grid. Adding a count that reads test   *
 * rows any other way would silently break that -- see              *
 * test_team_scope_predicate() below for the four places it applies. *
 ******************************************************************/

/******************************************************************
 * FUNCTION: SORT TESTS FLAT                                        *
 * Flattens the grouped result into one ordered list of tests and   *
 * paginates it BY TEST.                                            *
 *                                                                  *
 * Sorting drops the control grouping on purpose: the columns a     *
 * user can sort by are all test-level, so "sort by Next Due" has   *
 * to mean "the most overdue test first ON THE PAGE" -- inside      *
 * groups it would scatter the answer across the whole list. Each   *
 * row therefore carries its own control, which the group row used  *
 * to supply.                                                       *
 *                                                                  *
 * A test mapped to several controls yields one row per pairing,    *
 * matching the grouped view (and the unlink action, which acts on  *
 * the pairing, not the test).                                      *
 *                                                                  *
 * Comparison notes: ids and minutes compare numerically; names and *
 * testers compare case-insensitively so "alice" doesn't sort after *
 * "Zoe"; a missing next date sorts LAST in both directions, since  *
 * "no date" is not earlier than every date, it is absent.          *
 ******************************************************************/
function sort_tests_flat(array $controls, string $sort, string $dir, int $start, int $length): array
{
    $rows = [];
    foreach ($controls as $control) {
        foreach (($control['tests'] ?? []) as $test) {
            $test['control'] = [
                'id' => $control['id'] ?? 0,
                'control_number' => $control['control_number'] ?? '',
                'short_name' => $control['short_name'] ?? '',
                'long_name' => $control['long_name'] ?? '',
                'framework_count' => $control['framework_count'] ?? 0,
            ];
            $rows[] = $test;
        }
    }

    $descending = ($dir === 'desc');

    // Result ordering is the grid's own severity order, not alphabetical:
    // failing first when descending, because that is what a compliance officer
    // sorting by result is looking for.
    $result_rank = ['danger' => 3, 'warning' => 2, 'success' => 1, 'neutral' => 0];

    $key = function (array $t) use ($sort, $result_rank) {
        switch ($sort) {
            case 'id':          return [0, (int) ($t['id'] ?? 0)];
            case 'tester':      return [0, mb_strtolower((string) ($t['tester_name'] ?? ''))];
            case 'schedule':    return [0, mb_strtolower((string) ($t['schedule_summary'] ?? ''))];
            case 'last_result': return [0, (int) ($result_rank[$t['last_result_family'] ?? 'neutral'] ?? 0)];
            case 'last_date':
                // Same absent-sorts-last rule as next_due: a test that has
                // never run has no last date, which is not "the oldest".
                $last = (string) ($t['last_date'] ?? '');
                $last_missing = ($last === '' || strpos($last, '0000-00-00') === 0);
                return [$last_missing ? 1 : 0, $last];
            case 'next_due':
                // Absent dates sort last whichever way the arrow points.
                $next = (string) ($t['next_date'] ?? '');
                $missing = ($next === '' || strpos($next, '0000-00-00') === 0);
                return [$missing ? 1 : 0, $next];
            case 'name':
            default:            return [0, mb_strtolower((string) ($t['name'] ?? ''))];
        }
    };

    usort($rows, function ($a, $b) use ($key, $descending) {
        [$a_missing, $a_val] = $key($a);
        [$b_missing, $b_val] = $key($b);

        // The missing-value bucket is independent of direction.
        if ($a_missing !== $b_missing) {
            return $a_missing <=> $b_missing;
        }

        $cmp = is_string($a_val) ? strcmp($a_val, (string) $b_val) : ($a_val <=> $b_val);
        if ($cmp === 0) {
            // Stable, predictable tiebreak so equal values don't shuffle
            // between requests and make pagination lose or repeat a row.
            $cmp = ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            return $cmp;
        }

        return $descending ? -$cmp : $cmp;
    });

    $total = count($rows);
    $page = ($length === -1) ? array_slice($rows, $start) : array_slice($rows, $start, max(0, $length));

    return ['tests' => $page, 'recordsFiltered' => $total];
}

/******************************************************************
 * FUNCTION: TEST IN SCOPE PREDICATE                                *
 * A correlated EXISTS saying "this test maps to at least one       *
 * non-deleted control", resolved through the SAME junction the     *
 * grid uses (test_control_pairs_sql(), which UNIONs                *
 * test_control_map with the legacy scalar column for unmigrated    *
 * rows).                                                           *
 *                                                                  *
 * The count helpers below used to join `fc.id = t.framework_       *
 * control_id` directly. That scalar column holds only ONE of a     *
 * common test's controls, and control deletion is a soft delete    *
 * that never touches test_control_map -- so deleting whichever     *
 * control happened to sit in that column dropped the test from     *
 * every KPI tile, filter count and tester option, while the grid   *
 * (which resolves through the junction) still listed it under its  *
 * other controls. Visible, clickable, and uncounted. Reproduced    *
 * on a real instance: scalar join counted 5, junction counted 6.   *
 *                                                                  *
 * Also carries the Team Separation scoping (see test_team_scope_    *
 * predicate() below), so every consumer of this predicate --       *
 * fetch_active_test_schedule_rows() and every count it feeds, plus *
 * get_define_tests_filter_counts() -- inherits visibility scoping  *
 * together. Queries that don't route through here (build_tests_    *
 * grid()'s test-row fetch, resolve_candidate_control_ids()'s test  *
 * join, count_common_tests()) call test_team_scope_predicate()     *
 * directly; there is no third way to reach a test row.             *
 *                                                                  *
 * $alias is the framework_control_tests alias to correlate against.*
 ******************************************************************/
function test_in_scope_predicate(string $alias = 't'): string
{
    $alias = grid_sql_alias($alias);
    $pairs = test_control_pairs_sql();
    $team = test_team_scope_predicate($alias);

    return "EXISTS (
        SELECT 1
        FROM {$pairs} tcp_scope
            INNER JOIN `framework_controls` fc_scope ON fc_scope.id = tcp_scope.`framework_control_id`
        WHERE tcp_scope.`test_id` = {$alias}.`id` AND fc_scope.deleted = 0
    ) AND {$team}";
}

/******************************************************************
 * FUNCTION: GRID SQL ALIAS (internal)                              *
 * Table aliases are interpolated into these predicates, and every  *
 * one of them is an internal constant rather than request data.    *
 * They are still validated at the boundary so a future caller that *
 * derives one from input can't turn a predicate builder into an    *
 * injection point; anything that isn't a bare identifier falls     *
 * back to the default tests alias.                                 *
 ******************************************************************/
function grid_sql_alias(string $alias): string
{
    return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) ? $alias : 't';
}

/******************************************************************
 * FUNCTION: TEST TEAM SCOPE PREDICATE                              *
 * A SQL boolean saying "the current user is allowed to see this    *
 * test", or the constant 1=1 when nothing is scoping them.         *
 *                                                                  *
 * WHY THIS EXISTS: the pre-redesign Define Tests grid filtered     *
 * every row through is_user_allowed_to_access($uid, $test_id,      *
 * 'test') when the Team Separation Extra was active                *
 * (getDefineTestsResponse(), includes/api.php). The redesigned     *
 * grid and its KPI counts checked only the `compliance` permission,*
 * which silently widened list/count visibility for Team Separation *
 * customers even though the single-record endpoints still enforced *
 * it. This restores that scoping to the list and count surfaces.   *
 *                                                                  *
 * Row-at-a-time is_user_allowed_to_access() is one query per test  *
 * -- fine for a page of rows, ruinous for tiles that count the     *
 * whole catalog -- so this uses the Extra's SQL-predicate form,    *
 * get_user_teams_query_for_tests(), the same integration Core      *
 * already uses for the audit_timeline datatable view               *
 * (get_datatable_data(), includes/functions.php).                  *
 *                                                                  *
 * The predicate is wrapped in a correlated EXISTS over a LEFT-     *
 * JOINed `framework_controls fc`, because the Extra's fragment     *
 * names fc.control_owner. LEFT, not INNER: is_user_allowed_to_     *
 * access() also LEFT JOINs, so a test whose scalar framework_      *
 * control_id doesn't resolve is still visible to its own tester /  *
 * stakeholders / team, exactly as the row-level check would say.   *
 * Using the scalar column (not the junction) is deliberate too --  *
 * it mirrors the row-level check exactly, so the grid can never    *
 * show a row that opening would deny.                              *
 *                                                                  *
 * Returns '1=1' when: the Extra isn't installed, the Extra is too  *
 * old to expose the helper (Extras update independently of Core --*
 * see extras/upgrade/backwards_compatibility.php), or the Extra    *
 * itself decides the user is unrestricted (admin, or the           *
 * allow_everyone_to_see_test_and_audit setting).                   *
 ******************************************************************/
function test_team_scope_predicate(string $alias = 't'): string
{
    $alias = grid_sql_alias($alias);

    if (!function_exists('team_separation_extra') || !team_separation_extra()) {
        return '1=1';
    }

    $separation = realpath(__DIR__.'/../extras/separation/index.php');
    if (!$separation) {
        return '1=1';
    }
    require_once($separation);

    if (!function_exists('get_user_teams_query_for_tests')) {
        // Unreachable in any configuration Core already supports: the
        // audit_timeline datatable view (get_datatable_data(),
        // includes/functions.php) calls this same Extra helper whenever
        // separation is on, so a separation-enabled instance whose Extra
        // lacks it is already broken elsewhere. Log rather than fail closed --
        // silently emptying every compliance user's grid would be a worse
        // failure than the one being guarded against -- but log loudly, since
        // this state means test visibility is NOT being scoped.
        write_debug_log('Team Separation is enabled but the Extra does not provide get_user_teams_query_for_tests(); the Define Tests grid and its counts are UNSCOPED.', 'error');

        return '1=1';
    }

    // $where=false, $and=false -> the bare boolean, with no WHERE/AND glue.
    $team = trim((string) get_user_teams_query_for_tests($alias, false, false));

    return compose_team_scope_predicate($alias, $team);
}

/******************************************************************
 * FUNCTION: COMPOSE TEAM SCOPE PREDICATE (internal)                *
 * The pure SQL-assembly half of test_team_scope_predicate(): wraps *
 * the Team Separation Extra's own boolean fragment in the EXISTS   *
 * subquery that ties it to the grid row's control. Split out so    *
 * the composition -- the part that could carry an alias or join    *
 * bug -- is unit-testable without loading the Extra (which pulls   *
 * in the whole application). $alias is assumed already validated   *
 * by grid_sql_alias(); this helper does no validation of its own.  *
 *                                                                    *
 * A trivial fragment ('', '(1)', '( 1 )' -- the Extra's "no        *
 * restriction" outputs) collapses to '1=1' so an unrestricted user *
 * or an all-teams membership doesn't wrap every row in a pointless *
 * EXISTS. Any other fragment is scoped through framework_controls. *
 ******************************************************************/
function compose_team_scope_predicate(string $alias, string $team_fragment): string
{
    $team = trim($team_fragment);
    if ($team === '' || $team === '(1)' || $team === '( 1 )') {
        return '1=1';
    }

    return "EXISTS (
        SELECT 1
        FROM (SELECT 1) sep_row
            LEFT JOIN `framework_controls` fc ON fc.id = {$alias}.`framework_control_id`
        WHERE {$team}
    )";
}

/******************************************************************
 * FUNCTION: FETCH ACTIVE TEST SCHEDULE ROWS (internal)              *
 * id/next_date/schedule_type/audit_initiation_offset for every       *
 * ACTIVE test -- non-deleted control (fc.deleted = 0), non-retired   *
 * test (t.retired_at IS NULL, i.e. show_retired=false, matching the  *
 * grid's default un-toggled population) -- via tests_grid_predicates *
 * (above). Shared by count_active_tests(), count_overdue_tests(),    *
 * count_due_soon_tests(), and count_failing_tests() so none of them  *
 * can define "active test" differently from the others or from the  *
 * grid.                                                               *
 *                                                                     *
 * @return array<int, array{id: int, next_date: ?string, schedule_type: ?string, audit_initiation_offset: ?int}>
 ******************************************************************/
function fetch_active_test_schedule_rows(): array
{
    $predicates = tests_grid_predicates(false);
    $scope = test_in_scope_predicate('t');

    $db = db_open();
    $stmt = $db->prepare("
        SELECT t.id, t.next_date, t.schedule_type, t.audit_initiation_offset
        FROM `framework_control_tests` t
        WHERE {$scope} AND ({$predicates['retired_predicate']})
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    return $rows;
}

/**
 * Count of non-retired `framework_control_tests` belonging to a non-deleted
 * control -- the Total tile's headline number. Same active-test population
 * build_tests_grid() enriches by default (show_retired=false).
 */
function count_active_tests(): int
{
    return count(fetch_active_test_schedule_rows());
}

/**
 * Count of distinct controls with >= 1 active test -- the Total tile's
 * "across N controls" subtitle. Delegates to resolve_candidate_control_ids()
 * with coverage='with', the exact query build_tests_grid() runs for the
 * coverage='with' filter (HAVING COUNT(t.id) > 0).
 */
function count_controls_with_tests(): int
{
    $filters = parse_grid_request(['coverage' => 'with']);

    return count(resolve_candidate_control_ids($filters));
}

/**
 * Count of non-retired `framework_control_tests` mapped to MORE THAN ONE
 * control via `test_control_map` -- the Total tile's "M common" prefix
 * (band UX punchlist item 2, unlocked by Phase 4a). Counted directly off
 * `test_control_map` (not the fallback-inclusive test_control_pairs_sql()
 * union): a common test always has >1 map row because Phase 4a's backfill
 * (and every subsequent common-test write) wrote one row per mapped
 * control -- see test_control_pairs_sql()'s docblock above. A scalar-only
 * test written by one of the two raw-SQL writers noted there has at most
 * one control (zero map rows), so it can never satisfy HAVING COUNT(...) >
 * 1 and is correctly excluded without needing the fallback union.
 */
function count_common_tests(): int
{
    $db = db_open();
    // Join framework_controls and exclude soft-deleted controls so a test
    // whose second mapping points at a deleted control isn't counted as
    // "common" -- keeps this in agreement with count_controls_with_tests()/
    // count_coverage_gap_controls(), which resolve coverage over non-deleted
    // controls only (via resolve_candidate_control_ids()).
    // fc_map, not fc: test_team_scope_predicate() brings its own LEFT-JOINed
    // `fc` for the Extra's fc.control_owner clause, and two different controls
    // under one alias would silently answer the wrong question.
    $team_scope = test_team_scope_predicate('ft');
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM (
            SELECT ft.id
            FROM `framework_control_tests` ft
                JOIN `test_control_map` tcm ON tcm.test_id = ft.id
                JOIN `framework_controls` fc_map ON fc_map.id = tcm.framework_control_id AND fc_map.deleted = 0
            WHERE ft.retired_at IS NULL AND ({$team_scope})
            GROUP BY ft.id
            HAVING COUNT(DISTINCT tcm.framework_control_id) > 1
        ) x
    ");
    $stmt->execute();
    $count = (int) $stmt->fetchColumn();
    db_close($db);

    return $count;
}

/**
 * Count of controls with 0 active tests -- the Coverage gaps tile.
 * Delegates to resolve_candidate_control_ids() with coverage='gaps', the
 * exact query build_tests_grid() runs for the coverage='gaps' filter
 * (HAVING COUNT(t.id) = 0) -- the SAME predicate the brief requires.
 */
function count_coverage_gap_controls(): int
{
    $filters = parse_grid_request(['coverage' => 'gaps']);

    return count(resolve_candidate_control_ids($filters));
}

/**
 * Count of active tests where is_test_overdue(next_date) is true -- the
 * Overdue tile. Reuses is_test_overdue() (this file, pure) over the same
 * active-test population the grid's quick.overdue filter enriches.
 */
function count_overdue_tests(): int
{
    $count = 0;
    foreach (fetch_active_test_schedule_rows() as $row) {
        if (is_test_overdue($row['next_date'])) {
            ++$count;
        }
    }

    return $count;
}

/**
 * Count of active tests inside their audit-initiation lead-in window -- the
 * Due soon tile. Reuses is_test_overdue() + is_test_due_soon() (this file,
 * pure) -- the exact pair build_tests_grid()'s Step 3 calls to derive
 * `overdue` and then `due_soon` for each test row -- over the same
 * active-test population.
 */
function count_due_soon_tests(): int
{
    $count = 0;
    foreach (fetch_active_test_schedule_rows() as $row) {
        $overdue = is_test_overdue($row['next_date']);
        if (is_test_due_soon($row['next_date'], $row['schedule_type'] ?? 'manual', $row['audit_initiation_offset'] ?? 0, $overdue)) {
            ++$count;
        }
    }

    return $count;
}

/**
 * Count of active tests whose newest audit result is Fail -- the Failing
 * tile. Reuses get_tests_last_results() (Task 2) + last_result_state_family()
 * + test_last_result_is_failing() (Task 2) -- the exact chain build_tests_
 * grid()'s Step 3 / quick.failing filter uses -- over the same active-test
 * id set.
 */
function count_failing_tests(): int
{
    $rows = fetch_active_test_schedule_rows();
    $test_ids = array_map(static fn ($row) => (int) $row['id'], $rows);

    if (empty($test_ids)) {
        return 0;
    }

    $last_results = get_tests_last_results($test_ids);

    $count = 0;
    foreach ($last_results as $last) {
        if (test_last_result_is_failing(last_result_state_family($last['result']))) {
            ++$count;
        }
    }

    return $count;
}

/**
 * Count of active tests whose newest audit result is Pass -- the Passing
 * tile. Reuses get_tests_last_results() (Task 2) + last_result_state_family()
 * + test_last_result_is_passing() -- the exact chain build_tests_grid()'s
 * Step 3 / quick.passing filter uses -- over the same active-test id set.
 */
function count_passing_tests(): int
{
    $rows = fetch_active_test_schedule_rows();
    $test_ids = array_map(static fn ($row) => (int) $row['id'], $rows);

    if (empty($test_ids)) {
        return 0;
    }

    $last_results = get_tests_last_results($test_ids);

    $count = 0;
    foreach ($last_results as $last) {
        if (test_last_result_is_passing(last_result_state_family($last['result']))) {
            ++$count;
        }
    }

    return $count;
}

/******************************************************************
 * FUNCTION: GET DEFINE TESTS FILTER COUNTS                         *
 * Option counts for the grid's Schedule, Show and Tag selects, so  *
 * a select can say how much is behind each choice the way the      *
 * quick chips and the Tester roster already do -- and so a choice  *
 * that would empty the grid says 0 before it is clicked.           *
 *                                                                  *
 * GLOBAL, not faceted: like the quick-chip counts, these describe  *
 * the whole test set and ignore whatever else is filtered. One     *
 * rule across every count on the page is learnable; a mix of       *
 * global and contextual numbers is not. (Faceted counts -- each    *
 * dimension counted with its own filter lifted -- would be the     *
 * upgrade, and would need the filter pipeline run per dimension.)  *
 *                                                                  *
 * Schedule/tag counts are of ACTIVE tests, matching the chips.     *
 * The Show counts are the exception by definition: 'active' and    *
 * 'retired_only' are the two halves, 'all' their sum.              *
 *                                                                  *
 * @return array{schedule:array<string,int>,retired:array<string,int>,tags:array<string,int>}
 ******************************************************************/
function get_define_tests_filter_counts(): array
{
    $counts = [
        'schedule' => ['calendar' => 0, 'interval' => 0, 'manual' => 0],
        'retired' => ['active' => 0, 'all' => 0, 'retired_only' => 0],
        'tags' => [],
        // Coverage counts CONTROLS, not tests -- it is the one control-scoped
        // filter on the page, and its numbers come from the same helpers the
        // insights band's tiles use so the two can't disagree.
        'coverage' => [
            'with' => count_controls_with_tests(),
            'gaps' => count_coverage_gap_controls(),
        ],
        // Framework and Family are control attributes, so they count CONTROLS
        // too -- same unit as Coverage directly beside them in the toolbar.
        'framework' => [],
        'family' => [],
    ];

    $predicates = tests_grid_predicates(false);
    $scope = test_in_scope_predicate('t');
    $db = db_open();

    // Schedule mode + the active/retired split, in one pass over every test
    // on a non-deleted control (no retired predicate here -- the split is
    // exactly what is being counted).
    $stmt = $db->prepare("
        SELECT t.schedule_type, t.retired_at
        FROM `framework_control_tests` t
        WHERE {$scope}
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $retired = $row['retired_at'] !== null;

        ++$counts['retired']['all'];
        ++$counts['retired'][$retired ? 'retired_only' : 'active'];

        if (!$retired) {
            $mode = $row['schedule_type'] ?: 'manual';
            if (isset($counts['schedule'][$mode])) {
                ++$counts['schedule'][$mode];
            }
        }
    }

    // Controls per framework. A control can map to several frameworks, so this
    // is a per-framework DISTINCT count and the numbers deliberately don't sum
    // to the control total.
    $stmt = $db->prepare("
        SELECT fcm.framework, COUNT(DISTINCT fc.id) AS control_count
        FROM `framework_controls` fc
            INNER JOIN `framework_control_mappings` fcm ON fcm.control_id = fc.id
        WHERE {$predicates['controls_where']}
        GROUP BY fcm.framework
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts['framework'][(string) $row['framework']] = (int) $row['control_count'];
    }

    // Controls per family (a control has exactly one).
    $stmt = $db->prepare("
        SELECT fc.family, COUNT(*) AS control_count
        FROM `framework_controls` fc
        WHERE {$predicates['controls_where']} AND fc.family > 0
        GROUP BY fc.family
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts['family'][(string) $row['family']] = (int) $row['control_count'];
    }

    // Tag counts, active tests only -- same basis as the schedule counts.
    $stmt = $db->prepare("
        SELECT tg.tag, COUNT(DISTINCT t.id) AS test_count
        FROM `framework_control_tests` t
            INNER JOIN `tags_taggees` tt ON tt.taggee_id = t.id AND tt.type = 'test'
            INNER JOIN `tags` tg ON tg.id = tt.tag_id
        WHERE {$scope} AND ({$predicates['retired_predicate']})
        GROUP BY tg.tag
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts['tags'][(string) $row['tag']] = (int) $row['test_count'];
    }

    db_close($db);

    $counts['coverage']['all'] = $counts['coverage']['with'] + $counts['coverage']['gaps'];

    // ai_suggested `show` count -- ONLY when the AI control-test capability is
    // enabled (§3). Absent when off, so no ai_suggested chip renders and the
    // toolbar is byte-identical to today. Belt-and-suspenders reachability for
    // ai_capability_enabled() (CLAUDE.md).
    require_once __DIR__ . '/artificial_intelligence.php';
    if (ai_capability_enabled('control_test_generation')) {
        $counts['retired']['ai_suggested'] = count_pending_control_test_suggestions();
    }

    return $counts;
}

/******************************************************************
 * FUNCTION: GET DEFINE TESTS TESTER OPTIONS                        *
 * The roster behind the grid's Tester filter: every user who owns  *
 * at least one ACTIVE test, with how many they own.                *
 *                                                                  *
 * Org hierarchy: the names are intersected with                    *
 * get_custom_table('enabled_users'), which is the app's own        *
 * organization-scoped user list -- when the Organizational         *
 * Hierarchy Extra is enabled it returns only users of the selected *
 * business unit (for admins too; see that function's docblock), so *
 * this filter can't surface people from an organization the viewer *
 * isn't currently in. With the Extra off it is simply every        *
 * enabled user, and the intersection is a no-op.                   *
 *                                                                  *
 * A tester outside that set keeps their tests in the grid (hiding  *
 * the rows would misstate coverage) -- they just aren't offered as *
 * a name to filter by.                                             *
 *                                                                  *
 * Counts are of active tests and ignore the grid's other filters,  *
 * matching how the quick-chip counts behave.                       *
 *                                                                  *
 * @return array<int, array{value:int,name:string,count:int}>       *
 ******************************************************************/
function get_define_tests_tester_options(): array
{
    $predicates = tests_grid_predicates(false);
    $scope = test_in_scope_predicate('t');

    $db = db_open();
    $stmt = $db->prepare("
        SELECT t.tester, COUNT(*) AS test_count
        FROM `framework_control_tests` t
        WHERE {$scope} AND ({$predicates['retired_predicate']}) AND t.tester > 0
        GROUP BY t.tester
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    if (empty($rows)) {
        return [];
    }

    $counts = [];
    foreach ($rows as $row) {
        $counts[(int) $row['tester']] = (int) $row['test_count'];
    }

    $options = [];
    foreach (get_custom_table('enabled_users') as $user) {
        $id = (int) $user['value'];
        if (!isset($counts[$id])) {
            continue;
        }
        $options[] = ['value' => $id, 'name' => (string) $user['name'], 'count' => $counts[$id]];
    }

    usort($options, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

    return $options;
}

/**
 * Every Define Tests filter count, in ONE pass over the active test set, so
 * each option can show its count. Reuses the exact predicates and per-test
 * helpers the grid's filters + the KPI tiles use (is_test_overdue /
 * is_test_due_soon / test_last_result_is_failing / test_last_result_is_passing),
 * so a count always agrees with what choosing that option actually shows.
 * Global (unfiltered), distinct per test, matching the insights band.
 *
 * Carries 'total' (the size of that same active population) so callers needing
 * the headline number don't re-scan the catalog to recount rows this pass has
 * already walked -- see build_tests_grid(), which derives its title pills from
 * this instead of calling count_active_tests()/count_overdue_tests() again.
 */
function get_define_tests_quick_counts(): array
{
    $counts = ['total' => 0, 'mine' => 0, 'overdue' => 0, 'due_soon' => 0, 'failing' => 0, 'passing' => 0, 'inconclusive' => 0, 'not_tested' => 0, 'scheduled' => 0, 'manual' => 0];

    $predicates = tests_grid_predicates(false);
    $scope = test_in_scope_predicate('t');
    $db = db_open();
    $stmt = $db->prepare("
        SELECT t.id, t.tester, t.next_date, t.schedule_type, t.audit_initiation_offset
        FROM `framework_control_tests` t
        WHERE {$scope} AND ({$predicates['retired_predicate']})
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    if (empty($rows)) {
        return $counts;
    }

    $counts['total'] = count($rows);
    $test_ids = array_map(static fn ($r) => (int) $r['id'], $rows);
    $last_results = get_tests_last_results($test_ids);
    $current_uid = (int) ($_SESSION['uid'] ?? 0);

    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $schedule_type = $r['schedule_type'] ?? 'manual';
        $overdue = is_test_overdue($r['next_date']);

        if ($current_uid > 0 && (int) $r['tester'] === $current_uid) {
            ++$counts['mine'];
        }
        if ($overdue) {
            ++$counts['overdue'];
        }
        $due_soon = is_test_due_soon($r['next_date'], $schedule_type, $r['audit_initiation_offset'] ?? 0, $overdue);
        if ($due_soon) {
            ++$counts['due_soon'];
        }
        $family = last_result_state_family($last_results[$id]['result'] ?? null);
        if (test_last_result_is_failing($family)) {
            ++$counts['failing'];
        }
        if (test_last_result_is_passing($family)) {
            ++$counts['passing'];
        }
        if ($family === 'warning') {
            ++$counts['inconclusive'];
        }
        if ($family === 'neutral') {
            ++$counts['not_tested'];
        }
        if ($schedule_type === 'manual') {
            ++$counts['manual'];
        }
        if ($schedule_type !== 'manual' && !$overdue && !$due_soon) {
            ++$counts['scheduled'];
        }
    }

    return $counts;
}
