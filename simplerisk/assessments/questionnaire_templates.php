<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file

    require_once(realpath(__DIR__ . '/../includes/assessments.php'));

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    // Set the breadcrumb title key to one of the keys for create/edit question or nothing if we're not on a sub-page
    $breadcrumb_title_key = isset($_GET['action']) ? ['edit_template' => 'EditQuestionnaireTemplate', 'add_template' => 'NewQuestionnaireTemplate', 'add_template_from_scf' => 'NewQuestionnaireTemplate'][$_GET['action']] : '';

    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'datatables:rowreorder', 'WYSIWYG', 'WYSIWYG:Assessments', 'tabs:logic', 'multiselect', 'CUSTOM:common.js', 'cve_lookup', 'CUSTOM:pages/assessment.js', 'EXTRA:JS:assessments:questionnaire_templates.js', 'editable', 'JSLocalization'], ["check_assessments" => true], $breadcrumb_title_key, required_localization_keys: ['SearchForControl', 'SearchForFramework']);

    // Check if assessment extra is enabled
    if (assessments_extra()) {

        // Include the assessments extra
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    } else {

        header("Location: ../index.php");
        exit(0);

    }

    // Process actions on questionnaire template pages
    if (process_assessment_questionnaire_templates()) {

        refresh();

    }

?>

<div class="row bg-white">
    <div class="col-12">
    <?php 
        if (!isset($_GET['action']) || $_GET['action']=="template_list") { 
    ?>
        <div class="card-body my-2 border">
    <?php
            if (has_permission("assessment_add_template")) {
    ?>
            <button type="button" class="btn btn-submit float-end" data-sr-role='dt-settings' data-sr-target='assessment-questionnaire-templates-table' data-bs-toggle="modal" data-bs-target="#assessment-questionnaire-template--add"><?= $escaper->escapeHtml($lang['Add']); ?></button>
    <?php
            }
    ?>
            <div class="row">
                <div class="col-12">
    <?php 
                    display_questionnaire_templates(); 
    ?>
                </div>
            </div>
        </div>
    <?php
        } elseif (isset($_GET['action']) && $_GET['action']=="add_template") {
    ?>
        <div class="hero-unit card-body my-2 border">
    <?php 
            display_questionnaire_template_add();
    ?>
        </div>
    <?php
        } elseif (isset($_GET['action']) && $_GET['action']=="add_template_from_scf") {
    ?>
        <div class="hero-unit card-body my-2 border">
    <?php 
            display_questionnaire_template_add_from_scf(); 
    ?>
        </div>
    <?php
        } elseif (isset($_GET['action']) && $_GET['action']=="edit_template") {
    ?>
        <div class="hero-unit card-body my-2 border">
    <?php 
            display_questionnaire_template_edit($_GET['id']); 
    ?>
        </div>
    <?php
        }
    ?>
    </div>
</div>
<div class="modal fade" id="assessment-questionnaire-template--add" tabindex="-1" aria-labelledby="assessment-questionnaire-template--add" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable modal-dark">
        <div class="modal-content">
            <div class="modal-body">
                <div class="form-group text-center">
                    <label for=""><?= $escaper->escapeHtml($lang['WouldYouLikeSimpleRiskToAutomaticallyGenerate']); ?></label>
                </div>
                <div class="form-group text-center project-delete-actions">
                    <a class="btn btn-submit" href="questionnaire_templates.php?action=add_template_from_scf"><?= $escaper->escapeHtml($lang['Yes']);?></a>
                    <a class="btn btn-secondary" href="questionnaire_templates.php?action=add_template"><?= $escaper->escapeHtml($lang['No']);?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>