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

        provision_mfa_row_for_uid($uid);
    }

    // Set all users to MFA enabled
    $stmt = $db->prepare("UPDATE `user` set `multi_factor` = 1;");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************************
 * FUNCTION: PROVISION MFA ROW FOR UID        *
 **********************************************
 * Ensures a user has a user_mfa row, created unverified.
 *
 * This is the per-user body of enable_mfa_for_all_users(), extracted so the
 * behaviour that caused the lockout can be tested directly. The whole-table
 * function rewrites multi_factor for every account, so it cannot be exercised
 * against a real database without destroying state; this can.
 *
 * It must NEVER mark the user verified. "Verified" means the user enrolled an
 * authenticator and proved a token, and the login router reads it to choose
 * between the enrolment page (which shows a QR) and the authentication page
 * (token entry only). Marking someone verified who never enrolled sends them to
 * a prompt for a code they cannot generate, with no route to the QR — the
 * lockout this change fixes, admins included.
 *
 * Safe to call repeatedly: the row is only created when absent, and an existing
 * row — including its verified flag and enrolment stamp — is left untouched.
 */
function provision_mfa_row_for_uid($uid)
{
    if (!user_mfa_exists_for_uid($uid))
    {
        // Creates the row with verified = 0 and no enrolment stamp.
        get_mfa_secret_for_uid($uid);
    }
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

/*****************************************************
 * FUNCTION: IS MFA ENROLMENT TRACKING AVAILABLE     *
 *****************************************************
 * Whether user_mfa.enrolled_at exists yet.
 *
 * The column arrives in upgrade_from_20260709001(), and SimpleRisk keeps serving
 * logins while an instance is deployed but not yet upgraded. Every site that
 * touches the column checks here first so that window behaves consistently:
 * reads skip the enrolment guard (leaving users verified rather than downgrading
 * all of them at once) and writes fall back to column-less SQL rather than
 * throwing "Unknown column" and fataling the enrolment page.
 *
 * Deliberately not memoised in a static: the upgrade itself adds the column
 * mid-request, and a cached "false" would then break every write for the rest of
 * that request. This is one information_schema lookup on the login path.
 */
function mfa_enrolment_tracking_available()
{
    return (bool)field_exists_in_table('enrolled_at', 'user_mfa');
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

        // Defence in depth: a verified row must also carry an enrolment stamp.
        //
        // enrolled_at is written by verify_mfa_for_uid(), the only function that
        // sets verified = 1, and upgrade_from_20260709001() backfilled it for
        // every row that was already verified. So after that upgrade the pair
        // (verified = 1, enrolled_at IS NULL) is unreachable through any normal
        // path — it can only appear if something sets the verified flag in bulk
        // without going through verify_mfa_for_uid(), which is exactly the class
        // of bug that stranded users behind a token prompt they could not
        // satisfy. Treating it as unverified routes those users to enrolment
        // instead of locking them out.
        //
        // Deliberately NOT inferred from last_mfa_token being NULL: that column
        // was added by upgrade_from_20240205001() with no backfill, so every
        // user who enrolled before 20240315-001 and has not completed an MFA
        // login since has a NULL there while being genuinely enrolled. Treating
        // those users as unverified would route them to the enrolment page,
        // which renders a QR of their EXISTING secret to anyone holding just the
        // password — turning a lockout fix into an MFA downgrade.
        //
        // array_key_exists (not isset, and not a bare index) so that an instance
        // running this code before the migration has added the column keeps
        // every user verified rather than downgrading all of them at once.
        if ($verified && array_key_exists('enrolled_at', $results) && $results['enrolled_at'] === null)
        {
            $verified = false;
        }
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

    // Set this uid to verified, stamping when the enrolment was confirmed.
    //
    // This is the only place verified is set to 1, which is what lets
    // is_mfa_verified_for_uid() treat a verified row with no enrolled_at as an
    // anomaly rather than a legitimate state. COALESCE keeps the original stamp
    // if the user re-verifies later, so the column stays a record of first
    // enrolment rather than last login.
    //
    // Falls back to column-less SQL before the migration has run, so enrolment
    // still works on a deployed-but-not-yet-upgraded instance.
    $sql = mfa_enrolment_tracking_available()
        ? "UPDATE `user_mfa` SET `verified` = 1, `enrolled_at` = COALESCE(`enrolled_at`, NOW()) WHERE uid = :uid;"
        : "UPDATE `user_mfa` SET `verified` = 1 WHERE uid = :uid;";

    $stmt = $db->prepare($sql);
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

    // Clear the verified flag and the enrolment stamp together.
    //
    // enrolled_at must be non-NULL exactly when the row carries a confirmed
    // enrolment — that is what lets is_mfa_verified_for_uid() treat a verified
    // row with no stamp as an anomaly. Clearing verified while leaving the stamp
    // behind would break that, and COALESCE in verify_mfa_for_uid() would then
    // resurrect the stale date on the next enrolment. The two per-user reset
    // paths (mfa_delete_userid, disable_mfa_for_uid) drop the whole row, so this
    // is the only place the pair could drift apart.
    $sql = mfa_enrolment_tracking_available()
        ? "UPDATE `user_mfa` SET `verified` = 0, `enrolled_at` = NULL WHERE uid = :uid;"
        : "UPDATE `user_mfa` SET `verified` = 0 WHERE uid = :uid;";

    $stmt = $db->prepare($sql);
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

/*****************************************
 * FUNCTION: IS MFA TOKEN A REPLAY *
 *****************************************
 * Returns true when the supplied token is the same one already recorded as the
 * last successfully-used token for this user, which is how we stop a captured
 * token being replayed inside its own 30-second window.
 *
 * $stored_hash is user_mfa.last_mfa_token, which is NULL until the user's first
 * successful verification. A NULL means "no token has been used yet", so nothing
 * can be a replay of it — return false rather than handing the NULL to
 * password_verify(), which raises a deprecation notice on PHP 8.1+ for a null
 * $hash and would otherwise fire on every fresh enrollment.
 */
function is_mfa_token_replay($token, $stored_hash)
{
    // No previously-used token recorded, so this one cannot be a replay
    if ($stored_hash === null || $stored_hash === '')
    {
        return false;
    }

    return password_verify($token, $stored_hash);
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
    // @phan-suppress-next-line PhanTypeInvalidDimOffset -- get_user_by_id() returns the user row including 'username' when valid
    $username = $user ? ($user['username'] ?? '') : '';

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

    // Generate the QR code.
    //
    // RETURNS the markup rather than echoing it. Its one caller,
    // display_mfa_verification_page(), interpolates this into a string it
    // echoes afterwards -- so while this echoed, PHP evaluated the call first
    // and the QR was emitted ABOVE the whole block, leaving the `col-6` meant
    // to hold it empty. That has been the behaviour on both index.php and
    // account/mfa.php since the QR was moved in-process (2f6bf5c6b1).
    return '<img src="'.(new \chillerlan\QRCode\QRCode)->render($data).'" alt="QR Code" width="300px" height="300px" />';
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

    // Check the MFA attempts for this uid
    if (!check_mfa_attempts($uid))
    {
        // If we have too many MFA attempts return false
        return false;
    }

    // Get the POSTed secret
    $verify_secret = isset($_POST['mfa_secret']) ? $_POST['mfa_secret'] : null;

    // Get the secret for the currently logged in user
    $mfa = get_mfa_secret_for_uid($uid);

    // Get the secret key, timestamp, and last token hash
    $secret = $mfa['secret'];
    $user_timestamp = $mfa['timestamp'];
    $user_mfa_token_hash = $mfa['last_mfa_token'];

    // Create a new Google2FA
    $google2fa = new \PragmaRX\Google2FA\Google2FA();

    $timestamp = $google2fa->verifyKeyNewer($secret, $verify_secret, $user_timestamp);

    // If we have a valid MFA token and it is not the last one used
    if ($timestamp !== false && !is_mfa_token_replay($verify_secret, $user_mfa_token_hash))
    {
        // Update the MFA timestamp and token for this UID to prevent replay
        update_mfa_for_uid($uid, $timestamp, $verify_secret);

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
    if ($timestamp !== false && !is_mfa_token_replay($mfa_token, $user_mfa_token_hash))
    {
        // Update the MFA timestamp and token for this UID
        update_mfa_for_uid($uid, $timestamp, $mfa_token);

        // Disable MFA for the user
        disable_mfa_for_uid($uid);

        // Kill any other sessions for this uid
        kill_sessions_of_user($uid, true);

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
        if ($timestamp !== false && !is_mfa_token_replay($mfa_token, $user_mfa_token_hash))
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

    // Emits the token field only. index.php is the sole caller and now supplies
    // the surrounding card, its heading (YourSimpleRiskAccountIsProtected) and
    // its subtitle (VerifyItsYou), so repeating them here would double them up.
    echo "
        <div class='sr-auth-field'>
            <label for='mfa_token'>" . $escaper->escapeHtml($lang['MFAToken']) . "</label>
            <input id='mfa_token' name='mfa_token' type='number' minlength='6' maxlength='6' autofocus='autofocus' class='form-control' inputmode='numeric' autocomplete='one-time-code'/>
        </div>
        <div class='sr-auth-actions'>
            <input type='submit' class='btn btn-submit' name='authenticate' value='" . $escaper->escapeHtml($lang['Verify']) . "' />
        </div>
    ";
}

/******************************************************************
 * The user_mfa enrolment-tracking migration used by the          *
 * 20260709-001 database upgrade. It lives here rather than in    *
 * upgrade.php so upgrade.php holds only the release functions    *
 * and the upgrade harness; upgrade.php require_once's this file. *
 ******************************************************************/
/**********************************************************
 * FUNCTION: MIGRATE USER MFA ENROLMENT TRACKING          *
 **********************************************************
 * Adds and populates user_mfa.enrolled_at.
 *
 * Extracted from upgrade_from_20260709001() so the migration can be exercised
 * directly by tests rather than by a copy of its SQL — a copy would keep passing
 * if this were changed or deleted.
 *
 * ORDERING IS LOAD-BEARING. The login path treats (verified = 1, enrolled_at
 * IS NULL) as an anomaly and routes that user to the enrolment page, which
 * renders a QR of their existing TOTP secret. MySQL DDL is not transactional, so
 * adding a nullable column first and backfilling second would leave every
 * enrolled user in exactly that state for the gap between the two statements —
 * and permanently if the upgrade aborts in between, since logins are still
 * served while the DB version is behind.
 *
 * So the column is added already stamped for every row (NOT NULL DEFAULT
 * CURRENT_TIMESTAMP), then relaxed to nullable, and only then cleared for the
 * rows that never enrolled. Every intermediate state reads as "enrolled", which
 * is the fail-safe direction: at worst a user is asked for a token, never handed
 * their own secret.
 *
 * Idempotent, including after a partial run. Each step is guarded by the
 * condition it establishes rather than all of them sharing one existence check:
 * an upgrade that died between the ADD and the MODIFY would otherwise leave the
 * column permanently NOT NULL, because the re-run would see the column present
 * and skip the MODIFY forever — and unverify_mfa_for_uid() writes NULL to that
 * column at runtime.
 */
function migrate_user_mfa_enrolment_tracking($db) {

    $column_comment = 'When MFA enrolment was confirmed. Backfilled at the 20260709-001 upgrade for rows already verified.';

    // Step 1 — add the column, stamped for every existing row, so no row is ever
    // readable as verified-without-stamp. Guarded on the column's absence.
    if (!field_exists_in_table('enrolled_at', 'user_mfa')) {
        $db->query("
            ALTER TABLE `user_mfa`
            ADD `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            COMMENT '{$column_comment}';
        ");
        echo "Added user_mfa.enrolled_at to record when MFA enrolment was confirmed.<br />\n";
    }

    // Step 2 — relax to nullable so rows that never enrolled can carry NULL.
    // Guarded on the column still being NOT NULL, which is the condition this
    // step removes, so an upgrade interrupted after step 1 still converges.
    $is_nullable = $db->query("
        SELECT `IS_NULLABLE`
        FROM `information_schema`.`COLUMNS`
        WHERE `TABLE_SCHEMA` = DATABASE()
            AND `TABLE_NAME` = 'user_mfa'
            AND `COLUMN_NAME` = 'enrolled_at';
    ")->fetchColumn();

    if ($is_nullable === 'NO') {
        $db->query("
            ALTER TABLE `user_mfa`
            MODIFY `enrolled_at` DATETIME NULL DEFAULT NULL
            COMMENT '{$column_comment}';
        ");
        echo "Made user_mfa.enrolled_at nullable so unenrolled users carry no stamp.<br />\n";
    }

    // Self-heal: stamp any verified row still missing one (a prior partial run).
    $stamped = $db->exec("
        UPDATE `user_mfa`
        SET `enrolled_at` = NOW()
        WHERE `verified` = 1
            AND `enrolled_at` IS NULL;
    ");
    if ($stamped > 0) {
        echo "Recorded an enrolment stamp on " . (int)$stamped . " already-verified MFA record(s).<br />\n";
    }

    // A row that is not verified has no confirmed enrolment, so it must not
    // carry a stamp — including the default one the ADD above applied.
    $cleared = $db->exec("
        UPDATE `user_mfa`
        SET `enrolled_at` = NULL
        WHERE `verified` = 0
            AND `enrolled_at` IS NOT NULL;
    ");
    if ($cleared > 0) {
        echo "Cleared the enrolment stamp from " . (int)$cleared . " unverified MFA record(s).<br />\n";
    }
}

?>