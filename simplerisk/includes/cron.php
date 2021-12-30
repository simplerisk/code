<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include the required function file
require_once('functions.php');

// Array containing the crons to run
$cron_jobs = array(
	'cron_backup',
	'cron_vulnmgmt',
	'cron_notification',
);

/***************************
 * FUNCTION: GET CRON JOBS *
 ***************************/
function get_cron_jobs()
{
	// Get the cron jobs array
	global $cron_jobs;

	// Create an empty array of all cron job info
	$all_cron_job_info = array();

	// For each cron job
	foreach ($cron_jobs as $cron_job)
	{
		write_debug_log("Cron Job: {$cron_job}");

		// Get the script name for the cron job
		$script_name = $cron_job . '.php';

		// Get the full path to the cron script
		$cron_script = realpath(__DIR__ . '/../cron/' . $script_name);

		// Create the command to run the script via PHP
		$command = PHP_BINARY . ' -f ' . $cron_script;

		write_debug_log("Cron Command: {$command}");

		// If both a function and script exist for that cron job
		if (function_exists($cron_job) && is_file($cron_script))
		{
			// Call the script to get the schedule
			$schedule = call_user_func($cron_job);

			write_debug_log("Cron Schedule: {$schedule}");

			// Create an array with the cron job info
			$cron_job_info = array(
				$cron_job => array(
					'command' => $command,
					'schedule' => $schedule,
				)
			);

			// Merge the cron job info into the all cron job info array
			$all_cron_job_info = array_merge($all_cron_job_info, $cron_job_info);
		}
	}

	// Return the array of all of the cron job info
	return $all_cron_job_info;
}

/***************************
 * FUNCTION: CRON SCHEDULE *
 *****************************/
function cron_schedule($cron_schedule)
{
        // Set the schedule
        switch ($cron_schedule)
        {
			case "minutely":
				$schedule = '* * * * *';
				break;
			case "hourly":
				$schedule = '0 * * * *';
				break;
            case "daily":
				$schedule = '0 0 * * *';
				break;
            case "weekly":
				$schedule = '0 0 * * 0';
				break;
            case "monhtly":
				$schedule = '0 0 1 * *';
				break;
            default:
				$schedule = '0 0 * * *';
        }

        // Return the schedule
        return $schedule;
}


/*************************
 * FUNCTION: CRON BACKUP *
 *************************/
function cron_backup()
{
	// Get the backup schedule
	$schedule = cron_schedule(get_setting("backup_schedule"));

	// Return the schedule
	return $schedule;
}

/***************************
 * FUNCTION: CRON VULNMGMT *
 ***************************/
function cron_vulnmgmt()
{
	// Get the vulnerability management schedule
	$schedule = cron_schedule(get_setting("extra_vulnmgmt_cron_schedule"));

	// Return the schedule
	return $schedule;
}

/*******************************
 * FUNCTION: CRON NOTIFICATION *
 *******************************/
function cron_notification()
{
	// Get the notification schedule
	$schedule = cron_schedule("minutely");

	// Return the schedule
	return $schedule;
}

?>
