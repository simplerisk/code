<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/messages.php'));
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/permissions.php'));

/*******************************
 * FUNCTION: OLD GENERATE SALT *
 *******************************/
function oldGenerateSalt($username)
{
    $salt = '$2a$15$';
    $salt = $salt . md5(strtolower($username));
    return $salt;
}

/*******************************
 * FUNCTION: GENERATE SALT *
 *******************************/
function generateSalt($username)
{
        // Open the database connection
        $db = db_open();

        // If strict user validation is disabled
        if (get_setting('strict_user_validation') == 0)
        {
        // Get the users unique salt
        $stmt = $db->prepare("SELECT salt FROM user WHERE LOWER(convert(`username` using utf8)) = LOWER(:username)");
        }
        else
        {
        // Get the users unique salt
        $stmt = $db->prepare("SELECT salt FROM user WHERE username = :username");
        }

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();
    $values = $stmt->fetchAll();
    $salt = '$2a$15$' . md5($values[0]['salt']);

        // Close the database connection
        db_close($db);

        return $salt;
}

/***************************
 * FUNCTION: GENERATE HASH *
 ***************************/
function generateHash($salt, $password)
{
    // The crypt function can take a while so we increase the max execution time
    set_time_limit(120);

    $hash = crypt($password, $salt);
    return $hash;
}

/***************************
 * FUNCTION: GET USER TYPE *
 ***************************/
function get_user_type($user, $upgrade = false)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // If we are upgrading
        if ($upgrade)
        {
            // Query the DB for a matching enabled user
            $stmt = $db->prepare("SELECT type FROM user WHERE enabled = 1 AND LOWER(convert(`username` using utf8)) = LOWER(:user)");
        }
        else $stmt = $db->prepare("SELECT type FROM user WHERE enabled = 1 AND lockout = 0 AND LOWER(convert(`username` using utf8)) = LOWER(:user)");
    }
    else
    {
        // If we are upgrading
        if ($upgrade)
        {
            // Query the DB for a matching enabled user
                $stmt = $db->prepare("SELECT type FROM user WHERE enabled = 1 AND username = :user");
        }
        else $stmt = $db->prepare("SELECT type FROM user WHERE enabled = 1 AND lockout = 0 AND username = :user");
    }

    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // If the user does not exist
    if (empty($array))
    {
        $type = "DNE";
    }
    else $type = $array[0]['type'];

    // If the type isn't simplerisk or ldap or saml
    if ($type != "simplerisk" && $type != "ldap" && $type != "saml")
    {
        // The user does not exist
        $type = "DNE";
    }

    // Close the database connection
    db_close($db);

    return $type;
}

/***************************
 * FUNCTION: IS VALID USER *
 ***************************/
function is_valid_user($user, $pass, $upgrade = false)
{
    // Default set valid_simplerisk, valid_ad, and valid_saml to false
    $valid_simplerisk = false;
    $valid_ad = false;
    $valid_saml = false;

    // Find the type of the user in the database
    $type = get_user_type($user, $upgrade);

    // If the user does not exist
    if ($type == "DNE")
    {
        // Write the debug log
        write_debug_log("Not a valid user in SimpleRisk.");

        // If we should automatically add new users with a default role
        if (get_setting('AUTHENTICATION_ADD_NEW_USERS') == 1)
        {
            // If custom authentication is enabled
            if (custom_authentication_extra())
            {
                // Include the custom authentication extra
                require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

                // Check for a valid Active Directory user
                list($valid_ad, $dn, $attributes) = is_valid_ad_user($user, $pass);

                // If the user is a valid AD user
                if ($valid_ad) {
                    // Get groups associated to the user
                    $groupNames = get_ldap_group_by_dn($dn);
                    
                    // Getting the local teams mapped to the groups of the user
                    $attributes['teams'] = get_mapped_local_team_ids($groupNames, 'LDAP');
                    
                    // Add the new user
                    authentication_add_new_user('ldap', $user, $attributes);

                    // Store the groups
                    storeRemoteTeams($groupNames, 'LDAP');
                }
            }
            // Otherwise, return that the user is not valid
            else return false;
        }
        // Otherwise, return that the user is not valid
        else return false;
    }
    // If the type is simplerisk
    else if ($type == "simplerisk")
    {
        // Check for a valid SimpleRisk user
        $valid_simplerisk = is_valid_simplerisk_user($user, $pass);
    }
    // If the type is ldap
    else if ($type == "ldap")
    {
        // If custom authentication is enabled
        if (custom_authentication_extra())
        {
            // Include the custom authentication extra
            require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

            // Check for a valid Active Directory user
            list($valid_ad, $dn, $attributes)= is_valid_ad_user($user, $pass);

            // Update the existing local version of the LDAP user if it's enabled in the settings
            if ($valid_ad && get_setting('UPDATE_USER_WITH_DATA_FROM_IDP')) {
                
                // Get groups associated to the user
                $groupNames = get_ldap_group_by_dn($dn);
                
                // Store whether the manager attribute was set, so we only update it if it is intended to be updated from the remote server
                $attributes['LDAP_MANAGER_ATTRIBUTE_SET'] = !empty(get_setting('LDAP_MANAGER_ATTRIBUTE'));

                // Getting the local teams mapped to the groups of the user
                $attributes['teams'] = get_mapped_local_team_ids($groupNames, 'LDAP');

                // Update user data
                authentication_add_new_user('ldap', $user, $attributes, true);
                
                // Store the groups
                storeRemoteTeams($groupNames, 'LDAP');
            }
        }
    }
    // If the type is saml
    else if ($type == "saml")
    {
        // If custom authentication is enabled
        if (custom_authentication_extra())
        {
            // Include the custom authentication extra
            require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

            // Check for a valid SAML user
            $valid_saml = is_valid_saml_user($user);
        }
    }

    // If either the SAML, AD, or SimpleRisk user are valid
    if ($valid_saml || $valid_ad || $valid_simplerisk) {
        return true;
    }
    else return false;
}

/**********************************
 * FUNCTION: SET USER PERMISSIONS *
 **********************************/
function set_user_permissions($user, $upgrade = false)
{
    // Open the database connection
    $db = db_open();

    // Convert the username to a uid
    $uid = get_id_by_user($user);

    // If user id is provided
    if (is_int($uid) && $uid > 0) {
        $stmt = $db->prepare("SELECT `username` FROM user WHERE value = :user_id;");
        $stmt->bindParam(":user_id", $uid, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetchColumn();
    }

    // If we are not doing an upgrade
    if (!$upgrade)
    {
        
        $organizational_hierarchy_extra = organizational_hierarchy_extra();
        
        $user_query_sql = "
                SELECT
                    value,
                    type,
                    name,
                    lang,
                    custom_display_settings,
                    admin
                    " . ($organizational_hierarchy_extra ? ",selected_business_unit" : "") . "
                FROM user WHERE";
        
        // If strict user validation is disabled
        if (get_setting('strict_user_validation') == 0)
        {
            // Query the DB for the users complete information
            $stmt = $db->prepare($user_query_sql . " LOWER(convert(`username` using utf8)) = LOWER(:user);");
        }
        else
        {
            // Query the DB for the users complete information
            $stmt = $db->prepare($user_query_sql . " `username` = :user;");
        }
    }
    // If we are doing an upgrade
    else
    {
        // If strict user validation is disabled
        if (get_setting('strict_user_validation') == 0)
        {
            // Query the DB for minimal user permissions needed
            $stmt = $db->prepare("SELECT value, type, name, lang, admin FROM user WHERE LOWER(convert(`username` using utf8)) = LOWER(:user)");
        }
        else
        {
            // Query the DB for minimal user permissions needed
            $stmt = $db->prepare("SELECT value, type, name, lang, admin FROM user WHERE username = :user");
        }
    }

    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Set the simplerisk timezone for any datetime functions
    set_simplerisk_timezone();

    // Set the minimal session values
    $_SESSION['uid'] = $array[0]['value'];
    $_SESSION['user'] = $user;
    $_SESSION['name'] = $array[0]['name'];
    $_SESSION['admin'] = $array[0]['admin'];
    $_SESSION['user_type'] = $array[0]['type'];

    // If we are not doing an upgrade
    if (!$upgrade)
    {
        $_SESSION['custom_display_settings'] = empty($array[0]['custom_display_settings']) ? array() : json_decode($array[0]['custom_display_settings'] ?? '', true);

        $possible_permissions = get_possible_permissions();
        $actual_permissions = get_permissions_of_user($_SESSION['uid']);
        // Set permissions
        foreach($possible_permissions as $permission) {
            $_SESSION[$permission] = in_array($permission, $actual_permissions);
        }

//        // If the encryption extra is enabled
//        if (encryption_extra())
//        {
            // Load the extra
//            require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
//            
            // Set the encrypted password in the session
//            $_SESSION['encrypted_pass'] = get_enc_pass($user, fetch_tmp_pass());
//        }
        if ($organizational_hierarchy_extra) {
            $_SESSION['selected_business_unit'] = $array[0]['selected_business_unit'];
            require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
            fix_selected_business_unit($_SESSION['uid'], $array[0]['selected_business_unit']);
        }
    }

    // Get the latest SimpleRisk version and add it to the session
    $_SESSION['latest_version_app'] = latest_version('app');

    // If the users language is not null
    if (!is_null($array[0]['lang']))
    {
        // Set the session value
        $_SESSION['lang'] = $array[0]['lang'];
    }
    // Otherwise, the session should use the default language
    else
    {
        $default_language = get_setting("default_language");
        if (!$default_language)
        {
            $_SESSION['lang'] = "en";
        }
        else $_SESSION['lang'] = get_setting("default_language");
    }

    // Close the database connection
    db_close($db);
}

/**************************
 * FUNCTION: GRANT ACCESS *
 **************************/
function grant_access()
{
    // Change the session id to prevent session fixation
    session_regenerate_id(true);

    // Grant access
    $_SESSION["access"] = "1";
    // Clear any failed login attempts
    clear_failed_logins($_SESSION['uid']);

    // Update the last login
    update_last_login($_SESSION['uid']);

    // Audit log
    $message = "Username \"" . $_SESSION['user'] . "\" logged in successfully.";
    write_log($_SESSION['uid'] + 1000, $_SESSION['uid'], $message, "user");
}

/************************************************************************************
 * FUNCTION: FAKE SIMPLERISK USER VALIDITY CHECK                                    *
 * This function's only purpose to spend the same time we're using when             *
 * checking an EXISTING user's credentials.                                         *
 * It's to prevent an attacker gathering usernames using time based enumeration.    *
 ************************************************************************************/
function fake_simplerisk_user_validity_check() {

    $db = db_open();

    // Get a random username
    $stmt = $db->prepare("
        SELECT
            `username`
        FROM
            `user`
        ORDER BY
            RAND()
        LIMIT
            1;
    ");

    $stmt->execute();

    $username = $stmt->fetchColumn();

    db_close($db);

    // Do a validity check with a random password
    is_valid_simplerisk_user($username, generate_token(40));
}

/**************************************
 * FUNCTION: IS VALID SIMPLERISK USER *
 **************************************/
function is_valid_simplerisk_user($user, $pass)
{
    // Old password hash format
    $salt = oldGenerateSalt($user);
    $oldProvidedPassword = generateHash($salt, $pass);

    // New password hash format
    $salt = generateSalt($user);
    $providedPassword = generateHash($salt, $pass);

    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Query the DB for a matching user and hash
        $stmt = $db->prepare("SELECT value, password FROM user WHERE LOWER(convert(`username` using utf8)) = LOWER(:user)");
    }
    else
    {
        // Query the DB for a matching user and hash
        $stmt = $db->prepare("SELECT value, password FROM user WHERE username = :user");
    }

    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Get the stored password
    $storedPassword = $array[0]['password'];

    // Close the database connection
    db_close($db);

    // If the passwords are equal
    if (hash_equals($storedPassword, $providedPassword) || hash_equals($storedPassword, $oldProvidedPassword))
    {
        return true;
    }
    else return false;
}

/********************************
 * FUNCTION: IS SIMPLERISK USER *
 ********************************/
function is_simplerisk_user($username) {

    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0) {

        // Query the DB for a matching user and hash
        $stmt = $db->prepare("
            SELECT 
                value 
            FROM 
                user 
            WHERE 
                type = 'simplerisk' AND LOWER(convert(`username` using utf8)) = LOWER(:username)
        ");
    } else {

        // Query the DB for a matching user and hash
        $stmt = $db->prepare("
            SELECT 
                value 
            FROM 
                user 
            WHERE 
                type = 'simplerisk' AND username = :username
        ");

    }

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the user does not exist return 0
    if (empty($array)) {

        return 0;

    // Otherwise, return the user id value
    } else {
        return $array[0]['value'];
    }
}

/****************************
 * FUNCTION: GENERATE TOKEN *
 ****************************/
function generate_token($size)
{               
    $token = "";
    $values = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
	$values_count = count($values);
        
    for ($i = 0; $i < $size; $i++)
    {
        // If the random int function exists (PHP 7)
        if (function_exists('random_int'))
        {
            // Generate the token using the random_int function
            $token .= $values[random_int(0, $values_count-1)];
        }
        else $token .= $values[array_rand($values)];
    }

    return $token;
}

/****************************************
 * FUNCTION: PASSWORD RESET BY USERNAME *
 ****************************************/
function password_reset_by_username($username) {               

    $userid = is_simplerisk_user($username);
    
    // Check if the username exists
    if ($userid != 0) {    
        password_reset_by_userid($userid);

        // Adding a random wait to increase noise in response time to make it harder for timing attacks on the password reset 
        wait(rand(0, 2000));
        return true;
    } else {
        // Adding a random wait to increase noise in response time to make it harder for timing attacks on the password reset
        wait(rand(1000, 3000));
        return false;
    }
}

/**************************************
 * FUNCTION: PASSWORD RESET BY USERID *
 **************************************/
function password_reset_by_userid($userid) {
        
    // Generate a 20 character reset token
    $token = generate_token(20);

    // Open the database connection
    $db = db_open();

    // Get the users e-mail address
    $stmt = $db->prepare("
        SELECT 
            username, name, email, salt 
        FROM 
            user 
        WHERE 
            value=:userid
    ");
    $stmt->bindParam(":userid", $userid, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    $username = $array[0]['username'];
    $name = $array[0]['name'];
    $email = $array[0]['email'];

    // Delete previous tokens for this user
    $stmt = $db->prepare("
        DELETE FROM 
            password_reset 
        WHERE 
            username=:username
    ");
    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Insert into the password reset table
    $stmt = $db->prepare("
        INSERT INTO 
            password_reset 
        (
            `username`, `token`
        ) 
        VALUES 
        (
            :username, :token
        )
    ");

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->bindParam(":token", $token, PDO::PARAM_STR, 20);

    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Send the reset e-mail
    send_reset_email($username, $name, $email, $token);

    // If this was submitted by an unauthenticated user
    if (!isset($_SESSION['uid'])) {

        $user = "Unauthenticated";
        $uid = 0;

    // Otherwise, set the user and uid
    } else {

        $user = $_SESSION['user'];
        $uid = $_SESSION['uid'];

    }

    // Audit log
    $message = "A password reset request was submitted for user '{$username}' by the '{$user}' user.";
    write_log($userid + 1000, $uid, $message, "user");

}

/******************************
 * FUNCTION: SEND RESET EMAIL *
 ******************************/
function send_reset_email($username, $name, $email, $token) {

    global $escaper;

    $to = $email;
    $subject = "Password Reset Token";

    // To send HTML mail, the Content-type header must be set
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

    // Additional headers
    $headers .= "From: SimpleRisk <noreply@simplerisk.com>\r\n";
    $headers .= "Reply-To: SimpleRisk <noreply@simplerisk.com>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Create the full HTML message
    $body = "<html><body>\n";
    $body .= "<p>Hello " . $escaper->escapeHtml($name).",</p>\n";
    $body .= "<p>A request was submitted to reset your SimpleRisk password.</p>\n";
    
    $resetRequestMessages = getPasswordReqeustMessages();
    if(count($resetRequestMessages)){
        $body .= "<p><b>Password should have the following requirements.</b></p>\n";
        $body .= "<ul>\n";
        foreach($resetRequestMessages as $resetRequestMessage){
            $body .= "<li>{$resetRequestMessage}</li>\n";
        }
        $body .= "</ul>\n";
    }
    
    //$base_url = get_current_url();
    //$pattern[0] = "/\/(\w)*\.php$/";
    //$pattern[1] = "/\/admin\/(\w)*\.php$/";
    //$base_url = preg_replace($pattern, "/reset.php", $base_url);
    
    $body .= "<b>Username:</b>&nbsp;&nbsp;{$escaper->escapeHtml($username)}<br/>\n";
    $body .= "<b>Reset Token:</b>&nbsp;&nbsp;".$token."<br/>\n";
    if(get_setting('simplerisk_base_url') != false) {
        $base_url = get_base_url() . "/reset.php";
        $base_url = $base_url . "?token=".$token."&username={$escaper->escapeHtml($username)}";
        $body .= "<p>You may now use the \"<a href='{$base_url}'>Forgot your password</a>\" link on the SimpleRisk log in page to reset your password.</p>";
    } else {
        $body .= "<p>Please visit the SimpleRisk log in page to reset your password.</p>";
    }
    $body .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
    $body .= "</body></html>\n";
    
    //mail($to, $subject, $body, $headers);

    // Require the mail functions
    require_once(realpath(__DIR__ . '/mail.php'));

    // Send the e-mail
    send_email($name, $email, $subject, $body);
}

/*************************************
 * FUNCTION: PASSWORD RESET BY TOKEN *
 *************************************/
function password_reset_by_token($username, $token, $password, $repeat_password)
{
    global $lang, $escaper;
    $userid = is_simplerisk_user($username);

    // If the reset token is valid
    if (is_valid_reset_token($username, $token))
    {
        // If the username exists
        if ($userid != 0)
        {
            // Check the password
            $error_code = valid_password($password, $repeat_password, $userid);

            // If the password is valid
            if ($error_code == 1)
            {
                // Open the database connection
                $db = db_open();

                // Create the new password hash
                $salt = generateSalt($username);
                $hash = generateHash($salt, $password);

                // Update the password
                $stmt = $db->prepare("UPDATE user SET password=:hash, last_password_change_date=NOW() WHERE username=:username");
                $stmt->bindParam(":hash", $hash, PDO::PARAM_STR, 60);
                $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
                $stmt->execute();

                // Delete tokens for this user
                $stmt = $db->prepare("DELETE FROM password_reset WHERE username=:username AND token=:token");
                $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
                $stmt->bindParam(":token", $token, PDO::PARAM_STR,20);
                $stmt->execute();


                // Close the database connection
                db_close($db);

                // Clean up other sessions of the user and roll the current session's id
                kill_other_sessions_of_current_user($userid);

                // Display an alert
                set_alert(true, "good", "Your password has been reset successfully!");
                return true;
            }
            // The password is not valid
            else
            {
                // Display an alert
                //set_alert(true, "bad", password_error_message($error_code));

                // Return false
                return false;
            }
        }
        // Username was invalid
        else
        {
            // Display an alert
            set_alert(true, "bad", "There was an error with your password reset.");

            // Return false
            return false;
        }
    }
    // Reset token was invalid
    else
    {
        // Display an alert
        set_alert(true, "bad", "Invalid user reset token.");

        // Return false
        return false;
    }
}

/**********************************
 * FUNCTION: IS VALID RESET TOKEN *
 **********************************/
function is_valid_reset_token($username, $token)
{
	// Open the database connection
	$db = db_open();

    // Delete tokens older than 15 minutes
    $stmt = $db->prepare("DELETE FROM password_reset WHERE timestamp < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute();

	// If strict user validation is disabled
	if (get_setting('strict_user_validation') == 0)
	{
		// Increment the attempts for the username
		$stmt = $db->prepare("UPDATE password_reset SET attempts=attempts+1 WHERE LOWER(convert(`username` using utf8)) = LOWER(:username)");
	}
	else
	{
		// Increment the attempts for the username
		$stmt = $db->prepare("UPDATE password_reset SET attempts=attempts+1 WHERE username=:username");
	}

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();

	// If strict user validation is disabled
	if (get_setting('strict_user_validation') == 0)
	{
        // Search for a valid token
        $stmt = $db->prepare("SELECT attempts FROM password_reset WHERE LOWER(convert(`username` using utf8)) = LOWER(:username) AND token=:token");
	}
	else
	{
        // Search for a valid token
        $stmt = $db->prepare("SELECT attempts FROM password_reset WHERE username=:username AND token=:token");
	}

	$stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->bindParam(":token", $token, PDO::PARAM_STR, 20);
	$stmt->execute();

	// Store the list in the array
	$array = $stmt->fetchAll();

	// Close the database connection
	db_close($db);

    // If there is not a match for the username and token
    if (empty($array))
    {
        return false;
    }
    else
    {
        // Get the number of attempts
        $attempts = $array[0]['attempts'];

        // Matching token has been attempted <= 5 times
        if ($attempts < 5)
        {
            return true;
        }
        // Matching token has been attempted > 5 times
        else
        {
            // Expire the reset token
            expire_reset_token($token);

            return false;
        }
    }
}

/********************************
 * FUNCTION: EXPIRE RESET TOKEN *
 ********************************/
function expire_reset_token($token)
{
    // Open the database connection
    $db = db_open();

    // Remove the matching token
    $stmt = $db->prepare("DELETE FROM password_reset WHERE token=:token");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR, 20);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************************
 * FUNCTION: EXPIRE RESET TOKEN FOR USERNAME *
 *********************************************/
function expire_reset_token_for_username($username)
{
    // Open the database connection
    $db = db_open();

    // Remove the matching token
    $stmt = $db->prepare("DELETE FROM password_reset WHERE username=:username");
    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*******************************
 * FUNCTION: ADD SESSION CHECK *
 *******************************/
function add_session_check($permissions = [])
{
	write_debug_log("SCRIPT NAME: " . $_SERVER['SCRIPT_NAME']);

	// If a session is not currently started and active
	if (session_status() === PHP_SESSION_NONE)
	{
		write_debug_log("A session is not currently started and active.");

		// If we are using the database for sessions
		if (USE_DATABASE_FOR_SESSIONS == "true")
		{
			write_debug_log("We are using the database for sessions.");

			// Set the custom session save handler
			session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
		}

		// If we are running an old version of PHP
		if (PHP_VERSION_ID < 70300)
		{
			write_debug_log("We are running a version of PHP < 7.3 (" . PHP_VERSION_ID . ").");
			write_debug_log("Setting the session cookie parameters.");

			// Set the session cookie parameters
            $parameters = [
                "lifetime" => 0,
                "path" => "/",
                "domain" => "",
                "secure" => isset($_SERVER["HTTPS"]),
                "httponly" => true,
                "samesite" => "Strict",
            ];
            session_set_cookie_params($parameters);
		}
		else
		{
			write_debug_log("We are running a newer version of PHP >= 7.3 (" . PHP_VERSION_ID . ").");
			write_debug_log("Setting the session cookie parameters.");

			// Set the session cookie parameters
            $parameters = [
                "lifetime" => 0,
                "path" => "/",
                "domain" => "",
                "secure" => isset($_SERVER["HTTPS"]),
                "httponly" => true,
                "samesite" => "Strict",
            ];
            session_set_cookie_params($parameters);
		}

		// Set the current session name
		write_debug_log("Setting the current session name.");
		session_name('SimpleRisk');

		// Start the session
		write_debug_log("Starting the session.");
		session_start();
	}

	// Check for session timeout or renegotiation
	write_debug_log("Checking for session timeout or renegotiation.");
	session_check();

	// Enforcing the access permission by default
	enforce_permission("access");

	// For each permission provided
	foreach ($permissions as $permission => $value)
	{
		// If the value is true
		if ($value)
		{
			// Handle the permission
			switch ($permission)
			{
                case "check_admin":
					write_debug_log("Admin permission is required.");
					enforce_permission("admin");
                    break;
                case "check_assessments":
					write_debug_log("Assessments permission is required.");
					enforce_permission("assessments");
                    break;
                case "check_assets":
					write_debug_log("Assets permission is required.");
					enforce_permission("asset");
                    break;
                case "check_compliance":
					write_debug_log("Compliance permission is required.");
					enforce_permission("compliance");
                    break;
                case "check_governance":
					write_debug_log("Governance permission is required.");
					enforce_permission("governance");
                    break;
                case "check_riskmanagement":
					write_debug_log("Risk management permission is required.");
					enforce_permission("riskmanagement");
                    break;
				case "check_im":
					write_debug_log("Incident Management Incidents permission is required.");
					enforce_permission("im_incidents");
					break;
				case "check_im_reporting":
					write_debug_log("Incident Management Reporting permission is required.");
					enforce_permission("im_reporting");
					break;
				case "check_im_configure":
					write_debug_log("Incident Management Configure permission is required.");
					enforce_permission("im_configure");
					break;
				case "check_vm_vulnerabilities":
				    write_debug_log("Vulnerability Management 'Vulnerabilities' permission is required.");
				    enforce_permission("vm_vulnerabilities");
				    break;
				case "check_vm_configure":
				    write_debug_log("Vulnerability Management 'Configure' permission is required.");
				    enforce_permission("vm_configure");
				    break;
                case "check_ai":
                    write_debug_log("Artificial Intelligence permission is required.");
                    enforce_permission("ai_access");
                    break;
			}
		}
	}
}

/***************************
 * FUNCTION: SESSION CHECK *
 ***************************/
function session_check()
{
	// Perform session garbage collection
	sess_gc(1440);

        // Get the session activity timeout
        $session_activity_timeout = get_setting("session_activity_timeout");
        if (!$session_activity_timeout) $session_activity_timeout = "3600";

        // Last request was more $last_activity
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $session_activity_timeout))
        {
            // unset $_SESSION variable for the run-time
            session_unset();

            // destroy session data in storage
            session_destroy();

            // Return false
            return false;
        }
        // If the session created value has not been set
        if (!isset($_SESSION['CREATED']))
        {
			// Set it with the current time
			$_SESSION['CREATED'] = time();
        }
        // Otherwise check if it was created more than $created
        else
        {
            $session_absolute_timeout = get_setting("session_absolute_timeout");
            if (!$session_absolute_timeout) $session_absolute_timeout = "28800";

            if (time() - $_SESSION['CREATED'] > $session_absolute_timeout)
            {
                // unset $_SESSION variable for the run-time
                session_unset();

                // destroy session data in storage
                session_destroy();

                // Return false
                return false;
            }
        }

        // update last activity time stamp
        $_SESSION['LAST_ACTIVITY'] = time();

	// Return true
	return true;
}

/**************************
 * FUNCTION: SESSION OPEN *
 **************************/
function sess_open($sess_path, $sess_name)
{
        // Perform session garbage collection
        sess_gc(1440);

        return true;
}

/***************************
 * FUNCTION: SESSION CLOSE *
 ***************************/
function sess_close()
{
        // Perform session garbage collection
        sess_gc(1440);

        return true;
}

/**************************
 * FUNCTION: SESSION READ *
 **************************/
function sess_read($sess_id)
{
        // Open the database connection
        $db = db_open();

        // Get the session data
        $stmt = $db->prepare("SELECT data FROM sessions WHERE `id`=:sess_id");
        $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 128);
        $stmt->execute();
        $array = $stmt->fetchAll();

/*
        // If the session does not exist
        if (empty($array))
        {
                $current_time = time();

                // Create the session
                $stmt = $db->prepare("INSERT INTO sessions (id, access) VALUES (:sess_id, :current_time)");
                $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 128);
                $stmt->bindParam(":current_time", $current_time, PDO::PARAM_INT, 10);
                $stmt->execute();

                $return = "";
        }
        // The session does exist
        else
        {
                $stmt = $db->prepare("UPDATE sessions SET `access`=:current_time WHERE `id`=:sess_id");
                $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 128);
                $stmt->bindParam(":current_time", $current_time, PDO::PARAM_INT, 10);
                $stmt->execute();

                $return = $array[0]['data'];
        }
*/

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                return '';
        }
        else return $array[0]['data'];
}

/***************************
 * FUNCTION: SESSION WRITE *
 ***************************/
function sess_write($sess_id, $data)
{
    $access = time();
    
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("REPLACE INTO sessions VALUES (:sess_id, :access, :data)");
    $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR);
    $stmt->bindParam(":access", $access, PDO::PARAM_INT);
    $stmt->bindParam(":data", $data, PDO::PARAM_LOB);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    return true;
}

/*****************************
 * FUNCTION: SESSION DESTROY *
 *****************************/
function sess_destroy($sess_id)
{
        // Perform session garbage collection
        sess_gc(1440);

        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("DELETE FROM sessions WHERE `id`=:sess_id");
        $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 128);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/****************************************
 * FUNCTION: SESSION GARBAGE COLLECTION *
 ****************************************/
function sess_gc($sess_maxlifetime)
{
    $sess_maxlifetime = get_setting("session_activity_timeout");
    $old = time() - $sess_maxlifetime;

        // Open the database connection
        $db = db_open();

        $current_time = time();
        $stmt = $db->prepare("DELETE FROM sessions WHERE `access` < :old");
    $stmt->bindParam(":old", $old, PDO::PARAM_INT, 10);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/********************
 * FUNCTION: LOGOUT *
 ********************/
function logout()
{
    // Get the session username and uid
    $username = $_SESSION['user'];
    $uid = $_SESSION['uid'];

    // Audit log
    $message = "Username \"" . $username . "\" logged out successfully.";
    write_log($uid + 1000, $uid, $message, "user");

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
    
    if (custom_authentication_extra() && get_setting('LOGOUT_FROM_REMOTE_SESSIONS', false)) {
        // Include the custom authentication extra
        require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

        saml_logout();
    }
}

/************************************
 * FUNCTION: STRICT USER VALIDATION *
 ************************************/
function strict_user_validation($username)
{
    // If strict user validation is enabled
    if (get_setting('strict_user_validation') == 0)
    {
        $username = strtolower($username);
    }

    // Return the username
    return $username;
}

/****************************************
* FUNCTION: ADD LOGIN ATTEMPT AND BLOCK *
****************************************/
function add_login_attempt_and_block($user)
{
    // Check if the user exists
    $user_id = is_simplerisk_user($user);

    // If the user exists
    if($user_id !== 0)
    {
        // Get the IP of the login request
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Open the database connection
        $db = db_open();

        // Add a failed login for the user
        $stmt = $db->prepare("INSERT INTO `failed_login_attempts` (`user_id`, `ip`) VALUE (:user_id, :ip);");
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":ip", $ip, PDO::PARAM_STR, 15);
        $stmt->execute();

        // Clear any expired login attempts
        check_expired_lockouts();

        // Get the number of failed attempts for the user
        $stmt = $db->prepare("SELECT id FROM `failed_login_attempts` WHERE user_id=:user_id AND expired=0;");
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $array = $stmt->fetchAll();
        $failed_attempts = count($array);

        // Close the database connection
        db_close($db);

        // Get how many attempts before a lockout
        $lockout_attempts = (int)get_setting("pass_policy_attempt_lockout");

        // If the user has met or exceeded the lockout attempts
        if ($failed_attempts >= $lockout_attempts)
        {
            // Block the user
            block_user($user_id);

            // Write to audit log
            $message = 'Username "' . $user . '" has been blocked due to reaching maximum login attempt counter.';
            write_log($user_id + 1000, $user_id, $message, "user");
        }
    }
}

/************************************
 * FUNCTION: CHECK EXPIRED LOCKOUTS *
 ************************************/
function check_expired_lockouts()
{
    // Get the lockout time
    $lockout_time = get_setting('pass_policy_attempt_lockout_time');

    // If the lockout time isn't unlimited
    if ($lockout_time != 0)
    {
            // Open the database connection
            $db = db_open();

        // Expire entries in the failed login table older than lockout time
            $stmt = $db->prepare("UPDATE `failed_login_attempts` SET expired=1 WHERE date < (NOW() - INTERVAL :lockout_time MINUTE);");
        $stmt->bindParam(":lockout_time", $lockout_time, PDO::PARAM_INT);
            $stmt->execute();

        // Close the database connection
        db_close($db);
    }
}

/***********************
* FUNCTION: BLOCK USER *
***********************/
function block_user($user_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("UPDATE user SET lockout=1 WHERE value=:user_id;");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************
 * FUNCTION: CLEAR FAILED LOGINS *
 *********************************/
function clear_failed_logins($user_id)
{
    // Open the database connection
    $db = db_open();

    // Expire entries in the failed login table for the user
    $stmt = $db->prepare("UPDATE `failed_login_attempts` SET expired=1 WHERE user_id=:user_id;");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: ADD LAST PASSWORD HISTORY *
 ***************************************/
function add_last_password_history($user_id, $old_salt, $old_password)
{
    // Open the database connection
    $db = db_open();

    // Check if row exists
    $stmt = $db->prepare("SELECT user_id, salt, password FROM user_pass_history WHERE user_id LIKE :user_id AND salt LIKE :salt AND password LIKE :password;");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":salt", $old_salt, PDO::PARAM_STR, 20);
    $stmt->bindParam(":password", $old_password, PDO::PARAM_STR, 60);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if(count($result) == 0){
        // There is no entry like that, adding new one
        $stmt = $db->prepare("INSERT INTO user_pass_history (user_id, salt, password) VALUES (:user_id, :salt, :password);");
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":salt", $old_salt, PDO::PARAM_STR, 20);
        $stmt->bindParam(":password", $old_password, PDO::PARAM_STR, 60);
        $stmt->execute();        
    }

    // Close the database connection
    db_close($db);
}

/**********************************************
 * FUNCTION: CHECK ADD PASSWORD REUSE HISTORY *
 **********************************************/
function check_add_password_reuse_history($user_id, $password)
{
	$pass_policy_reuse_limit = get_setting('pass_policy_reuse_limit');

	// If the password policy reuse limit is 0
	if ($pass_policy_reuse_limit == 0)
	{
		// We don't need to check
		return true;
	}
	else
	{
		// Open the database connection
		$db = db_open();

		// Check if row exists
		$stmt = $db->prepare("SELECT * FROM user_pass_reuse_history WHERE user_id = :user_id AND password LIKE :password;");
		$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
		$stmt->bindParam(":password", $password, PDO::PARAM_STR, 60);
		$stmt->execute();
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
    
		if(!$result)
		{
			// Insert new record
			$stmt = $db->prepare("INSERT INTO user_pass_reuse_history(`user_id`, password) VALUES(:user_id, :password);");
			$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
			$stmt->bindParam(":password", $password, PDO::PARAM_STR, 60);
			$stmt->execute();
		}
		elseif($result['counts'] < $pass_policy_reuse_limit)
		{
			$stmt = $db->prepare("UPDATE user_pass_reuse_history SET `counts`=`counts`+1 WHERE `user_id`=:user_id AND password=:password ");
			$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
			$stmt->bindParam(":password", $password, PDO::PARAM_STR, 60);
			$stmt->execute();
		}
		else
		{
			// Close the database connection
			db_close($db);

			return false;
		}
	}

	// Close the database connection
	db_close($db);
    
	return true;
}

/****************************
 * FUNCTION: RESET PASSWORD *
 ****************************/
function reset_password($user_id, $current_password, $new_password, $confirm_password)
{
	global $lang;

	// Get the user by the user ID
	$user = get_user_by_id($user_id);
	$username = $user['username'];
    
	// If the current password is correct
	if (is_valid_user($username, $current_password))
	{
		// Check if the new password is valid
		$error_code = valid_password($new_password, $confirm_password, $user_id);

		// If the new password is valid
		if ($error_code == 1)
		{
			// Generate the salt
			$salt = generateSalt($username);

			// Generate the password hash
			$hash = generateHash($salt, $new_password);

			// If it is possible to reuse password
			if(check_add_password_reuse_history($user_id, $hash))
			{
				// Get user old data
				$old_data = get_salt_and_password_by_user_id($user_id);

				// Add the old data to the pass_history table
				add_last_password_history($user_id, $old_data["salt"], $old_data["password"]);

				// Update the password
				update_password($username, $hash);

            
				// Display an alert
				set_alert(true, "good", "Your password has been updated successfully!");
            
				// Unset the change password value
				if(isset($_SESSION['change_password']))
				{
					unset($_SESSION['change_password']);
				}
            
				// Set the user permissions
				set_user_permissions($username);

				// Get base URL
				//$base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['SERVER_NAME']}{$_SERVER['SCRIPT_NAME']}";
				//$base_url = htmlspecialchars( $base_url, ENT_QUOTES, 'UTF-8' );
				//$base_url = pathinfo($base_url)['dirname'];

				// Filter out authentication extra from the base url
				//$base_url = str_replace("/extras/authentication", "", $base_url);
				//$_SESSION['base_url'] = $base_url;
				$_SESSION['base_url'] = get_base_url();

				// Set login status
				login($username, $new_password);
			}
			else
			{
				set_alert(true, "bad", $lang['PasswordNoLongerUse']);
			}
		}
		else
		{
			// If the error code was false
			if ($error_code == false)
			{
				// Set an alert
				set_alert(true, "bad", $lang['NewPasswordDoesNotMatchPolicy']);
			}
		}
	}
	else set_alert(true, "bad", $lang['PasswordIncorrect']);
}

/*******************
 * FUNCTION: LOGIN *
 *******************/
function login($user, $pass)
{
    // Get the multi_factor value for this uid
    $multi_factor = get_multi_factor_for_uid();

    // If no multi factor authentication is enabled for the user
    if ($multi_factor == 0)
    {
        // If the encryption extra is enabled
        if (encryption_extra())
        {
            // Load the extra
            require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

            // Check user enc
            check_user_enc($user, $pass);
        }

        // Grant the user access
        grant_access();

        // Select where to redirect the user next
        select_redirect();
    }
    // If the core multi_factor authentication is enabled for the user
    else if ($multi_factor == 1)
    {
        // If MFA is already verified for this user
        if (is_mfa_verified_for_uid())
        {
            // Set session access to mfa
            $_SESSION["access"] = "mfa";
        }
        // If the MFA is not verified
        else
        {
            // Set session access to mfa
            $_SESSION["access"] = "mfa_verify";
        }
    }
}

/****************************************************************************
 * FUNCTION: KILL SESSIONS OF USER                                          *
 * Used to kill off sessions of a user.                                     *
 * Set $keep_current_session to true if the current session should be kept. *
 ****************************************************************************/
function kill_sessions_of_user($user_id, $keep_current_session = false) {

    $sid = session_id();

    $db = db_open();

    if ($keep_current_session) {
        // Get the session ids that are not THIS session
        $stmt = $db->prepare("SELECT `id` FROM sessions where `id` <> :session_id and length(`data`) > 20;");
        $stmt->bindParam(":session_id", $sid);
    } else {
        // Get the session ids including THIS session
        $stmt = $db->prepare("SELECT `id` FROM sessions where length(`data`) > 20;");
    }

    $stmt->execute();
    $session_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($session_ids)) {
        // Force-write current session changes
        session_write_close();

        // Iterate through the session ids
        foreach($session_ids as $session_id) {

            // Activate our target session
            session_id($session_id);
            session_start();

            // Kill session if the user id matches the current user
            if (isset($_SESSION['uid']) && $_SESSION['uid'] == $user_id) {
                // unset $_SESSION variable for the run-time
                session_unset();

                // destroy session data in storage
                session_destroy();

            }
            // Force-write current session changes
            session_write_close();
        }

        // Start our old session again
        session_id($sid);
        session_start();
    }

    db_close($db);
}

/********************************************************************
 * FUNCTION: KILL OTHER SESSIONS OF CURRENT USER                    *
 * Used to kill off possible other sessions of  the                 *
 * logged in user and rolls session id of the                       *
 * current one to make sure noone can re-use the session cookies.   *
 ********************************************************************/
function kill_other_sessions_of_current_user($userid = false) {

    if(isset($_SESSION['uid']) || $userid) kill_sessions_of_user($_SESSION['uid'] ?? $userid, true);

    // change session ID for the current session and invalidate old session ID
    session_regenerate_id(true);

    // update creation time
    $_SESSION['CREATED'] = time();
}

/*****************************
 * FUNCTION: MIGRATE TO TOTP *
 *****************************/
function migrate_to_totp($app)
{
    // Add a session value to verify the secret
    $_SESSION['mfa_verify_secret'] = "true";

    // Set the MFA app in the session
    $_SESSION['mfa_app'] = $app;
}

?>