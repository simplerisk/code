<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_admin" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/advanced_search'))) {
        // Include the Advanced Search Extra
        require_once(realpath(__DIR__ . '/../extras/advanced_search/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate'])) {
            // Enable the Advanced Search Extra
            enable_advanced_search_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate'])) {
            // Disable the Advanced Search Extra
            disable_advanced_search_extra();
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display() {
        global $lang;
        global $escaper;

        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/advanced_search'))) {
            // But the extra is not activated
            if (!advanced_search_extra()) {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("advanced_search")) {
                    echo "<form name=\"activate_extra\" method=\"post\" action=\"\">\n";
                    echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" /><br />\n";
                    echo "</form>\n";
                } else // The extra is restricted
                    echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            } else { // Once it has been activated

                // Include the Advanced Search Extra
                require_once(realpath(__DIR__ . '/../extras/advanced_search/index.php'));

                echo "
                    <form name=\"deactivate\" method=\"post\">
                        <font color=\"green\">
                            <b>" . $escaper->escapeHtml($lang['Activated']) . "</b>
                        </font> [" . advanced_search_version() . "]
                        &nbsp;&nbsp;
                        <input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" />
                    </form>\n";
            }
        } else { // Otherwise, the Extra does not exist
            echo "<a href=\"https://www.simplerisk.com/extras\" target=\"_blank\">Purchase the Extra</a>\n";
        }
    }

?>

<!doctype html>
<html>
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

	display_bootstrap_javascript();
?>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
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
            display_license_check();
            view_top_menu("Configure");

            // Get any alert messages
            get_alert();
        ?>
        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span3">
                    <?php view_configure_menu("Extras"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span12">
                            <div class="hero-unit">
                                <h4><?php echo $escaper->escapeHtml($lang['AdvancedSearchExtra']); ?></h4>
                                <?php display(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            <?php prevent_form_double_submit_script(); ?>
        </script>
    </body>
</html>
