<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/includes/api.php'));
require_once(realpath(__DIR__ . '/includes/simplerisk.php'));
require_once(realpath(__DIR__ . '/includes/assets.php'));
require_once(realpath(__DIR__ . '/includes/governance.php'));
require_once(realpath(__DIR__ . '/includes/risks.php'));
require_once(realpath(__DIR__ . '/includes/compliance.php'));
require_once(realpath(__DIR__ . '/includes/artificial_intelligence.php'));
require_once(realpath(__DIR__ . '/includes/reporting.php'));
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/governance.php'));
require_once(realpath(__DIR__ . '/../../includes/compliance.php'));
require_once(realpath(__DIR__ . '/../../includes/reporting.php'));
require_once(realpath(__DIR__ . '/../../includes/api.php'));
require_once(realpath(__DIR__ . '/../../includes/services.php'));
require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/../../includes/Components/SimpleriskApiExceptionHandler.php'));

// Add various security headers except CSP
add_security_headers(true, true, true, true, false);

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

// If access is authenticated
if (api_v2_is_authenticated())
{
    // SimpleRisk Admin Routes
    app()->get('/admin/version', 'api_v2_admin_version');
    app()->get('/admin/version/app', 'api_v2_admin_version_app');
    app()->get('/admin/version/db', 'api_v2_admin_version_db');
    app()->post('/admin/upgrade/db', 'api_v2_admin_upgrade_db');
    app()->get('/admin/write_debug_log', 'api_v2_admin_write_debug_log');
    app()->delete('/admin/tag', 'api_v2_admin_tag_delete');
    app()->delete('/admin/tag/all', 'api_v2_admin_tag_delete_all');
    app()->get('/admin/governance/documents/maptocontrols', 'api_v2_update_all_document_control_mappings');

    // SimpleRisk Assets Routes
    app()->get('/assets', 'api_v2_assets');
    app()->get('/assets/associations', 'api_v2_assets_associations');
    app()->get('/assets/tags', 'api_v2_assets_tags_get');

    // SimpleRisk Governance Routes

    app()->get('/governance/frameworks', 'api_v2_governance_frameworks');
    app()->get('/governance/frameworks/treegrid', 'api_v2_governance_frameworks_treegrid');
    app()->get('/governance/frameworks/associations', 'api_v2_governance_frameworks_associations');
    app()->get('/governance/controls', 'api_v2_governance_controls');
    app()->get('/governance/controls/associations', 'api_v2_governance_controls_associations');
    app()->get('/governance/controls/topdocuments', 'api_v2_governance_controls_top_documents');
    app()->get('/governance/documents', 'api_v2_governance_documents');
    app()->delete('/governance/documents', 'api_v2_governance_documents_delete');
    app()->get('/governance/documents/controls', 'api_v2_governance_documents_to_controls');
    app()->post('/governance/documents/controls', 'getDocumentsToControlsDatatableResponse');
    app()->get('/governance/documents/associations', 'api_v2_governance_documents_associations');
    app()->get('/governance/documents/terms', 'api_v2_governance_documents_significant_terms');
    app()->get('/governance/documents/topcontrols', 'api_v2_governance_documents_top_controls');
    app()->get('/governance/keywords', 'api_v2_governance_keywords');
    app()->post('/governance/save_custom_documents_to_controls_display_settings', 'saveCustomDocumentsToControlsDisplaySettingsAPI');

    // SimpleRisk Risk Routes
    app()->get('/risks', 'api_v2_risks');
    app()->get('/risks/associations', 'api_v2_risks_associations');
    app()->get('/risks/tags', 'api_v2_risks_tags_get');

    //SimpleRisk Compliance Routes
    app()->get('/compliance/tests', 'api_v2_compliance_tests');
    app()->get('/compliance/tests/associations', 'api_v2_compliance_tests_associations');
    app()->get('/compliance/tests/tags', 'api_v2_compliance_tests_tags_get');
    app()->get('/compliance/audits/tags', 'api_v2_compliance_audits_tags_get');

    // SimpleRisk Artificial Intelligence Routes
    app()->get('/ai/recommendations', 'api_v2_ai_recommendations');

    // SimpleRisk Reports Routes
    app()->get('/reports/risk/average', 'api_v2_reports_risk_average');
    app()->get('/reports/risk/opencount', 'api_v2_reports_risk_open_count');

    // RISK API from external app
    // Define the normal routes
    //app()->get('/', 'api_v2_documentation_redirect');
    app()->get('/whoami', 'whoami');
    app()->get('/management', 'show_management');
    app()->get('/management/risk/view', 'viewrisk');
    app()->post('/management/risk/add', 'addRisk');
    app()->post('/management/risk/update', 'updateRisk');
    app()->post('/management/mitigation/add', 'saveMitigation');
    app()->get('/management/mitigation/view', 'viewmitigation');
    app()->post('/management/review/add', 'saveReview');
    app()->get('/management/review/view', 'viewreview');
    app()->get('/admin', 'show_admin');
    app()->get('/admin/users/all', 'allusers');
    app()->get('/admin/users/enabled', 'enabledusers');
    app()->get('/admin/users/disabled', 'disabledusers');
    app()->post('/admin/fields/add', 'customization_addCustomField');
    app()->post('/admin/fields/delete', 'customization_deleteCustomField');
    app()->get('/admin/fields/get', 'customization_getCustomField');
    app()->get('/reports', 'show_reports');
    app()->get('/reports/dynamic', 'dynamicrisk');
    app()->get('/risk_levels', 'risk_levels');

    // RISK API from form
    app()->get('/reports/appetite', 'appetite_report_api');
    app()->post('/reports/high_risk', 'high_risk_report_datatable');
    app()->post('/reports/user_management_reports', 'user_management_reports_api');
    app()->get('/reports/user_management_reports_unique_column_data', 'user_management_reports_unique_column_data_api');

    app()->post('/management/risk/reopen', 'reopenForm');
    app()->get('/management/risk/overview', 'overviewForm');

    app()->post('/reports/dynamic', 'dynamicriskForm');
    app()->post('/reports/dynamic_unique_column_data', 'dynamicriskUniqueColumnDataAPI');
    app()->post('/reports/save-dynamic-selections', 'saveDynamicSelectionsForm');
    app()->post('/reports/delete-dynamic-selection', 'deleteDynamicSelectionForm');
    app()->post('/reports/my_open_risk', 'my_open_risk_datatable');
    app()->post('/reports/recent_commented_risk', 'recent_commented_risk_datatable');
    app()->get('/reports/governance/control_gap_analysis', 'controlGapAnalysisResponse');

    app()->post('/reports/save-graphical-selections', 'saveGraphicalSelectionsForm');
    app()->post('/reports/delete-graphical-selection', 'deleteGraphicalSelectionForm');

    app()->get('/management/risk/viewhtml', 'viewriskHtmlForm');

    app()->get('/management/risk/closerisk', 'closeriskHtmlForm');
    app()->post('/management/risk/closerisk', 'closeriskForm');

    app()->get('/management/risk/view_all_reviews', 'viewAllReviewsForm');
    app()->get('/management/risk/editdetails', 'editdetailsForm');
    app()->post('/management/risk/saveDetails', 'saveDetailsForm');
    app()->post('/management/risk/saveMitigation', 'saveMitigationForm');
    app()->post('/management/risk/saveReview', 'saveReviewForm');

    app()->get('/management/risk/changestatus', 'changestatusForm');
    app()->post('/management/risk/updateStatus', 'updateStatusForm');

    app()->get('/management/risk/mark-unmitigation', 'markUnmitigationForm');
    app()->post('/management/risk/saveMarkUnmitigation', 'saveMarkUnmitigationForm');
    app()->get('/management/risk/mark-unreview', 'markUnreviewForm');
    app()->post('/management/risk/saveMarkUnreview', 'saveMarkUnreviewForm');

    app()->get('/management/risk/scoreaction', 'scoreactionForm');
    app()->post('/management/risk/saveScore', 'saveScoreForm');

    app()->post('/management/risk/saveSubject', 'saveSubjectForm');

    app()->post('/management/risk/saveComment', 'saveCommentForm');
    app()->post('/management/risk/accept_mitigation', 'acceptMitigationForm');
    app()->post('/management/risk/fix_review_date_format', 'fixReviewDateFormat');

    app()->post('/management/impportexport/deleteMapping', 'deleteMapping');

    app()->post('/assessment/update', 'updateAssessment');

    app()->post('/datatable/framework_controls', 'getFrameworkControlsDatatable');
    app()->post('/datatable/mitigation_controls', 'getMitigationControlsDatatable');
    app()->get('/role_responsibilities/get_responsibilities', 'getResponsibilitiesByRoleIdForm');

    /******************** Risk Management Datatatable API **********************/
    app()->post('/risk_management/plan_mitigation', 'getPlanMitigationsDatatableResponse');
    app()->post('/risk_management/managment_review', 'getManagementReviewsDatatableResponse');
    app()->post('/risk_management/review_risks', 'getReviewRisksDatatableResponse');
    app()->get('/risk_management/review_date_issues', 'getReviewsWithDateIssuesDatatableResponse');

    /******************** Custom Display Settings API **********************/
    app()->post('/risk_management/save_custom_plan_mitigation_display_settings', 'saveCustomPlanMitigationDisplaySettingsAPI');
    app()->post('/risk_management/save_custom_perform_reviews_display_settings', 'saveCustomPerformReviewsDisplaySettingsAPI');
    app()->post('/risk_management/save_custom_reviewregularly_display_settings', 'saveCustomReviewregularlyDisplaySettingsAPI');

    /******************** Governance and Compliance API **********************/
    //app()->get('/governance/frameworks', 'getFrameworksResponse');
    app()->get('/governance/tabular_documents', 'getTabularDocumentsResponse');

    app()->post('/governance/update_framework_status', 'updateFrameworkStatusResponse');
    app()->post('/governance/update_framework_parent', 'updateFrameworkParentResponse');
    app()->get('/governance/parent_frameworks_dropdown', 'getParentFrameworksDropdownResponse');
    app()->get('/governance/selected_parent_frameworks_dropdown', 'getSelectedParentFrameworksDropdownResponse');
    app()->get('/governance/control', 'getControlResponse');
    app()->get('/governance/framework', 'getFrameworkResponse');
    app()->post('/governance/update_framework', 'updateFrameworkResponse');
    app()->get('/governance/parent_documents_dropdown', 'getParentDocumentsDropdownResponse');
    app()->get('/governance/documents', 'getDocumentsResponse');
    app()->get('/governance/document', 'getDocumentResponse');
    app()->get('/governance/selected_parent_documents_dropdown', 'getSelectedParentDocumentsDropdownResponse');
    app()->get('/governance/related_controls_by_framework_ids', 'getRelatedControlsByFrameworkIdsResponse');
    app()->get('/governance/rebuild_control_filters', 'getControlFiltersByFrameworksResponse');

    app()->post('/governance/add_control', 'addControlResponse');
    app()->post('/governance/update_control', 'updateControlResponse');

    app()->post('/compliance/define_tests', 'getDefineTestsResponse');
    app()->get('/compliance/test', 'getTestResponse');
    app()->get('/compliance/initiate_audits', 'getInitiateTestAuditsResponse');
    app()->post('/compliance/active_audits', 'getActiveTestAuditsResponse');
    app()->post('/compliance/save_audit_comment', 'saveTestAuditCommentResponse');
    app()->post('/compliance/past_audits', 'getPastTestAuditsResponse');
    app()->post('/compliance/reopen_audit', 'reopenTestAuditResponse');
    app()->post('/compliance/audit_initiation/initiate', 'initiateFrameworkControlTestsResponse');
    app()->get('/compliance/audit_timeline', 'auditTimelineResponse');
    app()->post('/compliance/delete_audit', 'deleteTestAuditResponse');
    /*************************************************************************/

    /******************************* Audit Log API **********************************/
    app()->get('/audit_logs', 'get_audit_logs_api');
    /****************************************** *************************************/

    /******************************* Assets API *************************************/
    app()->post('/assets/update_asset', 'assets_update_asset_API');
    app()->post('/assets/create_asset', 'assets_create_asset_API');
    app()->post('/assets/view/asset_data', 'assets_for_view_API');
    app()->post('/assets/view/action', 'assets_view_action_API');
    app()->get('/assets/options', 'get_asset_options');
    app()->post('/asset-group/create', 'asset_group_create');
    app()->post('/asset-group/update', 'asset_group_update');
    app()->post('/asset-group/delete', 'asset_group_delete');
    app()->post('/asset-group/remove_asset', 'asset_group_remove_asset');
    app()->get('/asset-group/tree', 'asset_group_tree');
    app()->get('/asset-group/info', 'asset_group_info');
    app()->get('/asset-group/options', 'get_asset_group_options');
    app()->post('/assets/create', 'create_asset_api');
    app()->post('/assets/delete', 'delete_asset_api');
    /********************************************************************************/

    /********************* RISK FORMULA API ***************************/
    app()->post('/riskformula/add_impact', 'add_impact_api');
    app()->post('/riskformula/delete_impact', 'delete_impact_api');
    app()->post('/riskformula/add_likelihood', 'add_likelihood_api');
    app()->post('/riskformula/delete_likelihood', 'delete_likelihood_api');
    app()->post('/riskformula/update_impact_or_likelihood_name', 'update_impact_or_likelihood_name_api');
    app()->post('/riskformula/update_custom_score', 'update_custom_score_api');
    /******************************************************************/

    /********************* RISK LEVEL API **************************/
    app()->post('/risklevel/update', 'update_risk_level_API');
    /***************************************************************/

    /********************* CONTRIBUTING RISKS API ***************************/
    app()->post('/contributing_risks/add', 'add_contributing_risks_api');
    app()->post('/contributing_risks/update/likelihood', 'update_contributing_risks_likelihood_api');
    app()->post('/contributing_risks/update/impact', 'update_contributing_risks_impact_api');
    app()->post('/contributing_risks/delete/likelihood', 'delete_contributing_risks_likelihood_api');
    app()->post('/contributing_risks/delete/impact', 'delete_contributing_risks_impact_api');
    app()->post('/contributing_risks/table_list', 'contributing_risks_table_list_api');
    /******************************************************************/

    /***************** DOCUMENTS API *****************/
    app()->post('/documents/create', 'create_document_api');
    app()->post('/documents/update', 'update_document_api');
    app()->post('/documents/delete', 'delete_document_api');
    /***********************************************************/

    /***************** DOCUMENT EXCEPTIONS API *****************/
    app()->post('/exceptions/create', 'create_exception_api');
    app()->post('/exceptions/update', 'update_exception_api');
    app()->post('/exceptions/delete', 'delete_exception_api');
    app()->post('/exceptions/approve', 'approve_exception_api');
    app()->post('/exceptions/unapprove', 'unapprove_exception_api');
    app()->post('/exceptions/batch-delete', 'batch_delete_exception_api');
    app()->get('/exceptions/tree', 'get_exceptions_as_treegrid_api');
    app()->get('/exceptions/exception', 'get_exception_api');
    app()->get('/exceptions/info', 'get_exception_for_display_api');
    app()->get('/exceptions/audit_log', 'get_exceptions_audit_log_api');
    app()->get('/exceptions/status', 'get_exceptions_status_api');
    app()->get('/associated-exceptions/tree', 'get_associated_exceptions_as_treegrid_api');
    /***********************************************************/

    app()->get('/management/tag_options_of_type', 'getTagOptionsOfType');
    app()->get('/management/tag_options_of_types', 'getTagOptionsOfTypes');

    app()->get('/upload_encoding_issue_fix/datatable', 'getFilesWithEncodingIssuesDatatableResponse');
    app()->post('/upload_encoding_issue_fix/file_upload', 'uploadFileToFixFileEncodingIssue');

    // Return scoring histories
    app()->get('/management/risk/scoring_history', 'scoringHistory');
    app()->get('/management/risk/residual_scoring_history', 'residualScoringHistory');

    // Get manager by owner
    app()->get('/user/manager', 'getManagerByUserAPI');

    // Interal api for ajax
    app()->post('/set_custom_display', 'setCustomDisplay');
    app()->post('/set_custom_audits_column', 'setCustomAuditsColumn');

    // Get unfiltered table data
    app()->get('/admin/tables/fullData', 'getTableData');

    // Get Mitigation Control Info
    app()->get('/mitigation_controls/get_mitigation_control_info', 'get_mitigation_control_info');

    // Get Tooltip Info
    app()->post('/likelihood_impact_chart/tooltip', 'get_tooltip_api');

    app()->post('/one_click_upgrade', 'one_click_upgrade');

    /**************************** PROJECT API ******************************/
    app()->post('/management/project/add', 'add_project_api');
    app()->post('/management/project/delete', 'delete_project_api');
    app()->post('/management/project/update', 'update_project_api');
    app()->post('/management/project/edit', 'edit_project_api');
    app()->post('/management/project/update_status', 'update_project_status_api');
    app()->post('/management/project/update_order', 'update_project_order_api');
    app()->get('/management/project/detail', 'detail_project_api');

    // Get risk catalog table data
    app()->get('/admin/risk_catalog/datatable', 'getRiskCatalogDatatableAPI');
    app()->get('/admin/risk_catalog/detail', 'getRiskCatalogAPI');
    app()->post('/admin/risk_catalog/update_order', 'updateRiskCatalogOrderAPI');
    app()->post('/admin/risk_catalog/add_risk_catalog', 'addRiskCatalogAPI');
    app()->post('/admin/risk_catalog/update_risk_catalog', 'updateRiskCatalogAPI');
    app()->post('/admin/risk_catalog/delete_risk_catalog', 'deleteRiskCatalogAPI');
    app()->post('/admin/risk_catalog/swap_groups', 'swapGroupCatalogAPI');

    // Get threat catalog table data
    app()->get('/admin/threat_catalog/datatable', 'getThreatCatalogDatatableAPI');
    app()->get('/admin/threat_catalog/detail', 'getThreatCatalogAPI');
    app()->post('/admin/threat_catalog/update_order', 'updateThreatCatalogOrderAPI');
    app()->post('/admin/threat_catalog/add_threat_catalog', 'addThreatCatalogAPI');
    app()->post('/admin/threat_catalog/update_threat_catalog', 'updateThreatCatalogAPI');
    app()->post('/admin/threat_catalog/delete_threat_catalog', 'deleteThreatCatalogAPI');

    // This status call needs to be available with ComplianceForge SCF disabled
    app()->get('/complianceforgescf/enable', 'api_complianceforgescf_enable');
    app()->get('/complianceforgescf/disable', 'api_complianceforgescf_disable');
    app()->get('/complianceforgescf/status', 'api_complianceforgescf_status');

    // Datatable/report column selection settings API
    app()->post('/admin/column_settings/save_column_settings', 'saveColumnSelectionSettingsAPI');

    // Enable / disable the Incident Management Extra
    app()->post('/admin/incidentmanagement', 'incidentManagementAPI');

    /************************** DATATABLE API BEGIN *******************************/
    app()->post('/get/datatable', 'getDatatableAPI');
    /*************************** DATATABLE API END ********************************/

    /************************** UI LAYOUT API BEGIN *******************************/
    app()->post('/ui/layout', 'api_save_ui_layout');
    app()->get('/ui/layout', 'api_get_ui_layout');
    app()->get('/ui/widget', 'api_get_ui_widget');
    app()->post('/ui/default_layout', 'api_update_default_status');
    /*************************** UI LAYOUT API END ********************************/

    /************************** SIMPLERISK EXTRAS APIS ************************************/

    // If the Advanced Search Extra is enabled
    if (advanced_search_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/advanced_search/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the advanced search routes
            get_advanced_search_routes();
        }
    }

    // If the API Extra is enabled
    if (api_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/api/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the api routes
            get_api_routes();
        }
    }

    // If the Artificial Intelligence Extra is enabled
    if (artificial_intelligence_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/artificial_intelligence/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the artificial intelligence routes
            get_artificial_intelligence_routes();
        }
    }

    // If the Assessments Extra is enabled
    if (assessments_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/assessments/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the assessments routes
            get_assessments_routes();
        }
    }

    // If the Authentication Extra is enabled
    if (custom_authentication_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/authentication/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the authentication routes
            get_authentication_routes();
        }
    }

    // If the ComplianceForge SCF Extra is enabled
    if (complianceforge_scf_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/complianceforgescf/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the complianceforge scf routes
            get_complianceforge_scf_routes();
        }
    }

    // If the Customization Extra is enabled
    if (customization_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/customization/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the customization routes
            get_customization_routes();
        }
    }

    // If the Encryption Extra is enabled
    if (encryption_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/encryption/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the encryption routes
            get_encryption_routes();
        }
    }

    // If the Import Export Extra is enabled
    if (import_export_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/import-export/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the import-export routes
            get_import_export_routes();
        }
    }

    // If the Incident Management Extra is enabled
    if (incident_management_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/incident_management/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the incident management routes
            get_incident_management_routes();
        }
    }

    // If the Jira Extra is enabled
    if (jira_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/jira/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the jira routes
            get_jira_routes();
        }
    }

    // If the Notification Extra is enabled
    if (notification_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/notification/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the notification routes
            get_notification_routes();
        }
    }

    // If the Organizational Hierarchy Extra is enabled
    if (organizational_hierarchy_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/organizational_hierarchy/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the organizational hierarchy routes
            get_organizational_hierarchy_routes();
        }
    }

    // If the Separation Extra is enabled
    if (team_separation_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/separation/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the separation routes
            get_separation_routes();
        }
    }

    // If the UCF Extra is enabled
    if (ucf_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/ucf/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the ucf routes
            get_ucf_routes();
        }
    }

    // If the Vulnerability Management Extra is enabled
    if (vulnmgmt_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../extras/vulnmgmt/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the vulnmgmt routes
            get_vulnmgmt_routes();
        }
    }

    // If the instance is registered
    if (get_setting('registration_registered') != 0)
    {
        // Require file
        $required_file = realpath(__DIR__ . '/../../extras/upgrade/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);

            // Get the upgrade routes
            get_upgrade_routes();
        }
    }

    /**************************************************************************************/

    // Set the error handling if the page is not found
    app()->set404(function ()
    {
        $response['status'] = 404;
        $response['status_message'] = "The Requested API Endpoint Was Not Found";
        $response['data'] = null;
        response()->json($response);
    });

    // Configure leaf logging to the error log
    /*
    Leaf\Config::set([
        'log.enabled' => true,
        'log.dir' => sys_get_temp_dir(),
        'log.file' => 'simplerisk.log',
    ]);
    */

    // Enable the debugging if needed. Default is false.
    app()->config('debug', false);

    // Add the custom Simplerisk API Exception Handler
    app()->setErrorHandler(new SimpleriskApiExceptionHandler());

    // Run the leaf route
    app()->run();

}
// If this request uses the risk assessment questionnaire token
elseif(check_questionnaire_get_token()) {
    // Here's a separate section for when the request is not authenticated,
    // but has a valid questionnaire token.
    // This won't create an authenticated session, but allows questionnaires to
    // serve some data to unauthenticated contacts

    app()->get('/asset-group/options', 'get_asset_group_options_noauth');

    // Configure leaf logging to the error log
    /*
    Leaf\Config::set([
        'log.enabled' => true,
        'log.dir' => sys_get_temp_dir(),
        'log.file' => 'simplerisk.log',
    ]);
    */

    // Enable the debugging if needed. Default is false.
    app()->config('debug', false);

    // Add the custom Simplerisk API Exception Handler
    app()->setErrorHandler(new SimpleriskApiExceptionHandler());

    // Run the leaf route
    app()->run();

}

?>
