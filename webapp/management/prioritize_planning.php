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

// If the risks were saved to projects
if (isset($_POST['update_projects'])) {
    foreach ($_POST['ids'] as $risk_id) {
        $project_id = $_POST['risk_' . $risk_id];
        update_risk_project($project_id, $risk_id);
    }

    // There is an alert message
    $alert = "good";
    $alert_message = "The risks were saved successfully to the projects.";
}


// If the order was updated
if (isset($_POST['update_order'])) {
    foreach ($_POST['ids'] as $id) {
        $order = $_POST['order_' . $id];
        update_project_order($order, $id);
    }

    // There is an alert message
    $alert = "good";
    $alert_message = "The project order was updated successfully.";
}

// If the projects were saved to status
if (isset($_POST['update_project_status'])) {
    foreach ($_POST['projects'] as $project_id) {
        $status_id = $_POST['project_' . $project_id];
        update_project_status($status_id, $project_id);
    }

    // There is an alert message
    $alert = "good";
    $alert_message = "The project statuses were successfully updated.";
}

// Check if a new project was submitted
if (isset($_POST['add_project'])) {
    $name = $_POST['new_project'];

    // Insert a new project up to 100 chars
    add_name("projects", $name, 100);

    // Audit log
    $risk_id = 1000;
    $message = "A new project was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // There is an alert message
    $alert = "good";
    $alert_message = "A new project was added successfully.";
}

// Check if a project was deleted
if (isset($_POST['delete_project'])) {
    $value = (int)$_POST['projects'];

    // Verify value is an integer
    if (is_int($value)) {
        // If the project ID is 0 (ie. Unassigned Risks)
        if ($value == 0) {
            // There is an alert message
            $alert = "bad";
            $alert_message = "You cannot delete the Unassigned Risks project or we will have no place to put unassigned risks.  Sorry.";
        } // If the project has risks associated with it
        else if (project_has_risks($value)) {
            // There is an alert message
            $alert = "bad";
            $alert_message = "You cannot delete a project that has risks assigned to it.  Drag the risks back to the Unassigned Risks tab, save it, and try again.";
        } else {
            delete_value("projects", $value);

            // Audit log
            $risk_id = 1000;
            $message = "An existing project was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

            // There is an alert message
            $alert = "good";
            $alert_message = "An existing project was deleted successfully.";
        }
    } // We should never get here as we bound the variable as an int
    else {
        // There is an alert message
        $alert = "bad";
        $alert_message = "The project ID was not a valid value.  Please try again.";
    }
}

$localvars['active_menu'] = "PrioritizeForProjectPlanning";

// Get the projects
$projects = get_projects();

// Get the total number of projects
$count = count($projects);

// Initialize the counter
$counter = 1;

// For each project created
foreach ($projects as $project) {
    // Get the project ID
    $id = (int)$project['value'];

    $localvars['inline_css'] .= "#sortable-" . $escaper->escapeHtml($id) . " li";

    // If it's not the last one
    if ($counter != $count) {
        echo ", ";
        $counter++;
    }
}

$localvars['inline_css'] .= ", #statussortable-1 li, #statussortable-2 li, #statussortable-3 li, #statussortable-4 li";
$localvars['inline_css'] .= " { margin: 0 5px 5px 5px; padding: 5px; font-size: 0.75em; width: 120px; }\n";




$localvars['inline_js'] .= "$( \"";

                    // Initialize the counter
                    $counter = 1;

                // For each project created
                foreach ($projects as $project) {
                    // Get the project ID
                    $id = (int)$project['value'];

                    $localvars['inline_js'] .= "#sortable-" . $id;

                    // If it's not the last one
                    if ($counter != $count) {
                        echo ", ";
                        $counter++;
                    }
                }

$localvars['inline_js'] .= ", #statussortable-1, #statussortable-2, #statussortable-3, #statussortable-4";
$localvars['inline_js'] .= "\" ).sortable().disableSelection();\n";


$localvars['AddAndRemoveProjects'] = $lang['AddAndRemoveProjects'];
$localvars['AddAndRemoveProjectsHelp'] = $lang['AddAndRemoveProjectsHelp'];
$localvars['Add'] = $lang['Add'];
$localvars['AddNewProjectNamed'] = $lang['AddNewProjectNamed'];
$localvars['DeleteCurrentProjectNamed'] = $lang['DeleteCurrentProjectNamed'];

$localvars['dd_projects'] =create_dropdown("projects");

$localvars['Delete'] = $lang['Delete'];


$localvars['AddUnassignedRisksToProjects'] = $lang['AddUnassignedRisksToProjects'];
$localvars['AddUnassignedRisksToProjectsHelp'] = $lang['AddUnassignedRisksToProjectsHelp'];
$localvars['PrioritizeProjects'] = $lang['PrioritizeProjects'];
$localvars['PrioritizeProjectsHelp'] = $lang['PrioritizeProjectsHelp'];
$localvars['DetermineProjectStatus'] = $lang['DetermineProjectStatus'];
$localvars['ProjectStatusHelp'] = $lang['ProjectStatusHelp'];

$localvars['rw_project_tabs'] = get_project_tabs();
$localvars['rw_project_list'] = get_project_list();
$localvars['rw_project_status'] = get_project_status();

$template = $twig->loadTemplate('management_prioritize_planning.html.twig');

$template->display(array_merge($base_twigvars, $localvars));