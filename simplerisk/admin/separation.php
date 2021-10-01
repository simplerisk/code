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

                'allow_all_to_asset_noassign_team'              => isset($_POST['allow_all_to_asset_noassign_team']) ? 1 : 0,

                'allow_document_owner_to_see_documents'         => isset($_POST['allow_document_owner_to_see_documents']) ? 1 : 0,
                'allow_approver_to_see_documents'               => isset($_POST['allow_approver_to_see_documents']) ? 1 : 0,
                'allow_stakeholders_to_see_documents'           => isset($_POST['allow_stakeholders_to_see_documents']) ? 1 : 0,
                'allow_all_to_document_noassign_team'           => isset($_POST['allow_all_to_document_noassign_team']) ? 1 : 0,
                'allow_all_to_see_document'                     => isset($_POST['allow_all_to_see_document']) ? 1 : 0,
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
