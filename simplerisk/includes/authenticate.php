<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/bootstrap.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/messages.php'));
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/extras.php'));

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
 * FUNCTION: HASH PASSWORD *
 ***************************/
function hash_password($password)
{
    // Use PHP's native password_hash() with bcrypt so that the salt is
    // generated from a cryptographically secure random source rather than
    // from a deterministic MD5 digest.  The output is a 60-character
    // self-contained hash that embeds the algorithm, cost, salt, and digest.
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 15]);
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

/****************************************
 * FUNCTION: LOG USER DNE REASON         *
 ****************************************
 * get_user_type() collapses four distinct conditions to "DNE" — no matching row, enabled=0,
 * lockout=1, or an unrecognized type — which left administrators reading the logs unable to
 * tell a typo apart from a locked-out account. This helper re-queries the user row without
 * those filters so a specific log line can be emitted for each cause. Called only from
 * is_valid_user when the get_user_type() result is DNE.
 ****************************************/
function log_user_dne_reason($user)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        $stmt = $db->prepare("SELECT enabled, lockout, type FROM user WHERE LOWER(convert(`username` using utf8)) = LOWER(:user)");
    }
    else
    {
        $stmt = $db->prepare("SELECT enabled, lockout, type FROM user WHERE username = :user");
    }

    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    if (!$row)
    {
        write_debug_log("Login failed: no user record matched username \"" . $user . "\".", 'info');
    }
    elseif ((int)$row['enabled'] !== 1)
    {
        write_debug_log("Login failed: user \"" . $user . "\" is disabled.", 'info');
    }
    elseif ((int)$row['lockout'] === 1)
    {
        write_debug_log("Login failed: user \"" . $user . "\" is locked out.", 'info');
    }
    elseif (!in_array($row['type'], array('simplerisk', 'ldap', 'saml'), true))
    {
        write_debug_log("Login failed: user \"" . $user . "\" has an unrecognized type \"" . $row['type'] . "\".", 'error');
    }
}

/***************************
 * FUNCTION: IS VALID USER *
 ***************************
 * $upgrade = true is passed exclusively by admin/upgrade.php. When set, the lockout check is
 * intentionally omitted so that the database upgrade page remains accessible even when a schema
 * migration has left the user table in a state that breaks normal login. See the comment block
 * in admin/upgrade.php for a full explanation of the tradeoffs accepted for that entrypoint.
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
        log_user_dne_reason($user);

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
        // Check for a valid SAML user (returns false if custom authentication extra is disabled)
        $valid_saml = call_extra_function(
            'custom_authentication_extra',
            __DIR__ . '/../extras/authentication/index.php',
            'is_valid_saml_user',
            [$user],
            false
        );
    }

    // If either the SAML, AD, or SimpleRisk user are valid
    if ($valid_saml || $valid_ad || $valid_simplerisk) {
        return true;
    }
    else return false;
}

/**********************************
 * FUNCTION: SET USER PERMISSIONS *
 **********************************
 * $upgrade = true is passed exclusively by admin/upgrade.php. When set, only the minimal session
 * values needed for the upgrade UI are populated (value, type, name, lang, admin) rather than the
 * full permission set. This avoids querying permission tables that may be mid-migration. See the
 * comment block in admin/upgrade.php for a full explanation of the upgrade entrypoint design.
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

    $organizational_hierarchy_extra = false;

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
    // Cast to int: PDO returns MySQL ints as strings, and kill_sessions_of_user()
    // depends on the uid|i:N; PHP serialization format to locate sessions by REGEXP.
    $_SESSION['uid'] = (int)$array[0]['value'];
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

    // Close the database connection
    db_close($db);

    // If no matching user was found, return false
    if (empty($array)) {
        return false;
    }

    $storedPassword = $array[0]['password'];
    $userId = (int)$array[0]['value'];

    // password_verify() handles all bcrypt variants ($2a$, $2x$, $2y$) so it
    // correctly verifies both the old crypt()-based hashes (MD5-derived salts,
    // whether username-based or per-user-salt) and any modern password_hash()
    // hashes already in the database.
    if (password_verify($pass, $storedPassword))
    {
        // Rehash on login if the stored hash was produced with the old crypt()
        // path (prefix $2a$, deterministic MD5-derived salt) or if the cost
        // factor differs from what we now require.  password_hash() uses a
        // cryptographically random salt and produces $2y$-prefixed hashes.
        $needsRehash = !str_starts_with($storedPassword, '$2y$')
                    || password_needs_rehash($storedPassword, PASSWORD_BCRYPT, ['cost' => 15]);

        if ($needsRehash)
        {
            $newHash = hash_password($pass);
            $db = db_open();
            $update = $db->prepare("UPDATE user SET password = :password WHERE value = :value");
            $update->bindParam(":password", $newHash, PDO::PARAM_STR, 60);
            $update->bindParam(":value", $userId, PDO::PARAM_INT);
            $update->execute();
            db_close($db);
            write_debug_log("Migrated password hash to password_hash() format for user value: {$userId}.", 'info');
        }

        return true;
    }

    return false;
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
        $token .= $values[random_int(0, $values_count-1)];
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
    }

    // Always wait the same range regardless of whether the username exists,
    // so response timing cannot be used to enumerate valid usernames.
    wait(rand(1000, 3000));
    return ($userid != 0);
}

/**************************************
 * FUNCTION: PASSWORD RESET BY USERID *
 **************************************/
function password_reset_by_userid($userid) {
        
    // Generate a 32 character reset token (~190 bits of entropy)
    $token = generate_token(32);

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

    // Hash the token before storing — the raw token is sent to the user via email;
    // only the SHA-256 digest lives in the database so a DB compromise does not
    // yield immediately usable tokens.
    $token_hash = hash('sha256', $token);

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->bindParam(":token", $token_hash, PDO::PARAM_STR, 64);

    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Send the raw token to the user via email
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

    // Require the mail functions
    require_once(realpath(__DIR__ . '/mail.php'));

    // Send the e-mail
    send_email_immediate($name, $email, $subject, $body);
}

/*************************************
 * FUNCTION: PASSWORD RESET BY TOKEN *
 *************************************/
function password_reset_by_token($username, $token, $password, $repeat_password)
{
    global $lang, $escaper;
    $userid = is_simplerisk_user($username);
    $ip = $_SERVER['REMOTE_ADDR'];

    // Check rate limit before attempting token validation
    if (is_password_reset_rate_limited($username, $ip))
    {
        write_debug_log("Password reset rate limit reached for username \"" . $username . "\" from IP " . $ip . ".", "warning");

        $lockout_time = (int)(get_setting("password_reset_attempt_lockout_time") ?: 5);

        // Display an alert
        set_alert(true, "bad", "Too many invalid reset attempts. Please wait " . $lockout_time . " minute(s) before trying again, or request a new password reset.");

        // Return false
        return false;
    }

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

                // Create the new password hash using a cryptographically random salt
                $hash = hash_password($password);

                // Update the password
                $stmt = $db->prepare("UPDATE user SET password=:hash, last_password_change_date=NOW() WHERE username=:username");
                $stmt->bindParam(":hash", $hash, PDO::PARAM_STR, 60);
                $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
                $stmt->execute();

                // Delete tokens for this user
                $stmt = $db->prepare("DELETE FROM password_reset WHERE username=:username AND token=:token");
                $token_hash = hash('sha256', $token);
                $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
                $stmt->bindParam(":token", $token_hash, PDO::PARAM_STR, 64);
                $stmt->execute();

                // Close the database connection
                db_close($db);

                // Clear rate limit attempts on successful reset
                clear_password_reset_attempts($username);

                write_debug_log("Password reset successful for username \"" . $username . "\" from IP " . $ip . ".", "info");

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
                set_alert(true, "bad", password_error_message($error_code));

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
        // Record the failed attempt against this username and IP
        record_password_reset_attempt($username, $ip);

        write_debug_log("Invalid password reset token submitted for username \"" . $username . "\" from IP " . $ip . ".", "warning");

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

    // Get the password reset token expiration period (default 15 minutes)
    $password_reset_token_expiration = get_setting("password_reset_token_expiration") ?: 15;

    // Delete tokens older than the expiration period
    $delete_stmt = $db->prepare("DELETE FROM password_reset WHERE timestamp < DATE_SUB(NOW(), INTERVAL :expiration MINUTE)");
    $delete_stmt->bindParam(":expiration", $password_reset_token_expiration, PDO::PARAM_INT);
    $delete_stmt->execute();

	// If strict user validation is disabled
	if (get_setting('strict_user_validation') == 0)
	{
        // Search for a valid token
        $stmt = $db->prepare("SELECT 1 FROM password_reset WHERE LOWER(convert(`username` using utf8)) = LOWER(:username) AND token=:token");
	}
	else
	{
        // Search for a valid token
        $stmt = $db->prepare("SELECT 1 FROM password_reset WHERE username=:username AND token=:token");
	}

	// Hash the submitted token so we compare digests, never plaintext
    $token_hash = hash('sha256', $token);

	$stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->bindParam(":token", $token_hash, PDO::PARAM_STR, 64);
	$stmt->execute();

	// Store the list in the array
	$array = $stmt->fetchAll();

	// Close the database connection
	db_close($db);

    return !empty($array);
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

/***************************************************
 * FUNCTION: IS PASSWORD RESET RATE LIMITED        *
 ***************************************************/
function is_password_reset_rate_limited($username, $ip)
{
    // Open the database connection
    $db = db_open();

    // Get the rate limit lockout window and max attempts (default: 5 min, 5 attempts)
    $expiration = (int)(get_setting("password_reset_attempt_lockout_time") ?: 5);
    $max_attempts = 5;

    $stmt = $db->prepare("SELECT attempts FROM password_reset_attempts WHERE username=:username AND ip=:ip AND last_attempt >= DATE_SUB(NOW(), INTERVAL :expiration MINUTE)");
    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->bindParam(":ip", $ip, PDO::PARAM_STR, 45);
    $stmt->bindParam(":expiration", $expiration, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return $row && (int)$row['attempts'] >= $max_attempts;
}

/***************************************************
 * FUNCTION: RECORD PASSWORD RESET ATTEMPT         *
 ***************************************************/
function record_password_reset_attempt($username, $ip)
{
    // Open the database connection
    $db = db_open();

    // Clean up attempts older than the lockout window
    $expiration = (int)(get_setting("password_reset_attempt_lockout_time") ?: 5);
    $stmt = $db->prepare("DELETE FROM password_reset_attempts WHERE last_attempt < DATE_SUB(NOW(), INTERVAL :expiration MINUTE)");
    $stmt->bindParam(":expiration", $expiration, PDO::PARAM_INT);
    $stmt->execute();

    // Insert or increment the attempt counter for this username+IP pair
    $stmt = $db->prepare("INSERT INTO password_reset_attempts (username, ip, attempts, last_attempt) VALUES (:username, :ip, 1, NOW()) ON DUPLICATE KEY UPDATE attempts=attempts+1, last_attempt=NOW()");
    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->bindParam(":ip", $ip, PDO::PARAM_STR, 45);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***************************************************
 * FUNCTION: CLEAR PASSWORD RESET ATTEMPTS         *
 ***************************************************/
function clear_password_reset_attempts($username)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DELETE FROM password_reset_attempts WHERE username=:username");
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
	write_debug_log("SCRIPT NAME: " . $_SERVER['SCRIPT_NAME'], "debug");

	// If a session is not currently started and active
	if (session_status() === PHP_SESSION_NONE)
	{
		write_debug_log("A session is not currently started and active.", "debug");

		// If we are using the database for sessions
		if (use_database_for_sessions())
		{
			write_debug_log("We are using the database for sessions.", "debug");

			// Set the custom session save handler
			SimpleRiskSessionHandler::register();
		}

		// If we are running an old version of PHP
		if (PHP_VERSION_ID < 70300)
		{
			write_debug_log("We are running a version of PHP < 7.3 (" . PHP_VERSION_ID . ").", "debug");
			write_debug_log("Setting the session cookie parameters.", "debug");

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
			write_debug_log("We are running a newer version of PHP >= 7.3 (" . PHP_VERSION_ID . ").", 'debug');
			write_debug_log("Setting the session cookie parameters.", 'debug');

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
		write_debug_log("Setting the current session name.", "debug");
		session_name('SimpleRisk');

		// Start the session
		write_debug_log("Starting the session.", "debug");
		session_start();
	}

	// Check for session timeout or renegotiation
	write_debug_log("Checking for session timeout or renegotiation.", "debug");
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
					write_debug_log("Admin permission is required.", "debug");
					enforce_permission("admin");
                    break;
                case "check_assessments":
					write_debug_log("Assessments permission is required.", "debug");
					enforce_permission("assessments");
                    break;
                case "check_assets":
					write_debug_log("Assets permission is required.", "debug");
					enforce_permission("asset");
                    break;
                case "check_compliance":
					write_debug_log("Compliance permission is required.", "debug");
					enforce_permission("compliance");
                    break;
                case "check_governance":
					write_debug_log("Governance permission is required.", "debug");
					enforce_permission("governance");
                    break;
                case "check_riskmanagement":
					write_debug_log("Risk management permission is required.", "debug");
					enforce_permission("riskmanagement");
                    break;
                case "check_any_of":
					// OR semantics: pass an array of permission tokens.
					// Authorization succeeds if the caller holds any one of them.
					$authorized = false;
					if (is_array($value)) {
						foreach ($value as $any_perm) {
							if (check_permission($any_perm)) {
								$authorized = true;
								break;
							}
						}
					}
					if (!$authorized) {
						$any_list = is_array($value) ? implode(', ', $value) : '(invalid value)';
						write_debug_log("User does not have any of the required permissions: {$any_list}. Redirecting to login.", "info");
						header("Location: ../index.php");
						exit(0);
					}
                    break;
				case "check_im":
					write_debug_log("Incident Management Incidents permission is required.", "debug");
					enforce_permission("im_incidents");
					break;
				case "check_im_reporting":
					write_debug_log("Incident Management Reporting permission is required.", "debug");
					enforce_permission("im_reporting");
					break;
				case "check_im_configure":
					write_debug_log("Incident Management Configure permission is required.", "debug");
					enforce_permission("im_configure");
					break;
				case "check_vm_vulnerabilities":
				    write_debug_log("Vulnerability Management 'Vulnerabilities' permission is required.", "debug");
				    enforce_permission("vm_vulnerabilities");
				    break;
				case "check_vm_configure":
				    write_debug_log("Vulnerability Management 'Configure' permission is required.", "debug");
				    enforce_permission("vm_configure");
				    break;
                case "check_ai":
                    write_debug_log("Artificial Intelligence permission is required.", "debug");
                    enforce_permission("ai_access");
                    break;
			}
		}
	}

	// Capture the current page URL as the user's most recent legitimate
	// landing point. redirect_permission_denied() reads this back when a
	// later permission check fails, so the user is bounced to where they
	// came from with a toast rather than to a generic dashboard.
	//
	// Skip:
	//   - non-GET requests (POST handlers shouldn't anchor navigation)
	//   - pages that opted out via 'is_action' => true (file downloads,
	//     POST/AJAX action handlers, and pages with a secondary check
	//     that may itself redirect — those would loop back to themselves)
	//
	// Source the URL from SCRIPT_FILENAME + http_build_query($_GET) via
	// get_encoded_request_uri() so the stored value is server-derived
	// rather than reflected from request input.
	if (
		isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET'
		&& empty($permissions['is_action'])
	) {
		$base_url = !empty($_SESSION['base_url']) ? $_SESSION['base_url'] : get_base_url();
		$_SESSION['workflow_start'] = rtrim($base_url, '/') . get_encoded_request_uri();
	}
}

/***************************
 * FUNCTION: SESSION CHECK *
 ***************************/
function session_check()
{
	// Note: garbage collection of expired `sessions` rows is NOT called
	// here. The previous "sess_gc on every request" cadence compounded
	// row-lock contention once the session handler started using
	// SELECT ... FOR UPDATE — every page load tried to DELETE from a
	// table where some row was potentially X-locked by another worker.
	//
	// The actual security gate is the activity / absolute timeout
	// enforcement below: it reads $_SESSION['LAST_ACTIVITY'] and
	// $_SESSION['CREATED'] on every authenticated request and destroys
	// the session if either is exceeded. That gate is unaffected by
	// whether or not the underlying DB row has been physically gc'd —
	// a still-present row past its timeout is rejected here too.
	//
	// Hygiene gc still happens, but probabilistically: SimpleRiskSessionHandler
	// sets session.gc_probability=1 / session.gc_divisor=100 (~1% of
	// requests) so PHP invokes our handler's gc() method naturally.

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

/*****************************************************************************
 * CLASS: SIMPLERISK SESSION HANDLER                                         *
 *                                                                           *
 * Reads use SELECT ... FOR UPDATE inside a transaction so concurrent        *
 * requests for the same SID serialize on the row's exclusive lock the same  *
 * way PHP's default file handler serializes via flock(). The lock is held   *
 * from read() (start of request) through write() (end of request), so the   *
 * application's $_SESSION mutations cannot be raced/overwritten by another  *
 * worker that loaded a stale snapshot before this request committed.        *
 *                                                                           *
 * Closes the SR-1691 race that drops set_alert() flash messages when the    *
 * Playwright integration suite shares one admin SID across N parallel       *
 * workers (and the equivalent rare-but-real race between an admin's         *
 * concurrent browser tabs hitting the app at the same time).                *
 *                                                                           *
 * Falls back to the legacy non-locking sess_*() functions on any PDO error  *
 * (e.g. transaction not supported, lock wait timeout) so a degraded DB does *
 * not lock users out — they revert to the previous race-prone-but-working   *
 * behavior. The fallback writes the failure to the debug log so operators   *
 * can see when degradation kicks in.                                        *
 *****************************************************************************/
class SimpleRiskSessionHandler implements SessionHandlerInterface
{
    /**
     * Per-instance PDO held across read() -> write() so a single transaction
     * spans the request's session window. The handler opens its OWN private
     * connection (not the db_open() singleton) so its transaction is isolated
     * from any app-side beginTransaction() calls during the request.
     */
    private ?\PDO $pdo = null;

    /**
     * True when read() has begun a transaction that write()/close() will commit.
     */
    private bool $inTransaction = false;

    /**
     * Per-instance set of operations for which failSafe() has already emitted
     * a warning log line during this request. Prevents repeating the same
     * "falling back" message multiple times if the same op fails twice in one
     * request lifecycle (read after a failover, write after a failover, etc.).
     * Each new request constructs a fresh handler instance, so degraded-DB
     * conditions still surface one warning per request rather than going silent.
     *
     * @var array<string, true>
     */
    private array $failSafeLogged = [];

    /**
     * Constructor is intentionally a no-op. The ini_set side effects that
     * configure PHP's probabilistic gc live in register() instead, so
     * instantiating the handler from a test harness (or any non-session
     * caller) doesn't silently mutate global PHP state. Callers that want
     * the handler attached to session_set_save_handler() go through
     * SimpleRiskSessionHandler::register() — see that method's docblock.
     */
    public function __construct()
    {
    }

    /**
     * Register this handler with PHP's session machinery. Use this in
     * place of `session_set_save_handler(new SimpleRiskSessionHandler())`
     * at every caller — the ini_set lines below are session-machinery
     * configuration and belong here, not inside the constructor.
     *
     * Make PHP's probabilistic garbage collector self-sufficient. Many
     * Debian/Ubuntu PHP packages ship with session.gc_probability=0 because
     * the OS provides a /usr/lib/php/sessionclean cron job — but that
     * cron job only cleans the file-based handler's directory, not our
     * custom `sessions` table. Without setting these, PHP would never
     * invoke SessionHandlerInterface::gc(), and the legacy code's
     * workaround was to call sess_gc() explicitly on every request.
     * That workaround compounds row-lock contention under load, so we
     * set the standard PHP probability instead: ~1% of requests trigger
     * gc, which on a busy install is plenty to keep the table bounded.
     * Only override when the install hasn't explicitly enabled gc.
     */
    public static function register(): void
    {
        if ((int)ini_get('session.gc_probability') === 0) {
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
        session_set_save_handler(new self(), true);
    }

    public function open(string $path, string $name): bool
    {
        // Note: we deliberately do NOT call sess_gc() from open() the way the
        // legacy handler did. gc's bulk "DELETE FROM sessions WHERE access < :old"
        // would block on any other worker's row-level X-lock, hitting
        // innodb_lock_wait_timeout under load. Instead, gc runs through the
        // SessionHandlerInterface::gc() entry point, which PHP invokes
        // probabilistically based on session.gc_probability / session.gc_divisor.
        return true;
    }

    public function close(): bool
    {
        $this->finishTransaction(true);
        $this->pdo = null;
        return true;
    }

    public function read(string $id): string
    {
        try {
            $pdo = $this->getPdo();
            if (!$this->inTransaction) {
                $pdo->beginTransaction();
                $this->inTransaction = true;
            }

            $stmt = $pdo->prepare("SELECT data FROM sessions WHERE `id` = :sess_id FOR UPDATE");
            $stmt->bindParam(':sess_id', $id, \PDO::PARAM_STR, 128);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->clearDegradedFlag();
            return $row ? (string)$row['data'] : '';
        } catch (\Throwable $e) {
            // Connection died, transaction not supported, or lock wait
            // exceeded innodb_lock_wait_timeout. Drop the broken connection,
            // fall back to legacy behavior so the user keeps their session.
            $this->failSafe($e, 'read');
            return sess_read($id);
        }
    }

    public function write(string $id, string $data): bool
    {
        // No transaction means one of:
        //   (a) read() hit a PDO error and failSafe()'d to legacy — stay there
        //       so the request completes via the same handler family that
        //       served the read.
        //   (b) session_regenerate_id(true) just ran. PHP's regen flow is
        //       write(old_id) -> destroy(old_id) -> close() -> generate new id
        //       -> open(). It does NOT call read() with the new id, so when
        //       session_write_close() eventually fires write(new_id, $data)
        //       here, $this->inTransaction is false (destroy committed it
        //       and close() set $this->pdo to null). Falling through to
        //       sess_write() is correct: the new SID's row didn't exist
        //       before this write, so there's no prior state to be raced and
        //       no need for a FOR UPDATE lock on a row we're about to
        //       create. DO NOT "fix" this by adding beginTransaction()
        //       here — without a matching read(), the post-regen close()
        //       would commit (or rollback) a transaction with no
        //       state-versus-state semantics, and the next session_start()
        //       on this handler instance would deadlock against itself.
        if (!$this->inTransaction) {
            return sess_write($id, $data);
        }

        try {
            $pdo = $this->getPdo();
            $access = time();
            $stmt = $pdo->prepare(
                "REPLACE INTO sessions (id, access, data) VALUES (:sess_id, :access, :data)"
            );
            $stmt->bindParam(':sess_id', $id, \PDO::PARAM_STR);
            $stmt->bindParam(':access', $access, \PDO::PARAM_INT);
            $stmt->bindParam(':data', $data, \PDO::PARAM_LOB);
            $stmt->execute();

            $pdo->commit();
            $this->inTransaction = false;
            $this->clearDegradedFlag();
            return true;
        } catch (\Throwable $e) {
            $this->failSafe($e, 'write');
            return sess_write($id, $data);
        }
    }

    public function destroy(string $id): bool
    {
        // Releasing the read-lock first keeps DELETE simple and avoids deadlock
        // risk if other handler instances are also racing on this row.
        $this->finishTransaction(true);
        try {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE `id` = :sess_id");
            $stmt->bindParam(':sess_id', $id, \PDO::PARAM_STR, 128);
            $stmt->execute();
            $this->clearDegradedFlag();
            return true;
        } catch (\Throwable $e) {
            $this->failSafe($e, 'destroy');
            return sess_destroy($id);
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        // Refuse to run if read() already started a transaction on this PDO.
        // PHP's own SessionHandler contract invokes gc *between* open() and
        // read(), so $this->inTransaction is false in the normal flow — but a
        // future caller that triggers session_gc() manually mid-request would
        // otherwise issue a DELETE on the connection holding the open
        // FOR UPDATE, mixing session-row deletion into the read-write
        // transaction. The contract above stays intact; this is belt-and-
        // suspenders for the edge case.
        if ($this->inTransaction) {
            return 0;
        }

        // Use this handler's private PDO so we don't open a third connection
        // (handler + app singleton + a dedicated gc connection). gc is invoked
        // by PHP during session_start, after open() but before read() — so
        // $this->pdo is not yet inside the read-write transaction, and the
        // DELETE here auto-commits before beginTransaction() runs.
        //
        // session_activity_timeout is SimpleRisk's authoritative session
        // lifetime, so it intentionally overrides the caller-supplied
        // $max_lifetime (typically 1440 from PHP defaults) — even when the
        // setting is the smaller of the two. gc() only physically reaps rows
        // that session_check() would already reject on the next request, so
        // reaping on the configured timeout rather than a larger caller value
        // keeps the table aligned with the real expiry policy and never
        // deletes a row a live session still depends on.
        //
        // ORDER BY access ASC LIMIT $cap caps each invocation and prioritizes
        // the oldest rows, so the table converges to bounded size across
        // probabilistic gc calls without ever doing an unbounded DELETE.
        //
        // Cadence math for the cap: with session.gc_probability=1 and
        // session.gc_divisor=100, ~1% of requests trigger gc. A 500-row cap
        // therefore clears up to 500 rows per ~100 requests. After a
        // maintenance window or a heavy Playwright batch that backs up the
        // table to, say, 50_000 stale rows, the table converges in
        // ~10_000 requests. On a busy install that's a few hours; on a
        // quiet one it's a few days. Operators with unusually large
        // backlogs (or unusually quiet installs) can raise the cap via
        // the optional `session_gc_limit` setting; absent that setting,
        // the default of 500 applies — see get_setting() fallback below.
        try {
            $configured = get_setting('session_activity_timeout');
            if ($configured) {
                $max_lifetime = (int)$configured;
            }
            $cap = (int)get_setting('session_gc_limit', 500);
            if ($cap <= 0) {
                $cap = 500;
            }
            $old = time() - $max_lifetime;
            $pdo = $this->getPdo();
            // LIMIT clauses can't be bound as a parameter on every MySQL
            // build; cast to int and concatenate. $cap was sourced from a
            // setting and was just int-validated, so concatenation is safe.
            $stmt = $pdo->prepare(
                "DELETE FROM sessions WHERE `access` < :old ORDER BY `access` ASC LIMIT " . $cap
            );
            $stmt->bindParam(':old', $old, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            // gc failure must never cascade into request failure — log,
            // drop the broken connection so the next getPdo() reopens it,
            // and let the caller's session_start continue.
            if (function_exists('write_debug_log') && !isset($this->failSafeLogged['gc'])) {
                write_debug_log("SimpleRiskSessionHandler::gc: " . $e->getMessage(), 'warning');
                $this->failSafeLogged['gc'] = true;
            }
            $this->pdo = null;
            return 0;
        }
    }

    /**
     * Open (or reuse) this handler's PDO. We deliberately bypass db_open() —
     * that helper is a singleton (shared via $GLOBALS['db_global']) used by
     * application code, and reusing it would mean every app-side query
     * during the request runs inside this handler's transaction, breaking
     * any code that does its own beginTransaction(). Open a private
     * connection instead so the handler's transaction window is isolated
     * from the application.
     *
     * Connection options mirror db_open(): same charset/collation,
     * group_concat_max_len, and time_zone init command so query semantics
     * on this connection match the rest of the app. Doesn't strictly matter
     * for the session-handler queries (LOB data is charset-agnostic, no
     * datetime columns, no GROUP_CONCAT in the handler) but cheap insurance
     * if a future change adds a session-related query that does care.
     *
     * Declared `protected` rather than `private` so the failSafe test suite
     * can subclass the handler with an override that throws \PDOException
     * — that's the only way to deterministically exercise the legacy
     * fallback path without an actual DB outage. No production caller
     * should override this.
     */
    protected function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            $dsn = "mysql:charset=UTF8;dbname=" . DB_DATABASE
                 . ";host=" . DB_HOSTNAME . ";port=" . DB_PORT;
            // PDO::ATTR_ERRMODE defaults to ERRMODE_EXCEPTION on PHP 8.1+, so
            // we don't pass it explicitly. currentTimezoneOffset() comes from
            // functions.php which is required by every entry point before
            // SimpleRiskSessionHandler is instantiated.
            $tz_offset = function_exists('currentTimezoneOffset') ? currentTimezoneOffset() : '+00:00';
            // Set a short innodb_lock_wait_timeout on this handler's connection
            // (default MySQL value is 50s) so that under heavy session contention
            // — typical in Playwright runs where multiple workers share one
            // admin SID via storageState — we fail fast and fall back to the
            // legacy non-locking path via failSafe() rather than letting a
            // single request block all subsequent ones for the full 50s.
            // Affects only this handler's session; app-level queries via
            // db_open() keep the server-default lock wait timeout.
            //
            // Run this connection at READ-COMMITTED isolation (server default
            // is REPEATABLE-READ). In REPEATABLE-READ, `SELECT ... FOR UPDATE
            // WHERE id = :sid` on a non-existent row takes an "insert intention"
            // gap lock on the index range — two concurrent requests creating
            // NEW sessions with IDs that fall in the same primary-key gap can
            // serialize on that gap lock even though the rows themselves are
            // unrelated. Under READ-COMMITTED there's no gap lock on the
            // not-found case, so concurrent fresh-SID creations don't contend.
            // The handler's queries (single-key SELECT followed by REPLACE on
            // that same key) are unaffected by the looser intra-transaction
            // semantics READ-COMMITTED provides, since the read+write happens
            // inside a single transaction that other connections can't observe
            // mid-flight regardless.
            // PHP 8.5 moved PDO MySQL constants to the Pdo\Mysql namespace and
            // deprecated the PDO::MYSQL_ATTR_* aliases. Use the forward-compat
            // lookup so PHP 8.1-8.3 is unchanged.
            $pdo_mysql_init_command = defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? constant('Pdo\Mysql::ATTR_INIT_COMMAND') : \PDO::MYSQL_ATTR_INIT_COMMAND;
            $pdo_mysql_ssl_ca       = defined('Pdo\Mysql::ATTR_SSL_CA')       ? constant('Pdo\Mysql::ATTR_SSL_CA')       : \PDO::MYSQL_ATTR_SSL_CA;
            $options = [
                $pdo_mysql_init_command => "SET NAMES utf8mb4, "
                    . "@@group_concat_max_len = 4294967295, "
                    . "@@session.innodb_lock_wait_timeout = 5, "
                    . "@@session.transaction_isolation = 'READ-COMMITTED', "
                    . "time_zone='" . $tz_offset . "'",
            ];
            if (defined('DB_SSL_CERTIFICATE_PATH') && DB_SSL_CERTIFICATE_PATH !== '') {
                $options[$pdo_mysql_ssl_ca] = DB_SSL_CERTIFICATE_PATH;
            }
            $this->pdo = new \PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
            // Defense-in-depth: the @@session.transaction_isolation assignment in
            // MYSQL_ATTR_INIT_COMMAND above silently no-ops on older MySQL builds
            // where the variable was named `tx_isolation`, and on configurations
            // where global isolation is locked. Re-run the assignment as a
            // standalone statement so the handler is guaranteed to be on
            // READ-COMMITTED on every supported server build — REPEATABLE-READ
            // would take an insert-intention gap lock on the `FOR UPDATE` of a
            // non-existent row, the exact contention the comment block above
            // sets out to avoid.
            $this->pdo->query("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
        }
        return $this->pdo;
    }

    /**
     * Commit (or roll back) any open transaction without throwing. Used from
     * close() / destroy() / failSafe() where we want the cleanest possible
     * exit regardless of error state.
     */
    private function finishTransaction(bool $commit): void
    {
        if ($this->pdo === null || !$this->inTransaction) {
            return;
        }
        try {
            if ($commit && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (\Throwable $ignore) {
            // Best-effort cleanup — swallow so the calling read()/write()
            // path can fall back without re-throwing.
        }
        $this->inTransaction = false;
    }

    /**
     * Drop the broken connection so the next call re-opens fresh, and log the
     * reason so operators can see when the locking handler is degrading to
     * the legacy code path. Logs at most once per op per request so a single
     * cascading failure (e.g. read then write both failing) doesn't double-log;
     * across requests the warning still fires, so sustained degradation
     * remains visible at request granularity.
     */
    private function failSafe(\Throwable $e, string $op): void
    {
        $this->finishTransaction(false);
        $this->pdo = null;
        if (function_exists('write_debug_log') && !isset($this->failSafeLogged[$op])) {
            write_debug_log(
                "SimpleRiskSessionHandler::$op falling back to legacy sess_*() "
                . "after PDO error: " . $e->getMessage(),
                'warning'
            );
            $this->failSafeLogged[$op] = true;
        }
        // Surface degradation to operators via the health-check page. The
        // setting holds the timestamp the current outage began; the
        // health-check banner reads it and shows a warning when non-zero.
        // update_setting() uses db_open() — a separate connection from this
        // handler's private PDO — so even if our private PDO has died, the
        // settings write typically succeeds. If it doesn't, swallow the
        // error: a degraded session handler must never cascade into request
        // failure.
        //
        // Stamp once per outage transition, not once per failed op: a fresh
        // time() on every failed request would defeat update_setting()'s
        // unchanged-value short-circuit and write an audit_log row per
        // request under a sustained outage. The banner only needs non-zero
        // semantics; clearDegradedFlag() resets it to '0' on the first
        // success, so the next outage re-stamps. Guard mirrors
        // clearDegradedFlag()'s.
        //
        // Note: failSafe() can run inside read(), which PHP invokes before
        // session_start() populates $_SESSION. update_setting() may populate
        // its own $_SESSION audit defaults ('System User') as a side effect;
        // that scratch state is harmless because read()'s returned payload is
        // deserialized over $_SESSION immediately afterward.
        try {
            if (function_exists('get_setting') && function_exists('update_setting')) {
                $flag = get_setting('session_handler_degraded');
                if ($flag === false || $flag === '' || $flag === '0') {
                    update_setting('session_handler_degraded', (string)time());
                }
            }
        } catch (\Throwable $ignored) {
            // best-effort; the warning log line above is the primary signal
        }
    }

    /**
     * Clear the operator-visible degradation flag when a session operation
     * has just succeeded on this handler's private PDO. Called from the
     * success paths of read() / write() / destroy(). No-op when the flag
     * is already cleared (the get_setting cache makes the read free, so
     * we save an update_setting round-trip on the common case).
     */
    private function clearDegradedFlag(): void
    {
        if (!function_exists('get_setting') || !function_exists('update_setting')) {
            return;
        }
        try {
            $flag = get_setting('session_handler_degraded');
            if ($flag !== false && $flag !== '' && $flag !== '0') {
                update_setting('session_handler_degraded', '0');
            }
        } catch (\Throwable $ignored) {
            // best-effort recovery signal; never cascade to caller
        }
    }
}

/**************************
 * FUNCTION: SESSION OPEN *
 **************************/
function sess_open($sess_path, $sess_name)
{
        // gc runs probabilistically through SimpleRiskSessionHandler::gc().
        // Removed the legacy unconditional sess_gc(1440) call here so failover
        // paths don't double-open connections (handler's private PDO + the
        // dedicated gc PDO + db_open() singleton).
        return true;
}

/***************************
 * FUNCTION: SESSION CLOSE *
 ***************************/
function sess_close()
{
        // gc runs probabilistically through SimpleRiskSessionHandler::gc().
        // See sess_open() comment above.
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
        // gc runs probabilistically through SimpleRiskSessionHandler::gc();
        // no longer call sess_gc(1440) inline here.
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
    // The configured idle-timeout setting wins over the caller-supplied
    // $sess_maxlifetime (which is typically 1440 from the legacy callers).
    $configured = get_setting("session_activity_timeout");
    if ($configured) {
        $sess_maxlifetime = (int)$configured;
    }
    $old = time() - $sess_maxlifetime;

    // Use a DEDICATED PDO connection — not the shared db_open() singleton —
    // so the DELETE does not run inside any application-level transaction
    // that might be in progress on the singleton, and so the gc is
    // independent of the SimpleRiskSessionHandler's own private connection.
    //
    // Cap each gc invocation with LIMIT so we don't try to delete an
    // unbounded number of rows in one statement. If gc was queued behind
    // an X-lock on one row, we don't want it to scan thousands more after
    // the lock releases. With session.gc_probability=1/100 in the new
    // handler, gc will run again on a future request and chip away the
    // remainder. Single-call cap of 500 rows is plenty for steady-state.
    try {
        $dsn = "mysql:charset=UTF8;dbname=" . DB_DATABASE
             . ";host=" . DB_HOSTNAME . ";port=" . DB_PORT;
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (defined("DB_SSL_CERTIFICATE_PATH") && DB_SSL_CERTIFICATE_PATH !== '') {
            $pdo_mysql_ssl_ca = defined('Pdo\Mysql::ATTR_SSL_CA') ? constant('Pdo\Mysql::ATTR_SSL_CA') : PDO::MYSQL_ATTR_SSL_CA;
            $options[$pdo_mysql_ssl_ca] = DB_SSL_CERTIFICATE_PATH;
        }
        $gc_db = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        // ORDER BY access ASC prioritizes the oldest rows so the table
        // converges to bounded size deterministically across calls.
        $stmt = $gc_db->prepare("DELETE FROM sessions WHERE `access` < :old ORDER BY `access` ASC LIMIT 500");
        $stmt->bindParam(":old", $old, PDO::PARAM_INT);
        $stmt->execute();
    } catch (\Throwable $e) {
        // Don't let gc failure cascade into request failure — log and move on.
        if (function_exists('write_debug_log')) {
            write_debug_log("sess_gc: " . $e->getMessage(), 'warning');
        }
    }

    return true;
}

/********************
 * FUNCTION: LOGOUT *
 ********************/
function logout()
{
    // Get the session username and uid
    $username = $_SESSION['user'] ?? '';
    $uid = $_SESSION['uid'] ?? 0;

    // Audit log
    $message = "Username \"" . $username . "\" logged out successfully.";
    write_log($uid + 1000, $uid, $message, "user");

    // Invalidate all server-side sessions for this user (other browsers,
    // devices, or stolen cookies) so that logout is a complete sign-out.
    // Skip the current session row — SimpleRiskSessionHandler still holds
    // an exclusive row lock on it from this request's session_start(),
    // and kill_sessions_of_user's bulk DELETE would self-deadlock against
    // it. session_destroy() below commits the handler's transaction and
    // then deletes the row, so the current session is still cleaned up.
    kill_sessions_of_user($uid, true);

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
function check_add_password_reuse_history($user_id, $plaintext_password)
{
	$pass_policy_reuse_limit = get_setting('pass_policy_reuse_limit');

	// If the password policy reuse limit is 0
	if ($pass_policy_reuse_limit == 0)
	{
		// We don't need to check
		return true;
	}

	// Open the database connection
	$db = db_open();

	// Fetch all stored password hashes for this user so we can verify each one
	// with password_verify().  A direct SQL hash comparison is not possible here
	// because password_hash() generates a unique random salt each time, so two
	// hashes of the same password will never match as byte strings.
	$stmt = $db->prepare("SELECT id, password, counts FROM user_pass_reuse_history WHERE user_id = :user_id");
	$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Close the connection while we do the (potentially slow) bcrypt verifications
	db_close($db);

	$matchedRow = null;
	foreach ($rows as $row)
	{
		// password_verify() works for both $2y$ (password_hash) and $2a$ (old crypt) bcrypt hashes
		if (password_verify($plaintext_password, $row['password']))
		{
			$matchedRow = $row;
			break;
		}
	}

	// Re-open the database connection to write the result
	$db = db_open();

	if ($matchedRow === null)
	{
		// Password not seen before — store its hash in the reuse history
		$newHash = hash_password($plaintext_password);
		$stmt = $db->prepare("INSERT INTO user_pass_reuse_history(`user_id`, `password`) VALUES(:user_id, :password)");
		$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
		$stmt->bindParam(":password", $newHash, PDO::PARAM_STR, 60);
		$stmt->execute();
		db_close($db);
		return true;
	}
	elseif ($matchedRow['counts'] < $pass_policy_reuse_limit)
	{
		// Password was used before but is still within the allowed reuse count
		$stmt = $db->prepare("UPDATE user_pass_reuse_history SET `counts` = `counts` + 1 WHERE `id` = :id");
		$stmt->bindParam(":id", $matchedRow['id'], PDO::PARAM_INT);
		$stmt->execute();
		db_close($db);
		return true;
	}
	else
	{
		// Password reuse limit reached — block this password
		db_close($db);
		return false;
	}
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
			// If it is possible to reuse password (pass plaintext so password_verify() can check history)
			if(check_add_password_reuse_history($user_id, $new_password))
			{
				// Generate the password hash using a cryptographically random salt
				$hash = hash_password($new_password);

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
        // Check user enc (no-op if encryption extra is disabled)
        call_extra_function(
            'encryption_extra',
            __DIR__ . '/../extras/encryption/index.php',
            'check_user_enc',
            [$user, $pass]
        );

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

    $db = db_open();

    // PHP session serialization stores uid as 'uid|i:N;' — match it at the
    // start of the data string or after a semicolon to avoid false positives
    $uid_pattern = '(^|;)uid\\|i:' . (int)$user_id . ';';

    if ($keep_current_session) {
        $sid = session_id();
        $stmt = $db->prepare("DELETE FROM sessions WHERE `id` <> :session_id AND `data` REGEXP :uid_pattern");
        $stmt->bindParam(":session_id", $sid);
        $stmt->bindParam(":uid_pattern", $uid_pattern);
    } else {
        $stmt = $db->prepare("DELETE FROM sessions WHERE `data` REGEXP :uid_pattern");
        $stmt->bindParam(":uid_pattern", $uid_pattern);
    }

    $stmt->execute();

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