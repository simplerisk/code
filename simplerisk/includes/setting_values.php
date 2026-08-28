<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Pure coercion helpers for values read out of the `settings` table. No DB, no
// I/O, no language file — safe to require from anywhere, including a cron job
// that runs before the rest of the application is bootstrapped.
//
// `settings`.`value` is a string column and get_setting() returns whatever is
// stored verbatim (or its $default when the row is absent). Callers that feed
// such a value to a parameter with a scalar type declaration therefore need a
// coercion step, because PHP 8 rejects a non-numeric string for an int
// parameter with a TypeError rather than silently casting it.

/*********************************************************************
 * FUNCTION: FORMAT SETTING TIMESTAMP                                *
 * Render a settings-table value as a formatted date, for logging.   *
 *                                                                   *
 * Returns $fallback when the value cannot be a real timestamp: an   *
 * absent row (false/null), an existing-but-empty row (''), a        *
 * non-numeric string, or a non-positive number (0 means "never      *
 * stamped", not the Unix epoch).                                    *
 *                                                                   *
 * Why this exists: date()'s $timestamp parameter is typed ?int, so  *
 * date("Y-m-d H:i:s", '') throws a TypeError. When that happens     *
 * inside a queue job's task_check(), the exception propagates out   *
 * of the closure, the cron loader abandons that job, and the        *
 * capability silently stops running — on every tick, indefinitely.  *
 * core_ai_context_update failed this way 1,440 times a day for over *
 * a month with no symptom other than "the AI never produces         *
 * anything". A log line must never be able to kill its own job.     *
 *********************************************************************/
function format_setting_timestamp(
    mixed $value,
    string $format = "Y-m-d H:i:s",
    string $fallback = "never"
): string {
    if (!is_numeric($value)) {
        return $fallback;
    }

    $timestamp = (int)$value;

    if ($timestamp <= 0) {
        return $fallback;
    }

    return date($format, $timestamp);
}

/*********************************************************************
 * FUNCTION: COERCE SETTING TIMESTAMP                                *
 * A settings-table value as a Unix timestamp int, safe to pass to   *
 * an arithmetic expression or a typed int parameter. Non-numeric or *
 * empty (never stamped, or a corrupted value) coerces to 0 rather   *
 * than raising a TypeError — the same bug class documented on       *
 * format_setting_timestamp() above, for the arithmetic side instead *
 * of the logging side: `time() - $last_check` on a non-numeric      *
 * string throws just as surely as date() does.                      *
 *********************************************************************/
function coerce_setting_timestamp(mixed $value): int
{
    return is_numeric($value) ? (int)$value : 0;
}

?>
