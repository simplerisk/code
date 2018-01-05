<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * enforce that users only see the risks for the teams that they    *
 * have been added as a member of.                                  *
 ********************************************************************/

// Extra Version
define('GOVERNANCE_EXTRA_VERSION', '20180104-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_governance_extra_database();

// UCF access token
$ucf_access_token = "3ea216a5652ac505bddba44e52479b99b865d589";

/*************************************
 * FUNCTION: ENABLE GOVERNANCE EXTRA *
 *************************************/
function enable_governance_extra()
{
	// Open the database connection
	$db = db_open();

	// Enable the governance extra
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'governance', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
	$stmt->execute();

        // Create the table to track governance
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `governance` (`id` int(11) NOT NULL AUTO_INCREMENT, mitigation_id int(11) DEFAULT NULL, risk_ids blob NOT NULL, creation_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, last_update timestamp NOT NULL DEFAULT '0000-00-00 00:00:00', submitted_by int(11) NOT NULL DEFAULT 1, name blob, description blob, frameworks blob, PRIMARY KEY(id))");
        $stmt->execute();

	// Get the list of current mitigations
	$stmt = $db->prepare("SELECT * FROM `mitigations`");
	$stmt->execute();
	$mitigations = $stmt->fetchAll();
	
	// For each mitigation
	foreach ($mitigations as $mitigation)
	{
		// Get the values
		$mitigation_id = $mitigation['id'];
		$risk_id = $mitigation['risk_id'];
		$submission_date = $mitigation['submission_date'];
		$last_update = $mitigation['last_update'];
		$current_solution = try_decrypt($mitigation['current_solution']);
		$security_requirements = try_decrypt($mitigation['security_requirements']);
		$security_recommendations = try_decrypt($mitigation['security_recommendations']);
		$submitted_by = $mitigation['submitted_by'];
		$frameworks = ":0:";

		// Make the description
		$description = $current_solution . "\n\n" . $security_requirements . "\n\n" . $security_recommendations;

		// Add the entry to the controls table
		$stmt = $db->prepare("INSERT INTO `controls` (mitigation_id, risk_ids, creation_date, last_update, submitted_by, name, description, frameworks) VALUES (:mitigation_id, :risk_ids, :creation_date, :last_update, :submitted_by, :name, :description, :frameworks)");
		$stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
		$stmt->bindParam(":risk_ids", $risk_id, PDO::PARAM_INT);
		$stmt->bindParam(":creation_date", $submission_date, PDO::PARAM_STR);
		$stmt->bindParam(":last_update", $last_update, PDO::PARAM_STR);
		$stmt->bindParam(":submitted_by", $submitted_by, PDO::PARAM_INT);
		$stmt->bindParam(":name", $current_soluiton, PDO::PARAM_STR);
		$stmt->bindParam(":description", $description, PDO::PARAM_LOB);
		$stmt->bindParam(":frameworks", $frameworks, PDO::PARAM_STR);
		$stmt->execute();
	}

	// Close the database connection
	db_close($db);
}

/************************************
 * FUNCTION: DISABLE CONTROLS EXTRA *
 ************************************/
function disable_governance_extra()
{
	// Open the database connection
	$db = db_open();

	// Disable the governance extra
	$stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'governance'");
	$stmt->execute();

	// Close the database connection
	db_close($db);
}

/********************************
 * FUNCTION: GOVERNANCE VERSION *
 ********************************/
function governance_version()
{
	// Return the version
	return GOVERNANCE_EXTRA_VERSION;
}

/********************************
 * FUNCTION: DISPLAY GOVERNANCE *
 ********************************/
function display_governance()
{
	global $escaper;
	global $lang;

	echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . governance_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";
}

?>
