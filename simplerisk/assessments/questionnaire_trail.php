<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/assessments.php'));

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

if(isset($_POST['download_audit_log']))
{
    
    global $escaper, $lang;
    // Include Laminas Escaper for HTML Output Encoding
    $escaper = new simpleriskEscaper();
    
    // Add various security headers
    add_security_headers();
    
    add_session_check();
    
    // Include the SimpleRisk language file
    require_once(language_file());
    
    if(is_admin())
    {
        // If extra is activated, download audit logs
        if (import_export_extra())
        {
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
            
            $days = get_param('get', 'days', 7);
        
            download_audit_logs($days, ['contact', 'questionnaire_question', 'questionnaire_template', 'questionnaire', 'questionnaire_tracking', 'questionnaire_template', 'questionnaire_question'], $escaper->escapeHtml($lang['QuestionnaireAuditTrailReport']));
        }else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['YouCantDownloadBecauseImportExportExtraDisabled']));
            refresh();
        }
    }
    // If this is not admin user, disable download
    else
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['AdminPermissionRequired']));
        refresh();
    }
}

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'WYSIWYG:Assessments', 'multiselect', 'CUSTOM:common.js', 'cve_lookup', 'CUSTOM:pages/assessment.js'], ["check_assessments" => true]);

?>
<div class="row">
    <div class="col-12">
        <div class='card-body my-2 border'>
            <div class="well">
                <?php display_questionnaire_trail(); ?>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
	render_footer();
?>