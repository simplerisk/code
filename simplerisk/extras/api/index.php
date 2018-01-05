<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * utilize an API for queries to and from the database.             *
 ********************************************************************/

// Extra Version
define('API_EXTRA_VERSION', '20180104-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/services.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_api_extra_database();

/******************************
 * FUNCTION: ENABLE API EXTRA *
 ******************************/
function enable_api_extra()
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'api', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Create a salt to use for keys
    $salt = generate_token(20);
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'api_salt', `value` = :salt");
    $stmt->bindParam(":salt", $salt, PDO::PARAM_STR, 20);
    $stmt->execute();

    // Create the table to track API keys
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `api_keys` (`user_id` int(11) NOT NULL PRIMARY KEY, `api_key_hash` varchar(256) NOT NULL)");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*******************************
 * FUNCTION: DISABLE API EXTRA *
 *******************************/
function disable_api_extra()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'api'");
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/*************************
 * FUNCTION: API VERSION *
 *************************/
if (!function_exists('api_version')) {
    function api_version()
    {
        // Return the version
        return API_EXTRA_VERSION;
    }
}

/**************************
 * FUNCTION: GET API SALT *
 **************************/
function get_api_salt()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT value FROM `settings` WHERE `name` = 'api_salt'");
        $stmt->execute();
    $salt = $stmt->fetch();

        // Close the database connection
        db_close($db);

    // Return the salt
    return $salt[0];
}

/*************************
 * FUNCTION: DISPLAY API *
 *************************/
function display_api()
{
        global $escaper;
        global $lang;

        echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . api_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";
}

/*********************************
 * FUNCTION: DISPLAY API PROFILE *
 *********************************/
function display_api_profile()
{
    global $escaper;
    global $lang;

    // Get the user's API key
    $api_key = get_user_api_key();

    // If the API key was found for the user
    if ($api_key != "")
    {
        // If the user asked to generate an API key
        if (isset($_POST['generate_api_key']))
        {
            // Create a random 64 character API key
            $api_key = generate_token(64);

            // Set the API key for the user
            set_api_key($api_key, $_SESSION['user']);

            // Show the API key
            echo "<tr><td>" . $escaper->escapeHtml($lang['APIKey']) . ":&nbsp;</td><td><input type=\"text\" name=\"api_key\" value=\"" . $escaper->escapeHtml($api_key) . "\" /></td></tr>\n";
        }
        // If the user asked to invalidate an API key
        else if (isset($_POST['invalidate_api_key']))
        {
            // Invalidate the API key
            remove_api_key($_SESSION['uid']);

            // Display the key generation button
            echo "<tr><td>" . $escaper->escapeHtml($lang['APIKey']) . ":&nbsp;</td><td><form name=\"api_key\" method=\"post\"><input type=\"submit\" name=\"generate_api_key\" value=\"" . $escaper->escapeHtml($lang['GenerateAPIKey']) . "\" /></form></td></tr>\n";
        }
        // Otherwise, show the regenerate key and delete key buttons
        else
        {
            echo "<tr><td>" . $escaper->escapeHtml($lang['APIKey']) . ":&nbsp;</td><td><form name=\"api_key\" method=\"post\"><input type=\"submit\" name=\"generate_api_key\" value=\"" . $escaper->escapeHtml($lang['RotateAPIKey']) . "\" />&nbsp;&nbsp;<input type=\"submit\" name=\"invalidate_api_key\" value=\"" . $escaper->escapeHtml($lang['InvalidateAPIKey']) . "\" /></form></td></tr>\n";
        }
    }
    // Otherwise, no API key was found for the user
    else
    {
        // If the user asked to generate an API key
        if (isset($_POST['generate_api_key']))
        {
            // Create a random 64 character API key
            $api_key = generate_token(64);

            // Set the API key for the user
            set_api_key($api_key, $_SESSION['user']);

            // Show the API key
            echo "<tr><td>" . $escaper->escapeHtml($lang['APIKey']) . ":&nbsp;</td><td><input type=\"text\" name=\"api_key\" value=\"" . $escaper->escapeHtml($api_key) . "\" /></td></tr>\n";
        }
        // Otherwise, display the key generation button
        else
        {
            echo "<tr><td>" . $escaper->escapeHtml($lang['APIKey']) . ":&nbsp;</td><td><form name=\"api_key\" method=\"post\"><input type=\"submit\" name=\"generate_api_key\" value=\"" . $escaper->escapeHtml($lang['GenerateAPIKey']) . "\" /></form></td></tr>\n";
        }
    }
}

/*************************
 * FUNCTION: GET API KEY *
 *************************/
function get_user_api_key()
{
    // Get the current user ID
    $user_id = $_SESSION['uid'];

        // Open the database connection
        $db = db_open();

    // Get the key for the uid
    $stmt = $db->prepare("SELECT `api_key_hash` FROM `api_keys` WHERE user_id=:user_id");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT, 11);
    $stmt->execute();

    $api_key = $stmt->fetch();

    // If the count of values in api_key is 0
    if (count($api_key) == 0)
    {
        return "";
    }
    // Otherwise, return the api_key
    else return $api_key[0];
}

/*************************
 * FUNCTION: SET API KEY *
 *************************/
function set_api_key($api_key, $username)
{
    // Get the salt
    $salt = get_api_salt();

    // Create the hashed api_key
    $api_key_hash = generateHash($salt, $api_key);

        // Open the database connection
        $db = db_open();

    // Get the userid associated with the username
    $stmt = $db->prepare("SELECT value FROM `user` WHERE username=:username");
    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();
    $user_id = $stmt->fetch();
    $user_id = $user_id[0];

    // Store the API key hash
    $stmt = $db->prepare("INSERT INTO `api_keys` (user_id, api_key_hash) VALUES (:user_id, :api_key_hash) ON DUPLICATE KEY UPDATE api_key_hash=:api_key_hash");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);
    $stmt->bindParam(":api_key_hash", $api_key_hash, PDO::PARAM_STR);
    $stmt->execute();

        // Close the database connection
        db_close($db);
}

/****************************
 * FUNCTION: REMOVE API KEY *
 ****************************/
function remove_api_key($user_id)
{
        // Open the database connection
        $db = db_open();

        // Delete the API key
        $stmt = $db->prepare("DELETE FROM `api_keys` WHERE user_id=:user_id");
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT, 11);
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/******************************
* FUNCTION: CHECK ENCRYPTION LEVEL FOR LOGIN *
******************************/
function check_encryption_level(){
    if (encryption_extra()){
        // Load the extra
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        

        // If an api key was sent
        if (isset($_GET['key']))
        {
            // Get the api key
            $api_key = $_GET['key'];

                // Get the salt
            $salt = get_api_salt();
                
            // Create the hashed api_key
            $api_key_hash = generateHash($salt, $api_key);
            
            // Open the database connection
            $db = db_open();

            // Check for a matching api key hash
            $stmt = $db->prepare("SELECT user_id FROM `api_keys` WHERE api_key_hash = :api_key_hash");
            $stmt->bindParam(":api_key_hash", $api_key_hash, PDO::PARAM_STR);
            $stmt->execute();
            $array = $stmt->fetchAll();
            if(!$array){
                return true;
            }
            $user_id = $array[0]['user_id'];

            // Close the database connection
            db_close($db);

            // If a user ID was returned
            if (count($array) > 0)
            {
                // Query the DB for the users complete information
                $stmt = $db->prepare("SELECT value, type, name, username, lang, assessments, asset, admin, review_veryhigh, review_high, review_medium, review_low, review_insignificant, submit_risks, modify_risks, plan_mitigations, close_risks FROM user WHERE value = :user_id");
                $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT, 11);
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
                $username = $array[0]['username'];
                
                return check_encryption_from_external($username);
            }
        }


    }
    
    return true;
}

/******************************
 * FUNCTION: AUTHENTICATE KEY *
 ******************************/
function authenticate_key()
{
    // If an api key was sent
    if (isset($_GET['key']))
    {
        // Get the api key
        $api_key = $_GET['key'];

            // Get the salt
        $salt = get_api_salt();
            
        // Create the hashed api_key
        $api_key_hash = generateHash($salt, $api_key);
        
        // Open the database connection
        $db = db_open();

        // Check for a matching api key hash
        $stmt = $db->prepare("SELECT user_id FROM `api_keys` WHERE api_key_hash = :api_key_hash");
        $stmt->bindParam(":api_key_hash", $api_key_hash, PDO::PARAM_STR);
        $stmt->execute();
        $array = $stmt->fetchAll();
        if(!$array){
            return false;
        }
        $user_id = $array[0]['user_id'];

        // Close the database connection
        db_close($db);

        // If a user ID was returned
        if (count($array) > 0)
        {
            // Query the DB for the users complete information
            $stmt = $db->prepare("SELECT value, type, name, username, lang, assessments, asset, admin, review_veryhigh, review_high, review_medium, review_low, review_insignificant, submit_risks, modify_risks, plan_mitigations, close_risks FROM user WHERE value = :user_id");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT, 11);
            $stmt->execute();

            // Store the list in the array
            $array = $stmt->fetchAll();

            // Get base url
            $base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}";
            $base_url = htmlspecialchars( $base_url, ENT_QUOTES, 'UTF-8' );
            $base_url = pathinfo($base_url)['dirname'];
            $base_url = dirname($base_url);

            // Set the permissions
            $_SESSION['base_url'] = $base_url;
            $_SESSION['uid'] = $array[0]['value'];
            $_SESSION['user'] = $array[0]['username'];
            $_SESSION['name'] = $array[0]['name'];
            $_SESSION['admin'] = $array[0]['admin'];
            $_SESSION['user_type'] = $array[0]['type'];
            $_SESSION['assessments'] = $array[0]['assessments'];
            $_SESSION['asset'] = $array[0]['asset'];
            $_SESSION['review_veryhigh'] = $array[0]['review_veryhigh'];
            $_SESSION['review_high'] = $array[0]['review_high'];
            $_SESSION['review_medium'] = $array[0]['review_medium'];
            $_SESSION['review_low'] = $array[0]['review_low'];
            $_SESSION['review_insignificant'] = $array[0]['review_insignificant'];
            $_SESSION['submit_risks'] = $array[0]['submit_risks'];
            $_SESSION['modify_risks'] = $array[0]['modify_risks'];
            $_SESSION['close_risks'] = $array[0]['close_risks'];
            $_SESSION['plan_mitigations'] = $array[0]['plan_mitigations'];
            /**
            * by Juha
            * 
            */
            
            // If the users language is not null
            if (!is_null($array[0]['lang']))
            {
                // Set the session value
                $_SESSION['lang'] = $array[0]['lang'];
            }
            // Otherwise, the session should use the default language
            else $_SESSION['lang'] = LANG_DEFAULT;

            $_SESSION["access"] = "granted";            
            
            // Set encrypted pass
            if (encryption_extra()){
                // Load the extra
                require_once(realpath(__DIR__ . '/../encryption/index.php'));
                
                // Check if can be encrypted from external
                if (!check_encryption_from_external($_SESSION['user'])){
                    return false;
                }
                // The user has not yet been activated
                else
                {
                    // Get the current password encrypted with the temp key
                    $encrypted_pass = get_enc_pass($_SESSION['user'], fetch_tmp_pass());
                }   
                $_SESSION['encrypted_pass'] = $encrypted_pass;
            }

            return $user_id;
        }
        // Otherwise, return false
        else return false;
    }
    // Otherwise, return false
    else return false;
}

/*********************************
 * FUNCTION: PROCESS API REQUEST *
 *********************************/
function process_api_request()
{
    // Get the request method
    $request_method = $_SERVER['REQUEST_METHOD'];

    // If the method is POST and the HTTP X HTTP METHOD exists
    if ($request_method == "POST" && array_key_exists("HTTP_X_HTTP_METHOD", $_SERVER))
    {
        // If the method is DELETE
        if ($_SERVER['HTTP_X_HTTP_METHOD'] == "DELETE")
        {
            $request_method = "DELETE";
        }
        // Else, if the method is PUT
        else if ($_SERVER['HTTP_X_HTTP_METHOD'] == "PUT")
        {
            $request_method = "PUT";
        }
        // Else, unexpected header
        else
        {
            // Return an error message
            json_response(405, "Unexpected Header", NULL);            
        }
    }

    switch ($request_method)
    {
        case "DELETE":
            break;
        case "POST":
            break;
        case "GET":
            break;
        case "PUT":
            break;
        default:
            json_response(405, "Invalid Method", NULL);
            break;
    }
}

?>
