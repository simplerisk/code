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

    <?php
        // Use these HighCharts scripts
        $scripts = [
                'highcharts.js',
        ];

        // Display the highcharts javascript source
        display_highcharts_javascript($scripts);

?>

  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
  
  <?php

        // If the Incident Management Extra is enabled
        if (incident_management_extra())
        {
                // Include the incident management javascript file
                echo "        <script src=\"../extras/incident_management/js/incident_management.js?" . current_version("app") . "\"></script>\n";

                // Include the incident management css file
                echo "        <link rel=\"stylesheet\" href=\"../extras/incident_management/css/incident_management.css?" . current_version("app") . "\">\n";
        }

	setup_favicon("..");
	setup_alert_requirements("..");
  ?>
</head>

<body>

  <?php
    display_license_check();

    view_top_menu("Reporting");

    // Get any alert messages
    get_alert();
  ?>

  <div class="container-fluid">
    <?php display_side_navigation("GovernanceRiskCompliance"); ?>
    <div class="row-fluid">
      <div class="spacer"></div>
      <div class="span2">
        <?php view_reporting_menu("Overview"); ?>
      </div>
      <div class="span4">
        <div class="container-fluid">
          <br />
          <div class="row-fluid">
            <div class="span4">
              <div class="well">
                <?php open_closed_pie(js_string_escape($lang['OpenVsClosed'])); ?>
              </div>
            </div>
            <div class="span4">
              <div class="well">
                <?php open_mitigation_pie(js_string_escape($lang['MitigationPlannedVsUnplanned'])); ?>
              </div>
            </div>
            <div class="span4">
              <div class="well">
                <?php open_review_pie(js_string_escape($lang['ReviewedVsUnreviewed'])); ?>
              </div>
            </div>
          </div>
          <div class="row-fluid">
            <div class="span12">
              <div class="well">
                <?php risks_by_month_table(); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    
    </div>
  </div>
</body>

</html>

