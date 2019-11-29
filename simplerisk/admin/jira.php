<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION)) {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true") {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    require_once(language_file());

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted") {
        set_unauthenticated_redirect();
        header("Location: ../index.php");
        exit(0);
    }

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1") {
        header("Location: ../index.php");
        exit(0);
    }

    // Include the CSRF-magic library
    // Make sure it's called after the session is properly setup
    include_csrf_magic();

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/jira'))) {
        // Include the Jira Extra
        require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate'])) {
            // Enable the Jira Extra
            enable_jira_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate'])) {
            // Disable the Jira Extra
            disable_jira_extra();
        }
        
        if (isset($_POST['update_connection_settings'])) {
            jira_update_connection_settings();
        }

        if (isset($_POST['update_project_synchronization_settings'])) {
            jira_update_project_synchronization_settings();
        }

        if (isset($_POST['update_general_synchronization_settings'])) {
            jira_update_general_synchronization_settings();
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display() {
        global $lang;
        global $escaper;

        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/jira'))) {
            // But the extra is not activated
            if (!jira_extra()) {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("jira")) {
                    echo "<form name=\"activate_extra\" method=\"post\" action=\"\">\n";
                    echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" /><br />\n";
                    echo "</form>\n";
                } else // The extra is restricted
                    echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            } else { // Once it has been activated

                // Include the Jira Extra
                require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

                echo "
                    <form name=\"deactivate\" method=\"post\">
                        <font color=\"green\">
                            <b>" . $escaper->escapeHtml($lang['Activated']) . "</b>
                        </font> [" . jira_version() . "]
                        &nbsp;&nbsp;
                        <input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" />
                    </form>\n";
                    
                display_jira_extra_options();
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
        <script src="../js/jquery.min.js"></script>
        <script src="../js/bootstrap.min.js"></script>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/bootstrap.css">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css">

        <link rel="stylesheet" href="../css/divshot-util.css">
        <link rel="stylesheet" href="../css/divshot-canvas.css">
        <link rel="stylesheet" href="../css/display.css">

        <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="../css/theme.css">
        <style>
            .instructions {
                display: inline-block;
                font-size: 0.8em;
                color: orangered;
                position: relative;
                top: -5px;
                line-height: 1.2;
            }

            .sub-option.lv1 {
                padding-left: 25px;
            }

            .sub-option.lv2 {
                padding-left: 25px;
            }
            
            .hidden-checkbox + label:before,
            .hidden-radio + label:before {
                top: 0px;
            }
            
            .hidden-checkbox + label, .hidden-radio + label {
                font-size: 16px;
                font-weight: bold;
            }
            
            .sub-option .hidden-checkbox + label, .sub-option .hidden-radio + label {
                font-weight: normal;
            }
            
            .checkbox-instructions {
                padding-left: 25px;
            }
            
        </style>
        <?php
            setup_alert_requirements("..");
        ?>    
    </head>

    <body>

        <?php
            view_top_menu("Configure");
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
                                <h4><?php echo $escaper->escapeHtml($lang['JiraExtra']); ?></h4>
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

        <?php
            // Get any alert messages
            get_alert();
        ?>
    </body>
</html>
