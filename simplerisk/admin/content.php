<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/mail.php'));
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

global $escaper, $lang;

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
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" type="text/css" href="../css/jquery-ui.min.css?<?php echo current_version("app"); ?>" />
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
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
          <?php view_configure_menu("Content"); ?>
        </div>
        <div class="span9">
          <div class="wrap">
            <ul class="tabs group">
              <li><a <?php echo (!isset($_POST['tab']) || (isset($_POST['tab']) && $_POST['tab'] == 'frameworks') ? "class=\"active\"" : ""); ?> href="#/frameworks"><?php echo $escaper->escapeHtml($lang['Frameworks']); ?></a></li>
              <li><a <?php echo ((isset($_POST['tab']) && $_POST['tab'] == 'assessments') ? "class=\"active\"" : ""); ?> href="#/assessments"><?php echo $escaper->escapeHtml($lang['Assessments']); ?></a></li>
            </ul>
            <div id="content">
              <div id="frameworks" <?php echo (isset($_POST['tab']) && $_POST['tab'] != 'frameworks' ? "style=\"display: none;\"" : ""); ?> >
                <h4><u><?php echo $escaper->escapeHtml($lang['Frameworks']); ?></u></h4>

<?php
	// If this is not on the hosted platform and the Import-Export Extra is either not purchased, not installed or not activated
	if (get_setting('hosting_tier') == false && (!core_is_purchased("import-export") || !core_is_installed("import-export") || !core_extra_activated("import-export")))
	{
		$import_export_check = false;
                // URL for the frameworks
                $url = "https://github.com/simplerisk/import-content/raw/master/Control%20Frameworks/frameworks.xml";
                
                // HTTP Options
                $opts = array(
                        'ssl'=>array(
                                'verify_peer'=>true,
                                'verify_peer_name'=>true,
                        ),
                        'http'=>array(
                                'method'=>"GET",
                                'header'=>"content-type: application/json\r\n",
                        )
                );
                $context = stream_context_create($opts);
                
		// Get the list of frameworks from GitHub
                $frameworks = @file_get_contents($url, false, $context);
                $frameworks_xml = simplexml_load_string($frameworks);

		// If this is not on the hosted platform and the Import-Export Extra is not purchased
		if (get_setting('hosting_tier') == false && !core_is_purchased("import-export"))
		{
			echo "<h4><a href=\"register.php\">Purchase</a> the Import-Export Extra to gain access to one-click install the following control frameworks:</h4>";
		}
		// If the Import-Export extra is purchased but not installed
		else if (!core_is_installed("import-export"))
		{
			echo "<h4><a href=\"register.php\">Install</a> your purchased Import-Export Extra to gain access to one-click install the following control frameworks:</h4>";
		}
		// If the Import-Export extra is purchased and installed but not activated
		else if (!core_extra_activated("import-export"))
		{
			echo "<h4><a href=\"importexport.php\">Activate</a> your Import-Export Extra to gain access to one-click install the following control frameworks:</h4>";
		}

		echo "    <ol style=\"list-style-type: disc;\">\n";

		// For each framework returned from GitHub
		foreach ($frameworks_xml as $framework_xml)
		{
			$name = $framework_xml->{"name"};
			echo "      <li>" . $escaper->escapeHtml($name) . "</li>\n";
		}
		echo "    </ol>\n";
	}
	// The Import-Export Extra is purchased, installed and activated
	else
	{
		// Set the Import-Export check to good
		$import_export_check = true;

		// Load the Import-Export Extra
		require_once(realpath(__DIR__ . "/../extras/import-export/index.php"));

		// Show the frameworks from GitHub
		show_github_frameworks();
	}
?>
              </div>
              <div id="assessments" <?php echo (!isset($_POST['tab']) || (isset($_POST['tab']) && $_POST['tab'] != 'assessments') ? "style=\"display: none;\"" : ""); ?> >
                <h4><u><?php echo $escaper->escapeHtml($lang['Assessments']); ?></u></h4>

<?php

        // If this is not on the hosted platform and the Risk Assessment Extra is either not purchased, not installed or not activated
        if (get_setting('hosting_tier') == false && (!core_is_purchased("assessments") || !core_is_installed("assessments") || !core_extra_activated("assessments")))
        {
                // URL for the assessments 
                $url = "https://raw.githubusercontent.com/simplerisk/import-content/master/Risk%20Assessments/assessments.xml";

                // HTTP Options
                $opts = array(
                        'ssl'=>array(
                                'verify_peer'=>true,
                                'verify_peer_name'=>true,
                        ),
                        'http'=>array(
                                'method'=>"GET",
                                'header'=>"content-type: application/json\r\n",
                        )
                );
                $context = stream_context_create($opts);

                // Get the list of assessments from GitHub
                $assessments = @file_get_contents($url, false, $context);
                $assessments_xml = simplexml_load_string($assessments);

                // If this is not on the hosted platform and the Risk Assessment Extra is not purchased
                if (get_setting('hosting_tier') == false && !core_is_purchased("assessments"))
                {
			// If the Import-Export check passed
			if ($import_export_check)
			{
                        	echo "<h4><a href=\"register.php\">Purchase</a> the Risk Assessment Extra to gain access to one-click install the following assessments:</h4>";
			}
			else
			{
				echo "<h4>The Import-Export and Risk Assessment Extras may be <a href=\"register.php\">purchased</a>, <a href=\"register.php\">installed</a> and <a href=\"register.php\">activated</a> to gain access to one-click install the following assessments:</h4>";
			}
                }
                // If the Risk Assessment extra is purchased but not installed
                else if (!core_is_installed("assessments"))
                {
                        // If the Import-Export check passed
                        if ($import_export_check)
                        {
                        	echo "<h4><a href=\"register.php\">Install</a> your purchased Risk Assessment Extra to gain access to one-click install the following assessments:</h4>";
			}
                        else
                        {
				echo "<h4>The Import-Export and Risk Assessment Extras may be <a href=\"register.php\">purchased</a>, <a href=\"register.php\">installed</a> and <a href=\"register.php\">activated</a> to gain access to one-click install the following assessments:</h4>";
                        }
                }
                // If the Risk Assessment extra is purchased and installed but not activated
                else if (!core_extra_activated("assessments"))
                {
                        // If the Import-Export check passed
                        if ($import_export_check)
                        {
                        	echo "<h4><a href=\"assessments.php\">Activate</a> your Risk Assessment Extra to gain access to one-click install the following assessments:</h4>";
			}
                        else
                        {
				echo "<h4>The Import-Export and Risk Assessment Extras may be <a href=\"register.php\">purchased</a>, <a href=\"register.php\">installed</a> and <a href=\"register.php\">activated</a> to gain access to one-click install the following assessments:</h4>";
                        }
                }

                echo "    <ol style=\"list-style-type: disc;\">\n";

                // For each assessment returned from GitHub
                foreach ($assessments_xml as $assessment_xml)
                {
                        $name = $assessment_xml->{"name"};
                        echo "      <li>" . $escaper->escapeHtml($name) . "</li>\n";
                }
                echo "    </ol>\n";
        }
        // The Risk Assessment Extra is purchased, installed and activated
        else
        {
                // Show the assessments from GitHub
                show_github_assessment();
        }

?>

              </div>

            </div>
          </div>
        </div>
        <script>
            (function($) {

                var tabs =  $(".tabs li a");
                
                var hash = window.location.hash;
                if(hash){
                    //console.log(hash);
                    tabs.removeClass('active');
                    $(".tabs").find("[href='"+hash+"']").addClass('active');

                    var content = hash.replace('/','');
                    $("#content > div").hide();
                    $(content).fadeIn(200);
                }
                
                tabs.click(function() {
                    var content = this.hash.replace('/','');
                    tabs.removeClass("active");
                    $(this).addClass("active");
                    
                    $('#content > div').hide();

                    $(content).fadeIn(200);
                });

            })(jQuery);
        </script>
      </div>
    </div>
  </body>
</html>
