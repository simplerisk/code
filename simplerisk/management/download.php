<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));

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
        $file_type = isset($_REQUEST['file_type'])?$_REQUEST['file_type']:"file";

    // Get the file for the submitted file id
    download_file($id, $file_type);
    }

?>
