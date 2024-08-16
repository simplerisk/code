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

// Add various security headers
add_security_headers();

// Add the session
add_session_check();

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

$user_info = get_user_by_id($_SESSION['uid']);
$tag_ids = explode(',', (string)$user_info['custom_risks_and_issues_settings']);

$risk_tags = isset($_REQUEST['risk_tags']) ? $_REQUEST['risk_tags'] : $tag_ids;
$start_date = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : format_date(date('Y-m-d', strtotime('-30 days')));
$end_date = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : format_date(date('Y-m-d'));
setting_risks_and_issues_tags($risk_tags);

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>

  <!-- jQuery Javascript -->
  <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>
  <script src="../vendor/node_modules/jquery-ui/dist/jquery-ui.min.js?<?= $current_app_version ?>" id="script_jqueryui"></script>

  <!-- Bootstrap tether Core JavaScript -->
  <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

  <script src="../js/bootstrap-multiselect.js?<?= $current_app_version ?>"></script>
  <script src="../js/sorttable.js?<?= $current_app_version ?>"></script>
  <script src="../js/obsolete.js?<?= $current_app_version ?>"></script>
  <script src="../js/simplerisk/dynamic.js?<?= $current_app_version ?>"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?= $current_app_version ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?= $current_app_version ?>">

  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">
  <link rel="stylesheet" href="../css/theme.css?<?= $current_app_version ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?= $current_app_version ?>">
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
