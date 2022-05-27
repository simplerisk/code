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

$user_info = get_user_by_id($_SESSION['uid']);
$tag_ids = explode(',', $user_info['custom_risks_and_issues_settings']);

$risk_tags = isset($_REQUEST['risk_tags']) ? $_REQUEST['risk_tags'] : $tag_ids;
$start_date = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : format_date(date('Y-m-d', strtotime('-30 days')));
$end_date = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : format_date(date('Y-m-d'));
setting_risks_and_issues_tags($risk_tags);

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
        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
  <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/sorttable.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/obsolete.js?<?php echo current_version("app"); ?>"></script>
  <script src="../js/dynamic.js?<?php echo current_version("app"); ?>"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
  <?php
    setup_favicon("..");
    setup_alert_requirements("..");
  ?>
    <style>
        .group-name-row {
            cursor: pointer;
        }
        td.group-name > i {
            margin-right: 10px;
            width: 10px;
        }
        .download_link{cursor: pointer;}
    </style>
    <script>
        $(document).ready(function() {
            $('.group-name-row').click(function() {
                var id = $(this).data('group-name-row-id');
                $("[data-group-id='" + id + "']").toggle();
                $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
            });
        });
    </script>
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
        <?php view_reporting_menu("RisksAndIssues"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div id="selections" class="span12">
            <div class="well">
              <?php view_risks_and_issues_selections($risk_tags, $start_date, $end_date); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <div class="span12">
            <?php risks_and_issues_table($risk_tags, $start_date, $end_date); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php display_set_default_date_format_script(); ?>
</body>

</html>
