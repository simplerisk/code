<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
  * License, v. 2.0. If a copy of the MPL was not distributed with this
  * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/includes/libs.php'));

// If the login form was posted
if (isset($_POST['submit'])) {
    $user = $_POST['user'];
    $pass = $_POST['pass'];

    // If the user is valid
    if (is_valid_user($user, $pass)) {
        // If the custom authentication extra is installed
        if (custom_authentication_extra()) {
            // Include the custom authentication extra
            require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

            // Get the enabled authentication for the user
            $enabled_auth = enabled_auth($user);

            // If no multi factor authentication is enabled for the user
            if ($enabled_auth == 1) {
                // Grant the user access
                grant_access();

                // Redirect to the reports index
                header("Location: reports");
            } // If Duo authentication is enabled for the user
            else if ($enabled_auth == 2) {
                // Set session access to duo
                $_SESSION["access"] = "duo";
            } // If Toopher authentication is enabled for the user
            else if ($enabled_auth == 3) {
                // Set session access to toopher
                $_SESSION["access"] = "toopher";
            }
        } // Otherwise no second factor is necessary
        else {
            // Grant the user access
            grant_access();

            // Redirect to the reports index
            header("Location: reports");
        }
    } else $_SESSION["access"] = "denied";
}

// If the user has already authorized and we are authorizing with duo
if (isset($_SESSION["access"]) && ($_SESSION["access"] == "duo")) {
    // If a response has been posted
    if (isset($_POST['sig_response'])) {
        // Include the custom authentication extra
        require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

        // Get the response back from Duo
        $resp = Duo::verifyResponse(IKEY, SKEY, get_duo_akey(), $_POST['sig_response']);

        // If the response is not null
        if ($resp != NULL) {
            // Grant the user access
            grant_access();

            // Redirect to the reports index
            header("Location: reports");
        }
    }
}

// If the user has authenticated and now we need to authenticate with duo
if (isset($_SESSION["access"]) && $_SESSION["access"] == "duo") {
    // Include the custom authentication extra
    require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

    // Perform a duo authentication request for the user
    $base_twigvars['duo_value'] = duo_authentication($_SESSION["user"]);

    $base_twigvars['session_access_duo'] = true;

}

$template = $twig->loadTemplate('index.html.twig');


$template->display($base_twigvars);