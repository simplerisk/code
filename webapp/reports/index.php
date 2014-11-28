<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */


// Include required functions file
require_once(realpath(__DIR__ . '/../includes/libs.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/reporting.php'));

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

$localvars = array();

if($alert == "good" || $alert == "bad") {

    $localvars['alert'] = true;
    $localvars['alert_type'] = $alert;
    $localvars['alert_message'] = $alert_message;
}

$localvars['html_reporting_menu'] = view_reporting_menu("RiskDashboard");

$localvars['OpenRisks'] = $lang['OpenRisks'];
$localvars['ClosedRisks'] = $lang['ClosedRisks'];


$localvars['html_OpenRisks'] = $escaper->escapeHtml(get_open_risks());
$localvars['html_ClosedRisks'] = $escaper->escapeHtml(get_closed_risks());

$localvars['html_RiskLevel'] = open_risk_level_pie($escaper->escapeHtml($lang['RiskLevel']));
$localvars['html_Status'] = open_risk_status_pie($escaper->escapeHtml($lang['Status']));
$localvars['html_SiteLocation'] = open_risk_location_pie($escaper->escapeHtml($lang['SiteLocation']));
$localvars['html_Category'] = open_risk_category_pie($escaper->escapeHtml($lang['Category']));
$localvars['html_Team'] = open_risk_team_pie($escaper->escapeHtml($lang['Team']));
$localvars['html_Technology'] = open_risk_technology_pie($escaper->escapeHtml($lang['Technology']));
$localvars['html_Owner'] = open_risk_owner_pie($escaper->escapeHtml($lang['Owner']));
$localvars['html_OwnersManager'] =  open_risk_owners_manager_pie($escaper->escapeHtml($lang['OwnersManager']));
$localvars['html_RiskScoringMethod'] = open_risk_scoring_method_pie($escaper->escapeHtml($lang['RiskScoringMethod']));
$localvars['html_Reason'] = closed_risk_reason_pie($escaper->escapeHtml($lang['Reason']));



$template = $twig->loadTemplate('reports_index.html.twig');

$template->display(array_merge($base_twigvars, $localvars));