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

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

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
require_once(language_file());

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
    <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/pages/assessment.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/cve_lookup.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/jquery.datetimepicker.full.min.js?<?php echo current_version("app"); ?>"></script>
    <script src="../extras/assessments/includes/js/questionnaire-result_share.js?<?php echo current_version("app"); ?>"></script>

    <?php
        // Use these HighCharts scripts
        $scripts = [
		'highcharts.js',
		'highcharts-more.js',
		'modules/exporting.js',
		'modules/export-data.js',
		'modules/accessibility.js',
	];

        // Display the highcharts javascript source
        display_highcharts_javascript($scripts);

    ?>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">

    <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../vendor/components/font-awesome/css/all.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/assessments_tabs.css?<?php echo current_version("app"); ?>">
    <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    <link rel="stylesheet" href="../css/jquery.datetimepicker.min.css?<?php echo current_version("app"); ?>">

    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>
</head>

<body>

    <?php
        view_top_menu("Assessments");

        // Get any alerts
        get_alert();
    ?>
    <!--<div id="load" style="display:none;"><img src="<?php echo $_SESSION['base_url']; ?>/images/loading.gif"></div>-->
    <div id="load" style="display:none;"><?php echo $escaper->escapeHtml($lang['SendingPleaseWait']); ?></div>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_assessments_menu("QuestionnaireResults"); ?>
            </div>
            <div class="span9">
                <?php if(isset($_GET['action']) && $_GET['action']=="full_view"){ ?>
                    <?php display_questionnaire_fullview(); ?>
                <?php } else {
                     display_questionnaire_results(); 
                } ?>
            </div>
        </div>
    </div>
    <?php display_set_default_date_format_script(); ?>
</body>

</html>
