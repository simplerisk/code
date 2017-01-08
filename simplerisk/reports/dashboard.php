<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/reporting.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// If we want to enable the Content Security Policy (CSP) - This may break Chrome
if (CSP_ENABLED == "true")
{
  // Add the Content-Security-Policy header
  header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
}

// Session handler is database
if (USE_DATABASE_FOR_SESSIONS == "true")
{
  session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
}

// Start the session
session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

if (!isset($_SESSION))
{
        session_name('SimpleRisk');
        session_start();
}

// Include the language file
require_once(language_file());

require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
  header("Location: ../index.php");
  exit(0);
}

// Get the risk pie array
$pie_array = get_pie_array();

?>

<!doctype html>
<html>

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/sorttable.js"></script>
  <script src="../js/obsolete.js"></script>
  <script src="../js/highcharts/code/highcharts.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">

  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">
</head>

<body>


  <?php view_top_menu("Reporting"); ?>

  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_reporting_menu("RiskDashboard"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <h3><?php echo $escaper->escapeHtml($lang['OpenRisks']); ?> (<?php echo $escaper->escapeHtml(get_open_risks()); ?>)</h3>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php open_risk_level_pie(js_string_escape($lang['RiskLevel'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_status_pie($pie_array, js_string_escape($lang['Status'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_location_pie($pie_array, js_string_escape($lang['SiteLocation'])); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php open_risk_source_pie($pie_array, js_string_escape($lang['RiskSource'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_category_pie($pie_array, js_string_escape($lang['Category'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_team_pie($pie_array, js_string_escape($lang['Team'])); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php open_risk_technology_pie($pie_array, js_string_escape($lang['Technology'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_owner_pie($pie_array, js_string_escape($lang['Owner'])); ?>
            </div>
          </div>
          <div class="span4">
            <div class="well">
              <?php open_risk_owners_manager_pie($pie_array, js_string_escape($lang['OwnersManager'])); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php open_risk_scoring_method_pie($pie_array, js_string_escape($lang['RiskScoringMethod'])); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid">
          <h3><?php echo $escaper->escapeHtml($lang['ClosedRisks']); ?>: (<?php echo $escaper->escapeHtml(get_closed_risks()); ?>)</h3>
        </div>
        <div class="row-fluid">
          <div class="span4">
            <div class="well">
              <?php closed_risk_reason_pie(js_string_escape($lang['Reason'])); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
