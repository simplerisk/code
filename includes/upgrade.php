<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/functions.php'));

// Include the language file
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');



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

/********************************
 * FUNCTION: DISPLAY LOGIN FORM *
 ********************************/
function display_login_form()
{
	global $lang;
	global $escaper;

	echo "<p><label><u>" . $escaper->escapeHtml($lang['LogInHere']) . "</u></label></p>\n";
        echo "<form name=\"authenticate\" method=\"post\" action=\"\">\n";
        echo $escaper->escapeHtml($lang['Username']) . ": <input class=\"input-medium\" name=\"user\" id=\"user\" type=\"text\" /><br />\n";
        echo $escaper->escapeHtml($lang['Password']) . ": <input class=\"input-medium\" name=\"pass\" id=\"pass\" type=\"password\" autocomplete=\"off\" />\n";
        echo "<br />\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Login']) . "</button>\n";
        echo "</form>\n";
}

/**********************************
 * FUNCTION: DISPLAY UPGRADE INFO *
 **********************************/
function display_upgrade_info()
{
	global $escaper;

	// Get the current application version
	$app_version = current_version("app");

	echo "The current application version is: " . $escaper->escapeHtml($app_version) . "<br />\n";

	// Get the current database version
	$db_version = current_version("db");

	echo "The current database version is: " . $escaper->escapeHtml($db_version) . "<br />\n";

	echo "This script will ugprade your database to the next version of SimpleRisk.  Please make sure you have backed up your database before proceeding.  Click &quot;CONTINUE&quot; to begin.<br />\n";
        echo "<br />\n";
        echo "<form name=\"upgrade_database\" method=\"post\" action=\"\">\n";
        echo "<button type=\"submit\" name=\"upgrade_database\" class=\"btn btn-primary\">CONTINUE</button>\n";
        echo "</form>\n";
}

/*************************************
 * FUNCTION: UPDATE DATABASE VERSION *
 *************************************/
function update_database_version($db)
{
	// Update the database version information
	echo "Updating the database version information.<br />\n";

	$stmt = $db->prepare("UPDATE `settings` SET `value` = '" . VERSION_UPGRADING_TO . "' WHERE `settings`.`name` = 'db_version' AND `settings`.`value` = '" . VERSION_TO_UPGRADE . "' LIMIT 1 ;");
	$stmt->execute();
}

/**************************************
 * FUNCTION: UPGRADE FROM 20140728001 *
 **************************************/
function upgrade_from_20140728001($db)
{
	// Database version to upgrade
	define('VERSION_TO_UPGRADE', '20140728-001');

	// Database version upgrading to
	define('VERSION_UPGRADING_TO', '20141013-001');

	// Creating a table to store supporting documentation files
        echo "Creating a table to store supporting documentation files.<br />\n";
        $stmt = $db->prepare("CREATE TABLE files(id INT NOT NULL AUTO_INCREMENT, risk_id INT NOT NULL, name VARCHAR(100) NOT NULL, unique_name VARCHAR(30) NOT NULL, type VARCHAR(30) NOT NULL, size INT NOT NULL, timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, user INT NOT NULL, content BLOB NOT NULL, PRIMARY KEY(id));");
        $stmt->execute();
}

/******************************
 * FUNCTION: UPGRADE DATABASE *
 ******************************/
function upgrade_database()
{
	// Connect to the database
	echo "Connecting to the SimpleRisk database.<br />\n";
	$db = db_open();

	echo "Beginning upgrade of SimpleRisk database.<br />\n";

	// Get the current database version
	$db_version = current_version("db");

	// Run the upgrade for the appropriate current version
	switch ($db_version)
	{
		case "20140728-001":
			upgrade_from_20140728001($db);
			update_database_version($db);
			break;
		default:
			echo "No database upgrade is needed at this time.<br />\n";
	}

	// Disconnect from the database
	echo "Disconnecting from the SimpleRisk database.<br />\n";
	db_close($db);

	echo "SimpleRisk database upgrade is complete.<br />\n";
}

?>
