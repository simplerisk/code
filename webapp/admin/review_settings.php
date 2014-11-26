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
if (isset($_POST['update_review_settings'])) {
    $high = (int)$_POST['high'];
    $medium = (int)$_POST['medium'];
    $low = (int)$_POST['low'];

    // Check if all values are integers
    if (is_int($high) && is_int($medium) && is_int($low)) {
        // Update the review settings
        update_review_settings($high, $medium, $low);

        // Audit log
        $risk_id = 1000;
        $message = "The review settings were modified by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        $alert = "good";
        $alert_message = "The review settings have been updated successfully!";
    } // NOTE: This will never trigger as we bind $high, $medium, and $low to integer values
    else {
        $alert = "bad";
        $alert_message = "One of your review settings is not an integer value.  Please try again.";
    }
}


$localvars = array();

if($alert == "good" || $alert == "bad") {

    $localvars['alert'] = true;
    $localvars['alert_type'] = $alert;
    $localvars['alert_message'] = $alert_message;
}

$localvars['html_adm_menu'] = view_configure_menu("ConfigureReviewSettings");

$localvars['IWantToReviewHighRiskEvery'] = $lang['IWantToReviewHighRiskEvery'];
$localvars['days'] = $lang['days'];
$localvars['IWantToReviewMediumRiskEvery'] = $lang['IWantToReviewMediumRiskEvery'];
$localvars['IWantToReviewLowRiskEvery'] = $lang['IWantToReviewLowRiskEvery'];
$localvars['Update'] = $lang['Update'];



$review_levels = get_review_levels();

$localvars['rv_lv_0'] = $review_levels[0]['value'];
$localvars['rv_lv_1'] = $review_levels[1]['value'];
$localvars['rv_lv_2'] = $review_levels[2]['value'];

$template = $twig->loadTemplate('admin_review_settings.html.twig');

$template->display(array_merge($base_twigvars, $localvars));
