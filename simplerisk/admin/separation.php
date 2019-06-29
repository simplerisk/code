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

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
        header("Location: ../index.php");
        exit(0);
    }

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/separation')))
    {
        // Include the Separation Extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate']))
        {
            // Enable the Separation Extra
            enable_team_separation_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate']))
        {
            // Disable the Separation Extra
            disable_team_separation_extra();
        }
        
        // If the user wants to update permissions for risk
        if(isset($_POST['update_permissions'])){
            $permissions = array(
                'allow_owner_to_risk'                           => isset($_POST['allow_owner_to_risk']) ? 1 : 0,
                'allow_ownermanager_to_risk'                    => isset($_POST['allow_ownermanager_to_risk']) ? 1 : 0,
                'allow_submitter_to_risk'                       => isset($_POST['allow_submitter_to_risk']) ? 1 : 0,
                'allow_team_member_to_risk'                     => isset($_POST['allow_team_member_to_risk']) ? 1 : 0,
                'allow_stakeholder_to_risk'                     => isset($_POST['allow_stakeholder_to_risk']) ? 1 : 0,
                'allow_all_to_risk_noassign_team'               => isset($_POST['allow_all_to_risk_noassign_team']) ? 1 : 0,

                'allow_control_owner_to_see_test_and_audit'     => isset($_POST['allow_control_owner_to_see_test_and_audit']) ? 1 : 0,
                'allow_tester_to_see_test_and_audit'            => isset($_POST['allow_tester_to_see_test_and_audit']) ? 1 : 0,
                'allow_stakeholders_to_see_test_and_audit'      => isset($_POST['allow_stakeholders_to_see_test_and_audit']) ? 1 : 0,
                'allow_team_members_to_see_test_and_audit'      => isset($_POST['allow_team_members_to_see_test_and_audit']) ? 1 : 0,
                'allow_everyone_to_see_test_and_audit'          => isset($_POST['allow_everyone_to_see_test_and_audit']) ? 1 : 0,
            );
            update_permission_settings($permissions);
            set_alert(true, "good", $lang['SavedSuccess']);
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display()
    {
        global $lang;
        global $escaper;

        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/separation')))
        {
            // But the extra is not activated
            if (!team_separation_extra())
            {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("separation"))
                {
                echo "<form name=\"activate_extra\" method=\"post\" action=\"\">\n";
                echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" /><br />\n";
                echo "</form>\n";
            }
                 // The extra is restricted
                 else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            }
            // Once it has been activated
            else
            {
                // Include the Team Separation Extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                display_team_separation();
            }
        }
        // Otherwise, the Extra does not exist
        else
        {
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
        <?php
            setup_alert_requirements("..");
        ?>    
    </head>

    <body>

        <?php
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
                                <h4>Team-Based Separation Extra</h4>
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
