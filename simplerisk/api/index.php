<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));
    require_once(realpath(__DIR__ . '/../includes/api.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

    // Add various security headers except CSP
    add_security_headers(true, true, true, true, false);

    // Include the language file
    // Ignoring detections related to language files
    // @phan-suppress-next-line SecurityCheck-PathTraversal
    require_once(language_file());
    
    // If access is authenticated
    if (is_authenticated())
    {
        // Initialize the epiphany api
        Epi::init('api', 'route', 'session');

        // Disable exceptions
        Epi::setSetting('exceptions', true);

        // RISK API from external app
        // Define the normal routes
        getRoute()->get('/', 'show_endpoints');
        getRoute()->get('/version', 'show_version');
        getRoute()->get('/whoami', 'whoami');
        getRoute()->get('/management', 'show_management');
        getRoute()->get('/management/risk/view', 'viewrisk');
        getRoute()->post('/management/risk/add', 'addRisk');
        getRoute()->post('/management/risk/update', 'updateRisk');
        getRoute()->post('/management/mitigation/add', 'saveMitigation');
        getRoute()->get('/management/mitigation/view', 'viewmitigation');
        getRoute()->post('/management/review/add', 'saveReview');
        getRoute()->get('/management/review/view', 'viewreview');
        getRoute()->get('/admin', 'show_admin');
        getRoute()->get('/admin/users/all', 'allusers');
        getRoute()->get('/admin/users/enabled', 'enabledusers');
        getRoute()->get('/admin/users/disabled', 'disabledusers');
        getRoute()->post('/admin/fields/add', 'customization_addCustomField');
        getRoute()->post('/admin/fields/delete', 'customization_deleteCustomField');
        getRoute()->get('/admin/fields/get', 'customization_getCustomField');
        getRoute()->get('/reports', 'show_reports');
        getRoute()->get('/reports/dynamic', 'dynamicrisk');
        getRoute()->get('/risk_levels', 'risk_levels');

        // RISK API from form
        getRoute()->get('/reports/appetite', 'appetite_report_api');
        getRoute()->post('/reports/high_risk', 'high_risk_report_datatable');
        getRoute()->post('/reports/user_management_reports', 'user_management_reports_api');
        getRoute()->get('/reports/user_management_reports_unique_column_data', 'user_management_reports_unique_column_data_api');

        getRoute()->post('/management/risk/reopen', 'reopenForm');
        getRoute()->get('/management/risk/overview', 'overviewForm');

        getRoute()->post('/reports/dynamic', 'dynamicriskForm');
        getRoute()->post('/reports/dynamic_unique_column_data', 'dynamicriskUniqueColumnDataAPI');
        getRoute()->post('/reports/save-dynamic-selections', 'saveDynamicSelectionsForm');
        getRoute()->post('/reports/delete-dynamic-selection', 'deleteDynamicSelectionForm');
        getRoute()->post('/reports/my_open_risk', 'my_open_risk_datatable');
        getRoute()->post('/reports/recent_commented_risk', 'recent_commented_risk_datatable');
        getRoute()->get('/reports/governance/control_gap_analysis', 'controlGapAnalysisResponse');

        getRoute()->post('/reports/save-graphical-selections', 'saveGraphicalSelectionsForm');
        getRoute()->post('/reports/delete-graphical-selection', 'deleteGraphicalSelectionForm');

        getRoute()->get('/management/risk/viewhtml', 'viewriskHtmlForm');

        getRoute()->get('/management/risk/closerisk', 'closeriskHtmlForm');
        getRoute()->post('/management/risk/closerisk', 'closeriskForm');

        getRoute()->get('/management/risk/view_all_reviews', 'viewAllReviewsForm');
        getRoute()->get('/management/risk/editdetails', 'editdetailsForm');
        getRoute()->post('/management/risk/saveDetails', 'saveDetailsForm');
        getRoute()->post('/management/risk/saveMitigation', 'saveMitigationForm');
        getRoute()->post('/management/risk/saveReview', 'saveReviewForm');

        getRoute()->get('/management/risk/changestatus', 'changestatusForm');
        getRoute()->post('/management/risk/updateStatus', 'updateStatusForm');

        getRoute()->get('/management/risk/mark-unmitigation', 'markUnmitigationForm');
        getRoute()->post('/management/risk/saveMarkUnmitigation', 'saveMarkUnmitigationForm');
        getRoute()->get('/management/risk/mark-unreview', 'markUnreviewForm');
        getRoute()->post('/management/risk/saveMarkUnreview', 'saveMarkUnreviewForm');

        getRoute()->get('/management/risk/scoreaction', 'scoreactionForm');
        getRoute()->post('/management/risk/saveScore', 'saveScoreForm');

        getRoute()->post('/management/risk/saveSubject', 'saveSubjectForm');

        getRoute()->post('/management/risk/saveComment', 'saveCommentForm');
        getRoute()->post('/management/risk/accept_mitigation', 'acceptMitigationForm');
        getRoute()->post('/management/risk/fix_review_date_format', 'fixReviewDateFormat');

        getRoute()->post('/management/impportexport/deleteMapping', 'deleteMapping');

        getRoute()->post('/assessment/update', 'updateAssessment');

        getRoute()->post('/datatable/framework_controls', 'getFrameworkControlsDatatable');
        getRoute()->get('/datatable/mitigation_controls', 'getMitigationControlsDatatable');
        getRoute()->get('/role_responsibilities/get_responsibilities', 'getResponsibilitiesByRoleIdForm');

        /******************** Risk Management Datatatable API **********************/
        getRoute()->post('/risk_management/plan_mitigation', 'getPlanMitigationsDatatableResponse');
        getRoute()->post('/risk_management/managment_review', 'getManagementReviewsDatatableResponse');
        getRoute()->post('/risk_management/review_risks', 'getReviewRisksDatatableResponse');
        getRoute()->get('/risk_management/review_date_issues', 'getReviewsWithDateIssuesDatatableResponse');

        /******************** Custom Display Settings API **********************/
        getRoute()->post('/risk_management/save_custom_plan_mitigation_display_settings', 'saveCustomPlanMitigationDisplaySettingsAPI');
        getRoute()->post('/risk_management/save_custom_perform_reviews_display_settings', 'saveCustomPerformReviewsDisplaySettingsAPI');
        getRoute()->post('/risk_management/save_custom_reviewregularly_display_settings', 'saveCustomReviewregularlyDisplaySettingsAPI');

        /******************** Governance and Compliance API **********************/
        getRoute()->get('/governance/frameworks', 'getFrameworksResponse');
        getRoute()->get('/governance/tabular_documents', 'getTabularDocumentsResponse');

        getRoute()->post('/governance/update_framework_status', 'updateFrameworkStatusResponse');
        getRoute()->post('/governance/update_framework_parent', 'updateFrameworkParentResponse');
        getRoute()->get('/governance/parent_frameworks_dropdown', 'getParentFrameworksDropdownResponse');
        getRoute()->get('/governance/selected_parent_frameworks_dropdown', 'getSelectedParentFrameworksDropdownResponse');
        getRoute()->get('/governance/control', 'getControlResponse');
        getRoute()->get('/governance/framework', 'getFrameworkResponse');
        getRoute()->get('/governance/parent_documents_dropdown', 'getParentDocumentsDropdownResponse');
        getRoute()->get('/governance/documents', 'getDocumentsResponse');
        getRoute()->get('/governance/document', 'getDocumentResponse');
        getRoute()->get('/governance/selected_parent_documents_dropdown', 'getSelectedParentDocumentsDropdownResponse');
        getRoute()->get('/governance/related_controls_by_framework_ids', 'getRelatedControlsByFrameworkIdsResponse');
        getRoute()->get('/governance/rebuild_control_filters', 'getControlFiltersByFrameworksResponse');
        
        getRoute()->post('/governance/add_control', 'addControlResponse');
        getRoute()->post('/governance/update_control', 'updateControlResponse');

        getRoute()->get('/compliance/define_tests', 'getDefineTestsResponse');
        getRoute()->get('/compliance/test', 'getTestResponse');
        getRoute()->get('/compliance/initiate_audits', 'getInitiateTestAuditsResponse');
        getRoute()->post('/compliance/active_audits', 'getActiveTestAuditsResponse');
        getRoute()->post('/compliance/save_audit_comment', 'saveTestAuditCommentResponse');
        getRoute()->post('/compliance/past_audits', 'getPastTestAuditsResponse');
        getRoute()->post('/compliance/reopen_audit', 'reopenTestAuditResponse');
        getRoute()->post('/compliance/audit_initiation/initiate', 'initiateFrameworkControlTestsResponse');
        getRoute()->get('/compliance/audit_timeline', 'auditTimelineResponse');
        getRoute()->post('/compliance/delete_audit', 'deleteTestAuditResponse');
        /*************************************************************************/

        /******************************* Audit Log API **********************************/
        getRoute()->get('/audit_logs', 'get_audit_logs_api');
        /****************************************** *************************************/
        
        /******************************* Assets API *************************************/
        getRoute()->post('/assets/update_asset', 'assets_update_asset_API_switch');
        getRoute()->post('/assets/view/asset_data', 'assets_for_view_API');
        getRoute()->post('/assets/view/action', 'assets_view_action_API');
        getRoute()->get('/assets/options', 'get_asset_options');
        getRoute()->post('/asset-group/create', 'asset_group_create');
        getRoute()->post('/asset-group/update', 'asset_group_update');
        getRoute()->post('/asset-group/delete', 'asset_group_delete');
        getRoute()->post('/asset-group/remove_asset', 'asset_group_remove_asset');
        getRoute()->get('/asset-group/tree', 'asset_group_tree');
        getRoute()->get('/asset-group/info', 'asset_group_info');
        getRoute()->get('/asset-group/options', 'get_asset_group_options');
        /********************************************************************************/

        /********************* RISK FORMULA API ***************************/
        getRoute()->post('/riskformula/add_impact', 'add_impact_api');
        getRoute()->post('/riskformula/delete_impact', 'delete_impact_api');
        getRoute()->post('/riskformula/add_likelihood', 'add_likelihood_api');
        getRoute()->post('/riskformula/delete_likelihood', 'delete_likelihood_api');
        getRoute()->post('/riskformula/update_impact_or_likelihood_name', 'update_impact_or_likelihood_name_api');
        getRoute()->post('/riskformula/update_custom_score', 'update_custom_score_api');
        /******************************************************************/

        /********************* RISK LEVEL API **************************/
        getRoute()->post('/risklevel/update', 'update_risk_level_API');
        /***************************************************************/

        /********************* CONTRIBUTING RISKS API ***************************/
        getRoute()->post('/contributing_risks/add', 'add_contributing_risks_api');
        getRoute()->post('/contributing_risks/update/likelihood', 'update_contributing_risks_likelihood_api');
        getRoute()->post('/contributing_risks/update/impact', 'update_contributing_risks_impact_api');
        getRoute()->post('/contributing_risks/delete/likelihood', 'delete_contributing_risks_likelihood_api');
        getRoute()->post('/contributing_risks/delete/impact', 'delete_contributing_risks_impact_api');
        getRoute()->post('/contributing_risks/table_list', 'contributing_risks_table_list_api');
        /******************************************************************/

        /***************** DOCUMENTS API *****************/
        getRoute()->post('/documents/create', 'create_document_api');
        getRoute()->post('/documents/update', 'update_document_api');
        getRoute()->post('/documents/delete', 'delete_document_api');
        /***********************************************************/

        /***************** DOCUMENT EXCEPTIONS API *****************/
        getRoute()->post('/exceptions/create', 'create_exception_api');
        getRoute()->post('/exceptions/update', 'update_exception_api');
        getRoute()->post('/exceptions/delete', 'delete_exception_api');
        getRoute()->post('/exceptions/approve', 'approve_exception_api');
        getRoute()->post('/exceptions/batch-delete', 'batch_delete_exception_api');
        getRoute()->get('/exceptions/tree', 'get_exceptions_as_treegrid_api');
        getRoute()->get('/exceptions/exception', 'get_exception_api');
        getRoute()->get('/exceptions/info', 'get_exception_for_display_api');
        getRoute()->get('/exceptions/audit_log', 'get_exceptions_audit_log_api');
        /***********************************************************/

        getRoute()->get('/management/tag_options_of_type', 'getTagOptionsOfType');
        getRoute()->get('/management/tag_options_of_types', 'getTagOptionsOfTypes');

        getRoute()->get('/upload_encoding_issue_fix/datatable', 'getFilesWithEncodingIssuesDatatableResponse');
        getRoute()->post('/upload_encoding_issue_fix/file_upload', 'uploadFileToFixFileEncodingIssue');
        
        // Return scoring histories
        getRoute()->get('/management/risk/scoring_history', 'scoringHistory');
        getRoute()->get('/management/risk/residual_scoring_history', 'residualScoringHistory');
        
        // Get manager by owner
        getRoute()->get('/user/manager', 'getManagerByUserAPI');

        // Interal api for ajax
        getRoute()->post('/set_custom_display', 'setCustomDisplay');
        getRoute()->post('/set_custom_audits_column', 'setCustomAuditsColumn');

        // Define the API routes
        getApi()->get('/version.json', 'api_version', EpiApi::external);

        // Get unfiltered table data
        getRoute()->get('/admin/tables/fullData', 'getTableData');
        
        // Get Mitigation Control Info
        getRoute()->get('/mitigation_controls/get_mitigation_control_info', 'get_mitigation_control_info');

        // Get Tooltip Info
        getRoute()->post('/likelihood_impact_chart/tooltip', 'get_tooltip_api');

        getRoute()->post('/one_click_upgrade', 'one_click_upgrade');

        /**************************** PROJECT API ******************************/
        getRoute()->post('/management/project/add', 'add_project_api');
        getRoute()->post('/management/project/delete', 'delete_project_api');
        getRoute()->post('/management/project/update', 'update_project_api');
        getRoute()->post('/management/project/edit', 'edit_project_api');
        getRoute()->post('/management/project/update_status', 'update_project_status_api');
        getRoute()->post('/management/project/update_order', 'update_project_order_api');
        getRoute()->get('/management/project/detail', 'detail_project_api');

        // Get risk catalog table data
        getRoute()->get('/admin/risk_catalog/datatable', 'getRiskCatalogDatatableAPI');
        getRoute()->get('/admin/risk_catalog/detail', 'getRiskCatalogAPI');
        getRoute()->post('/admin/risk_catalog/update_order', 'updateRiskCatalogOrderAPI');
        getRoute()->post('/admin/risk_catalog/add_risk_catalog', 'addRiskCatalogAPI');
        getRoute()->post('/admin/risk_catalog/update_risk_catalog', 'updateRiskCatalogAPI');
        getRoute()->post('/admin/risk_catalog/delete_risk_catalog', 'deleteRiskCatalogAPI');
        getRoute()->post('/admin/risk_catalog/swap_groups', 'swapGroupCatalogAPI');

        // Get threat catalog table data
        getRoute()->get('/admin/threat_catalog/datatable', 'getThreatCatalogDatatableAPI');
        getRoute()->get('/admin/threat_catalog/detail', 'getThreatCatalogAPI');
        getRoute()->post('/admin/threat_catalog/update_order', 'updateThreatCatalogOrderAPI');
        getRoute()->post('/admin/threat_catalog/add_threat_catalog', 'addThreatCatalogAPI');
        getRoute()->post('/admin/threat_catalog/update_threat_catalog', 'updateThreatCatalogAPI');
        getRoute()->post('/admin/threat_catalog/delete_threat_catalog', 'deleteThreatCatalogAPI');

        // This status call needs to be available with ComplianceForge SCF disabled
        getRoute()->get('/complianceforgescf/enable', 'api_complianceforgescf_enable');
        getRoute()->get('/complianceforgescf/disable', 'api_complianceforgescf_disable');
        getRoute()->get('/complianceforgescf/status', 'api_complianceforgescf_status');

	// Datatable/report column selection settings API
	getRoute()->post('/admin/column_settings/save_column_settings', 'saveColumnSelectionSettingsAPI');

	/************************** SIMPLERISK EXTRAS APIS ************************************/

        // If the Advanced Search Extra is enabled
        if (advanced_search_extra())
        {
            // Required file
            $required_file = realpath(__DIR__ . '/../extras/advanced_search/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/api/includes/api.php');

            // If the file exists
            if (file_exists($required_file))
            {
                // Include the required file
                require_once($required_file);

                // Get the api routes
                get_api_routes();
            }
        }

        // If the Assessments Extra is enabled
        if (assessments_extra())
        {
            // Required file
            $required_file = realpath(__DIR__ . '/../extras/assessments/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/authentication/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/complianceforgescf/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/customization/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/encryption/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/import-export/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/incident_management/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/jira/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/notification/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/organizational_hierarchy/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/separation/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/ucf/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/vulnmgmt/includes/api.php');

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
            $required_file = realpath(__DIR__ . '/../extras/upgrade/includes/api.php');

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

	// Try the epiphany route
	try
	{
        	// Run epiphany
        	getRoute()->run();
	}
	catch (EpiException $e)
	{
		//echo $e->getMessage();
		// Return a JSON response
		return json_response(404, $escaper->escapeHtml($lang['TheRequestedAPIEndpointWasNotFound']), NULL);
	}
    } elseif(check_questionnaire_get_token()) {
        // Here's a separate section for when the request is not authenticated,
        // but has a valid questionnaire token.
        // This won't create an authenticated session, but allows questionnaires to
        // serve some data to unauthenticated contacts

        // Initialize the epiphany api
        Epi::init('api', 'route', 'session');

        // Disable exceptions
        Epi::setSetting('exceptions', true);

        getRoute()->get('/asset-group/options', 'get_asset_group_options_noauth');

        // Run epiphany
        getRoute()->run();
    }

?>
