<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Pure helpers for inspecting the SimpleRisk runtime configuration, plus the
// pure predicates that decide what a configuration setting means for a
// particular page.
//
// The second group is currently just demo_mode_blocks_password_reset(), which
// encodes reset.php's branching rather than inspecting config.php. It lives
// here because the only thing that makes it worth extracting is that this file
// is side-effect free and therefore unit-testable in isolation — the property
// documented below. If that group grows past a couple of entries, it wants its
// own module rather than continuing to widen this file's remit.
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

if (!function_exists('resolve_demo_mode')) {
    /**
     * Pure resolver for the DEMO_MODE contract.
     *
     * Takes the raw value the way `defined('DEMO_MODE') ? DEMO_MODE : null`
     * would produce it, and returns the resolved boolean: true means "this
     * instance is a shared public demo".
     *
     * Note the direction of the default, which is the opposite of
     * resolve_use_database_for_sessions() and deliberately so. That helper
     * fails *toward* its feature because the recommended setting is "on".
     * DEMO_MODE fails *away* from its feature: an ordinary customer install
     * must never wander into demo mode because someone typo'd a value or
     * left an un-substituted placeholder in config.php. Only an explicit,
     * recognizable affirmative turns it on.
     *
     * Accepted affirmatives: boolean true, integer 1, and the strings
     * 'true' / '1' / 'on' / 'yes' (case-insensitive, surrounding whitespace
     * ignored). Everything else — including null, 'false', '0', and any
     * unrecognized string — resolves to false.
     *
     * Pure with respect to its input; no I/O. Split out so the contract can
     * be exhaustively unit-tested without forking a PHP process per case to
     * override the runtime constant.
     */
    function resolve_demo_mode($raw_value): bool
    {
        if (is_bool($raw_value)) {
            return $raw_value;
        }

        if (is_int($raw_value)) {
            return $raw_value === 1;
        }

        if (is_string($raw_value)) {
            return in_array(strtolower(trim($raw_value)), ['true', '1', 'on', 'yes'], true);
        }

        return false;
    }
}

if (!function_exists('demo_mode')) {
    /**
     * Return true when this instance is running as a shared public demo.
     * The single point every consumer reads the DEMO_MODE intent.
     *
     * DEMO_MODE is an undocumented, opt-in config.php define — deliberately
     * absent from config.sample.php, because it exists to protect the
     * SimpleRisk-operated demo instances rather than to configure a customer
     * deployment. When it is on, SimpleRisk refuses the handful of
     * operations by which one visitor can spoil the demo for the next: the
     * ones that change how (or whether) the shared account can log in, and
     * the ones that let a visitor push arbitrary bytes into the database.
     *
     * To enable, add to simplerisk/includes/config.php:
     *
     *     define('DEMO_MODE', true);
     *
     * This is NOT a security boundary and must never be treated as one. It
     * does not make an instance safe to expose, it does not restrict a
     * hostile visitor to the blocked list, and nothing else in SimpleRisk
     * should grow a permission decision that consults it. It is a guard
     * rail against ordinary visitors breaking a demo by accident.
     *
     * Reads the runtime constant and delegates to resolve_demo_mode() for
     * the value contract.
     */
    function demo_mode(): bool
    {
        return resolve_demo_mode(defined('DEMO_MODE') ? DEMO_MODE : null);
    }
}

if (!function_exists('demo_mode_blocks_password_reset')) {
    /**
     * Whether reset.php should refuse the request it is holding.
     *
     * Extracted from the page rather than left inline because it is the one
     * demo-mode guard that is not a bare `if (demo_mode())` — it has to cover
     * two different POST handlers, and reset.php is reachable without logging
     * in at all. Hand-composed boolean logic in that position is worth being
     * able to test: a misplaced operator would silently re-open the path that
     * mails a reset link, or the one that sets a new password from a token.
     *
     * Both halves are blocked together because both end in the same place —
     * a new password on the account every demo visitor shares. The token half
     * matters most: password_reset_by_token() writes the password with its own
     * UPDATE rather than calling update_password(), so the chokepoint guard in
     * that function does not cover it and this is the only thing that does.
     *
     * Pure with respect to its inputs; the caller reads $_POST and demo_mode()
     * and passes the results in, so no I/O happens here.
     *
     * @param bool $is_reset_email_request      a 'send_reset_email' POST is present
     * @param bool $is_password_reset_submission a 'password_reset' POST is present
     * @param bool $is_demo_mode                the result of demo_mode()
     */
    function demo_mode_blocks_password_reset(
        bool $is_reset_email_request,
        bool $is_password_reset_submission,
        bool $is_demo_mode
    ): bool {
        return $is_demo_mode && ($is_reset_email_request || $is_password_reset_submission);
    }
}
