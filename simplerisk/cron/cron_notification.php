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
	require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

	// If the notification extra is enabled
	if (notification_extra())
	{
	    $message = "Looking for email notifications to send.";
	    write_debug_log($message);
	    //write_log(0, 0, $message, 'notification');

	    // Load the Email Notification Extra
	    require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

		// Run the email notification crons
		run_notification_crons();
	}
}

?>
