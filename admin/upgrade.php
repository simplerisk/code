<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        require_once('../includes/functions.php');
        require_once('../includes/authenticate.php');
	require_once('../includes/config.php');

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
        $version_to_upgrade = "20140224-001";

        // Database version upgrading to
        $version_upgrading_to = "20140413-001";

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRiskDBUpgrade');

        // Include the language file
        require_once(language_file());

        require_once('../includes/csrf-magic/csrf-magic.php');

        // Check for session timeout or renegotiation
        session_check();

        // If the user requested a logout
        if (isset($_GET['logout']) && $_GET['logout'] == "true")
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

	// Default is no alert
	$alert = false;

        // If the login form was posted
        if (isset($_POST['submit']))
        {
		/*** NEED TO REMOVE AFTER 20140413-001 RELEASE ***/
		$db = db_open();
		$database = DB_DATABASE;
		// Check to see if the lang column already exists
		$stmt = $db->prepare("SELECT null FROM INFORMATION_SCHEMA.COLUMNS WHERE `table_schema` = :database AND `table_name` = 'user' AND `column_name` = 'lang';");
		$stmt->bindParam(":database", $database, PDO::PARAM_STR);
                $stmt->execute();
        	$array = $stmt->fetchAll();
        	// If the column does not already exist
        	if (empty($array))
        	{
                        // Add lang column
                        $stmt = $db->prepare("ALTER TABLE `user` ADD `lang` VARCHAR( 2 ) NULL DEFAULT NULL AFTER `teams` ;");
                        $stmt->execute();
        	}
		db_close($db);
		/*** END REMOVE ***/

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
			if ($db_version == $version_to_upgrade)
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

			// Change the database to use UTF-8
			echo "Changing database to use UTF-8.<br />\n";
			$stmt = $db->prepare("ALTER DATABASE `" . DB_DATABASE . "` CHARACTER SET utf8 DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT COLLATE utf8_general_ci;");
			$stmt->execute();

			// Change the database tables to use UTF-8
			echo "Changing the database tables to use UTF-8.<br />\n";
			$stmt = $db->prepare("SHOW TABLES");
			$stmt->execute();
			$tables = $stmt->fetchAll();

			foreach ($tables as $table)
			{
				$name = $table[0];
				echo "Changing table " . $name . ".<br />\n";
				$stmt = $db->prepare("ALTER TABLE `" . $name . "` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;");
				$stmt->execute();
				$stmt = $db->prepare("ALTER TABLE `" . $name . "` ENGINE = MYISAM;");
				$stmt->execute();
			}

			// Create a table to list supported languages
			echo "Creating a new table to track supported languages.<br />\n";
			$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `languages` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(2) NOT NULL, `full` varchar(50) NOT NULL, PRIMARY KEY (`value`)) AUTO_INCREMENT=1 ENGINE = MYISAM");
			$stmt->execute();

			// Add the supported languages
			echo "Adding English and Portuguese as supported languages.<br />\n";
			$stmt = $db->prepare("INSERT INTO `languages` (`name`, `full`) VALUES ('en', 'English'), ('bp', 'Brazilian Portuguese')");
			$stmt->execute();

			/************************
		 	 * END DATABASE CHANGES *
		 	 ************************/

			// Update the database version information
			echo "Updating the database version information.<br />\n";
			$stmt = $db->prepare("UPDATE `settings` SET `value` = '" . $version_upgrading_to . "' WHERE `settings`.`name` = 'db_version' AND `settings`.`value` = '" . $version_to_upgrade . "' LIMIT 1 ;");
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
