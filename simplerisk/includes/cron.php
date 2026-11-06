<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include the required function file
require_once('functions.php');
// queue_config(): QUEUE_WORKER_COUNT / PROMISE_WORKER_COUNT for the worker entries.
require_once(realpath(__DIR__ . '/queue_config.php'));

// Array containing the crons to run
$cron_jobs = array(
    'cron_queue_loader',
    'cron_queue_worker',
    'cron_promise_worker',
	'cron_backup',
	'cron_vulnmgmt',
	'cron_notification',
	'cron_assessments',
    'cron_tfidf_recalculation',
    'cron_temporary_cleanup',
);

/***************************
 * FUNCTION: GET CRON JOBS *
 ***************************/
function get_cron_jobs()
{
	// Get the cron jobs array
	global $cron_jobs;

	// Create an empty array of all cron job info
	$all_cron_job_info = [];

	// For each cron job
	foreach ($cron_jobs as $cron_job)
	{
		write_debug_log("Cron Job: {$cron_job}", "info");

		// Get the script name for the cron job
		$script_name = $cron_job . '.php';

		// Get the full path to the cron script
		$cron_script = realpath(__DIR__ . '/../cron/' . $script_name);

		// Create the command to run the script via PHP
		$command = PHP_BINARY . ' -f ' . $cron_script;

		write_debug_log("Cron Command: {$command}", "debug");

		// If both a function and script exist for that cron job
		if (function_exists($cron_job) && is_file($cron_script))
		{
			// Call the script to get the schedule
			$schedule = call_user_func($cron_job);

			write_debug_log("Cron Schedule: {$schedule}", "info");

			// Create an array with the cron job info
			$cron_job_info = [
				$cron_job => [
					'command' => $command,
					'schedule' => $schedule,
				],
			];

			// Merge the cron job info into the all cron job info array
			$all_cron_job_info = array_merge($all_cron_job_info, $cron_job_info);
		}
	}

	// One orchestrator entry per configured worker (see cron_worker_slot_jobs()).
	$all_cron_job_info = cron_worker_slot_jobs($all_cron_job_info, [
		'cron_queue_worker'   => queue_config('QUEUE_WORKER_COUNT'),
		'cron_promise_worker' => queue_config('PROMISE_WORKER_COUNT'),
	]);

	// Return the array of all of the cron job info
	return $all_cron_job_info;
}

/**
 * Adds an orchestrator entry for each extra worker of the worker scripts in
 * $counts: with QUEUE_WORKER_COUNT = 3, 'cron_queue_worker' is joined by
 * 'cron_queue_worker_2' and 'cron_queue_worker_3', each running the SAME
 * command on the same schedule.
 *
 * Why: cron.php runs its jobs through job-runner, which holds a file lock per
 * job NAME until that job's process exits. A worker script runs for up to an
 * hour, so under one name a second copy could never start while the first
 * runs. With one name per configured worker, each minute's cron.php run starts
 * the copies whose previous run has ended, and each copy takes a free worker
 * slot itself (acquire_worker_slot()) or exits at once. Nothing here decides
 * which slot a process gets. With a count of 1 the list is unchanged.
 *
 * Pure.
 *
 * @param array<string, array{command:string, schedule:string}> $jobs
 * @param array<string, int> $counts worker script => configured count
 * @return array<string, array{command:string, schedule:string}>
 */
function cron_worker_slot_jobs(array $jobs, array $counts): array
{
	foreach ($counts as $name => $count) {
		if (!isset($jobs[$name])) {
			continue;
		}
		for ($slot = 2; $slot <= (int)$count; $slot++) {
			$jobs["{$name}_{$slot}"] = $jobs[$name];
		}
	}

	return $jobs;
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
            case "monthly":
				$schedule = '0 0 1 * *';
				break;
            case "every_five_minutes":
                $schedule = '*/5 * * * *';
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
/******************************
 * FUNCTION: CRON ASSESSMENTS *
 ******************************/
function cron_assessments()
{
	// Get the assessments schedule
	$schedule = cron_schedule("daily");

	// Return the schedule
	return $schedule;
}

/*******************************
 * FUNCTION: CRON QUEUE WORKER *
 *******************************/
function cron_queue_loader()
{
    // Get the queue worker schedule
    $schedule = cron_schedule("minutely");

    // Return the schedule
    return $schedule;
}

/*******************************
 * FUNCTION: CRON QUEUE WORKER *
 *******************************/
function cron_queue_worker()
{
    // Get the queue worker schedule
    $schedule = cron_schedule("minutely");

    // Return the schedule
    return $schedule;
}

/*********************************
 * FUNCTION: CRON PROMISE WORKER *
 *********************************/
function cron_promise_worker()
{
    // Get the promise schedule
    $schedule = cron_schedule("minutely");

    // Return the schedule
    return $schedule;
}

/************************************
 * FUNCTION: CRON TEMPORARY CLEANUP *
 ************************************/
function cron_temporary_cleanup()
{
    // Get the promise schedule
    $schedule = cron_schedule("hourly");

    // Return the schedule
    return $schedule;
}

/**************************************
 * FUNCTION: CRON TFIDF RECALCULATION *
 **************************************/
function cron_tfidf_recalculation()
{
    // Get the promise schedule
    $schedule = cron_schedule("weekly");

    // Return the schedule
    return $schedule;
}

?>