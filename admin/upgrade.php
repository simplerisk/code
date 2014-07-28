<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/config.php'));

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
        $version_to_upgrade = "20140526-001";

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

/*************************
 * FUNCTION: GET API KEY *
 *************************/
function get_api_key()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT value FROM `settings` WHERE `name`='api_key'");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                // Return false
                return false;
        }
        else return $array[0]['value'];
}

/*****************************
 * FUNCTION: CHECK VALID KEY *
 *****************************/
function check_valid_key($key)
{
        //If the key is correct
        if ($key == get_api_key())
        {
                return true;
        }
        else return false;
}

/********************
 * FUNCTION: LOGOUT *
 ********************/
function logout()
{
	// Deny access
        $_SESSION["access"] = "denied";

        // Reset the session data
        $_SESSION = array();


        // Send a Set-Cookie to invalidate the session cookie
        if (ini_get("session.use_cookies"))
        {
        	$params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
        }

        // Destroy the session
        session_destroy();

        // Redirect to the upgrade login form
        header( 'Location: upgrade.php' );
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
                echo "<div class=\"span12 greenalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $alert_message . "</div>\n";
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
      		echo "<p><label><u>Log In Here</u></label></p>\n";
      		echo "<form name=\"authenticate\" method=\"post\" action=\"\">\n";
      		echo "Username: <input class=\"input-medium\" name=\"user\" id=\"user\" type=\"text\" /><br />\n";
      		echo "Password: <input class=\"input-medium\" name=\"pass\" id=\"pass\" type=\"password\" autocomplete=\"off\" />\n";
		echo "<br />\n";
      		echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">Login</button>\n";
      		echo "</form>\n";
	}
	// Otherwise access was granted so check if the user is an admin
	else if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
        {
		// If CONTINUE was not pressed
		if (!isset($_POST['upgrade_database']))
		{
			// Get the current application version
			$app_version = current_version("app");

			echo "The current application version is: " . $app_version . "<br />\n";

			// Get the current database version
			$db_version = current_version("db");

			echo "The current database version is: " . $db_version . "<br />\n";

			// If the version to upgrade is the current version
			//if ($db_version == $version_to_upgrade)
			if ($db_version == $version_to_upgrade || $db_version == "20140413-001")
			{
				echo "This script will ugprade your database from version " . $version_to_upgrade . " to the version that goes with these application files.  Click &quot;CONTINUE&quot; to proceed.<br />\n";
				echo "<br />\n";
				echo "<form name=\"upgrade_database\" method=\"post\" action=\"\">\n";
				echo "<button type=\"submit\" name=\"upgrade_database\" class=\"btn btn-primary\">CONTINUE</button>\n";
				echo "</form>\n";
			}
			// Otherwise if the db version matches the app version
			else if ($db_version == $app_version)
			{
				echo "Your database is already upgraded to the version that matches your application files.  No additional upgrade is necessary to make it work properly.<br />\n";
			}
			// Otherwise this is not the right database version to upgrade
			else
			{
				echo "This script was meant to upgrade database version " . $version_to_upgrade . " but your current database version is " . $db_version . ".  You will need to use a different database upgrade script instead.<br />\n";
			}
		}
		// Otherwise, CONTINUE was pressed
		else
		{
			// Connect to the database
			echo "Connecting to the SimpleRisk database.<br />\n";
			$db = db_open();

			echo "Beginning upgrade of SimpleRisk database.<br />\n";

			/****************************
                 	* DATABASE CHANGES GO HERE *
		 	****************************/

			// Add Spanish as a supported language file
			echo "Adding Spanish as a supported language.<br />\n";
			$stmt = $db->prepare("INSERT INTO `languages` (`name`, `full`) VALUES ('es', 'Spanish')");
			$stmt->execute();

                        // If the language is en
                        if (LANG_DEFAULT == "en")
                        {
				// Change "Reject Risk" to "Reject Risk and Close"
				echo "Changing \"Reject Risk\" to \"Reject Risk and Close\".<br />\n";
				$stmt = $db->prepare("ALTER TABLE `review` CHANGE `name` `name` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;");
				$stmt->execute();
				$stmt = $db->prepare("UPDATE `review` SET `name` = 'Reject Risk and Close' WHERE `value` =2;");
				$stmt->execute();
			}
                        // If the language is bp
                        else if (LANG_DEFAULT == "bp")
                        {
                                // Change "Reject Risk" to "Reject Risk and Close"
                                echo "Changing \"Reject Risk\" to \"Reject Risk and Close\".<br />\n";
                                $stmt = $db->prepare("ALTER TABLE `review` CHANGE `name` `name` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;");
                                $stmt->execute();
                                $stmt = $db->prepare("UPDATE `review` SET `name` = 'Rechazar Riesgo y Cerrar' WHERE `value` =2;");
                                $stmt->execute();
			}

			// Update database to allow decimal risk levels
			echo "Updating database to allow decimal values for risk levels.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `risk_levels` CHANGE `value` `value` DECIMAL( 2,1 ) NOT NULL ;");
			$stmt->execute();

			// Add field to track custom next review date
			echo "Adding a next review field to the management review table.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `mgmt_reviews` ADD `next_review` VARCHAR( 10 ) NOT NULL DEFAULT '0000-00-00';");
			$stmt->execute();

			// Set database to be able to insert a primary key with a value of 0
			echo "Setting database to accept a primary key with a value of 0.<br />\n";
			$stmt = $db->prepare("SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\"");
			$stmt->execute();

			// If the language is en
			if (LANG_DEFAULT == "en")
			{
				// Set the Unassigned Risks project to a value of 0
				echo "Setting the Unassigned Risks project value to 0.<br />\n";
				$stmt = $db->prepare("UPDATE `projects` SET value = 0 WHERE name = \"Unassigned Risks\"");
				$stmt->execute();
			}
			// If the language is bp
			else if (LANG_DEFAULT == "bp")
			{
                                // Set the Unassigned Risks project to a value of 0
                                echo "Setting the Unassigned Risks project value to 0.<br />\n";
                                $stmt = $db->prepare("UPDATE `projects` SET value = 0 WHERE name = \"Riscos não Atribuídos\"");
                                $stmt->execute();
			}

			// Add a column to track project status
			echo "Adding a column to track project status.<br />\n";
			$stmt = $db->prepare("ALTER TABLE `projects` ADD `status` INT NOT NULL DEFAULT '1'");
			$stmt->execute();

			/************************
		 	 * END DATABASE CHANGES *
		 	 ************************/

			// Update the database version information
			echo "Updating the database version information.<br />\n";
			$stmt = $db->prepare("UPDATE `settings` SET `value` = '" . $version_upgrading_to . "' WHERE `settings`.`name` = 'db_version' LIMIT 1 ;");
			$stmt->execute();

			// Disconnect from the database
			echo "Disconnecting from the SimpleRisk database.<br />\n";
        		db_close($db);

			echo "SimpleRisk database upgrade is complete.<br />\n";
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
