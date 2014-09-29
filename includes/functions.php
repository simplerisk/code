<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/cvss.php'));

// Include the language file
require_once(language_file());

/******************************
 * FUNCTION: DATABASE CONNECT *
 ******************************/
function db_open()
{
        // Connect to the database
        try
        {
                $db = new PDO("mysql:charset=UTF8;dbname=".DB_DATABASE.";host=".DB_HOSTNAME.";port=".DB_PORT,DB_USERNAME,DB_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$db->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES utf8");
		$db->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET CHARACTER SET utf8");

                return $db;
        }
        catch (PDOException $e)
        {
                printf("A fatal error has occurred.  Please contact support.");
                //die("Database Connection Failed: " . $e->getMessage());
        }

        return null;
}

/*********************************
 * FUNCTION: DATABASE DISCONNECT *
 *********************************/
function db_close($db)
{
        // Close the DB connection
        $db = null;
}

/*****************************
 * FUNCTION: STATEMENT DEBUG *
 *****************************/
function statement_debug($stmt)
{
	try
	{
		$stmt->execute();
	}
	catch (PDOException $e)
	{
		echo "ERROR: " . $e->getMessage();
	}
}

/***************************************
 * FUNCTION: GET DATABASE TABLE VALUES *
 ***************************************/
function get_table($name)
{
	// Open the database connection
	$db = db_open();

	// Query the database
	$stmt = $db->prepare("SELECT * FROM `$name` ORDER BY value");
	$stmt->execute();

	// Store the list in the array
	$array = $stmt->fetchAll();

	// Close the database connection
        db_close($db);

	return $array;
}

/***************************************
 * FUNCTION: GET TABLE ORDERED BY NAME *
 ***************************************/
function get_table_ordered_by_name($table_name)
{
        // Open the database connection
        $db = db_open();

	// Create the query statement
	$stmt = $db->prepare("SELECT * FROM `$table_name` ORDER BY name");

        // Execute the database query
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/******************************
 * FUNCTION: GET CUSTOM TABLE *
 ******************************/
function get_custom_table($type)
{
        // Open the database connection
        $db = db_open();

	// Array of CVSS values
	$allowed_cvss_values = array('AccessComplexity', 'AccessVector', 'Authentication', 'AvailabilityRequirement', 'AvailImpact', 'CollateralDamagePotential', 'ConfidentialityRequirement', 'ConfImpact', 'Exploitability', 'IntegImpact', 'IntegrityRequirement', 'RemediationLevel', 'ReportConfidence', 'TargetDistribution');

	// If we want enabled users
	if ($type == "enabled_users")
	{
        	$stmt = $db->prepare("SELECT * FROM user WHERE enabled = 1 ORDER BY name");
	}
	// If we want disabled users
	else if ($type == "disabled_users")
	{
		$stmt = $db->prepare("SELECT * FROM user WHERE enabled = 0 ORDER BY name");
	}
	// If we want a CVSS scoring table
	else if (in_array($type, $allowed_cvss_values))
	{
		$stmt = $db->prepare("SELECT * FROM CVSS_scoring WHERE metric_name = :type ORDER BY id");
		$stmt->bindParam(":type", $type, PDO::PARAM_STR, 30);
	}

	// Execute the database query
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/*****************************
 * FUNCTION: GET RISK LEVELS *
 *****************************/
function get_risk_levels()
{
	// Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT * FROM risk_levels ORDER BY value");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/*******************************
 * FUNCTION: GET REVIEW LEVELS *
 *******************************/
function get_review_levels()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT * FROM review_levels ORDER BY value");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/********************************
 * FUNCTION: UPDATE RISK LEVELS *
 ********************************/
function update_risk_levels($high, $medium, $low)
{
        // Open the database connection
        $db = db_open();
 
        // Update the high risk level
        $stmt = $db->prepare("UPDATE risk_levels SET value=:value WHERE name='High'");
	$stmt->bindParam(":value", $high, PDO::PARAM_STR);
        $stmt->execute();

        // Update the medium risk level
        $stmt = $db->prepare("UPDATE risk_levels SET value=:value WHERE name='Medium'");
        $stmt->bindParam(":value", $medium, PDO::PARAM_STR);
        $stmt->execute();

        // Update the low risk level
        $stmt = $db->prepare("UPDATE risk_levels SET value=:value WHERE name='Low'");
        $stmt->bindParam(":value", $low, PDO::PARAM_STR);
        $stmt->execute();
        
        // Close the database connection
        db_close($db);
        
        return true;
}

/************************************
 * FUNCTION: UPDATE REVIEW SETTINGS *
 ************************************/
function update_review_settings($high, $medium, $low)
{
        // Open the database connection
        $db = db_open();

        // Update the high risk level
        $stmt = $db->prepare("UPDATE review_levels SET value=:value WHERE name='High'");
        $stmt->bindParam(":value", $high, PDO::PARAM_INT);
        $stmt->execute();

        // Update the medium risk level
        $stmt = $db->prepare("UPDATE review_levels SET value=:value WHERE name='Medium'");
        $stmt->bindParam(":value", $medium, PDO::PARAM_INT);
        $stmt->execute();

        // Update the low risk level
        $stmt = $db->prepare("UPDATE review_levels SET value=:value WHERE name='Low'");
        $stmt->bindParam(":value", $low, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/**********************************
 * FUNCTION: CREATE CVSS DROPDOWN *
 **********************************/
function create_cvss_dropdown($name, $selected = NULL, $blank = true)
{
	echo "<select id=\"" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\" name=\"" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\" class=\"form-field\" style=\"width:120px;\" onClick=\"javascript:showHelp('" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "Help');updateScore();\">\n";

        // If the blank is true
        if ($blank == true)
        {
                echo "    <option value=\"\">--</option>\n";
        }

        // Get the list of options
        $options = get_custom_table($name);

        // For each option
        foreach ($options as $option)
        {
		// Create the CVSS metric value
		$value = $option['abrv_metric_value'];

                // If the option is selected
                if ($selected == $value)
                {
                        $text = " selected";
                }
                else $text = "";

                echo "    <option value=\"" . $value . "\"" . $text . ">" . htmlentities($option['metric_value'], ENT_QUOTES, 'UTF-8', false) . "</option>\n";
        }

        echo "  </select>\n";
}

/*************************************
 * FUNCTION: CREATE NUMERIC DROPDOWN *
 *************************************/
function create_numeric_dropdown($name, $selected = NULL, $blank = true)
{
        echo "<select id=\"" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\" name=\"" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\" class=\"form-field\" style=\"width:50px;\" onClick=\"javascript:showHelp('" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "Help');updateScore();\">\n";

        // If the blank is true
        if ($blank == true)
        {
                echo "    <option value=\"\">--</option>\n";
        }

        // For each option
        for ($value=0; $value<=10; $value++)
        {
                // If the option is selected
                if ("$selected" === "$value")
                {
                        $text = " selected";
                }
                else $text = "";

                echo "    <option value=\"" . $value . "\"" . $text . ">" . $value . "</option>\n";
        }

        echo "  </select>\n";
}

/************************************
 * FUNCTION: CREATE SELECT DROPDOWN *
 ************************************/
function create_dropdown($name, $selected = NULL, $rename = NULL, $blank = true, $help = false)
{
	// If we want to update the helper when selected
	if ($help == true)
	{
		$helper = "  onClick=\"javascript:showHelp('" . htmlentities($rename, ENT_QUOTES, 'UTF-8', false) . "Help');updateScore();\"";
	}
	else $helper = "";

	if ($rename != NULL)
	{
		echo "<select id=\"" . htmlentities($rename, ENT_QUOTES, 'UTF-8', false) . "\" name=\"" . htmlentities($rename, ENT_QUOTES, 'UTF-8', false) . "\" class=\"form-field\" style=\"width:auto;\"" . $helper . ">\n";
	}
	else echo "<select id=\"" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\" name=\"" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\" class=\"form-field\" style=\"width:auto;\"" . $helper . ">\n";

	// If the blank is true
	if ($blank == true)
	{
		echo "    <option value=\"\">--</option>\n";
	}

	// If we want a table that should be ordered by name instead of value
	if ($name == "user" || $name == "category" || $name == "team" || $name == "technology" || $name == "location" || $name == "regulation" || $name == "languages" || $name == "projects")
	{
		$options = get_table_ordered_by_name($name);
	}
	// If we want a table of only enabled users
	else if ($name == "enabled_users")
	{
		$options = get_custom_table($name);
	}
	// If we want a table of only disabled users
	else if ($name == "disabled_users")
	{
		$options = get_custom_table($name);
	}
	// Otherwise
	else
	{
        	// Get the list of options
        	$options = get_table($name);
	}

        // For each option
        foreach ($options as $option)
        {
		// If the option is selected
		if ($selected == $option['value'])
		{
			$text = " selected";
		}
		else $text = "";

                echo "    <option value=\"" . $option['value'] . "\"" . $text . ">" . htmlentities(stripslashes($option['name']), ENT_QUOTES, 'UTF-8', false) . "</option>\n";
        }

	echo "  </select>\n";
}

/**************************************
 * FUNCTION: CREATE MULTIPLE DROPDOWN *
 **************************************/
function create_multiple_dropdown($name, $selected = NULL, $rename = NULL, $disabled = NULL)
{
	global $lang;
	
        if ($rename != NULL)
        {
                echo "<select multiple". ($disabled != NULL ? " disabled=\"disabled\"" : "") ." id=\"" . htmlentities($rename, ENT_QUOTES, 'UTF-8', false) . "\" name=\"" . htmlentities($rename, ENT_QUOTES, 'UTF-8', false) . "[]\">\n";
        }
        else echo "<select multiple". ($disabled != NULL ? " disabled=\"disabled\"" : "") ." id=\"" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\" name=\"" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "[]\">\n";

	// Create all or none options
	echo "    <option value=\"all\">". $lang['ALL'] ."</option>\n";
	echo "    <option value=\"none\">". $lang['NONE'] ."</option>\n";

        // Get the list of options
        $options = get_table($name);

        // For each option
        foreach ($options as $option)
        {
		// Pattern is a team id surrounded by colons
		$regex_pattern = "/:" . $option['value'] .":/";

                // If the user belongs to the team or all was selected
                if (preg_match($regex_pattern, $selected, $matches) || $selected == "all")
                {
                        $text = " selected";
                }
                else $text = "";

                echo "    <option value=\"" . $option['value'] . "\"" . $text . ">" . htmlentities($option['name'], ENT_QUOTES, 'UTF-8', false) . "</option>\n";
        }

        echo "  </select>\n";
}

/*******************************
 * FUNCTION: CREATE RISK TABLE *
 *******************************/
function create_risk_table()
{
	global $lang;
	
	$impacts = get_table("impact");
	$likelihoods = get_table("likelihood");

	// Create legend table
	echo "<table>\n";
	echo "<tr height=\"20px\">\n";
	echo "<td><div style=\"width:10px;height:10px;border:1px solid #000;background-color:red;\" /></td>\n";
	echo "<td>". $lang['HighRisk'] ."</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "<td><div style=\"width:10px;height:10px;border:1px solid #000;background-color:orange;\" /></td>\n";
	echo "<td>". $lang['MediumRisk'] ."</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<td><div style=\"width:10px;height:10px;border:1px solid #000;background-color:yellow;\" /></td>\n";
        echo "<td>". $lang['LowRisk'] ."</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<td><div style=\"width:10px;height:10px;border:1px solid #000;background-color:white;\" /></td>\n";
        echo "<td>". $lang['Irrelevant'] ."</td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "<br />\n";

	echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"10\">\n";

	// For each impact level
	for ($i=4; $i>=0; $i--)
	{
		echo "<tr>\n";
	
		// If this is the first row add the y-axis label
		if ($i == 4)
		{
			echo "<td rowspan=\"5\"><div class=\"text-rotation\"><b>". $lang['Impact'] ."</b></div></td>\n";
		}

		// Add the y-axis values
        	echo "<td bgcolor=\"silver\" height=\"50px\" width=\"100px\">" . htmlentities($impacts[$i]['name'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
        	echo "<td bgcolor=\"silver\" align=\"center\" height=\"50px\" width=\"100px\">" . $impacts[$i]['value'] . "</td>\n";

		// For each likelihood level
		for ($j=0; $j<=4; $j++)
		{
			// Calculate risk
			$risk = calculate_risk($impacts[$i]['value'], $likelihoods[$j]['value']);

			// Get the risk color
			$color = get_risk_color($risk);

			echo "<td align=\"center\" bgcolor=\"" . $color . "\" height=\"50px\" width=\"100px\">" . $risk . "</td>\n";
		}

		echo "</tr>\n";
	}

        echo "<tr>\n";
	echo "<td>&nbsp;</td>\n";
	echo "<td>&nbsp;</td>\n";
	echo "<td>&nbsp;</td>\n";

	// Add the x-axis values
	for ($x=0; $x<=4; $x++)
	{
		echo "<td align=\"center\" bgcolor=\"silver\" height=\"50px\" width=\"100px\">" . $likelihoods[$x]['value'] . "<br />" . htmlentities($likelihoods[$x]['name'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
	}

	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td>&nbsp;</td>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<td>&nbsp;</td>\n";
	echo "<td colspan=\"5\" align=\"center\"><b>". $lang['Likelihood'] ."</b></td>\n";
	echo "</tr>\n";
	echo "</table>\n";
}

/****************************
 * FUNCTION: CALCULATE RISK *
 ****************************/
function calculate_risk($impact, $likelihood)
{
	// If the impact or likelihood are not a 1 to 5 value
	if (preg_match("/^[1-5]$/", $impact) && preg_match("/^[1-5]$/", $likelihood))
	{
		// Get risk_model
		$risk_model = get_setting("risk_model");

		// Pick the risk formula
		if ($risk_model == 1)
		{
			$max_risk = 35;
			$risk = ($likelihood * $impact) + (2 * $impact);
		}
		else if ($risk_model == 2)
		{
			$max_risk = 30;
			$risk = ($likelihood * $impact) + $impact;
		}
        	else if ($risk_model == 3)
        	{
			$max_risk = 25;
                	$risk = $likelihood * $impact;
        	}
        	else if ($risk_model == 4)
        	{
			$max_risk = 30;
                	$risk = ($likelihood * $impact) + $likelihood;
        	}
        	else if ($risk_model == 5)
        	{
			$max_risk = 35;
                	$risk = ($likelihood * $impact) + (2 * $likelihood);
        	}

		// This puts it on a 1 to 10 scale similar to CVSS
		$risk = round($risk * (10 / $max_risk), 1);
	}
	// If the impact or likelihood were not specified risk is 10
	else $risk = 10;

	return $risk;
}

/****************************
 * FUNCTION: GET RISK COLOR *
 ****************************/
function get_risk_color($risk)
{
        // Open the database connection
        $db = db_open();

        // Get the risk levels
        $stmt = $db->prepare("SELECT name FROM risk_levels WHERE value<=$risk ORDER BY value DESC LIMIT 1");
        $stmt->execute();

	// Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

	// Find the color
	if ($array[0]['name'] == "High")
	{
		$color = "red";
	}
	else if ($array[0]['name'] == "Medium")
	{
		$color = "orange";
	}
	else if ($array[0]['name'] == "Low")
	{
		$color = "yellow";
	}
	else $color = "white";

        return $color;
}

/*********************************
 * FUNCTION: GET RISK LEVEL NAME *
 *********************************/
function get_risk_level_name($risk)
{
	// If the risk is not null
	if ($risk != "")
	{
        	// Open the database connection
        	$db = db_open();
        	// Get the risk levels
        	$stmt = $db->prepare("SELECT name FROM risk_levels WHERE value<=$risk ORDER BY value DESC LIMIT 1");
        	$stmt->execute();

        	// Store the list in the array
        	$array = $stmt->fetchAll();

        	// Close the database connection
        	db_close($db);

		// If the risk is High, Medium, or Low
		if ($array[0]['name'] != "")
		{
			return $array[0]['name'];
		}
		// Otherwise the risk is Irrelevant
		else return "Irrelevant";
	}
	// Return a null value
	return "";
}

/*******************************
 * FUNCTION: UPDATE RISK MODEL *
 *******************************/
function update_risk_model($risk_model)
{
        // Open the database connection
        $db = db_open();

        // Get the risk levels
        $stmt = $db->prepare("UPDATE settings SET value=:risk_model WHERE name='risk_model'");
	$stmt->bindParam(":risk_model", $risk_model, PDO::PARAM_INT);
        $stmt->execute();

	// Get the list of all risks using the classic formula
	$stmt = $db->prepare("SELECT id, calculated_risk, CLASSIC_likelihood, CLASSIC_impact FROM risk_scoring WHERE scoring_method = 1");
	$stmt->execute();

        // Store the list in the risks array
        $risks = $stmt->fetchAll();

	// For each risk using the classic formula
	foreach ($risks as $risk)
	{
		$likelihood = $risk['CLASSIC_likelihood'];
		$impact = $risk['CLASSIC_impact'];

                // Calculate the risk via classic method
                $calculated_risk = calculate_risk($impact, $likelihood);

		// If the calculated risk is different than what is in the DB
		if ($calculated_risk != $risk['calculated_risk'])
		{
			// Update the value in the DB
			$stmt = $db->prepare("UPDATE risk_scoring SET calculated_risk = :calculated_risk WHERE id = :id");
			$stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_INT);
			$stmt->bindParam(":id", $risk['id'], PDO::PARAM_INT);
			$stmt->execute();
		}
	}

        // Close the database connection
        db_close($db);

	return true;
}

/***********************************
 * FUNCTION: CHANGE SCORING METHOD *
 ***********************************/
function change_scoring_method($risk_id, $scoring_method)
{
        // Subtract 1000 from the risk_id
        $id = $risk_id - 1000;

        // Open the database connection
        $db = db_open();

	// Update the scoring method for the given risk ID
	$stmt = $db->prepare("UPDATE risk_scoring SET scoring_method = :scoring_method WHERE id = :id");
	$stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
	$stmt->bindParam(":id", $id, PDO::PARAM_INT);
	$stmt->execute();

        // Close the database connection
        db_close($db);

	// Return the new scoring method
	return $scoring_method;
}

/**************************
 * FUNCTION: UPDATE TABLE *
 **************************/
function update_table($table, $name, $value)
{
        // Open the database connection
        $db = db_open();

        // Get the risk levels
        $stmt = $db->prepare("UPDATE $table SET name=:name WHERE value=:value");
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, 20);
	$stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/*************************
 * FUNCTION: GET SETTING *
 *************************/
function get_setting($setting)
{
        // Open the database connection
        $db = db_open();

        // Get the risk levels
        $stmt = $db->prepare("SELECT * FROM settings where name=:setting");
        $stmt->bindParam(":setting", $setting);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

	$value = $array[0]['value'];

	return $value;
}

/***************************
 * FUNCTION: INSERT STRING *
 ***************************/
function add_name($table, $name, $size=20)
{
        // Open the database connection
        $db = db_open();

        // Get the risk levels
        $stmt = $db->prepare("INSERT INTO $table (`name`) VALUES (:name)");
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, $size);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/**************************
 * FUNCTION: DELETE VALUE *
 **************************/
function delete_value($table, $value)
{
        // Open the database connection
        $db = db_open();

        // Delete the table value
        $stmt = $db->prepare("DELETE FROM $table WHERE value=:value");
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/*************************
 * FUNCTION: ENABLE USER *
 *************************/
function enable_user($value)
{
        // Open the database connection
        $db = db_open();

        // Set enabled = 1 for the user
        $stmt = $db->prepare("UPDATE user SET enabled = 1 WHERE value=:value");
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/**************************
 * FUNCTION: DISABLE USER *
 **************************/
function disable_user($value)
{
        // Open the database connection
        $db = db_open();
        
        // Set enabled = 0 for the user
        $stmt = $db->prepare("UPDATE user SET enabled = 0 WHERE value=:value");
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/*****************************
 * FUNCTION: DOES USER EXIST *
 *****************************/
function user_exist($user)
{
        // Open the database connection
        $db = db_open();

        // Find the user
	$stmt = $db->prepare("SELECT * FROM user WHERE name=:user");
	$stmt->bindParam(":user", $user, PDO::PARAM_STR, 20);

        $stmt->execute();

	// Fetch the array
	$array = $stmt->fetchAll();

	// If the array is empty
	if (empty($array))
	{
		$return = false;
	}
	else $return = true;

        // Close the database connection
        db_close($db);

        return $return;
}

/**********************
 * FUNCTION: ADD USER *
 **********************/
function add_user($type, $user, $email, $name, $salt, $hash, $teams, $admin, $review_high, $review_medium, $review_low, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor)
{
        // Open the database connection
        $db = db_open();

        // Insert the new user
        $stmt = $db->prepare("INSERT INTO user (`type`, `username`, `name`, `email`, `salt`, `password`, `teams`, `admin`, `review_high`, `review_medium`, `review_low`, `submit_risks`, `modify_risks`, `plan_mitigations`, `close_risks`, `multi_factor`) VALUES (:type, :user, :name, :email, :salt, :hash, :teams, :admin, :review_high, :review_medium, :review_low, :submit_risks, :modify_risks, :plan_mitigations, :close_risks, :multi_factor)");
	$stmt->bindParam(":type", $type, PDO::PARAM_STR, 20);
	$stmt->bindParam(":user", $user, PDO::PARAM_STR, 20);
	$stmt->bindParam(":name", $name, PDO::PARAM_STR, 50);
	$stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
	$stmt->bindParam(":salt", $salt, PDO::PARAM_STR, 20);
	$stmt->bindParam(":hash", $hash, PDO::PARAM_STR, 60);
	$stmt->bindParam(":teams", $teams, PDO::PARAM_STR, 200);
        $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
	$stmt->bindParam(":review_high", $review_high, PDO::PARAM_INT);
	$stmt->bindParam(":review_medium", $review_medium, PDO::PARAM_INT);
	$stmt->bindParam(":review_low", $review_low, PDO::PARAM_INT);
	$stmt->bindParam(":submit_risks", $submit_risks, PDO::PARAM_INT);
	$stmt->bindParam(":modify_risks", $modify_risks, PDO::PARAM_INT);
	$stmt->bindParam(":plan_mitigations", $plan_mitigations, PDO::PARAM_INT);
	$stmt->bindParam(":close_risks", $close_risks, PDO::PARAM_INT);
	$stmt->bindParam(":multi_factor", $multi_factor, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/*************************
 * FUNCTION: UPDATE USER *
 *************************/
function update_user($user_id, $name, $email, $teams, $lang, $admin, $review_high, $review_medium, $review_low, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor)
{
        // If the language is empty
        if ($lang == "")
        {
                // Set the value to null
                $lang = NULL;
        }

        // Open the database connection
        $db = db_open();

        // Update the user
        $stmt = $db->prepare("UPDATE user set `name`=:name, `email`=:email, `teams`=:teams, `lang` =:lang, `admin`=:admin, `review_high`=:review_high, `review_medium`=:review_medium, `review_low`=:review_low, `submit_risks`=:submit_risks, `modify_risks`=:modify_risks, `plan_mitigations`=:plan_mitigations, `close_risks`=:close_risks, `multi_factor`=:multi_factor WHERE `value`=:user_id");
	$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, 50);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
	$stmt->bindParam(":teams", $teams, PDO::PARAM_STR, 200);
	$stmt->bindParam(":lang", $lang, PDO::PARAM_STR, 2);
        $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
        $stmt->bindParam(":review_high", $review_high, PDO::PARAM_INT);
        $stmt->bindParam(":review_medium", $review_medium, PDO::PARAM_INT);
        $stmt->bindParam(":review_low", $review_low, PDO::PARAM_INT);
        $stmt->bindParam(":submit_risks", $submit_risks, PDO::PARAM_INT);
        $stmt->bindParam(":modify_risks", $modify_risks, PDO::PARAM_INT);
        $stmt->bindParam(":plan_mitigations", $plan_mitigations, PDO::PARAM_INT);
	$stmt->bindParam(":close_risks", $close_risks, PDO::PARAM_INT);
	$stmt->bindParam(":multi_factor", $multi_factor, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

	// If the update affects the current logged in user
	if ($_SESSION['uid'] == $user_id)
	{
		// Update the session values
        	$_SESSION['admin'] = (int)$admin;
        	$_SESSION['review_high'] = (int)$review_high;
        	$_SESSION['review_medium'] = (int)$review_medium;
        	$_SESSION['review_low'] = (int)$review_low;
        	$_SESSION['submit_risks'] = (int)$submit_risks;
        	$_SESSION['modify_risks'] = (int)$modify_risks;
        	$_SESSION['close_risks'] = (int)$close_risks;
        	$_SESSION['plan_mitigations'] = (int)$plan_mitigations;
		$_SESSION['lang'] = $lang;
	}

        return true;
}

/****************************
 * FUNCTION: GET USER BY ID *
 ****************************/
function get_user_by_id($id)
{
	// Open the database connection
	$db = db_open();

	// Get the user information
	$stmt = $db->prepare("SELECT * FROM user WHERE value = :value");
	$stmt->bindParam(":value", $id, PDO::PARAM_INT);
	$stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

	// Close the database connection
	db_close($db);

	return $array[0];
}

/*******************************
 * FUNCTION: GET VALUE BY NAME *
 *******************************/
function get_value_by_name($table, $name)
{
        // Open the database connection
        $db = db_open();

        // Get the user information
        $stmt = $db->prepare("SELECT value FROM $table WHERE name = :name");
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

	// If the array is empty
	if (empty($array))
	{
		// Return null
		return null;
	}
	// Otherwise, return the first value in the array
        else return $array[0]['value'];
}

/*****************************
 * FUNCTION: UPDATE PASSWORD *
 *****************************/
function update_password($user, $hash)
{
        // Open the database connection
        $db = db_open();

        // Update password
        $stmt = $db->prepare("UPDATE user SET password=:hash WHERE username=:user");
	$stmt->bindParam(":user", $user, PDO::PARAM_STR, 20);
	$stmt->bindParam(":hash", $hash, PDO::PARAM_STR, 60);
	$stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/*************************
 * FUNCTION: SUBMIT RISK *
 *************************/
function submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes)
{
        // Open the database connection
        $db = db_open();

        // Add the risk
        $stmt = $db->prepare("INSERT INTO risks (`status`, `subject`, `reference_id`, `regulation`, `control_number`, `location`, `category`, `team`, `technology`, `owner`, `manager`, `assessment`, `notes`, `submitted_by`) VALUES (:status, :subject, :reference_id, :regulation, :control_number, :location, :category, :team, :technology, :owner, :manager, :assessment, :notes, :submitted_by)");
	$stmt->bindParam(":status", $status, PDO::PARAM_STR, 10);
        $stmt->bindParam(":subject", $subject, PDO::PARAM_STR, 100);
	$stmt->bindParam(":reference_id", $reference_id, PDO::PARAM_STR, 20);
	$stmt->bindParam(":regulation", $regulation, PDO::PARAM_INT);
	$stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR, 20);
	$stmt->bindParam(":location", $location, PDO::PARAM_INT);
	$stmt->bindParam(":category", $category, PDO::PARAM_INT);
	$stmt->bindParam(":team", $team, PDO::PARAM_INT);
	$stmt->bindParam(":technology", $technology, PDO::PARAM_INT);
	$stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
	$stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
	$stmt->bindParam(":assessment", $assessment, PDO::PARAM_STR);
	$stmt->bindParam(":notes", $notes, PDO::PARAM_STR);
	$stmt->bindParam(":submitted_by", $_SESSION['uid'], PDO::PARAM_INT);
        $stmt->execute();

	// Get the id of the risk
	$last_insert_id = $db->lastInsertId();

        // Close the database connection
        db_close($db);

        return $last_insert_id;
}

/************************************
 * FUNCTION: GET_CVSS_NUMERIC_VALUE *
 ************************************/
function get_cvss_numeric_value($abrv_metric_name, $abrv_metric_value)
{
        // Open the database connection
        $db = db_open();

	// Find the numeric value for the submitted metric
	$stmt = $db->prepare("SELECT numeric_value FROM CVSS_scoring WHERE abrv_metric_name = :abrv_metric_name AND abrv_metric_value = :abrv_metric_value");
	$stmt->bindParam(":abrv_metric_name", $abrv_metric_name, PDO::PARAM_STR, 3);
	$stmt->bindParam(":abrv_metric_value", $abrv_metric_value, PDO::PARAM_STR, 3);
	$stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
	
        // Close the database connection
        db_close($db);

	// Return the numeric value found
	return $array[0]['numeric_value'];
}

/*********************************
 * FUNCTION: SUBMIT RISK SCORING *
 *********************************/
function submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkill, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPDiscovery, $OWASPExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom)
{
	// Open the database connection
        $db = db_open();

	// If the scoring method is Classic (1)
	if ($scoring_method == 1)
	{
        	// Calculate the risk via classic method
        	$calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

        	// Create the database query
		$stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact`) VALUES (:last_insert_id, :scoring_method, :calculated_risk, :CLASSIC_likelihood, :CLASSIC_impact)");
        	$stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
        	$stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        	$stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        	$stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
        	$stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
	}
	// If the scoring method is CVSS (2)
	else if ($scoring_method == 2)
	{
        	// Get the numeric values for the CVSS submission
		$AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
                $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
                $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
                $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
                $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
                $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
                $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
                $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
                $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
                $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
                $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
                $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
                $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
                $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

		// Calculate the risk via CVSS method
	        $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);

        	// Create the database query
		$stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `CVSS_AccessVector`, `CVSS_AccessComplexity`, `CVSS_Authentication`, `CVSS_ConfImpact`, `CVSS_IntegImpact`, `CVSS_AvailImpact`, `CVSS_Exploitability`, `CVSS_RemediationLevel`, `CVSS_ReportConfidence`, `CVSS_CollateralDamagePotential`, `CVSS_TargetDistribution`, `CVSS_ConfidentialityRequirement`, `CVSS_IntegrityRequirement`, `CVSS_AvailabilityRequirement`) VALUES (:last_insert_id, :scoring_method, :calculated_risk, :CVSS_AccessVector, :CVSS_AccessComplexity, :CVSS_Authentication, :CVSS_ConfImpact, :CVSS_IntegImpact, :CVSS_AvailImpact, :CVSS_Exploitability, :CVSS_RemediationLevel, :CVSS_ReportConfidence, :CVSS_CollateralDamagePotential, :CVSS_TargetDistribution, :CVSS_ConfidentialityRequirement, :CVSS_IntegrityRequirement, :CVSS_AvailabilityRequirement)");
        	$stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
        	$stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        	$stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        	$stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
        	$stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
	}
	// If the scoring method is DREAD (3)
	else if ($scoring_method == 3)
	{
		// Calculate the risk via DREAD method
		$calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

		// Create the database query
		$stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `DREAD_DamagePotential`, `DREAD_Reproducibility`, `DREAD_Exploitability`, `DREAD_AffectedUsers`, `DREAD_Discoverability`) VALUES (:last_insert_id, :scoring_method, :calculated_risk, :DREAD_DamagePotential, :DREAD_Reproducibility, :DREAD_Exploitability, :DREAD_AffectedUsers, :DREAD_Discoverability)");
        	$stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
        	$stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        	$stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        	$stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
        	$stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
        	$stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
        	$stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
        	$stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
	}
	// If the scoring method is OWASP (4)
	else if ($scoring_method == 4)
        {
		$threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
		$vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

		// Average the threat agent and vulnerability factors to get the likelihood
		$OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

		$technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
		$business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

		// Average the technical and business impacts to get the impact
		$OWASP_impact = ($technical_impact + $business_impact)/2;

                // Calculate the overall OWASP risk score
		$calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

                // Create the database query
                $stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `OWASP_SkillLevel`, `OWASP_Motive`, `OWASP_Opportunity`, `OWASP_Size`, `OWASP_EaseOfDiscovery`, `OWASP_EaseOfExploit`, `OWASP_Awareness`, `OWASP_IntrusionDetection`, `OWASP_LossOfConfidentiality`, `OWASP_LossOfIntegrity`, `OWASP_LossOfAvailability`, `OWASP_LossOfAccountability`, `OWASP_FinancialDamage`, `OWASP_ReputationDamage`, `OWASP_NonCompliance`, `OWASP_PrivacyViolation`) VALUES (:last_insert_id, :scoring_method, :calculated_risk, :OWASP_SkillLevel, :OWASP_Motive, :OWASP_Opportunity, :OWASP_Size, :OWASP_EaseOfDiscovery, :OWASP_EaseOfExploit, :OWASP_Awareness, :OWASP_IntrusionDetection, :OWASP_LossOfConfidentiality, :OWASP_LossOfIntegrity, :OWASP_LossOfAvailability, :OWASP_LossOfAccountability, :OWASP_FinancialDamage, :OWASP_ReputationDamage, :OWASP_NonCompliance, :OWASP_PrivacyViolation)");
                $stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
                $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
                $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        	$stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
        	$stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
	}
	// If the scoring method is Custom (5)
	else if ($scoring_method == 5)
        {
		// If the custom value is not between 0 and 10
		if (!(($custom >= 0) && ($custom <= 10)))
		{
			// Set the custom value to 10
			$custom = 10;
		}

		// Calculated risk is the custom value
		$calculated_risk = $custom;

                // Create the database query
		$stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `Custom`) VALUES (:last_insert_id, :scoring_method, :calculated_risk, :Custom)");
                $stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
                $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
                $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
		$stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
	}
	// Otherwise
	else
	{
		return false;
	}

        // Add the risk score
	$stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/**********************************
 * FUNCTION: UPDATE CLASSIC SCORE *
 **********************************/
function update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact)
{
        // Subtract 1000 from the id
        $id = $id - 1000;

        // Open the database connection
        $db = db_open();

        // Calculate the risk via classic method
        $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

        // Create the database query
        $stmt = $db->prepare("UPDATE risk_scoring SET calculated_risk=:calculated_risk, CLASSIC_likelihood=:CLASSIC_likelihood, CLASSIC_impact=:CLASSIC_impact WHERE id=:id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
        $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);

        // Add the risk score
        $stmt->execute();

        // Audit log
        $risk_id = $id + 1000;
        $message = "Risk scoring for risk ID \"" . $risk_id . "\" was updated by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);

        $alert = true;
        $alert_message = "Risk scoring was updated successfully.";

        // Close the database connection
        db_close($db);

        return $calculated_risk;
}

/*******************************
 * FUNCTION: UPDATE CVSS SCORE *
 *******************************/
function update_cvss_score($id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement)
{
        // Subtract 1000 from the id
        $id = $id - 1000;

        // Open the database connection
        $db = db_open();

        // Get the numeric values for the CVSS submission
        $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
        $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
        $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
        $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
        $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
        $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
        $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
        $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
        $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
        $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
        $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
        $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
        $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
        $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

        // Calculate the risk via CVSS method
        $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);

        // Create the database query
        $stmt = $db->prepare("UPDATE risk_scoring SET calculated_risk=:calculated_risk, CVSS_AccessVector=:CVSS_AccessVector, CVSS_AccessComplexity=:CVSS_AccessComplexity, CVSS_Authentication=:CVSS_Authentication, CVSS_ConfImpact=:CVSS_ConfImpact, CVSS_IntegImpact=:CVSS_IntegImpact, CVSS_AvailImpact=:CVSS_AvailImpact, CVSS_Exploitability=:CVSS_Exploitability, CVSS_RemediationLevel=:CVSS_RemediationLevel, CVSS_ReportConfidence=:CVSS_ReportConfidence, CVSS_CollateralDamagePotential=:CVSS_CollateralDamagePotential, CVSS_TargetDistribution=:CVSS_TargetDistribution, CVSS_ConfidentialityRequirement=:CVSS_ConfidentialityRequirement, CVSS_IntegrityRequirement=:CVSS_IntegrityRequirement, CVSS_AvailabilityRequirement=:CVSS_AvailabilityRequirement WHERE id=:id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);

        // Add the risk score
        $stmt->execute();

        // Audit log    
        $risk_id = $id + 1000;
        $message = "Risk scoring for risk ID \"" . $risk_id . "\" was updated by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);

        $alert = true;  
        $alert_message = "Risk scoring was updated successfully.";

        // Close the database connection
        db_close($db);

        return $calculated_risk;
}

/********************************
 * FUNCTION: UPDATE DREAD SCORE *
 ********************************/
function update_dread_score($id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability)
{
        // Subtract 1000 from the id
        $id = $id - 1000;

        // Open the database connection
        $db = db_open();

        // Calculate the risk via DREAD method
        $calculated_risk = ($DREADDamagePotential + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

        // Create the database query
        $stmt = $db->prepare("UPDATE risk_scoring SET calculated_risk=:calculated_risk, DREAD_DamagePotential=:DREAD_DamagePotential, DREAD_Reproducibility=:DREAD_Reproducibility, DREAD_Exploitability=:DREAD_Exploitability, DREAD_AffectedUsers=:DREAD_AffectedUsers, DREAD_Discoverability=:DREAD_Discoverability WHERE id=:id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":DREAD_DamagePotential", $DREADDamagePotential, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);

        // Add the risk score
        $stmt->execute();

        // Audit log    
        $risk_id = $id + 1000;
        $message = "Risk scoring for risk ID \"" . $risk_id . "\" was updated by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);

        $alert = true;  
        $alert_message = "Risk scoring was updated successfully.";

        // Close the database connection
        db_close($db);

        return $calculated_risk;
}

/********************************
 * FUNCTION: UPDATE OWASP SCORE *
 ********************************/
function update_owasp_score($id, $OWASPSkill, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPDiscovery, $OWASPExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation)
{
        // Subtract 1000 from the id
        $id = $id - 1000;

        // Open the database connection
        $db = db_open();

        $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
        $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

        // Average the threat agent and vulnerability factors to get the likelihood
        $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

        $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
        $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

        // Average the technical and business impacts to get the impact
        $OWASP_impact = ($technical_impact + $business_impact)/2;

        // Calculate the overall OWASP risk score
        $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

        // Create the database query
        $stmt = $db->prepare("UPDATE risk_scoring SET calculated_risk=:calculated_risk, OWASP_SkillLevel=:OWASP_SkillLevel, OWASP_Motive=:OWASP_Motive, OWASP_Opportunity=:OWASP_Opportunity, OWASP_Size=:OWASP_Size, OWASP_EaseOfDiscovery=:OWASP_EaseOfDiscovery, OWASP_EaseOfExploit=:OWASP_EaseOfExploit, OWASP_Awareness=:OWASP_Awareness, OWASP_IntrusionDetection=:OWASP_IntrusionDetection, OWASP_LossOfConfidentiality=:OWASP_LossOfConfidentiality, OWASP_LossOfIntegrity=:OWASP_LossOfIntegrity, OWASP_LossOfAvailability=:OWASP_LossOfAvailability, OWASP_LossOfAccountability=:OWASP_LossOfAccountability, OWASP_FinancialDamage=:OWASP_FinancialDamage, OWASP_ReputationDamage=:OWASP_ReputationDamage, OWASP_NonCompliance=:OWASP_NonCompliance, OWASP_PrivacyViolation=:OWASP_PrivacyViolation WHERE id=:id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);

        // Add the risk score
        $stmt->execute();

        // Audit log    
        $risk_id = $id + 1000;
        $message = "Risk scoring for risk ID \"" . $risk_id . "\" was updated by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);

        $alert = true;  
        $alert_message = "Risk scoring was updated successfully.";

        // Close the database connection
        db_close($db);

        return $calculated_risk;
}

/*********************************
 * FUNCTION: UPDATE CUSTOM SCORE *
 *********************************/
function update_custom_score($id, $custom)
{
        // Subtract 1000 from the id
        $id = $id - 1000;

        // Open the database connection
        $db = db_open();

        // If the custom value is not between 0 and 10
        if (!(($custom >= 0) && ($custom <= 10)))
        {
        	// Set the custom value to 10
                $custom = 10;
        }

        // Calculated risk is the custom value
        $calculated_risk = $custom;

        // Create the database query
        $stmt = $db->prepare("UPDATE risk_scoring SET calculated_risk=:calculated_risk, Custom=:Custom WHERE id=:id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR, 5);
        $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);

        // Add the risk score
        $stmt->execute();

        // Audit log    
        $risk_id = $id + 1000;
        $message = "Risk scoring for risk ID \"" . $risk_id . "\" was updated by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);

        $alert = true;  
        $alert_message = "Risk scoring was updated successfully.";

        // Close the database connection
        db_close($db);

        return $calculated_risk;
}

/*********************************
 * FUNCTION: UPDATE RISK SCORING *
 *********************************/
function update_risk_scoring($id, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkill, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPDiscovery, $OWASPExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom)
{
        // Subtract 1000 from the id
        $id = $id - 1000;

        // Open the database connection
        $db = db_open();

        // If the scoring method is Classic (1)
        if ($scoring_method == 1)
        {
                // Calculate the risk via classic method
                $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

                // Create the database query
		$stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CLASSIC_likelihood=:CLASSIC_likelihood, CLASSIC_impact=:CLASSIC_impact WHERE id=:id");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
                $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
                $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
                $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
        }
        // If the scoring method is CVSS (2)
        else if ($scoring_method == 2)
        {
                // Get the numeric values for the CVSS submission
                $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
                $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
                $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
                $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
                $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
                $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
                $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
                $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
                $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
                $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
                $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
                $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
                $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
                $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

                // Calculate the risk via CVSS method
                $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);

                // Create the database query
		$stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CVSS_AccessVector=:CVSS_AccessVector, CVSS_AccessComplexity=:CVSS_AccessComplexity, CVSS_Authentication=:CVSS_Authentication, CVSS_ConfImpact=:CVSS_ConfImpact, CVSS_IntegImpact=:CVSS_IntegImpact, CVSS_AvailImpact=:CVSS_AvailImpact, CVSS_Exploitability=:CVSS_Exploitability, CVSS_RemediationLevel=:CVSS_RemediationLevel, CVSS_ReportConfidence=:CVSS_ReportConfidence, CVSS_CollateralDamagePotential=:CVSS_CollateralDamagePotential, CVSS_TargetDistribution=:CVSS_TargetDistribution, CVSS_ConfidentialityRequirement=:CVSS_ConfidentialityRequirement, CVSS_IntegrityRequirement=:CVSS_IntegrityRequirement, CVSS_AvailabilityRequirement=:CVSS_AvailabilityRequirement WHERE id=:id");
		$stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
                $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
                $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
                $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
        }
        // If the scoring method is DREAD (3)
        else if ($scoring_method == 3)
        {
                // Calculate the risk via DREAD method
                $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

                // Create the database query
		$stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, DREAD_DamagePotential=:DREAD_DamagePotential, DREAD_Reproducibility=:DREAD_Reproducibility, DREAD_Exploitability=:DREAD_Exploitability, DREAD_AffectedUsers=:DREAD_AffectedUsers, DREAD_Discoverability=:DREAD_Discoverability WHERE id=:id");
		$stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
                $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
                $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
                $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
                $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
                $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
                $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
        }
        // If the scoring method is OWASP (4)
        else if ($scoring_method == 4)
        {
                $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
                $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

                // Average the threat agent and vulnerability factors to get the likelihood
                $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

                $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
                $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

                // Average the technical and business impacts to get the impact
                $OWASP_impact = ($technical_impact + $business_impact)/2;

                // Calculate the overall OWASP risk score
		$calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

                // Create the database query
		$stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, OWASP_SkillLevel=:OWASP_SkillLevel, OWASP_Motive=:OWASP_Motive, OWASP_Opportunity=:OWASP_Opportunity, OWASP_Size=:OWASP_Size, OWASP_EaseOfDiscovery=:OWASP_EaseOfDiscovery, OWASP_EaseOfExploit=:OWASP_EaseOfExploit, OWASP_Awareness=:OWASP_Awareness, OWASP_IntrusionDetection=:OWASP_IntrusionDetection, OWASP_LossOfConfidentiality=:OWASP_LossOfConfidentiality, OWASP_LossOfIntegrity=:OWASP_LossOfIntegrity, OWASP_LossOfAvailability=:OWASP_LossOfAvailability, OWASP_LossOfAccountability=:OWASP_LossOfAccountability, OWASP_FinancialDamage=:OWASP_FinancialDamage, OWASP_ReputationDamage=:OWASP_ReputationDamage, OWASP_NonCompliance=:OWASP_NonCompliance, OWASP_PrivacyViolation=:OWASP_PrivacyViolation WHERE id=:id");
		$stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
                $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
                $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
                $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
        }
        // If the scoring method is Custom (5)
        else if ($scoring_method == 5)
        {
                // If the custom value is not between 0 and 10
                if (!(($custom >= 0) && ($custom <= 10)))
                {
                        // Set the custom value to 10
                        $custom = 10;
                }

                // Calculated risk is the custom value
                $calculated_risk = $custom;

                // Create the database query
		$stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, Custom=:Custom WHERE id=:id");
		$stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
                $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
                $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
        }
        // Otherwise
        else
        {
                return false;
        }

        // Add the risk score
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return $calculated_risk;
}

/*******************************
 * FUNCTION: SUBMIT MITIGATION *
 *******************************/
function submit_mitigation($risk_id, $status, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
        // Subtract 1000 from id
        $risk_id = (int)$risk_id - 1000;

        // Get current datetime for last_update
        $current_datetime = date('Y-m-d H:i:s');

        // Open the database connection
        $db = db_open();
        
        // Add the mitigation
        $stmt = $db->prepare("INSERT INTO mitigations (`risk_id`, `planning_strategy`, `mitigation_effort`, `current_solution`, `security_requirements`, `security_recommendations`, `submitted_by`) VALUES (:risk_id, :planning_strategy, :mitigation_effort, :current_solution, :security_requirements, :security_recommendations, :submitted_by)");

        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":planning_strategy", $planning_strategy, PDO::PARAM_INT);
	$stmt->bindParam(":mitigation_effort", $mitigation_effort, PDO::PARAM_INT);
	$stmt->bindParam(":current_solution", $current_solution, PDO::PARAM_STR);
	$stmt->bindParam(":security_requirements", $security_requirements, PDO::PARAM_STR);
	$stmt->bindParam(":security_recommendations", $security_recommendations, PDO::PARAM_STR);
	$stmt->bindParam(":submitted_by", $_SESSION['uid'], PDO::PARAM_INT);
        $stmt->execute();

	// Get the new mitigation id
	$mitigation_id = get_mitigation_id($risk_id);

	// Update the risk status and last_update
	$stmt = $db->prepare("UPDATE risks SET status=:status, last_update=:last_update, mitigation_id=:mitigation_id WHERE id = :risk_id");
	$stmt->bindParam(":status", $status, PDO::PARAM_STR, 20);
	$stmt->bindParam(":last_update", $current_datetime, PDO::PARAM_STR, 20);
	$stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
	$stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);

	$stmt->execute();

        // If notification is enabled
        if (notification_extra())
        {
                // Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

		// Send the notification
		notify_new_mitigation($risk_id);
        }

        // Close the database connection
        db_close($db);

        return $current_datetime;
}

/**************************************
 * FUNCTION: SUBMIT MANAGEMENT REVIEW *
 **************************************/
function submit_management_review($risk_id, $status, $review, $next_step, $reviewer, $comments, $next_review)
{
        // Subtract 1000 from id
        $risk_id = (int)$risk_id - 1000;

        // Get current datetime for last_update
        $current_datetime = date('Y-m-d H:i:s');

        // Open the database connection
        $db = db_open();

        // Add the review
        $stmt = $db->prepare("INSERT INTO mgmt_reviews (`risk_id`, `review`, `reviewer`, `next_step`, `comments`, `next_review`) VALUES (:risk_id, :review, :reviewer, :next_step, :comments, :next_review)");

        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
	$stmt->bindParam(":review", $review, PDO::PARAM_INT);
	$stmt->bindParam(":reviewer", $reviewer, PDO::PARAM_INT);
	$stmt->bindParam(":next_step", $next_step, PDO::PARAM_INT);
	$stmt->bindParam(":comments", $comments, PDO::PARAM_STR);
	$stmt->bindParam(":next_review", $next_review, PDO::PARAM_STR, 10);

        $stmt->execute();

        // Get the new mitigation id
        $mgmt_review = get_review_id($risk_id);

        // Update the risk status and last_update
        $stmt = $db->prepare("UPDATE risks SET status=:status, last_update=:last_update, review_date=:review_date, mgmt_review=:mgmt_review WHERE id = :risk_id");
        $stmt->bindParam(":status", $status, PDO::PARAM_STR, 20);
        $stmt->bindParam(":last_update", $current_datetime, PDO::PARAM_STR, 20);
	$stmt->bindParam(":review_date", $current_datetime, PDO::PARAM_STR, 20);
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":mgmt_review", $mgmt_review, PDO::PARAM_INT);

        $stmt->execute();

        // If notification is enabled
        if (notification_extra())
        {
                // Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

		// Send the notification
		notify_new_review($risk_id);
        }

        // Close the database connection
        db_close($db);

        return true;
}

/*************************
 * FUNCTION: UPDATE RISK *
 *************************/
function update_risk($id, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes)
{
	// Subtract 1000 from id
	$id = $id - 1000;

	// Get current datetime for last_update
	$current_datetime = date('Y-m-d H:i:s');

        // Open the database connection
        $db = db_open();

        // Update the risk
	$stmt = $db->prepare("UPDATE risks SET subject=:subject, reference_id=:reference_id, regulation=:regulation, control_number=:control_number, location=:location, category=:category, team=:team, technology=:technology, owner=:owner, manager=:manager, assessment=:assessment, notes=:notes, last_update=:date WHERE id = :id");

	$stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":subject", $subject, PDO::PARAM_STR, 100);
	$stmt->bindParam(":reference_id", $reference_id, PDO::PARAM_STR, 20);
	$stmt->bindParam(":regulation", $regulation, PDO::PARAM_INT);
	$stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR, 20);
	$stmt->bindParam(":location", $location, PDO::PARAM_INT);
        $stmt->bindParam(":category", $category, PDO::PARAM_INT);
        $stmt->bindParam(":team", $team, PDO::PARAM_INT);
        $stmt->bindParam(":technology", $technology, PDO::PARAM_INT);
        $stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
        $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
        $stmt->bindParam(":assessment", $assessment, PDO::PARAM_STR);
        $stmt->bindParam(":notes", $notes, PDO::PARAM_STR);
	$stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
        $stmt->execute();

        // If notification is enabled
        if (notification_extra())
        {
                // Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

		// Send the notification
		notify_risk_update($id);
        }

        // Close the database connection
        db_close($db);

        return true;
}

/************************
 * FUNCTION: CONVERT ID *
 ************************/
function convert_id($id)
{
	// Add 1000 to any id to make it at least 4 digits
	$id = $id + 1000;

	return $id;
}

/****************************
 * FUNCTION: GET RISK BY ID *
 ****************************/
function get_risk_by_id($id)
{
        // Open the database connection
        $db = db_open();

	// Subtract 1000 from the id
	$id = $id - 1000;

        // Query the database
	$stmt = $db->prepare("SELECT a.*, b.*, c.next_review FROM risk_scoring a INNER JOIN risks b on a.id = b.id LEFT JOIN mgmt_reviews c on b.mgmt_review = c.id WHERE b.id=:id LIMIT 1");
	$stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

	// If team separation is enabled
	if (team_separation_extra())
	{
		//Include the team separation extra
		require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

		// If this Extra was not called via the command line
		if (PHP_SAPI != 'cli')
		{
			// Strip out risks the user should not have access to
			$array = strip_no_access_risks($array);
		}
	}

        return $array;
}

/**********************************
 * FUNCTION: GET MITIGATION BY ID *
 **********************************/
function get_mitigation_by_id($risk_id)
{
        // Open the database connection
        $db = db_open();

        // Subtract 1000 from the id
        $risk_id = $risk_id - 1000;

        // Query the database
        $stmt = $db->prepare("SELECT * FROM mitigations WHERE risk_id=:risk_id");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If team separation is enabled
        if (team_separation_extra())
        {
                //Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                // Strip out risks the user should not have access to
                $array = strip_no_access_risks($array);
        }

	// If the array is empty
	if (empty($array))
	{
		return false;
	}
        else return $array;
}

/******************************
 * FUNCTION: GET REVIEW BY ID *
 ******************************/
function get_review_by_id($risk_id)
{
        // Open the database connection
        $db = db_open();

        // Subtract 1000 from the id
        $risk_id = $risk_id - 1000;

        // Query the database
        $stmt = $db->prepare("SELECT * FROM mgmt_reviews WHERE risk_id=:risk_id ORDER BY submission_date DESC");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If team separation is enabled
        if (team_separation_extra())
        {
                //Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                // Strip out risks the user should not have access to
                $array = strip_no_access_risks($array);
        }

        // If the array is empty
        if (empty($array))
        {
                return false;
        }
        else return $array;
}

/***********************
 * FUNCTION: GET RISKS *
 ***********************/
function get_risks($sort_order=0)
{
        // Open the database connection
        $db = db_open();

	// If this is the default, sort by risk
	if ($sort_order == 0)
	{
        	// Query the database
		$stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" ORDER BY calculated_risk DESC");
        	$stmt->execute();

        	// Store the list in the array
        	$array = $stmt->fetchAll();
	}

	// 1 = Show risks requiring mitigations
	else if ($sort_order == 1)
	{
		// Query the database
		$stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE mitigation_id = 0 AND status != \"Closed\" ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
	}

        // 2 = Show risks requiring management review
        else if ($sort_order == 2)
        {
                // Query the database
		$stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE mgmt_review = 0 AND status != \"Closed\" ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

	// 3 = Show risks by review date
	else if ($sort_order == 3)
	{
		// Query the database
		$stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id LEFT JOIN mgmt_reviews c ON b.mgmt_review = c.id WHERE status != \"Closed\" ORDER BY review_date ASC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
	}

	// 4 = Show risks that are closed
	else if ($sort_order == 4)
        {
		// Query the database
		$stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status = \"Closed\" ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

	// 5 = Show open risks that should be considered for projects
	else if ($sort_order == 5)
	{
		// Query the database
		$stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 2) AS c ON a.id = c.risk_id WHERE status != \"Closed\" ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

	// 6 = Show open risks accepted until next review
	else if ($sort_order == 6)
	{
		// Query the database
		$stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 1) AS c ON a.id = c.risk_id WHERE status != \"Closed\" ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
	}

	// 7 = Show open risks to submit as production issues
	else if ($sort_order == 7)
	{
		// Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 3) AS c ON a.id = c.risk_id WHERE status != \"Closed\" ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
	}

        // 8 = Show all open risks assigned to this user by risk level
        else if ($sort_order == 8)
        {
                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND (owner = :uid OR manager = :uid) ORDER BY calculated_risk DESC");
		$stmt->bindParam(":uid", $_SESSION['uid'], PDO::PARAM_INT);
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

	// 9 = Show open risks scored by CVSS Scoring
	else if ($sort_order == 9)
	{
                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 2 ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
	}

        // 10 = Show open risks scored by Classic Scoring
        else if ($sort_order == 10)
        {
                // Query the database
		$stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 1 ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // 11 = Show All Risks by Date Submitted
        else if ($sort_order == 11)
        {
                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.status, b.submission_date, d.name AS team, c.name FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN user c ON b.submitted_by = c.value LEFT JOIN team d ON b.team = d.value ORDER BY DATE(b.submission_date) DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // 12 = Show management reviews by date
        else if ($sort_order == 12)
        {
                // Query the database
                $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS review, e.name AS next_step FROM risks a JOIN mgmt_reviews b ON a.id = b.risk_id JOIN user c ON b.reviewer = c.value LEFT JOIN review d ON b.review = d.value LEFT JOIN next_step e ON b.next_step = e.value ORDER BY DATE(b.submission_date) DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // 13 = Show mitigations by date
        else if ($sort_order == 13)
        {
                // Query the database
                $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS planning_strategy, e.name AS mitigation_effort FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value ORDER BY DATE(b.submission_date) DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // 14 = Show open risks scored by DREAD Scoring
        else if ($sort_order == 14)
        {
                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 3 ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // 15 = Show open risks scored by OWASP Scoring
        else if ($sort_order == 15)
        {
                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 4 ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // 16 = Show open risks scored by Custom Scoring
        else if ($sort_order == 16)
        {
                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 5 ORDER BY calculated_risk DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // 17 = Show closed risks by date
        else if ($sort_order == 17)
        {
                // Query the database
		$stmt = $db->prepare("SELECT a.id, a.subject, c.name AS team, d.name AS user, b.closure_date, e.name AS close_reason, f.calculated_risk FROM risks a LEFT JOIN closures b ON a.close_id = b.id LEFT JOIN team c ON a.team = c.value LEFT JOIN user d ON b.user_id = d.value LEFT JOIN close_reason e ON b.close_reason = e.value LEFT JOIN risk_scoring f ON a.id = f.id WHERE a.status='Closed' ORDER BY b.closure_date DESC");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }
	// 18 = Get open risks by team
	else if ($sort_order == 18)
	{
		$stmt = $db->prepare("SELECT a.id, a.subject, c.name AS team, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN team c ON a.team = c.value WHERE status != 'Closed' ORDER BY a.team, b.calculated_risk DESC");
		$stmt->execute();

		// Store the list in the array
		$array = $stmt->fetchAll();
	}
	// 19 = Get open risks by technology
	else if ($sort_order == 19)
	{
		$stmt = $db->prepare("SELECT a.id, a.subject, c.name AS technology, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN technology c ON a.technology = c.value WHERE status != 'Closed' ORDER BY a.technology, b.calculated_risk DESC");
		$stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // 20 = Get open high risks
        else if ($sort_order == 20)
        {
		// Get the high risk level
		$stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'High'");
		$stmt->execute();
		$array = $stmt->fetch();
		$high = $array['value'];

                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND a.calculated_risk >= :high ORDER BY calculated_risk DESC");
		$stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();
        }

        // Close the database connection
        db_close($db);

	// If team separation is enabled
	if (team_separation_extra())
	{
		// Include the team separation extra
		require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

		// Strip out risks the user should not have access to
		$array = strip_no_access_risks($array);
	}

        return $array;
}

/****************************
 * FUNCTION: GET RISK TABLE *
 ****************************/
function get_risk_table($sort_order=0)
{
	global $lang;
	
        // Get risks
        $risks = get_risks($sort_order);

	echo "<table class=\"table table-bordered table-condensed sortable\">\n";
	echo "<thead>\n";
	echo "<tr>\n";
	echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
	echo "<th align=\"left\" width=\"150px\">". $lang['Status'] ."</th>\n";
	echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
	echo "<th align=\"center\" width=\"100px\">". $lang['Risk'] ."</th>\n";
	echo "<th align=\"center\" width=\"150px\">". $lang['Submitted'] ."</th>\n";
	echo "<th align=\"center\" width=\"100px\">". $lang['MitigationPlanned'] ."</th>\n";
	echo "<th align=\"center\" width=\"100px\">". $lang['ManagementReview'] ."</th>\n";
	echo "</tr>\n";
	echo "</thead>\n";
	echo "<tbody>\n";

	// For each risk
	foreach ($risks as $risk)
	{
		// Get the risk color
		$color = get_risk_color($risk['calculated_risk']);

		echo "<tr>\n";
		echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "\">" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "</a></td>\n";
		echo "<td align=\"left\" width=\"150px\">" . htmlentities($risk['status'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"left\" width=\"300px\">" . htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"100px\">" . planned_mitigation(htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false), htmlentities($risk['mitigation_id'], ENT_QUOTES, 'UTF-8', false)) . "</td>\n";
		echo "<td align=\"center\" width=\"100px\">" . management_review(htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false), htmlentities($risk['mgmt_review'], ENT_QUOTES, 'UTF-8', false)) . "</td>\n";
		echo "</tr>\n";
	}

	echo "</tbody>\n";
	echo "</table>\n";

	return true;
}

/***************************************
 * FUNCTION: GET SUBMITTED RISKS TABLE *
 ***************************************/
function get_submitted_risks_table($sort_order=11)
{
	global $lang;
	
        // Get risks
        $risks = get_risks($sort_order);

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
	echo "<th align=\"center\" width=\"150px\">". $lang['SubmissionDate'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['CalculatedRisk'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['Status'] ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $lang['Team'] ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $lang['SubmittedBy'] ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // For each risk
        foreach ($risks as $risk)
        {
                // Get the risk color
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "\">" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"150px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['status'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['team'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['name'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";

        return true;
}

/***********************************
 * FUNCTION: GET MITIGATIONS TABLE *
 ***********************************/
function get_mitigations_table($sort_order=13)
{
	global $lang;
	
        // Get risks
        $risks = get_risks($sort_order);

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['MitigationDate'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['PlanningStrategy'] ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $lang['MitigationEffort'] ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $lang['SubmittedBy'] ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // For each risk
        foreach ($risks as $risk)
        {
                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "\">" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['planning_strategy'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['mitigation_effort'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['name'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";

        return true;
}

/*************************************
 * FUNCTION: GET REVIEWED RISK TABLE *
 *************************************/
function get_reviewed_risk_table($sort_order=12)
{
	global $lang;
	
        // Get risks
        $risks = get_risks($sort_order);

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
	echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['ReviewDate'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['Review'] ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $lang['NextStep'] ."</th>\n";
	echo "<th align=\"center\" width=\"150px\">". $lang['Reviewer'] ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // For each risk
        foreach ($risks as $risk)
        {
                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "\">" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['review'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['next_step'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['name'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";

        return true;
}

/***************************************
 * FUNCTION: GET CLOSED RISKS TABLE *
 ***************************************/
function get_closed_risks_table($sort_order=17)
{
        global $lang;

        // Get risks
        $risks = get_risks($sort_order);

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['CalculatedRisk'] ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $lang['Team'] ."</th>\n";
	echo "<th align=\"center\" width=\"150px\">". $lang['DateClosed'] ."</th>\n";
	echo "<th align=\"center\" width=\"150px\">". $lang['ClosedBy'] ."</th>\n";
	echo "<th align=\"center\" width=\"150px\">". $lang['CloseReason'] ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // For each risk
        foreach ($risks as $risk)
        {
                // Get the risk color
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "\">" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"150px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['team'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['closure_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['user'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities($risk['close_reason'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";

        return true;
}

/**********************************
 * FUNCTION: GET RISK TEAMS TABLE *
 **********************************/
function get_risk_teams_table($sort_order=18)
{
        global $lang;

	// Get risks
	$risks = get_risks($sort_order);

	// Set the current team to empty
	$current_team = "";

	// For each team
	foreach ($risks as $risk)
	{
		$risk_id = (int)$risk['id'];
		$subject = htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false);
		$team = htmlentities(stripslashes($risk['team']), ENT_QUOTES, 'UTF-8', false);
		$submission_date = $risk['submission_date'];
		$calculated_risk = $risk['calculated_risk'];
		$color = get_risk_color($risk['calculated_risk']);

		// If the team is empty
		if ($team == "")
		{
			// Team name is Unassigned
			$team = $lang['Unassigned'];
		}

		// If the team is not the current team
		if ($team != $current_team)
		{
			// If this is not the first team
			if ($current_team != "")
			{
			        echo "</tbody>\n";
        			echo "</table>\n";
        			echo "<br />\n";
			}

			// If the team is not empty
			if ($team != "")
			{
				// Set the team to the current team
				$current_team = $team;
			}
			else $current_team = $lang['Unassigned'];

			// Display the table header
        		echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        		echo "<thead>\n";
        		echo "<tr>\n";
        		echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $current_team ."</font></center></th>\n";
        		echo "</tr>\n";
        		echo "<tr>\n";
        		echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        		echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        		echo "<th align=\"left\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        		echo "<th align=\"left\" width=\"150px\">". $lang['DateSubmitted'] ."</th>\n";
        		echo "</tr>\n";
        		echo "</thead>\n";
			echo "<tbody>\n";
		}

		// Display the risk information
                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n";
	}
}

/*****************************************
 * FUNCTION: GET RISK TECHNOLOGIES TABLE *
 *****************************************/
function get_risk_technologies_table($sort_order=19)
{
        global $lang;

        // Get risks
        $risks = get_risks($sort_order);

        // Set the current technology to empty
        $current_technology = "";

        // For each technology
        foreach ($risks as $risk)
        {
                $risk_id = (int)$risk['id'];
                $subject = htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false);
                $technology = htmlentities(stripslashes($risk['technology']), ENT_QUOTES, 'UTF-8', false);
                $submission_date = $risk['submission_date'];
                $calculated_risk = $risk['calculated_risk'];
                $color = get_risk_color($risk['calculated_risk']);

                // If the technology is empty
                if ($technology == "")
                {
                        // Technology name is Unassigned
                        $technology = $lang['Unassigned'];
                }

                // If the technology is not the current technology
                if ($technology != $current_technology)
                {
                        // If this is not the first technology
                        if ($current_technology != "")
                        {
                                echo "</tbody>\n";
                                echo "</table>\n";
                                echo "<br />\n";
                        }

                        // If the technology is not empty
                        if ($technology != "")
                        {
                                // Set the technology to the current technology
                                $current_technology = $technology;
                        }
                        else $current_technology = $lang['Unassigned'];

                        // Display the table header
                        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
                        echo "<thead>\n";
                        echo "<tr>\n";
                        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $current_technology ."</font></center></th>\n";
                        echo "</tr>\n";
                        echo "<tr>\n";
                        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
                        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
                        echo "<th align=\"left\" width=\"100px\">". $lang['Risk'] ."</th>\n";
                        echo "<th align=\"left\" width=\"150px\">". $lang['DateSubmitted'] ."</th>\n";
                        echo "</tr>\n";
                        echo "</thead>\n";
                        echo "<tbody>\n";
                }

                // Display the risk information
                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n";
        }
}

/************************************
 * FUNCTION: GET RISK SCORING TABLE *
 ************************************/
function get_risk_scoring_table()
{
	global $lang;
	
	echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $lang['ClassicRiskScoring'] ."</font></center></th>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['DateSubmitted'] ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(10);

        // For each risk
        foreach ($risks as $risk)
        {
        	$subject = htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false);
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";  
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $lang['CVSSRiskScoring'] ."</font></center></th>\n";
        echo "</tr>\n"; 
        echo "<tr>\n";  
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['DateSubmitted'] ."</th>\n";
        echo "</tr>\n"; 
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(9);

        // For each risk
        foreach ($risks as $risk)
        {               
                $subject = htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false);
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";  
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n"; 
        }               

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";  
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $lang['DREADRiskScoring'] ."</font></center></th>\n";
        echo "</tr>\n"; 
        echo "<tr>\n";  
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['DateSubmitted'] ."</th>\n";
        echo "</tr>\n"; 
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(14);

        // For each risk
        foreach ($risks as $risk)
        {               
                $subject = htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false);
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";  
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n"; 
        }               

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";  
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $lang['OWASPRiskScoring'] ."</font></center></th>\n";
        echo "</tr>\n"; 
        echo "<tr>\n";  
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['DateSubmitted'] ."</th>\n";
        echo "</tr>\n"; 
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(15);

        // For each risk
        foreach ($risks as $risk)
        {               
                $subject = htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false);
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";  
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n"; 
        }               

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";  
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $lang['CustomRiskScoring'] ."</font></center></th>\n";
        echo "</tr>\n"; 
        echo "<tr>\n";  
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['DateSubmitted'] ."</th>\n";
        echo "</tr>\n"; 
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(16);

        // For each risk
        foreach ($risks as $risk)
        {               
                $subject = htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false);
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";  
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
		echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
                echo "</tr>\n"; 
        }               

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";
}

/******************************************
 * FUNCTION: GET PROJECTS AND RISKS TABLE *
 ******************************************/
function get_projects_and_risks_table()
{
	global $lang;
	
	// Get projects
	$projects = get_projects();

	// For each project
	foreach ($projects as $project)
	{
                $id = (int)$project['value'];
                $name = htmlentities(stripslashes($project['name']), ENT_QUOTES, 'UTF-8', false);
                $order = (int)$project['order'];

                // If the project is not 0 (ie. Unassigned Risks)
                if ($id != 0)
                {
        		echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        		echo "<thead>\n";
        		echo "<tr>\n";
        		echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">" . $name . "</font></center></th>\n";
        		echo "</tr>\n";
		        echo "<tr>\n";
        		echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        		echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        		echo "<th align=\"left\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        		echo "<th align=\"left\" width=\"150px\">". $lang['DateSubmitted'] ."</th>\n";
        		echo "</tr>\n";
        		echo "</thead>\n";
        		echo "<tbody>\n";

        		// Get risks marked as consider for projects
        		$risks = get_risks(5);

                	// For each risk
                	foreach ($risks as $risk)
                	{
                        	$subject = htmlentities(stripslashes($risk['subject']), ENT_QUOTES, 'UTF-8', false);
                        	$risk_id = (int)$risk['id'];
                        	$project_id = (int)$risk['project_id'];
                        	$color = get_risk_color($risk['calculated_risk']);

                        	// If the risk is assigned to that project id
                        	if ($id == $project_id)
                        	{
					echo "<tr>\n";
                			echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                			echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                			echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . htmlentities($risk['calculated_risk'], ENT_QUOTES, 'UTF-8', false) . "</td>\n";
					echo "<td align=\"center\" width=\"150px\">" . htmlentities(date(DATETIMESIMPLE, strtotime($risk['submission_date'])), ENT_QUOTES, 'UTF-8', false) . "</td>\n";
					echo "</tr>\n";
				}
			}

			echo "</tbody>\n";
			echo "</table>\n";
			echo "<br />\n";
		}
	}

}

/******************************
 * FUNCTION: GET PROJECT LIST *
 ******************************/
function get_project_list()
{
	global $lang;
	
        // Get projects
        $projects = get_projects();

	echo "<form action=\"\" method=\"post\">\n";
	echo "<input type=\"submit\" name=\"update_order\" value=\"". $lang[ 'Update'] ."\" /><br /><br />\n";
	echo "<ul id=\"prioritize\">\n";

        // For each project
        foreach ($projects as $project)
        {
		$id = $project['value'];
		$name = $project['name'];
		$order = $project['order'];

		// If the project is not 0 (ie. Unassigned Risks)
		if ($id != 0)
		{
			echo "<li class=\"ui-state-default\" id=\"sort_" . $id . "\">\n";
			echo "<span>&#x21C5;</span>&nbsp;" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\n";
			echo "<input type=\"hidden\" id=\"order" . $id . "\" name=\"order_" . $id . "\" value=\"" . $order . "\" />\n";
			echo "<input type=\"hidden\" name=\"ids[]\" value=\"" . $id . "\" />\n";
			echo "</li>\n";
		}
	}

	echo "</ul>\n";
	echo "<br /><input type=\"submit\" name=\"update_order\" value=\"". $lang[ 'Update'] ."\" />\n";
	echo "</form>\n";

	return true;
}

/********************************
 * FUNCTION: GET PROJECT STATUS *
 ********************************/
function get_project_status()
{
	global $lang;

        // Get projects
        $projects = get_projects();

	echo "<form action=\"\" method=\"post\">\n";
	echo "<div id=\"statustabs\">\n";
	echo "<ul>\n";
        echo "<li><a href=\"#statustabs-1\">". $lang['ActiveProjects'] ."</a></li>\n";
        echo "<li><a href=\"#statustabs-2\">" .$lang['OnHoldProjects'] ."</a></li>\n";
        echo "<li><a href=\"#statustabs-3\">" .$lang['CompletedProjects'] ."</a></li>\n";
        echo "<li><a href=\"#statustabs-4\">" .$lang['CancelledProjects'] ."</a></li>\n";
	echo "</ul>\n";

	// For each of the project status types
	for ($i=1; $i <=4; $i++)
	{
		echo "<div id=\"statustabs-".$i."\">\n";
		echo "<ul id=\"statussortable-".$i."\" class=\"connectedSortable ui-helper-reset\">\n";

        	foreach ($projects as $project)
        	{
                	$id = $project['value'];
                	$name = $project['name'];
			$status = $project['status'];

			// If the status is the same as the current project status and the name is not Unassigned Risks
			if ($status == $i && $name != "Unassigned Risks")
			{

                                echo "<li id=\"" . $id . "\" class=\"project\">" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "\n";
                                echo "<input class=\"assoc-project-with-status\" type=\"hidden\" id=\"project" . $id . "\" name=\"project_" . $id . "\" value=\"" . $status . "\" />\n";
                                echo "<input id=\"all-project-ids\" class=\"all-project-ids\" type=\"hidden\" name=\"projects[]\" value=\"" . $id . "\" />\n";
                                echo "</li>\n";
			}
		}

        	echo "</ul>\n";
        	echo "</div>\n";
        }

	echo "</div>\n";
	echo "<br /><input type=\"submit\" name=\"update_project_status\" value=\"" .$lang['UpdateProjectStatuses'] ."\" />\n";
	echo "</form>\n";

        return true;
}

/**********************************
 * FUNCTION: UPDATE PROJECT ORDER *
 **********************************/
function update_project_order($order, $id)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE projects SET `order` = :order WHERE `value` = :id");
	$stmt->bindParam(":order", $order, PDO::PARAM_INT);
	$stmt->bindParam(":id", $id, PDO::PARAM_INT);

        $stmt->execute();

        // Close the database connection
        db_close($db);

	return true;
}

/*********************************
 * FUNCTION: UPDATE RISK PROJECT *
 *********************************/
function update_risk_project($project_id, $risk_id)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE risks SET `project_id` = :project_id WHERE `id` = :risk_id");
        $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);

        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/***********************************
 * FUNCTION: UPDATE PROJECT STATUS *
 ***********************************/
function update_project_status($status_id, $project_id)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE projects SET `status` = :status_id WHERE `value` = :project_id");
        $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
        $stmt->bindParam(":status_id", $status_id, PDO::PARAM_INT);

        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/******************************
 * FUNCTION: GET PROJECT TABS *
 ******************************/
function get_project_tabs()
{
	global $lang;
	
	$projects = get_projects();

	echo "<form action=\"\" method=\"post\">\n";
	echo "<div id=\"tabs\">\n";
	echo "<ul>\n";
	
	foreach ($projects as $project)
	{
		$id = $project['value'];
		$name = $project['name'];

		echo "<li><a href=\"#tabs-" . $id . "\">" . htmlentities($name, ENT_QUOTES, 'UTF-8', false) . "</a></li>\n";
	}

	echo "</ul>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(5);

	// For each project
	foreach ($projects as $project)
	{
		$id = $project['value'];
		$name = $project['name'];

		echo "<div id=\"tabs-" . $id . "\">\n";
		echo "<ul id=\"sortable-" . $id . "\" class=\"connectedSortable ui-helper-reset\">\n";

		// For each risk
		foreach ($risks as $risk)
		{
			$subject = stripslashes($risk['subject']);
			$risk_id = $risk['id'];
			$project_id = $risk['project_id'];
                	$color = get_risk_color($risk['calculated_risk']);

			// If the risk is assigned to that project id
			if ($id == $project_id)
			{
				echo "<li id=\"" . $risk_id . "\" class=\"" . $color . "\"><a href=\"index.php?module=1&page=5&id=" . htmlentities(convert_id($risk['id']), ENT_QUOTES, 'UTF-8', false) . "\">" . htmlentities($subject, ENT_QUOTES, 'UTF-8', false) . "</a>\n";
				echo "<input class=\"assoc-risk-with-project\" type=\"hidden\" id=\"risk" . $risk_id . "\" name=\"risk_" . $risk_id . "\" value=\"" . $project_id . "\" />\n";
                        	echo "<input id=\"all-risk-ids\" class=\"all-risk-ids\" type=\"hidden\" name=\"ids[]\" value=\"" . $risk_id . "\" />\n";
                        	echo "</li>\n";
			}
		}

		echo "</ul>\n";
		echo "</div>\n";
	}

	echo "</div>\n";
	echo "<br /><input type=\"submit\" name=\"update_projects\" value=\"". $lang['SaveRisksToProjects'] ."\" />\n";
	echo "</form>\n";
}

/**************************
 * FUNCTION: GET PROJECTS *
 **************************/
function get_projects($order="order")
{
        // Open the database connection
        $db = db_open();

	// If the order is by status
	if ($order == "status")
	{
		$stmt = $db->prepare("SELECT * FROM projects ORDER BY `status` ASC");
	}
	// If the order is by order
	else
	{
        	// Query the database
        	$stmt = $db->prepare("SELECT * FROM projects ORDER BY `order` ASC");
	}

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
        
        // Close the database connection
        db_close($db);

        return $array;
}

/*******************************
 * FUNCTION: PROJECT HAS RISKS *
 *******************************/
function project_has_risks($project_id)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT * FROM risks WHERE project_id = :project_id");
	$stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // If the array is empty
        if (empty($array))
        {
                $return = false;
        }
        else $return = true;

        // Close the database connection
        db_close($db);

        return $return;
}

/*******************************
 * FUNCTION: GET REVIEWS TABLE *
 *******************************/
function get_reviews_table($sort_order=3)
{
	global $lang;
	
        // Get risks
        $risks = get_risks($sort_order);

        // Initialize the reviews array
        $reviews = array();

	// Parse through each row in the array
	foreach ($risks as $key => $row)
	{
		// Create arrays for each value
                $risk_id[$key] = (int)$row['id'];
                $subject[$key] = htmlentities(stripslashes($row['subject']), ENT_QUOTES, 'UTF-8', false);
                $status[$key] = htmlentities($row['status'], ENT_QUOTES, 'UTF-8', false);
                $calculated_risk[$key] = htmlentities($row['calculated_risk'], ENT_QUOTES, 'UTF-8', false);
                $color[$key] = get_risk_color($row['calculated_risk']);
                $dayssince[$key] = dayssince($row['submission_date']);
                $next_review[$key] = next_review($color[$key], $risk_id[$key], $row['next_review'], false);
                $next_review_html[$key] = next_review($color[$key], $row['id'], $row['next_review']);

                // Create a new array of reviews
                $reviews[] = array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key]);

                // Sort the reviews array by next_review
                array_multisort($next_review, SORT_DESC, SORT_STRING, $calculated_risk, SORT_DESC, SORT_NUMERIC, $reviews);
	}

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $lang['ID'] ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $lang['Status'] ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $lang['Subject'] ."</th>\n";
        echo "<th align=\"center\" width=\"100px\">". $lang['Risk'] ."</th>\n";
        echo "<th align=\"center\" width=\"100px\">". $lang['DaysOpen'] ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $lang['NextReviewDate'] ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // For each risk
        foreach ($reviews as $review)
        {
                $risk_id = $review['risk_id'];
                $subject = $review['subject'];
                $status = $review['status'];
                $calculated_risk = $review['calculated_risk'];
                $color = $review['color'];
                $dayssince = $review['dayssince'];
                $next_review = $review['next_review'];
                $next_review_html = $review['next_review_html'];

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"index.php?module=1&page=5&id=" . convert_id($risk_id) . "\">" . convert_id($risk_id) . "</a></td>\n";
                echo "<td align=\"left\" width=\"150px\">" . $status . "</td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $subject . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $color . "\" width=\"100px\">" . $calculated_risk . "</td>\n";
                echo "<td align=\"center\" width=\"100px\">" . $dayssince . "</td>\n";
                echo "<td align=\"center\" width=\"150px\">" . $next_review_html . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";

        return true;
}

/*******************************
 * FUNCTION: MANAGEMENT REVIEW *
 *******************************/
function management_review($risk_id, $mgmt_review)
{
	global $lang;
	
	// If the review hasn't happened
	if ($mgmt_review == "0")
	{
		$value = "<a href=\"index.php?module=1&page=6&id=" . $risk_id . "\">". $lang['No'] ."</a>";
	}
	else $value = $lang['Yes'];

	return $value;
}

/********************************
 * FUNCTION: PLANNED MITIGATION *
 ********************************/
function planned_mitigation($risk_id, $mitigation_id)
{
	global $lang;
	
        // If the review hasn't happened
        if ($mitigation_id == "0")
        {
                $value = "<a href=\"index.php?module=1&page=7&id=" . $risk_id . "\">". $lang['No'] ."</a>";
        }
        else $value = $lang['Yes'];

        return $value;
}

/*******************************
 * FUNCTION: GET NAME BY VALUE *
 *******************************/
function get_name_by_value($table, $value)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT name FROM $table WHERE value=:value LIMIT 1");   
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);

        $stmt->execute();
        
        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

	// If we get a value back from the query
	if (isset($array[0]['name']))
	{
		// Return that value
        	return stripslashes($array[0]['name']);
	}
	// Otherwise, return an empty string
	else return "";
} 

/*****************************
 * FUNCTION: UPDATE LANGUAGE *
 *****************************/
function update_language($uid, $language)
{
	// Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE user SET lang = :language WHERE value = :uid");
	$stmt->bindParam(":language", $language, PDO::PARAM_STR);
	$stmt->bindParam(":uid", $uid, PDO::PARAM_INT);

	$stmt->execute();

        // Close the database connection
        db_close($db);

	// If the session belongs to the same UID as the one we are updating
	if ($_SESSION['uid'] == $uid)
	{
		// Update the language for the session
		$_SESSION['lang'] = $language;
	}
}

/***************************
 * FUNCTION: GET CVSS NAME *
 ***************************/
function get_cvss_name($metric_name, $abrv_metric_value)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT metric_value FROM CVSS_scoring WHERE metric_name=:metric_name AND abrv_metric_value=:abrv_metric_value LIMIT 1");
	$stmt->bindParam(":metric_name", $metric_name, PDO::PARAM_STR, 30);
        $stmt->bindParam(":abrv_metric_value", $abrv_metric_value, PDO::PARAM_STR, 3);

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If we get a value back from the query
        if (isset($array[0]['metric_value']))
        {
                // Return that value
		return stripslashes($array[0]['metric_value']);
        }
        // Otherwise, return an empty string
        else return "";
}

/*******************************
 * FUNCTION: GET MITIGATION ID *
 *******************************/
function get_mitigation_id($risk_id)
{       
        // Open the database connection
        $db = db_open();
        
        // Query the database
        $stmt = $db->prepare("SELECT id FROM mitigations WHERE risk_id=:risk_id");
	$stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

	// Store the list in the array
        $array = $stmt->fetchAll();
        
        // Close the database connection
        db_close($db);
        
        return $array[0]['id'];
}

/********************************
 * FUNCTION: GET MGMT REVIEW ID *
 ********************************/
function get_review_id($risk_id)
{
        // Open the database connection
        $db = db_open();

        // Query the database
	// Get the most recent management review id
        $stmt = $db->prepare("SELECT id FROM mgmt_reviews WHERE risk_id=:risk_id ORDER BY submission_date DESC LIMIT 1");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array[0]['id'];
}

/*****************************
 * FUNCTION: DAYS SINCE DATE *
 *****************************/
function dayssince($date)
{
	$datetime1 = new DateTime($date);
	$datetime2 = new DateTime("now");
	$days = $datetime1->diff($datetime2);

	// Return the number of days
	return $days->format('%a');
}

/**********************************
 * FUNCTION: GET LAST REVIEW DATE *
 **********************************/
function get_last_review($risk_id)
{
        // Open the database connection
        $db = db_open();

        // Select the last submission date
	$stmt = $db->prepare("SELECT submission_date FROM mgmt_reviews WHERE risk_id=:risk_id ORDER BY submission_date DESC LIMIT 1");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
                return "";
        }
        else return $array[0]['submission_date'];
}

/**********************************
 * FUNCTION: GET NEXT REVIEW DATE *
 **********************************/
function next_review($color, $risk_id, $next_review, $html = true)
{
	global $lang;
	
	// If the next_review is null
	if ($next_review == null)
	{
		// The risk has not been reviewed yet
		$text = $lang['UNREVIEWED'];
	}
	// If the risk has been reviewed
	else
	{
		// If the review used the default date
		if ($next_review == "0000-00-00")
		{
			// Get the last review for this risk
			$last_review = get_last_review($risk_id);

			// Get the review levels
			$review_levels = get_review_levels();

			// If high risk
			if ($color == "red")
			{
				// Get days to review high risks
				$days = $review_levels[0]['value'];
			}
			// If medium risk
			else if ($color == "orange")
			{
                        	// Get days to review medium risks
                        	$days = $review_levels[1]['value'];
			}
			// If low risk
			else if ($color == "yellow")
			{
                        	// Get days to review low risks
                        	$days = $review_levels[2]['value'];
			}

			// Next review date
                	$last_review = new DateTime($last_review);
                	$next_review = $last_review->add(new DateInterval('P'.$days.'D'));
		}
		// A custom next review date was used
		else $next_review = new DateTime($next_review);

		// If the next review date is after today
		if (strtotime($next_review->format('Y-m-d')) > time())
		{
			$text = $next_review->format(DATESIMPLE);
		}
		else $text = $lang['PASTDUE'];
	}

	// If we want to include the HTML code
	if ($html == true)
	{
		// Add the href tag to make it HTML
		$html = "<a href=\"index.php?module=1&page=6&id=" . convert_id($risk_id) . "\">" . $text . "</a>";

		// Return the HTML code
		return $html;
	}
	// Otherwise just return the text
	else return $text;
}

/**********************************
 * FUNCTION: NEXT REVIEW BY SCORE *
 **********************************/
function next_review_by_score($calculated_risk)
{
	// Get risk color by score
	$color = get_risk_color($calculated_risk);

        // Get the review levels
        $review_levels = get_review_levels();

        // If high risk
        if ($color == "red")
        {
                // Get days to review high risks
                $days = $review_levels[0]['value'];
        }
        // If medium risk
        else if ($color == "orange")
        {
                // Get days to review medium risks
                $days = $review_levels[1]['value'];
        }
        // If low risk
        else if ($color == "yellow")
        {
                // Get days to review low risks
                $days = $review_levels[2]['value'];
        }

        // Next review date
        $today = new DateTime('NOW');
        $next_review = $today->add(new DateInterval('P'.$days.'D'));
	$next_review = $next_review->format(DATESIMPLE);

	// Retunr the next review date
	return $next_review;
}

/************************
 * FUNCTION: CLOSE RISK *
 ************************/
function close_risk($id, $user_id, $status, $close_reason, $note)
{
        // Subtract 1000 from id
        $id = $id - 1000;

        // Get current datetime for last_update
        $current_datetime = date('Y-m-d H:i:s');

        // Open the database connection
        $db = db_open();

        // Add the closure
        $stmt = $db->prepare("INSERT INTO closures (`risk_id`, `user_id`, `close_reason`, `note`) VALUES (:risk_id, :user_id, :close_reason, :note)");

        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
	$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":close_reason", $close_reason, PDO::PARAM_INT);
        $stmt->bindParam(":note", addslashes($note), PDO::PARAM_STR);

        $stmt->execute();

        // Get the new mitigation id
        $close_id = get_close_id($id);

        // Update the risk
        $stmt = $db->prepare("UPDATE risks SET status=:status,last_update=:date,project_id=0,close_id=:close_id WHERE id = :id");

        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":status", $status, PDO::PARAM_STR, 50);
        $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
	$stmt->bindParam(":close_id", $close_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/**************************
 * FUNCTION: GET CLOSE ID *
 **************************/
function get_close_id($risk_id)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        // Get the close id
        $stmt = $db->prepare("SELECT id FROM closures WHERE risk_id=:risk_id ORDER BY closure_date DESC LIMIT 1");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array[0]['id'];
}

/*************************
 * FUNCTION: REOPEN RISK *
 *************************/
function reopen_risk($id)
{
        // Subtract 1000 from id
        $id = $id - 1000;

        // Get current datetime for last_update
        $current_datetime = date('Y-m-d H:i:s');

        // Open the database connection
        $db = db_open();

        // Update the risk
        $stmt = $db->prepare("UPDATE risks SET status=\"Reopened\",last_update=:date,close_id=\"0\" WHERE id = :id");

        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/*************************
 * FUNCTION: ADD COMMENT *
 *************************/
function add_comment($id, $user_id, $comment)
{
        // Subtract 1000 from id
        $id = $id - 1000;

        // Get current datetime for last_update
        $current_datetime = date('Y-m-d H:i:s');
        
        // Open the database connection
        $db = db_open();
        
        // Add the closure
        $stmt = $db->prepare("INSERT INTO comments (`risk_id`, `user`, `comment`) VALUES (:risk_id, :user, :comment)");
        
        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":comment", $comment, PDO::PARAM_STR);

        $stmt->execute();
        
        // Update the risk
        $stmt = $db->prepare("UPDATE risks SET last_update=:date WHERE id = :id");

        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
}

/**************************
 * FUNCTION: GET COMMENTS *
 **************************/
function get_comments($id)
{
        // Subtract 1000 from id
	$id = $id - 1000;

        // Open the database connection
        $db = db_open();

        // Get the comments
        $stmt = $db->prepare("SELECT a.date, a.comment, b.name FROM comments a LEFT JOIN user b ON a.user = b.value WHERE risk_id=:risk_id ORDER BY date DESC");

        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);

        $stmt->execute();

        // Store the list in the array
        $comments = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        foreach ($comments as $comment)
        {
		$text = htmlentities(stripslashes($comment['comment']), ENT_QUOTES, 'UTF-8', false);
		$date = htmlentities(date(DATETIME, strtotime($comment['date'])), ENT_QUOTES, 'UTF-8', false);
		$user = htmlentities(stripslashes($comment['name']), ENT_QUOTES, 'UTF-8', false);

		echo "<p>".$date." > ".$user.": ".$text."</p>\n";
	}

        return true;
}

/*****************************
 * FUNCTION: GET AUDIT TRAIL *
 *****************************/
function get_audit_trail($id = NULL)
{
	// Open the database connection
	$db = db_open();

	// If the ID is not NULL
	if ($id != NULL)
	{
        	// Subtract 1000 from id
        	$id = $id - 1000;

        	// Get the comments for this specific ID
        	$stmt = $db->prepare("SELECT timestamp, message FROM audit_log WHERE risk_id=:risk_id ORDER BY timestamp DESC");

        	$stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
	}
	// Otherwise get the full audit trail
	else
	{
		$stmt = $db->prepare("SELECT timestamp, message FROM audit_log ORDER BY timestamp DESC");
	}

        $stmt->execute();
        
        // Store the list in the array
        $logs = $stmt->fetchAll();
        
        // Close the database connection
        db_close($db);
        
        foreach ($logs as $log)
        {       
                $text = htmlentities(stripslashes($log['message']), ENT_QUOTES, 'UTF-8', false);
                $date = htmlentities(date(DATETIME, strtotime($log['timestamp'])), ENT_QUOTES, 'UTF-8', false); 
                
                echo "<p>".$date." > ".$text."</p>\n";
        }

        return true;
}

/*******************************
 * FUNCTION: UPDATE MITIGATION *
 *******************************/
function update_mitigation($id, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
        // Subtract 1000 from id
        $id = $id - 1000;

        // Get current datetime for last_update
        $current_datetime = date('Y-m-d H:i:s');

        // Open the database connection
        $db = db_open();

        // Update the risk
	$stmt = $db->prepare("UPDATE mitigations SET last_update=:date, planning_strategy=:planning_strategy, mitigation_effort=:mitigation_effort, current_solution=:current_solution, security_requirements=:security_requirements, security_recommendations=:security_recommendations WHERE risk_id=:id");

        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
	$stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
        $stmt->bindParam(":planning_strategy", $planning_strategy, PDO::PARAM_INT);
	$stmt->bindParam(":mitigation_effort", $mitigation_effort, PDO::PARAM_INT);
        $stmt->bindParam(":current_solution", $current_solution, PDO::PARAM_STR);
        $stmt->bindParam(":security_requirements", $security_requirements, PDO::PARAM_STR);
	$stmt->bindParam(":security_recommendations", $security_recommendations, PDO::PARAM_STR);
        $stmt->execute();

        // If notification is enabled
        if (notification_extra())
        {
                // Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

		// Send the notification
		notify_mitigation_update($id);
        }

        // Close the database connection
        db_close($db);

        return $current_datetime;
}

/**************************
 * FUNCTION: GET REVIEWS *
 **************************/
function get_reviews($id)
{
	global $lang;
	
        // Subtract 1000 from id
        $id = $id - 1000;

        // Open the database connection
        $db = db_open();

        // Get the comments
        $stmt = $db->prepare("SELECT * FROM mgmt_reviews WHERE risk_id=:risk_id ORDER BY submission_date DESC");

        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);

        $stmt->execute();

        // Store the list in the array
        $reviews = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        foreach ($reviews as $review)
        {
                $date = htmlentities(date(DATETIME, strtotime($review['submission_date'])), ENT_QUOTES, 'UTF-8', false);
		$reviewer =  get_name_by_value("user", $review['reviewer']);
		$review_value = get_name_by_value("review", $review['review']);
		$next_step = get_name_by_value("next_step", $review['next_step']);
		$comment = htmlentities(stripslashes($review['comments']), ENT_QUOTES, 'UTF-8', false);

		echo "<p>\n";
		echo "<u>".$date."</u><br />\n";
		echo $lang['Reviewer'] .": ".$reviewer."<br />\n";
		echo $lang['Review'] .": ".$review_value."<br />\n";
		echo $lang['NextStep'] .": ".$next_step."<br />\n";
		echo $lang['Comment'] .": ".$comment."\n";
		echo "</p>\n";
        }

        return true;
}

/****************************
 * FUNCTION: LATEST VERSION *
 ****************************/
function latest_version($param)
{
	$version_page = file('https://updates.simplerisk.it/Current_Version.xml');
 
	if ($param == "app")
	{
		$regex_pattern = "/<appversion>(.*)<\/appversion>/";
	}
	else if ($param == "db")
	{
		$regex_pattern = "/<dbversion>(.*)<\/dbversion>/";
	}

	foreach ($version_page as $line)
	{           
        	if (preg_match($regex_pattern, $line, $matches)) 
        	{
                	$latest_version = $matches[1];
        	}   
	}      

	// Return the latest version HTML encoded
	return htmlentities($latest_version, ENT_QUOTES, 'UTF-8', false);
}

/*****************************
 * FUNCTION: CURRENT VERSION *
 *****************************/
function current_version($param)
{
        if ($param == "app")
        {
		require_once(realpath(__DIR__ . '/version.php'));

		return APP_VERSION;
        }
        else if ($param == "db")
        {
		// Open the database connection
		$db = db_open();

		$stmt = $db->prepare("SELECT * FROM settings WHERE name=\"db_version\"");

		// Execute the statement
        	$stmt->execute();
        
       		// Get the current version
        	$array = $stmt->fetchAll();

        	// Close the database connection
        	db_close($db);

		// Return the current version
		return $array[0]['value'];
	}
}

/***********************
 * FUNCTION: WRITE LOG *
 ***********************/
function write_log($risk_id, $user_id, $message)
{
        // Subtract 1000 from id
        $risk_id = $risk_id - 1000;

        // Open the database connection
        $db = db_open();

        // Get the comments
        $stmt = $db->prepare("INSERT INTO audit_log (risk_id, user_id, message) VALUES (:risk_id, :user_id, :message)");

        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
	$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
	$stmt->bindParam(":message", $message, PDO::PARAM_STR);

        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/*******************************
 * FUNCTION: UPDATE LAST LOGIN *
 *******************************/
function update_last_login($user_id)
{
	// Get current datetime for last_update
        $current_datetime = date('Y-m-d H:i:s');

        // Open the database connection
        $db = db_open();

        // Update the last login
        $stmt = $db->prepare("UPDATE user SET `last_login`=:last_login WHERE `value`=:user_id");
	$stmt->bindParam(":last_login", $current_datetime, PDO::PARAM_STR, 20);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

	return true;
}

/*******************************
 * FUNCTION: GET ANNOUNCEMENTS *
 *******************************/
function get_announcements()
{
	$announcements = "<ul>\n";

        $announcement_file = file('https://updates.simplerisk.it/announcements.xml');

	$regex_pattern = "/<announcement>(.*)<\/announcement>/";

        foreach ($announcement_file as $line)
        {
                if (preg_match($regex_pattern, $line, $matches))
                {
                        $announcements .= "<li>" . htmlentities($matches[1], ENT_QUOTES, 'UTF-8', false) . "</li>\n";
                }
        }

	$announcements .= "</ul>";

        // Return the announcement
        return $announcements;
}

/***************************
 * FUNCTION: LANGUAGE FILE *
 ***************************/
function language_file()
{
	// If the language is set for the user
	if (isset($_SESSION['lang']))
	{
		// Use the users language
		return realpath(__DIR__ . '/../languages/' . $_SESSION['lang'] . '/lang.' . $_SESSION['lang'] . '.php');
	}
	// If the default language is defined in the config file
	else if (defined('LANG_DEFAULT'))
	{
		// Use the default language
		return realpath(__DIR__ . '/../languages/' . LANG_DEFAULT . '/lang.' . LANG_DEFAULT . '.php');
	}
	// Otherwise, use english
	else return realpath(__DIR__ . '/../languages/en/lang.en.php');
}

/*****************************************
 * FUNCTION: CUSTOM AUTHENTICATION EXTRA *
 *****************************************/
function custom_authentication_extra()
{
        // Open the database connection
        $db = db_open();

        // Update the last login
        $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'custom_auth'");
        $stmt->execute();

        // Get the results array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If no value was found
        if (empty($array))
        {
                return false;
        }
        // If the value is true
        else if ($array[0]['value'] == true)
        {
                return true;
        }
        else return false;
}

/***********************************
 * FUNCTION: TEAM SEPARATION EXTRA *
 ***********************************/
function team_separation_extra()
{
        // Open the database connection
        $db = db_open();

        // Update the last login
        $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'team_separation'");
        $stmt->execute();

        // Get the results array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If no value was found
	if (empty($array))
	{
		return false;
	}
	// If the value is true
	else if ($array[0]['value'] == true)
	{
		return true;
	}
	else return false;
}

/********************************
 * FUNCTION: NOTIFICATION EXTRA *
 ********************************/
function notification_extra()
{
        // Open the database connection
        $db = db_open();

        // Update the last login
        $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'notifications'");
        $stmt->execute();

        // Get the results array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If no value was found
        if (empty($array))
        {
                return false;
        }
        // If the value is true
        else if ($array[0]['value'] == true)
        {
                return true;
        }
        else return false;
}

/******************************
 * FUNCTION: ENCRYPTION EXTRA *
 ******************************/
function encryption_extra()
{
        // Open the database connection
        $db = db_open();

        // Update the last login
        $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'encryption'");
        $stmt->execute();

        // Get the results array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If no value was found
        if (empty($array))
        {
                return false;
        }
        // If the value is true
        else if ($array[0]['value'] == true)
        {
                return true;
        }
        else return false;
}

?>
