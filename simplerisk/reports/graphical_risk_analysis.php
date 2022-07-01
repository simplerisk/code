<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/graphical.php'));
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

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>"
      xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

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
    <script src="../js/obsolete.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>
    <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet"
          href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

    <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
    <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">

    <?php
    setup_favicon("..");
    setup_alert_requirements("..");

    $settings = [];
    $selection_id = get_param("GET", "selection", "");
    if($selection_id) {
        $selection = get_graphical_saved_selection($selection_id);
        if($selection['type'] == 'private' && $selection['user_id'] != $_SESSION['uid'] && !is_admin())
        {
            set_alert(true, "bad", $lang['NoPermissionForThisSelection']);
        } else {
            $settings = json_decode($selection['graphical_display_settings'], true);
        }
    }
    ?>
</head>
<body>
<?php view_top_menu("Reporting"); ?>

<div class="container-fluid">
    <div class="row-fluid">
        <div class="span3">
            <?php view_reporting_menu("GraphicalRiskAnalysis"); ?>
        </div>
        <div class="span9">
            <?php get_alert(); ?>
            <div class="row-fluid">
                <div id="selections" class="span12">
                    <div class="well">
                        <form id="graphical_risk_analysis" name="graphical_risk_analysis" action="" method="POST">
                            <div class="row-fluid">
                                <?php display_graphic_type_dropdown($settings); ?>
                            </div>
                            <div class="row-fluid">
                                <?php display_y_axis($settings); ?>
                            </div>
                            <div class="row-fluid">
                                <?php display_x_axis($settings); ?>
                            </div>
                            <div class="row-fluid">
                                <?php display_save_graphic_selection(); ?>
                            </div>
                            <div class="row-fluid">
                                <input type="submit" name="generate_report" value="<?php echo $escaper->escapeHtml($lang['GenerateReport']);?>" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="row-fluid bottom-offset-10"></div>
            <div class="row-fluid">
                <div class="span12">
                    <?php display_graphical_risk_analysis(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>