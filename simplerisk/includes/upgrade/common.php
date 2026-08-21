<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*******************************************************************************
 * UPGRADE-ONLY SHARED HELPERS                                                  *
 *******************************************************************************
 * ADMISSION RULE: a function belongs in this file only if EVERY call site is
 * part of the upgrade process — a release `upgrade_from_*` function, one of the
 * two upgrade drivers in includes/upgrade.php, or another helper in this file.
 *
 * Anything a runtime page, API endpoint, or cron job ALSO calls belongs in
 * includes/functions.php instead. `table_exists()`, `field_exists_in_table()`,
 * and `index_exists_on_table()` are the standing example: they look like
 * migration plumbing, but they have roughly 277 call sites outside the upgrade
 * process — `table_exists()` in particular is the guard CLAUDE.md mandates for
 * the Core/Extra database boundary. Moving those here would make hundreds of
 * runtime paths depend on an upgrade file.
 *
 * This file exists because includes/upgrade.php passed 11,000 lines and the
 * practice of extracting named, directly-testable helpers out of the release
 * functions had nowhere to put them.
 *
 * It also holds the standing integrity checks both upgrade drivers call once
 * the database reaches the application version -- see
 * run_upgrade_integrity_checks() below -- so a reader who lands there first
 * has a file-level cue for what else is in scope here.
 *
 * LOAD ORDER: includes/upgrade.php requires this file AFTER functions.php. The
 * requires below are belt-and-suspenders per CLAUDE.md's function-reachability
 * rule — require_once makes a duplicate include a no-op.
 *******************************************************************************/

require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../authenticate.php'));
require_once(realpath(__DIR__ . '/../licensing.php'));



/**
 * Does a named constraint, or any constraint of a given type, exist on a table?
 *
 * table_exists() / field_exists_in_table() / index_exists_on_table() in
 * includes/functions.php cover tables, columns and indexes; a migration that adds
 * a PRIMARY KEY or a FOREIGN KEY has to introspect TABLE_CONSTRAINTS instead, and
 * that query was written out inline at each site until it appeared twice.
 *
 * This one lives here rather than beside those siblings because every call site is
 * part of the upgrade process -- the admission rule at the top of this file.
 *
 * Unlike those siblings this takes the caller's $db rather than opening its own.
 * Both callers are mid-migration with a connection already in hand, and a
 * migration that opens a second connection per guard is the kind of detail that
 * only shows up as a connection-limit failure on a large upgrade.
 *
 * @param PDO         $db              An open connection.
 * @param string      $table           Table to inspect. Not user input — every
 *                                     caller passes a literal — but it is bound
 *                                     rather than interpolated regardless.
 * @param string      $constraint_type 'PRIMARY KEY', 'FOREIGN KEY', 'UNIQUE'.
 * @param string|null $constraint_name Restrict to this constraint name; null
 *                                     matches any constraint of the type, which
 *                                     is what a PRIMARY KEY check wants (its
 *                                     name is always 'PRIMARY').
 */
function constraint_exists_on_table($db, $table, $constraint_type, $constraint_name = null) {

    $sql = "
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND CONSTRAINT_TYPE = :constraint_type
    ";

    if ($constraint_name !== null) {
        $sql .= " AND CONSTRAINT_NAME = :constraint_name";
    }

    $sql .= " LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":table", $table, PDO::PARAM_STR);
    $stmt->bindParam(":constraint_type", $constraint_type, PDO::PARAM_STR);

    if ($constraint_name !== null) {
        $stmt->bindParam(":constraint_name", $constraint_name, PDO::PARAM_STR);
    }

    $stmt->execute();

    return (bool)$stmt->fetchColumn();
}

/**
 * Read a column's actual definition, or null when the table/column is absent.
 *
 * `field_exists_in_table()` answers "is it there"; a migration that converges a
 * column's SHAPE needs to know what the shape currently is. That matters most
 * for the case this file exists to serve: an instance arriving from an unknown
 * release. Guarding on the real definition means a migration can be a no-op on
 * the instances that already match -- which is the difference between "safe to
 * re-run" and "rebuilds a table holding document blobs on every upgrade".
 *
 * Returns the raw information_schema row: DATA_TYPE, IS_NULLABLE ('YES'/'NO'),
 * COLUMN_DEFAULT (null when there is none), CHARACTER_MAXIMUM_LENGTH, EXTRA.
 *
 * @return array<string,mixed>|null
 */
function column_attributes($db, $table, $column)
{
    $database = DB_DATABASE;

    $stmt = $db->prepare(
        "SELECT `DATA_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `CHARACTER_MAXIMUM_LENGTH`, `EXTRA`
           FROM `information_schema`.`COLUMNS`
          WHERE `TABLE_SCHEMA` = :database
            AND `TABLE_NAME`   = :table
            AND `COLUMN_NAME`  = :column
          LIMIT 1;"
    );
    $stmt->bindParam(":database", $database, PDO::PARAM_STR);
    $stmt->bindParam(":table", $table, PDO::PARAM_STR);
    $stmt->bindParam(":column", $column, PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/*******************************************************************************
 * UPGRADE DISPATCH PREDICATES                                                  *
 *******************************************************************************
 * The four functions below decide what the upgrade drivers do, rather than
 * performing a migration themselves. They are pure -- no DB, no globals, no
 * output -- which is the point: the drivers that consume them open a database,
 * echo HTML and self-recurse, so the decisions are only testable once they are
 * lifted out.
 *
 * They satisfy the admission rule at the top of this file: every call site is
 * includes/upgrade.php's two drivers, update_database_version(), the Upgrade
 * Extra's dispatch loop (via the upgrade_chain_did_advance() shim in
 * extras/upgrade/backwards_compatibility.php), or a test. No runtime page, API
 * endpoint, or cron job calls any of them.
 *
 * Watch that boundary if is_release_version_string() ever picks up a caller
 * outside the upgrade process -- api/v2/includes/simplerisk.php still validates
 * an admin-POSTed version with its own inline copy of the pattern, and switching
 * it to this helper would give this function a runtime caller and move it to
 * includes/functions.php under the same rule.
 *******************************************************************************/

/***************************************
 * FUNCTION: IS RELEASE VERSION STRING *
 ***************************************/
/**
 * True only for a real release version, YYYYMMDD-VVV -- one of the strings that
 * actually appears in $releases. The unreleased placeholder every in-flight
 * upgrade function targets ('2026XXXX-001') is deliberately NOT one: it names no
 * release, so a db_version carrying it matches nothing in $releases afterwards.
 *
 * Pure -- no DB, no globals, no output -- so the decision is testable without
 * touching a real db_version row.
 *
 * Anchored with \z rather than $, because PCRE's `$` also matches immediately
 * before a trailing newline and "20260709-001\n" would satisfy it.
 *
 * @param mixed $version
 * @return bool
 */
function is_release_version_string($version)
{
    return is_string($version) && preg_match("/^\d{8}-\d{3}\z/", $version) === 1;
}

/************************************
 * FUNCTION: UPGRADE CHAIN ADVANCED *
 ************************************/
/**
 * Did an upgrade function actually move the database forward?
 *
 * Only a real release version counts. update_database_version() refuses to write
 * anything else, so an unchanged version, the unreleased placeholder, and a
 * degenerate read (current_version("db") returns null when the db_version row is
 * missing -- functions.php reads $array[0]['value'] unguarded) all mean nothing
 * moved. False therefore means "stop", never "dispatch again": re-resolving from
 * an unchanged version yields the same function forever.
 *
 * Pure, and shared -- each of the three drivers that dispatch upgrade functions
 * used to carry its own copy of this comparison.
 *
 * @param mixed $db_version_before Version dispatched on.
 * @param mixed $db_version_after  Version read back after the function ran.
 * @return bool
 */
function upgrade_chain_advanced($db_version_before, $db_version_after)
{
    if (!is_release_version_string($db_version_after))
    {
        return false;
    }

    return (string)$db_version_before !== $db_version_after;
}

/*************************************
 * FUNCTION: IS NEWEST KNOWN RELEASE *
 *************************************/
/**
 * True when $version is the last entry in $releases -- the newest release this
 * copy of the code carries a migration path to, so there is nothing further to
 * apply regardless of what APP_VERSION says.
 *
 * Takes the array rather than reaching for the global so the decision stays pure.
 *
 * @param mixed $version
 * @param array $releases
 * @return bool
 */
function is_newest_known_release($version, array $releases)
{
    if ($releases === [])
    {
        return false;
    }

    return $version === $releases[array_key_last($releases)];
}

/********************************************
 * FUNCTION: DECIDE DATABASE UPGRADE ACTION *
 ********************************************/
/**
 * What upgrade_database() should do for a given pair of versions. Pure -- no DB,
 * no globals, no output -- so the whole decision matrix is testable directly.
 *
 * Returns one of:
 *
 *   'run_chain'          The database is behind the application and an upgrade
 *                        function exists for it. Ordinary case: run it, then
 *                        continue up the chain.
 *
 *   'run_in_flight'      The versions already match, but an upgrade function
 *                        exists FOR the current version. Two different situations
 *                        arrive here, and they are told apart afterwards by
 *                        upgrade_chain_advanced() rather than in advance:
 *
 *                        (a) Development. The function for the current version is
 *                            the one still being written this cycle. It targets
 *                            the '2026XXXX-001' placeholder, which
 *                            update_database_version() refuses to write, so
 *                            db_version does not move. Running it is how a
 *                            developer applies in-flight changes and re-applies
 *                            them for validation.
 *
 *                        (b) A one-click upgrade on a customer instance.
 *                            APP_VERSION is a PHP constant, and both one-click
 *                            drivers resolve it BEFORE the file swap
 *                            (extras/upgrade/index.php, includes/api.php), so it
 *                            still names the OLD release while the upgrade.php on
 *                            disk belongs to the NEW bundle. $app_version does not
 *                            describe the code that is running: the versions only
 *                            appear to match, and the resolved function is an
 *                            ordinary released migration that DOES advance
 *                            db_version.
 *
 *                        Earlier revisions of this docblock claimed (b) could not
 *                        happen -- that a shipped bundle never carries an
 *                        upgrade_from_<APP_VERSION>. That is true of the bundle
 *                        and irrelevant here, because the APP_VERSION being
 *                        compared is the previous release's. Do not restore the
 *                        claim; nothing gates this branch to development.
 *
 *                        Both are safe to run: every upgrade function is required
 *                        to be idempotent. The caller halts for (a) and continues
 *                        up the chain for (b).
 *
 *   'up_to_date'         Nothing further to apply -- either the versions match and
 *                        no function exists for the current one, or the database
 *                        is already at the newest release in $releases and only a
 *                        stale in-memory $app_version makes the two look different
 *                        (the tail of case (b) above).
 *
 *   'no_upgrade_function' The database version is behind but names no release we
 *                        have a function for -- a hand-edited row, a partially
 *                        restored backup, or an instance stranded by the guard
 *                        bug this change fixes. Deliberately NOT repaired here:
 *                        we cannot tell which schema is actually behind such a
 *                        value, and writing a guessed version would assert a
 *                        state nobody has checked.
 *
 * @param mixed $app_version
 * @param mixed $db_version
 * @param bool  $upgrade_function_exists Whether a callable upgrade function was
 *                                       resolved for $db_version.
 * @param bool  $db_is_newest_release    Whether $db_version is the last entry in
 *                                       $releases -- see is_newest_known_release().
 * @return string
 */
function decide_database_upgrade_action($app_version, $db_version, $upgrade_function_exists, $db_is_newest_release = false)
{
    if ($app_version != $db_version)
    {
        if ($upgrade_function_exists)
        {
            return 'run_chain';
        }

        // The database is at the newest release we know how to reach, so there is
        // nothing left to apply and this is not a fault to report. Reached at the
        // end of a one-click upgrade, where $app_version is the pre-swap constant.
        if ($db_is_newest_release)
        {
            return 'up_to_date';
        }

        return 'no_upgrade_function';
    }

    return $upgrade_function_exists ? 'run_in_flight' : 'up_to_date';
}

/**********************************************************
 * FUNCTION: MIGRATE CONTROL STATUS TO NOT NULL           *
 **********************************************************
 * Makes framework_controls.control_status NOT NULL DEFAULT 2 ("Not Tested"),
 * backfilling any pre-existing NULLs to 2 first.
 *
 * Extracted from upgrade_from_20260709001() so the migration can be exercised
 * directly by tests rather than by a copy of its SQL — a copy would keep passing
 * if this were changed or deleted.
 *
 * WHY: NULL was never a modeled state. No UI offers an "unknown" choice, and the
 * column has defaulted to 2 since earlier in this same upgrade — NULL is reachable
 * only through an explicit `INSERT ... control_status = NULL`. But a NULL row was
 * actively harmful, because the two halves of the controls table disagree about it:
 *
 *   - DISPLAY folds NULL into "Not Tested" (governance.php's
 *     `CASE control_status WHEN 1 ... ELSE 'Not Tested' END`, mirrored by the v2
 *     API row shaper's `?? 2`), so the row renders as Not Tested; while
 *   - FILTERING and the status COUNT chips both match on `control_status = 2` in
 *     SQL, which is never true for NULL.
 *
 * So a NULL row was visible in the unfiltered table and vanished the moment the
 * user filtered by the very status it displayed, and the chips under-reported.
 * Collapsing NULL into 2 at the schema level removes the ambiguity at its source,
 * rather than teaching every filter and count to special-case NULL.
 *
 * ORDERING IS LOAD-BEARING: the backfill must run BEFORE the ALTER. This instance
 * has no NULL rows, but a customer upgrading from older data may — and the ALTER
 * would fail outright on their database if the NULLs were still there.
 *
 * Idempotent: the UPDATE matches no rows on a re-run, and the ALTER is skipped once
 * INFORMATION_SCHEMA reports the column is already NOT NULL with a default of 2.
 * The backfill is deliberately left unguarded (it is a no-op UPDATE on a re-run) so
 * a run that died between the UPDATE and the ALTER still backfills on the retry.
 */
function migrate_control_status_not_null($db) {

    // The column is added by a much earlier upgrade function; guard so this is a
    // no-op rather than an error on a chain that has not reached that point.
    if (!field_exists_in_table('control_status', 'framework_controls')) return;

    // Step 1 — backfill. NULL and "Not Tested" are the same thing.
    $db->query("UPDATE `framework_controls` SET `control_status` = 2 WHERE `control_status` IS NULL;");

    // Step 2 — only ALTER when the column is not already in the target shape.
    $stmt = $db->prepare("
        SELECT `IS_NULLABLE`, `COLUMN_DEFAULT`
        FROM `information_schema`.`COLUMNS`
        WHERE `TABLE_SCHEMA` = :database
            AND `TABLE_NAME` = 'framework_controls'
            AND `COLUMN_NAME` = 'control_status';
    ");
    $database = DB_DATABASE; // a variable, because bindParam takes it by reference
    $stmt->bindParam(":database", $database, PDO::PARAM_STR);
    $stmt->execute();
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column && $column['IS_NULLABLE'] === 'NO' && (string)$column['COLUMN_DEFAULT'] === '2') return;

    $db->query("ALTER TABLE `framework_controls` MODIFY `control_status` tinyint(1) NOT NULL DEFAULT 2;");
    echo "Set framework_controls.control_status to NOT NULL DEFAULT 2 (Not Tested)<br />\n";
}

/**********************************************************
 * FUNCTION: MIGRATE CONTROL APPLICABILITY SCHEMA         *
 **********************************************************
 * Creates the per-framework control applicability schema behind the Statement of
 * Applicability: the decision table, the customer-extendable reason list, and the
 * two framework-level SoA fields.
 *
 * Extracted from upgrade_from_20260709001() so the migration can be exercised
 * directly by tests rather than by a copy of its SQL — a copy would keep passing
 * if this were changed or deleted.
 *
 * WHY A SEPARATE TABLE, KEYED ON (framework, control_id):
 *
 * Applicability must NOT live on `framework_control_mappings`. Those rows are
 * derived data owned by the SCF importer, with an auto-increment `id` that changes
 * whenever they are rebuilt — and three separate paths delete and recreate them:
 *
 *   - remove_framework_from_controls()   (includes/governance.php)  — framework removal
 *   - the SCF Extra's forced-reuse path  (extras/complianceforgescf/index.php)
 *   - every SCF version upgrade          (extras/complianceforgescf/upgrade.php)
 *
 * An exclusion justification is the opposite of derived data: it is a deliberate,
 * audited human decision a customer may have to defend to an auditor years later.
 * Hanging it off a mapping row would mean a routine SCF upgrade silently destroyed
 * it — a compliance incident with no error message anywhere. Keyed on the stable
 * business key instead, a mapping rebuild is a non-event, and a control that stops
 * being mapped into a framework goes DORMANT rather than deleted (an auditor may
 * still ask about last year's exclusion).
 *
 * APPLICABLE IS STILL THE DEFAULT AND IS STILL NEVER MATERIALISED. There is
 * deliberately no backfill here: no 1,535-row materialisation per framework,
 * exclusion is always an explicit audited act, and there is nothing for a rebuild
 * to lose. Absence of a row still means applicable, and DELETE still means "reset
 * to the framework default".
 *
 * WHY `state` NOW CARRIES 'applicable' AS WELL AS THE TWO DEVIATIONS (spec §4).
 * An applicable control may now carry its OWN justification and inclusion
 * reason(s) instead of printing the framework's boilerplate 90 times over, and
 * storing that needs a row. The storage contract is therefore:
 *
 *   no row                  applicable, framework-default justification
 *   state = 'applicable'    applicable, with THIS narrative and/or reason(s)
 *   state = 'not_applicable'/'inherited'   excluded / inherited, as before
 *   DELETE the row          reset to the framework default
 *
 * `narrative` becomes NULLABLE for the same reason: a justification is OPTIONAL
 * for an applicable control (a taxonomy reason alone is a complete answer), while
 * assert_applicability_narrative() continues to refuse a deviation without one.
 * Storing '' instead would make "no narrative" and "deliberately blank narrative"
 * indistinguishable — the absent-vs-empty confusion that produced nine data-loss
 * bugs on this branch.
 *
 * REASONS ARE MULTI-SELECT, via `framework_control_applicability_reasons`. A
 * control is commonly included for more than one reason (legal AND results of
 * risk assessment), and forcing a single choice would make the document less
 * truthful. The composite PRIMARY KEY makes a duplicate reason impossible by
 * construction rather than by convention, and ON DELETE CASCADE means the
 * existing delete-to-reset semantics clear the reasons too.
 *
 * The scalar `framework_control_applicability`.`reason_id` is GONE (Task 4). It
 * never shipped, so nothing is migrated for a customer; what the drop is guarded
 * for is the development and testing instances that already ran this branch's
 * schema and hold values in it. Those values are folded into the join table
 * first — see the note at the DROP — and then the column goes, because the
 * writer, the reader and the v2 API now read the join table exclusively.
 *
 * Idempotent: every CREATE is table_exists()-guarded, every ADD COLUMN is
 * field_exists_in_table()-guarded, every MODIFY is skipped once INFORMATION_SCHEMA
 * reports the column already in its target shape, and the reason seed is
 * existence-checked row by row. The seed deliberately does NOT use INSERT IGNORE —
 * `name` carries no UNIQUE key (the list is customer-extendable), so there would be
 * no duplicate-key error for IGNORE to swallow and every re-run would insert
 * another full copy.
 */
function migrate_control_applicability_schema($db) {

    // The column's CURRENT shape, straight from INFORMATION_SCHEMA, so each
    // MODIFY below can be skipped once it has already been applied. Returns null
    // when the column does not exist, which lets a caller no-op rather than error
    // on a chain that has not created the table yet.
    //
    // The comparison is against the WHOLE target definition rather than a
    // substring search for the new member: `enum('not_applicable','inherited')`
    // already contains the text "applicable", so a naive strpos() would report the
    // migration as already done on an unmigrated column and silently skip it.
    $column_shape = static function ($table, $column) use ($db) {

        $stmt = $db->prepare("
            SELECT `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :database
                AND `TABLE_NAME` = :table
                AND `COLUMN_NAME` = :column;
        ");
        $database = DB_DATABASE; // a variable, because bindParam takes it by reference
        $stmt->bindParam(":database", $database, PDO::PARAM_STR);
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->bindParam(":column", $column, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    };

    // Configurable exclusion/inheritance/inclusion reasons, following the existing
    // `control_class` option-table pattern (value INT PK, name MEDIUMTEXT) so
    // customers can extend the list. `applies_to` says which state offers a
    // reason: a third party performing the control is an "inherited" reason,
    // not an exclusion, and "Contractual obligation" is a reason a control is IN
    // scope rather than out of it.
    if (!table_exists('control_applicability_reason')) {
        $db->prepare("
            CREATE TABLE IF NOT EXISTS `control_applicability_reason` (
                `value` INT NOT NULL AUTO_INCREMENT,
                `name` MEDIUMTEXT NOT NULL,
                `applies_to` ENUM('applicable','not_applicable','inherited') NOT NULL DEFAULT 'not_applicable',
                PRIMARY KEY (`value`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ")->execute();
        echo "Created the control_applicability_reason table.<br />\n";
    }

    // Instances already carrying this branch have the two-member enum. Widen it
    // BEFORE the seed below runs, or the four inclusion reasons are rejected as
    // out-of-range values by strict SQL mode.
    //
    // DEFAULT 'not_applicable' is restated deliberately: a bare MODIFY drops the
    // column default, and the reason list is customer-extendable through a form
    // that may not send `applies_to` at all.
    $applies_to = $column_shape('control_applicability_reason', 'applies_to');
    if ($applies_to !== null
        && strtolower((string)$applies_to['COLUMN_TYPE']) !== "enum('applicable','not_applicable','inherited')") {

        $db->prepare("
            ALTER TABLE `control_applicability_reason`
            MODIFY `applies_to` ENUM('applicable','not_applicable','inherited') NOT NULL DEFAULT 'not_applicable';
        ")->execute();
        echo "Extended control_applicability_reason.applies_to to offer inclusion reasons.<br />\n";
    }

    // Existence-checked seed — see the INSERT IGNORE note in the docblock.
    //
    // The four 'applicable' rows are the standard SoA inclusion drivers (ISO/IEC
    // 27001 6.1.3(d)). "Results of risk assessment" is already implemented in
    // substance by the existing risk linkage; naming it makes the reasoning
    // explicit and consistent with the other three.
    //
    // Seeded as literals, NOT through $lang, because this is a customer-extendable
    // option table: `name` is customer-authored free text that every reader
    // (get_applicability_reasons(), the API, the modal picklist) renders straight
    // from the row, exactly as `control_class` does. A key would render as a key
    // for the customer rows that will never have one.
    $seed = [
        ['Not applicable to the defined scope',            'not_applicable'],
        ['No such asset or technology',                    'not_applicable'],
        ['Function not performed by the organization',     'not_applicable'],
        ['Legal or regulatory requirement does not apply', 'not_applicable'],
        ['Risk accepted',                                  'not_applicable'],
        ['Covered by another control',                     'not_applicable'],
        ['Performed by a third party',                     'inherited'],
        ['Legal or regulatory requirement',                'applicable'],
        ['Contractual obligation',                         'applicable'],
        ['Business requirement or best practice',          'applicable'],
        ['Results of risk assessment',                     'applicable'],
    ];
    $check = $db->prepare("SELECT COUNT(*) FROM `control_applicability_reason` WHERE `name` = :n");
    $ins   = $db->prepare("INSERT INTO `control_applicability_reason` (`name`, `applies_to`) VALUES (:n, :a)");
    foreach ($seed as list($name, $applies_to_value)) {
        $check->execute([':n' => $name]);
        if ((int)$check->fetchColumn() === 0) {
            $ins->execute([':n' => $name, ':a' => $applies_to_value]);
        }
    }

    if (!table_exists('framework_control_applicability')) {
        $db->prepare("
            CREATE TABLE IF NOT EXISTS `framework_control_applicability` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `framework` INT NOT NULL,
                `control_id` INT NOT NULL,
                `state` ENUM('applicable','not_applicable','inherited') NOT NULL,
                `narrative` TEXT NULL,
                `provider` VARCHAR(200) NOT NULL DEFAULT '',
                `decided_by` INT NOT NULL,
                `decided_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_framework_control` (`framework`, `control_id`),
                KEY `control_id` (`control_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ")->execute();
        echo "Created the framework_control_applicability table.<br />\n";
    }

    // Instances already carrying this branch have the pre-§4 shape: a two-member
    // `state` enum and a NOT NULL `narrative`. Both MODIFYs are guarded by the
    // column's own current definition, so a re-run is a no-op.
    $state = $column_shape('framework_control_applicability', 'state');
    if ($state !== null
        && strtolower((string)$state['COLUMN_TYPE']) !== "enum('applicable','not_applicable','inherited')") {

        $db->prepare("
            ALTER TABLE `framework_control_applicability`
            MODIFY `state` ENUM('applicable','not_applicable','inherited') NOT NULL;
        ")->execute();
        echo "Extended framework_control_applicability.state to store the applicable state.<br />\n";
    }

    $narrative = $column_shape('framework_control_applicability', 'narrative');
    if ($narrative !== null && strtoupper((string)$narrative['IS_NULLABLE']) !== 'YES') {

        $db->prepare("ALTER TABLE `framework_control_applicability` MODIFY `narrative` TEXT NULL;")->execute();
        echo "Made framework_control_applicability.narrative nullable.<br />\n";
    }

    // Inclusion/exclusion reasons are MULTI-select, so they hang off a join table
    // rather than the scalar `reason_id`. The composite PRIMARY KEY makes a
    // duplicate reason impossible by construction — worth stating explicitly,
    // because a sibling table on this branch relied on INSERT IGNORE without a
    // unique key and silently failed to dedupe.
    //
    // ON DELETE CASCADE is what keeps delete-to-reset honest: resetting a control
    // to the framework default deletes the applicability row, and the reasons that
    // justified the old decision must not outlive it.
    //
    // The reason side gets a foreign key too, and RESTRICT rather than CASCADE.
    // The two directions are not symmetric:
    //   - applicability_id CASCADEs because the decision owning the reason is
    //     gone, so the reason has nothing left to justify.
    //   - reason_id RESTRICTs because the taxonomy row is shared. CASCADE there
    //     would let removing one entry from the picklist silently strip the
    //     justification off every control citing it -- in an SoA, an auditable
    //     document, that is the worst available outcome. RESTRICT makes such a
    //     delete fail loudly while the reason is still in use, which is the
    //     correct prompt: retire it from the controls first.
    // RESTRICT is InnoDB's default, but it is spelled out because the choice is
    // deliberate and sits one line under a CASCADE.
    if (!table_exists('framework_control_applicability_reasons')) {
        $db->prepare("
            CREATE TABLE IF NOT EXISTS `framework_control_applicability_reasons` (
                `applicability_id` INT NOT NULL,
                `reason_id` INT NOT NULL,
                PRIMARY KEY (`applicability_id`, `reason_id`),
                KEY `reason_id` (`reason_id`),
                CONSTRAINT `fk_fcar_applicability` FOREIGN KEY (`applicability_id`)
                    REFERENCES `framework_control_applicability` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_fcar_reason` FOREIGN KEY (`reason_id`)
                    REFERENCES `control_applicability_reason` (`value`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ")->execute();
        echo "Created the framework_control_applicability_reasons table.<br />\n";
    }

    // Instances already carrying this branch built the table with only the
    // applicability-side key, so `reason_id` had an index but nothing enforcing
    // that it names a real row -- an orphan was reachable by any direct DB edit.
    // Add the missing constraint after the fact. Guarded on the constraint's own
    // presence, so a re-run is a no-op.
    //
    // Orphans have to go first or the ALTER cannot succeed. Deleting them is
    // hygiene, not data loss: applicability_reason_rows() LEFT JOINs the taxonomy
    // so a dangling id renders as an id with an EMPTY NAME, which shows an
    // auditor a justification entry that says nothing. Once the constraint is in
    // place that state is unreachable, and the LEFT JOIN stays as defence for
    // rows predating it. Neither table has reached a customer -- both are created
    // in this same unreleased upgrade -- so this can only touch development and
    // testing instances.
    //
    // Best-effort, matching the audit_log primary-key migration above: a failure
    // here must not fatal the rest of the upgrade.
    try {
        $reason_fk_exists = constraint_exists_on_table(
            $db,
            'framework_control_applicability_reasons',
            'FOREIGN KEY',
            'fk_fcar_reason'
        );

        if (!$reason_fk_exists) {

            $delete_orphans = $db->prepare("
                DELETE ar FROM `framework_control_applicability_reasons` ar
                LEFT JOIN `control_applicability_reason` r ON ar.`reason_id` = r.`value`
                WHERE r.`value` IS NULL;
            ");
            $delete_orphans->execute();

            if ($delete_orphans->rowCount() > 0) {
                echo "Removed " . (int)$delete_orphans->rowCount() . " applicability reason row(s) pointing at a reason that no longer exists.<br />\n";
            }

            $db->prepare("
                ALTER TABLE `framework_control_applicability_reasons`
                ADD CONSTRAINT `fk_fcar_reason` FOREIGN KEY (`reason_id`)
                    REFERENCES `control_applicability_reason` (`value`) ON DELETE RESTRICT;
            ")->execute();
            echo "Added the missing foreign key from framework_control_applicability_reasons.reason_id to control_applicability_reason.<br />\n";
        }
    } catch (Exception $e) {
        write_debug_log("Upgrade failed to add fk_fcar_reason to framework_control_applicability_reasons: " . $e->getMessage() . ". Applicability reasons remain unconstrained against the reason taxonomy.", 'warning');
        echo "Warning: failed to add the applicability reason foreign key (" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ").<br />\n";
    }

    // THE SCALAR `reason_id` GOES, now that the writer, the reader and the v2 API
    // all read the join table instead (Task 4). It has never reached a customer —
    // both tables are new on this branch — so this is a schema DEFINITION change
    // rather than a migration, and the CREATE above no longer names the column at
    // all.
    //
    // The one-row copy is nevertheless done first. The spec called for no data
    // copy because nothing has shipped, and strictly that is true; but every
    // development and testing instance carrying this branch holds real exclusion
    // reasons in that column, and those reasons are rendered into an SoA. Three
    // lines that turn "your existing exclusions silently lose their reason" into
    // "they keep it" are worth more than the purity of dropping outright.
    //
    // INSERT IGNORE genuinely dedupes here — unlike the reason seed above, this
    // target HAS a (composite) PRIMARY KEY for the duplicate to collide with —
    // so a re-run before the DROP is a no-op.
    if (field_exists_in_table('reason_id', 'framework_control_applicability')) {

        $db->prepare("
            INSERT IGNORE INTO `framework_control_applicability_reasons` (`applicability_id`, `reason_id`)
            SELECT `id`, `reason_id` FROM `framework_control_applicability` WHERE `reason_id` IS NOT NULL;
        ")->execute();

        $db->prepare("ALTER TABLE `framework_control_applicability` DROP COLUMN `reason_id`;")->execute();
        echo "Moved framework_control_applicability.reason_id into the reasons join table and dropped it.<br />\n";
    }

    // Framework-level SoA fields. Both are deliberately left NULL on existing
    // frameworks: the SoA *export* prompts for them when they are missing, which
    // is the only moment they matter. Seeding boilerplate here would suppress that
    // prompt and ship an auditor a scope and an inclusion driver nobody reviewed.
    if (!field_exists_in_table('scope_statement', 'frameworks')) {
        $db->prepare("ALTER TABLE `frameworks` ADD `scope_statement` TEXT;")->execute();
        echo "Added scope_statement to the frameworks table.<br />\n";
    }
    if (!field_exists_in_table('default_inclusion_justification', 'frameworks')) {
        $db->prepare("ALTER TABLE `frameworks` ADD `default_inclusion_justification` TEXT;")->execute();
        echo "Added default_inclusion_justification to the frameworks table.<br />\n";
    }

    // The scope statement became a rich-text field after the two columns above
    // were added, so anything already in it is plain text. Same release, so
    // there is nothing to convert on a customer's upgrade path — see the
    // function's own note for why it is written and called anyway.
    migrate_framework_scope_statement_rich_text($db);
}

/**********************************************************
 * FUNCTION: MIGRATE FRAMEWORK SCOPE STATEMENT RICH TEXT  *
 **********************************************************
 * Converts any PLAIN-TEXT value already sitting in `frameworks`.`scope_statement`
 * into the rich text that column now holds.
 *
 * Extracted from upgrade_from_20260709001() so the conversion can be exercised
 * directly by tests rather than by a copy of its logic — a copy would keep
 * passing if this were changed or deleted.
 *
 * WHY IT EXISTS AT ALL, GIVEN IT CANNOT RUN FOR A CUSTOMER. `scope_statement` is
 * ADDED by the same (still unreleased) upgrade function that now calls this, so
 * on every real upgrade path the column is created empty three statements above
 * and there is nothing here to convert. What DOES hold plain-text statements is
 * every development and testing instance that has been carrying this branch —
 * and a plain-text row rendered as HTML silently loses every newline its author
 * typed, which on a scope statement means a list of sites collapsing into one
 * sentence. This is the callable that repairs those, and it is the honest place
 * for the rule to live if the column's release ever splits from this change's.
 *
 * IDEMPOTENT BY DETECTION rather than by a flag: plain_text_to_rich_text()
 * (includes/functions.php) returns an already-tagged value untouched, so a
 * re-run is a no-op. Its detection is a tag-shaped regex and NOT
 * `strip_tags($v) !== $v`, because strip_tags() discards everything after an
 * unterminated "<" — so a scope statement reading "R&D < 500 users" would be
 * misread as markup and skipped, and that is precisely the row most in need of
 * escaping before something renders it.
 *
 * NULL IS LEFT NULL. It is the "never asked" state that makes the SoA prompt;
 * writing '' would silently answer the question.
 **********************************************************/
function migrate_framework_scope_statement_rich_text($db) {

    if (!field_exists_in_table('scope_statement', 'frameworks')) {
        return;
    }

    $rows = $db->query(
        "SELECT `value`, `scope_statement` FROM `frameworks`
         WHERE `scope_statement` IS NOT NULL AND `scope_statement` <> '';"
    )->fetchAll(PDO::FETCH_ASSOC);

    $update    = $db->prepare("UPDATE `frameworks` SET `scope_statement` = :s WHERE `value` = :v;");
    $converted = 0;

    foreach ($rows as $row) {

        $before = (string)$row['scope_statement'];
        $after  = plain_text_to_rich_text($before);

        if ($after === $before) {
            continue;
        }

        $update->execute([':s' => $after, ':v' => (int)$row['value']]);
        $converted++;
    }

    if ($converted > 0) {
        echo "Converted " . (int)$converted . " plain-text framework scope statement(s) to rich text.<br />\n";
    }
}

/**********************************************************
 * FUNCTION: MIGRATE FRAMEWORK CONTROL MAPPING SUBJECT    *
 **********************************************************
 * Adds `framework_control_mappings`.`reference_subject` — the FRAMEWORK's own
 * title for the control it cites, per mapping row.
 *
 * Extracted from upgrade_from_20260709001() so the migration can be exercised
 * directly by tests rather than by a copy of its SQL — a copy would keep passing
 * if this were changed or deleted.
 *
 * WHY A THIRD COLUMN RATHER THAN REUSING `reference_text`:
 *
 * The mapping row already carries two per-framework facts. `reference_name` is
 * the CODE the framework cites the control by ("5.1", "GV.OC-01"), and it is
 * what the Statement of Applicability's Reference column prints. `reference_text`
 * was designed to hold the framework's full CONTROL STATEMENT — normative
 * paragraph-length prose, which is what the AI enhancement job
 * (extras/artificial_intelligence/jobs/ai_control_reference_enhance.php) writes
 * into it as `control_text`.
 *
 * Neither is the control's SUBJECT: the short title an SoA prints beside the
 * clause number ("Policies for information security"). Without it the Name
 * column has nothing to show but the SimpleRisk control's `short_name`, which on
 * an SCF-derived catalogue reads "GOV-01: Security, Compliance & Resilience
 * Program (SCRP)" — the SCF's name for its own control, in a document that is
 * supposed to be about ISO's. Overloading `reference_text` instead would mean
 * the same column held a title on one row and three paragraphs on the next, with
 * no way for a reader to tell which, and would silently redefine what the AI job
 * has already been writing.
 *
 * NULLABLE AND NOT BACKFILLED. There is nothing truthful to backfill it with:
 * `short_name` is the other catalogue's title, which is exactly the substitution
 * this column exists to stop. NULL means "this framework has not told us its
 * title for this control", and build_soa_rows() (includes/soa.php) falls back to
 * `short_name` for that row — the pre-existing behaviour — rather than printing
 * a blank Name cell.
 *
 * VARCHAR(1000), matching the width `reference_name` carried before it was
 * reduced to 255. A control title is short; the headroom costs nothing on a
 * nullable column and avoids a second migration for a verbose framework.
 *
 * Idempotent: guarded by field_exists_in_table(), so a re-run is a clean no-op.
 * Fresh installs need no separate change — generate_database_sql.yml builds the
 * installer SQL by replaying this same upgrade chain and dumping the result.
 */
function migrate_framework_control_mapping_subject($db) {

    if (!table_exists('framework_control_mappings')) {
        return;
    }

    if (!field_exists_in_table('reference_subject', 'framework_control_mappings')) {
        $db->query("
            ALTER TABLE `framework_control_mappings`
            ADD `reference_subject` VARCHAR(1000) DEFAULT NULL
            COMMENT \"The framework's own title for this control, as the Statement of Applicability prints it.\"
            AFTER `reference_name`;
        ");
        echo "Added framework_control_mappings.reference_subject for the framework's own control title.<br />\n";
    }
}

/**********************************************************
 * FUNCTION: BACKFILL FRAMEWORK INCLUSION JUSTIFICATION   *
 **********************************************************
 * Gives every framework that has never had one a default inclusion
 * justification, so the Statement of Applicability's Justification column stops
 * printing blank cells for the majority of frameworks.
 *
 * Extracted from upgrade_from_20260709001() so the migration can be exercised
 * directly by tests rather than by a copy of its SQL — a copy would keep passing
 * if this were changed or deleted.
 *
 * WHY THERE IS ANYTHING TO BACKFILL. The column arrived earlier in this same
 * upgrade (migrate_control_applicability_schema()) as a nullable TEXT that
 * nothing seeded and nothing required — the framework form offered the sentence
 * as a PLACEHOLDER, on the reasoning that an unreviewed default must not reach a
 * customer's SoA by inaction. The result was measured on the development
 * instance: 19 of 20 frameworks NULL, and therefore a blank Justification cell
 * on every applicable control with no linked risks — 1,500 of them on SCF. A
 * blank cell is not a more honest document than a boilerplate one; it is the
 * single most common finding against a hand-maintained SoA, produced here by the
 * tool that exists to prevent it. So the sentence is now the default answer in
 * all three places: seeded on the create form, backfilled here, and substituted
 * at read time by soa_framework_default_justification() (includes/soa.php).
 *
 * `IS NULL` ONLY, and that is the whole care this migration needs. The column
 * carries THREE states, and Task 16 made the difference load-bearing:
 *
 *     NULL   never asked for      -> backfilled here
 *     ''     deliberately cleared -> LEFT ALONE
 *     text   answered             -> LEFT ALONE
 *
 * A customer who emptied the box made a decision, and overwriting it with
 * boilerplate on upgrade would be the migration answering a question on their
 * behalf that they had already answered. They lose nothing by being skipped: the
 * read-time fallback fills the column for them too, without touching their row.
 *
 * LOCALIZED FROM $lang, not an English literal — upgrade.php loads the language
 * file at the top, so this writes the sentence in the language the upgrading
 * administrator is running, which is the same string the create form would have
 * seeded. If the key is missing (a customer maintaining their own lang file that
 * predates it) the backfill is SKIPPED rather than writing the key name or
 * English into an audit document; those rows stay NULL and the read-time
 * fallback covers them.
 *
 * Idempotent: after one run no framework matches `IS NULL`, so a re-run updates
 * zero rows. Fresh installs need no separate change — generate_database_sql.yml
 * builds the installer SQL by replaying this same upgrade chain and dumping the
 * result, and a fresh install's own frameworks are created through the form.
 */
function migrate_framework_default_inclusion_justification($db) {

    global $lang;

    if (!table_exists('frameworks') || !field_exists_in_table('default_inclusion_justification', 'frameworks')) {
        return;
    }

    $sentence = trim((string)($lang['DefaultInclusionJustificationPlaceholder'] ?? ''));

    if ($sentence === '') {
        return;
    }

    $stmt = $db->prepare(
        "UPDATE `frameworks`
         SET `default_inclusion_justification` = :sentence
         WHERE `default_inclusion_justification` IS NULL;"
    );
    $stmt->execute([':sentence' => $sentence]);

    if ($stmt->rowCount() > 0) {
        echo "Backfilled the default inclusion justification on " . (int)$stmt->rowCount() . " framework(s).<br />\n";
    }
}

/**********************************************************
 * FUNCTION: DUPLICATE INSTANCE IDS                       *
 **********************************************************
 * The instance_id values confirmed to be shared by many unrelated organizations.
 *
 * HOW THEY HAPPENED: two delivery vectors shipped an already-populated
 * `settings` row for instance_id — a pre-seeded database file, and a VM image
 * with a pre-populated database. create_simplerisk_instance_id() returns the
 * existing value when one is present, so every instance built from those
 * artifacts kept the seeded id, and the registration path of the day sent that
 * id to the licensing service instead of minting a fresh one. One id ended up
 * shared by 30 organizations.
 *
 * MAINTENANCE OBLIGATION: this list is load-bearing indefinitely.
 * repair_duplicate_instance_id() is a STANDING check, not a one-time release
 * migration — it runs on every upgrade, on every admin/upgrade.php upgrade
 * attempt (pressing Continue, not merely opening the page), and on every call
 * into the management API's structured upgrade driver, including a no-op one.
 * If a ninth carrier is ever identified and is not added here, the check will
 * run, find nothing, and report clean. There is no mechanism that detects a
 * stale list; keeping it current is a human responsibility.
 *
 * Each value is a generate_token(50) string. Transcribe exactly.
 *
 * THIS LIST IS MIRRORED in DuplicateInstanceIdRepairTest::EXPECTED_IDS, which
 * transcribes it independently so a typo here fails loudly rather than silently
 * disabling a carrier. Adding an entry to one list without the other turns that
 * test red immediately, in either direction — that is the intent, not a defect.
 * Update both.
 *
 * @return list<string>
 **********************************************************/
function duplicate_instance_ids()
{
    return [
        'buQDR9yTTlqUXGoPmUQjq7OBJJZQzSovc4tAaPj6jyeaM4y4G9',
        'QIpmS4gO8l2ryKX4JeHdMxMGv5L5OFyqVdqxeWMvT9oSkUDZYY',
        'NvkrFq8rfVWA0wt0PKMMBHUQOjmPOZ63OwOrcVjKjVUm0Qc2ux',
        '36ODqYyyd8vthw2715KV3st85j7qCRlK9OlGn07PZa3VgtRLZ6',
        'p5AxaccLa3noBJK69mVPIBXh06DGVb5EDbp6RInhyUY9ObUc2f',
        'iJBZlvb5pGOHKXqoxbh8br39EJkAPl2e0WPd7fsPjMnpKs7qQ6',
        'CG2IPndnJBm4GL141AFJQnx7Wsl6gT3JGGW02uGjZQOzhE6RVK',
        'C2zedFBTA6QmJmIGVAE4yzrwYcQ3jCQ9q4RZQynHS4ljEh7Yx0',
    ];
}

/**********************************************************
 * FUNCTION: REPAIR DUPLICATE INSTANCE ID                 *
 **********************************************************
 * Give this instance a unique identity if it is carrying one of the ids in
 * duplicate_instance_ids().
 *
 * ORDER IS THE WHOLE POINT: register FIRST, overwrite the local settings only
 * once a 200 comes back. The obvious reading of "clear the id and key, then
 * re-register" leaves the instance UNREGISTERED whenever the licensing service
 * is unreachable -- a real possibility mid-upgrade on a customer network -- which
 * is strictly worse than the duplication it was meant to fix.
 * licensing_register_with_retry() mints the new id itself and retries on 409, so
 * the old values can simply stay in place until it succeeds.
 *
 * An unregistered carrier is repaired locally with no /register call: there is
 * no registration to preserve and no credential to rotate, and calling /register
 * would create a registration the customer never initiated. (The default
 * $cache_warm still fires and reaches /license/check -- that call is unrelated
 * to registration and safe to make either way.)
 *
 * IDEMPOTENT BOTH WAYS. After a success the id is no longer in the list, so a
 * re-run is a no-op. After a failure nothing was written, so the next upgrade
 * retries. Two instances sharing an id and upgrading at the same moment each
 * mint their own.
 *
 * NEVER THROWS. A licensing outage must not stop the database version from
 * advancing.
 *
 * Settings are written through update_or_insert_setting() rather than raw SQL
 * because it refreshes $GLOBALS['setting_<name>'], which get_setting() reads.
 * A raw REPLACE INTO would leave license_check_daily() reporting the new
 * registration under the OLD id for the rest of the request.
 *
 * @param PDO           $db         Open connection, reused for the settings reads/writes.
 * @param callable|null $transport  Test seam forwarded to licensing_register_with_retry().
 * @param callable|null $cache_warm Test seam; defaults to license_check_daily().
 **********************************************************/
function repair_duplicate_instance_id($db, ?callable $transport = null, ?callable $cache_warm = null): void
{
    $current = get_setting('instance_id', false, true, $db);

    // The path essentially every instance takes: one cached read and an
    // in_array over eight strings. Stay silent.
    if (!is_string($current) || !in_array($current, duplicate_instance_ids(), true)) {
        return;
    }

    if ($cache_warm === null) {
        $cache_warm = 'license_check_daily';
    }

    echo "Detected an instance_id that was distributed with a pre-populated database and is shared by other organizations. Repairing.<br />\n";
    write_debug_log(
        "repair_duplicate_instance_id: instance_id '{$current}' is a known shared value; repairing.",
        'notice'
    );

    // Forensic record of the entitlement state BEFORE anything is written. D6
    // below warms the licensing cache after a successful rotation on the
    // premise that a shared id resolves to no valid licensing, so there is
    // nothing good to lose -- but that premise is about server-side state and
    // is not verifiable from this code. If it is wrong for even one of the
    // eight carriers, this line is what survives to reconstruct what the
    // instance had. get_cached_license_entries() rows carry only extra_name,
    // effective/is_free booleans, license status/start/end dates,
    // current_version and download_sha256 -- no credential-shaped field -- so
    // logging the whole decoded array does not risk the never-log-secrets rule.
    write_debug_log(
        'repair_duplicate_instance_id: pre-rotation entitlement state — enforcement='
        . get_cached_enforcement_level() . ', entries=' . json_encode(get_cached_license_entries()),
        'notice'
    );

    $services_api_key = get_setting('services_api_key', false, true, $db);
    $registered = (get_setting('registration_registered', false, true, $db) == 1)
        && is_string($services_api_key)
        && $services_api_key !== '';

    if ($registered) {
        try {
            $result = licensing_register_with_retry([
                'fname'   => (string)get_setting('registration_fname',   '', true, $db),
                'lname'   => (string)get_setting('registration_lname',   '', true, $db),
                'company' => (string)get_setting('registration_company', '', true, $db),
                'title'   => (string)get_setting('registration_title',   '', true, $db),
                'phone'   => (string)get_setting('registration_phone',   '', true, $db),
                'email'   => (string)get_setting('registration_email',   '', true, $db),
            ], $transport);
        } catch (\Throwable $e) {
            write_debug_log(
                'repair_duplicate_instance_id: re-registration threw (' . $e->getMessage()
                . '); leaving the existing identity in place.',
                'error'
            );
            echo "Could not reach the licensing service; the existing registration was left in place and this will be retried on the next upgrade.<br />\n";
            return;
        }

        // A genuine transport-level failure (non-200, or the 409 retries
        // exhausted): the service was NOT usefully reached, so this is the one
        // remaining path where "could not reach the licensing service" is an
        // accurate thing to tell the console.
        if (empty($result['ok'])) {
            write_debug_log(
                'repair_duplicate_instance_id: re-registration failed ('
                . (isset($result['error']) ? $result['error'] : 'unknown')
                . '); instance_id and services_api_key left unchanged. The next upgrade will retry.',
                'warning'
            );
            echo "Could not reach the licensing service; the existing registration was left in place and this will be retried on the next upgrade.<br />\n";
            return;
        }

        // The service DID answer with a 200 here -- everything below this point
        // is a different failure class, and must not be described as
        // unreachable. Two things are checked and both are treated as a
        // failure, not a partial success:
        //
        //   - A missing services_api_key: writing the new instance_id without a
        //     matching key would pair a brand-new identity with the OLD
        //     carrier's key -- a combination that authenticates as nothing and
        //     is permanent, because the new id is no longer in
        //     duplicate_instance_ids() and the standing check never revisits it.
        //
        //   - A malformed instance_id: LICENSING_URL is overridable in
        //     config.php, so a spoofed or compromised endpoint could return any
        //     non-empty string. Nothing downstream is injectable -- every write
        //     is a bound parameter and every render site escapes -- but a
        //     malformed id would still be written PERMANENTLY for the same
        //     reason as above. The regex also keeps a hostile response from
        //     putting a newline into the notice/critical log lines below.
        $valid_instance_id = !empty($result['instance_id'])
            && preg_match('/^[A-Za-z0-9]{50}$/', (string)$result['instance_id']);

        if (!$valid_instance_id || empty($result['services_api_key'])) {
            $error = !$valid_instance_id
                ? 'registration returned a malformed or empty instance_id'
                : 'registration returned no services_api_key';
            write_debug_log(
                "repair_duplicate_instance_id: re-registration succeeded but the response was "
                . "unusable ({$error}); instance_id and services_api_key left unchanged. The next "
                . 'upgrade will retry.',
                'warning'
            );
            echo "The licensing service responded, but its answer could not be used to update this instance's identity. Nothing was changed; this will be retried on the next upgrade.<br />\n";
            return;
        }

        // Only now is it safe to overwrite the local identity. Check the
        // instance_id write before attempting the key write: update_or_insert_setting()
        // returns false rather than throwing on a caught DB error, and writing the
        // key against a still-stale instance_id would produce the mirror-image
        // broken pair -- the OLD id with the NEW key.
        if (!update_or_insert_setting('instance_id', $result['instance_id'], $db)) {
            write_debug_log(
                "repair_duplicate_instance_id: failed to write the new instance_id; leaving the "
                . 'existing identity in place. The next upgrade will retry.',
                'error'
            );
            echo "The licensing service responded, but this instance's identity could not be saved locally. Nothing was changed; this will be retried on the next upgrade.<br />\n";
            return;
        }

        // The instance_id write succeeded; the key write is checked the same way.
        // If it fails here, the instance is left with the NEW instance_id paired
        // with the OLD carrier's key -- the same permanent broken pair the guard
        // above exists to prevent (the new id is no longer in
        // duplicate_instance_ids(), so the standing check never revisits it).
        // Compensate by writing $current back onto instance_id rather than using
        // a DB transaction: a rollBack() would restore the row but leave
        // $GLOBALS['setting_instance_id'] holding the new id, reintroducing the
        // exact cache-incoherence bug the coherence test guards. A second
        // update_or_insert_setting() call fixes the row and the memo together.
        if (!update_or_insert_setting('services_api_key', $result['services_api_key'], $db)) {
            if (!update_or_insert_setting('instance_id', $current, $db)) {
                // Both writes failed: the instance is now stuck with the NEW
                // instance_id and the OLD services_api_key, and cannot be
                // rolled back automatically. Log both ids (never the key) so
                // support can reconcile by hand.
                write_debug_log(
                    'repair_duplicate_instance_id: CRITICAL -- the services_api_key write failed '
                    . "and the compensating instance_id rollback ALSO failed. The instance is left "
                    . "with instance_id '{$result['instance_id']}' paired with the OLD services_api_key "
                    . "from '{$current}'. This pair will not authenticate and requires manual "
                    . 'reconciliation.',
                    'critical'
                );
                echo "This instance's identity could not be fully updated and needs manual attention. Please contact support.<br />\n";
                return;
            }

            write_debug_log(
                "repair_duplicate_instance_id: the licensing service issued a new registration "
                . "('{$result['instance_id']}') but the services_api_key write failed; rolled "
                . "instance_id back to '{$current}'. The next upgrade will retry, which will "
                . 'register again.',
                'error'
            );
            echo "The licensing service issued a new registration, but this instance's identity could not be saved locally; the change was rolled back to the previous identity. This will be retried on the next upgrade.<br />\n";
            return;
        }

        // The key is deliberately absent from this line.
        write_debug_log(
            "repair_duplicate_instance_id: re-registered; instance_id '{$current}' -> "
            . "'{$result['instance_id']}'.",
            'notice'
        );
        echo "Re-registered this instance under a unique instance_id.<br />\n";
    } else {
        $new_id = generate_token(50);
        if (!update_or_insert_setting('instance_id', $new_id, $db)) {
            write_debug_log(
                "repair_duplicate_instance_id: failed to write the new instance_id; leaving the "
                . "existing identity '{$current}' in place. The next upgrade will retry.",
                'error'
            );
            echo "Could not update the local instance identity; this will be retried on the next upgrade.<br />\n";
            return;
        }
        write_debug_log(
            "repair_duplicate_instance_id: instance is not registered; instance_id '{$current}' -> "
            . "'{$new_id}' with no licensing call.",
            'notice'
        );
        echo "Replaced the shared instance_id with a unique value.<br />\n";
    }

    // Push the new identity into the licensing records now rather than waiting
    // up to 24h for the daily cron. The duplicates resolve to no valid
    // licensing, so there is no good cached entitlement set at risk here.
    try {
        call_user_func($cache_warm);
    } catch (\Throwable $e) {
        write_debug_log(
            'repair_duplicate_instance_id: the identity was rotated successfully but the licensing '
            . 'cache warm failed (' . $e->getMessage() . '). The daily check will catch up.',
            'warning'
        );
    }
}

/**********************************************************
 * FUNCTION: RUN UPGRADE INTEGRITY CHECKS                 *
 **********************************************************
 * Standing data-integrity checks, run by BOTH upgrade drivers at the point
 * where the database has reached the application version.
 *
 * WHY STANDING RATHER THAN RELEASE-BOUND: a check parked in one
 * upgrade_from_*() only ever fires for instances crossing that single version
 * boundary. An instance already past it -- including one restored next year from
 * a template database -- is never examined. The duplicate-instance_id problem
 * recurred from 2018 to 2026, so a one-shot check would miss the next
 * occurrence. Running here also means a carrier that is already fully upgraded
 * is repaired the next time an admin deliberately presses Continue on an
 * already-current admin/upgrade.php -- merely opening the page only renders
 * display_upgrade_info() and never reaches this -- or, with no admin visit at
 * all, the next time the management API's run_database_upgrade_structured() is
 * called, including a no-op call. That second path is what lets a HOSTED
 * instance heal passively.
 *
 * The cost on the path essentially every instance takes is one cached
 * get_setting() and an in_array over eight strings.
 *
 * Must not throw: a failed check must never stop the database version from
 * advancing.
 *
 * ADDING A CHECK: it belongs here only if it is safe to run on every upgrade
 * and on every no-op call from either driver -- meaning idempotent, cheap when
 * there is nothing to do, and harmless when it fails. Anything that is a
 * one-time schema or data change belongs in the current release's
 * upgrade_from_*() instead.
 *
 * The two seams are forwarded, not consumed, and both drivers call this with
 * neither -- so production behaviour is exactly `repair_duplicate_instance_id($db)`.
 * They exist because the branch a caller most needs covered is the one where a
 * check REPORTS something, and reaching it with the real defaults would make a
 * test rotate an identity against the live licensing service. Without a seam
 * here the only reachable test case is the silent no-op.
 *
 * @param PDO           $db         Open connection from the calling driver.
 * @param callable|null $transport  Test seam forwarded to repair_duplicate_instance_id().
 * @param callable|null $cache_warm Test seam forwarded to repair_duplicate_instance_id().
 **********************************************************/
function run_upgrade_integrity_checks($db, ?callable $transport = null, ?callable $cache_warm = null)
{
    try {
        repair_duplicate_instance_id($db, $transport, $cache_warm);
    } catch (\Throwable $e) {
        write_debug_log(
            'run_upgrade_integrity_checks: repair_duplicate_instance_id() failed: '
            . $e->getMessage(),
            'error'
        );
    }
}

/*******************************************************************************
 * FUNCTION: APPLY DATABASE RELEASE                                             *
 *******************************************************************************
 * Apply ONE release's upgrade function and report what happened.
 *
 * The shared step for the two JSON-facing drivers:
 * run_database_upgrade_structured() (the management extra's /upgrade) and
 * api_v2_admin_upgrade_db() (POST /admin/upgrade/db). The second used to
 * hand-roll its own and was missing most of what the first does -- no grants
 * check, no captured exception, no advancement check -- so an instance upgraded
 * through that endpoint could be told "Upgrade successful" by a release
 * function that threw or that moved nothing.
 *
 * NOT used by upgrade_database(), and that is deliberate rather than an
 * oversight to tidy up later. That driver echoes to the operator's page as it
 * goes (admin/upgrade.php reads it live); this function buffers a release
 * function's output and returns it as an array, which would replace a running
 * commentary with a single lump at the end. The two therefore keep separate
 * dispatch, and share the part that actually caused divergence --
 * upgrade_chain_advanced() and finalize_database_upgrade().
 *
 * So a change HERE does not automatically reach upgrade_database(). Check it
 * too, or the drift this function was written to end starts over.
 *
 * What it deliberately does NOT do is decide anything: no chain walking, no
 * status code, no output. Those differ per channel and belong to the caller.
 * This returns facts and lets each channel report them in its own shape.
 *
 * @param object $db      An open database handle.
 * @param string $version The release whose upgrade function should run.
 *
 * @return array {
 *     @var string $from     Database version before the call.
 *     @var string $to       Database version after it.
 *     @var bool   $ran      Whether an upgrade function was found and called.
 *     @var bool   $advanced Whether the database version actually moved.
 *     @var bool   $success  $ran && no error && $advanced.
 *     @var string $error    Internal error text, for the log -- never for a caller.
 *     @var array  $messages The function's echoed progress, as lines.
 * }
 */
function apply_database_release($db, $version)
{
    // The baseline is READ, never taken from $version. They are the same value
    // for the chain drivers, which dispatch on the version they just read -- but
    // the v2 endpoint takes the version from its caller, and trusting it there
    // would compute "did the database move?" against a number the database was
    // never on, reporting advancement for a migration that did nothing.
    $before = current_version("db");

    $result = array(
        'from'     => (string)$before,
        'to'       => (string)$before,
        'ran'      => false,
        'advanced' => false,
        'success'  => false,
        'error'    => '',
        'messages' => array(),
    );

    $function_name = get_database_upgrade_function_for_release($version);
    if ($function_name === false || !function_exists($function_name)) {
        return $result;
    }

    // The release functions narrate themselves with echo. Capture that here so
    // a caller answering in JSON does not emit HTML into its own response --
    // which is what forced api_v2_admin_upgrade_db() to run the function bare,
    // and is why an exception from it escaped uncaught.
    $outer = ob_get_level();
    ob_start();
    try {
        call_user_func($function_name, $db);
    } catch (\Throwable $e) {
        // Kept out of the return value's message list on purpose: exception text
        // carries table and column names, file paths and MySQL error strings.
        // A caller gets "something failed"; the detail goes to the log.
        $result['error'] = $e->getMessage();
    } finally {
        // Bounded rather than while-true: a release function that opened a
        // buffer and did not close it must not let this unwind past the
        // caller's own.
        $guard = 0;
        while (ob_get_level() > $outer && $guard++ < 32) {
            $result['messages'] = array_merge(upgrade_output_to_messages(ob_get_clean()), $result['messages']);
        }
    }

    $result['ran']      = true;
    $result['to']       = current_version("db");
    $result['advanced'] = upgrade_chain_advanced($before, $result['to']);
    $result['success']  = ($result['error'] === '' && $result['advanced']);

    return $result;
}

/**
 * Run something while holding the instance-wide upgrade lock.
 *
 * Two upgrade chains running at once on one database is the worst concurrency
 * bug this subsystem can have. Both drivers read the same db_version, both
 * dispatch the same upgrade_from_* function, and the migrations are written to
 * be idempotent against a RE-RUN, not against a simultaneous run: two
 * interleaved ALTERs on the same table, two seed inserts racing the same
 * existence check, two update_database_version() writes deciding what the
 * version is now.
 *
 * It is not hypothetical. The Upgrade Extra took a lock for its own start-once
 * check and nothing else did, so the hosted fleet's automation calling the
 * management /upgrade endpoint could walk the chain underneath an administrator
 * who had just clicked Upgrade in the UI -- two entirely different channels,
 * neither aware of the other.
 *
 * A MySQL advisory lock rather than a file: it is named (so it excludes across
 * web workers, which a per-process flag cannot) and it lives in the database
 * (so it excludes across NODES sharing one database, which a lock on local disk
 * cannot). Same lock name the Upgrade Extra uses, so the channels exclude each
 * other and not merely themselves.
 *
 * Non-blocking by default. A caller who cannot get the lock has an upgrade
 * already in progress, and queueing behind it would run a second chain the
 * moment the first finished -- the caller wants to be told "not now", not to
 * wait and then do the wrong thing.
 *
 * @param callable $work    Runs while the lock is held.
 * @param int      $wait    Seconds to wait for the lock. 0 = do not wait.
 * @param mixed    $refused Returned when the lock could not be taken.
 * @return mixed The work's return value, or $refused.
 */
function with_upgrade_lock(callable $work, $wait = 0, $refused = false)
{
    // db_open() returns a shared singleton and db_close() is a no-op, so this
    // is the same MySQL session the work itself will use. That is what makes
    // the lock re-entrant for a nested caller in the same request (MySQL grants
    // the same session the lock again and counts it, and the matching
    // RELEASE_LOCK decrements) while still excluding a different request.
    $db = db_open();
    if (!$db) {
        // Refuse rather than proceed unserialised. An upgrade that cannot prove
        // it is alone is exactly the one not to start.
        write_debug_log('Could not open a connection to take the upgrade lock; refusing to upgrade.', 'error');
        return $refused;
    }

    $acquired = false;

    // ONLY the acquisition is guarded. An exception from $work() propagates.
    //
    // Catching it here and returning $refused would tell the caller "an upgrade
    // is already running" when what actually happened is that the upgrade ran
    // and failed -- reporting one outcome for a completely different one, which
    // is the single most common defect this subsystem has had. The callers
    // already handle migration failures properly; the lock has no business
    // rewriting them.
    try {
        $stmt = $db->prepare("SELECT GET_LOCK('simplerisk_upgrade', :wait) AS acquired");
        $stmt->bindParam(":wait", $wait, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // GET_LOCK returns 1 (taken), 0 (timed out) or NULL (error). Only 1 is
        // permission to proceed.
        $acquired = ((int)($row['acquired'] ?? 0) === 1);
    } catch (Throwable $e) {
        write_debug_log('Taking the upgrade lock failed: ' . $e->getMessage(), 'error');
        $acquired = false;
    }

    if (!$acquired) {
        write_debug_log('An upgrade is already running on this instance, or the lock could not be taken; refusing to start a second one.', 'warning');
        db_close($db);
        return $refused;
    }

    try {
        return $work();
    } finally {
        // Must run, and cannot be left to the connection closing: db_close() is
        // a no-op here, so a lock left held would stay held for the rest of the
        // request -- and on a persistent connection, beyond it.
        try { $db->query("SELECT RELEASE_LOCK('simplerisk_upgrade')"); } catch (Throwable $e) {}
        db_close($db);
    }
}
