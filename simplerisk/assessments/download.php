<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/assessments.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

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
    $parameters = [
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => isset($_SERVER["HTTPS"]),
        "httponly" => true,
        "samesite" => "Strict",
    ];
    session_set_cookie_params($parameters);

    session_name('SimpleRisk');
    session_start();
}

// Check if assessment extra is enabled
if(assessments_extra())
{
    // Include the assessments extra
    require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
}
else
{
    header("Location: ../index.php");
    exit(0);
}

    // Check if a file id was sent
    if (isset($_GET['id']) || isset($_POST['id']))
    {
        if (isset($_GET['id']))
        {
            // Set the id to the get parameter
            $id = $_GET['id'];
        }
        else if (isset($_POST['id']))
        {
            // Set the id to the post parameter
            $id = $_POST['id'];
        }

        // Get the file for the submitted file id
        download_questionnaire_file($id);
    }

?>
