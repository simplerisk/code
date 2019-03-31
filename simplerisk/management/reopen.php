<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    require_once(language_file());

    require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
    set_unauthenticated_redirect();
            header("Location: ../index.php");
            exit(0);
    }

    // Enforce that the user has access to risk management
    enforce_permission_riskmanagement();

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
