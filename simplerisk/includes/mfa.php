<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));

/**************************************
 * FUNCTION: ENABLE MFA FOR ALL USERS *
 **************************************/
function enable_mfa_for_all_users()
{
    // Open the database connection
    $db = db_open();

    // Get the list of all users
    $stmt = $db->prepare("SELECT * FROM `user`;");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each user
    foreach ($users as $user)
    {
        // Get the user ID
        $uid = $user['value'];

        // Create an entry in the user_mfa table for the user
        verify_mfa_for_uid($uid);
        user_mfa_verified($uid);
    }

    // Set all users to MFA enabled
    $stmt = $db->prepare("UPDATE `user` set `multi_factor` = 1;");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************************
 * FUNCTION: DISABLE MFA FOR UNVERIFIED USERS *
 **********************************************/
function disable_mfa_for_unverified_users()
{
    // Open the database connection
    $db = db_open();

    // Set multi_factor to disabled for all unverified users
    $stmt = $db->prepare("UPDATE `user` u LEFT JOIN `user_mfa` um ON u.value = um.uid SET u.`multi_factor` = 0 WHERE um.verified = 0;");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************************
 * FUNCTION: GET MULTI FACTOR FOR UID *
 **************************************/
function get_multi_factor_for_uid($uid = null)
{
    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // Open the database connection
    $db = db_open();

    // Get the user_mfa table for this uid
    $stmt = $db->prepare("SELECT `multi_factor` FROM `user` WHERE value = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();
    $multi_factor = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the multi factor value
    return $multi_factor['multi_factor'];
}

/*******************************
 * FUNCTION: GET MFA BY USERID *
 *******************************/
function get_mfa_by_userid($uid)
{
    // Open the database connection
    $db = db_open();

    // Get the user_mfa table for this uid
    $stmt = $db->prepare("SELECT * FROM `user_mfa` WHERE uid = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();

    // Get the value for this uid
    $user_mfa = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the user_mfa
    return $user_mfa;
}

/*************************************
 * FUNCTION: IS MFA VERIFIED FOR UID *
 *************************************/
function is_mfa_verified_for_uid($uid = null)
{
    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // Open the database connection
    $db = db_open();

    // Get the user_mfa table for this uid
    $stmt = $db->prepare("SELECT *  FROM `user_mfa` WHERE uid = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If we already have an entry in the user_mfa table
    if (!empty($results))
    {
        // Get the verified value
        $verified = $results['verified'];
    }
    // If we do not already have an entry in the user_mfa table
    else
    {
        // Set it to not verified
        $verified = false;
    }

    // Return the verified value
    return $verified;
}

/*************************************
 * FUNCTION: USER MFA EXISTS FOR UID *
 *************************************/
function user_mfa_exists_for_uid($uid)
{
    // Open the database connection
    $db = db_open();

    // Get the user_mfa table for this uid
    $stmt = $db->prepare("SELECT *  FROM `user_mfa` WHERE uid = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If we already have an entry in the user_mfa table
    if (!empty($results))
    {
        // Return that the entry exists
        return true;
    }
    // If we do not already have an entry in the user_mfa table
    else
    {
        // Return that the entry does not exist
        return false;
    }
}

function user_mfa_verified($uid)
{
    // Open the database connection
    $db = db_open();

    // Get the user_mfa table for this uid
    $stmt = $db->prepare("SELECT *  FROM `user_mfa` WHERE uid = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If we already have an entry in the user_mfa table
    if (!empty($results))
    {
        // Get the verified value
        $verified = $results['verified'];
    }
    // If we do not already have an entry in the user_mfa table
    else
    {
        // Create the MFA for this uid
        get_mfa_secret_for_uid($uid);

        // Set it to not verified
        $verified = false;
    }

    // Return the verified value
    return $verified;
}

/********************************
 * FUNCTION: ENABLE MFA FOR UID *
 ********************************/
function enable_mfa_for_uid($uid = null)
{
    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // Open the database connection
    $db = db_open();

    // Set the user to MFA enabled
    $stmt = $db->prepare("UPDATE `user` SET `multi_factor` = 1 WHERE value = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************
 * FUNCTION: DISABLE MFA FOR UID *
 *********************************/
function disable_mfa_for_uid($uid = null)
{
    global $lang;

    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // If we do not require MFA for all users
    if (!get_setting("mfa_required"))
    {
        // Open the database connection
        $db = db_open();

        // Set the multi_factor value for this user to 0
        $stmt = $db->prepare("UPDATE `user` SET `multi_factor` = 0 WHERE `value` = :uid;");
        $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
        $stmt->execute();

        // Remove any entries in the user_mfa table for this user
        $stmt = $db->prepare("DELETE FROM `user_mfa` WHERE `uid` = :uid;");
        $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        // Display an alert
        set_alert(true, "good", $lang['MFADisabledSuccessfully']);
    }
    // If MFA is required for all users
    else
    {
        // Display an alert
        set_alert(true, "bad", $lang['MFARequiredForAllusers']);
    }
}

/********************************
 * FUNCTION: MFA ENABLED FOR UID *
 ********************************/
function mfa_enabled_for_uid($uid)
{
    // Open the database connection
    $db = db_open();

    // Set the user to MFA enabled
    $stmt = $db->prepare("SELECT `multi_factor` FROM `user` WHERE value = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();
    $multi_factor = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If MFA is enabled for the user
    if ($multi_factor['multi_factor'] === 1)
    {
        return true;
    }
    else return false;
}

/*******************************
 * FUNCTION: MFA DELETE USERID *
 *******************************/
function mfa_delete_userid($uid)
{
    // Open the database connection
    $db = db_open();

    // Delete the user_mfa entry for this user ID
    $stmt = $db->prepare("DELETE FROM `user_mfa` WHERE uid = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/********************************
 * FUNCTION: VERIFY MFA FOR UID *
 ********************************/
function verify_mfa_for_uid($uid)
{
    // Open the database connection
    $db = db_open();

    // Set this uid to verified
    $stmt = $db->prepare("UPDATE `user_mfa` SET `verified` = 1 WHERE uid = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************
 * FUNCTION: UNVERIFY MFA FOR UID *
 **********************************/
function unverify_mfa_for_uid($uid)
{
    // Open the database connection
    $db = db_open();

    // Set this uid to verified
    $stmt = $db->prepare("UPDATE `user_mfa` SET `verified` = 0 WHERE uid = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: GET MFA SECRET FOR UID *
 ************************************/
function get_mfa_secret_for_uid($uid)
{
    // Open the database connection
    $db = db_open();

    // Check if we already have an entry in the user_mfa table for this user
    $stmt = $db->prepare("SELECT * FROM `user_mfa` WHERE `uid` = :uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If we already have an entry in the user_mfa table
    if (!empty($results))
    {
        // Return the result
        return $results;
    }
    // Otherwise, create a new entry in the user_mfa table
    else
    {
        // Create the new MFA secret key
        $secret = create_mfa_secret_for_uid($uid);
    }

    // Return the secret
    return $secret;
}

/********************************
 * FUNCTION: UPDATE MFA FOR UID *
 ********************************/
function update_mfa_for_uid($uid, $timestamp, $token)
{
    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // Create a hash of the token
    $token_hash = password_hash($token, PASSWORD_BCRYPT);

    // If the timestamp is 1 set it to the current unix timestamp divided by the key regeneration period of 30s
    $timestamp = ($timestamp === true ? time() / 30 : $timestamp);

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("UPDATE `user_mfa` SET timestamp=:timestamp, `last_mfa_token`=:token_hash WHERE uid=:uid;");
    $stmt->bindParam(":timestamp", $timestamp, PDO::PARAM_INT);
    $stmt->bindParam(":token_hash", $token_hash);
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: CREATE MFA SECRET FOR UID *
 ***************************************/
function create_mfa_secret_for_uid($uid = null)
{
    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // If we don't already have a user_mfa entry for this user
    if (!user_mfa_exists_for_uid($uid))
    {
        // Open the database connection
        $db = db_open();

        // Create a new Google2FA
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        // Create the new MFA secret key
        $secret = $google2fa->generateSecretKey();

        // Store it in the database
        $stmt = $db->prepare("INSERT INTO `user_mfa` (`uid`, `verified`, `secret`) VALUES (:uid, 0, :secret);");
        $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
        $stmt->bindParam(":secret", $secret, PDO::PARAM_STR);
        $stmt->execute();

        // Get the results
        $stmt = $db->prepare("SELECT * FROM `user_mfa` WHERE uid=:uid;");
        $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetch(PDO::FETCH_ASSOC);

        // Close the database connection
        db_close($db);

        // Return the MFA secret
        return $results;
    }
}

/***********************************
 * FUNCTION: GET MFA QR CODE IMAGE *
 ***********************************/
function get_mfa_qr_code_image($uid)
{
    // Get the username for this uid
    $user = get_user_by_id($uid);
    $username = $user['username'];

    // Get the MFA secret for the authenticated user
    $mfa = get_mfa_secret_for_uid($uid);

    // Get the secret key
    $secret = $mfa['secret'];

    // Create a TOTP URI
    $parameters = [
        "secret" => $secret,
        "issuer" => "SimpleRisk",
        "image" => "https://www.simplerisk.com/sites/default/files/logos/logo.png",
    ];

    // Build an HTTP string from the parameters
    $totp_parameters = http_build_query($parameters, '', '&');

    // Construct the TOTP URI
    $data = "otpauth://totp/SimpleRisk:" . $username . "?" . $totp_parameters;

    // Generate the QR code
    echo '<img src="'.(new \chillerlan\QRCode\QRCode)->render($data).'" alt="QR Code" width="300px" height="300px" />';
}

/********************************
 * FUNCTION: PROCESS MFA VERIFY *
 ********************************/
function process_mfa_verify($uid = null)
{
    global $lang;

    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // Get the POSTed secret
    $verify_secret = isset($_POST['mfa_secret']) ? $_POST['mfa_secret'] : null;

    // Get the secret for the currently logged in user
    $mfa = get_mfa_secret_for_uid($uid);

    // Get the secret key
    $secret = $mfa['secret'];

    // Create a new Google2FA
    $google2fa = new \PragmaRX\Google2FA\Google2FA();

    // If the secrets match
    if ($google2fa->verifyKey($secret, $verify_secret))
    {
        // Set the user to MFA enabled
        enable_mfa_for_uid($uid);

        // Set the user to MFA verified
        verify_mfa_for_uid($uid);

        // Kill any other sessions for this uid
        kill_sessions_of_user($uid, true);

        // Display an alert
        set_alert(true, "good", $lang['MFAEnabledSuccessfully']);

        // Return true
        return true;
    }
    else return false;
}

/*********************************
 * FUNCTION: PROCESS MFA DISABLE *
 *********************************/
function process_mfa_disable($uid = null)
{
    global $lang;

    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // Get the POSTed MFA token
    $mfa_token = isset($_POST['mfa_token']) ? $_POST['mfa_token'] : null;

    // Get the user_mfa for the uid
    $mfa = get_mfa_secret_for_uid($uid);

    // Get the secret key, timestamp and mfa_token_hash
    $user_secret = $mfa['secret'];
    $user_timestamp = $mfa['timestamp'];
    $user_mfa_token_hash = $mfa['last_mfa_token'];

    // Create a new Google2FA
    $google2fa = new \PragmaRX\Google2FA\Google2FA();

    $timestamp = $google2fa->verifyKeyNewer($user_secret, $mfa_token, $user_timestamp);

    // If we have a valid MFA token and it is not the last one used
    if ($timestamp !== false && !password_verify($mfa_token, $user_mfa_token_hash))
    {
        // Update the MFA timestamp and token for this UID
        update_mfa_for_uid($uid, $timestamp, $mfa_token);

        // Disable MFA for the user
        disable_mfa_for_uid($uid);

        // Display an alert
        set_alert(true, "good", $lang['MFADisabledSuccessfully']);

        // Return true
        return true;
    }
    // If the secrets don't match
    else
    {
        // Display an alert
        set_alert(true, "bad", $lang['MFAVerificationFailed']);

        // Return false
        return false;
    }
}

/****************************************
 * FUNCTION: CONFIRM MATCHING MFA TOKEN *
 ****************************************/
function does_mfa_token_match($mfa_token = null, $uid = null)
{
    // If the MFA token was not provided
    if($mfa_token === null)
    {
        // Set the MFA token to the POSTed value
        $mfa_token = isset($_POST['mfa_token']) ? $_POST['mfa_token'] : null;
    }

    // If the uid is null
    if ($uid === null )
    {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    // Check the MFA attempts for this uid
    if (!check_mfa_attempts($uid))
    {
        // If we have too many MFA attempts return false
        return false;
    }
    // Otherwise keep checking the user MFA
    else
    {
        // Get the user_mfa for the uid
        $mfa = get_mfa_secret_for_uid($uid);

        // Get the secret key, timestamp and mfa_token_hash
        $user_secret = $mfa['secret'];
        $user_timestamp = $mfa['timestamp'];
        $user_mfa_token_hash = $mfa['last_mfa_token'];

        // Create a new Google2FA
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        $timestamp = $google2fa->verifyKeyNewer($user_secret, $mfa_token, $user_timestamp);

        // If we have a valid MFA token
        if ($timestamp !== false && !password_verify($mfa_token, $user_mfa_token_hash))
        {
            // Update the MFA timestamp and token for this UID
            update_mfa_for_uid($uid, $timestamp, $mfa_token);

            // Return true
            return true;
        }
        else return false;
    }
}

/********************************
 * FUNCTION: CHECK MFA ATTEMPTS *
 ********************************/
function check_mfa_attempts($uid)
{
    // Open the database connection
    $db = db_open();

    // Delete all MFA attempts over a minute old
    $stmt = $db->prepare("DELETE FROM `user_mfa_attempts` WHERE `timestamp` < (NOW() - INTERVAL 1 MINUTE);");
    $stmt->execute();

    // Add this as a new MFA attempt
    $stmt = $db->prepare("INSERT INTO `user_mfa_attempts` (`userid`) VALUES (:uid);");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();

    // Get the number of MFA attempts over the past minute
    $stmt = $db->prepare("SELECT * FROM `user_mfa_attempts` WHERE userid=:uid;");
    $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If we had more than 5 attempts in the past minute
    if (count($attempts) > 5)
    {
        // Return false
        return false;
    }
    // Otherwise, return true
    else return true;
}

/*******************************************
 * FUNCTION: DISPLAY MFA VERIFICATION PAGE *
 *******************************************/
function display_mfa_verification_page($uid = null)
{

    global $escaper, $lang;

    // If the uid is null
    if ($uid === null ) {
        // Set it to the session uid
        $uid = $_SESSION['uid'];
    }

    echo "
        <h4 class='m-b-30'>" . $escaper->escapeHtml($lang['ProtectYourSimpleRiskAccount']) . "</h4>
    ";

    // Get the multi_factor value for this uid
    $multi_factor = get_multi_factor_for_uid($uid);

    // If the user has Duo or Toopher for MFA
    if ($multi_factor == 2 || $multi_factor == 3) {
        // Display a message about them being removed
        echo "
        <h5 class='m-b-20'>" . $escaper->escapeHtml($lang['DuoToopherRemoved']) . "</h5>
        ";
    }

    echo "
        <h5 class='m-b-20'>" . $escaper->escapeHtml($lang['2FADescription']) . "</h5>

        <div class='row align-items-center m-b-20'>
            <div class='col-6'>
                <h5>" . $escaper->escapeHtml($lang['2FAStep1']) . "</h5>
            </div>
            <div class='col-6'>
                <h5>" . $escaper->escapeHtml($lang['2FAStep2']) . "</h5>
            </div>
        </div>
        
        <div class='row'>
            <div class='col-6'>
                " . get_mfa_qr_code_image($uid) . "
            </div>
            <div class='col-6'>
                <div class='d-flex'>
                    <input class='form-control m-r-10' name='mfa_secret' type='number' minlength='6' maxlength='6' autofocus='autofocus' />
                    <input class='btn btn-submit' type='submit' name='verify' value='" . $escaper->escapeHtml($lang['Verify']) . "' />
                </div>
            </div>
        </div>
    ";
}

/************************************
 * FUNCTION: DISPLAY MFA RESET PAGE *
 ************************************/
function display_mfa_reset_page()
{

    global $lang, $escaper;

    echo "
        <h4 class='m-b-30'>" . $escaper->escapeHtml($lang['YourSimpleRiskAccountIsProtected']) . "</h4>
    ";

    // If we do not require MFA for all users
    if (!get_setting("mfa_required")) {
    
        // Allow MFA to be disabled
        echo "
        <h5 class='m-b-20'>" . $escaper->escapeHtml($lang['ToDisableMFA']) . "</h5>
        <div class='row m-b-20'>
            <div class='col-6 d-flex align-items-center'>
                <label style='width: 100px; min-width: 100px;'>" . $escaper->escapeHtml($lang['MFAToken']) . ":</label>
                <input name='mfa_token' type='number' minlength='6' maxlength='6' class='form-control m-r-20'/>
                <input type='submit' class='btn btn-dark' name='disable' value='" . $escaper->escapeHtml($lang['Disable']) . "' />
            </div>
        </div>
        ";

    // Otherwise display a message that disabling MFA is not available
    } else {

        echo "
        <h5 class='m-b-20'>" . $escaper->escapeHtml($lang['MFARequiredForAllusers']) . "</h5>
        ";

    }
}

/*********************************************
 * FUNCTION: DISPLAY MFA AUTHENTICATION PAGE *
 *********************************************/
function display_mfa_authentication_page()
{

    global $lang, $escaper;

    echo "
        <div class='card' style='margin-top: 43.8px;'>
            <div class='card-body'>
                <h4 class='m-b-30'>" . $escaper->escapeHtml($lang['YourSimpleRiskAccountIsProtected']) . "</h4>
                <h5 class='m-b-20'>" . $escaper->escapeHtml($lang['VerifyItsYou']) . "</h5>
                <div class='d-flex align-items-center m-b-20'>
                    <label style='width: 100px; min-width: 100px;'>" . $escaper->escapeHtml($lang['MFAToken']) . ":</label>
                    <input name='mfa_token' type='number' minlength='6' maxlength='6' autofocus='autofocus' class='form-control m-r-20'/>
                    <input type='submit' class='btn btn-submit' name='authenticate' value='" . $escaper->escapeHtml($lang['Verify']) . "' />
                </div>
            </div>
        </div>
    ";
}

?>