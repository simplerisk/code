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

// Extra Version
define('NOTIFICATION_EXTRA_VERSION', '20180104-001');

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Include the functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/mail.php'));
require_once(language_file());
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_notification_extra_database();

// If the extra is enabled
if (notification_extra())
{
	// And the extra is called from the command line
	if (PHP_SAPI === 'cli')
	{
		// Get the notification settings
		$configs = get_notification_settings();

		// If the cron is enabled to run
		if(check_available_cron($configs))
		{
			// Run the automated notifications of past due and renewals
			run_auto_notification();
		}
	}
	// If the auto notification was run manually
	else if (isset($_POST) && isset($_POST['auto_run_now']))
	{
		// Run the automated notifications of past due and renewals
		run_auto_notification();

		// Create an alert
		set_alert(true, "good", "The automated notification of unreviewed and past due risks has been sent.");
	}
}

/********************************
 * FUNCTION: GET SIMPLERISK URL *
 ********************************/
function get_simplerisk_url()
{
    // Figure out if the site uses http or https
    if (isset($_SERVER['HTTPS']))
    {
            $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
    }
    else $protocol = "http";

    // If a server port is set
    if (isset($_SERVER['SERVER_PORT']))
    {
        // If the server port is not 80 or 443
        if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443")
        {
            $port = ":" . $_SERVER["SERVER_PORT"];
        }
        else $port = "";
    }
    else $port = "";

    // If there is a request URI
    //if (isset($_SERVER['REQUEST_URI']))
    //{
    //    // Get the URI without the notification extra path
    //    $uri = str_replace("/extras/notification/", "", $_SERVER['REQUEST_URI']);
    //}
    //else $uri = "";

    // Create the URL
    //$url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $port . $uri;
    $url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $port;

    return $url;
}

/***************************************
 * FUNCTION: ENABLE NOTIFICATION EXTRA *
 ***************************************/
function enable_notification_extra()
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'notifications', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Add default values
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'VERBOSE', `value` = 'true'");
    $stmt->execute();
    
    $url = get_simplerisk_url();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SIMPLERISK_URL', `value` = :url");
    $stmt->bindParam(":url", $url);
    $stmt->execute();
        
    $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'FROM_NAME'");
    $stmt->execute();
    
    $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'FROM_EMAIL'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_NEW_RISK', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_RISK_UPDATE', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_NEW_MITIGATION', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_MITIGATION_UPDATE', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_REVIEW', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_CLOSE', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_RISK_COMMENT', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
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

    // Import an existing configuration file and remove it
    import_and_remove_notification_config_file();

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: DISABLE NOTIFICATION EXTRA *
 ****************************************/
function disable_notification_extra()
{
    // Open the database connection
    $db = db_open();

    // Drop a cron table
    $stmt = $db->prepare("DROP TABLE `cron_history`;");
    $stmt->execute();

    // Query the database
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'notifications'");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************
 * FUNCTION: GET USERS TO NOTIFY *
 *********************************/
function get_users_to_notify($risk_id)
{
        // Get the notification settings
        $configs = get_notification_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

        // Initialize the email array
        $email_array = array();

    // If we are supposed to notify the submitter
    if ($NOTIFY_SUBMITTER == "true")
    {
            $submitter_email = get_submitter_email($risk_id);

            // If the risk has a submitter
            if (!empty($submitter_email))
            {
                    // Add the owner to the email array
                    $row['name'] = $submitter_email[0]['name'];
                    $row['email'] = $submitter_email[0]['email'];
                    $email_array[] = $row;
            }
    }

    // If we are supposed to notify the owner
    if ($NOTIFY_OWNER == "true")
    {
        $owner_email = get_owner_email($risk_id);

        // If the risk has an owner
        if (!empty($owner_email))
        {
                // Add the owner to the email array
            $row['name'] = $owner_email[0]['name'];
                $row['email'] = $owner_email[0]['email'];
            $email_array[] = $row;
        }
    }

    // If we are supposed to notify the owner's manager
    if ($NOTIFY_OWNERS_MANAGER == "true")
    {
        $owners_manager_email = get_owners_manager_email($risk_id);

        // If the risk has an owner's manager
        if (!empty($owners_manager_email))
        {
                // Add the owner's manager to the email array
            $row['name'] = $owners_manager_email[0]['name'];
            $row['email'] = $owners_manager_email[0]['email'];
            $email_array[] = $row;
        }
    }

    // If we are supposed to notify the team
    if ($NOTIFY_TEAM == "true")
    {
        $team_email = get_team_email($risk_id);

        // If the risk has a team
        if (!empty($team_email))
        {
            // Add the team to the email array
            foreach ($team_email as $email)
            {
                $row['name'] = $email['name'];
                $row['email'] = $email['email'];
                $email_array[] = $row;
            }
        }
    }

    // If we are supposed to notify the team
    if ($NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
    {
        $emails = get_additional_stakeholder_emails($risk_id);

        // Add the additional stakeholders to the email array
        foreach ($emails as $email)
        {
            $row['name'] = $email['name'];
            $row['email'] = $email['email'];
            $email_array[] = $row;
        }
    }

    // Create an array of unique combined e-mails
//    $all_emails = array_map("unserialize", array_unique(array_map("serialize", $email_array)));
    $all_emails = array();
    foreach($email_array as $row){
        $all_emails[$row['email']] = $row;
    }

    // Write the debug log
    write_debug_log("Risk ID is ".$risk_id." and emails to send to are:\n" . print_r($all_emails, true));

    // Return the array of unique combined e-mails
    return $all_emails;
}

/*********************************
 * FUNCTION: GET SUBMITTER EMAIL *
 *********************************/
function get_submitter_email($risk_id)
{
        // Subtract 1000 from id
        $risk_id = $risk_id - 1000;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT a.id, b.name, b.email FROM `risks` a JOIN `user` b ON a.submitted_by = b.value WHERE a.id = :risk_id AND b.enabled = 1");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/*****************************
 * FUNCTION: GET OWNER EMAIL *
 *****************************/
function get_owner_email($risk_id)
{
    // Subtract 1000 from id
    $risk_id = $risk_id - 1000;

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT a.id, b.name, b.email FROM `risks` a JOIN `user` b ON a.owner = b.value WHERE a.id = :risk_id AND b.enabled = 1");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/**************************************
 * FUNCTION: GET OWNERS MANAGER EMAIL *
 **************************************/
function get_owners_manager_email($risk_id)
{
        // Subtract 1000 from id
        $risk_id = $risk_id - 1000;

        // Open the database connection
        $db = db_open();

        // Query the database
    $stmt = $db->prepare("SELECT a.id, b.name, b.email FROM `risks` a JOIN `user` b ON a.manager = b.value WHERE a.id = :risk_id AND b.enabled = 1");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/****************************
 * FUNCTION: GET TEAM EMAIL *
 ****************************/
function get_team_email($risk_id)
{
    // Subtract 1000 from id
    $risk_id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Get the team for the risk
    $stmt = $db->prepare("SELECT team FROM `risks` WHERE `id` = :risk_id");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    $team = "%:" . $array['team'] . ":%";

    $stmt = $db->prepare("SELECT name, email FROM `user` WHERE (teams LIKE :team OR teams = 'all') AND enabled = 1");
    $stmt->bindParam(":team", $team, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/************************************************
 * FUNCTION: GET ADDITIONAL STAKEHOLDER EMAILS *
 ************************************************/
function get_additional_stakeholder_emails($risk_id)
{
    // Subtract 1000 from id
    $risk_id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Get the additional_stakeholders for the risk
    $stmt = $db->prepare("SELECT additional_stakeholders FROM `risks` WHERE `id` = :risk_id");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $obj = $stmt->fetchObject();
    $stakeholders = explode(",", $obj->additional_stakeholders);
    
    if(!$stakeholders){
        return array();
    }
    
    $keys   = array();
    $values = array();
    
    foreach($stakeholders as $index => $stakeholder){
        $keys[] = ":".$index;
        $values[":".$index] = $stakeholder;
    }
    
    $stmt = $db->prepare("SELECT name, email FROM `user` WHERE value in (". implode(",", $keys) .")");
    foreach($keys as $key){
        $stmt->bindParam($key, $values[$key], PDO::PARAM_INT);
    }
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/***************************
 * FUNCTION: PREPARE EMAIL *
 ***************************/
function prepare_email($risk_id, $subject, $message)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Create the full HTML message
    $full_message = "<html><body>\n";
    $full_message .= "<p>Hello,</p>\n";
    $full_message .= $message;
    $full_message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
    $full_message .= "</body></html>\n";

    // Get the emails to send to
    $users = get_users_to_notify($risk_id);

    // Create the variable to send email to
    //$send_to = "";

    // Format the emails to send to
    //foreach($users as $user)
    //{
    //    $send_to .= "\"" . $user['name'] . "\" <" . $user['email'] . ">, ";
    //}

    // Append simplerisk to the subject
    $subject = "[SIMPLERISK] " . $subject;

    // To send HTML mail, the Content-type header must be set
    //$headers = "MIME-Version: 1.0\r\n";
    //$headers .= "Content-type: text/html; charset=UTF-8\r\n";

    // Additional headers
    //$headers .= "From: " . $FROM_NAME . " <" . $FROM_EMAIL . ">\r\n";
        //$headers .= "Reply-To: " . $FROM_NAME . " <" . $FROM_EMAIL . ">\r\n";
        //$headers .= "X-Mailer: PHP/" . phpversion();

    // For each user
    foreach ($users as $user)
    {
        $name = $user['name'];
        $email = $user['email'];

        // Write the debug log
        write_debug_log("Name: ".$name);
        write_debug_log("Email: ".$email);
        write_debug_log("Subject: ".$subject);
        write_debug_log("Full Message: ".$full_message);

        // Send the e-mail
        send_email($name, $email, $subject, $full_message);
    }

    // If we have users to send to
    //if (!empty($users))
    //{
    //    // Send the e-mail
    //    mail($send_to, $subject, $full_message, $headers);
    //}

    // Wait a second before sending another e-mail
    sleep(1);
}

/*************************************
 * FUNCTION: ADDITIONAL RISK DETAILS *
 *************************************/
function additional_risk_details($risk_id)
{
    global $lang;
    global $escaper;

    // Get the details of the risk
    $risk = get_risk_by_id($risk_id);

    // If the risk was found use the values for the risk
    if (count($risk) != 0)
    {
        $status = $risk[0]['status'];
        $subject = try_decrypt($risk[0]['subject']);
        $calculated_risk = $risk[0]['calculated_risk'];
        $reference_id = $risk[0]['reference_id'];
        $regulation = $risk[0]['regulation'];
        $control_number = $risk[0]['control_number'];
        $location = $risk[0]['location'];
        $source = $risk[0]['source'];
        $category = $risk[0]['category'];
        $team = $risk[0]['team'];
        $technology = $risk[0]['technology'];
        $owner = $risk[0]['owner'];
        $manager = $risk[0]['manager'];
        $assessment = try_decrypt($risk[0]['assessment']);
        $notes = try_decrypt($risk[0]['notes']);

        $message = "<p><b><u>Risk Details:</u></b></p>\n";
        $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Status']) .":</strong> </td><td>" . $escaper->escapeHtml($status) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Subject']) .":</strong> </td><td>" . $escaper->escapeHtml($subject) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['CalculatedRisk']) .":</strong> </td><td>" . $escaper->escapeHtml($calculated_risk) . " (". $escaper->escapeHtml(get_risk_level_name($calculated_risk)) . ")</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ExternalReferenceId']) .":</strong> </td><td>" . $escaper->escapeHtml($reference_id) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ControlRegulation']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("regulation", $regulation)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ControlNumber']) .":</strong> </td><td>" . $escaper->escapeHtml($control_number) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['SiteLocation']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("location", $location)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['RiskSource']) . ":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("source", $source)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Category']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Team']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("team", $team)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Technology']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("technology", $technology)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Owner']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['OwnersManager']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['RiskAssessment']) .":</strong> </td><td>" . $escaper->escapeHtml($assessment) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['AdditionalNotes']) .":</strong> </td><td>" . $escaper->escapeHtml($notes) . "</td></tr>\n";
        $message .= "</table>\n";
    }
    else $message = "";

    // Return the message
    return $message;
}

/*******************************************
 * FUNCTION: ADDITIONAL MITIGATION DETAILS *
 *******************************************/
function additional_mitigation_details($risk_id)
{
        global $lang;
        global $escaper;

        // Get the details of the mitigation
        $mitigation = get_mitigation_by_id($risk_id);

        // If the mitigation was found use the values for the mitigation
        if (count($mitigation) != 0)
        {
        $mitigation_date = $mitigation[0]['submission_date'];
        $planning_strategy = $mitigation[0]['planning_strategy'];
        $mitigation_effort = $mitigation[0]['mitigation_effort'];
        $mitigation_cost = $mitigation[0]['mitigation_cost'];
        $mitigation_owner = $mitigation[0]['mitigation_owner'];
        $mitigation_team = $mitigation[0]['mitigation_team'];
        $current_solution = try_decrypt($mitigation[0]['current_solution']);
        $security_requirements = try_decrypt($mitigation[0]['security_requirements']);
        $security_recommendations = try_decrypt($mitigation[0]['security_recommendations']);

            $message = "<p><b><u>Mitigation Details:</u></b></p>\n";
            $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
            $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationDate']) .":</strong> </td><td>" . $escaper->escapeHtml($mitigation_date) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['PlanningStrategy']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationEffort']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationCost']) .":</strong> </td><td>" . $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationOwner']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $mitigation_owner)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationTeam']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("team", $mitigation_team)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['CurrentSolution']) .":</strong> </td><td>" . $escaper->escapeHtml($current_solution) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['SecurityRequirements']) .":</strong> </td><td>" . $escaper->escapeHtml($security_requirements) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['SecurityRecommendations']) .":</strong> </td><td>" . $escaper->escapeHtml($security_recommendations) . "</td></tr>\n";
            $message .= "</table>\n";
    }
    else $message = "";

        // Return the message
        return $message;
}

/***************************************
 * FUNCTION: ADDITIONAL REVIEW DETAILS *
 ***************************************/
function additional_review_details($risk_id)
{
        global $lang;
        global $escaper;

        // Get the details of the review
        $review = get_review_by_id($risk_id);

        // If the review was found use the values for the review
        if (count($review) != 0)
        {
        $review_date = $review[0]['submission_date'];
        $reviewer = $review[0]['reviewer'];
        $mgmt_review = $review[0]['review'];
        $next_step = $review[0]['next_step'];
        $comments = try_decrypt($review[0]['comments']);

                $message = "<p><b><u>Review Details:</u></b></p>\n";
                $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
                $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ReviewDate']) .":</strong> </td><td>" . $escaper->escapeHtml($review_date) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Reviewer']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Review']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("review", $mgmt_review)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['NextStep']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Comments']) .":</strong> </td><td>" . $escaper->escapeHtml($comments) . "</td></tr>\n";
                $message .= "</table>\n";
        }
        else $message = "";

        // Return the message
        return $message;
}

/**************************************
 * FUNCTION: ADDITIONAL CLOSE DETAILS *
 **************************************/
function additional_close_details($risk_id)
{
        global $lang;
        global $escaper;

        // Get the details of the close
        $close = get_close_by_id($risk_id);

        // If the closure was found use the values for the closure
        if (count($close) != 0)
        {
                $user = $close[0]['user_id'];
        $closure_date = $close[0]['closure_date'];
        $close_reason = $close[0]['close_reason'];
        $note = $close[0]['note'];

                $message = "<p><b><u>Close Details:</u></b></p>\n";
                $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
                $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['DateClosed']) .":</strong> </td><td>" . $escaper->escapeHtml($closure_date) . "</td></tr>\n";
                $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ClosedBy']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $user)) . "</td></tr>\n";
                $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['CloseReason']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("close_reason", $close_reason)) . "</td></tr>\n";
                $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Comments']) .":</strong> </td><td>" . $escaper->escapeHtml($note) . "</td></tr>\n";
                $message .= "</table>\n";
        }
        else $message = "";

        // Return the message
    return $message;
}

/*****************************
 * FUNCTION: NOTIFY NEW RISK *
 *****************************/
function notify_new_risk($id, $subject)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a new risk
    if ($NOTIFY_ON_NEW_RISK == "true")
    {
        global $escaper;

        // Add 1000 to get the risk ID
        $risk_id = $id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "New Risk Submitted";
        $email_message = "<p>A new risk has been submitted by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.  The risk has been recorded as risk ID " . $escaper->escapeHtml($risk_id) .".</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>Actions:</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/mitigate.php?id=" . $escaper->escapeHtml($risk_id) . "\">Plan a Mitigation</a></li>\n";
        $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) . "\">Perform a Review</a></li>\n";
        $email_message .= "</ul>\n";
        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message);
    }
}

/********************************
 * FUNCTION: NOTIFY RISK UPDATE *
 ********************************/
function notify_risk_update($id)
{
        // Get the notification settings
        $configs = get_notification_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

        // If we are supposed to notify on a risk update
        if ($NOTIFY_ON_RISK_UPDATE == "true")
        {
            global $escaper;

            // Add 1000 to get the risk ID
            $risk_id = $id + 1000;

            // Get the users information
            $user_info = get_user_by_id($_SESSION['uid']);
            $name = $user_info['name'];

            // Create the message
            $email_subject = "Risk ID " . $escaper->escapeHtml($risk_id) . " Updated";
            $email_message = "<p>Risk ID " . $escaper->escapeHtml($risk_id) . " was updated by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

            // If verbosity is enabled
            if ($VERBOSE == "true")
            {
                    $email_message .= additional_risk_details($risk_id);
            }

            $email_message .= "<p><b><u>Actions:</u></b></p><ul>\n";
            $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
            $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) . "\">Perform a Review</a></li>\n";
            $email_message .= "</ul>\n";

            // Send the e-mail
            prepare_email($risk_id, $email_subject, $email_message);
    }
}

/***********************************
 * FUNCTION: NOTIFY NEW MITIGATION *
 ***********************************/
function notify_new_mitigation($id)
{
        // Get the notification settings
        $configs = get_notification_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

        // If we are supposed to notify on a new mitigation
        if ($NOTIFY_ON_NEW_MITIGATION == "true")
        {
        global $escaper;

            // Add 1000 to get the risk ID
            $risk_id = $id + 1000;

            // Get the users information
            $user_info = get_user_by_id($_SESSION['uid']);
            $name = $user_info['name'];

            // Create the message
            $email_subject = "Risk Mitigation Submitted for Risk ID " . $escaper->escapeHtml($risk_id);
            $email_message = "<p>A mitigation was submitted by " . $escaper->escapeHtml($name) . " for risk ID ". $escaper->escapeHtml($risk_id) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

            // If verbosity is enabled
            if ($VERBOSE == "true")
            {
            $email_message .= additional_mitigation_details($risk_id);
                    $email_message .= additional_risk_details($risk_id);
            }

            $email_message .= "<p><b><u>Actions:</u></b></p><ul>\n";
            $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
            $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) . "\">Perform a Review</a></li>\n";
            $email_message .= "</ul>\n";

            // Send the e-mail
            prepare_email($risk_id, $email_subject, $email_message);
    }
}

/**************************************
 * FUNCTION: NOTIFY MITIGATION UPDATE *
 **************************************/
function notify_mitigation_update($id)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
            // Set the name value pair as a variable
            ${$config['name']} = $config['value'];
    }
    // If we are supposed to notify on a mitigation update
    if ($NOTIFY_ON_MITIGATION_UPDATE == "true")
    {
        global $escaper;

        // Add 1000 to get the risk ID
        $risk_id = $id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "Risk Mitigation Updated for Risk ID " . $escaper->escapeHtml($risk_id);
        $email_message = "<p>The mitigation for risk ID " . $escaper->escapeHtml($risk_id) . " was updated by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
        $email_message .= additional_mitigation_details($risk_id);
                $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>Actions:</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) . "\">Perform a Review</a></li>\n";
        $email_message .= "</ul>\n";
        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message);
    }
}

/*******************************
 * FUNCTION: NOTIFY NEW REVIEW *
 *******************************/
function notify_new_review($id)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a review
    if ($NOTIFY_ON_REVIEW == "true")
    {
        global $escaper;

        // Add 1000 to get the risk ID
        $risk_id = $id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "Management Review Performed for Risk ID " . $escaper->escapeHtml($risk_id);
        $email_message = "<p>A management review of risk ID " . $escaper->escapeHtml($risk_id) . " was performed by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_review_details($risk_id);
            $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>Actions:</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message);
    }
}

/*******************************
 * FUNCTION: NOTIFY RISK CLOSE *
 *******************************/
function notify_risk_close($id)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a close
    if ($NOTIFY_ON_CLOSE == "true")
    {
        global $escaper;

        // Add 1000 to get the risk ID
        $risk_id = $id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "Risk ID " . $escaper->escapeHtml($risk_id) . " Has Been Closed";
        $email_message = "<p>Risk ID " . $escaper->escapeHtml($risk_id) . " has been closed by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_close_details($risk_id);
            $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>Actions:</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message);
    }
}

/*********************************
 * FUNCTION: NOTIFY RISK COMMENT *
 *********************************/
function notify_risk_comment($id, $comment)
{
        global $lang;
        // Get the notification settings
        $configs = get_notification_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

        // If we are supposed to notify on a risk comment
        if (notification_extra() && $NOTIFY_ON_RISK_COMMENT == "true")
        {
                global $escaper;

                // Add 1000 to get the risk ID
                $risk_id = $id + 1000;
                
                // Get the risk
                $risk = get_risk_by_id($risk_id);

                // Get the users information
                $user_info = get_user_by_id($_SESSION['uid']);
                $name = $user_info['name'];

                // Create the messageRISK
                $email_subject = "Comment Added to Risk";
                $email_message = "<p><b>".$escaper->escapeHtml($lang['Risk'])." ".$escaper->escapeHtml($lang['Subject']).": </b>".try_decrypt($risk[0]['subject'])."</p>\n";
                $email_message .= "<p><b>".$escaper->escapeHtml($lang['RiskAssessment']).": </b>".try_decrypt($risk[0]['assessment'])."</p>\n";
                $email_message .= "<p><b>".$escaper->escapeHtml($lang['Comment']).": </b>".$comment."</p>\n";
                $email_message .= "<p>A new comment has been added to risk ID " . $escaper->escapeHtml($risk_id) ." by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

                // If verbosity is enabled
                if ($VERBOSE == "true")
                {
                        $email_message .= additional_risk_details($risk_id);
                }

                $email_message .= "<p><b><u>Actions:</u></b></p><ul>\n";
                $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
                $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/mitigate.php?id=" . $escaper->escapeHtml($risk_id) . "\">Plan a Mitigation</a></li>\n";
                $email_message .= "<li><a href=\"" . $SIMPLERISK_URL . "/management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) . "\">Perform a Review</a></li>\n";
                $email_message .= "</ul>\n";

                // Send the e-mail
                prepare_email($risk_id, $email_subject, $email_message);
        }
}

/*****************************
 * FUNCTION: UPDATE SETTINGS *
 *****************************/
if (!function_exists('update_settings')) {
    function update_settings($configs)
    {
        global $lang;
	global $escaper;

        // Open the database connection
        $db = db_open();

        // If VERBOSE is not empty
        if ($configs['VERBOSE'] != "")
        {
            // Update VERBOSE
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'VERBOSE'");
                $stmt->bindParam(":value", $configs['VERBOSE']);
                $stmt->execute();
        }

        // If SIMPLERISK_URL is not empty
        if ($configs['SIMPLERISK_URL'] != "")
        {
            // Update SIMPLERISK_URL
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SIMPLERISK_URL'");
                $stmt->bindParam(":value", $configs['SIMPLERISK_URL']);
                $stmt->execute();
        }

        // If NOTIFY_SUBMITTER is not empty
        if ($configs['NOTIFY_SUBMITTER'] != "")
        {
            // Update NOTIFY_SUBMITTER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        // If NOTIFY_OWNER is not empty
        if ($configs['NOTIFY_OWNER'] != "")
        {
            // Update NOTIFY_OWNER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['NOTIFY_OWNER']);
                $stmt->execute();
        }

        // If NOTIFY_OWNERS_MANAGER is not empty
        if ($configs['NOTIFY_OWNERS_MANAGER'] != "")
        {
            // Update NOTIFY_OWNERS_MANAGER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        // If NOTIFY_TEAM is not empty
        if ($configs['NOTIFY_TEAM'] != "")
        {
            // Update NOTIFY_TEAM
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['NOTIFY_TEAM']);
                $stmt->execute();
        }

        // If NOTIFY_ON_NEW_RISK is not empty
        if ($configs['NOTIFY_ON_NEW_RISK'] != "")
        {
            // Update NOTIFY_ON_NEW_RISK
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_NEW_RISK'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_NEW_RISK']);
                $stmt->execute();
        }

        // If NOTIFY_ON_RISK_UPDATE is not empty
        if ($configs['NOTIFY_ON_RISK_UPDATE'] != "")
        {
                // Update NOTIFY_ON_RISK_UPDATE
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_RISK_UPDATE'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_RISK_UPDATE']);
                $stmt->execute();
        }

        // If NOTIFY_ON_NEW_MITIGATION is not empty
        if ($configs['NOTIFY_ON_NEW_MITIGATION'] != "")
        {
            // Update NOTIFY_ON_NEW_MITIGATION
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_NEW_MITIGATION'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_NEW_MITIGATION']);
                $stmt->execute();
        }

        // If NOTIFY_ON_MITIGATION_UPDATE is not empty
        if ($configs['NOTIFY_ON_MITIGATION_UPDATE'] != "")
        {
            // Update NOTIFY_ON_MITIGATION_UPDATE
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_MITIGATION_UPDATE'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_MITIGATION_UPDATE']);
                $stmt->execute();
        }

        // If NOTIFY_ON_REVIEW is not empty
        if ($configs['NOTIFY_ON_REVIEW'] != "")
        {
            // Update NOTIFY_ON_REVIEW
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_REVIEW'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_REVIEW']);
                $stmt->execute();
        }

        // If NOTIFY_ON_CLOSE is not empty
        if ($configs['NOTIFY_ON_CLOSE'] != "")
        {
            // Update NOTIFY_ON_CLOSE
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_CLOSE'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_CLOSE']);
                $stmt->execute();
        }

        // If NOTIFY_ON_RISK_COMMENT is not empty
        if ($configs['NOTIFY_ON_RISK_COMMENT'] != "")
        {
            // Update NOTIFY_ON_RISK_COMMENT
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_RISK_COMMENT'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_RISK_COMMENT']);
                $stmt->execute();
        }

        // If NOTIFY_ADDITIONAL_STAKEHOLDERS is not empty
        if ($configs['NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
            // Update NOTIFY_ON_RISK_COMMENT
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }
        
        // If AUTO_NOTIFY_SUBMITTER is not empty
        if ($configs['AUTO_NOTIFY_SUBMITTER'] != "")
        {
            // Update AUTO_NOTIFY_SUBMITTER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        // If AUTO_NOTIFY_OWNER is not empty
        if ($configs['AUTO_NOTIFY_OWNER'] != "")
        {
            // Update AUTO_NOTIFY_OWNER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_OWNER']);
                $stmt->execute();
        }

        // If AUTO_NOTIFY_OWNERS_MANAGER is not empty
        if ($configs['AUTO_NOTIFY_OWNERS_MANAGER'] != "")
        {
            // Update AUTO_NOTIFY_OWNERS_MANAGER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        // If AUTO_NOTIFY_TEAM is not empty
        if ($configs['AUTO_NOTIFY_TEAM'] != "")
        {
            // Update AUTO_NOTIFY_TEAM
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_TEAM']);
                $stmt->execute();
        }

        // If AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS is not empty
        if ($configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
		// Update AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }

	// If AUTO_NOTIFY_REVIEWERS is not empty
	if ($configs['AUTO_NOTIFY_REVIEWERS'] != "")
	{
		// Update AUTO_NOTIFY_REVIEWERS
		$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_REVIEWERS'");
		$stmt->bindParam(":value", $configs['AUTO_NOTIFY_REVIEWERS']);
		$stmt->execute();
	}

        // SET CRON JOB SETTINGS
        if (isset($configs['CRON_PERIOD']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_PERIOD'");
            $stmt->bindParam(":value", $configs['CRON_PERIOD']);
            $stmt->execute();
        }

        if (isset($configs['CRON_HOUR']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_HOUR'");
            $stmt->bindParam(":value", $configs['CRON_HOUR']);
            $stmt->execute();
        }
        
        if (isset($configs['CRON_MINUTE']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_MINUTE'");
            $stmt->bindParam(":value", $configs['CRON_MINUTE']);
            $stmt->execute();
        }

        if (isset($configs['CRON_DAY_OF_WEEK']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_DAY_OF_WEEK'");
            $stmt->bindParam(":value", $configs['CRON_DAY_OF_WEEK']);
            $stmt->execute();
        }
    
        if (isset($configs['CRON_MONTH']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_MONTH'");
            $stmt->bindParam(":value", $configs['CRON_MONTH']);
            $stmt->execute();
        }

        if (isset($configs['CRON_DATE']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_DATE'");
            $stmt->bindParam(":value", $configs['CRON_DATE']);
            $stmt->execute();
        }
        
        // Close the database connection
        db_close($db);
        
        set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));

        // Return true;
        return true;
    }
}

/****************************************
 * FUNCTION: UPDATE NOTIFICATION CONFIG *
 ****************************************/
function update_notification_config()
{
    $configs['VERBOSE'] = isset($_POST['verbose']) ? 'true' : 'false';
    $configs['SIMPLERISK_URL'] = $_POST['simplerisk_url'];
    $configs['NOTIFY_SUBMITTER'] = isset($_POST['notify_submitter']) ? 'true' : 'false';
    $configs['NOTIFY_OWNER'] = isset($_POST['notify_owner']) ? 'true' : 'false';
    $configs['NOTIFY_OWNERS_MANAGER'] = isset($_POST['notify_owners_manager']) ? 'true' : 'false';
    $configs['NOTIFY_TEAM'] = isset($_POST['notify_team']) ? 'true' : 'false';
    $configs['NOTIFY_ON_NEW_RISK'] = isset($_POST['notify_on_new_risk']) ? 'true' : 'false';
    $configs['NOTIFY_ON_RISK_UPDATE'] = isset($_POST['notify_on_risk_update']) ? 'true' : 'false';
    $configs['NOTIFY_ON_NEW_MITIGATION'] = isset($_POST['notify_on_new_mitigation']) ? 'true' : 'false';
    $configs['NOTIFY_ON_MITIGATION_UPDATE'] = isset($_POST['notify_on_mitigation_update']) ? 'true' : 'false';
    $configs['NOTIFY_ON_REVIEW'] = isset($_POST['notify_on_review']) ? 'true' : 'false';
    $configs['NOTIFY_ON_CLOSE'] = isset($_POST['notify_on_close']) ? 'true' : 'false';
    $configs['NOTIFY_ON_RISK_COMMENT'] = isset($_POST['notify_on_risk_comment']) ? 'true' : 'false';
    $configs['NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_SUBMITTER'] = isset($_POST['auto_notify_submitter']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_OWNER'] = isset($_POST['auto_notify_owner']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_OWNERS_MANAGER'] = isset($_POST['auto_notify_owners_manager']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_TEAM'] = isset($_POST['auto_notify_team']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['auto_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_REVIEWERS'] = isset($_POST['auto_notify_reviewers']) ? 'true' : 'false';
    
    // SET CRON CONFIGS
    if(isset($_POST['cron_period'])){
        $configs['CRON_PERIOD']     = $_POST['cron_period'];
    }
    
    if(isset($_POST['cron_hour'])){
        $configs['CRON_HOUR']     = $_POST['cron_hour'];
    }
    
    if(isset($_POST['cron_minute'])){
        $configs['CRON_MINUTE']     = $_POST['cron_minute'];
    }
    
    if(isset($_POST['cron_day_of_week'])){
        $configs['CRON_DAY_OF_WEEK']     = $_POST['cron_day_of_week'];
    }
    
    if(isset($_POST['cron_month'])){
        $configs['CRON_MONTH']     = $_POST['cron_month'];
    }
    
    if(isset($_POST['cron_date'])){
        $configs['CRON_DATE']     = $_POST['cron_date'];
    }
    
    // Update the settings
    update_settings($configs);
}

/***************************************
 * FUNCTION: GET NOTIFICATION SETTINGS *
 ***************************************/
function get_notification_settings()
{
        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("SELECT * FROM `settings` WHERE `name` = 'VERBOSE' OR `name` = 'SIMPLERISK_URL' OR `name` = 'NOTIFY_SUBMITTER' OR `name` = 'NOTIFY_OWNER' OR `name` = 'NOTIFY_OWNERS_MANAGER' OR `name` = 'NOTIFY_TEAM' OR `name` = 'NOTIFY_ON_NEW_RISK' OR `name` = 'NOTIFY_ON_RISK_UPDATE' OR `name` = 'NOTIFY_ON_NEW_MITIGATION' OR `name` = 'NOTIFY_ON_MITIGATION_UPDATE' OR `name` = 'NOTIFY_ON_REVIEW' OR `name` = 'NOTIFY_ON_CLOSE' OR `name` = 'NOTIFY_ON_RISK_COMMENT' OR `name` = 'NOTIFY_ADDITIONAL_STAKEHOLDERS' OR `name` = 'CRON_PERIOD' OR `name` = 'CRON_HOUR' OR `name` = 'CRON_MINUTE' OR `name` = 'CRON_MONTH' OR `name` = 'CRON_DATE' OR `name` = 'CRON_DAY_OF_WEEK' OR `name` = 'AUTO_NOTIFY_SUBMITTER' OR `name` = 'AUTO_NOTIFY_OWNER' OR `name` = 'AUTO_NOTIFY_OWNERS_MANAGER' OR `name` = 'AUTO_NOTIFY_TEAM' OR `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS' OR `name` = 'AUTO_NOTIFY_REVIEWERS';");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/*********************************************
 * FUNCTION: CREATE HOUR AND MINUTE DROPDOWN *
 *********************************************/
function create_time_html($configArray, $enabled = true){
    $configs = [];
    foreach($configArray as $config){
        $configs[$config['name']] = $config['value'];
    }
    
    $html = "<span>Time</span>: <select name='cron_hour' ".($enabled ? "" : " disabled ").">";
    foreach(range(0, 23) as $value){
        if(isset($configs['CRON_HOUR']) && $configs['CRON_HOUR'] == $value){
            $html .= "<option selected value='{$value}'>{$value}</option>";
        }else{
            $html .= "<option value='{$value}'>{$value}</option>";
        }
    }
    $html .= "</select>";
    
    $html .= "&nbsp;&nbsp; : &nbsp;&nbsp;";
    
    $html .= "<select name='cron_minute' ".($enabled ? "" : " disabled ").">";
    foreach(range(0, 59) as $value){
        if(isset($configs['CRON_MINUTE']) && $configs['CRON_MINUTE'] == $value){
            $html .= "<option selected value='{$value}'>{$value}</option>";
        }else{
            $html .= "<option value='{$value}'>{$value}</option>";
        }
    }
    $html .= "</select>";
    
    return $html;
}

/*********************************************
 * FUNCTION: CREATE DAY OF WEEK DROPDOWN *
 *********************************************/
function create_day_of_week_html($configArray, $enabled = true){
    $configs = [];
    foreach($configArray as $config){
        $configs[$config['name']] = $config['value'];
    }

    $timestamp = strtotime('next Sunday');

    $html = "<span>Day of Week:</span> <select name='cron_day_of_week' ".($enabled ? "" : " disabled ").">";
    for ($i = 0; $i < 7; $i++) {
        if(isset($configs['CRON_DAY_OF_WEEK']) && $configs['CRON_DAY_OF_WEEK'] == $i){
            $html .= "<option selected value='{$i}'>".strftime('%A', $timestamp)."</option>";
        }else{
            $html .= "<option value='{$i}'>".strftime('%A', $timestamp)."</option>";
        }
        $timestamp = strtotime('+1 day', $timestamp);
    }
    $html .= "</select>";
    return $html;
}

/*********************************************
 * FUNCTION: CREATE DATE DROPDOWN *
 *********************************************/
function create_date_html($configArray, $enabled = true){
    $configs = [];
    foreach($configArray as $config){
        $configs[$config['name']] = $config['value'];
    }

    $html = "<span>Date:</span> <select name='cron_date' ".($enabled ? "" : " disabled ").">";
    foreach(range(1, 31) as $value){
        if(isset($configs['CRON_DATE']) && $configs['CRON_DATE'] == $value){
            $html .= "<option selected value='{$value}'>{$value}</option>";
        }else{
            $html .= "<option value='{$value}'>{$value}</option>";
        }
    }
    $html .= "</select>";
    
    return $html;
}

/*********************************************
 * FUNCTION: CREATE MONTHS AND DATE DROPDOWN *
 *********************************************/
function create_day_html($configArray, $enabled = true){
    $configs = [];
    foreach($configArray as $config){
        $configs[$config['name']] = $config['value'];
    }

    $html = "<span>Day</span>: <select name='cron_month' ".($enabled ? "" : " disabled ").">";
    for ($m=1; $m<=12; $m++) {
        $month = date('F', mktime(0,0,0,$m, 1, date('Y')));
        if(isset($configs['CRON_MONTH']) && $configs['CRON_MONTH'] == $m){
            $html .= "<option selected value='{$m}'>{$month}</option>";
        }else{
            $html .= "<option value='{$m}'>{$month}</option>";
        }
    }
    $html .= "</select>";
    
    $html .= "&nbsp;&nbsp; ";
    
    $html .= "<select name='cron_date' ".($enabled ? "" : " disabled ").">";
    foreach(range(1, 31) as $value){
        if(isset($configs['CRON_DATE']) && $configs['CRON_DATE'] == $value){
            $html .= "<option selected value='{$value}'>{$value}</option>";
        }else{
            $html .= "<option value='{$value}'>{$value}</option>";
        }

    }
    $html .= "</select>";
    
    return $html;
}

/**********************************
 * FUNCTION: DISPLAY NOTIFICATION *
 **********************************/
function display_notification()
{
    global $escaper;
    global $lang;

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . notification_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

        // Get the notification settings
        $configs = get_notification_settings();

        // For each configuration
        foreach ($configs as $config)
        {
            // Set the name value pair as a variable
            ${$config['name']} = $config['value'];
        }

        echo "<form name=\"notification_extra\" method=\"post\" action=\"\">\n";
        echo "<table>\n";
        
/*
        echo "<tr>\n";
        echo "<td colspan=\"2\"><u><strong>".$lang["Schedule"].":</strong></u></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Period:</td>\n";
        echo "
            <td>
                <select name=\"cron_period\" id=\"cron_period\" >
                    <option value=\"\">--- Select Period ---</option>
                    <option value=\"daily\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='daily') ? " selected " : "") ." >".$lang["Daily"]."</option>
                    <option value=\"weekly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='weekly') ? " selected " : "") ." >".$lang["Weekly"]."</option>
                    <option value=\"monthly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='monthly') ? " selected " : "") ." >".$lang["Monthly"]."</option>
                    <!-- option value=\"quarterly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='quarterly') ? " selected " : "") ." >".$lang["Quarterly"]."</option -->
                    <option value=\"annually\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='annually') ? " selected " : "") ." >".$lang["Annually"]."</option>
                </select>
            </td>\n
        ";
        echo "</tr>\n";
        
        $dailySelected      = (isset($CRON_PERIOD) && $CRON_PERIOD=='daily');
        $weeklySelected     = (isset($CRON_PERIOD) && $CRON_PERIOD=='weekly');
        $monthlySelected    = (isset($CRON_PERIOD) && $CRON_PERIOD=='monthly');
        $annuallySelected   = (isset($CRON_PERIOD) && $CRON_PERIOD=='annually');
        echo "
            <tr id='specified_daily' class='specified_time_holder' ". ($dailySelected ? "style='display:table-row'" : "") .">
                <td>".$lang["SpecifiedTime"].":</td>\n
                <td>
                ".create_time_html($configs, $dailySelected)."
                </td>
            </tr>
            
            <tr id='specified_weekly' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='weekly') ? "style='display:table-row'" : "") .">
                <td>".$lang["SpecifiedTime"].":</td>\n
                <td>
                ".create_day_of_week_html($configs, $weeklySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $weeklySelected)."
                </td>
            </tr>

            <tr id='specified_monthly' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='monthly') ? "style='display:table-row'" : "") .">
                <td>".$lang["SpecifiedTime"].":</td>\n
                <td>
                ".create_date_html($configs, $monthlySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $monthlySelected)."
                </td>
            </tr>
            
            <tr id='specified_annually' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='annually') ? "style='display:table-row'" : "") .">
                <td>".$lang["SpecifiedTime"].":</td>\n
                <td>
                ".create_day_html($configs, $annuallySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $annuallySelected)."
                </td>
            </tr>
        ";
*/
        
        echo "<tr>\n";
        echo "<td colspan=\"2\"><u><strong>When to notify:</strong></u></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify on New Risk:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_on_new_risk\" id=\"notify_on_new_risk\"" . ($NOTIFY_ON_NEW_RISK == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify on Risk Update:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_on_risk_update\" id=\"notify_on_risk_update\"" . ($NOTIFY_ON_RISK_UPDATE == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify on New Mitigation:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_on_new_mitigation\" id=\"notify_on_new_mitigation\"" . ($NOTIFY_ON_NEW_MITIGATION == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify on Mitigation Update:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_on_mitigation_update\" id=\"notify_on_mitigation_update\"" . ($NOTIFY_ON_MITIGATION_UPDATE == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify on Risk Review:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_on_review\" id=\"notify_on_review\"" . ($NOTIFY_ON_REVIEW == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify on Risk Close:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_on_close\" id=\"notify_on_close\"" . ($NOTIFY_ON_CLOSE == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify on Risk Comment:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_on_risk_comment\" id=\"notify_on_risk_comment\"" . ($NOTIFY_ON_RISK_COMMENT == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td colspan=\"2\">&nbsp;</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td colspan=\"2\"><u><strong>Who to notify:</strong></u></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify Submitter:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_submitter\" id=\"notify_submitter\"" . ($NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify Owner:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_owner\" id=\"notify_owner\"" . ($NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify Owners Manager:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_owners_manager\" id=\"notify_owners_manager\"" . ($NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Notify Team:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_team\" id=\"notify_team\"" . ($NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>". $escaper->escapeHtml($lang['AdditionalStakeholders']) .":</td>\n";
        echo "<td><input type=\"checkbox\" name=\"notify_additional_stakeholders\" id=\"additional_stakeholders\"" . ($NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td colspan=\"2\">&nbsp;</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td colspan=\"2\"><u><strong>How to notify:</strong></u></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>Verbose Emails:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"verbose\" id=\"verbose\"" . ($VERBOSE == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>SimpleRisk URL:</td>\n";
        echo "<td><input type=\"text\" name=\"simplerisk_url\" id=\"simplerisk_url\" value=\"" . $SIMPLERISK_URL . "\" /></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        //echo "<div class=\"form-actions\">\n";
        //echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Submit']) . "</button>\n";
        //echo "</div>\n";
        //echo "</form>\n";
	echo "<br />\n";
	echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
	echo "<tr align=\"center\"><td><h4>Automated Notifications of Unreviewed/Past Due Risks</h4></td></tr>\n";
	echo "<tr><td>Place the following in your crontab to run automatically:<br />0 * * * * /usr/bin/php -f " . realpath(__DIR__ . '/index.php') . "</td></tr>\n";
	echo "<tr><td>\n";
	echo "<table border=\"0\" width=\"100%\">\n";
        echo "<tr>\n";
        echo "<td colspan=\"2\"><u><strong>".$escaper->escapeHtml($lang["Schedule"]).":</strong></u><span style=\"float: right;\"><button type=\"submit\" name=\"auto_run_now\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['RunNow']) . "</button></span></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td width=\"100px\">Period:</td>\n";
        echo "
            <td>
                <select name=\"cron_period\" id=\"cron_period\" >
                    <option value=\"\">--- Select Period ---</option>
                    <option value=\"daily\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='daily') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Daily"])."</option>
                    <option value=\"weekly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='weekly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Weekly"])."</option>
                    <option value=\"monthly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='monthly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Monthly"])."</option>
                    <!-- option value=\"quarterly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='quarterly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Quarterly"])."</option -->
                    <option value=\"annually\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='annually') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Annually"])."</option>
                </select>
            </td>\n
        ";
	echo "</tr>\n";
        $dailySelected      = (isset($CRON_PERIOD) && $CRON_PERIOD=='daily');
        $weeklySelected     = (isset($CRON_PERIOD) && $CRON_PERIOD=='weekly');
        $monthlySelected    = (isset($CRON_PERIOD) && $CRON_PERIOD=='monthly');
        $annuallySelected   = (isset($CRON_PERIOD) && $CRON_PERIOD=='annually');
        echo "
            <tr id='specified_daily' class='specified_time_holder' ". ($dailySelected ? "style='display:table-row'" : "") .">
                <td width=\"100px\">".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_time_html($configs, $dailySelected)."
                </td>
            </tr>
            
            <tr id='specified_weekly' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='weekly') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_day_of_week_html($configs, $weeklySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $weeklySelected)."
                </td>
            </tr>

            <tr id='specified_monthly' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='monthly') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_date_html($configs, $monthlySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $monthlySelected)."
                </td>
            </tr>
            
            <tr id='specified_annually' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='annually') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_day_html($configs, $annuallySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $annuallySelected)."
                </td>
            </tr>
        ";
        echo "</tr>\n";
	echo "<tr><td colspan=\"2\"><u><strong>Who To Notify:</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_submitter\" id=\"auto_notify_submitter\"" . ($AUTO_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;Notify Submitter</td></tr>\n";
	echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_owner\" id=\"auto_notify_owner\"" . ($AUTO_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;Notify Owner</td></tr>\n";
	echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_owners_manager\" id=\"auto_notify_owners_manager\"" . ($AUTO_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;Notify Owner&rsquo;s Manager</td></tr>\n";
	echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_team\" id=\"auto_notify_team\"" . ($AUTO_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;Notify Team</td></tr>\n";
	echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_additional_stakeholders\" id=\"additional_stakeholders\"" . ($AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;Notify Additional Stakeholders</td></tr>\n";
	echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_reviewers\" id=\"reviewers\"" . ($AUTO_NOTIFY_REVIEWERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;Notify Reviewers</td></tr>\n";
	echo "</table>\n";
	echo "</td></tr>\n";
	echo "</table>\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Submit']) . "</button>\n";
        echo "</div>\n";
	echo "</form>\n";
}

/**********************************
 * FUNCTION: NOTIFICATION VERSION *
 **********************************/
function notification_version()
{
    // Return the version
    return NOTIFICATION_EXTRA_VERSION;
}

/******************************
 * FUNCTION: READ CONFIG FILE *
 ******************************/
if (!function_exists('read_config_file')) {
function read_config_file()
{
        // Location of the configuration file
        $config_file = realpath(__DIR__ . '/includes/config.php');

        // Open the file for reading
        $handle = fopen($config_file, 'r');

        // If we can read the file
        if ($handle)
        {
                // Create a configuration array
                $config_array = array();

                // Read each line in the file
                while ($line = fgets($handle))
                {
                        // If the line begins with define
                        if (preg_match('/^define\(\'*\'*/', $line))
                        {
                                // Grab the parameter and value
                                preg_match('/\((.*?)\,(.*?)\)/s', $line, $matches);
                                $param_name = $matches[1];
                                $param_value = $matches[2];

                                // Remove any double quotes
                                $param_name = str_replace('"', "", $param_name);
                                $param_value = str_replace('"', "", $param_value);

                                // Remove any single quotes
                                $param_name = str_replace('\'', "", $param_name);
                                $param_value = str_replace('\'', "", $param_value);

                                // Remove any spaces
                                $param_name = str_replace(' ', "", $param_name);
                                $param_value = str_replace(' ', "", $param_value);

                                $config_array[$param_name] = $param_value;
                        }
                }

                // Close the file
                fclose($handle);

                // Return the configuration array
                return $config_array;
        }
        else
        {
                // Return an error
                return 0;
        }
}
}

/********************************************************
 * FUNCTION: IMPORT AND REMOVE NOTIFICATION CONFIG FILE *
 ********************************************************/
function import_and_remove_notification_config_file()
{
        global $escaper;

        // Location of the configuration file
        $config_file = realpath(__DIR__ . '/includes/config.php');

        // If a configuration file exists
        if (file_exists($config_file))
        {
                // Read the configuration file
                $configs = read_config_file();

                // Update the configuration in the settings table
                if (update_settings($configs))
                {
                        // Remove the configuration file
                        if (!delete_file($config_file))
                        {
                                $alert_message = "ERROR: Could not remove " . $config_file;
                                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                                echo "<div class=\"span12 redalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                                echo "</div>\n";
                        }
                }
        }
}

/****************************
 * FUNCTION: SAVE CRON DATE *
 ****************************/
function save_cron_history($date)
{
    // Open the database connection
    $db = db_open();

    // Get latest sent date
    $stmt = $db->prepare("INSERT INTO `cron_history`(`sent_at`) VALUES(:sent_at);");
    $stmt->bindParam(":sent_at", $date);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
}

/******************************************************
 * FUNCTION: GET THE DATE THAT CRON JOB SHOULD BE RUN *
 ******************************************************/
function check_available_cron($configs=false)
{
    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If the cron period value is not set
    if(!isset($CRON_PERIOD) || !$CRON_PERIOD)
    {
        // Do nothing
        return false;
    }
    
    // Open the database connection
    $db = db_open();

    // Get latest sent date
    $stmt = $db->prepare("SELECT * FROM `cron_history` ORDER BY `sent_at` desc Limit 1;");
    $stmt->execute();
    
    $latestCronjob = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    // Get latest date and time of cronjob
    $latestDate = isset($latestCronjob) ? $latestCronjob['sent_at'] : "";

    // Get the target date based on the specified cron period
    if($CRON_PERIOD == 'daily'){
        $targetDate = date('Y-m-d H:i:s', mktime($CRON_HOUR, $CRON_MINUTE, 0));
    }elseif($CRON_PERIOD == 'weekly'){
        $targetDate =  date('Y-m-d H:i:s', strtotime(($CRON_DAY_OF_WEEK - date('w')).' day', mktime($CRON_HOUR, $CRON_MINUTE, 0)));
    }elseif($CRON_PERIOD == 'monthly'){
        $targetDate =  date('Y-m-d H:i:s', mktime($CRON_HOUR, $CRON_MINUTE, 0, null, $CRON_DATE));
    }elseif($CRON_PERIOD == 'annually'){
        $targetDate =  date('Y-m-d H:i:s', mktime($CRON_HOUR, $CRON_MINUTE, 0, $CRON_MONTH, $CRON_DATE));
    }

    // Format the last run and next run in DateTime format
    $last_run = new DateTime(date('Y-m-d', strtotime($latestDate)));
    $next_run = new DateTime(date('Y-m-d', strtotime($targetDate)));

    // If the cron job hasn't been run yet today
    if ($last_run->format('Y-m-d') != $next_run->format('Y-m-d'))
    {
        // If it is after the target time
        if (time() - strtotime($targetDate) >= 0)
        {
            // If the target time is within the past 5 minutes
            if (time() - strtotime($targetDate) < 300)
            {
                // Save the current date to the cron history table
                save_cron_history(date('Y-m-d H:i:s', time()));

                // Return that the cron was available to run
                return true;
            }
        }
        // Otherwise, do nothing
        else return false;
    }
    // Otherwise, do nothing
    else return false;
}

/***********************************
 * FUNCTION: RUN AUTO NOTIFICATION *
 ***********************************/
function run_auto_notification()
{
	global $lang;
	global $escaper;

	// Get the notification settings
	$configs = get_notification_settings();

	// If the encryption extra is enabled
	if (encryption_extra())
	{
		// Load the extra
		require_once(realpath(__DIR__ . '/../encryption/index.php'));
                
                // Get username to get encrypted pass
                $username = get_username_for_encrypted_pass();
                
                if (check_encryption_from_external($username))
		{
			// Get the current password encrypted with the temp key
			$encrypted_pass = get_enc_pass($username, fetch_tmp_pass());
                }
                // The user has not yet been activated
                else
                {
			$encrypted_pass = false;
                }
                
                $_SESSION['encrypted_pass'] = $encrypted_pass;
	}

	// For each configuration
	foreach ($configs as $config)
	{
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
	}

	// Get the list of users
	$users = get_custom_table("enabled_users");

	// Open the database connection
	$db = db_open();

	// For each user
	foreach ($users as $user)
	{
		$user_id = $user['value'];
		$name = $user['name'];
		$email = $user['email'];
		$teams = $user['teams'];
		$review_veryhigh = $user['review_veryhigh'];
		$review_high = $user['review_high'];
		$review_medium = $user['review_medium'];
		$review_low = $user['review_low'];
		$review_insignificant = $user['review_insignificant'];

		// If we are supposed to auto notify submitters
		if ($AUTO_NOTIFY_SUBMITTER == "true")
		{
			// Get all open risks with that user as submitter
			$stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.additional_stakeholders, c.name as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN team c ON b.team = c.value LEFT JOIN user d ON b.owner = d.value LEFT JOIN user e ON b.manager = e.value LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id LEFT JOIN user g ON b.submitted_by = g.value WHERE status != \"Closed\" AND b.submitted_by = :user_id ORDER BY calculated_risk DESC");
			$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
			$stmt->execute();
			$submitter_risks = $stmt->fetchAll();
		}
		else $submitter_risks = array();

		// If we are supposed to auto notify owners
		if ($AUTO_NOTIFY_OWNER == "true")
		{
			// Get all open risks with that user as owner
			$stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.additional_stakeholders, c.name as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN team c ON b.team = c.value LEFT JOIN user d ON b.owner = d.value LEFT JOIN user e ON b.manager = e.value LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id LEFT JOIN user g ON b.submitted_by = g.value WHERE status != \"Closed\" AND b.owner = :user_id ORDER BY calculated_risk DESC");
			$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
			$stmt->execute();
			$owner_risks = $stmt->fetchAll();
		}
		else $owner_risks = array();

		// If we are supposed to auto notify owners manager
		if ($AUTO_NOTIFY_OWNERS_MANAGER == "true")
		{
			// Get all open risks with that user as manager
			$stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.additional_stakeholders, c.name as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN team c ON b.team = c.value LEFT JOIN user d ON b.owner = d.value LEFT JOIN user e ON b.manager = e.value LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id LEFT JOIN user g ON b.submitted_by = g.value WHERE status != \"Closed\" AND b.manager = :user_id ORDER BY calculated_risk DESC");
			$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
			$stmt->execute();
			$manager_risks = $stmt->fetchAll();
		}
		else $manager_risks = array();

		// If we are supposed to auto notify team
		if ($AUTO_NOTIFY_TEAM == "true")
		{
			// If the team is not none
			if ($teams != "none")
			{
				// Remove the first colon from the teams list
				$teams = substr($teams, 1);

				// Remove the last colon from the teams list
				$teams = substr($teams, 0, -1);

				// Get an array of teams the user belongs to
				$teams = explode("::", $teams);

				// Get the number of teams
				$number_of_teams = count($teams);

				// Create an empty string for the team SQL
				$teams_sql = "";

				// For each team
				for ($i = 0; $i < $number_of_teams; $i++)
				{
					// If this isn't the last team
					if ($i != $number_of_teams - 1)
					{
						$teams_sql .= "b.team = " . $teams[$i] . " OR ";
					}
					else $teams_sql .= "b.team = " . $teams[$i];
				}

				// If there is at least one team
				if ($number_of_teams > 0)
				{
					// Get all open risks for the teams the user belongs to
					$stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.additional_stakeholders, c.name as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN team c ON b.team = c.value LEFT JOIN user d ON b.owner = d.value LEFT JOIN user e ON b.manager = e.value LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id LEFT JOIN user g ON b.submitted_by = g.value WHERE status != \"Closed\" AND (" . $teams_sql . ") ORDER BY calculated_risk DESC");
					$stmt->execute();
					$team_risks = $stmt->fetchAll();
				}
				// Otherwise the team risks array is empty
				else $team_risks = array();
			}
			// Otherwise the team risks array is empty
			else $team_risks = array();
		}
		else $team_risks = array();

		// If we are supposed to auto notify additional stakeholders
		if ($AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
		{
			// Get all open risks with that user as manager
			$stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.additional_stakeholders, c.name as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN team c ON b.team = c.value LEFT JOIN user d ON b.owner = d.value LEFT JOIN user e ON b.manager = e.value LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id LEFT JOIN user g ON b.submitted_by = g.value WHERE status != \"Closed\" AND find_in_set(:user_id, b.additional_stakeholders) ORDER BY calculated_risk DESC");
			$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
			$stmt->execute();
			$stakeholder_risks = $stmt->fetchAll();
		}
		else $stakeholder_risks = array();

		// If we are supposed to auto notify reviewers
		if ($AUTO_NOTIFY_REVIEWERS == "true")
		{
                        // If the team is not none
                        if ($teams != "none")
                        {
                                // Remove the first colon from the teams list
                                $teams = substr($teams, 1);

                                // Remove the last colon from the teams list
                                $teams = substr($teams, 0, -1);

                                // Get an array of teams the user belongs to
                                $teams = explode("::", $teams);

                                // Get the number of teams
                                $number_of_teams = count($teams);

                                // Create an empty string for the team SQL
                                $teams_sql = "";

                                // For each team
                                for ($i = 0; $i < $number_of_teams; $i++)
                                {
                                        // If this isn't the last team
                                        if ($i != $number_of_teams - 1)
                                        {
                                                $teams_sql .= "b.team = " . $teams[$i] . " OR ";
                                        }
                                        else $teams_sql .= "b.team = " . $teams[$i];
                                }

                                // If there is at least one team
                                if ($number_of_teams > 0)
                                {
                                        // Get all open risks for the teams the user belongs to
                                        $stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.additional_stakeholders, c.name as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN team c ON b.team = c.value LEFT JOIN user d ON b.owner = d.value LEFT JOIN user e ON b.manager = e.value LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id LEFT JOIN user g ON b.submitted_by = g.value WHERE status != \"Closed\" AND (" . $teams_sql . ") ORDER BY calculated_risk DESC");
                                        $stmt->execute();
                                        $unfiltered_reviewer_risks = $stmt->fetchAll();

					// Create the reviewer risks array
					$reviewer_risks = array();

					// For each of the unfiltered risks
					foreach ($unfiltered_reviewer_risks as $array)
					{
						// Get the risk level name
						$risk_level_name = get_risk_level_name($array['calculated_risk']);

						// If the risk level is one the user can review
						if (($risk_level_name == 'VeryHigh' && $review_veryhigh) || ($risk_level_name == 'High' && $review_high) || ($risk_level_name == 'Medium' && $review_medium) || ($risk_level_name == 'Low' && $review_low) || ($risk_level_name == 'Insignificant' && $review_insignificant))
						{
							$reviewer_risks[] = $array;
						}
					}
                                }
                                // Otherwise the reviewer risks array is empty
                                else $reviewer_risks = array();
                        }
                        // Otherwise the reviewer risks array is empty
                        else $reviewer_risks = array();
                }
		else $reviewer_risks = array();

		// Merge the arrays together
		$risks = array();
		if (!empty($submitter_risks)) $risks = array_merge($risks, $submitter_risks);
		if (!empty($owner_risks)) $risks = array_merge($risks, $owner_risks);
		if (!empty($manager_risks)) $risks = array_merge($risks, $manager_risks);
		if (!empty($team_risks)) $risks = array_merge($risks, $team_risks);
		if (!empty($stakeholder_risks)) $risks = array_merge($risks, $stakeholder_risks);
		if (!empty($reviewer_risks)) $risks = array_merge($risks, $reviewer_risks);

		// Remove duplicates from the multidimensional array
		$risks = array_map("unserialize", array_unique(array_map("serialize", $risks)));

		// Create some empty arrays
		$risk_email = array();
		$status_text = array();
		$calculated_risk = array();

               // For each risk in the array
               foreach ($risks as $risk)
               {
			// Get whether the risk level is high, medium, or low
			$risk_level = get_risk_level_name($risk['calculated_risk']);

			// Get the next review date based on the risk level
			$next_review = $risk['next_review'];
			$next_review = next_review($risk_level, $risk['id'], $next_review);

			// If the risk is unreviewed or past due then we will need to send a notification
			if (preg_match("/UNREVIEWED/", $next_review, $status) || preg_match("/".$lang['PASTDUE']."/", $next_review, $status))
			{
				// Set the status text in the array
				$risk['status_text'] = $status[0];
				$risk[9] = $status[0];

				// Add the value to the risk email array
				$risk_email[] = $risk;
			}
			else
			{
				// Set the status text in the array
				$risk['status_text'] = "";
				$risk[9] = "";
			}
		}

		// If there are risks for this user
		if (!empty($risk_email))
		{
			// Get the list of columns
			foreach ($risk_email as $key => $row)
			{
				$status_text[$key] = $row['status_text'];
				$calculated_risk[$key] = $row['calculated_risk'];
			}

			// Sort the risk email array by status text and calculated risk
			array_multisort($status_text, SORT_DESC, SORT_STRING, $calculated_risk, SORT_DESC, SORT_NUMERIC, $risk_email);

			// Create the message
			$message = "<html><body>\n";
			$message .= "<p>You are receiving this message because you are the submitter, owner, owner's manager, belong to the team, or are an additional stakeholder associated with the following risks which need to be reviewed.  You will continue to receive e-mail reminders until a review has taken place.</p>\n";

			// Track the status
			$status_tracker = "";

			// For each risk
			foreach ($risk_email as $risk)
			{
				// Get the risk values
				$id = convert_id($risk['id']);
				$calculated_risk = $risk['calculated_risk'];
				$color = $escaper->escapeHtml(get_risk_color($calculated_risk));
				$subject = try_decrypt($risk['subject']);
				$status_text = $risk['status_text'];
				$submitter = $risk['submitter'];
				$owner = $risk['owner'];
				$manager = $risk['manager'];
				$team = $risk['team'];

				// If the values aren't set, set them to unassigned
				if (is_null($submitter)) $submitter = $lang['Unassigned'];
				if (is_null($owner)) $owner = $lang['Unassigned'];
				if (is_null($manager)) $manager = $lang['Unassigned'];
				if (is_null($team)) $team = $lang['Unassigned'];

				// If the status tracker is different than the status text
				if ($status_tracker != $status_text)
				{
					// If the status tracker is not null
					if ($status_tracker != "")
					{
						// End the current table
						$message .= "</table>\n";
						$message .= "</p>\n";
					}

					// Set the status tracker to the status text
					$status_tracker = $status_text;

					// Display the table header
					$message .= "<p>\n";
					$message .= "<table cellpadding=\"10px\" style=\"width:100%\">\n";
					$message .= "<caption><b><u>" . $escaper->escapeHtml($status_text) . "</u></b></caption>\n";
					$message .= "<tr>\n";
					$message .= "<th>" . $escaper->escapeHtml($lang['RiskId']) . "</th>\n";
					$message .= "<th>" . $escaper->escapeHtml($lang['CalculatedRisk']) . "</th>\n";
					$message .= "<th>" . $escaper->escapeHtml($lang['Subject']) . "</th>\n";
					$message .= "<th>" . $escaper->escapeHtml($lang['SubmittedBy']) . "</th>\n";
					$message .= "<th>" . $escaper->escapeHtml($lang['Owner']) . "</th>\n";
					$message .= "<th>" . $escaper->escapeHtml($lang['OwnersManager']) . "</th>\n";
					$message .= "<th>" . $escaper->escapeHtml($lang['Team']) . "</th>\n";
					$message .= "<th>" . $escaper->escapeHtml($lang['PerformAReview']) . "</th>\n";
					$message .= "</tr>\n";
				}

				$message .= "<tr>\n";
				$message .= "<td align=\"center\"><a href=\"" . $SIMPLERISK_URL . "/management/view.php?id=" . $escaper->escapeHtml($id) . "\">" . $escaper->escapeHtml($id) . "</a></td>\n";
				$message .= "<td align=\"center\"><table width=\"25px\" height=\"25px\" border=\"0\" style=\"border: 1px solid #000000; background-color: {$color};\"><tr><td valign=\"middle\" halign=\"center\"><center><font size=\"2\">" . $escaper->escapeHtml($calculated_risk) . "</font></center></td></tr></table></td>\n";
				$message .= "<td align=\"left\">" . $escaper->escapeHtml($subject) . "</td>\n";
				$message .= "<td align=\"center\">" . $escaper->escapeHtml($submitter) . "</td>\n";
				$message .= "<td align=\"center\">" . $escaper->escapeHtml($owner) . "</td>\n";
				$message .= "<td align=\"center\">" . $escaper->escapeHtml($manager) . "</td>\n";
				$message .= "<td align=\"center\">" . $escaper->escapeHtml($team) . "</td>\n";
				$message .= "<td align=\"center\"><a href=\"" . $SIMPLERISK_URL . "/management/mgmt_review.php?id=" . $escaper->escapeHtml($id) . "\">" . $escaper->escapeHtml($lang['Review']) . "</a></td>\n";
				$message .= "</tr>\n";
			}

			// End the current table and message
			$message .= "</table>\n";
			$message .= "</p>\n";
			$message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
			$message .= "</body></html>\n";

			// Create the subject
			$subject = "[SIMPLERISK] Notification of Unreviewed and Past Due Risks";

			// Send the email
			send_email($name, $email, $subject, $message);
		}
	}

	// Close the database connection
	db_close($db);
}

?>
