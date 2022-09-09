<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/reporting.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/compliance.php'));
require_once(realpath(__DIR__ . '/governance.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/datefix.php'));
require_once(realpath(__DIR__ . '/extras.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include the language file
require_once(language_file(true));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

/******************************
 * FUNCTION: IS AUTHENTICATED *
 ******************************/
function is_authenticated()
{
    // If encryption level is 'user'
    if (api_extra())
    {
        // Require the API Extra
        require_once(realpath(__DIR__ . '/../extras/api/index.php'));
        if(!check_encryption_level()){
            echo uncomfortable_encryption_level();
            return false;
        }
    }
    
    // If we are authenticated with a key
    if (is_key_authenticated() != false)
    {
	    // Return true
	    return true;
    }
    // If we are not authenticated with a key but have an authenticated session
    else if (is_session_authenticated())
    {
	    // Return true
	    return true;
    }
    else if(check_questionnaire_get_token()) {
        return false;
    }
    // Access was not authenticated
    else
    {
	    unauthenticated_access();
    }
}

/**************************************
 * FUNCTION: IS SESSION AUTHENTICATED *
 **************************************/
function is_session_authenticated()
{
    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRisk');
        session_start();
    }

    // Check for session timeout or renegotiation
    session_check();

    // If the session is authenticated
    if (isset($_SESSION["access"]) && ($_SESSION["access"] == "1" || $_SESSION["access"] == "granted"))
    {
	    // Load CSRF Magic
	    csrf_init();

	    // Return true
	    return true;
    }
    else return false;

    /*
    // If the session is not authenticated
    if (!isset($_SESSION["access"]) || ($_SESSION["access"] != "1" && $_SESSION["access"] != "granted"))
    {
        return false;
    }
    else 
    {
        // If internal request on browser, check csrf token
        if(!isset($_GET['key']))
        {
            // Load CSRF Magic
            csrf_init();
        }
        else
        {
            if (api_extra())
            {
                require_once(realpath(__DIR__ . '/../extras/api/index.php'));
                
                if(!is_valid_key_by_uid($_GET['key'], $_SESSION['uid']))
                {
                    // Load CSRF Magic
                    csrf_init();
                }
            }
            else
            {
                // Load CSRF Magic
                csrf_init();
            }
        }

        return true;
    }
     */
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
function uncomfortable_encryption_level()
{
    global $lang, $escaper;
    // Return a JSON response
    json_response(401, $escaper->escapeHtml($lang['APIInCompatibleWithEncryptionLevel']), NULL);
}

/************************************
 * FUNCTION: UNAUTHENTICATED ACCESS *
 ************************************/
function unauthenticated_access()
{
    global $lang, $escaper;
    // Return a JSON response
    json_response(401, $escaper->escapeHtml($lang['UnauthenticatedAccessInAPI']), NULL);
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
          <li><a href="mock.php?option=get_version">/version</a> -> (print the version of the api)</li>
          <li><a href="mock.php?option=get_whoami">/whoami</a> -> (shows the currently authenticated user)</li>
        </ul>';

  // Show the management menu
  show_management();

  // Show the admin menu
  show_admin();

  // Show the reports menu
  show_reports();
  
  // Show audit log
  show_audit_log();
}

/*****************************
 * FUNCTION: SHOW MANAGEMENT *
 *****************************/
function show_management()
{
  echo '<ul>
          <li><a href="mock.php?option=get_risk_view">/management/risk/view </a> -> (view a risk)</li>
          <li><a href="mock.php?option=add_risk">/management/risk/add</a> -> (add a risk)</li>
          <li><a href="mock.php?option=update_risk">/management/risk/update</a> -> (update a risk)</li>

          <li><a href="mock.php?option=get_mitigation_view">/management/mitigation/view </a> -> (view a mitigation)</li>
          <li><a href="mock.php?option=save_mitigation">/management/mitigation/add</a> -> (add a mitigation)</li>

          <li><a href="mock.php?option=get_review_view">/management/review/view </a> -> (view a review)</li>
          <li><a href="mock.php?option=save_review">/management/review/add</a> -> (add a review)</li>
          <li><a href="mock.php?option=get_scoring_history">/management/risk/scoring_history</a> -> (view scoring history)</li>
          <li><a href="mock.php?option=get_risk_levels">/risk_levels</a> -> (get risk levels)</li>
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
          <li><a href="admin/tables/fullData">/admin/tables/fullData</a> -> (shows unfiltered table data)</li>
        </ul>';
}

/******************************
 * FUNCTION: REPORTS API LIST *
 ******************************/
function show_reports()
{
  echo '<ul>
          <li><a href="reports/dynamic">/reports/dynamic</a> -> (shows dynamic risk report)</li>
        </ul>';
}

/********************************
 * FUNCTION: AUDIT LOG API LIST *
 ********************************/
function show_audit_log()
{
  echo '<ul>
          <li><a href="mock.php?option=get_audit_logs">/audit_logs</a> -> (return audit logs)</li>
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
  return '1.1';
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
	    // Get the user id for the user
            $uid = $user['value'];

	    // Get the teams for this user
	    $teams = get_user_teams($uid);

	    // For each team
	    foreach ($teams as $key => $value)
	    {
		    // Convert the number to a name
		    $teams[$key] = get_name_by_value('team', $value);
	    }

	    // Get the user permissions
	    $permissions = get_permissions_of_user($uid);

	    // Get the role ID
	    $role_id = $user['role_id'];

	    // Get the role
	    $role = get_role($role_id);

            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login'], "teams" => $teams, "role" => $role['name'], "responsibilities" => $permissions);
        }

        // Return a JSON response
        json_response(200, "allusers", $data);
    }
    else
    {
        unauthorized_access();
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
            // Get the user id for the user
            $uid = $user['value'];

            // Get the teams for this user
            $teams = get_user_teams($uid);

            // For each team
            foreach ($teams as $key => $value)
            {
                    // Convert the number to a name
                    $teams[$key] = get_name_by_value('team', $value);
            }

            // Get the user permissions
            $permissions = get_permissions_of_user($uid);

            // Get the role ID
            $role_id = $user['role_id'];

            // Get the role
            $role = get_role($role_id);

            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login'], "teams" => $teams, "role" => $role['name'], "responsibilities" => $permissions);
        }

        // Return a JSON response
        json_response(200, "enabledusers", $data);
    }
    else
    {
        unauthorized_access();
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
            // Get the user id for the user
            $uid = $user['value'];

            // Get the teams for this user
            $teams = get_user_teams($uid);

            // For each team
            foreach ($teams as $key => $value)
            {
                    // Convert the number to a name
                    $teams[$key] = get_name_by_value('team', $value);
            }

            // Get the user permissions
            $permissions = get_permissions_of_user($uid);

            // Get the role ID
            $role_id = $user['role_id'];

            // Get the role
            $role = get_role($role_id);

            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login'], "teams" => $teams, "role" => $role['name'], "responsibilities" => $permissions);
        }

        // Return a JSON response
        json_response(200, "disabledusers", $data);
    }
    else
    {
        unauthorized_access();
    }
}

/*******************************************
 * FUNCTION: REPORTS - DYNAMIC RISK        *
 * This function is called through the API *
 *******************************************/
function dynamicrisk()
{
    global $escaper;
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
        

        $start = (isset($_GET['start']) && $_GET['start']) ? $_GET['start'] : 0;
        $length = (isset($_GET['length']) && $_GET['length']) ? $_GET['length'] : 10;
        $rowCount = "";

        // Get column filters
        $column_filters = isset($_GET['column_filters']) ? $_GET['column_filters'] : [];

        $review_levels = get_review_levels();

        // Query the risks
        $data = risks_query($status, $sort, $group, $column_filters, $rowCount, $start, $length);
        $rows = array();
        foreach($data as $risk){
            $row = array(
                "id"                    => $escaper->escapeHtml($risk['id'] + 1000),
                "status"                => $escaper->escapeHtml($risk['status']),
                "subject"               => $escaper->escapeHtml($risk['subject']),
                "reference_id"          => $escaper->escapeHtml($risk['reference_id']),
                "regulation"            => $escaper->escapeHtml($risk['regulation']),
                "control_number"        => $escaper->escapeHtml($risk['control_number']),
                "location"              => $escaper->escapeHtml($risk['location']),
                "source"                => $escaper->escapeHtml($risk['source']),
                "category"              => $escaper->escapeHtml($risk['category']),
                "team"                  => $escaper->escapeHtml($risk['team']),
                "technology"            => $escaper->escapeHtml($risk['technology']),
                "owner"                 => $escaper->escapeHtml($risk['owner']),
                "manager"               => $escaper->escapeHtml($risk['manager']),
                "submitted_by"          => $escaper->escapeHtml($risk['submitted_by']),
                "scoring_method"        => $escaper->escapeHtml($risk['scoring_method']),
                "calculated_risk"       => $escaper->escapeHtml($risk['calculated_risk']),
                "residual_risk"         => $escaper->escapeHtml($risk['residual_risk']),
                "color"                 => get_risk_color($risk['calculated_risk']),
                "residual_color"        => get_risk_color($risk['residual_risk']),
                "submission_date"       => $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))),
                "review_date"           => $escaper->escapeHtml($risk['review_date']),
                "project"               => $escaper->escapeHtml($risk['project']),
                "project_status"        => $escaper->escapeHtml($risk['project_status']),
                "mitigation_planned"    => getTextBetweenTags(planned_mitigation($risk['id'], $risk['mitigation_id']), "a") , // mitigation plan
                "management_review"     => getTextBetweenTags(management_review($risk['id'], $risk['mgmt_review'], $risk['next_review_date']), "a"), // management review
                "days_open"             => $escaper->escapeHtml($risk['days_open']),
                "next_review_date"      => $risk['next_review_date'],
                "next_step"             => $escaper->escapeHtml($risk['next_step']),
                "affected_assets"       => $risk['affected_assets'],
                "risk_assessment"       => $escaper->escapeHtml($risk['risk_assessment']),
                "additional_notes"      => $escaper->escapeHtml($risk['additional_notes']),
                "current_solution"      => $escaper->escapeHtml($risk['current_solution']),
                "security_recommendations" => $escaper->escapeHtml($risk['security_recommendations']),
                "security_requirements" => $escaper->escapeHtml($risk['security_requirements']),
                "planning_strategy"     => $escaper->escapeHtml($risk['planning_strategy']),
                "planning_date"         => $escaper->escapeHtml($risk['planning_date']),
                "mitigation_effort"     => $escaper->escapeHtml($risk['mitigation_effort']),
                "mitigation_cost"       => $escaper->escapeHtml($risk['mitigation_cost']),
                "mitigation_owner"      => $escaper->escapeHtml($risk['mitigation_owner']),
                "mitigation_team"       => $escaper->escapeHtml($risk['mitigation_team']),
                "mitigation_accepted"   => $escaper->escapeHtml($risk['mitigation_accepted']),
                "closure_date"          => $escaper->escapeHtml($risk['closure_date']),
                "mitigation_date"       => $escaper->escapeHtml($risk['mitigation_date']),
                "mitigation_control_names" => $escaper->escapeHtml($risk['mitigation_control_names']),
                "risk_tags"             => $escaper->escapeHtml($risk['risk_tags']),
            );
            $rows[] = $row;
        }

        // Return a JSON response
        json_response(200, "dynamicrisk", $rows);
    }
}

/************************************
 * FUNCTION: MANAGEMENT - VIEW RISK *
 ************************************/
function viewrisk() {
    global $lang, $escaper;

    if (!check_permission("riskmanagement")) {
        global $escaper, $lang;
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
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
            $regulation = get_name_by_value("frameworks", $risk[0]['regulation']);
            $control_number = $risk[0]['control_number'];
            $location = $risk[0]['location_names'];
            $source = get_name_by_value("source", $risk[0]['source']);
            $category = get_name_by_value("category", $risk[0]['category']);
            $team = $risk[0]['team_names'];
            $technology = $risk[0]['technology_names'];
            $additional_stakeholders = $risk[0]['additional_stakeholder_names'];
            $owner = get_name_by_value("user", $risk[0]['owner']);
            $manager = get_name_by_value("user", $risk[0]['manager']);
            $assessment = try_decrypt($risk[0]['assessment']);
            $notes = try_decrypt($risk[0]['notes']);
            $assets = array_map(function($item) { return array('name'=>$item['name'], 'type'=>$item['class']); }, get_assets_and_asset_groups_of_type($id, 'risk'));
            $tags = $risk[0]['risk_tags'];
            $submission_date = $risk[0]['submission_date'];
            $mitigation_id = $risk[0]['mitigation_id'];
            $mgmt_review = $risk[0]['mgmt_review'];
            $calculated_risk = $risk[0]['calculated_risk'];
            $next_review = $risk[0]['next_review'];
            $color = get_risk_color($calculated_risk);
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
            $ContributingLikelihood = $risk[0]['Contributing_Likelihood'];
            $contributing_risks_impacts = $risk[0]['Contributing_Risks_Impacts'];
            if($contributing_risks_impacts){
                $ContributingImpacts = get_contributing_impacts_by_subjectimpact_values($contributing_risks_impacts);
            }else{
                $ContributingImpacts = [];
            }

            // Get closures
            $closures = get_close_by_id($id);
            if($closures)
                $closure_date = $closures[0]['closure_date'];
            else
                $closure_date = "";

            $risk = array(
                "id" => $id,
                "status" => $status,
                "subject" => $subject,
                "reference_id" => $reference_id,
                "regulation" => $regulation,
                "control_number" => $control_number,
                "location" => $location, "source" => $source,
                "category" => $category, 
                "team" => $team,
                "technology" => $technology,
                "additional_stakeholders" => $additional_stakeholders,
                "owner" => $owner,
                "manager" => $manager,
                "assessment" => $assessment,
                "notes" => $notes,
                "affected_assets" => $assets,
                "submission_date" => $submission_date,
                "mitigation_id" => $mitigation_id,
                "mgmt_review" => $mgmt_review,
                "calculated_risk" => $calculated_risk,
                "next_review" => $next_review,
                "color" => $color,
                "scoring_method" => $scoring_method,
                "calculated_risk" => $calculated_risk,
                "tags" => $tags,
                "CLASSIC_likelihood" => $CLASSIC_likelihood,
                "CLASSIC_impact" => $CLASSIC_impact,
                "CVSS_AccessVector" => $CVSS_AccessVector,
                "CVSS_AccessComplexity" => $CVSS_AccessComplexity,
                "CVSS_Authentication" => $CVSS_Authentication,
                "CVSS_ConfImpact" => $CVSS_ConfImpact,
                "CVSS_IntegImpact" => $CVSS_IntegImpact,
                "CVSS_AvailImpact" => $CVSS_AvailImpact,
                "CVSS_Exploitability" => $CVSS_Exploitability,
                "CVSS_RemediationLevel" => $CVSS_RemediationLevel,
                "CVSS_ReportConfidence" => $CVSS_ReportConfidence,
                "CVSS_CollateralDamagePotential" => $CVSS_CollateralDamagePotential,
                "CVSS_TargetDistribution" => $CVSS_TargetDistribution,
                "CVSS_ConfidentialityRequirement" => $CVSS_ConfidentialityRequirement,
                "CVSS_IntegrityRequirement" => $CVSS_IntegrityRequirement,
                "CVSS_AvailabilityRequirement" => $CVSS_AvailabilityRequirement,
                "DREAD_DamagePotential" => $DREAD_DamagePotential,
                "DREAD_Reproducibility" => $DREAD_Reproducibility,
                "DREAD_Exploitability" => $DREAD_Exploitability,
                "DREAD_AffectedUsers" => $DREAD_AffectedUsers,
                "DREAD_Discoverability" => $DREAD_Discoverability,
                "OWASP_SkillLevel" => $OWASP_SkillLevel,
                "OWASP_Motive" => $OWASP_Motive,
                "OWASP_Opportunity" => $OWASP_Opportunity,
                "OWASP_Size" => $OWASP_Size,
                "OWASP_EaseOfDiscovery" => $OWASP_EaseOfDiscovery,
                "OWASP_EaseOfExploit" => $OWASP_EaseOfExploit,
                "OWASP_Awareness" => $OWASP_Awareness,
                "OWASP_IntrusionDetection" => $OWASP_IntrusionDetection,
                "OWASP_LossOfConfidentiality" => $OWASP_LossOfConfidentiality,
                "OWASP_LossOfIntegrity" => $OWASP_LossOfIntegrity,
                "OWASP_LossOfAvailability" => $OWASP_LossOfAvailability,
                "OWASP_LossOfAccountability" => $OWASP_LossOfAccountability,
                "OWASP_FinancialDamage" => $OWASP_FinancialDamage,
                "OWASP_ReputationDamage" => $OWASP_ReputationDamage,
                "OWASP_NonCompliance" => $OWASP_NonCompliance,
                "OWASP_PrivacyViolation" => $OWASP_PrivacyViolation,
                "Custom" => $custom,
                "ContributingLikelihood" => $ContributingLikelihood,
                "ContributingImpacts" => $ContributingImpacts,
                "closure_date" => $closure_date
            );

            // If customization extra is enabled
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                // Save custom fields
                $risk["custom_values"] = getCustomFieldValuesByRiskId($id, 1);
            }

            $data[] = $risk;

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

/******************************************
 * FUNCTION: MANAGEMENT - VIEW MITIGATION *
 ******************************************/
function viewmitigation() {
    
    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        // Return a JSON response
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
    }
    $risk_id = $_GET['id'];
    $mitigation = get_mitigation_by_id($risk_id);

    if(!isset($mitigation[0])){
        // Return a JSON response
        json_response(400, $escaper->escapeHtml($lang['NoMitigation']), NULL);
    }

    $mitigation = $mitigation[0];
    $supporting_files = get_supporting_files($risk_id, 2);
    $mitigation['supporting_files'] = array();
    foreach($supporting_files as $supporting_file){
        $mitigation['supporting_files'][] = $_SESSION['base_url']."/management/download.php?id=" . $escaper->escapeHtml($supporting_file['unique_name']);
    }

    $data = array(
        "submission_date"=> $mitigation['submission_date'],
        "planning_date"=> $mitigation['planning_date'],
        "planning_strategy"=> $mitigation['planning_strategy'],
        "planning_strategy_name"=> $mitigation['planning_strategy_name'],
        "mitigation_effort"=> $mitigation['mitigation_effort'],
        "mitigation_effort_name"=> $mitigation['mitigation_effort_name'],
        "mitigation_cost"=> $mitigation['mitigation_cost'],
        "mitigation_min_cost"=> $mitigation['mitigation_min_cost'],
        "mitigation_max_cost"=> $mitigation['mitigation_max_cost'],
        "mitigation_owner"=> $mitigation['mitigation_owner'],
        "mitigation_owner_name"=> $mitigation['mitigation_owner_name'],
        "mitigation_team"=> $mitigation['mitigation_team'],
        "mitigation_team_name"=> $mitigation['mitigation_team_name'],
        "current_solution"=> $mitigation['current_solution'],
        "security_requirements"=> $mitigation['security_requirements'],
        "security_recommendations"=> $mitigation['security_recommendations'],
        "submitted_by"=> $mitigation['submitted_by'],
        "submitted_by_name"=> $mitigation['submitted_by_name'],
        "supporting_files"=> $mitigation['supporting_files']
    );

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // Save custom fields
        $data["custom_values"] = getCustomFieldValuesByRiskId($risk_id, 2);
    }

    json_response(200, "Mitigation View", $data);
}

/**************************************
 * FUNCTION: MANAGEMENT - VIEW REVIEW *
 **************************************/
function viewreview() {
    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        // Return a JSON response
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
    }

    $risk_id = $_GET['id'];
    $review = get_review_by_id($risk_id);

    if(!isset($review[0])){
        // Return a JSON response
        json_response(400, $escaper->escapeHtml($lang['NoReview']), NULL);
    }
    $review = $review[0];
    $risk = get_risk_by_id($risk_id);
    $risk = $risk[0];
    $risk_level = get_risk_level_name($risk['calculated_risk']);
    $residual_risk_level = get_risk_level_name($risk['residual_risk']);

    // If next_review_date_uses setting is Residual Risk.
    if(get_setting('next_review_date_uses') == "ResidualRisk")
    {
        $next_review = next_review($residual_risk_level, $risk_id-1000, $risk['next_review'], false);
    }
    // If next_review_date_uses setting is Inherent Risk.
    else
    {
        $next_review = next_review($risk_level, $risk_id-1000, $risk['next_review'], false);
    }

    $data = array(
        "submission_date"=> $review['submission_date'],
        "reviewer"=> $review['reviewer'],
        "review"=> $review['review'],
        "next_step"=> $review['next_step'],
        "next_review"=> $next_review,
        "comments"=> $review['comments']
    );

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // Save custom fields
        $data["custom_values"] = getCustomFieldValuesByRiskId($risk_id, 3, $review['id']);
    }

    json_response(200, "Review View", $data);
}


/************************************
 * FUNCTION: REPORTS - DYNAMIC RISK *
 ************************************/
function dynamicriskForm()
{
    global $escaper;
    
    // If the status, sort, and group are not sent
    if ((!isset($_REQUEST['status']) || !isset($_REQUEST['sort']) || !isset($_REQUEST['group'])) && !isset($_REQUEST['risks_by_team']))
    {
        set_alert(true, "bad", "You need to specify a status, sort, and group parameter.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    else
    {
        // Get the status, sort, and group
        $draw   = $escaper->escapeHtml($_POST['draw']);
        $status = isset($_POST['status']) ? $_POST['status'] : 0;
        $sort   = isset($_POST['sort']) ? $_POST['sort'] : 0;
        $group  = isset($_POST['group']) ? $_POST['group'] : 0;

        $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
        $length = $_POST['length'] ? (int)$_POST['length'] : 10;
        $group_value_from_db = $_POST['group_value'] ? $_POST['group_value'] : "";
        $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";

        // Sanitizing input
        $orderColumnName = !empty($_POST['columns'][$orderColumnIndex]['name']) && preg_match('/^[a-zA-Z0-9_]+$/', $_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
        // Sanitizing input
        $orderDir = !empty($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        // Params in risks_by_teams page
        $risks_by_team = isset($_POST['risks_by_team']) ? true : false;
        $teams = isset($_POST['teams']) ? $_POST['teams'] : [];
        $owners = isset($_POST['owners']) ? $_POST['owners'] : [];
        $ownersmanagers = isset($_POST['ownersmanagers']) ? $_POST['ownersmanagers'] : [];
        
        // Get column filters
        $column_filters = isset($_POST['columnFilters']) ? $_POST['columnFilters'] : [];

        $table_columns = isset($_POST['table_columns']) ? $_POST['table_columns'] : [];

        $risk_levels = get_risk_levels();

        $rowCount = 0;
        // Query the risks
        $risks = risks_query($status, $sort, $group, $column_filters, $rowCount, $start, $length, $group_value_from_db, "", [], $orderColumnName, $orderDir, $risks_by_team, $teams, $owners, $ownersmanagers);

        $datas = array();
        foreach($risks as $row){
            $row['id'] = (int)$row['id'] + 1000;

            $tags = "";
            if ($row['risk_tags']) {
                foreach(str_getcsv($row['risk_tags']) as $tag) {
                    $tags .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin: 1px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
                }
            }
            $data_row = [];
            foreach ($table_columns as $column) {
                if(stripos($column, "custom_field_") === false){
                    switch ($column) {
                        default:
                            if(array_key_exists($column, $row)) {
                                $data_row[] = $escaper->escapeHtml($row[$column]);
                            } else {
                                $data_row[] = "";
                            }
                            break;
                        case 'id':
                            $data_row[] = "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($row['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($row['id'])."</a>";
                            break;
                        case 'risk_status':
                            $data_row[] = $escaper->escapeHtml($row['status']);
                            break;
                        case 'closure_date':
                            $data_row[] = $escaper->escapeHtml(format_datetime($row['closure_date'], "", "H:i"));
                            break;
                        case 'risk_tags':
                            $data_row[] = $tags;
                            break;
                        case 'submission_date':
                            $data_row[] = $escaper->escapeHtml(format_datetime($row['submission_date'], "", "H:i"));
                            break;
                        case 'affected_assets':
                            $data_row[] = "<div class='affected-asset-cell'>{$row['affected_assets']}</div>";
                            break;
                        case 'mitigation_planned':
                            $data_row[] = planned_mitigation($row['id'], $row['mitigation_id']);
                            break;
                        case 'management_review':
                            $data_row[] = management_review($row['id'], $row['mgmt_review'], $row['next_review_date']);
                            break;
                        case "calculated_risk":
                        case "calculated_risk_30":
                        case "calculated_risk_60":
                        case "calculated_risk_90":
                        case "residual_risk":
                        case "residual_risk_30":
                        case "residual_risk_60":
                        case "residual_risk_90":
                            $color = get_risk_color_from_levels($row[$column], $risk_levels);
                            $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($row[$column]) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>";
                            break;
                    }
                } else if(customization_extra()) {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                    $field_id = str_replace("custom_field_", "", $column);
                    $custom_values = getCustomFieldValuesByRiskId($row['id']);
                    $custom_data_row = "";
                    foreach($custom_values as $custom_value)
                    {
                        // Check if this custom value is for the active field
                        if($custom_value['field_id'] == $field_id){
                            $custom_data_row = get_custom_field_name_by_value($field_id, $custom_value['field_type'], $custom_value['encryption'], $custom_value['value']);
                            break;
                        }
                    }
                    $data_row[] = $custom_data_row;
                    $row["custom_field_".$field_id] = strip_tags($custom_data_row);
                }
            }
            $data_row['risk'] = utf8ize($row);
            $datas[] = $data_row;
        }
        if(($pos = stripos($orderColumnName, "custom_field_")) !== false){
            usort($datas, function($a, $b) use ($orderDir, $orderColumnName){
                // For identical custom fields we're sorting on the id, so the results' order is not changing
                if ($a['risk'][$orderColumnName] === $b['risk'][$orderColumnName]) {
                    return (int)$a['risk']['id'] - (int)$b['risk']['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a['risk'][$orderColumnName], $b['risk'][$orderColumnName]);
                } else {
                    return strcmp($b['risk'][$orderColumnName], $a['risk'][$orderColumnName]);
                }
            });
        }

        $results = array(
            "draw" => $draw,
            "recordsTotal" => $rowCount,
            "recordsFiltered" => $rowCount,
            "data" => $datas
        );

        // Return a JSON response
        echo json_encode($results);
    }
}

/*******************************************************
 * FUNCTION: DYNAMIC RISK UNIQUE DATA FOR EACH COLUMNS *
 *******************************************************/
function dynamicriskUniqueColumnDataAPI()
{
    global $escaper, $lang;
    
    // If the status, sort, and group are not sent
    if ((!isset($_REQUEST['status']) || !isset($_REQUEST['group'])) && !isset($_REQUEST['risks_by_team']))
    {
        set_alert(true, "bad", "You need to specify a status, sort, and group parameter.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    else
    {
        // Get the status, sort, and group
        $status = isset($_POST['status']) ? $_POST['status'] : 0;
        $group  = isset($_POST['group']) ? $_POST['group'] : 0;
        
        $group_value_from_db = $_POST['group_value'] ? $_POST['group_value'] : "";
        
        // Params in risks_by_teams page
        $risks_by_team = isset($_POST['risks_by_team']) ? true : false;

        // Query the risks
        $risks = get_dynamicrisk_unique_column_data($status, $group, $group_value_from_db);

        $datas = array();
        
        // If Customization Extra is true, include custom extra
        if(customization_extra())
        {
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            $custom_extra = true;
            $active_fields = get_all_fields();
        }
        else
        {
            $custom_extra = false;
        }
        
        $uniqueColumns = [];
        
        $risk_ids = [];
        foreach($risks as $row){
            $risk_ids[] = $row['id'];
            foreach($row as $key=>$value)
            {
                $key = strtolower($key);

                if(isset($uniqueColumns[$key]))
                {
                    $uniqueColumns[$key][] = $value;
                }
                else
                {
                    $uniqueColumns[$key] = [$value];
                }
            }
        }
        
        $uniqueColumns = array_map("array_values", array_map("array_unique", $uniqueColumns));

        $delimiter = "---";
        
        $decrypted_unique_names = [];

        $results = [];
        foreach($uniqueColumns as $key => $uniqueColumnArr)
        {
            $continue = false;
            
            switch($key)
            {
                case "regulation":
                case "project":
                    $uniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, ',', $delimiter, true);
                break;
                case "location":
                    $uniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, ',', $delimiter);
                break;
                case "risk_tags":
                    $uniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, ',', $delimiter);
                break;
                // columns splitted by ","
                case "risk_status":
                case "source":
                case "category":
                case "team":
                case "additional_stakeholders":
                case "technology":
                case "owner":
                case "manager":
                case "submitted_by":
                case "closed_by":
                case "close_reason":
                case "regulation":
                case "next_step":
                case "planning_strategy":
                case "mitigation_cost":
                case "mitigation_owner":
                case "mitigation_team":
                case "mitigation_controls":
                case "risk_mapping":
                case "threat_mapping":
                    $uniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, ',', $delimiter);
                break;
                case "affected_assets":
                    $affectedAssetsUniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, ',', $delimiter, true);
                    // Set asset data class
                    $affectedAssetsUniqueColumnArr = array_map(function($arr){
                        $arr['class'] = "asset";
                        $arr['value'] .= "-asset";
                        return $arr;
                    }, $affectedAssetsUniqueColumnArr);
                    
                    $affectedAssetGroupsUniqueColumnArr = get_name_value_array_from_text_array($uniqueColumns['affected_asset_groups'], ',', $delimiter);
                    // Set group data class
                    $affectedAssetGroupsUniqueColumnArr = array_map(function($arr){
                        $arr['class'] = "group";
                        $arr['value'] .= "-group";
                        return $arr;
                    }, $affectedAssetGroupsUniqueColumnArr);

                    $affectedAssetGroupsUniqueArr = [];
                    foreach($affectedAssetsUniqueColumnArr as $arr){
                        $asset_id = base64_decode(trim(str_replace("-asset", "", $arr["value"])));
                        $groups = get_asset_groups_from_asset($asset_id);
                        foreach($groups as $arr_group){
                            $affectedAssetGroupsUniqueArr[] = array(
                                "value" => base64_encode($arr_group["id"])."-group",
                                "text" => $escaper->escapeHtml($arr_group["name"]),
                                "class" => "group"
                            );
                        }
                    }
                    $affectedAssetGroupsUniqueColumnArr = array_merge($affectedAssetGroupsUniqueColumnArr,$affectedAssetGroupsUniqueArr);
                    $affectedAssetGroupsUniqueColumnArr = array_map("unserialize", array_unique(array_map("serialize", $affectedAssetGroupsUniqueColumnArr)));

                    $uniqueColumnArr = array_merge($affectedAssetsUniqueColumnArr, $affectedAssetGroupsUniqueColumnArr); 
                break;
                case "scoring_method":
                    $uniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, ',', $delimiter);
                    
                    $uniqueColumnArr = array_map(function($text_value_arr) use($escaper){
                        $text = $escaper->escapeHtml(get_scoring_method_name(base64_decode($text_value_arr['value'])));
                        $value = $text_value_arr['value'];
                        return ["text"=>$text, "value"=>$value];
                    }, $uniqueColumnArr);
                break;
                case "mitigation_effort":
                    $uniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, ',', $delimiter, false, true);
                break;
                default: 
                    $continue = true;
                break;
            }
            
            if(!empty($continue)) continue;
            
            $results[$key] = array_values($uniqueColumnArr);
        }

        $results["mitigation_planned"] = array(
                [
                    "value" => base64_encode(1),
                    "text" => $escaper->escapeHtml($lang['Yes']),
                ],
                [
                    "value" => base64_encode(2),
                    "text" => $escaper->escapeHtml($lang['No']),
                ],
            );

        $results["mitigation_accepted"] = array(
                [
                    "value" => base64_encode(1),
                    "text" => $escaper->escapeHtml($lang['Yes']),
                ],
                [
                    "value" => base64_encode(2),
                    "text" => $escaper->escapeHtml($lang['No']),
                ],
            );

        $results["management_review"] = array(
                [
                    "value" => base64_encode(1),
                    "text" => $escaper->escapeHtml($lang['Yes']),
                ],
                [
                    "value" => base64_encode(2),
                    "text" => $escaper->escapeHtml($lang['No']),
                ],
                [
                    "value" => base64_encode(3),
                    "text" => $escaper->escapeHtml($lang['PASTDUE']),
                ],
            );

        // If customization extra is enabled, add custom fields
        if($custom_extra)
        {
            foreach($active_fields as $active_field)
            {
                // If this is custom field and it is dropdown field, set unique column
                if($active_field['is_basic'] == 0)
                {
                    if(in_array($active_field['type'], ["dropdown", "multidropdown", "user_multidropdown"]))
                    {
                        $results['custom_field_'.$active_field['id']] = get_name_value_array_for_custom_field($active_field['id'], $active_field['type'], $risk_ids);
                    }
                    elseif($active_field['type'] == "date")
                    {
                        $results['custom_field_'.$active_field['id']] = ["field_type" => "date"];
                    }
                }
            }
        }

        // Return a JSON response
        echo json_encode($results);
    }
    
}

/**
* Get html of tab container
*
* @param mixed $id : risk ID
* @param mixed $template : template php name
*/
function getTabHtml($id, $template){
    global $lang, $escaper;

     // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        if (!extra_grant_access($_SESSION['uid'], $id))
        {
            // Do not allow the user to update the risk
            $access = false;
        }
        // Otherwise, allow the user to update the risk
        else $access = true;
    }
    // Otherwise, allow the user to update the risk
    else $access = true;

    // Get the details of the risk
    $risk = get_risk_by_id($id);

    // If the risk was found use the values for the risk
    if (count($risk) != 0)
    {
        $submitted_by = $risk[0]['submitted_by'];
        $status = $risk[0]['status'];
        $subject = $risk[0]['subject'];
        $reference_id = $risk[0]['reference_id'];
        $regulation = $risk[0]['regulation'];
        $control_number = $risk[0]['control_number'];
        $location = $risk[0]['location'];
        $source = $risk[0]['source'];
        $category = $risk[0]['category'];
        $team = $risk[0]['team'];
        $additional_stakeholders = $risk[0]['additional_stakeholders'];
        $technology = $risk[0]['technology'];
        $owner = $risk[0]['owner'];
        $manager = $risk[0]['manager'];
        $assessment = $risk[0]['assessment'];
        $notes = $risk[0]['notes'];
        $jira_issue_key = jira_extra() ? $risk[0]['jira_issue_key'] : "";
        $submission_date = $risk[0]['submission_date'];
        $risk_tags = $risk[0]['risk_tags'];
        $mitigation_id = $risk[0]['mitigation_id'];
        $mgmt_review = $risk[0]['mgmt_review'];
        $calculated_risk = $risk[0]['calculated_risk'];
        $residual_risk = $risk[0]['residual_risk'];
        $next_review = $risk[0]['next_review'];
        $color = get_risk_color($calculated_risk);
        $risk_level = get_risk_level_name($calculated_risk);
        $residual_risk_level = get_risk_level_name($residual_risk);

        $scoring_method = $risk[0]['scoring_method'];
        $CLASSIC_likelihood = $risk[0]['CLASSIC_likelihood'];
        $CLASSIC_impact = $risk[0]['CLASSIC_impact'];
        $AccessVector = $risk[0]['CVSS_AccessVector'];
        $AccessComplexity = $risk[0]['CVSS_AccessComplexity'];
        $Authentication = $risk[0]['CVSS_Authentication'];
        $ConfImpact = $risk[0]['CVSS_ConfImpact'];
        $IntegImpact = $risk[0]['CVSS_IntegImpact'];
        $AvailImpact = $risk[0]['CVSS_AvailImpact'];
        $Exploitability = $risk[0]['CVSS_Exploitability'];
        $RemediationLevel = $risk[0]['CVSS_RemediationLevel'];
        $ReportConfidence = $risk[0]['CVSS_ReportConfidence'];
        $CollateralDamagePotential = $risk[0]['CVSS_CollateralDamagePotential'];
        $TargetDistribution = $risk[0]['CVSS_TargetDistribution'];
        $ConfidentialityRequirement = $risk[0]['CVSS_ConfidentialityRequirement'];
        $IntegrityRequirement = $risk[0]['CVSS_IntegrityRequirement'];
        $AvailabilityRequirement = $risk[0]['CVSS_AvailabilityRequirement'];
        $DREADDamagePotential = $risk[0]['DREAD_DamagePotential'];
        $DREADReproducibility = $risk[0]['DREAD_Reproducibility'];
        $DREADExploitability = $risk[0]['DREAD_Exploitability'];
        $DREADAffectedUsers = $risk[0]['DREAD_AffectedUsers'];
        $DREADDiscoverability = $risk[0]['DREAD_Discoverability'];
        $OWASPSkillLevel = $risk[0]['OWASP_SkillLevel'];
        $OWASPMotive = $risk[0]['OWASP_Motive'];
        $OWASPOpportunity = $risk[0]['OWASP_Opportunity'];
        $OWASPSize = $risk[0]['OWASP_Size'];
        $OWASPEaseOfDiscovery = $risk[0]['OWASP_EaseOfDiscovery'];
        $OWASPEaseOfExploit = $risk[0]['OWASP_EaseOfExploit'];
        $OWASPAwareness = $risk[0]['OWASP_Awareness'];
        $OWASPIntrusionDetection = $risk[0]['OWASP_IntrusionDetection'];
        $OWASPLossOfConfidentiality = $risk[0]['OWASP_LossOfConfidentiality'];
        $OWASPLossOfIntegrity = $risk[0]['OWASP_LossOfIntegrity'];
        $OWASPLossOfAvailability = $risk[0]['OWASP_LossOfAvailability'];
        $OWASPLossOfAccountability = $risk[0]['OWASP_LossOfAccountability'];
        $OWASPFinancialDamage = $risk[0]['OWASP_FinancialDamage'];
        $OWASPReputationDamage = $risk[0]['OWASP_ReputationDamage'];
        $OWASPNonCompliance = $risk[0]['OWASP_NonCompliance'];
        $OWASPPrivacyViolation = $risk[0]['OWASP_PrivacyViolation'];
        $custom = $risk[0]['Custom'];
        $risk_catalog_mapping = $risk[0]['risk_catalog_mapping'];
        $threat_catalog_mapping = $risk[0]['threat_catalog_mapping'];
        $template_group_id = $risk[0]['template_group_id'];
        
        $ContributingLikelihood = $risk[0]['Contributing_Likelihood'];
        $contributing_risks_impacts = $risk[0]['Contributing_Risks_Impacts'];
        if($contributing_risks_impacts){
            $ContributingImpacts = get_contributing_impacts_by_subjectimpact_values($contributing_risks_impacts);
        }else{
            $ContributingImpacts = [];
        }
        $display_risk = true;
    }
    // If the risk was not found use null values
    else
    {
        $submitted_by = "";
        // If Risk ID exists.
        if(check_risk_by_id($id)){
            $status = $lang["RiskDisplayPermission"];
        }
        // If Risk ID does not exist.
        else{
            $status = $lang["RiskIdDoesNotExist"];
        }
        $subject = "N/A";
        $reference_id = "N/A";
        $regulation = "";
        $control_number = "N/A";
        $location = "";
        $source = "";
        $category = "";
        $team = "";
        $additional_stakeholders = "";
        $technology = "";
        $owner = "";
        $manager = "";
        $assessment = "";
        $notes = "";
        $jira_issue_key = "";
        $submission_date = "";
        $risk_tags = "";
        $mitigation_id = "";
        $mgmt_review = "";
        $calculated_risk = "0.0";
        $next_review = "";
        $color = "";
        $risk_level = "";

        $scoring_method = "";
        $CLASSIC_likelihood = "";
        $CLASSIC_impact = "";
        $AccessVector = "";
        $AccessComplexity = "";
        $Authentication = "";
        $ConfImpact = "";
        $IntegImpact = "";
        $AvailImpact = "";
        $Exploitability = "";
        $RemediationLevel = "";
        $ReportConfidence = "";
        $CollateralDamagePotential = "";
        $TargetDistribution = "";
        $ConfidentialityRequirement = "";
        $IntegrityRequirement = "";
        $AvailabilityRequirement = "";
        $DREADDamagePotential = "";
        $DREADReproducibility = "";
        $DREADExploitability = "";
        $DREADAffectedUsers = "";
        $DREADDiscoverability = "";
        $OWASPSkillLevel = "";
        $OWASPMotive = "";
        $OWASPOpportunity = "";
        $OWASPSize = "";
        $OWASPEaseOfDiscovery = "";
        $OWASPEaseOfExploit = "";
        $OWASPAwareness = "";
        $OWASPIntrusionDetection = "";
        $OWASPLossOfConfidentiality = "";
        $OWASPLossOfIntegrity = "";
        $OWASPLossOfAvailability = "";
        $OWASPLossOfAccountability = "";
        $OWASPFinancialDamage = "";
        $OWASPReputationDamage = "";
        $OWASPNonCompliance = "";
        $OWASPPrivacyViolation = "";
        $custom = "";
        $risk_catalog_mapping = "";
        $threat_catalog_mapping = "";
        $template_group_id = "";
        
        $ContributingLikelihood = "";
        $ContributingImpacts = [];
        $display_risk = false;
    }

    // If the current scoring method was changed to Classic
    if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 1 && $access)
    {
        // Set the new scoring method
        $scoring_method = change_scoring_method($id, "1");

        // Update the classic score
        $calculated_risk = update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact);

        // Display an alert
        set_alert(true, "good", "The scoring method has been successfully changed to Classic.");
    }
    // If the current scoring method was changed to CVSS
    else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 2 && $access)
    {
        // Set the new scoring method
        $scoring_method = change_scoring_method($id, "2");

        // Update the cvss score
        $calculated_risk = update_cvss_score($id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);

        // Display an alert
        set_alert(true, "good", "The scoring method has been successfully changed to CVSS.");
    }
    // If the current scoring method was changed to DREAD
    else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 3 && $access)
    {
        // Set the new scoring method
        $scoring_method = change_scoring_method($id, "3");

        // Update the dread score
        $calculated_risk = update_dread_score($id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);

        // Display an alert
        set_alert(true, "good", "The scoring method has been successfully changed to DREAD.");
    }
    // If the current scoring method was changed to OWASP
    else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 4 && $access)
    {
        // Set the new scoring method
        $scoring_method = change_scoring_method($id, "4");

        // Update the owasp score
        $calculated_risk = update_owasp_score($id, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);

        // Display an alert
        set_alert(true, "good", "The scoring method has been successfully changed to OWASP.");
    }
    // If the current scoring method was changed to Custom
    else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 5 && $access)
    {
        // Set the new scoring method
        $scoring_method = change_scoring_method($id, "5");

        // Update the custom score
        $calculated_risk = update_custom_score($id, $custom);

        // Display an alert
        set_alert(true, "good", "The scoring method has been successfully changed to Custom.");
    }
    // If the current scoring method was changed to Contributing Risk
    else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 6 && $access)
    {
        // Set the new scoring method
        $scoring_method = change_scoring_method($id, "6");

        // Update the custom score
        $calculated_risk = update_contributing_risk_score($id, $ContributingLikelihood, $ContributingImpacts);

        // Display an alert
        set_alert(true, "good", "The scoring method has been successfully changed to Contributing Risk.");
    }

    if ($submission_date == "")
    {
        $submission_date = "N/A";
    }
    else $submission_date = format_date($submission_date);

    // Get the mitigation for the risk
    $mitigation = get_mitigation_by_id($id);

    // If a mitigation exists for the risk and the user is allowed to access
    if ($mitigation == true && $access)
    {
        // Set the mitigation values
        $mitigation_date    = format_date($mitigation[0]['submission_date']);
        $planning_strategy  = $mitigation[0]['planning_strategy'];
        $mitigation_effort  = $mitigation[0]['mitigation_effort'];
        $mitigation_cost    = $mitigation[0]['mitigation_cost'];
        $mitigation_owner   = $mitigation[0]['mitigation_owner'];
        $mitigation_team    = $mitigation[0]['mitigation_team'];
        $current_solution   = $mitigation[0]['current_solution'];
        $security_requirements      = $mitigation[0]['security_requirements'];
        $security_recommendations   = $mitigation[0]['security_recommendations'];
        $planning_date      = format_date($mitigation[0]['planning_date']);
        $mitigation_percent = (isset($mitigation[0]['mitigation_percent']) && $mitigation[0]['mitigation_percent'] >= 0 && $mitigation[0]['mitigation_percent'] <= 100) ? $mitigation[0]['mitigation_percent'] : 0;
        $mitigation_controls = isset($mitigation[0]['mitigation_controls']) ? $mitigation[0]['mitigation_controls'] : "";
    }
    // Otherwise
    else
    {
        // Set the values to empty
        $mitigation_date    = "";
        $planning_strategy  = "";
        $mitigation_effort  = "";
        $mitigation_cost    = 1;
        $mitigation_owner   = $owner;
        $mitigation_team    = $team;
        $current_solution   = "";
        $security_requirements      = "";
        $security_recommendations   = "";
        $planning_date      = "";
        $mitigation_percent = 0;
        $mitigation_controls = "";
    }

    // Get the management reviews for the risk
    $mgmt_reviews = get_review_by_id($id);

    // If a mitigation exists for this risk and the user is allowed to access
    if ($mgmt_reviews && $access)
    {
        // Set the mitigation values
        $review_date = $mgmt_reviews[0]['submission_date'];
        $review_date = date(get_default_datetime_format("g:i A T"), strtotime($review_date));

        $review = $mgmt_reviews[0]['review'];
        $review_id = $mgmt_reviews[0]['id'];
        $next_step = $mgmt_reviews[0]['next_step'];

        // If next_review_date_uses setting is Residual Risk.
        if(get_setting('next_review_date_uses') == "ResidualRisk")
        {
            $next_review = next_review($residual_risk_level, ($id-1000), $next_review, false, false);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $next_review = next_review($risk_level, ($id-1000), $next_review, false, false);
        }

        $reviewer = $mgmt_reviews[0]['reviewer'];
        $comments = $mgmt_reviews[0]['comments'];
    }else
    // Otherwise
    {
        // Set the values to empty
        $review_date = "N/A";
        $review_id = 0;
        $review = "";
        $next_step = "";
        $next_review = "";
        $reviewer = "";
        $comments = "";
    }
//    $default_next_review = get_next_review_default($risk_id);

    $isAjax = true;

    $action = isset($_GET['action']) ? $_GET['action'] : "";
    ob_start();

    include(realpath(__DIR__ . "/../management/partials/{$template}.php"));
    $viewhtml = ob_get_contents();
    ob_end_clean();

    $viewhtml = addCSRTToken($viewhtml);

    return $viewhtml;
}

function addCSRTToken($html){

    /****** create csrf token ******/
    $tokens = csrf_get_tokens();
    $name = $GLOBALS['csrf']['input-name'];
    $endslash = $GLOBALS['csrf']['xhtml'] ? ' /' : '';
    $input = "<input type='hidden' name='$name' value=\"$tokens\"$endslash>";
    $html = preg_replace('#(<form[^>]*method\s*=\s*["\']post["\'][^>]*>)#i', '$1' . $input, $html);
    /****** end csrf token ******/

    return $html;
}

/************************************
 * FUNCTION: MANAGEMENT - VIEW RISK HTML*
 ************************************/
function viewriskHtmlForm()
{
    global $lang, $escaper;
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    // Test that the ID is a numeric value
    $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

    $viewhtml = getTabHtml($id, 'viewhtml');
    

    json_response(200, get_alert(true), $viewhtml);
}


/*************************************
 * FUNCTION: MANAGEMENT - REOPEN RISK *
 *************************************/
function reopenForm()
{
    global $lang, $escaper;
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // If the user should not have access to the risk
        if (!extra_grant_access($_SESSION['uid'], $id))
        {
            set_alert(true, "bad", "You don't have permission.");

            // Return a JSON response
            json_response(400, get_alert(true), NULL);
        }
    }

    if (jira_extra()) {
        require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

        $risk_id = $id - 1000;
        $metadata = get_risk_issue_association_metadata($risk_id);
        
        if ($metadata && isset($metadata['issue_key'])) {
            $issue_key = $metadata['issue_key'];
            $synchronized_risk_field_values = get_synchronized_risk_field_values($risk_id);

        }
    }

    // Reopen the risk
    reopen_risk($id);

    if (jira_extra() && isset($issue_key) && $issue_key) {

        // check for changes in the risk and create the changelog entries
        jira_update_pending_risk_changes($risk_id, $synchronized_risk_field_values);

        // then synchronize
        jira_push_changes($issue_key, $risk_id);
    }

    $html = getTabHtml($id, 'overview');

    json_response(200, get_alert(true), $html);
}

/*************************************
 * FUNCTION: MANAGEMENT - REOPEN RISK *
 *************************************/
function overviewForm()
{
    global $lang, $escaper;
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $html = getTabHtml($id, 'overview');

    json_response(200, get_alert(true), $html);
}

/*************************************
 * FUNCTION: MANAGEMENT - GET CLOSE RISK HTML*
 *************************************/
function closeriskHtmlForm()
{
    global $lang, $escaper;
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    ob_start();
    include(realpath(__DIR__ . '/../management/partials/close.php'));
    $html = ob_get_contents();
    ob_end_clean();

    // Add token to form tag
    $html = addCSRTToken($html);

    json_response(200, get_alert(true), $html);
}

/*************************************
 * FUNCTION: MANAGEMENT - Close RISK *
 *************************************/
function closeriskForm()
{
    global $lang, $escaper;
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $access = check_access_for_risk($id);

    if(check_permission("modify_risks") && $access){
        $status = "Closed";
        $close_reason = $_POST['close_reason'];
        $note = $_POST['note'];

        if (jira_extra()) {
            require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

            $risk_id = $id - 1000;
            $metadata = get_risk_issue_association_metadata($risk_id);
            
            if ($metadata && isset($metadata['issue_key'])) {
                $issue_key = $metadata['issue_key'];
                $synchronized_risk_field_values = get_synchronized_risk_field_values($risk_id);

            }
        }

        // Submit a review
        submit_management_review($id, $status, null, null, $_SESSION['uid'], $note, "0000-00-00", true);

        // Close the risk
        close_risk($id, $_SESSION['uid'], $status, $close_reason, $note);

        if (jira_extra() && isset($issue_key) && $issue_key) {

            // check for changes in the risk and create the changelog entries
            jira_update_pending_risk_changes($risk_id, $synchronized_risk_field_values);

            // then synchronize
            jira_push_changes($issue_key, $risk_id);
        }

        // Display an alert
        set_alert(true, "good", "Your risk has now been marked as closed.");

        $viewhtml = getTabHtml($id, 'viewhtml');

        json_response(200, get_alert(true), $viewhtml);

    }else{
        set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);


    }

}



/*************************************
 * FUNCTION: MANAGEMENT - Get Details *
 *************************************/
function editdetailsForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $html = getTabHtml($id, 'details');

    json_response(200, get_alert(true), $html);
}

/**********************************************
 * FUNCTION: MANAGEMENT - Get All Review HTML *
 **********************************************/
function viewAllReviewsForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $html = getTabHtml($id, 'review');

    json_response(200, get_alert(true), $html);
}

/*************************************
 * FUNCTION: MANAGEMENT - Update Details *
 *************************************/
function saveDetailsForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $id = $_GET['id'];
    $risk_id = $id - 1000;

    $access = check_access_for_risk($id);
    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){

        if (jira_extra()) {
            require_once(realpath(__DIR__ . '/../extras/jira/index.php'));
            $issue_key = isset($_POST['jira_issue_key'])?strtoupper(trim($_POST['jira_issue_key'])):"";
            if ($issue_key && !jira_validate_issue_key($issue_key, $risk_id)) {
                json_response(400, get_alert(true), NULL);
                return;
            }

            $synchronized_risk_field_values = get_synchronized_risk_field_values($risk_id);
        }

        $error = update_risk($id);

        $risk = get_risk_by_id($id);


        // If the jira extra is activated and after saving the issue_key
        // there's a jira issue associated to the risk
        if (jira_extra() && jira_update_risk_issue_connection($risk_id, $issue_key, false)) {

            // check for changes in the risk and create the changelog entries
            jira_update_pending_risk_changes($risk_id, $synchronized_risk_field_values);

            // then synchronize
            jira_push_changes($issue_key, $risk_id);
        }

        /************************** Save Risk Score Method *********************************************/
        // Risk scoring method
        // 1 = Classic
        // 2 = CVSS
        // 3 = DREAD
        // 4 = OWASP
        // 5 = Custom

        // Classic Risk Scoring Inputs
        $scoring_method = (int)get_param("post", "scoring_method");
        $CLASSIC_likelihood = (int)get_param("post", "likelihood");
        $CLASSIC_impact = (int)get_param("post", "impact", 0);


        // CVSS Risk Scoring Inputs
        $AccessVector = get_param("post", "AccessVector");
        $AccessComplexity = get_param("post", "AccessComplexity");
        $Authentication = get_param("post", "Authentication");
        $ConfImpact = get_param("post", "ConfImpact");
        $IntegImpact = get_param("post", "IntegImpact");
        $AvailImpact = get_param("post", "AvailImpact");
        $Exploitability = get_param("post", "Exploitability");
        $RemediationLevel = get_param("post", "RemediationLevel");
        $ReportConfidence = get_param("post", "ReportConfidence");
        $CollateralDamagePotential = get_param("post", "CollateralDamagePotential");
        $TargetDistribution = get_param("post", "TargetDistribution");
        $ConfidentialityRequirement = get_param("post", "ConfidentialityRequirement");
        $IntegrityRequirement = get_param("post", "IntegrityRequirement");
        $AvailabilityRequirement = get_param("post", "AvailabilityRequirement");

        // DREAD Risk Scoring Inputs
        $DREADDamagePotential = (int)get_param("post", "DREADDamage");
        $DREADReproducibility = (int)get_param("post", "DREADReproducibility");
        $DREADExploitability = (int)get_param("post", "DREADExploitability");
        $DREADAffectedUsers = (int)get_param("post", "DREADAffectedUsers");
        $DREADDiscoverability = (int)get_param("post", "DREADDiscoverability");

        // OWASP Risk Scoring Inputs
        $OWASPSkillLevel = (int)get_param("post", "OWASPSkillLevel");
        $OWASPMotive = (int)get_param("post", "OWASPMotive");
        $OWASPOpportunity = (int)get_param("post", "OWASPOpportunity");
        $OWASPSize = (int)get_param("post", "OWASPSize");
        $OWASPEaseOfDiscovery = (int)get_param("post", "OWASPEaseOfDiscovery");
        $OWASPEaseOfExploit = (int)get_param("post", "OWASPEaseOfExploit");
        $OWASPAwareness = (int)get_param("post", "OWASPAwareness");
        $OWASPIntrusionDetection = (int)get_param("post", "OWASPIntrusionDetection");
        $OWASPLossOfConfidentiality = (int)get_param("post", "OWASPLossOfConfidentiality");
        $OWASPLossOfIntegrity = (int)get_param("post", "OWASPLossOfIntegrity");
        $OWASPLossOfAvailability = (int)get_param("post", "OWASPLossOfAvailability");
        $OWASPLossOfAccountability = (int)get_param("post", "OWASPLossOfAccountability");
        $OWASPFinancialDamage = (int)get_param("post", "OWASPFinancialDamage");
        $OWASPReputationDamage = (int)get_param("post", "OWASPReputationDamage");
        $OWASPNonCompliance = (int)get_param("post", "OWASPNonCompliance");
        $OWASPPrivacyViolation = (int)get_param("post", "OWASPPrivacyViolation");

        // Custom Risk Scoring
        $custom = (float)get_param("post", "Custom");
        
        // Contributing Risk Scoring
        $ContributingLikelihood = (int)get_param("post", "ContributingLikelihood");
        $ContributingImpacts = get_param("post", "ContributingImpacts");

        update_risk_scoring($id, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);

//        }


        /******************* end risk score method ***********************************/
        if ($error == 1)
        {
          // Display an alert
          set_alert(true, "good", "The risk has been successfully modified.");
        }
        else
        {
          // Display an alert
          set_alert(true, "bad", $error);
        }


        $html = getTabHtml($id, 'details');

        json_response(200, get_alert(true), $html);

    }else{
        set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);

    }

}

/*************************************
 * FUNCTION: MANAGEMENT - Add/Update Mitigation *
 *************************************/
function saveMitigationForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $id = $_GET['id'];
    $access = check_access_for_risk($id);

    // Check if the user has access to plan mitigations
    if (!isset($_SESSION["plan_mitigations"]) || $_SESSION["plan_mitigations"] != 1 || !$access)
    {
        global $lang;

        set_alert(true, "bad", $lang['MitigationPermissionMessage']);
        json_response(400, get_alert(true), null);
    }
    // If user has permission for plan mitigation.
    else
    {
        $risk = get_risk_by_id($id);
        if (count($risk) != 0){
            $mitigation_id = $risk[0]['mitigation_id'];
        }else{
            $mitigation_id = "";
        }

        // If we don't yet have a mitigation
        if (!$mitigation_id)
        {
            $status = "Mitigation Planned";
            // Submit mitigation and get the mitigation date back
            $error = submit_mitigation($id, $status, $_POST);
        }
        else
        {
            // Update mitigation and get the mitigation date back
            $error = update_mitigation($id, $_POST);
        }

        $html = getTabHtml($id, 'details');

        $mitigation_percent = isset($post['mitigation_percent']) ? (int)$_POST['mitigation_percent'] : 0;

        ob_start();
        view_score_html($id, $risk[0]['calculated_risk'], $mitigation_percent);
        $score_wrapper_html = ob_get_contents();
        ob_end_clean();

        // Calculate residual risk score
        $data = ['score_wrapper_html' => $score_wrapper_html, 'html' => $html];
        if ($error == 1)
        {
          // Display an alert
          set_alert(true, "good", "The Mitigation has been successfully modified.");
        }
        else
        {
          // Display an alert
          set_alert(true, "bad", $error);
        }

        json_response(200, get_alert(true), $data);
    }

}

/*************************************
 * FUNCTION: MANAGEMENT - Add/Update Review *
 *************************************/
function saveReviewForm()
{
    global $lang;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    }

    $id = $_GET['id'];

    // Get the risk by risk id
    $risk = get_risk_by_id($id);

    // If a risk was returned
    if (count($risk) != 0)
    {
        // Check that the user has access to this risk id
	$access = check_access_for_risk($id);

	// Check that the user has permission to review this risk level
	$review = check_review_permission_by_risk_id($id);

	// If the user has permission to the risk and permission to review
	if ($access && $review)
	{
            $status = "Mgmt Reviewed";
            $review = (int)get_param("POST", 'review');
            $next_step = (int)get_param("POST", 'next_step');
            $reviewer = $_SESSION['uid'];
            $comments = get_param("POST", 'comments');
            $custom_date = get_param("POST", 'custom_date');

            if ($custom_date == "yes")
            {
                $custom_review = get_param("POST", 'next_review');

                // Check the date format
                if (!validate_date($custom_review, get_default_date_format()))
                {
                    $custom_review = "0000-00-00";
                }
                // Otherwise, set the proper format for submitting to the database
                else
                {
                    $custom_review = get_standard_date_from_default_format($custom_review);
                }
            }
            else {
                $risk_id = (int)$risk[0]['id'];

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $custom_review = next_review_by_score($risk[0]['residual_risk']);
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $custom_review = next_review_by_score($risk[0]['calculated_risk']);
                }

                $custom_review = get_standard_date_from_default_format($custom_review);
            }

            submit_management_review($id, $status, $review, $next_step, $reviewer, $comments, $custom_review);
            set_alert(true, "good", $lang['SavedSuccess']);

            if ($next_step == 2) {
                $project = get_param("POST", 'project', 0);
                $prefix = 'new-projval-prfx-';
                if (startsWith($project, $prefix)) {//It's a new project's name
                    $name = substr($project, strlen($prefix));
                    if(isset($_SESSION["add_projects"]) && $_SESSION["add_projects"] == 1) {
                        $project = add_name("projects", try_encrypt($name));
                        set_alert(true, "good", $lang['SuccessCreateProject']);
                    } else {
                        set_alert(true, "bad", $lang['NoAddProjectPermission']);
                    }
                }

                if (ctype_digit((string)$project)) {
                    update_risk_project((int)$project, $id - 1000);
                    set_alert(true, "good", $lang['SuccessSetProject']);
                } else if(strlen($project)){
                    set_alert(true, "bad", $lang['ThereWasAProblemWithAddingTheProject']);
                }
            }

	    $html = getTabHtml($id, 'details');

	    json_response(200, get_alert(true), $html);
        }
	else
	{
            // Display an alert
            set_alert(true, "bad", "You do not have permission to review risks at this risk level.  Any reviews that you attempt to submit will not be recorded.  Please contact an administrator if you feel that you have reached this message in error.");
	}
    }else{

        set_alert(true, "bad", $lang['RiskUpdatePermissionMessage']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }


}

/*************************************
 * FUNCTION: MANAGEMENT - GET CHAGNE STATUS HTML*
 *************************************/
function changestatusForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $id = $_GET['id'];

    ob_start();
    include(realpath(__DIR__ . '/../management/partials/changestatus.php'));
    $html = ob_get_contents();
    ob_end_clean();

    // Add token to form tag
    $html = addCSRTToken($html);

    json_response(200, get_alert(true), $html);
}

/*************************************
 * FUNCTION: MANAGEMENT - UPDATE STATUS *
 *************************************/
function updateStatusForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    // If the user has permission to modify the risk and has access to the risk
    if(has_permission("modify_risks") && check_access_for_risk($id)){

        $status_id = (int)$_POST['status'];

        // Get the name associated with the status
        $status = get_name_by_value("status", $status_id);

        // Check that the id is a numeric value
        if (is_numeric($id)) {
            if (jira_extra()) {
                require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

                $risk_id = $id - 1000;
                $metadata = get_risk_issue_association_metadata($risk_id);

                if ($metadata && isset($metadata['issue_key'])) {
                    $issue_key = $metadata['issue_key'];
                    $synchronized_risk_field_values = get_synchronized_risk_field_values($risk_id);
                }
            }

            // Update the status of the risk
            update_risk_status($id, $status);

            if (jira_extra() && isset($issue_key) && $issue_key) {

                // check for changes in the risk and create the changelog entries
                jira_update_pending_risk_changes($risk_id, $synchronized_risk_field_values);

                // then synchronize
                jira_push_changes($issue_key, $risk_id);
            }
        }

        $html = getTabHtml($id, 'viewhtml');

        json_response(200, get_alert(true), $html);

    }else{

        set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);


    }

}

/********************************************************
 * FUNCTION: MANAGEMENT - GET MARK AS UNMITIGATION HTML *
 ********************************************************/
function markUnmitigationForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $id = $_GET['id'];

    ob_start();
    include(realpath(__DIR__ . '/../management/partials/unmitigation.php'));
    $html = ob_get_contents();
    ob_end_clean();

    // Add token to form tag
    $html = addCSRTToken($html);

    json_response(200, get_alert(true), $html);
}

/***************************************************
 * FUNCTION: MANAGEMENT - UPDATE UNMITIGATION RISK *
 ***************************************************/
function saveMarkUnmitigationForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];
    $access = check_access_for_risk($id);

    // Check if the user has access to plan mitigations
    if (!isset($_SESSION["plan_mitigations"]) || $_SESSION["plan_mitigations"] != 1 || !$access)
    {
        global $lang;

        set_alert(true, "bad", $lang['MitigationPermissionMessage']);
        json_response(400, get_alert(true), null);
    }
    // If user has permission for plan mitigation.
    else
    {
        $risk = get_risk_by_id($id);
        if (count($risk) != 0){
            $mitigation_id = $risk[0]['mitigation_id'];
        }else{
            $mitigation_id = "";
        }

        if ($mitigation_id)
        {
            // Submit Unmitigation
            $error = submit_unmitigation($id);
        }
        else
        {
            $error = "There is no Mitigation."; 
        }
        if ($error == 1)
        {
          // Display an alert
          set_alert(true, "good", "The Mitigation has been successfully deleted.");
        }
        else
        {
          // Display an alert
          set_alert(true, "bad", $error);
        }

        $html = getTabHtml($id, 'viewhtml');

        json_response(200, get_alert(true), $html);

    }
}

/****************************************************
 * FUNCTION: MANAGEMENT - GET MARK AS UNREVIEW HTML *
 ****************************************************/
function markUnreviewForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $id = $_GET['id'];

    ob_start();
    include(realpath(__DIR__ . '/../management/partials/unreview.php'));
    $html = ob_get_contents();
    ob_end_clean();

    // Add token to form tag
    $html = addCSRTToken($html);

    json_response(200, get_alert(true), $html);
}
/***********************************************
 * FUNCTION: MANAGEMENT - UPDATE UNREVIEW RISK *
 ***********************************************/
function saveMarkUnreviewForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];
    $risk = get_risk_by_id($id);

    // If a risk was returned
    if (count($risk) != 0)
    {
        // Check that the user has access to this risk id
        $access = check_access_for_risk($id);

        // Check that the user has permission to review this risk level
        $review = check_review_permission_by_risk_id($id);

        // If the user has permission to the risk and permission to review
        if ($access && $review)
        {
            submit_management_unreview($id);
            set_alert(true, "good", $lang['SavedSuccess']);

            $html = getTabHtml($id, 'viewhtml');

            json_response(200, get_alert(true), $html);
        }
        else
        {
                // Display an alert
                set_alert(true, "bad", "You do not have permission to review risks at this risk level.  Any reviews that you attempt to submit will not be recorded.  Please contact an administrator if you feel that you have reached this message in error.");
        }
    }else{

        set_alert(true, "bad", $lang['RiskUpdatePermissionMessage']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
}

/*****************************************
 * FUNCTION: MANAGEMENT - SCORING ACTION *
 ****************************************/
function scoreactionForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $html = getTabHtml($id, 'score-overview');

    json_response(200, get_alert(true), $html);
}

/*****************************************
 * FUNCTION: MANAGEMENT - UPDATE SUBJECT*
 ****************************************/
function saveSubjectForm()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    // If the user has permission to modify the risk and has access to the risk
    if(has_permission("modify_risks") && check_access_for_risk($id)){

        $new_subject = $_POST['subject'];
        if (trim($new_subject) != '')
        {
            if (jira_extra()) {
                require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

                $risk_id = $id - 1000;
                $metadata = get_risk_issue_association_metadata($risk_id);
                
                if ($metadata && isset($metadata['issue_key'])) {
                    $issue_key = $metadata['issue_key'];
                    $synchronized_risk_field_values = get_synchronized_risk_field_values($risk_id);

                }
            }

            // Limit the subject's length
            $maxlength = (int)get_setting('maximum_risk_subject_length', 300);
            if (strlen($new_subject) > $maxlength) {
                set_alert(true, "bad", _lang('RiskSubjectTruncated', ['limit' => $maxlength]));
                $new_subject = substr($new_subject, 0, $maxlength);
            }

            $subject = try_encrypt($new_subject);
            update_risk_subject($id, $subject);

            if (jira_extra() && isset($issue_key) && $issue_key) {

                // check for changes in the risk and create the changelog entries
                jira_update_pending_risk_changes($risk_id, $synchronized_risk_field_values);

                // then synchronize
                jira_push_changes($issue_key, $risk_id);
            }
            set_alert(true, "good", "The subject has been successfully modified.");
        } else {
            set_alert(true, "bad", "The subject of a risk cannot be empty.");
            json_response(400, get_alert(true), NULL);

        }

        $html = getTabHtml($id, 'overview');

        json_response(200, get_alert(true), $html);

    }else{
        set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);

    }


}

/*****************************************
 * FUNCTION: MANAGEMENT - UPDATE COMMENT*
 ****************************************/
function saveCommentForm()
{
    global $escaper, $lang;
    
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $access = check_access_for_risk($id);

//    if(!isset($_SESSION["modify_risks"]) || $_SESSION["modify_risks"] != 1 || !$access){
    if(!$access){
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoAccessRiskPermission']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    elseif($_SESSION["comment_risk_management"] == 1) {
        $comment = $_POST['comment'];

        if($comment == null){
            set_alert(true, "bad", $escaper->escapeHtml($lang['CommentRiskRequired']));

            // Return a JSON response
            json_response(400, get_alert(true), NULL);

        }

        if($comment != null){
            // Add the comment
            add_comment($id, $_SESSION['uid'], $comment);
        }
    }
    else {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoCommentRiskPermission']));
    }
    $html = getTabHtml($id, 'comments-list');

    json_response(200, get_alert(true), $html);
}

/********************************************
 * FUNCTION: MANAGEMENT - Accept Mitigation *
 ********************************************/
function acceptMitigationForm()
{
    global $lang, $escaper;

    // If user has no permission for accept mitigation
    if(empty($_SESSION['accept_mitigation']))
    {
        set_alert(true, "bad", "You have no permission for accept mitigation.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    
    // If the id is not sent
    elseif (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    else
    {
        $id = (int)$_GET['id'];
        $accept = (int)$_POST['accept'];

        // Check if user has a permission for accept mitigation
        if(empty($_SESSION["accept_mitigation"])){
            set_alert(true, "bad", "You do not have permission to accept mitigation.  Please contact an Administrator if you feel that you have reached this message in error.");
            // Return a JSON response
            json_response(400, get_alert(true), NULL);
        }
    
        accept_mitigation_by_risk_id($id, $accept);

        $message = view_accepted_mitigations($id);

        $data = array("accept_mitigation_text" => $message);

        json_response(200, get_alert(true), $data);
    }
}

/*************************************
 * FUNCTION: MANAGEMENT - Save Scores*
 *************************************/
function saveScoreForm()
{
    global $lang, $escaper;
    
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $id = $_GET['id'];

    $access = check_access_for_risk($id);

    if(!isset($_SESSION["modify_risks"]) || $_SESSION["modify_risks"] != 1 || !$access){
        set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $action = isset($_GET['action']) ? $_GET['action'] : "";

    switch($action){
        case "update_custom":
            $custom = (float)$_POST['Custom'];
            update_custom_score($id, $custom);
        break;

        case "update_classic":
            $CLASSIC_likelihood = (int)$_POST['likelihood'];
            $CLASSIC_impact = (int)$_POST['impact'];

            // Update the risk scoring
            update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact);
        break;

        case "update_cvss":
            $AccessVector = $_POST['AccessVector'];
            $AccessComplexity = $_POST['AccessComplexity'];
            $Authentication = $_POST['Authentication'];
            $ConfImpact = $_POST['ConfImpact'];
            $IntegImpact = $_POST['IntegImpact'];
            $AvailImpact = $_POST['AvailImpact'];
            $Exploitability = $_POST['Exploitability'];
            $RemediationLevel = $_POST['RemediationLevel'];
            $ReportConfidence = $_POST['ReportConfidence'];
            $CollateralDamagePotential = $_POST['CollateralDamagePotential'];
            $TargetDistribution = $_POST['TargetDistribution'];
            $ConfidentialityRequirement = $_POST['ConfidentialityRequirement'];
            $IntegrityRequirement = $_POST['IntegrityRequirement'];
            $AvailabilityRequirement = $_POST['AvailabilityRequirement'];

            // Update the risk scoring
            update_cvss_score($id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        break;

        case "update_dread":
            $DREADDamagePotential = (int)$_POST['DamagePotential'];
            $DREADReproducibility = (int)$_POST['Reproducibility'];
            $DREADExploitability = (int)$_POST['Exploitability'];
            $DREADAffectedUsers = (int)$_POST['AffectedUsers'];
            $DREADDiscoverability = (int)$_POST['Discoverability'];

            // Update the risk scoring
            update_dread_score($id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        break;

        case "update_owasp":
            $OWASPSkillLevel = (int)$_POST['SkillLevel'];
            $OWASPMotive = (int)$_POST['Motive'];
            $OWASPOpportunity = (int)$_POST['Opportunity'];
            $OWASPSize = (int)$_POST['Size'];
            $OWASPEaseOfDiscovery = (int)$_POST['EaseOfDiscovery'];
            $OWASPEaseOfExploit = (int)$_POST['EaseOfExploit'];
            $OWASPAwareness = (int)$_POST['Awareness'];
            $OWASPIntrusionDetection = (int)$_POST['IntrusionDetection'];
            $OWASPLossOfConfidentiality = (int)$_POST['LossOfConfidentiality'];
            $OWASPLossOfIntegrity = (int)$_POST['LossOfIntegrity'];
            $OWASPLossOfAvailability = (int)$_POST['LossOfAvailability'];
            $OWASPLossOfAccountability = (int)$_POST['LossOfAccountability'];
            $OWASPFinancialDamage = (int)$_POST['FinancialDamage'];
            $OWASPReputationDamage = (int)$_POST['ReputationDamage'];
            $OWASPNonCompliance = (int)$_POST['NonCompliance'];
            $OWASPPrivacyViolation = (int)$_POST['PrivacyViolation'];

            // Update the risk scoring
            update_owasp_score($id, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
        break;
        
        case "update_contributing_risk":
            $ContributingLikelihood = (int)$_POST['ContributingLikelihood'];
            $ContributingImpacts = $_POST['ContributingImpacts'];
            update_contributing_risk_score($id, $ContributingLikelihood, $ContributingImpacts);
        break;

    }

    $html = getTabHtml($id, 'score-overview');

    json_response(200, get_alert(true), $html);
}

/**********************************************
 * FUNCTION: MANAGEMENT - Get Scoring History *
 **********************************************/
function scoringHistory()
{
    // If the risk id is sent
    if (isset($_GET['id']))
    {
        //sleep(3);
        $risk_id = $_GET['id'];

        // Check whether the user should be able to access this risk_id
        $access = check_access_for_risk($risk_id);

        // If the user has access
        if ($access)
        {
            $histories = get_scoring_histories($risk_id);
            $current_history = end($histories);
            $current_history['last_update'] = date('Y-m-d H:i:s');
            array_push($histories, $current_history);
            json_response(200, "scoring_history", $histories);
        }
        else
        {
            // The user is not authorized to access that risk
            json_response(401, "The user does not have permission to view this risk.", "");
        }
    }
    // If the risk id was not sent
    else
    {
        // Return history for all risks
        $histories = get_scoring_histories();
        json_response(200, get_alert(true), $histories);
    }
}

/*******************************************************
 * FUNCTION: MANAGEMENT - Get Residual Scoring History *
 *******************************************************/
function residualScoringHistory()
{
    // If the risk id is sent
    if (isset($_GET['id']))
    {
        //sleep(3);
        $risk_id= $_GET['id'];

        // Check whether the user should be able to access this risk_id
        $access = check_access_for_risk($risk_id);

        // If the user has access
        if ($access)
        {
            $residual_histories = get_residual_scoring_histories($risk_id);
            $current_history = end($residual_histories);
            $current_history['last_update'] = date('Y-m-d H:i:s');
            array_push($residual_histories, $current_history);
            json_response(200, "residual_scoring_history", $residual_histories);
        }
        else
        {
            // The user is not authorized to access that risk
            json_response(401, "The user does not have permission to view this risk.", "");
        }
    }
    // If the risk id was not sent
    else
    {
        // Return history for all risks
        $residual_histories = get_residual_scoring_histories();
        json_response(200, get_alert(true), $residual_histories);
    }
}

/**********************************************************
 * FUNCTION: UPDATERISK - UPDATE A RISK FROM EXTERNAL APP *
 **********************************************************/
function updateRisk(){
    global $lang, $escaper;

    // If the id is not sent
    if (get_param("POST", 'id', false) === false)
    {
        $status = "400";
        $status_message = $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']);
        // Return a JSON response
        json_response($status, $status_message, NULL);
    }

    $id = get_param("POST", 'id');

    $risk = get_risk_by_id($id);

    if(!$risk){
        $status = "400";
        // If Risk ID exists.
        if(check_risk_by_id($id)){
            $status_message = $lang["RiskDisplayPermission"];
        }
        // If Risk ID does not exist.
        else{
            $status_message = $lang["RiskIdDoesNotExist"];
        }
        // Return a JSON response
        json_response($status, $status_message, NULL);
    }

    $new_subject = get_param("POST", 'subject', false);
    if(($new_subject !== false && $new_subject == "") || !trim($new_subject)){
        $status = "400";
        $status_message = $escaper->escapeHtml($lang['SubjectRiskCannotBeEmpty']);
        // Return a JSON response
        json_response($status, $status_message, NULL);
    }


    $access = check_access_for_risk($id);
    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){

        if($new_subject !== false){
            // Limit the subject's length
            $new_subject = substr($new_subject, 0, (int)get_setting('maximum_risk_subject_length', 300));

            $subject = try_encrypt($new_subject);
            update_risk_subject($id, $subject);
        }

        $success = update_risk($id, true);

        if($success == 1){

            /************************** Save Risk Score Method *********************************************/
            // Risk scoring method
            // 1 = Classic
            // 2 = CVSS
            // 3 = DREAD
            // 4 = OWASP
            // 5 = Custom


            // Classic Risk Scoring Inputs
            $scoring_method = (int)get_param("POST", 'scoring_method');
            $CLASSIC_likelihood = (int)get_param("POST", 'likelihood');
            $CLASSIC_impact =(int)get_param("POST", 'impact');

            // CVSS Risk Scoring Inputs
            $AccessVector = get_param("POST", 'AccessVector');
            $AccessComplexity = get_param("POST", 'AccessComplexity');
            $Authentication = get_param("POST", 'Authentication');
            $ConfImpact = get_param("POST", 'ConfImpact');
            $IntegImpact = get_param("POST", 'IntegImpact');
            $AvailImpact = get_param("POST", 'AvailImpact');
            $Exploitability = get_param("POST", 'Exploitability');
            $RemediationLevel = get_param("POST", 'RemediationLevel');
            $ReportConfidence = get_param("POST", 'ReportConfidence');
            $CollateralDamagePotential = get_param("POST", 'CollateralDamagePotential');
            $TargetDistribution = get_param("POST", 'TargetDistribution');
            $ConfidentialityRequirement = get_param("POST", 'ConfidentialityRequirement');
            $IntegrityRequirement = get_param("POST", 'IntegrityRequirement');
            $AvailabilityRequirement = get_param("POST", 'AvailabilityRequirement');

            // DREAD Risk Scoring Inputs
            $DREADDamagePotential = (int)get_param("POST", 'DREADDamage');
            $DREADReproducibility = (int)get_param("POST", 'DREADReproducibility');
            $DREADExploitability = (int)get_param("POST", 'DREADExploitability');
            $DREADAffectedUsers = (int)get_param("POST", 'DREADAffectedUsers');
            $DREADDiscoverability = (int)get_param("POST", 'DREADDiscoverability');

            // OWASP Risk Scoring Inputs
            $OWASPSkillLevel = (int)get_param("POST", 'OWASPSkillLevel');
            $OWASPMotive = (int)get_param("POST", 'OWASPMotive');
            $OWASPOpportunity = (int)get_param("POST", 'OWASPOpportunity');
            $OWASPSize = (int)get_param("POST", 'OWASPSize');
            $OWASPEaseOfDiscovery = (int)get_param("POST", 'OWASPEaseOfDiscovery');
            $OWASPEaseOfExploit = (int)get_param("POST", 'OWASPEaseOfExploit');
            $OWASPAwareness = (int)get_param("POST", 'OWASPAwareness');
            $OWASPIntrusionDetection = (int)get_param("POST", 'OWASPIntrusionDetection');
            $OWASPLossOfConfidentiality = (int)get_param("POST", 'OWASPLossOfConfidentiality');
            $OWASPLossOfIntegrity = (int)get_param("POST", 'OWASPLossOfIntegrity');
            $OWASPLossOfAvailability = (int)get_param("POST", 'OWASPLossOfAvailability');
            $OWASPLossOfAccountability = (int)get_param("POST", 'OWASPLossOfAccountability');
            $OWASPFinancialDamage = (int)get_param("POST", 'OWASPFinancialDamage');
            $OWASPReputationDamage = (int)get_param("POST", 'OWASPReputationDamage');
            $OWASPNonCompliance = (int)get_param("POST", 'OWASPNonCompliance');
            $OWASPPrivacyViolation = (int)get_param("POST", 'OWASPPrivacyViolation');

            // Custom Risk Scoring
            $custom = (float)get_param("POST", 'Custom');

            // Contributing Risk Scoring
            $ContributingLikelihood = (int)get_param("POST", "ContributingLikelihood");
            $ContributingImpacts = (int)get_param("POST", "ContributingImpacts");
            
            update_risk_scoring($id, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);
            $status = 200;
            $status_message = "Risk ID " . $id . " updated successfully!";
        }else{
            $status = 400;
            // If there are any errors, $success has error messages
            $status_message = $success;
        }
    }else{
        $status = 400;
        $status_message = $lang["RiskUpdatePermissionMessage"];
    }
    // Return a JSON response
    json_response($status, $status_message, NULL);
}

/****************************************************
 * FUNCTION: ADDRISK - ADD A RISK FROM EXTERNAL APP *
 ****************************************************/
function addRisk(){
    global $lang, $escaper;

    $subject = get_param("POST", 'subject');
    $tags = get_param("POST", 'tags', []);

    foreach($tags as $tag){
        if (strlen($tag) > 255) {
            set_alert(true, "bad", $lang['MaxTagLengthWarning']);
            json_response(400, get_alert(true), []);
        }
    }
    
    if (jira_extra()) {
        require_once(realpath(__DIR__ . '/../extras/jira/index.php'));
        $issue_key = strtoupper(trim(get_param("POST", 'jira_issue_key')));
        if ($issue_key && !jira_validate_issue_key($issue_key)) {
            json_response(400, get_alert(true), NULL);
        }
    }

    if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1)
    {
        $status = "401";
        $status_message = $escaper->escapeHtml($lang['RiskAddPermissionMessage']);
        $data = array();
    }elseif(!trim($subject)){
        $status = "400";
        $status_message = $escaper->escapeHtml($lang['SubjectRiskCannotBeEmpty']);
        $data = array();
    }else{

        $status = "New";
        $reference_id = get_param("POST", 'reference_id');
        $regulation = (int)get_param("POST", 'regulation');
        $control_number = get_param("POST", 'control_number');
        $location = get_param("POST", 'location', []);
        $location = implode(",", $location);
        $source = (int)get_param("POST", 'source');
        $category = (int)get_param("POST", 'category');
        if(is_array(get_param("POST", 'team'))){
            $team = get_param("POST", 'team');
        }else{
            $team = get_value_string_by_table('team');
        }
        
        if(is_array(get_param("POST", 'technology'))){
            $technology = get_param("POST", '$technology');
        }else{
            $technology = [];
        }
        $owner = (int)get_param("POST", 'owner');
        $manager = (int)get_param("POST", 'manager');
        $assessment = get_param("POST", 'assessment');
        $notes = get_param("POST", 'notes');
        if(is_array(get_param("POST", 'additional_stakeholders'))){
            $additional_stakeholders = get_param("POST", 'additional_stakeholders');
        }else{
            $additional_stakeholders = [];
        }

        // Risk scoring method
        // 1 = Classic
        // 2 = CVSS
        // 3 = DREAD
        // 4 = OWASP
        // 5 = Custom
        $scoring_method = (int)get_param("POST", 'scoring_method');

        // Classic Risk Scoring Inputs
        $CLASSIClikelihood = (int)get_param("POST", 'likelihood');
        $CLASSICimpact =(int) get_param("POST", 'impact');

        // CVSS Risk Scoring Inputs
        $CVSSAccessVector = get_param("POST", 'AccessVector');
        $CVSSAccessComplexity = get_param("POST", 'AccessComplexity');
        $CVSSAuthentication = get_param("POST", 'Authentication');
        $CVSSConfImpact = get_param("POST", 'ConfImpact');
        $CVSSIntegImpact = get_param("POST", 'IntegImpact');
        $CVSSAvailImpact = get_param("POST", 'AvailImpact');
        $CVSSExploitability = get_param("POST", 'Exploitability');
        $CVSSRemediationLevel = get_param("POST", 'RemediationLevel');
        $CVSSReportConfidence = get_param("POST", 'ReportConfidence');
        $CVSSCollateralDamagePotential = get_param("POST", 'CollateralDamagePotential');
        $CVSSTargetDistribution = get_param("POST", 'TargetDistribution');
        $CVSSConfidentialityRequirement = get_param("POST", 'ConfidentialityRequirement');
        $CVSSIntegrityRequirement = get_param("POST", 'IntegrityRequirement');
        $CVSSAvailabilityRequirement = get_param("POST", 'AvailabilityRequirement');

        // DREAD Risk Scoring Inputs
        $DREADDamage = (int)get_param("POST", 'DREADDamage');
        $DREADReproducibility = (int)get_param("POST", 'DREADReproducibility');
        $DREADExploitability = (int)get_param("POST", 'DREADExploitability');
        $DREADAffectedUsers = (int)get_param("POST", 'DREADAffectedUsers');
        $DREADDiscoverability = (int)get_param("POST", 'DREADDiscoverability');

        // OWASP Risk Scoring Inputs
        $OWASPSkillLevel = (int)get_param("POST", 'OWASPSkillLevel');
        $OWASPMotive = (int)get_param("POST", 'OWASPMotive');
        $OWASPOpportunity = (int)get_param("POST", 'OWASPOpportunity');
        $OWASPSize = (int)get_param("POST", 'OWASPSize');
        $OWASPEaseOfDiscovery = (int)get_param("POST", 'OWASPEaseOfDiscovery');
        $OWASPEaseOfExploit = (int)get_param("POST", 'OWASPEaseOfExploit');
        $OWASPAwareness = (int)get_param("POST", 'OWASPAwareness');
        $OWASPIntrusionDetection = (int)get_param("POST", 'OWASPIntrusionDetection');
        $OWASPLossOfConfidentiality = (int)get_param("POST", 'OWASPLossOfConfidentiality');
        $OWASPLossOfIntegrity = (int)get_param("POST", 'OWASPLossOfIntegrity');
        $OWASPLossOfAvailability = (int)get_param("POST", 'OWASPLossOfAvailability');
        $OWASPLossOfAccountability = (int)get_param("POST", 'OWASPLossOfAccountability');
        $OWASPFinancialDamage = (int)get_param("POST", 'OWASPFinancialDamage');
        $OWASPReputationDamage = (int)get_param("POST", 'OWASPReputationDamage');
        $OWASPNonCompliance = (int)get_param("POST", 'OWASPNonCompliance');
        $OWASPPrivacyViolation = (int)get_param("POST", 'OWASPPrivacyViolation');

        // Custom Risk Scoring
        $custom = (float)get_param("POST", 'Custom');

        // Contributing Risk Scroing
        $ContributingLikelihood = (int)get_param("POST", "ContributingLikelihood", "");
        $ContributingImpacts = get_param("POST", "ContributingImpacts", []);
        
        // Submit risk and get back the id
        $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes, 0, 0, false, $additional_stakeholders);

        // If the encryption extra is enabled, updates order_by_subject
        if (encryption_extra())
        {
            // Load the extra
            require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

//            create_subject_order(isset($_SESSION['encrypted_pass']) && $_SESSION['encrypted_pass'] ? $_SESSION['encrypted_pass'] : fetch_key());
        }

        if($scoring_method){
            // Submit risk scoring
            submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);
        }
        else{
            // Submit risk scoring
            submit_risk_scoring($last_insert_id);
        }

        $affected_assets = get_param("POST", 'affected_assets');

        if ($affected_assets)
            import_assets_asset_groups_for_type($last_insert_id, $affected_assets, 'risk');

        //Add tags
        updateTagsOfType($last_insert_id, 'risk', $tags);

        // Create the connection between the risk and the jira issue
        if (jira_extra()) {
            if ($issue_key) {
                if (jira_update_risk_issue_connection($last_insert_id, $issue_key)) {
                    jira_push_changes($issue_key, $last_insert_id);
                }
            } else {
                CreateIssueForRisk($last_insert_id);
            }
        }

        // If the notification extra is enabled
        if (notification_extra())
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

            // Send the notification
            notify_new_risk($last_insert_id);
        }

        // There is an alert message
        $risk_id = (int)$last_insert_id + 1000;

        $status = 200;
        $status_message = $escaper->escapeHtml("Risk ID " . $risk_id . " submitted successfully!");
        $data = array(
            'risk_id' => $risk_id
        );
    }

    // Return a JSON response
    json_response($status, $status_message, $data);
}

/*****************************************************************
 * FUNCTION: SAVEMITIGATION - SAVE A MITIGATION FROM EXTERNAL APP*
 * PARAM: id: risk_id + 1000
 ****************************************************************/
function saveMitigation(){
    global $lang, $escaper;

    $data = array();
    $id = get_param("POST", "id", false);

    if (!$id)
    {
        $status = 400;
        $status_message = $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']);
        return json_response($status, $status_message, $data);
    }


    $risk = get_risk_by_id($id);

    // If the risk doesn't exist, return;
    if(count($risk) == 0){
        $status = 400;
        $status_message = $escaper->escapeHtml($lang['InvalidRiskID']);
        return json_response($status, $status_message, $data);
    }

    $access = check_access_for_risk($id);

    // Check if the user has access to plan mitigations
    if (!isset($_SESSION["plan_mitigations"]) || $_SESSION["plan_mitigations"] != 1)
    {
        $plan_mitigations = false;

        $status = 400;
        $status_message = $escaper->escapeHtml($lang['MitigationPermissionMessage']);
    }
    // If user has permission for modifing risks.
    elseif(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){
        $mitigation_id = $risk[0]['mitigation_id'];
        // Submit mitigation and get the mitigation date back
        $post = array(
            'planning_strategy'         => get_param("POST", "planning_strategy", 0),
            'mitigation_effort'         => get_param("POST", "mitigation_effort", 0),
            'mitigation_cost'           => get_param("POST", "mitigation_cost", 0),
            'mitigation_owner'          => get_param("POST", "mitigation_owner", 0),
            'mitigation_team'           => get_param("POST", "mitigation_team", []),
            'current_solution'          => get_param("POST", "current_solution"),
            'security_requirements'     => get_param("POST", "security_requirements"),
            'security_recommendations'  => get_param("POST", "security_recommendations"),
            'planning_date'             => get_param("POST", "planning_date"),
            'mitigation_percent'        => get_param("POST", "mitigation_percent"),
        );

        // If we don't yet have a mitigation
        if (!$mitigation_id)
        {
            $status = "Mitigation Planned";


            $mitigation_date = submit_mitigation($id, $status, $post);
        }
        else
        {
            // Update mitigation and get the mitigation date back
            $mitigation_date = update_mitigation($id, $post);
        }
        $status = 200;
        $status_message = $escaper->escapeHtml($lang['Success']);
        $data = array(
            'risk_id' => $id
        );
    }else{
        $status = 400;
        $status_message = $escaper->escapeHtml($lang['RiskUpdatePermissionMessage']);
    }
    return json_response($status, $status_message, $data);
}

/*****************************************************************
 * FUNCTION: SAVEREVIEW - SAVE A REVIEW FROM EXTERNAL APP*
 * PARAM: id: risk_id + 1000
 ****************************************************************/
function saveReview(){
    global $lang, $escaper;

    $id = get_param("POST", "id", false);
    $data = array();
    if (!$id)
    {
        $status = 400;
        $status_message = $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']);
        return json_response($status, $status_message, $data);
    }


    $risk = get_risk_by_id($id);
    // If the risk doesn't exist, return;
    if(count($risk) == 0){
        $status = 400;
        $status_message = $escaper->escapeHtml($lang['InvalidRiskID']);
        return json_response($status, $status_message, $data);
    }

    $access = check_access_for_risk($id);
    // If user has permission for modifing risks.
    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){
        $risk_level = get_risk_level_name($risk[0]['calculated_risk']);

	// Check that the user has permission to review this risk level
        $approved = check_review_permission_by_risk_id($id);
        if (!$approved){
            $status = 400;
            $params = array(
                'risk_level' => $risk_level
            );
            $status_message = _lang('RiskReviewPermission', $params);
            return json_response($status, $status_message);
        }else{
            $status = "Mgmt Reviewed";
            $review = (int)get_param('POST', 'review');
            $next_step = (int)get_param('POST', 'next_step');
            $reviewer = $_SESSION['uid'];
            $comments = get_param('POST', 'comments');
            $custom_date = get_param('POST', 'custom_date');

            if ($custom_date == "yes")
            {
                $custom_review = get_param('POST', 'next_review');

                // Check the date format
                if (!validate_date($custom_review, get_default_date_format()))
                {
                    $custom_review = "0000-00-00";
                }
                // Otherwise, set the proper format for submitting to the database
                else
                {
                    $custom_review = get_standard_date_from_default_format($custom_review);
                }
            }
            else {
                $custom_review = "0000-00-00";
                $risk_level = get_risk_level_name($risk[0]['calculated_risk']);
                $residual_risk_level = get_risk_level_name($risk[0]['residual_risk']);
                $risk_id = (int)$risk[0]['id'];

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $custom_review = next_review($residual_risk_level, $risk_id, $custom_review, false, false, date("Y-m-d"));
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $custom_review = next_review($risk_level, $risk_id, $custom_review, false, false, date("Y-m-d"));
                }
                $custom_review = get_standard_date_from_default_format($custom_review);
            }

            $data = array(
                'risk_id' => $id
            );

            submit_management_review($id, $status, $review, $next_step, $reviewer, $comments, $custom_review);
            
            if ($next_step == 2) {
                $project = get_param('POST', 'project', 0);
                $prefix = 'new-projval-prfx-';
                if (startsWith($project, $prefix)) {//It's a new project's name
                    $name = substr($project, strlen($prefix));
                    if(isset($_SESSION["add_projects"]) && $_SESSION["add_projects"] == 1) {
                        $project = add_name("projects", try_encrypt($name));
                        set_alert(true, "good", $lang['SuccessCreateProject']);
                    } else {
                        set_alert(true, "bad", $lang['NoAddProjectPermission']);
                    }
                }

                if (ctype_digit((string)$project)) {
                    update_risk_project((int)$project, $id - 1000);
                    set_alert(true, "good", $lang['SuccessSetProject']);
                } else {
                    set_alert(true, "bad", $lang['ThereWasAProblemWithAddingTheProject']);
                }
            }
        }
        $status = 200;
        $status_message = $lang['Success'];
    }else{
        $status = 400;
        $status_message = $lang['RiskUpdatePermissionMessage'];
    }
    return json_response($status, $status_message, $data);
}

/*****************************
 * FUNCTION: GET RSIK LEVELS *
 *****************************/
function risk_levels(){
    global $lang;

    $risk_levels = get_risk_levels();
    $results = array();
    foreach($risk_levels as $risk_level){
        $results[] = array(
            'value' => $risk_level['value'],
            'name' => $risk_level['display_name'],
            'color' => $risk_level['color'],
        );
    }

    $data = array(
        'risk_levels' => $results
    );
    $status = 200;
    $status_message = $lang['Success'];

    return json_response($status, $status_message, $data);
}

/*****************************************************************
 * FUNCTION: SET CUSTOM DISPLAY COLUMNS *
 ****************************************************************/
function setCustomDisplay(){
    $_SESSION['custom_display_settings'] = isset($_POST['columns']) ? $_POST['columns'] : array();
    save_custom_display_settings();
}
/*****************************************************************
 * FUNCTION: SET CUSTOM AUDITS COLUMNS *
 ****************************************************************/
function setCustomAuditsColumn(){
    $_SESSION['custom_audits_columns'] = isset($_POST['columns']) ? $_POST['columns'] : array();
}

/****************************
 * FUNCTION: DELETE MAPPING *
 ****************************/
function deleteMapping(){
    global $lang;

    // If the extra directory exists
    if (is_dir(realpath(__DIR__ . '/../extras/import-export')))
    {
        // But the extra is not activated
        if (!import_export_extra()){
            $status = 400;
            $status_message = $lang['ActivateTheImportExportExtra'];
            set_alert(true, "bad", $lang['ActivateTheImportExportExtra']);
        }
        // Once it has been activated
        else
        {
            // Include the Import-Export Extra
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

            $id = $_POST['id'];
            delete_mapping($id);

            $status = 200;
            $status_message = $lang['Success'];
            set_alert(true, "good", $lang['DeletedMappingSuccess']);
        }
    }


    return json_response($status, $status_message);
}

/*****************************************************
 * FUNCTION: UPDATE ALL QUESTIONS FOR ONE ASSESSMENT *
 *****************************************************/
function updateAssessment(){
    global $lang, $escaper;

    $rows = json_decode($_POST['assessments'], true);
    $assessment_id = (int)$_GET["assessment_id"];
    if(assessments_extra()){
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
    }
    foreach($rows as $row){
        $question_id = $row['question_id'];
        $question = $row['question'];
        $answer = [];
        $submit_risk = [];
        $answer_id = [];
        $risk_subject = [];
        $risk_score = [];
        $risk_owner = [];
        $assets_asset_groups = [];
        $assessment_scoring_ids = [];

        foreach($row['answers'] as $answerRow){
            $answer[]       = $answerRow['answer'];
            $submit_risk[]  = $answerRow['submit_risk'];
            $answer_id[]    = $answerRow['answer_id'];
            $risk_subject[] = $answerRow['risk_subject'];
//            $risk_score[]   = $answerRow['risk_score'];
            $risk_owner[]   = $answerRow['risk_owner'];
            $assets_asset_groups[$answerRow['answer_id']] = isset($answerRow['assets_asset_groups']) ? $answerRow['assets_asset_groups'] : [];

            $data = array(
                'scoring_method' => $answerRow['scoring_method'],

                // Classic Risk Scoring Inputs
                'CLASSIClikelihood' => $answerRow['likelihood'],
                'CLASSICimpact' =>  $answerRow['impact'],

                // CVSS Risk Scoring Inputs
                'CVSSAccessVector' => $answerRow['AccessVector'],
                'CVSSAccessComplexity' => $answerRow['AccessComplexity'],
                'CVSSAuthentication' => $answerRow['Authentication'],
                'CVSSConfImpact' => $answerRow['ConfImpact'],
                'CVSSIntegImpact' => $answerRow['IntegImpact'],
                'CVSSAvailImpact' => $answerRow['AvailImpact'],
                'CVSSExploitability' => $answerRow['Exploitability'],
                'CVSSRemediationLevel' => $answerRow['RemediationLevel'],
                'CVSSReportConfidence' => $answerRow['ReportConfidence'],
                'CVSSCollateralDamagePotential' => $answerRow['CollateralDamagePotential'],
                'CVSSTargetDistribution' => $answerRow['TargetDistribution'],
                'CVSSConfidentialityRequirement' => $answerRow['ConfidentialityRequirement'],
                'CVSSIntegrityRequirement' => $answerRow['IntegrityRequirement'],
                'CVSSAvailabilityRequirement' => $answerRow['AvailabilityRequirement'],
                // DREAD Risk Scoring Inputs
                'DREADDamage' => $answerRow['DREADDamage'],
                'DREADReproducibility' => $answerRow['DREADReproducibility'],
                'DREADExploitability' => $answerRow['DREADExploitability'],
                'DREADAffectedUsers' => $answerRow['DREADAffectedUsers'],
                'DREADDiscoverability' => $answerRow['DREADDiscoverability'],
                // OWASP Risk Scoring Inputs
                'OWASPSkillLevel' => $answerRow['OWASPSkillLevel'],
                'OWASPMotive' => $answerRow['OWASPMotive'],
                'OWASPOpportunity' => $answerRow['OWASPOpportunity'],
                'OWASPSize' => $answerRow['OWASPSize'],
                'OWASPEaseOfDiscovery' => $answerRow['OWASPEaseOfDiscovery'],
                'OWASPEaseOfExploit' => $answerRow['OWASPEaseOfExploit'],
                'OWASPAwareness' => $answerRow['OWASPAwareness'],
                'OWASPIntrusionDetection' => $answerRow['OWASPIntrusionDetection'],
                'OWASPLossOfConfidentiality' => $answerRow['OWASPLossOfConfidentiality'],
                'OWASPLossOfIntegrity' => $answerRow['OWASPLossOfIntegrity'],
                'OWASPLossOfAvailability' => $answerRow['OWASPLossOfAvailability'],
                'OWASPLossOfAccountability' => $answerRow['OWASPLossOfAccountability'],
                'OWASPFinancialDamage' => $answerRow['OWASPFinancialDamage'],
                'OWASPReputationDamage' => $answerRow['OWASPReputationDamage'],
                'OWASPNonCompliance' => $answerRow['OWASPNonCompliance'],
                'OWASPPrivacyViolation' => $answerRow['OWASPPrivacyViolation'],

                // Custom Risk Scoring
                'Custom' => $answerRow['Custom'],
            );
            if($answerRow['assessment_scoring_id']){
                $risk_score[] = update_assessment_scoring($answerRow['assessment_scoring_id'], $data);
                $assessment_scoring_ids[] = $answerRow['assessment_scoring_id'];
            }
            else{
                $assessment_scoring_ids[] = add_assessment_scoring($data);
            }
        }


        update_assessment_question($assessment_id, $question_id, $question, $answer, $submit_risk, $answer_id, $risk_subject, $risk_score, $risk_owner, $assets_asset_groups, $assessment_scoring_ids);
    }
    $status = 200;
    $status_message = $lang['SavedSuccess'];
    return json_response($status, $escaper->escapeHtml($status_message), NULL);
}

/****************************
 * FUNCTION: GET TABLE DATA *
 ****************************/
function getTableData()
{
    global $escaper;

    // If the user is an administrator
    if (is_admin())
    {
        // If a table name was not sent
        if (!(isset($_GET['table'])))
        {
            $status = "400";
            $status_message = $escaper->escapeHtml("A table name was not sent.");
            $data = array();
            json_response($status, $status_message, $data);
        }
        else
        {
            // Get the table name
            $table = get_param("GET", 'table');

            // If the table name is valid
            if (is_simplerisk_db_table($table))
            {
                $data = get_full_table($table);
                $status = 200;
                $status_message = "Table retrieved successfully.";

                // Return a JSON response
                json_response($status, $status_message, $data);
            }
            // The table name is not valid
            else
            {
                $status = "400";
                $status_message = $escaper->escapeHtml("An invalid table name was provided.");
                $data = array();
                json_response($status, $status_message, $data);
            }
        }
    }
    else
    {
        unauthorized_access();
    }
}

/*******************************************************
 * FUNCTION: GET DATA FOR FRAMEWORK CONTROLS DATATABLE *
 *******************************************************/
function getFrameworkControlsDatatable(){
    global $lang;
    global $escaper;

    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("control", "", 2);

    }
    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $draw = $escaper->escapeHtml($_POST['draw']);
        $control_class = isset($_POST['control_class']) ? $_POST['control_class'] : [];
        $control_phase = isset($_POST['control_phase']) ? $_POST['control_phase'] : [];
        $control_family = isset($_POST['control_family']) ? $_POST['control_family'] : [];
        $control_owner = isset($_POST['control_owner']) ? $_POST['control_owner'] : [];
        $control_framework = isset($_POST['control_framework']) ? $_POST['control_framework'] : [];
        $control_priority = isset($_POST['control_priority']) ? $_POST['control_priority'] : [];
        $control_type = isset($_POST['control_type']) ? $_POST['control_type'] : [];
        $control_status = isset($_POST['control_status']) ? $_POST['control_status'] : [];
        $control_text = $_POST['control_text'];

        $controls = get_framework_controls_by_filter($control_class, $control_phase, $control_owner, $control_family, $control_framework, $control_priority, $control_type, $control_status, $control_text);
        
        $recordsTotal = count($controls);

        $data = array();

        foreach ($controls as $key=>$control)
        {
            // If it is not requested to view all.
            if($_POST['length'] != -1){
                if($key < $_POST['start']){
                    continue;
                }
                if($key >= ($_POST['start'] + $_POST['length'])){
                    break;
                }
            }
            $edit = '<a href="#" class="control-block--edit pull-right" title="'.$escaper->escapeHtml($lang["Edit"]).'" data-id="'.$escaper->escapeHtml($control['id']).'"><i class="fa fa-edit"></i></a>';
            // Remove clone button if user has no permission for add new controls
            if(empty($_SESSION['add_new_controls']))
            {
                $clone = "";
            }
            // Add clone button if user has the permission
            else
            {
                $clone = '<a href="#" class="control-block--clone pull-right" title="'.$escaper->escapeHtml($lang["Clone"]).'" data-id="'.$escaper->escapeHtml($control['id']).'"><i class="fa fa-clone"></i></a>';
            }
            $delete = '<a href="javascript:void(0);" class="control-block--delete pull-right" title="'.$escaper->escapeHtml($lang["Delete"]).'" data-id="'.$escaper->escapeHtml($control['id']).'"><i class="fa fa-trash"></i></a>';
            $html = "<div class='control-block item-block clearfix'>\n";
                $html .= "<div class='control-block--header clearfix' data-project=''>\n";

                    $html .= "<div class='checkbox-in-div'>\n";
                        $html .= "<input type='checkbox' name='control_ids[]' value='".$escaper->escapeHtml($control['id'])."'>\n";
                    $html .= "</div>\n";
                    
                    $html .= "<div class='control-block--row text-right'>\n";
                        $html .= $delete.$clone.$edit;
                    $html .= "</div>\n";
                    $html .= "<div class='control-block--row control-content'>\n";
                    if (customization_extra())
                    {
                        $html .= "<div class='row-fluid'>";
                            $html .= display_detail_control_fields_view('top', $active_fields, $control);
                        $html .= "</div>";
                        $html .= "<div class='row-fluid'>";
                            $html .=  "<div class='span5 left-panel'>\n";
                                $html .= display_detail_control_fields_view('left', $active_fields, $control);
                            $html .= "</div>";
                            $html .=  "<div class='span5 right-panel'>\n";
                                $html .= display_detail_control_fields_view('right', $active_fields, $control);
                            $html .= "</div>";
                        $html .= "</div>";
                        $html .= "<div class='row-fluid'>";
                            $html .= display_detail_control_fields_view('bottom', $active_fields, $control);
                        $html .= "</div>";
                    } else {
                        $html .= "<div class='row-fluid'>";
                            $html .= display_control_name_view($control['short_name'], 'top');
                            $html .= display_control_longname_view($control['long_name'], 'top');
                            $html .= display_control_number_view2($control['control_number'], 'top');
                        $html .= "</div>";
                        $html .= "<div class='row-fluid'>";
                            $html .=  "<div class='span5 left-panel'>\n";
                                $html .= display_control_owner_view($control['control_owner_name'], 'left');
                                $html .= display_control_priority_view($control['control_priority_name'], 'left');
                                $html .= display_current_maturity_view($control['control_maturity_name'], 'left');
                                $html .= display_desired_maturity_view($control['desired_maturity_name'], 'left');
                                $html .= display_control_class_view($control['control_class_name'], 'left');
                            $html .= "</div>";
                            $html .=  "<div class='span5 right-panel'>\n";
                                $html .= display_control_phase_view($control['control_phase_name'], 'right');
                                $html .= display_control_family_view($control['family_short_name'], 'right');
                                $html .= display_control_mitigation_percent_view($control['mitigation_percent'], 'right');
                                $html .= display_control_type_view($control['control_type_ids'], 'right');
                                $html .= display_control_status_view($control['control_status'], 'right');
                            $html .= "</div>";
                        $html .= "</div>";
                        $html .= "<div class='row-fluid'>";
                            $html .= display_control_description_view($control['description'], 'bottom');
                            $html .= display_supplemental_guidance_view($control['supplemental_guidance'], 'bottom');
                            $html .= display_mapping_framework_view($control['id'], 'bottom');
                        $html .= "</div>";
                    }
                    $html .= "</div>\n";
                $html .= "</div>\n";
            $html .= "</div>\n";
            $data[] = [$html];
        }
        $classList  = getAvailableControlClassList($control_framework);
        $phaseList  = getAvailableControlPhaseList($control_framework);
        $familyList  = getAvailableControlFamilyList($control_framework);
        $ownerList  = getAvailableControlOwnerList($control_framework);
        $priorityList  = getAvailableControlPriorityList($control_framework);
        $result = array(
            'draw' => $draw,
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'classList' => $classList ,
            'phaseList' => $phaseList ,
            'familyList' => $familyList ,
            'ownerList' => $ownerList ,
            'priorityList' => $priorityList ,
        );
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/********************************************************
 * FUNCTION: GET DATA FOR Mitigation CONTROLS DATATABLE *
 ********************************************************/
function getMitigationControlsDatatable(){
    global $lang;
    global $escaper;

    if (check_permission("riskmanagement")) {
        $draw = $escaper->escapeHtml($_GET['draw']);
        $flag = $escaper->escapeHtml($_GET['flag']);
        $mitigation_id = $escaper->escapeHtml($_GET['mitigation_id']);
        $control_ids = $_GET['control_ids'];
        $control_id_array = str_getcsv($control_ids);
    
        $controls = get_framework_controls($control_ids);
    
        $recordsTotal = count($controls);
    
        $data = array();
    
        foreach ($controls as $key=>$control)
        {
            // If it is not requested to view all.
            if($_GET['length'] != -1){
                if($key < $_GET['start']){
                    continue;
                }
                if($key >= ($_GET['start'] + $_GET['length'])){
                    break;
                }
            }
            $html = "<div class='control-block item-block clearfix'>\n";
                $html .= "<div class='control-block--header clearfix' data-project=''>\n";
    
                    $html .= "<br>\n";
                    $html .= "<div class='control-block--row'>\n";
                        $html .= "<table width='100%'>\n";
                            $html .= "<tr>\n";
                                $html .= "<td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlLongName'])."</strong>: </td>\n";
                                $html .= "<td colspan='5'>".$escaper->escapeHtml($control['long_name'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlShortName'])."</strong>: </td>\n";
                                $html .= "<td width='57%' colspan='3'>".$escaper->escapeHtml($control['short_name'])."</td>\n";
                                $html .= "<td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlOwner'])."</strong>: </td>\n";
                                $html .= "<td width='17%'>".$escaper->escapeHtml($control['control_owner_name'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td  align='right'><strong>".$escaper->escapeHtml($lang['ControlClass'])."</strong>: </td>\n";
                                $html .= "<td>".$escaper->escapeHtml($control['control_class_name'])."</td>\n";
                                $html .= "<td  align='right'><strong>".$escaper->escapeHtml($lang['ControlPhase'])."</strong>: </td>\n";
                                $html .= "<td>".$escaper->escapeHtml($control['control_phase_name'])."</td>\n";
                                $html .= "<td  align='right'><strong>".$escaper->escapeHtml($lang['ControlNumber'])."</strong>: </td>\n";
                                $html .= "<td>".$escaper->escapeHtml($control['control_number'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td align='right'><strong>".$escaper->escapeHtml($lang['ControlPriority'])."</strong>: </td>\n";
                                $html .= "<td>".$escaper->escapeHtml($control['control_priority_name'])."</td>\n";
                                $html .= "<td width='200px' align='right'><strong>".$escaper->escapeHtml($lang['ControlFamily'])."</strong>: </td>\n";
                                $html .= "<td>".$escaper->escapeHtml($control['family_short_name'])."</td>\n";
                                $html .= "<td width='200px' align='right'><strong>".$escaper->escapeHtml($lang['MitigationPercent'])."</strong>: </td>\n";
                                $html .= "<td>".$escaper->escapeHtml($control['mitigation_percent'])."%</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td align='right'><strong>".$escaper->escapeHtml($lang['Description'])."</strong>: </td>\n";
                                $html .= "<td colspan='5'>".$escaper->escapeHtml($control['description'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td align='right'><strong>".$escaper->escapeHtml($lang['SupplementalGuidance'])."</strong>: </td>\n";
                                $html .= "<td colspan='5'>".$escaper->escapeHtml($control['supplemental_guidance'])."</td>\n";
                            $html .= "</tr>\n";
                        $html .= "</table>\n";
                        $mapped_frameworks = get_mapping_control_frameworks($control['id']);
                        $html .= "<div class='container-fluid'>\n";
                            $html .= "<div class='well'>";
                                $html .= "<h5><span>".$escaper->escapeHtml($lang['MappedControlFrameworks'])."</span></h5>";
                                $html .= "<table width='100%' class='table table-bordered'>\n";
                                    $html .= "<tr>\n";
                                        $html .= "<th width='50%'>".$escaper->escapeHtml($lang['Framework'])."</th>\n";
                                        $html .= "<th width='35%'>".$escaper->escapeHtml($lang['Control'])."</th>\n";
                                    $html .= "</tr>\n";
                                    foreach ($mapped_frameworks as $framework){
                                        $html .= "<tr>\n";
                                            $html .= "<td>".$escaper->escapeHtml($framework['framework_name'])."</td>\n";
                                            $html .= "<td>".$escaper->escapeHtml($framework['reference_name'])."</td>\n";
                                        $html .= "</tr>\n";
                                    }
                                $html .= "</table>\n";
                            $html .= "</div>\n";
                        $html .= "</div>\n";
                    $html .= "</div>\n";
            $validation = get_mitigation_to_controls($mitigation_id,$control['id']);
            $control_status_names = get_names_by_multi_values("control_type", $control['control_type_ids']);
            $files = get_validation_files($mitigation_id, $control['id']);
            $html .= "<div class='container-fluid'>\n";
            $validation_details = isset($validation["validation_details"]) ? $validation["validation_details"] : "";
            $validation_owner = isset($validation["validation_owner"]) ? $validation["validation_owner"] : 0;
            $validation_mitigation_percent = (isset($validation["validation_details"]) && $validation["validation_mitigation_percent"] >= 0 && $validation["validation_mitigation_percent"] <= 100) ? $validation["validation_mitigation_percent"] : 0;
            if($flag == "edit"){
                if($validation_mitigation_percent && $validation_details != "") {
                    $arrow_class = "fa-caret-down";
                    $panel_css ="";
                } else {
                    $arrow_class = "fa-caret-right";
                    $panel_css ="display: none;";
                }
                $html .= "<div class='well'>";
                    $html .= "<h5 class='collapsible--toggle'><span><i class='fa ".$arrow_class."'></i>".$escaper->escapeHtml($lang['ControlValidation'])."</span></h5>";
                    $html .= "<div class='collapsible' style='".$panel_css."'>";
                        $html .= "<div class='row-fluid'>";
                            $html .= "<div class='span4'>
                                ".$escaper->escapeHtml($lang['Details']).":<br>
                                <textarea class='active-textfield' title='".$escaper->escapeHtml($lang['Details']) ."' name='validation_details_".$control['id']."' style='width:100%;' rows='3'>".$escaper->escapeHtml($validation_details)."</textarea>
                            </div>";
                        $html .= "</div>";
                        $html .= "<div class='row-fluid'>";
                            $html .= "<div class='span4'>
                                ".$escaper->escapeHtml($lang['Owner']).":<br>
                                ".create_dropdown("enabled_users", $validation_owner, "validation_owner_".$control['id'], true, false, true)."
                            </div>";
                        $html .= "</div>";
                        $html .= "<div class='row-fluid'>";
                            $html .= "<div class='span4'>
                                ".$escaper->escapeHtml($lang['MitigationPercent']).":<br>
                                <input type='number' min='0' max='100' name='validation_mitigation_percent_".$control['id']."' value='".$escaper->escapeHtml($validation_mitigation_percent) ."' size='50' class='percent active-textfield' />
                            </div>";
                        $html .= "</div>\n";
                        $html .= "<div class='row-fluid'>";
                            $exist_files = "";
                            foreach ($files as $file)
                            {
                                $exist_files .= "<li>
                                    <div class='file-name'><a href=\"download.php?id=" . $escaper->escapeHtml($file['id']) . "&file_type=validation_file\" target=\"_blank\" />" . $escaper->escapeHtml($file['name']) . "</a></div>
                                    <a href='#' class='remove-file' ><i class='fa fa-times'></i></a>
                                    <input type='hidden' name='file_ids_".$control['id']."[]' value='".$escaper->escapeHtml($file['id'])."'>
                                </li>";
                            }
                            $html .= "<div class='span4'>
                                ".$escaper->escapeHtml($lang['UploadArtifact']).":<br>
                                    <div class='file-uploader'>
                                        <div class='file_name' data-file='artifact-file-".$control['id']."'></div>
                                        <script>
                                              var max_upload_size = ".$escaper->escapeJs(get_setting('max_upload_size', 0)).";
                                              var fileTooBigMessage = '".$escaper->escapeJs($lang['FileIsTooBigToUpload'])."';
                                        </script>
                                        <label for=\"artifact-file-upload-".$control['id']."\" class=\"btn active-textfield\">".$escaper->escapeHtml($lang['ChooseFile'])."</label> <span class=\"file-count-html\"><span class=\"file-count\">".count($files)."</span> ".$escaper->escapeHtml($lang['FileAdded'])."</span>
                                        <p><font size=\"2\"><strong>Max ". $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)) ." Mb</strong></font></p>
                                        <ul class=\"exist-files\">".$exist_files."</ul>
                                        <ul class=\"file-list\"></ul>
                                        <input type=\"file\" name=\"artifact-file-".$control['id']."[]\" id=\"artifact-file-upload-".$control['id']."\" class=\"hidden-file-upload active\" />
                                    </div>
                            </div>";
                        $html .= "</div>\n";
                        $html .= "<div class='row-fluid'>";
                            $html .= "<div class='span4'>
                                ".$escaper->escapeHtml($lang['ControlType']).": ".$escaper->escapeHtml($control_status_names)."
                            </div>";
                        $html .= "</div>";
                        if(strpos($control_status_names, "Enterprise") !== false) {
                            $control_status = array("1" => $escaper->escapeHtml($lang["Pass"]), "0" => $escaper->escapeHtml($lang["Fail"]));
                            $html .= "<div class='row-fluid'>";
                                $html .= "<div class='span4'>
                                    ".$escaper->escapeHtml($lang['ControlStatus']).": ".$control_status[$control['control_status']]."
                                </div>";
                            $html .= "</div>";
                        }
                    $html .= "</div>\n";
                $html .= "</div>\n";
            }
            if($flag == "view" && ($validation_details || $validation_details || $validation_mitigation_percent)){
                $html .= "<div class='well'>";
                    $html .= "<h5><span>".$escaper->escapeHtml($lang['ControlValidation'])."</span></h5>";
                    $html .= "<div class='row-fluid'>";
                        $html .= "<div class='span4'>
                            <b>".$escaper->escapeHtml($lang['Details']).":</b>&nbsp;
                            ".nl2br($escaper->escapeHtml($validation_details))."
                        </div>";
                        $html .= "</div>";
                        $html .= "<div class='row-fluid'>";
                        $html .= "<div class='span4'>
                            <b>".$escaper->escapeHtml($lang['Owner']).":</b>&nbsp;
                            ".$escaper->escapeHtml(get_name_by_value("user", $validation_details))."
                        </div>";
                    $html .= "</div>";
                    $html .= "<div class='row-fluid'>";
                        $html .= "<div class='span4'>
                            <b>".$escaper->escapeHtml($lang['MitigationPercent']).":</b>&nbsp;
                            ".$escaper->escapeHtml($validation_mitigation_percent)." %
                        </div>";
                    $html .= "</div>\n";
                    $html .= "<div class='row-fluid'>";
                        $html .= "<div class='span4'><b>".$escaper->escapeHtml($lang['UploadArtifact']).":</b>&nbsp;";
                        foreach ($files as $file)
                        {
                            $html .= "<div class =\"doc-link edit-mode\"><a href=\"download.php?id=" . $escaper->escapeHtml($file['id']) . "&file_type=validation_file\" >" . $escaper->escapeHtml($file['name']) . "</a></div>";
                        }
    
                        $html .= "</div>";
                    $html .= "</div>\n";
                    $html .= "<div class='row-fluid'>";
                        $html .= "<div class='span4'>
                            <b>".$escaper->escapeHtml($lang['ControlType'])."</b>: ".$escaper->escapeHtml(get_names_by_multi_values("control_type", $control['control_type_ids']))."
                        </div>";
                    $html .= "</div>";
              
                    if(strpos($control_status_names, "Enterprise") !== false) {
                        $control_status = array("1" => $escaper->escapeHtml($lang["Pass"]), "0" => $escaper->escapeHtml($lang["Fail"]));
                        $html .= "<div class='row-fluid'>";
                            $html .= "<div class='span4'>
                                <b>".$escaper->escapeHtml($lang['ControlStatus'])."</b>: ".$control_status[$control['control_status']]."
                            </div>";
                        $html .= "</div>";
                    }
                $html .= "</div>\n";
            }
            $html .= "  </div>
                    </div>
                </div>\n";
            $data[] = [$html];
        }
        $result = array(
            'draw' => $draw,
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
        exit;
    } else {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
    }
}

/**********************************************
 * FUNCTION: GET DATA FOR FRAMEWORK DATATABLE *
 **********************************************/
function getFrameworksResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $status = (int)$_GET['status'];
        $result = get_frameworks_as_treegrid($status);
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/*************************************
 * FUNCTION: UPDATE FRAMEWORK STATUS *
 *************************************/
function updateFrameworkStatusResponse()
{
    global $lang, $escaper;

    $status_id  = (int)$_POST['status'];
    $framework_id = (int)$_POST['framework_id'];

    // If user has no permission for modify frameworks
    if(empty($_SESSION['modify_frameworks']))
    {
        $status_message = $escaper->escapeHtml($lang['NoModifyFrameworkPermission']);
        // Display an alert
        set_alert(true, "bad", $status_message);
    }
    // If user has permission for modify frameworks
    else
    {
        update_framework_status($status_id, $framework_id);

        $status_message = $escaper->escapeHtml($lang['FrameworkStatusSuccessUpdate']);

        // Display an alert
        set_alert(true, "good", $status_message);
    }

    json_response(200, $status_message, []);
}

/****************************
 * FUNCTION: ADD CONTROL *
 ****************************/
function addControlResponse()
{
    global $lang, $escaper;
    $control = array(
        'short_name' => isset($_POST['short_name']) ? $_POST['short_name'] : "",
        'long_name' => isset($_POST['long_name']) ? $_POST['long_name'] : "",
        'description' => isset($_POST['description']) ? $_POST['description'] : "",
        'supplemental_guidance' => isset($_POST['supplemental_guidance']) ? $_POST['supplemental_guidance'] : "",
        'control_owner' => isset($_POST['control_owner']) ? (int)$_POST['control_owner'] : 0,
        'control_class' => isset($_POST['control_class']) ? (int)$_POST['control_class'] : 0,
        'control_phase' => isset($_POST['control_phase']) ? (int)$_POST['control_phase'] : 0,
        'control_number' => isset($_POST['control_number']) ? $_POST['control_number'] : "",
        'control_current_maturity' => isset($_POST['control_current_maturity']) ? $_POST['control_current_maturity'] : 0,
        'control_desired_maturity' => isset($_POST['control_desired_maturity']) ? $_POST['control_desired_maturity'] : 0,
        'control_priority' => isset($_POST['control_priority']) ? (int)$_POST['control_priority'] : 0,
        'control_type' => isset($_POST['control_type']) ? $_POST['control_type'] : [],
        'control_status' => isset($_POST['control_status']) ? (int)$_POST['control_status'] : 1,
        'family' => isset($_POST['family']) ? (int)$_POST['family'] : 0,
        'mitigation_percent' => (isset($_POST['mitigation_percent']) && $_POST['mitigation_percent'] >= 0 && $_POST['mitigation_percent'] <= 100) ? (int)$_POST['mitigation_percent'] : 0
    );
    $map_framework_ids = isset($_POST['map_framework_id'])?$_POST['map_framework_id']:array();
    $reference_names = isset($_POST['reference_name'])?$_POST['reference_name']:array();
    $map_frameworks = array();
    foreach($map_framework_ids as $index=>$frameworks){
        $reference_name = isset($reference_names[$index])?$reference_names[$index]:"";
        $map_frameworks[] = array($frameworks,$reference_name);
    }
    $control["map_frameworks"] = $map_frameworks;
    $control_id = "";
    // Check if the control name is null
    if (!$control['short_name'])
    {
        // Display an alert
        set_alert(true, "bad", "The control name cannot be empty.");
    }
    // Otherwise
    else
    {
        // If user has no permission for add new controls
        if(empty($_SESSION['add_new_controls']))
        {
            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoAddControlPermission']));
        }
        // Insert a new control up to 100 chars
        elseif($control_id = add_framework_control($control))
        {
            // Display an alert
            set_alert(true, "good", "A new control was added successfully.");
        }
        else
        {
            // Display an alert
            set_alert(true, "bad", "The control already exists.");
        }
    }
    json_response(200, get_alert(true), array("control_id"=>$control_id));
}
/****************************
 * FUNCTION: UPDATE CONTROL *
 ****************************/
function updateControlResponse()
{
    global $lang, $escaper;

    $control_id = (int)$_POST['control_id'];

    // If user has no permission to modify controls
    if(empty($_SESSION['modify_controls']))
    {
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoModifyControlPermission']));
    }
    // Verify value is an integer
    elseif (is_int($control_id))
    {
        $control = array(
            'short_name' => isset($_POST['short_name']) ? $_POST['short_name'] : "",
            'long_name' => isset($_POST['long_name']) ? $_POST['long_name'] : "",
            'description' => isset($_POST['description']) ? $_POST['description'] : "",
            'supplemental_guidance' => isset($_POST['supplemental_guidance']) ? $_POST['supplemental_guidance'] : "",
            'control_owner' => isset($_POST['control_owner']) ? (int)$_POST['control_owner'] : 0,
            'control_class' => isset($_POST['control_class']) ? (int)$_POST['control_class'] : 0,
            'control_phase' => isset($_POST['control_phase']) ? (int)$_POST['control_phase'] : 0,
            'control_number' => isset($_POST['control_number']) ? $_POST['control_number'] : "",
            'control_current_maturity' => isset($_POST['control_current_maturity']) ? (int)$_POST['control_current_maturity'] : 0,
            'control_desired_maturity' => isset($_POST['control_desired_maturity']) ? (int)$_POST['control_desired_maturity'] : 0,
            'control_priority' => isset($_POST['control_priority']) ? (int)$_POST['control_priority'] : 0,
            'control_type' => isset($_POST['control_type']) ? $_POST['control_type'] : [],
            'control_status' => isset($_POST['control_status']) ? (int)$_POST['control_status'] : 1,
            'family' => isset($_POST['family']) ? (int)$_POST['family'] : 0,
            'mitigation_percent' => (isset($_POST['mitigation_percent']) && $_POST['mitigation_percent'] >= 0 && $_POST['mitigation_percent'] <= 100) ? (int)$_POST['mitigation_percent'] : 0
        );
        $map_framework_ids = isset($_POST['map_framework_id'])?$_POST['map_framework_id']:array();
        $reference_names = isset($_POST['reference_name'])?$_POST['reference_name']:array();
        $map_frameworks = array();
        foreach($map_framework_ids as $index=>$frameworks){
            $reference_name = isset($reference_names[$index])?$reference_names[$index]:"";
            $map_frameworks[] = array($frameworks,$reference_name);
        }
        $control["map_frameworks"] = $map_frameworks;
        // Update the control
        update_framework_control($control_id, $control);

        // Display an alert
        set_alert(true, "good", "An existing control was updated successfully.");

        json_response(200, get_alert(true), array("control_id"=>$control_id));
    }
    // We should never get here as we bound the variable as an int
    else
    {
        // Display an alert
        set_alert(true, "bad", "The control ID was not a valid value.  Please try again.");
        json_response(400, get_alert(true), []);
    }
}


/*************************************
 * FUNCTION: UPDATE FRAMEWORK PARENT *
 *************************************/
function updateFrameworkParentResponse() {

    global $lang;

    if(has_permission('modify_frameworks')){

        $parent  = (int)$_POST['parent'];
        $framework_id = (int)$_POST['framework_id'];

        // Check if the user is going to setup a circular reference
        if ($parent && $framework_id && detect_circular_parent_reference($framework_id, $parent)) {
            set_alert(true, "bad", $lang['FrameworkCantBeItsOwnParent']); //No you don't! Circular reference detected...
            json_response(400, get_alert(true), []);
        } else {
            update_framework_parent($parent, $framework_id);

            set_alert(true, "good", $lang['FrameworkParentUpdated']);
            json_response(200, get_alert(true), []);
        }
    } else {
        set_alert(true, "bad", $lang['NoModifyFrameworkPermission']);
        json_response(400, get_alert(true), []);
    }
}

/*******************************************************************
 * FUNCTION: GET PARENT FRAMEWORKS DROPDOWN WITH NO SELECTED VALUE *
 *******************************************************************/
function getParentFrameworksDropdownResponse()
{
    global $lang, $escaper;
    
    $status = (int)$_GET['status'];

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $frameworks = get_frameworks($status);

        $html = "<select name='parent'>\n";
        $html .= "<option value='0'>--</option>";
        make_tree_options_html($frameworks, 0, $html);
        $html .= "</select>\n";
        json_response(200, "Get parent framework dropdown html", ["html" => $html]);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/******************************************************************
 * FUNCTION: GET PARENT DOCUMENTS DROPDOWN WITH NO SELECTED VALUE *
 ******************************************************************/
function getParentDocumentsDropdownResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $type = $_GET['type'];

//        $documents = get_documents($type);
        $documents = get_documents();
        $options = [];
        foreach($documents as $document)
        {
            $options[] = array(
                'name' => $document['document_name'],
                'value' => $document['id'],
                'parent' => $document['parent'],
            );
        }

        $html = "<select name='parent'>\n";
        $html .= "<option value='0'>--</option>";
        make_tree_options_html($options, 0, $html);
        $html .= "</select>\n";
        json_response(200, "Get parent documents dropdown html", ["html" => $html]);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/****************************************************************
 * FUNCTION: GET PARENT FRAMEWORKS DROPDOWN WITH SELECTED VALUE *
 ****************************************************************/
function getSelectedParentFrameworksDropdownResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $child_id = (int)$_GET['child_id'];

        // Get child framework
        $framework = get_framework($child_id);
        $status = $framework['status'];

        // Parent framework ID
        $selected = $framework['parent'];

        $frameworks = get_frameworks($status);

        // Frameworks removed child framework
        $new_frameworks = [];
        foreach($frameworks as $framework){
            if($framework['value'] != $child_id){
                $new_frameworks[] = $framework;
            }
        }

        $html = "<select name='parent'>\n";
        $html .= "<option value='0'>--</option>";
        make_tree_options_html($new_frameworks, 0, $html, "", $selected);
        $html .= "</select>\n";
        json_response(200, "Get parent framework dropdown html", ["html" => $html]);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/***************************************************************
 * FUNCTION: GET PARENT DOCUMENTS DROPDOWN WITH SELECTED VALUE *
 ***************************************************************/
function getSelectedParentDocumentsDropdownResponse()
{
    global $lang, $escaper;
    
    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $child_id = (int)$_GET['child_id'];
        $type = $_GET['type'];

        // Get child document
        $child_document = get_document_by_id($child_id);

        // Parent document ID
        $selected = $child_document['parent'];

//        $documents = get_documents($type);
        $documents = get_documents();

        // Documents removed child document
        $new_documents = [];
        foreach($documents as $document){
            if($document['id'] != $child_id){
                $document['value'] = $document['id'];
                $document['name'] = $escaper->escapeHtml($document['document_name']);
                $new_documents[] = $document;
            }
        }

        $html = "<select name='parent'>\n";
        $html .= "<option value='0'>--</option>";
        make_tree_options_html($new_documents, 0, $html, "", $selected);
        $html .= "</select>\n";
        json_response(200, "Get parent framework dropdown html", ["html" => $html]);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/**************************************************
 * FUNCTION: GET CONTROL FILTERS BY FRAMEWORK IDS *
 **************************************************/
function getControlFiltersByFrameworksResponse()
{
    global $lang, $escaper;
    
    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $control_framework = isset($_GET['control_framework']) ? $_GET['control_framework'] : [];

        $classList  = getAvailableControlClassList($control_framework);
        $phaseList  = getAvailableControlPhaseList($control_framework);
        $familyList  = getAvailableControlFamilyList($control_framework);
        $ownerList  = getAvailableControlOwnerList($control_framework);
        $priorityList  = getAvailableControlPriorityList($control_framework);

        $result = array(
            'classList' => $classList ,
            'phaseList' => $phaseList ,
            'familyList' => $familyList ,
            'ownerList' => $ownerList ,
            'priorityList' => $priorityList ,
        );
        
        json_response(200, "Get framework control IDs by framework ids", $result);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/***************************************************
 * FUNCTION: GET RELATED CONTROLS BY FRAMEWORK IDS *
 ***************************************************/
function getRelatedControlsByFrameworkIdsResponse()
{
    global $lang, $escaper;
    
    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $fids = get_param("get", "fids", "");
        if($fids)
        {
            $fids_arr = str_getcsv($fids);
            $controls = get_framework_controls_by_filter("all", "all", "all", "all", $fids_arr);
            
            $control_ids = array_map(function($control) use ($escaper){
                return array(
                    'value' => $control['id'],
                    'name' => $escaper->escapeHtml($control['short_name']),
                );
                
            }, $controls);
        }
        // If fids is empty, returns empty
        else
        {
            $control_ids = [];
        }
        
        json_response(200, "Get framework control IDs by framework ids", ["control_ids" => $control_ids]);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/***************************************************************
 * FUNCTION: Initiate FRAMEWORK CONTROL TESTS AND GET RESPONSE *
 ***************************************************************/
function initiateFrameworkControlTestsResponse()
{
    global $lang, $escaper;
    
    // If the user has compliance permissions
    if (check_permission("compliance") && isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1)
    {
        $id     = (int)$_POST['id'];
        $type   = $_POST['type'];
        $tags   = empty($_POST['tags']) ? [] : $_POST['tags'];

        if($name = initiate_framework_control_tests($type, $id, $tags))
        {
            if($type == 'framework'){
                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedAllTestsUnderFramework', ['framework' => $name])));
            }elseif($type == 'control'){
                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedAllTestsUnderControl', ['control' => $name])));
            }elseif($type == 'test'){
                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedTest', ['test' => $name])));
            }
            json_response(200, get_alert(true), []);
        }
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['FailedInitiate']));
            json_response(400, get_alert(true), NULL);
        }
    }
    else
    {
        set_alert(true, "bad", $lang['NoPermissionForCompliance']);
        json_response(400, get_alert(true), NULL);
    }
    
}

/***************************************************************
 * FUNCTION: AUDIT TIMELINE RESPONSE *
 ***************************************************************/
function auditTimelineResponse()
{
    global $lang, $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance"))
    {
        $draw = $escaper->escapeHtml($_GET['draw']);

        $orderColumn = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        $orderColumnName = isset($_GET['columns'][$orderColumn]['name']) ? $_GET['columns'][$orderColumn]['name'] : null;
        $orderDir = !empty($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        // Get risks requiring mitigations
        $audit_tests = get_audit_tests($orderColumnName, $orderDir);
        $recordsTotal = count($audit_tests);
        $data = array();

        foreach ($audit_tests as $key=>$audit_test)
        {
            // If it is not requested to view all
            if($_GET['length'] != -1){
                if($key < $_GET['start']){
                    continue;
                }
                if($key >= ($_GET['start'] + $_GET['length'])){
                    break;
                }
            }
            
            $active_audits_url = $_SESSION['base_url'].'/compliance/active_audits.php?test_id='.$audit_test['id'];
            $past_audits_url = $_SESSION['base_url'].'/compliance/past_audits.php?test_id='.$audit_test['id'];
            $buttons = '<button class="btn-initiate-audit" id="'.$audit_test['id'].'" style="width:100%;">'.$escaper->escapeHtml($lang['InitiateAudit']).'</button>
                        <a class="btn" href="'.$active_audits_url.'" target="_blank">'.$escaper->escapeHtml($lang['ViewActiveAudits']).'</a>
                        <a class="btn" href="'.$past_audits_url.'" target="_blank">'.$escaper->escapeHtml($lang['ViewPastAudits']).'</a>';

            $data[] = [
                $buttons,
                $escaper->escapeHtml($audit_test['name']),
                $escaper->escapeHtml($audit_test['framework_names']),
                $audit_test['last_date'],
                $audit_test['last_test_result'],
                $audit_test['next_date'],
            ];
        }
    }
    else
    {
        $draw = $escaper->escapeHtml($_GET['draw']);
        $data = [];
        $recordsTotal = 0;
        $recordsTotal = 0;
    }

    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );
    echo json_encode($result);
    exit;
    
}

/*******************************
 * FUNCTION: GET CONTROL BY ID *
 *******************************/
function getControlResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $id = $_GET['control_id'];
        $control = get_framework_control($id);
        $mapped_frameworks = get_mapping_control_frameworks($id);
        $html = "";
        foreach ($mapped_frameworks as $framework){
            $html .= "<tr>\n";
                $html .= "<td>".create_dropdown('frameworks', $framework['framework'],'map_framework_id[]', true, false, true, 'required')."</td>\n";
                $html .= "<td><input type='text' name='reference_name[]' value='".$escaper->escapeHtml($framework['reference_name'])."' class='form-control' maxlength='100' required></td>\n";
                $html .= "<td><a href='javascript:void(0);' class='control-block--delete-mapping' title='".$escaper->escapeHtml($lang["Delete"])."'><i class='fa fa-trash'></i></a></td>\n";
            $html .= "</tr>\n";
        }
        json_response(200, "Get framework control by ID", ["control" => $control, "mapped_frameworks" => $html]);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/*********************************
 * FUNCTION: GET FRAMEWORK BY ID *
 *********************************/
function getFrameworkResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $id = $_GET['framework_id'];
        $framework = get_framework($id);
        json_response(200, "Get framework by ID", ["framework" => $framework]);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/***********************************************************************
 * FUNCTION: RETURN JSON DATA FOR DEFINE TESTS DATATABLE IN COMPLIANCE *
 ***********************************************************************/
function getDefineTestsResponse()
{
    global $lang;
    global $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance"))
    {
        $draw = $escaper->escapeHtml($_GET['draw']);
        $control_framework = empty($_GET['control_framework']) ? [] : $_GET['control_framework'];
        $control_family = isset($_GET['control_family']) ? $_GET['control_family'] : [];
        $control_name = isset($_GET['control_name']) ? $_GET['control_name'] : "";

        $controls = get_framework_controls_by_filter("all", "all", "all", $control_family, $control_framework, "all", "all", "all", $control_name);
        $recordsTotal = count($controls);

         // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // It means that either the user is an admin
            // or everyone has access to the tests/audits.
            // It means we can treat Team Separation like it is disabled        
            $separation_enabled = !should_skip_test_and_audit_permission_check();
        } else
            $separation_enabled = false;
        
        $data = array();
        foreach ($controls as $key=>$control)
        {
            // If it is not requested to view all
            if($_GET['length'] != -1){
                if($key < $_GET['start']){
                    continue;
                }
                if($key >= ($_GET['start'] + $_GET['length'])){
                    break;
                }
            }

            $tests = get_framework_control_tests_by_control_id($control['id']);
            $html = "<div class='control-block item-block clearfix'>\n";
                $html .= "<div class='control-block--header clearfix' data-project=''>\n";
                    $html .= "<br>\n";
                    $html .= "<div class='control-block--row'>\n";
                        $html .= "<table width='100%'>\n";
                            $html .= "<tr>\n";
                                $html .= "<td width='20%' align='right'><strong>".$escaper->escapeHtml($lang['ControlLongName'])."</strong>: </td>\n";
                                $html .= "<td width='80%'>".$escaper->escapeHtml($control['long_name'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td align='right'><strong>".$escaper->escapeHtml($lang['ControlOwner'])."</strong>: </td>\n";
                                $html .= "<td>".$escaper->escapeHtml($control['control_owner_name'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td align='right'><strong>". $escaper->escapeHtml($lang['Description']) ."</strong>: </td>\n";
                                $html .= "<td>". nl2br($escaper->escapeHtml($control['description'])) ."</td>\n";
                            $html .= "</tr>\n";
                            $mapped_frameworks = get_mapping_control_frameworks($control['id']);
                            if(count($mapped_frameworks) > 0){
                                $html .= "<tr><td colspan='2'>\n";
                                    $html .= "<div class='container-fluid'>\n";
                                        $html .= "<div class='well'>";
                                            $html .= "<h5><span>".$escaper->escapeHtml($lang['MappedControlFrameworks'])."</span></h5>";
                                            $html .= "<table width='100%' class='table table-bordered'>\n";
                                                $html .= "<tr>\n";
                                                    $html .= "<th width='50%'>".$escaper->escapeHtml($lang['Framework'])."</th>\n";
                                                    $html .= "<th width='35%'>".$escaper->escapeHtml($lang['Control'])."</th>\n";
                                                $html .= "</tr>\n";
                                                foreach ($mapped_frameworks as $framework){
                                                    $html .= "<tr>\n";
                                                        $html .= "<td>".$escaper->escapeHtml($framework['framework_name'])."</td>\n";
                                                        $html .= "<td>".$escaper->escapeHtml($framework['reference_name'])."</td>\n";
                                                    $html .= "</tr>\n";
                                                }
                                            $html .= "</table>\n";
                                        $html .= "</div>\n";
                                    $html .= "</div>\n";
                                $html .= "</td></tr>\n";
                            }
                        $html .= "</table>\n";
                    $html .= "</div>\n";

                    if(isset($_SESSION["define_tests"]) && $_SESSION["define_tests"] == 1){
                        $html .= "<div class='text-right'>\n";
                            $html .= "<a href='#test--add' data-control-id='". $control['id'] ."' role='button' data-toggle='modal' class='btn add-test'>".$escaper->escapeHtml($lang['AddTest'])."</a>";
                        $html .= "</div>\n";
                    }
                    $html .= "<div class='framework-control-test-list'>\n";
                        $html .= "<table width='100%' class='table table-bordered table-striped table-condensed sortable'>\n";
                            $html .= "
                                <thead>
                                    <tr>
                                        <th>".$escaper->escapeHtml($lang['ID'])."</th>
                                        <th>".$escaper->escapeHtml($lang['TestName'])."</th>
                                        <th>".$escaper->escapeHtml($lang['Tester'])."</th>
                                        <th>".$escaper->escapeHtml($lang['AdditionalStakeholders'])."</th>
                                        <th width='150px'>".$escaper->escapeHtml($lang['Tags'])."</th>
                                        <th width='110px'>".$escaper->escapeHtml($lang['TestFrequency'])."</th>
                                        <th width='110px'>".$escaper->escapeHtml($lang['LastTestDate'])."</th>
                                        <th width='110px'>".$escaper->escapeHtml($lang['NextTestDate'])."</th>
                                        <th width='130px'>".$escaper->escapeHtml($lang['ApproximateTime'])."</th>
                                        <th width='50px'>&nbsp;</th>
                                    </tr>
                                </thead>
                            ";
                            $html .= "<tbody>";
                                foreach($tests as $test){
                                    if ($separation_enabled) {
                                        if (!is_user_allowed_to_access($_SESSION['uid'], $test['id'], 'test')) {
                                            continue;
                                        }
                                    }
                                    $tags_view = "";
                                    if ($test['tags']) {
                                        foreach(str_getcsv($test['tags']) as $tag) {
                                            $tags_view .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin-right:2px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
                                        }
                                    } else {
                                        $tags_view .= "";
                                    }
                                    
                                    $last_date = format_date($test['last_date']);
                                    $next_date = format_date($test['next_date']);
                                    if(isset($_SESSION["edit_tests"]) && $_SESSION["edit_tests"] == 1){
                                        $edit_row = "<a data-id='".$escaper->escapeHtml($test['id'])."' class='edit-test' data-id=\"{$escaper->escapeHtml($test['id'])}\"><i class=\"fa fa-edit\"></i></a>";
                                    } else $edit_row = "";
                                    if(isset($_SESSION["delete_tests"]) && $_SESSION["delete_tests"] == 1){
                                        $delete_row = "<a class='delete-row' data-toggle=\"modal\" data-id=\"{$escaper->escapeHtml($test['id'])}\"><i class=\"fa fa-trash\"></i></a>";
                                    } else $delete_row = "";

                                    
                                    $html .= "
                                        <tr>
                                            <td>".$escaper->escapeHtml($test['id'])."</td>
                                            <td>".$escaper->escapeHtml($test['name'])."</td>
                                            <td>".$escaper->escapeHtml($test['tester_name'])."</td>
                                            <td>".$escaper->escapeHtml(get_stakeholder_names($test['additional_stakeholders'], 3))."</td>
                                            <td>".$tags_view."</td>
                                            <td class='text-center'>".(int)$test['test_frequency']. " " .$escaper->escapeHtml($test['test_frequency'] > 1 ? $escaper->escapeHtml($lang['days']) : $escaper->escapeHtml($lang['Day']))."</td>
                                            <td class='text-center'>".$escaper->escapeHtml($last_date)."</td>
                                            <td class='text-center'>".$escaper->escapeHtml($next_date)."</td>
                                            <td class='text-center'>".(int)$test['approximate_time']. " " .$escaper->escapeHtml($test['approximate_time'] > 1 ? $escaper->escapeHtml($lang['minutes']) : $escaper->escapeHtml($lang['minute']))."</td>
                                            <td class='text-center'>{$edit_row}&nbsp;&nbsp;{$delete_row}
                                            </td>
                                        </tr>
                                    ";
                                }
                            $html .= "</tbody>";
                        $html .= "</table>\n";
                    $html .= "</div>\n";
                $html .= "</div>\n";
            $html .= "</div>\n";
            $data[] = [$html];
        }
        $result = array(
            'draw' => $draw,
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
    }
    exit;
}

/***********************
 * FUNCTION: GET TESTS *
 ***********************/
function getTestResponse()
{
    global $lang, $escaper;
    
    $id = (int)$_GET['id'];

    // If the user has compliance permissions
    if (check_permission("compliance"))
    {
        // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
            if (!is_user_allowed_to_access($_SESSION['uid'], $id, 'test')) {
                set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForThisTest']));
                json_response(400, get_alert(true), null);
                return;
            }
        }

        $test = get_framework_control_test_by_id($id);
        if($test){

            $test['last_date'] = format_date($test['last_date']);
            $test['next_date'] = format_date($test['next_date']);
            
            json_response(200, "success", $test);
        }else{
            json_response(400, "Ivalid test ID.", NULL);
        }
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
    }

}

/*******************************************************
 * FUNCTION: RETURN JSON DATA FOR INITIATE AUDITS TREE *
 *******************************************************/
function getInitiateTestAuditsResponse()
{
    global $lang;
    global $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance"))
    {
        $filter_by_text         = $_GET["filter_by_text"];
        $filter_by_status       = empty($_GET["filter_by_status"]) ? [] : $_GET["filter_by_status"];
        $filter_by_frequency    = $_GET["filter_by_frequency"];
        $filter_by_framework    = empty($_GET["filter_by_framework"]) ? [] : $_GET["filter_by_framework"];
        $filter_by_control      = $_GET["filter_by_control"];

        $results = array();

        // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // It means that either the user is an admin
            // or everyone has access to the tests/audits.
            // It means we can treat Team Separation like it is disabled
            if (should_skip_test_and_audit_permission_check()) {
                $separation_enabled = false;
            } else {
                $separation_enabled = true;
                $compliance_separation_access_info = get_compliance_separation_access_info();
            }
        } else
            $separation_enabled = false;

        // If framework was loaded
        if(empty($_GET['id'])){
            // Get active frameworks
            $frameworks = get_initiate_frameworks_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control);
            
            foreach($frameworks as $framework){
                if ($separation_enabled && !in_array($framework['value'], $compliance_separation_access_info['frameworks']))
                    continue;

                if(isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1) 
                    $action = "<div class='text-center'><button data-id='{$framework['value']}' class='initiate-framework-audit-btn' >".$escaper->escapeHtml($lang['InitiateFrameworkAudit'])."</button></div>"; 
                else $action = "";
                $results[] = array(
                    'id' => 'framework_'.$framework['value'],
                    'state' => 'closed',
                    'name' => "<a class='framework-name' data-id='{$framework['value']}' href='' title='".$escaper->escapeHtml($lang['Framework'])."'>".$escaper->escapeHtml($framework['name'])."</a>",
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework['last_audit_date'])),
                    'test_frequency' => $escaper->escapeHtml($framework['desired_frequency']),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework['next_audit_date'])),
                    'status' => $escaper->escapeHtml($framework['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => $action
                );
            }
        }
        // If a framework node was clicked
        elseif(stripos($_GET['id'], "framework_") !== false){
            $framework_value = (int)str_replace("framework_", "", $_GET['id']);
            $framework_controls = get_initiate_controls_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control, $framework_value);
            foreach($framework_controls as $framework_control){
                if ($separation_enabled && !in_array($framework_control['id'], $compliance_separation_access_info['framework_controls']))
                    continue;
                
                if(isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1) 
                    $action = "<div class='text-center'><button data-id='{$framework_control['id']}' class='initiate-control-audit-btn' >".$escaper->escapeHtml($lang['InitiateControlAudit'])."</button></div>"; 
                else $action = "";
                $results[] = array(
                    'id' => "control_".$framework_value."_".$framework_control['id'],
                    'state' => 'closed',
                    'name' => "<a class='control-name' data-id='{$framework_control['id']}' href='' title='".$escaper->escapeHtml($lang['Control'])."'>".$escaper->escapeHtml($framework_control['short_name'])."</a>",
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework_control['last_audit_date'])),
                    'test_frequency' => $escaper->escapeHtml($framework_control['desired_frequency']),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework_control['next_audit_date'])),
                    'status' => $escaper->escapeHtml($framework_control['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => $action
                );
            }
        }
        elseif(stripos($_GET['id'], "control_") !== false)
        {
            $framework_and_control = str_replace("control_", "", $_GET['id']);
            $framework_id = (int)explode("_", $framework_and_control)[0];
            $control_id = (int)explode("_", $framework_and_control)[1];

            $framework_control_tests = get_initiate_tests_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control, $framework_id, $control_id);
            foreach($framework_control_tests as $framework_control_test){
                if ($separation_enabled && !in_array($framework_control_test['id'], $compliance_separation_access_info['framework_control_tests']))
                    continue;

                if(isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1) 
                    $action = "<div class='text-center'><button data-id='{$framework_control_test['id']}' class='initiate-test-btn' >".$escaper->escapeHtml($lang['InitiateTest'])."</button></div>"; 
                else $action = "";
                $results[] = array(
                    'id' => "test_".$framework_and_control."_".$framework_control_test['id'],
                    'state' => 'open',
                    'name' => "<a class='test-name' data-id='{$framework_control_test['id']}' href='".$_SESSION['base_url']."/' title='".$escaper->escapeHtml($lang['Test'])."'>".$escaper->escapeHtml($framework_control_test['name'])."</a>",
                    'test_frequency' => $escaper->escapeHtml($framework_control_test['test_frequency']),
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework_control_test['last_date'])),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework_control_test['next_date'])),
                    'status' => $escaper->escapeHtml($framework_control_test['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => $action
                );
            }
        }
        echo json_encode($results);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
    }    

    exit;
}

/**********************************************************************
 * FUNCTION: RETURN JSON DATA FOR PAST AUDITS DATATABLE IN COMPLIANCE *
 **********************************************************************/
function getPastTestAuditsResponse()
{
    global $lang;
    global $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance"))
    {
        $draw = $escaper->escapeHtml($_POST['draw']);

        // Filter params
        $filters = array(
            "filter_text"   => $escaper->escapeHtml($_POST['filter_text']),
            "filter_control"        => empty($_POST['filter_control']) ? [] : $_POST['filter_control'],
            "filter_test_result"    => empty($_POST['filter_test_result']) ? [] : $_POST['filter_test_result'],
            "filter_framework"      => empty($_POST['filter_framework']) ? [] : $_POST['filter_framework'],
            "filter_tags"           => empty($_POST['filter_tags']) ? [] : $_POST['filter_tags'],
            "filter_start_audit_date"   => $_POST['filter_start_audit_date'] ? get_standard_date_from_default_format($_POST['filter_start_audit_date']) : "",
            "filter_end_audit_date"     => $_POST['filter_end_audit_date'] ? get_standard_date_from_default_format($_POST['filter_end_audit_date']) : "",
            "filter_testname"   => empty($_POST['filter_testname']) ? '' : $_POST['filter_testname']
        );

        $columnNames = array(
            "test_name",
            "last_date",
            "control_name",
            "framework_name",
            "tags",
            "status",
            "test_result",
        );
        $orderColumn = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : -1;
        $columnName = isset($columnNames[$orderColumn]) ? $columnNames[$orderColumn] : false;
        $orderDir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) == "asc" ? "asc" : "desc";

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
            }
        }

        // Get past tests
        $past_test_audits = get_framework_control_test_audits(false, $columnName, $orderDir, $filters, $column_filters);

        $recordsTotal = count($past_test_audits);

        $data = array();

        foreach ($past_test_audits as $key=>$test_audit)
        {
            // If it is not requested to view all
            if($_POST['length'] != -1){
                if($key < $_POST['start']){
                    continue;
                }
                if($key >= ($_POST['start'] + $_POST['length'])){
                    break;
                }
            }

            $background_class = $escaper->escapeHtml($test_audit['background_class']);

            $test_date = format_date($test_audit['test_date']);
            if(isset($_SESSION["modify_audits"]) && $_SESSION["modify_audits"] == 1){
                $reopen_button = "<button class='reopen' data-id='{$test_audit['id']}'>".$escaper->escapeHtml($lang['Reopen'])."</button>";
            } else $reopen_button = "";

            $tags_view = "";
            if ($test_audit['tags']) {
                foreach(str_getcsv($test_audit['tags']) as $tag) {
                    $tags_view .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin-right:2px; margin-bottom:2px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
                }
            } else {
                $tags_view .= "";
            }

            $data[] = [
                "<div ><a href='".$_SESSION['base_url']."/compliance/view_test.php?id=".$test_audit['id']."' class='text-left'>".$escaper->escapeHtml($test_audit['name'])."</a><input type='hidden' class='background-class' data-background='{$background_class}'></div>",
                "<div>".$escaper->escapeHtml($test_date)."</div>",
                "<div >".$escaper->escapeHtml($test_audit['control_name'])."</div>",
                "<div >".$escaper->escapeHtml($test_audit['framework_name'])."</div>",
                "<div >".$tags_view."</div>",
                "<div >".$escaper->escapeHtml($test_audit['audit_status_name'])."</div>",
                "<div >".$escaper->escapeHtml($test_audit['test_result'] ? $test_audit['test_result'] : "--")."</div>",
                "<div class='text-center'>".$reopen_button."</div>",
            ];
        }
        $result = array(
            'draw' => $draw,
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
    }
}

/************************************************************************
 * FUNCTION: RETURN JSON DATA FOR ACTIVE AUDITS DATATABLE IN COMPLIANCE *
 ************************************************************************/
function getActiveTestAuditsResponse()
{
    global $lang;
    global $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance"))
    {
        $draw = $escaper->escapeHtml($_POST['draw']);

        $orderColumn = (int)$_POST['order'][0]['column'];
        $orderDir = strtolower($_POST['order'][0]['dir']) == "asc" ? "asc" : "desc";

        // Filter params
        $filters = array(
            "filter_text"       => $escaper->escapeHtml($_POST['filter_text']),
            "filter_framework"  => empty($_POST['filter_framework']) ? [] : $_POST['filter_framework'],
            "filter_status"     => empty($_POST['filter_status']) ? [] : $_POST['filter_status'],
            "filter_tester"     => empty($_POST['filter_tester']) ? [] : $_POST['filter_tester'],
            "filter_testname"   => empty($_POST['filter_testname']) ? [] : $_POST['filter_testname'],
            "filter_tags"       => empty($_POST['filter_tags']) ? [] : $_POST['filter_tags'],
        );

        $columnNames = array(
            "test_name",
            "test_frequency",
            "tester",
            "additional_stakeholders",
            "objective",
            "control_name",
            "framework_name",
            "tags",
            "status",
            "test_date",
            "last_date",
            "next_date",
            "actions"
        );

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
            }
        }

        // Get active tests
        $active_tests = get_framework_control_test_audits(true, $columnNames[$orderColumn], $orderDir, $filters, $column_filters);

        $recordsTotal = count($active_tests);

        $data = array();

        foreach ($active_tests as $key=>$test)
        {
            // If it is not requested to view all
            if($_POST['length'] != -1){
                if($key < $_POST['start']){
                    continue;
                }
                if($key >= ($_POST['start'] + $_POST['length'])){
                    break;
                }
            }

            if(date("Y-m-d") <= $test['next_date']){
                $next_date_background_class = "green-background";
            }else{
                $next_date_background_class = "red-background";
            }

            $test_date = format_date($test['test_date']);
            $last_date = format_date($test['last_date']);
            $next_date = format_date($test['next_date']);

            if(isset($_SESSION["delete_audits"]) && $_SESSION["delete_audits"] == 1) 
                $delete_button = "<button class='btn delete-btn' data-id='{$test['id']}' >".$escaper->escapeHtml($lang['Delete'])."</button>";
            else $delete_button = "";

            $tags_view = "";
            if ($test['tags']) {
                foreach(str_getcsv($test['tags']) as $tag) {
                    $tags_view .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin-right:2px; margin-bottom:2px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
                }
            } else {
                $tags_view .= "";
            }

            $data[] = [
                "<div><a href='".$_SESSION['base_url']."/compliance/testing.php?id=".$test['id']."' class='text-left'>".$escaper->escapeHtml($test['name'])."</a><input type='hidden' class='background-class' data-background='{$next_date_background_class}'></div>",
                "<div>".(int)$test['test_frequency']. " " .$escaper->escapeHtml($test['test_frequency'] > 1 ? $lang['days'] : $lang['Day'])."</div>",
                "<div>".$escaper->escapeHtml($test['tester_name'])."</div>",
                "<div>".$escaper->escapeHtml(get_stakeholder_names($test['additional_stakeholders'], 2))."</div>",                
                "<div>".$escaper->escapeHtml($test['objective'])."</div>",
                "<div>".$escaper->escapeHtml($test['control_name'])."</div>",
                "<div>".$escaper->escapeHtml($test['framework_name'])."</div>",
                "<div>".$tags_view."</div>",
                "<div>".$escaper->escapeHtml($test['audit_status_name'])."</div>",
                "<div>".$escaper->escapeHtml($test_date)."</div>",
                "<div>".$escaper->escapeHtml($last_date)."</div>",
                "<div class='text-center '>".$escaper->escapeHtml($next_date)."</div>",
                "<div class='text-center'>".$delete_button."</div>"
            ];
        }
        $result = array(
            'draw' => $draw,
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
    }
}

/********************************
 * FUNCTION: SAVE AUDIT COMMENT *
 ********************************/
function saveTestAuditCommentResponse()
{
    global $lang, $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance"))
    {
        $test_audit_id =  (int)$_POST['id'];

        // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
            if (!is_user_allowed_to_access($_SESSION['uid'], $test_audit_id, 'audit')) {
                set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForThisAudit']));
                json_response(400, get_alert(true), null);
                return;
            }
        }

        $comment =  $_POST['comment'];

        // Save comment
        save_test_comment($test_audit_id, $comment);

        $commentList = get_testing_comment_list($test_audit_id);

        $test_audit = get_framework_control_test_audit_by_id($test_audit_id);

        $message = "Comment was added to audit test \"" . $escaper->escapeHtml($test_audit['name']) . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log((int)$test_audit_id + 1000, $_SESSION['uid'], $message, "test_audit");

        json_response(200, get_alert(true), $commentList);

    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
    }
}

/*******************************
 * FUNCTION: DELETE TEST AUDIT *
 *******************************/
function deleteTestAuditResponse()
{
    global $lang, $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance") && isset($_SESSION["delete_audits"]) && $_SESSION["delete_audits"] == 1)
    {
        $audit_id = (int)$_POST['id'];

        // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
            if (!is_user_allowed_to_access($_SESSION['uid'], $audit_id, 'audit')) {
                set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForThisAudit']));
                json_response(400, get_alert(true), null);
                return;
            }
        }

        delete_test_audit($audit_id);

        set_alert(true, "good", $escaper->escapeHtml($lang['TestAuditWasDeletedSuccessfully']));
        json_response(200, get_alert(true), null);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
    }
}

/*******************************
 * FUNCTION: REOPEN TEST AUDIT *
 *******************************/
function reopenTestAuditResponse()
{
    global $lang, $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance") && isset($_SESSION["reopen_audits"]) && $_SESSION["reopen_audits"] == 1)
    {
        $audit_id = $_POST['id'];

        // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
            if (!is_user_allowed_to_access($_SESSION['uid'], $audit_id, 'audit')) {
                set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForThisAudit']));
                json_response(400, get_alert(true), null);
                return;
            }
        }

        reopen_test_audit($audit_id);

        $result = array(
            'status' => true
        );

        json_response(200, "Reopen Test Audit", $result);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
    }
}

/********************************************
 * FUNCTION: CUSTOMIZATION ADD CUSTOM FIELD *
 ********************************************/
function customization_addCustomField()
{
    // Check customization extra is enabled
    if (customization_extra())
    {
        // If the customization extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/customization/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            // Call the addCustomField function
            addCustomField();
        }
    }
}

/***********************************************
 * FUNCTION: CUSTOMIZATION DELETE CUSTOM FIELD *
 ***********************************************/
function customization_deleteCustomField()
{
    // Check customization extra is enabled
    if (customization_extra())
    {
        // If the customization extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/customization/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            // Call the deleteCustomField function
            deleteCustomField();
        }
    }
}

/********************************************
 * FUNCTION: CUSTOMIZATION GET CUSTOM FIELD *
 ********************************************/
function customization_getCustomField()
{
    // Check customization extra is enabled
    if (customization_extra())
    {
        // If the customization extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/customization/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            // Call the getCustomField function
            getCustomField();
        }
    }
}

/********************************************
 * FUNCTION: GET RESPONSIBILITES BY ROLE ID *
 ********************************************/
function getResponsibilitiesByRoleIdForm(){
    global $lang, $escaper;
    if($_SESSION['admin'] == 1)
    {
        $role_id = (int)$_GET['role_id'];
        
        // Get responsibilities by role ID
        $responsibilities = get_role($role_id);

        json_response(200, "Success", $responsibilities);
    }
    else
    {
        $status = "bad";
        $message = $escaper->escapeHtml($lang["AdminPermissionRequired"]);
        $status_code = 400;
        set_alert(true, $status, $message);
        json_response($status_code, $message);
    }
}

/******************************
 * FUNCTION: ADD IMPACT VALUE *
 ******************************/
function add_impact_api(){
    global $lang, $escaper;

    if($_SESSION['admin'] == 1)
    {
        // Create a new impact
        if(add_impact()){
            $status = "good";
            $message = $escaper->escapeHtml($lang["SuccessAddingImpact"]);
            $status_code = 200;
        }else{
            $status = "bad";
            $message = $escaper->escapeHtml($lang["FailAddingImpact"]);
            $status_code = 400;
        }
    }
    else
    {
        $status = "bad";
        $message = $escaper->escapeHtml($lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    set_alert(true, $status, $message);
    // Return a JSON response
    json_response($status_code, $message);
}

/**********************************
 * FUNCTION: ADD LIKELIHOOD VALUE *
 **********************************/
function add_likelihood_api(){
    global $lang, $escaper;

    if($_SESSION['admin'] == 1)
    {
        // Create a new likelihood
        if(add_likelihood()){
            $status = "good";
            $message = $escaper->escapeHtml($lang["SuccessAddingLikelihood"]);
            $status_code = 200;
        }else{
            $status = "bad";
            $message = $escaper->escapeHtml($lang["FailAddingLikelihood"]);
            $status_code = 400;
        }
    }
    else
    {
        $status = "bad";
        $message = $escaper->escapeHtml($lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    set_alert(true, $status, $message);
    // Return a JSON response
    json_response($status_code, $message);
}

/*********************************
 * FUNCTION: DELETE IMPACT VALUE *
 *********************************/
function delete_impact_api(){
    global $lang, $escaper;

    if($_SESSION['admin'] == 1)
    {
        // Delete highest impact
        if(delete_impact()){
            $status = "good";
            $message = $escaper->escapeHtml($lang["SuccessDeletingImpact"]);
            $status_code = 200;
        }else{
            $status = "bad";
            $message = $escaper->escapeHtml($lang["FailDeletingImpact"]);
            $status_code = 400;
        }
    }
    else
    {
        $status = "bad";
        $message = $escaper->escapeHtml($lang["AdminPermissionRequired"]);
        $status_code = 200;
    }

    set_alert(true, $status, $message);
    // Return a JSON response
    json_response($status_code, $message);
}

/*************************************
 * FUNCTION: DELETE LIKELIHOOD VALUE *
 *************************************/
function delete_likelihood_api(){
    global $lang, $escaper;

    if($_SESSION['admin'] == 1)
    {
        // Delete highest likelihood
        if(delete_likelihood()){
            $status = "good";
            $message = $escaper->escapeHtml($lang["SuccessDeletingLikelihood"]);
            $status_code = 200;
        }else{
            $status = "bad";
            $message = $escaper->escapeHtml($lang["FailDeletingLikelihood"]);
            $status_code = 400;
        }
    }
    else
    {
        $status = "bad";
        $message = $escaper->escapeHtml($lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    set_alert(true, $status, $message);
    // Return a JSON response
    json_response($status_code, $message);
}

/**********************************************
 * FUNCTION: UPDATE IMPACT OR LIKELIHOOD NAME *
 **********************************************/
function update_impact_or_likelihood_name_api(){
    global $lang, $escaper;

    $value = (int)get_param("POST", "value");
    $name = get_param("POST", "name");
    $type = get_param("POST", "type");
    
    if (!in_array($type, ['impact', 'likelihood'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyATypeParameter']);
        $status_code = 400;
    } elseif(strlen($name) > 50) {
        set_alert(true, "bad", _lang(ucfirst($type) . "HasMaxChars", ['length' => 50]));
        $status_code = 400;
    } elseif($_SESSION['admin'] == 1) {
        if(update_table($type, $name, $value, 50)) {
            set_alert(true, "good", $lang["SuccessUpdating" . ucfirst($type) . "Name"]);
            json_response(200, get_alert(true), ['confirmed_data' => $escaper->escapeHtml($name)]);
            return;
        } else {
            set_alert(true, "bad", $lang["FailUpdating" . ucfirst($type) . "Name"]);
            $status_code = 400;
        }
    } else {
        set_alert(true, "bad", $lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    // Return a JSON response
    json_response($status_code, get_alert(true), null);
}

/*********************************
 * FUNCTION: UPDATE CUSTOM SCORE *
 *********************************/
function update_custom_score_api(){
    global $lang, $escaper;

    $impact = (int)get_param("POST", "impact");
    $likelihood = (int)get_param("POST", "likelihood");
    $score = (float)get_param("POST", "score");

    if($_SESSION['admin'] == 1) {
        if (is_valid_impact_and_likelihood($impact, $likelihood)) {
            if (0 <= $score && $score <= 10.0) {
                set_stored_risk_score($impact, $likelihood, $score, true);
                set_alert(true, "good", $lang["SuccessUpdatingCustomScore"]);
                $confirmed_score = round(get_stored_risk_score($impact, $likelihood), 1);
                $color = get_risk_color($confirmed_score);
                json_response(200, get_alert(true), [
                    'confirmed_data' => $escaper->escapeHtml($confirmed_score),
                    'color' => $escaper->escapeHtml($color)
                ]);
            } else {
                set_alert(true, "bad", $lang["RiskScoreIsOutOfRange"]);
                json_response(400, get_alert(true), null);
            }
        } else {
            set_alert(true, "bad", $lang["InvalidImpactOrLikelihood"]);
            json_response(400, get_alert(true), null);
        }
    } else {
        set_alert(true, "bad", $lang["AdminPermissionRequired"]);
        json_response(400, get_alert(true), null);
    }
}


/**********************************************
 * FUNCTION: GET DATA FOR DOCUMENTS DATATABLE *
 **********************************************/
function getDocumentsResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $type = $_GET['type'];
        $result = get_documents_as_treegrid($type);
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/**************************************
 * FUNCTION: GET DOCUMENT BY ID PARAM *
 **************************************/
function getDocumentResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $document = get_document_by_id($id);

        $document['creation_date'] = format_date($document['creation_date']);
        $document['last_review_date'] = format_date($document['last_review_date']);
        $document['approval_date'] = format_date($document['approval_date']);
        $document['next_review_date'] = format_date($document['next_review_date']);
        $document['control_ids'] = explode(',', $document['control_ids']);
        $document['framework_ids'] = explode(',', $document['framework_ids']);
        $document['team_ids'] = explode(',', $document['team_ids']);
        $document['additional_stakeholders'] = explode(',', $document['additional_stakeholders']);

        json_response(200, "Success", $document);
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/******************************************************
 * FUNCTION: GET DATA FOR TABULAR DOCUMENTS DATATABLE *
 ******************************************************/
function getTabularDocumentsResponse()
{
    global $escaper, $lang;
    
    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $type = $_GET['type'];
        $document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        // If this is request to view all versions of selected document.
        if($document_id)
        {
            // Get current document
            $current_document = get_document_by_id($document_id);
            $version = $current_document['file_version'];

            // Get documents with versions
            $documents = get_document_versions_by_id($document_id);
            
            foreach($documents as &$document){
                $document['id'] = $document['id']."_".$document['file_version'];
                $document['state'] = "open";
                $document['document_type'] = $escaper->escapeHtml($document['document_type']);
                $document['document_name'] = "<a href=\"".$_SESSION['base_url']."/governance/download.php?id=".$document['unique_name']."\" >".$escaper->escapeHtml($document['document_name']). " (".$document['file_version'].")" ."</a>";
                $document['status'] = $escaper->escapeHtml(get_name_by_value('document_status', $document['status']));
                $document['creation_date'] = format_date($document['creation_date']);
                $document['approval_date'] = format_date($document['approval_date']);
                $document['actions'] = "<div class=\"text-center\">&nbsp;&nbsp;&nbsp;";
                if(!empty($_SESSION['delete_documentation']) && $version != $document['file_version'])
                {
                    $document['actions'] .= "<a class=\"document--delete\" data-version=\"".$document['file_version']."\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-trash\"></i></a>&nbsp;&nbsp;&nbsp;";
                }
                $document['actions'] .= "</div>";
            }
        }
        // If this is request to view document list.
        else
        {
            $filterRules = isset($_GET["filterRules"])?json_decode($_GET["filterRules"],true):array();
            $filtered_documents = array();
            $documents = get_documents($type);
            foreach($documents as &$document){
                $frameworks = get_frameworks_by_ids($document["framework_ids"]);
                $framework_names = implode(", ", array_map(function($framework){
                    return $framework['name'];
                }, $frameworks));

                $control_ids = explode(",", $document["control_ids"]);
                $controls = get_framework_controls_by_filter("all", "all", "all", "all", "all", "all", "all", "all", "", $control_ids);
                $control_names = implode(", ", array_map(function($control){
                    return $control['short_name'];
                }, $controls));

                // document filtering
                if(count($filterRules)>0) {
                    foreach($filterRules as $filter){
                        $value = $filter['value'];
                        switch($filter['field']){
                            case "document_name":
                                if( stripos($document['document_name'], $value) === false ){
                                    continue 3;
                                }
                                break;
                            case "document_type":
                                if( stripos($document['document_type'], $value) === false ){
                                    continue 3;
                                }
                                break;
                            case "framework_names":
                                if( stripos($framework_names, $value) === false ){
                                    continue 3;
                                }
                                break;
                            case "control_names":
                                if( stripos($control_names, $value) === false ){
                                    continue 3;
                                }
                                break;
                            case "creation_date":
                                if( stripos(format_date($document['creation_date']), $value) === false ){
                                    continue 3;
                                }
                                break;
                            case "approval_date":
                                if( stripos(format_date($document['approval_date']), $value) === false ){
                                    continue 3;
                                }
                                break;
                            case "status":
                                if( stripos($document['status'], $value) === false ){
                                    continue 3;
                                }
                                break;
                        }
                    }
                }

                $document['state'] = "closed";
                $document['document_type'] = $escaper->escapeHtml($document['document_type']);
                $document['document_name'] = "<a href=\"".$_SESSION['base_url']."/governance/download.php?id=".$document['unique_name']."\" >".$escaper->escapeHtml($document['document_name'])."</a>";
                $document['status'] = $escaper->escapeHtml(get_name_by_value('document_status', $document['status']));
                $document['framework_names'] = $escaper->escapeHtml($framework_names);
                $document['control_names'] = $escaper->escapeHtml($control_names);
                $document['creation_date'] = format_date($document['creation_date']);
                $document['approval_date'] = format_date($document['approval_date']);
                $document['actions'] = "<div class=\"text-center\">&nbsp;&nbsp;&nbsp;";
                if(!empty($_SESSION['modify_documentation']))
                {
                    $document['actions'] .= "<a class=\"document--edit\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-edit\"></i></a>&nbsp;&nbsp;&nbsp;";
                }
                if(!empty($_SESSION['delete_documentation']))
                {
                    $document['actions'] .= "<a class=\"document--delete\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-trash\"></i></a>&nbsp;&nbsp;&nbsp;";
                }
                $document['actions'] .= "</div>";
                $filtered_documents[] = $document;
            }
            $documents = $filtered_documents;
        }
        
        echo json_encode($documents);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/*******************************************************
 * FUNCTION: GET DATA FOR MITIGATION CONTROL DATATABLE *
 *******************************************************/
 
function get_mitigation_control_info(){
    global $lang;
    global $escaper;
    
    $control_id = $_GET['control_id'];
    $height     = $_GET['scroll_top'];
    
    $some_control = get_framework_controls( $control_id );
    $mapped_frameworks = get_mapping_control_frameworks($control_id);
    if(count($mapped_frameworks) > 0){
        $mapping_framework_table = "
            <table width='100%' class='table table-bordered'>
                <tr>
                    <th width='60%'>".$escaper->escapeHtml($lang['Framework'])."</th>
                    <th width='40%'>".$escaper->escapeHtml($lang['Control'])."</th>
                </tr>";
                foreach ($mapped_frameworks as $framework){
                    $mapping_framework_table .= "<tr>
                        <td>".$escaper->escapeHtml($framework['framework_name'])."</td>
                        <td>".$escaper->escapeHtml($framework['reference_name'])."</td>
                    </tr>";
                }
        $mapping_framework_table .= "</table>";
    } else $mapping_framework_table = "";

    $control_long_name  = $some_control[0]['long_name'];
    $control_short_name = $some_control[0]['short_name'];
    $control_owner      = $some_control[0]['control_owner_name'];
    $control_framework  = $some_control[0]['framework_names'];
    $control_class      = $some_control[0]['control_class_name'];
    $control_phase      = $some_control[0]['control_phase_name'];
    $control_number     = $some_control[0]['control_number'];
    $control_priority   = $some_control[0]['control_priority_name'];
    $control_family     = $some_control[0]['family_short_name'];
    $mitigation_percent = $some_control[0]['mitigation_percent'];
    $description        = $some_control[0]['description'];
    $supplemental_guidance = $some_control[0]['supplemental_guidance'];
    
    $control_info = '<table width="100%" class="table table-bordered">
        <tbody>
            <tr>
                <td width="13%" align="right"><strong>' . $escaper->escapeHtml($lang['ControlShortName']) . '</strong>: </td>
                <td width="57%" colspan="3">'. $escaper->escapeHtml($control_short_name) .'</td>
                <td width="13%" align="right"><strong>' . $escaper->escapeHtml($lang['ControlOwner']) . '</strong>: </td>
                <td width="17%">'. $escaper->escapeHtml($control_owner) .'</td>
            </tr>
            <tr>
                <td align="right"><strong>' . $escaper->escapeHtml($lang['ControlClass']) . '</strong>: </td>
                <td width="22%">'. $escaper->escapeHtml($control_class) .'</td>
                <td width="13%" align="right"><strong>' . $escaper->escapeHtml($lang['ControlPhase']) . '</strong>: </td>
                <td width="22%">'. $escaper->escapeHtml($control_phase) .'</td>
                <td align="right"><strong>' . $escaper->escapeHtml($lang['ControlNumber']) . '</strong>: </td>
                <td>' . $escaper->escapeHtml( $control_number ) . '</td>
            </tr>
            <tr>
                <td align="right"><strong>' . $escaper->escapeHtml($lang['ControlPriority']) . '</strong>: </td>
                <td>' . $escaper->escapeHtml( $control_priority ) . '</td>
                <td width="200px" align="right"><strong>' . $escaper->escapeHtml($lang['ControlFamily']) . '</strong>: </td>
                <td>' . $escaper->escapeHtml( $control_family ) . '</td>
                <td width="200px" align="right"><strong>' . $escaper->escapeHtml($lang['MitigationPercent']) . '</strong>: </td>
                <td>' . $escaper->escapeHtml( $mitigation_percent ) . '%</td>
            </tr>
            <tr>
            <td align="right"><strong>' . $escaper->escapeHtml($lang['Description']) . '</strong>: </td>
            <td colspan="5">' . nl2br($escaper->escapeHtml( $description )) . '</td>
            </tr>
            <tr>
            <td align="right"><strong>' . $escaper->escapeHtml($lang['SupplementalGuidance']) . '</strong>: </td>
            <td colspan="5">' . nl2br($escaper->escapeHtml( $supplemental_guidance )) . '</td>
            </tr>
            <tr>
            <td align="right"><strong>' . $escaper->escapeHtml($lang['MappedControlFrameworks']) . '</strong>: </td>
            <td colspan="5">' .$mapping_framework_table . '</td>
            </tr>
        </tbody>
    </table>';

    $data['control_info'] = $control_info;
    $data['scroll_top']   = $height;
    
    json_response(200, "Success", $data);
    exit;
}

/*************************************
 * GET TOOLTIP INFO OF THE HIGHCHART *
 *************************************/
function get_tooltip_api()
{
    global $lang;
    global $escaper;
    
    // Get risk ids by comma
    $risk_ids = $_POST['risk_ids'];
    
    // Get risk ids in array
    $risk_ids = explode(",", $risk_ids);

    $tooltip_html ="";

    foreach($risk_ids as $risk_id){
        $risk = get_risk_by_id($risk_id);
        // If risk by risk ID no exist, go to next risk ID
        if(empty($risk[0])){
            continue;
        }
        $risk = $risk[0];

        $calculated_risk = $risk['calculated_risk'];
        $color = get_risk_color($calculated_risk);
        
        $tooltip_html .=  '<a href="'. $_SESSION['base_url'].'/management/view.php?id=' . $escaper->escapeHtml(convert_id($risk['id'])) . '" style="" ><b>' . $escaper->escapeHtml(try_decrypt($risk['subject'])) . '</b></a><hr>';
    }

    json_response(200, "result", $tooltip_html);
    exit();
}

/*************************************************************
 * FUNCTION: RETURN JSON DATA FOR PLAN MITIGATIONS DATATABLE *
 *************************************************************/
function getPlanMitigationsDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has risk management permissions
    if (check_permission("riskmanagement"))
    {
        $user = get_user_by_id($_SESSION['uid']);
        $settings = json_decode($user["custom_plan_mitigation_display_settings"], true);
        $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
        $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
        $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
        $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
        $columns = [];
        foreach($columns_setting as $column){
            if(stripos($column[0], "custom_field_") !== false){
                if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
            } else if($column[1] == 1) $columns[] = $column[0];
        }
        if(!count($columns)){
            $columns = array("id","risk_status","subject","calculated_risk","submission_date","mitigation_planned","management_review");
        }

        $draw = $escaper->escapeHtml($_POST['draw']);

        $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
        $length = $_POST['length'] ? (int)$_POST['length'] : 10;
        $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
        $orderColumnName = isset($_POST['columns'][$orderColumn]['name']) ? $_POST['columns'][$orderColumn]['name'] : null;;
        $orderDir = !empty($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
            }
        }

        // Get risks requiring mitigations
        $risks = get_risks(1, $orderColumnName, $orderDir);

        $encryption_columns = array("regulation", "project", "risk_assessment", "additional_notes", "current_solution", "security_recommendations", "security_requirements", "comments");

        if(encryption_extra()&&in_array($orderColumnName, $encryption_columns)){
            $decrypted_risks = array();
            foreach($risks as $risk)
            {
                $risk['encryption_order'] = try_decrypt($risk[$orderColumnName]);
                $decrypted_risks[] = $risk;
            }
            $risks = $decrypted_risks;
            usort($risks, function($a, $b) use ($orderDir) {
                if($orderDir == "asc") 
                    return strcasecmp($a['encryption_order'], $b['encryption_order']);
                else 
                    return strcasecmp($b['encryption_order'], $a['encryption_order']);
            });
        }

        $risk_levels = get_risk_levels();
        $review_levels = get_review_levels();

        // If we're ordering by the 'management_review' column
        if ($orderColumnName === 'management_review') {
            // Calculate the 'management_review' values
            foreach($risks as &$risk) {
                $risk_level = get_risk_level_name($risk['calculated_risk']);
                $residual_risk_level = get_risk_level_name($risk['residual_risk']);

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }

                $risk['management_review'] = management_review($risk['id'], $risk['mgmt_review'], $next_review);
                $risk['management_review_text'] = management_review_text_only($risk['mgmt_review'], $next_review);
            }
            unset($risk);

            // Sorting by the management review text as the normal 'management_review' field contains html
            usort($risks, function($a, $b) use ($orderDir){
                // For identical management reviews we're sorting on the id, so the results' order is not changing
                if ($a['management_review_text'] === $b['management_review_text']) {
                    return (int)$a['id'] - (int)$b['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a['management_review_text'], $b['management_review_text']);
                } else {
                    return strcmp($b['management_review_text'], $a['management_review_text']);
                }
            });
        }

        // If we're ordering by the 'Next Review Date' column
        if ($orderColumnName === 'next_review_date') {
            // Calculate the 'management_review' values
            foreach($risks as &$risk) {
                $risk_level = get_risk_level_name($risk['calculated_risk']);
                $residual_risk_level = get_risk_level_name($risk['residual_risk']);

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }

                $risk['next_review_text'] = $next_review;
            }

            // Sorting by the management review text as the normal 'management_review' field contains html
            usort($risks, function($a, $b) use ($orderDir){
                // For identical management reviews we're sorting on the id, so the results' order is not changing
                if ($a['next_review_text'] === $b['next_review_text']) {
                    return (int)$a['id'] - (int)$b['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a['next_review_text'], $b['next_review_text']);
                } else {
                    return strcmp($b['next_review_text'], $a['next_review_text']);
                }
            });
        }

        $review_levels = get_review_levels();
        
        $risks_data = [];
        foreach ($risks as $key=>$risk)
        {
            $color = get_risk_color($risk['calculated_risk']);

            $risk_level = get_risk_level_name($risk['calculated_risk']);
            $residual_risk_level = get_risk_level_name($risk['residual_risk']);

            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk")
            {
                $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels, false);
            }
            // If next_review_date_uses setting is Inherent Risk.
            else
            {
                $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels, false);
            }
            $submission_date = date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']));
            $mitigation_planned = planned_mitigation(convert_id($risk['id']), $risk['mitigation_id'], "PlanYourMitigations");
            $management_review = management_review(convert_id($risk['id']), $risk['mgmt_review'], $next_review, true, "PlanYourMitigations");
            $data_row = [];
            // Storing the data in a different format for filtering
            // no html - so filtering on 'div' won't return items with <div> in it
            // unencrypted - so don't have to unencrypt again for filtering
            // unescaped - so you can find the correct items searching for '&'
            $filter_data = [];
            foreach($columns as $column){
                switch ($column) {
                    default :
                        if(($pos = stripos($column, "custom_field_")) !== false){
                            if(customization_extra()){
                                $field_id = str_replace("custom_field_", "", $column);
                                $custom_values = getCustomFieldValuesByRiskId(convert_id($risk['id']));
                                $text = "";
                                // Get value of custom filed
                                foreach($custom_values as $custom_value)
                                {
                                    // Check if this custom value is for the active field
                                    if($custom_value['field_id'] == $field_id){
                                        $text = get_custom_field_name_by_value($field_id, $custom_value['field_type'], $custom_value['encryption'], $custom_value['value']);
                                        break;
                                    }
                                }
                                $data_row[] = $text;
                                $risk[$column] = strip_tags($text);
                                $filter_data[$column] = $risk[$column];
                            }
                        } else {
                            $data_row[] = $escaper->escapeHtml($risk[$column]);
                            $filter_data[$column] = $risk[$column];
                        }
                        break;
                    case "id":
                        $id = convert_id($risk['id']);
                        $data_row[] = "<div data-id='{$id}' class='open-risk'><a href=\"../management/view.php?id={$id}&active=PlanYourMitigations\">{$id}</a></div>";
                        $filter_data[$column] = $id;
                        break;
                    case "risk_status":
                        $data_row[] = $escaper->escapeHtml($risk['status']);
                        $filter_data[$column] = $risk['status'];
                        break;
                    case "calculated_risk":
                        $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder' style='position:relative;'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>";
                        $filter_data[$column] = $risk['calculated_risk'];
                        break;
                    case "submission_date":
                        $data_row[] = $escaper->escapeHtml($submission_date);
                        $filter_data[$column] = $submission_date;
                        break;
                    case "mitigation_planned":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-mitigation mitigation active-cell\" >".$mitigation_planned."</div>";
                        $filter_data[$column] = $mitigation_planned;
                        break;
                    case "management_review":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-review management active-cell\">".$management_review."</div>";
                        $filter_data[$column] = $management_review;
                        break;
                    case "closure_date":
                        $filter_data[$column] = format_datetime($risk['closure_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "regulation":
                        $filter_data[$column] = try_decrypt($risk["regulation"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "scoring_method":
                        $filter_data[$column] = get_scoring_method_name($risk["scoring_method"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "project":
                        $filter_data[$column] = try_decrypt($risk["project"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "risk_assessment":
                        $filter_data[$column] = try_decrypt($risk["risk_assessment"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "additional_notes":
                        $filter_data[$column] = try_decrypt($risk["additional_notes"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "affected_assets":
                        // Do a lookup for the list of affected assets
                        $affected_assets = '';
                        $assets_array = [];

                        // If the affected assets or affected asset groups is not empty
                        if ($risk['affected_assets']) {
                            foreach (explode(', ', $risk['affected_assets']) as $asset) {
                                $asset = try_decrypt($asset);
                                $affected_assets .= "<span class='asset'>" . $escaper->escapeHtml($asset) . "</span>";
                                $assets_array []= $asset;
                            }
                        }

                        if ($risk['affected_asset_groups']) {
                            foreach (explode(', ', $risk['affected_asset_groups']) as $group) {
                                $affected_assets .= "<span class='group'>" . $escaper->escapeHtml($group) . "</span>";
                                $assets_array []= $group;
                            }
                        }

                        $data_row[] = $affected_assets ? "<div class='affected-asset-cell'>{$affected_assets}</div>" : '';
                        $filter_data[$column] = !empty($assets_array) ? implode(' ', $assets_array) : '';
                        break;
                    case "mitigation_cost":
                        $mitigation_min_cost = $risk['mitigation_min_cost'];
                        $mitigation_max_cost = $risk['mitigation_max_cost'];
                        // If the mitigation costs are empty
                        if (empty($mitigation_min_cost) && empty($mitigation_max_cost))
                        {
                                // Return no value
                                $mitigation_cost = "";
                        }
                        else 
                        {
                            $mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;
                            if (!empty($risk['valuation_level_name']))
                                $mitigation_cost .= " ({$risk['valuation_level_name']})";
                        }
                        $data_row[] = $escaper->escapeHtml($mitigation_cost);
                        $filter_data[$column] = $mitigation_cost;
                        break;
                    case "mitigation_accepted":
                        $mitigation_accepted = $risk['mitigation_accepted'] ? $lang['Yes'] : $lang['No'];
                        $data_row[] = $escaper->escapeHtml($mitigation_accepted);
                        $filter_data[$column] = $mitigation_accepted;
                        break;
                    case "mitigation_date":
                        $filter_data[$column] = format_datetime($risk['mitigation_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "current_solution":
                        $filter_data[$column] = try_decrypt($risk["current_solution"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "security_recommendations":
                        $filter_data[$column] = try_decrypt($risk["security_recommendations"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "security_requirements":
                        $filter_data[$column] = try_decrypt($risk["security_requirements"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "review_date":
                        $filter_data[$column] = format_datetime($risk['review_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "planning_date":
                        $filter_data[$column] = format_datetime($risk['planning_date'], "", "");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "next_review_date":
                        $data_row[] = $escaper->escapeHtml($next_review);
                        $filter_data[$column] = $next_review;
                        break;
                    case "comments":
                        $filter_data[$column] = try_decrypt($risk["comments"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "risk_tags":
                        $tags = "";
                        $filter_data[$column] = '';
                        if ($risk['risk_tags']) {
                            $filter_data[$column] = str_getcsv($risk['risk_tags']);
                            foreach($filter_data[$column] as $tag) {
                                $tags .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin: 1px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
                            }
                        }
                        $data_row[] = $tags;
                        break;
                    case "risk_mapping":
                        if (!empty($risk['risk_catalog_mapping'])) {
                            $filter_data[$column] = get_names_by_multi_values("risk_catalog", $risk['risk_catalog_mapping'], false, ", ", true);
                            $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        } else {
                            $data_row[] = '';
                            $filter_data[$column] = '';
                        }
                        break;
                    case "threat_mapping":
                        if (!empty($risk['threat_catalog_mapping'])) {
                            $filter_data[$column] = get_names_by_multi_values("threat_catalog", $risk['threat_catalog_mapping'], false, ", ", true);
                            $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        } else {
                            $data_row[] = '';
                            $filter_data[$column] = '';
                        }
                        break;
                }
            }
            $risk["data_row"] = $data_row;
            $risk["filter_data"] = $filter_data;
            $risks_data[] = $risk;
        }

        if(($pos = stripos($orderColumnName, "custom_field_")) !== false){
            // Sorting by the custom field review text as the normal 'management_review' field contains html
            usort($risks_data, function($a, $b) use ($orderDir, $orderColumnName){
                // For identical custom fields we're sorting on the id, so the results' order is not changing
                if ($a[$orderColumnName] === $b[$orderColumnName]) {
                    return (int)$a['id'] - (int)$b['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a[$orderColumnName], $b[$orderColumnName]);
                } else {
                    return strcmp($b[$orderColumnName], $a[$orderColumnName]);
                }
            });
        }

        $data = array();
        foreach ($risks_data as $key=>$risk)
        {
            $filter_data = $risk["filter_data"];
            // column filter 
            $success = true;
            foreach($column_filters as $column_name => $val){
                switch ($column_name) {
                    default :
                        if(stripos($filter_data[$column_name], $val) === false){
                            $success = false;
                        }
                        break;
                    case "risk_tags":
                        if ($filter_data['risk_tags']) {
                            $tag_match = false;
                            foreach ($filter_data['risk_tags'] as $tag) {
                                $tag_match |= stripos($tag, $val) !== false;
                                if ($tag_match) {
                                    break;
                                }
                            }
                            if (!$tag_match) {
                                $success = false;
                            }
                        } else {
                            $success = false;
                        }
                        break;
                }
            }
            if($success == true) $data[] = $risk["data_row"];
        }
        $risks_by_page = [];

        if($length == -1)
        {
            $risks_by_page = $data;
        }
        else
        {
            for($i=$start; $i<count($data) && $i<$start + $length; $i++){
                $risks_by_page[] = $data[$i];
            }
        }
        $recordsTotal = count($data);
        $result = array(
            'draw' => $draw,
            'data' => $risks_by_page,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
    }
}

/**************************************************************
 * FUNCTION: RETURN JSON DATA FOR MANAGEMENT REVIEW DATATABLE *
 **************************************************************/
function getManagementReviewsDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has risk management permissions
    if (check_permission("riskmanagement"))
    {
        $user = get_user_by_id($_SESSION['uid']);
        $settings = json_decode($user["custom_perform_reviews_display_settings"], true);
        $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
        $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
        $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
        $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
        $columns = [];
        foreach($columns_setting as $column){
            if(stripos($column[0], "custom_field_") !== false){
                if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
            } else if($column[1] == 1) $columns[] = $column[0];
        }
        if(!count($columns)){
            $columns = array("id","risk_status","subject","calculated_risk","submission_date","mitigation_planned","management_review");
        }

        $draw = $escaper->escapeHtml($_POST['draw']);

        $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
        $length = $_POST['length'] ? (int)$_POST['length'] : 10;
        $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
        $orderColumnName = isset($_POST['columns'][$orderColumn]['name']) ? $_POST['columns'][$orderColumn]['name'] : null;
        $orderDir = !empty($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
            }
        }

        // Get risks requiring mitigations
        $risks = get_risks(2, $orderColumnName, $orderDir);

        $encryption_columns = array("regulation", "project", "risk_assessment", "additional_notes", "current_solution", "security_recommendations", "security_requirements", "comments");

        if(encryption_extra()&&in_array($orderColumnName, $encryption_columns)){
            $decrypted_risks = array();
            foreach($risks as $risk)
            {
                $risk['encryption_order'] = try_decrypt($risk[$orderColumnName]);
                $decrypted_risks[] = $risk;
            }
            $risks = $decrypted_risks;
            usort($risks, function($a, $b) use ($orderDir) {
                if($orderDir == "asc") 
                    return strcasecmp($a['encryption_order'], $b['encryption_order']);
                else 
                    return strcasecmp($b['encryption_order'], $a['encryption_order']);
            });
        }

        $risk_levels = get_risk_levels();
        $review_levels = get_review_levels();

        // If we're ordering by the 'management_review' column
        if ($orderColumnName === 'management_review') {
            // Calculate the 'management_review' values
            foreach($risks as &$risk) {
                $risk_level = get_risk_level_name($risk['calculated_risk']);
                $residual_risk_level = get_risk_level_name($risk['residual_risk']);

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }

                $risk['management_review'] = management_review($risk['id'], $risk['mgmt_review'], $next_review);
                $risk['management_review_text'] = management_review_text_only($risk['mgmt_review'], $next_review);
            }
            unset($risk);

            // Sorting by the management review text as the normal 'management_review' field contains html
            usort($risks, function($a, $b) use ($orderDir){
                // For identical management reviews we're sorting on the id, so the results' order is not changing
                if ($a['management_review_text'] === $b['management_review_text']) {
                    return (int)$a['id'] - (int)$b['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a['management_review_text'], $b['management_review_text']);
                } else {
                    return strcmp($b['management_review_text'], $a['management_review_text']);
                }
            });
        }

        // If we're ordering by the 'Next Review Date' column
        if ($orderColumnName === 'next_review_date') {
            // Calculate the 'management_review' values
            foreach($risks as &$risk) {
                $risk_level = get_risk_level_name($risk['calculated_risk']);
                $residual_risk_level = get_risk_level_name($risk['residual_risk']);

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }

                $risk['next_review_text'] = $next_review;
            }

            // Sorting by the management review text as the normal 'management_review' field contains html
            usort($risks, function($a, $b) use ($orderDir){
                // For identical management reviews we're sorting on the id, so the results' order is not changing
                if ($a['next_review_text'] === $b['next_review_text']) {
                    return (int)$a['id'] - (int)$b['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a['next_review_text'], $b['next_review_text']);
                } else {
                    return strcmp($b['next_review_text'], $a['next_review_text']);
                }
            });
        }

       
        $review_levels = get_review_levels();

        $risks_data = [];
        foreach ($risks as $key=>$risk)
        {
            $color = get_risk_color($risk['calculated_risk']);

            $risk_level = get_risk_level_name($risk['calculated_risk']);
            $residual_risk_level = get_risk_level_name($risk['residual_risk']);

            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk")
            {
                $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
            }
            // If next_review_date_uses setting is Inherent Risk.
            else
            {
                $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
            }
            $submission_date = date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']));
            $mitigation_planned = planned_mitigation(convert_id($risk['id']), $risk['mitigation_id'], "PerformManagementReviews");
            $management_review = management_review(convert_id($risk['id']), $risk['mgmt_review'], $next_review, true, "PerformManagementReviews");
            $data_row = [];
            // Storing the data in a different format for filtering
            // no html - so filtering on 'div' won't return items with <div> in it
            // unencrypted - so don't have to unencrypt again for filtering
            // unescaped - so you can find the correct items searching for '&'
            $filter_data = [];
            foreach($columns as $column){
                switch ($column) {
                    default :
                        if(($pos = stripos($column, "custom_field_")) !== false){
                            if(customization_extra()){
                                $field_id = str_replace("custom_field_", "", $column);
                                $custom_values = getCustomFieldValuesByRiskId(convert_id($risk['id']));
                                $text = "";
                                // Get value of custom filed
                                foreach($custom_values as $custom_value)
                                {
                                    // Check if this custom value is for the active field
                                    if($custom_value['field_id'] == $field_id){
                                        $text = get_custom_field_name_by_value($field_id, $custom_value['field_type'], $custom_value['encryption'], $custom_value['value']);
                                        break;
                                    }
                                }
                                $data_row[] = $text;
                                $risk[$column] = strip_tags($text);
                                $filter_data[$column] = $risk[$column];
                            }
                        } else {
                            $data_row[] = $escaper->escapeHtml($risk[$column]);
                            $filter_data[$column] = $risk[$column];
                        }
                        break;
                    case "id":
                        $id = convert_id($risk['id']);
                        $data_row[] = "<div data-id='{$id}' class='open-risk'><a href=\"../management/view.php?id={$id}&active=PerformManagementReviews\">{$id}</a></div>";
                        $filter_data[$column] = $id;
                        break;
                    case "risk_status":
                        $data_row[] = $escaper->escapeHtml($risk['status']);
                        $filter_data[$column] = $risk['status'];
                        break;
                    case "calculated_risk":
                        $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder' style='position:relative;'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>";
                        $filter_data[$column] = $risk['calculated_risk'];
                        break;
                    case "submission_date":
                        $data_row[] = $escaper->escapeHtml($submission_date);
                        $filter_data[$column] = $submission_date;
                        break;
                    case "mitigation_planned":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-mitigation mitigation active-cell\" >".$mitigation_planned."</div>";
                        $filter_data[$column] = $mitigation_planned;
                        break;
                    case "management_review":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-review management active-cell\">".$management_review."</div>";
                        $filter_data[$column] = $management_review;
                        break;
                    case "closure_date":
                        $filter_data[$column] = format_datetime($risk['closure_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "regulation":
                        $filter_data[$column] = try_decrypt($risk["regulation"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "scoring_method":
                        $filter_data[$column] = get_scoring_method_name($risk["scoring_method"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "project":
                        $filter_data[$column] = try_decrypt($risk["project"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "risk_assessment":
                        $filter_data[$column] = try_decrypt($risk["risk_assessment"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "additional_notes":
                        $filter_data[$column] = try_decrypt($risk["additional_notes"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "affected_assets":
                        // Do a lookup for the list of affected assets
                        $affected_assets = '';
                        $assets_array = [];

                        // If the affected assets or affected asset groups is not empty
                        if ($risk['affected_assets']) {
                            foreach (explode(', ', $risk['affected_assets']) as $asset) {
                                $asset = try_decrypt($asset);
                                $affected_assets .= "<span class='asset'>" . $escaper->escapeHtml($asset) . "</span>";
                                $assets_array []= $asset;
                            }
                        }

                        if ($risk['affected_asset_groups']) {
                            foreach (explode(', ', $risk['affected_asset_groups']) as $group) {
                                $affected_assets .= "<span class='group'>" . $escaper->escapeHtml($group) . "</span>";
                                $assets_array []= $group;
                            }
                        }

                        $data_row[] = $affected_assets ? "<div class='affected-asset-cell'>{$affected_assets}</div>" : '';
                        $filter_data[$column] = !empty($assets_array) ? implode(' ', $assets_array) : '';
                        break;
                    case "mitigation_cost":
                        $mitigation_min_cost = $risk['mitigation_min_cost'];
                        $mitigation_max_cost = $risk['mitigation_max_cost'];
                        // If the mitigation costs are empty
                        if (empty($mitigation_min_cost) && empty($mitigation_max_cost))
                        {
                            // Return no value
                            $mitigation_cost = "";
                        }
                        else
                        {
                            $mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;
                            if (!empty($risk['valuation_level_name']))
                                $mitigation_cost .= " ({$risk['valuation_level_name']})";
                        }
                        $data_row[] = $escaper->escapeHtml($mitigation_cost);
                        $filter_data[$column] = $mitigation_cost;
                        break;
                    case "mitigation_accepted":
                        $mitigation_accepted = $risk['mitigation_accepted'] ? $lang['Yes'] : $lang['No'];
                        $data_row[] = $escaper->escapeHtml($mitigation_accepted);
                        $filter_data[$column] = $mitigation_accepted;
                        break;
                    case "mitigation_date":
                        $filter_data[$column] = format_datetime($risk['mitigation_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "current_solution":
                        $filter_data[$column] = try_decrypt($risk["current_solution"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "security_recommendations":
                        $filter_data[$column] = try_decrypt($risk["security_recommendations"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "security_requirements":
                        $filter_data[$column] = try_decrypt($risk["security_requirements"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "review_date":
                        $filter_data[$column] = format_datetime($risk['review_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "planning_date":
                        $filter_data[$column] = format_datetime($risk['planning_date'], "", "");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "next_review_date":
                        $data_row[] = $escaper->escapeHtml($next_review);
                        $filter_data[$column] = $next_review;
                        break;
                    case "comments":
                        $filter_data[$column] = try_decrypt($risk["comments"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "risk_tags":
                        $tags = "";
                        $filter_data[$column] = '';
                        if ($risk['risk_tags']) {
                            $filter_data[$column] = str_getcsv($risk['risk_tags'], '|');
                            foreach($filter_data[$column] as $tag) {
                                $tags .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin: 1px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
                            }
                        }
                        $data_row[] = $tags;
                        break;
                    case "risk_mapping":
                        if (!empty($risk['risk_catalog_mapping'])) {
                            $filter_data[$column] = get_names_by_multi_values("risk_catalog", $risk['risk_catalog_mapping'], false, ", ", true);
                            $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        } else {
                            $data_row[] = '';
                            $filter_data[$column] = '';
                        }
                        break;
                    case "threat_mapping":
                        if (!empty($risk['threat_catalog_mapping'])) {
                            $filter_data[$column] = get_names_by_multi_values("threat_catalog", $risk['threat_catalog_mapping'], false, ", ", true);
                            $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        } else {
                            $data_row[] = '';
                            $filter_data[$column] = '';
                        }
                        break;
                }
            }
            $risk["data_row"] = $data_row;
            $risk["filter_data"] = $filter_data;
            $risks_data[] = $risk;
        }

        if(($pos = stripos($orderColumnName, "custom_field_")) !== false){
            // Sorting by the custom field review text as the normal 'management_review' field contains html
            usort($risks_data, function($a, $b) use ($orderDir, $orderColumnName){
                // For identical custom fields we're sorting on the id, so the results' order is not changing
                if ($a[$orderColumnName] === $b[$orderColumnName]) {
                    return (int)$a['id'] - (int)$b['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a[$orderColumnName], $b[$orderColumnName]);
                } else {
                    return strcmp($b[$orderColumnName], $a[$orderColumnName]);
                }
            });
        }

        $data = array();
        foreach ($risks_data as $key=>$risk) {
            // column filter
            $filter_data = $risk["filter_data"];
            $success = true;
            foreach($column_filters as $column_name => $val){
                switch ($column_name) {
                    default :
                        if(stripos($filter_data[$column_name], $val) === false){
                            $success = false;
                        }
                        break;
                    case "risk_tags":
                        if ($filter_data['risk_tags']) {
                            $tag_match = false;
                            foreach ($filter_data['risk_tags'] as $tag) {
                                $tag_match |= stripos($tag, $val) !== false;
                                if ($tag_match) {
                                    break;
                                }
                            }
                            if (!$tag_match) {
                                $success = false;
                            }
                        } else {
                            $success = false;
                        }
                        break;
                }
            }
            if($success == true) $data[] = $risk["data_row"];
        }
        $risks_by_page = [];
        
        if($length == -1)
        {
            $risks_by_page = $data;
        }
        else
        {
            for($i=$start; $i<count($data) && $i<$start + $length; $i++){
                $risks_by_page[] = $data[$i];
            }
        }
        $recordsTotal = count($data);
        $result = array(
            'draw' => $draw,
            'data' => $risks_by_page,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
    }
}

/*********************************************************
 * FUNCTION: RETURN JSON DATA FOR REVIEW RISKS DATATABLE *
 *********************************************************/
function getReviewRisksDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has risk management permissions
    if (check_permission("riskmanagement"))
    {
        $user = get_user_by_id($_SESSION['uid']);
        $settings = json_decode($user["custom_reviewregularly_display_settings"], true);
        $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
        $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
        $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
        $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
        $columns = [];
        foreach($columns_setting as $column){
            if(stripos($column[0], "custom_field_") !== false){
                if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
            } else if($column[1] == 1) $columns[] = $column[0];
        }
        if(!count($columns)){
            $columns = array("id","risk_status","subject","calculated_risk","days_open","next_review_date");
        }

        $draw = $escaper->escapeHtml($_POST['draw']);

        $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
        $length = $_POST['length'] ? (int)$_POST['length'] : 10;
        $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
        $orderColumnName = isset($_POST['columns'][$orderColumn]['name']) ? $_POST['columns'][$orderColumn]['name'] : null;
        $orderDir = !empty($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
            }
        }

        // Get the list of reviews
        $risks = get_risks(3, $orderColumnName, $orderDir);

        $encryption_columns = array("regulation", "project", "risk_assessment", "additional_notes", "current_solution", "security_recommendations", "security_requirements", "comments");

        if(encryption_extra()&&in_array($orderColumnName, $encryption_columns)){
            $decrypted_risks = array();
            foreach($risks as $risk)
            {
                $risk['encryption_order'] = try_decrypt($risk[$orderColumnName]);
                $decrypted_risks[] = $risk;
            }
            $risks = $decrypted_risks;
            usort($risks, function($a, $b) use ($orderDir) {
                if($orderDir == "asc") 
                    return strcasecmp($a['encryption_order'], $b['encryption_order']);
                else 
                    return strcasecmp($b['encryption_order'], $a['encryption_order']);
            });
        }

        // Initialize the arrays
        $sorted_reviews = array();
        $need_reviews = array();
        $need_next_review = array();
        $need_calculated_risk = array();
        $reviews = array();
        $date_next_review = array();
        $date_calculated_risk = array();

        $risk_levels = get_risk_levels();
        $next_review_date_uses = get_setting('next_review_date_uses');

        $review_levels = get_review_levels();

        // If we're ordering by the 'management_review' column
        if ($orderColumnName === 'management_review') {
            // Calculate the 'management_review' values
            foreach($risks as &$risk) {
                $risk_level = get_risk_level_name($risk['calculated_risk']);
                $residual_risk_level = get_risk_level_name($risk['residual_risk']);

                // If next_review_date_uses setting is Residual Risk.
                if(get_setting('next_review_date_uses') == "ResidualRisk")
                {
                    $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }
                // If next_review_date_uses setting is Inherent Risk.
                else
                {
                    $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
                }

                $risk['management_review'] = management_review($risk['id'], $risk['mgmt_review'], $next_review);
                $risk['management_review_text'] = management_review_text_only($risk['mgmt_review'], $next_review);
            }
            unset($risk);

            // Sorting by the management review text as the normal 'management_review' field contains html
            usort($risks, function($a, $b) use ($orderDir){
                // For identical management reviews we're sorting on the id, so the results' order is not changing
                if ($a['management_review_text'] === $b['management_review_text']) {
                    return (int)$a['id'] - (int)$b['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a['management_review_text'], $b['management_review_text']);
                } else {
                    return strcmp($b['management_review_text'], $a['management_review_text']);
                }
            });
        }

        // Parse through each row in the array
        foreach ($risks as $key => $row)
        {
            // Create arrays for each value
            $risk_id[$key] = (int)$row['id'];
            $subject[$key] = $row['subject'];
            $status[$key] = $row['status'];
            $calculated_risk[$key] = $row['calculated_risk'];
            $color[$key] = get_risk_color_from_levels($row['calculated_risk'], $risk_levels);
            $risk_level = get_risk_level_name_from_levels($row['calculated_risk'], $risk_levels);
            $residual_risk_level = get_risk_level_name_from_levels($row['residual_risk'], $risk_levels);
//            $dayssince[$key] = dayssince($row['submission_date']);
            $dayssince[$key] = $row['days_open'];

            // If next_review_date_uses setting is Residual Risk.
            if($next_review_date_uses == "ResidualRisk")
            {
                $next_review[$key] = next_review($residual_risk_level, $risk_id[$key], $row['next_review'], false);
                $next_review_html[$key] = next_review($residual_risk_level, $row['id'], $row['next_review']);
            }
            // If next_review_date_uses setting is Inherent Risk.
            else
            {
                $next_review[$key] = next_review($risk_level, $risk_id[$key], $row['next_review'], false);
                $next_review_html[$key] = next_review($risk_level, $row['id'], $row['next_review']);
            }

            $sorted_reviews[] =  array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key], 'risk'=>$row);

            // If the next review is UNREVIEWED or PAST DUE
            if ($next_review[$key] == "UNREVIEWED" || $next_review[$key] == $lang['PASTDUE'])
            {
                // Create an array of the risks needing immediate review
                $need_reviews[] = array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key], 'risk'=>$row);
                $need_next_review[] = $next_review[$key];
                $need_calculated_risk[] = $calculated_risk[$key];
            }
            // Otherwise it is an actual review date
            else {
                // Create an array of the risks with future reviews
                $reviews[] = array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key], 'risk'=>$row);
                // Convert next review to standard date fromat for sort
                $standard_next_review = get_standard_date_from_default_format($next_review[$key]);
                $date_next_review[] = $standard_next_review;
                $date_calculated_risk[] = $calculated_risk[$key];
            }
        }
        
        if($orderColumnName == "next_review_date"){
            // Sort the need reviews array by next_review
            array_multisort($need_next_review, SORT_DESC, SORT_STRING, $need_calculated_risk, SORT_DESC, SORT_NUMERIC, $need_reviews);

            // Sort the reviews array by next_review
            array_multisort($date_next_review, SORT_ASC, SORT_STRING, $date_calculated_risk, SORT_DESC, SORT_NUMERIC, $reviews);

            // Merge the two arrays back together to a single reviews array
            $reviews = array_merge($need_reviews, $reviews);
            
            if($orderDir == "desc"){
                $reviews = array_reverse($reviews);
            }
        }else{
            $reviews = $sorted_reviews;
        }
        
        $reviews_data = [];
        foreach ($reviews as $key=>$review)
        {
            $risk = $review["risk"];
            $risk_id = $review['risk_id'];
            $subject = $review['subject'];
            $status = $review['status'];
            $calculated_risk = $review['calculated_risk'];
            $color = $review['color'];
            $dayssince = $review['dayssince'];
            $next_review = $review['next_review'];
            $next_review_html = $review['next_review_html'];
            $submission_date = date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']));
            $mitigation_planned = planned_mitigation(convert_id($risk['id']), $risk['mitigation_id'],"ReviewRisksRegularly");
            $management_review = management_review(convert_id($risk['id']), $risk['mgmt_review'], $next_review, true, "ReviewRisksRegularly");
            $data_row = [];
            // Storing the data in a different format for filtering
            // no html - so filtering on 'div' won't return items with <div> in it
            // unencrypted - so don't have to unencrypt again for filtering
            // unescaped - so you can find the correct items searching for '&'
            $filter_data = [];
            foreach($columns as $column){
                switch ($column) {
                    default :
                        if(($pos = stripos($column, "custom_field_")) !== false){
                            if(customization_extra()){
                                $field_id = str_replace("custom_field_", "", $column);
                                $custom_values = getCustomFieldValuesByRiskId(convert_id($risk['id']));
                                $text = "";
                                // Get value of custom filed
                                foreach($custom_values as $custom_value)
                                {
                                    // Check if this custom value is for the active field
                                    if($custom_value['field_id'] == $field_id){
                                        $text = get_custom_field_name_by_value($field_id, $custom_value['field_type'], $custom_value['encryption'], $custom_value['value']);
                                        break;
                                    }
                                }
                                $data_row[] = $text;
                                $risk[$column] = strip_tags($text);
                                $filter_data[$column] = $risk[$column];
                            }
                        } else {
                            $data_row[] = $escaper->escapeHtml($risk[$column]);
                            $filter_data[$column] = $risk[$column];
                        }
                        break;
                    case "id":
                        $id = convert_id($risk_id);
                        $data_row[] = "<div data-id='{$id}' class='open-risk'><a href=\"../management/view.php?id={$id}&active=ReviewRisksRegularly\">{$id}</a></div>";
                        $filter_data[$column] = $id;
                        break;
                    case "risk_status":
                        $data_row[] = $escaper->escapeHtml($status);
                        $filter_data[$column] = $status;
                        break;
                    case "calculated_risk":
                        $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder' style='position:relative;'>" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>";
                        $filter_data[$column] = $calculated_risk;
                        break;
                    case "days_open":
                        $data_row[] = $escaper->escapeHtml($dayssince);
                        $filter_data[$column] = $dayssince;
                        break;
                    case "submission_date":
                        $data_row[] = $escaper->escapeHtml($submission_date);
                        $filter_data[$column] = $submission_date;
                        break;
                    case "mitigation_planned":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-mitigation mitigation active-cell\" >".$mitigation_planned."</div>";
                        $filter_data[$column] = $mitigation_planned;
                        break;
                    case "management_review":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-review management active-cell\">".$management_review."</div>";
                        $filter_data[$column] = $management_review;
                        break;
                    case "closure_date":
                        $filter_data[$column] = format_datetime($risk['closure_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "regulation":
                        $filter_data[$column] = try_decrypt($risk["regulation"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "scoring_method":
                        $filter_data[$column] = get_scoring_method_name($risk["scoring_method"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "project":
                        $filter_data[$column] = try_decrypt($risk["project"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "risk_assessment":
                        $filter_data[$column] = try_decrypt($risk["risk_assessment"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "additional_notes":
                        $filter_data[$column] = try_decrypt($risk["additional_notes"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "affected_assets":
                        // Do a lookup for the list of affected assets
                        $affected_assets = '';
                        $assets_array = [];

                        // If the affected assets or affected asset groups is not empty
                        if ($risk['affected_assets']) {
                            foreach (explode(', ', $risk['affected_assets']) as $asset) {
                                $asset = try_decrypt($asset);
                                $affected_assets .= "<span class='asset'>" . $escaper->escapeHtml($asset) . "</span>";
                                $assets_array []= $asset;
                            }
                        }

                        if ($risk['affected_asset_groups']) {
                            foreach (explode(', ', $risk['affected_asset_groups']) as $group) {
                                $affected_assets .= "<span class='group'>" . $escaper->escapeHtml($group) . "</span>";
                                $assets_array []= $group;
                            }
                        }

                        $data_row[] = $affected_assets ? "<div class='affected-asset-cell'>{$affected_assets}</div>" : '';
                        $filter_data[$column] = !empty($assets_array) ? implode(' ', $assets_array) : '';
                        break;
                    case "mitigation_cost":
                        $mitigation_min_cost = $risk['mitigation_min_cost'];
                        $mitigation_max_cost = $risk['mitigation_max_cost'];
                        // If the mitigation costs are empty
                        if (empty($mitigation_min_cost) && empty($mitigation_max_cost))
                        {
                                // Return no value
                                $mitigation_cost = "";
                        }
                        else 
                        {
                            $mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;
                            if (!empty($risk['valuation_level_name']))
                                $mitigation_cost .= " ({$risk['valuation_level_name']})";
                        }
                        $data_row[] = $escaper->escapeHtml($mitigation_cost);
                        $filter_data[$column] = $mitigation_cost;
                        break;
                    case "mitigation_accepted":
                        $mitigation_accepted = $risk['mitigation_accepted'] ? $lang['Yes'] : $lang['No'];
                        $data_row[] = $escaper->escapeHtml($mitigation_accepted);
                        $filter_data[$column] = $mitigation_accepted;
                        break;
                    case "mitigation_date":
                        $filter_data[$column] = format_datetime($risk['mitigation_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "current_solution":
                        $filter_data[$column] = try_decrypt($risk["current_solution"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "security_recommendations":
                        $filter_data[$column] = try_decrypt($risk["security_recommendations"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "security_requirements":
                        $filter_data[$column] = try_decrypt($risk["security_requirements"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "review_date":
                        $filter_data[$column] = format_datetime($risk['review_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "planning_date":
                        $filter_data[$column] = format_datetime($risk['planning_date'], "", "");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "next_review_date":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_id($risk_id)) ." class=\"text-center open-review\" >".$next_review_html."</div>";
                        $filter_data[$column] = $next_review;
                        break;
                    case "comments":
                        $filter_data[$column] = try_decrypt($risk["comments"]);
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "risk_tags":
                        $tags = "";
                        $filter_data[$column] = '';
                        if ($risk['risk_tags']) {
                            $filter_data[$column] = str_getcsv($risk['risk_tags'], '|');
                            foreach($filter_data[$column] as $tag) {
                                $tags .= "<button class=\"btn btn-secondary btn-sm\" style=\"pointer-events: none;margin: 1px;padding: 4px 12px;\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</button>";
                            }
                        }
                        $data_row[] = $tags;
                        break;
                    case "risk_mapping":
                        if (!empty($risk['risk_catalog_mapping'])) {
                            $filter_data[$column] = get_names_by_multi_values("risk_catalog", $risk['risk_catalog_mapping'], false, ", ", true);
                            $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        } else {
                            $data_row[] = '';
                            $filter_data[$column] = '';
                        }
                        break;
                    case "threat_mapping":
                        if (!empty($risk['threat_catalog_mapping'])) {
                            $filter_data[$column] = get_names_by_multi_values("threat_catalog", $risk['threat_catalog_mapping'], false, ", ", true);
                            $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        } else {
                            $data_row[] = '';
                            $filter_data[$column] = '';
                        }
                        break;
                }
            }
            $review["data_row"] = $data_row;
            $review["filter_data"] = $filter_data;
            $review["risk"] = $risk;
            $reviews_data[] = $review;
        }

        if(($pos = stripos($orderColumnName, "custom_field_")) !== false){
            // Sorting by the custom field review text as the normal 'management_review' field contains html
            usort($reviews_data, function($a, $b) use ($orderDir, $orderColumnName){
                // For identical custom fields we're sorting on the id, so the results' order is not changing
                if ($a["risk"][$orderColumnName] === $b["risk"][$orderColumnName]) {
                    return (int)$a["risk"]['id'] - (int)$b["risk"]['id'];
                }
                if($orderDir == "asc") {
                    return strcmp($a["risk"][$orderColumnName], $b["risk"][$orderColumnName]);
                } else {
                    return strcmp($b["risk"][$orderColumnName], $a["risk"][$orderColumnName]);
                }
            });
        }

        $data = array();
        foreach ($reviews_data as $key=>$review)
        {
            $risk = $review["filter_data"];
            // column filter 
            $success = true;
            foreach($column_filters as $column_name => $val){
                switch ($column_name) {
                    default :
                        if(stripos($risk[$column_name], $val) === false){
                            $success = false;
                        }
                        break;
                    case "risk_tags":
                        if ($risk['risk_tags']) {
                            $tag_match = false;
                            foreach ($risk['risk_tags'] as $tag) {
                                $tag_match |= stripos($tag, $val) !== false;
                                if ($tag_match) {
                                    break;
                                }
                            }
                            if (!$tag_match) {
                                $success = false;
                            }
                        } else {
                            $success = false;
                        }
                        break;
                }
            }
            if($success == true) $data[] = $review["data_row"];
        }

        $risks_by_page = [];

        if($length == -1)
        {
            $risks_by_page = $data;
        }
        else
        {
            for($i=$start; $i<count($data) && $i<$start + $length; $i++){
                $risks_by_page[] = $data[$i];
            }
        }
        $recordsTotal = count($data);
        $result = array(
            'draw' => $draw,
            'data' => $risks_by_page,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
    }
}

/**************************
 * FUNCTION: VERIFY ASSET *
 **************************/
function assets_verify_asset()
{
    global $lang, $escaper;

        // If the id is not sent
    if (!isset($_POST['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } else {
        // If the user has asset permissions
        if (check_permission("asset"))
        {
            $id = (int)$_POST['id'];
            if (verify_asset($id)) {
                set_alert(true, "good", $escaper->escapeHtml($lang['AssetWasVerifiedSuccessfully']));
                json_response(200, get_alert(true), null);
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($lang['ThereWasAProblemVerifyingTheAsset']));
                json_response(400, get_alert(true), NULL);
            }
        }
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForAsset']));
            json_response(400, get_alert(true), NULL);
        }
    }
}


/**************************
 * FUNCTION: DELETE ASSET *
 **************************/
function assets_delete_asset($discard = false)
{
    global $lang, $escaper;

        // If the id is not sent
    if (!isset($_POST['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } else {
        // If the user has asset permissions
        if (check_permission("asset"))
        {
            $id = (int)$_POST['id'];
            if (delete_asset($id)) {
                set_alert(true, "good", $escaper->escapeHtml($discard? $lang['AssetWasDiscardedSuccessfully']: $lang['AssetWasDeletedSuccessfully']));
                json_response(200, get_alert(true), null);
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($discard? $lang['ThereWasAProblemDiscardingTheAsset'] : $lang['ThereWasAProblemDeletingTheAsset']));
                json_response(400, get_alert(true), NULL);
            }
        }
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForAsset']));
            json_response(400, get_alert(true), NULL);
        }
    }
}

/**************************
 * FUNCTION: DISCARD ASSET *
 **************************/
function assets_discard_asset()
{
    assets_delete_asset(true);
}

function assets_verify_assets()
{
    global $lang, $escaper;

        // If the ids are not sent
    if (!isset($_POST['ids']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } else {
        // If the user has asset permissions
        if (check_permission("asset"))
        {
            $ids = json_decode($_POST['ids']);
            if (verify_assets($ids)) {
                set_alert(true, "good", $escaper->escapeHtml($lang['AssetsWereVerifiedSuccessfully']));
                json_response(200, get_alert(true), null);
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($lang['ThereWasAProblemVerifyingTheAssets']));
                json_response(400, get_alert(true), NULL);
            }
        }
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForAsset']));
            json_response(400, get_alert(true), NULL);
        }
    }
}


function assets_delete_assets($discard=false)
{
    global $lang, $escaper;

        // If the ids are not sent
    if (!isset($_POST['ids']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } else {
        // If the user has asset permissions
        if (check_permission("asset"))
        {
            $ids = json_decode($_POST['ids']);
            if (delete_assets($ids)) {
                set_alert(true, "good", $escaper->escapeHtml($discard? $lang['AssetsWereDiscardedSuccessfully']: $lang['AssetsWereDeletedSuccessfully']));
                json_response(200, get_alert(true), null);
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($discard? $lang['ThereWasAProblemDiscardingTheAssets'] : $lang['ThereWasAProblemDeletingTheAssets']));
                json_response(400, get_alert(true), NULL);
            }
        }
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForAsset']));
            json_response(400, get_alert(true), NULL);
        }
    }
}

function assets_discard_assets()
{
    assets_delete_assets(true);
}

/**************************
 * FUNCTION: UPDATE ASSET *
 **************************/
function assets_update_asset()
{
    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_POST['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } 
    else 
    {
        // If the user has asset permissions
        if (check_permission("asset"))
        {
            $id = (int)get_param("POST", "id");
            $fieldName = get_param("POST", "fieldName");
            $fieldValue = get_param("POST", "fieldValue");
            
            // If this is custom field
            if(stripos($fieldName, "custom_field") !== false)
            {
                $custom_field_id = str_replace(['custom_field', '[', ']'], '', $fieldName);
                
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                    
                    //$updated = save_asset_custom_field_by_field_id($id, $custom_field_id, $fieldValue);
                    $_POST['custom_field'] = array($custom_field_id => $fieldValue); 
                    $updated = save_custom_field_values($id, "asset");
                }
                else
                {
                    $updated = false;
                }
            }
            // If this is not custom field
            else
            {
                if($fieldName){
                    $fieldName = explode("-", $fieldName)[0];
                }

                $updated = update_asset_field_value_by_field_name($id, $fieldName, $fieldValue);
            }
            
            // If success for update
            if($updated){
                $asset = get_asset_by_id($id);
                set_alert(true, "good", $escaper->escapeHtml($lang['AssetWasUpdatedSuccessfully']));
                if ($fieldName == "tags") {
                    $options = [];
                    foreach(getTagsOfType('asset') as $tag) {
                        $options[] = array('label' => $tag['tag'], 'value' => $tag['id']);
                    }
                    json_response(200, get_alert(true), $options);
                } else {
                    json_response(200, get_alert(true), null);
                }
            }
            // If failed for update
            else
            {
                set_alert(true, "bad", $escaper->escapeHtml($lang['ThereWasAProblemUpdatingTheAsset']));
                json_response(400, get_alert(true), NULL);
            }

        }
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForAsset']));
            json_response(400, get_alert(true), NULL);
        }
    }
}


/*******************************************************************
 * FUNCTION: GET THE BODY OF THE TABLE LISTING THE VERIFIED ASSETS *
 *******************************************************************/
function assets_verified_asset_table_body()
{
    if (check_permission("asset")) {

        ob_start();
        display_asset_table_body();
        $body = ob_get_contents();
        ob_end_clean();
        
        json_response(200, null, $body);
    } else {
        global $lang;
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        json_response(400, get_alert(true), NULL);
    }
}

/*********************************************************
 * FUNCTION: RETURN JSON DATA FOR REVIEW RISKS DATATABLE *
 *********************************************************/
function getReviewsWithDateIssuesDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has risk management permissions
    if (check_permission("riskmanagement"))
    {
        $draw = (int)$escaper->escapeHtml($_GET['draw']);

        $order_column = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
        $order_dir = $escaper->escapeHtml($_GET['order'][0]['dir']) == "asc" ? "asc" : "desc";
        $offset = (int)$_GET['start'];
        $page_size = (int)$_GET['length'];

        $response = getReviewsWithDateIssues($order_column, $order_dir, $offset, $page_size);
        $recordsTotal = $response[0];
        $reviews = $response[1];

        $data = array();

        foreach ($reviews as $key=>$review) {
            $risk_id = $review['risk_id'];
            $review_id = $review['review_id'];
            $subject = try_decrypt($review['subject']);
            $next_review = $review['next_review'];

            $select = "<select id=\"format_" . $escaper->escapeHtml($review['review_id']) . "\" style=\"width:auto;height:auto;padding:0px;margin:0px;\">\n";
            $select .= "<option value=\"\">" . $escaper->escapeHtml($lang['PleaseSelect']) . "</option>\n";
            $pf = possibleFormats($review['next_review']);

            foreach($pf as $format) {
                $select .= "<option value=\"" . $escaper->escapeHtml($format) . "\">" . $escaper->escapeHtml(convertDateFormatFromPHP($format)) . "</option>\n";
            }
            $select .= "</select>";

            $data[] = [
                "<div data-id=". $escaper->escapeHtml(convert_id($risk_id)) ." class='open-risk'><a target=\"_blank\" href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></div>",
                $escaper->escapeHtml($subject),
                "<div data-id=". $escaper->escapeHtml(convert_id($risk_id)) ." class=\"text-center\" >".$escaper->escapeHtml($next_review)."</div>",
                $select,
                $review_id = $escaper->escapeHtml($review['review_id']),
            ];
        }
        $result = array(
            'draw' => $draw,
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
    }
}

/************************************
 * FUNCTION: FIX REVIEW DATE FORMAT *
 ************************************/
function fixReviewDateFormat() {

    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_POST['review_id'])) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } elseif (!isset($_POST['format'])) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyTheFormatParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } else {
        // If the user has risk management permissions
        if (check_permission("riskmanagement"))
        {
            $id = (int)$_POST['review_id'];

            $format = convertDateFormatToPHP($_POST['format']);

            if (fixNextReviewDateFormat($id, $format)) {
                set_alert(true, "good", $escaper->escapeHtml($lang['NextReviewDateWasUpdatedSuccessfully']));
                json_response(200, get_alert(true), null);
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($lang['NextReviewDateUpdateFailed']));
                json_response(400, get_alert(true), NULL);
            }
        } else {
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForRiskManagement']));
            json_response(400, get_alert(true), NULL);
        }
    }
}

/*************************************
 * FUNCTION: GET TAG OPTIONS OF TYPE *
 *************************************/
function getTagOptionsOfType() {

    global $lang, $escaper, $tag_types;

    // If the type is not sent or it's value is not one of the supported types
    if (!isset($_GET['type']) || !in_array($_GET['type'], $tag_types))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyATypeParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    else
    {
        $type = $_GET['type'];

        if ($type === 'risk' && !check_permission("riskmanagement")) {
            set_alert(true, "bad", $lang['NoPermissionForRiskManagement']);
            json_response(400, get_alert(true), NULL);
            return;
        } elseif($type === 'asset' && !check_permission("asset")) {
            set_alert(true, "bad", $lang['NoPermissionForAsset']);
            json_response(400, get_alert(true), NULL);
            return;
        } elseif(($type === 'questionnaire_answer' || $type === 'questionnaire_pending_risk') && (!check_permission("assessments") || !assessments_extra())) {
            set_alert(true, "bad", $lang['NoPermissionForAssessments']);
            json_response(400, get_alert(true), NULL);
            return;
        } elseif(($type === 'incident_management_source' || $type === 'incident_management_destination') && ((!check_permission("im_submit_incidents") && !check_permission("im_edit_incidents"))  || !incident_management_extra())) {
            set_alert(true, "bad", $lang['NoPermissionForIncidentManagement']);
            json_response(400, get_alert(true), NULL);
            return;
        } else {
            $options = [];
            foreach(getTagsOfType($type) as $tag) {
                $options[] = array('label' => $tag['tag'], 'value' => (int)$tag['id']);
            }

            json_response(200, null, $options);
        }
    }
}

/*************************************
 * FUNCTION: GET TAG OPTIONS OF TYPES *
 *************************************/
function getTagOptionsOfTypes() {
    
    global $lang, $escaper, $tag_types;
    
    // Getting the types
    $types = isset($_GET['type']) ? explode(',', $_GET['type']) : [];
    
    // Making sure we only accept the types we can work with
    $types = array_intersect($types, $tag_types);
    
    // If there's no type left
    if (empty($types))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyATypeParameter']));
        
        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } else {

        if (in_array('risk', $types) && !check_permission("riskmanagement")) {
            set_alert(true, "bad", $lang['NoPermissionForRiskManagement']);
            json_response(400, get_alert(true), NULL);
            return;
        } elseif(in_array('asset', $types) && !check_permission("asset")) {
            set_alert(true, "bad", $lang['NoPermissionForAsset']);
            json_response(400, get_alert(true), NULL);
            return;
        } elseif((in_array('questionnaire_answer', $types) || in_array('questionnaire_pending_risk', $types)) && (!check_permission("assessments") || !assessments_extra())) {
            set_alert(true, "bad", $lang['NoPermissionForAssessments']);
            json_response(400, get_alert(true), NULL);
            return;
        }

        $options = [];
        foreach(getTagsOfTypes($types) as $tag) {
            $options[] = array('label' => $tag['tag'], 'value' => (int)$tag['id']);
        }
        
        json_response(200, null, $options);

    }
}

/***********************************
 * FUNCTION: UPDATE RISK LEVEL API *
 ***********************************/
function update_risk_level_API() {

    global $lang, $escaper;

    if(is_admin())
    {
        $level = (int)$_POST['level'];

        if ($level < 0 || $level > 3) {
            set_alert(true, "bad", $lang['RiskLevelInvalidLevelParameter']);

            // Return a JSON response
            json_response(400, get_alert(true), NULL);
            return;
        }

        $field = $_POST['field'];

        if (!in_array($field, ['value', 'color', 'display_name'])) {
            set_alert(true, "bad", $lang['RiskLevelInvalidFieldParameter']);

            // Return a JSON response
            json_response(400, get_alert(true), NULL);
            return;
        }

        $value = $_POST['value'];
        $risk_levels = get_risk_levels();
        $originalValue = $risk_levels[$level][$field];

        if ($field === 'value') {
            if (!is_numeric($value)) {
                set_alert(true, "bad", $lang['RiskLevelNonNumericValueParameter']);

                // Return a JSON response
                json_response(400, get_alert(true), $originalValue);
                return;
            } else {
                $value = (float)$value;
                if(($level == 3 && $risk_levels[2]['value'] < $value) || ($level == 0 && $risk_levels[1]['value'] > $value) || ($level != 3 && $level != 0 && $risk_levels[$level-1]['value'] < $value && $risk_levels[$level+1]['value'] > $value)) {
                    $risk_levels[$level][$field] = $value;
                } else {
                    // Otherwise, there was a problem with the order
                    set_alert(true, "bad", $lang['RiskLevelInvalidValueOrder']);
                    json_response(400, get_alert(true), $originalValue);
                    return;
                }
                //if (($risk_levels[0]['value'] > $risk_levels[1]['value']) && ($risk_levels[1]['value'] < $risk_levels[2]) && ($risk_levels[2]['value'] < $risk_levels[3]['value']))
            }
        } elseif(strlen($value) > 20) {
            set_alert(true, "bad", $lang['RiskLevelTooLongValueParameter']);

            // Return a JSON response
            json_response(400, get_alert(true), $originalValue);
            return;
        } elseif($field === 'color' && !preg_match("/^#(?:[a-f0-9]{3}){1,2}$/i", $value)) {
            set_alert(true, "bad", $lang['RiskLevelInvalidColorParameter']);

            // Return a JSON response
            json_response(400, get_alert(true), $originalValue);
            return;
        }

        $level_names_arr = array("Low", "Medium", "High", "Very High");
        $name = $level_names_arr[$level];

        update_risk_level($field, $value, $name);

        // Audit log
        write_log(1000, $_SESSION['uid'], _lang('RiskLevelAuditLog', array(
            'field' => $field == 'display_name'? 'display name' : $field,
            'name' => $name,
            'originalValue' => $originalValue,
            'value' => $value,
            'user' => $_SESSION['user']
        )));

        set_alert(true, "good", $lang['RiskLevelSuccessfullyUpdated']);
        json_response(200, get_alert(true), null);
        return;
    }
    else
    {
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        json_response(400, get_alert(true), NULL);
        return;
    }
}

/*****************************
 * FUNCTION: LIST EXCEPTIONS *
 *****************************/
function get_exceptions_as_treegrid_api()
{
    global $lang;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('view')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionCreate']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    if (empty($_GET['type']) || !trim($_GET['type']) || !in_array($_GET['type'], ['policy', 'control', 'unapproved'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyATypeParameter']);

        json_response(400, get_alert(true), NULL);
        return;
    }
    $type = $_GET['type'];
    $result = get_exceptions_as_treegrid($type);
    json_response(200, null, $result);
}

/********************************
 * FUNCTION: GET EXCEPTION DATA *
 ********************************/
function get_exception_api()
{
    global $lang, $escaper;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('view')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionCreate']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    if (empty($_GET['id']) || !trim($_GET['id']) || !ctype_digit($_GET['id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);

        json_response(400, get_alert(true), NULL);
        return;
    }

    $exception = get_exception((int)$_GET['id']);

    $exception['additional_stakeholders'] = $exception['additional_stakeholders'] ? explode(',', $exception['additional_stakeholders']) : [];
    $exception['associated_risks'] = $exception['associated_risks'] ? explode(',', $exception['associated_risks']) : [];
    $exception['creation_date'] = format_date($exception['creation_date']);
    $exception['next_review_date'] = format_date($exception['next_review_date']);
    $exception['approval_date'] = format_date($exception['approval_date']);
    $exception['approved'] = boolval($exception['approved']);

    json_response(200, null, $exception);

}

/***********************************************
 * FUNCTION: GET EXCEPTION DATA FOR DISPLAYING *
 ***********************************************/
function get_exception_for_display_api()
{
    global $lang, $escaper;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('view')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionCreate']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    if (empty($_GET['id']) || !trim($_GET['id']) || !ctype_digit($_GET['id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);

        json_response(400, get_alert(true), NULL);
        return;
    }elseif (empty($_GET['type']) || !trim($_GET['type']) || !in_array($_GET['type'], ['policy', 'control'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyATypeParameter']);

        json_response(400, get_alert(true), NULL);
        return;
    }
    $type = $_GET['type'];
    $exception = get_exception_for_display((int)$_GET['id'], $type);

    $exception['name'] = $escaper->escapeHtml($exception['name']);
    $exception["{$type}_name"] = $escaper->escapeHtml($exception['parent_name']);
    $exception["type"] = $type;
    $exception["type_text"] = $escaper->escapeHtml($lang[ucfirst($type)]);
    $exception['document_exceptions_status'] = $escaper->escapeHtml($exception['document_exceptions_status']);
    $exception['owner'] = $escaper->escapeHtml($exception['owner']);
    $exception['additional_stakeholders'] = get_stakeholder_names($exception['additional_stakeholders'], 4, true);
    $exception['associated_risks'] = get_risk_subjects_by_ids($exception['associated_risks'], 4, true);
    $exception['creation_date'] = format_date($exception['creation_date']);
    $exception['next_review_date'] = format_date($exception['next_review_date']);
    if ($type = $_GET['approval']) {
        $exception['approval_date'] = format_date($exception['approval_date']);
        $exception['approver'] = $escaper->escapeHtml($exception['approver']);
    } else {
        //If we need the info for approval
        //we'll show what the value of the approval date and approver will be
        $exception['approval_date'] = date(get_default_date_format());
        $exception['approver'] = $escaper->escapeHtml($_SESSION['name'] ? $_SESSION['name'] : $_SESSION['user']);
    }
    $exception['description'] = nl2br($escaper->escapeHtml($exception['description']));
    $exception['justification'] = nl2br($escaper->escapeHtml($exception['justification']));
    if($exception['unique_name'])
        $exception['file_download'] = "<a href=\"".$_SESSION['base_url']."/governance/download.php?id=".$exception['unique_name']."\" >".$escaper->escapeHtml($exception['file_name']). " (".$exception['file_version'].")" ."</a>";
    else $exception['file_download'] = "";

    foreach($exception as $key => $value) {
        if (strlen($value) == 0)
            $exception[$key] = "--";
    }

    json_response(200, null, $exception);
}

function create_exception_api() {

    global $lang;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('create')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionCreate']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    ##Checking required parameters##
    ################################
    if (empty($_POST['name']) || !trim($_POST['name'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (empty($_POST['owner']) || !ctype_digit($_POST['owner']) || !get_user_by_id((int)$_POST['owner'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyTheOwnerParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    }

    $policy = isset($_POST['policy']) && ctype_digit($_POST['policy']) ? (int)$_POST['policy'] : false;
    $control = isset($_POST['control']) && ctype_digit($_POST['control']) ? (int)$_POST['control'] : false;

    //You have to choose a policy or a control, you can't choose both
    if (!($policy xor $control)) {
        set_alert(true, "bad", $lang['ChooseAPolicyOrControl']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    }

    $name = $_POST['name'];
    $status = (int)$_POST['document_exceptions_status'];
    $owner = (int)$_POST['owner'];
    $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
    $associated_risks = empty($_POST['associated_risks']) ? "" : implode(",", $_POST['associated_risks']);
    $review_frequency = !empty($_POST['review_frequency']) ? $_POST['review_frequency'] : 0;
    $description = $_POST['description'];
    $justification = $_POST['justification'];

    ##Checking if non-required parameters have valid values##
    #########################################################
    if ($review_frequency < 0) {
        set_alert(true, "bad", $lang['InvalidReviewFrequency']);

        json_response(400, get_alert(true), NULL);
        return;
    }

    $today_dt = strtotime(date('Ymd'));
    $creation_date = get_standard_date_from_default_format($_POST['creation_date']);

    if (!$creation_date || $creation_date === "0000-00-00")
        $creation_date = date('Y-m-d');
    else {
        if (strtotime($creation_date) > $today_dt) {
            set_alert(true, "bad", $lang['InvalidCreationDate']);

            json_response(400, get_alert(true), NULL);
            return;
        }
    }

    //calculate next review date
    $next_review_date = get_standard_date_from_default_format($_POST['next_review_date']);
    if (!$next_review_date || $next_review_date === "0000-00-00") {
        $next_review_date = strtotime($creation_date) + ($review_frequency * 24 * 3600);
        if ($next_review_date < $today_dt) {
            $next_review_date = $today_dt;
        }
        $next_review_date = date('Y-m-d', $next_review_date);
    } elseif (strtotime($next_review_date) < $today_dt) {
        set_alert(true, "bad", $lang['InvalidNextReviewDate']);

        json_response(400, get_alert(true), NULL);
        return;
    }

    $approval_date = get_standard_date_from_default_format($_POST['approval_date']);
    $approver = (ctype_digit($_POST['approver']) && get_user_by_id((int)$_POST['approver'])) ? (int)$_POST['approver'] : false;
    $approved = false;
    if ($approval_date && $approval_date !== "0000-00-00") {
        if (strtotime($approval_date) > $today_dt) {
            set_alert(true, "bad", $lang['InvalidApprovalDate']);

            json_response(400, get_alert(true), NULL);
            return;
        }
        //Can only be approved if the user has the approve_exception permission
        $approved = boolval($approver) && check_permission_exception('approve');
    }

    // Approval Date can't be before the Creation Date
    if ($approval_date !== "0000-00-00" && strtotime($approval_date) < strtotime($creation_date)) {
        set_alert(true, "bad", $lang['InvalidApprovalDateCreationDateOrder']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    try {
        $id = create_exception($name, $status, $policy, $control, $owner, $additional_stakeholders, $creation_date, $review_frequency, $next_review_date, $approval_date, $approver, $approved, $description, $justification, $associated_risks);
    } catch(Exception $e) {
        error_log($e);
        set_alert(true, "bad", $lang['ThereWasAProblemCreatingTheException']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    // If success for create
    if($id){
        set_alert(true, "good", $lang['ExceptionWasCreatedSuccessfully']);

        //returning the created exception's type
        //the returned data is needed to know what tabs to refresh
        json_response(200, get_alert(true), array('approved' => $approved, 'type' => $policy ? "policy" : "control"));
    }
    // If failed for update
    else{
        set_alert(true, "bad", $lang['ThereWasAProblemCreatingTheException']);
        json_response(400, get_alert(true), NULL);
    }
}

function update_exception_api() {

    global $lang;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('update')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionUpdate']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    ##Checking required parameters##
    ################################
    if (empty($_POST['exception_id']) || !ctype_digit($_POST['exception_id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (empty($_POST['name']) || !trim($_POST['name'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (empty($_POST['owner']) || !ctype_digit($_POST['owner']) || !get_user_by_id((int)$_POST['owner'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyTheOwnerParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    }

    $policy = isset($_POST['policy']) && ctype_digit($_POST['policy']) ? (int)$_POST['policy'] : false;
    $control = isset($_POST['control']) && ctype_digit($_POST['control']) ? (int)$_POST['control'] : false;

    //You have to choose a policy or a control, you can't choose both
    if (!($policy xor $control)) {
        set_alert(true, "bad", $lang['ChooseAPolicyOrControl']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    }

    $id = (int)$_POST['exception_id'];
    $name = $_POST['name'];
    $status = (int)$_POST['document_exceptions_status'];
    $owner = (int)$_POST['owner'];
    $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
    $associated_risks = empty($_POST['associated_risks']) ? "" : implode(",", $_POST['associated_risks']);
    $review_frequency = !empty($_POST['review_frequency']) ? $_POST['review_frequency'] : 0;
    $description = $_POST['description'];
    $justification = $_POST['justification'];

    ##Checking if non-required parameters have valid values##
    #########################################################
    if ($review_frequency < 0) {
        set_alert(true, "bad", $lang['InvalidReviewFrequency']);

        json_response(400, get_alert(true), NULL);
        return;
    }

    $today_dt = strtotime(date('Ymd'));
    $creation_date = get_standard_date_from_default_format($_POST['creation_date']);

    if (!$creation_date || $creation_date === "0000-00-00")
        $creation_date = date('Y-m-d');
    else {
        if (strtotime($creation_date) > $today_dt) {
            set_alert(true, "bad", $lang['InvalidCreationDate']);

            json_response(400, get_alert(true), NULL);
            return;
        }
    }

    //calculate next review date
    $next_review_date = get_standard_date_from_default_format($_POST['next_review_date']);
    if (!$next_review_date || $next_review_date === "0000-00-00") {
        $next_review_date = strtotime($creation_date) + ($review_frequency * 24 * 3600);
        if ($next_review_date < $today_dt) {
            $next_review_date = $today_dt;
        }
        $next_review_date = date('Y-m-d', $next_review_date);
    } elseif (strtotime($next_review_date) < $today_dt) {
        set_alert(true, "bad", $lang['InvalidNextReviewDate']);

        json_response(400, get_alert(true), NULL);
        return;
    }

    $approved_original = !empty($_POST['approved_original']);
    $approval_date = get_standard_date_from_default_format($_POST['approval_date']);
    $approver = (ctype_digit($_POST['approver']) && get_user_by_id((int)$_POST['approver'])) ? (int)$_POST['approver'] : false;
    $approved = false;
    if ($approval_date && $approval_date !== "0000-00-00") {
        if (strtotime($approval_date) > $today_dt) {
            set_alert(true, "bad", $lang['InvalidApprovalDate']);

            json_response(400, get_alert(true), NULL);
            return;
        }

        //Can only be approved if the user has the approve_exception permission
        $approved = boolval($approver) && check_permission_exception('approve');
    }

    if ($approved && get_setting('exception_update_resets_approval')) {
        $approved = false;
    }

    // Approval Date can't be before the Creation Date
    if ($approval_date !== "0000-00-00" && strtotime($approval_date) < strtotime($creation_date)) {
        set_alert(true, "bad", $lang['InvalidApprovalDateCreationDateOrder']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    try {
        $result = update_exception(
            $name,
	    $status,
            $policy,
            $control,
            $owner,
            $additional_stakeholders,
            $creation_date,
            $review_frequency,
            $next_review_date,
            $approval_date,
            $approver,
            $approved,
            $description,
            $justification,
            $associated_risks,
            $id);

    } catch(Exception $e) {
        error_log($e);
        set_alert(true, "bad", $lang['ThereWasAProblemUpdatingTheException']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    if($result){
        set_alert(true, "good", $lang['ExceptionWasUpdatedSuccessfully']);

        //returning the created exception's type
        //the returned data is needed to know what tabs to refresh
        json_response(200, get_alert(true), array('approved' => $approved_original, 'type' => $policy ? "policy" : "control"));
        return;
    }
    else{
        set_alert(true, "bad", $lang['ThereWasAProblemUpdatingTheException']);
        json_response(400, get_alert(true), NULL);
    }
}

function approve_exception_api() {

    global $lang;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('approve')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionApprove']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    // If the id is not sent
    if (empty($_POST['exception_id']) || !ctype_digit($_POST['exception_id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    }else {
        $id = (int)$_POST['exception_id'];

        approve_exception($id);

        set_alert(true, "good", $lang['ExceptionWasApprovedSuccessfully']);
        json_response(200, get_alert(true), null);
    }
}

function delete_exception_api() {

    global $lang;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('delete')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionDelete']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    // If the id is not sent
    if (empty($_POST['exception_id']) || !ctype_digit($_POST['exception_id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    }else {
        $id = (int)$_POST['exception_id'];

        delete_exception($id);

        set_alert(true, "good", $lang['ExceptionWasDeletedSuccessfully']);
        json_response(200, get_alert(true), null);
    }
}

function batch_delete_exception_api() {

    global $lang;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('delete')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionDelete']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    // If the id is not sent
    if (empty($_POST['parent_id']) || !ctype_digit($_POST['parent_id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
        return;
    }elseif (empty($_POST['type']) || !trim($_POST['type']) || !in_array($_POST['type'], ['policy', 'control'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyATypeParameter']);

        json_response(400, get_alert(true), NULL);
        return;
    }else {

        $approved = !empty($_POST['approved']);
        $type = $_POST['type'];

        batch_delete_exception((int)$_POST['parent_id'], $type, $approved);

        set_alert(true, "good", $lang['ExceptionsWereDeletedSuccessfully_' . $type]);
        json_response(200, get_alert(true), null);
    }
}

/******************************************
 * FUNCTION: GET EXCEPTIONS AUDIT LOG API *
 ******************************************/
function get_exceptions_audit_log_api() 
{

    global $lang, $escaper;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission_exception('view')) {
        set_alert(true, "bad", $lang['NoPermissionForExceptionView']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    $days = !empty($_GET['days']) && ctype_digit($_GET['days']) ? (int)$_GET['days'] : 7;

    if ($days < 0)
        $days = 7;

    json_response(200, null, array_map(function($log) use ($escaper) {
            return array(
                'timestamp' => date(get_default_datetime_format("g:i A T"), strtotime($log['timestamp'])),
                'message' => $escaper->escapeHtml(try_decrypt($log['message']))
            );
        }, get_exceptions_audit_log($days))
    );
}

/*******************************
 * FUNCTION: GET AUDIT LOG API *
 *******************************/
function get_audit_logs_api() 
{
    global $lang;

    if (is_admin()) 
    {
        $days = get_param("get", "days", 7);
        $log_type = get_param("get", "log_type", NULL);
        
        // If log_type is string, try to make array by comman and trim all values
        if($log_type)
        {
            $log_type = str_getcsv($log_type);
        }
        else
        {
            $log_type = NULL;
        }

        json_response(200, null, array_map(function($log) {
                return array(
                    'timestamp' => date(get_default_datetime_format("g:i A T"), strtotime($log['timestamp'])),
                    'username' => $log['user_fullname'],
                    'message' => $log['message']
                );
            }, get_audit_trail(NULL, $days, $log_type))
        );

        return;
    } 
    else
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['AdminPermissionRequired']));
        json_response(400, get_alert(true), NULL);
        return;
    }

}

function get_asset_options() {

    global $lang;

    if (check_permission("asset"))
    {
        $data = get_entered_assets(!isset($_GET['verified']) ? null : boolval($_GET['verified']));

        // To get the id and name from the assets
        $data = array_map(
            function($element){
                global $escaper;
                return array(
                    'id' => $element['id'],
                    'name' => $escaper->escapeHtml(try_decrypt($element['name']))
                );
            }, $data
        );
        json_response(200, null, $data);
    }
    else
    {
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        json_response(400, get_alert(true), NULL);
        return;
    }
}

function asset_group_tree() {

    global $lang, $escaper;

    if (!check_permission("asset")) {
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        json_response(400, get_alert(true), NULL);
        return;
    }elseif (!isset($_GET['page']) || !isset($_GET['rows'])) {
        set_alert(true, "bad", $lang['TreegridMissingRequiredParameters']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    $id = isset($_GET['id']) && !empty($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : false;

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $rows = isset($_GET['rows']) ? (int)$_GET['rows'] : 10;
    $offset = ($page-1)*$rows;

    // Parent items
    if ($id === false) {
        $data = get_asset_groups_for_treegrid($offset, $rows);
        json_response(200, get_alert(true), $data);
    } else { // The children
        $data = get_assets_of_asset_group_for_treegrid($id);
        json_response(200, get_alert(true), $data);
    }
}

function asset_group_info()
{
    global $lang, $escaper;

    if (!check_permission("asset")) {
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        json_response(400, get_alert(true), NULL);
        return;
    }elseif (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    
    try {
        $group = get_asset_group((int)$_GET['id']);
        $group['name'] = $group['name'];
        
        foreach ($group['selected_assets'] as &$selected_asset)
        {
            $selected_asset['name'] = $escaper->escapeHtml($selected_asset['name']);
        }

        foreach ($group['available_assets'] as &$available_asset)
        {
            $available_asset['name'] = $escaper->escapeHtml($available_asset['name']);
        }

        json_response(200, null, $group);
        return;
    } catch(Exception $e) {
        error_log($e);
        set_alert(true, "bad", $lang['ThereWasAProblemGettingTheAssetGroup']);
        json_response(400, get_alert(true), NULL);
        return;
    }
}

function asset_group_create()
{
    global $lang;

    if (!check_permission("asset")) {
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (empty($_POST['name'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    $name = $_POST['name'];
    $selected_assets = empty($_POST['selected_assets']) ? [] : $_POST['selected_assets'];

    if (get_value_by_name('asset_groups', $name)) {
        set_alert(true, "bad", $lang['AssetGroupNameAlreadyInUse']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    
    try {
        $id = create_asset_group($name, $selected_assets);
    } catch(Exception $e) {
        error_log($e);
        $id = false;
    }

    if ($id) {
        set_alert(true, "good", $lang['AssetGroupCreatedSuccessfully']);
        json_response(200, get_alert(true), null);
        return;
    } else {
        set_alert(true, "bad", $lang['ThereWasAProblemCreatingTheAssetGroup']);
        json_response(400, get_alert(true), NULL);
        return;
    }
}

function asset_group_update()
{
    global $lang;

    if (!check_permission("asset")) {
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (empty($_POST['asset_group_id']) || !ctype_digit($_POST['asset_group_id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (empty($_POST['name'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    $id = (int)$_POST['asset_group_id'];
    $name = $_POST['name'];
    $selected_assets = empty($_POST['selected_assets']) ? [] : $_POST['selected_assets'];

    $id_check = get_value_by_name('asset_groups', $name);
    if ($id_check != $id && $id_check !== null) {
        set_alert(true, "bad", $lang['AssetGroupNameAlreadyInUse']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    
    try {
        update_asset_group($id, $name, $selected_assets);
    } catch(Exception $e) {
        error_log($e);
        set_alert(true, "bad", $lang['ThereWasAProblemUpdatingTheAssetGroup']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    set_alert(true, "good", $lang['AssetGroupUpdatedSuccessfully']);
    json_response(200, get_alert(true), null);
}

function asset_group_delete()
{
    global $lang;

    if (!check_permission("asset")) {
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (empty($_POST['asset_group_id']) || !ctype_digit($_POST['asset_group_id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    try {
        $deleted = delete_asset_group((int)$_POST['asset_group_id']);
    } catch(Exception $e) {
        error_log($e);
        $deleted = false;
    }

    if (!$deleted) {
        set_alert(true, "bad", $lang['ThereWasAProblemDeletingTheAssetGroup']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    
    set_alert(true, "good", $lang['AssetGroupDeletedSuccessfully']);
    json_response(200, get_alert(true), null);
}

function asset_group_remove_asset()
{
    global $lang;

    if (!check_permission("asset")) {
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (empty($_POST['asset_group_id']) || !ctype_digit($_POST['asset_group_id'])
            || empty($_POST['asset_id']) || !ctype_digit($_POST['asset_id'])) {
        set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
        json_response(400, get_alert(true), NULL);
        return;
    }

    try {
        $removed = remove_asset_from_asset_group((int)$_POST['asset_id'], (int)$_POST['asset_group_id']);
    } catch(Exception $e) {
        error_log($e);
        $removed = false;
    }

    if (!$removed) {
        set_alert(true, "bad", $lang['ThereWasAProblemRemovingTheAssetFromAssetGroup']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    
    set_alert(true, "good", $lang['AssetRemovedFromAssetGroupSuccessfully']);
    json_response(200, get_alert(true), null);
}

function get_asset_group_options() {

    if (check_permission("asset") || check_permission("assessments") || check_permission("riskmanagement")) {
        $risk_id = isset($_GET['risk_id']) && ctype_digit($_GET['risk_id']) ? (int)$_GET['risk_id'] : false;
        json_response(200, null, get_assets_and_asset_groups_for_dropdown($risk_id));
    } else {
        global $lang;

        set_alert(true, "bad", $lang['NoPermissionForAssetAssetGroupList']);
        json_response(400, get_alert(true), NULL);
        return;
    }
}

function get_asset_group_options_noauth() {

    if (get_setting("ASSESSMENT_ASSET_SHOW_AVAILABLE") && check_questionnaire_get_token()) {
        $risk_id = isset($_GET['risk_id']) && ctype_digit($_GET['risk_id']) ? (int)$_GET['risk_id'] : false;
        json_response(200, null, get_assets_and_asset_groups_for_dropdown($risk_id));
    } else {
        global $lang;

        set_alert(true, "bad", $lang['NoPermissionForAssetAssetGroupList']);
        json_response(400, get_alert(true), NULL);
        return;
    }
}

/***************************************
 * FUNCTION: GET MANAGER ID BY USER ID *
 ***************************************/
function getManagerByUserAPI()
{
    $user_id = get_param("get", 'id');
    $user = get_user_by_id($user_id);
    
    set_alert(true, "good", "success");
    
    json_response(200, get_alert(true), array("manager" => $user["manager"]));
}

/*************************************
 * FUNCTION: SAVE DYNAMIC SELECTIONS *
 *************************************/
function saveDynamicSelectionsForm()
{
    global $lang, $escaper;
    
    $type = get_param("post", "type");
    $name = get_param("post", "name");

    // If the id is not sent
    if (!$type || !$name)
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['ThereAreRequiredFields']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    
    // Check if this name already existing
    if(check_exisiting_dynamic_selection_name($_SESSION['uid'], $name))
    {
        set_alert(true, "bad", $lang['TheNameAlreadyExists']);
        json_response(400, get_alert(true), []);
    }
    else
    {
        $custom_display_settings = get_param("post", "columns");
        $custom_selection_settings = get_param("post", "selects");
        $custom_column_filters = get_param("post", "columnFilters");
        $id = save_dynamic_selections($type, $name, $custom_display_settings, $custom_selection_settings,$custom_column_filters);

        $saved_selection = get_dynamic_saved_selection($id);
        if ($saved_selection) {
            set_alert(true, "good", $lang['SavedSuccess']);
            json_response(200, get_alert(true), ['value' => $id, 'name' => $saved_selection['name'], 'type' => $saved_selection['type']]);
        }
    }
    set_alert(true, "bad", $lang['SelectionSaveFailed']);
    json_response(400, get_alert(true), []);
}

/**************************************
 * FUNCTION: DELETE DYNAMIC SELECTION *
 **************************************/
function deleteDynamicSelectionForm()
{
    global $lang, $escaper;
    
    $id = get_param("post", "id");

    // If the id is not sent
    if (!$id) {
        set_alert(true, "bad", $lang['ThereAreRequiredFields']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    // Get the selection data so we can check if the user has the permission to delete the saved selection
    $selection = get_dynamic_saved_selection($id);
    
    // Admins can access/manage all saved selections
    if($_SESSION['admin'] || $selection['user_id'] == $_SESSION['uid']) {

        delete_dynamic_selection($id);

        // Not returning the alert on purpose because the UI logic is refreshing the page and if we user get_alert() here
        // then it'll remove it from the session and won't be displayed after the reload
        set_alert(true, "good", $lang['DeletedSuccess']);
        json_response(200, null, null);
    }

    set_alert(true, "bad", $lang['NoPermissionForThisSelection']);
    json_response(400, get_alert(true), null);
}

/****************************************
 * FUNCTION: REPORTS - RISK APPETITE    *
 ****************************************/
function appetite_report_api()
{
    global $lang, $escaper;
    
    // If the type are not sent
    if (!isset($_GET['type'])) {
        json_response(400, $lang['YouNeedToSpecifyATypeParameter'], NULL);
    } else {
        $type = $_GET['type'];

        if (!in_array($type, ['in', 'out'])) {
            json_response(400, $lang['YouNeedToSpecifyATypeParameter'], NULL);
        } else {
            $start = (isset($_GET['start']) && $_GET['start']) ? (int)$_GET['start'] : 0;
            $length = (isset($_GET['length']) && $_GET['length']) ? (int)$_GET['length'] : 10;

            $draw = $escaper->escapeHtml($_GET['draw']);

            $orderColumn = (int)$_GET['order'][0]['column'];
            $orderDir = strtolower($_GET['order'][0]['dir']) == "asc" ? "asc" : "desc";
            $column_filters = [];
            for ( $i=0 ; $i<count($_GET['columns']) ; $i++ ) {
                if ( isset($_GET['columns'][$i]) && $_GET['columns'][$i]['searchable'] == "true" && $_GET['columns'][$i]['search']['value'] != '' ) {
                    $column_filters[$_GET['columns'][$i]['name']] = $_GET['columns'][$i]['search']['value'];
                }
            }

            // Query the risks
            $data = get_risks_by_appetite($type, $start, $length, $orderColumn, $orderDir, $column_filters);

            $rows = array();
            foreach($data['data'] as $risk){
                $rows[] = array(
                    "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($risk['id'])."</a>",
                    $escaper->escapeHtml($risk['subject']),
                    "<div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color ".$escaper->escapeHtml($risk['color'])."\" style=\"background-color:" . $escaper->escapeHtml($risk['color']) . "\"></span></div>",
                    "<div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['residual_risk']) . "<span class=\"risk-color ".$escaper->escapeHtml($risk['residual_color'])."\" style=\"background-color:" . $escaper->escapeHtml($risk['residual_color']) . "\"></span></div>"
                );
            }

            $result = array(
                'draw' => $draw,
                'data' => $rows,
                'recordsTotal' => $data['recordsTotal'],
                'recordsFiltered' => $data['recordsTotal'],
            );

            echo json_encode($result);
            exit;
        }
    }
}

/****************************************
 * FUNCTION: REPORTS - TEAMS AND USERS  *
 ****************************************/
function user_management_reports_api() {
    
    global $lang, $escaper;
    
    if (!is_admin()) {
        json_response(400, $lang['AdminPermissionRequired'], NULL);
    }elseif (!isset($_POST['type'])) {
        json_response(400, $lang['YouNeedToSpecifyATypeParameter'], NULL);
    } else {
        $type = $_POST['type'];
        
        if (!in_array($type, ['users_of_teams', 'teams_of_users', 'users_of_permissions', 'permissions_of_users', 'users_of_roles'])) {
            json_response(400, $lang['YouNeedToSpecifyATypeParameter'], NULL);
        } else {
            $start = (isset($_POST['start']) && $_POST['start']) ? (int)$_POST['start'] : 0;
            $length = (isset($_POST['length']) && $_POST['length']) ? (int)$_POST['length'] : 10;
            
            $draw = $escaper->escapeHtml($_POST['draw']);
            
            $orderColumn = (int)$_POST['order'][0]['column'];
            $orderDir = strtolower($_POST['order'][0]['dir']) == "asc" ? "asc" : "desc";

            // Sanitizing filter input data
            if (isset($_POST['columnFilters']) && $_POST['columnFilters'] && is_array($_POST['columnFilters'])) {
                
                $columnFilters = $_POST['columnFilters'];
                // $_POST['columnFilters'] is a multi-level associative array and have to sanitize the values in the inner array
                // So we're iterating through all the filters
                array_walk($columnFilters, function($filters, $key) {return array_map('intval', $filters);});
            } else {
                $columnFilters = [];
            }

            // These types require team separation to be activated
            if (in_array($type, ['users_of_teams', 'teams_of_users'])) {
                
                if (!team_separation_extra()) {
                    json_response(400, $lang['YouNeedTeamSeparationEnabled'], NULL);
                    return;
                }
                
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
                
                $results = get_user_management_reports_report_data_separation($type, 'normal', $start, $length, $orderColumn, $orderDir, $columnFilters);
            } else {
                // In this case we're doing the sorting and the paging from the PHP code as the column we can sort by is not the same as in the database
                // so sorting and paging gives wrong results if done by the query
                if ($type === 'users_of_permissions') {
                    $results = get_user_management_reports_report_data($type, 'normal', 0, -1, $orderColumn, $orderDir, $columnFilters);
                } else {
                    $results = get_user_management_reports_report_data($type, 'normal', $start, $length, $orderColumn, $orderDir, $columnFilters);
                }
            }

            $rows = array();
            foreach($results['data'] as $data) {
                if ($type === 'users_of_teams') {
                    $rows[] = array(
                        $escaper->escapeHtml($data['name']),
                        $escaper->escapeHtml($data['users'])
                    );
                } elseif ($type === 'teams_of_users') {
                    $rows[] = array(
                        $escaper->escapeHtml($data['name']),
                        $escaper->escapeHtml($data['username']),
                        $escaper->escapeHtml($lang[$data['status'] ? 'Enabled' : 'Disabled']),
                        $escaper->escapeHtml($data['teams'])
                    );
                } elseif ($type === "users_of_permissions") {
                    $rows[] = array(
                        $data['name'] ? $escaper->escapeHtml($data['name']) : "",
                        $escaper->escapeHtml($data['users'])
                    );
                } elseif ($type === "permissions_of_users") {
                    $rows[] = array(
                        $escaper->escapeHtml($data['name']),
                        $escaper->escapeHtml($data['username']),
                        $escaper->escapeHtml($lang[$data['status'] ? 'Enabled' : 'Disabled']),
                        $escaper->escapeHtml($data['permissions'])
                    );
                } elseif ($type === "users_of_roles") {
                    $rows[] = array(
                        $escaper->escapeHtml($data['name']),
                        $escaper->escapeHtml($data['users'])
                    );
                }
            }
            
            if ($type === 'users_of_permissions') {
                // Sorting
                $orderDir = strtoupper($orderDir);
                usort($rows, function($a, $b) use ($orderDir) {
                    $cmp = strcmp($a[0], $b[0]);
                    return $orderDir === 'ASC' ? $cmp : $cmp * -1;
                });
                
                //Paging
                if ($length != -1) { // if not all result is requested
                    $rows = array_slice($rows, $start, $length);
                }
            }

            $result = array(
                'draw' => $draw,
                'data' => $rows,
                'recordsTotal' => $results['recordsTotal'],
                'recordsFiltered' => $results['recordsTotal'],
            );
            
            echo json_encode($result);
            exit;
        }
    }
}

/************************************************************
 * FUNCTION: USER MANAGEMENT REPORTS - UNIQUE COLUMN DATA   *
 * Function to get the data for the column filters.         *
 * Only getting the items that could produce valid results. *
 ************************************************************/
function user_management_reports_unique_column_data_api() {
    
    global $lang, $escaper;

    if (!is_admin()) {
        json_response(400, $lang['AdminPermissionRequired'], NULL);
    }elseif (!isset($_GET['type'])) {
        json_response(400, $lang['YouNeedToSpecifyATypeParameter'], NULL);
    } else {
        $type = $_GET['type'];
        
        if (!in_array($type, ['users_of_teams', 'teams_of_users', 'users_of_permissions', 'permissions_of_users', 'users_of_roles'])) {
            json_response(400, $lang['YouNeedToSpecifyATypeParameter'], NULL);
        } else {
            
            // These types require team separation to be activated
            if (in_array($type, ['users_of_teams', 'teams_of_users'])) {
                
                if (!team_separation_extra()) {
                    json_response(400, $lang['YouNeedTeamSeparationEnabled'], NULL);
                    return;
                }
                
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
                
                // get the report data in 'full' mode that's returning the ids of the items
                $results = get_user_management_reports_report_data_separation($type, 'full', 0, -1, 0, 'asc', []);
            } else {
                // get the report data in 'full' mode that's returning the ids of the items
                $results = get_user_management_reports_report_data($type, 'full', 0, -1, 0, 'asc', []);
            }
            
            $unique_data = [];
            
            if ($type === 'users_of_teams') {
                $unique_keys['teams'] = [];
                $unique_keys['users'] = [];
                
                $unique_data['teams'] = [];
                $unique_data['users'] = [];
                
                foreach($results as $data){
                    
                    if ($data['value'] && !in_array($data['value'], $unique_keys['teams'])) {
                        $unique_data['teams'][] = array('value' => $escaper->escapeHtml($data['value']), 'text' => $escaper->escapeHtml($data['name']));
                        $unique_keys['teams'][] = $data['value'];
                    }
                    
                    foreach(json_decode($data['users'], true) as $user) {
                        if (!in_array($user['value'], $unique_keys['users'])) {
                            $unique_data['users'][] = array('value' => $escaper->escapeHtml($user['value']), 'text' => $escaper->escapeHtml($user['name']));
                            $unique_keys['users'][] = $user['value'];
                        }
                    }
                }
            } elseif ($type === "teams_of_users") {
                $unique_keys['users'] = [];
                $unique_keys['statuses'] = [];
                $unique_keys['teams'] = [];
                
                $unique_data['users'] = [];
                $unique_data['usernames'] = [];
                $unique_data['statuses'] = [];
                $unique_data['teams'] = [];
                
                foreach($results as $data){
                    
                    if (!in_array($data['value'], $unique_keys['users'])) {
                        $unique_data['users'][] = array('value' => $escaper->escapeHtml($data['value']), 'text' => $escaper->escapeHtml($data['name']));
                        $unique_data['usernames'][] = array('value' => $escaper->escapeHtml($data['value']), 'text' => $escaper->escapeHtml($data['username']));
                        $unique_keys['users'][] = $data['value'];
                    }
                    
                    if (!in_array($data['status'], $unique_keys['statuses'])) {
                        $unique_data['statuses'][] = array('value' => $escaper->escapeHtml($data['status']), 'text' => $escaper->escapeHtml($lang[$data['status'] ? 'Enabled' : 'Disabled']));
                        $unique_keys['statuses'][] = $data['status'];
                    }
                    
                    foreach(json_decode($data['teams'], true) as $team) {
                        if (!in_array($team['value'], $unique_keys['teams'])) {
                            $unique_data['teams'][] = array('value' => $escaper->escapeHtml($team['value']), 'text' => $escaper->escapeHtml($team['name']));
                            $unique_keys['teams'][] = $team['value'];
                        }
                    }
                }
            } elseif ($type === "users_of_permissions") {

                $unique_keys['permissions'] = [];
                $unique_keys['users'] = [];
                
                $unique_data['permissions'] = [];
                $unique_data['users'] = [];
                
                foreach($results as $data){
                    
                    if ($data['name'] && !in_array($data['name'], $unique_keys['permissions'])) {
                        $unique_data['permissions'][] = array('value' => $escaper->escapeHtml($data['id']), 'text' => $escaper->escapeHtml($data['name']));
                        $unique_keys['permissions'][] = $data['name'];
                    }
                    
                    foreach(json_decode($data['users'], true) as $user) {
                        if (!in_array($user['value'], $unique_keys['users'])) {
                            $unique_data['users'][] = array('value' => $escaper->escapeHtml($user['value']), 'text' => $escaper->escapeHtml($user['name']));
                            $unique_keys['users'][] = $user['value'];
                        }
                    }
                }
                
            } elseif ($type === "permissions_of_users") {
                
                $unique_keys['users'] = [];
                $unique_keys['statuses'] = [];
                $unique_keys['permissions'] = [];
                
                $unique_data['users'] = [];
                $unique_data['usernames'] = [];
                $unique_data['statuses'] = [];
                $unique_data['permissions'] = [];
                
                foreach($results as $data){
                    if (!in_array($data['value'], $unique_keys['users'])) {
                        $unique_data['users'][] = array('value' => $escaper->escapeHtml($data['value']), 'text' => $escaper->escapeHtml($data['name']));
                        $unique_data['usernames'][] = array('value' => $escaper->escapeHtml($data['value']), 'text' => $escaper->escapeHtml($data['username']));
                        $unique_keys['users'][] = $data['value'];
                    }
                    
                    if (!in_array($data['status'], $unique_keys['statuses'])) {
                        $unique_data['statuses'][] = array('value' => $escaper->escapeHtml($data['status']), 'text' => $escaper->escapeHtml($lang[$data['status'] ? 'Enabled' : 'Disabled']));
                        $unique_keys['statuses'][] = $data['status'];
                    }

                    foreach(json_decode($data['permissions'], true) as $permission) {
                        if (!in_array($permission['value'], $unique_keys['permissions'])) {
                            $unique_data['permissions'][] = array('value' => $escaper->escapeHtml($permission['value']), 'text' => $escaper->escapeHtml($permission['name']));
                            $unique_keys['permissions'][] = $permission['value'];
                        }
                    }
                }
            } elseif ($type === "users_of_roles") {
                
                $unique_keys['roles'] = [];
                $unique_keys['users'] = [];
                
                $unique_data['roles'] = [];
                $unique_data['users'] = [];
                
                foreach($results as $data){
                    
                    if ($data['name'] && !in_array($data['name'], $unique_keys['roles'])) {
                        $unique_data['roles'][] = array('value' => $escaper->escapeHtml($data['value']), 'text' => $escaper->escapeHtml($data['name']));
                        $unique_keys['roles'][] = $data['name'];
                    }
                    
                    foreach(json_decode($data['users'], true) as $user) {
                        if (!in_array($user['value'], $unique_keys['users'])) {
                            $unique_data['users'][] = array('value' => $escaper->escapeHtml($user['value']), 'text' => $escaper->escapeHtml($user['name']));
                            $unique_keys['users'][] = $user['value'];
                        }
                    }
                }
                
            }
            
            
            echo json_encode($unique_data);
            exit;
        }
    }
}

function one_click_upgrade() {
    
    // If the user is not an administrator
    if (!is_admin()) {
        unauthorized_access();
        return;
    }
    
    global $escaper, $lang;
    
    // If the upgrade extra exists
    if (file_exists(realpath(__DIR__ . '/../extras/upgrade/index.php'))) {
        // Require the upgrade extra file
        require_once(realpath(__DIR__ . '/../extras/upgrade/index.php'));

        // Checking if the Upgrade extra is already at a version that supports upgrade through its API
        $is_upgrade_mode_extra = function_exists('upgrade_download_extra');
        
        // To make sure the upgrade can finish on lower spec systems
        set_time_limit(600);
        
        header('Content-type: text/html; charset=utf-8');
        // Turn off output buffering
        ini_set('output_buffering', 'off');
        // Turn off PHP output compression
        ini_set('zlib.output_compression', false);
        // Implicitly flush the buffer(s)
        ini_set('implicit_flush', true);
        ob_implicit_flush(true);
        // Clear, and turn off output buffering
        while (ob_get_level() > 0) {
            // Get the curent level
            $level = ob_get_level();
            // End the buffering
            ob_end_clean();
            // If the current level has not changed, abort
            if (ob_get_level() == $level) break;
        }
        // Disable apache output buffering/compression
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
            apache_setenv('dont-vary', '1');
        }
        
        stream_write($lang['UpdateVersionCheck']);
        
        $current_app_version = current_version("app");
        $next_app_version = next_version($current_app_version);
        $db_version = current_version("db");
        
        $need_update_app = false;
        $need_update_db = false;
        $need_update_extras = false;

        // If the current version is not the latest
        if ($next_app_version != "") {
            stream_write(_lang('UpdateApplicationFilesOutOfDate',
                array(
                    'current' => $current_app_version,
                    'latest' => $next_app_version
                )
            ));
            $need_update_app = true;
        } else {
            stream_write($lang['UpdateApplicationFilesUpToDate']);
        }

        // If the app version is not the same as the database version
        if ($current_app_version != $db_version) {
            
            stream_write(_lang('UpdateDatabaseOutOfDate',
                array(
                    'app_version' => $current_app_version,
                    'db_version' => $db_version
                )
            ));
            $need_update_db = true;
        } elseif ($need_update_app && $next_app_version != $db_version) {
            // If the app is getting updated and not to the same version the db is on
            stream_write(_lang('UpdateDatabaseMustFollowAppVersion',
                array(
                    'app_version' => $next_app_version,
                    'db_version' => $db_version
                )
            ));
            $need_update_db = true;
        } else {
            stream_write($lang['UpdateDatabaseUpToDate']);
        }

        stream_write($lang['UpdateExtraVersionCheck']);
        $extra_upgrades = core_gather_extra_upgrades();

        if (count($extra_upgrades)) {
            stream_write(_lang('UpdateInstalledExtrasOutOfDate', array('extrasToUpdate' => implode(', ', $extra_upgrades))));
            $need_update_extras = true;
        } else {
            stream_write($lang['UpdateInstalledExtrasUpToDate']);
        }

        stream_write($lang['UpdateVersionCheckDone']);

        if (!($need_update_app || $need_update_db || $need_update_extras)) {
            stream_write($lang['UpdateNoUpdateRequired']);
            return;
        }

        stream_write($lang['BackupStart']);

        if ($is_upgrade_mode_extra) {
            
            // Do the app backup
            stream_write($lang['BackupStartApplication']);
            list($status, $result) = call_extra_api_functionality('upgrade', 'backup', 'app');
            
            // Check the results
            if ($status !== 200) {
                // Print the error message if the call failed for some reason and stop the upgrade
                stream_write_error($result['status_message']);
                stream_write_error($lang['BackupFailed']);
                return;
            }
            // Print the success message
            stream_write($result['status_message']);
            
            // Do the database backup
            stream_write($lang['BackupStartDatabase']);
            list($status, $result) = call_extra_api_functionality('upgrade', 'backup', 'db');
            
            // Check the results
            if ($status !== 200) {
                // Print the error message if the call failed for some reason and stop the upgrade
                stream_write_error($result['status_message']);
                stream_write_error($lang['BackupFailed']);
                return;
            }
            // Print the success message
            stream_write($result['status_message']);
        } else { // Using the core backup functionality
            if (!backup()) {
                stream_write_error($lang['BackupFailed']);
                return;
            }
        }
        
        stream_write($lang['UpdateStart']);
        
        if ($is_upgrade_mode_extra && $need_update_extras && in_array("upgrade", $extra_upgrades)) {
            
            stream_write(_lang('UpdateExtrasExtraUpdateStarted', array('extra' => 'upgrade')));
            // Do the `Upgrade` extra upgrade
            list($status, $result) = call_extra_api_functionality('upgrade', 'upgrade', 'app');
            
            // Check the results
            if ($status !== 200) {
                // Print the error message if the call failed for some reason and stop the upgrade
                stream_write_error($result['status_message']);
                return;
            }
            // Print the success message
            stream_write($result['status_message']);
        }

        if ($need_update_app) {
            if ($is_upgrade_mode_extra) {
                list($status, $result) = call_extra_api_functionality('upgrade', 'upgrade', 'core_app');
                
                // Check the results
                if ($status !== 200) {
                    // Print the error message if the call failed for some reason and stop the upgrade
                    stream_write_error($result['status_message']);
                    return;
                }
                // Print the success message
                stream_write($result['status_message']);
            } else {
                upgrade_application();
            }
        }

        if ($need_update_db) {
            if ($is_upgrade_mode_extra) {
                list($status, $result) = call_extra_api_functionality('upgrade', 'upgrade', 'core_db');

                // Check the results
                if ($status !== 200) {
                    // Print the error message if the call failed for some reason and stop the upgrade
                    // Parsing the database upgrade's message
                    if ($result && !empty($result['status_message'])) {
                        $messages = preg_split("/\s*<br\s*\/>\s*|\s*\\\\n\s*/", $result['status_message'], 0, PREG_SPLIT_NO_EMPTY);
                        $last = count($messages) - 1;
                        foreach($messages as $index => $message) {
                            if($index == $last) { // the last message is the actual error message
                                stream_write_error($message);
                            } else {
                                stream_write($message);
                            }
                        }
                    }
                    return;
                }

                // Parsing the database upgrade's message
                if ($result && !empty($result['status_message'])) {
                    $messages = preg_split("/\s*<br\s*\/>\s*|\s*\\\\n\s*/", $result['status_message'], 0, PREG_SPLIT_NO_EMPTY);
                    foreach($messages as $message) {
                        stream_write($message);
                    }
                }
            } else {
                require_once(realpath(__DIR__ . '/upgrade.php'));

                // Upgrade the database
                upgrade_database();

                // Convert tables to InnoDB
                convert_tables_to_innodb();

                // Convert tables to utf8_general_ci
                convert_tables_to_utf8();
            }
        }

        if ($need_update_extras) {
            if ($is_upgrade_mode_extra) {
                stream_write($lang['UpdateExtrasStarted']);
                foreach($extra_upgrades as $extra) {
                    if ($extra === 'upgrade') {
                        // The upgrade extra is already updated
                        continue;
                    }

                    stream_write(_lang('UpdateExtrasExtraUpdateStarted', array('extra' => $extra)));
                    // Do the extra upgrade
                    list($status, $result) = call_extra_api_functionality($extra, 'upgrade', 'app');

                    // Check the results
                    if ($status !== 200) {
                        // Print the error message if the call failed for some reason and stop the upgrade
                        stream_write_error($result['status_message']);
                        stream_write_error(_lang('UpdateExtrasUpdateExtraFailed', array('extra' => $extra)));
                        return;
                    }
                    // Print the success message
                    stream_write($result['status_message']);
                }
                stream_write($lang['UpdateExtrasSuccessful']);
            } else {
                core_upgrade_extras($extra_upgrades);
            }
        }

        stream_write($lang['UpdateSuccessful']);
    }
}
/****************************************************
 * FUNCTION: REPORTS - HIGH RISK                    *
 * The High Risk Report datatable's API function    *
 ****************************************************/
function high_risk_report_datatable() {

    global $escaper;

    $draw   = $escaper->escapeHtml($_POST['draw']);
    $score_used = isset($_GET['score_used']) && $_GET['score_used'] === 'residual' ? 'residual' : 'inherent';

    $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
    $length = $_POST['length'] ? (int)$_POST['length'] : 10;
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
    $orderColumnName = isset($_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
    $orderDir = isset($_POST['order'][0]['dir']) && strtoupper($_POST['order'][0]['dir']) === 'ASC'? "ASC" : 'DESC';
    $column_filters = [];
    for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
        if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
            $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
        }
    }

    switch ($orderColumnName) {
        case "management_review":
            // Sorted in PHP code
            $sort = false;
            break;

        case "mitigation_planned":
            $sort = "ORDER BY `rsk`.`mitigation_id` != 0 {$orderDir}, `rsk`.`id` ASC";
            break;

        case "id":
            $sort = "ORDER BY `rsk`.`id` {$orderDir}";
            break;

        case "risk_status":
            $sort = "ORDER BY `rsk`.`status` {$orderDir}, `rsk`.`id` ASC";
            break;

        case "subject":
            // If the encryption extra is enabled, sort by order_by_subject field
            if (encryption_extra()) {
                $sort = "ORDER BY `rsk`.`order_by_subject` {$orderDir}, `rsk`.`id` ASC";
            } else {
                $sort = "ORDER BY `rsk`.`subject` {$orderDir}, `rsk`.`id` ASC";
            }
            break;

        case "submission_date":
            $sort = "ORDER BY `rsk`.`submission_date` {$orderDir}, `rsk`.`id` ASC";
            break;

        case "score":
        default:
            $sort = "ORDER BY `score` {$orderDir}, `rsk`.`id` ASC";
            break;
    }

    // Open the database connection
    $db = db_open();

    // Get the high risk level
    $stmt = $db->prepare("SELECT value FROM `risk_levels` WHERE name = 'High'");
    $stmt->execute();
    $array = $stmt->fetch();
    $high = $array['value'];

    // Build the query parts related to whether we have separation enabled or not
    $separation_query_where = "";
    $separation_query_from = "";
    if (team_separation_extra()) {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $separation_query_where = " AND ". get_user_teams_query("rsk");
        $separation_query_from = "
            LEFT JOIN `risk_to_team` rtt ON `rsk`.`id` = `rtt`.`risk_id`
            LEFT JOIN `risk_to_additional_stakeholder` rtas ON `rsk`.`id` = `rtas`.`risk_id`
        ";
    }

    // If we're sorting in PHP($sort = false) or all the data is requested($length=-1)
    // then we're requesting all the data

    $filtering_where = "";
    $filtering_having = "";
    $select_score = "";

    if ($score_used=='inherent') {
        $select_score = "`scores`.`inherent_score` as score";
        $filtering_where = "AND `scoring`.`calculated_risk` >= :high";
        $filtering_having = "";
    } else {
        $select_score = "`scores`.`residual_score` as score";
        $filtering_where = "";
        $filtering_having = "HAVING `residual_score` >= :high";
    }
    $bind_params = [];
    $manual_column_filters = [];
    $having_query = "";
    foreach($column_filters as $name => $column_filter){
        if($name == "risk_status"){
            $separation_query_where .= " AND rsk.status LIKE :risk_status ";
            $bind_params[$name] = "%{$column_filter}%";
        } elseif($name == "score"){
            $separation_query_where .= " AND scoring.calculated_risk LIKE :score ";
            $bind_params[$name] = "%{$column_filter}%";
        } else {
            $manual_column_filters[$name] = $column_filter;
        }
    }
    $limit = $sort !== false && $length > 0 && !$manual_column_filters ? "LIMIT {$start}, {$length}" : "";

    // Assemble the final query
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS
            {$select_score},
            `scores`.`inherent_score`,
            `scores`.`residual_score`,
            `latest_review`.`next_review`,
            `rsk`.*
        FROM (
                SELECT
                    `rsk`.`id` as risk_id,
                    `scoring`.`calculated_risk` as inherent_score,
                    ROUND(`scoring`.`calculated_risk` - (`scoring`.`calculated_risk` * GREATEST(IFNULL(`mtg`.`mitigation_percent`,0), IFNULL(MAX(`ctrl`.`mitigation_percent`), 0)) / 100), 2) AS residual_score
                FROM `risk_scoring` scoring
                    JOIN `risks` rsk ON `scoring`.`id` = `rsk`.`id`
                    LEFT JOIN `mitigations` mtg ON `rsk`.`id` = `mtg`.`risk_id`
                    LEFT JOIN `mitigation_to_controls` mtc ON `mtg`.`id` = `mtc`.`mitigation_id`
                    LEFT JOIN `framework_controls` ctrl ON `mtc`.`control_id`=`ctrl`.`id` AND `ctrl`.`deleted`=0
                    {$separation_query_from}
                WHERE
                    `rsk`.`status` != 'Closed'
                    {$separation_query_where}
                    {$filtering_where}
                GROUP BY
                    `rsk`.`id`
                {$filtering_having}
            ) AS scores
            INNER JOIN `risks` rsk ON `scores`.`risk_id` = `rsk`.`id`
            LEFT JOIN (
                SELECT
                    c1.risk_id,
                    c1.next_review
                FROM
                    mgmt_reviews c1
                    RIGHT JOIN (
                        SELECT
                            risk_id,
                            MAX(submission_date) AS date
                        FROM
                            mgmt_reviews
                        GROUP BY
                            risk_id
                    ) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date
            ) latest_review ON `rsk`.`id` = latest_review.risk_id
        GROUP BY `rsk`.`id`
        {$sort}
        {$limit};
    ";

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":high", $high, PDO::PARAM_STR);
    foreach($bind_params as $name => $bind_param){
        $stmt->bindParam(":{$name}", $bind_param);
    }
    $stmt->execute();

    // Store the results in the array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get the result count
    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $rowCount = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    $risk_levels = get_risk_levels();
    $review_levels = get_review_levels();

    $next_review_date_uses = get_setting('next_review_date_uses');

    // If we're ordering by the 'management_review' column
    if ($sort === false && $orderColumnName === 'management_review') {
        // Calculate the 'management_review' values
        foreach($risks as &$risk) {
            $risk_level = get_risk_level_name_from_levels($risk[$next_review_date_uses == "ResidualRisk" ? 'residual_score' : 'inherent_score'], $risk_levels);
            $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);

            $risk['management_review'] = management_review($risk['id'], $risk['mgmt_review'], $next_review);
            $risk['management_review_text'] = management_review_text_only($risk['mgmt_review'], $next_review);
        }
        unset($risk);

        // Sorting by the management review text as the normal 'management_review' field contains html
        usort($risks, function($a, $b) use ($orderDir){
            // For identical management reviews we're sorting on the id, so the results' order is not changing
            if ($a['management_review_text'] === $b['management_review_text']) {
                return (int)$a['id'] - (int)$b['id'];
            }
            if($orderDir == "ASC") {
                return strcmp($a['management_review_text'], $b['management_review_text']);
            } else {
                return strcmp($b['management_review_text'], $a['management_review_text']);
            }
        });

        // // If not all the results are requested, cutting a piece of it
        // if($length > 0) {
        //     $risks = array_slice($risks, $start, $length);
        // }
    }

    // Assembling the response
    $datas = array();
    foreach($risks as $risk){

        $risk['id'] = (int)$risk['id'] + 1000;

        $color = get_risk_color_from_levels($risk['score'], $risk_levels);

        if (!isset($risk['management_review'])) {
            $risk_level = get_risk_level_name_from_levels($risk[$next_review_date_uses == "ResidualRisk" ? 'residual_score' : 'inherent_score'], $risk_levels);
            $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
            $risk['management_review'] = management_review($risk['id'], $risk['mgmt_review'], $next_review);
        }

        $subject = try_decrypt($risk['subject']);
        $submission_date = format_datetime($risk['submission_date'], "", "g:i A T");
        $mitigation_planned = planned_mitigation($risk['id'], $risk['mitigation_id']);
        $data = array(
            "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($risk['id'])."</a>",
            $escaper->escapeHtml($risk['status']),
            $escaper->escapeHtml($subject),
            "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['score']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>",
            $escaper->escapeHtml($submission_date),
            $mitigation_planned, // mitigation plan
            $risk['management_review'] // management review
        );
        $success = true;
        foreach($manual_column_filters as $column_name => $val){
            if($column_name == "id") {
                if( stripos($risk['id'], $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "subject") {
                if( stripos($subject, $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "submission_date") {
                if( stripos($submission_date, $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "mitigation_planned") {
                if( stripos(strip_tags($mitigation_planned), $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "management_review") {
                if( stripos(strip_tags($risk['management_review']), $val) === false ){
                    $success = false;
                    break;
                }
            }
        }

        if($success) $datas[] = $data;
    }
    if($manual_column_filters){
        $datas_by_page = [];
        if($length > 0)
        {
            for($i=$start; $i<count($datas) && $i<$start + $length; $i++){
                $datas_by_page[] = $datas[$i];
            }
        }
        else
        {
            $datas_by_page = $datas;
        }
        $rowCount = count($datas);
    } else {
        $datas_by_page = $datas;
    }

    $results = array(
        "draw" => $draw,
        "recordsTotal" => $rowCount,
        "recordsFiltered" => $rowCount,
        "data" => $datas_by_page
    );

    // Return a JSON response
    echo json_encode($results);
}

/****************************************
 *       FUNCTION: ADD NEW PROJECT      *
 ****************************************/
function add_project_api(){
    global $lang, $escaper;
    // check permission for project add 
    if(isset($_SESSION["add_projects"]) && $_SESSION["add_projects"] == 1){
        $name = isset($_POST['new_project'])?$_POST['new_project']:"";
        $exist = get_value_by_name("projects", $name);
        // Check if the project name is null
        if ($name == "")
        {
            $message = _lang('FieldRequired', array("field"=>"Project Name"));
            // Return a JSON response
            json_response(400, $escaper->escapeHtml($message), NULL);
        }
        // project name exist
        else if($exist)
        {
            // Return a JSON response
            json_response(400, $escaper->escapeHtml($lang['TheNameAlreadyExists']), NULL);
        }
        // Otherwise
        else
        {
            $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : "";
            if (!validate_date($due_date, get_default_date_format()))
            {
                $due_date = "0000-00-00";
            }
            // Otherwise, set the proper format for submitting to the database
            else
            {
                $due_date = get_standard_date_from_default_format($due_date);
            }
            $project = array(
                'name' => $name,
                'due_date' => $due_date,
                'consultant' => isset($_POST['consultant']) ? (int)$_POST['consultant'] : 0,
                'business_owner' => isset($_POST['business_owner']) ? (int)$_POST['business_owner'] : 0,
                'data_classification' => isset($_POST['data_classification']) ? (int)$_POST['data_classification'] : 0,
            );
            // Insert a new project
            $new_project_id = add_project($project);
            // If customization extra is enabled
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                // If there is error in saving custom project values, delete added project and return false
                if(!save_custom_field_values($new_project_id, "project"))
                {
                    // Delete just inserted project
                    delete_value("projects", $new_project_id);
                    set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidParams']));
                }
            }

            set_alert(true, "good", $escaper->escapeHtml($lang['AddedSuccess']));
            json_response(200, get_alert(true), NULL);
        }
    } else {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForThisAction']), NULL);
    }
}
/****************************************
 *   FUNCTION: EDIT PROJECT NAME        *
 ****************************************/
function edit_project_api(){
    global $lang, $escaper;
    $value = (int)$_POST['project_id'];
    // check permission for project add 
    if(isset($_SESSION["add_projects"]) && $_SESSION["add_projects"] == 1){
        $name = isset($_POST['name'])?$_POST['name']:"";
        $exist = get_value_by_name("projects", $name);
        // Check if the project name is null
        if ($name == "")
        {
            $message = _lang('FieldRequired', array("field"=>"Project Name"));
            // Return a JSON response
            json_response(400, $escaper->escapeHtml($message), NULL);
        }
        // project name exist
        else if($exist && $value != $exist)
        {
            // Return a JSON response
            json_response(400, $escaper->escapeHtml($lang['TheNameAlreadyExists']), NULL);
        }
        // Otherwise
        else
        {
            $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : "";
            if (!validate_date($due_date, get_default_date_format()))
            {
                $due_date = "0000-00-00";
            }
            // Otherwise, set the proper format for submitting to the database
            else
            {
                $due_date = get_standard_date_from_default_format($due_date);
            }
            $project = array(
                'name' => $name,
                'due_date' => $due_date,
                'consultant' => isset($_POST['consultant']) ? (int)$_POST['consultant'] : 0,
                'business_owner' => isset($_POST['business_owner']) ? (int)$_POST['business_owner'] : 0,
                'data_classification' => isset($_POST['data_classification']) ? (int)$_POST['data_classification'] : 0,
            );
            update_project($value, $project);
            // If customization extra is enabled
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                save_custom_field_values($value, "project");
            }

            set_alert(true, "good", $escaper->escapeHtml($lang['UpdatedSuccess']));
            json_response(200, get_alert(true), NULL);
        }
    } else {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForThisAction']), NULL);
    }
}
/****************************************
 *   FUNCTION: DETAIL PROJECT NAME        *
 ****************************************/
function detail_project_api(){
    global $lang, $escaper;
    $value = (int)$_GET['project_id'];
    // check permission for project add 
    if(isset($_SESSION["add_projects"]) && $_SESSION["add_projects"] == 1){
        $result = get_project($value);
        $result['name'] = try_decrypt($result['name']);
        $result['due_date'] = format_date($result['due_date']);
        json_response(200, "Get project by ID", $result);
    } else {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForThisAction']), NULL);
    }
}
/****************************************
 *       FUNCTION: DELETE PROJECT       *
 ****************************************/
function delete_project_api(){
    global $lang, $escaper;
    $value = (int)$_POST['project_id'];

    // check permission for project delete 
    if(isset($_SESSION["delete_projects"]) && $_SESSION["delete_projects"] == 1){
        // Verify value is an integer
        if (is_int($value))
        {
            // If the project ID is 0 (ie. Unassigned Risks)
            if ($value == 0)
            {
                // Display an alert
                //set_alert(true, "bad", "You cannot delete the Unassigned Risks project or we will have no place to put unassigned risks.  Sorry.");
                // Return a JSON response
                json_response(400, "You cannot delete the Unassigned Risks project or we will have no place to put unassigned risks.  Sorry.", NULL);
            }
            else
            {
                // Get the risks associated with the project
                $risks = get_project_risks($value);

                // For each associated risk
                foreach ($risks as $risk)
                {
                    // Set the project ID for the risk to unassigned (0)
                    update_risk_project(0, $risk['id']);
                }

                // Delete the project
                delete_value("projects", $value);

                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                    // delete custom project data
                    delete_custom_data_by_row_id($value, "project");
                }

                // Display an alert
                set_alert(true, "good", "An existing project was deleted successfully.");
                json_response(200, get_alert(true), NULL);
            }
        }
        // We should never get here as we bound the variable as an int
        else
        {
            // Display an alert
            //set_alert(true, "bad", "The project ID was not a valid value.  Please try again.");
            // Return a JSON response
            json_response(400, "The project ID was not a valid value.  Please try again.", NULL);
        }
    } else {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForThisAction']), NULL);
    }
}
/****************************************
 *       FUNCTION: UPDATE PROJECT       *
 ****************************************/
function update_project_api(){
    global $lang, $escaper;
    // check permission for project add 
    if(isset($_SESSION["manage_projects"]) && $_SESSION["manage_projects"] == 1){
        if (isset($_POST['risk_id']))
        {
            $risk_id = $_POST['risk_id'];
            $project_id = $_POST['project_id'];
            update_risk_project($project_id, $risk_id);  
            // Display an alert
            set_alert(true, "good", "The risks were saved successfully to the projects.");
            json_response(200, get_alert(true), NULL);
        } else {
            $message = _lang('FieldRequired', array("field"=>"Risk ID"));
            // Return a JSON response
            json_response(400, $escaper->escapeHtml($message), NULL);
        }
    } else {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForThisAction']), NULL);
    }
}
/**********************************************
 *       FUNCTION: UPDATE PROJECT STATUS      *
 *********************************************/
function update_project_status_api(){
    global $lang, $escaper;
    // check permission for project add 
    if(isset($_SESSION["manage_projects"]) && $_SESSION["manage_projects"] == 1){
        if (isset($_POST['status'])&&isset($_POST['project_id']))
        {
            $status_id  = $_POST['status'];
            $project_id = $_POST['project_id'];
            update_project_status($status_id, $project_id);

            if ($status_id == 3)
            {
              // Close the risks associated with the project
              completed_project($project_id);
            }
            // Otherwise
            else
            {
              // Reopen the risks associated with the project
              incomplete_project($project_id);
            }
            // Display an alert
            set_alert(true, "good", "The project statuses were successfully updated.");
            json_response(200, get_alert(true), NULL);
        } else {
            $message = _lang('FieldRequired', array("field"=>"Project ID"));
            // Return a JSON response
            json_response(400, $escaper->escapeHtml($message), NULL);
        }
    } else {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForThisAction']), NULL);
    }
}

/**********************************************
 *       FUNCTION: UPDATE PROJECT ORDER       *
 *********************************************/
function update_project_order_api(){
    global $lang, $escaper;
    // check permission for project add 
    if(isset($_SESSION["manage_projects"]) && $_SESSION["manage_projects"] == 1){
        if (isset($_POST['project_ids']))
        {
            $ids = $_POST['project_ids'];
            update_project_priority($ids);

            // Display an alert
            set_alert(true, "good", "The project order was updated successfully.");
            json_response(200, get_alert(true), NULL);
        } else {
            $message = _lang('FieldRequired', array("field"=>"Project IDs"));
            // Return a JSON response
            json_response(400, $escaper->escapeHtml($message), NULL);
        }
    } else {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForThisAction']), NULL);
    }
}

/****************************************
 * FUNCTION: GET RISK CATALOG DATATABLE *
 ****************************************/
function getRiskCatalogDatatableAPI() {
    global $escaper;

    if (is_admin()) {
        $risk_catalogs = get_risk_catalogs();
        $rows = array();
        foreach($risk_catalogs as $risk){
            $rows[] = array(
                "DT_RowId" => $risk['id'],
                "order" => $risk['order'],
                "group_id" => $risk['group_id'],
                "group_order" => $risk['group_order'],
                "group_name" => $escaper->escapeHtml($risk['group_name']),
                "number" => $escaper->escapeHtml($risk['number']),
                "name" => $escaper->escapeHtml($risk['name']),
                "description" => $escaper->escapeHtml($risk['description']),
                "function_name" => $escaper->escapeHtml($risk['function_name']),
                "actions" => "<a href='javascript:void();' class='edit_risk_catalog' data-id='".$risk['id']."' style='display:inline;'><i class='fa fa-edit'></i></a>&nbsp;&nbsp;&nbsp;<a href='javascript:void();' class='delete_risk_catalog' data-id='".$risk['id']."' style='display:inline;'><i class='fa fa-trash'></i></a>",
            );
        }
        $draw = $escaper->escapeHtml($_GET['draw']);
        $result = array(
            'draw' => $draw,
            'data' => $rows,
            'recordsTotal' => count($risk_catalogs),
            'recordsFiltered' => count($risk_catalogs),
        );
        echo json_encode($result);
        exit;
    }
    else
    {
        unauthorized_access();
    }
}

/******************************************
 * FUNCTION: GET THREAT CATALOG DATATABLE *
 ******************************************/
function getThreatCatalogDatatableAPI() {
    global $escaper;

    if (is_admin()) {
        $threat_catalogs = get_threat_catalogs();
        $rows = array();
        foreach($threat_catalogs as $threat){
            $rows[] = array(
                "DT_RowId" => $threat['id'],
                "order" => $threat['order'],
                "group_id" => $threat['group_id'],
                "group_order" => $threat['group_order'],
                "group_name" => $escaper->escapeHtml($threat['group_name']),
                "number" => $escaper->escapeHtml($threat['number']),
                "name" => $escaper->escapeHtml($threat['name']),
                "description" => $escaper->escapeHtml($threat['description']),
                "actions" => "<a href='javascript:void();' class='edit_threat_catalog' data-id='".$threat['id']."' style='display:inline;'><i class='fa fa-edit'></i></a>&nbsp;&nbsp;&nbsp;<a href='javascript:void();' class='delete_threat_catalog' data-id='".$threat['id']."' style='display:inline;'><i class='fa fa-trash'></i></a>",
            );
        }
        $draw = $escaper->escapeHtml($_GET['draw']);
        $result = array(
            'draw' => $draw,
            'data' => $rows,
            'recordsTotal' => count($threat_catalogs),
            'recordsFiltered' => count($threat_catalogs),
        );
        echo json_encode($result);
        exit;
    } else {
        unauthorized_access();
    }
}
/****************************************************
 * FUNCTION: SWAP RISK/THREAT CATALOG GROUP ORDERS  *
 ****************************************************/
function swapGroupCatalogAPI() {
    global $lang, $escaper;

    if (is_admin()) {
        $type = !empty($_POST['type']) && in_array($_POST['type'], ['risk', 'threat']) ? $_POST['type'] : false;
        if (!$type) {
            set_alert(true, "bad", $lang['YouNeedToSpecifyATypeParameter']);
            json_response(400, get_alert(true), NULL);
        }

        $group1_id = (int)$_POST['group1_id'];
        $group2_id = (int)$_POST['group2_id'];

        if (!$group1_id || !$group2_id || $group1_id === $group2_id) {
            set_alert(true, "bad", $lang['InvalidGroups']);
            json_response(400, get_alert(true), NULL);
        }

        $db = db_open();

        // Get the order values from group 1 and 2
        // I'd rather not use orders coming from the UI
        $stmt = $db->prepare("
            SELECT `order` FROM `{$type}_grouping` WHERE `value` = :group_id;
        ");
        $stmt->bindParam(":group_id", $group1_id, PDO::PARAM_INT);
        $stmt->execute();
        $group1_order = $stmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT `order` FROM `{$type}_grouping` WHERE `value` = :group_id;
        ");
        $stmt->bindParam(":group_id", $group2_id, PDO::PARAM_INT);
        $stmt->execute();
        $group2_order = $stmt->fetchColumn();

        // Update the orders based on the orders got from the DB
        $stmt = $db->prepare("
            UPDATE
                `{$type}_grouping`
            SET `order` = CASE
                    WHEN (`order` = :group1) THEN :group2
                    WHEN (`order` = :group2) THEN :group1
                END
            WHERE
                `order` IN (:group1, :group2);
        ");
        $stmt->bindParam(":group1", $group1_order, PDO::PARAM_INT);
        $stmt->bindParam(":group2", $group2_order, PDO::PARAM_INT);
        $stmt->execute();

        db_close($db);

        set_alert(true, "good", $lang['OrderUpdatedSuccessfully']);
        json_response(200, get_alert(true), NULL);
    } else {
        unauthorized_access();
    }
}

/****************************************
 * FUNCTION: GET RISK CATALOG DETAIL    *
 ****************************************/
function getRiskCatalogAPI()
{
    global $lang, $escaper;

    if (is_admin())
    {
        if (isset($_GET['risk_id']))
        {
            $id = $_GET['risk_id'];
            $result = get_risk_catalog($id);
            // Display an alert
            json_response(200, "Get risk catalog by ID", ["risk" => $result]);
        }
    }
    else
    {
        unauthorized_access();
    }
}

/***************************************
 * FUNCTION: GET THREAT CATALOG DETAIL *
 ***************************************/
function getThreatCatalogAPI()
{
    global $lang, $escaper;

    if (is_admin())
    {
        if (isset($_GET['threat_id']))
        {
            $id = $_GET['threat_id'];
            $result = get_threat_catalog($id);
            // Display an alert
            json_response(200, "Get threat catalog by ID", ["threat" => $result]);
        }
    }
    else
    {
        unauthorized_access();
    }
}

/****************************************
 * FUNCTION: UPDATE RISK CATALOG ORDER  *
 ****************************************/
function updateRiskCatalogOrderAPI()
{
    global $lang, $escaper;

    if (is_admin())
    {
        if (isset($_POST['orders']))
        {
            $orders = $_POST['orders'];
            update_risk_catalog_order($orders);
            // Display an alert
            set_alert(true, "good", $lang['OrderUpdatedSuccessfully']);
            json_response(200, get_alert(true), NULL);
        }

        return true;
    }
    else
    {
        unauthorized_access();
    }
}

/******************************************
 * FUNCTION: UPDATE THREAT CATALOG ORDER  *
 ******************************************/
function updateThreatCatalogOrderAPI()
{
    global $lang, $escaper;

    if (is_admin())
    {
        if (isset($_POST['orders']))
        {
            $orders = $_POST['orders'];
            update_threat_catalog_order($orders);
            // Display an alert
            set_alert(true, "good", $lang['OrderUpdatedSuccessfully']);
            json_response(200, get_alert(true), NULL);
        }

        return true;
    }
    else
    {
        unauthorized_access();
    }
}

/******************************
 * FUNCTION: ADD RISK CATALOG *
 ******************************/
function addRiskCatalogAPI()
{
    global $lang, $escaper;
    if (is_admin())
    {
        $data = array(
            "number" => isset($_POST['number']) ? $_POST['number'] : "",
            "grouping" => isset($_POST['risk_grouping']) ? $_POST['risk_grouping'] : 0,
            "name" => isset($_POST['name']) ? $_POST['name'] : "",
            "description" => isset($_POST['description']) ? $_POST['description'] : "",
            "function" => isset($_POST['risk_function']) ? $_POST['risk_function'] : 0,
        );
        if (!$data["number"])
        {
            // Display an alert
            set_alert(true, "bad", "The risk name cannot be empty.");
        } else {
            add_risk_catalog($data);
            // Display an alert
            set_alert(true, "good", "A new risk catalog item was added successfully.");
        }
        json_response(200, get_alert(true), NULL);
        return true;
    }
    else
    {
        unauthorized_access();
    }
}

/********************************
 * FUNCTION: ADD THREAT CATALOG *
 ********************************/
function addThreatCatalogAPI()
{
    global $lang, $escaper;
    if (is_admin())
    {
        $data = array(
            "number" => isset($_POST['number']) ? $_POST['number'] : "",
            "grouping" => isset($_POST['threat_grouping']) ? $_POST['threat_grouping'] : 0,
            "name" => isset($_POST['name']) ? $_POST['name'] : "",
            "description" => isset($_POST['description']) ? $_POST['description'] : "",
        );
        if (!$data["number"])
        {
            // Display an alert
            set_alert(true, "bad", "The threat name cannot be empty.");
        } else {
            add_threat_catalog($data);
            // Display an alert
            set_alert(true, "good", "A new threat catalog item was added successfully.");
        }
        json_response(200, get_alert(true), NULL);
        return true;
    }
    else
    {
        unauthorized_access();
    }
}

/*********************************
 * FUNCTION: UPDATE RISK CATALOG *
 *********************************/
function updateRiskCatalogAPI()
{
    global $lang, $escaper;
    if (is_admin())
    {
        $data = array(
            "id" => isset($_POST['id']) ? $_POST['id'] : "",
            "number" => isset($_POST['number']) ? $_POST['number'] : "",
            "grouping" => isset($_POST['risk_grouping']) ? $_POST['risk_grouping'] : 0,
            "name" => isset($_POST['name']) ? $_POST['name'] : "",
            "description" => isset($_POST['description']) ? $_POST['description'] : "",
            "function" => isset($_POST['risk_function']) ? $_POST['risk_function'] : 0,
        );
        if (!$data["id"])
        {
            // Display an alert
            set_alert(true, "bad", "The data ID was not a valid value.  Please try again.");
        } else {
            update_risk_catalog($data);
            // Display an alert
            set_alert(true, "good", "An existing risk catalog item was updated successfully.");
        }
        json_response(200, get_alert(true), NULL);
        return true;
    }
    else
    {
        unauthorized_access();
    }
}

/***********************************
 * FUNCTION: UPDATE THREAT CATALOG *
 ***********************************/
function updateThreatCatalogAPI()
{
    global $lang, $escaper;
    if (is_admin())
    {
        $data = array(
            "id" => isset($_POST['id']) ? $_POST['id'] : "",
            "number" => isset($_POST['number']) ? $_POST['number'] : "",
            "grouping" => isset($_POST['threat_grouping']) ? $_POST['threat_grouping'] : 0,
            "name" => isset($_POST['name']) ? $_POST['name'] : "",
            "description" => isset($_POST['description']) ? $_POST['description'] : "",
        );
        if (!$data["id"])
        {
            // Display an alert
            set_alert(true, "bad", "The data ID was not a valid value.  Please try again.");
        } else {
            update_threat_catalog($data);
            // Display an alert
            set_alert(true, "good", "An existing threat catalog item was updated successfully.");
        }
        json_response(200, get_alert(true), NULL);
        return true;
    }
    else
    {
        unauthorized_access();
    }
}

/*********************************
 * FUNCTION: DELETE RISK CATALOG *
 *********************************/
function deleteRiskCatalogAPI()
{
    global $lang, $escaper;
    if (is_admin())
    {
        $id = isset($_POST['id']) ? $_POST['id'] : "";
        if (!$id)
        {
            // Display an alert
            set_alert(true, "bad", "The data ID was not a valid value.  Please try again.");
        } else {
            delete_risk_catalog($id);
            // Display an alert
            set_alert(true, "good", "An existing risk catalog item was deleted successfully.");
        }
        json_response(200, get_alert(true), NULL);
        return true;
    }
    else
    {
        unauthorized_access();
    }

}

/***********************************
 * FUNCTION: DELETE THREAT CATALOG *
 ***********************************/
function deleteThreatCatalogAPI()
{
    global $lang, $escaper;
    if (is_admin())
    {
        $id = isset($_POST['id']) ? $_POST['id'] : "";
        if (!$id)
        {
            // Display an alert
            set_alert(true, "bad", "The data ID was not a valid value.  Please try again.");
        } else {
            delete_threat_catalog($id);
            // Display an alert
            set_alert(true, "good", "An existing risk catalog item was deleted successfully.");
        }
        json_response(200, get_alert(true), NULL);
        return true;
    }
    else
    {
        unauthorized_access();
    }

}

/**********************************************
 * FUNCTION: SAVE CUSTOM DISPLAY SETTINGS API *
 *********************************************/
function saveCustomPlanMitigationDisplaySettingsAPI(){
    global $escaper, $lang;
    if (!check_permission("riskmanagement")){
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }
    if(isset($_POST["risk_columns"]) && isset($_POST["mitigation_columns"]) && isset($_POST["review_columns"])){
        $data = array(
            "risk_colums" => $_POST["risk_columns"],
            "mitigation_colums" => $_POST["mitigation_columns"],
            "review_colums" => $_POST["review_columns"],
        );
        save_custom_risk_display_settings("custom_plan_mitigation_display_settings", $data);
        set_alert(true, "good", $lang['SavedSuccess']);
        json_response(200, get_alert(true), null);
    } else {
        set_alert(true, "bad", $lang['NoDataAvailable']);
        json_response(400, get_alert(true), NULL);
    }
    return;
}
function saveCustomPerformReviewsDisplaySettingsAPI(){
    global $escaper, $lang;
    if (!check_permission("riskmanagement")){
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }
    if(isset($_POST["risk_columns"]) && isset($_POST["mitigation_columns"]) && isset($_POST["review_columns"])){
        $data = array(
            "risk_colums" => $_POST["risk_columns"],
            "mitigation_colums" => $_POST["mitigation_columns"],
            "review_colums" => $_POST["review_columns"],
        );
        save_custom_risk_display_settings("custom_perform_reviews_display_settings", $data);
        set_alert(true, "good", $lang['SavedSuccess']);
        json_response(200, get_alert(true), null);
    } else {
        set_alert(true, "bad", $lang['NoDataAvailable']);
        json_response(400, get_alert(true), NULL);
    }
    return;
}
function saveCustomReviewregularlyDisplaySettingsAPI(){
    global $escaper, $lang;
    if (!check_permission("riskmanagement")){
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }
    if(isset($_POST["risk_columns"]) && isset($_POST["mitigation_columns"]) && isset($_POST["review_columns"])){
        $data = array(
            "risk_colums" => $_POST["risk_columns"],
            "mitigation_colums" => $_POST["mitigation_columns"],
            "review_colums" => $_POST["review_columns"],
        );
        save_custom_risk_display_settings("custom_reviewregularly_display_settings", $data);
        set_alert(true, "good", $lang['SavedSuccess']);
        json_response(200, get_alert(true), null);
    } else {
        set_alert(true, "bad", $lang['NoDataAvailable']);
        json_response(400, get_alert(true), NULL);
    }
    return;
}

/***********************************************************************************
 * NEXT SECTION CONTAINS FUNCTIONS DEDICATED TO FIXING FILE UPLOAD ENCODING ISSUES *
 ***********************************************************************************/
function getFilesWithEncodingIssuesDatatableResponse() {

    if (is_admin()) {
        global $lang;
        global $escaper;
        
        $draw = (int)$_GET['draw'];
        
        $order_column = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
        $order_dir = $escaper->escapeHtml($_GET['order'][0]['dir']) == "asc" ? "asc" : "desc";
        $offset = (int)$_GET['start'];
        $page_size = (int)$_GET['length'];
        
        $type = isset($_GET['type']) && in_array($_GET['type'], ['risk', 'compliance', 'questionnaire']) ? $_GET['type'] : 'risk';

        list($recordsTotal, $fileList) = get_files_with_encoding_issues($type, $order_column, $order_dir, $offset, $page_size);
        
        $data = array();
        
        foreach ($fileList as $file) {
            $file_name = $file['file_name'];
            $unique_name = $file['unique_name'];
            
            $row = [];
            switch ($type) {
                case 'risk':
                    $row['id'] = "<div class='open-risk'><a target=\"_blank\" href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($file['risk_id'])) . "\">" . $escaper->escapeHtml(convert_id($file['risk_id'])) . "</a></div>";
                    $row['subject'] = $escaper->escapeHtml(try_decrypt($file['subject']));
                    $row['view_type'] = $escaper->escapeHtml($lang[(int)$file['view_type'] === 1 ? 'Risk' : 'Mitigation']);
                break;
                case 'compliance':
                    
                    if ($file['ref_type'] === 'test_audit') {
                        
                        $closed = ((int)$file['status'] === (int)get_setting("closed_audit_status"));

                        $row['name'] = "<a target='_blank' href='../compliance/" . ($closed ? 'view_test' : 'testing') . ".php?id=" . $escaper->escapeHtml($file['id']) . "'>" . $escaper->escapeHtml($file['name']) . "</a>";
                    } else {
                        $row['name'] = $escaper->escapeHtml($file['name']);
                    }
                    
                    
                    $row['ref_type'] = $escaper->escapeHtml($lang['ref_type_' . $file['ref_type']]);
                break;
                case 'questionnaire':

                    $row['name'] = "<a target='_blank' href='../assessments/questionnaire_results.php?action=full_view&token=" . $escaper->escapeHtml($file['token']) . "'>" . $escaper->escapeHtml($file['name']) . "</a>";

                    $row['type'] = $escaper->escapeHtml($lang[$file['type']]);
                break;
            }

            $uploader = "
                <div class='file-uploader'>
                    <input type='text' class='form-control readonly' style='width: 50%; margin-bottom: 0px; cursor: default; padding: 2px 10px; height: 90%;'/>
                    <label for='file-upload-{$unique_name}' class='btn' style='padding: 2px 15px;'>" . $escaper->escapeHtml($lang['ChooseFile']) . "</label>
                    <span class='file-size'>
                        <label for=''></label>
                    </span>
                    <input type='file' id='file-upload-{$unique_name}' name='file' class='hidden-file-upload active' />
                </div>";
            
            
            $row['file_name'] = $escaper->escapeHtml($file_name);
            $row['file_uploader'] = $uploader;
            $row['unique_name'] = $unique_name;
            
            $data[] = $row;
            
        }
        $result = array(
            'draw' => $draw,
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
        );
        echo json_encode($result);
        exit;
    } else {
        unauthorized_access();
    }
}

function uploadFileToFixFileEncodingIssue() {

    // If the user is an administrator and the upload is EXACTLY one file
    if (is_admin() && !empty($_FILES) && count($_FILES) === 1) {

        global $lang, $escaper;

        $type = isset($_POST['type']) && in_array($_POST['type'], ['risk', 'compliance', 'questionnaire']) ? $_POST['type'] : false;

        if (!$type) {
            set_alert(true, "bad", $lang['YouNeedToSpecifyATypeParameter']);
            json_response(400, get_alert(true), NULL);
        }

        // If the user wants to upload a `questionnaire` type file, check if the assessment extra file exists
        if ($type === 'questionnaire' && file_exists(realpath(__DIR__ . '/../extras/assessments/index.php'))) {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
        } else {
            set_alert(true, "bad", $lang['NoPermissionForAssessments']);
            json_response(400, get_alert(true), NULL);
        }

        $unique_name = $_POST['unique_name'];

        $file_info = get_encoding_issue_file_info($type, $unique_name);

        if (!$file_info) {
            set_alert(true, "bad", $lang['InvalidUniqueName']);
            json_response(400, get_alert(true), NULL);
        }

        switch($type) {
            case 'risk':
                $error = upload_file($file_info['risk_id'], $_FILES['file'], $file_info['view_type']);
                if ($error === 1) {
                    delete_db_file($unique_name);
                } else {
                    json_response(400, $escaper->escapeHtml($error), NULL);
                }
            break;
            case 'compliance':
                $files = array(
                    'name' => [$_FILES['file']['name']],
                    'type' => [$_FILES['file']['type']],
                    'tmp_name' => [$_FILES['file']['tmp_name']],
                    'size' => [$_FILES['file']['size']],
                    'error' => [$_FILES['file']['error']]
                );

                if ($file_info['ref_type'] === 'test_audit') {

                    list($status, $_, $errors) = upload_compliance_files($file_info['ref_id'], "test_audit", $files);

                    if($status){
                        delete_compliance_file($file_info['id']);
                    } else {
                        json_response(400, $escaper->escapeHtml($errors[0]), NULL);
                        return;
                    }
                } elseif ($file_info['ref_type'] === 'exceptions') {

                    list($status, $file_ids, $errors) = upload_compliance_files($file_info['ref_id'], "exceptions", $files);

                    if (!$status) {
                        json_response(400, $escaper->escapeHtml($errors[0]), NULL);
                        return;
                    } else {

                        $db = db_open();

                        $stmt = $db->prepare("UPDATE `document_exceptions` SET file_id=:file_id WHERE value=:id");
                        $stmt->bindParam(":file_id", $file_ids[0], PDO::PARAM_INT);
                        $stmt->bindParam(":id", $file_info['ref_id'], PDO::PARAM_INT);
                        $stmt->execute();

                        db_close($db);

                        delete_compliance_file($file_info['id']);
                    }
                } elseif ($file_info['ref_type'] === 'documents') {

                    list($status, $file_ids, $errors) = upload_compliance_files($file_info['ref_id'], "documents", $files, $file_info['version']);

                    if (!$status) {
                        json_response(400, $escaper->escapeHtml($errors[0]), NULL);
                        return;
                    } else {
                        // Open the database connection
                        $db = db_open();

                        $stmt = $db->prepare("UPDATE `documents` SET file_id=:file_id WHERE id=:id");
                        $stmt->bindParam(":file_id", $file_ids[0], PDO::PARAM_INT);
                        $stmt->bindParam(":id", $file_info['ref_id'], PDO::PARAM_INT);
                        $stmt->execute();

                        db_close($db);

                        delete_compliance_file($file_info['id']);
                    }
                }
            break;
            case 'questionnaire':
                $files = array(
                    'name' => [$_FILES['file']['name']],
                    'type' => [$_FILES['file']['type']],
                    'tmp_name' => [$_FILES['file']['tmp_name']],
                    'size' => [$_FILES['file']['size']],
                    'error' => [$_FILES['file']['error']]
                );

                // It's ok to use the same logic for files attached to the answer or the questionnaire as in case of the file attached to the questionnaire
                // the files `template_id`, `question_id` and `parent_question_id` will be 0 anyway(this is the default what's used whe those parameters aren't present)
                $result = upload_questionnaire_files($file_info['tracking_id'], $files, $file_info['template_id'], $file_info['question_id'], $file_info['parent_question_id']);

                // Check if there was an error
                if($result !== true && is_array($result)){
                    json_response(400, $escaper->escapeHtml($result[0]), NULL);
                    return;
                } else { // Delete the original file if everything went well with the upload
                    delete_assessment_file($file_info['id']);
                }
            break;
        }

    } else {
        unauthorized_access();
    }
}
/*****************************************************
 * FUNCTION: REPORTS - All Open Risks Assigned to Me *
 * The My Open Risk Report datatable's API function  *
 *****************************************************/
function my_open_risk_datatable() {
    global $escaper;

    $draw   = $escaper->escapeHtml($_POST['draw']);

    $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
    $length = $_POST['length'] ? (int)$_POST['length'] : 10;
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
    $orderColumnName = isset($_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
    $orderDir = isset($_POST['order'][0]['dir']) && strtoupper($_POST['order'][0]['dir']) === 'ASC'? "ASC" : 'DESC';
    $column_filters = [];
    for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
        if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
            $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
        }
    }

    switch ($orderColumnName) {
        case "management_review":
            // Sorted in PHP code
            $sort = false;
            break;

        case "mitigation_planned":
            $sort = "ORDER BY b.`mitigation_id` != 0 {$orderDir}, b.`id` ASC";
            break;

        case "id":
            $sort = "ORDER BY b.`id` {$orderDir}";
            break;

        case "risk_status":
            $sort = "ORDER BY b.`status` {$orderDir}, b.`id` ASC";
            break;

        case "subject":
            // If the encryption extra is enabled, sort by order_by_subject field
            if (encryption_extra()) {
                $sort = "ORDER BY b.`order_by_subject` {$orderDir}, b.`id` ASC";
            } else {
                $sort = "ORDER BY b.`subject` {$orderDir}, b.`id` ASC";
            }
            break;

        case "submission_date":
            $sort = "ORDER BY b.`submission_date` {$orderDir}, b.`id` ASC";
            break;

        case "score":
        default:
            $sort = "ORDER BY a.calculated_risk {$orderDir}, b.`id` ASC";
            break;
    }
    // Open the database connection
    $db = db_open();

    // If we're sorting in PHP($sort = false) or all the data is requested($length=-1)
    // then we're requesting all the data

    $filtering_where = "";
 
    $bind_params = [];
    $manual_column_filters = [];
    foreach($column_filters as $name => $column_filter){
        if($name == "risk_status"){
            $filtering_where .= " AND b.status LIKE :risk_status ";
            $bind_params[$name] = "%{$column_filter}%";
        } elseif($name == "score"){
            $filtering_where .= " AND a.calculated_risk LIKE :score ";
            $bind_params[$name] = "%{$column_filter}%";
        } else {
            $manual_column_filters[$name] = $column_filter;
        }
    }
    $limit = $sort !== false && $length > 0 && !$manual_column_filters ? "LIMIT {$start}, {$length}" : "";

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
        // Query the database
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a
                LEFT JOIN risks b ON a.id = b.id
                LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE
                b.status != \"Closed\" AND (owner = :uid OR manager = :uid) ". $filtering_where . "
            GROUP BY b.id
            {$sort}
            {$limit};
        ";
    }
    else
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the separation query string
        $separation_query = get_user_teams_query("b", false, true);

        // Query the database
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a
                LEFT JOIN risks b ON a.id = b.id
                LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE
                b.status != \"Closed\" AND (owner = :uid OR manager = :uid) ". $filtering_where . $separation_query . "
            GROUP BY b.id
            {$sort}
            {$limit};
        ";
    }

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":uid", $_SESSION['uid'], PDO::PARAM_INT);
    foreach($bind_params as $name => $bind_param){
        $stmt->bindParam(":{$name}", $bind_param);
    }
    $stmt->execute();

    // Store the results in the array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the result count
    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $rowCount = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    $risk_levels = get_risk_levels();
    $review_levels = get_review_levels();

    $next_review_date_uses = get_setting('next_review_date_uses');

    // If we're ordering by the 'management_review' column
    if ($sort === false && $orderColumnName === 'management_review') {
        // Calculate the 'management_review' values
        foreach($risks as &$risk) {
            $risk_level = get_risk_level_name($risk['calculated_risk']);
            $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);

            $risk['management_review'] = management_review($risk['id'], $risk['mgmt_review'], $next_review);
            $risk['management_review_text'] = management_review_text_only($risk['mgmt_review'], $next_review);
        }
        unset($risk);

        // Sorting by the management review text as the normal 'management_review' field contains html
        usort($risks, function($a, $b) use ($orderDir){
            // For identical management reviews we're sorting on the id, so the results' order is not changing
            if ($a['management_review_text'] === $b['management_review_text']) {
                return (int)$a['id'] - (int)$b['id'];
            }
            if($orderDir == "ASC") {
                return strcmp($a['management_review_text'], $b['management_review_text']);
            } else {
                return strcmp($b['management_review_text'], $a['management_review_text']);
            }
        });

        // If not all the results are requested, cutting a piece of it
        if($length > 0) {
            $risks = array_slice($risks, $start, $length);
        }
    }

    // Assembling the response
    $datas = array();
    foreach($risks as $risk){

        $risk['id'] = (int)$risk['id'] + 1000;

        $color = get_risk_color_from_levels($risk['calculated_risk'], $risk_levels);

        $subject = try_decrypt($risk['subject']);
        $submission_date = format_datetime($risk['submission_date'], "", "g:i A T");

        $risk_level = get_risk_level_name($risk['calculated_risk']);
        $residual_risk_level = get_risk_level_name($risk['residual_risk']);

        // If next_review_date_uses setting is Residual Risk.
        if(get_setting('next_review_date_uses') == "ResidualRisk")
        {
            $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);
        }
        $mitigation_planned = planned_mitigation($risk['id'], $risk['mitigation_id']);
        $management_review =  management_review($risk['id'], $risk['mgmt_review'], $next_review);

        $data = array(
            "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($risk['id'])."</a>",
            $escaper->escapeHtml($risk['status']),
            $escaper->escapeHtml($subject),
            "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>",
            $escaper->escapeHtml(date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']))),
            $mitigation_planned, // mitigation plan
            $management_review // management review
        );
        $success = true;
        foreach($manual_column_filters as $column_name => $val){
            if($column_name == "id") {
                if( stripos($risk['id'], $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "subject") {
                if( stripos($subject, $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "submission_date") {
                if( stripos($submission_date, $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "mitigation_planned") {
                if( stripos(strip_tags($mitigation_planned), $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "management_review") {
                if( stripos(strip_tags($management_review), $val) === false ){
                    $success = false;
                    break;
                }
            }
        }

        if($success) $datas[] = $data;
    }
    if($manual_column_filters){
        $datas_by_page = [];
        if($length > 0)
        {
            for($i=$start; $i<count($datas) && $i<$start + $length; $i++){
                $datas_by_page[] = $datas[$i];
            }
        }
        else
        {
            $datas_by_page = $datas;
        }
        $rowCount = count($datas);
    } else {
        $datas_by_page = $datas;
    }

    $results = array(
        "draw" => $draw,
        "recordsTotal" => $rowCount,
        "recordsFiltered" => $rowCount,
        "data" => $datas_by_page
    );

    // Return a JSON response
    echo json_encode($results);
}
/**************************************************************
 * FUNCTION: REPORTS - All Recent Commented Risks             *
 * The Recent Commented Risk Report datatable's API function  *
 **************************************************************/
function recent_commented_risk_datatable() {
    global $escaper;

    $draw   = $escaper->escapeHtml($_POST['draw']);

    $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
    $length = $_POST['length'] ? (int)$_POST['length'] : 10;
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
    $orderColumnName = isset($_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
    $orderDir = isset($_POST['order'][0]['dir']) && strtoupper($_POST['order'][0]['dir']) === 'ASC'? "ASC" : 'DESC';
    $column_filters = [];
    for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
        if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
            $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
        }
    }

    switch ($orderColumnName) {
        case "management_review":
            // Sorted in PHP code
            $sort = false;
            break;

        case "mitigation_planned":
            $sort = "ORDER BY b.`mitigation_id` != 0 {$orderDir}, b.`comment` DESC";
            break;

        case "id":
            $sort = "ORDER BY b.`id` {$orderDir}";
            break;

        case "risk_status":
            $sort = "ORDER BY b.`status` {$orderDir}, b.`comment` DESC";
            break;

        case "subject":
            // If the encryption extra is enabled, sort by order_by_subject field
            if (encryption_extra()) {
                $sort = "ORDER BY b.`order_by_subject` {$orderDir}, b.`comment` DESC";
            } else {
                $sort = "ORDER BY b.`subject` {$orderDir}, b.`comment` DESC";
            }
            break;

        case "comment_date":
            $sort = "ORDER BY b.`comment_date` {$orderDir}, b.`comment` DESC";
            break;

        case "comment":
            $sort = "ORDER BY b.`comment` {$orderDir}, b.`comment` DESC";
            break;

        case "score":
        default:
            $sort = "ORDER BY a.calculated_risk {$orderDir}, b.`comment` DESC";
            break;
    }
    // Open the database connection
    $db = db_open();

    // If we're sorting in PHP($sort = false) or all the data is requested($length=-1)
    // then we're requesting all the data

    $filtering_where = "";
 
    $bind_params = [];
    $manual_column_filters = [];
    foreach($column_filters as $name => $column_filter){
        if($name == "risk_status"){
            $filtering_where .= " AND b.status LIKE :risk_status ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "score"){
            $filtering_where .= " AND a.calculated_risk LIKE :score ";
            $bind_params[$name] = "%{$column_filter}%";
        } else if($name == "comment") {
            $filtering_where .= " AND `comment` LIKE :comment ";
            $bind_params[$name] = "%{$column_filter}%";
        } else {
            $manual_column_filters[$name] = $column_filter;
        }
    }
    $limit = $sort !== false && $length > 0 && !$manual_column_filters ? "LIMIT {$start}, {$length}" : "";

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
        $separation_query = " AND 1";
    }
    else
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the separation query string
        $separation_query = get_user_teams_query("b", false, true);
    }
    // Query the database
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS a.calculated_risk, b.*, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
        FROM risk_scoring a
            LEFT JOIN (
                SELECT *,
                    (SELECT `comment` FROM `comments` c WHERE c.risk_id = r.id ORDER BY c.date DESC LIMIT 1) as `comment`,
                    (SELECT `date` FROM `comments` cd WHERE cd.risk_id = r.id ORDER BY cd.date DESC LIMIT 1) AS `comment_date` FROM risks r ) b ON a.id = b.id
            LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
            LEFT JOIN mitigations mg ON b.id = mg.risk_id
            LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
            LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            LEFT JOIN risk_to_additional_stakeholder rtas ON b.id=rtas.risk_id 
            LEFT JOIN risk_to_team rtt on b.id = rtt.risk_id
        WHERE
            `comment` IS NOT NULL ". $filtering_where . $separation_query . "
        GROUP BY b.id
        {$sort}
        {$limit};
    ";


    $stmt = $db->prepare($sql);

    foreach($bind_params as $name => $bind_param){
        $stmt->bindParam(":{$name}", $bind_param);
    }
    $stmt->execute();

    // Store the results in the array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the result count
    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $rowCount = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    $risk_levels = get_risk_levels();
    $review_levels = get_review_levels();

    $next_review_date_uses = get_setting('next_review_date_uses');

    // If we're ordering by the 'management_review' column
    if ($sort === false && $orderColumnName === 'management_review') {
        // Calculate the 'management_review' values
        foreach($risks as &$risk) {
            $risk_level = get_risk_level_name($risk['calculated_risk']);
            $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels);

            $risk['management_review'] = management_review($risk['id'], $risk['mgmt_review'], $next_review);
            $risk['management_review_text'] = management_review_text_only($risk['mgmt_review'], $next_review);
        }
        unset($risk);

        // Sorting by the management review text as the normal 'management_review' field contains html
        usort($risks, function($a, $b) use ($orderDir){
            // For identical management reviews we're sorting on the id, so the results' order is not changing
            if ($a['management_review_text'] === $b['management_review_text']) {
                return (int)$a['id'] - (int)$b['id'];
            }
            if($orderDir == "ASC") {
                return strcmp($a['management_review_text'], $b['management_review_text']);
            } else {
                return strcmp($b['management_review_text'], $a['management_review_text']);
            }
        });

        // If not all the results are requested, cutting a piece of it
        if($length > 0) {
            $risks = array_slice($risks, $start, $length);
        }
    }
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }

    // Assembling the response
    $datas = array();
    foreach($risks as $risk){

        $risk['id'] = (int)$risk['id'] + 1000;

        $color = get_risk_color_from_levels($risk['calculated_risk'], $risk_levels);
        $residual_color = get_risk_color($risk['residual_risk']);

        $subject = try_decrypt($risk['subject']);
        $comment_date = format_datetime($risk['comment_date'], "", "g:i A T");

        $risk_level = get_risk_level_name($risk['calculated_risk']);
        $residual_risk_level = get_risk_level_name($risk['residual_risk']);

        if (encryption_extra()) {
            $risk['comment'] = try_decrypt($risk['comment']);
        }
        

        $data = array(
            "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($risk['id'])."</a>",
            $escaper->escapeHtml($risk['status']),
            $escaper->escapeHtml($subject),
            "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>",
            "<div class='".$escaper->escapeHtml($residual_color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['residual_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($residual_color) . "\"></span></div></div>",
            $escaper->escapeHtml($comment_date),
            $escaper->escapeHtml($risk['comment']),
        );
        $success = true;
        foreach($manual_column_filters as $column_name => $val){
            if($column_name == "id") {
                if( stripos($risk['id'], $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "subject") {
                if( stripos($subject, $val) === false ){
                    $success = false;
                    break;
                }
            } else if($column_name == "comment_date") {
                if( stripos($comment_date, $val) === false ){
                    $success = false;
                    break;
                }
            }
        }

        if($success) $datas[] = $data;
    }
    if($manual_column_filters){
        $datas_by_page = [];
        if($length > 0)
        {
            for($i=$start; $i<count($datas) && $i<$start + $length; $i++){
                $datas_by_page[] = $datas[$i];
            }
        }
        else
        {
            $datas_by_page = $datas;
        }
        $rowCount = count($datas);
    } else {
        $datas_by_page = $datas;
    }

    $results = array(
        "draw" => $draw,
        "recordsTotal" => $rowCount,
        "recordsFiltered" => $rowCount,
        "data" => $datas_by_page
    );

    // Return a JSON response
    echo json_encode($results);
}
/***************************************************************************************
 * END OF SECTION CONTAINING FUNCTIONS DEDICATED TO FIXING FILE UPLOAD ENCODING ISSUES *
 ***************************************************************************************/

/*******************************************
 * FUNCTION: CONTROL GAP ANALYSIS RESPONSE *
 *******************************************/
function controlGapAnalysisResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
    	$framework_id = $escaper->escapeHtml($_GET['framework_id']);
    	$maturity = $escaper->escapeHtml($_GET['maturity']);
        $draw = $escaper->escapeHtml($_GET['draw']);

        $orderColumn = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        $orderColumnName = isset($_GET['columns'][$orderColumn]['name']) ? $_GET['columns'][$orderColumn]['name'] : null;
        $orderDir = !empty($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

    	// Get controls with gaps
    	$control_gaps = get_control_gaps($framework_id, $maturity, $orderColumnName, $orderDir);
        $recordsTotal = count($control_gaps);
        $data = array();

        foreach ($control_gaps as $key=>$control_gap)
        {
            // If it is not requested to view all
            if($_GET['length'] != -1){
                if($key < $_GET['start']){
                    continue;
                }
                if($key >= ($_GET['start'] + $_GET['length'])){
                    break;
                }
            }

            $data[] = [
                $escaper->escapeHtml($control_gap['control_number']),
        		$escaper->escapeHtml($control_gap['short_name']),
        		$escaper->escapeHtml($control_gap['control_phase_name']),
        		$escaper->escapeHtml($control_gap['family_short_name']),
        		$escaper->escapeHtml($control_gap['control_maturity_name']),
        		$escaper->escapeHtml($control_gap['desired_maturity_name']),
            ];
        }
    }
    else
    {
        $draw = $escaper->escapeHtml($_GET['draw']);
        $data = [];
        $recordsTotal = 0;
        $recordsTotal = 0;
    }

    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );
    echo json_encode($result);
    exit;
}

/******************************************
 * FUNCTION: ADD CONTRIBUTING RISKS VALUE *
 ******************************************/
function add_contributing_risks_api(){
    global $lang, $escaper;
    $table = get_param("POST", "table", "likelihood");
    $name = get_param("POST", "name");
    $contributing_risks_id = get_param("POST", "contributing_risks_id", "");

    if(is_admin())
    {
        if(add_contributing_risks($table, $name, $contributing_risks_id)){
            $status = "good";
            if($table == "likelihood")
                $message = $escaper->escapeHtml($lang["SuccessAddingLikelihood"]);
            else 
                $message = $escaper->escapeHtml($lang["SuccessAddingImpact"]);
            $status_code = 200;
        }else{
            $status = "bad";
            if($table == "likelihood")
                $message = $escaper->escapeHtml($lang["FailAddingLikelihood"]);
            else 
                $message = $escaper->escapeHtml($lang["FailAddingImpact"]);
            $status_code = 400;
        }
    }
    else
    {
        $status = "bad";
        $message = $escaper->escapeHtml($lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    set_alert(true, $status, $message);
    // Return a JSON response
    json_response($status_code, get_alert(true), null);
}

/*******************************************************
 * FUNCTION: UPDATE CONTRIBUTING RISKS LIKELIHOOD NAME *
 *******************************************************/
function update_contributing_risks_likelihood_api(){
    global $lang, $escaper;
    $value = (int)get_param("POST", "value");
    $name = get_param("POST", "name");
   
    if(is_admin()) {
        if(update_table("contributing_risks_likelihood", $name, $value, 50)){
            set_alert(true, "good", $lang["SuccessUpdatingLikelihoodName"]);
            $status_code = 200;
        } else {
            set_alert(true, "bad", $lang["FailUpdatingLikelihoodName"]);
            $status_code = 400;
        }
    } else {
        set_alert(true, "bad", $lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    // Return a JSON response
    json_response($status_code, get_alert(true), null);
}
/***************************************************
 * FUNCTION: UPDATE CONTRIBUTING RISKS IMPACT NAME *
 ***************************************************/
function update_contributing_risks_impact_api(){
    global $lang, $escaper;

    $id = (int)get_param("POST", "id");
    $name = get_param("POST", "name");
    
    if(is_admin()) {
        if(update_table_by_id("contributing_risks_impact", $name, $id, 50)){
            set_alert(true, "good", $lang["SuccessUpdatingImpactName"]);
            $status_code = 200;
        } else {
            set_alert(true, "bad", $lang["FailUpdatingImpactName"]);
            $status_code = 400;
        }
    } else {
        set_alert(true, "bad", $lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    // Return a JSON response
    json_response($status_code, get_alert(true), null);
}
/**************************************************
 * FUNCTION: DELETE CONTRIBUTING LIKELIHOOD RISKS *
 **************************************************/
function delete_contributing_risks_likelihood_api(){
    global $lang, $escaper;

    $value = (int)get_param("POST", "value");
    $table_name = "contributing_risks_likelihood";
    if(is_admin())
    {
        if(count(get_table($table_name)) == 1) {
            $status = "bad";
            $message = $escaper->escapeHtml($lang["CannotDeleteLastItem"]);
            $status_code = 400; 
        } else if(delete_value($table_name, $value)){
            // Open the database connection
            $db = db_open();
            // Get the max value 
            $stmt = $db->prepare("SELECT MAX(`value`) max_value FROM {$table_name}");
            $stmt->execute();
            $array = $stmt->fetch();
            $max_value = $array['max_value'];
            for($i=$value+1;$i<=$max_value;$i++){
                $new_value = $i-1;
                $stmt = $db->prepare("UPDATE {$table_name} SET value=:new_value WHERE value=:value");
                $stmt->bindParam(":new_value", $new_value, PDO::PARAM_INT);
                $stmt->bindParam(":value", $i, PDO::PARAM_INT);
                $stmt->execute();
            }
            // Close the database connection
            db_close($db);
            $status = "good";
            $message = $escaper->escapeHtml($lang["SuccessDeletingLikelihood"]);
            $status_code = 200;
        }else{
            $status = "bad";
            $message = $escaper->escapeHtml($lang["FailDeletingLikelihood"]);
            $status_code = 400;
        }
    }
    else
    {
        $status = "bad";
        $message = $escaper->escapeHtml($lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    set_alert(true, $status, $message);
    // Return a JSON response
    json_response($status_code, get_alert(true), null);
}
/**********************************************
 * FUNCTION: DELETE CONTRIBUTING IMPACT RISKS *
 **********************************************/
function delete_contributing_risks_impact_api(){
    global $lang, $escaper;

    $id = (int)get_param("POST", "id");
    $value = (int)get_param("POST", "value");
    $contributing_risks_id = (int)get_param("POST", "contributing_risks_id");
    $table_name = "contributing_risks_impact";

    if(is_admin())
    {
        // Open the database connection
        $db = db_open();
        $stmt = $db->prepare("SELECT * FROM {$table_name} WHERE contributing_risks_id = :contributing_risks_id");
        $stmt->bindParam(":contributing_risks_id", $contributing_risks_id, PDO::PARAM_INT);
        $stmt->execute();
        $array = $stmt->fetchAll();
        if(count($array) == 1) {
            $status = "bad";
            $message = $escaper->escapeHtml($lang["CannotDeleteLastItem"]);
            $status_code = 400; 
        } else if(delete_value_by_id($table_name, $id)){
            // Get the max value 
            $stmt = $db->prepare("SELECT MAX(`value`) max_value FROM {$table_name} WHERE contributing_risks_id = :contributing_risks_id");
            $stmt->bindParam(":contributing_risks_id", $contributing_risks_id, PDO::PARAM_INT);
            $stmt->execute();
            $array = $stmt->fetch();
            $max_value = $array['max_value'];
            for($i=$value+1;$i<=$max_value;$i++){
                $new_value = $i-1;
                $stmt = $db->prepare("UPDATE {$table_name} SET value=:new_value WHERE value=:value AND contributing_risks_id = :contributing_risks_id");
                $stmt->bindParam(":new_value", $new_value, PDO::PARAM_INT);
                $stmt->bindParam(":value", $i, PDO::PARAM_INT);
                $stmt->bindParam(":contributing_risks_id", $contributing_risks_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            $status = "good";
            $message = $escaper->escapeHtml($lang["SuccessDeletingImpact"]);
            $status_code = 200;
            // Close the database connection
        }else{
            $status = "bad";
            $message = $escaper->escapeHtml($lang["FailDeletingImpact"]);
            $status_code = 400;
        }
        db_close($db);
    }
    else
    {
        $status = "bad";
        $message = $escaper->escapeHtml($lang["AdminPermissionRequired"]);
        $status_code = 400;
    }

    set_alert(true, $status, $message);
    // Return a JSON response
    json_response($status_code, get_alert(true), null);
}
/**************************************
 * FUNCTION: LIST OF RISKS LIKELIHOOD *
 **************************************/
function contributing_risks_table_list_api(){
    $table = get_param("POST", "table", "likelihood");
    if($table == "likelihood")
        $table_list = display_contributing_risks_likelihood_table_list($table);
    else 
        $table_list = display_contributing_risks_impact_table_list($table);
    echo $table_list;exit;
}

/***************************************
 * FUNCTION: SAVE GRAPHICAL SELECTIONS *
 ***************************************/
function saveGraphicalSelectionsForm()
{
    global $lang, $escaper;
    
    $type = get_param("post", "selection_type");
    $name = get_param("post", "selection_name");

    // If the id is not sent
    if (!$type || !$name)
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['ThereAreRequiredFields']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    
    // Check if this name already existing
    if(check_exisiting_graphical_selection_name($_SESSION['uid'], $name))
    {
        set_alert(true, "bad", $lang['TheNameAlreadyExists']);
        json_response(400, get_alert(true), []);
    }
    else
    {
        $graphic_form_data = $_POST;
        if(isset($graphic_form_data['__csrf_magic'])) unset($graphic_form_data['__csrf_magic']);
        $id = save_graphical_selections($type, $name, $graphic_form_data);

        $saved_selection = get_graphical_saved_selection($id);
        if ($saved_selection) {
            set_alert(true, "good", $lang['SavedSuccess']);
            json_response(200, get_alert(true), ['value' => $id, 'name' => $saved_selection['name'], 'type' => $saved_selection['type']]);
        }
    }
    set_alert(true, "bad", $lang['SelectionSaveFailed']);
    json_response(400, get_alert(true), []);
}

/****************************************
 * FUNCTION: DELETE GRAPHICAL SELECTION *
 ****************************************/
function deleteGraphicalSelectionForm()
{
    global $lang, $escaper;
    
    $id = get_param("post", "id");

    // If the id is not sent
    if (!$id) {
        set_alert(true, "bad", $lang['ThereAreRequiredFields']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    // Get the selection data so we can check if the user has the permission to delete the saved selection
    $selection = get_graphical_saved_selection($id);
    
    // Admins can access/manage all saved selections
    if($_SESSION['admin'] || $selection['user_id'] == $_SESSION['uid']) {

        delete_graphical_selection($id);

        // Not returning the alert on purpose because the UI logic is refreshing the page and if we user get_alert() here
        // then it'll remove it from the session and won't be displayed after the reload
        set_alert(true, "good", $lang['DeletedSuccess']);
        json_response(200, null, null);
    }

    set_alert(true, "bad", $lang['NoPermissionForThisSelection']);
    json_response(400, get_alert(true), null);
}


?>
