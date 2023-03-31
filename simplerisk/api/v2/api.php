<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));

/********************************
 * FUNCTION: API V2 JSON RESULT *
 ********************************/
function api_v2_json_result($status_code, $status_message, $data)
{
    return json_response($status_code, $status_message, $data);
}

/********************************
 * FUNCTION: API V2 CHECK ADMIN *
 ********************************/
function api_v2_check_admin()
{
    // If the user calling this is not an admin
    if (!is_admin())
    {
        // The user is unauthorized
        $data = null;
        $status_code = 403;
        $status_message = "FORBIDDEN: The user does not having admin privileges.";

        // Return the result
        api_v2_json_result($status_code, $status_message, $data);

        // Do not process anything else
        exit;
    }
}

?>