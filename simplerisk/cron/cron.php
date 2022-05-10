<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Only run this script if called via the command line
if (php_sapi_name() == "cli")
{
    // Include required files
	require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/cron.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

	write_debug_log("Beginning Cron Run");

	// Create a new instance of Jobby
	$jobby = new Jobby\Jobby();

	// Get the cron job information from all cron jobs
	$cron_jobs = get_cron_jobs();

	// For each cron job
	foreach ($cron_jobs as $job_name => $cron_job)
	{
		// Create the job
		$jobby->add($job_name, $cron_job);
	}

	// Run all scheduled crons
	$jobby->run();

	// Update the setting with the last run time
	$cron_last_run = time();
	update_or_insert_setting('cron_last_run', $cron_last_run);
}

?>
