<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    require_once(realpath(__DIR__ . '/../includes/self_assessments.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    render_header_and_sidebar(
        ['blockUI', 'selectize', 'datatables', 'CUSTOM:pages/assessment.js', 'CUSTOM:pages/self-assessment.js'],
        ['check_assessments' => true],
        active_sidebar_menu: 'RiskManagement',
        required_localization_keys: [
            'SelfAssessments','NewSelfAssessment','PendingRisks','ChooseAFramework','EnabledFrameworks',
            'AllScfFrameworks','Questions','Start','InProgress','Completed','Resume','View','Delete',
            'ControlQuestion','Pass','Fail','NotApplicable','SaveProgress','MarkComplete',
            'AnsweredOfTotal','FailedSoFar','StartedBy','FailedControls','PushToRisk',
            'ConfirmDeleteSelfAssessment','ConfirmCompleteSelfAssessment','NoSelfAssessmentsYet','Back','Next',
            'Status','Search','Subject','Score',
            'Yes','No','Cancel','RequestFailed','ConfirmDeletePendingRisk','NoPendingRisks','Date','Assessments','Framework',
            'ControlID','Answer','ControlStatus','NoFailedControls','All','Control','Question',
            'ControlResultsTruncated','SelectAll','Select','NSelected','FilterByControl','Pushing','Deleting',
            'ConfirmPushSelectedPendingRisks','ConfirmDeleteSelectedPendingRisks','ConfirmDeleteSelectedSelfAssessments',
            'BulkPartialFailure',
        ]
    );

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assessments.php'));
    require_once(realpath(__DIR__ . '/../includes/extras.php'));

    // If an assessment was sent
    if (isset($_POST['send_assessment'])) {

        // Process the sent assessment (no-op if assessments extra is disabled)
        call_extra_function(
            'assessments_extra',
            __DIR__ . '/../extras/assessments/index.php',
            'process_sent_assessment'
        );
    }

    // If an action was sent
    if (isset($_GET['action'])) {

        // If the action is create
        if ($_GET['action'] == "create") {

            // Use the Create Assessments menu
            $menu = "CreateAssessment";

        // If the action is edit
        } else if ($_GET['action'] == "edit") {

            // Use the Edit Assessments menu
            $menu = "EditAssessment";

        // If the action is view
        } else if ($_GET['action'] == "view") {

            // Use the Self Assessments menu
            $menu = "SelfAssessments";

        // If the action is send
        } else if ($_GET['action'] == "send") {

            // Use the Send Assessments menu
            $menu = "SendAssessment";

        }
        
    // Otherwise
    } else {

        // Use the Self Assessments menu
        $menu = "SelfAssessments";

    }

?>
<div class="row">
    <div class="col-12">
    <?php
        // If the action was create
        if ((isset($_GET['action']) && $_GET['action'] == "create") || (isset($_POST['action']) && $_POST['action'] == "create")) {

            // Display the create assessments (no-op if assessments extra is disabled)
            call_extra_function(
                'assessments_extra',
                __DIR__ . '/../extras/assessments/index.php',
                'display_create_assessments'
            );
            
        // If the action was edit
        } else if ((isset($_GET['action']) && $_GET['action'] == "edit") || (isset($_POST['action']) && $_POST['action'] == "edit")) {

            // If the assessments extra is enabled
            if (assessments_extra()) {

                // Include the assessments extra
                require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
                
                // Display the edit assessments
                echo "
        <div id='edit-assessment-container'>
                ";
            display_edit_assessments();
                echo "
        </div>
                ";
            }
            
        // If the action was view
        } else if ((isset($_GET['action']) && $_GET['action'] == "view") || (isset($_POST['action']) && $_POST['action'] == "view")) {

                // Display the assessment questions
        display_view_assessment_questions();
        
        // If the action was send
        } else if ((isset($_GET['action']) && $_GET['action'] == "send") || (isset($_POST['action']) && $_POST['action'] == "send")) {

            // Display the send assessment options (no-op if assessments extra is disabled)
            call_extra_function(
                'assessments_extra',
                __DIR__ . '/../extras/assessments/index.php',
                'display_send_assessment_options'
            );

        } else {
            // Default view: SCF-based self-assessment.
            $ready = self_assessment_is_registered() && self_assessment_scf_installed();
            if (!$ready) {
                render_self_assessment_prereq_panel(self_assessment_is_registered(), self_assessment_scf_installed());
            } else {
                $can_gov = check_permission('governance') ? '1' : '0';
                echo "<div id='self-assessment-app' class='self-assessment' data-can-governance='{$can_gov}'></div>";
            }
        }
        
    ?>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>