<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/reporting.php'));
require_once(realpath(__DIR__ . '/assets.php'));

/******************************
 * FUNCTION: IS AUTHENTICATED *
 ******************************/
function is_authenticated()
{
	// If either the session or key is authenticated
	if (is_session_authenticated() || is_key_authenticated() != false)
	{
		// Return true
		return true;
	}
	else unauthenticated_access();
}

/**************************************
 * FUNCTION: IS SESSION AUTHENTICATED *
 **************************************/
function is_session_authenticated()
{
	// If the session is not authenticated
	if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
	{
		return false;
	}
	else return true;
}

/**********************************
 * FUNCTION: IS KEY AUTHENTICATED *
 **********************************/
function is_key_authenticated()
{
	// Check if the API Extra is enabled
	if (api_extra())
	{
		// Require the API Extra
		require_once(realpath(__DIR__ . '/../extras/api/index.php'));

		// Return whether the key is authenticated or not
		return authenticate_key();
	}
	// Otherwise return false
	else return false;
}

/************************************
 * FUNCTION: UNAUTHENTICATED ACCESS *
 ************************************/
function unauthenticated_access()
{
	// Return a JSON response
	json_response(401, "Unauthenticated Access.  Please log in or provide a key to use the SimpleRisk API.", NULL);
}

/**********************
 * FUNCTION: IS ADMIN *
 **********************/
function is_admin()
{
	// If the user is not logged in as an administrator
	if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
	{
		unauthorized_access();
	}
	else return true;
}

/*********************************
 * FUNCTION: UNAUTHORIZED ACCESS *
 *********************************/
function unauthorized_access()
{
        // Return a JSON response
        json_response(401, "Unauthorized Access.  The authenticated user does not have proper permissions.", NULL);
}

/****************************
 * FUNCTION: SHOW ENDPOINTS *
 ****************************/
function show_endpoints()
{
  // Show the main menu
  echo '<ul>
          <li><a href="">/</a> -> (home)</li>
          <li><a href="version">/version</a> -> (print the version of the api)</li>
          <li><a href="whoami">/whoami</a> -> (shows the currently authenticated user)</li>
        </ul>';

  // Show the management menu
  show_management();

  // Show the admin menu
  show_admin();

  // Show the reports menu
  show_reports();
}

/*****************************
 * FUNCTION: SHOW MANAGEMENT *
 *****************************/
function show_management()
{
  echo '<ul>
          <li><a href="management/risk/view">/management/risk/view</a> -> (view a risk)</li>
          <li><a href="management/risk/add">/management/risk/add</a> -> (add a risk)</li>
          <li><a href="management/mitigation/view">/management/mitigation/view</a> -> (view a mitigation)</li>
          <li><a href="management/mitigation/add">/management/mitigation/add</a> -> (add a mitigation)</li>
          <li><a href="management/review/view">/management/review/view</a> -> (view a review)</li>
          <li><a href="management/review/add">/management/review/add</a> -> (add a review)</li>
        </ul>';
}

/************************
 * FUNCTION: SHOW ADMIN *
 ************************/
function show_admin()
{
  echo '<ul>
          <li><a href="admin/users/all">/admin/users/all</a> -> (shows all users)</li>
          <li><a href="admin/users/enabled">/admin/users/enabled</a> -> (shows enabled users)</li>
          <li><a href="admin/users/disabled">/admin/users/disabled</a> -> (shows disabled users)</li>
        </ul>';
}

/**************************
 * FUNCTION: SHOW REPORTS *
 **************************/
function show_reports()
{
  echo '<ul>
          <li><a href="reports/dynamic">/reports/dynamic</a> -> (shows dynamic risk report)</li>
        </ul>';
}

/**************************
 * FUNCTION: SHOW VERSION *
 **************************/
function show_version()
{
	echo 'The version of this api is: ' . getApi()->invoke('/version.json');
}

/*************************
 * FUNCTION: API VERSION *
 *************************/
function api_version()
{
  return '1.0';
}

/********************
 * FUNCTION: WHOAMI *
 ********************/
function whoami()
{
	// Get the username and uid
	$user = $_SESSION['user'];
	$uid = $_SESSION['uid'];

	// Create the data array
	$data = array("username" => $user, "uid" => $uid);

	// Return a JSON response
	json_response(200, "whoami", $data);
}

/***********************
 * FUNCTION: ALL USERS *
 ***********************/
function allusers()
{
	// If the user is an administrator
	if (is_admin())
	{
		// Get the list of users ordered by name
		$users = get_table_ordered_by_name("user");

		// Initialize the data array
		$data = array();

		// For each item in the users array
		foreach ($users as $user)
		{
			// Create the new data array
			$data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login']);
		}

		// Return a JSON response
		json_response(200, "allusers", $data);
	}
}

/***************************
 * FUNCTION: ENABLED USERS *
 ***************************/
function enabledusers()
{
	// If the user is an administrator
	if (is_admin())
	{
		// Get the list of enabled users ordered by name
		$users = get_custom_table("enabled_users");

                // Initialize the data array
                $data = array();

		// For each item in the users array
		foreach ($users as $user)
		{
			// Create the new data array
			$data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login']);
		}

		// Return a JSON response
		json_response(200, "enabledusers", $data);
	}
}

/****************************
 * FUNCTION: DISABLED USERS *
 ****************************/
function disabledusers()
{
	// If the user is an administrator
	if (is_admin())
	{
                // Get the list of disabled users ordered by name
                $users = get_custom_table("disabled_users");

                // Initialize the data array
                $data = array();

                // For each item in the users array
                foreach ($users as $user)
                {
                        // Create the new data array
                        $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login']);
                }

                // Return a JSON response
                json_response(200, "disabledusers", $data);
	}
}

/************************************
 * FUNCTION: REPORTS - DYNAMIC RISK *
 ************************************/
function dynamicrisk()
{
	// If the status, sort, and group are not sent
	if (!isset($_GET['status']) || !isset($_GET['sort']) || !isset($_GET['group']))
	{
		// Return a JSON response
		json_response(400, "You need to specify a status, sort, and group parameter.", NULL);
	}
	else
	{
		// Get the status, sort, and group
		$status = $_GET['status'];
		$sort = $_GET['sort'];
		$group = $_GET['group'];

		// Query the risks
		$data = risks_query($status, $sort, $group);

        	// Return a JSON response
        	json_response(200, "dynamicrisk", $data);
	}
}

/************************************
 * FUNCTION: MANAGEMENT - VIEW RISK *
 ************************************/
function viewrisk()
{
        // If the id is not sent
        if (!isset($_GET['id']))
        {
                // Return a JSON response
                json_response(400, "You need to specify an id parameter.", NULL);
        }
	else
	{
		// Get the id
		$id = (int)$_GET['id'];

		// Query the risk
		$risk = get_risk_by_id($id);

		// If the risk was found use the values for the risk
		if (count($risk) != 0)
		{
                        $status = $risk[0]['status'];
                        $subject = try_decrypt($risk[0]['subject']);
                        $reference_id = $risk[0]['reference_id'];
                        $regulation = get_name_by_value("regulation", $risk[0]['regulation']);
                        $control_number = $risk[0]['control_number'];
                        $location = get_name_by_value("location", $risk[0]['location']);
			$source = get_name_by_value("source", $risk[0]['source']);
                        $category = get_name_by_value("category", $risk[0]['category']);
                        $team = get_name_by_value("team", $risk[0]['team']);
                        $technology = get_name_by_value("technology", $risk[0]['technology']);
                        $owner = get_name_by_value("user", $risk[0]['owner']);
                        $manager = get_name_by_value("user", $risk[0]['manager']);
                        $assessment = try_decrypt($risk[0]['assessment']);
                        $notes = try_decrypt($risk[0]['notes']);
			$assets = get_list_of_assets($id, false);
                        $submission_date = $risk[0]['submission_date'];
                        $mitigation_id = $risk[0]['mitigation_id'];
                        $mgmt_review = $risk[0]['mgmt_review'];
                        $calculated_risk = $risk[0]['calculated_risk'];
                        $next_review = $risk[0]['next_review'];
                        $color = get_risk_color($id);
                        $scoring_method = get_scoring_method_name($risk[0]['scoring_method']);
                        $CLASSIC_likelihood = get_name_by_value("likelihood", $risk[0]['CLASSIC_likelihood']);
                        $CLASSIC_impact = get_name_by_value("impact", $risk[0]['CLASSIC_impact']);
                        $CVSS_AccessVector = $risk[0]['CVSS_AccessVector'];
                        $CVSS_AccessComplexity = $risk[0]['CVSS_AccessComplexity'];
                        $CVSS_Authentication = $risk[0]['CVSS_Authentication'];
                        $CVSS_ConfImpact = $risk[0]['CVSS_ConfImpact'];
                        $CVSS_IntegImpact = $risk[0]['CVSS_IntegImpact'];
                        $CVSS_AvailImpact = $risk[0]['CVSS_AvailImpact'];
                        $CVSS_Exploitability = $risk[0]['CVSS_Exploitability'];
                        $CVSS_RemediationLevel = $risk[0]['CVSS_RemediationLevel'];
                        $CVSS_ReportConfidence = $risk[0]['CVSS_ReportConfidence'];
                        $CVSS_CollateralDamagePotential = $risk[0]['CVSS_CollateralDamagePotential'];
                        $CVSS_TargetDistribution = $risk[0]['CVSS_TargetDistribution'];
                        $CVSS_ConfidentialityRequirement = $risk[0]['CVSS_ConfidentialityRequirement'];
                        $CVSS_IntegrityRequirement = $risk[0]['CVSS_IntegrityRequirement'];
                        $CVSS_AvailabilityRequirement = $risk[0]['CVSS_AvailabilityRequirement'];
                        $DREAD_DamagePotential = $risk[0]['DREAD_DamagePotential'];
                        $DREAD_Reproducibility = $risk[0]['DREAD_Reproducibility'];
                        $DREAD_Exploitability = $risk[0]['DREAD_Exploitability'];
                        $DREAD_AffectedUsers = $risk[0]['DREAD_AffectedUsers'];
                        $DREAD_Discoverability = $risk[0]['DREAD_Discoverability'];
                        $OWASP_SkillLevel = $risk[0]['OWASP_SkillLevel'];
                        $OWASP_Motive = $risk[0]['OWASP_Motive'];
                        $OWASP_Opportunity = $risk[0]['OWASP_Opportunity'];
                        $OWASP_Size = $risk[0]['OWASP_Size'];
                        $OWASP_EaseOfDiscovery = $risk[0]['OWASP_EaseOfDiscovery'];
                        $OWASP_EaseOfExploit = $risk[0]['OWASP_EaseOfExploit'];
                        $OWASP_Awareness = $risk[0]['OWASP_Awareness'];
                        $OWASP_IntrusionDetection = $risk[0]['OWASP_IntrusionDetection'];
                        $OWASP_LossOfConfidentiality = $risk[0]['OWASP_LossOfConfidentiality'];
                        $OWASP_LossOfIntegrity = $risk[0]['OWASP_LossOfIntegrity'];
                        $OWASP_LossOfAvailability = $risk[0]['OWASP_LossOfAvailability'];
                        $OWASP_LossOfAccountability = $risk[0]['OWASP_LossOfAccountability'];
                        $OWASP_FinancialDamage = $risk[0]['OWASP_FinancialDamage'];
                        $OWASP_ReputationDamage = $risk[0]['OWASP_ReputationDamage'];
                        $OWASP_NonCompliance = $risk[0]['OWASP_NonCompliance'];
                        $OWASP_PrivacyViolation = $risk[0]['OWASP_PrivacyViolation'];
                        $custom = $risk[0]['Custom'];
		
			$data[] = array("id" => $id, "status" => $status, "subject" => $subject, "reference_id" => $reference_id, "regulation" => $regulation, "control_number" => $control_number, "location" => $location, "source" => $source, "category" => $category, "team" => $team, "technology" => $technology, "owner" => $owner, "manager" => $manager, "assessment" => $assessment, "notes" => $notes, "assets" => $assets, "submission_date" => $submission_date, "mitigation_id" => $mitigation_id, "mgmt_review" => $mgmt_review, "calculated_risk" => $calculated_risk, "next_review" => $next_review, "color" => $color, "scoring_method" => $scoring_method, "calculated_risk" => $calculated_risk, "CLASSIC_likelihood" => $CLASSIC_likelihood, "CLASSIC_impact" => $CLASSIC_impact, "CVSS_AccessVector" => $CVSS_AccessVector, "CVSS_AccessComplexity" => $CVSS_AccessComplexity, "CVSS_Authentication" => $CVSS_Authentication, "CVSS_ConfImpact" => $CVSS_ConfImpact, "CVSS_IntegImpact" => $CVSS_IntegImpact, "CVSS_AvailImpact" => $CVSS_AvailImpact, "CVSS_Exploitability" => $CVSS_Exploitability, "CVSS_RemediationLevel" => $CVSS_RemediationLevel, "CVSS_ReportConfidence" => $CVSS_ReportConfidence, "CVSS_CollateralDamagePotential" => $CVSS_CollateralDamagePotential, "CVSS_TargetDistribution" => $CVSS_TargetDistribution, "CVSS_ConfidentialityRequirement" => $CVSS_ConfidentialityRequirement, "CVSS_IntegrityRequirement" => $CVSS_IntegrityRequirement, "CVSS_AvailabilityRequirement" => $CVSS_AvailabilityRequirement, "DREAD_DamagePotential" => $DREAD_DamagePotential, "DREAD_Reproducibility" => $DREAD_Reproducibility, "DREAD_Exploitability" => $DREAD_Exploitability, "DREAD_AffectedUsers" => $DREAD_AffectedUsers, "DREAD_Discoverability" => $DREAD_Discoverability, "OWASP_SkillLevel" => $OWASP_SkillLevel, "OWASP_Motive" => $OWASP_Motive, "OWASP_Opportunity" => $OWASP_Opportunity, "OWASP_Size" => $OWASP_Size, "OWASP_EaseOfDiscovery" => $OWASP_EaseOfDiscovery, "OWASP_EaseOfExploit" => $OWASP_EaseOfExploit, "OWASP_Awareness" => $OWASP_Awareness, "OWASP_IntrusionDetection" => $OWASP_IntrusionDetection, "OWASP_LossOfConfidentiality" => $OWASP_LossOfConfidentiality, "OWASP_LossOfIntegrity" => $OWASP_LossOfIntegrity, "OWASP_LossOfAvailability" => $OWASP_LossOfAvailability, "OWASP_LossOfAccountability" => $OWASP_LossOfAccountability, "OWASP_FinancialDamage" => $OWASP_FinancialDamage, "OWASP_ReputationDamage" => $OWASP_ReputationDamage, "OWASP_NonCompliance" => $OWASP_NonCompliance, "OWASP_PrivacyViolation" => $OWASP_PrivacyViolation, "Custom" => $custom);

			// Return a JSON response
			json_response(200, "viewrisk", $data);
		}
		else
		{
                	// Return a JSON response
                	json_response(404, "Risk ID not found.", NULL);
		}
	}
}

/***********************************
 * FUNCTION: MANAGEMENT - ADD RISK *
 ***********************************/
function addrisk()
{

}

/******************************************
 * FUNCTION: MANAGEMENT - VIEW MITIGATION *
 ******************************************/
function viewmitigation()
{
        // If the id is not sent
        if (!isset($_GET['id']))
        {
                // Return a JSON response
                json_response(400, "You need to specify an id parameter.", NULL);
        }
}

/*****************************************
 * FUNCTION: MANAGEMENT - ADD MITIGATION *
 *****************************************/
function addmitigation()
{

}

/**************************************
 * FUNCTION: MANAGEMENT - VIEW REVIEW *
 **************************************/
function viewreview()
{
        // If the id is not sent
        if (!isset($_GET['id']))
        {
                // Return a JSON response
                json_response(400, "You need to specify an id parameter.", NULL);
        }
}

/*************************************
 * FUNCTION: MANAGEMENT - ADD REVIEW *
 *************************************/
function addreview()
{

}

?>
