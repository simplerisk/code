<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/assessments.php'));
require_once(realpath(__DIR__ . '/reporting.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/governance.php'));

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

/****************************
 * FUNCTION: UPGRADE LOGOUT *
 ****************************/
function upgrade_logout()
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

    echo "<h1 class=\"text-center welcome--msg\">Upgrade the SimpleRisk Database </h1>";
        echo "<form name=\"authenticate\" method=\"post\" action=\"\" class=\"loginForm\">\n";
    echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    echo "<tr><td colspan=\"2\"><label class=\"login--label\">" . $escaper->escapeHtml($lang['LogInHere']) . "</label></td></tr>\n";
    echo "<tr><td width=\"20%\"><label for=\"\">" . $escaper->escapeHtml($lang['Username']) . ":&nbsp;</label></td><td class=\"80%\"><input class=\"form-control input-medium\" name=\"user\" id=\"user\" type=\"text\" /></td></tr>\n";
    echo "<tr><td width=\"20%\"><label for=\"\">" . $escaper->escapeHtml($lang['Password']) . ":&nbsp;</label></td><td class=\"80%\"><input class=\"form-control input-medium\" name=\"pass\" id=\"pass\" type=\"password\" autocomplete=\"off\" /></td></tr>\n";
    echo "</table>\n";
    echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary pull-right\">" . $escaper->escapeHtml($lang['Login']) . "</button>\n";
    echo "<input class=\"btn btn-default pull-right\" value=\"" . $escaper->escapeHtml($lang['Reset']) . "\" type=\"reset\">\n";
    echo "</div>\n";
        echo "</form>\n";
}

/**********************************
 * FUNCTION: DISPLAY UPGRADE INFO *
 **********************************/
function display_upgrade_info()
{
    global $escaper;

    echo "<div class=\"container-fluid\">\n";
    echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span9\">\n";
        echo "<div class=\"well\">\n";
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
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/**************************************
 * FUNCTION: CONVERT TABLES TO INNODB *
 **************************************/
function convert_tables_to_innodb()
{
    // Connect to the database
    $db = db_open();

    // Find tables that are not InnoDB
    $stmt = $db->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema='" . DB_DATABASE . "' AND ENGINE!='InnoDB';");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // For each table that is not InnoDB
    foreach ($array as $value)
    {
        // Get the table name
        $table_name = $value['table_name'];

        // We cannot convert the session table due to id characters
        if ($table_name != "sessions")
        {
            // Change the table to InnoDB
            $stmt = $db->prepare("ALTER TABLE " . $table_name . " ENGINE=InnoDB;");
            $stmt->execute();
        }
    }

    // Disconnect from the database
    db_close($db);
}

/************************************
 * FUNCTION: CONVERT TABLES TO UTF8 *
 ************************************/
function convert_tables_to_utf8()
{
    // Connect to the database
    $db = db_open();

    // Find tables that are not InnoDB
    $stmt = $db->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema='" . DB_DATABASE . "' AND TABLE_COLLATION!='utf8_general_ci';");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // For each table that is not InnoDB
    foreach ($array as $value)
    {
        // Get the table name
        $table_name = $value['table_name'];

        // Change the table to InnoDB
        $stmt = $db->prepare("ALTER TABLE " . $table_name . " CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
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
    $all = false;

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

        // Match ALL statement
        $regex_pattern = "/ALL/";
        if (preg_match($regex_pattern, $string))
        {
            $all = true;
        }
    }

    // If the grants include all values
    if ($select && $insert && $update && $delete && $create && $drop && $alter)
    {
        return true;
    }
    // If the grant includes the all value
    else if ($all)
    {
        return true;
    }
    else return false;
}

/*************************************
 * FUNCTION: UPDATE DATABASE VERSION *
 *************************************/
function update_database_version($db, $version_to_upgrade, $version_upgrading_to)
{
    // Update the database version information
    echo "Updating the database version information.<br />\n";

    $stmt = $db->prepare("UPDATE `settings` SET `value` = '" . $version_upgrading_to . "' WHERE `settings`.`name` = 'db_version' AND `settings`.`value` = '" . $version_to_upgrade . "' LIMIT 1 ;");
    $stmt->execute();
}

/**************************************
 * FUNCTION: UPGRADE FROM 20140728001 *
 **************************************/
function upgrade_from_20140728001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20140728-001';

    // Database version upgrading to
    $version_upgrading_to = '20141013-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Creating a table to store supporting documentation files
    echo "Creating a table to store supporting documentation files.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS files(id INT NOT NULL AUTO_INCREMENT, risk_id INT NOT NULL, name VARCHAR(100) NOT NULL, unique_name VARCHAR(30) NOT NULL, type VARCHAR(30) NOT NULL, size INT NOT NULL, timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, user INT NOT NULL, content BLOB NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
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
    foreach ($array as $value){
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
                $comments = stripslashes($value['comment']);

                $stmt = $db->prepare("UPDATE comments SET comment=:comment WHERE id=:id");
                $stmt->bindParam(":id", $value['id']);
                $stmt->bindParam(":comment", $comments);
                $stmt->execute();
        }

        // Strip slashes from mitigations table entries
        echo "Stripping slashes from the mitigations table entries.<br />\n";
        $stmt = $db->prepare("SELECT id, planning_strategy, mitigation_effort, current_solution, security_requirements, security_recommendations FROM mitigations");
        $stmt->execute();
        $array = $stmt->fetchAll();
        foreach ($array as $value)
        {
                $planning_strategy    = stripslashes($value['planning_strategy']);
                $mitigation_effort    = stripslashes($value['mitigation_effort']);
                $current_solution    = stripslashes($value['current_solution']);
                $security_requirements = stripslashes($value['security_requirements']);
                $security_recommendations = stripslashes($value['security_recommendations']);

                $stmt = $db->prepare("UPDATE mitigations SET planning_strategy=:planning_strategy, mitigation_effort=:mitigation_effort, current_solution=:current_solution, security_requirements=:security_requirements, security_recommendations=:security_recommendations WHERE id=:id");
                $stmt->bindParam(":id", $value['id']);
                $stmt->bindParam(":planning_strategy", $planning_strategy);
                $stmt->bindParam(":mitigation_effort", $mitigation_effort);
                $stmt->bindParam(":current_solution", $current_solution);
                $stmt->bindParam(":security_requirements", $security_requirements);
                $stmt->bindParam(":security_recommendations", $security_recommendations);
                $stmt->execute();
        }

        // Strip slashes from mgmt_reviews table entries
        echo "Stripping slashes from the mgmt_reviews table entries.<br />\n";
        $stmt = $db->prepare("SELECT id, review, next_step, comments FROM mgmt_reviews");
        $stmt->execute();
        $array = $stmt->fetchAll();
        foreach ($array as $value)
        {
                $review        = stripslashes($value['review']);
                $next_step    = stripslashes($value['next_step']);
                $comments    = stripslashes($value['comments']);

        $stmt = $db->prepare("UPDATE mgmt_reviews SET review=:review, next_step=:next_step, comments=:comments WHERE id=:id");
                $stmt->bindParam(":id", $value['id']);
                $stmt->bindParam(":review", $review);
                $stmt->bindParam(":next_step", $next_step);
                $stmt->bindParam(":comments", $comments);
                $stmt->execute();
        }   

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20141013001 *
 **************************************/
function upgrade_from_20141013001($db)
{
        // Database version to upgrade
        $version_to_upgrade = '20141013-001';
        
        // Database version upgrading to
        $version_upgrading_to = '20141129-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

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
        else
        {
            $stmt = $db->prepare("INSERT INTO planning_strategy (`name`) VALUES ('Transfer');");
        }
    }
    else
    {
        $stmt = $db->prepare("INSERT INTO planning_strategy (`name`) VALUES ('Transfer');");
    }
    $stmt->execute();

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20141129001 *
 **************************************/
function upgrade_from_20141129001($db)
{
        // Database version to upgrade
        $version_to_upgrade = '20141129-001';

        // Database version upgrading to
        $version_upgrading_to = '20141214-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

        // Set the default value for the mitigation_id field in the risks table
        echo "Setting a default value for the mitigation_id field in the risks table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` MODIFY `mitigation_id` int(11) DEFAULT 0;");
        $stmt->execute();

        // Correct any mitigation_id values of null
        echo "Correcting any mitigation_id values of NULL.<br />\n";
        $stmt = $db->prepare("UPDATE `risks` SET mitigation_id = 0 WHERE mitigation_id IS NULL;");
        $stmt->execute();

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20141214001 *
 **************************************/
function upgrade_from_20141214001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20141214-001';
    
    // Database version upgrading to
    $version_upgrading_to = '20150202-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add the field to track asset management permission
    if (!field_exists_in_table('asset', 'user')) {
        echo "Adding a field to track asset management permissions.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD asset tinyint(1) DEFAULT 0 NOT NULL AFTER lang;");
        $stmt->execute();
    }

    // Give admin users asset management permissions
    echo "Giving admin users asset management permissions.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET asset='1' WHERE admin='1';");
    $stmt->execute();

    // Add the asset tracking table
    echo "Adding the table to track assets.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `assets` (id int(11) AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(15), name VARCHAR(200) NOT NULL UNIQUE, created TIMESTAMP DEFAULT NOW()) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add table to track risk to asset tagging
    echo "Adding table to track risk to asset tagging.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `risks_to_assets` (risk_id int(11), asset VARCHAR(200) NOT NULL, UNIQUE(risk_id,asset)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add a table for scoring methods
    echo "Adding a table for scoring methods.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `scoring_methods` (value int(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add scoring methods to table
    echo "Adding scoring methods to scoring methods table.<br />\n";
    $stmt = $db->prepare("INSERT INTO `scoring_methods` VALUES ('1', 'Classic'), ('2', 'CVSS'), ('3', 'DREAD'), ('4', 'OWASP'), ('5', 'Custom');");
    $stmt->execute();

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20150202001 *
 **************************************/
function upgrade_from_20150202001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20150202-001';

    // Database version upgrading to
    $version_upgrading_to = '20150321-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Increase the size of the name column of the settings table
    echo "Increasing the size of the settings table name column to hold 50 characters.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `settings` MODIFY `name` varchar(50) NOT NULL;");
    $stmt->execute();

    // Increase the size of the value column of the settings table
    echo "Increasing the size of the settings table value column to hold 200 characters.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `settings` MODIFY `value` varchar(200) NOT NULL;");
        $stmt->execute();

        // Set the default value for the mitigation_id field in the risks table to 0 instead of null
        echo "Setting the default value for the mitigation_id field in the risks table to 0.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` MODIFY `mitigation_id` int(11) DEFAULT 0;");
        $stmt->execute();

    // Update risks with mitigation_id of null to 0
    echo "Updating risks with a mitigation_id of null to 0.<br />\n";
    $stmt = $db->prepare("UPDATE `risks` SET `mitigation_id` = 0 WHERE mitigation_id is null;");
    $stmt->execute();

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20150321001 *
 **************************************/
function upgrade_from_20150321001($db)
{
        // Database version to upgrade
        $version_to_upgrade = '20150321-001';

        // Database version upgrading to
        $version_upgrading_to = '20150531-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Get the value for the low review level
    $stmt = $db->prepare("SELECT value FROM review_levels WHERE name = 'Low'");
    $stmt->execute();
    $array = $stmt->fetchAll();
    $low_value = $array[0]['value'];

    // Add a new Insignificant review level
    echo "Adding a new Insignificant review level.<br />\n";
    $stmt = $db->prepare("INSERT INTO `review_levels` VALUE (:low_value, 'Insignificant');");
    $stmt->bindParam(":low_value", $low_value, PDO::PARAM_INT);
    $stmt->execute();

    // Get the value for the high review level
    $stmt = $db->prepare("SELECT value FROM review_levels WHERE name = 'High'");
    $stmt->execute();
        $array = $stmt->fetchAll();
        $high_value = $array[0]['value'];

    // Add a new Very High review level
    echo "Adding a new Very High review level.<br />\n";
    $stmt = $db->prepare("INSERT INTO `review_levels` VALUE (:high_value, 'Very High');");
    $stmt->bindParam(":high_value", $high_value, PDO::PARAM_INT);
        $stmt->execute();

    // Modify the risk levels table to allow for two places to the left of the decimal
    echo "Modifying the risk levels table to allow for two places to the left of the decimal.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `risk_levels` MODIFY `value` decimal(3,1) NOT NULL;");
    $stmt->execute();

    // Add a new Very High risk level
    echo "Adding a new Very High risk level.<br />\n";
    $stmt = $db->prepare("INSERT INTO `risk_levels` VALUE (10.1, 'Very High');");
    $stmt->execute();

    // Add an id column to the review levels table
    if (!field_exists_in_table('id', 'review_levels')) {
        echo "Adding an id column to the review levels table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `review_levels` ADD id int(11) DEFAULT 0 NOT NULL FIRST;");
        $stmt->execute();
    }

    // Set default ids for the review levels table
    echo "Setting default ids for the review levels table.<br />\n";
    $stmt = $db->prepare("UPDATE `review_levels` SET id = 1 WHERE name = 'Very High';");
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `review_levels` SET id = 2 WHERE name = 'High';");
        $stmt->execute();
    $stmt = $db->prepare("UPDATE `review_levels` SET id = 3 WHERE name = 'Medium';");
        $stmt->execute();
    $stmt = $db->prepare("UPDATE `review_levels` SET id = 4 WHERE name = 'Low';");
        $stmt->execute();
    $stmt = $db->prepare("UPDATE `review_levels` SET id = 5 WHERE name = 'Insignificant';");
        $stmt->execute();
    
    // Add a new Very High user responsibility
    if (!field_exists_in_table('review_veryhigh', 'user')) {
        echo "Adding a new Very High user responsibility.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD review_veryhigh tinyint(1) NOT NULL DEFAULT '0' AFTER `admin`;");
        $stmt->execute();
    }

    // Give admin users ability to review Very High risks
    echo "Giving admin users the ability to review Very High risks.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET review_veryhigh='1' WHERE admin='1';");
    $stmt->execute();

    // Add a new Insignificant user responsibility
    if (!field_exists_in_table('review_insignificant', 'user')) {
        echo "Adding a new Insignificant user responsibility.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD review_insignificant tinyint(1) NOT NULL DEFAULT '0' AFTER `review_low`;");
        $stmt->execute();
    }

    // Give admin users ability to review Insignificant risks
    echo "Giving admin users the ability to review Insignificant risks.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET review_insignificant='1' WHERE admin='1';");
    $stmt->execute();

    // Create a random id for this SimpleRisk instance
    echo "Creating a random instance identifier.<br />\n";
    $instance_id = generate_token(50);
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('instance_id', :instance_id)");
    $stmt->bindParam(":instance_id", $instance_id, PDO::PARAM_STR, 50);
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20150531001 *
 **************************************/
function upgrade_from_20150531001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20150531-001';

    // Database version upgrading to
    $version_upgrading_to = '20150729-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Create a new file type table
    echo "Creating a new table to track upload file types.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `file_types` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(100) NOT NULL, PRIMARY KEY (`value`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add default file types
    echo "Adding default upload file types.<br />\n";
    $stmt = $db->prepare("INSERT INTO `file_types` VALUES (1,'image/gif'),(2,'image/jpg'),(3,'image/png'),(4,'image/x-png'),(5,'image/jpeg'),(6,'application/x-pdf'),(7,'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),(8,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),(9,'application/zip'),(10,'text/rtf'),(11,'application/octet-stream'),(12,'text/plain'),(13,'text/xml'),(14,'text/comma-separated-values'),(15,'application/vnd.ms-excel'),(16,'application/msword'),(17,'application/x-gzip'),(18,'application/force-download'),(19,'application/pdf');");
    $stmt->execute();

    // Set maximum upload file size
    echo "Setting maximum upload file size.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` VALUE ('max_upload_size', '5120000');");
    $stmt->execute();

    // Change file content from blob to longblob
    echo "Changing file content type in database from BLOB to LONGBLOB.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `files` MODIFY `content` longblob NOT NULL;");
    $stmt->execute();

    // Add a mitigation_team field to the mitigations table
    if (!field_exists_in_table('mitigation_team', 'mitigations')) {
        echo "Adding a mitigation_team field to the mitigations table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigations` ADD mitigation_team int(11) NOT NULL AFTER mitigation_effort;");
        $stmt->execute();
    }

    // If the batch asset file exists
    if (file_exists(realpath(__DIR__ . '/../assets/batch.php')))
    {
        // Delete the batch asset file
        echo "Deleting the batch asset management file.<br />\n";
    $file = realpath(__DIR__ . '/../assets/batch.php');
    $success = delete_file($file);
        if (!$success)
        {
            echo "<font color=\"red\"><b>Could not delete the batch asset management file.  You can manually delete it here: " . realpath(__DIR__ . '/../assets/batch.php') . "</b></font><br />\n";
        }
    }

    // Add a value column to the assets table
    if (!field_exists_in_table('value', 'assets')) {
        echo "Adding a value column to the assets table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `assets` ADD value int(11) DEFAULT 5 AFTER name;");
        $stmt->execute();
    }

    // Add a setting to show not registered
    echo "Adding a setting to show SimpleRisk is not registered.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('registration_registered', 0)");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20150729001 *
 **************************************/
function upgrade_from_20150729001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20150729-001';

    // Database version upgrading to
    $version_upgrading_to = '20150920-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Create a setting for password policy
    echo "Enabling the new password policy.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('pass_policy_enabled', 1)");
    $stmt->execute();

    // Set the default number of characters required to 8
    echo "Setting the default number of characters required to 8.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('pass_policy_min_chars', 8)");
    $stmt->execute();

    // Set the alpha characters to required
    echo "Setting Alpha characters to required.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('pass_policy_alpha_required', 1)");
    $stmt->execute();

    // Set the upper case characters to required
    echo "Setting Upper Case characters to required.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('pass_policy_upper_required', 1)");
    $stmt->execute();

    // Set the lower case characters to required
    echo "Setting Lower Case characters to required.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('pass_policy_lower_required', 1)");
    $stmt->execute();

    // Set the digits to required
    echo "Setting Digits to required.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('pass_policy_digits_required', 1)");
    $stmt->execute();

    // Set the special characters to required
    echo "Setting Special Characters to required.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('pass_policy_special_required', 1)");
    $stmt->execute();

    // Set the mgmt_review field default value to null
    echo "Setting the mgmt_review field's default value to null.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `risks` MODIFY `mgmt_review` int(11) DEFAULT NULL;");
    $stmt->execute();

    // Set the close_id field default value to null
    echo "Setting the close_id field's default value to null.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `risks` MODIFY `close_id` int(11) DEFAULT NULL;");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20150920001 *
 **************************************/
function upgrade_from_20150920001($db)
{
        // Database version to upgrade
    $version_to_upgrade = '20150920-001';

        // Database version upgrading to
    $version_upgrading_to = '20150928-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

        // Set the mgmt_review field default value to null
        echo "Setting the mgmt_review field's default value to null.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` MODIFY `mgmt_review` int(11) DEFAULT 0;");
        $stmt->execute();

    // Correct for bug in setting of mgmt_review in previous release
    echo "Updating mgmt_review for risks submitted since previous release.<br />\n";
    $stmt = $db->prepare("UPDATE `risks` SET `mgmt_review`=0 WHERE `mgmt_review` IS NULL;");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20150928001 *
 **************************************/
function upgrade_from_20150928001($db)
{
        // Database version to upgrade
        $version_to_upgrade = '20150928-001';

        // Database version upgrading to
        $version_upgrading_to = '20150930-001';

        echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

        echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20150930001 *
 **************************************/
function upgrade_from_20150930001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20150930-001';

    // Database version upgrading to
    $version_upgrading_to = '20151108-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Set the user_id field default value to 0
    echo "Setting the user_id field's default value to null.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `audit_log` MODIFY `user_id` int(11) DEFAULT 0 NOT NULL;");
    $stmt->execute();

    // Increase the size of the subject field to 300
    echo "Increasing the size of the subject field to 300 characters.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `risks` MODIFY `subject` varchar(300) NOT NULL;");
    $stmt->execute();

    // Add a location field for assets
    if (!field_exists_in_table('location', 'assets')) {
        echo "Adding a location field for assets.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `assets` ADD location int(11) NOT NULL AFTER value;");
        $stmt->execute();
    }

    // Add a team field for assets
    if (!field_exists_in_table('team', 'assets')) {
        echo "Adding a team field for assets.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `assets` ADD team int(11) NOT NULL AFTER location;");
        $stmt->execute();
    }

    // If the manage asset file exists
    if (file_exists(realpath(__DIR__ . '/../assets/manage.php')))
    {
        // Delete the manage asset file
        echo "Deleting the asset management file.<br />\n";
        $file = realpath(__DIR__ . '/../assets/manage.php');
        $success = delete_file($file);
        if (!$success)
        {
            echo "<font color=\"red\"><b>Could not delete the asset management file.  You can manually delete it here: " . realpath(__DIR__ . '/../assets/manage.php') . "</b></font><br />\n";
        }
    }

    // If the asset valuation file exists
    if (file_exists(realpath(__DIR__ . '/../assets/valuation.php')))
    {
        // Delete the asset valuation file
        echo "Deleting the asset valuation file.<br />\n";
        $file = realpath(__DIR__ . '/../assets/valuation.php');
        $success = delete_file($file);
        if (!$success)
        {
            echo "<font color=\"red\"><b>Could not delete the asset valuation file.  You can manually delete it here: " . realpath(__DIR__ . '/../assets/valuation.php') . "</b></font><br />\n";
        }
    }

    // Create the asset values table
    echo "Creating the asset values table.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `asset_values` (`id` int(11) NOT NULL, `min_value` int(11) NOT NULL, `max_value` int(11) DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add initial asset values
    echo "Adding initial asset values.<br />\n";
    $stmt = $db->prepare("INSERT INTO `asset_values` VALUES ('1','0','100000'),('2','100001','200000'),('3','200001','300000'),('4','300001','400000'),('5','400001','500000'),('6','500001','600000'),('7','600001','700000'),('8','700001','800000'),('9','800001','900000'),('10','900001','1000000');");
    $stmt->execute();

    // Set the default asset valuation
    echo "Setting the default asset valuation.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('default_asset_valuation', '5');");
    $stmt->execute();

    // Add a mitigation_owner field to the mitigations table
    if (!field_exists_in_table('mitigation_owner', 'mitigations')) {
        echo "Adding a mitigation_owner field to the mitigations table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigations` ADD mitigation_owner int(11) NOT NULL AFTER mitigation_effort;");
        $stmt->execute();
    }

    // Add a mitigation_cost field to the mitigations table
    if (!field_exists_in_table('mitigation_cost', 'mitigations')) {
        echo "Adding a mitigation_cost field to the mitigations table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigations` ADD mitigation_cost int(11) NOT NULL DEFAULT 1 AFTER mitigation_effort;");
        $stmt->execute();
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20151108001 *
 **************************************/
function upgrade_from_20151108001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20151108-001';

    // Database version upgrading to
    $version_upgrading_to = '20151219-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add an asset_id field to the risks_to_assets table
    if (!field_exists_in_table('asset_id', 'risks_to_assets')) {
        echo "Adding an asset_id field to the risks_to_assets table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks_to_assets` ADD COLUMN `asset_id` int(11) NOT NULL AFTER risk_id;");
        $stmt->execute();
    }

    // Delete orphaned entries in the assets table
    echo "Deleting orphaned entries in the assets table.<br />\n";
    $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE asset NOT IN (SELECT a.name FROM assets a);");
    $stmt->execute();

    // Map the asset id for risks_to_assets
    echo "Mapping the asset_id value in the risks_to_assets table.<br />\n";
    $stmt = $db->prepare("UPDATE `risks_to_assets` INNER JOIN `assets` ON `assets`.name = `risks_to_assets`.asset SET `risks_to_assets`.asset_id = `assets`.id;");
    $stmt->execute();

    // Set the file table default risk_id to 0
    echo "Setting the file table default risk_id to 0.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `files` MODIFY `risk_id` int(11) DEFAULT 0;");
    $stmt->execute();

    // Add a type field to the file table
    if (!field_exists_in_table('view_type', 'files')) {
        echo "Adding a type field to the file table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `files` ADD COLUMN `view_type` int(11) DEFAULT 1 AFTER `risk_id`;");
        $stmt->execute();
    }

    // Add a new status table
    echo "Adding a new status table.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `status` (value int(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add new custom statuses
    echo "Adding new custom statuses.<br />\n";
    if (defined('LANG_DEFAULT'))
        {
                if (LANG_DEFAULT == "en")
                {
                        $stmt = $db->prepare("INSERT INTO status (`name`) VALUES ('New'), ('Mitigation Planned'), ('Mgmt Reviewed'), ('Closed'), ('Reopened'), ('Untreated'), ('Treated');");
                }
                else if (LANG_DEFAULT == "es")
                {
                        $stmt = $db->prepare("INSERT INTO status (`name`) VALUES ('Nuevo'), ('Mitigación de Planificación'), ('Gestión Comentado'), ('Cerrado'), ('Reabierto'), ('Sin Tratar'), ('Tratada');");
                }
                else if (LANG_DEFAULT == "bp")
                {
                        $stmt = $db->prepare("INSERT INTO status (`name`) VALUES ('Novo'), ('Mitigação Planejado'), ('Gestão Avaliado'), ('Fechadas'), ('Reaberta'), ('Não Tratada'), ('Tratado');");
                }
        else
        {
            $stmt = $db->prepare("INSERT INTO status (`name`) VALUES ('New'), ('Mitigation Planned'), ('Mgmt Reviewed'), ('Closed'), ('Reopened'), ('Untreated'), ('Treated');");
        }
        }
        else
        {
                $stmt = $db->prepare("INSERT INTO status (`name`) VALUES ('New'), ('Mitigation Planned'), ('Mgmt Reviewed'), ('Closed'), ('Reopened'), ('Untreated'), ('Treated');");
        }
    $stmt->execute();

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

        echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20151219001 *
 **************************************/
function upgrade_from_20151219001($db)
{
        // Database version to upgrade
        $version_to_upgrade = '20151219-001';

        // Database version upgrading to
        $version_upgrading_to = '20160124-001';

        echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add a new currency setting
    echo "Adding a new currency setting.<br />\n";
    add_setting("currency", "$");

    // Add a risk source table
    echo "Adding a new risk source table.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `source` (value int(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Add new custom statuses
        echo "Adding new risk sources.<br />\n";
        if (defined('LANG_DEFAULT'))
        {
                if (LANG_DEFAULT == "en")
                {
                        $stmt = $db->prepare("INSERT INTO source (`name`) VALUES ('People'), ('Process'), ('System'), ('External');");
                }
                else if (LANG_DEFAULT == "es")
                {
                        $stmt = $db->prepare("INSERT INTO source (`name`) VALUES ('Gente'), ('Proceso'), ('Sistema'), ('Externo');");
                }
                else if (LANG_DEFAULT == "bp")
                {
                        $stmt = $db->prepare("INSERT INTO source (`name`) VALUES ('Pessoas'), ('Processo'), ('Sistema'), ('Externo');");
                }
        else
        {
            $stmt = $db->prepare("INSERT INTO source (`name`) VALUES ('People'), ('Process'), ('System'), ('External');");
        }
        }
        else
        {
                $stmt = $db->prepare("INSERT INTO source (`name`) VALUES ('People'), ('Process'), ('System'), ('External');");
        }
        $stmt->execute();

        // Add a source column to the risks table
        if (!field_exists_in_table('source', 'risks')) {
            echo "Adding a source column to the risks table.<br />\n";
            $stmt = $db->prepare("ALTER TABLE `risks` ADD source int(11) NOT NULL AFTER location;");
            $stmt->execute();
        }

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

        echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20160124001 *
 **************************************/
function upgrade_from_20160124001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20160124-001';

    // Database version upgrading to
    $version_upgrading_to = '20160331-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Delete old extra versions from settings table
    echo "Deleting old extra versions from settings table.<br />\n";
    $stmt = $db->prepare("DELETE FROM `settings` WHERE name='custom_auth_version';");
    $stmt->execute();
    $stmt = $db->prepare("DELETE FROM `settings` WHERE name='notifications_version';");
    $stmt->execute();
    $stmt = $db->prepare("DELETE FROM `settings` WHERE name='team_separation_version';");
    $stmt->execute();
    $stmt = $db->prepare("DELETE FROM `settings` WHERE name='import_export_version';");
    $stmt->execute();

    // Add the field to track assessments permission
    if (!field_exists_in_table('assessments', 'user')) {
        echo "Adding a field to track assessments permissions.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD assessments tinyint(1) DEFAULT 0 NOT NULL AFTER lang;");
        $stmt->execute();
    }

    // Give admin users assessments permissions
    echo "Giving admin users assessments permissions.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET assessments='1' WHERE admin='1';");
    $stmt->execute();

    // Add the assessment tracking table
    echo "Adding the table to track assessments.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `assessments` (id int(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, created TIMESTAMP DEFAULT NOW()) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add the assessment questions table
    echo "Adding the table to track assessment questions.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `assessment_questions` (`id` int(11) AUTO_INCREMENT PRIMARY KEY, `assessment_id` int(11) NOT NULL, `question` VARCHAR(1000) NOT NULL, `order` int(11) NOT NULL DEFAULT '999999') ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add the assessment answers table
    echo "Adding the table to track assessment answers.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `assessment_answers` (`id` int(11) AUTO_INCREMENT PRIMARY KEY, `assessment_id` int(11) NOT NULL, `question_id` int(11) NOT NULL, `answer` VARCHAR(200) NOT NULL, `submit_risk` tinyint(1) DEFAULT 0 NOT NULL, `risk_subject` VARCHAR(200) NOT NULL, `risk_score` int(11) NOT NULL, `risk_owner` int(11), `assets` VARCHAR(200), `order` int(11) NOT NULL DEFAULT '999999') ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add the pending risks table
    echo "Adding the table to track pending risks.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `pending_risks` (`id` int(11) AUTO_INCREMENT PRIMARY KEY, `assessment_id` int(11) NOT NULL, `subject` varchar(300) NOT NULL, `score` int(11) NOT NULL, `owner` int(11), `asset` varchar(200), `submission_date` TIMESTAMP DEFAULT NOW()) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add the Critical Security Controls assessment
    require_once(realpath(__DIR__ . '/assessments.php'));
    critical_security_controls_assessment();

    // Add PHPMailer settings
    echo "Adding PHPMailer settings.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_transport', 'sendmail');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_from_email', 'noreply@simplerisk.com');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_from_name', 'SimpleRisk');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_replyto_email', 'noreply@simplerisk.com');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_replyto_name', 'SimpleRisk');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_host', 'smtp1.example.com');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_smtpauth', 'false');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_username', 'user@example.com');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_password', 'secret');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_smtpsecure', 'none');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_port', '587');");
    $stmt->execute();

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

        echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20160331001 *
 **************************************/
function upgrade_from_20160331001($db)
{
        // Database version to upgrade
        $version_to_upgrade = '20160331-001';

        // Database version upgrading to
        $version_upgrading_to = '20160612-001';

        echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";


        // Update the assessment answers table to use a blob for the risk subject
        echo "Updating the assessment answers table to use a blob for the risk subject.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `assessment_answers` MODIFY `risk_subject` blob NOT NULL;");
        $stmt->execute();

        // Update the pending risks table to use a blob for the subject
    echo "Updating the pending risks table to use a blob for the subject.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `pending_risks` MODIFY `subject` blob NOT NULL;");
        $stmt->execute();

    // Update the user table to use a blob for the username
    echo "Updating the user table to use a blob for the username.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `user` MODIFY `username` blob NOT NULL;");
    $stmt->execute();

    // Update the user table to use a blob for the email
    echo "Updating the user table to use a blob for the email.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `user` MODIFY `email` blob NOT NULL;");
    $stmt->execute();

    // Update the language table to have 5 character names
    echo "Updating the language table to have 5 character names.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `languages` MODIFY `name` varchar(5) NOT NULL;");
    $stmt->execute();

    // Add new language translations
    echo "Adding new language translations.<br />\n";
    $stmt = $db->prepare("INSERT INTO `languages` (name, full) VALUES ('ar','Arabic'), ('ca', 'Catalan'), ('cs', 'Czech'), ('da', 'Danish'), ('de', 'German'), ('el', 'Greek'), ('fi', 'Finnish'), ('fr', 'French'), ('he', 'Hebrew'), ('hi', 'Hindi'), ('hu', 'Hungarian'), ('it', 'Italian'), ('ja', 'Japanese'), ('ko', 'Korean'), ('nl', 'Dutch'), ('no', 'Norwegian'), ('pl', 'Polish'), ('pt', 'Portuguese'), ('ro', 'Romanian'), ('ru', 'Russian'), ('sr', 'Serbian'), ('sv', 'Swedish'), ('tr', 'Turkish'), ('uk', 'Ukranian'), ('vi', 'Vietnamese'), ('zh-CN', 'Chinese Simplified'), ('zh-TW', 'Chinese Traditional');");
    $stmt->execute();

        // Update the database version
        update_database_version($db, $version_to_upgrade, $version_upgrading_to);

        echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20160612001 *
 **************************************/
function upgrade_from_20160612001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20160612-001';

    // Database version upgrading to
    $version_upgrading_to = '20161023-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Find all risks with a status of Closed and no closures entry
    echo "Searching for risks with a status of Closed and no closures entry.<br />\n";
    $stmt = $db->prepare("SELECT * FROM `risks` WHERE status=\"Closed\" AND close_id = 0;");
    $stmt->execute();
    $array = $stmt->fetchAll();

    // For each risk
    foreach ($array as $risk)
    {
        $id = $risk['id'];
        $risk_id = (int)$id + 1000;
        $status = "Closed";
        $close_reason = "";
        $note = "";
        
        // Close the risk
        close_risk($risk_id, $_SESSION['uid'], $status, $close_reason, $note);
        echo "Created a closures entry for risk ID " . $risk_id . ".<br />\n";
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20161023001 *
 **************************************/
function upgrade_from_20161023001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20161023-001';

    // Database version upgrading to
    $version_upgrading_to = '20161030-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/**************************************
 * FUNCTION: UPGRADE FROM 20161030001 *
 **************************************/
function upgrade_from_20161030001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20161030-001';

    // Database version upgrading to
    $version_upgrading_to = '20161122-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Create a setting for strict user validation
    echo "Enabling the new strict user validation policy.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (name, value) VALUES ('strict_user_validation', 1)");
    $stmt->execute();

    // Update the user table to allow for a 5 character lang value
    echo "Updating the user table to use a 5 character lang value.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `user` MODIFY `lang` VARCHAR(5) DEFAULT null;");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);

    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20161122-001 *
 ***************************************/
function upgrade_from_20161122001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20161122-001';

    // Database version upgrading to
    $version_upgrading_to = '20170102-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Added a new field mitigate planning date to the mitigate table
    if (!field_exists_in_table('planning_date', 'mitigations')) {
        echo "Adding a new field mitigate planning date to the mitigate table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigations` ADD `planning_date` DATE NOT NULL AFTER `submitted_by`;");
        $stmt->execute();
    }

    // Updated user to be able to allow for more teams
    echo "Updating the user to be able to allow for more teams.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `user` MODIFY `teams` VARCHAR(4000) NOT NULL DEFAULT 'none'");
    $stmt->execute();

    // Added a new field, details to the asset table
    if (!field_exists_in_table('details', 'assets')) {
        echo "Adding a new field, details to the asset table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `assets` ADD  `details` LONGTEXT  AFTER `team`;");
        $stmt->execute();
    }

    // Added new rows, pass_policy_min_age, pass_policy_max_age, pass_policy_attempt_lockout, pass_policy_re_use_tracking to the settings table.
    echo "Adding new rows for pass_policy_min_age, pass_policy_max_age, pass_policy_attempt_lockout, pass_policy_re_use_tracking to the settings table. <br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('pass_policy_min_age', 0), ('pass_policy_max_age', 0), ('pass_policy_re_use_tracking', 0), ('pass_policy_attempt_lockout', 0), ('pass_policy_attempt_lockout_time', 10);");
    $stmt->execute();


    // Add a table to track password re-use
    echo "Adding the table to track password re-use.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `user_pass_history` (`id` int(11) AUTO_INCREMENT PRIMARY KEY, `user_id` int(11) NOT NULL, `salt` varchar(20) NOT NULL, `password` binary(60) NOT NULL, `add_date` TIMESTAMP DEFAULT NOW()) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add a table to track failed login attempts
    echo "Adding the table to track failed login attempts.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `failed_login_attempts` (`id` int(11) AUTO_INCREMENT PRIMARY KEY, `expired` TINYINT DEFAULT 0, `user_id` int(11) NOT NULL, `ip` VARCHAR(15) DEFAULT '0.0.0.0', `date` TIMESTAMP DEFAULT NOW()) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Added last_password_change_date to user table:
    if (!field_exists_in_table('last_password_change_date', 'user')) {
        echo "Adding last_password_change_date to user table. <br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `last_password_change_date` TIMESTAMP DEFAULT NOW() AFTER `last_login`;");
        $stmt->execute();
    }

    // Set last password change date to current date for all users
    echo "Setting the last password change date to now for all users.<br />\n";
    $stmt = $db->prepare("UPDATE `user` set last_password_change_date=NOW();");
    $stmt->execute();

    // Add lockout to user table
    if (!field_exists_in_table('lockout', 'user')) {
        echo "Adding lockout to user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `lockout` TINYINT NOT NULL DEFAULT 0 AFTER `enabled`;");
        $stmt->execute();
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20170102-001 *
 ***************************************/
function upgrade_from_20170102001($db){
    // Database version to upgrade
    $version_to_upgrade = '20170102-001';

    // Database version upgrading to
    $version_upgrading_to = '20170108-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Added a new table to track risk score.
    echo "Adding the table to track risk score.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `risk_scoring_history` (
                          `id` int(11) NOT NULL,
                          `risk_id` int(11) NOT NULL,
                          `calculated_risk` float NOT NULL,
                          `last_update` datetime NOT NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
    ");
    $stmt->execute();

    // Set a primary key to risk score table.
    if (!field_exists_in_table('id', 'risk_scoring_history')) {
        $stmt = $db->prepare("ALTER TABLE `risk_scoring_history` ADD PRIMARY KEY (`id`);");
        $stmt->execute();
    }

    // Set a primary key to auto increment.
    $stmt = $db->prepare("ALTER TABLE `risk_scoring_history` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;");
    $stmt->execute();

    // Add current risks to the risk_scoring_history table
    echo "Adding current risks to the risk scoring history table.<br />\n";
    $stmt = $db->prepare("SELECT a.id, a.calculated_risk, b.submission_date FROM risk_scoring a JOIN risks b on a.id = b.id;");
    $stmt->execute();
    $array = $stmt->fetchAll();

    // For each item in the array
    foreach ($array as $row)
    {
        $stmt = $db->prepare("INSERT INTO `risk_scoring_history` (`risk_id`, `calculated_risk`, `last_update`) VALUES (:risk_id, :calculated_risk, :last_update);");
        $stmt->bindParam(":risk_id", $row['id'], PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $row['calculated_risk'], PDO::PARAM_STR);
        $stmt->bindParam(":last_update", $row['submission_date'], PDO::PARAM_STR);
        $stmt->execute();
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20170108-001 *
 ***************************************/
function upgrade_from_20170108001($db){
    // Database version to upgrade
    $version_to_upgrade = '20170108-001';

    // Database version upgrading to
    $version_upgrading_to = '20170312-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Set the password reset table to 200 charcter username
    echo "Updating the password reset table to use a 200 character username.<br />\n";
    $stmt = $db->prepare("ALTER TABLE password_reset MODIFY COLUMN username VARCHAR(200);");
        $stmt->execute();

    // Get the list of all reviews with a next review of 0000-00-00 or PAST DUE
    echo "Fixing next_review bug from 20170106-01 release.<br />\n";
    $stmt = $db->prepare("UPDATE mgmt_reviews set next_review = '0000-00-00' WHERE next_review='PAST DUE';");
    $stmt->execute();

    // Updated settings table for value field from varchar(200) to have text type
    echo "Updated settings table for value field to have text type.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `settings` CHANGE `value` `value` TEXT ;");
    $stmt->execute();
    
    // Removed the "on update CURRENT_TIMESTAMP" on mgmt_reviews
    echo "Removed the \"on update CURRENT_TIMESTAMP\" on mgmt_reviews.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `mgmt_reviews` CHANGE `submission_date` `submission_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;");
    $stmt->execute();
    
    // Added color field to risk_levels table.
    if (!field_exists_in_table('color', 'risk_levels')) {
        echo "Added a color field to risk_levels table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risk_levels` ADD `color` VARCHAR(20) NOT NULL AFTER `name`; ");
        $stmt->execute();
    }

    $stmt = $db->prepare("UPDATE `risk_levels` SET `color` = 'red' WHERE `name` = 'Very High'; ");
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `risk_levels` SET `color` = 'orangered' WHERE `name` = 'High'; ");
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `risk_levels` SET `color` = 'orange' WHERE `name` = 'Medium'; ");
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `risk_levels` SET `color` = 'yellow' WHERE `name` = 'Low'; ");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20170312-001 *
 ***************************************/
function upgrade_from_20170312001($db){
    // Database version to upgrade
    $version_to_upgrade = '20170312-001';

    // Database version upgrading to
    $version_upgrading_to = '20170416-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";


    // Set the sessions table to use 255 charcter id
    echo "Updating the sessions table to use max 255 characters id.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `sessions` CHANGE `id` `id` VARCHAR(255) NOT NULL;");
    $stmt->execute();

    // Set enable_popup to true by default
    echo "Set enable_popup to true by default.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (`name`, `value`) VALUES ('enable_popup', '1'); ");
    $stmt->execute();

    // Change a next_review field type to DATE
    echo "Change a next_review field type to DATE.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `mgmt_reviews` CHANGE `next_review` `next_review` DATE NOT NULL DEFAULT '0000-00-00'; ");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
        echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20170416-001 *
 ***************************************/
function upgrade_from_20170416001($db){
        // Database version to upgrade
        $version_to_upgrade = '20170416-001';

        // Database version upgrading to
        $version_upgrading_to = '20170614-001';

        echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Set the sessions table to use 255 charcter id
    echo "Updating the sessions table to use max 255 characters id.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `sessions` CHANGE `id` `id` VARCHAR(255) NOT NULL;");
    $stmt->execute();

    // Change the sessions data type to BLOB
    echo "Changing the data field type in the sessions table to BLOB.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `sessions` MODIFY `data` BLOB;");
    $stmt->execute();

    // Add default remember password limit value
    echo "Add default remember password limit value.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`name`, `value`) VALUES ('pass_policy_reuse_limit', '0');");
    $stmt->execute();

    // Add a table to track password history reused
    echo "Adding a table to track password history reused.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `user_pass_reuse_history` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `password` binary(60) NOT NULL,
          `counts` int(11) NOT NULL DEFAULT '1', 
          PRIMARY KEY(id)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Add new file types
    echo "Add new file types, text/csv, application/csv.<br />\n";
    $stmt = $db->prepare("INSERT INTO `file_types` (`value`, `name`) VALUES (NULL, 'text/csv'), (NULL, 'application/csv');");
    $stmt->execute();

    // Add a new field, `change_password` to user table
    if (!field_exists_in_table('change_password', 'user')) {
        echo "Add a new field, `change_password` to user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `change_password` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20170614-001 *
 ***************************************/
function upgrade_from_20170614001($db){
        // Database version to upgrade
        $version_to_upgrade = '20170614-001';

        // Database version upgrading to
        $version_upgrading_to = '20170723-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add a new field, mitigation_percent to mitigations table
    if (!field_exists_in_table('mitigation_percent', 'mitigations')) {
        echo "Add a new field, `mitigation_percent` to `mitigations` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigations` ADD `mitigation_percent` INT NOT NULL;");
        $stmt->execute();
    }

    // Add a new field, custom_display_settings to manage dynamic columns
    if (!field_exists_in_table('custom_display_settings', 'user')) {
        echo "Add a new field, `custom_display_settings` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `custom_display_settings` VARCHAR( 1000 ) NOT NULL;");
        $stmt->execute();
    }

    // Add a new setting, default risk score
    echo "Add a new setting, default risk score.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`name`, `value`) VALUES ('default_risk_score', '10');");
    $stmt->execute();

    // Add a new field to risks table
    if (!field_exists_in_table('additional_stakeholders', 'risks')) {
        echo "Add a new field, `additional_stakeholders` to risks table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD `additional_stakeholders` VARCHAR( 500 ) NOT NULL;");
        $stmt->execute();
    }

    // Set NOTIFY_ADDITIONAL_STAKEHOLDERS to true by default
    echo "Set NOTIFY_ADDITIONAL_STAKEHOLDERS to true by default.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`name` ,`value`) VALUES ('NOTIFY_ADDITIONAL_STAKEHOLDERS', 'true');");
    $stmt->execute();

        // Set default checked values for Dynamic Risk Report
        echo "Setting default checked values for Dynamic Risk Report.<br />\n";
        $stmt = $db->prepare("update user set custom_display_settings='[\"id\",\"subject\",\"calculated_risk\",\"submission_date\",\"mitigation_planned\",\"management_review\"]';");
        $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20170723-001 *
 ***************************************/
function upgrade_from_20170723001($db){
    // Database version to upgrade
    $version_to_upgrade = '20170723-001';

    // Database version upgrading to
    $version_upgrading_to = '20170724-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Set default checked values for Dynamic Risk Report
    echo "Setting default checked values for Dynamic Risk Report.<br />\n";
    $stmt = $db->prepare("update user set custom_display_settings='[\"id\",\"subject\",\"calculated_risk\",\"submission_date\",\"mitigation_planned\",\"management_review\"]';");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20170724-001 *
 ***************************************/
function upgrade_from_20170724001($db){
    // Database version to upgrade
    $version_to_upgrade = '20170724-001';

    // Database version upgrading to
    $version_upgrading_to = '20180104-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Set default all permissions to true for Risks
    echo "Set default all permissions to true for Risks.<br />\n";
    $permissions = array(
        'allow_owner_to_risk'           => 1,
        'allow_ownermanager_to_risk'    => 1,
        'allow_submitter_to_risk'       => 1,
        'allow_team_member_to_risk'     => 1,
        'allow_stakeholder_to_risk'     => 1
    );
    foreach($permissions as $key => $value){
        // Add or Update the permission to risk.
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = :name, `value` = :value ON DUPLICATE KEY UPDATE `value` = :value");
        $stmt->bindParam(":name", $key, PDO::PARAM_STR, 50);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Add a field, comment to pending_risks table
    if (!field_exists_in_table('comment', 'pending_risks')) {
        echo "Add a comment field for pending risks.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `pending_risks` ADD `comment` VARCHAR( 500 ) NULL AFTER `asset`; ");
        $stmt->execute();
    }

    // Add a new field, `compliance` to user table
    if (!field_exists_in_table('compliance', 'user')) {
        echo "Adding a new `compliance` permission to the user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `compliance` TINYINT NOT NULL DEFAULT '0' AFTER `lang`;");
        $stmt->execute();
    }

    // Add a new field, `riskmanagement` to user table
    if (!field_exists_in_table('riskmanagement', 'user')) {
        echo "Adding a new `riskmanagement` permission to the user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `riskmanagement` TINYINT NOT NULL DEFAULT '1' AFTER `lang`;");
        $stmt->execute();
    }

    // Add a new field, `governance` to user table
    if (!field_exists_in_table('governance', 'user')) {
        echo "Adding a new `governance` permission to the user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `governance` TINYINT NOT NULL DEFAULT '0' AFTER `lang`;");
        $stmt->execute();
    }

    // Give admin users governance permissions
    echo "Giving admin users governance permissions.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET governance='1' WHERE admin='1';");
    $stmt->execute();

    // Give admin users compliance permissions
    echo "Giving admin users compliance permissions.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET compliance='1' WHERE admin='1';");
    $stmt->execute();

    // Create the table to track control frameworks
    echo "Creating the new frameworks table.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `frameworks` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` blob NOT NULL, `description` blob NOT NULL, `status` int(11) NOT NULL DEFAULT 1, PRIMARY KEY(value))");
    $stmt->execute();

    // Add some common control frameworks
    $stmt = $db->prepare("INSERT INTO `frameworks` (name, description, status) VALUES ('Custom', '', 1), ('HIPAA', 'https://www.hhs.gov/hipaa/index.html', 2),('ISO 27001', 'https://www.iso.org', 2),('PCI DSS 3.2', 'https://www.pcisecuritystandards.org', 2),('Sarbanes-Oxley', 'https://www.sec.gov/about/laws/soa2002.pdf', 2)");
    $stmt->execute();

    // Add some common control frameworks
    if (!field_exists_in_table('order', 'frameworks')) {
        $stmt = $db->prepare("ALTER TABLE `frameworks` ADD `order` INT NOT NULL;");
        $stmt->execute();
    }

    // Update the assessment answers table to accept a float for risk score
    echo "Updating the assessment answers table to accept a float for risk score.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `assessment_answers` MODIFY `risk_score` float NOT NULL;");
    $stmt->execute();

    // Update the pending risks table to accept a float for risk score
    echo "Updating the pending risks table to accept a float for risk score.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `pending_risks` MODIFY `score` float NOT NULL;");
    $stmt->execute();

    // Add the NIST 800-171 Assessment Questionnaire
    echo "Adding the NIST 800-171 assessment questionnaire.<br />\n";
    nist_800_171_assessment();

    // Add the PCI DSS 3.2 Assessment Questionnaire
    echo "Adding the PCI DSS 3.2 assessment questionnaire.<br />\n";
    pci_dss_3_2_assessment();

    // Add the HIPAA (April 2016) Assessment Questionnaire
    echo "Adding the HIPAA (April 2016) assessment questionnaire.<br />\n";
    hipaa_april_2016_assessment();

    // Add the field tracking table
    echo "Adding a field tracking table.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `fields` (id int(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, type VARCHAR(20) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add assessment_answer_id field to pending_risks table.
    if (!field_exists_in_table('assessment_answer_id', 'pending_risks')) {
        echo "Add assessment_answer_id field to pending_risks table.<br />\n";
        $stmt = $db->prepare("
            ALTER TABLE `pending_risks` ADD `assessment_answer_id` INT NOT NULL AFTER `assessment_id`;
        ");
        $stmt->execute();
    }
    
   // Creating a table to store framework controls.
    echo "Creating a table to store framework controls.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `framework_controls` (
          `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `short_name` VARCHAR(100) NOT NULL,
          `long_name` BLOB ,
          `description` BLOB ,
          `supplemental_guidance` BLOB,
          `framework_ids` VARCHAR(255) ,
          `control_owner` INT(11) ,
          `control_class` INT(11) ,
          `control_phase` INT(11) ,
          `control_number` VARCHAR(20) ,
          `control_priority` INT(11) ,
          `family` INT(11),
          `submission_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Creating a table, control_class.
    echo "Creating a table, control_class.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `control_class` (
          `value` INT PRIMARY KEY,
          `name` MEDIUMTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Add control class to table
    echo "Adding control classes to control class table.<br />\n";
    $stmt = $db->prepare("
        INSERT INTO `control_class` (`value`, `name`) VALUES ('1', 'Technical'), ('2', 'Operational'), ('3', 'Management');
    ");
    $stmt->execute();
    
    // Creating a table, control_phase.
    echo "Creating a table, control_phase.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `control_phase` (
          `value` INT AUTO_INCREMENT PRIMARY KEY,
          `name` MEDIUMTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Add control class to table
    echo "Adding control phases to control phase table.<br />\n";
    $stmt = $db->prepare("
        INSERT INTO `control_phase` (`value`, `name`) VALUES ('1', 'Physical'), ('2', 'Procedural'), ('3', 'Technical'), ('4', 'Legal and Regulatory or Compliance');
    ");
    $stmt->execute();
    
    // Creating a table, control_priority.
    echo "Creating a table, control_priority.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `control_priority` (
          `value` INT PRIMARY KEY,
          `name` MEDIUMTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Add control priorities to table
    echo "Adding control priorities to control priority table.<br />\n";
    $stmt = $db->prepare("
        INSERT INTO `control_priority` (`value`, `name`) VALUES ('1', 'P0'), ('2', 'P1'), ('3', 'P2'), ('4', 'P3');
    ");
    $stmt->execute();
    
    // Creating a table, family.
    echo "Creating a table, family.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `family` (
          `value` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `short_name` MEDIUMTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Add auto increment to control class table
    echo "Add auto increment to control class table.<br />\n";
    $stmt = $db->prepare("
        ALTER TABLE `control_class` CHANGE `value` `value` INT(11) NOT NULL AUTO_INCREMENT; 
    ");
    $stmt->execute();
    
    // Add auto increment to control priority table
    echo "Add auto increment to control priority table.<br />\n";
    $stmt = $db->prepare("
        ALTER TABLE `control_priority` CHANGE `value` `value` INT(11) NOT NULL AUTO_INCREMENT;
    ");
    $stmt->execute();
    
    // Add mitigation_controls field to `mitigations` table
    if (!field_exists_in_table('mitigation_controls', 'mitigations')) {
        echo "Add mitigation_controls field to `mitigations` table.<br />\n";
        $stmt = $db->prepare("
            ALTER TABLE `mitigations` ADD `mitigation_controls` MEDIUMTEXT;
        ");
        $stmt->execute();
    }

    // Add PHPMailer settings
    echo "Adding PHPMailer setting for SMTP Auto TLS.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` VALUES ('phpmailer_smtpautotls', 'true');");
    $stmt->execute();
    
    // Create framework controls test table
    echo "Creating framework controls test table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `framework_control_tests` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `tester` int(11) NOT NULL,
          `test_frequency` int(11) NOT NULL DEFAULT '0',
          `last_date` date NOT NULL,
          `next_date` date NOT NULL,
          `name` MEDIUMTEXT NOT NULL,
          `objective` MEDIUMTEXT NOT NULL,
          `test_steps` MEDIUMTEXT NOT NULL,
          `approximate_time` int(11) NOT NULL,
          `expected_results` MEDIUMTEXT NOT NULL,
          `framework_control_id` int(11) NOT NULL,
          `desired_frequency` int(11) DEFAULT NULL,
          `status` int(11) NOT NULL DEFAULT '1',
          `created_at` DATE NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `id` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
    ");
    $stmt->execute();
    
    // Add parent field to frameworks
    if (!field_exists_in_table('parent', 'frameworks')) {
        echo "Adding parent field to frameworks.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `frameworks` ADD `parent` INT NOT NULL AFTER `value`;");
        $stmt->execute();
    }
    
    // Add last_audit_date, next_audit_date, desired_frequency field to frameworks
    if (!field_exists_in_table('last_audit_date', 'frameworks')) {
        echo "Adding last_audit_date field to frameworks.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `frameworks` ADD `last_audit_date` DATE;");
        $stmt->execute();
    }
    if (!field_exists_in_table('next_audit_date', 'frameworks')) {
        echo "Adding next_audit_date field to frameworks.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `frameworks` ADD `next_audit_date` DATE;");
        $stmt->execute();
    }
    if (!field_exists_in_table('desired_frequency', 'frameworks')) {
        echo "Adding desired_frequency field to frameworks.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `frameworks` ADD `desired_frequency` INT;");
        $stmt->execute();
    }    
    
    // Add last_audit_date, next_audit_date, desired_frequency field to framework_controls
    if (!field_exists_in_table('last_audit_date', 'framework_controls')) {
        echo "Adding last_audit_date field to framework_controls.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `last_audit_date` DATE;");
        $stmt->execute();
    }
    if (!field_exists_in_table('next_audit_date', 'framework_controls')) {
        echo "Adding next_audit_date field to framework_controls.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `next_audit_date` DATE;");
        $stmt->execute();
    }
    if (!field_exists_in_table('desired_frequency', 'framework_controls')) {
        echo "Adding desired_frequency field to framework_controls.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `desired_frequency` INT;");
        $stmt->execute();
    }
    if (!field_exists_in_table('status', 'framework_controls')) {
        echo "Adding status field to framework_controls.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `status` INT NOT NULL DEFAULT '1';");
        $stmt->execute();
    }

    // Create `framework_control_test_audits` table
    echo "Creating `framework_control_test_audits` table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `framework_control_test_audits` (
          `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `test_id` int(11) NOT NULL,
          `tester` int(11) NOT NULL,
          `test_frequency` int(11) NOT NULL DEFAULT '0',
          `last_date` date NOT NULL,
          `next_date` date NOT NULL,
          `name` mediumtext NOT NULL,
          `objective` mediumtext NOT NULL,
          `test_steps` mediumtext NOT NULL,
          `approximate_time` int(11) NOT NULL,
          `expected_results` mediumtext NOT NULL,
          `framework_control_id` int(11) NOT NULL,
          `desired_frequency` int(11) DEFAULT NULL,
          `status` int(11) NOT NULL DEFAULT '1',
          `created_at` datetime NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ;");
    $stmt->execute();
    
    // Create a framework_control_test_results table
    echo "Create a framework_control_test_results table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `framework_control_test_results` (
          `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `test_audit_id` int(11) NOT NULL,
          `test_result` varchar(50) NOT NULL,
          `summary` text NOT NULL,
          `test_date` date NOT NULL,
          `submitted_by` int(11) NOT NULL,
          `submission_date` datetime NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Create a compliance files table
    echo "Create a compliance files table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `compliance_files` (
          `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `ref_id` int(11) DEFAULT '0',
          `ref_type` varchar(100) DEFAULT '',
          `name` varchar(100) NOT NULL,
          `unique_name` varchar(30) NOT NULL,
          `type` varchar(30) NOT NULL,
          `size` int(11) NOT NULL,
          `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `user` int(11) NOT NULL,
          `content` longblob NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Create a test comments table
    echo "Create a test comments table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `framework_control_test_comments` (
          `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `test_audit_id` int(11) NOT NULL,
          `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `user` int(11) NOT NULL,
          `comment` mediumtext NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8; 
    ");
    $stmt->execute();

    // Add a log_type field
    if (!field_exists_in_table('log_type', 'audit_log')) {
        echo "Adding a log_type field.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `audit_log` ADD `log_type` VARCHAR(100) NOT NULL ;");
        $stmt->execute();
    }

    // Set the timestamp not to update on update
    echo "Setting the timestamp for the audit_log not to update on update.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `audit_log` CHANGE `timestamp` `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;");
    $stmt->execute();

    // Add values to log_type filed of current logs
    echo "Updating values to log_type filed of current logs.<br />\n";
    $stmt = $db->prepare("UPDATE `audit_log` SET `log_type`='risk';");
    $stmt->execute();

    // Create assessment_scoring table
    echo "Creating assessment_scoring table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `assessment_scoring` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `scoring_method` int(11) NOT NULL,
          `calculated_risk` float NOT NULL,
          `CLASSIC_likelihood` float NOT NULL DEFAULT '5',
          `CLASSIC_impact` float NOT NULL DEFAULT '5',
          `CVSS_AccessVector` varchar(3) NOT NULL DEFAULT 'N',
          `CVSS_AccessComplexity` varchar(3) NOT NULL DEFAULT 'L',
          `CVSS_Authentication` varchar(3) NOT NULL DEFAULT 'N',
          `CVSS_ConfImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_IntegImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_AvailImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_Exploitability` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_RemediationLevel` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_ReportConfidence` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_CollateralDamagePotential` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_TargetDistribution` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_ConfidentialityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_IntegrityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_AvailabilityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `DREAD_DamagePotential` int(11) DEFAULT '10',
          `DREAD_Reproducibility` int(11) DEFAULT '10',
          `DREAD_Exploitability` int(11) DEFAULT '10',
          `DREAD_AffectedUsers` int(11) DEFAULT '10',
          `DREAD_Discoverability` int(11) DEFAULT '10',
          `OWASP_SkillLevel` int(11) DEFAULT '10',
          `OWASP_Motive` int(11) DEFAULT '10',
          `OWASP_Opportunity` int(11) DEFAULT '10',
          `OWASP_Size` int(11) DEFAULT '10',
          `OWASP_EaseOfDiscovery` int(11) DEFAULT '10',
          `OWASP_EaseOfExploit` int(11) DEFAULT '10',
          `OWASP_Awareness` int(11) DEFAULT '10',
          `OWASP_IntrusionDetection` int(11) DEFAULT '10',
          `OWASP_LossOfConfidentiality` int(11) DEFAULT '10',
          `OWASP_LossOfIntegrity` int(11) DEFAULT '10',
          `OWASP_LossOfAvailability` int(11) DEFAULT '10',
          `OWASP_LossOfAccountability` int(11) DEFAULT '10',
          `OWASP_FinancialDamage` int(11) DEFAULT '10',
          `OWASP_ReputationDamage` int(11) DEFAULT '10',
          `OWASP_NonCompliance` int(11) DEFAULT '10',
          `OWASP_PrivacyViolation` int(11) DEFAULT '10',
          `Custom` float DEFAULT '10',
          PRIMARY KEY (`id`),
          UNIQUE KEY `id` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
    ");
    $stmt->execute();

    // Add assessment_scoring_id field to assessment_answers table
    if (!field_exists_in_table('assessment_scoring_id', 'assessment_answers')) {
        echo "Adding assessment_scoring_id field to assessment_answers table. <br />\n";
        $stmt = $db->prepare("ALTER TABLE `assessment_answers` ADD `assessment_scoring_id` INT NOT NULL AFTER `risk_score` ");
        $stmt->execute();
    }
    
    // Add an entry in the assessment_scoring table for each current assessment answer
    echo "Adding an entry in the assessment scoring table for each assessment answer.<br />\n";
    $stmt = $db->prepare("SELECT * FROM `assessment_answers`;");
    $stmt->execute();
    $array = $stmt->fetchAll();

    // For each item in the array
    foreach ($array as $row)
    {
        $stmt = $db->prepare("INSERT INTO `assessment_scoring` (`scoring_method`, `calculated_risk`, `custom`) VALUES (5, :calculated_risk, :custom);");
        $stmt->bindParam(":calculated_risk", $row['risk_score']);
        $stmt->bindParam(":custom", $row['risk_score']);
        $stmt->execute();

        // Get the id of the assessment scoring
        $last_insert_id = $db->lastInsertId();

        // Update the assessment_scoring_id in the assessment_scoring table
        $stmt = $db->prepare("UPDATE `assessment_answers` SET assessment_scoring_id = :assessment_scoring_id WHERE id = :id;");
        $stmt->bindParam(":assessment_scoring_id", $last_insert_id, PDO::PARAM_INT);
        $stmt->bindParam(":id", $row['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20180104-001 *
 ***************************************/
function upgrade_from_20180104001($db){
    // Database version to upgrade
    $version_to_upgrade = '20180104-001';

    // Database version upgrading to
    $version_upgrading_to = '20180301-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Set the timestamp not to update on update
    echo "Setting the timestamp for the audit_log not to update on update.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `audit_log` CHANGE `timestamp` `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;");
    $stmt->execute();

    // Increase the control family to 50 characters
    echo "Increasing the control family from 20 to 100 characters and renaming field from short_name to name.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `family` CHANGE short_name name varchar(100) NOT NULL;");
    $stmt->execute();

    // Add a mitigation_percent field to framework_controls table
    if (!field_exists_in_table('mitigation_percent', 'framework_controls')) {
        echo "Adding a mitigation_percent field to framework_controls table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `mitigation_percent` INT NOT NULL DEFAULT '0' AFTER `desired_frequency`;");
        $stmt->execute();
    }

    // Created a table, test_status.
    echo "Creating a table, test_status.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `test_status` (
          `value` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL, 
          PRIMARY KEY(value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Add audit statuses to the test status table
    echo "Adding audit statuses to the test status table.<br />\n";
    $stmt = $db->prepare("INSERT INTO `test_status` (`value`, `name`) VALUES (1, \"Pending Evidence from Control Owner\"), (2, \"Evidence Submitted / Pending Review\"), (3, \"Passed Internal QA\"), (4, \"Remediation Required\"), (5, \"Closed\"); ");
    $stmt->execute();

    // Set a closed audit status to settings table
    echo "Setting the default closed audit status in the settings table.<br />\n";
    update_setting("closed_audit_status", 5);

    // Set the session last activity timeout
    echo "Creating a database setting for the session last activity timeout.<br />\n";
    set_session_last_activity_timeout();

    // Set the session renegotiation period 
    echo "Creating a database setting for the session renegotiation period.<br />\n";
    set_session_renegotiation_period();

    // Set the content security policy
    echo "Creating a database setting for the content security policy.<br />\n";
    set_content_security_policy();

    // Set the debug logging
    echo "Creating a database setting for debug logging.<br />\n";
    set_debug_logging();

    // Set the debug log file
    echo "Creating a database setting for the debug log file.<br />\n";
    set_debug_log_file();

    // Set the default language
    echo "Creating a database setting for the default language.<br />\n";
    set_default_language();

    // Set the default timezone
    echo "Creating a database setting for the default timezone.<br />\n";
    set_default_timezone();

    // Add support for the Bulgarian language
    echo "Add support for the Bulgarian language.<br />\n";
    $stmt = $db->prepare("INSERT INTO languages (`name`, `full`) VALUES ('bg', 'Bulgarian');");
    $stmt->execute();

    // Add support for the Slovak language
    echo "Add support for the Slovak language.<br />\n";
    $stmt = $db->prepare("INSERT INTO languages (`name`, `full`) VALUES ('sk', 'Slovak');");
    $stmt->execute();

    // Add questionnaire pending risks table
    echo "Adding questionnaire pending risks table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_pending_risks` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_tracking_id` int(11) NOT NULL,
          `questionnaire_scoring_id` int(11) NOT NULL,
          `subject` blob NOT NULL,
          `owner` int(11) DEFAULT NULL,
          `asset` varchar(200) DEFAULT NULL,
          `comment` varchar(500) DEFAULT NULL,
          `submission_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;
    ");
    $stmt->execute();

    // Add a new setting to show all risks in plan projects
    echo "Adding a new setting to show all risks in Plan Projects.<br />\n";
    add_setting("plan_projects_show_all", "0");

    // Convert all MyISAM tables to InnoDB
    convert_tables_to_innodb();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20180301-001 *
 ***************************************/
function upgrade_from_20180301001($db){
    // Database version to upgrade
    $version_to_upgrade = '20180301-001';

    // Database version upgrading to
    $version_upgrading_to = '20180527-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Updating file types from 100 characters to 1000
    echo "Updating file types from 100 characters to 1000.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `file_types` MODIFY `name` varchar(1000) NOT NULL;");
    $stmt->execute();

    // Creating a table to store residual risk score history
    echo "Creating a table to store residual risk score history.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `residual_risk_scoring_history` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `risk_id` int(11) NOT NULL,
          `residual_risk` float NOT NULL,
          `last_update` datetime NOT NULL, 
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
    );
    $stmt->execute();

    // Add deleted field to framework_controls table
    if (!field_exists_in_table('deleted', 'framework_controls')) {
        echo "Adding deleted field to framework_controls table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `deleted` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    // Add display_name field to risk_levels table
    if (!field_exists_in_table('display_name', 'risk_levels')) {
        echo "Adding display_name field to risk_levels table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risk_levels` ADD `display_name` VARCHAR(20) NOT NULL; ");
        $stmt->execute();
    }

    // Set display_name values
    echo "Setting display_name values.<br />\n";
    $stmt = $db->prepare("Update `risk_levels` set display_name = name; ");
    $stmt->execute();

    // Creating date_formats table
    echo "Creating date_formats table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `date_formats` (
          `value` varchar(20) NOT NULL PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Add values to date_formats table
    echo "Adding values to date_formats table.<br />\n";
    $stmt = $db->prepare("
        INSERT INTO `date_formats` (`value`) VALUES
        ('DD MM YYYY'),
        ('DD-MM-YYYY'),
        ('DD.MM.YYYY'),
        ('DD/MM/YYYY'),
        ('MM DD YYYY'),
        ('MM-DD-YYYY'),
        ('MM.DD.YYYY'),
        ('MM/DD/YYYY'),
        ('YYYY DD MM'),
        ('YYYY MM DD'),
        ('YYYY-DD-MM'),
        ('YYYY-MM-DD'),
        ('YYYY.DD.MM'),
        ('YYYY.MM.DD'),
        ('YYYY/DD/MM'),
        ('YYYY/MM/DD');
    ");
    $stmt->execute();

    // Add a new default date format setting
    echo "Adding a new default date format setting.<br />\n";
    add_setting("default_date_format", "MM/DD/YYYY");

    // Create role table
    echo "Creating role table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `role` (
          `value` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `name` varchar(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Create role_responsibilities table
    echo "Creating role_responsibilities table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `role_responsibilities` (
          `role_id` int(11) NOT NULL,
          `responsibility_name` varchar(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Add Administrator role
    echo "Adding Administrator role.<br />\n";
    $stmt = $db->prepare("INSERT INTO `role` (`value`, `name`) VALUES ('1', 'Administrator'); ");
    $stmt->execute();
    
    // Add a field, role_id to `user` table
    if (!field_exists_in_table('role_id', 'user')) {
        echo "Adding a field, role_id to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `role_id` INT NOT NULL AFTER `teams` ;");
        $stmt->execute();
    }

    // Add a field, accept_mitigation to `user` table
    if (!field_exists_in_table('accept_mitigation', 'user')) {
        echo "Adding a field, accept_mitigation to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `accept_mitigation` TINYINT(1) NOT NULL DEFAULT '0' AFTER `review_high`; ");
        $stmt->execute();
    }
    
    // Get all teams
    $stmt = $db->prepare("SELECT value FROM `team` ");
    $stmt->execute();
    $teamIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $teams = ":".implode("::", $teamIds).":";
    
    // Assign administrator role to all users with access to configure menu.
    $stmt = $db->prepare("UPDATE `user` SET governance=1, riskmanagement=1, compliance=1, assessments=1, asset=1, review_veryhigh=1, accept_mitigation=1, review_high=1, review_medium=1, review_low=1, review_insignificant=1, submit_risks=1, modify_risks=1, plan_mitigations=1, close_risks=1, role_id=1, teams='{$teams}' WHERE admin=1; ");
    $stmt->execute();
    
    // Create a mitigation_accept_users table
    echo "Creating a mitigation_accept_users table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `mitigation_accept_users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `risk_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `created_at` datetime NOT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Add new fields to user table
    if (!field_exists_in_table('add_new_frameworks', 'user')) {
        echo "Adding new field `add_new_frameworks` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `add_new_frameworks` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }
    if (!field_exists_in_table('modify_frameworks', 'user')) {
        echo "Adding new field `modify_frameworks` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `modify_frameworks` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }
    if (!field_exists_in_table('delete_frameworks', 'user')) {
        echo "Adding new field `delete_frameworks` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `delete_frameworks` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }
    if (!field_exists_in_table('add_new_controls', 'user')) {
        echo "Adding new field `add_new_controls` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `add_new_controls` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }
    if (!field_exists_in_table('modify_controls', 'user')) {
        echo "Adding new field `modify_controls` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `modify_controls` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }
    if (!field_exists_in_table('delete_controls', 'user')) {
        echo "Adding new field `delete_controls` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `delete_controls` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    // Assign new governance roles to the users who currently have the Allow access to Governance  menu field checked
    $stmt = $db->prepare("UPDATE `user` SET add_new_frameworks=1, modify_frameworks=1, delete_frameworks=1, add_new_controls=1, modify_controls=1, delete_controls=1 WHERE governance=1; ");
    $stmt->execute();
    
    // Update technology field from Int type to String
    echo "Updating technology field from Int type to String.<br />\n";
    $stmt = $db->prepare("
        ALTER TABLE `risks` CHANGE `technology` `technology` VARCHAR(500) NOT NULL ; 
    ");
    $stmt->execute();
    
    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20180527-001 *
 ***************************************/
function upgrade_from_20180527001($db){
    // Database version to upgrade
    $version_to_upgrade = '20180527-001';

    // Database version upgrading to
    $version_upgrading_to = '20180627-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20180627-001 *
 ***************************************/
function upgrade_from_20180627001($db){
    // Database version to upgrade
    $version_to_upgrade = '20180627-001';

    // Database version upgrading to
    $version_upgrading_to = '20180812-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Update team field from Int type to String
    echo "Updating team field from Int type to String.<br />\n";
    $stmt = $db->prepare("
        ALTER TABLE `risks` CHANGE `team` `team` VARCHAR(500) NOT NULL DEFAULT '0';
    ");
    $stmt->execute();
    
    // Add new fields, `add_documentation`, `modify_documentation`, `delete_documentation` to user table
    if (!field_exists_in_table('add_documentation', 'user')) {
        echo "Adding a new `add_documentation` permission to the user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `add_documentation` TINYINT NOT NULL DEFAULT '0';");
        $stmt->execute();
    }
    if (!field_exists_in_table('modify_documentation', 'user')) {
        echo "Adding a new `modify_documentation` permission to the user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `modify_documentation` TINYINT NOT NULL DEFAULT '0' AFTER `add_documentation`;");
        $stmt->execute();
    }
    if (!field_exists_in_table('delete_documentation', 'user')) {
        echo "Adding a new `delete_documentation` permission to the user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `delete_documentation` TINYINT NOT NULL DEFAULT '0' AFTER `modify_documentation`;");
        $stmt->execute();
    }

    // Set add/modify/delete documentation responsibilities to users who currently have the Allow access to Governance menu field 
    echo "Setting add/modify/delete documentation responsibilities to users who currently have the Allow access to Governance menu field.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET add_documentation=1, modify_documentation=1, delete_documentation=1 WHERE governance=1; ");
    $stmt->execute();

    // If the file upload settings file exists
    if (file_exists(realpath(__DIR__ . '/../admin/uploads.php')))
    {
        // Delete the file
        echo "Deleting the /admin/uploads.php file as the configuration has been moved to the Settings page.<br />\n";
        unlink(realpath(__DIR__ . '/../admin/uploads.php'));
    }

    // If the mail settings file exists
    if (file_exists(realpath(__DIR__ . '/../admin/mail_settings.php')))
    {
        // Delete the file
        echo "Deleting the /admin/mail_settings.php file as the configuration has been moved to the Settings page.<br />\n";
        unlink(realpath(__DIR__ . '/../admin/mail_settings.php'));
    }

    // Update name field size of settings table
    echo "Updating name field size of settings table.<br />\n";
    $stmt = $db->prepare("
        ALTER TABLE `settings` CHANGE `name` `name` VARCHAR( 100 );
    ");
    $stmt->execute();

    // Create documents table
    echo "Creating documents table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `documents` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `document_type` varchar(50) COLLATE utf8_bin NOT NULL,
          `document_name` text COLLATE utf8_bin NOT NULL,
          `parent` int(11) NOT NULL,
          `status` enum('Draft','InReview','Approved','') COLLATE utf8_bin NOT NULL,
          `file_id` int(11) NOT NULL,
          `creation_date` date NOT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Add version filed to compliance_files table
    if (!field_exists_in_table('version', 'compliance_files')) {
        echo "Adding version field to compliance_files table.<br />\n";
        $stmt = $db->prepare("
            ALTER TABLE `compliance_files` ADD `version` INT NULL DEFAULT NULL;
        ");
        $stmt->execute();
    }
    
    // Set default impact value for invalid impacts
    echo "Setting default impact value for invalid impacts.<br />\n";
    $impact_count = get_impacts_count();
    
    $stmt = $db->prepare("UPDATE `risk_scoring` SET `CLASSIC_impact` = :impact_count WHERE `CLASSIC_impact` > :impact_count ;");
    $stmt->bindParam(":impact_count", $impact_count, PDO::PARAM_INT);
    $stmt->execute();

    // Set default likelihood value for invalid likelihoods
    echo "Setting default likelihood value for invalid likelihood.<br />\n";
    $likelihood_count = get_likelihoods_count();
    
    $stmt = $db->prepare("UPDATE `risk_scoring` SET `CLASSIC_likelihood` = :likelihood_count WHERE `CLASSIC_likelihood` > :likelihood_count ;");
    $stmt->bindParam(":likelihood_count", $likelihood_count, PDO::PARAM_INT);
    $stmt->execute();

    // Update question 144 (disallow unauthorized outbound traffic) to have both Yes and No answers
    echo "Updating question 144 (disallow unauthorized outbound traffic) to have both Yes and No answers.<br />\n";
    $stmt = $db->prepare("SELECT id FROM assessment_questions WHERE question='(1.3.4) Do you disallow unauthorized outbound traffic from the cardholder data environment to the internet?';");
    $stmt->execute();
    
    // Store the ID for the question
    $array = $stmt->fetchAll();

    // Update the question answer to No
    $stmt = $db->prepare("UPDATE assessment_answers SET answer='No' WHERE question_id=:question_id AND submit_risk=1;");
    $stmt->bindParam(":question_id", $array[0]['id'], PDO::PARAM_INT);
    $stmt->execute();

    // Update question 433 (Does the covered entity use or disclose PHI...) to have both Yes and No answers
    echo "Updating question 433 (Does the covered entity use or disclose PHI for the purpose of research, conducts research, provides psychotherapy services, and uses compound authorizations?)<br />\n";
    $stmt = $db->prepare("SELECT id FROM assessment_questions WHERE question='§164.508(b) (3) Does the covered entity use or disclose PHI for the purpose of research, conducts research, provides psychotherapy services, and uses compound authorizations?';");
    $stmt->execute();

    // Store the ID for the question
    $array = $stmt->fetchAll();

    // Update the question answer to No
    $stmt = $db->prepare("UPDATE assessment_answers SET answer='No' WHERE question_id=:question_id AND submit_risk=1;");
    $stmt->bindParam(":question_id", $array[0]['id'], PDO::PARAM_INT);
    $stmt->execute();

    // Remove risks from scoring history that have been deleted
    echo "Removing risks from the risk scoring history that have been deleted.<br />\n";
    $stmt = $db->prepare("DELETE FROM `risk_scoring_history` WHERE risk_id NOT IN (SELECT id FROM `risks` WHERE id is NOT NULL);");
    $stmt->execute();
    echo "Removing risks from the residual risk scoring history that have been deleted.<br />\n";
    $stmt = $db->prepare("DELETE FROM `residual_risk_scoring_history` WHERE risk_id NOT IN (SELECT id FROM `risks` WHERE id is NOT NULL);");
    
    // Update `ASSESSMENT_ASSET_SHOW_AVAILABLE` setting value to 1
    echo "Update `ASSESSMENT_ASSET_SHOW_AVAILABLE` setting value to 1";
    $stmt = $db->prepare("UPDATE `settings` SET `value` = '1' WHERE `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE';");
    $stmt->execute();
    
    // Adding comment permissions to user table
    if (!field_exists_in_table('comment_risk_management', 'user')) {
        echo "Adding new field `comment_risk_management` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `comment_risk_management` TINYINT( 1 ) NOT NULL DEFAULT '0';");
        $stmt->execute();
    }
    if (!field_exists_in_table('comment_compliance', 'user')) {
        echo "Adding new field `comment_compliance` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `comment_compliance` TINYINT( 1 ) NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    // Set existing user permissions to allow comments
    echo "Setting permissions for existing users with risk management access to comment.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET comment_risk_management=1 WHERE riskmanagement=1;");
    $stmt->execute();
    echo "Setting permissions for existing users with compliance access to comment.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET comment_compliance=1 WHERE compliance=1;");
    $stmt->execute();
    
    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20180812-001 *
 ***************************************/
function upgrade_from_20180812001($db){
    // Database version to upgrade
    $version_to_upgrade = '20180812-001';

    // Database version upgrading to
    $version_upgrading_to = '20180814-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20180814-001 *
 ***************************************/
function upgrade_from_20180814001($db){
    // Database version to upgrade
    $version_to_upgrade = '20180814-001';

    // Database version upgrading to
    $version_upgrading_to = '20180830-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20180830-001 *
 ***************************************/
function upgrade_from_20180830001($db){
    // Database version to upgrade
    $version_to_upgrade = '20180830-001';

    // Database version upgrading to
    $version_upgrading_to = '20180916-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Set default date for governance document creation date
    echo "Setting default date for governance document creation date.<br />\n";
    $stmt = $db->prepare("UPDATE `documents` SET `creation_date`='".date("Y-m-d")."' WHERE `creation_date`='0000-00-00'; ");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20180916-001 *
 ***************************************/
function upgrade_from_20180916001($db){
    // Database version to upgrade
    $version_to_upgrade = '20180916-001';

    // Database version upgrading to
    $version_upgrading_to = '20181103-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Set default value for custom_display_settings field in user table
    echo "Setting default value for custom_display_settings field in user table.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `user` CHANGE `custom_display_settings` `custom_display_settings` VARCHAR(1000) NOT NULL DEFAULT ''; ");
    $stmt->execute();

    // Set the name column in the file_types table to be unique
    echo "Setting the name in the file_types table to be a unique value.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `file_types` MODIFY `name` VARCHAR(250) NOT NULL UNIQUE;");
    $stmt->execute();

    // Add a new table to track file type extensions
    echo "Adding a new table to track the upload file type extensions.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `file_type_extensions` (
          `value` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(10) NOT NULL UNIQUE,
          PRIMARY KEY(value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Add acceptable file type extensions to table
    echo "Adding a file type extension for existing file types.<br />\n";
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('gif');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('jpg');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('png');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('jpeg');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('pdf');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('dotx');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('xlsx');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('zip');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('rtf');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('txt');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('xml');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('csv');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('xls');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('doc');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('gz');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('dot');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('xlt');");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES ('xla');");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20181103-001 *
 ***************************************/
function upgrade_from_20181103001($db){
    // Database version to upgrade
    $version_to_upgrade = '20181103-001';

    // Database version upgrading to
    $version_upgrading_to = '20190105-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Create contributing_risks table
    echo "Creating contributing_risks table.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `contributing_risks`(id INT NOT NULL AUTO_INCREMENT, `subject` varchar(1000) NOT NULL, `weight` float NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add default contributing risks
    echo "Adding default contributing risk.<br />\n";
    $stmt = $db->prepare("INSERT INTO contributing_risks(`subject`, weight) VALUES('Safety', 0.25), ('SLA', 0.25), ('Financial', 0.25), ('Reputation', 0.25);");
    $stmt->execute();

    // Add \"Contributing Risk\" value to scoring_methods table
    echo "Adding \"Contributing Risk\" value to scoring_methods table.<br />\n";
    $stmt = $db->prepare("INSERT INTO `scoring_methods` (`value`, `name`) VALUES ('6', 'Contributing Risk'); ");
    $stmt->execute();

    // Create risk_scoring_contributing_impacts table
    echo "Creating risk_scoring_contributing_impacts table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `risk_scoring_contributing_impacts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `risk_scoring_id` int(11) NOT NULL,
          `contributing_risk_id` int(11) NOT NULL,
          `impact` int(11) NOT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    
    // Add a field Contributing_Likelihood to risk_scoring table
    if (!field_exists_in_table('Contributing_Likelihood', 'risk_scoring')) {
        echo "Adding a field Contributing_Likelihood to risk_scoring table.<br />\n";
        $stmt = $db->prepare("
            ALTER TABLE `risk_scoring` ADD `Contributing_Likelihood` INT DEFAULT '0'; 
        ");
        $stmt->execute();
    }

    // Create assessment_scoring_contributing_impacts table
    echo "Creating assessment_scoring_contributing_impacts table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `assessment_scoring_contributing_impacts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `assessment_scoring_id` int(11) NOT NULL,
          `contributing_risk_id` int(11) NOT NULL,
          `impact` int(11) NOT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Add a field Contributing_Likelihood to assessment_scoring table
    if (!field_exists_in_table('Contributing_Likelihood', 'assessment_scoring')) {
        echo "Adding a field Contributing_Likelihood to assessment_scoring table.<br />\n";
        $stmt = $db->prepare("
            ALTER TABLE `assessment_scoring` ADD `Contributing_Likelihood` INT DEFAULT '0'; 
        ");
        $stmt->execute();
    }

    // Delete records for deleted risks from closures table
    echo "Deleting records for deleted risks from closures table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM closures t1 LEFT JOIN risks t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Create new records in closures table for risks to unmatched close_id
    echo "Creating new records in closures table for risks to unmatched close_id.<br />\n";
    $stmt = $db->prepare("
        INSERT INTO `closures` (`id`, `risk_id`, `user_id`, `closure_date`, `close_reason`, `note`)
        SELECT t1.close_id, t1.id, 1, CURRENT_TIMESTAMP, 2, '--'
        FROM risks t1 LEFT JOIN closures t2 ON t1.close_id=t2.id
        WHERE t1.status='Closed' AND t1.close_id>0 AND t2.id IS NULL
        ;
    ");
    $stmt->execute();

    // Create new records in closures table for risks close_id is null
    echo "Creating new records in closures table for risks to unmatched close_id.<br />\n";
    $stmt = $db->prepare("
        INSERT INTO `closures` (`risk_id`, `user_id`, `closure_date`, `close_reason`, `note`)
        SELECT t1.id, 1, CURRENT_TIMESTAMP, 2, '--'
        FROM risks t1 
        WHERE t1.status='Closed' AND (t1.close_id IS NULL OR t1.close_id=0) 
        ;
    ");
    $stmt->execute();

    // Update close_id in risks table for risks close_id is null
    echo "Updating close_id in risks table for risks close_id is null.<br />\n";
    $stmt = $db->prepare("
        UPDATE `risks` t1, `closures` t2, (SELECT risk_id, max(closure_date) closure_date FROM `closures` GROUP BY risk_id) t3 SET t1.close_id=t2.id WHERE t1.id=t2.risk_id and t2.risk_id=t3.risk_id and t2.closure_date=t3.closure_date;
    ");
    $stmt->execute();

    // Delete records for deleted risks from comments table
    echo "Deleting records for deleted risks from comments table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM comments t1 LEFT JOIN risks t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Delete records for deleted risks from files table
    echo "Deleting records for deleted risks from files table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM files t1 LEFT JOIN risks t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Delete records for deleted risks from mgmt_reviews table
    echo "Deleting records for deleted risks from mgmt_reviews table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM mgmt_reviews t1 LEFT JOIN risks t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Delete records for deleted risks from mitigations table
    echo "Deleting records for deleted risks from mitigations table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM mitigations t1 LEFT JOIN risks t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Delete records for deleted risks from risks_to_assets table
    echo "Deleting records for deleted risks from risks_to_assets table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM risks_to_assets t1 LEFT JOIN risks t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Delete records for deleted risks from risk_scoring table
    echo "Deleting records for deleted risks from risk_scoring table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM risk_scoring t1 LEFT JOIN risks t2 ON t1.id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Delete records for deleted risks from risk scoring history table
    echo "Deleting records for deleted risks from risk scoring history table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM risk_scoring_history t1 LEFT JOIN risks t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Delete records for deleted risks from residual risk scoring history table
    echo "Deleting records for deleted risks from residual risk scoring history table.<br />\n";
    $stmt = $db->prepare("
        DELETE t1 FROM residual_risk_scoring_history t1 LEFT JOIN risks t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL; 
    ");
    $stmt->execute();

    // Increase limit of characters for name of Impact table
    echo "Increasing limit of characters for name of Impact table.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `impact` CHANGE `name` `name` VARCHAR(50); ");
    $stmt->execute();

    // Increase limit of characters for name of Likelihood table
    echo "Increasing limit of characters for name of Likelihood table.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `likelihood` CHANGE `name` `name` VARCHAR(50); ");
    $stmt->execute();

    // Add a new field to framework_control_tests table
    if (!field_exists_in_table('additional_stakeholders', 'framework_control_tests')) {
        echo "Adding a new field, `additional_stakeholders` to framework_control_tests table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_control_tests` ADD `additional_stakeholders` VARCHAR( 500 ) NOT NULL after `created_at`;");
        $stmt->execute();
    }

    // Adding `verified` field to the `assets` table
    if (!field_exists_in_table('verified', 'assets')) {
        echo "Adding `verified` field to the `assets` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `assets` ADD `verified` TINYINT NOT NULL DEFAULT 0 AFTER `created`;");
        $stmt->execute();
    }

    // Set the existing assets' `verified` flag to true
    echo "Set the existing assets' `verified` flag to true.<br />\n";
    $stmt = $db->prepare("UPDATE `assets` SET `verified`=1;");
    $stmt->execute();

    // Create new setting for 'Automatically verify new assets' and set to false by default
    echo "Create new setting for 'Automatically verify new assets' and set to false by default.<br />\n";
    $stmt = $db->prepare("INSERT INTO `settings` (`name`, `value`) VALUES ('auto_verify_new_assets', '0');");
    $stmt->execute();

    // Get any assets in risks_to_assets that are not in assets
    $stmt = $db->prepare("SELECT * FROM risks_to_assets WHERE asset_id NOT IN (SELECT id FROM assets);");
    $stmt->execute();
    $assets = $stmt->fetchAll();

    // If there are assets in risks_to_assets that are not in assets
    if (count($assets) > 0)
    {
        // Add the assets as unverified assets
        echo "Moving assets to unverified.<br />\n";

        // Create an array for the risks_to_assets
        $risks_to_assets = array();

        // For each of the assets
        foreach ($assets as $asset)
        {
            // Get the asset values
            $name = $asset['asset'];
            $risk_id = $asset['risk_id'];
            $asset_id = $asset['asset_id'];

            // Delete the entry in the risks_to_assets table
            $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE risk_id = :risk_id AND asset_id = :asset_id");
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
            $stmt->execute();

            // Add the asset as unverified
            $asset_id = add_asset("", $name);

            $risks_to_assets[] = array("risk_id" => $risk_id, "asset_id" => $asset_id);
        }

        // Updating unique key of `risks_to_assets` table to use both `risk_id` and `asset_id`
        echo "Updating unique key of `risks_to_assets` table to use both `risk_id` and `asset_id`.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks_to_assets` DROP INDEX `risk_id`, ADD UNIQUE KEY `risk_id` (`risk_id`,`asset_id`);");
        $stmt->execute();

        // For each risks_to_assets
        foreach ($risks_to_assets as $asset)
        {
            $risk_id = $asset['risk_id'];
            $asset_id = $asset['asset_id'];

            // Add a new entry in the risks_to_assets table
            $stmt = $db->prepare("INSERT INTO `risks_to_assets` (`risk_id`, `asset_id`) VALUES (:risk_id, :asset_id)");
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
    else
    {
        // Updating unique key of `risks_to_assets` table to use both `risk_id` and `asset_id`
        echo "Updating unique key of `risks_to_assets` table to use both `risk_id` and `asset_id`.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks_to_assets` DROP INDEX `risk_id`, ADD UNIQUE KEY `risk_id` (`risk_id`,`asset_id`);");
        $stmt->execute();
    }

    // Check if the asset column exists in the risks_to_assets table
    $stmt = $db->prepare("SHOW COLUMNS FROM `risks_to_assets` LIKE 'asset';");
    $stmt->execute();
    $result = $stmt->fetchAll();

    // If the asset column exists in the risks_to_assets table
    if (count($result) > 0)
    {
        // Drop the asset column in the risks_to_assets table
        echo "Dropping the asset column from the risks_to_assets table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks_to_assets` DROP COLUMN `asset`;");
        $stmt->execute();
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20190105-001 *
 ***************************************/
function upgrade_from_20190105001($db){
    // Database version to upgrade
    $version_to_upgrade = '20190105-001';

    // Database version upgrading to
    $version_upgrading_to = '20190210-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Update mitigation_team field from Int to String
    echo "Updating mitigation_team field from Int to String.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `mitigations` CHANGE `mitigation_team` `mitigation_team` VARCHAR(100) DEFAULT ''; ");
    $stmt->execute();

    // Add `review_date` field to the `documents` table
    if (!field_exists_in_table('review_date', 'documents')) {
        echo "Add `review_date` field to the `documents` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `review_date` date AFTER `creation_date`;");
        $stmt->execute();
    }

    // Remove the ON UPDATE CURRENT_TIMESTAMP from tables
    echo "Removing ON UPDATE CURRENT_TIMESTAMP from closures table.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `closures` CHANGE `closure_date` `closure_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;");
    $stmt->execute();
    echo "Removing ON UPDATE CURRENT_TIMESTAMP from comments table.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `comments` CHANGE `date` `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;");
    $stmt->execute();
    echo "Removing ON UPDATE CURRENT_TIMESTAMP from framework_control_test_comments table.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `framework_control_test_comments` CHANGE `date` `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;");
    $stmt->execute(); 

    /*Changes related to using frameworks instead of the regulations table for
      control regulations*/

    // Rename Regulation "PCI DSS" to "PCI DSS 3.2"
    update_table("regulation", "PCI DSS 3.2", get_value_by_name("regulation", "PCI DSS"));

    // Rename Framework "PCI DSS" to "PCI DSS 3.2"
    if(get_value_by_name("frameworks", "PCI DSS")){
        update_framework(get_value_by_name("frameworks", "PCI DSS"), "PCI DSS 3.2");
    }
    
    // Rename Framework from "Sarbanes-Oxley" to "Sarbanes-Oxley (SOX)"
    if(get_value_by_name("frameworks", "Sarbanes-Oxley")){
        update_framework(get_value_by_name("frameworks", "Sarbanes-Oxley"), "Sarbanes-Oxley (SOX)");
    }

    // Get the list of regulations
    $options = get_table("regulation");

    $mappings = array();

    // Create the regulation_id => framework_id mapping while adding the missing frameworks
    foreach ($options as $option) {
        $id = get_value_by_name("frameworks", $option['name']);
        if (!$id) {
            $id = add_framework($option['name'], "");
        }

        $mappings[$option['value']] = $id;
    }

    // Get the list of risks that have regulations setup
    $stmt = $db->prepare("select ri.id risk_id, ri.regulation from risks ri where ri.regulation is not null and ri.regulation > 0;");
    $stmt->execute();

    // Store the list in the array
    $risks = $stmt->fetchAll();

    // Update risks to point to the new framework ids
    foreach ($risks as $risk) {
        $stmt = $db->prepare("UPDATE risks SET regulation = :regulation WHERE id = :id;");
        $stmt->bindParam(":id", $risk['risk_id'], PDO::PARAM_INT);
        $stmt->bindParam(":regulation", $mappings[$risk['regulation']], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Update framework_controls to be able to allow for more framework_ids
    echo "Updating the framework_controls to be able to allow for more framework_ids.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `framework_controls` MODIFY `framework_ids` VARCHAR(4000);");
    $stmt->execute();

    // Update dates in the 'mgmt_reviews.next_review' column thats format isn't
    // 'Y-m-d'. If we successfully updated every date or there were none to start with
    // we update the column's type from 'varchar(10)' to 'date'
    require_once(realpath(__DIR__ . '/datefix.php'));
    if (getTypeOfColumn('mgmt_reviews', 'next_review') == 'varchar') {
        echo "Updating reviews where the `next_review` date is not in the proper format.<br />\n";
        $count = count($reviews = getAllReviewsWithDateIssues());

        if ($count) {
            foreach ($reviews as $review) {
                $date = $review['next_review'];
                $pf = possibleFormats($date);

                if (count($pf) == 0) { //Not a date
                    resetNextReviewDate($review['review_id']);
                    $count -= 1;
                } elseif (count($pf) == 1 && fixNextReviewDateFormat($review['review_id'], $pf[0])) { //save the date
                    $count -= 1;
                }
            }
        }

        // Only re-count if we have to, but do it to make sure
        if (!$count && !count(getAllReviewsWithDateIssues())) {
            // Change `next_review` column to date type
            if (changeNextReviewToDateType()) {
                echo "Successfully fixed all review date issues!<br />\n";
            }
        }
    }

    if (!get_setting('simplerisk_base_url') && isset($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER)) {
        echo "Setting the default value for the SimpleRisk Base URL.<br />\n";

        $url = get_current_url();

        // Remove the admin path from the URL
        $url = preg_replace('/\/admin\/.*/', '', $url);

        add_setting('simplerisk_base_url', $url);
    }

    //Setting the default value for the alert timeout
    if (!get_setting("alert_timeout")) {
        echo "Setting the default value for the alert timeout.<br />\n";
        //Indicate that it should use the default settings
        add_setting('alert_timeout', '5');
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20190210-001 *
 ***************************************/
function upgrade_from_20190210001($db){
    // Database version to upgrade
    $version_to_upgrade = '20190210-001';

    // Database version upgrading to
    $version_upgrading_to = '20190331-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Only create and add the values if the table doesn't exist yet
    // to make sure we only insert values once
    if (!table_exists('test_results')) {
        // Creating the test_results table.
        echo "Creating the test_results table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `test_results` (
              `value` INT(11) NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(20) NOT NULL,
              `background_class` VARCHAR(100) NOT NULL,
              PRIMARY KEY(value),
              CONSTRAINT `name_unique` UNIQUE (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();

        echo "Adding possible test results to the test results table.<br />\n";
        $stmt = $db->prepare("INSERT INTO `test_results` (`name`, `background_class`) VALUES ('Pass', 'green-background'), ('Inconclusive', 'white-background'), ('Fail', 'red-background');");
        $stmt->execute();
    } else echo "The test_results table already exists.<br />\n";

    // Creating the tags table.
    echo "Creating the tags table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `tags` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tag` VARCHAR(50) NOT NULL,
            PRIMARY KEY(`id`),
            CONSTRAINT `tag_unique` UNIQUE (`tag`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Creating the tags_taggees table.
    echo "Creating the tags_taggees table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `tags_taggees` (
            `tag_id` INT(11) NOT NULL,
            `taggee_id` INT(11) NOT NULL,
            `type` VARCHAR(20) NOT NULL,
            CONSTRAINT `tag_taggee_unique` UNIQUE (`tag_id`, `taggee_id`, `type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Change the `framework_controls`.`control_number` field's type to 'varchar'.
    echo "Change the `framework_controls`.`control_number` field's type to 'varchar'.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `framework_controls` CHANGE `control_number` `control_number` varchar(100);");
    $stmt->execute();

    // Create a setting for email prepend
    echo "Setting the default email prepend.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (name, value) VALUES ('phpmailer_prepend', '[SIMPLERISK]');");
    $stmt->execute();

    // Removing the unneeded `SIMPLERISK_URL` setting
    echo "Removing the unneeded `SIMPLERISK_URL` setting<br />\n";
    delete_setting('SIMPLERISK_URL');

    // Adding default setting for Risk Appetite if needed
    if (!get_setting('risk_appetite')) {
        echo "Adding default setting for Risk Appetite<br />\n";
        add_setting("risk_appetite", 0);
    }

    // Creating the document_exceptions table.
    echo "Creating the document_exceptions table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `document_exceptions` (
            `value` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `policy_document_id` INT(11),
            `control_framework_id` INT(11),
            `owner` INT(11),
            `additional_stakeholders` VARCHAR(500) NOT NULL,
            `creation_date` DATE NOT NULL DEFAULT '0000-00-00',
            `review_frequency` int(11) NOT NULL DEFAULT '0',
            `next_review_date` DATE NOT NULL DEFAULT '0000-00-00',
            `approval_date` DATE NOT NULL DEFAULT '0000-00-00',
            `approver` INT(11),
            `approved` tinyint(1) NOT NULL DEFAULT '0',
            `description` blob NOT NULL,
            `justification` blob NOT NULL,
            PRIMARY KEY(value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Adding Document Exception management related permissions to `user` table
    echo "Adding Document Exception management related permissions to `user` table.<br />\n";
    if (!field_exists_in_table('view_exception', 'user')) {
        $stmt = $db->prepare("ALTER TABLE `user` ADD `view_exception` TINYINT(1) NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    if (!field_exists_in_table('create_exception', 'user')) {
        $stmt = $db->prepare("ALTER TABLE `user` ADD `create_exception` TINYINT(1) NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    if (!field_exists_in_table('update_exception', 'user')) {
        $stmt = $db->prepare("ALTER TABLE `user` ADD `update_exception` TINYINT(1) NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    if (!field_exists_in_table('delete_exception', 'user')) {
        $stmt = $db->prepare("ALTER TABLE `user` ADD `delete_exception` TINYINT(1) NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    if (!field_exists_in_table('approve_exception', 'user')) {
        $stmt = $db->prepare("ALTER TABLE `user` ADD `approve_exception` TINYINT(1) NOT NULL DEFAULT '0';");
        $stmt->execute();
    }

    // Assign administrator role to all users with access to configure menu.
    $stmt = $db->prepare("
        UPDATE `user`
        SET `view_exception` = 1,
            `create_exception` = 1,
            `update_exception` = 1,
            `delete_exception` = 1,
            `approve_exception` = 1
        WHERE admin=1;
    ");

    $stmt->execute();

    // Delete Unassinged Risks project
    echo "Deleting \"Unassinged Risks\" project.<br />\n";
    $unassigned_risks_project_name = "Unassigned Risks";
    $unassigned_risks_project_id = 0;
    $projects = get_projects();
    foreach($projects as $project){
        if($project['name'] == $unassigned_risks_project_name){
            $unassigned_risks_project_id = $project['value'];
            break;
        }
    }
    $stmt = $db->prepare("DELETE FROM `projects` WHERE value=:value; ");
    $stmt->bindParam(":value", $unassigned_risks_project_id);
    $stmt->execute();
    
    // Adding new field `control_ids` to `documents` table.
    if (!field_exists_in_table('control_ids', 'documents')) {
        echo "Adding new field `control_ids` to `documents` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `control_ids` VARCHAR(500) NOT NULL;");
        $stmt->execute();
    }

    // Adding new field `framework_ids` to `documents` table.
    if (!field_exists_in_table('framework_ids', 'documents')) {
        echo "Adding new field `framework_ids` to `documents` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `framework_ids` VARCHAR(500) NOT NULL;");
        $stmt->execute();
    }

    // Adding new field `valuation_level_name` to `asset_values` table
    if (!field_exists_in_table('valuation_level_name', 'asset_values')) {
        echo "Adding new field `valuation_level_name` to `asset_values` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `asset_values` ADD `valuation_level_name` VARCHAR(100) NOT NULL;");
        $stmt->execute();
    }

    echo "Setting the default value for the SimpleRisk Base URL.<br />\n";
    $url = get_current_url();
    // Remove the admin path from the URL
    $url = preg_replace('/\/admin\/.*/', '', $url);
    add_setting('simplerisk_base_url', $url);

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20190331-001 *
 ***************************************/
function upgrade_from_20190331001($db){
    // Database version to upgrade
    $version_to_upgrade = '20190331-001';

    // Database version upgrading to
    $version_upgrading_to = '20190630-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Creating the asset_groups table.
    if (!table_exists('asset_groups')) {
        echo "Creating the asset_groups table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `asset_groups` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT `name_unique` UNIQUE (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    // Creating the assets_asset_groups table.
    if (!table_exists('assets_asset_groups')) {
        echo "Creating the assets_asset_groups table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `assets_asset_groups` (
                `asset_id` INT(11) NOT NULL,
                `asset_group_id` INT(11) NOT NULL,
                CONSTRAINT `asset_asset_group_unique` UNIQUE (`asset_id`, `asset_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    if (!table_exists('risks_to_asset_groups')) {
        echo "Creating the risks_to_asset_groups table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `risks_to_asset_groups` (
                `risk_id` INT(11) NOT NULL,
                `asset_group_id` INT(11) NOT NULL,
                CONSTRAINT `risk_asset_group_unique` UNIQUE (`risk_id`, `asset_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    // Add support for the Mongolian language
    if (!get_value_by_name('languages', 'mn')) {
        echo "Add support for the Mongolian language.<br />\n";
        $stmt = $db->prepare("INSERT INTO languages (`name`, `full`) VALUES ('mn', 'Mongolian');");
        $stmt->execute();
    }

    if (!field_exists_in_table('manager', 'user')) {
        // Add manager field to user table
        echo "Adding manager field to user table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `manager` INT NULL; ");
        $stmt->execute();
    }

    if (!table_exists('assessment_answers_to_assets')) {
        echo "Creating the assessment_answers_to_assets table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `assessment_answers_to_assets` (
                `assessment_answer_id` INT(11) NOT NULL,
                `asset_id` INT(11) NOT NULL,
                CONSTRAINT `assessment_answer_asset_unique` UNIQUE (`assessment_answer_id`, `asset_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    if (!table_exists('assessment_answers_to_asset_groups')) {
        echo "Creating the assessment_answers_to_asset_groups table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `assessment_answers_to_asset_groups` (
                `assessment_answer_id` INT(11) NOT NULL,
                `asset_group_id` INT(11) NOT NULL,
                CONSTRAINT `assessment_answer_asset_group_unique` UNIQUE (`assessment_answer_id`, `asset_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }
    
    if (field_exists_in_table('assets', 'assessment_answers')
        && table_exists('assessment_answers_to_asset_groups')
        && table_exists('assessment_answers_to_assets')) {

        // Get any answers that have assets setup        
        $stmt = $db->prepare("SELECT id, assets FROM assessment_answers WHERE TRIM(assets) != '' AND assets IS NOT NULL;");
        $stmt->execute();
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($answers) {
            echo "Migrating Assessment Answers to to the new database structure.<br />\n";

            // Iterate through the answers
            foreach($answers as $answer) {

                $answer_id = $answer['id'];
                $asset_names = explode(',', $answer['assets']);

                // Iterate through the asset names
                foreach($asset_names as $asset_name) {

                    if (!$asset_name)
                        continue;

                    // Get the asset id if it exists
                    $asset_id = asset_exists($asset_name);

                    // If it doesn't yet
                    if (!$asset_id)
                        // Then create it
                        $asset_id = add_asset_by_name_with_forced_verification($asset_name, true);

                    if (!$asset_id)
                        continue;

                    // Add the new asset for this assessment answer
                    $stmt = $db->prepare("INSERT INTO `assessment_answers_to_assets` (`assessment_answer_id`, `asset_id`) VALUES (:assessment_answer_id, :asset_id)");
                    $stmt->bindParam(":assessment_answer_id", $answer_id, PDO::PARAM_INT);
                    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }

        // Drop the assets column of the assessment_answers table
        echo "Dropping the assets column of the assessment_answers table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `assessment_answers` DROP COLUMN `assets`;");
        $stmt->execute();
    }

    // Updated `pending_risks` table's `asset` field to text type
    if (getTypeOfColumn('pending_risks', 'asset') == 'varchar') {
        echo "Updated `pending_risks` table's `asset` field to text type.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `pending_risks` CHANGE `asset` `affected_assets` TEXT;");
        $stmt->execute();
    }

    // Creating the items_to_teams table.
    if (!table_exists('items_to_teams')) {
        echo "Creating the `items_to_teams` table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `items_to_teams` (
                `item_id` INT(11) NOT NULL,
                `team_id` INT(11) NOT NULL,
                `type` VARCHAR(20) NOT NULL,
                CONSTRAINT `item_team_unique` UNIQUE (`item_id`, `team_id`, `type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    // Add a new risk model to the `risk_models` table
    if (get_name_by_value('risk_models', 6, false) === false) {
        echo "Adding a new risk model (called 'Custom') to the `risk_models` table.<br />\n";
        $stmt = $db->prepare("INSERT INTO `risk_models` (`value`, `name`) VALUES (6, 'Custom');");
        $stmt->execute();
    }

    // Creating the custom_risk_model_values table.
    if (!table_exists('custom_risk_model_values')) {
        echo "Creating the `custom_risk_model_values` table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `custom_risk_model_values` (
                `impact` INT(11) NOT NULL,
                `likelihood` INT(11) NOT NULL,
                `value` DOUBLE(3,1) NOT NULL,
                CONSTRAINT `impact_likelihood_unique` UNIQUE (`impact`, `likelihood`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
        
        echo "Pre-populating the `custom_risk_model_values` table based on the currently selected 'Risk Model'.<br />\n";
        $GLOBALS['count_of_impacts']        = $count_of_impacts     = count(get_table("impact"));
        $GLOBALS['count_of_likelihoods']    = $count_of_likelihoods = count(get_table("likelihood"));

        foreach (range(1, $count_of_impacts) as $impact) {
            foreach (range(1, $count_of_likelihoods) as $likelihood) {
                set_stored_risk_score($impact, $likelihood, calculate_risk($impact, $likelihood));
            }
        }
    }

    // If the SimpleRisk instance is registered
    if (get_setting('registration_registered') != 0)
    {
        // Get the current registration values
        $name = get_setting('registration_name');
        $company = get_setting('registration_company');
        $title = get_setting('registration_title');
        $phone = get_setting('registration_phone');
        $email = get_setting('registration_email');

        // Split the name into two parts using the first space
        $array = explode(' ', $name, 2);
        $fname = (isset($array[0]) ? $array[0] : "");
	$lname = (isset($array[1]) ? $array[1] : "");

	// Add the new first and last name settings
	add_setting("registration_fname", $fname);
	add_setting("registration_lname", $lname);

        update_registration($name="", $company="", $title="", $phone="", $email="", $fname="", $lname="");
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/******************************
 * FUNCTION: UPGRADE DATABASE *
 ******************************/
function upgrade_database()
{
    // Connect to the database
    $db = db_open();

    // If the grant check for the database user is successful
    if (check_grants($db))
    {
        // Get the current database version
        $db_version = current_version("db");

        // Run the upgrade for the appropriate current version
        switch ($db_version)
        {
            case "20140728-001":
                upgrade_from_20140728001($db);
                upgrade_database();
                break;
            case "20141013-001":
                upgrade_from_20141013001($db);
                upgrade_database();
                break;
            case "20141129-001":
                upgrade_from_20141129001($db);
                upgrade_database();
                break;
            case "20141214-001":
                upgrade_from_20141214001($db);
                upgrade_database();
                break;
            case "20150202-001":
                upgrade_from_20150202001($db);
                upgrade_database();
                break;
            case "20150321-001":
                upgrade_from_20150321001($db);
                upgrade_database();
                break;
            case "20150531-001":
                upgrade_from_20150531001($db);
                upgrade_database();
                break;
            case "20150729-001":
                upgrade_from_20150729001($db);
                upgrade_database();
                break;
            case "20150920-001":
                upgrade_from_20150920001($db);
                upgrade_database();
                break;
            case "20150928-001":
                upgrade_from_20150928001($db);
                upgrade_database();
                break;
            case "20150930-001":
                upgrade_from_20150930001($db);
                upgrade_database();
                break;
            case "20151108-001":
                upgrade_from_20151108001($db);
                upgrade_database();
                break;
            case "20151219-001":
                upgrade_from_20151219001($db);
                upgrade_database();
                break;
            case "20160124-001":
                upgrade_from_20160124001($db);
                upgrade_database();
                break;
            case "20160331-001":
                upgrade_from_20160331001($db);
                upgrade_database();
                break;
            case "20160612-001":
                upgrade_from_20160612001($db);
                upgrade_database();
                break;
            case "20161023-001":
                upgrade_from_20161023001($db);
                upgrade_database();
                break;
            case "20161030-001":
                upgrade_from_20161030001($db);
                upgrade_database();
                break;
            case "20161122-001":
                upgrade_from_20161122001($db);
                upgrade_database();
                break;
            case "20170102-001":
                upgrade_from_20170102001($db);
                upgrade_database();
                break;
            case "20170108-001":
                upgrade_from_20170108001($db);
                upgrade_database();
                break;
            case "20170312-001":
                upgrade_from_20170312001($db);
                upgrade_database();
                break;
            case "20170416-001":
                upgrade_from_20170416001($db);
                upgrade_database();
                break;
            case "20170614-001":
                upgrade_from_20170614001($db);
                upgrade_database();
                break;
            case "20170723-001":
                upgrade_from_20170723001($db);
                upgrade_database();
                break;
            case "20170724-001":
                upgrade_from_20170724001($db);
                upgrade_database();
                break;
            case "20180104-001":
                upgrade_from_20180104001($db);
                upgrade_database();
                break;
            case "20180301-001":
                upgrade_from_20180301001($db);
                upgrade_database();
                break;
            case "20180527-001":
                upgrade_from_20180527001($db);
                upgrade_database();
                break;
            case "20180627-001":
                upgrade_from_20180627001($db);
                upgrade_database();
                break;
            case "20180812-001":
                upgrade_from_20180812001($db);
                upgrade_database();
                break;
            case "20180814-001":
                upgrade_from_20180814001($db);
                upgrade_database();
                break;
            case "20180830-001":
                upgrade_from_20180830001($db);
                upgrade_database();
                break;
            case "20180916-001":
                upgrade_from_20180916001($db);
                upgrade_database();
                break;
            case "20181103-001":
                upgrade_from_20181103001($db);
                upgrade_database();
                break;
            case "20190105-001":
                upgrade_from_20190105001($db);
                upgrade_database();
                break;
            case "20190210-001":
                upgrade_from_20190210001($db);
                upgrade_database();
                break;
            case "20190331-001":
                upgrade_from_20190331001($db);
                upgrade_database();
                break;                
            default:
                echo "You are currently running the version of the SimpleRisk database that goes along with your application version.<br />\n";
        }
    }
    // If the grant check was not successful
    else
    {
        echo "A check of your database user privileges found that one of the necessary grants was missing.  Please ensure that you have granted SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, and ALTER permissions to the user.<br />\n";
    }

    // Disconnect from the database
    db_close($db);
}

/*****************************************
 * FUNCTION: DISPLAY CACHE CLEAR WARNING *
 *****************************************/
function display_cache_clear_warning()
{
	global $lang;
	global $escaper;

	echo "<image src=\"../images/exclamation_warning.png\" width=\"30\" height=\"30\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['CacheClearWarning']);
}

?>
