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

/**************************
 * FUNCTION: CHECK GRANTS *
 **************************/
function check_grants($db)
{
	$stmt = $db->prepare("SHOW GRANTS FOR CURRENT_USER;");
	$stmt->execute();
	$array = $stmt->fetchAll();

	// Set the values to false
	$select = false;
	$insert = false;
	$update = false;
	$delete = false;
	$create = false;
	$drop = false;
	$alter = false;

	// For each row of the array
	foreach ($array as $value)
	{
		$string = $value[0];

		// Match SELECT statement
		$regex_pattern = "/SELECT/";
		if (preg_match($regex_pattern, $string))
		{
			$select = true;
		}

                // Match INSERT statement
                $regex_pattern = "/INSERT/";
                if (preg_match($regex_pattern, $string))
                {
                        $insert = true;
                }

                // Match UPDATE statement
                $regex_pattern = "/UPDATE/";
                if (preg_match($regex_pattern, $string))
                {
                        $update = true;
                }

                // Match DELETE statement
                $regex_pattern = "/DELETE/";
                if (preg_match($regex_pattern, $string))
                {
                        $delete = true;
                }

                // Match CREATE statement
                $regex_pattern = "/CREATE/";
                if (preg_match($regex_pattern, $string))
                {
                        $create = true;
                }

                // Match DROP statement
                $regex_pattern = "/DROP/";
                if (preg_match($regex_pattern, $string))
                {
                        $drop = true;
                }

                // Match ALTER statement
                $regex_pattern = "/ALTER/";
                if (preg_match($regex_pattern, $string))
                {
                        $alter = true;
                }
	}

	// If the grants include all values
	if ($select && $insert && $update && $delete && $create && $drop && $alter)
	{
		return true;
	}
	else return false;
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

	// Strip slashes from user table entries
	echo "Stripping slashes from the user table entries.<br />\n";
	$stmt = $db->prepare("SELECT value, name, email, username FROM user");
	$stmt->execute();
	$array = $stmt->fetchAll();
	foreach ($array as $value)
	{
		$stmt = $db->prepare("UPDATE user SET name=:name, email=:email, username=:username WHERE value=:value");
		$stmt->bindParam(":value", $value['value']);
		$stmt->bindParam(":name", stripslashes($value['name']));
		$stmt->bindParam(":email", stripslashes($value['email']));
		$stmt->bindParam(":username", stripslashes($value['username']));
		$stmt->execute();
	}

	// Strip slashes from closures table entries
	echo "Stripping slashes from the closures table entries.<br />\n";
	$stmt = $db->prepare("SELECT id, close_reason, note FROM closures");
	$stmt->execute();
	$array = $stmt->fetchAll();
	foreach ($array as $value)
        {
                $stmt = $db->prepare("UPDATE closures SET close_reason=:close_reason, note=:note WHERE id=:id");
                $stmt->bindParam(":id", $value['id']);
                $stmt->bindParam(":close_reason", stripslashes($value['close_reason']));
                $stmt->bindParam(":note", stripslashes($value['note']));
                $stmt->execute();
        }

        // Strip slashes from risks table entries
        echo "Stripping slashes from the risks table entries.<br />\n";
        $stmt = $db->prepare("SELECT id, subject, reference_id, control_number, location, assessment, notes FROM risks");
        $stmt->execute();
        $array = $stmt->fetchAll();
        foreach ($array as $value)
        {
                $stmt = $db->prepare("UPDATE risks SET subject=:subject, reference_id=:reference_id, control_number=:control_number, location=:location, assessment=:assessment, notes=:notes WHERE id=:id");
                $stmt->bindParam(":id", $value['id']);
                $stmt->bindParam(":subject", stripslashes($value['subject']));
                $stmt->bindParam(":reference_id", stripslashes($value['reference_id']));
		$stmt->bindParam(":control_number", stripslashes($value['control_number']));
		$stmt->bindParam(":location", stripslashes($value['location']));
		$stmt->bindParam(":assessment", stripslashes($value['assessment']));
		$stmt->bindParam(":notes", stripslashes($value['notes']));
                $stmt->execute();
        }

        // Strip slashes from comments table entries
        echo "Stripping slashes from the comments table entries.<br />\n";
        $stmt = $db->prepare("SELECT id, comment FROM comments");
        $stmt->execute();
        $array = $stmt->fetchAll();
        foreach ($array as $value)
        {
                $stmt = $db->prepare("UPDATE comments SET comment=:comment WHERE id=:id");
                $stmt->bindParam(":id", $value['id']);
                $stmt->bindParam(":comment", stripslashes($value['comment']));
                $stmt->execute();
        }

        // Strip slashes from mitigations table entries
        echo "Stripping slashes from the mitigations table entries.<br />\n";
        $stmt = $db->prepare("SELECT id, planning_strategy, mitigation_effort, current_solution, security_requirements, security_recommendations FROM mitigations");
        $stmt->execute();
        $array = $stmt->fetchAll();
        foreach ($array as $value)
        {
                $stmt = $db->prepare("UPDATE mitigations SET planning_strategy=:planning_strategy, mitigation_effort=:mitigation_effort, current_solution=:current_solution, security_requirements=:security_requirements, security_recommendations=:security_recommendations WHERE id=:id");
                $stmt->bindParam(":id", $value['id']);
                $stmt->bindParam(":planning_strategy", stripslashes($value['planning_strategy']));
                $stmt->bindParam(":mitigation_effort", stripslashes($value['mitigation_effort']));
                $stmt->bindParam(":current_solution", stripslashes($value['current_solution']));
                $stmt->bindParam(":security_requirements", stripslashes($value['security_requirements']));
                $stmt->bindParam(":security_recommendations", stripslashes($value['security_recommendations']));
                $stmt->execute();
        }

        // Strip slashes from mgmt_reviews table entries
        echo "Stripping slashes from the mgmt_reviews table entries.<br />\n";
        $stmt = $db->prepare("SELECT id, review, next_step, comments FROM mgmt_reviews");
        $stmt->execute();
        $array = $stmt->fetchAll();
        foreach ($array as $value)
        {
		$stmt = $db->prepare("UPDATE mgmt_reviews SET review=:review, next_step=:next_step, comments=:comments WHERE id=:id");
                $stmt->bindParam(":id", $value['id']);
                $stmt->bindParam(":review", stripslashes($value['review']));
                $stmt->bindParam(":next_step", stripslashes($value['next_step']));
                $stmt->bindParam(":comments", stripslashes($value['comments']));
                $stmt->execute();
        }   
}

/**************************************
 * FUNCTION: UPGRADE FROM 20141013001 *
 **************************************/
function upgrade_from_20141013001($db)
{
        // Database version to upgrade
        define('VERSION_TO_UPGRADE', '20141013-001');

        // Database version upgrading to
        define('VERSION_UPGRADING_TO', '20141129-001');

	// Set the default value for the last_login field in the user table
	echo "Setting a default value for the last_login field in the user table.<br />\n";
	$stmt = $db->prepare("ALTER TABLE `user` MODIFY `last_login` datetime DEFAULT NULL;");
	$stmt->execute();

	// Set the default value for the mitigation_id field in the risks table
	echo "Setting a default value for the mitigation_id field in the risks table.<br />\n";
	$stmt = $db->prepare("ALTER TABLE `risks` MODIFY `mitigation_id` int(11) DEFAULT NULL;");
	$stmt->execute();

	// Make sure that the Unassigned Risks project is ID 0
	echo "Setting the \"Unassigned Risks\" project to ID 0.<br />\n";
	$stmt = $db->prepare("UPDATE `projects` SET value=0 WHERE name='Unassigned Risks'");
	$stmt->execute();

	// Add Transfer as a risk planning strategy
	echo "Adding \"Transfer\" as a risk planning strategy.<br />\n";
	if (defined('LANG_DEFAULT'))
	{
		if (LANG_DEFAULT == "en")
		{
			$stmt = $db->prepare("INSERT INTO planning_strategy (`name`) VALUES ('Transfer');");
		}
		else if (LANG_DEFAULT == "es")
		{
			$stmt = $db->prepare("INSERT INTO planning_strategy (`name`) VALUES ('Transferencia');");
		}
		else if (LANG_DEFAULT == "bp")
		{
			$stmt = $db->prepare("INSERT INTO planning_strategy (`name`) VALUES ('Transferência');");
		}
	}
	else
	{
		$stmt = $db->prepare("INSERT INTO planning_strategy (`name`) VALUES ('Transfer');");
	}
	$stmt->execute();
}

/**************************************
 * FUNCTION: UPGRADE FROM 20141129001 *
 **************************************/
function upgrade_from_20141129001($db)
{
        // Database version to upgrade
        define('VERSION_TO_UPGRADE', '20141129-001');

        // Database version upgrading to
        define('VERSION_UPGRADING_TO', '20141214-001');

        // Set the default value for the mitigation_id field in the risks table
        echo "Setting a default value for the mitigation_id field in the risks table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` MODIFY `mitigation_id` int(11) DEFAULT 0;");
        $stmt->execute();

        // Correct any mitigation_id values of null
        echo "Correcting any mitigation_id values of NULL.<br />\n";
        $stmt = $db->prepare("UPDATE `risks` SET mitigation_id = 0 WHERE mitigation_id IS NULL;");
        $stmt->execute();
}

/**************************************
 * FUNCTION: UPGRADE FROM 20141214001 *
 **************************************/
function upgrade_from_20141214001($db)
{
	// Database version to upgrade
	define('VERSION_TO_UPGRADE', '20141214-001');

	// Database version upgrading to
	define('VERSION_UPGRADING_TO', '20150202-001');

	// Add the field to track asset management permission
	echo "Adding a field to track asset management permissions.<br />\n";
	$stmt = $db->prepare("ALTER TABLE `user` ADD asset tinyint(1) DEFAULT 0 NOT NULL AFTER lang;");
	$stmt->execute();

	// Give admin users asset management permissions
	echo "Giving admin users asset management permissions.<br />\n";
	$stmt = $db->prepare("UPDATE `user` SET asset='1' WHERE admin='1';");
	$stmt->execute();

	// Add the asset tracking table
	echo "Adding the table to track assets.<br />\n";
	$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `assets` (id int(11) AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(15), name VARCHAR(200) NOT NULL UNIQUE, created TIMESTAMP DEFAULT NOW());");
	$stmt->execute();

	// Add table to track risk to asset tagging
	echo "Adding table to track risk to asset tagging.<br />\n";
	$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `risks_to_assets` (risk_id int(11), asset VARCHAR(200) NOT NULL, UNIQUE(risk_id,asset));");
	$stmt->execute();

	// Add a table for scoring methods
	echo "Adding a table for scoring methods.<br />\n";
	$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `scoring_methods` (value int(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20));");
	$stmt->execute();

	// Add scoring methods to table
	echo "Adding scoring methods to scoring methods table.<br />\n";
	$stmt = $db->prepare("INSERT INTO `scoring_methods` VALUES ('1', 'Classic'), ('2', 'CVSS'), ('3', 'DREAD'), ('4', 'OWASP'), ('5', 'Custom');");
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

	// If the grant check for the database user is successful
	if (check_grants($db))
	{
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
                        case "20141013-001":
                                upgrade_from_20141013001($db);
                                update_database_version($db);
                                break;
			case "20141129-001":
				upgrade_from_20141129001($db);
				update_database_version($db);
				break;
			case "20141214-001":
				upgrade_from_20141214001($db);
				update_database_version($db);
				break;
			default:
				echo "No database upgrade is needed at this time.<br />\n";
		}
	}
	// If the grant check was not succesful
	else
	{
		echo "A check of your database user privileges found that one of the necessary grants was missing.  Please ensure that you have granted SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, and ALTER permissions to the user.<br />\n";
	}

	// Disconnect from the database
	echo "Disconnecting from the SimpleRisk database.<br />\n";
	db_close($db);

	echo "SimpleRisk database upgrade is complete.<br />\n";
}

?>
