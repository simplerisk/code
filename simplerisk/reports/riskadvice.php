<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/reporting.php'));
require_once(realpath(__DIR__ . '/../includes/analysis.php'));
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
		'modules/funnel.js',
                'modules/exporting.js',
        ];

        // Display the highcharts javascript source
        display_highcharts_javascript($scripts);

    ?>

  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">

  <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
  <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
  <?php
    setup_favicon("..");
    setup_alert_requirements("..");
  ?>
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
        <?php view_reporting_menu("RiskAdvice"); ?>
      </div>
      <div class="span9">
        <div class="row-fluid">
          <div class="well">
            <h4><?php echo $escaper->escapeHtml($lang['RiskPyramid']); ?></h4>
            <?php get_risk_pyramid(); ?>
            <br />
            <p><?php echo $escaper->escapeHtml($lang['RiskPyramidDescription']); ?></p>
          </div>
          <div class="well">
            <h4><?php echo $escaper->escapeHtml($lang['RiskAdvice']); ?></h4>
            <?php get_risk_advice(); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
