<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to send  *
 * email messages to users associated with the risks that are       *
 * entered into the system.  Call it once to enable the Extra and   *
 * then schedule it to run as a cron job to have it automatically   *
 * send email messages when risks are due for review.  We recommend *
 * scheduling on a monthly basis in order to keep communications to *
 * a reasonable level.                                              *
 ********************************************************************/

// Name of the version value in the settings table
//define('VERSION_NAME', 'notification_extra_version');

global $notification_updates;
$notification_updates = array(
	'upgrade_notification_extra_20170922001',
	'upgrade_notification_extra_20171201001',
);

/*************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA DATABASE *
 *************************************************/
function upgrade_notification_extra_database()
{
    global $notification_updates;

    $version_name = 'notification_extra_version';

    // Get the current database version
    $db_version = get_settting_by_name($version_name);

    // If the database setting does not exist
    if(!$db_version)
    {
        // Set the initial version to 0
        $db_version = 0;
        update_or_insert_setting($version_name, $db_version);
    }

    // If there is a function to upgrade to the next version
    if (array_key_exists($db_version, $notification_updates))
    {
        // Get the function to upgrade to the next version
        $function = $notification_updates[$db_version];

        // If the function exists
        if (function_exists($function))
        {
            // Call the function
            call_user_func($function);

            // Set the next database version
            $db_version = $db_version + 1;

            // Update the database version
            update_or_insert_setting($version_name, $db_version);

            // Call the upgrade function again
            upgrade_notification_extra_database();
        }
    }
}


/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20170922001 *
 ****************************************************/
function upgrade_notification_extra_20170922001()
{
    // Connect to the database
    $db = db_open();


    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_RISK_COMMENT', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    // Create a table for history of Cron Job
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `cron_history` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `process_id` varchar(100) DEFAULT NULL,
          `sent_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;    
    ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_PERIOD', `value` = ''");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_HOUR', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_MINUTE', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_DAY_OF_WEEK', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_DATE', `value` = '1'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_MONTH', `value` = '1'");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20171201001 *
 ****************************************************/
function upgrade_notification_extra_20171201001()
{
    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_SUBMITTER', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_OWNER', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_OWNERS_MANAGER', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_TEAM', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_REVIEWERS', `value` = 'true'");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

?>
