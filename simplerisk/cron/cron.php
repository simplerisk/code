<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

declare(strict_types=1);

use JobRunner\JobRunner\Job\CliJob;
use JobRunner\JobRunner\Job\JobList;
use JobRunner\JobRunner\CronJobRunner;

// Only run this script if called via the command line
if (php_sapi_name() == "cli")
{
    // Include required files
	require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/cron.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

	write_debug_log_cli("Beginning Cron Run");

	// Create a new instance of Job-Runner
	$jobList = new JobList();

	// Get the cron job information from all cron jobs
	$cron_jobs = get_cron_jobs();

	// For each cron job
	foreach ($cron_jobs as $job_name => $cron_job)
	{
		// Create the job
		$command = $cron_job['command'];
		$schedule = $cron_job['schedule'];
		$job = new CliJob($command, $schedule, $job_name);
		$jobList->push($job);
	}

	// Run all scheduled crons
	CronJobRunner::create()->run($jobList);

	// Update the setting with the last run time
	$cron_last_run = time();
	update_or_insert_setting('cron_last_run', $cron_last_run);
}

?>