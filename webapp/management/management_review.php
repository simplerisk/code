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

// Default is no alert
$alert = false;

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted") {
    header("Location: ../index.php");
    exit(0);
}

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];

// If reviewed is passed via GET
if (isset($_GET['reviewed'])) {
    // If it's true
    if ($_GET['reviewed'] == true) {
        $alert = "good";
        $alert_message = "Management review submitted successfully!";
    }
}


$localvars['active_menu'] = "PerformManagementReviews";
$localvars['tb_risk'] = get_risk_table(2);

$template = $twig->loadTemplate('management_review.html.twig');

$template->display(array_merge($base_twigvars, $localvars));



