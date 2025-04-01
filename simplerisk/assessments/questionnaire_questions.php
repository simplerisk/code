<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file (required here for the assessments_extra() call)
    require_once(realpath(__DIR__ . '/../includes/functions.php'));

    // Check if assessment extra is enabled before rendering anything
    if (assessments_extra()) {
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
    } else {
        header("Location: ../index.php");
        exit(0);
    }

    // Set the breadcrumb title key to one of the keys for create/edit question or nothing if we're not on a sub-page
    $breadcrumb_title_key = isset($_GET['action']) ? ['edit_question' => 'EditQuestionnaireQuestion', 'add_question' => 'NewQuestionnaireQuestion'][$_GET['action']] : '';

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG:Assessments', 'multiselect', 'CUSTOM:common.js', 'cve_lookup', 'CUSTOM:pages/assessment.js'], ["check_assessments" => true], $breadcrumb_title_key);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/assessments.php'));

    // Process actions on questionnaire pages
    if (process_assessment_questionnaire_questions()) {
        refresh();
    }

?>
<div class="row bg-white">
    <div class="col-12">
    <?php 
        if (!isset($_GET['action']) || $_GET['action'] == "questions_list") {

        display_questionnaire_questions();

        } elseif (isset($_GET['action']) && $_GET['action']=="add_question") {
    ?>
        <div class="hero-unit">
    <?php
            display_questionnaire_question_add();
    ?>
        </div>
    <?php
        } elseif (isset($_GET['action']) && $_GET['action']=="edit_question") {
    ?>
        <div class="hero-unit">
    <?php
            display_questionnaire_question_edit($_GET['id']);
    ?>
        </div>
    <?php
        }
    ?>
    </div>
</div>
<script>
    <?php prevent_form_double_submit_script(['aseessment-questionnaire-question--delete-form']);?>
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>