<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Pure helpers for inspecting the SimpleRisk runtime configuration.
//
// Both bootstrap.php (install-marker gate) and extras/upgrade/index.php's
// do_pre_upgrade_check() consult the same set of required DB_* constants
// to decide whether a config is complete. The list lives here so the two
// call sites cannot drift apart.
//
// Side-effect free: nothing in this file reads config.php, opens a DB
// connection, or emits output. That makes the predicates safe to unit
// test in isolation (see tests/unit/ConfigCheckTest.php) without the
// install-dispatch behavior bootstrap.php applies on top.

if (!function_exists('config_required_db_defines')) {
    /**
     * Canonical list of DB_* constants that config.php must define for the
     * application to be considered installed. Used by bootstrap.php and
     * by extras/upgrade/index.php to determine whether the configuration
     * file is complete enough to proceed.
     */
    function config_required_db_defines(): array
    {
        return ['DB_HOSTNAME', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    }
}

if (!function_exists('config_defines_all_present')) {
    /**
     * Return true if every constant name in $required_defines has been
     * define()'d in the current PHP process.
     *
     * Pure with respect to its input list and the process-global constant
     * table — does not touch config.php, the filesystem, or the database.
     * That lets tests pass arbitrary constant names and assert behavior
     * without polluting (or depending on) the SimpleRisk-specific DB_*
     * names.
     *
     * An empty $required_defines array returns true (vacuously satisfied).
     */
    function config_defines_all_present(array $required_defines): bool
    {
        foreach ($required_defines as $required_define) {
            if (!defined($required_define)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('resolve_use_database_for_sessions')) {
    /**
     * Pure resolver for the USE_DATABASE_FOR_SESSIONS contract.
     *
     * Takes the raw value the way `defined('USE_DATABASE_FOR_SESSIONS') ?
     * USE_DATABASE_FOR_SESSIONS : null` would produce it, and returns the
     * resolved boolean: true means "store sessions in the DB".
     *
     * Returns false only on the exact opt-out string 'false'. Everything
     * else — null (constant undefined), the un-substituted
     * '__USE_DATABASE_FOR_SESSIONS__' placeholder left by a partial
     * installer, a PHP boolean from a hand-edited config — resolves to
     * true (the documented safe default). The helper fails closed toward
     * the recommended setting rather than silently disabling DB-backed
     * sessions by failing != "true" comparisons across 18 call sites.
     *
     * Pure with respect to its input; no I/O. Split out so the contract
     * can be exhaustively unit-tested without forking a PHP process per
     * case to override the runtime constant.
     */
    function resolve_use_database_for_sessions($raw_value): bool
    {
        return $raw_value !== 'false';
    }
}

if (!function_exists('use_database_for_sessions')) {
    /**
     * Return true when SimpleRisk should store PHP session data in the
     * database, false when it should use the filesystem. The single
     * point every consumer reads the USE_DATABASE_FOR_SESSIONS intent.
     *
     * Reads the runtime constant and delegates to
     * resolve_use_database_for_sessions() for the value contract.
     */
    function use_database_for_sessions(): bool
    {
        return resolve_use_database_for_sessions(
            defined('USE_DATABASE_FOR_SESSIONS') ? USE_DATABASE_FOR_SESSIONS : null
        );
    }
}
