<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/reporting.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
add_session_check();

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];

// If the select_report form was posted
$report = isset($_POST['report'])?(int)$_POST['report']:0;
$sort_by = isset($_POST['sort_by'])?(int)$_POST['sort_by']:0;
$projects = isset($_REQUEST['projects']) ? $_REQUEST['projects'] : [];

if (import_export_extra()){
    // Include the Import-Export Extra
    require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

    // if download request, download all risks
    if (isset($_GET['option']) && $_GET['option'] == "download")
    {
        $control_framework = isset($_POST['control_framework']) ? $_POST['control_framework'] : [];
        $control_family = isset($_POST['control_family']) ? $_POST['control_family'] : [];
        $control_class = isset($_POST['control_class']) ? $_POST['control_class'] : [];
        $control_phase = isset($_POST['control_phase']) ? $_POST['control_phase'] : [];
        $control_priority = isset($_POST['control_priority']) ? $_POST['control_priority'] : [];
        $control_owner = isset($_POST['control_owner']) ? $_POST['control_owner'] : [];
        $filters = array(
          'control_framework' => $control_framework,
          'control_family' => $control_family,
          'control_class' => $control_class,
          'control_phase' => $control_phase,
          'control_priority' => $control_priority,
          'control_owner' => $control_owner,
        );
        download_risks_and_controls_report($report, $sort_by, $projects, $filters);
    }
}

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

	display_bootstrap_javascript();
?>
  <script src="../js/sorttable.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/obsolete.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/dynamic.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>"
  <?php
    setup_favicon("..");
    setup_alert_requirements("..");
  ?>
  <style>
    .download_link{cursor: pointer;}
  </style>
</head>

<body>

  <?php
    view_top_menu("Reporting");
    // Get any alert messages
    get_alert();
  ?>

  <div class="container-fluid">
    <div class="row-fluid">
        <div class="span3">
            <?php view_reporting_menu("RisksAndControls"); ?>
        </div>
        <div class="span9">
            <div class="row-fluid">
                <form name="select_report" method="post" action="">
                    <div class="well">
                        <?php view_risks_and_controls_selections($report, $sort_by, $projects); ?>
                    </div>
                    <?php if($report==0) {?>
                      <div class="well">
                        <?php view_controls_filter_selections(); ?>
                      </div>
                    <?php }?>
                </form>
            </div>
            <div class="row-fluid bottom-offset-10">
                <div class="span6 text-left top-offset-15">
                </div>
                <?php
                // If the Import-Export Extra is installed
                if (is_dir(realpath(__DIR__ . '/../extras/import-export')))
                {
                    // And the Extra is activated
                    if (import_export_extra())
                    {
                        // Include the Import-Export Extra
                        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
                        // Display the download link
                        display_download_link("risks-and-controls-report");
                    }
                }
                ?>
            </div>
            <div class="row-fluid">
                <div class="span12">
                    <?php risks_and_control_table($report, $sort_by, $projects); ?>
                </div>
            </div>
        </div>
    </div>
  </div>
</body>

</html>
