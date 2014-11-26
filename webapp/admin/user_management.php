<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/libs.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// If we want to enable the Content Security Policy (CSP) - This may break Chrome
if (CSP_ENABLED == "true") {
    // Add the Content-Security-Policy header
    header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
}

// Session handler is database
if (USE_DATABASE_FOR_SESSIONS == "true") {
    session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
}

// Start the session
session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
session_start('LessRisk');

// Include the language file
require_once(language_file());

require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted") {
    header("Location: ../index.php");
    exit(0);
}

// Default is no alert
$alert = false;

// Check if access is authorized
if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1") {
    header("Location: ../index.php");
    exit(0);
}

// Check if a new user was submitted
if (isset($_POST['add_user'])) {
    $type = $_POST['type'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $user = $_POST['new_user'];
    $pass = $_POST['password'];
    $repeat_pass = $_POST['repeat_password'];
    $teams = $_POST['team'];
    $admin = isset($_POST['admin']) ? '1' : '0';
    $submit_risks = isset($_POST['submit_risks']) ? '1' : '0';
    $modify_risks = isset($_POST['modify_risks']) ? '1' : '0';
    $close_risks = isset($_POST['close_risks']) ? '1' : '0';
    $plan_mitigations = isset($_POST['plan_mitigations']) ? '1' : '0';
    $review_high = isset($_POST['review_high']) ? '1' : '0';
    $review_medium = isset($_POST['review_medium']) ? '1' : '0';
    $review_low = isset($_POST['review_low']) ? '1' : '0';
    $multi_factor = (int)$_POST['multi_factor'];

    // If the type is 1
    if ($type == "1") {
        $type = "simplerisk";
    } // If the type is 2
    else if ($type == "2") {
        $type = "ldap";
    } else $type = "INVALID";

    // Verify that the two passwords are the same
    if ("$pass" == "$repeat_pass") {
        // Verify that the user does not exist
        if (!user_exist($user)) {
            // Create a unique salt for the user
            $salt = generate_token(20);

            // Hash the salt
            $salt_hash = '$2a$15$' . md5($salt);

            // Generate the password hash
            $hash = generateHash($salt_hash, $pass);

            // Create a boolean for all
            $all = false;

            // Create a boolean for none
            $none = false;

            // Create the team value
            foreach ($teams as $value) {
                // If the selected value is all
                if ($value == "all") $all = true;

                // If the selected value is none
                if ($value == "none") $none = true;

                $team .= ":";
                $team .= $value;
                $team .= ":";
            }

            // If no value was submitted then default to none
            if ($value == "") $none = true;

            // If all was selected then assign all teams
            if ($all) $team = "all";

            // If none was selected then assign no teams
            if ($none) $team = "none";

            // Insert a new user
            add_user($type, $user, $email, $name, $salt, $hash, $team, $admin, $review_high, $review_medium, $review_low, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor);

            // Audit log
            $risk_id = 1000;
            $message = "A new user was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

            $alert = "good";
            $alert_message = "The new user was added successfully.";
        } // Otherwise, the user already exists
        else {
            $alert = "bad";
            $alert_message = "The username already exists.  Please try again with a different username.";
        }
    } // Otherewise, the two passwords are different
    else {
        $alert = "bad";
        $alert_message = "The password and repeat password entered were different.  Please try again.";
    }
}

// Check if a user was enabled
if (isset($_POST['enable_user'])) {
    $value = (int)$_POST['disabled_users'];

    // Verify value is an integer
    if (is_int($value)) {
        enable_user($value);

        // Audit log
        $risk_id = 1000;
        $message = "A user was enabled by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "The user was enabled successfully.";
    }
}

// Check if a user was disabled
if (isset($_POST['disable_user'])) {
    $value = (int)$_POST['enabled_users'];

    // Verify value is an integer
    if (is_int($value)) {
        disable_user($value);

        // Audit log
        $risk_id = 1000;
        $message = "A user was disabled by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "The user was disabled successfully.";
    }

}

// Check if a user was deleted
if (isset($_POST['delete_user'])) {
    $value = (int)$_POST['user'];

    // Verify value is an integer
    if (is_int($value)) {
        delete_value("user", $value);

        // Audit log
        $risk_id = 1000;
        $message = "An existing user was deleted by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "The existing user was deleted successfully.";
    }
}

// Check if a password reset was requeted
if (isset($_POST['password_reset'])) {
    $value = (int)$_POST['user'];

    // Verify value is an integer
    if (is_int($value)) {
        password_reset_by_userid($value);

        // Audit log
        $risk_id = 1000;
        $message = "A password reset request was submitted by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);


        // There is an alert message
        $alert = "good";
        $alert_message = "A password reset email was sent to the user.";
    }
}

$localvars = array();

if($alert == "good" || $alert == "bad") {

    $localvars['alert'] = true;
    $localvars['alert_type'] = $alert;
    $localvars['alert_message'] = $alert_message;
}

$localvars['html_adm_menu'] = view_configure_menu("UserManagement");

$localvars['AddANewUser'] = $lang['AddANewUser'];
$localvars['Type'] = $lang['Type'];

$localvars['cauth_extra'] = "";

// If the custom authentication extra is enabeld
if (custom_authentication_extra()) {
    // Display the LDAP option
    $localvars['cauth_extra'] = "<option value=\"2\">LDAP</option>\n";
}

$localvars['FullName'] = $lang['FullName'];
$localvars['EmailAddress'] = $lang['EmailAddress'];
$localvars['Username'] = $lang['Username'];
$localvars['Password'] = $lang['Password'];
$localvars['RepeatPassword'] = $lang['RepeatPassword'];
$localvars['Teams'] = $lang['Teams'];
$localvars['UserResponsibilities'] = $lang['UserResponsibilities'];
$localvars['AbleToSubmitNewRisks'] = $lang['AbleToSubmitNewRisks'];
$localvars['AbleToModifyExistingRisks'] = $lang['AbleToModifyExistingRisks'];
$localvars['AbleToCloseRisks'] = $lang['AbleToCloseRisks'];
$localvars['AbleToPlanMitigations'] = $lang['AbleToPlanMitigations'];
$localvars['AbleToReviewLowRisks'] = $lang['AbleToReviewLowRisks'];
$localvars['AbleToReviewMediumRisks'] = $lang['AbleToReviewMediumRisks'];
$localvars['AbleToReviewHighRisks'] = $lang['AbleToReviewHighRisks'];
$localvars['AllowAccessToConfigureMenu'] = $lang['AllowAccessToConfigureMenu'];
$localvars['MultiFactorAuthentication'] =$lang['MultiFactorAuthentication'];
$localvars['None'] = $lang['None'];
$localvars['html_mma'] = "";
$localvars['Add'] = $lang['Add'];
$localvars['ViewDetailsForUser'] = $lang['ViewDetailsForUser'];
$localvars['DetailsForUser'] = $lang['DetailsForUser'];
$localvars['Select'] = $lang['Select'];
$localvars['EnableAndDisableUsers'] = $lang['EnableAndDisableUsers'];
$localvars['EnableAndDisableUsersHelp'] = $lang['EnableAndDisableUsersHelp'];
$localvars['DisableUser'] = $lang['DisableUser'];
$localvars['Disable'] = $lang['Disable'];
$localvars['EnableUser'] = $lang['EnableUser'];
$localvars['DeleteAnExistingUser'] = $lang['DeleteAnExistingUser'];
$localvars['DeleteCurrentUser'] = $lang['DeleteCurrentUser'];
$localvars['Delete'] = $lang['Delete'];
$localvars['PasswordReset'] = $lang['PasswordReset'];
$localvars['SendPasswordResetEmailForUser'] = $lang['SendPasswordResetEmailForUser'];
$localvars['Send'] = $lang['Send'];


/*
// If the custom authentication extra is installed
if (custom_authentication_extra()) {
    // Include the custom authentication extra
    require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));

    // Display the multi factor authentication options
    $localvars['html_mma'] = multi_factor_authentication_options(1);
}*/

//$localvars[''] = $lang[''];


$localvars['dd_team'] = create_multiple_dropdown("team");
$localvars['dd_user'] = create_dropdown("user");
$localvars['dd_enabled_users'] = create_dropdown("enabled_users");
$localvars['dd_disabled_users'] = create_dropdown("disabled_users");


$template = $twig->loadTemplate('admin_user_management.html.twig');

$template->display(array_merge($base_twigvars, $localvars));