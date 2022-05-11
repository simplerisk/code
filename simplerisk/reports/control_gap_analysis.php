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

// If User has no access permission for governance menu, enforce to main page.
if(empty($_SESSION['governance']))
{
    header("Location: ../index.php");
    exit(0);
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
?>
    <script src="../js/jquery.easyui.min.js?<?php echo current_version("app"); ?>"></script>
<?php
        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
    <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>

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

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">
    
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>

    <style>
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
                <?php view_reporting_menu("ControlGapAnalysis"); ?>
            </div>
            <div class="span9">
                <div class="row-fluid">
                    <?php display_control_gap_analysis(); ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function($) {

        var tabs =  $(".tabs li a");
  
        tabs.click(function() {
                var content = this.hash.replace('/','');
                tabs.removeClass("active");
                $(this).addClass("active");
                $("#content").find('.settings_tab').hide();
                $(content).fadeIn(200);
        });
        })(jQuery);

      var frameworks = document.getElementById('framework');
      frameworks.addEventListener('change', function() {
        document.getElementById('framework_form').submit();
      });
    </script>
</body>

</html>

