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
    if (is_dir(realpath(__DIR__ . '/../extras/customization')))
    {
        // Include the Customization Extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate']))
        {
            // Enable the Customization Extra
            enable_customization_extra();
            
            refresh();
        }

        // If the user wants to deactivate the extra
        elseif (isset($_POST['deactivate']))
        {
            // Disable the Customization Extra
            disable_customization_extra();
            
            refresh();
        }

        // If the user wants to deactivate the extra
        elseif (isset($_POST['restore']))
        {
            $fgroup = get_param("POST", "fgroup", "risk");
            $template_group_id = get_param("POST", "template_group_id", "1");
            // Set default main fields
            set_default_main_fields($fgroup, $template_group_id);
            refresh();
        }

        // If user wants to update custom field
        elseif (isset($_POST['update-custom-field']))
        {
            $id = get_param("POST", "id");
            $name = get_param("POST", "name");
            $required = get_param("POST", "required", 0);
            $encryption = get_param("POST", "encryption", 0);
            $alphabetical_order = get_param("POST", "alphabetical_order", 0);
            
            if(!$id || !$name){
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameFieldIsRequired']));
            }else{
                if(update_custom_field($id, $name, $required, $encryption, $alphabetical_order))
                    set_alert(true, "good", $escaper->escapeHtml($lang['SuccessfullyUpdatedCustomField']));
            }
            refresh();
        }

        // Check if creating field was submitted
        elseif (isset($_POST['create_field']))
        {
            $fgroup = $_POST['fgroup'];
            $name = $_POST['name'];
            $type = $_POST['type'];
            $required = isset($_POST['required']) ? 1 : 0;
            $encryption = isset($_POST['encryption']) ? 1 : 0;
            $alphabetical_order = isset($_POST['alphabetical_order']) ? 1 : 0;

            // Create the new field
            if ($field_id = create_field($fgroup, $name, $type, $required, $encryption, $alphabetical_order))
            {
                // Set field_id as Session variable for auto select of custom fields dropdown
                $_SESSION['custom_field_id'] = $field_id;
                
                // Audit log
                $risk_id = 1000;
                $message = "A custom field named \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                
                // Display an alert
                set_alert(true, "good", "The new custom field was created successfully.");
            }
            
            refresh();
        }
        // If add template group submitted
        elseif (isset($_POST['add_template_group']))
        {
            $name = get_param("POST", "name");
            $fgroup = get_param("POST", "fgroup", "risk");
            $old_group = get_custom_template_group_by_name($name,$fgroup);

            if(!$name){
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameFieldIsRequired']));
            }elseif($old_group){ 
                set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameAlreadyExists']));
            }else{
                add_custom_template_group($name, $fgroup);
                set_alert(true, "good", $escaper->escapeHtml($lang['AddedSuccess']));
            }
            refresh();
        }
        // If update template group submitted
        elseif (isset($_POST['update_template_group']))
        {
            $id = get_param("POST", "id");
            $name = get_param("POST", "name");
            $fgroup = get_param("POST", "fgroup", "risk");
            $old_group = get_custom_template_group_by_name($name,$fgroup);

            if(!$id || !$name){
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameFieldIsRequired']));
            }elseif($old_group && $name == $old_group['name']){ 
                set_alert(true, "bad", $escaper->escapeHtml($lang['TheNameAlreadyExists']));
            }else{
                update_custom_template_group($id, $name);
                set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
            }
            refresh();
        }
        // If delete template group submitted
        elseif (isset($_POST['delete_template_group']))
        {
            $id = get_param("POST", "custom_template_group");
            if(!$id){
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));
            }else{
                delete_custom_template_group($id);
                set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
            }

            refresh();
        }
        // If assign template group to bussiness unit
        elseif (isset($_POST['assign_template']))
        {
            $fgroup = get_param("POST", "fgroup");
            $business_unit_ids = get_param("POST", "business_unit_ids");
            if(!$business_unit_ids){
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));
            }else{
                if(assign_template_to_business_unit($fgroup, $business_unit_ids)) set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
                else set_alert(true, "bad", $escaper->escapeHtml($lang['UpdateFailed']));
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
        if (is_dir(realpath(__DIR__ . '/../extras/customization')))
        {
            // If the extra is not activated
            if (!customization_extra())
            {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("customization"))
                {
                    echo "<div class=\"hero-unit\">\n";
                    echo "<h4>" . $escaper->escapeHtml($lang['CustomizationExtra']) . "</h4>\n";
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
                // Include the Customizaton Extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                display_customization();
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

	// Use these jquery-ui scripts
	$scripts = [
		'jquery-ui.min.js',
	];

	// Include the jquery-ui javascript source
	display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
        <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>
        
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

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
        <script type="text/javascript">
            $(document).ready(function(){
                var $tabs = $("#main").tabs({
                        active: $('.tabs a.active').parent().index(),
                        show: { effect: "fade", duration: 200 },
                        beforeActivate: function(event, ui){
                            ui.oldTab.find('a').removeClass("active");
                            ui.newTab.find('a').addClass("active");
                        },
                   });
            });
        </script>
      </head>

      <body>

    <?php
	display_license_check();
        view_top_menu("Configure");

    ?>
    <?php get_alert(); ?>
        <div class="container-fluid">
          <div class="row-fluid">
            <div class="span3">
              <?php view_configure_menu("Extras"); ?>
            </div>
            <div class="span9">
              <div class="row-fluid">
                <div class="span12">
                    <?php display(); ?>
                </div>
              </div>
            </div>
          </div>
        <script>
            <?php prevent_form_double_submit_script(); ?>
        </script>      
      </body>
    </html>
