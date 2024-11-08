<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'cve_lookup', 'WYSIWYG', 'multiselect', 'tabs:logic', 'CUSTOM:common.js', 'CUSTOM:pages/assessment.js', 'datetimerangepicker'], ['check_assessments' => true], '');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assessments.php'));

    // Check if we should add a pending risk
    if (isset($_POST['add'])) {

        // Push the pending risk to a real risk
        push_pending_risk();

    }

    // Check if we should delete a pending risk
    if (isset($_POST['delete'])) {

        // Get the risk id to delete
        $pending_risk_id = (int)$_POST['pending_risk_id'];

        // Delete the pending risk
        delete_pending_risk($pending_risk_id);

        // Set the alert message
        set_alert(true, "good", "The pending risk was deleted successfully.");

    }

    // If an assessment was posted
    if (isset($_POST['action']) && $_POST['action'] == "submit") {

        // Process the assessment
        process_assessment();

    }

    // If an assessment was sent
    if (isset($_POST['send_assessment'])) {

        // If the assessments extra is enabled
        if (assessments_extra()) {

            // Include the assessments extra
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Process the sent assessment
            process_sent_assessment();

        }
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

            // If the assessments extra is enabled
            if (assessments_extra()) {

                // Include the assessments extra
                require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

                // Display the create assessments
        display_create_assessments();

            }
            
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

            // If the assessments extra is enabled
            if (assessments_extra()) {

                // Include the assessments extra
                require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

                // Display the send assessment options
        display_send_assessment_options();

            }

        } else {
                // Display the available assessments
        display_self_assessments();
        }
        
    ?>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>