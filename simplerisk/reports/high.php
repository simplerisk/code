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
add_security_headers();


if (!isset($_SESSION))
{
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

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
  set_unauthenticated_redirect();
  header("Location: ../index.php");
  exit(0);
}

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];
?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

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
        <?php view_reporting_menu("HighRiskReport"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div class="span7">
            <div class="well">
              <?php
              $open = get_open_risks();
              $high = get_high_risks();
              $veryhigh = get_veryhigh_risks();

              // If there are not 0 open risks
              if ($open != 0)
              {
                $highpercent = 100*($high/$open);
                $veryhighpercent = 100*($veryhigh/$open);
              }
              else
              {
                $highpercent = 0;
                $veryhighpercent = 0;
              }
              ?>
              <h3><?php echo $escaper->escapeHtml($lang['TotalOpenRisks']); ?>: <?php echo $escaper->escapeHtml($open); ?></h3>
              <?php
              // If we have very high risks
              if ($veryhigh > 0)
              {
                echo "<h3>" . $escaper->escapeHtml($lang['TotalVeryHighRisks']) . ": " . $escaper->escapeHtml($veryhigh) . "</h3>";
                echo "<h3>" . $escaper->escapeHtml($lang['VeryHighRiskPercentage']) . ": " . $escaper->escapeHtml(round($veryhighpercent, 2)) . "%</h3>";
              }

              // If we have high risks
              if ($high > 0)
              {
                echo "<h3>" . $escaper->escapeHtml($lang['TotalHighRisks']) . ": " . $escaper->escapeHtml($high) . "</h3>";
                echo "<h3>" . $escaper->escapeHtml($lang['HighRiskPercentage']) . ": " . $escaper->escapeHtml(round($highpercent, 2)) . "%</h3>";
              }
              ?>
            </div>
          </div>
          <div class="span5">
            <div class="well">
              <?php open_risk_level_pie($escaper->escapeHtml($lang['RiskLevel'])); ?>
            </div>
          </div>
        </div>
        <?php get_risk_table(20); ?>
      </div>
    </div>
  </div>
</body>

</html>
