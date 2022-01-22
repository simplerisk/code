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

	// If the vulnerability management extra is enabled
	if (vulnmgmt_extra())
	{
	    $message = "Vulnerability management update requested.";
	    write_debug_log($message);
	    write_log(0, 0, $message, 'vulnmgmt');

	    // Load the vulnerability management extra
	    require_once(realpath(__DIR__ . '/../extras/vulnmgmt/index.php'));
		require_once(realpath(__DIR__ . '/../extras/vulnmgmt/includes/api.php'));

	    // Call the vulnerability management update function
		api_vulnmgmt_update();
	}
}

?>
