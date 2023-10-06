<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/mfa.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/messages.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Add various security headers
add_security_headers();

// Add the session
add_session_check();

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// If the user attempted to verify the MFA
if (isset($_POST['verify']))
{
    // If the MFA verification process was successful
    if (process_mfa_verify())
    {
        // Redirect back to the profile.php page
        header("Location: profile.php");
    }
}

// If the user attempted to disable the MFA
if (isset($_POST['disable']))
{
    // If the MFA disable process was successful
    if (process_mfa_disable())
    {
        // Redirect back to the profile.php page
        header("Location: profile.php");
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
    <script src="../js/permissions-widget.js?<?php echo current_version("app"); ?>"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">
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
  view_top_menu("Configure");

  // Get any alert messages
  get_alert();

  ?>

  <div class="container-fluid">
      <div class="row-fluid">
          <div class="span12">
              <div class="row-fluid">
                  <div class="span6">

<?php

echo "<form name='mfa' method='post' action=''>\n";

// If the authenticated user does not have MFA enabled
if (!mfa_enabled_for_uid($_SESSION['uid']))
{
    // Display the MFA verification webpage content
    display_mfa_verification_page();
}
else
{
    // Display the MFA reset webpage content
    display_mfa_reset_page();
}

echo "</form>\n";

?>
                      </div>
                  </div>
              </div>
          </div>
      </div>

  </body>

</html>