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
	"check_im_reporting" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// If the Incident Management Extra is enabled
if (incident_management_extra())
{       
        // Load the Incident Management Extra
        require_once(realpath(__DIR__ . '/../extras/incident_management/index.php'));

        process_incident_management();
}
else
{
	// Redirect them to the activation page
	header("Location: ../admin/incidentmanagement.php");
}

?>

<!doctype html>
<html>

    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <script src="../js/jquery.min.js"></script>
        <script src="../js/jquery-ui.min.js"></script>
        <script src="../js/jquery.dataTables.js"></script>
        <script src="../js/jquery.blockUI.min.js"></script>
        <script src="../js/bootstrap.min.js"></script>
        <script src="../js/bootstrap-multiselect.js"></script>
        <script src="../js/selectize.min.js"></script>
<?php
        // If the Incident Management Extra is enabled
        if (incident_management_extra())
        {
                // Include the incident management javascript file
                echo "        <script src=\"../extras/incident_management/js/incident_management.js\"></script>\n";

		// Include the incident management css file
		echo "        <link rel=\"stylesheet\" href=\"../extras/incident_management/css/incident_management.css\">\n";
        }
?>
        <script>
            var simplerisk = {
                incident: "<?php echo $lang['Incident']; ?>",
	            newincident: "<?php echo $lang['NewIncident']; ?>"
            }
            
            var max_upload_size = "<?php echo $escaper->escapeJs(get_setting('max_upload_size', 0)); ?>";
            var fileTooBigMessage = "<?php echo $escaper->escapeJs($lang['FileIsTooBigToUpload']); ?>"; 
            var fileSizeLabel = "<?php echo $escaper->escapeJs($lang['FileSize']);?>"; 
        </script>
	<link rel="stylesheet" href="../css/bootstrap.css">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css">
	<link rel="stylesheet" href="../css/bootstrap-multiselect.css">
        <link rel="stylesheet" href="../css/divshot-util.css">
        <link rel="stylesheet" href="../css/divshot-canvas.css">
	<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
        <link rel="stylesheet" href="../css/jquery.dataTables.css">
        <link rel="stylesheet" href="../css/style.css">
        <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="../css/theme.css">
        <link rel="stylesheet" href="../css/side-navigation.css">
        <link rel="stylesheet" href="../css/selectize.bootstrap3.css">
	<link rel="stylesheet" href="../css/settings_tabs.css">
	<style>
           .tabs li:focus {
               outline: none;
           }
	</style>

        <?php
            setup_favicon("..");
            setup_alert_requirements("..");
        ?>
    </head>

    <body>

        <?php

	// If the Incident Management Extra is enabled
	if (incident_management_extra())
	{
		view_incident_management_top_menu("Reporting");
	}

            // Get any alert messages
            get_alert();
        ?>
        
        <div class="container-fluid">
          <?php display_side_navigation("IncidentManagement"); ?>
          <div class="row-fluid">
            <div class="spacer"></div>
            <div class="span2">
              <?php 
		// If the Incident Management Extra is enabled
		if (incident_management_extra())
		{
			// Display the Reporting menu items
			view_incident_management_reporting_menu();
		}
              ?>
            </div>
            <div class="span4">
              <div class="container-fluid">
                <div class="row-fluid">
                  <div class="span12">
                    <br />
              <?php 
                // If the Incident Management Extra is enabled
                if (incident_management_extra())
                {
			// Display the Reporting content
			display_incident_management_reporting();
		}
              ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

	<script>
		$ = jQuery.noConflict();
		$.noConflict();
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

	</script>
    </body>
</html>
