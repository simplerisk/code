<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/libs.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));


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

// If mitigated was passed back to the page as a GET parameter
if (isset($_GET['mitigated'])) {
    // If its true
    if ($_GET['mitigated'] == true) {
        $alert = "good";
        $alert_message = "Mitigation submitted successfully!";
    }
}

$localvars = array();

if($alert == "good" || $alert == "bad") {

    $localvars['alert'] = true;
    $localvars['alert_type'] = $alert;
    $localvars['alert_message'] = $alert_message;
}

$localvars['active_menu'] = "PlanYourMitigations";
$localvars['tb_risk'] = get_risk_table(1);

$template = $twig->loadTemplate('management_plan_mitigations.html.twig');

$template->display(array_merge($base_twigvars, $localvars));