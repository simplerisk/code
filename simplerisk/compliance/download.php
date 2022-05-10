<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../includes/governance.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_compliance" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

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
        download_compliance_file($id);
    }

?>
