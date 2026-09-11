<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/functions.php'));

/**
 * Data Integrity framework: staging-table CRUD, detector registry, and the
 * detector implementations themselves. See
 * docs/superpowers/specs/2026-08-26-data-integrity-framework-design.md.
 */

/**
 * Insert a newly-detected issue, or refresh/reopen an existing one on the
 * same natural key (issue_type, table_name, column_name, record_id). Never
 * creates a duplicate row for the same finding across rescans.
 */
function upsert_data_integrity_issue(
    PDO $db,
    string $issue_type,
    string $table_name,
    string $column_name,
    string $record_id,
    ?string $broken_value,
    ?string $suggested_value
): void {
    $stmt = $db->prepare("
        INSERT INTO `data_integrity_issues`
            (`issue_type`, `table_name`, `column_name`, `record_id`, `broken_value`, `suggested_value`, `status`, `detected_at`, `resolved_at`)
        VALUES
            (:issue_type, :table_name, :column_name, :record_id, :broken_value, :suggested_value, 'open', NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            `broken_value` = VALUES(`broken_value`),
            `suggested_value` = VALUES(`suggested_value`),
            `detected_at` = NOW(),
            `status` = 'open',
            `resolved_at` = NULL
    ");
    $stmt->bindValue(':issue_type', $issue_type, PDO::PARAM_STR);
    $stmt->bindValue(':table_name', $table_name, PDO::PARAM_STR);
    $stmt->bindValue(':column_name', $column_name, PDO::PARAM_STR);
    $stmt->bindValue(':record_id', $record_id, PDO::PARAM_STR);
    $stmt->bindValue(':broken_value', $broken_value, $broken_value === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
    $stmt->bindValue(':suggested_value', $suggested_value, $suggested_value === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
    $stmt->execute();
}

/**
 * @return array<int, array{id:int, issue_type:string, table_name:string, column_name:string, record_id:string, broken_value:?string, suggested_value:?string, status:string, detected_at:string}>
 */
// The admin review page renders every returned row in one pass (no
// pagination UI yet -- see the architecture note in
// api_v2_data_integrity_issues_list()). This cap bounds the worst case (a
// pathological import leaving tens of thousands of corrupted rows) without
// affecting realistic volumes, which run in the tens to low hundreds.
const DATA_INTEGRITY_ISSUES_LIST_LIMIT = 1000;

function get_open_data_integrity_issues(PDO $db, ?string $issue_type = null, int $limit = DATA_INTEGRITY_ISSUES_LIST_LIMIT): array
{
    if ($issue_type !== null) {
        $stmt = $db->prepare("SELECT * FROM `data_integrity_issues` WHERE `status` = 'open' AND `issue_type` = :issue_type ORDER BY `detected_at` ASC LIMIT :limit");
        $stmt->bindValue(':issue_type', $issue_type, PDO::PARAM_STR);
    } else {
        $stmt = $db->prepare("SELECT * FROM `data_integrity_issues` WHERE `status` = 'open' ORDER BY `detected_at` ASC LIMIT :limit");
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function resolve_data_integrity_issue(PDO $db, int $id): bool
{
    $stmt = $db->prepare("UPDATE `data_integrity_issues` SET `status` = 'resolved', `resolved_at` = NOW() WHERE `id` = :id AND `status` = 'open'");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

function count_open_data_integrity_issues(?PDO $db = null, ?string $issue_type = null): int
{
    $owns_db = ($db === null);
    if ($owns_db) $db = db_open();

    if ($issue_type !== null) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM `data_integrity_issues` WHERE `status` = 'open' AND `issue_type` = :issue_type");
        $stmt->bindValue(':issue_type', $issue_type, PDO::PARAM_STR);
        $stmt->execute();
        $count = (int)$stmt->fetchColumn();
    } else {
        $count = (int)$db->query("SELECT COUNT(*) FROM `data_integrity_issues` WHERE `status` = 'open'")->fetchColumn();
    }

    if ($owns_db) db_close($db);
    return $count;
}

function data_integrity_has_open_issues(?PDO $db = null): bool
{
    return count_open_data_integrity_issues($db) > 0;
}

/**
 * Deletes 'resolved' rows older than $days (audit-trail retention). Returns
 * the number of rows purged.
 */
function purge_resolved_data_integrity_issues(PDO $db, int $days = 90): int
{
    $stmt = $db->prepare("DELETE FROM `data_integrity_issues` WHERE `status` = 'resolved' AND `resolved_at` < (NOW() - INTERVAL :days DAY)");
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount();
}

/**
 * Single source of truth for every Data Integrity detector. Adding a new
 * detector is one entry here plus its scan_fn (and apply_fn, if it can
 * auto-suggest a fix) -- no new job, table, Health Check, or notification
 * wiring required (those all read this registry / count_open_data_integrity_
 * issues() generically). The admin review UI is NOT generic: admin/
 * data_integrity.php and js/simplerisk/pages/data-integrity.js hardcode a
 * tab + table pair per known issue_type ('invalid_text_encoding',
 * 'file_content_mismatch') rather than iterating this registry -- a third
 * detector needs its own tab/table/render-path added there too, or its
 * findings will be returned by the API but never shown anywhere.
 *
 * repair_mode:
 *   'inline_text_edit' -- broken_value/suggested_value are populated; the
 *      review UI shows an editable suggested-fix field and apply_fn writes
 *      the admin-approved value back.
 *   'link_to_record' -- no suggested value is possible (e.g. corrupted
 *      binary content); the review UI shows the record's table/id instead,
 *      for the admin to go find manually (no viewer page exists yet).
 *      apply_fn is null; resolution is detected on the next scan.
 *
 * recheck_fn(PDO $db, array{table_name:string, column_name:string,
 * record_id:string} $issue): ?bool -- re-examines exactly the one row a
 * currently-open issue points at, used by resolve_stale_data_integrity_
 * issues() to auto-close issues a scan no longer reproduces. Returns
 * false when the row is confirmed no longer broken (or no longer exists --
 * nothing left to be broken), true when it's confirmed still broken, or
 * null when it can't be determined this run (e.g. a decrypt failure, or
 * the table/column is no longer a scan target). Only false resolves the
 * issue; true and null both leave it open. A detector that omits this key
 * (null) opts out of auto-reconciliation entirely -- its issues stay open
 * until repaired, the pre-existing behavior.
 */
function data_integrity_detectors(): array
{
    return [
        'invalid_text_encoding' => [
            'label_key'   => 'DataIntegrityTextEncoding',
            'repair_mode' => 'inline_text_edit',
            'scan_fn'     => 'scan_invalid_text_encoding',
            'apply_fn'    => 'apply_text_encoding_repair',
            'recheck_fn'  => 'recheck_invalid_text_encoding',
        ],
        'file_content_mismatch' => [
            'label_key'   => 'DataIntegrityFileEncoding',
            'repair_mode' => 'link_to_record',
            'scan_fn'     => 'scan_file_content_mismatch',
            'apply_fn'    => null,
            'recheck_fn'  => 'recheck_file_content_mismatch',
        ],
    ];
}

/**
 * (table, column) -> whether the column is encrypted-capable (must go
 * through try_decrypt()/try_encrypt()). This list IS the allow-list
 * apply_text_encoding_repair() validates against -- nothing outside it is
 * ever reachable from a repair request, regardless of what a caller sends.
 */
function data_integrity_text_encoding_scan_targets(): array
{
    return [
        'risks' => [
            'subject' => true, 'assessment' => true, 'notes' => true,
        ],
        'mitigations' => [
            'current_solution' => true, 'security_recommendations' => true, 'security_requirements' => true,
        ],
        'mgmt_reviews' => [
            'comments' => true,
        ],
        'assets' => [
            'name' => true, 'ip' => true, 'details' => true, 'location' => false,
        ],
        'asset_groups' => ['name' => false],
        'framework_controls' => [
            'short_name' => false, 'long_name' => false, 'description' => false,
            'supplemental_guidance' => false, 'control_number' => false,
        ],
        'framework_control_tests' => [
            'name' => false, 'objective' => false, 'test_steps' => false, 'expected_results' => false,
        ],
        'user' => [
            'username' => false, 'name' => false, 'email' => false,
        ],
        // Customization Extra -- Core must not query this unguarded.
        'custom_template_group' => ['name' => false],
    ];
}

/**
 * Every scan-target table's primary key column is `id` except `user`,
 * whose primary key column is `value` (see `DESCRIBE user`). Centralized
 * here so scan_invalid_text_encoding() and apply_text_encoding_repair()
 * never hardcode `id` for a table where that column doesn't exist.
 */
function data_integrity_text_encoding_primary_key(string $table): string
{
    return $table === 'user' ? 'value' : 'id';
}

/**
 * True when $raw has a genuine encoding problem worth flagging: invalid
 * UTF-8 byte sequences, or a stray control character that has no legitimate
 * place in GRC field data. Deliberately narrower than what
 * sanitize_import_cell_value() normalizes -- that function also trims
 * incidental leading/trailing whitespace, which is not a data integrity
 * problem. Flagging a pure whitespace/newline difference as "invalid text
 * encoding" produced a suggested-fix diff indistinguishable from doing
 * nothing, which is exactly what a real encoding corruption should never
 * look like.
 */
function has_invalid_text_encoding(string $raw): bool
{
    if (!mb_check_encoding($raw, 'UTF-8')) {
        return true;
    }

    return preg_match(STRAY_CONTROL_CHARACTER_PATTERN, $raw) === 1;
}

/**
 * data_integrity_detectors()'s recheck_fn for 'invalid_text_encoding'. Used
 * only by resolve_stale_data_integrity_issues() -- re-reads the single row a
 * stale open issue points at and re-applies the same encrypted-column
 * handling scan_invalid_text_encoding() uses, so the two never disagree on
 * what "still broken" means.
 *
 * Validates (table_name, column_name) against the same fixed allow-list
 * apply_text_encoding_repair() uses before building any SQL -- an issue's
 * staged table_name/column_name are scan-authored, not user-authored, but
 * still not trusted as literal SQL identifiers (CLAUDE.md: identifiers that
 * can't be bound must be validated against a fixed allow-list).
 */
function recheck_invalid_text_encoding(PDO $db, array $issue): ?bool
{
    $table = $issue['table_name'];
    $column = $issue['column_name'];

    $targets = data_integrity_text_encoding_scan_targets();
    if (!isset($targets[$table][$column])) {
        // No longer (or never) a scanned target -- e.g. the Customization
        // Extra was uninstalled after this issue was staged. Can't confirm
        // either way; leave the issue open rather than guess.
        return null;
    }

    if ($table === 'custom_template_group' && !table_exists('custom_template_group')) {
        return null;
    }

    $pk = data_integrity_text_encoding_primary_key($table);
    $stmt = $db->prepare("SELECT `{$column}` AS `value` FROM `{$table}` WHERE `{$pk}` = :id");
    $stmt->bindValue(':id', $issue['record_id'], PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        // The record is gone -- nothing left to be broken.
        return false;
    }

    $is_encrypted = $targets[$table][$column];
    if ($is_encrypted) {
        $raw = try_decrypt_or_null($row['value']);
        if ($raw === null) {
            // Same decrypt-failure case scan_invalid_text_encoding() skips
            // -- can't confirm the row is fixed, so don't resolve it on
            // missing information.
            return null;
        }
    } else {
        $raw = $row['value'];
    }

    if (!is_string($raw) || $raw === '') {
        return false;
    }

    return has_invalid_text_encoding($raw);
}

/**
 * data_integrity_detectors()'s recheck_fn for 'file_content_mismatch'. Mirrors
 * scan_file_content_mismatch()'s size<>LENGTH(content) predicate for exactly
 * the one row a stale open issue points at.
 */
function recheck_file_content_mismatch(PDO $db, array $issue): ?bool
{
    $table = $issue['table_name'];
    $allowed_tables = ['compliance_files', 'files', 'questionnaire_files'];

    if (!in_array($table, $allowed_tables, true)) {
        return null;
    }

    if ($table === 'questionnaire_files' && !table_exists('questionnaire_files')) {
        return null;
    }

    $stmt = $db->prepare("SELECT `size`, LENGTH(`content`) AS `content_length` FROM `{$table}` WHERE `unique_name` = :unique_name");
    $stmt->bindValue(':unique_name', $issue['record_id'], PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        // The record is gone -- nothing left to be broken.
        return false;
    }

    return ((int)$row['size']) !== ((int)$row['content_length']);
}

/**
 * Resolves every currently-open issue of $issue_type whose (table_name,
 * column_name, record_id) natural key was NOT among this scan's $found
 * results -- but only when $recheck_fn can positively confirm the specific
 * row is no longer broken (or no longer exists). A row $found simply
 * doesn't mention could mean "genuinely fixed" OR "this scan skipped it for
 * an unrelated reason" (e.g. scan_invalid_text_encoding() skips a row it
 * can't decrypt) -- treating every absence as "fixed" would silently close
 * an issue we never actually re-verified. $recheck_fn removes that
 * ambiguity by re-examining exactly that one row; only its `false` return
 * resolves the issue, `true` and `null` both leave it open.
 *
 * Deliberately queries every open row of this type directly rather than
 * going through get_open_data_integrity_issues() -- that function's default
 * limit (DATA_INTEGRITY_ISSUES_LIST_LIMIT) exists to bound what the admin
 * review UI renders in one pass, not what this internal reconciliation may
 * close.
 *
 * @param array<int, array{table_name:string, column_name:string, record_id:string}> $found
 * @return int Number of issues resolved
 */
function resolve_stale_data_integrity_issues(PDO $db, string $issue_type, callable $recheck_fn, array $found): int
{
    $found_keys = [];
    foreach ($found as $issue) {
        $found_keys[$issue['table_name'] . "\0" . $issue['column_name'] . "\0" . $issue['record_id']] = true;
    }

    $stmt = $db->prepare("SELECT `id`, `table_name`, `column_name`, `record_id` FROM `data_integrity_issues` WHERE `status` = 'open' AND `issue_type` = :issue_type");
    $stmt->bindValue(':issue_type', $issue_type, PDO::PARAM_STR);
    $stmt->execute();

    $resolved = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['table_name'] . "\0" . $row['column_name'] . "\0" . $row['record_id'];
        if (isset($found_keys[$key])) {
            continue;
        }

        if (recheck_data_integrity_issue_safely($recheck_fn, $db, $row) === false && resolve_data_integrity_issue($db, (int)$row['id'])) {
            $resolved++;
        }
    }

    return $resolved;
}

/**
 * Runs $recheck_fn and swallows any throw into null -- the same "can't
 * determine, leave the issue open" signal a recheck_fn returns for a known
 * unresolvable case (e.g. a decrypt failure). A DB-level failure (a dropped
 * connection, a table renamed between scan and reconciliation) must not
 * abort the rest of run_data_integrity_scan()'s per-detector loop, which
 * still has later detectors to scan/reconcile and the purge + notification
 * steps to run afterward. Mirrors apply_data_integrity_repair_safely()'s
 * rationale for apply_fn: log only the exception class and target
 * table/column, never the exception message, since some recheck targets
 * (user.username, user.email) are unencrypted plaintext PII a DB error
 * message can embed a fragment of.
 */
function recheck_data_integrity_issue_safely(callable $recheck_fn, PDO $db, array $issue): ?bool
{
    try {
        return $recheck_fn($db, $issue);
    } catch (\Throwable $e) {
        write_debug_log("data integrity reconciliation: recheck_fn threw for issue {$issue['id']} ({$issue['table_name']}.{$issue['column_name']}): " . get_class($e), 'warning');
        return null;
    }
}

/**
 * @return array<int, array{table_name:string, column_name:string, record_id:string, broken_value:string, suggested_value:string}>
 *
 * For encrypted-capable columns, broken_value/suggested_value in the
 * returned array are ALREADY re-encrypted before this function returns --
 * callers (upsert_data_integrity_issue()) never see plaintext for those
 * columns, so the staging table never holds an unencrypted copy of data the
 * Encrypted Database Extra is keeping encrypted at rest. Encryption is a
 * no-op when the extra isn't active, so this is safe unconditionally.
 *
 * Uses try_decrypt_or_null(), not try_decrypt(), because this runs from the
 * queue worker (no HTTP session): try_decrypt()'s failure path calls
 * set_alert(), a session write that has nothing to write into here. A row
 * that fails to decrypt (corrupted ciphertext / key mismatch -- a different
 * failure mode than this detector targets) is logged and skipped rather
 * than misreported as a plaintext-encoding issue.
 */
function scan_invalid_text_encoding(PDO $db): array
{
    $found = [];

    foreach (data_integrity_text_encoding_scan_targets() as $table => $columns) {
        if ($table === 'custom_template_group' && !table_exists('custom_template_group')) {
            continue;
        }

        $pk = data_integrity_text_encoding_primary_key($table);

        foreach ($columns as $column => $is_encrypted) {
            $stmt = $db->prepare("SELECT `{$pk}` AS `pk_value`, `{$column}` AS `value` FROM `{$table}` WHERE `{$column}` IS NOT NULL");
            $stmt->execute();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($is_encrypted) {
                    $raw = try_decrypt_or_null($row['value']);
                    if ($raw === null) {
                        write_debug_log("Data Integrity scan: could not decrypt {$table}.{$column} id={$row['pk_value']}; skipped.", "warning");
                        continue;
                    }
                } else {
                    $raw = $row['value'];
                }

                if (!is_string($raw) || $raw === '') {
                    continue;
                }

                if (!has_invalid_text_encoding($raw)) {
                    continue;
                }

                $suggested = sanitize_import_cell_value($raw);
                if ($suggested !== $raw) {
                    if ($is_encrypted) {
                        $broken_value = try_encrypt($raw);
                        $suggested_value = try_encrypt($suggested);

                        // try_encrypt() returns false (never plaintext) on a
                        // key/openssl failure. upsert_data_integrity_issue()'s
                        // broken_value/suggested_value params are ?string --
                        // passing false would be a TypeError that aborts the
                        // whole scan batch, not just this one row. Skip the
                        // row instead, matching the decrypt-failure handling
                        // above.
                        if ($broken_value === false || $suggested_value === false) {
                            write_debug_log("Data Integrity scan: could not encrypt {$table}.{$column} id={$row['pk_value']}; skipped.", "warning");
                            continue;
                        }
                    } else {
                        $broken_value = $raw;
                        $suggested_value = $suggested;
                    }

                    $found[] = [
                        'table_name'      => $table,
                        'column_name'     => $column,
                        'record_id'       => (string)$row['pk_value'],
                        'broken_value'    => $broken_value,
                        'suggested_value' => $suggested_value,
                    ];
                }
            }
        }
    }

    return $found;
}

/**
 * Runs $apply_fn and swallows any throw into a plain `false`, logging only
 * the exception class and the target table/column -- never the exception
 * message, which for a DB-level write failure (e.g. MySQL's "Incorrect
 * string value" / "Duplicate entry" errors) can embed a fragment of the
 * offending value, and some repair targets (user.username, user.email) are
 * unencrypted plaintext PII.
 *
 * Lifted out of api_v2_data_integrity_issues_bulk_repair()'s loop so a bad
 * row's apply_fn throwing is unit-testable directly with a fake throwing
 * callable, without needing to force a real DB-level failure over HTTP.
 */
function apply_data_integrity_repair_safely(callable $apply_fn, PDO $db, array $issue, string $new_value, int $id): bool
{
    try {
        return (bool)$apply_fn($db, $issue, $new_value);
    } catch (\Throwable $e) {
        write_debug_log("data integrity repair: apply_fn threw for issue {$id} ({$issue['table_name']}.{$issue['column_name']}): " . get_class($e), 'warning');
        return false;
    }
}

/**
 * Writes an admin-approved repair back to the real table/column. Validates
 * (table_name, column_name) against the fixed allow-list before building
 * any SQL -- staged data is scan-authored, not user-authored, but this is
 * still not trusted as a literal SQL identifier at write time.
 */
function apply_text_encoding_repair(PDO $db, array $issue, string $new_value): bool
{
    $targets = data_integrity_text_encoding_scan_targets();
    $table = $issue['table_name'];
    $column = $issue['column_name'];

    if (!isset($targets[$table][$column])) {
        return false;
    }

    // Extra-owned table -- mirror the scan side's guard. An issue for this
    // table can be approved after the Customization Extra has been
    // uninstalled (a real, if narrow, timing window between scan and admin
    // approval); without this check the UPDATE below would throw an
    // uncaught PDOException (Core connections use ERRMODE_EXCEPTION) rather
    // than failing the repair cleanly like every other "can't do this"
    // path in this function.
    if ($table === 'custom_template_group' && !table_exists('custom_template_group')) {
        write_debug_log("Data Integrity repair: {$table} does not exist (Customization Extra not installed); not writing.", "warning");
        return false;
    }

    // try_encrypt() returns false on a key/openssl failure (never plaintext
    // -- that changed in PR #1994, and was already possible before it via a
    // raw openssl_encrypt() failure). PDO binds false as "" on a string
    // column, which would silently blank the field with no error surfaced.
    // Treat it as a failed repair instead of writing anything.
    if ($targets[$table][$column]) {
        $encrypted = try_encrypt($new_value);
        if ($encrypted === false) {
            write_debug_log("Data Integrity repair: try_encrypt() failed for {$table}.{$column} id={$issue['record_id']}; not writing.", "error");
            return false;
        }
        $value_to_store = $encrypted;
    } else {
        $value_to_store = $new_value;
    }

    // One literal prepared statement per allow-listed (table, column) pair --
    // never string-interpolated identifiers. The WHERE clause uses each
    // table's real primary key column (`value` for `user`, `id` everywhere
    // else -- see data_integrity_text_encoding_primary_key()); the bind
    // placeholder is always named :id regardless of the column it targets.
    $sql_by_target = [
        'risks.subject'                        => "UPDATE `risks` SET `subject` = :v WHERE `id` = :id",
        'risks.assessment'                     => "UPDATE `risks` SET `assessment` = :v WHERE `id` = :id",
        'risks.notes'                          => "UPDATE `risks` SET `notes` = :v WHERE `id` = :id",
        'mitigations.current_solution'         => "UPDATE `mitigations` SET `current_solution` = :v WHERE `id` = :id",
        'mitigations.security_recommendations' => "UPDATE `mitigations` SET `security_recommendations` = :v WHERE `id` = :id",
        'mitigations.security_requirements'    => "UPDATE `mitigations` SET `security_requirements` = :v WHERE `id` = :id",
        'mgmt_reviews.comments'                => "UPDATE `mgmt_reviews` SET `comments` = :v WHERE `id` = :id",
        'assets.name'                          => "UPDATE `assets` SET `name` = :v WHERE `id` = :id",
        'assets.ip'                            => "UPDATE `assets` SET `ip` = :v WHERE `id` = :id",
        'assets.details'                       => "UPDATE `assets` SET `details` = :v WHERE `id` = :id",
        'assets.location'                      => "UPDATE `assets` SET `location` = :v WHERE `id` = :id",
        'asset_groups.name'                    => "UPDATE `asset_groups` SET `name` = :v WHERE `id` = :id",
        'framework_controls.short_name'        => "UPDATE `framework_controls` SET `short_name` = :v WHERE `id` = :id",
        'framework_controls.long_name'         => "UPDATE `framework_controls` SET `long_name` = :v WHERE `id` = :id",
        'framework_controls.description'       => "UPDATE `framework_controls` SET `description` = :v WHERE `id` = :id",
        'framework_controls.supplemental_guidance' => "UPDATE `framework_controls` SET `supplemental_guidance` = :v WHERE `id` = :id",
        'framework_controls.control_number'    => "UPDATE `framework_controls` SET `control_number` = :v WHERE `id` = :id",
        'framework_control_tests.name'             => "UPDATE `framework_control_tests` SET `name` = :v WHERE `id` = :id",
        'framework_control_tests.objective'        => "UPDATE `framework_control_tests` SET `objective` = :v WHERE `id` = :id",
        'framework_control_tests.test_steps'       => "UPDATE `framework_control_tests` SET `test_steps` = :v WHERE `id` = :id",
        'framework_control_tests.expected_results' => "UPDATE `framework_control_tests` SET `expected_results` = :v WHERE `id` = :id",
        'user.username'                        => "UPDATE `user` SET `username` = :v WHERE `value` = :id",
        'user.name'                            => "UPDATE `user` SET `name` = :v WHERE `value` = :id",
        'user.email'                           => "UPDATE `user` SET `email` = :v WHERE `value` = :id",
        'custom_template_group.name'           => "UPDATE `custom_template_group` SET `name` = :v WHERE `id` = :id",
    ];

    $key = "{$table}.{$column}";
    if (!isset($sql_by_target[$key])) {
        return false;
    }

    $stmt = $db->prepare($sql_by_target[$key]);
    $stmt->bindValue(':v', $value_to_store, PDO::PARAM_STR);
    $stmt->bindValue(':id', (int)$issue['record_id'], PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}

/**
 * Migrated from get_files_with_encoding_issues() in includes/functions.php
 * (the legacy admin/fix_upload_encoding_issues.php checker, which this
 * detector replaces and which was retired). Detection signature unchanged: a
 * stored `size` that doesn't match the actual byte-length of `content`
 * means the file's bytes were mangled by a charset conversion during
 * storage. There is no possible auto-suggested fix for corrupted binary
 * content -- repair_mode is 'link_to_record'; the admin re-uploads the file
 * through the normal UI.
 *
 * The legacy checker covers three separate file-storage tables sharing the
 * same unique_name/size/content shape (compliance_files, files,
 * questionnaire_files) -- this scans all three, not just compliance_files,
 * to avoid a coverage regression once the legacy checker is retired.
 * questionnaire_files is Assessments-Extra-owned; files and compliance_files
 * are Core.
 *
 * @return array<int, array{table_name:string, column_name:string, record_id:string, broken_value:null, suggested_value:null}>
 */
function scan_file_content_mismatch(PDO $db): array
{
    $found = [];

    $tables = ['compliance_files', 'files'];
    if (table_exists('questionnaire_files')) {
        $tables[] = 'questionnaire_files';
    }

    foreach ($tables as $table) {
        $stmt = $db->query("SELECT `unique_name` FROM `{$table}` WHERE `size` <> LENGTH(`content`)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $found[] = [
                'table_name'      => $table,
                'column_name'     => 'content',
                'record_id'       => $row['unique_name'],
                'broken_value'    => null,
                'suggested_value' => null,
            ];
        }
    }

    return $found;
}
