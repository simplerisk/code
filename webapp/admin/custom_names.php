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

// Check if the impact update was submitted
if (isset($_POST['update_impact'])) {
    $new_name = $_POST['new_name'];
    $value = (int)$_POST['impact'];

    // Verify value is an integer
    if (is_int($value)) {
        update_table("impact", $new_name, $value);

        // Audit log
        $risk_id = 1000;
        $message = "The impact naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "The impact naming convention was updated successfully.";
    }
}

// Check if the likelihood update was submitted
if (isset($_POST['update_likelihood'])) {
    $new_name = $_POST['new_name'];
    $value = (int)$_POST['likelihood'];

    // Verify value is an integer
    if (is_int($value)) {
        update_table("likelihood", $new_name, $value);

        // Audit log
        $risk_id = 1000;
        $message = "The likelihood naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "The likelihood naming convention was updated successfully.";
    }
}

// Check if the mitigation effort update was submitted
if (isset($_POST['update_mitigation_effort'])) {
    $new_name = $_POST['new_name'];
    $value = (int)$_POST['mitigation_effort'];

    // Verify value is an integer
    if (is_int($value)) {
        update_table("mitigation_effort", $new_name, $value);

        // Audit log
        $risk_id = 1000;
        $message = "The mitigation effort naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "The mitigation effort naming convention was updated successfully.";
    }
}

$localvars = array();

if($alert == "good" || $alert == "bad") {

    $localvars['alert'] = true;
    $localvars['alert_type'] = $alert;
    $localvars['alert_message'] = $alert_message;
}

$localvars['html_adm_menu'] = view_configure_menu("RedefineNamingConventions");

$localvars['Impact'] = $lang['Impact'];
$localvars['Change'] = $lang['Change'];
$localvars['to'] = $lang['to'];
$localvars['Update'] = $lang['Update'];
$localvars['Likelihood'] = $lang['Likelihood'];
$localvars['MitigationEffort'] = $lang['MitigationEffort'];


$localvars['dd_impact'] = create_dropdown("impact");
$localvars['dd_likelihood'] = create_dropdown("likelihood");
$localvars['dd_mitigation_effort'] = create_dropdown("mitigation_effort");


$template = $twig->loadTemplate('admin_custom_names.html.twig');

$template->display(array_merge($base_twigvars, $localvars));