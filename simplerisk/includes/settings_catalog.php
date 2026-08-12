<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Settings Hub catalog — single source of truth for every admin tile
 * shown in the Settings Hub at /admin/index.php.
 *
 * Each entry describes:
 *   label_key   — lang key for the tile title (and search index)
 *   desc_key    — lang key for the tile description
 *   path        — path relative to simplerisk/ (must point to an existing file)
 *   tags        — one or more of: system, customization, users, data, maintenance, extras
 *   visibility  — gating: {mode: 'always'} OR {mode: 'extra', extra: '<name>'} OR {mode: 'callable', fn: '<name>'}
 *   extra_name  — (optional) canonical Extra slug used to look up <name>_extra() and is_extra_installed(<name>).
 *                 Defaults to visibility.extra when visibility.mode is 'extra'.
 *   required_permissions — (optional) array of permission slugs; user must have at least
 *                          one of them to see the tile. Defaults to ['admin'].
 *
 * Per-tile visibility is filtered server-side (see user_can_access_settings_tile).
 *
 * Preferences (the page that holds default values and behavioral toggles
 * such as alert timeout and risk-mapping requirements), Health Check, and
 * Register & Upgrade are first-class catalog entries and are also the three
 * default favorites every user is seeded with (see default_favorite_settings_keys).
 * In addition, settings-hub.js renders a hardcoded fallback set inside
 * .hub__main when the catalog API fetch fails — Security, Preferences,
 * Health Check, and Register & Upgrade — so an admin who has broken the
 * API (e.g. by misconfiguring Base URL, which lives on the Security page)
 * still has a one-click path to the pages they'd need to fix the Base URL,
 * recover, diagnose, and identify the version. Security is included
 * specifically because the Base URL field it hosts is a common cause of
 * the API failure, and it would otherwise be unreachable from the fallback.
 */

/********************************
 * FUNCTION: SETTINGS CATALOG   *
 ********************************/
function settings_catalog(): array
{
    return [
        // --- system ---
        'settings_file_upload' => [
            'label_key'   => 'FileUpload',
            'desc_key'    => 'SettingsFileUploadDesc',
            'path'        => 'admin/settings_file_upload.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
        ],
        'settings_logging' => [
            'label_key'   => 'Logging',
            'desc_key'    => 'SettingsLoggingDesc',
            'path'        => 'admin/settings_logging.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
        ],
        'settings_mail' => [
            'label_key'   => 'Mail',
            'desc_key'    => 'SettingsMailDesc',
            'path'        => 'admin/settings_mail.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
        ],
        'settings_security' => [
            'label_key'   => 'Security',
            'desc_key'    => 'SettingsSecurityDesc',
            'path'        => 'admin/settings_security.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
        ],
        'health_check' => [
            'label_key'   => 'HealthCheck',
            'desc_key'    => 'HealthCheckDesc',
            'path'        => 'admin/health_check.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
        ],
        'announcements' => [
            'label_key'   => 'Announcements',
            'desc_key'    => 'AnnouncementsDesc',
            'path'        => 'admin/announcements.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
        ],
        'register' => [
            'label_key'   => 'RegisterAndUpgrade',
            'desc_key'    => 'RegisterAndUpgradeDesc',
            'path'        => 'admin/register.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
            // admin/register.php enforce_permission("admin"); only show the tile to admins
            // so a vm_configure/im_configure hub user isn't offered a tile that 403s on click.
            'required_permissions' => ['admin'],
        ],
        'licenses' => [
            'label_key'   => 'Licenses',
            'desc_key'    => 'LicensesDesc',
            'path'        => 'admin/licenses.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
            // admin/licenses.php enforce_permission("admin"); admin-only tile (see register above).
            'required_permissions' => ['admin'],
        ],
        'queue_monitor' => [
            'label_key'   => 'QueueMonitor',
            'desc_key'    => 'QueueMonitorDesc',
            'path'        => 'admin/queue_monitor.php',
            'tags'        => ['system'],
            'visibility'  => ['mode' => 'always'],
        ],
        // --- customization ---
        'artificial_intelligence_core' => [
            'label_key'   => 'ArtificialIntelligence',
            'desc_key'    => 'ArtificialIntelligenceDesc',
            'path'        => 'admin/artificial_intelligence_core.php',
            'tags'        => ['customization'],
            'visibility'  => ['mode' => 'always'],
        ],
        'settings_preferences' => [
            'label_key'   => 'Preferences',
            'desc_key'    => 'SettingsPreferencesDesc',
            'path'        => 'admin/settings_preferences.php',
            'tags'        => ['customization'],
            'visibility'  => ['mode' => 'always'],
        ],
        'custom_names' => [
            'label_key'   => 'RedefineNamingConventions',
            'desc_key'    => 'RedefineNamingConventionsDesc',
            'path'        => 'admin/custom_names.php',
            'tags'        => ['customization'],
            'visibility'  => ['mode' => 'always'],
        ],
        'risk_configuration' => [
            'label_key'   => 'RiskConfiguration',
            'desc_key'    => 'RiskConfigurationDesc',
            'path'        => 'admin/risk_configuration.php',
            'tags'        => ['customization'],
            'visibility'  => ['mode' => 'always'],
        ],
        'risk_catalog' => [
            'label_key'   => 'RiskAndThreatCatalog',
            'desc_key'    => 'RiskAndThreatCatalogDesc',
            'path'        => 'admin/risk_catalog.php',
            'tags'        => ['customization'],
            'visibility'  => ['mode' => 'always'],
        ],
        'add_remove_values' => [
            'label_key'   => 'AddAndRemoveValues',
            'desc_key'    => 'AddAndRemoveValuesDesc',
            'path'        => 'admin/add_remove_values.php',
            'tags'        => ['customization'],
            'visibility'  => ['mode' => 'always'],
        ],

        // --- users ---
        'user_management' => [
            'label_key'   => 'UserManagement',
            'desc_key'    => 'UserManagementDesc',
            'path'        => 'admin/user_management.php',
            'tags'        => ['users'],
            'visibility'  => ['mode' => 'always'],
        ],
        'role_management' => [
            'label_key'   => 'RoleManagement',
            'desc_key'    => 'RoleManagementDesc',
            'path'        => 'admin/role_management.php',
            'tags'        => ['users'],
            'visibility'  => ['mode' => 'always'],
        ],
        'team_management' => [
            'label_key'   => 'TeamManagement',
            'desc_key'    => 'TeamManagementDesc',
            'path'        => 'admin/team_management.php',
            'tags'        => ['users'],
            'visibility'  => ['mode' => 'always'],
        ],

        // --- data ---
        'delete_risks' => [
            'label_key'   => 'DeleteRisks',
            'desc_key'    => 'DeleteRisksDesc',
            'path'        => 'admin/delete_risks.php',
            'tags'        => ['data'],
            'visibility'  => ['mode' => 'always'],
        ],
        'audit_trail' => [
            'label_key'   => 'AuditTrail',
            'desc_key'    => 'AuditTrailDesc',
            'path'        => 'admin/audit_trail.php',
            'tags'        => ['data'],
            'visibility'  => ['mode' => 'always'],
        ],

        // --- maintenance ---
        'fix_review_dates' => [
            'label_key'   => 'FixReviewDates',
            'desc_key'    => 'FixReviewDatesDesc',
            'path'        => 'admin/fix_review_dates.php',
            'tags'        => ['maintenance'],
            'visibility'  => ['mode' => 'callable', 'fn' => 'settings_visibility_fix_review_dates'],
        ],
        'fix_encoding_issues' => [
            'label_key'   => 'FixFileEncodingIssues',
            'desc_key'    => 'FixFileEncodingIssuesDesc',
            'path'        => 'admin/fix_upload_encoding_issues.php',
            'tags'        => ['maintenance'],
            'visibility'  => ['mode' => 'callable', 'fn' => 'settings_visibility_fix_encoding_issues'],
        ],
        'db_upgrade' => [
            'label_key'   => 'DatabaseUpgrade',
            'desc_key'    => 'DatabaseUpgradeDesc',
            'path'        => 'admin/upgrade.php',
            'tags'        => ['maintenance'],
            'visibility'  => ['mode' => 'always'],
        ],
        'settings_backups' => [
            'label_key'   => 'Backups',
            'desc_key'    => 'SettingsBackupsDesc',
            'path'        => 'admin/settings_backups.php',
            'tags'        => ['maintenance'],
            'visibility'  => ['mode' => 'always'],
        ],
        // --- extras ---
        'advanced_search_extra' => [
            'label_key'   => 'AdvancedSearchExtra',
            'desc_key'    => 'AdvancedSearchExtraDesc',
            'path'        => 'admin/advanced_search.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'advanced_search',
        ],
        'ai_extra' => [
            'label_key'   => 'ArtificialIntelligenceExtra',
            'desc_key'    => 'ArtificialIntelligenceExtraDesc',
            'path'        => 'admin/artificial_intelligence.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'artificial_intelligence',
        ],
        'api_extra' => [
            'label_key'   => 'APIExtra',
            'desc_key'    => 'APIExtraDesc',
            'path'        => 'admin/api.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'api',
        ],
        'assessments_extra' => [
            'label_key'   => 'AssessmentsExtra',
            'desc_key'    => 'AssessmentsExtraDesc',
            'path'        => 'admin/assessments.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'assessments',
        ],
        'authentication_extra' => [
            'label_key'   => 'CustomAuthenticationExtra',
            'desc_key'    => 'CustomAuthenticationExtraDesc',
            'path'        => 'admin/authentication.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'authentication',
        ],
        'customization_extra' => [
            'label_key'   => 'CustomizationExtra',
            'desc_key'    => 'CustomizationExtraDesc',
            'path'        => 'admin/customization.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'customization',
        ],
        'encryption_extra' => [
            'label_key'   => 'EncryptedDatabaseExtra',
            'desc_key'    => 'EncryptionExtraDesc',
            'path'        => 'admin/encryption.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'encryption',
        ],
        'import_export_extra' => [
            'label_key'   => 'ImportExportExtra',
            'desc_key'    => 'ImportExportExtraDesc',
            'path'        => 'admin/importexport.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'import-export',
        ],
        'incident_management_activation' => [
            'label_key'   => 'IncidentManagementExtra',
            'desc_key'    => 'IncidentManagementExtraDesc',
            'path'        => 'admin/incidentmanagement.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'incident_management',
            'required_permissions' => ['admin', 'im_configure'],
        ],
        'jira_extra' => [
            'label_key'   => 'JiraIntegrationExtra',
            'desc_key'    => 'JiraExtraDesc',
            'path'        => 'admin/jira.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'jira',
        ],
        'notification_extra' => [
            'label_key'   => 'NotificationExtra',
            'desc_key'    => 'NotificationExtraDesc',
            'path'        => 'admin/notification.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'notification',
        ],
        'organizational_hierarchy_extra' => [
            'label_key'   => 'OrganizationalHierarchyExtra',
            'desc_key'    => 'OrganizationManagementDesc',
            'path'        => 'admin/organizational_hierarchy.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'organizational_hierarchy',
        ],
        'scf_extra' => [
            'label_key'   => 'SCFExtra',
            'desc_key'    => 'SCFExtraDesc',
            'path'        => 'admin/securecontrolsframework.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'complianceforgescf',
            // SCF doesn't need to be purchased — the instance just needs
            // to be registered before the SCF Extra can be downloaded.
            // When the SCF Extra isn't installed, the tile renders
            // "Registration Required" and links to admin/register.php
            // instead of going through the purchase / license flow.
            'uninstalled_state' => 'registration_required',
        ],
        'separation_extra' => [
            'label_key'   => 'TeamBasedSeparationExtra',
            'desc_key'    => 'SeparationExtraDesc',
            'path'        => 'admin/separation.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'separation',
        ],
        'ucf_extra' => [
            'label_key'   => 'UCFExtra',
            'desc_key'    => 'UCFExtraDesc',
            'path'        => 'admin/ucf.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'ucf',
        ],
        'vulnmgmt_activation' => [
            'label_key'   => 'VulnerabilityManagementExtra',
            'desc_key'    => 'VulnerabilityManagementExtraDesc',
            'path'        => 'admin/vulnmgmt.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'vulnmgmt',
            'required_permissions' => ['admin', 'vm_configure'],
        ],
        'workflows_extra' => [
            'label_key'   => 'WorkflowsExtra',
            'desc_key'    => 'WorkflowsExtraDesc',
            'path'        => 'admin/workflows.php',
            'tags'        => ['extras'],
            'visibility'  => ['mode' => 'always'],
            'extra_name'  => 'workflows',
        ],
    ];
}

/**********************************************
 * FUNCTION: USER CAN ACCESS SETTINGS TILE    *
 **********************************************/
/**
 * Resolve a tile's combined visibility + permission gate. Returns true
 * only when the install-level visibility check AND the per-user
 * permission check both pass.
 *
 * Visibility modes (does this tile exist for ANY user on this install?):
 *   'always'   — visible whenever the user passes the permission gate below.
 *   'extra'    — visible only when the named Extra is installed; calls <name>_extra().
 *                Uses function_exists() as a belt-and-suspenders guard against
 *                boot-order edge cases where the Extra-detection function isn't
 *                loaded yet (mirrors the pattern in reports_catalog.php).
 *   'callable' — visible only when the named predicate function returns true.
 *
 * Permission gate (does THIS user have access?):
 *   required_permissions — array of permission slugs; user must have at least
 *                          one. Defaults to ['admin'] so any entry that does
 *                          not declare the field stays admin-only.
 */
function user_can_access_settings_tile(array $entry): bool
{
    // Visibility gate: is this tile relevant to the install at all?
    $vis = $entry['visibility'] ?? null;
    if (!is_array($vis)) {
        return false;
    }
    $mode = $vis['mode'] ?? '';
    $visible = false;
    if ($mode === 'always') {
        $visible = true;
    } elseif ($mode === 'extra') {
        $fn = ($vis['extra'] ?? '') . '_extra';
        $visible = function_exists($fn) && $fn();
    } elseif ($mode === 'callable') {
        $fn = $vis['fn'] ?? '';
        $visible = $fn !== '' && function_exists($fn) && $fn();
    }
    if (!$visible) {
        return false;
    }

    // Permission gate: does the current user have at least one of the
    // required permissions? Default to ['admin'] so existing entries
    // (which only admins should see) work without explicit declaration.
    $required = $entry['required_permissions'] ?? ['admin'];
    foreach ($required as $perm) {
        if ($perm === 'admin' && function_exists('is_admin') && is_admin()) {
            return true;
        }
        if ($perm !== 'admin' && function_exists('check_permission') && check_permission($perm)) {
            return true;
        }
    }
    return false;
}

/******************************************************
 * FUNCTION: USER CAN ACCESS SETTINGS HUB             *
 ******************************************************/
/**
 * Returns true if the user can reach /admin/index.php (the Settings
 * Hub). Currently this is admin OR vm_configure OR im_configure — the
 * three permissions whose holders have at least one tile they can use
 * in the Hub.
 *
 * Keep this list in sync with the required_permissions values declared
 * on individual catalog entries; if a new permission ever becomes
 * relevant (e.g. a future Extra adds another granular permission),
 * extend this function.
 */
function user_can_access_settings_hub(): bool
{
    if (function_exists('is_admin') && is_admin()) {
        return true;
    }
    if (function_exists('check_permission')) {
        if (check_permission('vm_configure')) {
            return true;
        }
        if (check_permission('im_configure')) {
            return true;
        }
    }
    return false;
}

/******************************************************
 * FUNCTION: SETTINGS VISIBILITY — FIX REVIEW DATES   *
 ******************************************************/
/**
 * The fix_review_dates page is only relevant when the mgmt_reviews table
 * still has a varchar next_review column (legacy schema). Matches the
 * predicate sidebar.php used pre-collapse.
 */
function settings_visibility_fix_review_dates(): bool
{
    return function_exists('getTypeOfColumn')
        && getTypeOfColumn('mgmt_reviews', 'next_review') === 'varchar';
}

/********************************************************
 * FUNCTION: SETTINGS VISIBILITY — FIX ENCODING ISSUES  *
 ********************************************************/
/**
 * The fix_upload_encoding_issues page is only relevant when there are
 * upload files with encoding issues. Matches the predicate sidebar.php
 * used pre-collapse.
 */
function settings_visibility_fix_encoding_issues(): bool
{
    return function_exists('has_files_with_encoding_issues')
        && has_files_with_encoding_issues();
}

/******************************************************
 * FUNCTION: ADD USER FAVORITE SETTINGS               *
 ******************************************************/
/**
 * Insert a (user_id, settings_key) pair into user_favorite_settings.
 * Idempotent via ON DUPLICATE KEY UPDATE no-op (composite PK).
 */
function add_user_favorite_settings(int $user_id, string $settings_key): bool
{
    $db = db_open();
    try {
        $stmt = $db->prepare("
            INSERT INTO `user_favorite_settings` (`user_id`, `settings_key`)
            VALUES (:user_id, :settings_key)
            ON DUPLICATE KEY UPDATE `settings_key` = `settings_key`
        ");
        $stmt->bindValue(':user_id',      $user_id,      PDO::PARAM_INT);
        $stmt->bindValue(':settings_key', $settings_key, PDO::PARAM_STR);
        return $stmt->execute();
    } finally {
        db_close($db);
    }
}

/******************************************************
 * FUNCTION: REMOVE USER FAVORITE SETTINGS            *
 ******************************************************/
/**
 * Delete the (user_id, settings_key) row. Idempotent — removing a
 * non-favorite returns true with zero rows affected.
 */
function remove_user_favorite_settings(int $user_id, string $settings_key): bool
{
    $db = db_open();
    try {
        $stmt = $db->prepare("
            DELETE FROM `user_favorite_settings`
            WHERE `user_id` = :user_id AND `settings_key` = :settings_key
        ");
        $stmt->bindValue(':user_id',      $user_id,      PDO::PARAM_INT);
        $stmt->bindValue(':settings_key', $settings_key, PDO::PARAM_STR);
        return $stmt->execute();
    } finally {
        db_close($db);
    }
}

/******************************************************
 * FUNCTION: LIST USER FAVORITE SETTINGS              *
 ******************************************************/
/**
 * Return the list of settings_keys this user has favorited, alphabetical.
 */
function list_user_favorite_settings(int $user_id): array
{
    $db = db_open();
    try {
        $stmt = $db->prepare("
            SELECT `settings_key`
            FROM `user_favorite_settings`
            WHERE `user_id` = :user_id
            ORDER BY `settings_key` ASC
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } finally {
        db_close($db);
    }
}

/******************************************************
 * FUNCTION: DEFAULT FAVORITE SETTINGS KEYS           *
 ******************************************************/
/**
 * Catalog keys seeded as favorites for every new user (and backfilled
 * for every existing user by upgrade_from_20260422001()). These three
 * are the high-traffic admin destinations every admin reaches for:
 * Preferences (default values and behavioral toggles — the most-likely
 * first stop after the legacy monolithic Settings tile was split),
 * Health Check (the diagnostic surface), and Register & Upgrade (license
 * key entry and one-click Core upgrades). These three are also part of
 * the API-failure fallback set rendered by settings-hub.js (alongside
 * Security, which is in the fallback for Base URL recovery but is not a
 * default favorite), so favoriting them by default lines up with "what an
 * admin can always recover to."
 *
 * To change the default set, edit this list and add the new keys to
 * the backfill block in the most recent upgrade function so existing
 * users receive the new defaults too.
 */
function default_favorite_settings_keys(): array
{
    return [
        'settings_preferences',
        'health_check',
        'register',
    ];
}

/******************************************************
 * FUNCTION: SEED DEFAULT FAVORITE SETTINGS           *
 ******************************************************/
/**
 * Seed the default favorite settings tiles for a single user.
 * Idempotent (INSERT IGNORE on the composite PK).
 *
 * The $db parameter lets callers share an already-open connection.
 */
function seed_default_favorite_settings(int $user_id, ?PDO $db = null): void
{
    if ($user_id <= 0) {
        return;
    }
    // Defensive guard: the user_favorite_settings table is created by
    // upgrade_from_20260422001(). If add_user() is called against an
    // instance that hasn't applied that upgrade yet, skip the seed
    // rather than throwing a PDOException that would break user creation.
    if (!table_exists('user_favorite_settings')) {
        return;
    }
    $own_db = false;
    if ($db === null) {
        $db = db_open();
        $own_db = true;
    }
    try {
        $keys = default_favorite_settings_keys();
        $stmt = $db->prepare("
            INSERT IGNORE INTO `user_favorite_settings` (`user_id`, `settings_key`)
            VALUES (:user_id, :settings_key)
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        foreach ($keys as $settings_key) {
            $stmt->bindValue(':settings_key', $settings_key, PDO::PARAM_STR);
            $stmt->execute();
        }
    } finally {
        if ($own_db) {
            db_close($db);
        }
    }
}

/******************************************************
 * FUNCTION: FIND SETTINGS HUB BREADCRUMB FOR REQUEST *
 ******************************************************/
/**
 * Match the current request against the Settings Hub catalog. Returns
 * an array describing the breadcrumb to render, or null when the
 * current page is not a Settings Hub destination.
 *
 * Returns one of:
 *   - null                                    — not a Hub destination.
 *   - ['leaf_label' => '<tile label>']        — top-level catalog tile.
 *   - ['leaf_label' => '<sub-tile label>',
 *      'sub_hub' => ['heading_lang_key' => '<key>',
 *                    'section_key'      => '<key>']]
 *                                             — IM sub-hub destination.
 *
 * The caller resolves the labels via $lang and emits the breadcrumb
 * with "Settings >" as the root and an /admin/index.php href.
 *
 * Top-level catalog matches are checked BEFORE sub-hub matches. For a
 * page like admin/notification.php (which is both the top-level
 * 'notification_extra' tile and the IM sub-hub 'im_notifications' tile),
 * the top-level breadcrumb "Settings > Notification Extra" is the
 * correct interpretation — the IM sub-hub merely surfaces a shortcut
 * to the same destination, but the page belongs to the top-level
 * Notification Extra in the catalog hierarchy.
 *
 * @param string $current_script Typically $_SERVER['SCRIPT_NAME'].
 * @param ?string $current_menu  Typically $_GET['menu'] ?? null.
 */
function find_settings_hub_breadcrumb(string $current_script, ?string $current_menu): ?array
{
    // First pass: top-level tile match. A top-level catalog entry is the
    // canonical home of a page in the Hub's hierarchy, so when a page
    // appears as both a top-level tile and a sub-hub shortcut, the
    // top-level breadcrumb wins.
    foreach (settings_catalog() as $entry) {
        $entry_path = $entry['path'] ?? '';
        $parts = explode('?', $entry_path, 2);
        $script = $parts[0];
        $query  = $parts[1] ?? '';

        if ($query !== '' || $script === '') {
            continue;
        }
        if (str_ends_with($current_script, '/' . $script)) {
            return ['leaf_label' => $entry['label_key']];
        }
    }

    // Second pass: sub-hub tile match. Reached only when the current
    // page isn't a top-level catalog entry but is a child page of a tile
    // declaring a sub_hub. No catalog entry currently declares one, but
    // the plumbing is kept in place for forward compatibility.
    foreach (settings_catalog() as $entry) {
        if (!isset($entry['sub_hub']) || !is_array($entry['sub_hub'])) {
            continue;
        }
        foreach (($entry['sub_hub']['tiles'] ?? []) as $sub) {
            $sub_path = $sub['path'] ?? '';
            $sub_parts = explode('?', $sub_path, 2);
            $sub_script = $sub_parts[0];
            $sub_query  = $sub_parts[1] ?? '';

            if ($sub_script === '' || !str_ends_with($current_script, '/' . $sub_script)) {
                continue;
            }
            if ($sub_query !== '') {
                parse_str($sub_query, $expected_params);
                if (!isset($expected_params['menu']) || $current_menu !== $expected_params['menu']) {
                    continue;
                }
            }
            return [
                'leaf_label' => $sub['label_key'],
                'sub_hub' => [
                    'heading_lang_key' => $entry['sub_hub']['heading_lang_key'] ?? '',
                    'section_key'      => $entry['sub_hub']['section_key'] ?? '',
                ],
            ];
        }
    }
    return null;
}

/******************************************************
 * FUNCTION: COMPUTE EXTRA TILE STATE                 *
 ******************************************************/
/**
 * Resolve the activation/installation state for a catalog tile that
 * represents an Extra. Pure function: callers inject closures that
 * proxy <name>_extra() and is_extra_installed(<name>) so this can be
 * unit-tested without relying on the global function table.
 *
 * Returns one of: 'activated', 'deactivated', 'uninstalled' — or, when the
 * catalog entry declares an 'uninstalled_state' override, that override
 * string instead of 'uninstalled'. (Currently used by the SCF Extra tile
 * to render 'registration_required' since SCF doesn't need a license
 * purchase — the instance just needs to be registered before SCF is
 * downloadable.)
 *
 * Entries without an extra_name field return 'activated' — non-Extras
 * tiles always render as available; the JS does not draw a badge for
 * tiles whose tag set doesn't include 'extras'.
 *
 * @param array $entry          catalog entry, may contain an 'extra_name' key
 * @param callable $is_active   fn(string $extra_name): bool — proxies <name>_extra()
 * @param callable $is_installed fn(string $extra_name): bool — proxies is_extra_installed(<name>)
 */
function compute_extra_tile_state(array $entry, callable $is_active, callable $is_installed): string
{
    $extra_name = $entry['extra_name']
        ?? ($entry['visibility']['extra'] ?? '');

    if ($extra_name === '') {
        return 'activated';
    }

    if ($is_active($extra_name)) {
        return 'activated';
    }
    if ($is_installed($extra_name)) {
        return 'deactivated';
    }
    return $entry['uninstalled_state'] ?? 'uninstalled';
}

/******************************************************
 * FUNCTION: SETTINGS CATALOG ENTRY FOR EXTRA         *
 ******************************************************/
/**
 * The catalog entry that represents a given Extra, or null when the catalog
 * has no tile for it.
 *
 * Exists so a consumer OUTSIDE the Settings Hub can ask the hub's own catalog
 * where an Extra lives and what state vocabulary it uses, instead of keeping a
 * second copy of `path` / `uninstalled_state`. Two places sending users to
 * different install-or-activate flows for the same Extra is a divergence this
 * codebase has had to repair more than once.
 *
 * Pure: settings_catalog() returns a literal array.
 *
 * @param string $extra_name canonical Extra slug, e.g. 'complianceforgescf'
 *
 * @return array|null
 */
function settings_catalog_entry_for_extra(string $extra_name): ?array
{
    foreach (settings_catalog() as $entry) {
        $name = $entry['extra_name'] ?? ($entry['visibility']['extra'] ?? '');
        if ($name !== '' && $name === $extra_name) {
            return $entry;
        }
    }

    return null;
}

/******************************************************
 * FUNCTION: RESOLVE EXTRA AFFORDANCE                 *
 ******************************************************/
/**
 * THE SHARED "SHOW WHAT'S POSSIBLE, MARK WHAT'S LOCKED" DECISION.
 *
 * SimpleRisk's standing habit was to make an affordance for an Extra the
 * customer does not have ABSENT — not greyed, not a teaser. That hides the
 * product from the person most likely to buy it: burying a feature cannot
 * create a sell opportunity. The rule is now the opposite and it is general,
 * not per-surface: SHOW WHAT'S POSSIBLE, AND MARK WHAT'S OUT OF REACH BECAUSE
 * IT ISN'T LICENSED (or isn't downloaded, or isn't switched on). A locked row
 * names the Extra and says how to unlock it; it never pretends to be clickable.
 *
 * This function is the DECISION half of that treatment and is deliberately
 * separate from any rendering, so a dropdown row (Define Control Frameworks'
 * acquisition chooser) and a toolbar button (the Statement of Applicability's
 * PDF/XLSX exports) can reach the same answer without sharing markup. The
 * presentation half is the `.sr-locked*` component in
 * scss/modules/_locked-affordance.scss.
 *
 * It is a thin, pure layer over compute_extra_tile_state() — the Settings Hub's
 * existing state machine — and adds exactly two things that machine cannot know
 * on its own:
 *
 *   1. REGISTRATION. compute_extra_tile_state() collapses "not installed" to
 *      the entry's `uninstalled_state`, which for the SCF tile is the constant
 *      'registration_required'. But an instance that IS already registered has
 *      nothing left to register — its next step is downloading the Extra. So a
 *      'registration_required' result on a registered instance is refined to
 *      'ready_to_download', the same state name the hub's own license
 *      enrichment produces for a downloadable Extra.
 *
 *   2. WHERE TO GO. Each state's unlock destination, taken from the catalog
 *      entry itself (`path`) or from the destination the Settings Hub's click
 *      router already uses for that state, so the two agree by construction:
 *        registration_required / ready_to_download → admin/register.php
 *              (settings-hub.js routes 'registration_required' there, and
 *              register.php is also where core_display_upgrade_extras() renders
 *              the per-Extra download buttons — the same two-step onboarding
 *              getting_started_catalog() encodes as 'register' → 'install_scf')
 *        deactivated                               → the entry's own `path`
 *              (each Extra's admin page carries the Activate button; the hub
 *              opens a modal that POSTs the same activation, and
 *              getting_started_catalog()'s 'activate_scf' points at the page)
 *        purchase                                  → simplerisk.com/extras/
 *              (settings-hub.js's openPurchaseModal() opens exactly this)
 *
 * A NOTE ON 'purchase' vs 'ready_to_download' for a PAID Extra: the hub can
 * tell them apart only after an async license lookup against the SimpleRisk
 * API. Server-side, synchronously, we cannot — so an uninstalled Extra with no
 * `uninstalled_state` override resolves to 'purchase', which is the honest
 * upsell answer and the one the customer most often needs. (A customer who has
 * already bought it will find it waiting on the Register & Upgrade page.)
 *
 * `path` is returned CATALOG-RELATIVE (relative to simplerisk/, e.g.
 * 'admin/register.php'), exactly as settings_catalog() stores it. Callers
 * prefix it for their own depth; that is what keeps a subpath install
 * (https://host/simplerisk/) working without a base-URL lookup.
 *
 * Pure — the three facts it depends on are injected, so every combination is
 * testable without a database, a session, or an installed Extra.
 *
 * @param array $entry         a settings_catalog() entry (see
 *                             settings_catalog_entry_for_extra())
 * @param bool  $is_activated  <name>_extra() — the Extra is switched on
 * @param bool  $is_installed  is_extra_installed(<name>) — its files are present
 * @param bool  $is_registered get_setting('registration_registered') == 1
 *
 * @return array{state: string, path: ?string, external: bool}
 *         state ∈ activated | deactivated | ready_to_download |
 *                 registration_required | purchase
 */
function resolve_extra_affordance(array $entry, bool $is_activated, bool $is_installed, bool $is_registered): array
{
    // "Activated" is only believable when the files are actually there. The
    // activation flag is a settings row and the Extra is a directory, and a
    // restore or a partial upgrade can leave the row saying "on" with nothing
    // behind it — the same drift is_dir() guarded against where this decision
    // used to live. Folding it in here means every consumer inherits the guard
    // instead of each remembering it.
    $really_active = $is_activated && $is_installed;

    $state = compute_extra_tile_state(
        $entry,
        static fn(string $name): bool => $really_active,
        static fn(string $name): bool => $is_installed
    );

    // Refinement 1: registration is a prerequisite, not a permanent state.
    if ($state === 'registration_required' && $is_registered) {
        $state = 'ready_to_download';
    }

    // Refinement 2: an uninstalled Extra with no override is a paid one, and
    // the synchronous answer we can give is the marketplace.
    if ($state === 'uninstalled') {
        $state = 'purchase';
    }

    switch ($state) {
        case 'registration_required':
        case 'ready_to_download':
            return ['state' => $state, 'path' => 'admin/register.php', 'external' => false];
        case 'deactivated':
            return ['state' => $state, 'path' => $entry['path'] ?? null, 'external' => false];
        case 'purchase':
            return ['state' => $state, 'path' => 'https://www.simplerisk.com/extras/', 'external' => true];
        default:
            // 'activated' — nothing to unlock, so nowhere to send anyone.
            return ['state' => $state, 'path' => null, 'external' => false];
    }
}

