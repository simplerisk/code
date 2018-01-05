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
//define('VERSION_NAME', 'governance_extra_version');

global $governance_updates;
$governance_updates = array(
);

/*************************************************
 * FUNCTION: UPGRADE GOVERNANCE EXTRA DATABASE *
 *************************************************/
function upgrade_governance_extra_database()
{
    global $governance_updates;

    $version_name = 'governance_extra_version';

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
    if (array_key_exists($db_version, $governance_updates))
    {
        // Get the function to upgrade to the next version
        $function = $governance_updates[$db_version];

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
            upgrade_governance_extra_database();
        }
    }
}

?>
