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
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");

    // If we want to enable the Content Security Policy (CSP) - This may break Chrome
    if (CSP_ENABLED == "true")
    {
        // Add the Content-Security-Policy header
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
    }

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
        getRoute()->get('/management/mitigation/view', 'viewmitigation');
        getRoute()->post('/management/mitigation/add', 'saveMitigation');
        getRoute()->get('/management/review/view', 'viewreview');
        getRoute()->post('/management/review/add', 'saveReview');
        getRoute()->get('/admin', 'show_admin');
        getRoute()->get('/admin/users/all', 'allusers');
        getRoute()->get('/admin/users/enabled', 'enabledusers');
        getRoute()->get('/admin/users/disabled', 'disabledusers');
        getRoute()->post('/admin/fields/add', 'addCustomField');
        getRoute()->post('/admin/fields/delete', 'deleteCustomField');
        getRoute()->get('/reports', 'show_reports');
        getRoute()->get('/reports/dynamic', 'dynamicrisk');
        getRoute()->get('/risk_levels', 'risk_levels');

        // RISK API from form
        getRoute()->get('/management/risk/reopen', 'reopenForm');
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

        getRoute()->post('/management/impportexport/deleteMapping', 'deleteMapping');
        getRoute()->post('/assessment/update', 'updateAssessment');

        getRoute()->get('/datatable/framework-controls', 'getFrameworkControlsDatatable');
        getRoute()->get('/mitigation_controls', 'getMitigationControls');
        
        /******************** Governance and Compliance API **********************/
        getRoute()->get('/governance/frameworks', 'getFrameworksResponse');
        getRoute()->post('/governance/update_framework_status', 'updateFrameworkStatusResponse');
        getRoute()->post('/governance/update_framework_parent', 'updateFrameworkParentResponse');
        getRoute()->get('/governance/parent_frameworks_dropdown', 'getParentFrameworksDropdownResponse');
        getRoute()->get('/governance/selected_parent_frameworks_dropdown', 'getSelectedParentFrameworksDropdownResponse');
        getRoute()->get('/governance/control', 'getControlResponse');
        getRoute()->get('/governance/framework', 'getFrameworkResponse');
        getRoute()->get('/compliance/define_tests', 'getDefineTestsResponse');
        getRoute()->get('/compliance/test', 'getTestResponse');
        getRoute()->get('/compliance/initiate_audits', 'getInitiateTestAuditsResponse');
        getRoute()->get('/compliance/active_audits', 'getActiveTestAuditsResponse');
        getRoute()->post('/compliance/save_audit_comment', 'saveTestAuditCommentResponse');
        getRoute()->get('/compliance/past_audits', 'getPastTestAuditsResponse');
        getRoute()->post('/compliance/reopen_audit', 'reopenTestAuditResponse');
        /*************************************************************************/
    
        /***************************** Assessment API *********************************/
        getRoute()->get('/assessment/contacts', 'assessment_extra_getAssessmentContacts');
        getRoute()->get('/assessment/questionnaire_questions', 'assessment_extra_getAssessmentQuestionnaireQuestions');
        getRoute()->get('/assessment/questionnaire/template/dynamic', 'assessment_extra_questionnaireTemplateDynamicAPI');
        getRoute()->get('/assessment/questionnaire/dynamic', 'assessment_extra_questionnaireDynamicAPI');
        getRoute()->get('/assessment/questionnaire/results/dynamic', 'assessment_extra_questionnaireResultsDynamicAPI');
        getRoute()->post('/assessment/questionnaire/save_result_comment', 'assessment_extra_saveQuestionnaireResultCommentAPI');
        /******************************************************************************/
        
        // Return scoring histories
        getRoute()->get('/management/risk/scoring_history', 'scoringHistory');

        // Interal api for ajax
        getRoute()->post('/set_custom_display', 'setCustomDisplay');
        
        // Define the API routes
        getApi()->get('/version.json', 'api_version', EpiApi::external);

        // Get unfiltered table data
        getRoute()->get('/admin/tables/fullData', 'getTableData');

        // Run epiphany
        getRoute()->run();
    }

?>
