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

	// If we should do automatic backups
	if (get_setting('backup_auto') == "true") {

	    $message = "Automatic backup requested.";
	    write_debug_log($message);
	    write_log(0, 0, $message, 'backup');

	    do_backup();
	}
}

// To do the actual backup
// It's a separate function to be able to call from the UI
// the $force parameter is to tell the function that it is called from the UI to force an immediate backup
// (in this case the set_alert() function that can be used since there IS a session)
function do_backup($force=false) {
    // Create a timestamp for the current date and time
    $timestamp = date("Y-m-d--H-i-s");
    
    // Get the backup directory path
    $backup_path = get_setting('backup_path');
    
    // Set the error to false
    $error = false;
    
    // If the backup directory does not exist
    if (!is_dir($backup_path))
    {
        // If we could not create the backup directory
        if (!mkdir($backup_path))
        {
            // We have a problem
            $error = true;
            
            // Write a message to the error log
            $message = "Unable to create a backup directory under " . $backup_path . ".";
            write_debug_log($message);

            // If the backup was requested from the UI
            if ($force) {
                set_alert(true, "bad", $message);
            }
        }
    }
    
    // Get the path to the timestamp directory for this backup
    $timestamp_path = $backup_path . '/' . $timestamp;
    
    // If the timestamp directory does not exist
    if (!$error && !is_dir($timestamp_path))
    {
        // If we could not create the timestamp directory
        if (!mkdir($timestamp_path))
        {
            // We have a problem
            $error = true;
            
            // Write a message to the error log
            $message = "Unable to create a backup directory under " . $timestamp_path . ".";
            write_debug_log($message);

            // If the backup was requested from the UI
            if ($force) {
                set_alert(true, "bad", $message);
            }
        }
    }
    
    if ($error) {
        $message = "Backup failed.";
        write_debug_log($message);
        write_log(0, 0, $message, 'backup');

        // If the backup was requested from the UI
        if ($force) {
            set_alert(true, "bad", $message);
        }

        return;
    }

    // If we haven't hit an error at this point get and check for mysqldump
    if(!is_process('mysqldump'))
    {
        $mysqldump_path = get_setting('mysqldump_path');
    }
    else $mysqldump_path = "mysqldump";
    
    // Get the path to the database backup file
    $db_backup_file = $timestamp_path . '/simplerisk-db-backup-' . $timestamp . '.sql';
    
    // Sanitize the mysqldump command
    $db_backup_cmd = escapeshellcmd($mysqldump_path) . ' --opt --lock-tables=false --skip-add-locks -h ' . escapeshellarg(DB_HOSTNAME) . ' -u ' . escapeshellarg(DB_USERNAME) . ' -p' . escapeshellarg(DB_PASSWORD) . ' ' . escapeshellarg(DB_DATABASE) . ' > ' . escapeshellarg($db_backup_file);
    
    // Backup the database
    $mysqldump = system($db_backup_cmd);
    
    // Compress the database backup
    $db_zip_file_name = $timestamp_path . '/simplerisk-db-backup-' . $timestamp . '.zip';
    create_zip_file($db_backup_file, $db_zip_file_name);
    
    // Remove the uncompressed database backup
    unlink($db_backup_file);
    
    // Create a zip file containing the current SimpleRisk files
    $simplerisk_directory =  realpath(__DIR__) . '/../';
    $app_zip_file_name = $timestamp_path . '/simplerisk-app-backup-' . $timestamp . '.zip';
    create_zip_file($simplerisk_directory, $app_zip_file_name);
    
    // Get the number of days to keep backups for
    $backup_remove = (int)get_setting('backup_remove');
    
    // Create a random id for the backup
    $random_id = generate_token(50);
    
    // Open the database connection
    $db = db_open();
    
    // Insert the backup information into the database
    $stmt = $db->prepare("INSERT INTO `backups` (`random_id`, `app_zip_file_name`, `db_zip_file_name`) VALUES (:random_id, :app_zip_file_name, :db_zip_file_name);");
    $stmt->bindParam(":random_id", $random_id, PDO::PARAM_STR, 50);
    $stmt->bindParam(":app_zip_file_name", $app_zip_file_name, PDO::PARAM_STR);
    $stmt->bindParam(":db_zip_file_name", $db_zip_file_name, PDO::PARAM_STR);
    $stmt->execute();
    
    // Delete backup information from the database that is older than the backup_remove days ago
    $stmt = $db->prepare("DELETE FROM `backups` WHERE timestamp < (NOW() - INTERVAL :backup_remove DAY);");
    $stmt->bindParam(":backup_remove", $backup_remove, PDO::PARAM_INT);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
    
    $directories = glob($backup_path."/*");
    $now = time();
    
    // Remove all directories older than the backup remove days
    foreach ($directories as $directory)
    {
        // If it is a directory
        if (is_dir($directory))
        {
            // If the directory was created more than the backup_remove days ago
            // 60 seconds x 60 minutes x 24 hours x # of days
            if ($now - filemtime($directory) >= 60 * 60 * 24 * $backup_remove)
            {
                // Remove all files from this directory
                array_map('unlink', glob("$directory/*.*"));
                
                // Remove the directory
                rmdir($directory);
                
                $message = "Removed backup older than {$backup_remove} days: {$directory}.";
                write_debug_log($message);
                write_log(0, 0, $message, 'backup');
            }
        }
    }
    
    $message = "Backup successfully completed.";
    write_debug_log($message);
    write_log(0, 0, $message, 'backup');
    
    // If the backup was requested from the UI
    if ($force) {
        set_alert(true, "good", $message);
    }
}

?>
