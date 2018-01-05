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
define('SEPARATION_EXTRA_VERSION', '20180104-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));

require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_separation_extra_database();

/******************************************
 * FUNCTION: ENABLE TEAM SEPARATION EXTRA *
 ******************************************/
function enable_team_separation_extra()
{
	// Open the database connection
	$db = db_open();

	// Query the database
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'team_separation', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
	$stmt->execute();

    // Enable all permissions to true 
    $permissions = array(
        'allow_owner_to_risk'           => 1,
        'allow_ownermanager_to_risk'    => 1,
        'allow_submitter_to_risk'       => 1,
        'allow_team_member_to_risk'     => 1,
        'allow_stakeholder_to_risk'     => 1
    );
    update_permission_settings($permissions);
    
	// Close the database connection
	db_close($db);
}

/*******************************************
 * FUNCTION: DISABLE TEAM SEPARATION EXTRA *
 *******************************************/
function disable_team_separation_extra()
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'team_separation'");
    $stmt->execute();

    // Disable all permissions to true 
    $permissions = array(
        'allow_owner_to_risk'           => 0,
        'allow_ownermanager_to_risk'    => 0,
        'allow_submitter_to_risk'       => 0,
        'allow_team_member_to_risk'     => 0,
        'allow_stakeholder_to_risk'     => 0
    );
    update_permission_settings($permissions);
    
    // Close the database connection
    db_close($db);
}

function update_permission_settings($permissions){
    // Open the database connection
    $db = db_open();
    
    foreach($permissions as $key => $value){
        // Add or Update the permission to risk.
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = :name, `value` = :value ON DUPLICATE KEY UPDATE `value` = :value");
        $stmt->bindParam(":name", $key, PDO::PARAM_STR, 50);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);

        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/****************************
 * FUNCTION: GET USER TEAMS *
 ****************************/
function get_user_teams($user_id)
{
	// Open the database connection
	$db = db_open();

	// Query the database
	$stmt = $db->prepare("SELECT teams FROM `user` WHERE `value` = :user_id");
	$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
	$stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

	// Get the list of teams
	$teams = $array['teams'];

	// Close the database connection
	db_close($db);

	return $teams;
}

/***************************
 * FUNCTION: GET RISK TEAM *
 ***************************/
function get_risk_team($risk_id)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT team FROM `risks` WHERE `id` = :risk_id");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetch();

	// If the risk has no team
	if ($array['team'] == 0)
	{
		// Make it viewable to everyone
		$team = "all";
	}
	// Otherwise
	else
	{
        	// Get the team id
        	$team = $array['team'];
	}

        // Close the database connection
        db_close($db);

        return $team;
}

/********************************
 * FUNCTION: EXTRA GRANT ACCESS *
 ********************************/
function extra_grant_access($user_id, $risk_id)
{
	// Subtract 1000 to get the actual ID
	$risk_id = $risk_id - 1000;
    
    // Get Risk By Id

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT owner, manager, team,additional_stakeholders, submitted_by  FROM risks where id=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();
    $risk = $stmt->fetch();

    // Close the database connection
    db_close($db);

    if(get_setting('allow_owner_to_risk')){
        if($risk['owner'] == $user_id){
            return true;
        }
    }
    if(get_setting('allow_ownermanager_to_risk')){
        if($risk['manager'] == $user_id){
            return true;
        }
    }
    if(get_setting('allow_submitter_to_risk')){
        if($risk['submitted_by'] == $user_id){
            return true;
        }
    }
    
    if(get_setting('allow_stakeholder_to_risk')){
        if(in_array($user_id, explode(",", $risk['additional_stakeholders']))){
            return true;
        }
    }
    
    if(get_setting('allow_team_member_to_risk')){
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($user_id);

        // Get the team assigned to the risk
        $risk_team = get_risk_team($risk_id);

        // If the user has access to every team or the risk does not have a team assigned
        if ($user_teams == "all" || $risk_team == "all")
        {
            return true;
        }

        // If the user has access to no teams
        if ($user_teams == "none")
        {
            return false;
        }

        // Pattern is a team id surrounded by colons
        $regex_pattern = "/:" . $risk_team .":/";

        // Check if the risk team is in the user teams
        if (preg_match($regex_pattern, $user_teams))
        {
            return true;
        }
    }
    
	return false;
}

/***********************************
 * FUNCTION: STRIP NO ACCESS RISKS *
 ***********************************/
function strip_no_access_risks($risks)
{
	// Initialize the access array
	$access_array = array();
    // For each risk
    foreach ($risks as $risk)
    {
        if(!isset($risk['id'])){
            continue;
        }
        // Risk ID is the actual ID plus 1000
        $risk_id = $risk['id'] + 1000;

		// If the user should have access to the risk
		if (extra_grant_access($_SESSION['uid'], $risk_id))
		{
			// Add the risk to the access array
			$access_array[] = $risk;
		}
	}

	return $access_array;
}

/***********************************************
 * FUNCTION: STRIP NO ACCESS OPEN RISK SUMMARY *
 ***********************************************/
function strip_no_access_open_risk_summary($veryhigh, $high, $medium, $low, $teams = false)
{
    if($teams !== false){
        if($teams == ""){
            $teams_query = " AND 0 ";
        }else{
            $options = explode(",", $teams);
            $teams_query = generate_or_query($options, 'team', 'b');
            $teams_query = " AND ( {$teams_query} ) ";
        }
    }else{
        $teams_query = "";
    }

	// Open the database connection
    $db = db_open();

	// Query the database
	$stmt = $db->prepare("select a.calculated_risk, CASE WHEN a.calculated_risk >= :veryhigh THEN 'Very High' WHEN a.calculated_risk < :veryhigh AND a.calculated_risk >= :high THEN 'High' WHEN a.calculated_risk < :high AND a.calculated_risk >= :medium THEN 'Medium' WHEN a.calculated_risk < :medium AND a.calculated_risk >= :low THEN 'Low' WHEN a.calculated_risk < :low AND a.calculated_risk >= 0 THEN 'Insignificant' END AS level, b.* from `risk_scoring` a JOIN `risks` b ON a.id = b.id WHERE b.status != \"Closed\" {$teams_query} ORDER BY a.calculated_risk DESC");
	$stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
	$stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
	$stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
	$stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
	$stmt->execute();

	// Store the list in the array
	$risks = $stmt->fetchAll();

        // Initialize the access array
        $access_array = array();

        // For each risk
        foreach ($risks as $risk)
        {
                // Risk ID is the actual ID plus 1000
                $risk_id = $risk['id'] + 1000;

                // If the user should have access to the risk
                if (extra_grant_access($_SESSION['uid'], $risk_id))
                {
                        // Add the risk to the access array
                        $access_array[] = $risk;
                }
        }

        // Close the database connection
        db_close($db);

	// Set the level to empty
	$level = "";
    $level_count = 0;
    
	// Count the number of risks at each level
	foreach ($access_array as $risk)
	{
		// Get the current level
		$current_level = $risk['level'];

		// If the level is not new
		if ($current_level == $level)
		{
			$level_count++;
		}
		else
		{
			// If the level is not empty
			if ($level != "")
			{
				// Add the previous level to the array
				$level_array[] = array('level'=>$level, 'num'=>$level_count);
			}

			// Set the new level and reset the count
			$level = $current_level;
			$level_count = 1;
		}
	}

	// Update the final level
	$level_array[] = array('level'=>$level, 'num'=>$level_count);

	return $level_array;
}

/**************************************
 * FUNCTION: STRIP NO ACCESS RISK PIE *
 **************************************/
function strip_no_access_risk_pie($pie, $teams = false)
{
    if($teams !== false){
        if($teams == ""){
            $teams_query = " AND 0 ";
        }else{
            $options = explode(",", $teams);
            if($pie = "close_reason"){
                $teams_query = generate_or_query($options, 'team', 'c');
            }else{
                $teams_query = generate_or_query($options, 'team', 'a');
            }
            $teams_query = " AND ( {$teams_query} ) ";
        }
    }else{
        $teams_query = "";
    }

    // Open the database connection
    $db = db_open();

	switch($pie)
	{
		case 'status':
			$field = "status";
			$stmt = $db->prepare("SELECT id, status FROM `risks` a WHERE a.status != \"Closed\" {$teams_query} ORDER BY a.status DESC");
			$stmt->execute();
			break;
		case 'location':
			$field = "name";
			$stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `location` b ON a.location = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
			$stmt->execute();
			break;
		case 'source':
			$field = "name";
			$stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `source` b ON a.source = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
			$stmt->execute();
			break;
		case 'category':
			$field = "name";
			$stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `category` b ON a.category = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
			$stmt->execute();
			break;
		case 'team':
			$field = "name";
			$stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `team` b ON a.team = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
			$stmt->execute();
			break;
		case 'technology':
			$field = "name";
			$stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `technology` b ON a.technology = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
			$stmt->execute();
			break;
		case 'owner':
			$field = "name";
			$stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `user` b ON a.owner = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
			$stmt->execute();
			break;
		case 'manager':
			$field = "name";
			$stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `user` b ON a.manager = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
			$stmt->execute();
			break;
		case 'scoring_method':
			$field = "name";
			$stmt = $db->prepare("SELECT a.id, CASE WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS name, COUNT(*) AS num FROM `risks` a LEFT JOIN `risk_scoring` b ON a.id = b.id WHERE status != \"Closed\" {$teams_query} ORDER BY b.scoring_method DESC");
			$stmt->execute();
			break;
		case 'close_reason':
			$field = "name";
			$stmt = $db->prepare("SELECT a.close_reason, a.risk_id as id, b.name, MAX(closure_date) FROM `closures` a JOIN `close_reason` b ON a.close_reason = b.value JOIN `risks` c ON a.risk_id = c.id WHERE c.status = \"Closed\" {$teams_query} GROUP BY risk_id ORDER BY name DESC;");
			$stmt->execute();
			break;
		default:
			break;
	}

        // Store the list in the array
        $risks = $stmt->fetchAll();

        // Initialize the access array
        $access_array = array();

        // For each risk
        foreach ($risks as $risk)
        {
            // Risk ID is the actual ID plus 1000
            $risk_id = $risk['id'] + 1000;

            // If the user should have access to the risk
            if (extra_grant_access($_SESSION['uid'], $risk_id))
            {
                // Add the risk to the access array
                $access_array[] = $risk;
            }
        }

        // Close the database connection
        db_close($db);

        // Set the value to empty
        $value = "";
        $value_count = 0;

        // Count the number of risks for each value
        foreach ($access_array as $risk)
        {
            // Get the current value
            $current_value = $risk[$field];

            // If the value is not new
            if ($current_value == $value)
            {
                $value_count++;
            }
            else
            {
                // If the value is not empty
                if ($value != "")
                {
                        // Add the previous value to the array
                        $value_array[] = array($field=>$value, 'num'=>$value_count);
                }

                // Set the new value and reset the count
                $value = $current_value;
                $value_count = 1;
            }
        }

        // Update the final value
	if ($value == null) $value = "Unassigned";
    $value_array[] = array($field=>$value, 'num'=>$value_count);

    return $value_array;
}

/*************************************
 * FUNCTION: TEAM SEPARATION VERSION *
 *************************************/
function team_separation_version()
{
	// Return the version
	return SEPARATION_EXTRA_VERSION;
}

/*********************************
 * FUNCTION: GET USERS WITH TEAM *
 *********************************/
function get_users_with_team($team)
{
        // Pattern is a team id surrounded by colons
        $team = "%:" . $team .":%";

        // Open the database connection
        $db = db_open();

        // Get the list of all teams
        $stmt = $db->prepare("SELECT username FROM user where teams LIKE :team ORDER BY username");
	$stmt->bindParam(":team", $team, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Store the list in the array
        $users = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

	// Return the users array
	return $users;
}

/******************************************
 * FUNCTION: GET NUMBER OF RISKS FOR TEAM *
 ******************************************/
function get_number_of_risks_for_team($team)
{
        // Open the database connection
        $db = db_open();

        // Get the list of all teams
        $stmt = $db->prepare("SELECT count(*) as count FROM risks WHERE team = :team");
	$stmt->bindParam(":team", $team, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

	// Return the count
	$array = $stmt->fetch();
	return $array['count'];
}

/*************************************
 * FUNCTION: DISPLAY TEAMS AND USERS *
 *************************************/
function display_teams_and_users()
{
	global $escaper;
	global $lang;

        // Open the database connection
        $db = db_open();

	// Get the list of all teams
	$stmt = $db->prepare("SELECT * FROM team ORDER BY name");
	$stmt->execute();

	// Store the list in the array
        $teams = $stmt->fetchAll();

	// For each team
	foreach ($teams as $team)
	{
		// Display the table header
		echo "<table class=\"table table-bordered table-striped table-condensed sortable table-margin-top\">\n";
		echo "<thead>\n";
		echo "<tr>\n";
		echo "<th>" . $escaper->escapeHtml($team['name']) . "</th>\n";
		echo "</tr>\n";
		echo "</thead>\n";
		echo "<tbody>\n";

		// Get the list of users for this team
		$users = get_users_with_team($team['value']);

		// If there are no users
		if (empty($users))
		{
			// Get the number of risks for the team
			$count = get_number_of_risks_for_team($team['value']);
			echo "<tr><td><font color=\"red\"><b>" . $count . " RISK(S) AND NO USERS ASSIGNED TO TEAM</b></font></td></tr>\n";
		}
		else
		{
			// For each user
			foreach ($users as $user)
			{
				echo "<tr><td>" . $escaper->escapeHtml($user['username']) . "</td></tr>\n";
			}
		}

		echo "</tbody>\n";
		echo "</table>\n";
	}

        // Close the database connection
        db_close($db);
}

/*************************************
 * FUNCTION: DISPLAY TEAM SEPARATION *
 *************************************/
function display_team_separation()
{
    global $escaper;
    global $lang;

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . team_separation_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

//	echo "<h3><u>Team Assignments</u></h3>\n";
    display_group_permissions();
//	display_teams_and_users();
}

/***********************************
 * FUNCTION: DISPLAY TEAM PERMISSIONS*
 ***********************************/
function display_group_permissions()
{
    global $escaper;
    global $lang;
    
    echo "<form method=\"POST\">\n";
    echo "<br>";
    echo "<p><input ".(get_setting('allow_owner_to_risk') ? "checked": "")." name=\"allow_owner_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_owner_to_risk\" type=\"checkbox\">  <label for=\"allow_owner_to_risk\">&nbsp;&nbsp; ".$lang['AllowOwnerToSeeRiskDetails']."</label></p>\n";
    echo "<p><input ".(get_setting('allow_ownermanager_to_risk') ? "checked": "")." name=\"allow_ownermanager_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_ownermanager_to_risk\" type=\"checkbox\">  <label for=\"allow_ownermanager_to_risk\">&nbsp;&nbsp; ".$lang['AllowOwnerManagerToSeeRiskDetails']."</label></p>\n";
    echo "<p><input ".(get_setting('allow_submitter_to_risk') ? "checked": "")." name=\"allow_submitter_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_submitter_to_risk\" type=\"checkbox\">  <label for=\"allow_submitter_to_risk\">&nbsp;&nbsp; ".$lang['AllowRiskSubmitterToSeeRiskDetails']."</label></p>\n";
    echo "<p><input ".(get_setting('allow_team_member_to_risk') ? "checked": "")." name=\"allow_team_member_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_team_member_to_risk\" type=\"checkbox\">  <label for=\"allow_team_member_to_risk\">&nbsp;&nbsp; ".$lang['AllowTeamMembersToSeeRiskDetails']."</label></p>\n";
    echo "<p><input ".(get_setting('allow_stakeholder_to_risk') ? "checked": "")." name=\"allow_stakeholder_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_stakeholder_to_risk\" type=\"checkbox\">  <label for=\"allow_stakeholder_to_risk\">&nbsp;&nbsp; ".$lang['AllowAdditionalStakeholdersToSeeRiskDetails']."</label></p>\n";
    echo "<br>";
    echo "<p><input value=\"".$lang['Update']."\" name=\"update_permissions\" type=\"submit\"></p>";
    echo "</form>\n";
}

/***********************************
 * FUNCTION: GET TEAM QUERY STRING *
 ***********************************/
function get_team_query_string($user_teams, $rename = false)
{
    // Create an array based on the colon delimeter
    $teams = explode(":", $user_teams);

    // String starts as empty
    $string = "";

    foreach ($teams as $team)
    {
        // If the team is an integer
        if (is_numeric($team))
        {
		    // If we need to rename the team
		    if ($rename != false)
		    {
			    $string .= $rename . ".team = " . $team . " OR ";
		    }
            // Otherwise append the team to the string
            else $string .= "`team` = ". $team . " OR ";
        }
    }

	// If we need to rename the team
	if ($rename != false)
	{
		// Add a final team can be empty
		$string .= $rename . ".team = \"\"";
	}
    // Add a final team can be empty
    else $string .= "`team` = \"\"";

	// Return the string
	return $string;
}
/************************************
 * FUNCTION: STRIP OPEN CLOSED PIE  *
 ************************************/
function strip_open_closed_pie()
{
//	// Get the teams the user is assigned to
//	$user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
//        if ($user_teams == "all")
//        {
		// Open the database connection
//		$db = db_open();

		// Query the database
//		$stmt = $db->prepare("SELECT id, CASE WHEN status = \"Closed\" THEN 'Closed' WHEN status != \"Closed\" THEN 'Open' END AS name FROM `risks` ORDER BY name");
//		$stmt->execute();

		// Store the list in the array
//		$array = $stmt->fetchAll();

		// Close the database connection
//		db_close($db);
//        }
	// If the user has access to no teams
//	else if ($user_teams == "none")
//	{
		// Return an empty array
//		$array = array();
//	}
	// Otherwise
//	else
//	{
		// Get the team query string
//		$string = get_team_query_string($user_teams);

		// Open the database connection
//		$db = db_open();

		// Query the database
//		$stmt = $db->prepare("SELECT id, CASE WHEN status = \"Closed\" THEN 'Closed' WHEN status != \"Closed\" THEN 'Open' END AS name FROM `risks` WHERE " . $string . " ORDER BY name");
//		$stmt->execute();

		// Store the list in the array
//		$array = $stmt->fetchAll();

		// Close the database connection
//		db_close($db);
//	}

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id, CASE WHEN status = \"Closed\" THEN 'Closed' WHEN status != \"Closed\" THEN 'Open' END AS name FROM `risks` WHERE " . $separation_query . " ORDER BY name");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

	// Return the array
	return $array;
}

/****************************************
 * FUNCTION: STRIP OPEN MITIGATION PIE  *
 ****************************************/
function strip_open_mitigation_pie()
{
//        // Get the teams the user is assigned to
//        $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
//        if ($user_teams == "all")
//        {
                // Open the database connection
//                $db = db_open();

                // Query the database
//		$stmt = $db->prepare("SELECT id, CASE WHEN mitigation_id = 0 THEN 'Unmitigated' WHEN mitigation_id != 0 THEN 'Mitigated' END AS name FROM `risks` WHERE status != \"Closed\" ORDER BY name");
//                $stmt->execute();

                // Store the list in the array
//                $array = $stmt->fetchAll();

                // Close the database connection
//                db_close($db);
//        }
        // If the user has access to no teams
//        else if ($user_teams == "none")
//        {
                // Return an empty array
//                $array = array();
//        }
        // Otherwise
//        else
//        {
                // Get the team query string
//                $string = get_team_query_string($user_teams);

                // Open the database connection
//                $db = db_open();

                // Query the database
//		$stmt = $db->prepare("SELECT id, CASE WHEN mitigation_id = 0 THEN 'Unmitigated' WHEN mitigation_id != 0 THEN 'Mitigated' END AS name FROM `risks` WHERE status != \"Closed\" AND (" . $string . ") ORDER BY name");
//                $stmt->execute();

                // Store the list in the array
//                $array = $stmt->fetchAll();

                // Close the database connection
//                db_close($db);
//        }

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id, CASE WHEN mitigation_id = 0 THEN 'Unmitigated' WHEN mitigation_id != 0 THEN 'Mitigated' END AS name FROM `risks` WHERE status != \"Closed\" AND (" . $separation_query . ") ORDER BY name");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);



    // Return the array
    return $array;
}

/************************************
 * FUNCTION: STRIP OPEN REVIEW PIE  *
 ************************************/
function strip_open_review_pie()
{
//        // Get the teams the user is assigned to
//        $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
//        if ($user_teams == "all")
//        {
                // Open the database connection
//                $db = db_open();

                // Query the database
//		$stmt = $db->prepare("SELECT id, CASE WHEN mgmt_review = 0 THEN 'Unreviewed' WHEN mgmt_review != 0 THEN 'Reviewed' END AS name FROM `risks` WHERE status != \"Closed\" ORDER BY name");
//                $stmt->execute();

                // Store the list in the array
//                $array = $stmt->fetchAll();

                // Close the database connection
//                db_close($db);
//        }
        // If the user has access to no teams
//        else if ($user_teams == "none")
//        {
                // Return an empty array
//                $array = array();
//        }
        // Otherwise
//        else
//        {
                // Get the team query string
//                $string = get_team_query_string($user_teams);

                // Open the database connection
//                $db = db_open();

                // Query the database
//		$stmt = $db->prepare("SELECT id, CASE WHEN mgmt_review = 0 THEN 'Unreviewed' WHEN mgmt_review != 0 THEN 'Reviewed' END AS name FROM `risks` WHERE status != \"Closed\" AND (" . $string . ") ORDER BY name");
//                $stmt->execute();

                // Store the list in the array
//                $array = $stmt->fetchAll();

                // Close the database connection
//                db_close($db);
//        }

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id, CASE WHEN mgmt_review = 0 THEN 'Unreviewed' WHEN mgmt_review != 0 THEN 'Reviewed' END AS name FROM `risks` WHERE status != \"Closed\" AND (" . $separation_query . ") ORDER BY name");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the array
    return $array;
}

/***********************************
 * FUNCTION: STRIP GET OPEN RISKS  *
 ***********************************/
function strip_get_open_risks($teams = false)
{
    if($teams !== false){
        if($teams == ""){
            $teams_query = " AND 0 ";
        }else{
            $options = explode(",", $teams);
            $teams_query = generate_or_query($options, 'team');
            $teams_query = " AND ( {$teams_query} ) ";
        }
    }else{
        $teams_query = "";
    }
    /*
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
        if ($user_teams == "all")
        {
            // Open the database connection
            $db = db_open();

            // Query the database
	$stmt = $db->prepare("SELECT id FROM `risks` WHERE status != \"Closed\"");
            $stmt->execute();

            // Store the list in the array
            $array = $stmt->fetchAll();

            // Close the database connection
            db_close($db);
        }
        // If the user has access to no teams
        else if ($user_teams == "none")
        {
            // Return an empty array
            $array = array();
        }
        // Otherwise
        else
        {
            // Get the team query string
            $string = get_team_query_string($user_teams);

            // Open the database connection
            $db = db_open();

            // Query the database
	$stmt = $db->prepare("SELECT id FROM `risks` WHERE status != \"Closed\" AND (" . $string . ")");
            $stmt->execute();

            // Store the list in the array
            $array = $stmt->fetchAll();

            // Close the database connection
            db_close($db);
        }
    */
    
    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id FROM `risks` WHERE status != \"Closed\" AND (" . $separation_query . ") {$teams_query};");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

   // Return the array
	return $array;
}

/*************************************
 * FUNCTION: STRIP GET CLOSED RISKS  *
 *************************************/
function strip_get_closed_risks($teams = false)
{
    if($teams !== false){
        if($teams == ""){
            $teams_query = " AND 0 ";
        }else{
            $options = explode(",", $teams);
            $teams_query = generate_or_query($options, 'team');
            $teams_query = " AND ( {$teams_query} ) ";
        }
    }else{
        $teams_query = "";
    }
    
    /*
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
        if ($user_teams == "all")
        {
                // Open the database connection
                $db = db_open();

                // Query the database
		$stmt = $db->prepare("SELECT id FROM `risks` WHERE status = \"Closed\" {$teams_query} ");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();

                // Close the database connection
                db_close($db);
        }
        // If the user has access to no teams
        else if ($user_teams == "none")
        {
                // Return an empty array
                $array = array();
        }
        // Otherwise
        else
        {
                // Get the team query string
                $string = get_team_query_string($user_teams);

                // Open the database connection
                $db = db_open();

                // Query the database
		$stmt = $db->prepare("SELECT id FROM `risks` WHERE status = \"Closed\" AND (" . $string . ") {$teams_query}");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();

                // Close the database connection
                db_close($db);
        }
    */
    
    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();
    // Query the database
    $stmt = $db->prepare("SELECT id FROM `risks` WHERE status = \"Closed\" AND (" . $separation_query . ") {$teams_query} ");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

   // Return the array
    return $array;
}    

/******************************************
 * FUNCTION: STRIP GET OPENED RISKS ARRAY *
 ******************************************/
function strip_get_opened_risks_array()
{
//    // Get the teams the user is assigned to
//    $user_teams = get_user_teams($_SESSION['uid']);

    // If the user has access to every team
//    if ($user_teams == "all")
//    {
        // Open the database connection
//        $db = db_open();

        // Query the database
//	    $stmt = $db->prepare("SELECT id, submission_date FROM risks ORDER BY submission_date;");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }
    // If the user has access to no teams
//    else if ($user_teams == "none")
//    {
        // Return an empty array
//        $array = array();
//    }
    // Otherwise
//    else
//    {
        // Get the team query string
//        $string = get_team_query_string($user_teams);

        // Open the database connection
//        $db = db_open();

        // Query the database
//	    $stmt = $db->prepare("SELECT id, submission_date FROM risks WHERE " . $string . " ORDER BY submission_date;");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id, submission_date FROM risks WHERE " . $separation_query . " ORDER BY submission_date;");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the array
    return $array;
}

/******************************************
 * FUNCTION: STRIP GET CLOSED RISKS ARRAY *
 ******************************************/
function strip_get_closed_risks_array()
{
//    // Get the teams the user is assigned to
//    $user_teams = get_user_teams($_SESSION['uid']);

    // If the user has access to every team
//    if ($user_teams == "all")
//    {
        // Open the database connection
//        $db = db_open();

        // Query the database
//$stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' order by closure_date;");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }
    // If the user has access to no teams
//    else if ($user_teams == "none")
//    {
        // Return an empty array
//        $array = array();
//    }
    // Otherwise
//    else
//    {
        // Get the team query string
//        $string = get_team_query_string($user_teams);

        // Open the database connection
//        $db = db_open();

        // Query the database
//$stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' AND (" . $string . ") order by closure_date;");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' AND (" . $separation_query . ") order by closure_date;");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);


    // Return the array
    return $array;
}

/**********************************
 * FUNCTION: GET USER TEAMS QUERY *
 **********************************/
function get_user_teams_query($rename = false, $where = false, $and = false, $onlyTeam = false)
{
    // If this is called from the command line
    if(PHP_SAPI === 'cli'){
        return "";
    }
    
    $orWheres = array();
    if($onlyTeam === false){
        
        if(get_setting('allow_owner_to_risk')){
            if ($rename != false){
                $query = $rename.".owner=".$_SESSION['uid'];
            }else{
                $query = "owner=".$_SESSION['uid'];
            }
            $query = " ({$query}) ";
            array_push($orWheres, $query);
        }
        
        if(get_setting('allow_ownermanager_to_risk')){
            if ($rename != false){
                $query = $rename.".manager=".$_SESSION['uid'];
            }else{
                $query = "manager=".$_SESSION['uid'];
            }
            $query = " ({$query}) ";
            array_push($orWheres, $query);
        }
        
        if(get_setting('allow_submitter_to_risk')){
            if ($rename != false){
                $query = $rename.".submitted_by=".$_SESSION['uid'];
            }else{
                $query = "submitted_by=".$_SESSION['uid'];
            }
            $query = " ({$query}) ";
            array_push($orWheres, $query);
        }
        
        if(get_setting('allow_stakeholder_to_risk')){
            if ($rename != false){
                $query = "FIND_IN_SET({$_SESSION['uid']}, {$rename}.`additional_stakeholders`)";
            }else{
                $query = "FIND_IN_SET({$_SESSION['uid']}, `additional_stakeholders`)";
            }
            $query = " ({$query}) ";
            array_push($orWheres, $query);
        }
    }
    
    if(get_setting('allow_team_member_to_risk')){
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($_SESSION['uid']);
        
        if ($user_teams == "all")
        {
            $user_teams = get_all_teams();
        }

        // Get the team query string
        $query = get_team_query_string($user_teams, $rename);
        $query = " ($query) ";
        array_push($orWheres, $query);
    }
    if(count($orWheres)){
        $string = implode(" OR ", $orWheres);
    }else{
        $string = " 0 ";
    }

	// String with an empty query string
	$query_string = "";

	// If we should have a where clause
	if ($where)
	{
		$query_string .= " WHERE " . $string . " ";
	}
	// If we should have an and clause
	else if ($and)
	{
		$query_string .= " AND (" . $string . ") ";
	}
	// Otherwise just use the string
	else $query_string = $string;

	// Return the query string
	return $query_string;
}

/***************************
 * FUNCTION: GET ALL TEAMS *
 ***************************/
function get_all_teams()
{
	// Open the database connection
	$db = db_open();

	// Query the database
	$stmt = $db->prepare("SELECT GROUP_CONCAT(value) AS value FROM team;");
	$stmt->execute();
	$array = $stmt->fetchAll();
	$string = ":" . str_replace(",", "::", $array[0]['value']) . ":";

	// Close the database connection
	db_close($db);

	// Return the list of teams 
	return $string;
}

?>
