<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Pure audit-schedule engine: computes recurrence occurrences from a rule
// (unit + interval + anchor) minus per-occurrence exceptions. No DB, no I/O.

/**
 * The nth (0-based) natural occurrence of the rule, 'Y-m-d'.
 * Month/year units clamp the anchor day-of-month to the last valid day.
 */
function audit_schedule_base_occurrence(string $anchor, string $unit, int $interval, int $n): ?string {
    if ($interval < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$anchor)) {
        return null;
    }
    $ay = (int)substr($anchor, 0, 4);
    $am = (int)substr($anchor, 5, 2);
    $ad = (int)substr($anchor, 8, 2);

    switch ($unit) {
        case 'day':
            return date('Y-m-d', strtotime("$anchor +" . ($n * $interval) . " days"));
        case 'week':
            return date('Y-m-d', strtotime("$anchor +" . ($n * $interval * 7) . " days"));
        case 'month':
            $months = ($am - 1) + ($n * $interval);
            $y = $ay + intdiv($months, 12);
            $m = ($months % 12) + 1;
            $last = (int)date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
            return sprintf('%04d-%02d-%02d', $y, $m, min($ad, $last));
        case 'year':
            $y = $ay + ($n * $interval);
            $last = (int)date('t', strtotime(sprintf('%04d-%02d-01', $y, $am)));
            return sprintf('%04d-%02d-%02d', $y, $am, min($ad, $last));
    }
    return null;
}

/**
 * Apply an exception to a natural occurrence date.
 * Returns the effective 'Y-m-d', or null if the occurrence is skipped.
 */
function audit_schedule_apply_exception(string $natural, array $exceptions): ?string {
    if (!isset($exceptions[$natural])) {
        return $natural;
    }
    $ex = $exceptions[$natural];
    if (!empty($ex['skipped'])) {
        return null;
    }
    return !empty($ex['override_date']) ? $ex['override_date'] : $natural;
}

/**
 * First effective occurrence date on or after $on_or_after, or null.
 * Scans natural occurrences forward; a small look-ahead past $on_or_after
 * absorbs overrides that shift a later occurrence slightly earlier.
 */
function audit_schedule_next_occurrence(string $anchor, string $unit, int $interval, string $on_or_after, array $exceptions = []): ?string {
    $candidates = [];
    $lookahead = 8;   // extra occurrences scanned past the boundary
    $seen_boundary = 0;
    for ($n = 0; $n < 100000; $n++) {
        $natural = audit_schedule_base_occurrence($anchor, $unit, $interval, $n);
        if ($natural === null) {
            break;
        }
        $effective = audit_schedule_apply_exception($natural, $exceptions);
        if ($effective !== null && $effective >= $on_or_after) {
            $candidates[] = $effective;
        }
        if ($natural >= $on_or_after) {
            if (++$seen_boundary >= $lookahead) {
                break;
            }
        }
    }
    if (empty($candidates)) {
        return null;
    }
    sort($candidates);
    return $candidates[0];
}

/**
 * All effective occurrence dates within [$start, $end], sorted ascending, unique.
 */
function audit_schedule_occurrences(string $anchor, string $unit, int $interval, string $start, string $end, array $exceptions = []): array {
    $out = [];
    $lookahead = 8;
    $past_end = 0;
    for ($n = 0; $n < 100000; $n++) {
        $natural = audit_schedule_base_occurrence($anchor, $unit, $interval, $n);
        if ($natural === null) {
            break;
        }
        $effective = audit_schedule_apply_exception($natural, $exceptions);
        if ($effective !== null && $effective >= $start && $effective <= $end) {
            $out[$effective] = true;
        }
        if ($natural > $end) {
            if (++$past_end >= $lookahead) {
                break;
            }
        }
    }
    $out = array_keys($out);
    sort($out);
    return $out;
}

/**
 * Normalize a request-shaped exceptions list (array of
 * {occurrence_date, override_date, skipped}) into the engine's keyed map
 * (occurrence_date => ['override_date' => ?string, 'skipped' => bool]).
 * Entries missing occurrence_date are dropped. Pure, no DB/HTTP.
 */
function normalize_schedule_exceptions(array $list): array {
    $out = [];
    foreach ($list as $ex) {
        if (!empty($ex['occurrence_date'])) {
            $out[$ex['occurrence_date']] = [
                'override_date' => $ex['override_date'] ?? null,
                'skipped' => !empty($ex['skipped']),
            ];
        }
    }
    return $out;
}

/**
 * Parse a decoded schedule_preview request body into the argument shape
 * consumed by audit_schedule_occurrences(). Applies the same start/end
 * defaulting (today .. +2 years) used by the API handler. Pure, no DB/HTTP.
 */
function parse_schedule_preview_request(array $body): array {
    return [
        'cadence_unit'        => $body['cadence_unit'] ?? '',
        'cadence_interval'    => (int)($body['cadence_interval'] ?? 0),
        'cadence_anchor_date' => $body['cadence_anchor_date'] ?? '',
        'start'               => $body['start'] ?? date('Y-m-d'),
        'end'                 => $body['end'] ?? date('Y-m-d', strtotime('+2 years')),
        'exceptions'          => normalize_schedule_exceptions($body['schedule_exceptions'] ?? []),
    ];
}

/**
 * Parse the schedule-related fields out of a test create/update request
 * body into the argument shape consumed by add_framework_control_test() /
 * update_framework_control_test(). Empty-string fields normalize to null
 * (treated as "not supplied" by both functions). schedule_exceptions
 * follows the update_framework_control_test() null-vs-array contract:
 * a non-array value (missing field, or a schedule_exceptions value that
 * isn't an array) parses to null ("leave existing exceptions untouched");
 * an array value (including []) normalizes to the engine map ("replace
 * with this set"). Pure, no DB/HTTP.
 */
function parse_test_schedule_fields(array $body): array {
    $norm = function ($v) {
        return ($v === null || $v === '') ? null : $v;
    };
    $exceptions = $body['schedule_exceptions'] ?? null;

    $fields = [
        'schedule_type'        => $norm($body['schedule_type'] ?? null),
        'cadence_unit'         => $norm($body['cadence_unit'] ?? null),
        'cadence_interval'     => (($body['cadence_interval'] ?? '') !== '') ? (int)$body['cadence_interval'] : null,
        'cadence_anchor_date'  => $norm($body['cadence_anchor_date'] ?? null),
        'schedule_exceptions'  => is_array($exceptions) ? normalize_schedule_exceptions($exceptions) : null,
    ];

    // Cadence describes a CALENDAR schedule and nothing else, so a request that
    // names a manual or interval schedule carries no cadence no matter what the
    // form posted.
    //
    // The Edit Test modal always submits the cadence controls -- they are hidden
    // in the other two modes, not disabled -- so their idle defaults (month, 1)
    // rode along on every save. A no-op save of an Interval test therefore wrote
    // cadence_unit='month', cadence_interval=1 onto a row that had never had a
    // cadence: inert while the schedule stays Interval, but it silently
    // pre-seeds a choice the user never made, and it would be waiting for them
    // pre-filled if they ever opened Calendar mode.
    //
    // null, not '': null is update_framework_control_test()'s "leave the stored
    // value alone", so this declines to WRITE a cadence rather than erasing one.
    // A test that genuinely had a calendar cadence and is switched to Interval
    // keeps it, unused, and gets it back if the user switches Calendar on again
    // -- that value was a real choice; these defaults never were.
    //
    // An OMITTED schedule_type (null) is untouched: it means "don't change the
    // schedule", and a caller updating only cadence fields on an existing
    // calendar test must keep working.
    if ($fields['schedule_type'] === 'manual' || $fields['schedule_type'] === 'interval') {
        $fields['cadence_unit'] = null;
        $fields['cadence_interval'] = null;
        $fields['cadence_anchor_date'] = null;
        $fields['schedule_exceptions'] = null;
    }

    return $fields;
}

/**
 * The next test date for an INTERVAL-style (non-calendar) test: the legacy
 * model where a test is due again `test_frequency` days after it was last
 * run. Pure -- no DB, and no clock at all: the result is a function of the
 * last date and the frequency, never of when the save happens.
 *
 * Returns 'Y-m-d', or false for "no next date".
 *
 * NOT clamped to today. A test last run on 2026-03-15 with a 90-day frequency
 * was due 2026-06-13; if that is in the past then the test is LATE, and
 * saying so is the entire job of the Overdue state, the Overdue tile and the
 * grid's due column. updateTestResponse() used to rewrite any past result to
 * today, so saving an unrelated edit -- a typo in the name -- silently
 * asserted the test was due today and erased 36 days of lateness. The
 * matching "next date can't be in the past" VALIDATION had already been
 * commented out in that handler (its $lang['InvalidNextTestDate'] message
 * still exists), so the rule survived only as a silent rewrite: the save
 * reported success and changed a compliance-relevant date without telling
 * anyone.
 *
 * Derivation only happens when there is something to derive from -- a
 * last_date AND a positive frequency. Otherwise the submitted date stands,
 * which is also what createTest() has always done, so add and update finally
 * agree about who owns this field.
 *
 * @param string|false|null $last_date      'Y-m-d' or empty
 * @param int               $test_frequency days between runs; <= 0 means no cadence
 * @param string|false|null $submitted      'Y-m-d' or empty, from the form
 * @return string|false
 */
function resolve_interval_next_date($last_date, int $test_frequency, $submitted) {

    $has_last_date = $last_date && $last_date !== '0000-00-00';
    $has_submitted = $submitted && $submitted !== '0000-00-00';

    // No cadence to project from: whatever the user set stands. A manual test
    // with no frequency is scheduled by a human, so the form is authoritative.
    if (!$has_last_date || $test_frequency <= 0) {
        return $has_submitted ? $submitted : false;
    }

    return date('Y-m-d', strtotime($last_date) + $test_frequency * 24 * 60 * 60);
}

/**
 * Normalizes a submitted date to 'Y-m-d' or false — where false means "the
 * user left this blank".
 *
 * get_standard_date_from_default_format() answers an empty field with the
 * STRING '0000-00-00', which is truthy. Every `if (!$date)` guard therefore
 * reads a blank date as a real one, and every strtotime() comparison against
 * it compares to 1899. That mis-read is what made switching a test with a
 * Last Test Date into Calendar mode fail with "Next Test Date can't be before
 * Last Test Date!": Calendar mode submits no next_date, the handler received
 * '0000-00-00', the order check compared 1899 < the last test date, and the
 * calendar branch that discards next_date entirely ran only afterwards.
 *
 * Normalizing at the parse point rather than reordering that one branch fixes
 * the class of bug, not the instance: every downstream guard in the handler
 * gets a value whose truthiness means what it says.
 *
 * @param string|false|null $date raw output of get_standard_date_from_default_format()
 * @return string|false
 */
function normalize_submitted_date($date) {

    if (!is_string($date)) {
        return false;
    }

    $date = trim($date);

    // MySQL's zero date in either precision, plus anything that parses to a
    // zero year -- all of them mean "nothing was entered".
    if ($date === '' || strpos($date, '0000-00-00') === 0) {
        return false;
    }

    return $date;
}

/**
 * Parses a date an API caller submitted into canonical 'Y-m-d'.
 *
 * The v2 compliance CRUD handlers used to bind their date fields straight into
 * DATE columns with no parse at all. MM/DD/YYYY is SimpleRisk's own default
 * display format — what the datepickers show and what getTestResponse() answers
 * with — so it is the format a caller reading the UI naturally sends, and MySQL
 * cannot store it: '06/19/2026' landed as '0000-00-00' while the endpoint still
 * answered 200. Nothing usable stored, and nothing said so.
 *
 * TWO FORMATS ARE ACCEPTED, deliberately, and that is wider than the sibling
 * handlers' bare get_standard_date_from_default_format() call:
 *
 *   - ISO 'Y-m-d'. It is what the DATE columns hold, what getTestById() and
 *     getAuditById() answer with, and what every existing machine caller
 *     already sends (tests/api/ComplianceCrudTest and
 *     tests/api/ComplianceAuditApprovalTest both submit date('Y-m-d'), and
 *     updateAuditById()'s "field omitted, keep the stored value" fallback
 *     re-submits the stored ISO string). On an MM/DD/YYYY instance the display
 *     parse REJECTS ISO, so converting only via the display format would have
 *     broken every one of those callers — a fix that trades one silent zero
 *     date for another.
 *   - The instance's configured display format, passed in as $display_format so
 *     this function stays pure and this file keeps its no-DB contract. Callers
 *     supply get_default_date_format() (includes/functions.php).
 *
 * THREE-WAY RETURN, because "blank" and "garbage" are different answers and
 * collapsing them is the bug:
 *
 *   'Y-m-d'  the parsed date.
 *   false    the field was blank or the zero date — "nothing submitted". Same
 *            sentinel normalize_submitted_date() uses, so callers can keep
 *            their existing `=== false` "leave it alone" branches.
 *   null     a value WAS submitted and is not a date. The caller must answer
 *            400 rather than store a zero date, because a 200 that silently
 *            stores nothing is indistinguishable from success.
 *
 * Compare with === : false and null are both falsy.
 *
 * Parsing is strict — DateTime::getLastErrors() is consulted, not just the
 * return value, because createFromFormat() happily rolls '2026-02-31' forward
 * to March 3rd (a warning) and ignores trailing garbage like '2026-06-19xyz'
 * (an error) while still handing back a DateTime. A typo must be refused, not
 * silently relocated. Non-zero-padded input ('6/19/2026') is still accepted:
 * the format matches, nothing is rolled over, and it is only the strictness of
 * a naive re-format comparison that would have rejected it.
 *
 * Pure: no DB, no globals, no output.
 *
 * @param mixed  $raw            the submitted value
 * @param string $display_format PHP date format, e.g. 'm/d/Y'
 * @return string|false|null
 */
function parse_submitted_api_date($raw, string $display_format) {

    // Arrays/objects are not dates. Refusing them here keeps the (string) cast
    // below from raising, and a caller that sent one gets a 400 rather than a 500.
    if ($raw !== null && !is_scalar($raw)) {
        return null;
    }

    $raw = trim((string)($raw ?? ''));

    // Blank, or MySQL's zero date in either precision — "nothing submitted".
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return false;
    }

    // ISO first: it is unambiguous, and on an instance whose display format IS
    // 'Y-m-d' the second pass would be identical anyway.
    foreach (array_unique(['Y-m-d', $display_format]) as $format) {
        if ($format === '') {
            continue;
        }

        // '!' resets the unparsed fields to the epoch rather than to "now", so a
        // date-only format can't inherit today's time and drift across a
        // midnight boundary.
        $parsed = DateTime::createFromFormat('!' . $format, $raw);

        // PHP >= 8.2 returns false from getLastErrors() when the parse was
        // clean; older builds return an array of zero counts. Treat both as OK.
        $errors = DateTime::getLastErrors();
        $unclean = is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

        if ($parsed instanceof DateTime && !$unclean) {
            return $parsed->format('Y-m-d');
        }
    }

    return null;
}

/*******************************************************************************
 * FUNCTION: SUBMITTED DATE IS IMPOSSIBLE                                       *
 * Distinguishes the two ways parse_submitted_api_date() can return null, so    *
 * the 400 can say which one happened.                                          *
 *                                                                              *
 *   '02/31/2026' on an m/d/Y instance -> STRUCTURALLY FINE, the day does not   *
 *                                        exist. This returns true.             *
 *   'banana'                          -> not a date in any accepted format.    *
 *                                        This returns false.                   *
 *                                                                              *
 * Without the split, both produced "submit it as YYYY-MM-DD or m/d/Y" — advice *
 * the Feb-31 caller had already followed, which reads as the server being      *
 * broken rather than the date being wrong.                                     *
 *                                                                              *
 * The signal is createFromFormat()'s error/warning split: a value that MATCHES  *
 * the format but names a non-existent day still returns a DateTime (PHP rolls   *
 * it forward to Mar 3) and reports error_count 0 with warning_count 1. A value  *
 * that does not match the format returns false with error_count > 0. So        *
 * "object + no errors + warnings" is exactly the impossible-date case.         *
 *                                                                              *
 * Month overflow ('13/05/2026' on m/d/Y — a DD/MM caller) lands here too, and   *
 * that is the right bucket: month 13 genuinely does not exist, and the message  *
 * names the expected format, which is the hint that caller needs.              *
 *******************************************************************************/
function submitted_date_is_impossible($raw, string $display_format) {

    if ($raw !== null && !is_scalar($raw)) {
        return false;
    }

    $raw = trim((string)($raw ?? ''));

    if ($raw === '') {
        return false;
    }

    foreach (array_unique(['Y-m-d', $display_format]) as $format) {
        if ($format === '') {
            continue;
        }

        $parsed = DateTime::createFromFormat('!' . $format, $raw);
        $errors = DateTime::getLastErrors();

        // getLastErrors() returns false on a clean parse in PHP >= 8.2, so a
        // non-array reading means "no warnings" — not the impossible-date case.
        if (!is_array($errors)) {
            continue;
        }

        if ($parsed instanceof DateTime
            && ($errors['error_count'] ?? 0) === 0
            && ($errors['warning_count'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

/*******************************************************************************
 * FUNCTION: SUBMITTED DATE ERROR MESSAGE                                       *
 * Builds the localized 400 body for a date parse_submitted_api_date() refused.  *
 * Centralized because every date call site in api.php needs identical wording, *
 * and because the escaping rule is easy to get wrong at an individual site.    *
 *                                                                              *
 * $field is what the caller sees named as the bad field. Pass the LOCALIZED    *
 * label with the wire name in parentheses -- "Last Test Date (last_date)" --   *
 * so the modal user reads their own language and an API caller still learns    *
 * which request key to correct.                                                *
 *                                                                              *
 * ESCAPING: _lang() escapes every param exactly once. The result is delivered  *
 * as a JSON status_message which the compliance pages hand to toastr, and      *
 * toastr renders HTML by default — decoding those entities exactly once. That  *
 * is the documented escape-once/decode-once round trip (CLAUDE.md, toastr      *
 * rule), so do NOT pre-escape $raw here and do NOT re-escape client-side.      *
 *                                                                              *
 * The submitted value is echoed back because "the date you sent" is the whole  *
 * point of the message, but it is truncated first: it is unvalidated caller    *
 * input and an unbounded echo turns a 400 body into a log-flooding vector.     *
 *                                                                              *
 * TWO format arguments, deliberately, because they are two different strings:  *
 *   $php_format   'm/d/Y'      — get_default_date_format(), for PARSING        *
 *   $human_format 'MM/DD/YYYY' — get_setting('default_date_format'), to SHOW   *
 * Showing the PHP format would tell a customer to type 'm/d/Y'. The human one  *
 * is passed in rather than read here so this file keeps needing no require of  *
 * functions.php — tests/unit/ParseSubmittedApiDateTest.php includes it alone.  *
 *                                                                              *
 * NOTE this function calls _lang() and so is NOT safe to call from a @group    *
 * pure test. submitted_date_is_impossible() above is the pure, testable half.  *
 *******************************************************************************/
function submitted_date_error_message($raw, string $field, string $php_format, string $human_format) {

    $display = is_scalar($raw) ? trim((string)$raw) : '';

    if (mb_strlen($display) > 40) {
        $display = mb_substr($display, 0, 40) . '...';
    }

    if ($display !== '' && submitted_date_is_impossible($raw, $php_format)) {
        return _lang('ImpossibleSubmittedDate', [
            'field'  => $field,
            'value'  => $display,
            'format' => $human_format,
        ]);
    }

    return _lang('InvalidSubmittedDate', [
        'field'  => $field,
        'format' => $human_format,
    ]);
}
