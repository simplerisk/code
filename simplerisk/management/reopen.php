<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_riskmanagement" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

    // Check if a risk ID was sent
    if (isset($_GET['id']) || isset($_POST['id']))
    {
        if (isset($_GET['id']))
        {
            // Test that the ID is a numeric value
            $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);
        }
        else if (isset($_POST['id']))
        {
            // Test that the ID is a numeric value
            $id = (is_numeric($_POST['id']) ? (int)$_POST['id'] : 0);
        }

        // If team separation is enabled
        if (team_separation_extra())
        {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // If the user should not have access to the risk
            if (!extra_grant_access($_SESSION['uid'], $id))
            {
                // Redirect back to the page the workflow started on
                header("Location: " . $_SESSION["workflow_start"]);
                exit(0);
            }
        }

        // Reopen the risk
        reopen_risk($id);

        // Display an alert
        set_alert(true, "good", "Your risk has now been reopened.");

        // Check that the id is a numeric value
        if (is_numeric($id))
        {
            // Create the redirection location
            $url = "view.php?id=" . $id;

            // Redirect to view risk page
            header("Location: " . $url);
        }
    }
    else header('Location: reports/closed.php');
?>
