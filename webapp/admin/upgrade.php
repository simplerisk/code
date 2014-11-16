<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/config.php'));
	require_once(realpath(__DIR__ . '/../includes/upgrade.php'));

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

        // Database version to upgrade
        $version_to_upgrade = "20140728-001";

        // Database version upgrading to
        $version_upgrading_to = "20140728-001";

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRiskDBUpgrade');

        // Include the language file
        require_once(language_file());

        require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

        // Check for session timeout or renegotiation
        session_check();

        // If the user requested a logout
        if (isset($_GET['logout']) && $_GET['logout'] == "true")
        {
		// Log the user out
		logout();
	}

	// Default is no alert
	$alert = false;

        // If the login form was posted
        if (isset($_POST['submit']))
        {
                $user = $_POST['user'];
                $pass = $_POST['pass'];

                // If the user is valid
                if (is_valid_user($user, $pass))
                {
                        // Check if the user is an admin
                        if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
                        {
                                // Grant access
                                $_SESSION["access"] = "granted";
                        }
                        // The user is not an admin
                        else
                        {
				$alert = "bad";
                                $alert_message = "You need to log in as an administrative user in order to upgrade the database.";

                                // Deny access
                                $_SESSION["access"] = "denied";
                        }
                }
                // The user was not valid
                else
                {
			// Send an alert
			$alert = "bad";

                        // Invalid username or password
                        $alert_message = "Invalid username or password.";

                        // Deny access
                        $_SESSION["access"] = "denied";
                }
        }

	// If an API key is set and is valid
	if (isset($_GET['key']) && check_valid_key($_GET['key']))
	{
		// Grant access
		$_SESSION["access"] = "granted";

		// API key is admin
		$_SESSION["admin"] = "1";
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
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="upgrade.php">Database Upgrade Script</a>
              </li>
              <li>
                <a href="upgrade.php?logout=true">Logout</a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
<?php
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
        <div class="span12">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
<?php
	// If access was not granted display the login form
	if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
	{
		// Display the login form
		display_login_form();
	}
	// Otherwise access was granted so check if the user is an admin
	else if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
        {
		// If CONTINUE was not pressed
		if (!isset($_POST['upgrade_database']))
		{
			// Display the upgrade information
			display_upgrade_info();
		}
		// Otherwise, CONTINUE was pressed
		else
		{
			// Upgrade the database
			upgrade_database();
		}
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
