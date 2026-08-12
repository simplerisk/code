<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Reports Hub catalog — single source of truth for every report and dashboard
 * shown in the Reports Hub and Dashboards Hub pages.
 *
 * Each entry describes:
 *   label_key   — lang key for the card/sidebar title
 *   desc_key    — lang key for the card description (Task 5 adds these to lang.en.php)
 *   path        — path relative to simplerisk/ root (must point to an existing file)
 *   kind        — 'report' or 'dashboard'
 *   tags        — one or more of: riskmanagement, compliance, governance, asset, incident_management
 *                 (incident_management entries are contributed by the IM Extra via
 *                  extras/incident_management/includes/reports_catalog.php when
 *                  incident_management_extra() returns true)
 *   permissions — access gate: mode='all'|'any', require=[perm_key, ...]
 *
 * Permission keys mirror the $_SESSION keys set by set_user_permissions() on login.
 * 'mode=all' requires every listed key to be truthy (AND-gate).
 * 'mode=any' requires at least one listed key to be truthy (OR-gate).
 */

/*****************************
 * FUNCTION: REPORTS CATALOG *
 *****************************/
function reports_catalog(): array
{
    $catalog = [

        // -----------------------------------------------------------------
        // Dashboards (kind='dashboard')
        // -----------------------------------------------------------------

        'home' => [
            'label_key'   => 'HomeDashboard',
            'desc_key'    => 'HomeDashboardDesc',
            'path'        => 'reports/home.php',
            'kind'        => 'dashboard',
            'tags'        => ['riskmanagement', 'compliance', 'governance'],
            'permissions' => ['mode' => 'any', 'require' => ['riskmanagement', 'compliance', 'governance']],
        ],

        'risk_management_dashboard' => [
            'label_key'   => 'RiskManagementDashboard',
            'desc_key'    => 'RiskManagementDashboardDesc',
            'path'        => 'reports/risk_management_dashboard.php',
            'kind'        => 'dashboard',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'compliance_dashboard' => [
            'label_key'   => 'ComplianceDashboard',
            'desc_key'    => 'ComplianceDashboardDesc',
            'path'        => 'reports/compliance_dashboard.php',
            'kind'        => 'dashboard',
            'tags'        => ['compliance'],
            'permissions' => ['mode' => 'all', 'require' => ['compliance']],
        ],

        'governance_dashboard' => [
            'label_key'   => 'GovernanceDashboard',
            'desc_key'    => 'GovernanceDashboardDesc',
            'path'        => 'reports/governance_dashboard.php',
            'kind'        => 'dashboard',
            'tags'        => ['governance'],
            'permissions' => ['mode' => 'all', 'require' => ['governance']],
        ],

        // -----------------------------------------------------------------
        // Risk Management reports (kind='report', tags=[riskmanagement])
        // -----------------------------------------------------------------

        'risk_charts' => [
            'label_key'   => 'RiskCharts',
            'desc_key'    => 'RiskChartsDesc',
            'path'        => 'reports/dashboard.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'risk_appetite' => [
            'label_key'   => 'RiskAppetiteReport',
            'desc_key'    => 'RiskAppetiteReportDesc',
            'path'        => 'reports/risk_appetite.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'risk_trend' => [
            'label_key'   => 'RiskTrend',
            'desc_key'    => 'RiskTrendDesc',
            'path'        => 'reports/trend.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'dynamic_risk_report' => [
            'label_key'   => 'DynamicRiskReport',
            'desc_key'    => 'DynamicRiskReportDesc',
            'path'        => 'reports/dynamic_risk_report.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'graphical_risk_analysis' => [
            'label_key'   => 'GraphicalRiskAnalysis',
            'desc_key'    => 'GraphicalRiskAnalysisDesc',
            'path'        => 'reports/graphical_risk_analysis.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'risk_average_baseline_metric' => [
            'label_key'   => 'RiskAverageOverTime',
            'desc_key'    => 'RiskAverageOverTimeDesc',
            'path'        => 'reports/risk_average_baseline_metric.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'mean_time_to_remediate' => [
            'label_key'   => 'MeanTimeToRemediate',
            'desc_key'    => 'MeanTimeToRemediateDesc',
            'path'        => 'reports/mean_time_to_remediate.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'likelihood_impact' => [
            'label_key'   => 'LikelihoodImpact',
            'desc_key'    => 'LikelihoodImpactDesc',
            'path'        => 'reports/likelihood_impact.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'risk_advice' => [
            'label_key'   => 'RiskAdvice',
            'desc_key'    => 'RiskAdviceDesc',
            'path'        => 'reports/riskadvice.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'risks_and_issues' => [
            'label_key'   => 'RisksAndIssues',
            'desc_key'    => 'RisksAndIssuesDesc',
            'path'        => 'reports/risks_and_issues.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'my_open' => [
            'label_key'   => 'AllOpenRisksAssignedToMeByRiskLevel',
            'desc_key'    => 'AllOpenRisksAssignedToMeByRiskLevelDesc',
            'path'        => 'reports/my_open.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'review_needed' => [
            'label_key'   => 'AllOpenRisksNeedingReview',
            'desc_key'    => 'AllOpenRisksNeedingReviewDesc',
            'path'        => 'reports/review_needed.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'risks_open_by_team' => [
            'label_key'   => 'AllOpenRisksByTeamByLevel',
            'desc_key'    => 'AllOpenRisksByTeamByLevelDesc',
            'path'        => 'reports/risks_open_by_team.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'high' => [
            'label_key'   => 'HighRiskReport',
            'desc_key'    => 'HighRiskReportDesc',
            'path'        => 'reports/high.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'submitted_by_date' => [
            'label_key'   => 'SubmittedRisksByDate',
            'desc_key'    => 'SubmittedRisksByDateDesc',
            'path'        => 'reports/submitted_by_date.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'mitigations_by_date' => [
            'label_key'   => 'MitigationsByDate',
            'desc_key'    => 'MitigationsByDateDesc',
            'path'        => 'reports/mitigations_by_date.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'mgmt_reviews_by_date' => [
            'label_key'   => 'ManagementReviewsByDate',
            'desc_key'    => 'ManagementReviewsByDateDesc',
            'path'        => 'reports/mgmt_reviews_by_date.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'closed_by_date' => [
            'label_key'   => 'ClosedRisksByDate',
            'desc_key'    => 'ClosedRisksByDateDesc',
            'path'        => 'reports/closed_by_date.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'recent_commented' => [
            'label_key'   => 'CurrentRiskComments',
            'desc_key'    => 'CurrentRiskCommentsDesc',
            'path'        => 'reports/recent_commented.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'closed' => [
            'label_key'   => 'AllClosedRisksByRiskLevel',
            'desc_key'    => 'AllClosedRisksByRiskLevelDesc',
            'path'        => 'reports/closed.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'next_review' => [
            'label_key'   => 'AllOpenRisksAcceptedUntilNextReviewByRiskLevel',
            'desc_key'    => 'AllOpenRisksAcceptedUntilNextReviewByRiskLevelDesc',
            'path'        => 'reports/next_review.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'open' => [
            'label_key'   => 'AllOpenRisksByRiskLevel',
            'desc_key'    => 'AllOpenRisksByRiskLevelDesc',
            'path'        => 'reports/open.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'production_issues' => [
            'label_key'   => 'AllOpenRisksToSubmitAsAProductionIssueByRiskLevel',
            'desc_key'    => 'AllOpenRisksToSubmitAsAProductionIssueByRiskLevelDesc',
            'path'        => 'reports/production_issues.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'projects' => [
            'label_key'   => 'AllOpenRisksConsideredForProjectsByRiskLevel',
            'desc_key'    => 'AllOpenRisksConsideredForProjectsByRiskLevelDesc',
            'path'        => 'reports/projects.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'projects_and_risks' => [
            'label_key'   => 'ProjectsAndRisksAssigned',
            'desc_key'    => 'ProjectsAndRisksAssignedDesc',
            'path'        => 'reports/projects_and_risks.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'risk_scoring' => [
            'label_key'   => 'AllOpenRisksByScoringMethod',
            'desc_key'    => 'AllOpenRisksByScoringMethodDesc',
            'path'        => 'reports/risk_scoring.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'teams' => [
            'label_key'   => 'AllOpenRisksByTeam',
            'desc_key'    => 'AllOpenRisksByTeamDesc',
            'path'        => 'reports/teams.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        'technologies' => [
            'label_key'   => 'AllOpenRisksByTechnology',
            'desc_key'    => 'AllOpenRisksByTechnologyDesc',
            'path'        => 'reports/technologies.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement']],
        ],

        // -----------------------------------------------------------------
        // Compliance reports (kind='report', tags=[compliance])
        // -----------------------------------------------------------------

        'dynamic_audit_report' => [
            'label_key'   => 'DynamicAuditReport',
            'desc_key'    => 'DynamicAuditReportDesc',
            'path'        => 'reports/dynamic_audit_report.php',
            'kind'        => 'report',
            'tags'        => ['compliance'],
            'permissions' => ['mode' => 'all', 'require' => ['compliance']],
        ],

        'audit_timeline' => [
            'label_key'   => 'AuditTimeline',
            'desc_key'    => 'AuditTimelineDesc',
            'path'        => 'reports/audit_timeline.php',
            'kind'        => 'report',
            'tags'        => ['compliance'],
            'permissions' => ['mode' => 'all', 'require' => ['compliance']],
        ],

        'audit_remediation_cycle_time' => [
            'label_key'   => 'AuditRemediationCycleTime',
            'desc_key'    => 'AuditRemediationCycleTimeDesc',
            'path'        => 'reports/audit_remediation_cycle_time.php',
            'kind'        => 'report',
            'tags'        => ['compliance'],
            'permissions' => ['mode' => 'all', 'require' => ['compliance']],
        ],

        // -----------------------------------------------------------------
        // Governance reports (kind='report', tags=[governance])
        // -----------------------------------------------------------------

        'control_gap_analysis' => [
            'label_key'   => 'ControlGapAnalysis',
            'desc_key'    => 'ControlGapAnalysisDesc',
            'path'        => 'reports/control_gap_analysis.php',
            'kind'        => 'report',
            'tags'        => ['governance'],
            'permissions' => ['mode' => 'all', 'require' => ['governance']],
        ],

        // The report is CORE -- `governance` and nothing more, the same gate the
        // controls page it draws from already uses. Only its EXPORT is gated on
        // the Import/Export Extra, which is decided inside the report rather than
        // here: an Extra-gated catalog entry would hide the whole document from
        // every customer who has not bought an Extra they do not need to read it.
        //
        // It takes a ?framework= parameter, which the Hub cannot supply. Opening
        // it from the Hub therefore lands on a framework picker rather than an
        // error -- an SoA is a per-framework document and there is no
        // cross-framework roll-up to show instead.
        'statement_of_applicability' => [
            'label_key'   => 'StatementOfApplicability',
            'desc_key'    => 'StatementOfApplicabilityDesc',
            'path'        => 'reports/statement_of_applicability.php',
            'kind'        => 'report',
            'tags'        => ['governance'],
            'permissions' => ['mode' => 'all', 'require' => ['governance']],
        ],

        'document_program_report' => [
            'label_key'   => 'DocumentProgramReport',
            'desc_key'    => 'DocumentProgramReportDesc',
            'path'        => 'reports/document_program_report.php',
            'kind'        => 'report',
            'tags'        => ['governance'],
            'permissions' => ['mode' => 'all', 'require' => ['governance']],
        ],

        'exception_report' => [
            'label_key'   => 'ExceptionReport',
            'desc_key'    => 'ExceptionReportDesc',
            'path'        => 'reports/exception_report.php',
            'kind'        => 'report',
            'tags'        => ['governance'],
            'permissions' => ['mode' => 'all', 'require' => ['governance']],
        ],

        // -----------------------------------------------------------------
        // Multi-tagged reports (cross-domain, kind='report')
        // -----------------------------------------------------------------

        'connectivity_visualizer' => [
            'label_key'   => 'ConnectivityVisualizer',
            'desc_key'    => 'ConnectivityVisualizerDesc',
            'path'        => 'reports/connectivity_visualizer.php',
            'kind'        => 'report',
            // 'tags' stays the 4-domain category taxonomy the Reports Hub
            // filter uses (design intent, not a permission list) --
            // view_exception has no corresponding category chip. Keep
            // 'permissions.require' in sync with connectivity_visualizer.php's
            // render_header_and_sidebar() check_any_of list instead: both
            // gate the SAME page, and drifting apart either hides the tile
            // from someone who can open the page (this bug, pre-fix) or
            // shows a tile that 403s.
            'tags'        => ['riskmanagement', 'asset', 'governance', 'compliance'],
            'permissions' => ['mode' => 'any', 'require' => ['riskmanagement', 'asset', 'governance', 'compliance', 'view_exception']],
        ],

        'risks_and_assets' => [
            'label_key'   => 'RisksAndAssets',
            'desc_key'    => 'RisksAndAssetsDesc',
            'path'        => 'reports/risks_and_assets.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement', 'asset'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement', 'asset']],
        ],

        'risks_and_controls' => [
            'label_key'   => 'RisksAndControls',
            'desc_key'    => 'RisksAndControlsDesc',
            'path'        => 'reports/risks_and_controls.php',
            'kind'        => 'report',
            'tags'        => ['riskmanagement', 'governance'],
            'permissions' => ['mode' => 'all', 'require' => ['riskmanagement', 'governance']],
        ],

        'assets_and_controls' => [
            'label_key'   => 'AssetsAndControls',
            'desc_key'    => 'AssetsAndControlsDesc',
            'path'        => 'reports/assets_and_controls.php',
            'kind'        => 'report',
            'tags'        => ['asset', 'governance'],
            'permissions' => ['mode' => 'all', 'require' => ['asset', 'governance']],
        ],

        'documents_to_controls' => [
            'label_key'   => 'DocumentControlMapping',
            'desc_key'    => 'DocumentControlMappingDesc',
            'path'        => 'reports/documents_to_controls.php',
            'kind'        => 'report',
            'tags'        => ['governance'],
            'permissions' => ['mode' => 'all', 'require' => ['governance']],
        ],

    ];

    // Merge entries contributed by Extras when each Extra is enabled.
    // Core entries always win on key collision (`+=` preserves the LHS
    // value when keys match), so an Extra cannot silently override a
    // Core report.
    if (function_exists('incident_management_extra') && incident_management_extra()) {
        require_once(realpath(__DIR__ . '/../extras/incident_management/includes/reports_catalog.php'));
        $catalog += incident_management_reports_catalog_entries();

        // Home is the universal dashboard — it can host incident widgets, so
        // surface it under the Incident Management chip too, but ONLY when the IM
        // Extra is installed (mirroring how the chip and the IM catalog entries
        // are gated). The DB-backed gate is here; the pure tag/permission edit is
        // factored into augment_home_catalog_for_incident_management() below so it
        // can be unit-tested without the DB.
        $catalog = augment_home_catalog_for_incident_management($catalog);
    }

    return $catalog;
}

/**********************************************************
 * FUNCTION: AUGMENT HOME CATALOG FOR INCIDENT MANAGEMENT *
 **********************************************************/
/**
 * When the Incident Management Extra is active, Home (the universal dashboard)
 * should also appear under the Incident Management chip on the Dashboards hub,
 * because it can host incident widgets (themselves im_reporting-guarded). Adds
 * the 'incident_management' tag and the 'im_reporting' permission to the 'home'
 * entry so IM-permitted users see Home under the IM chip and an im_reporting-only
 * user can reach it (Home's permission mode is 'any').
 *
 * Pure (no DB/session): the DB-backed incident_management_extra() gate lives at
 * the single call site in reports_catalog(). Idempotent — the in_array guards
 * mean calling it more than once never duplicates the tag/permission. A no-op
 * when there is no 'home' entry.
 *
 * @param array $catalog the catalog to augment
 * @return array the catalog with Home augmented (unchanged if no 'home' entry)
 */
function augment_home_catalog_for_incident_management(array $catalog): array
{
    if (!isset($catalog['home']) || !is_array($catalog['home'])) {
        return $catalog;
    }

    $home = $catalog['home'];

    // Read both slices into their own locals BEFORE mutating $home. Appending to
    // $home['tags'] in place would narrow Phan's inferred shape of $home to just
    // {tags:...}, making a subsequent ['permissions'] read look like an invalid
    // offset — so pull both out first, mutate the locals, then reassign whole.
    $tags        = (isset($home['tags']) && is_array($home['tags'])) ? $home['tags'] : null;
    $permissions = (isset($home['permissions']) && is_array($home['permissions'])) ? $home['permissions'] : null;

    if (is_array($tags) && !in_array('incident_management', $tags, true)) {
        $tags[] = 'incident_management';
        $home['tags'] = $tags;
    }

    if (is_array($permissions) && isset($permissions['require']) && is_array($permissions['require'])
        && !in_array('im_reporting', $permissions['require'], true)) {
        $permissions['require'][] = 'im_reporting';
        $home['permissions'] = $permissions;
    }

    $catalog['home'] = $home;

    return $catalog;
}

/***********************************
 * FUNCTION: USER CAN ACCESS REPORT *
 ***********************************/
/**
 * Decide whether the current user can open the given catalog entry.
 * Mirrors the page-level add_session_check() shape: 'all' = AND-gate,
 * 'any' = OR-gate over the listed permission keys.
 *
 * Reads $_SESSION directly (set by set_user_permissions on login).
 */
function user_can_access_report(array $entry): bool
{
    $required = $entry['permissions']['require'] ?? [];
    if (!is_array($required) || empty($required)) {
        // Malformed entry — deny rather than grant. Asymmetric mode=all/any
        // empty-require behavior is a latent footgun; close it explicitly.
        return false;
    }
    if ($entry['permissions']['mode'] === 'any') {
        foreach ($required as $perm) {
            if (!empty($_SESSION[$perm])) {
                return true;
            }
        }
        return false;
    }
    foreach ($required as $perm) {
        if (empty($_SESSION[$perm])) {
            return false;
        }
    }
    return true;
}

/**
 * Insert a (user_id, report_key) pair into user_favorite_reports.
 * Idempotent: PRIMARY KEY collision is handled via ON DUPLICATE KEY UPDATE
 * with a no-op assignment.
 */
function add_user_favorite_report(int $user_id, string $report_key): bool
{
    $db = db_open();
    try {
        $stmt = $db->prepare("
            INSERT INTO `user_favorite_reports` (`user_id`, `report_key`)
            VALUES (:user_id, :report_key)
            ON DUPLICATE KEY UPDATE `report_key` = `report_key`
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':report_key', $report_key, PDO::PARAM_STR);
        return $stmt->execute();
    } finally {
        db_close($db);
    }
}

/**
 * Delete the (user_id, report_key) row. Naturally idempotent: deleting a
 * nonexistent row returns true (zero rows affected, no error).
 */
function remove_user_favorite_report(int $user_id, string $report_key): bool
{
    $db = db_open();
    try {
        $stmt = $db->prepare("
            DELETE FROM `user_favorite_reports`
            WHERE `user_id` = :user_id AND `report_key` = :report_key
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':report_key', $report_key, PDO::PARAM_STR);
        return $stmt->execute();
    } finally {
        db_close($db);
    }
}

/**
 * Return the list of report_keys this user has favorited, alphabetical.
 */
function list_user_favorite_reports(int $user_id): array
{
    $db = db_open();
    try {
        $stmt = $db->prepare("
            SELECT `report_key`
            FROM `user_favorite_reports`
            WHERE `user_id` = :user_id
            ORDER BY `report_key` ASC
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } finally {
        db_close($db);
    }
}

/**
 * Catalog keys seeded as favorites for every new user (and backfilled
 * for every existing user by upgrade_from_20260302001()). These three
 * are broadly useful regardless of role: a configurable risk-report
 * builder, a configurable audit-report builder, and the cross-domain
 * visualizer that ties them together.
 *
 * To change the default set, edit this list and add the new keys to the
 * backfill block in the most recent upgrade function so existing users
 * receive the new defaults too.
 */
function default_favorite_report_keys(): array
{
    return [
        'connectivity_visualizer',
        'dynamic_risk_report',
        'dynamic_audit_report',
    ];
}

/**
 * Seed the default favorite reports for a single user. Idempotent —
 * INSERT IGNORE on the composite PK preserves any pre-existing rows,
 * so users who already favorited any of these (or who unfavorite them
 * later) are unaffected on re-run.
 *
 * Permission-blind by design: every user gets every default key,
 * regardless of whether they currently have the permission required
 * to open that report. This matches the rest of the favorites model,
 * where user_favorite_reports rows can exist for entries the user can
 * no longer access (e.g. after a permission revocation) and the
 * catalog / favorites endpoints filter via user_can_access_report()
 * at display time. The trade-off is a couple of inert DB rows per
 * user in exchange for forward-compatibility: a user who later gains
 * a missing permission immediately sees the relevant default favorite
 * without needing a re-seed.
 *
 * Called from add_user() for every new user. The $db parameter lets
 * callers share an already-open connection (avoiding the open/close
 * overhead inside a wrapping transaction-like flow).
 */
function seed_default_favorite_reports(int $user_id, ?PDO $db = null): void
{
    if ($user_id <= 0) {
        return;
    }
    // Defensive guard: the user_favorite_reports table is created by
    // upgrade_from_20260422001(). If add_user() is called against an
    // instance that hasn't applied that upgrade yet, skip the seed
    // rather than throwing a PDOException that would break user creation.
    if (!table_exists('user_favorite_reports')) {
        return;
    }
    $own_db = false;
    if ($db === null) {
        $db = db_open();
        $own_db = true;
    }
    try {
        $stmt = $db->prepare("
            INSERT IGNORE INTO `user_favorite_reports` (`user_id`, `report_key`)
            VALUES (:user_id, :report_key)
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        foreach (default_favorite_report_keys() as $report_key) {
            $stmt->bindValue(':report_key', $report_key, PDO::PARAM_STR);
            $stmt->execute();
        }
    } finally {
        if ($own_db) {
            db_close($db);
        }
    }
}
