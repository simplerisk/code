<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Pure lifecycle decisions for the AI risk analysis sweep. No DB, no I/O, no
// language file — safe to require from anywhere, and standalone-testable.
//
// Every function here gates a paid AI call, so each is deliberately small
// enough to reason about in isolation and is covered by @group pure tests.

/*********************************************************************
 * FUNCTION: AI RISK CONSECUTIVE FAILURES FROM ROWS                  *
 * Count trailing 'failed' tasks since the last 'completed' one.     *
 *                                                                   *
 * $rows is ordered oldest -> newest. Anchoring on the last success  *
 * is what makes the bound CONSECUTIVE rather than lifetime, so a    *
 * risk that succeeds once is fully rehabilitated. 'canceled' is not *
 * a failure — that is precisely how the capability-enable reset      *
 * neutralises past attempts without erasing the history.            *
 *********************************************************************/
function ai_risk_consecutive_failures_from_rows(array $rows): int
{
    $failures = 0;

    foreach ($rows as $row) {
        $status = (string)($row['status'] ?? '');

        if ($status === 'completed') {
            $failures = 0;
        } elseif ($status === 'failed') {
            $failures++;
        }
        // pending / in_progress / canceled: not a verdict, leave the count alone.
    }

    return $failures;
}

/*********************************************************************
 * FUNCTION: AI RISK IS STALE                                        *
 * Has this risk changed since its last analysis, and has that       *
 * change settled long enough to be worth spending a call on?        *
 *                                                                   *
 * A null $last_analysis means the risk has never been analysed —    *
 * that case belongs to task_check's "no row" clause, not here, so   *
 * it is deliberately NOT stale.                                     *
 *                                                                   *
 * The debounce is the difference between one call per edit session  *
 * and one call per keystroke-save.                                  *
 *********************************************************************/
function ai_risk_is_stale(
    ?string $last_change,
    ?string $last_analysis,
    string $now,
    int $debounce_minutes = 15
): bool {
    if ($last_change === null || $last_change === '' || $last_analysis === null || $last_analysis === '') {
        return false;
    }

    $change   = strtotime($last_change);
    $analysis = strtotime($last_analysis);
    $nowTs    = strtotime($now);

    if ($change === false || $analysis === false || $nowTs === false) {
        return false;
    }

    if ($change <= $analysis) {
        return false;
    }

    return ($nowTs - $change) >= ($debounce_minutes * 60);
}

/*********************************************************************
 * FUNCTION: AI RISK INPUT FINGERPRINT                               *
 * A hash of everything the analysis prompt is built from.           *
 *                                                                   *
 * Backstop only: event-driven invalidation is the primary signal,   *
 * and this exists to catch what the hooks miss (a write path nobody *
 * hooked, or a neighbour type added later). Sorting the node ids    *
 * makes it order-independent, so ranking changes alone do not look  *
 * like input changes.                                               *
 *********************************************************************/
function ai_risk_input_fingerprint(array $context, array $risk_fields): string
{
    $ids = [];
    foreach (($context['nodes'] ?? []) as $node) {
        if (is_array($node) && isset($node['node_id'])) {
            $ids[] = (string)$node['node_id'];
        }
    }
    sort($ids);

    ksort($risk_fields);

    return hash('sha256', json_encode([$ids, $risk_fields]));
}

/*********************************************************************
 * FUNCTION: AI RISK RESPONSE SECTIONS PRESENT                       *
 * Which required <h4> sections a response is MISSING.               *
 *                                                                   *
 * Full re-analysis replaced an earlier delta design, so continuity  *
 * between successive analyses of the same risk has to come from the *
 * contract rather than from the model choosing to preserve its own  *
 * wording. Fixed sections are what make two runs comparable.        *
 *                                                                   *
 * Matching is case- and whitespace-insensitive because models drift *
 * on capitalisation, and rejecting a good answer over a capital     *
 * letter would cost a retry and two more AI calls.                  *
 *********************************************************************/
function ai_risk_response_missing_sections(string $html, array $required): array
{
    $haystack = preg_replace('/\s+/', ' ', strtolower(strip_tags($html)));

    $missing = [];
    foreach ($required as $section) {
        $needle = preg_replace('/\s+/', ' ', strtolower(trim($section)));
        if (strpos($haystack, $needle) === false) {
            $missing[] = $section;
        }
    }

    return $missing;
}

/*********************************************************************
 * FUNCTION: AI RISK DATA IS PRESENT                                 *
 * Pure predicate: did ai_prepare_risk_data() actually find a risk?  *
 *                                                                   *
 * get_risk_by_id() returns [] for an id that no longer exists, and  *
 * ai_prepare_risk_data() passes that straight back. Without this    *
 * check the prompt builders happily serialize [] into the request   *
 * and spend a real, paid AI call asking the model to analyze a      *
 * risk whose details are literally the two characters "[]". On a    *
 * backlogged queue that is not hypothetical: risks are routinely    *
 * deleted between task_check queueing them and the promise stage    *
 * running hours later.                                              *
 *********************************************************************/
function ai_risk_data_is_present($risk): bool
{
    return is_array($risk) && isset($risk[0]) && is_array($risk[0]) && $risk[0] !== [];
}

/*********************************************************************
 * FUNCTION: AI RISK RAW ID                                          *
 * Translate a 1000-offset display risk id to the raw risks.id this  *
 * job's DB queries and ai_get_context() key on. One named conversion*
 * instead of inline `$risk_id - 1000` at every call site:           *
 * risks_associated_with()'s docblock documents an earlier version   *
 * of this exact subtraction silently resolving the WRONG risk       *
 * (42 -> -958, dropped; or raw 1200 read back as display 1200 -> the*
 * wrong record) when a caller mis-signed or omitted it.             *
 *********************************************************************/
function ai_risk_raw_id(int $display_risk_id): int
{
    return $display_risk_id - 1000;
}

/*********************************************************************
 * FUNCTION: AI RISK IDENTITY CANDIDATES                             *
 * Pure: the ordered list of user ids whose visibility may stand in  *
 * for "who is this analysis for", most-specific first.              *
 *                                                                   *
 * ai_get_context() scopes the graph to the caller's L2/L3/L4        *
 * permissions, but the queue worker runs as System User (uid -1, no *
 * permissions), which would yield an empty graph. Background        *
 * analysis therefore has to borrow somebody's visibility.           *
 *                                                                   *
 * The requester is preferred whenever one exists: when a user       *
 * triggers analysis from the risk view, scoping to THEM means the   *
 * generated prose can never describe a neighbor they could not      *
 * already look up. Only the cron sweep, which has no requester at   *
 * all, falls back to the risk's own people.                         *
 *                                                                   *
 * Duplicates are collapsed so a risk whose owner is also its        *
 * manager doesn't get probed twice.                                 *
 *********************************************************************/
function ai_risk_identity_candidates(array $risk, int $requested_by_uid = 0): array
{
    $candidates = [];

    foreach (array_merge([$requested_by_uid], array_map(
        fn($field) => (int)($risk[$field] ?? 0),
        ['owner', 'manager', 'submitted_by']
    )) as $uid) {
        if ($uid > 0 && !in_array($uid, $candidates, true)) {
            $candidates[] = $uid;
        }
    }

    return $candidates;
}

?>
