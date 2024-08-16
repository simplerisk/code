<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

$breadcrumb_title_key = '';
$active_sidebar_menu = '';
$active_sidebar_submenu = '';
if (isset($_GET['action']) && $_GET['action'] == 'full_view') {
    $active_sidebar_menu = 'Assessments';
    $active_sidebar_submenu = 'QuestionnaireResults';
    $breadcrumb_title_key = 'Questionnaire Detail';
}

render_header_and_sidebar(['chart.js', 'blockUI', 'selectize', 'datatables', 'WYSIWYG:Assessments', 'multiselect', 'CUSTOM:common.js', 'datetimerangepicker', 'cve_lookup', 'CUSTOM:pages/assessment.js', 'EXTRA:JS:assessments:questionnaire-result_share.js'], ["check_assessments" => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, required_localization_keys: ['ConfirmDeletePendingRisk']);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/assessments.php'));

if(isset($_POST['download_audit_log']))
{
    if(is_admin())
    {
        // If extra is activated, download audit logs
        if (import_export_extra()) {
            $tracking_id = (int)$_POST['tracking_id'];
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
            download_audit_logs(get_param('post', 'days', 7), 'questionnaire_tracking', $escaper->escapeHtml($lang['QuestionnaireResultAuditTrailReport']), $tracking_id + 1000);
        } else {
            set_alert(true, "bad", $lang['YouCantDownloadBecauseImportExportExtraDisabled']);
            refresh();
        }
    }
    // If this is not admin user, disable download
    else
    {
        set_alert(true, "bad", $lang['AdminPermissionRequired']);
        refresh();
    }
}

// Check if assessment extra is enabled
if(assessments_extra())
{
    // Include the assessments extra
    require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
}
else
{ 
    header("Location: ../index.php");
    exit(0);
}

// Process actions on questionnaire pages
if(process_questionnaire_pending_risks()){
    refresh();
}

?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body my-2 border">
<?php
    if(isset($_GET['action']) && $_GET['action']=="full_view") {
            display_questionnaire_fullview(); 
    } else {
        echo "
            <div class='row'>
                <div class='col-10'>
                    <p>" . $escaper->escapeHtml($lang['QuestionnaireResultsHelp']) . ".</p>
                </div>
                <div class='col-2 text-end'>
                    <a data-sr-role='dt-settings' data-sr-target='questionnaire-results-table' href='#' title='" . $escaper->escapeHtml($lang['Settings']) . "' role='button' class='btn btn-dark float-end' data-bs-toggle='modal' data-bs-target='#setting_modal'><i class='fa fa-cog'></i></a>
                </div>
            </div>
        ";
            display_questionnaire_results();
    }
?>
        </div>
    </div>
</div>
<!-- MODEL WINDOW FOR DISPLAY SETTINGS -->
<div class="modal fade" id="setting_modal" tabindex="-1" aria-labelledby="setting_modallable" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['Settings']); ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="custom_display_settings" name="custom_display_settings" method="post">
                    <?php echo display_custom_questionnaire_columns("custom_questionnaire_results_display_settings");?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                <button type="button" id="save_display_settings" class="btn btn-submit"><?php echo $escaper->escapeHtml($lang['Save']); ?></button>
            </div>
        </div>
    </div>
</div>
<!-- MODEL WINDOW FOR FILE UPLOAD -->
<div class="modal fade" id="file_upload_modal" tabindex="-1" aria-labelledby="file_upload_modallabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="file-upload-form" action="#" method="POST" autocomplete="off" enctype="multipart/form-data">
                <div class="modal-header">
                    <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['FileUpload']); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class='row mt-2 attachment-container'>
                        <div class='col-4'>
                            <div class='form-group'>
                                <div class='pull-left'>
                                    <div class='file-uploader'>
                                        <label for='file-upload' class='btn btn-dark'>Choose File</label>
                                        <span class='file-count-html'> <span class='file-count'>0</span> File Added</span>
                                        <p><font size='2'><strong>Max <?= $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)) ?> Mb</strong></font></p>
                                        <input type='file' id='file-upload' name='file[]' class='hidden-file-upload hide active' />
                                        <input type="hidden" name="token"  value="<?= isset($_GET['token']) ? $escaper->escapeHtml($_GET['token']) : '' ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                    <button type="submit" id="file_upload" name="file_upload" class="btn btn-submit"><?php echo $escaper->escapeHtml($lang['Upload']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
	// Render the footer of the page. Please don't put code after this part.
	render_footer();
?>