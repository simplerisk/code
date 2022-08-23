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
    if (is_dir(realpath(__DIR__ . '/../extras/notification')))
    {
        // Include the Notification Extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // If the user wants to activate the extra
        if (isset($_POST['activate']))
        {
            // Enable the Notification Extra
            enable_notification_extra();
        }

        // If the user wants to deactivate the extra
        if (isset($_POST['deactivate']))
        {
            // Disable the Notification Extra
            disable_notification_extra();
        }

        // If the user updated the configuration
        if (isset($_POST['submit']))
        {
            // Update the notification configuration
            update_notification_config();
        }
        
        // If notification extra is enabled, process form data
        if (notification_extra())
        {
            process_run_now_notification();
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
        if (is_dir(realpath(__DIR__ . '/../extras/notification')))
        {
            // But the extra is not activated
            if (!notification_extra())
            {
                // If the extra is not restricted based on the install type
                if (!restricted_extra("notification"))
                {
                    echo "<form id='activate_extra' name=\"activate\" method=\"post\" action=\"\">\n";
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
                // Include the Notification Extra
                require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

                display_notification();
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
    <link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">

    <?php if (notification_extra()) { // Only need these if the extra is enabled ?>
        <script src="../vendor/tinymce/tinymce/tinymce.min.js?<?php echo current_version("app"); ?>"></script>
        <script src="../extras/notification/js/editor.js?<?php echo current_version("app"); ?>"></script>
    	<style>
    	   .tox .tox-mbtn, .tox .tox-tbtn {
                color: #222f3e !important;
            }

            .tox .tox-mbtn:hover, .tox .tox-tbtn:hover {
                color: #222f3e !important;
                background-color: #dee0e2 !important;
            }

            .tox .tox-listboxfield .tox-listbox--select {
                color: #222f3e !important;
            }

             .tox .tox-listboxfield .tox-listbox--select:hover {
                background-color: #ffffff !important;
            }

            .tox .tox-tbtn--active, .tox .tox-tbtn:active {
                background-color: #c8cbcf !important;
            }

            .details_template {
                display: none;
            }
        </style>
	<?php }?>

    <?php
        setup_favicon("..");
        setup_alert_requirements("..");
    ?>    
  </head>

  <body>
<?php
    display_license_check();

    view_top_menu("Configure");

    // Get any alet messages
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
                <h4>Notification Extra</h4>
                <?php display(); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>

        $(document).ready(function(){
            $(".period-dropdown").change(function(){
                var period = $(this).val();
                
                var container = $(this).closest("table");
                $(".specified_time_holder", container).hide();
                $(".specified_time_holder input, .specified_time_holder select", container).prop('disabled', true);
                
                $("#specified_" + period, container).show();
                $("#specified_" + period + " input," + "#specified_" + period + " select", container).prop('disabled', false);
                
            });

            var tabs =  $(".tabs li a");
            var hash = window.location.hash;
            if(hash){
                tabs.removeClass("active");
                $(".tabs").find("[href='"+hash+"']").addClass("active");

                var content = hash.replace('/','');
                $("#content > div").hide();
                $(content).fadeIn(200);
            }

            tabs.click(function() {
                var content = this.hash.replace('/','');
                tabs.removeClass("active");
                $(this).addClass("active");
                $("#content > div").hide();
                $(content).fadeIn(200);
            });
        });

        <?php prevent_form_double_submit_script(['activate_extra', 'deactivate_extra']); ?>
    </script>
  </body>

</html>
