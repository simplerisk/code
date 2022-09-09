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
require_once(realpath(__DIR__ . '/permissions.php'));

// Include the language file
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// These are here to make sure they're available when upgrading
if (!function_exists('index_exists_on_table')) {
    function index_exists_on_table($index_name, $table) {

        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("SHOW INDEX FROM `{$table}` WHERE `Key_name` = '{$index_name}';");
        $stmt->execute();

        // Fetch the results
        $results = $stmt->rowCount();

        // Close the database connection
        db_close($db);

        return $results;
    }
}

if (!function_exists('field_exists_in_table')) {
    function field_exists_in_table($field, $table) {

        // Open the database connection
        $db = db_open();

        // Query the field of the table
        $stmt = $db->prepare("
            SELECT
                1
            FROM
                information_schema.columns
            WHERE
                table_schema = :database
                AND table_name = :table
                AND column_name = :field;
        ");
        $database = DB_DATABASE; //Have to make a variable as bindParam can't take parameter by reference
        $stmt->bindParam(":database", $database, PDO::PARAM_STR);
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->bindParam(":field", $field, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the results
        $results = $stmt->rowCount();

        // Close the database connection
        db_close($db);

        return $results;
    }
}

if (!function_exists('table_exists')) {
    function table_exists($table) {

        // Open the database connection
        $db = db_open();

        // Query the schema for the table
        $database = DB_DATABASE; //Have to make a variable as bindParam can't take parameter by reference
        $stmt = $db->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = :database AND table_name = :table;");
        $stmt->bindParam(":database", $database, PDO::PARAM_STR);
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the results
        $results = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return count($results) > 0;
    }
}

global $releases;
$releases = array(
	"20140728-001",
	"20141013-001",
	"20141129-001",
	"20141214-001",
	"20150202-001",
	"20150321-001",
	"20150531-001",
	"20150729-001",
	"20150920-001",
	"20150928-001",
	"20150930-001",
	"20151108-001",
	"20151219-001",
	"20160124-001",
	"20160331-001",
	"20160612-001",
	"20161023-001",
	"20161030-001",
	"20161122-001",
	"20170102-001",
	"20170108-001",
	"20170312-001",
	"20170416-001",
	"20170614-001",
	"20170723-001",
	"20170724-001",
	"20180104-001",
	"20180301-001",
	"20180527-001",
	"20180627-001",
	"20180812-001",
	"20180814-001",
	"20180830-001",
	"20180916-001",
	"20181103-001",
	"20190105-001",
	"20190210-001",
	"20190331-001",
	"20190630-001",
	"20190930-001",
	"20191130-001",
	"20200328-001",
	"20200401-001",
	"20200711-001",
	"20201005-001",
	"20201106-001",
	"20201123-001",
	"20210121-001",
	"20210305-001",
	"20210625-001",
	"20210630-001",
	"20210713-001",
	"20210802-001",
	"20210806-001",
	"20210930-001",
	"20211010-001",
	"20211027-001",
	"20211115-001",
	"20211230-001",
	"20220122-001",
	"20220306-001",
	"20220401-001",
	"20220527-001",
	"20220701-001",
	"20220823-001",
	"20220909-001",
);

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
function check_valid_key($key) {
    
    $db_api_key = get_api_key();
    //If the key is set and correct
    if ($db_api_key && $key == $db_api_key) {
        return true;
    }
    
    return false;
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
    $stmt = $db->prepare("SELECT `table_name` AS table_name FROM `information_schema`.`tables` WHERE `table_schema` = '" . DB_DATABASE . "' AND `ENGINE` != 'InnoDB';");
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
    $stmt = $db->prepare("SELECT `table_name` as table_name FROM `information_schema`.`tables` WHERE `table_schema` = '" . DB_DATABASE . "' AND `TABLE_COLLATION` != 'utf8_general_ci';");
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
    $references = false;
    $index = false;
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

        // Match REFERENCES statement
        $regex_pattern = "/REFERENCES/";
        if (preg_match($regex_pattern, $string))
        {
            $references = true;
        }

        // Match INDEX statement
        $regex_pattern = "/INDEX/";
        if (preg_match($regex_pattern, $string))
        {
            $index = true;
        }

        // Match ALL statement
        $regex_pattern = "/ALL/";
        if (preg_match($regex_pattern, $string))
        {
            $all = true;
        }
    }

    // If the grants include all values
    if ($select && $insert && $update && $delete && $create && $drop && $alter && $references && $index)
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
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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

    if (field_exists_in_table('team', 'assets')) {
        echo "Updating `team` field in `assets` table to string type.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `assets` CHANGE `team` `teams` VARCHAR(1000) NULL;  ");
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

/***************************************
 * FUNCTION: UPGRADE FROM 20190630-001 *
 ***************************************/
function upgrade_from_20190630001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20190630-001';

    // Database version upgrading to
    $version_upgrading_to = '20190930-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    echo "Creating dynamic_saved_selections table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `dynamic_saved_selections` (
          `value` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `type` enum('private','public') NOT NULL,
          `name` varchar(100) NOT NULL,
          `custom_display_settings` varchar(1000) DEFAULT NULL,
          PRIMARY KEY(value)
        )  ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    echo "Updating location field type to string.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `risks` CHANGE `location` `location` VARCHAR(500) NULL; ");
    $stmt->execute();

    echo "Updating unassigned values from 0 to empty string for location field.<br />\n";
    $stmt = $db->prepare("UPDATE `risks` SET location='' WHERE location='0' or location IS NULL; ");
    $stmt->execute();

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20190930-001 *
 ***************************************/
function upgrade_from_20190930001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20190930-001';

    // Database version upgrading to
    $version_upgrading_to = '20191130-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    if (field_exists_in_table('team', 'assets')) {
        echo "Updating `team` field in `assets` table to string type.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `assets` CHANGE `team` `teams` VARCHAR(1000) NULL;  ");
        $stmt->execute();
    }

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20191130-001 *
 ***************************************/
function upgrade_from_20191130001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20191130-001';

    // Database version upgrading to
    $version_upgrading_to = '20200328-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Creating junction table for mitigations <-> framework_controls and doing the migration
    if (field_exists_in_table('mitigation_controls', 'mitigations')) {
        if (!table_exists('mitigation_to_controls')) {
            echo "Creating mitigation_to_controls table.<br />\n";
            $stmt = $db->prepare("
                CREATE TABLE IF NOT EXISTS `mitigation_to_controls` (
                    `mitigation_id` int(11) NOT NULL,
                    `control_id` int(11) NOT NULL,
                    PRIMARY KEY(`mitigation_id`, `control_id`),
                    INDEX(`control_id`, `mitigation_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");
            $stmt->execute();
        }

        echo "Migrating mitigation controls to new table.<br />\n";
        $stmt = $db->prepare("
            SELECT DISTINCT m.id mitigation_id, fc.id control_id FROM mitigations m, framework_controls fc WHERE FIND_IN_SET(fc.id, m.mitigation_controls);
        ");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        foreach($array as $mitigation_id => $controls) {
            $sql = "INSERT INTO `mitigation_to_controls`(mitigation_id, control_id) values";
            foreach($controls as $control) {
                $sql .= "('{$mitigation_id}', '{$control['control_id']}'),";
            }
            $sql = trim($sql, ",");
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }
        echo "Deleting `mitigation_controls` field from the `mitigations table`.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigations` DROP `mitigation_controls`;");
        $stmt->execute();
    }

    // Creating junction table for framework_controls <-> frameworks associations and doing the migration
    if (field_exists_in_table('framework_ids', 'framework_controls')) {
        if (!table_exists('framework_control_to_framework')) {
            echo "Creating `framework_control_to_framework` table.<br />\n";
            $stmt = $db->prepare("
                CREATE TABLE IF NOT EXISTS `framework_control_to_framework` (
                    `control_id` int(11) NOT NULL,
                    `framework_id` int(11) NOT NULL,
                    PRIMARY KEY(`control_id`, `framework_id`),
                    INDEX(`framework_id`, `control_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");
            $stmt->execute();
        }

        echo "Migrating framework_ids field in framework_controls table to new table.<br />\n";
        $stmt = $db->prepare("
            SELECT DISTINCT t1.id control_id, t2.value framework_id FROM `framework_controls` t1, frameworks t2 WHERE FIND_IN_SET(t2.value, t1.framework_ids);
        ");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        foreach($array as $control_id => $frameworks) {
            $sql = "INSERT INTO `framework_control_to_framework`(control_id, framework_id) values";
            foreach($frameworks as $framework) {
                $sql .= "('{$control_id}', '{$framework['framework_id']}'),";
            }
            $sql = trim($sql, ",");
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }
        echo "Deleting `framework_ids` field from the `framework_controls` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` DROP `framework_ids`;");
        $stmt->execute();
    }

    // Creating junction table for risk <-> location associations and doing the migration
    if (field_exists_in_table('location', 'risks')) {
        if (!table_exists('risk_to_location')) {
            echo "Creating `risk_to_location` table.<br />\n";
            $stmt = $db->prepare("
                CREATE TABLE IF NOT EXISTS `risk_to_location` (
                    `risk_id` int(11) NOT NULL,
                    `location_id` int(11) NOT NULL,
                    PRIMARY KEY(`risk_id`, `location_id`),
                    INDEX(`location_id`, `risk_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");
            $stmt->execute();
        }

        echo "Migrating location field in risks table to new table.<br />\n";
        $stmt = $db->prepare("
            SELECT DISTINCT t1.id risk_id, t2.value location_id FROM `risks` t1, `location` t2 WHERE FIND_IN_SET(t2.value, t1.location);
        ");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        foreach($array as $risk_id => $locations) {
            $sql = "REPLACE INTO `risk_to_location`(risk_id, location_id) values";
            foreach($locations as $location) {
                $sql .= "('{$risk_id}', '{$location['location_id']}'),";
            }
            $sql = trim($sql, ",");
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }

        echo "Deleting `location` field from the `risks` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` DROP `location`; ");
        $stmt->execute();
    }

    // Creating junction table for risk <-> team associations and doing the migration
    if (field_exists_in_table('team', 'risks')) {
        if (!table_exists('risk_to_team')) {
            echo "Creating `risk_to_team` table.<br />\n";
            $stmt = $db->prepare("
                CREATE TABLE IF NOT EXISTS `risk_to_team` (
                    `risk_id` int(11) NOT NULL,
                    `team_id` int(11) NOT NULL,
                    PRIMARY KEY(`risk_id`, `team_id`),
                    INDEX(`team_id`, `risk_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");
            $stmt->execute();
        }

        echo "Migrating team field in risks table to new table.<br />\n";
        $stmt = $db->prepare("
            SELECT DISTINCT t1.id risk_id, t2.value team_id FROM `risks` t1, `team` t2 WHERE FIND_IN_SET(t2.value, t1.team);
        ");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        foreach($array as $risk_id => $teams) {
            $sql = "INSERT INTO `risk_to_team`(risk_id, team_id) values";
            foreach($teams as $team) {
                $sql .= "('{$risk_id}', '{$team['team_id']}'),";
            }
            $sql = trim($sql, ",");
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }

        echo "Deleting `team` field from the `risks` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` DROP `team`; ");
        $stmt->execute();
    }

    // Creating junction table for risk <-> technology associations and doing the migration
    if (field_exists_in_table('technology', 'risks')) {
        if (!table_exists('risk_to_technology')) {
            echo "Creating `risk_to_technology` table.<br />\n";
            $stmt = $db->prepare("
                CREATE TABLE IF NOT EXISTS `risk_to_technology` (
                    `risk_id` int(11) NOT NULL,
                    `technology_id` int(11) NOT NULL,
                    PRIMARY KEY(`risk_id`, `technology_id`),
                    INDEX(`technology_id`, `risk_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");
            $stmt->execute();
        }

        echo "Migrating technology field in risks table to new table.<br />\n";
        $stmt = $db->prepare("
            SELECT DISTINCT t1.id risk_id, t2.value technology_id FROM `risks` t1, `technology` t2 WHERE FIND_IN_SET(t2.value, t1.technology);
        ");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        foreach($array as $risk_id => $technologies) {
            $sql = "INSERT INTO `risk_to_technology`(risk_id, technology_id) values";
            foreach($technologies as $technology) {
                $sql .= "('{$risk_id}', '{$technology['technology_id']}'),";
            }
            $sql = trim($sql, ",");
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }
        
        echo "Deleting `technology` field from the `risks` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` DROP `technology`; ");
        $stmt->execute();
    }

    // Creating junction table for risk <-> additional stakeholder(i.e. user) associations and doing the migration
    if (field_exists_in_table('additional_stakeholders', 'risks')) {
        if (!table_exists('risk_to_additional_stakeholder')) {
            echo "Creating `risk_to_additional_stakeholder` table.<br />\n";
            $stmt = $db->prepare("
                CREATE TABLE IF NOT EXISTS `risk_to_additional_stakeholder` (
                    `risk_id` int(11) NOT NULL,
                    `user_id` int(11) NOT NULL,
                    PRIMARY KEY(`risk_id`, `user_id`),
                    INDEX(`user_id`, `risk_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");
            $stmt->execute();
        }

        echo "Migrating additional_stakeholders field in risks table to new table. <br />\n";
        $stmt = $db->prepare("
            SELECT DISTINCT t1.id risk_id, t2.value user_id FROM `risks` t1, `user` t2 WHERE FIND_IN_SET(t2.value, t1.additional_stakeholders);
        ");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        foreach($array as $risk_id => $additional_stakeholders) {
            $sql = "INSERT INTO `risk_to_additional_stakeholder`(risk_id, user_id) values";
            foreach($additional_stakeholders as $additional_stakeholder) {
                $sql .= "('{$risk_id}', '{$additional_stakeholder['user_id']}'),";
            }
            $sql = trim($sql, ",");
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }

        echo "Deleting `additional_stakeholders` field from the `risks` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` DROP `additional_stakeholders`; ");
        $stmt->execute();
    }

    // Creating junction table for mitigation <-> team associations and doing the migration
    if (field_exists_in_table('mitigation_team', 'mitigations')) {
        if (!table_exists('mitigation_to_team')) {
            echo "Creating `mitigation_to_team` table.<br />\n";
            $stmt = $db->prepare("
                CREATE TABLE IF NOT EXISTS `mitigation_to_team` (
                    `mitigation_id` int(11) NOT NULL,
                    `team_id` int(11) NOT NULL,
                    PRIMARY KEY(`mitigation_id`, `team_id`),
                    INDEX(`team_id`, `mitigation_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");
            $stmt->execute();
        }

        echo "Migrating mitigation_team field in mitigations table to new table<br />\n";
        $stmt = $db->prepare("
            SELECT DISTINCT t1.id mitigation_id, t2.value team_id FROM `mitigations` t1, `team` t2 WHERE FIND_IN_SET(t2.value, t1.mitigation_team);
        ");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        foreach($array as $mitigation_id => $teams) {
            $sql = "INSERT INTO `mitigation_to_team`(mitigation_id, team_id) values";
            foreach($teams as $team) {
                $sql .= "('{$mitigation_id}', '{$team['team_id']}'),";
            }
            $sql = trim($sql, ",");
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }

        echo "Deleting `mitigation_team` field from the `mitigations` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigations` DROP `mitigation_team`; ");
        $stmt->execute();
    }
    
    // Creating junction table for user <-> team associations and doing the migration
    if (field_exists_in_table('teams', 'user')) {

        if (!table_exists('user_to_team')) {
            echo "Creating `user_to_team` table.<br />\n";
            $stmt = $db->prepare("
                CREATE TABLE IF NOT EXISTS `user_to_team` (
                    `user_id` int(11) NOT NULL,
                    `team_id` int(11) NOT NULL,
                    PRIMARY KEY(`user_id`, `team_id`),
                    INDEX(`team_id`, `user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ");
            $stmt->execute();
        }

        echo "Migrating teams field in user table to a new table<br />\n";
        $stmt = $db->prepare("
            SELECT
                DISTINCT u.value as user_id, t.value as team_id
            FROM
                `user` u, `team` t
            WHERE
                FIND_IN_SET(t.value, replace(u.teams, ':', ',')) OR u.teams = 'all' or u.admin=1;
        ");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

        foreach($array as $user_id => $teams) {
            $sql = "REPLACE INTO `user_to_team`(user_id, team_id) values";
            foreach($teams as $team) {
                $sql .= "('{$user_id}', '{$team['team_id']}'),";
            }
            $sql = trim($sql, ",");
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }

        echo "Deleting `teams` field from the `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` DROP `teams`;");
        $stmt->execute();
    }

    if (!index_exists_on_table('taggee_type', 'tags_taggees')) {
        echo "Adding index 'taggee_type' to table 'tags_taggees'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `tags_taggees` ADD INDEX `taggee_type` (`taggee_id`, `type`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('risk_id', 'mitigations')) {
        echo "Adding index 'risk_id' to table 'mitigations'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigations` ADD INDEX `risk_id` (`risk_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('calculated_risk', 'risk_scoring')) {
        echo "Adding index 'calculated_risk' to table 'risk_scoring'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risk_scoring` ADD INDEX `calculated_risk` (`calculated_risk`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('risk_id', 'mitigation_accept_users')) {
        echo "Adding index 'risk_id' to table 'mitigation_accept_users'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigation_accept_users` ADD INDEX `risk_id` (`risk_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('category', 'risks')) {
        echo "Adding index 'category' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `category` (`category`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('close_id', 'risks')) {
        echo "Adding index 'close_id' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `close_id` (`close_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('manager', 'risks')) {
        echo "Adding index 'manager' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `manager` (`manager`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('mgmt_review', 'risks')) {
        echo "Adding index 'mgmt_review' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `mgmt_review` (`mgmt_review`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('owner', 'risks')) {
        echo "Adding index 'owner' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `owner` (`owner`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('project_id', 'risks')) {
        echo "Adding index 'project_id' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `project_id` (`project_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('source', 'risks')) {
        echo "Adding index 'source' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `source` (`source`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('status', 'risks')) {
        echo "Adding index 'status' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `status` (`status`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('submitted_by', 'risks')) {
        echo "Adding index 'submitted_by' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `submitted_by` (`submitted_by`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('regulation', 'risks')) {
        echo "Adding index 'regulation' to table 'risks'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD INDEX `regulation` (`regulation`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('item_type', 'items_to_teams')) {
        echo "Adding index 'item_type' to table 'items_to_teams'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `items_to_teams` ADD INDEX `item_type` (`item_id`, `type`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('team_type', 'items_to_teams')) {
        echo "Adding index 'team_type' to table 'items_to_teams'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `items_to_teams` ADD INDEX `team_type` (`team_id`, `type`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('type', 'items_to_teams')) {
        echo "Adding index 'type' to table 'items_to_teams'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `items_to_teams` ADD INDEX `type` (`type`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('asset_id', 'risks_to_assets')) {
        echo "Adding index 'asset_id' to table 'risks_to_assets'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks_to_assets` ADD INDEX `asset_id` (`asset_id`, `risk_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('asset_group_id', 'risks_to_asset_groups')) {
        echo "Adding index 'asset_group_id' to table 'risks_to_asset_groups'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks_to_asset_groups` ADD INDEX `asset_group_id` (`asset_group_id`, `risk_id`);");
        $stmt->execute();
    }

    if (!field_exists_in_table('custom_selection_settings', 'dynamic_saved_selections')) {
        echo "Add `custom_selection_settings` field to `dynamic_saved_selections` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `dynamic_saved_selections` ADD `custom_selection_settings` VARCHAR(1000) NOT NULL AFTER `custom_display_settings`;");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20200328-001 *
 ***************************************/
function upgrade_from_20200328001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20200328-001';

    // Database version upgrading to
    $version_upgrading_to = '20200401-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20200401-001 *
 ***************************************/
function upgrade_from_20200401001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20200401-001';

    // Database version upgrading to
    $version_upgrading_to = '20200711-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add a document owner field to documents table
    if (!field_exists_in_table('document_owner', 'documents')) {
        echo "Adding a document owner field to documents table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `document_owner` INT DEFAULT 0 NOT NULL AFTER `framework_ids`;");
        $stmt->execute();
    }
    // Add a additional stakeholders field to documents table
    if (!field_exists_in_table('additional_stakeholders', 'documents')) {
        echo "Adding a additional stakeholders field to documents table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `additional_stakeholders` VARCHAR(500) NOT NULL AFTER `document_owner`;");
        $stmt->execute();
    }
    // Add a approver field to documents table
    if (!field_exists_in_table('approver', 'documents')) {
        echo "Adding a approver field to documents table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `approver` INT DEFAULT 0 NOT NULL AFTER `additional_stakeholders`;");
        $stmt->execute();
    }
    // Change a review_date field to approval_date in documents table
    if (field_exists_in_table('review_date', 'documents')) {
        echo "Change a review_date field to approval_date in documents table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` CHANGE `review_date` `approval_date` DATE NULL DEFAULT NULL;");
        $stmt->execute();
    }
    // Add a custom_column_filters field to dynamic_saved_selections table
    if (!field_exists_in_table('custom_column_filters', 'dynamic_saved_selections')) {
        echo "Adding a custom_column_filters field to dynamic_saved_selections table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `dynamic_saved_selections` ADD `custom_column_filters` TEXT NOT NULL AFTER `custom_selection_settings`;");
        $stmt->execute();
    }
    // Add new permission fields to user table
    if (!field_exists_in_table('add_projects', 'user')) {
        echo "Adding new field `add_projects` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `add_projects` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    if (!field_exists_in_table('delete_projects', 'user')) {
        echo "Adding new field `delete_projects` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `delete_projects` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    if (!field_exists_in_table('manage_projects', 'user')) {
        echo "Adding new field `manage_projects` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `manage_projects` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    // Add new permission for Compliance
    if (!field_exists_in_table('define_tests', 'user')) {
        echo "Adding new field `define_tests` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `define_tests` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    if (!field_exists_in_table('edit_tests', 'user')) {
        echo "Adding new field `edit_tests` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `edit_tests` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    if (!field_exists_in_table('delete_tests', 'user')) {
        echo "Adding new field `delete_tests` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `delete_tests` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    if (!field_exists_in_table('initiate_audits', 'user')) {
        echo "Adding new field `initiate_audits` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `initiate_audits` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    if (!field_exists_in_table('modify_audits', 'user')) {
        echo "Adding new field `modify_audits` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `modify_audits` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    if (!field_exists_in_table('reopen_audits', 'user')) {
        echo "Adding new field `reopen_audits` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `reopen_audits` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    if (!field_exists_in_table('delete_audits', 'user')) {
        echo "Adding new field `delete_audits` to `user` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `user` ADD `delete_audits` TINYINT(1) NOT NULL DEFAULT '0'");
        $stmt->execute();
    }
    // Add a review_frequency field to documents table
    if (!field_exists_in_table('review_frequency', 'documents')) {
        echo "Adding a review frequency field to documents table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `review_frequency` INT NOT NULL DEFAULT '0' AFTER `creation_date`;");
        $stmt->execute();
    }
    // Add a next_review_date field to documents table
    if (!field_exists_in_table('next_review_date', 'documents')) {
        echo "Adding a next review date field to documents table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `next_review_date` DATE NOT NULL AFTER `review_frequency`;");
        $stmt->execute();
    }

    // Add the new risk management permissions to users who currently have the allow access to risk management menu
    echo "Add the new risk management permissions to users who can access risk management menu.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET `add_projects` = '1', `delete_projects` = '1', `manage_projects` = '1' WHERE `riskmanagement` = 1;");
    $stmt->execute();

    // Add the new compliance permissions to users who currently have the allow access to access compliance menu
    echo "Add the new compliance permissions to users who can access compliance menu.<br />\n";
    $stmt = $db->prepare("UPDATE `user` SET `define_tests` = '1', `edit_tests` = '1', `delete_tests` = '1', `initiate_audits` = '1', `modify_audits` = '1', `reopen_audits` = '1', `delete_audits` = '1' WHERE `compliance` = 1 ");
    $stmt->execute();

    // Add the new permissions to Administrator role
    echo "Add the new permissions to Administrator role.<br />\n";
    $new_permissions = array(
        "add_projects",
        "delete_projects",
        "manage_projects",
        "define_tests",
        "edit_tests",
        "delete_tests",
        "initiate_audits",
        "modify_audits",
        "reopen_audits",
        "delete_audits"
    );
    foreach ($new_permissions as $permission)
    {
        $stmt = $db->prepare("DELETE FROM `role_responsibilities` WHERE `role_id`=1 AND `responsibility_name` = :responsibility_name");
        $stmt->bindParam(":responsibility_name", $permission, PDO::PARAM_STR, 100);
        $stmt->execute();
        $stmt = $db->prepare("INSERT INTO `role_responsibilities`(`role_id`, `responsibility_name`) VALUES(1, :responsibility_name);");
        $stmt->bindParam(":responsibility_name", $permission, PDO::PARAM_STR, 100);
        $stmt->execute();
    }

    // Add a file id field to document exceptions table
    if (!field_exists_in_table('file_id', 'document_exceptions')) {
        echo "Add a file id field to document exceptions table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `document_exceptions` ADD `file_id` INT NOT NULL;");
        $stmt->execute();
    }

    // Add a validation_details field to mitigation_to_controls table
    if (!field_exists_in_table('validation_details', 'mitigation_to_controls')) {
        echo "Adding a validation details field to mitigation_to_controls table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigation_to_controls` ADD `validation_details` MEDIUMTEXT NULL AFTER `control_id`;");
        $stmt->execute();
    }

    // Add a validation_owner field to mitigation_to_controls table
    if (!field_exists_in_table('validation_owner', 'mitigation_to_controls')) {
        echo "Adding a validation owner field to mitigation_to_controls table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigation_to_controls` ADD `validation_owner` INT NULL DEFAULT '0' AFTER `validation_details`;");
        $stmt->execute();
    }

    // Add a validation_mitigation_percent field to mitigation_to_controls table
    if (!field_exists_in_table('validation_mitigation_percent', 'mitigation_to_controls')) {
        echo "Adding a validation mitigation percent field to mitigation_to_controls table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigation_to_controls` ADD `validation_mitigation_percent` INT NULL DEFAULT '0' AFTER `validation_owner`;");
        $stmt->execute();
    }

    // Add a table for framework control mappings
    echo "Adding a table for framework control mappings.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `framework_control_mappings` (`id` int(11) NOT NULL AUTO_INCREMENT,`control_id` int(11) NOT NULL,`framework` int(11) NOT NULL,`reference_name` varchar(200) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Migrate every framework and control into new mapping table
    echo "Migrate every framework and control into new mapping table.<br />\n";
    $stmt = $db->prepare("DELETE t1 FROM `framework_control_mappings` t1 LEFT JOIN framework_control_to_framework t2 ON t1.`control_id` = t2.`control_id` AND t1.`framework` = t2.framework_id WHERE t2.`control_id` IS NOT NULL");
    $stmt->execute();

    $stmt = $db->prepare("INSERT INTO `framework_control_mappings` (`control_id`, `framework`, `reference_name`) SELECT t1.`control_id`, t1.`framework_id`, t3.`control_number` FROM `framework_control_to_framework` t1 LEFT JOIN frameworks t2 ON t1.framework_id = t2.value LEFT JOIN framework_controls t3 ON t1.control_id = t3.id WHERE t2.status = 1");
    $stmt->execute();

    // Add a table for risk grouping
    echo "Adding a table for risk grouping.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `risk_grouping` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, PRIMARY KEY (`value`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add new group to risk grouping table
    echo "Add new group to risk grouping table.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `risk_grouping` (`value`, `name`) VALUES
        (1, 'Access Control'),
        (2, 'Asset Management'),
        (3, 'Business Continuity'),
        (4, 'Exposure'),
        (5, 'Governance'),
        (6, 'Situational Awareness');");
    $stmt->execute();

    // Add a table for risk function
    echo "Adding a table for risk function.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `risk_function` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, PRIMARY KEY (`value`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add new rows to risk function table
    echo "Add new rows to risk function table.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `risk_function` (`value`, `name`) VALUES
        (1, 'Identify'),
        (2, 'Protect'),
        (3, 'Detect'),
        (4, 'Respond'),
        (5, 'Recover');");
    $stmt->execute();

    // Add a table for risk catalog
    echo "Adding a table for risk catalog.<br />\n";
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `risk_catalog` ( `id` int(11) NOT NULL AUTO_INCREMENT, `number` varchar(20) NOT NULL, `grouping` int(11) NOT NULL, `name` varchar(1000) NOT NULL, `description` text NOT NULL, `function` int(11) NOT NULL, `order` int(11) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Add new rows to risk catalog table
    echo "Add new rows to risk catalog table.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `risk_catalog` (`id`, `number`, `grouping`, `name`, `description`, `function`, `order`) VALUES
        (1, 'R-AC-1', 1, 'Inability to maintain individual accountability', 'There is a failure to maintain asset ownership and it is not possible to have non-repudiation of actions or inactions.', 2, 1),
        (2, 'R-AC-2', 1, 'Improper assignment of privileged functions', 'There is a failure to implement lease privileges.', 2, 2),
        (3, 'R-AC-3', 1, 'Privilege escalation', 'Access to privileged functions cannot be controlled.', 2, 3),
        (4, 'R-AC-4', 1, 'Unauthorized access', 'Access is granted to unauthorized individuals or services.', 2, 4),
        (5, 'R-AM-1', 2, 'Lost, damaged or stolen asset(s)', 'Asset(s) are lost, damaged or stolen.', 2, 5),
        (6, 'R-AM-2', 2, 'Loss of integrity through unauthorized changes ', 'Unauthorized changes damage the integrity of the system / application / service.', 2, 6),
        (7, 'R-BC-1', 3, 'Business interruption ', 'There is increased latency or a service outage.', 5, 7),
        (8, 'R-BC-2', 3, 'Data loss / corruption ', 'There is a failure to maintain the confidentiality of the data (compromise) or data is corrupted (loss).', 5, 8),
        (9, 'R-BC-3', 3, 'Improper response to incidents ', 'Response actions fail to act appropriately in a timely manner to properly address the incident.', 4, 9),
        (10, 'R-BC-4', 3, 'Inability to investigate / prosecute incidents', 'Response actions either corrupt evidence or impede the ability to prosecute incidents.', 4, 10),
        (11, 'R-BC-5', 3, 'Expense associated with managing a loss event', 'There are financial reprocussions from responding to an incident or loss.', 4, 11),
        (12, 'R-BC-6', 3, 'Reduction in productivity', 'Productivity is negatively affected by the incident.', 2, 12),
        (13, 'R-EX-1', 4, 'Loss of revenue ', 'A financial loss occures from either a loss of clients or inability to generate future revenue.', 5, 13),
        (14, 'R-EX-2', 4, 'Cancelled contract', 'A contract is cancelled due to a violation of a contract clause.', 5, 14),
        (15, 'R-EX-3', 4, 'Diminished competitive advantage', 'The competitive advantage of the organization is jeapordized.', 5, 15),
        (16, 'R-EX-4', 4, 'Diminished reputation ', 'Negative publicity tarnishes the organization\'s reputation.', 5, 16),
        (17, 'R-EX-5', 4, 'Fines and judgements', 'There are legal and/or financial damages resulting from statutory / regulatory / contractual non-compliance.', 5, 17),
        (18, 'R-EX-6', 4, 'Unmitigated vulnerabilities', 'Thre are unmitigated technical vulnerabilities that exist without compensating controls or other mitigation actions.', 2, 18),
        (19, 'R-EX-7', 4, 'System compromise', 'Malicious software infects the system(s) that affects its confidentiality, integrity and availability.', 2, 19),
        (20, 'R-EX-8', 4, 'Information loss / compromise due to technical attack', 'Users fall for phishing, or other technical attacks, that compromise data, systems, applications or services.', 2, 20),
        (21, 'R-EX-9', 4, 'Information loss / compromise due to non-technical attack', 'Users fall for a social engineering attack, that compromise data, systems, applications or services.', 2, 21),
        (22, 'R-GV-1', 5, 'Inability to support business processes / missions', 'Security /privacy are unable to support the organization\'s mission requirements for secure technologies & processes.', 2, 22),
        (23, 'R-GV-2', 5, 'Ineffective remediation actions', 'There is no oversight to ensure remediation actions are correct and/or effective.', 2, 23),
        (24, 'R-GV-3', 5, 'Improper internal security / privacy practices', 'Internal procedures do not exist or are improper. Procedures fail to meet \"reasonable practices\" expected by industry standards.', 2, 24),
        (25, 'R-GV-4', 5, 'Improper third-party security / privacy practices', 'Third-party procedures do not exist or are improper. Procedures fail to meet \"reasonable practices\" expected by industry standards.', 2, 25),
        (26, 'R-GV-5', 5, 'Lack of accountability for security / privacy roles & responsibilities', 'There is a failure to govern security / privacy roles & responsibilities.', 1, 26),
        (27, 'R-GV-6', 5, 'Gap or lapse in security / privacy controls coverage', 'There is improper scoping of control environment, which leads to a potential gap or lapse in security / privacy controls coverage.', 1, 27),
        (28, 'R-GV-7', 5, 'Abusive content or action', 'There is harmful speech / violence threats / illegal content that negatively affect business operations.', 1, 28),
        (29, 'R-SA-1', 6, 'Inability to maintain situational awareness', 'There is an inability to detect incidents.', 3, 29),
        (30, 'R-SA-2', 6, 'Lack of a security-minded workforce', 'The workforce lacks user-level understanding about security & privacy principles.', 2, 30),
        (31, 'R-SA-3', 6, 'Lack of oversight of internal security / privacy controls', 'There is a lack of due diligence / due care in overseeing the organization\'s internal security / privacy controls.', 1, 31),
        (32, 'R-SA-4', 6, 'Lack of oversight of third-party security / privacy controls ', 'There is a lack of due diligence / due care in overseeing security / privacy controls operated by third-party service providers.', 1, 32);
    ");
    $stmt->execute();

    // Add new setting for risk mapping required
    echo "Add new setting for risk mapping required.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (`name`, `value`) VALUES ('risk_mapping_required',0);");
    $stmt->execute();

    // Add a risk_catalog_mapping field to risks table
    if (!field_exists_in_table('risk_catalog_mapping', 'risks')) {
        echo "Adding a risk catalog mapping field to risks table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD `risk_catalog_mapping` INT NULL DEFAULT NULL;");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

}

/***************************************
 * FUNCTION: UPGRADE FROM 20200711-001 *
 ***************************************/
function upgrade_from_20200711001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20200711-001';

    // Database version upgrading to
    $version_upgrading_to = '20201005-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add a custom_plan_mitigation_display_settings field to user table
    if (!field_exists_in_table('custom_plan_mitigation_display_settings', 'user')) {
        echo "Adding a custom_plan_mitigation_display_settings field to user table.<br />\n";
        $stmt = $db->prepare('ALTER TABLE `user` ADD `custom_plan_mitigation_display_settings` VARCHAR(2000) NULL DEFAULT \'{"risk_colums":[["id","1"],["risk_status","1"],["subject","1"],["calculated_risk","1"],["submission_date","1"]],"mitigation_colums":[["mitigation_planned","1"]],"review_colums":[["management_review","1"]]}
\';');
        $stmt->execute();
    }

    // Add a custom_perform_reviews_display_settings field to user table
    if (!field_exists_in_table('custom_perform_reviews_display_settings', 'user')) {
        echo "Adding a custom_perform_reviews_display_settings field to user table.<br />\n";
        $stmt = $db->prepare('ALTER TABLE `user` ADD `custom_perform_reviews_display_settings` VARCHAR(2000) NULL DEFAULT \'{"risk_colums":[["id","1"],["risk_status","1"],["subject","1"],["calculated_risk","1"],["submission_date","1"]],"mitigation_colums":[["mitigation_planned","1"]],"review_colums":[["management_review","1"]]}
\';');
        $stmt->execute();
    }

    // Add a custom_reviewregularly_display_settings field to user table
    if (!field_exists_in_table('custom_reviewregularly_display_settings', 'user')) {
        echo "Adding a custom_reviewregularly_display_settings field to user table.<br />\n";
        $stmt = $db->prepare('ALTER TABLE `user` ADD `custom_reviewregularly_display_settings` VARCHAR(2000) NULL DEFAULT \'{"risk_colums":[["id","1"],["risk_status","1"],["subject","1"],["calculated_risk","1"],["days_open","1"]],"review_colums":[["management_review","0"],["review_date","0"],["next_step","0"],["next_review_date","1"],["comments","0"]]}\';');
        $stmt->execute();
    }

    // Add a control_id index to framework_control_mappings table
    if (!index_exists_on_table('control_id', 'framework_control_mappings')) {
        echo "Adding index 'control_id' to framework_control_mappings table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_control_mappings` ADD INDEX `control_id`(`control_id`);");
        $stmt->execute();
    }

    // Add a framework index to framework_control_mappings table
    if (!index_exists_on_table('framework', 'framework_control_mappings')) {
        echo "Adding index 'framework' to framework_control_mappings table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_control_mappings` ADD INDEX `framework`(`framework`);");
        $stmt->execute();
    }

    // Fix the `tag` field's length of the `tags` table 
    if (table_exists('tags')) {
        echo "Fix the `tag` field's length of the `tags` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `tags` CHANGE `tag` `tag` VARCHAR(255) NOT NULL;");
        $stmt->execute();
    }

    $permission_groups_and_permissions = [
        'governance' => [
            'name' => 'Governance',
            'description' => '',
            'order' => 1,
            'permissions' => [
                'governance' => [
                    'name' => 'Allow Access to "Governance" Menu',
                    'description' => 'This permission grants a user access to the "Governance" menu in SimpleRisk.',
                    'order' => 1,
                ],
                'add_new_frameworks' => [
                    'name' => 'Able to Add New Frameworks',
                    'description' => 'This permission allows a user to create new Control Frameworks in the "Governance" menu at the top, followed by "Define Control Frameworks" menu on the left.',
                    'order' => 2
                ],
                'modify_frameworks' => [
                    'name' => 'Able to Modify Existing Frameworks',
                    'description' => 'This permission allows a user to modify existing Control Frameworks in the "Governance" menu at the top, followed by "Define Control Frameworks" menu on the left.',
                    'order' => 3
                ],
                'delete_frameworks' => [
                    'name' => 'Able to Delete Existing Frameworks',
                    'description' => 'This permission allows a user to delete existing Control Frameworks in the "Governance" menu at the top, followed by "Define Control Frameworks" menu on the left.',
                    'order' => 4
                ],
                'add_new_controls' => [
                    'name' => 'Able to Add New Controls',
                    'description' => 'This permission allows a user to add new Framework Controls in the "Governance" menu at the top, followed by "Define Control Frameworks" menu on the left then going to the controls tab.',
                    'order' => 5
                ],
                'modify_controls' => [
                    'name' => 'Able to Modify Existing Controls',
                    'description' => 'This permission allows a user to modify existing Framework Controls in the "Governance" menu at the top, followed by "Define Control Frameworks" menu on the left then going to the controls tab.',
                    'order' => 6
                ],
                'delete_controls' => [
                    'name' => 'Able to Delete Existing Controls',
                    'description' => 'This permission allows a user to delete existing Framework Controls in the "Governance" menu at the top, followed by "Define Control Frameworks" menu on the left then going to the controls tab.',
                    'order' => 7
                ],
                'add_documentation' => [
                    'name' => 'Able to Add Documentation',
                    'description' => 'This permission allows a user to upload Policies/Guidelines/Standards/Procedures in the "Governance" menu at the top, followed by "Document Program" on the left.',
                    'order' => 8
                ],
                'modify_documentation' => [
                    'name' => 'Able to Modify Documentation',
                    'description' => 'This permission allows a user to modify Policies/Guidelines/Standards/Procedures in the "Governance" menu at the top, followed by "Document Program" on the left.',
                    'order' => 9
                ],
                'delete_documentation' => [
                    'name' => 'Able to Delete Documentation',
                    'description' => 'This permission allows a user to delete Policies/Guidelines/Standards/Procedures in the "Governance" menu at the top, followed by "Document Program" on the left.',
                    'order' => 10
                ],
                'view_exception' => [
                    'name' => 'Able to View Exceptions',
                    'description' => 'This permission allows a user to view exceptions for Policies and Controls as well as Unapproved Exceptions in the "Governance" menu at the top, followed by "Define Exceptions" on the left.',
                    'order' => 11
                ],
                'create_exception' => [
                    'name' => 'Able to Create Exceptions',
                    'description' => 'This permission allows a user to create exceptions for Policies and Controls in the "Governance" menu at the top, followed by "Define Exceptions" on the left.',
                    'order' => 12
                ],
                'update_exception' => [
                    'name' => 'Able to Update Exceptions',
                    'description' => 'This permission allows a user to modify/update exceptions for Policies and Controls in the "Governance" menu at the top, followed by "Define Exceptions" on the left.',
                    'order' => 13
                ],
                'delete_exception' => [
                    'name' => 'Able to Delete Exceptions',
                    'description' => 'This permission allows a user to delete exceptions for Policies and Controls in the "Governance" menu at the top, followed by "Define Exceptions" on the left.',
                    'order' => 14
                ],
                'approve_exception' => [
                    'name' => 'Able to Approve Exceptions',
                    'description' => 'This permission allows a user to approve an exception moving it from the Unapproved Exceptions tab to its respecitve Policy or Control Exceptions tab in the "Governance" menu at the top, followed by "Define Exceptions" on the left.',
                    'order' => 15
                ]
            ]
        ],
        'risk_management' => [
            'name' => 'Risk Management',
            'description' => '',
            'order' => 2,
            'permissions' => [
                'riskmanagement' => [
                    'name' => 'Allow Access to "Risk Management" Menu',
                    'description' => 'This permission will allow a user to see the "Risk Management" menu in SimpleRisk and allow them to use any risk management responsibilities they have been assigned. If a user has been assigned this permission, but no others, they will only be able to see the details for risks, mitigations, and reviews, but will not be able to edit or submit anything. (Note: If team-based separation is in use, users will only see risks that are assigned to a team they are part of, otherwise, no risks will be displayed to that user.)',
                    'order' => 1
                ],
                'submit_risks' => [
                    'name' => 'Able to Submit New Risks',
                    'description' => 'This permission, as the name suggests, allows for the submission of new risks in the "Risk Management" menu. If a user has this permission, but does not have the "Able to Modify Risk" permission, they will not be able to edit risks, even if they are the original submitter.',
                    'order' => 2
                ],
                'modify_risks' => [
                    'name' => 'Able to Modify Existing Risks',
                    'description' => 'This permission allows users to save changes made to risks. No risk, mitigation, or review will be able to be modified with out it.',
                    'order' => 3
                ],
                'close_risks' => [
                    'name' => 'Able to Close Risks',
                    'description' => 'This permission grants a user the ability to close a risk.',
                    'order' => 4
                ],
                'plan_mitigations' => [
                    'name' => 'Able to Plan Mitigations',
                    'description' => 'This permission is neccessary along with the "Able to Modify Risks" permission, in order to give the user the ability to plan and save mitigations.',
                    'order' => 5
                ],
                'accept_mitigation' => [
                    'name' => 'Able to Accept Mitigations',
                    'description' => 'This permission allows a user to accept risk mitigations. This is separate from submitting mitigations as this only refers to the check box found in each risk mitigation to signify this particular mitigation has been accepted by management. This is not a core step in the risk management life cycle and serves as an additional feature for users needing to delegate responsibilities further.',
                    'order' => 6
                ],
                'review_insignificant' => [
                    'name' => 'Able to Review Insignificant Risks',
                    'description' => 'This permission, along with the "Able to Modify Risks" permission, will grant the user the ability to review risks that have a current score that would be labeled as "Insignificant" by the risk scoring system. You may change which risk scores are defined as "Insignificant" by selecting the "Configure" menu at the top, followed by "Configure Risk Formula" on the left.',
                    'order' => 7
                ],
                'review_low' => [
                    'name' => 'Able to Review Low Risks',
                    'description' => 'This permission, along with the "Able to Modify Risks" permission, will grant the user the ability to review risks that have a current score that would be labeled as "Low" by the risk scoring system. You may change which risk scores are defined as "Low" by selecting the "Configure" menu at the top, followed by "Configure Risk Formula” on the left.',
                    'order' => 8
                ],
                'review_medium' => [
                    'name' => 'Able to Review Medium Risks',
                    'description' => 'This permission, along with the "Able to Modify Risks” permission, will grant the user the ability to review risks that have a current score that would be labeled as "Medium" by the risk scoring system. You may change which risk scores are defined as "Medium" by selecting the "Configure” menu at the top, followed by "Configure Risk Formula” on the left.',
                    'order' => 9
                ],
                'review_high' => [
                    'name' => 'Able to Review High Risks',
                    'description' => 'This permission, along with the "Able to Modify Risks” permission, will grant the user the ability to review risks that have a current score that would be labeled as "High" by the risk scoring system. You may change which risk scores are defined as "High" by selecting the "Configure” menu at the top, followed by "Configure Risk Formula” on the left.',
                    'order' => 10
                ],
                'review_veryhigh' => [
                    'name' => 'Able to Review Very High Risks',
                    'description' => 'This permission, along with the "Able to Modify Risks” permission, will grant the user the ability to review risks that have a current score that would be labeled as "Very High" by the risk scoring system. You may change which risk scores are defined as "Very High" by selecting the "Configure” menu at the top, followed by "Configure Risk Formula” on the left.',
                    'order' => 11
                ],
                'comment_risk_management' => [
                    'name' => 'Able to Comment Risk Management',
                    'description' => 'This permission allows a user to add comments to risks they can otherwise already access.',
                    'order' => 12
                ],
                'add_projects' => [
                    'name' => 'Able to Add Projects',
                    'description' => 'This permission allows a user to create new Projects in the "Risk Management" menu at the top, followed by "Plan Projects" menu on the left.',
                    'order' => 13
                ],
                'delete_projects' => [
                    'name' => 'Able to Delete Projects',
                    'description' => 'This permission alows a user to delete existing projects from the "Risk Management" menu at the to, followed by "Plan Projects" menu on the left.',
                    'order' => 14
                ],
                'manage_projects' => [
                    'name' => 'Able to Manage Projects',
                    'description' => 'This permission alows a user to modfiy/manage existing projects from the "Risk Management" menu at the to, followed by "Plan Projects" menu on the left.',
                    'order' => 15
                ]
            ]
        ],
        'compliance' => [
            'name' => 'Compliance',
            'description' => '',
            'order' => 3,
            'permissions' => [
                'compliance' => [
                    'name' => 'Allow Access to "Compliance" Menu',
                    'description' => 'This permission will allow users to see and access the "Compliance" menu at the top.',
                    'order' => 1
                ],
                'comment_compliance' => [
                    'name' => 'Able to Comment Compliance',
                    'description' => 'This permission allows a user to add comments to control audits they can otherwise already access.',
                    'order' => 2
                ],
                'define_tests' => [
                    'name' => 'Able to Define Tests',
                    'description' => 'This permission allows a user to define/create tests in the "Compliance" menu at the top, followed by "Define Tests" on the left.',
                    'order' => 3
                ],
                'edit_tests' => [
                    'name' => 'Able to Edit Tests',
                    'description' => 'This permission allows a user to edit/modify tests in the "Compliance" menu at the top, followed by "Define Tests" on the left.',
                    'order' => 4
                ],
                'delete_tests' => [
                    'name' => 'Able to Delete Tests',
                    'description' => 'This permission allows a user to delete tests in the "Compliance" menu at the top, followed by "Define Tests" on the left.',
                    'order' => 5
                ],
                'initiate_audits' => [
                    'name' => 'Able to Initiate Audits',
                    'description' => 'This permission allows a user to iniate an audit of tests, controls, and/or frameworks in the "Compliance" menu at the top followed by "Initiate Audits" on the left.',
                    'order' => 6
                ],
                'modify_audits' => [
                    'name' => 'Able to Modify Audits',
                    'description' => 'This permission allows a user to modify audits of tests, controls, and/or frameworks in the "Compliance" menu at the top followed by "Active Audits" on the left.',
                    'order' => 7
                ],
                'reopen_audits' => [
                    'name' => 'Able to Reopen Audits',
                    'description' => 'This permission allows a user to reopen audits of tests, controls, and/or frameworks in the "Compliance" menu at the top followed by "Past Audits" on the left.',
                    'order' => 8
                ],
                'delete_audits' => [
                    'name' => 'Able to Delete Audits',
                    'description' => 'This permission allows a user to delete audits of tests, controls, and/or frameworks in the "Compliance" menu at the top followed by "Past Audits" on the left.',
                    'order' => 9
                ]
            ]
        ],
        'asset_management' => [
            'name' => 'Asset Management',
            'description' => '',
            'order' => 4,
            'permissions' => [
                'asset' => [
                    'name' => 'Allow Access to "Asset Management" Menu',
                    'description' => 'This permission allows a user to create, modify, and delete assets. This permission will grant the user full control in the Asset Management Menu.',
                    'order' => 1
                ]
            ]
        ],
        'assessments' => [
            'name' => 'Assessments',
            'description' => '',
            'order' => 5,
            'permissions' => [
                'assessments' => [
                    'name' => 'Allow Access to "Assessments" Menu',
                    'description' => 'This permission will allow the user access to the "Assessments" menu. If SimpleRisk has the "Risk Assessment Extra" enabled the user will gain access to this as well with all the permssions to make, change, and send assessments.',
                    'order' => 1
                ]
            ]
        ]
    ];

    $stmt = $db->prepare("DROP TABLE IF EXISTS `permissions`, `permission_to_user`, `permission_groups`, `permission_to_permission_group`;");
    $stmt->execute();

    if (!table_exists('permissions')) {
        echo "Creating `permissions` table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `permissions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `key` varchar(100) NOT NULL UNIQUE,
                `name` varchar(200) NOT NULL,
                `description` blob NOT NULL,
                `order` int NOT NULL,
                PRIMARY KEY(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }
    if (!table_exists('permission_to_user')) {
        echo "Creating `permission_to_user` table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `permission_to_user` (
                `permission_id` int(11) NOT NULL ,
                `user_id` int(11) NOT NULL,
                PRIMARY KEY(`permission_id`, `user_id`),
                INDEX(`user_id`, `permission_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }
    
    if (!table_exists('permission_groups')) {
        echo "Creating `permission_groups` table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `permission_groups` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(200) NOT NULL UNIQUE,
                `description` blob NOT NULL,
                `order` int NOT NULL,
                PRIMARY KEY(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }
    
    if (!table_exists('permission_to_permission_group')) {
        echo "Creating `permission_to_permission_group` table.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `permission_to_permission_group` (
                `permission_id` int(11) NOT NULL,
                `permission_group_id` int(11) NOT NULL,
                PRIMARY KEY(`permission_id`, `permission_group_id`),
                INDEX(`permission_group_id`, `permission_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    // Doing the user permission migration. Checking for the 'assessments' permission here, but could've chosen
    // any other as they're dropped in the same query
    if (field_exists_in_table('assessments', 'user')) {
        
        echo "Populating `permissions` and `permission_groups` tables.<br />\n";
        $possible_permissions = [];
        foreach ($permission_groups_and_permissions as $_ => $group) {
            $group_name = $group['name'];
            $group_description = $group['description'];
            $group_order = $group['order'];
            $permissions = $group['permissions'];
            
            // Creating the permission group
            $stmt = $db->prepare("INSERT IGNORE INTO `permission_groups` (`name`, `description`, `order`) VALUES (:name, :description, :order);");
            $stmt->bindParam(":name", $group_name, PDO::PARAM_STR);
            $stmt->bindParam(":description", $group_description, PDO::PARAM_STR);
            $stmt->bindParam(":order", $group_order, PDO::PARAM_INT);
            $stmt->execute();
            
            // Getting the permission group id
            $group_id = $db->lastInsertId();
            
            foreach ($permissions as $key => $permission) {
                $permission_name = $permission['name'];
                $permission_description = $permission['description'];
                $permission_order = $permission['order'];
                
                // Creating the permission
                $stmt = $db->prepare("INSERT IGNORE INTO `permissions` (`key`, `name`, `description`, `order`) VALUES (:key, :name, :description, :order);");
                $stmt->bindParam(":key", $key, PDO::PARAM_STR);
                $stmt->bindParam(":name", $permission_name, PDO::PARAM_STR);
                $stmt->bindParam(":description", $permission_description, PDO::PARAM_STR);
                $stmt->bindParam(":order", $permission_order, PDO::PARAM_INT);
                $stmt->execute();
                
                // Getting the permission id
                $permission_id = $db->lastInsertId();
                
                $possible_permissions[] = $key;
                
                // Adding the permission to the group
                $stmt = $db->prepare("INSERT IGNORE INTO `permission_to_permission_group` (`permission_id`, `permission_group_id`) VALUES (:permission_id, :permission_group_id);");
                $stmt->bindParam(":permission_id", $permission_id);
                $stmt->bindParam(":permission_group_id", $group_id);
                $stmt->execute();
            }
        }
        
        echo "Starting the migration of the user permissions.<br />\n";
        echo "Getting users' current permissions.<br />\n";
        $permission_selects = [];
        // Building the selects that'll return the users and their permissions in a 'joinable' way
        foreach ($possible_permissions as $permission) {
            $permission_selects[] = "SELECT value, '$permission' AS name FROM `user` WHERE `$permission` = 1 OR `admin` = 1";
        }
        $permissions_from_part = implode(" UNION ALL ", $permission_selects);

        // The query joins the above built union query's results with the `permissions` table, this way being able to
        // return not the permission keys(which we'd have to process later), but the actual permission ids from the `permissions` table
        $stmt = $db->prepare("
            SELECT
                `u`.`value` AS value,
                `p`.`id`
            FROM
                `user` u
                LEFT JOIN ($permissions_from_part) perms ON `u`.`value` = `perms`.`value`
                INNER JOIN `permissions` p on `p`.`key` = `perms`.`name`
        ");
        $stmt->execute();
        $permissions_of_users = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);

        echo "Populating `permission_to_user` table.<br />\n";
        foreach($permissions_of_users as $user_id => $permissions) {
            // We can safely do that as both the user id and the keys of the permissions are coming from a safe source
            // user id is an INT from the db and the permission keys are coming from the array structure of $permission_groups_and_permissions
            $stmt = $db->prepare("INSERT INTO `permission_to_user` (`permission_id`, `user_id`) SELECT `id`, {$user_id} FROM `permissions` WHERE `id` IN (" . implode(",", $permissions) . ");");
            $stmt->execute();
        }
        
        echo "Dropping permission columns of the `user` table.<br />\n";
        $column_drop_parts = [];
        foreach ($possible_permissions as $permission) {
            $column_drop_parts[] = "DROP `{$permission}`";
        }
        
        $stmt = $db->prepare("ALTER TABLE `user` " . implode(',', $column_drop_parts) . ";");
        $stmt->execute();
        echo "Finished the migration of the user permissions.<br />\n";
    }
    
    // If the `admin` column doesn't exist in the `role` table yet.
    if (!field_exists_in_table('admin', 'role')) {
        
        echo "Adding the `admin` column to the `role` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `role` ADD `admin` tinyint(1) NOT NULL DEFAULT 0;");
        $stmt->execute();
        
        // Populate the `admin` column
        // It's admin if the id is 1 or it has the 'admin' responsibility associated in the `role_responsibilities` table
        echo "Populating the `admin` column of the `role` table.<br />\n";
        $stmt = $db->prepare("
            UPDATE
                `role` r
                INNER JOIN `role_responsibilities` rr ON `rr`.`role_id` = `r`.`value`
            SET
                `r`.`admin` = 1
            WHERE
                `rr`.`responsibility_name`='admin' OR `r`.`value` = 1;");
        $stmt->execute();
    }
    
    // If the `default` column doesn't exist in the `role` table yet.
    if (!field_exists_in_table('default', 'role')) {
        
        echo "Adding the `default` column to the `role` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `role` ADD `default` tinyint(1) UNIQUE DEFAULT NULL;");
        $stmt->execute();
        
        $default_user_role = get_setting("default_user_role");
        
        if ($default_user_role) {
            echo "Setting the default role in the `role` table.<br />\n";
            set_default_role($default_user_role);
            
            echo "Removing the 'default_user_role' setting from the `settings` table.<br />\n";
            $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'default_user_role';");
            $stmt->execute();
        } else {
            echo "There's no default role to set.<br />\n";
        }
    }
    
    // If the `responsibility_name` column exists in the `role_responsibilities` table then migrate the data
    if (field_exists_in_table('responsibility_name', 'role_responsibilities')) {
        
        echo "Starting migration of the roles.<br />\n";
        
        // Clean up every junction entries that aren't tied to a role
        echo "Cleaning up leftover entries from the `role_responsibilities` table.<br />\n";
        $stmt = $db->prepare("
            DELETE
                `junction`
            FROM
                `role_responsibilities` `junction`
                LEFT JOIN `role` `tbl` ON `junction`.`role_id` = `tbl`.`value`
            WHERE
                `tbl`.`value` IS NULL;
        ");
        $stmt->execute();
        
        // Add the `permission_id` column
        echo "Adding the `permission_id` column to the `role_responsibilities` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `role_responsibilities` ADD `permission_id` INT(11);");
        $stmt->execute();
        
        // Populate the `permission_id` column
        echo "Populating the `permission_id` column of the `role_responsibilities` table.<br />\n";
        $stmt = $db->prepare("
            UPDATE
                `role_responsibilities` rr
                INNER JOIN `permissions` p ON `rr`.`responsibility_name` = `p`.`key`
            SET
                `rr`.`permission_id` = `p`.`id`;");
        $stmt->execute();
        
        // Delete entries where no matching permission was found
        echo "Cleaning up entries from the `role_responsibilities` table where no matching permission was found.<br />\n";
        $stmt = $db->prepare("DELETE FROM `role_responsibilities` WHERE `permission_id` IS NULL;");
        $stmt->execute();
        
        // Modify the `permission_id` column to be mandatory
        echo "Modifying the `permission_id` column of the `role_responsibilities` table to be mandatory.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `role_responsibilities` MODIFY `permission_id` INT(11) NOT NULL;");
        $stmt->execute();
        
        // Drop the `responsibility_name` column as it's not needed anymore
        echo "Modifying the `permission_id` column of the `role_responsibilities` table to be mandatory.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `role_responsibilities` DROP COLUMN `responsibility_name`;");
        $stmt->execute();
        
        // Add the indexes that are required for a junction table
        if (!index_exists_on_table('role_id', 'role_responsibilities')) {
            echo "Adding index 'role_id' to table 'role_responsibilities'.<br />\n";
            $stmt = $db->prepare("ALTER TABLE `role_responsibilities` ADD PRIMARY KEY `role_id` (`role_id`, `permission_id`);");
            $stmt->execute();
        }
        
        if (!index_exists_on_table('permission_id', 'role_responsibilities')) {
            echo "Adding index 'permission_id' to table 'role_responsibilities'.<br />\n";
            $stmt = $db->prepare("ALTER TABLE `role_responsibilities` ADD INDEX `permission_id` (`permission_id`, `role_id`);");
            $stmt->execute();
        }
        
        // Grant all permissions to admin roles as they were handled differently before
        echo "Granting all permissions to admin roles.<br />\n";
        $stmt = $db->prepare("
            INSERT IGNORE INTO
                `role_responsibilities`(`role_id`, `permission_id`)
            SELECT
                `r`.`value`,
                `p`.`id`
            FROM
                `role` r, `permissions` p
            WHERE
                `r`.`admin` = 1;");
        $stmt->execute();
        
        echo "Finished migration of the roles.<br />\n";
    }

    // Fix issues related to larger PHP session ids in SUSE (and possibly other OSes)
    echo "Increasing the maximum session ID size from 32 to 128 characters.<br />\n";
    $stmt = $db->prepare("ALTER TABLE sessions CHANGE id id varchar(128) NOT NULL;");
    $stmt->execute();
    
    // Add last_updated field to framework_control_test_results table
    if (!field_exists_in_table('last_updated', 'framework_control_test_results')) {
        echo "Adding last_updated field to framework_control_test_results table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_control_test_results` ADD `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;");
        $stmt->execute();
    }

    // Delete the PCI DSS 3.2 framework if no controls are associated with it
    echo "Deleting the PCI DSS 3.2 framework if no controls are associated with it.<br />\n";
    $stmt = $db->prepare("DELETE FROM `frameworks` WHERE name='PCI DSS 3.2' AND value NOT IN (SELECT `framework` FROM framework_control_mappings);");
    $stmt->execute();

    // Delete the Sarbanes-Oxley (SOX) framework if no controls are associated with it
    echo "Deleting the Sarbanes-Oxley (SOX) framework if no controls are associated with it.<br />\n";
    $stmt = $db->prepare("DELETE FROM `frameworks` WHERE name='Sarbanes-Oxley (SOX)' AND value NOT IN (SELECT `framework` FROM framework_control_mappings);");
    $stmt->execute();

    // Delete the HIPAA framework if no controls are associated with it
    echo "Deleting the HIPAA framework if no controls are associated with it.<br />\n";
    $stmt = $db->prepare("DELETE FROM `frameworks` WHERE name='HIPAA' AND value NOT IN (SELECT `framework` FROM framework_control_mappings);");
    $stmt->execute();

    // Delete the ISO 27001 framework if no controls are associated with it
    echo "Deleting the ISO 27001 framework if no controls are associated with it.<br />\n";
    $stmt = $db->prepare("DELETE FROM `frameworks` WHERE name='ISO 27001' AND value NOT IN (SELECT `framework` FROM framework_control_mappings);");
    $stmt->execute();

    // Change the settings table to allow MEDIUMTEXT size values to accommodate larger SAML_METADATA_XML values
    echo "Changing the settings table from TEXT to MEDIUMTEXT to allow for larger value fields.<br />\n";
    $stmt = $db->prepare("ALTER TABLE settings MODIFY value mediumtext;");
    $stmt->execute();

    // Add new group to risk grouping table
    echo "Adding new Incident Response group to risk grouping table.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `risk_grouping` (`name`) VALUES
	('Incident Response');");
    $stmt->execute();

    // Get the id of the Incident Response group
    $incident_response_group_id = $db->lastInsertId();

    // Add new rows to risk catalog table
    echo "Add new rows to risk catalog table.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `risk_catalog` (`number`, `grouping`, `name`, `description`, `function`, `order`) VALUES
        ('R-IR-1', :incident_response_group_id, 'Inability to investigate / prosecute incidents', 'Response actions either corrupt evidence or impede the ability to prosecute incidents.', 4, 1),
	('R-IR-2', :incident_response_group_id, 'Improper response to incidents', 'Response actions fail to act appropriately in a timely manner to properly address the incident.', 4, 2),
	('R-IR-3', :incident_response_group_id, 'Ineffective remediation actions', 'There is no oversight to ensure remediation actions are correct and/or effective.', 2, 3),
	('R-IR-4', :incident_response_group_id, 'Expense associated with managing a loss event', 'There are financial repercussions from responding to an incident or loss.', 4, 4);
    ");
    $stmt->bindParam(":incident_response_group_id", $incident_response_group_id, PDO::PARAM_INT);
    $stmt->execute();

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20201005-001 *
 ***************************************/
function upgrade_from_20201005001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20201005-001';

    // Database version upgrading to
    $version_upgrading_to = '20201106-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20201106-001 *
 ***************************************/
function upgrade_from_20201106001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20201106-001';

    // Database version upgrading to
    $version_upgrading_to = '20201123-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Removing the unneeded setting for Session Renegotiation Period
    echo "Removing the unneeded setting for Session Renegotiation Period<br />\n";
    delete_setting('session_renegotiation_period');

    // Adding default setting for Session Absolute Timeout
    if (!get_setting('session_absolute_timeout')) {
        echo "Adding default setting for Session Absolute Timeout<br />\n";
        add_setting("session_absolute_timeout", 28800);
    }

    if (table_exists('files')) {
        // Increase the file type field to 128 in files table.
        echo "Increase the file type field to 128 in files table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `files` CHANGE `type` `type` VARCHAR(128);");
        $stmt->execute();
    }

    if (table_exists('compliance_files')) {
        // Increase the file type field to 128 in compliance_files table.
        echo "Increase the file type field to 128 in compliance_files table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `compliance_files` CHANGE `type` `type` VARCHAR(128);");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20201123-001 *
 ***************************************/
function upgrade_from_20201123001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20201123-001';
    
    // Database version upgrading to
    $version_upgrading_to = '20210121-001';
    
    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
    
    // Add new language translations
    echo "Adding new language translations.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `languages` (name, full) VALUES ('si', 'Sinhala');");
    $stmt->execute();
    
    // Updating the length of the `tags` table's `type` column
    echo "Updating the length of the `tags` table's `type` column to 40 characters.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `tags_taggees` CHANGE `type` `type` VARCHAR(40);");
    $stmt->execute();
    
    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20210121-001 *
 ***************************************/
function upgrade_from_20210121001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20210121-001';
    
    // Database version upgrading to
    $version_upgrading_to = '20210305-001';
    
    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
    
    // Add control_maturity field to framework_controls table
    if (!field_exists_in_table('control_maturity', 'framework_controls')) {
        echo "Adding control_maturity field to framework_controls table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `control_maturity` INT NOT NULL DEFAULT '0' AFTER `control_number`;");
        $stmt->execute();
    }

    // Add desired_maturity field to framework_controls table
    if (!field_exists_in_table('desired_maturity', 'framework_controls')) {
      echo "Adding desired_maturity field to framework_controls table.<br />\n";
      $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `desired_maturity` INT NOT NULL DEFAULT '0' AFTER `control_maturity`;");
      $stmt->execute();
    }

    // Creating a control_maturity table.
    echo "Creating a control_maturity table.<br />\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `control_maturity` (
          `value` INT NOT NULL PRIMARY KEY,
          `name` MEDIUMTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Add the control maturity level to control_maturity table
    echo "Adding the control maturity level to control_maturity table.<br />\n";
    $stmt = $db->prepare("INSERT IGNORE INTO `control_maturity` (`value`, `name`) VALUES (0, 'Not Performed'),(1, 'Performed'), (2, 'Documented'), (3, 'Managed'), (4, 'Reviewed'),(5, 'Optimizing');");
    $stmt->execute();

    // Update framework_controls to be able to allow for longer short_name
    echo "Updating the framework_controls to be able to allow for longer short_name.<br />\n";
    $stmt = $db->prepare("ALTER TABLE `framework_controls` MODIFY `short_name` VARCHAR(1000)");
    $stmt->execute();

    // Add a last review date field to documents table
    if (!field_exists_in_table('last_review_date', 'documents')) {
        echo "Add `last_review_date` field to the `documents` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `last_review_date` date AFTER `creation_date`;");
        $stmt->execute();
    }

    // Add a team_ids field to documents table.
    if (!field_exists_in_table('team_ids', 'documents')) {
        echo "Adding `team_ids` field to `documents` table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `team_ids` VARCHAR(500) NOT NULL;");
        $stmt->execute();
    }

    // Update complianceforge risk catalog
    if (table_exists('risk_catalog'))
    {
        echo "Updating the risk catalog values to match with ComplianceForge.<br />\n";
        // R-BC-3 => R-IR-2
        $stmt = $db->prepare("UPDATE `risks` r LEFT JOIN `risk_catalog` rc ON r.risk_catalog_mapping = rc.id JOIN (SELECT id FROM `risk_catalog` WHERE number = 'R-IR-2') rcm SET r.risk_catalog_mapping = rcm.id WHERE rc.number = 'R-BC-3';");
        $stmt->execute();
        $stmt = $db->prepare("DELETE FROM `risk_catalog` WHERE number = 'R-BC-3';");
        $stmt->execute();
        // R-BC-4 => R-IR-1
        $stmt = $db->prepare("UPDATE `risks` r LEFT JOIN `risk_catalog` rc ON r.risk_catalog_mapping = rc.id JOIN (SELECT id FROM `risk_catalog` WHERE number = 'R-IR-1') rcm SET r.risk_catalog_mapping = rcm.id WHERE rc.number = 'R-BC-4';");
        $stmt->execute();
        $stmt = $db->prepare("DELETE FROM `risk_catalog` WHERE number = 'R-BC-4';");
        $stmt->execute();
        // R-BC-5 => R-IR-4
        $stmt = $db->prepare("UPDATE `risks` r LEFT JOIN `risk_catalog` rc ON r.risk_catalog_mapping = rc.id JOIN (SELECT id FROM `risk_catalog` WHERE number = 'R-IR-4') rcm SET r.risk_catalog_mapping = rcm.id WHERE rc.number = 'R-BC-5';");
        $stmt->execute();
        $stmt = $db->prepare("DELETE FROM `risk_catalog` WHERE number = 'R-BC-5';");
        $stmt->execute();
        // R-GV-2 => R-IR-3
        $stmt = $db->prepare("UPDATE `risks` r LEFT JOIN `risk_catalog` rc ON r.risk_catalog_mapping = rc.id JOIN (SELECT id FROM `risk_catalog` WHERE number = 'R-IR-3') rcm SET r.risk_catalog_mapping = rcm.id WHERE rc.number = 'R-GV-2';");
        $stmt->execute();
        $stmt = $db->prepare("DELETE FROM `risk_catalog` WHERE number = 'R-GV-2';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'There is a failure to implement least privileges.' WHERE number = 'R-AC-2';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'Access to privileged functions is inadequate or cannot be controlled.' WHERE number = 'R-AC-3';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'Access is granted to unauthorized individuals, groups or services.' WHERE number = 'R-AC-4';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'Asset(s) is/are lost, damaged or stolen.' WHERE number = 'R-AM-1';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'Unauthorized changes corrupt the integrity of the system / application / service.' WHERE number = 'R-AM-2';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'There is increased latency or a service outage that negatively impacts business operations.' WHERE number = 'R-BC-1';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET number = 'R-BC-3', description = 'User productivity is negatively affected by the incident.' WHERE number = 'R-BC-6';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` JOIN (SELECT value FROM `risk_grouping` WHERE name = 'Business Continuity') risk_grouping SET `grouping` = risk_grouping.value, number = 'R-BC-4', name = 'Information loss / corruption or system compromise due to technical attack', description = 'Malware, phishing, hacking or other technical attacks compromise data, systems, applications or services.' WHERE number = 'R-EX-8';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` JOIN (SELECT value FROM `risk_grouping` WHERE name = 'Business Continuity') risk_grouping SET `grouping` = risk_grouping.value, number = 'R-BC-5', name = 'Information loss / corruption or system compromise due to non‐technical attack ', description = 'Social engineering, sabotage or other non-technical attack compromises data, systems, applications or services.' WHERE number = 'R-EX-9';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'Legal and/or financial damages result from statutory / regulatory / contractual non-compliance.' WHERE number = 'R-EX-5';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'Umitigated technical vulnerabilities exist without compensating controls or other mitigation actions.' WHERE number = 'R-EX-6';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET description = 'System / application / service is compromised affects its confidentiality, integrity,  availability and/or safety.' WHERE number = 'R-EX-7';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET name = 'Inability to support business processes', description = 'Implemented security /privacy practices are insufficient to support the organization\'s secure technologies & processes requirements.', `order` = 1 WHERE number = 'R-GV-1';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET number = 'R-GV-2', name = 'Incorrect controls scoping', description = 'There is incorrect or inadequate controls scoping, which leads to a potential gap or lapse in security / privacy controls coverage.', `order` = 2 WHERE number = 'R-GV-6';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET number = 'TMP', name = 'Lack of roles & responsibilities', description = 'Documented security / privacy roles & responsibilities do not exist or are inadequate.' 
WHERE number = 'R-GV-5';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET number = 'R-GV-5', name = 'Inadequate third-party practices', description = 'Third-party practices do not exist or are inadequate. Procedures fail to meet \"reasonable practices\" expected by industry standards.', `order` = 5 WHERE number = 'R-GV-4';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET number = 'R-GV-4', name = 'Inadequate internal practices ', description = 'Internal practices do not exist or are inadequate. Procedures fail to meet \"reasonable practices\" expected by industry standards.', `order` = 4 WHERE number = 'R-GV-3';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET number = 'R-GV-3', `order` = 3 WHERE number = 'TMP';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` SET number = 'R-GV-8', name = 'Illegal content or abusive action', description = 'There is abusive content / harmful speech / threats of violence / illegal content that negatively affect business operations.', `order` = 8 WHERE number = 'R-GV-7';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` JOIN (SELECT value FROM `risk_grouping` WHERE name = 'Governance') risk_grouping SET `grouping` = risk_grouping.value, number = 'R-GV-6', name = 'Lack of oversight of internal controls', `order` = 6 WHERE number = 'R-SA-3';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `risk_catalog` JOIN (SELECT value FROM `risk_grouping` WHERE name = 'Governance') risk_grouping SET `grouping` = risk_grouping.value, number = 'R-GV-7', name = 'Lack of oversight of third-party controls', `order` = 7 WHERE number = 'R-SA-4';");
        $stmt->execute();
    }

    // If the threat grouping table does not exist
    if (!table_exists('threat_grouping'))
    {
        // Add a table for threat grouping
        echo "Adding a table for threat grouping.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `threat_grouping` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, PRIMARY KEY (`value`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Add new group to threat grouping table
        echo "Add new group to threat grouping table.<br />\n";
        $stmt = $db->prepare("INSERT IGNORE INTO `threat_grouping` (`value`, `name`) VALUES
            (1, 'Natural Threat'),
            (2, 'Man-Made Threat');");
        $stmt->execute();
    }

    // If the threat catalog table does not exist
    if (!table_exists('threat_catalog'))
    {
        // Add a table for threat catalog
        echo "Adding a table for threat catalog.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `threat_catalog` ( `id` int(11) NOT NULL AUTO_INCREMENT, `number` varchar(20) NOT NULL, `grouping` int(11) NOT NULL, `name` varchar(1000) NOT NULL, `description` text NOT NULL, `order` int(11) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Add new rows to threat catalog table
        echo "Add new rows to threat catalog table.<br />\n";
        $stmt = $db->prepare("INSERT IGNORE INTO `threat_catalog` (`id`, `number`, `grouping`, `name`, `description`, `order`) VALUES
            (1, 'NT-1', 1, 'Drought & Water Shortage', 'Regardless of geographic location, periods of reduced rainfall are expected. For non-agricultural industries, drought may not be impactful to operations until it reaches the extent of water rationing.', 1),
            (2, 'NT-2', 1, 'Earthquakes', 'Earthquakes are sudden rolling or shaking events caused by movement under the earth’s surface. Although earthquakes usually last less than one minute, the scope of devastation can be widespread and have long-lasting impact.', 2),
            (3, 'NT-3', 1, 'Fire & Wildfires', 'Regardless of geographic location or even building material, fire is a concern for every business. When thinking of a fire in a building, envision a total loss to all technology hardware, including backup tapes, and all paper files being consumed in the fire.', 3),
            (4, 'NT-4', 1, 'Floods', 'Flooding is the most common of natural hazards and requires an understanding of the local environment, including floodplains and the frequency of flooding events. Location of critical technologies should be considered (e.g., server room is in the basement or first floor of the facility).', 4),
            (5, 'NT-5', 1, 'Hurricanes & Tropical Storms', 'Hurricanes and tropical storms are among the most powerful natural disasters because of their size and destructive potential. In addition to high winds, regional flooding and infrastructure damage should be considered when assessing hurricanes and tropical storms.', 5),
            (6, 'NT-6', 1, 'Landslides & Debris Flow', 'Landslides occur throughout the world and can be caused by a variety of factors including earthquakes, storms, volcanic eruptions, fire, and by human modification of land. Landslides can occur quickly, often with little notice. Location of critical technologies should be considered (e.g., server room is in the basement or first floor of the facility).', 6),
            (7, 'NT-7', 1, 'Pandemic (Disease) Outbreaks', 'Due to the wide variety of possible scenarios, consideration should be given both to the magnitude of what can reasonably happen during a pandemic outbreak (e.g., COVID-19, Influenza, SARS, Ebola, etc.) and what actions the business can be taken to help lessen the impact of a  pandemic on operations.', 7),
            (8, 'NT-8', 1, 'Severe Weather', 'Severe weather is a broad category of meteorological events that include events that range from damaging winds to hail.', 8),
            (9, 'NT-9', 1, 'Space Weather', 'Space weather includes natural events in space that can affect the near-earth environment and satellites. Most commonly, this is associated with solar flares from the Sun, so an understanding of how solar flares may impact the business is of critical importance in assessing this threat.', 9),
            (10, 'NT-10', 1, 'Thunderstorms & Lightning', 'Thunderstorms are most prevalent in the spring and summer months and generally occur during the afternoon and evening hours, but they can occur year-round and at all hours. Many hazardous weather events are associated with thunderstorms. Under the right conditions, rainfall from thunderstorms causes flash flooding and lightning is responsible for equipment damage, fires and fatalities.', 10),
            (11, 'NT-11', 1, 'Tornadoes', 'Tornadoes occur in many parts of the world, including the US, Australia, Europe, Africa, Asia, and South America. Tornadoes can happen at any time of year and occur at any time of day or night, but most tornadoes occur between 4–9 p.m. Tornadoes (with winds up to about 300 mph) can destroy all but the best-built man-made structures.', 11),
            (12, 'NT-12', 1, 'Tsunamis', 'All tsunamis are potentially dangerous, even though they may not damage every coastline they strike. A tsunami can strike anywhere along most of the US coastline. The most destructive tsunamis have occurred along the coasts of California, Oregon, Washington, Alaska and Hawaii.', 12),
            (13, 'NT-13', 1, 'Volcanoes', 'While volcanoes are geographically fixed objects, volcanic fallout can have significant downwind impacts for thousands of miles. Far outside of the blast zone, volcanoes can significantly damage or degrade transportation systems and also cause electrical grids to fail.', 13),
            (14, 'NT-14', 1, 'Winter Storms & Extreme Cold', 'Winter storms is a broad category of meteorological events that include events that range from ice storms, to heavy snowfall, to unseasonably (e.g., record breaking) cold temperatures. Winter storms can significantly impact business operations and transportation systems over a wide geographic region.', 14),
            (15, 'MT-1', 2, 'Civil or Political Unrest', 'Civil or political unrest can be singular or wide-spread events that can be unexpected and unpredictable. These events can occur anywhere, at any time.', 15),
            (16, 'MT-2', 2, 'Hacking & Other Cybersecurity Crimes', 'Unlike physical threats that prompt immediate action (e.g., \"stop, drop, and roll\" in the event of a fire), cyber incidents are often difficult to identify as the incident is occurring. Detection generally occurs after the incident has occurred, with the exception of \"denial of service\" attacks. The spectrum of cybersecurity risks is limitless and threats can have wide-ranging effects on the individual, organizational, geographic, and national levels.', 16),
            (17, 'MT-3', 2, 'Hazardous Materials Emergencies', 'Hazardous materials emergencies are focused on accidental disasters that occur in industrialized nations. These incidents can range from industrial chemical spills to groundwater contamination.', 17),
            (18, 'MT-4', 2, 'Nuclear, Biological and Chemical (NBC) Weapons', 'The use of NBC weapons are in the possible arsenals of international terrorists and it must be a consideration. Terrorist use of a “dirty bomb” — is considered far more likely than use of a traditional nuclear explosive device. This may be a combination a conventional explosive device with radioactive / chemical / biological material and be designed to scatter lethal and sub-lethal amounts of material over a wide area.', 18),
            (19, 'MT-5', 2, 'Physical Crime', 'Physical crime includes \"traditional\" crimes of opportunity. These incidents can range from theft, to vandalism, riots, looting, arson and other forms of criminal activities.', 19),
            (20, 'MT-6', 2, 'Terrorism & Armed Attacks', 'Armed attacks, regardless of the motivation of the attacker, can impact a businesses. Scenarios can range from single actors (e.g., \"disgruntled\" employee) all the way to a coordinated terrorist attack by multiple assailants. These incidents can range from the use of blade weapons (e.g., knives), blunt objects (e.g., clubs), to firearms and explosives.', 20),
            (21, 'MT-7', 2, 'Utility Service Disruption', 'Utility service disruptions are focused on the sustained loss of electricity, Internet, natural gas, water, and/or sanitation services. These incidents can have a variety of causes but  directly impact the fulfillment of utility services that your business needs to operate.', 21);
        ");
        $stmt->execute();
    }

    // Set default custom_display_settings value to user table.
    if (!field_exists_in_table('custom_display_settings', 'user')) {
        echo "Set default custom_display_settings value to user table.<br />\n";
        $custom_display_settings = json_encode(array(
            'id',
            'subject',
            'calculated_risk',
            'submission_date',
            'mitigation_planned',
            'management_review'
        ));
        $stmt = $db->prepare("UPDATE user SET custom_display_settings=:custom_display_settings WHERE custom_display_settings=''");
        $stmt->bindParam(":custom_display_settings", $custom_display_settings, PDO::PARAM_STR, 1000);
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20210305-001 *
 ***************************************/
function upgrade_from_20210305001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20210305-001';

    // Database version upgrading to
    $version_upgrading_to = '20210625-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add contributing_risks_likelihood table
    if (!table_exists('contributing_risks_likelihood')) {

        echo "Adding a table for contributing risks likelihood.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `contributing_risks_likelihood` (`id` int(11) NOT NULL AUTO_INCREMENT, `value` int(11) NOT NULL, `name` varchar(100) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        echo "Adding current levels from the existing Classic Risk Likelihood to 'contributing_risks_likelihood'.<br />\n";
        $stmt = $db->prepare("SELECT * FROM `likelihood` ORDER BY `value`;");
        $stmt->execute();
        $array = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($array as $row)
        {
            $stmt = $db->prepare("INSERT INTO `contributing_risks_likelihood` (`value`, `name`) VALUES (:value, :name);");
            $stmt->bindParam(":value", $row['value']);
            $stmt->bindParam(":name", $row['name']);
            $stmt->execute();
        }
    }
    // Add contributing_risks_impact table
    if (!table_exists('contributing_risks_impact')) {

        echo "Adding a table for contributing risks impact.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `contributing_risks_impact` (`id` int(11) NOT NULL AUTO_INCREMENT, `contributing_risks_id` int(11) NOT NULL, `value` int(11) NOT NULL, `name` varchar(100) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        echo "Adding current levels from the existing Classic Risk Impact to 'contributing_risks_impact'.<br />\n";
        $stmt = $db->prepare("SELECT * FROM `impact` ORDER BY `value`;");
        $stmt->execute();
        $impacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $stmt = $db->prepare("SELECT * FROM `contributing_risks` ORDER BY `id`;");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($impacts as $impact)
        {
            foreach($rows as $row){
                $stmt = $db->prepare("INSERT INTO `contributing_risks_impact` (`contributing_risks_id`, `value`, `name`) VALUES (:contributing_risks_id, :value, :name);");
                $stmt->bindParam(":contributing_risks_id", $row['id']);
                $stmt->bindParam(":value", $impact['value']);
                $stmt->bindParam(":name", $impact['name']);
                $stmt->execute();
            }
        }
    }
    
    // Add default current maturity setting
    echo "Adding default current maturity setting.<br />\n";
    update_or_insert_setting("default_current_maturity", 0);

    // Add default desired maturity setting
    echo "Adding default desired maturity setting.<br />\n";
    update_or_insert_setting("default_desired_maturity", 3);

    // Add default highcharts delivery method setting
    echo "Adding default highcharts delivery method setting.<br />\n";
    update_or_insert_setting("highcharts_delivery_method", "cdn");

    // If the control_number field exists in the risks table.
    if (!field_exists_in_table('control_number', 'risks')) {
        echo "Expanding the control_number field from 20 characters to 50 characters.<br />\n";
	$stmt = $db->prepare("ALTER TABLE risks CHANGE control_number control_number varchar(50) DEFAULT NULL;");
	$stmt->execute();
    }

    // Change name for "Able to Modify Existing Risks"
    echo "Changing permission name from 'Able to Modify Existing Risks' to 'Able to Modify Existing Risk Details'.<br />\n";
    $stmt = $db->prepare("UPDATE permissions set name='Able to Modify Risk Details' WHERE `key`='modify_risks';");
    $stmt->execute();

    // Add the backups table
    if (!table_exists('backups')) {

        echo "Adding a table for tracking backups.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `backups` (`id` int(11) NOT NULL AUTO_INCREMENT, `random_id` varchar(50) NOT NULL, `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, `app_zip_file_name` TEXT NOT NULL, `db_zip_file_name` TEXT NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();
    }

    // Add a setting to track auto backup
    echo "Enabling new auto-backup feature.<br />\n";
    update_setting('backup_auto', 'true');
    $backup_path = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'backup-simplerisk';
    update_setting('backup_path', $backup_path);
    update_setting('backup_schedule', 'daily');
    update_setting('backup_remove', '1');

    // Add template_group_id field to risk table
    if (!field_exists_in_table('template_group_id', 'risks')) {
        echo "Adding a field template_group_id to risk table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD `template_group_id` INT NOT NULL DEFAULT '1';");
        $stmt->execute();
    }

    // Set all teams to admistrator users
    echo "Updating all admin users to be assigned to every team.<br />\n";
    set_all_teams_to_administrators();

    // Remove unnecessary files
    echo "Removing unnecessary files.<br />\n";
    $remove_files = array(
            realpath(__DIR__ . '/../composer.json'),
            realpath(__DIR__ . '/../package.json'),
            realpath(__DIR__ . '/../config.rb'),
            realpath(__DIR__ . '/../Gemfile'),
	    realpath(__DIR__ . '/../management/plan-projects.php'),
	    realpath(__DIR__ . '/../js/min/plan-project-min.js'),
    );

    foreach ($remove_files as $file)
    {
        // If the file exists
        if (file_exists($file))
        {
            // Remove the file
            unlink($file);
        }
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20210625-001 *
 ***************************************/
function upgrade_from_20210625001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20210625-001';

    // Database version upgrading to
    $version_upgrading_to = '20210630-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Remove unnecessary files
    echo "Removing unnecessary files.<br />\n";
    $remove_files = array(
	    realpath(__DIR__ . '/../includes/PHPMailer'),
    );

    foreach ($remove_files as $directory)
    {
        // If the file exists
        if (is_dir($directory))
        {
	    // Remove the directory
	    delete_dir($directory);
        }
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20210630-001 *
 ***************************************/
function upgrade_from_20210630001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20210630-001';

    // Database version upgrading to
    $version_upgrading_to = '20210713-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20210713-001 *
 ***************************************/
function upgrade_from_20210713001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20210713-001';

    // Database version upgrading to
    $version_upgrading_to = '20210802-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add default jquery delivery method setting
    echo "Adding default jquery delivery method setting.<br />\n";
    update_or_insert_setting("jquery_delivery_method", "cdn");

    // Remove unnecessary files
    echo "Removing unnecessary files.<br />\n";
    $remove_files = array(
      realpath(__DIR__ . '/../js/jquery-3.3.1.min.js'),
	    realpath(__DIR__ . '/../js/jquery.min.js'),
	    realpath(__DIR__ . '/../js/jquery-ui.js'),
	    realpath(__DIR__ . '/../js/jquery-ui.min.js'),
    );

    foreach ($remove_files as $file)
    {
        // If the file exists
        if (file_exists($file))
        {
            // Remove the file
            unlink($file);
        }
    }

    // Add the document_status table
    if (!table_exists('document_status'))
    {
        echo "Adding a table for document status.<br />\n";
	      $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `document_status` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(100) NOT NULL, PRIMARY KEY (`value`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	      $stmt->execute();

	      echo "Adding values to the document status table.<br />\n";
	      $stmt = $db->prepare("INSERT INTO `document_status` (`name`) VALUES ('Draft'), ('In Review'), ('Approved');");
	      $stmt->execute();
    }

    // Add the document_status field to the documents table
    if (!field_exists_in_table('document_status', 'documents'))
    {
        echo "Adding a document_status field to the documents table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `document_status` int(11) DEFAULT 1 AFTER `status`;");
        $stmt->execute();

        echo "Copying values from status to document status.<br />\n";
        $stmt = $db->prepare("UPDATE `documents` SET document_status=2 WHERE status='InReview';");
        $stmt->execute();
        $stmt = $db->prepare("UPDATE `documents` SET document_status=3 WHERE status='Approved';");
        $stmt->execute();
    }

    // Remove the status field from the documents table
    if (field_exists_in_table('status', 'documents'))
    {
        echo "Removing the status field from the documents table<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` DROP COLUMN `status`;");
        $stmt->execute();
    }

    // Add a associated risks field to document exceptions table
    if (!field_exists_in_table('associated_risks', 'document_exceptions')) {
        echo "Add a associated risks field to document exceptions table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `document_exceptions` ADD `associated_risks` TEXT NOT NULL;");
        $stmt->execute();
    }

    // Change a next_review field type to VARCHAR
    if (field_exists_in_table('risk_catalog_mapping', 'risks'))
    {
        echo "Change a risk_catalog_mapping field type to VARCHAR.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` CHANGE `risk_catalog_mapping` `risk_catalog_mapping` VARCHAR(255) NOT NULL;");
        $stmt->execute();
    }

    // Add a threat_catalog_mapping field to risks table
    if (!field_exists_in_table('threat_catalog_mapping', 'risks')) {
        echo "Adding a threat catalog mapping field to risks table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` ADD `threat_catalog_mapping` VARCHAR(255) NOT NULL AFTER `risk_catalog_mapping`;");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20210802-001 *
 ***************************************/
function upgrade_from_20210802001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20210802-001';

    // Database version upgrading to
    $version_upgrading_to = '20210806-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20210806-001 *
 ***************************************/
function upgrade_from_20210806001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20210806-001';

    // Database version upgrading to
    $version_upgrading_to = '20210930-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add a status field to the document_exceptions table
    if (!field_exists_in_table('status', 'document_exceptions')) {
        echo "Adding a status field to the document_exceptions table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `document_exceptions` ADD `status` INT(11) NOT NULL DEFAULT 1 AFTER `associated_risks`;");
        $stmt->execute();
    }

    // Add the document_exceptions_status table
    if (!table_exists('document_exceptions_status'))
    {
        echo "Adding a table for document exceptions status.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `document_exceptions_status` (`value` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(100) NOT NULL, PRIMARY KEY (`value`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        echo "Adding values to the document exceptions status table.<br />\n";
        $stmt = $db->prepare("INSERT INTO `document_exceptions_status` (`name`) VALUES ('Open'), ('Closed');");
        $stmt->execute();
    }

    // Add the validation_files table
    if (!table_exists('validation_files'))
    {
        echo "Adding a table for validation files.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `validation_files` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `mitigation_id` int(11) NOT NULL,
          `control_id` int(11) NOT NULL,
          `name` varchar(100) NOT NULL,
          `type` varchar(30) NOT NULL,
          `size` int(11) NOT NULL,
          `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
          `user` int(11) NOT NULL,
          `content` longblob NOT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();
    }

    // Add the control_type table
    if (!table_exists('control_type')) {
        echo "Creating the control type table.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `control_type` (
          `value` int(11) NOT NULL AUTO_INCREMENT,
          `name` mediumtext NOT NULL,
          PRIMARY KEY(value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        $stmt = $db->prepare("INSERT INTO `control_type` (`value`, `name`) VALUES
            (1, 'Standalone'),
            (2, 'Project'),
            (3, 'Enterprise');");
        $stmt->execute();
    }

    // Add the framework_control_type_mappings table
    if (!table_exists('framework_control_type_mappings')) {
        echo "Creating the framework_control_type_mappings table.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `framework_control_type_mappings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `control_id` int(11) NOT NULL,
          `control_type_id` int(11) NOT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();
    }

    $stmt = $db->prepare("SELECT * FROM `framework_controls` WHERE deleted=0;");
    $stmt->execute();
    $array = $stmt->fetchAll();

    // For each item in the array
    foreach ($array as $row)
    {
        $stmt = $db->prepare("INSERT INTO `framework_control_type_mappings` (`control_id`, `control_type_id`) VALUES (:control_id, 1);");
        $stmt->bindParam(":control_id", $row['id']);
        $stmt->execute();
    }

    // Add control_status field to framework_controls table
    if (!field_exists_in_table('control_status', 'framework_controls')) {
        echo "Adding control_status field to framework_controls table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `framework_controls` ADD `control_status` tinyint(1) DEFAULT 1 AFTER `control_priority`;");
        $stmt->execute();
    }

    if (!table_exists('data_classification')) {
        echo "Creating the data_classification table.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `data_classification` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` MEDIUMTEXT NOT NULL,
          `order` int(11) NOT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        $stmt = $db->prepare("INSERT INTO `data_classification` (`name`, `order`) VALUES
            ('Public', 1),
            ('Internal', 2),
            ('Confidential', 3),
            ('Restricted', 4);");
        $stmt->execute();
    }
    // Add a due_date field to the projects table
    if (!field_exists_in_table('due_date', 'projects')) {
        echo "Adding a due_date field to the projects table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `projects` ADD `due_date` TIMESTAMP NULL DEFAULT NULL AFTER `name`;");
        $stmt->execute();
    }
    // Add a consultant field to the projects table
    if (!field_exists_in_table('consultant', 'projects')) {
        echo "Adding a consultant field to the projects table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `projects` ADD `consultant` int(11) DEFAULT NULL AFTER `due_date`;");
        $stmt->execute();
    }
    // Add a business_owner field to the projects table
    if (!field_exists_in_table('business_owner', 'projects')) {
        echo "Adding a business_owner field to the projects table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `projects` ADD `business_owner` int(11) DEFAULT NULL AFTER `consultant`;");
        $stmt->execute();
    }
    // Add a data_classification field to the projects table
    if (!field_exists_in_table('data_classification', 'projects')) {
        echo "Adding a data_classification field to the projects table.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `projects` ADD `data_classification` int(11) DEFAULT NULL AFTER `business_owner`;");
        $stmt->execute();
    }

    // Add default bootstrap delivery method setting
    echo "Adding default bootstrap delivery method setting.<br />\n";
    update_or_insert_setting("bootstrap_delivery_method", "cdn");

    // Remove unnecessary files
    echo "Removing unnecessary files.<br />\n";
    $remove_files = array(
	    realpath(__DIR__ . '/../.htaccess'),
	    realpath(__DIR__ . '/../js/bootstrap.min.js'),
    );

    foreach ($remove_files as $file)
    {
        // If the file exists
        if (file_exists($file))
        {
            // Remove the file
            unlink($file);
        }
    }

    echo "Adding the default value of 300 characters for the 'Maximum risk subject length' setting.<br />\n";
    update_or_insert_setting('maximum_risk_subject_length', 300);

    // Only have to change to LONGTEXT if it's a varchar
    if (!encryption_extra() && strtolower(getTypeOfColumn('risks', 'subject')) === 'varchar') {
        echo "Updated risks table's subject to be a LONGTEXT.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risks` CHANGE `subject` `subject` LONGTEXT NOT NULL;");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20210930-001 *
 ***************************************/
function upgrade_from_20210930001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20210930-001';

    // Database version upgrading to
    $version_upgrading_to = '20211010-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20211010-001 *
 ***************************************/
function upgrade_from_20211010001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20211010-001';

    // Database version upgrading to
    $version_upgrading_to = '20211027-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    if (!table_exists('framework_control_test_results_to_risks')) {
        //echo "Creating the framework_control_test_results_to_risks table.<br />\n";
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `framework_control_test_results_to_risks` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `test_results_id` int(11) DEFAULT NULL,
          `risk_id` int(11) DEFAULT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20211027-001 *
 ***************************************/
function upgrade_from_20211027001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20211027-001';

    // Database version upgrading to
    $version_upgrading_to = '20211115-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20211115-001 *
 ***************************************/
function upgrade_from_20211115001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20211115-001';

    // Database version upgrading to
    $version_upgrading_to = '20211230-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    if (!index_exists_on_table('risk_id', 'risk_scoring_history')) {
        echo "Adding index 'risk_id' to table 'risk_scoring_history'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risk_scoring_history` ADD INDEX(`risk_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('risk_id', 'residual_risk_scoring_history')) {
        echo "Adding index 'risk_id' to table 'residual_risk_scoring_history'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `residual_risk_scoring_history` ADD INDEX(`risk_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('contributing_risks_id', 'contributing_risks_impact')) {
        echo "Adding index 'contributing_risks_id' to table 'contributing_risks_impact'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `contributing_risks_impact` ADD INDEX(`contributing_risks_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('risk_scoring_id', 'risk_scoring_contributing_impacts')) {
        echo "Adding index 'risk_scoring_id' to table 'risk_scoring_contributing_impacts'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risk_scoring_contributing_impacts` ADD INDEX(`risk_scoring_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('contributing_risk_id', 'risk_scoring_contributing_impacts')) {
        echo "Adding index 'contributing_risk_id' to table 'risk_scoring_contributing_impacts'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risk_scoring_contributing_impacts` ADD INDEX(`contributing_risk_id`);");
        $stmt->execute();
    }

    if (!field_exists_in_table('order', 'risk_grouping')) {

        // Add the column to mark the default group for risk catalog items that doesn't have an actual group assigned
        echo "Adding column to mark the default 'risk_grouping' for 'risk_catalog' items that doesn't have an actual group assigned.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risk_grouping` ADD `default` TINYINT(1) DEFAULT 0 NOT NULL AFTER `name`;");
        $stmt->execute();

        // Get the risk groups for setting the order
        $stmt = $db->prepare("
            SELECT `value` FROM `risk_grouping` ORDER BY `name`;
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Add the field for storing the risk group order
        echo "Adding column for storing the order of 'risk_grouping' items.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `risk_grouping` ADD `order` INT(11) NOT NULL AFTER `default`;");
        $stmt->execute();

        // Add the default group
        echo "Adding the default 'risk_grouping'.<br />\n";
        $stmt = $db->prepare("INSERT INTO `risk_grouping`(`name`, `default`, `order`) VALUE ('Unassigned Items', 1, 0);");
        $stmt->execute();
        $default_group_id = $db->lastInsertId();

        // Set the order of the groups but let the default group be the first one
        echo "Setting the order of the 'risk_grouping' entries.<br />\n";
        foreach ($data as $order => $value) {
            $stmt = $db->prepare("UPDATE `risk_grouping` SET `order` = :order + 1 WHERE `value` = :value;");
            $stmt->bindParam(":order", $order, PDO::PARAM_INT);
            $stmt->bindParam(":value", $value, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Find risk catalog items that have no group assigned
        $stmt = $db->prepare("
            SELECT
            	`id`
            FROM `risk_catalog` rc
            	LEFT JOIN `risk_grouping` rg ON `rg`.`value` = `rc`.`grouping`
            WHERE
            	`rg`.`value` IS NULL;
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($data) {
            echo "Assigning groupless 'risk_catalog' entries to the default 'risk_grouping'.<br />\n";
            // Assign risk catalog items that have no group assigned to this new default group
            foreach ($data as $id) {
                $stmt = $db->prepare("UPDATE `risk_catalog` SET `grouping` = :group WHERE `id` = :id;");
                $stmt->bindParam(":group", $default_group_id, PDO::PARAM_INT);
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
    }

    if (!field_exists_in_table('order', 'threat_grouping')) {
        // Get the threat groups for setting the order
        $stmt = $db->prepare("
            SELECT `value` FROM `threat_grouping` ORDER BY `name`;
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Add the field for storing the threat group order
        echo "Adding column for storing the order of 'threat_grouping' items.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `threat_grouping` ADD `order` INT(11) NOT NULL AFTER `name`;");
        $stmt->execute();

        // Set the order of the groups
        echo "Setting the order of the 'threat_grouping' entries.<br />\n";
        foreach ($data as $order => $value) {
            $stmt = $db->prepare("UPDATE `threat_grouping` SET `order` = :order WHERE `value` = :value;");
            $stmt->bindParam(":order", $order, PDO::PARAM_INT);
            $stmt->bindParam(":value", $value, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20211230-001 *
 ***************************************/
function upgrade_from_20211230001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20211230-001';

    // Database version upgrading to
    $version_upgrading_to = '20220122-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Update desired_frequency value to test_frequency
    echo "Updating desired_frequency value to test_frequency.<br />\n";
    $stmt = $db->prepare("UPDATE `framework_control_tests` SET `desired_frequency` = `test_frequency`;");
    $stmt->execute();

    // Set SSL Certfiicate check to enabled by default
    echo "Setting SSL certificate check to enabled by default.<br />\n";
    add_setting("ssl_certificate_check", "1");

    if (!field_exists_in_table('default', 'threat_grouping')) {
        // Get the threat groups for setting the order
        $stmt = $db->prepare("
            SELECT `value` FROM `threat_grouping` ORDER BY `name`;
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Add the column to mark the default group for threat catalog items that doesn't have an actual group assigned
        echo "Adding column to mark the default 'threat_grouping' for 'threat_catalog' items that doesn't have an actual group assigned.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `threat_grouping` ADD `default` TINYINT(1) DEFAULT 0 NOT NULL AFTER `name`;");
        $stmt->execute();

        // Add the default group
        echo "Adding the default 'threat_grouping'.<br />\n";
        $stmt = $db->prepare("INSERT INTO `threat_grouping`(`name`, `default`, `order`) VALUE ('Unassigned Items', 1, 0);");
        $stmt->execute();
        $default_group_id = $db->lastInsertId();

        // Set the order of the groups but let the default group be the first one
        echo "Setting the order of the 'threat_grouping' entries.<br />\n";
        foreach ($data as $order => $value) {
            $stmt = $db->prepare("UPDATE `threat_grouping` SET `order` = :order + 1 WHERE `value` = :value;");
            $stmt->bindParam(":order", $order, PDO::PARAM_INT);
            $stmt->bindParam(":value", $value, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Find threat catalog items that have no group assigned
        $stmt = $db->prepare("
            SELECT
            	`id`
            FROM `threat_catalog` tc
            	LEFT JOIN `threat_grouping` tg ON `tg`.`value` = `tc`.`grouping`
            WHERE
            	`tg`.`value` IS NULL;
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($data) {
            echo "Assigning groupless 'threat_catalog' entries to the default 'threat_grouping'.<br />\n";
            // Assign threat catalog items that have no group assigned to this new default group
            foreach ($data as $id) {
                $stmt = $db->prepare("UPDATE `threat_catalog` SET `grouping` = :group WHERE `id` = :id;");
                $stmt->bindParam(":group", $default_group_id, PDO::PARAM_INT);
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20220122-001 *
 ***************************************/
function upgrade_from_20220122001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20220122-001';

    // Database version upgrading to
    $version_upgrading_to = '20220306-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    if (!table_exists('graphical_saved_selections')) {
        // Add the graphical_saved_selections table
        echo "Adding the table to graphical saved selections.<br />\n";
        $stmt = $db->prepare("
            CREATE TABLE `graphical_saved_selections` (
              `value` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `type` enum('private','public') NOT NULL,
              `name` varchar(100) NOT NULL,
              `graphical_display_settings` varchar(1000) NOT NULL,
              PRIMARY KEY(value)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    if (!field_exists_in_table('submitted_by', 'documents')) {
        // Adding the submitted_by field to the documents table to be able to track who submitted the document
        echo "Adding the submitted_by field to the documents table to be able to track who submitted the document.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `submitted_by` INT(11) DEFAULT 0 NOT NULL AFTER `id`;");
        $stmt->execute();
    }
    
    if (!field_exists_in_table('updated_by', 'documents')) {
        // Adding the updated_by field to the documents table to be able to track who updated the document
        echo "Adding the updated_by field to the documents table to be able to track who updated the document.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `documents` ADD `updated_by` INT(11) DEFAULT 0 NOT NULL AFTER `submitted_by`;");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}


/***************************************
 * FUNCTION: UPGRADE FROM 20220306-001 *
 ***************************************/
function upgrade_from_20220306001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20220306-001';

    // Database version upgrading to
    $version_upgrading_to = '20220401-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    if (!index_exists_on_table('rsci_index', 'risk_scoring_contributing_impacts')) {
        echo "Adding index 'rsci_index' to table 'risk_scoring_contributing_impacts'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX rsci_index ON `risk_scoring_contributing_impacts`(`risk_scoring_id`, `contributing_risk_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('rsci_index2', 'risk_scoring_contributing_impacts')) {
        echo "Adding index 'rsci_index2' to table 'risk_scoring_contributing_impacts'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX rsci_index2 ON `risk_scoring_contributing_impacts`(`contributing_risk_id`, `risk_scoring_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('cri_index', 'contributing_risks_impact')) {
        echo "Adding index 'cri_index' to table 'contributing_risks_impact'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX cri_index ON `contributing_risks_impact`(`contributing_risks_id`, `value`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('cri_index2', 'contributing_risks_impact')) {
        echo "Adding index 'cri_index2' to table 'contributing_risks_impact'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX cri_index2 ON `contributing_risks_impact`(`value`, `contributing_risks_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('crl_index', 'contributing_risks_likelihood')) {
        echo "Adding index 'crl_index' to table 'contributing_risks_likelihood'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX crl_index ON `contributing_risks_likelihood`(`value`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('likelihood_index', 'likelihood')) {
        echo "Adding index 'likelihood_index' to table 'likelihood'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX likelihood_index ON `likelihood`(`value`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('impact_index', 'impact')) {
        echo "Adding index 'impact_index' to table 'impact'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX impact_index ON `impact`(`value`);");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20220401-001 *
 ***************************************/
function upgrade_from_20220401001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20220401-001';

    // Database version upgrading to
    $version_upgrading_to = '20220527-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Add a custom_risks_and_issues_settings field to user table
    if (!field_exists_in_table('custom_risks_and_issues_settings', 'user')) {
        echo "Adding a custom_risks_and_issues_settings field to user table.<br />\n";
        $stmt = $db->prepare('ALTER TABLE `user` ADD `custom_risks_and_issues_settings` VARCHAR(2000) NULL DEFAULT NULL;');
        $stmt->execute();
    }

    /* Additional indexes on columns used in Dynamic Risk Report's query */
    if (!index_exists_on_table('rsh_last_update_idx', 'risk_scoring_history')) {
        echo "Adding index 'rsh_last_update_idx' to table 'risk_scoring_history'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX rsh_last_update_idx ON `risk_scoring_history`(`last_update`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('rrsh_last_update_idx', 'residual_risk_scoring_history')) {
        echo "Adding index 'rrsh_last_update_idx' to table 'residual_risk_scoring_history'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX rrsh_last_update_idx ON `residual_risk_scoring_history`(`last_update`);");
        $stmt->execute();
    }
    
    if (!index_exists_on_table('risk2team_risk_id', 'risk_to_team')) {
        echo "Adding index 'risk2team_risk_id' to table 'risk_to_team'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX risk2team_risk_id ON `risk_to_team`(`risk_id`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('risk2team_team_id', 'risk_to_team')) {
        echo "Adding index 'risk2team_team_id' to table 'risk_to_team'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX risk2team_team_id ON `risk_to_team`(`team_id`);");
        $stmt->execute();
    }
    
    if (!index_exists_on_table('mtg2team_mtg_id', 'mitigation_to_team')) {
        echo "Adding index 'mtg2team_mtg_id' to table 'mitigation_to_team'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX mtg2team_mtg_id ON `mitigation_to_team`(`mitigation_id`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('mtg2team_team_id', 'mitigation_to_team')) {
        echo "Adding index 'mtg2team_team_id' to table 'mitigation_to_team'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX mtg2team_team_id ON `mitigation_to_team`(`team_id`);");
        $stmt->execute();
    }
    
    if (index_exists_on_table('risk_id', 'mitigation_accept_users')) {
        echo "Dropping index 'risk_id' to table 'mitigation_accept_users'.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `mitigation_accept_users` DROP INDEX `risk_id`;");
        $stmt->execute();
    }
    if (!index_exists_on_table('mau_risk_id_idx', 'mitigation_accept_users')) {
        echo "Adding index 'mau_risk_id_idx' to table 'mitigation_accept_users'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX mau_risk_id_idx ON `mitigation_accept_users`(`risk_id`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('mau_user_idx', 'mitigation_accept_users')) {
        echo "Adding index 'mau_user_idx' to table 'mitigation_accept_users'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX mau_user_idx ON `mitigation_accept_users`(`user_id`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('mau_user_risk_idx', 'mitigation_accept_users')) {
        echo "Adding index 'mau_user_risk_idx' to table 'mitigation_accept_users'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX mau_user_risk_idx ON `mitigation_accept_users`(`user_id`, `risk_id`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('mau_risk_user_idx', 'mitigation_accept_users')) {
        echo "Adding index 'mau_risk_user_idx' to table 'mitigation_accept_users'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX mau_risk_user_idx ON `mitigation_accept_users`(`risk_id`, `user_id`);");
        $stmt->execute();
    }

    if (!index_exists_on_table('risk_levels_value_idx', 'risk_levels')) {
        echo "Adding index 'risk_levels_value_idx' to table 'risk_levels'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX risk_levels_value_idx ON `risk_levels`(`value`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('risk_levels_name_idx', 'risk_levels')) {
        echo "Adding index 'risk_levels_name_idx' to table 'risk_levels'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX risk_levels_name_idx ON `risk_levels`(`name`);");
        $stmt->execute();
    }
    
    if (!index_exists_on_table('framework_controls_deleted_idx', 'framework_controls')) {
        echo "Adding index 'framework_controls_deleted_idx' to table 'framework_controls'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX framework_controls_deleted_idx ON `framework_controls`(`deleted`);");
        $stmt->execute();
    }
    
    if (!index_exists_on_table('mtg2ctrl_mtg_idx', 'mitigation_to_controls')) {
        echo "Adding index 'mtg2ctrl_mtg_idx' to table 'mitigation_to_controls'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX mtg2ctrl_mtg_idx ON `mitigation_to_controls`(`mitigation_id`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('mtg2ctrl_control_idx', 'mitigation_to_controls')) {
        echo "Adding index 'mtg2ctrl_control_idx' to table 'mitigation_to_controls'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX mtg2ctrl_control_idx ON `mitigation_to_controls`(`control_id`);");
        $stmt->execute();
    }
    
    if (!index_exists_on_table('rsci_impact_idx', 'risk_scoring_contributing_impacts')) {
        echo "Adding index 'rsci_impact_idx' to table 'risk_scoring_contributing_impacts'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX rsci_impact_idx ON `risk_scoring_contributing_impacts`(`impact`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('cri_value_idx', 'contributing_risks_impact')) {
        echo "Adding index 'cri_value_idx' to table 'contributing_risks_impact'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX cri_value_idx ON `contributing_risks_impact`(`value`);");
        $stmt->execute();
    }
    
    if (!index_exists_on_table('closures_close_reason_idx', 'closures')) {
        echo "Adding index 'closures_close_reason_idx' to table 'closures'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX closures_close_reason_idx ON `closures`(`close_reason`);");
        $stmt->execute();
    }
    if (!index_exists_on_table('closures_user_id_idx', 'closures')) {
        echo "Adding index 'closures_user_id_idx' to table 'closures'.<br />\n";
        $stmt = $db->prepare("CREATE INDEX closures_user_id_idx ON `closures`(`user_id`);");
        $stmt->execute();
    }
    /* End of additional indexes on columns used in Dynamic Risk Report's query */
    
    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20220527-001 *
 ***************************************/
function upgrade_from_20220527001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20220527-001';

    // Database version upgrading to
    $version_upgrading_to = '20220701-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Change a location field to varchar in assets table
    echo "Changing a location field to varchar in assets table.<br />\n";
    $stmt = $db->prepare('ALTER TABLE `assets` CHANGE `location` `location` VARCHAR(1000) NOT NULL;');
    $stmt->execute();

    /* End of additional indexes on columns used in Dynamic Risk Report's query */
    
    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20220701-001 *
 ***************************************/
function upgrade_from_20220701001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20220701-001';

    // Database version upgrading to
    $version_upgrading_to = '20220823-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

    // Compile the list of unnecessary directories
    echo "Removing unnecessary directories.<br />\n";
    $remove_directories = array(
        realpath(__DIR__ . '/vendor/box'),
        realpath(__DIR__ . '/assessments/risks.php'),
    );

    // Remove the unnecessary directories
    foreach ($remove_directories as $directory)
    {
        // If the directory exists
        if (is_dir($directory))
        {
            // Remove the directory
            delete_dir($directory);
        }
    }

    // Altering the type of the comment column to be able to fit in longer comments
    if (getTypeOfColumn('pending_risks', 'comment') != 'text') {
        echo "Updating the type of the `comment` column of the `pending_risks` table to be able to fit in longer comments.<br />\n";
        $stmt = $db->prepare("ALTER TABLE `pending_risks` MODIFY `comment` TEXT NOT NULL;");
        $stmt->execute();
    }

    // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/***************************************
 * FUNCTION: UPGRADE FROM 20220823-001 *
 ***************************************/
function upgrade_from_20220823001($db)
{
    // Database version to upgrade
    $version_to_upgrade = '20220823-001';

    // Database version upgrading to
    $version_upgrading_to = '20220909-001';

    echo "Beginning SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";

        // To make sure page loads won't fail after the upgrade
    // as this session variable is not set by the previous version of the login logic
    $_SESSION['latest_version_app'] = latest_version('app');

    // Update the database version
    update_database_version($db, $version_to_upgrade, $version_upgrading_to);
    echo "Finished SimpleRisk database upgrade from version " . $version_to_upgrade . " to version " . $version_upgrading_to . "<br />\n";
}

/******************************
 * FUNCTION: UPGRADE DATABASE *
 ******************************/
function upgrade_database()
{
    global $escaper;

    // Connect to the database
    $db = db_open();

    // If the grant check for the database user is successful
    if (check_grants($db))
    {
        // Get the current application and database versions
        $app_version = current_version("app");
        $db_version = current_version("db");
        
        // If the application version is not the same as the database version
        if ($app_version != $db_version)
        {
            // Get the upgrade function to call for this release version
            $release_function_name = get_database_upgrade_function_for_release($db_version);

            // If a release function name was provided
            if ($release_function_name != false)
            {
                // If the release function exists
                if (function_exists($release_function_name))
                {
                    // Call the release function
                    call_user_func($release_function_name, $db);

                    // Recursively run the database upgrade for the next release
                    upgrade_database();

		            // If the composer.json file exists
                    $file = realpath(__DIR__ . '/../composer.json');
                    if (file_exists($file))
                    {
                        // If we successfully deleted the file
                        if (delete_file($file))
                        {
                            echo "Deleted the composer.json file.<br />\n";
                        }
                    }

                    // If the composer.lock file exists
                    $file = realpath(__DIR__ . '/../composer.lock');
                    if (file_exists($file))
                    {
                        // If we successfully deleted the file
                        if (delete_file($file))
                        {
                            echo "Deleted the composer.lock file.<br />\n";
                        }
                    }

		            // If the installed.json file exists
                    $file = realpath(__DIR__ . '/../vendor/composer/installed.json');
                    if (file_exists($file))
                    {
                        // If we successfully deleted the file
                        if (delete_file($file))
                        {
                            echo "Deleted the installed.json file.<br />\n";
                        }
                    }
                }
                else echo "The specified database upgrade function could not be found.<br />\n";
            }
            else echo "Unable to find an upgrade function for the current SimpleRisk database version.<br />\n";
        }
        // The applicaton and database are updated to the same version
        else
        {
            echo "You are currently running the version of the SimpleRisk database that goes along with your application version.<br />\n";
        }
    }
    // If the grant check was not successful
    else
    {
        echo "A check of your database user privileges found that one or more of the necessary grants were missing.  Please ensure that you have granted SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, REFERENCES AND INDEX permissions to the '" . $escaper->escapeHtml(DB_USERNAME) . "' user.<br />\n";
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

/*******************************************************
 * FUNCTION: GET DATABASE UPGRADE FUNCTION FOR RELEASE *
 *******************************************************/
function get_database_upgrade_function_for_release($release)
{
	global $releases;

	// If the release is in the releases array
	if (in_array($release, $releases))
	{
		// Remove the hyphen from the release
		$release = str_replace("-", "", $release);

		// Get the release function name
		$release_function_name = "upgrade_from_" . $release;

		// Return the release function name
		return $release_function_name;
	}
	// The release is not a valid release
	else return false;
}

?>
