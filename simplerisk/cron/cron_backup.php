<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Only run this script if called via the command line
if (php_sapi_name() == "cli")
{
    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/bootstrap.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
    // do_backup() / generate_database_backup() live in the backup domain module
    require_once(realpath(__DIR__ . '/../includes/backups.php'));

	// If we should do automatic backups
	if (get_setting('backup_auto') == "true") {

	    $message = "Automatic backup requested.";
	    write_debug_log($message, "notice");
	    write_log(0, 0, $message, 'backup');

	    do_backup();
	}
}

?>
