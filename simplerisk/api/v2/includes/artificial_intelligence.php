<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/artificial_intelligence.php'));

require_once(language_file());

/***************************************
 * FUNCTION: API V2 AI RECOMMENDATIONS *
 * *************************************/
function api_v2_ai_recommendations()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // If we have an Anthropic API key
    if (get_setting("anthropic_api_key") != false)
    {
        // Generate the anthropic message context
        $context_content = generate_anthropic_message_context();

        // Ask Anthropic for recommendations
        $advice = ask_anthropic_for_recommendations($context_content);

        // If we received advice
        if ($advice != false)
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";
            $data[] = $advice;
        }
        // If we did not receive advice
        else
        {
            // Set the status
            $status_code = 503;
            $status_message = "There was an issue retrieving a result from Anthropic.  Check the debug logs for more information.";
            $data = null;
        }
    }
    // If we do not have an Anthropic key
    else
    {
        // Set the status
        $status_code = 503;
        $status_message = "An Anthropic API key needs to be set to use this functionality.";
        $data = null;
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

?>