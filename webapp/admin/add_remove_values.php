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

// Check if a new category was submitted
if (isset($_POST['add_category'])) {
    $name = $_POST['new_category'];

    // Insert a new category up to 50 chars
    add_name("category", $name, 50);

    // Audit log
    $risk_id = 1000;
    $message = "A new category was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new category was added successfully.";
}

// Check if a category was deleted
if (isset($_POST['delete_category'])) {
    $value = (int)$_POST['category'];

    // Verify value is an integer
    if (is_int($value)) {
        delete_value("category", $value);

        // Audit log
        $risk_id = 1000;
        $message = "An existing category was removed by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "An existing category was removed successfully.";
    }
}

// Check if a new team was submitted
if (isset($_POST['add_team'])) {
    $name = $_POST['new_team'];

    // Insert a new team up to 50 chars
    add_name("team", $name, 50);

    // Audit log
    $risk_id = 1000;
    $message = "A new team was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new team was added successfully.";
}

// Check if a team was deleted
if (isset($_POST['delete_team'])) {
    $value = (int)$_POST['team'];

    // Verify value is an integer
    if (is_int($value)) {
        delete_value("team", $value);

        // Audit log
        $risk_id = 1000;
        $message = "An existing team was removed by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "An existing team was removed successfully.";
    }
}

// Check if a new technology was submitted
if (isset($_POST['add_technology'])) {
    $name = $_POST['new_technology'];

    // Insert a new technology up to 50 chars
    add_name("technology", $name, 50);

    // Audit log
    $risk_id = 1000;
    $message = "A new technology was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new technology was added successfully.";
}

// Check if a technology was deleted
if (isset($_POST['delete_technology'])) {
    $value = (int)$_POST['technology'];

    // Verify value is an integer
    if (is_int($value)) {
        delete_value("technology", $value);

        // Audit log
        $risk_id = 1000;
        $message = "An existing technology was removed by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "An existing technology was removed successfully.";
    }
}

// Check if a new location was submitted
if (isset($_POST['add_location'])) {
    $name = $_POST['new_location'];

    // Insert a new location up to 100 chars
    add_name("location", $name, 100);

    // Audit log
    $risk_id = 1000;
    $message = "A new location was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new location was added successfully.";
}

// Check if a location was deleted
if (isset($_POST['delete_location'])) {
    $value = (int)$_POST['location'];

    // Verify value is an integer
    if (is_int($value)) {
        delete_value("location", $value);

        // Audit log
        $risk_id = 1000;
        $message = "An existing location was removed by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "An existing location was removed successfully.";
    }
}

// Check if a new control regulation was submitted
if (isset($_POST['add_regulation'])) {
    $name = $_POST['new_regulation'];

    // Insert a new regulation up to 50 chars
    add_name("regulation", $name, 50);

    // Audit log
    $risk_id = 1000;
    $message = "A new control regulation was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new control regulation was added successfully.";
}

// Check if a control regulation was deleted
if (isset($_POST['delete_regulation'])) {
    $value = (int)$_POST['regulation'];

    // Verify value is an integer
    if (is_int($value)) {
        delete_value("regulation", $value);

        // Audit log
        $risk_id = 1000;
        $message = "An existing control regulation was removed by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "An existing control regulation was removed successfully.";
    }
}

// Check if a new planning strategy was submitted
if (isset($_POST['add_planning_strategy'])) {
    $name = $_POST['new_planning_strategy'];

    // Insert a new planning strategy up to 20 chars
    add_name("planning_strategy", $name, 20);

    // Audit log
    $risk_id = 1000;
    $message = "A new planning strategy was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new planning strategy was added successfully.";
}

// Check if a planning strategy was deleted
if (isset($_POST['delete_planning_strategy'])) {
    $value = (int)$_POST['planning_strategy'];

    // Verify value is an integer
    if (is_int($value)) {
        delete_value("planning_strategy", $value);

        // Audit log
        $risk_id = 1000;
        $message = "An existing planning strategy was removed by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "An existing planning strategy was removed successfully.";
    }
}

// Check if a new close reason was submitted
if (isset($_POST['add_close_reason'])) {
    $name = $_POST['new_close_reason'];

    // Insert a new close reason up to 50 chars
    add_name("close_reason", $name, 50);

    // Audit log
    $risk_id = 1000;
    $message = "A new close reason was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new close reason was added successfully.";
}

// Check if a close reason was deleted
if (isset($_POST['delete_close_reason'])) {
    $value = (int)$_POST['close_reason'];

    // Verify value is an integer
    if (is_int($value)) {
        delete_value("close_reason", $value);

        // Audit log
        $risk_id = 1000;
        $message = "An existing close reason was removed by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

        // There is an alert message
        $alert = "good";
        $alert_message = "An existing close reason was removed successfully.";
    }
}

$localvars = array();

if($alert == "good" || $alert == "bad") {

    $localvars['alert'] = true;
    $localvars['alert_type'] = $alert;
    $localvars['alert_message'] = $alert_message;
}

$localvars['html_adm_menu'] = view_configure_menu("AddAndRemoveValues");


$localvars['Category'] = $lang['Category'];
$localvars['AddNewCategoryNamed'] = $lang['AddNewCategoryNamed'];
$localvars['Add'] = $lang['Add'];
$localvars['DeleteCurrentCategoryNamed'] = $lang['DeleteCurrentCategoryNamed'];
$localvars['Delete'] = $lang['Delete'];
$localvars['Team'] = $lang['Team'];
$localvars['AddNewTeamNamed'] = $lang['AddNewTeamNamed'];
$localvars['DeleteCurrentTeamNamed'] = $lang['DeleteCurrentTeamNamed'];
$localvars['Technology'] = $lang['Technology'];
$localvars['AddNewTechnologyNamed'] = $lang['AddNewTechnologyNamed'];
$localvars['DeleteCurrentTechnologyNamed'] = $lang['DeleteCurrentTechnologyNamed'];
$localvars['SiteLocation'] = $lang['SiteLocation'];
$localvars['AddNewSiteLocationNamed'] = $lang['AddNewSiteLocationNamed'];
$localvars['DeleteCurrentSiteLocationNamed'] = $lang['DeleteCurrentSiteLocationNamed'];
$localvars['ControlRegulation'] = $lang['ControlRegulation'];
$localvars['AddNewControlRegulationNamed'] = $lang['AddNewControlRegulationNamed'];
$localvars['DeleteCurrentControlRegulationNamed'] = $lang['DeleteCurrentControlRegulationNamed'];
$localvars['RiskPlanningStrategy'] = $lang['RiskPlanningStrategy'];
$localvars['AddNewRiskPlanningStrategyNamed'] = $lang['AddNewRiskPlanningStrategyNamed'];
$localvars['DeleteCurrentRiskPlanningStrategyNamed'] = $lang['DeleteCurrentRiskPlanningStrategyNamed'];
$localvars['CloseReason'] = $lang['CloseReason'];
$localvars['AddNewCloseReasonNamed'] = $lang['AddNewCloseReasonNamed'];
$localvars['DeleteCurrentCloseReasonNamed'] = $lang['DeleteCurrentCloseReasonNamed'];


$localvars['dd_category'] = create_dropdown("category");
$localvars['dd_team'] = create_dropdown("team");
$localvars['dd_technology'] = create_dropdown("technology");
$localvars['dd_location'] = create_dropdown("location");
$localvars['dd_regulation'] = create_dropdown("regulation");
$localvars['dd_planning_strategy'] = create_dropdown("planning_strategy");
$localvars['dd_close_reason'] = create_dropdown("close_reason");


$template = $twig->loadTemplate('admin_add_remove_values.html.twig');

$template->display(array_merge($base_twigvars, $localvars));