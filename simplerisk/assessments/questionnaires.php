<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/assessments.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_assessments" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

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
if($result = process_assessment_questionnaires()){
    // If want to refresh current page
    if($result === true){
        refresh();
    }
    // If wnat to go to returned url.
    else{
        refresh($result);
    }
}
?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../vendor/node_modules/datatables.net/js/jquery.dataTables.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/simplerisk/common.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/simplerisk/pages/assessment.js?<?php echo current_version("app"); ?>"></script>
    <script src="../vendor/simplerisk/selectize.js/dist/js/standalone/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../vendor/tinymce/tinymce/tinymce.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/WYSIWYG/editor.js?<?php echo current_version("app"); ?>"></script>
    
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../vendor/node_modules/datatables.net-dt/css/jquery.dataTables.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/WYSIWYG/editor.css?<?php echo current_version("app"); ?>">

    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
    <style>
        .btn[disabled] {
            background-color: #3a3a3a !important;
        }
    </style>
</head>

<body>

    <?php
        view_top_menu("Assessments");

        // Get any alerts
        get_alert();
    ?>
    <div id="load" style="display:none;"><?php echo $escaper->escapeHtml($lang['SendingPleaseWait']); ?></div>
    
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_assessments_menu("Questionnaires"); ?>
            </div>
            <div class="span9">
                <?php if(isset($_GET['action']) && $_GET['action']=="list"){ ?>
                    <div class="row-fluid text-right">
                        <a href="#setting_modal" class="btn" title="<?php echo $escaper->escapeHtml($lang['Settings']);?>" role="button" data-toggle="modal">&nbsp;<i class="fa fa-cog"></i></a>
                        <?php if(has_permission("assessment_add_questionnaire")){ ?>
                        <a class="btn" href="questionnaires.php?action=add"><?php echo $escaper->escapeHtml($lang['Add']); ?></a>
                        <?php }?>
                    </div>
                    <div class="row-fluid">
                        <?php display_questionnaires(); ?>
                    </div>
                <?php }elseif(isset($_GET['action']) && $_GET['action']=="add"){ ?>
                    <?php display_questionnaire_add(); ?>
                <?php }elseif(isset($_GET['action']) && $_GET['action']=="edit"){ ?>
                        <?php display_questionnaire_edit($_GET['id']); ?>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- MODEL WINDOW FOR COLUMN SETTING -->
    <div id="setting_modal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="setting_modal" aria-hidden="true" style="width:800px;">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ColumnSelections']); ?></h4>
        </div>
        <div class="modal-body">
            <form id="custom_display_settings" name="custom_display_settings" method="post">
            <?php echo display_custom_questionnaires_columns("custom_plan_mitigation_display_settings");?>
            <input type="hidden" name="column_settings" value='1'>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
            <button type="submit" id="save_display_settings" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Save']); ?></button>
        </div>
    </div>

    <input type="hidden" id="_lang_SimpleriskUsers" value="<?php echo $escaper->escapeHtml($lang['SimpleriskUsers']) ?>">
    <input type="hidden" id="_lang_AssessmentContacts" value="<?php echo $escaper->escapeHtml($lang['AssessmentContacts']) ?>">
    <?php display_set_default_date_format_script(); ?>
</body>

</html>
