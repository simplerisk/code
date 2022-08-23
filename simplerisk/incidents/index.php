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
	"check_im" => true,
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

if(isset($_GET["action"]) && $_GET["action"] == "download"){
    if(isset($_GET["id"])) download_evidence_file($_GET["id"]);
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

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);
?>
        <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>

    <?php
        // Use these HighCharts scripts
        $scripts = [
                'highcharts.js',
        ];

        // Display the highcharts javascript source
        display_highcharts_javascript($scripts);

	display_bootstrap_javascript();
    ?>

        <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
<?php
        // If the Incident Management Extra is enabled
        if (incident_management_extra())
        {
                // Include the incident management javascript file
                echo "        <script src=\"../extras/incident_management/js/incident_management.js?" . current_version("app") . "\"></script>\n";

		// Include the incident management css file
		echo "        <link rel=\"stylesheet\" href=\"../extras/incident_management/css/incident_management.css?" . current_version("app") . "\">\n";
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
	<link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/style.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
	<link rel="stylesheet" href="../css/settings_tabs.css?<?php echo current_version("app"); ?>">
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
                view_incident_management_top_menu("Incidents");
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
			// Display the Incidents menu items
			view_incident_management_menu();
		}
              ?>
            </div>
            <div class="span4">
              <div class="container-fluid">
                <div class="row-fluid">
                  <div class="span9">
              <?php 
                // If the Incident Management Extra is enabled
                if (incident_management_extra())
                {
			// Display the Incidents content
			display_incident_management();
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
            var hash = window.location.hash;
            
            if(hash){
                tabs.removeClass("active");
                $(".tabs").find("[href='"+hash+"']").addClass("active");

                var content = hash.replace('/','');
                $("#content > div").hide();
                $(content).fadeIn(200);
            }

            tabs.click(function() {
                tabs.removeClass("active");
                $(this).addClass("active");

                var content = this.hash.replace('/','');
                $("#content > div").hide();
                $(content).fadeIn(200);
            });

        })(jQuery);

	</script>
        <?php display_set_default_date_format_script(); ?>
    </body>
</html>
