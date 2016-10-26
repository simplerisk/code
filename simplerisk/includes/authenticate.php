<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/messages.php'));
require_once(realpath(__DIR__ . '/alerts.php'));

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

	// Get the users unique salt
	$stmt = $db->prepare("SELECT salt FROM user WHERE username = :username");
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
function get_user_type($user)
{
	// Open the database connection
	$db = db_open();

	// Query the DB for a matching enabled user
        $stmt = $db->prepare("SELECT type FROM user WHERE enabled = 1 AND username = :user");
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
	$type = get_user_type($user);

	// If the user does not exist
	if ($type == "DNE")
	{
		// Return that the user is not valid
		return false;
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
			$valid_ad = is_valid_ad_user($user, $pass);
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
	if ($valid_saml || $valid_ad || $valid_simplerisk)
	{
		// Set the user permissions
		set_user_permissions($user, $pass, $upgrade);

		return true;
	}
	else return false;
}

/**********************************
 * FUNCTION: SET USER PERMISSIONS *
 **********************************/
function set_user_permissions($user, $pass, $upgrade = false)
{
	// Open the database connection
        $db = db_open();

	// If we are not doing an upgrade
	if (!$upgrade)
	{
        	// Query the DB for the users complete information
        	$stmt = $db->prepare("SELECT value, type, name, lang, assessments, asset, admin, review_veryhigh, review_high, review_medium, review_low, review_insignificant, submit_risks, modify_risks, plan_mitigations, close_risks FROM user WHERE username = :user");
	}
	// If we are doing an upgrade
	else
	{
		// Query the DB for minimal user permissions needed
		$stmt = $db->prepare("SELECT value, type, name, lang, admin FROM user WHERE username = :user");
	}

        $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Set the minimal session values
        $_SESSION['uid'] = $array[0]['value'];
        $_SESSION['user'] = $user;
        $_SESSION['name'] = $array[0]['name'];
        $_SESSION['admin'] = $array[0]['admin'];
	$_SESSION['user_type'] = $array[0]['type'];

	// If we are not doing an upgrade
	if (!$upgrade)
	{
		// Set additional session values
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

		// If the encryption extra is enabled
		if (encryption_extra())
		{
			// Load the extra
			require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

			// Set the encrypted password in the session
			$_SESSION['encrypted_pass'] = get_enc_pass($user, $pass);
		}
	}

	// If the users language is not null
	if (!is_null($array[0]['lang']))
	{
		// Set the session value
		$_SESSION['lang'] = $array[0]['lang'];
	}
	// Otherwise, the session should use the default language
	else $_SESSION['lang'] = LANG_DEFAULT;
}

/**************************
 * FUNCTION: GRANT ACCESS *
 **************************/
function grant_access()
{
	// Change the session id to prevent session fixation
	session_regenerate_id(true);

	// Grant access
	$_SESSION["access"] = "granted";

        // Update the last login
        update_last_login($_SESSION['uid']);

        // Audit log
        $risk_id = 1000;
        $message = "Username \"" . $_SESSION['user'] . "\" logged in successfully.";
        write_log($risk_id, $_SESSION['uid'], $message);
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

        // Query the DB for a matching user and hash
        $stmt = $db->prepare("SELECT password FROM user WHERE username = :user");
        $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

	// Get the stored password
	$storedPassword = $array[0]['password'];

        // Close the database connection
        db_close($db);

	// If the passwords are equal
	if (($providedPassword == $storedPassword) || ($oldProvidedPassword == $storedPassword))
	{
		return true;
	}
	else return false;
}

/********************************
 * FUNCTION: IS SIMPLERISK USER *
 ********************************/
function is_simplerisk_user($username)
{
        // Open the database connection
        $db = db_open();

        // Query the DB for a matching user and hash
        $stmt = $db->prepare("SELECT value FROM user WHERE type = 'simplerisk' AND username = :username");
        $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

	// Close the database connection
        db_close($db);

	// If the user does not exist return 0
	if (empty($array))
	{
		return 0;
	}
	// Otherwise, return the user id value
	else return $array[0]['value'];
}

/****************************
 * FUNCTION: GENERATE TOKEN *
 ****************************/
function generate_token($size)
{               
        $token = "";
        $values = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
        
        for ($i = 0; $i < $size; $i++)
        {
                $token .= $values[array_rand($values)];
        }
 
        return $token;
}

/****************************************
 * FUNCTION: PASSWORD RESET BY USERNAME *
 ****************************************/
function password_reset_by_username($username)
{               
        $userid = is_simplerisk_user($username);
        
        // Check if the username exists
        if ($userid != 0)
        {       
                password_reset_by_userid($userid);
        
                return true;
        }
        else return false;
}

/**************************************
 * FUNCTION: PASSWORD RESET BY USERID *
 **************************************/
function password_reset_by_userid($userid)
{
        
        // Generate a 20 character reset token
        $token = generate_token(20);

        // Open the database connection
        $db = db_open();

        // Get the users e-mail address
        $stmt = $db->prepare("SELECT username, name, email FROM user WHERE value=:userid");

        $stmt->bindParam(":userid", $userid, PDO::PARAM_INT);

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        $username = $array[0]['username'];
        $name = $array[0]['name'];
        $email = $array[0]['email'];

        // Insert into the password reset table
        $stmt = $db->prepare("INSERT INTO password_reset (`username`, `token`) VALUES (:username, :token)");

        $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
        $stmt->bindParam(":token", $token, PDO::PARAM_STR, 20);

        $stmt->execute();

        // Close the database connection
        db_close($db);

        // Send the reset e-mail
        send_reset_email($username, $name, $email, $token);

	// If this was submitted by an unauthenticated user
	if (!isset($_SESSION['uid']))
	{
		$user = "Unauthenticated";
		$uid = 0;
	}
	// Otherwise, set the user and uid
	else
	{
		$user = $_SESSION['user'];
		$uid = $_SESSION['uid'];
	}

	// Audit log
	$risk_id = 1000;
	$message = "A password reset request was submitted for user \"" . $username . "\" by the \"" . $user . "\" user.";
	write_log($risk_id, $uid, $message);
}

/******************************
 * FUNCTION: SEND RESET EMAIL *
 ******************************/
function send_reset_email($username, $name, $email, $token)
{
        $to = $email;
        $subject = "[SIMPLERISK] Password Reset Token";

        // To send HTML mail, the Content-type header must be set
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";

        // Additional headers
        $headers .= "From: SimpleRisk <noreply@simplerisk.it>\r\n";
        $headers .= "Reply-To: SimpleRisk <noreply@simplerisk.it>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Create the full HTML message
        $body = "<html><body>\n";
        $body .= "<p>Hello " . $name.",</p>\n";
        $body .= "<p>A request was submitted to reset your SimpleRisk password.</p>\n";
	$body .= "<b>Username:</b>&nbsp;&nbsp;".$username."<br/>\n";
	$body .= "<b>Reset Token:</b>&nbsp;&nbsp;".$token."<br/>\n";
	$body .= "<p>You may now use the \"<u>Forgot your password</u>\" link on the SimpleRisk log in page to reset your password.</p>";
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
	$userid = is_simplerisk_user($username);

	// If the reset token is valid
	if (is_valid_reset_token($username, $token))
	{
		// If the username exists
		if ($userid != 0)
		{
        		// Check the password
        		$error_code = valid_password($password, $repeat_password);

        		// If the password is valid
        		if ($error_code == 1)
			{
			        // Open the database connection
        			$db = db_open();

                        	// Create the new password hash
                        	$salt = generateSalt($username);
                        	$hash = generateHash($salt, $password);

                        	// Update the password
                        	$stmt = $db->prepare("UPDATE user SET password=:hash WHERE username=:username");
                        	$stmt->bindParam(":hash", $hash, PDO::PARAM_STR, 60);
                        	$stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
                        	$stmt->execute();

			        // Close the database connection
        			db_close($db);

                                // If the encryption extra is enabled
                                if (encryption_extra())
                                {
                                        // Load the extra
                                        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

                                        // Set the new encrypted password
                                        set_enc_pass($username, $password, $_SESSION['encrypted_pass']);
                                }

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
		// Display an alert
		set_alert(true, "bad", "There was an error with your password reset.");

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

	// Increment the attempts for the username
	$stmt = $db->prepare("UPDATE password_reset SET attempts=attempts+1 WHERE username=:username");
	$stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
	$stmt->execute();

        // Search for a valid token
        $stmt = $db->prepare("SELECT attempts FROM password_reset WHERE username=:username AND token=:token");

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

/***************************
 * FUNCTION: SESSION CHECK *
 ***************************/
function session_check()
{
	// Perform session garbage collection
	sess_gc(1440);

        // Last request was more $last_activity
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > LAST_ACTIVITY_TIMEOUT))
        {
                // unset $_SESSION variable for the run-time
                session_unset();

                // destroy session data in storage
                session_destroy();

		// Return false
		return false;
        }
        // update last activity time stamp
        $_SESSION['LAST_ACTIVITY'] = time();

        // If the session created value has not been set
        if (!isset($_SESSION['CREATED']))
        {
                // Set it with the current time
                $_SESSION['CREATED'] = time();
        }
        // Otherwise check if it was created more than $created
        else if (time() - $_SESSION['CREATED'] > SESSION_RENEG_TIMEOUT)
        {
                // change session ID for the current session an invalidate old session ID
                session_regenerate_id(true);

                // update creation time
                $_SESSION['CREATED'] = time();
        }

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
        $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 32);
        $stmt->execute();
        $array = $stmt->fetchAll();

/*
        // If the session does not exist
        if (empty($array))
        {
                $current_time = time();

                // Create the session
                $stmt = $db->prepare("INSERT INTO sessions (id, access) VALUES (:sess_id, :current_time)");
                $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 32);
                $stmt->bindParam(":current_time", $current_time, PDO::PARAM_INT, 10);
                $stmt->execute();

                $return = "";
        }
        // The session does exist
        else
        {
                $stmt = $db->prepare("UPDATE sessions SET `access`=:current_time WHERE `id`=:sess_id");
                $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 32);
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
                return false;
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
	$stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 32);
        $stmt->bindParam(":access", $access, PDO::PARAM_INT);
        $stmt->bindParam(":data", $data, PDO::PARAM_STR);
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
        $stmt->bindParam(":sess_id", $sess_id, PDO::PARAM_STR, 32);
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
	$risk_id = 1000;
	$message = "Username \"" . $username . "\" logged out successfully.";
	write_log($risk_id, $uid, $message);

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
}

?>
