<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    require_once(realpath(__DIR__ . '/../includes/assessments.php'));

    $breadcrumb_title_key = '';
    $active_sidebar_menu = '';
    $active_sidebar_submenu = '';
    if (isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'edit')) {
        $active_sidebar_menu = 'Assessments';
        $active_sidebar_submenu = 'Questionnaires';
        if ($_GET['action'] == 'add') {
            $breadcrumb_title_key = 'Add a new questionnaire';
        } else {
            $breadcrumb_title_key = 'Edit a questionnaire';
        }
    }

    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG', 'multiselect', 'tabs:logic', 'CUSTOM:common.js', 'CUSTOM:pages/assessment.js'], ['check_assessments' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);

    // Check if assessment extra is enabled
    if (assessments_extra()) {

        // Include the assessments extra
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    } else {

        // header("Location: ../index.php");
        // exit(0);
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    }

    // Process actions on questionnaire pages
    if ($result = process_assessment_questionnaires()) {
        
        // If want to refresh current page
        if ($result === true) {

            refresh();
        
        // If wnat to go to returned url.
        } else {

            refresh($result);

        }
    }
?>
<div class="row bg-white">
    <div class="col-12">
    <?php
        if (!isset($_GET['action']) || $_GET['action']=="list") {
    ?>
        <div class="card-body my-2 border">
            <div class="row">
                <div data-sr-role="dt-settings" data-sr-target="assessment-questionnaires-table" class="float-end">
    <?php
            if (has_permission("assessment_add_questionnaire")) {
    ?>
                    <a class="btn btn-submit" href="questionnaires.php?action=add"><?= $escaper->escapeHtml($lang['Add']); ?></a>
    <?php
            }
    ?>
                    <a id="setting_modal_btn" class="btn btn-primary" title="<?= $escaper->escapeHtml($lang['Settings']);?>" role="button"><i class="fa fa-cog"></i></a>
                </div>
                <div class="col-12 mt-2">
    <?php 
                    display_questionnaires(); 
    ?>
                </div>
            </div>
        </div>
    <?php
        } elseif(isset($_GET['action']) && $_GET['action']=="add") {

        display_questionnaire_add(); 

        } elseif(isset($_GET['action']) && $_GET['action']=="edit") {

        display_questionnaire_edit($_GET['id']);

        }
    ?>
    </div>
</div>

<!-- MODEL WINDOW FOR COLUMN SETTING -->
<div id="setting_modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="setting_modal" aria-hidden="true" style = "">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= $escaper->escapeHtml($lang['ColumnSelections']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="custom_display_settings" name="custom_display_settings" method="post">
    <?php 
                    display_custom_questionnaires_columns("custom_plan_mitigation_display_settings"); 
    ?>
                    <input type="hidden" name="column_settings" value='1'>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?= $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="submit" id="save_display_settings" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Save']); ?></button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="_lang_SimpleriskUsers" value="<?= $escaper->escapeHtml($lang['SimpleriskUsers']) ?>">
<input type="hidden" id="_lang_AssessmentContacts" value="<?= $escaper->escapeHtml($lang['AssessmentContacts']) ?>">
<script>
    $(function() {
        $('#setting_modal_btn').on('click', function(e) {
             $('#setting_modal').modal({
                backdrop: 'static',
                keyboard: false
            });
            $('#setting_modal').modal('show');
        });
    });
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
	render_footer();
?>