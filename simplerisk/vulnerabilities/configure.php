<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/assets.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
    "check_access" => true,
    "check_vm_configure" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// If the Vulnerability Management Extra is enabled
if (vulnmgmt_extra())
{       
        // Load the Vulnerability Management Extra
        require_once(realpath(__DIR__ . '/../extras/vulnmgmt/index.php'));
}
else
{
	// Redirect them to the activation page
	header("Location: ../admin/vulnmgmt.php");
}
?>

<!doctype html>
<html>

    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

	// If vulnerability management is enabled
	if (vulnmgmt_extra())
	{
		echo "    <script src=\"../extras/vulnmgmt/js/vulnmgmt.js?" . current_version("app") . "\"></script>\n";
	}

	display_bootstrap_javascript();
?>
        <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
	<script src="../js/sorttable.js?<?php echo current_version("app"); ?>"></script>
	<script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>
	<link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">
	<style>
      		.multiselect {
        		width: 350px;
      		} 
	</style>

        <?php
            setup_favicon("..");
            setup_alert_requirements("..");
        ?>
    </head>

    <body>

        <?php

	// If the Vulnerability Management Extra is enabled
	if (vulnmgmt_extra())
	{
                view_vulnerability_management_top_menu("Configure");
	}

            // Get any alert messages
            get_alert();
        ?>
        
        <div class="container-fluid">
          <?php display_side_navigation("VulnerabilityManagement"); ?>
          <div class="row-fluid">
            <div class="spacer"></div>
            <div class="span2">
              <?php 
		// If the Vulnerability Management Extra is enabled
		if (vulnmgmt_extra())
		{
			// Display the Vulnerabilities menu items
			view_vulnerability_management_configure_menu();
		}
              ?>
            </div>
            <div class="span4">
              <div class="container-fluid">
                <div class="row-fluid">
                  <div class="span9">
              <?php 
                // If the Vulnerability Management Extra is enabled
                if (vulnmgmt_extra())
                {
			// Display the Vulnerabilities content
			display_vulnerability_management_configure();
		}
              ?>
                  </div>
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

        function blockWithInfoMessage(message) {
            toastr.options = {
                "timeOut": "0",
                "extendedTimeOut": "0",
            }

            $('#vulnmgmt_wrapper').block({
                message: "<?php echo $escaper->escapeHtml($lang['Processing']); ?>",
                css: { border: '1px solid black' }
            });
            setTimeout(function(){ toastr.info(message); }, 1);
        }

        $(document).ready(function(){
          $("#platforms").multiselect({
            includeSelectAllOption: true,
            clearButton: true,
            filter: true
          });
          $("#sites").multiselect({
            includeSelectAllOption: true,
            clearButton: true,
            filter: true
          });
        }); 

	</script>
        <?php display_set_default_date_format_script(); ?>
    </body>
</html>
