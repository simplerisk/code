<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));

        // Include Zend Escaper for HTML Output Encoding
        require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
        $escaper = new Zend\Escaper\Escaper('utf-8');

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRisk');

        // Include the language file
        require_once(language_file());

        require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

        // Check for session timeout or renegotiation
        session_check();

        // Default is no alert
        $alert = false;

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Check if access is authorized
        if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
        {
                header("Location: ../index.php");
                exit(0);
        }

	// If SimpleRisk is not registered
	if (get_setting('registration_registered') == 0)
	{
		// Set registered to false
		$registered = false;

		// If the user has sent registration information
		if (isset($_POST['register']))
		{
			// Get the posted values
			$name = $_POST['name'];
			$company = $_POST['company'];
			$title = $_POST['title'];
			$phone = $_POST['phone'];
			$email = $_POST['email'];

			// Add the registration
			$result = add_registration($name, $company, $title, $phone, $email);

			// Set registered to true
			$registered = true;
		}
	}
	// SimpleRisk is registered
	else
	{
		// Set registered to true
		$registered = true;

		// If the user has updated their registration information
		if (isset($_POST['register']))
		{
                        // Get the posted values
                        $name = $_POST['name'];
                        $company = $_POST['company'];
                        $title = $_POST['title'];
                        $phone = $_POST['phone'];
                        $email = $_POST['email'];

			// Update the registration
			update_registration($name, $company, $title, $phone, $email);
		}
		// Otherwise get the registration values from the database
		else
		{
        		$name = get_setting("registration_name");
	        	$company = get_setting("registration_company");
        		$title = get_setting("registration_title");
        		$phone = get_setting("registration_phone");
        		$email = get_setting("registration_email");
		}
	}
?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
    <link rel="stylesheet" href="../css/paypal.css">
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

<?php
	view_top_menu("Configure");

        if ($alert == "good")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 greenalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
?>

    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("Register"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <p><h4><?php echo $escaper->escapeHtml($lang['RegisterSimpleRisk']); ?></h4></p>
                <p><?php echo $escaper->escapeHtml($lang['RegistrationText']); ?></p>
              </div>
            </div>
          </div>
          <div class="row-fluid">
            <div class="span6">
              <div class="hero-unit">
                <p><h4><?php echo $escaper->escapeHtml($lang['RegistrationInformation']); ?></h4></p>
                <form name="register" method="post" action="">
		<?php
			// If the instance is not registered
			if (!$registered)
			{
				// Display the registration table
				display_registration_table_edit();
			}
			// The instance is registered
			else
			{
				// The user wants to update the registration
				if (isset($_POST['update']))
				{
					// Display the editable registration table
					display_registration_table_edit($name, $company, $title, $phone, $email);
				}
				else
				{
					// Display the registration table
					display_registration_table($name, $company, $title, $phone, $email);
				}
			}
		?>
                </form>
              </div>
            </div>
            <div class="span6">
              <div class="hero-unit">
                <p><h4><?php echo $escaper->escapeHtml($lang['UpgradeSimpleRisk']); ?></h4></p>
		<?php
			// If the instance is not registered
			if (!$registered)
			{
				echo "Please register in order to be able to use the easy upgrade feature.";
			}
			// The instance is registered
			else
			{
				display_upgrade();
			}
		?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
