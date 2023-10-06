<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));

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
        $status_message = "FORBIDDEN: The user does not have admin privileges.";

        // Return the result
        api_v2_json_result($status_code, $status_message, $data);

        // Do not process anything else
        exit;
    }
}

/*************************************
 * FUNCTION: API V2 CHECK PERMISSION *
 *************************************/
function api_v2_check_permission($permission)
{
    // If the user calling this is not an admin
    if (!check_permission($permission))
    {
        // The user is unauthorized
        $data = null;
        $status_code = 403;
        $status_message = "FORBIDDEN: The user does not have the required permission to perform this action.";

        // Return the result
        api_v2_json_result($status_code, $status_message, $data);

        // Do not process anything else
        exit;
    }
}

/*************************************
 * FUNCTION: API V2 IS AUTHENTICATED *
 *************************************/
function api_v2_is_authenticated()
{
    // If the API Extra is activated
    if (api_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../../extras/api/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);
        }
    }

    // If the API Extra is enabled and an X-API-KEY header is set
    if (api_extra() && authenticate_key() !== false)
    {
        // Return true
        return true;
    }
    // If we are not authenticated with a key but have an authenticated session
    else if (is_session_authenticated())
    {
        // Return true
        return true;
    }
    else if(check_questionnaire_get_token()) {
        return false;
    }
    // Access was not authenticated
    else
    {
        unauthenticated_access();
    }
}

?>