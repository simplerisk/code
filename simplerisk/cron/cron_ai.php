<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Only run this script if called via the command line
if (php_sapi_name() == "cli")
{
    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/config.php'));
    require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

    // If we have an Anthropic API key
    if (get_setting("anthropic_api_key") != false)
    {
        // Get the last time we saved the AI context
        $last_saved = get_setting("ai_context_last_saved");
        write_debug_log_cli("Last Saved: " . date("Y-m-d H:i:s", $last_saved));

        // Get the last time we updated the AI data
        $last_updated = get_setting("ai_context_last_updated");
        write_debug_log_cli("Last Updated: " . date("Y-m-d H:i:s", $last_updated));

        // If we have saved the AI context since the last time we updated or we have never updated
        if ($last_updated < $last_saved || !$last_updated)
        {
            $message = "Updating AI data via cron.";
            write_debug_log_cli($message);
            write_log(0, 0, $message, 'artificial_intelligence');

            // Generate the anthropic message context
            $context_content = generate_anthropic_message_context();

            // Ask Anthropic for recommendations
            $advice = ask_anthropic_for_recommendations($context_content);

            // Set a new last updated timestamp
            update_setting("ai_context_last_updated", time());
        }
        // We do not need to update at this time
        else
        {
            write_debug_log_cli("No need to obtain updates from Anthropic at this time.");
        }
    }
}

?>
