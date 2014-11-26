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

// Check if the risk level update was submitted
if (isset($_POST['update_risk_levels'])) {
    $high = (float)$_POST['high'];
    $medium = (float)$_POST['medium'];
    $low = (float)$_POST['low'];
    $risk_model = (int)$_POST['risk_models'];

    // Check if all values are integers
    if (is_float($high) && is_float($medium) && is_float($low) && is_int($risk_model)) {
        // Check if low < medium < high
        if (($low < $medium) && ($medium < $high)) {
            // Update the risk level
            update_risk_levels($high, $medium, $low);

            // Audit log
            $risk_id = 1000;
            $message = "Risk level scoring was modified by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

            // TODO: This message will never be seen because of the alert condition for the risk model.
            $alert = "good";
            $alert_message = "The configuration was updated successfully.";
        } // Otherwise, there was a problem
        else {
            // There is an alert message
            $alert = "bad";
            $alert_message = "Your LOW risk needs to be less than your MEDIUM risk which needs to be less than your HIGH risk.";
        }

        // Risk model should be between 1 and 5
        if ((1 <= $risk_model) && ($risk_model <= 5)) {
            // Update the risk model
            update_risk_model($risk_model);

            // Audit log
            $risk_id = 1000;
            $message = "The risk formula was modified by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

            // There is an alert message
            $alert = "good";
            $alert_message = "The configuration was updated successfully.";
        } // Otherwise, there was a problem
        else {
            $alert = "good";
            $alert_message = "The risk formula submitted was an invalid value.";
        }
    }
}


$localvars = array();

if($alert == "good" || $alert == "bad") {

    $localvars['alert'] = true;
    $localvars['alert_type'] = $alert;
    $localvars['alert_message'] = $alert_message;
}

$localvars['html_adm_menu'] = view_configure_menu("ConfigureRiskFormula");

$localvars['MyClassicRiskFormulaIs'] = $lang['MyClassicRiskFormulaIs'];
$localvars['RISK'] = $lang['RISK'];
$localvars['dd_risk_models'] = create_dropdown("risk_models", get_setting("risk_model"));

$risk_levels = get_risk_levels();

$localvars['IConsiderHighRiskToBeAnythingGreaterThan'] = $lang['IConsiderHighRiskToBeAnythingGreaterThan'];
$localvars['IConsiderMediumRiskToBeLessThanAboveButGreaterThan'] = $lang['IConsiderMediumRiskToBeLessThanAboveButGreaterThan'];
$localvars['IConsiderlowRiskToBeLessThanAboveButGreaterThan'] = $lang['IConsiderlowRiskToBeLessThanAboveButGreaterThan'];
$localvars['Update'] = $lang['Update'];
$localvars['AllRiskScoresAreAdjusted'] = $lang['AllRiskScoresAreAdjusted'];

$localvars['rs_lv_2'] = $risk_levels[2]['value'];
$localvars['rs_lv_1'] = $risk_levels[1]['value'];
$localvars['rs_lv_0'] = $risk_levels[0]['value'];

$localvars['tb_risk'] = create_risk_table();

$template = $twig->loadTemplate('admin_index.html.twig');

$template->display(array_merge($base_twigvars, $localvars));


