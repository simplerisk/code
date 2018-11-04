<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/epiphany/src/Epi.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));
    require_once(realpath(__DIR__ . '/../includes/api.php'));

    // Add various security headers
    add_security_headers();

    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    if (!isset($_SESSION))
    {
        session_name('SimpleRisk');
        session_start();
    }

    // Check for session timeout or renegotiation
    session_check();

    // If access is authenticated
    if (is_authenticated())
    {
        // Set the base path for epiphany
        Epi::setPath('base', realpath(__DIR__ . '/../includes/epiphany/src'));

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
        getRoute()->post('/management/risk/reopen', 'reopenForm');
        getRoute()->get('/management/risk/overview', 'overviewForm');

        getRoute()->post('/reports/dynamic', 'dynamicriskForm');
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

        getRoute()->get('/management/risk/scoreaction', 'scoreactionForm');
        getRoute()->post('/management/risk/saveScore', 'saveScoreForm');

        getRoute()->post('/management/risk/saveSubject', 'saveSubjectForm');

        getRoute()->post('/management/risk/saveComment', 'saveCommentForm');
        getRoute()->post('/management/risk/accept_mitigation', 'acceptMitigationForm');

        getRoute()->post('/management/impportexport/deleteMapping', 'deleteMapping');
        getRoute()->post('/assessment/update', 'updateAssessment');

        getRoute()->get('/datatable/framework_controls', 'getFrameworkControlsDatatable');
        getRoute()->get('/datatable/mitigation_controls', 'getMitigationControlsDatatable');
        getRoute()->get('/role_responsibilities/get_responsibilities', 'getResponsibilitiesByRoleIdForm');

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
        
        getRoute()->get('/compliance/define_tests', 'getDefineTestsResponse');
        getRoute()->get('/compliance/test', 'getTestResponse');
        getRoute()->get('/compliance/initiate_audits', 'getInitiateTestAuditsResponse');
        getRoute()->get('/compliance/active_audits', 'getActiveTestAuditsResponse');
        getRoute()->post('/compliance/save_audit_comment', 'saveTestAuditCommentResponse');
        getRoute()->get('/compliance/past_audits', 'getPastTestAuditsResponse');
        getRoute()->post('/compliance/reopen_audit', 'reopenTestAuditResponse');
        getRoute()->post('/compliance/audit_initiation/initiate', 'initiateFrameworkControlTestsResponse');
        /*************************************************************************/

        /***************************** Assessment API *********************************/
        getRoute()->get('/assessment/contacts', 'assessment_extra_getAssessmentContacts');
        getRoute()->post('/assessment/questionnaire/copy', 'assessment_extra_copyQuestionnaireAPI');
        getRoute()->get('/assessment/questionnaire_questions', 'assessment_extra_getAssessmentQuestionnaireQuestions');
        getRoute()->get('/assessment/questionnaire/template/dynamic', 'assessment_extra_questionnaireTemplateDynamicAPI');
        getRoute()->get('/assessment/questionnaire/dynamic', 'assessment_extra_questionnaireDynamicAPI');
        getRoute()->get('/assessment/questionnaire/results/dynamic', 'assessment_extra_questionnaireResultsDynamicAPI');
        getRoute()->post('/assessment/questionnaire/save_result_comment', 'assessment_extra_saveQuestionnaireResultCommentAPI');
        getRoute()->post('/assessment/questionnaire/pending_risks', 'assessment_extra_createRisksFromQuestionnairePendingRisksAPI');
        getRoute()->get('/assessment/questionnaire/template_questions/dynamic', 'assessment_extra_questionnaireTemplateQuestionsDynamicAPI');
        /******************************************************************************/

        /********************* Customization API **************************/
        getRoute()->post('/customization/addOption', 'customization_extra_addOption');
        getRoute()->post('/customization/deleteOption', 'customization_extra_deleteOption');
        getRoute()->post('/customization/saveTemplate', 'customization_extra_saveTemplate');
        /******************************************************************/

        /********************* Authentication API **************************/
        getRoute()->post('/admin/authentication/add_ldap_group', 'authentication_extra_add_ldap_group');
        getRoute()->post('/admin/authentication/get_teams_by_ldap_group', 'authentication_extra_getTeamsByLdapGroup');
        getRoute()->post('/admin/authentication/delete_ldap_group', 'authentication_extra_deleteLdapGroup');
        getRoute()->post('/admin/authentication/set_ldap_group_and_teams', 'authentication_extra_setLdapGroupAndTeams');
        /******************************************************************/

        /********************* RISK FORMULA API ***************************/
        getRoute()->post('/riskformula/add_impact', 'add_impact_api');
        getRoute()->post('/riskformula/delete_impact', 'delete_impact_api');
        getRoute()->post('/riskformula/add_likelihood', 'add_likelihood_api');
        getRoute()->post('/riskformula/delete_likelihood', 'delete_likelihood_api');
        getRoute()->post('/riskformula/update_impact_name', 'update_impact_name_api');
        getRoute()->post('/riskformula/update_likelihood_name', 'update_likelihood_name_api');
        /******************************************************************/

        // Return scoring histories
        getRoute()->get('/management/risk/scoring_history', 'scoringHistory');
        getRoute()->get('/management/risk/residual_scoring_history', 'residualScoringHistory');

        // Interal api for ajax
        getRoute()->post('/set_custom_display', 'setCustomDisplay');

        // Define the API routes
        getApi()->get('/version.json', 'api_version', EpiApi::external);

        // Get unfiltered table data
        getRoute()->get('/admin/tables/fullData', 'getTableData');
        
        // Get Mitigation Control Info
        getRoute()->get('/mitigation_controls/get_mitigation_control_info', 'get_mitigation_control_info');

        // Get Tooltip Info
        getRoute()->post('/likelihood_impact_chart/tooltip', 'get_tooltip_api');
       
        // Run epiphany
        getRoute()->run();
    }

?>
