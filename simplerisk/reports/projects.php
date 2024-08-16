<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
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

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>

  <!-- jQuery Javascript -->
  <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>

  <!-- Bootstrap tether Core JavaScript -->
  <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

  <script src="../js/sorttable.js?<?= $current_app_version ?>"></script>
  <script src="../js/obsolete.js?<?= $current_app_version ?>"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?= $current_app_version ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?= $current_app_version ?>">

  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">
  <link rel="stylesheet" href="../css/theme.css?<?= $current_app_version ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?= $current_app_version ?>">
</head>

<body>

  <?php view_top_menu("Reporting"); ?>

  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_reporting_menu("AllOpenRisksConsideredForProjectsByRiskLevel"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid"><p><?php echo $escaper->escapeHtml($lang['ReportProjectsHelp']); ?>.</p></div>
        <?php get_risk_table(5); ?>
      </div>
    </div>
  </div>
</body>

</html>
