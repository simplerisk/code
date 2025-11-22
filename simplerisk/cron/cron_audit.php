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
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

	$message = "Automatic initiate test requested.";
    write_debug_log($message, "info");
    
    // Run the auto initiate test cron
    run_auto_initiate_test_cron();
}

?>
