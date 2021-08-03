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
    if (is_dir(realpath(__DIR__ . '/../extras/authentication')))
    {
        // Include the Authentication Extra
        require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

        // If the user updated the configuration
        if (isset($_POST['update_settings']) || isset($_POST['update_ldap']) || isset($_POST['update_saml']) || isset($_POST['update_mfa']))
        {
            // Update the authentication configuration
            update_authentication_config();

            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));

            refresh();
        }

        // If the user wants to activate the extra
        if (isset($_POST['activate']))
        {
            // Enable the Authentication Extra
            enable_authentication_extra();
            refresh();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate']))
        {
            // Disable the Authentication Extra
            disable_authentication_extra();
            refresh();
        }
        
        // If the user maps Team and LDAP Group
        if (isset($_POST['map_ldap_group_and_team']))
        {
            $ldap_group = $_POST['ldap_group'];
            $ldap_team = (int)$_POST['ldap_team'];
            
            if($ldap_group && $ldap_team)
            {
                // Map LDAP group and team
                setLdapTeamAndGroup($ldap_group, $ldap_team);
                
                set_alert(true, "good", $escaper->escapeHtml($lang['MapSuccessTeamAndGroup']));
            }
            else
            {
                set_alert(true, "bad", $escaper->escapeHtml($lang['MappingTeamAndLDAPGroupRequired']));
            }
            
            refresh();
        }

        // If the user deletes existing mappings
        if (isset($_POST['delete_existing_mappings']))
        {
            $ldap_group_and_team_id = (int)$_POST['existing_mappings'];
            
            if($ldap_group_and_team_id)
            {
                // Delete existing LDAP mappings
                deleteLdapGroupAndTeamByValue($ldap_group_and_team_id);
                
                set_alert(true, "good", $escaper->escapeHtml($lang['DeletedMappingSuccess']));
            }
            else
            {
                set_alert(true, "bad", $escaper->escapeHtml($lang['ExistingMappingsRequired']));
            }
            
            refresh();
        }
    }

    /*********************
     * FUNCTION: DISPLAY *
     *********************/
    function display($display = "")
    {
        global $lang;
        global $escaper;

        // If the extra directory exists
        if (is_dir(realpath(__DIR__ . '/../extras/authentication')))
        {
            // If the extra is not activated
            if (!custom_authentication_extra())
            {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("customauth"))
                {
                    echo "<form name=\"activate_extra\" method=\"post\" action=\"\">";
                        echo "<input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Activate']) . "\" name=\"activate\" /><br />";
                        echo "</form>\n";
                        echo "</div>\n";
                }
                // The extra is restricted
                else echo $escaper->escapeHtml($lang['YouNeedToUpgradeYourSimpleRiskSubscription']);
            }
            // Once it has been activated
            else
            {
                // Include the Authentication Extra
                require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));
                display_authentication();
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
?>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>
    <script src="../js/common.js"></script>
    
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">

    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

    <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/side-navigation.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">
    <link rel="stylesheet" href="../css/settings_tabs.css">
    
    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>    
  </head>

  <body>

<?php 
    display_license_check();
    view_top_menu("Configure");
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
                <h4>Custom Authentication Extra</h4>
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
