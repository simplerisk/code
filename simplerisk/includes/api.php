<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/config_check.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/display.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/reporting.php'));
// get_risk_connectivity_for_asset() (getAssetRiskAssociations, below). It moved
// out of reporting.php into entity_graph.php, and is reachable here only
// because reporting.php happens to require entity_graph.php itself -- declared
// directly rather than relying on that, per the CLAUDE.md reachability rule.
require_once(realpath(__DIR__ . '/entity_graph.php'));
require_once(realpath(__DIR__ . '/assets.php'));
require_once(realpath(__DIR__ . '/compliance.php'));
// get_test_audit_history() -- declared directly here rather than relying on
// api/v2/index.php's own require, per the CLAUDE.md reachability rule.
require_once(realpath(__DIR__ . '/compliance_grid.php'));
require_once(realpath(__DIR__ . '/audit_schedule.php'));
require_once(realpath(__DIR__ . '/governance.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/datefix.php'));
require_once(realpath(__DIR__ . '/extras.php'));
require_once(realpath(__DIR__ . '/artificial_intelligence.php'));
require_once(realpath(__DIR__ . '/notifications.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include the language file
require_once(language_file(true));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new simpleriskEscaper();

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
        if (use_database_for_sessions())
        {
            SimpleRiskSessionHandler::register();
        }

        // Start the session
        $parameters = [
            "lifetime" => 0,
            "path" => "/",
            "domain" => "",
            "secure" => isset($_SERVER["HTTPS"]),
            "httponly" => true,
            "samesite" => "Strict",
        ];
        session_set_cookie_params($parameters);

        session_name('SimpleRisk');
        session_start();
    }

    // Check for session timeout or renegotiation
    session_check();

    // If the session is authenticated
    if (isset($_SESSION["access"]) && ($_SESSION["access"] == "1" || $_SESSION["access"] == "granted"))
    {
	    // Load CSRF Magic — include_csrf_magic() to load the DB secret before validation
	    include_csrf_magic();

	    // Release the session lock right after auth so this request doesn't hold
	    // the DB handler's row-level FOR UPDATE on the session row for its entire
	    // duration. Without this, a page's parallel same-session AJAX (e.g.
	    // view.php's ~15 api/v2 calls) serialize on the one session row and pile
	    // up to 50-110s, some hitting the lock-wait timeout and 500ing (SR-1691).
	    // $_SESSION stays readable; the few writers (set_alert) re-acquire the
	    // lock briefly via with_alert_session().
	    if (session_status() === PHP_SESSION_ACTIVE) {
	        session_write_close();
	    }

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
function unauthorized_access() {

    global $lang, $escaper;

    // Return a JSON response
    json_response(401, $escaper->escapeHtml($lang['UnauthorizedAccessInAPI']), NULL);

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
    global $lang;

    // Check that this is an admin user
    if (!is_admin())
    {
        json_response(403, $lang['AdminPermissionRequired'], NULL);
        return;
    }

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

/*************************
 * FUNCTION: API VERSION *
 *************************/
// @phan-suppress-next-line PhanRedefineFunction -- core definition; extras/api/index.php overrides this
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
            $role_name = ($role) ? $role['name'] : "";


            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login'], "teams" => $teams, "role" => $role_name, "responsibilities" => $permissions);
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
            $role_name = ($role) ? $role['name'] : "";

            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login'], "teams" => $teams, "role" => $role_name, "responsibilities" => $permissions);
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
            $role_name = ($role) ? $role['name'] : "";

            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login'], "teams" => $teams, "role" => $role_name, "responsibilities" => $permissions);
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
    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

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
function viewrisk($id = null) {
    global $lang, $escaper;

    if (!check_permission("riskmanagement")) {
        global $escaper, $lang;
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    // If the id is not sent
    if ($id === null && !isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    else
    {
        // Get the id
        $id = (int)($id ?? $_GET['id']);

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
            $assets = array_map(function($item) { return array('name'=>$item['name'], 'type'=>$item['class']); }, get_assets_and_asset_groups_of_type($id, 'risk', true));
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

            $data = [$risk];

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
function viewmitigation($id = null) {

    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    // If the id is not sent
    if ($id === null && !isset($_GET['id']))
    {
        // Return a JSON response
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
    }
    $risk_id = $id ?? $_GET['id'];
    $mitigation = get_mitigation_by_id($risk_id);

    if(!isset($mitigation[0])){
        // Return a JSON response
        json_response(400, $escaper->escapeHtml($lang['NoMitigation']), NULL);
    }

    $mitigation = $mitigation[0];
    $supporting_files = get_supporting_files($risk_id, 2);
    $mitigation['supporting_files'] = array();
    foreach($supporting_files as $supporting_file){
        $mitigation['supporting_files'][] = build_url("management/download.php?id=" . $escaper->escapeHtml($supporting_file['unique_name']));
    }

    // NOTE (SR-1898): current_solution / security_requirements / security_recommendations
    // are encrypted-at-rest fields (Encrypted DB Extra); the value here is still
    // ciphertext (decrypted downstream), so it must NOT be purified at this boundary —
    // purifying ciphertext corrupts it and breaks decryption. These fields are purified
    // on write (submit_mitigation / update_mitigation purify the plaintext before try_encrypt).

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
function viewreview($id = null) {
    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    // If the id is not sent
    if ($id === null && !isset($_GET['id']))
    {
        // Return a JSON response
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
    }

    $risk_id = $id ?? $_GET['id'];
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
    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

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
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";

        // Sanitizing input
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumnName = !empty($_POST['columns'][$orderColumnIndex]['name']) && preg_match('/^[a-zA-Z0-9_]+$/', $_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
        // Sanitizing input
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
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
                foreach(str_getcsv($row['risk_tags'], '|', '"', '') as $tag) {
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
                            $data_row[] = "<a class='open-in-new-tab' href=\"../management/view.php?id=" . $escaper->escapeHtml($row['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($row['id'])."</a>";
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
                            $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($row[$column]) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeCssColor($color) . "\"></span></div></div>";
                            break;
                        case 'comments':
                        case 'risk_assessment':
                        case 'additional_notes':
                        case 'current_solution':
                        case 'security_recommendations':
                        case 'security_requirements':
                            $data_row[] = $escaper->purifyHtml($row[$column]);
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

        //passing null to the first parameter of type string in stripos() in late versions of PHP
        if(($pos = stripos($orderColumnName ?? '', "custom_field_")) !== false){
            // For identical custom fields we're sorting on the id, so the results' order is not changing
            usort($datas, function($a, $b) use ($orderDir, $orderColumnName){
                return $orderDir == "asc" ? [$a['risk'][$orderColumnName], (int)$a['risk']['id']] <=> [$b['risk'][$orderColumnName], (int)$b['risk']['id']] : [$b['risk'][$orderColumnName], (int)$b['risk']['id']] <=> [$a['risk'][$orderColumnName], (int)$a['risk']['id']];
            });
        }

        $results = array(
            "draw" => $draw,
            "recordsTotal" => $rowCount,
            "recordsFiltered" => $rowCount,
            "data" => $datas
        );

        // Return a JSON response
        // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output for DataTables; values are pre-escaped via escapeHtml()/purifyHtml()
        echo json_encode($results);
    }
}

/*******************************************************
 * FUNCTION: DYNAMIC RISK UNIQUE DATA FOR EACH COLUMNS *
 *******************************************************/
function dynamicriskUniqueColumnDataAPI()
{
    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }
    
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
        $active_fields = [];

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
                // columns split by ","
                case "mitigation_cost":
                    $uniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, ',', $delimiter);
                break;

                /*Move over fields here that can't have text separated by a comma(,).
				Don't forget to also update the query to use '|' as the separator instead of ','*/
                case "planning_strategy":
                case "close_reason":
                case "next_step":
                case "review":
                case "risk_status":
                case "source":
                case "category":

                case "closed_by":
                case "additional_stakeholders":
                case "owner":
                case "manager":
                case "submitted_by":
                case "reviewer":
                case "mitigation_owner":

                case "mitigation_controls":
                case "mitigation_team":
                case "location":
                case "risk_tags":
                case "technology":
                case "team":
                case "threat_mapping":
                case "risk_mapping_risk_grouping":
                case "risk_mapping_risk":
                case "risk_mapping_function":
                case "risk_mapping":
                    $uniqueColumnArr = get_name_value_array_from_text_array($uniqueColumnArr, '|', $delimiter);
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
        // NOTE (SR-1898): assessment / notes are encrypted-at-rest fields (Encrypted
        // DB Extra); the value here is still ciphertext (decrypted downstream), so it
        // must NOT be purified at this boundary — purifying ciphertext corrupts it and
        // breaks decryption. These fields are purified on write (submit_risk /
        // update_risk purify the plaintext before try_encrypt).
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
        $residual_risk_level = null;

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

    // Scoring-method changes triggered via GET on a getTabHtml caller (e.g.
    // /api/management/risk/viewhtml, /api/management/risk/scoreaction) write
    // through change_scoring_method() and update_*_score() to risk_scoring.
    // Gate the entire block on modify_risks so a riskmanagement-only user
    // cannot mutate stored scoring state by appending scoring_method to a view
    // URL. The original code only checked $access (team-separation visibility)
    // and let any reader trigger the writes. (HackerOne report.)
    if (isset($_GET['scoring_method']) && $access && check_permission("modify_risks"))
    {
        // If the current scoring method was changed to Classic
        if ($_GET['scoring_method'] == 1)
        {
            // Set the new scoring method
            $scoring_method = change_scoring_method($id, "1");

            // Update the classic score
            $calculated_risk = update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact);

            // Display an alert
            set_alert(true, "good", "The scoring method has been successfully changed to Classic.");
        }
        // If the current scoring method was changed to CVSS
        else if ($_GET['scoring_method'] == 2)
        {
            // Set the new scoring method
            $scoring_method = change_scoring_method($id, "2");

            // Update the cvss score
            $calculated_risk = update_cvss_score($id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);

            // Display an alert
            set_alert(true, "good", "The scoring method has been successfully changed to CVSS.");
        }
        // If the current scoring method was changed to DREAD
        else if ($_GET['scoring_method'] == 3)
        {
            // Set the new scoring method
            $scoring_method = change_scoring_method($id, "3");

            // Update the dread score
            $calculated_risk = update_dread_score($id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);

            // Display an alert
            set_alert(true, "good", "The scoring method has been successfully changed to DREAD.");
        }
        // If the current scoring method was changed to OWASP
        else if ($_GET['scoring_method'] == 4)
        {
            // Set the new scoring method
            $scoring_method = change_scoring_method($id, "4");

            // Update the owasp score
            $calculated_risk = update_owasp_score($id, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);

            // Display an alert
            set_alert(true, "good", "The scoring method has been successfully changed to OWASP.");
        }
        // If the current scoring method was changed to Custom
        else if ($_GET['scoring_method'] == 5)
        {
            // Set the new scoring method
            $scoring_method = change_scoring_method($id, "5");

            // Update the custom score
            $calculated_risk = update_custom_score($id, $custom);

            // Display an alert
            set_alert(true, "good", "The scoring method has been successfully changed to Custom.");
        }
        // If the current scoring method was changed to Contributing Risk
        else if ($_GET['scoring_method'] == 6)
        {
            // Set the new scoring method
            $scoring_method = change_scoring_method($id, "6");

            // Update the custom score
            $calculated_risk = update_contributing_risk_score($id, $ContributingLikelihood, $ContributingImpacts);

            // Display an alert
            set_alert(true, "good", "The scoring method has been successfully changed to Contributing Risk.");
        }
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
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_date    = format_date($mitigation[0]['submission_date']);
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $planning_strategy  = $mitigation[0]['planning_strategy'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_effort  = $mitigation[0]['mitigation_effort'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_cost    = $mitigation[0]['mitigation_cost'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_owner   = $mitigation[0]['mitigation_owner'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $mitigation_team    = $mitigation[0]['mitigation_team'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $current_solution   = $mitigation[0]['current_solution'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $security_requirements      = $mitigation[0]['security_requirements'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $security_recommendations   = $mitigation[0]['security_recommendations'];
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
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

    global $isAjax;
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

    if (!check_permission("riskmanagement")) {
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

    // Test that the ID is a numeric value
    $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

    $viewhtml = getTabHtml($id, 'viewhtml');
    

    json_response(200, get_alert(true), $viewhtml);
}


/*************************************
 * FUNCTION: MANAGEMENT - REOPEN RISK *
 *************************************/
function reopenForm($id = null)
{
    global $lang, $escaper;

    if (!check_permission("riskmanagement"))
    {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    if (!has_permission("modify_risks"))
    {
        json_response(403, $escaper->escapeHtml($lang['RiskUpdatePermissionMessage']), NULL);
        return;
    }

    // If the id is not sent
    if ($id === null && !isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $id ?? $_GET['id'];

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

    $risk_id = null;
    $synchronized_risk_field_values = null;

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

    if (!check_permission("riskmanagement")) {
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

    if(check_permission("close_risks") && $access){
        $status = "Closed";
        $close_reason = $_POST['close_reason'];
        $note = $_POST['note'];

        $risk_id = null;
        $synchronized_risk_field_values = null;

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
        set_alert(true, "bad", "You do not have permission to close risks.  Any attempts to close risks will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");

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

    if (!check_permission("riskmanagement")) {
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

        $issue_key = null;
        $synchronized_risk_field_values = null;

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
            $risk_id = null;
            $synchronized_risk_field_values = null;

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

/****************************************************
 * FUNCTION: MANAGEMENT - UPDATE SCORING METHOD     *
 ****************************************************
 * Dedicated POST endpoint that performs the scoring-method change as a real
 * mutation: csrf-magic protects POSTs automatically, the handler enforces
 * modify_risks + check_access_for_risk(), and getTabHtml() (which is reached
 * from this handler with $_GET['scoring_method'] absent) is then reduced to
 * pure rendering.
 *
 * The previous flow piggy-backed the mutation on GET /scoreaction (and other
 * getTabHtml-backed view URLs) by passing scoring_method on the query string,
 * which let any team-visible reader silently rewrite stored scoring state.
 * That GET path now also enforces modify_risks (defence in depth) but the
 * canonical mutation surface is this POST handler. Reported via HackerOne.
 ****************************************************/
function updateScoringMethodForm()
{
    global $lang, $escaper;

    // Required: id of the risk to rescore
    if (!isset($_POST['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));
        json_response(400, get_alert(true), NULL);
        return;
    }

    // Required: scoring_method to switch to
    if (!isset($_POST['scoring_method']))
    {
        set_alert(true, "bad", "You need to specify a scoring_method parameter.");
        json_response(400, get_alert(true), NULL);
        return;
    }

    $id     = (is_numeric($_POST['id']) ? (int)$_POST['id'] : 0);
    $method = (is_numeric($_POST['scoring_method']) ? (int)$_POST['scoring_method'] : 0);

    // Reject any scoring_method outside the supported set so we don't write
    // arbitrary integers into risks.scoring_method.
    if (!in_array($method, [1, 2, 3, 4, 5, 6], true))
    {
        set_alert(true, "bad", "Invalid scoring_method value.");
        json_response(400, get_alert(true), NULL);
        return;
    }

    // Permission gates: must hold modify_risks AND have team-separation
    // visibility into this specific risk.
    if (!check_permission("modify_risks") || !check_access_for_risk($id))
    {
        set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
        json_response(400, get_alert(true), NULL);
        return;
    }

    // Pull the risk's existing per-method fields so update_*_score() can
    // recompute against the values the user already entered. Mirrors what the
    // legacy inline block in getTabHtml does, just at a real mutation entry
    // point.
    $risk = get_risk_by_id($id);
    if (count($risk) == 0)
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['RiskIdDoesNotExist']));
        json_response(404, get_alert(true), NULL);
        return;
    }
    $r = $risk[0];

    change_scoring_method($id, (string)$method);

    switch ($method)
    {
        case 1:
            update_classic_score($id, $r['CLASSIC_likelihood'], $r['CLASSIC_impact']);
            set_alert(true, "good", "The scoring method has been successfully changed to Classic.");
            break;
        case 2:
            update_cvss_score(
                $id,
                $r['CVSS_AccessVector'], $r['CVSS_AccessComplexity'], $r['CVSS_Authentication'],
                $r['CVSS_ConfImpact'], $r['CVSS_IntegImpact'], $r['CVSS_AvailImpact'],
                $r['CVSS_Exploitability'], $r['CVSS_RemediationLevel'], $r['CVSS_ReportConfidence'],
                $r['CVSS_CollateralDamagePotential'], $r['CVSS_TargetDistribution'],
                $r['CVSS_ConfidentialityRequirement'], $r['CVSS_IntegrityRequirement'], $r['CVSS_AvailabilityRequirement']
            );
            set_alert(true, "good", "The scoring method has been successfully changed to CVSS.");
            break;
        case 3:
            update_dread_score(
                $id,
                $r['DREAD_DamagePotential'], $r['DREAD_Reproducibility'], $r['DREAD_Exploitability'],
                $r['DREAD_AffectedUsers'], $r['DREAD_Discoverability']
            );
            set_alert(true, "good", "The scoring method has been successfully changed to DREAD.");
            break;
        case 4:
            update_owasp_score(
                $id,
                $r['OWASP_SkillLevel'], $r['OWASP_Motive'], $r['OWASP_Opportunity'], $r['OWASP_Size'],
                $r['OWASP_EaseOfDiscovery'], $r['OWASP_EaseOfExploit'], $r['OWASP_Awareness'], $r['OWASP_IntrusionDetection'],
                $r['OWASP_LossOfConfidentiality'], $r['OWASP_LossOfIntegrity'], $r['OWASP_LossOfAvailability'],
                $r['OWASP_LossOfAccountability'], $r['OWASP_FinancialDamage'], $r['OWASP_ReputationDamage'],
                $r['OWASP_NonCompliance'], $r['OWASP_PrivacyViolation']
            );
            set_alert(true, "good", "The scoring method has been successfully changed to OWASP.");
            break;
        case 5:
            update_custom_score($id, $r['Custom']);
            set_alert(true, "good", "The scoring method has been successfully changed to Custom.");
            break;
        case 6:
            $contributing_risks_impacts = $r['Contributing_Risks_Impacts'];
            $contributing_impacts = $contributing_risks_impacts
                ? get_contributing_impacts_by_subjectimpact_values($contributing_risks_impacts)
                : [];
            update_contributing_risk_score($id, $r['Contributing_Likelihood'], $contributing_impacts);
            set_alert(true, "good", "The scoring method has been successfully changed to Contributing Risk.");
            break;
    }

    // Render and return the updated score-overview tab. The legacy inline
    // scoring_method handler in getTabHtml fires on isset($_GET['scoring_method'])
    // (api.php:1502 area). On a normal POST that branch is dormant because
    // $_GET is empty, but a caller that includes scoring_method in the query
    // string as well as the POST body would re-trigger the same mutation a
    // second time. The mutation is idempotent so it's not a correctness bug,
    // but it's a wasted DB round-trip. Drop the GET key explicitly so the
    // inline block can never re-fire from this entry point.
    unset($_GET['scoring_method']);
    $html = getTabHtml($id, 'score-overview');
    json_response(200, get_alert(true), $html);
}

/*****************************************
 * FUNCTION: MANAGEMENT - UPDATE SUBJECT*
 ****************************************/
function saveSubjectForm($id = null)
{
    global $lang, $escaper;

    // If the id is not sent
    if ($id === null && !isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $id ?? $_GET['id'];

    // If the user has permission to modify the risk and has access to the risk
    if(has_permission("modify_risks") && check_access_for_risk($id)){

        $new_subject = $_POST['subject'];
        if (trim($new_subject) != '')
        {
            $risk_id = null;
            $synchronized_risk_field_values = null;

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

/*****************************************************
 * FUNCTION: MANAGEMENT - SET PROJECT TO A RISK      *
 * POST /management/risk/setProjectToRisk?id={id}    *
 * Body: project_id={project_id}                     *
 *****************************************************/
function setProjectToRiskForm($id = null)
{
    global $lang, $escaper;

    if ($id === null && !isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));
        json_response(400, get_alert(true), NULL);
        return;
    }
    $id = $id ?? $_GET['id'];

    if (!has_permission("modify_risks") || !check_access_for_risk($id)) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForRiskManagement']));
        json_response(400, get_alert(true), NULL);
        return;
    }

    $project_id = isset($_POST['project_id']) ? $_POST['project_id'] : null;
    if (!ctype_digit((string)$project_id)) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['ThereWasAProblemWithAddingTheProject']));
        json_response(400, get_alert(true), NULL);
        return;
    }

    $risk_id = (int)$id - 1000;
    update_risk_project((int)$project_id, $risk_id);

    set_alert(true, "good", $escaper->escapeHtml($lang['SuccessSetProject']));
    json_response(200, get_alert(true), NULL);
}

/*****************************************
 * FUNCTION: MANAGEMENT - GET COMMENTS  *
 ****************************************/
function getRiskComments($id = null)
{
    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    $id = $id ?? $_GET['id'] ?? null;
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_risk($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    $raw = get_comments($id, false);
    $comments = [];
    foreach ($raw as $row) {
        $text = try_decrypt($row['comment']);
        if ($text !== null) {
            $comments[] = [
                'date' => $row['date'],
                'user' => $row['name'],
                'comment' => $text,
            ];
        }
    }

    json_response(200, 'Comments retrieved successfully.', $comments);
}

/*****************************************
 * FUNCTION: MANAGEMENT - UPDATE COMMENT*
 ****************************************/
function saveCommentForm($id = null)
{
    global $escaper, $lang;

    // If the id is not sent
    if ($id === null && !isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $id ?? $_GET['id'];

    $access = check_access_for_risk($id);

//    if(!isset($_SESSION["modify_risks"]) || $_SESSION["modify_risks"] != 1 || !$access){
    if(!$access){
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoAccessRiskPermission']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    elseif($_SESSION["comment_risk_management"] == 1) {
        $comment = $_POST['comment'] ?? null;

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
        json_response(403, $escaper->escapeHtml($lang['NoCommentRiskPermission']), NULL);
    }

    // getTabHtml includes a CSRF-checked template that requires HTTP_REFERER (set by
    // browsers but not by API clients). Skip the HTML rendering for API calls so the
    // csrf-magic library does not crash on the missing server variable.
    if (!isset($_SERVER['HTTP_REFERER'])) {
        json_response(200, get_alert(true), NULL);
        return;
    }

    $html = getTabHtml($id, 'comments-list');

    json_response(200, get_alert(true), $html);
}

/********************************************
 * FUNCTION: MANAGEMENT - Accept Mitigation *
 ********************************************/
function acceptMitigationForm($id = null)
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
    elseif ($id === null && !isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    else
    {
        $id = (int)($id ?? $_GET['id']);

        // Check team separation access
        if (!check_access_for_risk($id))
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForRiskManagement']));
            json_response(403, get_alert(true), NULL);
        }

        $accept = (int)$_POST['accept'];

        // Check if user has a permission for accept mitigation
        if(empty($_SESSION["accept_mitigation"])){
            set_alert(true, "bad", "You do not have permission to accept mitigation.  Please contact an Administrator if you feel that you have reached this message in error.");
            // Return a JSON response
            json_response(400, get_alert(true), NULL);
        }
    
        accept_mitigation_by_risk_id($id, $accept);

        // Send the notification (no-op if notification extra is disabled)
        call_extra_function(
            'notification_extra',
            __DIR__ . '/../extras/notification/index.php',
            'notify_mitigation_update',
            [$id - 1000]
        );

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
function scoringHistory($id = null)
{
    global $escaper, $lang;

    if (!check_permission("riskmanagement"))
    {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    // If the risk id is sent
    if ($id !== null || isset($_GET['id']))
    {
        //sleep(3);
        $risk_id = $id ?? $_GET['id'];

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
function residualScoringHistory($id = null)
{
    global $escaper, $lang;

    if (!check_permission("riskmanagement"))
    {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    // If the risk id is sent
    if ($id !== null || isset($_GET['id']))
    {
        //sleep(3);
        $risk_id = $id ?? $_GET['id'];

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
function updateRisk($id = null){
    global $lang, $escaper;

    // PHP only auto-populates $_POST for POST requests; parse the body for PATCH.
    // Never gate this on empty($_POST) -- csrf-magic leaves the CSRF token in
    // $_POST on any session-authenticated call, which makes that guard false and
    // silently drops the entire body while still answering 200.
    parse_non_post_body_into_post();

    // If the id is not sent
    if ($id === null && get_param("POST", 'id', false) === false)
    {
        $status = "400";
        $status_message = $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']);
        // Return a JSON response
        json_response($status, $status_message, NULL);
    }

    $id = $id ?? get_param("POST", 'id');

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

    // Only a subject the caller ACTUALLY SENT can be rejected for being blank.
    // An absent one means "leave it alone" -- the same PATCH convention the
    // junction-table handling below follows, and the reason the assignment
    // above defaults to false rather than ''.
    //
    // The previous condition carried a second clause, `|| !trim($new_subject)`,
    // which defeated that sentinel outright: trim(false) is '', so !trim(false)
    // is TRUE and an OMITTED subject 400'd with "The subject of a risk cannot be
    // empty". Every partial update therefore had to resend the subject it was
    // not changing -- and since the column is encrypted at rest, a caller doing
    // GET-then-PATCH had to round-trip a decrypted value purely to satisfy a
    // validation rule. addRisk()'s bare !trim($subject) is NOT the same bug:
    // creating a risk genuinely does require a subject.
    //
    // A sent-but-blank subject (''  or '   ') is still refused, which is the
    // rule this check exists to enforce.
    if ($new_subject !== false && trim($new_subject) === "") {
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
    
    $issue_key = null;

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

        // Send the notification (no-op if notification extra is disabled)
        call_extra_function(
            'notification_extra',
            __DIR__ . '/../extras/notification/index.php',
            'notify_new_risk',
            [$last_insert_id]
        );

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
function saveMitigation($id = null){
    global $lang, $escaper;

    // PHP only auto-populates $_POST for POST requests; parse the body for PATCH.
    // Never gate this on empty($_POST) -- csrf-magic leaves the CSRF token in
    // $_POST on any session-authenticated call, which makes that guard false and
    // silently drops the entire body while still answering 200.
    parse_non_post_body_into_post();

    $data = array();
    $id = $id ?? get_param("POST", "id", false);

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
    // If the user has access to the risk
    elseif($access){
        $mitigation_id = $risk[0]['mitigation_id'];

        // Build $post from the fields the caller ACTUALLY sent, rather than
        // materialising every key with a zero default.
        //
        // This is what makes the update below a partial update. Every default
        // here is a destructive value (0 selects "none" for the strategy,
        // effort, cost and owner; "" blanks the free text; [] drops the team
        // junction), and update_mitigation() used to bind all of them
        // unconditionally -- so a PATCH naming one field reset the whole
        // mitigation. Omitting the unsent keys lets update_mitigation()'s
        // $is_api filter leave those columns out of the UPDATE entirely.
        //
        // An explicitly-sent empty value still lands in $post and still clears
        // the field: the rule is absence-preserves, not emptiness-preserves.
        $mitigation_fields = array(
            'planning_strategy',
            'mitigation_effort',
            'mitigation_cost',
            'mitigation_owner',
            'mitigation_team',
            'current_solution',
            'security_requirements',
            'security_recommendations',
            'planning_date',
            'mitigation_percent',
        );

        $post = array();
        foreach ($mitigation_fields as $mitigation_field) {
            if (param_was_sent($mitigation_field)) {
                $post[$mitigation_field] = get_param("POST", $mitigation_field);
            }
        }

        // If we don't yet have a mitigation
        if (!$mitigation_id)
        {
            $status = "Mitigation Planned";

            // A create legitimately falls back to the defaults for anything the
            // caller left out -- there is no stored value to preserve.
            $mitigation_date = submit_mitigation($id, $status, $post);
        }
        else
        {
            // Update mitigation and get the mitigation date back. $is_api = true
            // selects partial-update semantics.
            $mitigation_date = update_mitigation($id, $post, true);
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
function saveReview($id = null){
    global $lang, $escaper;

    $id = $id ?? get_param("POST", "id", false);
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
    if (!$access) {
        $status = 400;
        $status_message = $escaper->escapeHtml($lang['NoPermissionForRiskManagement']);
        return json_response($status, $status_message, $data);
    }

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
    }
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

    $status = 200;
    $status_message = $lang['Success'];
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

    // This handler had only the route's authentication wrapper and an
    // Extra-activation check — activation is not a permission, so any logged-in
    // user could delete an import/export field mapping. Import/export mapping
    // management lives on admin/importexport.php, which renders behind
    // check_admin, so admin is the permission the UI itself enforces.
    if (!is_admin())
    {
        unauthorized_access();
        return;
    }

    $status = null;
    $status_message = null;

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

    // This handler had only the route's authentication wrapper and no
    // permission gate at all, so any logged-in user could overwrite an
    // assessment's questions, answers and scoring. assessments is the module
    // permission its own caller runs behind — assessments/assessment.php, which
    // loads pages/assessment.js (the only caller of this endpoint), renders
    // behind check_assessments → enforce_permission('assessments').
    if (!check_permission('assessments'))
    {
        unauthorized_access();
        return;
    }

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
function getFrameworkControlsDatatable() {

    global $lang;
    global $escaper;

    $active_fields = [];

    // Get the active control fields (returns [] if customization extra is disabled)
    $active_fields = call_extra_function(
        'customization_extra',
        __DIR__ . '/../extras/customization/index.php',
        'get_active_fields',
        ["control", "", 2],
        []
    );

    // If the user has governance permissions
    if (check_permission("governance")) {
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
        
        $recordsTotal = get_framework_controls_count();
        $recordsFiltered = count($controls);

        $data = array();

        foreach ($controls as $key=>$control) {
            // If it is not requested to view all.
            if($_POST['length'] != -1) {
                if($key < $_POST['start']) {
                    continue;
                }
                if($key >= ($_POST['start'] + $_POST['length'])) {
                    break;
                }
            }
            $edit = "<a href='#' class='btn btn-success ms-1 control-block--edit' title='{$escaper->escapeHtml($lang['Edit'])}' data-id='{$escaper->escapeHtml($control['id'])}'><i class='fa fa-edit'></i></a>";
            // Remove clone button if user has no permission for add new controls
            if(empty($_SESSION['add_new_controls'])) {
                $clone = "";
            } else { // Add clone button if user has the permission
                $clone = "<a href='#' class='btn btn-submit ms-1 control-block--clone' title='{$escaper->escapeHtml($lang['Clone'])}' data-id='{$escaper->escapeHtml($control['id'])}'><i class='fa fa-clone'></i></a>";
            }
            $delete = "<a href='' class='btn btn-primary control-block--delete' title='{$escaper->escapeHtml($lang['Delete'])}' data-id='{$escaper->escapeHtml($control['id'])}'><i class='fa fa-trash'></i></a>";
            $html = "
                <div class='control-block item-block clearfix'>
                    <div class='control-block--header clearfix' data-project='' style='padding: 1.25rem;'>
                        <div class='row mb-2'>
                            <div class='col-sm-12 col-md-8 checkbox-in-div'>
                                <input type='checkbox' name='control_ids[]' value='{$escaper->escapeHtml($control['id'])}' class='form-check-input'>
                                <span>This control is marked for deletion</span>
                            </div> 
                            <div class='col-sm-12 col-md-4 text-end control-block--row'>
                                {$delete}{$clone}{$edit}
                            </div>
                        </div>
                        <div class='control-block--row control-content pb-0'>
            ";
            if (customization_extra()) {
                $html .= "
                            <div class='row'>
                                <div class='col-12 top-panel'>" . 
                                    display_detail_control_fields_view('top', $active_fields, $control) . "
                                </div>
                            </div>
                            <div class='row'>
                                <div class='col-6 left-panel'>" . 
                                    display_detail_control_fields_view('left', $active_fields, $control) . "
                                </div>
                                <div class='col-6 right-panel'>" . 
                                    display_detail_control_fields_view('right', $active_fields, $control) . "
                                </div>
                            </div>
                            <div class='row'>
                                <div class='col-12 bottom-panel'>" . 
                                    display_detail_control_fields_view('bottom', $active_fields, $control) . "
                                </div>
                            </div>
                ";
            } else {
                $html .= "
                            <div class='row'>
                                <div class='col-12 top-panel'>" . 
                                    display_control_id_view($control['id'], 'top') .
                                    display_control_name_view($control['short_name'], 'top') . 
                                    display_control_longname_view($control['long_name'], 'top') . 
                                    display_control_number_view2($control['control_number'], 'top') . "
                                </div>
                            </div>
                            <div class='row'>
                                <div class='col-6 left-panel'>" . 
                                    display_control_owner_view($control['control_owner_name'], 'left') . 
                                    display_control_priority_view($control['control_priority_name'], 'left') . 
                                    display_current_maturity_view($control['control_maturity_name'], 'left') . 
                                    display_desired_maturity_view($control['desired_maturity_name'], 'left') . 
                                    display_control_class_view($control['control_class_name'], 'left') . "
                                </div>
                                <div class='col-6 right-panel'>" . 
                                    display_control_phase_view($control['control_phase_name'], 'right') . 
                                    display_control_family_view($control['family_short_name'], 'right') . 
                                    display_control_mitigation_percent_view($control['mitigation_percent'], 'right') . 
                                    display_control_type_view($control['control_type_ids'], 'right') . 
                                    display_control_status_view($control['control_status'], 'right') . "
                                </div>
                            </div>
                            <div class='row'>
                                <div class='col-12 bottom-panel'>" . 
                                    display_control_description_view($control['description'], 'bottom') . 
                                    display_supplemental_guidance_view($control['supplemental_guidance'], 'bottom') . 
                                    display_mapping_framework_view($control['id'], 'bottom') . 
                                    display_mapping_asset_view($control['id'], 'bottom') . "
                                </div>
                            </div>
                ";
            }
            $html .= "
                        </div>
                    </div>
                </div>
            ";
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
            'recordsFiltered' => $recordsFiltered,
            'classList' => $classList ,
            'phaseList' => $phaseList ,
            'familyList' => $familyList ,
            'ownerList' => $ownerList ,
            'priorityList' => $priorityList ,
        );
        echo json_encode($result);
        exit;
    } else {
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
        $draw = $escaper->escapeHtml($_POST['draw']);
        $flag = $escaper->escapeHtml($_POST['flag']);
        $mitigation_id = $escaper->escapeHtml($_POST['mitigation_id']);
        $control_ids = $_POST['control_ids'];
        $control_id_array = str_getcsv($control_ids, ',', '"', '');
    
        $controls = get_framework_controls($control_ids);
    
        $recordsTotal = count($controls);
    
        $data = array();
    
        foreach ($controls as $key=>$control) {
            // If it is not requested to view all.
            if($_POST['length'] != -1) {
                if($key < $_POST['start']) {
                    continue;
                }
                if($key >= ($_POST['start'] + $_POST['length'])) {
                    break;
                }
            }
            $html = "
                <div class='control-block item-block clearfix'>
                    <div class='card-body border clearfix' data-project=''>
                        <br>
                        <div class='control-block--row'>
                            <table width='100%'>
                                <tr>
                                    <td width='13%' align='right'><strong>" . $escaper->escapeHtml($lang['ControlLongName']) . "</strong>: </td>
                                    <td colspan='5'>" . $escaper->escapeHtml($control['long_name']) . "</td>
                                </tr>
                                <tr>
                                    <td width='13%' align='right'><strong>" . $escaper->escapeHtml($lang['ControlShortName']) . "</strong>: </td>
                                    <td width='57%' colspan='3'>" . $escaper->escapeHtml($control['short_name']) . "</td>
                                    <td width='13%' align='right' ><strong>" . $escaper->escapeHtml($lang['ControlOwner']) . "</strong>: </td>
                                    <td width='17%'>" . $escaper->escapeHtml($control['control_owner_name']) . "</td>
                                </tr>
                                <tr>
                                    <td  align='right'><strong>" . $escaper->escapeHtml($lang['ControlClass']) . "</strong>: </td>
                                    <td>" . $escaper->escapeHtml($control['control_class_name']) . "</td>
                                    <td  align='right'><strong>" . $escaper->escapeHtml($lang['ControlPhase']) . "</strong>: </td>
                                    <td>" . $escaper->escapeHtml($control['control_phase_name']) . "</td>
                                    <td  align='right'><strong>" . $escaper->escapeHtml($lang['ControlNumber']) . "</strong>: </td>
                                    <td>" . $escaper->escapeHtml($control['control_number']) . "</td>
                                </tr>
                                <tr>
                                    <td align='right'><strong>" . $escaper->escapeHtml($lang['ControlPriority']) . "</strong>: </td>
                                    <td>" . $escaper->escapeHtml($control['control_priority_name']) . "</td>
                                    <td width='200px' align='right'><strong>" . $escaper->escapeHtml($lang['ControlFamily']) . "</strong>: </td>
                                    <td>" . $escaper->escapeHtml($control['family_short_name']) . "</td>
                                    <td width='200px' align='right'><strong>" . $escaper->escapeHtml($lang['MitigationPercent']) . "</strong>: </td>
                                    <td>" . $escaper->escapeHtml($control['mitigation_percent']) . "%</td>
                                </tr>
                                <tr>
                                    <td align='right'><strong>" . $escaper->escapeHtml($lang['Description']) . "</strong>: </td>
                                    <td colspan='5'>" . $escaper->purifyHtml($control['description']) . "</td>
                                </tr>
                                <tr>
                                    <td align='right'><strong>" . $escaper->escapeHtml($lang['SupplementalGuidance']) . "</strong>: </td>
                                    <td colspan='5'>" . $escaper->purifyHtml($control['supplemental_guidance']) . "</td>
                                </tr>
                            </table>
            ";

            $html .= display_mapping_framework_view($control['id']);

            /*
            $mapped_frameworks = get_mapping_control_frameworks($control['id']);
            $html .= "
                            <div class='mt-3'>
                                <div class='well'>
                                    <h5><span>" . $escaper->escapeHtml($lang['MappedControlFrameworks']) . "</span></h5>
                                    <table width='100%' class='table table-bordered'>
                                        <tr>
                                            <th width='50%'>" . $escaper->escapeHtml($lang['Framework']) . "</th>
                                            <th width='35%'>" . $escaper->escapeHtml($lang['Control']) . "</th>
                                        </tr>
            ";

            foreach ($mapped_frameworks as $framework) {
                $html .= "
                                        <tr>
                                            <td>" . $escaper->escapeHtml($framework['framework_name']) . "</td>
                                            <td>" . $escaper->escapeHtml($framework['reference_name']) . "</td>
                                        </tr>
                ";
            }

            $html .= "
                                    </table>
                                </div>
                            </div>
                        </div>
            ";
            */

            $validation = get_mitigation_to_controls($mitigation_id,$control['id']);
            $control_status_names = get_names_by_multi_values("control_type", $control['control_type_ids']);
            $files = get_validation_files($mitigation_id, $control['id']);

            $html .= "
                        <div>
            ";

            $validation_details = isset($validation["validation_details"]) ? $validation["validation_details"] : "";
            $validation_owner = isset($validation["validation_owner"]) ? $validation["validation_owner"] : 0;
            $validation_mitigation_percent = (isset($validation["validation_details"]) && $validation["validation_mitigation_percent"] >= 0 && $validation["validation_mitigation_percent"] <= 100) ? $validation["validation_mitigation_percent"] : 0;
            
            if($flag == "edit") {
                if($validation_mitigation_percent && $validation_details != "") {
                    $arrow_class = "fa-caret-down";
                    $panel_css ="";
                } else {
                    $arrow_class = "fa-caret-right";
                    $panel_css ="display: none;";
                }

                $html .= "
                            <div class='well accordion'>
                                <div class='accordion-item'>
                                    <h2 class='accordion-header'>
                                        <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#control-validation-accordion-body'>" . $escaper->escapeHtml($lang['ControlValidation']) . "</button>
                                    </h2>
                                    <div id='control-validation-accordion-body' class='accordion-collapse collapse'>
                                        <div class='accordion-body'>
                                            <div class='row mb-2'>
                                                <div class='span4'>
                                                    <label>" . $escaper->escapeHtml($lang['Details']) . ":</label>
                                                    <textarea class='form-control active-textfield' title='" . $escaper->escapeHtml($lang['Details']) . "' name='validation_details_" . $control['id'] . "' style='width:100%;' rows='3'>" . $escaper->escapeHtml($validation_details) . "</textarea>
                                                </div>
                                            </div>
                                            <div class='row mb-2'>
                                                <div class='span4'>
                                                    <label>" . $escaper->escapeHtml($lang['Owner']) . ":</label>" . 
                                                    create_dropdown("enabled_users", $validation_owner, "validation_owner_".$control['id'], true, false, true) . "
                                                </div>
                                            </div>
                                            <div class='row mb-2'>
                                                <div class='span4'>
                                                    <label>" . $escaper->escapeHtml($lang['MitigationPercent']) . ":</label>
                                                    <input type='number' min='0' max='100' name='validation_mitigation_percent_" . $control['id'] . "' value='" . $escaper->escapeHtml($validation_mitigation_percent) . "' size='50' class='form-control percent active-textfield' />
                                                </div>
                                            </div>
                                            <div class='row mb-2'>
                ";

                $exist_files = "";
                foreach ($files as $file) {
                    $exist_files .= "
                        <li>
                            <div class='file-name'>
                                <a href='download.php?id=" . $escaper->escapeHtml($file['id']) . "&file_type=validation_file' target='_blank' />" . $escaper->escapeHtml($file['name']) . "</a>
                            </div>
                            <a href='#' class='remove-file' ><i class='fa fa-times'></i></a>
                            <input type='hidden' name='file_ids_" . $control['id'] . "[]' value='" . $escaper->escapeHtml($file['id']) . "'>
                        </li>
                    ";
                }
                $html .= "
                                                <div class='span4'>
                                                    <label>" . $escaper->escapeHtml($lang['UploadArtifact']) . ":</label>
                                                    <div class='file-uploader'>
                                                        <div class='file_name' data-file='artifact-file-" . $control['id'] . "'></div>
                                                        <script>
                                                            var max_upload_size = " . $escaper->escapeJs(get_setting('max_upload_size', 0)) . ";
                                                            var fileTooBigMessage = '" . $escaper->escapeJs($lang['FileIsTooBigToUpload']) . "';
                                                        </script>
                                                        <label for='artifact-file-upload-" . $control['id'] . "' class='btn btn-primary active-textfield'>" . $escaper->escapeHtml($lang['ChooseFile']) . "</label> 
                                                        <span class='file-count-html'><span class='file-count'>" . count($files) . "</span> " . $escaper->escapeHtml($lang['FileAdded']) . "</span>
                                                        <p><font size='2'><strong>Max " . $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)) . " Mb</strong></font></p>
                                                        <ul class='exist-files'>" . $exist_files . "</ul>
                                                        <ul class='file-list'></ul>
                                                        <input type='file' name='artifact-file-" . $control['id'] . "[]' id='artifact-file-upload-" . $control['id'] . "' class='hidden-file-upload active d-none' />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class='row mb-2'>
                                                <div class='span4'>
                                                    <label class='m-r-20'>" . $escaper->escapeHtml($lang['ControlType']) . ": </label>" . 
                                                    $escaper->escapeHtml($control_status_names) . "
                                                </div>
                                            </div>
                ";

                if(strpos($control_status_names, "Enterprise") !== false) {
                    $control_status = array("1" => $escaper->escapeHtml($lang["Pass"]), "0" => $escaper->escapeHtml($lang["Fail"]), "2" => $escaper->escapeHtml($lang["NotTested"]));
                    
                    $html .= "
                                            <div class='row mb-2'>
                                                <div class='span4'>" . 
                                                    $escaper->escapeHtml($lang['ControlStatus']) . ": " . $control_status[$control['control_status']] . "
                                                </div>
                                            </div>
                    ";
                }

                $html .= "
                                        </div>
                                    </div>
                                </div>
                            </div>
                ";
            }

            if($flag == "view" && ($validation_details || $validation_details || $validation_mitigation_percent)) {
                
                $html .= "
                            <div class='well'>
                                <h5><span>" . $escaper->escapeHtml($lang['ControlValidation']) . "</span></h5>
                                <div class='row mb-2'>
                                    <div class='span4'>
                                        <label class='m-r-20'>" . $escaper->escapeHtml($lang['Details']) . ":</label>&nbsp;" . 
                                        nl2br($escaper->escapeHtml($validation_details)) . "
                                    </div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='span4'>
                                        <label class='m-r-20'>" . $escaper->escapeHtml($lang['Owner']) . ":</label>&nbsp;" . 
                                        $escaper->escapeHtml(get_name_by_value("user", $validation_details)) . "
                                    </div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='span4'>
                                        <label class='m-r-20'>" . $escaper->escapeHtml($lang['MitigationPercent']) . ":</label>&nbsp;" . 
                                        $escaper->escapeHtml($validation_mitigation_percent) . " %
                                    </div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='span4'>
                                        <label class='m-r-20'>" . $escaper->escapeHtml($lang['UploadArtifact']) . ":</label>&nbsp;
                ";

                foreach ($files as $file)
                {
                    $html .= "
                                        <div class ='doc-link edit-mode'>
                                            <a href='download.php?id=" . $escaper->escapeHtml($file['id']) . "&file_type=validation_file' >" . $escaper->escapeHtml($file['name']) . "</a>
                                        </div>
                    ";
                }
    
                $html .= "
                                    </div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='span4'>
                                        <label class='m-r-20'>" . $escaper->escapeHtml($lang['ControlType']) . ": </label>" . 
                                        $escaper->escapeHtml(get_names_by_multi_values("control_type", $control['control_type_ids'])) . "
                                    </div>
                                </div>
                ";
              
                if(strpos($control_status_names, "Enterprise") !== false) {
                    $control_status = array("1" => $escaper->escapeHtml($lang["Pass"]), "0" => $escaper->escapeHtml($lang["Fail"]), "2" => $escaper->escapeHtml($lang["NotTested"]));
                
                    $html .= "
                                <div class='row mb-2'>
                                    <div class='span4'>
                                        <label class='m-r-20'>" . $escaper->escapeHtml($lang['ControlStatus']) . ": </label>" . 
                                        $control_status[$control['control_status']] . "
                                    </div>
                                </div>
                    ";
                }

                $html .= "
                            </div>
                ";
            }

            $html .= "
                        </div>
                    </div>
                </div>
            ";
            
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

/*****************************************
 * FUNCTION: UPDATE FRAMEWORK RESPONSE *
 ****************************************/
function updateFrameworkResponse() {

    global $lang, $escaper;

    $framework_id = get_param("POST", "framework_id", "");
    $name         = get_param("POST", "framework_name", "");
    $descripiton  = get_param("POST", "framework_description", "");
    $parent       = get_param("POST", "parent", "");

    // Check if user has a permission to modify framework
    if (check_permission('modify_frameworks')) {
        if (update_framework($framework_id, $name, $descripiton, $parent)) {
            set_alert(true, "good", $lang['FrameworkUpdated']);
            json_response(200, get_alert(true), []);
        } else {
            json_response(400, get_alert(true), []);
        }
    } else {
        set_alert(true, "bad", $lang['NoModifyFrameworkPermission']);
        json_response(400, get_alert(true), []);
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
        'short_name' => isset($_POST['short_name']) ? trim($_POST['short_name']) : "",
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
        'control_status' => isset($_POST['control_status']) ? (int)$_POST['control_status'] : 2,
        'family' => isset($_POST['family']) ? (int)$_POST['family'] : 0,
        'mitigation_percent' => (isset($_POST['mitigation_percent']) && $_POST['mitigation_percent'] >= 0 && $_POST['mitigation_percent'] <= 100) ? (int)$_POST['mitigation_percent'] : 0
    );
    $map_framework_ids = isset($_POST['map_framework_id'])?$_POST['map_framework_id']:array();
    $reference_names = isset($_POST['reference_name'])?$_POST['reference_name']:array();
    $reference_texts = isset($_POST['reference_text'])?$_POST['reference_text']:array();
    $map_frameworks = array();
    foreach($map_framework_ids as $index=>$frameworks){
        $reference_name = isset($reference_names[$index])?$reference_names[$index]:"";
        // NULL, not "", when the request said NOTHING about this row's clause
        // text -- see updateControlResponse() below for why. Harmless on this
        // create path (there is no stored text a new control could lose), but
        // both handlers build the same row shape for the same function and a
        // divergence between them is the next person's trap.
        $reference_text = isset($reference_texts[$index])?$reference_texts[$index]:null;
        $map_frameworks[] = array($frameworks,$reference_name,$reference_text);
    }
    $control["map_frameworks"] = $map_frameworks;
    $asset_maturity  = isset($_POST['asset_maturity']) ? $_POST['asset_maturity'] : [];
    $assets_asset_groups = isset($_POST['assets_asset_groups']) ? $_POST['assets_asset_groups'] : [];
    $mapped_assets = array();
    foreach($asset_maturity as $index=>$maturity){
        if($assets_asset_groups[$index]) $mapped_assets[] = array($maturity, $assets_asset_groups[$index]);
    }
    $control["mapped_assets"] = $mapped_assets;
    $control_id = "";

    // Check if the control name is null
    if (!$control['short_name'])
    {
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['TheControlNameCannotBeEmpty']));
        json_response(400, get_alert(true), null);
        return;
    }

    // If user has no permission for add new controls
    if(empty($_SESSION['add_new_controls']))
    {
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoAddControlPermission']));
        json_response(400, get_alert(true), null);
        return;
    }

    // Insert a new control up to 100 chars
    if($control_id = add_framework_control($control))
    {
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['ANewControlWasAddedSuccessfully']));
        json_response(200, get_alert(true), array("control_id"=>$control_id));
    }
    else
    {
        // Display an alert
        json_response(400, get_alert(true), null);
    }

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
            'short_name' => isset($_POST['short_name']) ? trim($_POST['short_name']) : "",
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
            // Set UNCONDITIONALLY, even to [], and that is correct HERE -- unlike
            // updateControlById() further down this file, which omits the key
            // unless the request proves it carried a submission. This is the
            // legacy whole-form POST: it is only ever reached from a page that
            // rendered the complete Edit Control form, so an absent
            // control_type[] genuinely means "the user deselected every type",
            // not "this request said nothing about types". Same reasoning
            // delete_control_to_frameworks_except() is deliberately not called
            // from update_framework_control() -- see its docblock in
            // includes/governance.php.
            'control_type' => isset($_POST['control_type']) ? (array)$_POST['control_type'] : [],
            'control_status' => isset($_POST['control_status']) ? (int)$_POST['control_status'] : 2,
            'family' => isset($_POST['family']) ? (int)$_POST['family'] : 0,
            'mitigation_percent' => (isset($_POST['mitigation_percent']) && $_POST['mitigation_percent'] >= 0 && $_POST['mitigation_percent'] <= 100) ? (int)$_POST['mitigation_percent'] : 0
        );
        $map_framework_ids = isset($_POST['map_framework_id'])?$_POST['map_framework_id']:array();
        $reference_names = isset($_POST['reference_name'])?$_POST['reference_name']:array();
        $reference_texts = isset($_POST['reference_text'])?$_POST['reference_text']:array();
        $map_frameworks = array();
        foreach($map_framework_ids as $index=>$frameworks){
            // reference_name STAYS "": the column is varchar(255) NOT NULL and part
            // of framework_control_mappings' only UNIQUE key, so there is no null to
            // mean "unsaid" with. A missing element here is a malformed request (the
            // input is `required` on the form that posts this), and it produces a
            // junk row rather than destroying one -- noted, not fixed here.
            $reference_name = isset($reference_names[$index])?$reference_names[$index]:"";
            // NULL, not "", when the request said NOTHING about this row's clause
            // text. This is an UPDATE, so "" was an instruction that landed: an API
            // client sending map_framework_id[] with three entries and a
            // reference_text[] with one -- or none at all, which the OpenAPI shape
            // permits -- blanked the stored clause text on the rows past the end of
            // the array. save_control_to_frameworks() now COALESCEs, so NULL means
            // preserve; "" still clears, which is what an emptied <textarea> sends.
            $reference_text = isset($reference_texts[$index])?$reference_texts[$index]:null;
            $map_frameworks[] = array($frameworks,$reference_name,$reference_text);
        }
        $control["map_frameworks"] = $map_frameworks;
        $asset_maturity  = isset($_POST['asset_maturity']) ? $_POST['asset_maturity'] : [];
        $assets_asset_groups = isset($_POST['assets_asset_groups']) ? $_POST['assets_asset_groups'] : [];
        $mapped_assets = array();
        foreach($asset_maturity as $index=>$maturity){
            if($assets_asset_groups[$index]) $mapped_assets[] = array($maturity, $assets_asset_groups[$index]);
        }
        $control["mapped_assets"] = $mapped_assets;

        // Check if the control name is null
        if (!$control['short_name'])
        {
            // Display an alert
            set_alert(true, "bad", "The control name cannot be empty.");
            json_response(400, get_alert(true), []);

        } else {

            // Update the control
            update_framework_control($control_id, $control);
    
            // Display an alert
            set_alert(true, "good", "An existing control was updated successfully.");
    
            json_response(200, get_alert(true), array("control_id"=>$control_id));

        }
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

        $html = "<select name='parent' class='form-select' title='{$escaper->escapeHtml($lang['ParentFramework'])}'>\n";
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

        $html = "
            <select name='parent' class='form-select'>
                <option value='0'>--</option>";
                make_tree_options_html($options, 0, $html);
        $html .= "
            </select>";
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

        $html = "<select name='parent' class='form-select'>\n";
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

        $html = "
            <select name='parent' class='form-select'>
                <option value='0'>--</option>";
                make_tree_options_html($new_documents, 0, $html, "", $selected);
        $html .= "
            </select>";
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
        $control_framework = isset($_POST['control_framework']) ? $_POST['control_framework'] : [];

        // $with_counts=true (the 2nd arg) opts into the COUNT(DISTINCT t1.id)
        // column each *List() function can now return -- see
        // getAvailableControlClassList() (includes/governance.php) for why this
        // is opt-in rather than the default: getFrameworkControlsDatatable()'s
        // OWN 5 calls to these same functions stay at the 1-arg default so its
        // response shape is untouched.
        $classList  = getAvailableControlClassList($control_framework, true);
        $phaseList  = getAvailableControlPhaseList($control_framework, true);
        $familyList  = getAvailableControlFamilyList($control_framework, true);
        $ownerList  = getAvailableControlOwnerList($control_framework, true);
        $priorityList  = getAvailableControlPriorityList($control_framework, true);
        // Added for the Define Control Frameworks filter sheet's Type facet
        // (governance-frameworks.js) -- getAvailableControlTypeList() used to
        // be an empty-query stub, so this key was never populated before.
        // No opt-in flag needed here: nothing consumed this function's output
        // before (it always returned []), so there is no prior shape to preserve.
        $typeList  = getAvailableControlTypeList($control_framework);

        // Two aggregates no option LIST can carry (Task 29):
        //
        //  - unassignedCounts: every *List() query above ends its WHERE with
        //    "t2.value is not null", which discards exactly the controls that
        //    have no family/owner/class/phase/priority/type -- so the
        //    Unassigned(-1) option the client prepends could never have had a
        //    count. get_control_facet_unassigned_counts() counts that bucket
        //    with get_framework_controls_by_filter()'s OWN unassigned
        //    predicate, so the chip and the filter agree by construction.
        //
        //  - statusCounts: Status has no lookup table to build a list from
        //    (three computed tokens), so there was no aggregate to report at
        //    all. Derived from control_status_token_map(), the same map
        //    controls_table_status_to_db() filters with.
        //
        //  - maturityCounts (Task 34): Maturity is the same shape of facet as
        //    Status -- three computed buckets, no lookup table. Bucketed by
        //    control_maturity_bucket(), the same function the table's own
        //    maturity filter (controls_table_apply_maturity_applicability(),
        //    api/v2/includes/governance_controls.php) compares with, so the
        //    chip equals what filtering by it returns.
        //
        // All three are framework-scoped by control_framework_scope_sql(), the
        // same single definition the six lists above scope with -- an option
        // list and its chip must never be scoped differently.
        //
        // These are NEW keys alongside the existing six lists; nothing about
        // the lists' own shape changes, so existing consumers are unaffected.
        $unassignedCounts = get_control_facet_unassigned_counts($control_framework);
        $statusCounts = get_control_status_counts($control_framework);
        $maturityCounts = get_control_maturity_counts($control_framework);

        $result = array(
            'classList' => $classList ,
            'phaseList' => $phaseList ,
            'familyList' => $familyList ,
            'ownerList' => $ownerList ,
            'priorityList' => $priorityList ,
            'typeList' => $typeList ,
            'unassignedCounts' => $unassignedCounts ,
            'statusCounts' => $statusCounts ,
            'maturityCounts' => $maturityCounts ,
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
            $fids_arr = str_getcsv($fids, ',', '"', '');
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

        $new_audit_id = null;
        if($name = initiate_framework_control_tests($type, $id, $tags, $new_audit_id))
        {
            if($type == 'framework'){
                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedAllTestsUnderFramework', ['framework' => $name])));
            }elseif($type == 'control'){
                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedAllTestsUnderControl', ['control' => $name])));
            }elseif($type == 'test'){
                set_alert(true, "good", $escaper->escapeHtml(_lang('InitiatedTest', ['test' => $name])));
            }
            // For a single test, return the new audit id so the caller can jump
            // straight to it (null for framework/control, which create many).
            json_response(200, get_alert(true), ['audit_id' => $new_audit_id]);
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

        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumn = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumnName = isset($_GET['columns'][$orderColumn]['name']) ? $_GET['columns'][$orderColumn]['name'] : null;
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
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
            
            $active_audits_url = build_url('compliance/active_audits.php?test_id='.$audit_test['id']);
            $past_audits_url = build_url('compliance/past_audits.php?test_id='.$audit_test['id']);
            $buttons = '<button class="btn btn-primary btn-initiate-audit" style="width:100%" id="'.$audit_test['id'].'">'.$escaper->escapeHtml($lang['InitiateAudit']).'</button>
                        <a class="btn btn-secondary my-1" style="width:100%" type="button" href="'.$active_audits_url.'" target="_blank"><i class="mdi mdi-open-in-new mx-2"></i>'.$escaper->escapeHtml($lang['ViewActiveAudits']).'</a>
                        <a class="btn btn-secondary" style="width:100%" type="button"href="'.$past_audits_url.'" target="_blank"><i class="mdi mdi-open-in-new mx-2"></i>'.$escaper->escapeHtml($lang['ViewPastAudits']).'</a>';

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
    // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output; build_url() called with hardcoded paths and DB integer IDs
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
        $control['description'] = utf8ize($control['description']);

        // Purify the rich-text fields at this output boundary — they feed the
        // Define-Control edit modal, which renders them raw into the WYSIWYG editor.
        $control['description'] = purify_rich_text_output($control['description'] ?? '');
        $control['supplemental_guidance'] = purify_rich_text_output($control['supplemental_guidance'] ?? '');

        if (!empty($control['custom_values'])) {
            foreach ($control['custom_values'] as &$custom_value) {
                switch ($custom_value['field_type']) {
                    case 'shorttext':
                    case 'longtext':
                        // If encryption for this field is enabled, decrypt value
                        if ($custom_value['encryption']) {
                            $custom_value['value'] = try_decrypt($custom_value['value']);
                        }
                        break;
                    case 'date':
                        $custom_value['value'] = $custom_value['value'] ? format_date($custom_value['value']) : '';
                        break;
                    case 'multidropdown':
                    case 'user_multidropdown':
                        $custom_value['value'] = $custom_value['value'] ? explode(',', $custom_value['value']) : '';
                        break;
                }
            }
        }
        $mapped_frameworks = get_mapping_control_frameworks($id);
        $frameworks_html = "";
        foreach ($mapped_frameworks as $framework){
            $frameworks_html .= "<tr>\n";
                // id: false -- one of these is emitted PER MAPPING ROW, and the
                // #add_mapping_row template (display_add_mapping_row(),
                // includes/governance.php) renders the same field again, so a
                // derived id is duplicated by construction. Nothing addresses
                // these by id: `[]` makes the id unusable as a `#` selector, and
                // every reader scopes by name within the row's own table.
                $frameworks_html .= "<td>".create_dropdown('frameworks', $framework['framework'],'map_framework_id[]', true, false, true, 'required', id: false)."</td>\n";
                $frameworks_html .= "<td><input type='text' name='reference_name[]' value='".$escaper->escapeHtml($framework['reference_name'])."' class='form-control' maxlength='100' required></td>\n";
                // The framework's own title for the control it cites -- the
                // Statement of Applicability's Name column. Nullable, and NULL
                // on every row that predates the column, so it is coalesced
                // rather than escaped straight out of the array.
                $frameworks_html .= "<td><input type='text' name='reference_subject[]' value='".$escaper->escapeHtml($framework['reference_subject'] ?? '')."' class='form-control' maxlength='1000' title='".$escaper->escapeHtml($lang['ReferenceSubjectHint'])."' placeholder='".$escaper->escapeHtml($lang['ReferenceSubject'])."'></td>\n";
                $frameworks_html .= "<td><textarea rows='3' cols='50' name='reference_text[]' class='form-control'>".$escaper->escapeHtml($framework['reference_text'])."</textarea>\n";
                $frameworks_html .= "<td class='text-center'><a href='javascript:void(0);' class='control-block--delete-mapping' title='".$escaper->escapeHtml($lang["Delete"])."'><i class='fa fa-trash'></i></a></td>\n";
            $frameworks_html .= "</tr>\n";
        }
        $mapped_assets = get_control_to_assets($id);
        $assets_html = "";
        foreach ($mapped_assets as $assets){
            $assets_html .= "<tr>\n";
                // id: false -- per-row, same reasoning as the framework <select>
                // above.
                $assets_html .= "<td>".create_dropdown("control_maturity", $assets['control_maturity'], "asset_maturity[]", false, false, true, "required", id: false)."</td>\n";
                $assets_html .= "<td><select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='".$escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder'])."'' required></select></td>\n";
                $assets_html .= "<td class='text-center'><a href='javascript:void(0);' class='control-block--delete-asset' title='".$escaper->escapeHtml($lang["Delete"])."'><i class='fa fa-trash'></i></a></td>\n";
            $assets_html .= "</tr>\n";
            $control['mapped_maturity'][] = $assets['control_maturity'];
        }
        json_response(200, "Get framework control by ID", ["control" => $control, "mapped_frameworks" => $frameworks_html, "mapped_assets" => $assets_html]);
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

        // Purify the rich-text description at this output boundary — it feeds the
        // Define-Framework edit modal, which renders it raw into the WYSIWYG editor.
        if (!empty($framework)) {
            $framework['description'] = purify_rich_text_output($framework['description'] ?? '');
        }

        if (!empty($framework['custom_values'])) {
            foreach ($framework['custom_values'] as &$custom_value) {
                switch ($custom_value['field_type']) {
                    case 'shorttext':
                    case 'longtext':
                        // If encryption for this field is enabled, decrypt value
                        if ($custom_value['encryption']) {
                            $custom_value['value'] = try_decrypt($custom_value['value']);
                        }
                        break;
                    case 'date':
                        $custom_value['value'] = $custom_value['value'] ? format_date($custom_value['value']) : '';
                        break;
                    case 'multidropdown':
                    case 'user_multidropdown':
                        $custom_value['value'] = $custom_value['value'] ? explode(',', $custom_value['value']) : '';
                        break;
                }
            }
        }

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
        $draw = $escaper->escapeHtml($_POST['draw']);
        $control_framework = empty($_POST['control_framework']) ? [] : $_POST['control_framework'];
        $control_family = isset($_POST['control_family']) ? $_POST['control_family'] : [];
        $control_name = isset($_POST['control_name']) ? $_POST['control_name'] : "";

        $controls = get_framework_controls_by_filter("all", "all", "all", $control_family, $control_framework, "all", "all", "all", $control_name);

         // If team separation is enabled
        if (team_separation_extra()) {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // It means that either the user is an admin
            // or everyone has access to the tests/audits.
            // It means we can treat Team Separation like it is disabled        
            $separation_enabled = !should_skip_test_and_audit_permission_check();
        } else {
            $separation_enabled = false;
        }

        $recordsTotal = count($controls);

        $data = array();
        foreach ($controls as $key=>$control)
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

            $tests = get_framework_control_tests_by_control_id($control['id']);

            $html = "
                <div class='card border border-primary mb-0'>
                    <div class='card-header d-flex justify-content-between align-items-center'>
                        <div>
                            <strong>{$escaper->escapeHtml($control['control_number'])}</strong>
                            <div class='text-muted small'>{$escaper->escapeHtml($control['short_name'])}</div>
                        </div>
            ";

            // Only display the "Add Test" button if the user has permissions to
            if(isset($_SESSION["define_tests"]) && $_SESSION["define_tests"] == 1)
            {
                $html .= "
                        <button data-control-id='{$control['id']}' class='btn btn-sm btn-dark add-test'>{$escaper->escapeHtml($lang['AddTest'])}</button>
                ";
            }

            $html .= "
                    </div>
                    <div class='card-body'>
                        <div class='row mb-2 top'>
                            <div class='col-2 text-right'><label>{$escaper->escapeHtml($lang['ControlLongName'])} :</label></div>
                            <div class='col-10'>{$escaper->escapeHtml($control['long_name'])}</div>
                        </div>
                        <div class='row mb-2 top'>
                            <div class='col-2 text-right'><label>{$escaper->escapeHtml($lang['ControlOwner'])} :</label></div>
                            <div class='col-10'>{$escaper->escapeHtml($control['control_owner_name'])}</div>
                        </div>
                        <div class='row mb-2 top'>
                            <div class='col-2 text-right'><label>{$escaper->escapeHtml($lang['Description'])} :</label></div>
                            <div class='col-10'>{$escaper->purifyHtml($control['description'])}</div>
                        </div>" . 
                        
                        display_mapping_framework_view($control['id'], 'bottom') . "
                        
                        <div class='framework-control-test-list'>
                            <table width='100%' class='table table-bordered table-striped table-condensed sortable mb-0'>
                                <thead class='table-active'>
                                    <tr>
                                        <th style='width: 100px; min-width: 100px'>{$escaper->escapeHtml($lang['ID'])}</th>
                                        <th style='width: 150px; min-width: 150px'>{$escaper->escapeHtml($lang['TestName'])}</th>
                                        <th style='width: 100px; min-width: 100px'>{$escaper->escapeHtml($lang['Tester'])}</th>
                                        <th style='width: 100px; min-width: 100px'>{$escaper->escapeHtml($lang['AdditionalStakeholders'])}</th>
                                        <th style='width: 150px; min-width: 150px'>{$escaper->escapeHtml($lang['Tags'])}</th>
                                        <th style='width: 110px; min-width: 110px'>{$escaper->escapeHtml($lang['TestFrequency'])}</th>
                                        <th style='width: 110px; min-width: 110px'>{$escaper->escapeHtml($lang['LastTestDate'])}</th>
                                        <th style='width: 110px; min-width: 110px'>{$escaper->escapeHtml($lang['NextTestDate'])}</th>
                                        <th style='width: 110px; min-width: 110px'>{$escaper->escapeHtml($lang['AutoInitiateAudit'])}</th>
                                        <th style='width: 110px; min-width: 110px'>{$escaper->escapeHtml($lang['AuditInitiationOffset'])}</th>
                                        <th style='width: 130px; min-width: 130px'>{$escaper->escapeHtml($lang['ApproximateTime'])}</th>
                                        <th class='text-center' style='width: 50px; min-width: 50px'>{$escaper->escapeHtml($lang['Actions'])}</th>
                                    </tr>
                                </thead>
                                <tbody>
            ";

            foreach ($tests as $test) {
                if ($separation_enabled) {
                    if (!is_user_allowed_to_access($_SESSION['uid'], $test['id'], 'test')) {
                        continue;
                    }
                }
                $tags_view = "";
                if ($test['tags']) {
                    foreach(str_getcsv($test['tags'], ',', '"', '') as $tag) {
                        $tags_view .= "
                            <button class='btn btn-secondary btn-sm' style='pointer-events: none;margin-right:2px;padding: 4px 12px;' role='button' aria-disabled='true'>{$escaper->escapeHtml($tag)}</button>
                        ";
                    }
                } else {
                    $tags_view .= "";
                }

                $last_date = format_date($test['last_date']);
                $next_date = format_date($test['next_date']);

                // There's no separate fields for marking auto audit initiation enabled and another for the offset.
                // Simply setting `audit_initiation_offset` to null means it's disabled
                $auto_audit_initiation = isset($test['audit_initiation_offset']) ? $escaper->escapeHtml($lang['Yes']) : $escaper->escapeHtml($lang['No']);
                $audit_initiation_offset = isset($test['audit_initiation_offset']) ? $escaper->escapeHtml($test['audit_initiation_offset']) . " " . ($test['audit_initiation_offset'] > 1 ? $escaper->escapeHtml($lang['days']) : $escaper->escapeHtml($lang['day'])) : "--";

                if (isset($_SESSION["edit_tests"]) && $_SESSION["edit_tests"] == 1) {
                    $edit_row = "
                        <a class='edit-test mx-1' data-id='{$escaper->escapeHtml($test['id'])}' role='button'><i class='fa fa-edit'></i></a>
                    ";
                } else {
                    $edit_row = "";
                }

                if (isset($_SESSION["delete_tests"]) && $_SESSION["delete_tests"] == 1) {
                    $delete_row = "
                        <a class='delete-row mx-1' data-toggle='modal' data-id='{$escaper->escapeHtml($test['id'])}' role='button'><i class='fa fa-trash'></i></a>
                    ";
                } else {
                    $delete_row = "";
                }


                $html .= "
                                    <tr>
                                        <td>{$escaper->escapeHtml($test['id'])}</td>
                                        <td>{$escaper->escapeHtml($test['name'])}</td>
                                        <td>{$escaper->escapeHtml($test['tester_name'])}</td>
                                        <td>{$escaper->escapeHtml(get_stakeholder_names($test['additional_stakeholders'], 3))}</td>
                                        <td>{$tags_view}</td>
                                        <td class='text-center'>" . (int)$test['test_frequency'] . " {$escaper->escapeHtml($test['test_frequency'] > 1 ? $escaper->escapeHtml($lang['days']) : $escaper->escapeHtml($lang['Day']))}</td>
                                        <td class='text-center'>{$escaper->escapeHtml($last_date)}</td>
                                        <td class='text-center'>{$escaper->escapeHtml($next_date)}</td>
                                        <td class='text-center'>{$auto_audit_initiation}</td>
                                        <td class='text-center'>{$audit_initiation_offset}</td>
                                        <td class='text-center'>" . (int)$test['approximate_time'] . " {$escaper->escapeHtml($test['approximate_time'] > 1 ? $escaper->escapeHtml($lang['minutes']) : $escaper->escapeHtml($lang['minute']))}</td>
                                        <td class='text-center'>{$edit_row}{$delete_row}</td>
                                    </tr>
                ";

            }

            $html .= "
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            ";

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

/*************************
 * FUNCTION: UPDATE TEST *
 *************************/
function updateTestResponse() {
    global $lang, $escaper;

    $error = false;
    $error_msg = "";

    // check permission
    if (!check_permission("edit_tests")) {

        // display an alert
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        json_response(400, get_alert(true), null);

    } else {
    
        $test_id = (int)$_POST['test_id'];

        // If team separation is enabled
        if (team_separation_extra()) {

            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test')) {
                $error = true;
                $error_msg = $lang['NoPermissionForThisTest'];
            }
        }
        
        $today_dt = strtotime(date('Ymd'));
        $tester = isset($_POST['tester']) ? (int)$_POST['tester'] : null;
        $teams = isset($_POST['team']) ? array_filter($_POST['team'], 'ctype_digit') : [];
        $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
        $test_frequency = (int)$_POST['test_frequency'];
        // DATES. Both fields used to be converted with
        // get_standard_date_from_default_format(), which understands the instance's
        // DISPLAY format and nothing else. Three separate silent failures followed
        // from that, every one of them behind a 200:
        //
        //   ISO ('2026-06-19' on an m/d/Y instance) -> '' -> read as "nothing
        //       submitted", so a machine caller's date was discarded. Every other
        //       compliance date handler accepts ISO (8bbb8d40c2) and getTestById()
        //       (GET /compliance/tests/{id}) answers in raw ISO, so a read-modify-write
        //       round trip through it dropped the field. Note the OTHER read endpoint,
        //       getTestResponse() (GET /compliance/test), answers display-formatted --
        //       it runs the dates through format_date() for the modal's datepickers --
        //       so the two reads disagree and only the ISO one round-trips. Accepting
        //       both formats on the write side is what makes either read usable.
        //   Garbage ('banana')                      -> '' -> discarded the same way,
        //       with nothing to distinguish a stored date from a dropped one.
        //   An impossible date ('02/31/2026')       -> silently ROLLED FORWARD to
        //       2026-03-03 and stored. Worse than dropping it: this persists a
        //       compliance date the caller never asked for.
        //
        // parse_submitted_api_date() (includes/audit_schedule.php) accepts ISO *and*
        // the configured display format, parses strictly (a rolled-over date is an
        // error, not a result), and separates "blank" (false) from "not a date"
        // (null) so the last two cases can be refused. submitted_date_error_message()
        // builds the 400 body and tells a malformed value apart from an impossible
        // one, so '02/31/2026' gets "not a real calendar date" rather than format
        // advice the caller already followed.
        $date_format = get_default_date_format();
        $human_date_format = get_setting('default_date_format');

        // isset(), not `?? ''` -- the two are NOT equivalent here, and the difference
        // was a live data-loss bug of its own. `?? ''` fed an OMITTED field through
        // the converter, which answers a blank with the *truthy* string '0000-00-00';
        // that reached update_framework_control_test() as an explicit clear, so an API
        // caller who never mentioned last_date wiped the stored one. (The comment
        // replaced here asserted the empty string normalized to false. It never did.)
        // Splitting on isset() restores the intended contract, matching updateTestById():
        //     omitted         -> false        -> keep the stored value
        //     submitted blank -> '0000-00-00' -> an explicit clear, which is what the
        //                                        edit form posts for an emptied field
        $last_date = false;
        if (isset($_POST['last_date'])) {
            $last_date = parse_submitted_api_date($_POST['last_date'], $date_format);
            if ($last_date === null) {
                json_response(400, submitted_date_error_message($_POST['last_date'], $lang['LastTestDate'] . ' (last_date)', $date_format, $human_date_format), NULL);
                return;
            }
            if ($last_date === false) $last_date = '0000-00-00';
        }

        // next_date deliberately does NOT get the '0000-00-00' treatment last_date
        // gets: a blank stays false. A Calendar-schedule update submits no next_date
        // at all, and '0000-00-00' is truthy, so every `if (!$next_date)` guard below
        // missed it and the order check compared the last test date against the year
        // 1899 -- which is what made switching a test that HAS a Last Test Date into
        // Calendar mode fail with "Next Test Date can't be before Last Test Date!".
        // false is also what resolve_interval_next_date() returns for a blank
        // submission, so an omitted field and a cleared one behave identically and the
        // interval path is unchanged.
        $next_date = false;
        if (isset($_POST['next_date'])) {
            $next_date = parse_submitted_api_date($_POST['next_date'], $date_format);
            if ($next_date === null) {
                json_response(400, submitted_date_error_message($_POST['next_date'], $lang['NextTestDate'] . ' (next_date)', $date_format, $human_date_format), NULL);
                return;
            }
        }
        $name = !empty($_POST['name']) ? trim($_POST['name']) : "";
        $objective = $_POST['objective'];
        $test_steps = $_POST['test_steps'];
        $approximate_time = !empty($_POST['approximate_time']) ? (int)$_POST['approximate_time'] : 0;
        $expected_results = $_POST['expected_results'];
        $tags = empty($_POST['tags']) ? [] : $_POST['tags'];

        // Phase 3a test-definition fields (Define Tests redesign). test_method/sample/
        // required_evidence are plain scalars, read the same way objective/test_steps
        // are above. approvers is a junction (framework_control_test_approvers) -- the
        // edit form always submits the full multi-select, so (unlike updateTestById's
        // CRUD PATCH path) there's no partial-update "preserve existing" case here.
        $test_method        = isset($_POST['test_method']) ? $_POST['test_method'] : "";
        $sample             = isset($_POST['sample']) ? $_POST['sample'] : "";
        $required_evidence  = isset($_POST['required_evidence']) ? $_POST['required_evidence'] : "";
        // Coerce to an array: a client may submit `approvers=5` (scalar) instead of
        // `approvers[]=5`; test_tester_conflicts_with_approvers() type-hints array,
        // so guard here to return a clean 400 rather than a TypeError/500.
        $approvers          = is_array($_POST['approvers'] ?? null) ? $_POST['approvers'] : [];

        // Phase 4a (common tests): controls is a junction (test_control_map).
        // Unlike approvers above, the edit modal does not yet always submit the full
        // set (Task 5, net-new), so mirror updateTestById's fetch-and-passthrough:
        // an omitted/non-array `controls` falls back to the currently-persisted set
        // ($existing_test, fetched below for the schedule fields) rather than wiping
        // it. $existing_test may be non-array if $test_id doesn't resolve to a row --
        // guarded the same way the schedule effective-value fallbacks below are.
        $controls_submitted = isset($_POST['controls']) && is_array($_POST['controls']);

        // The lead-in offset is no longer gated behind a separate Yes/No toggle: it's
        // simply shown (and optional) for Interval and Calendar schedules, and forced to
        // null (no automatic initiation) for Manual. schedule_type may be omitted (means
        // "keep the existing value" per update_framework_control_test()'s null contract),
        // so fall back to the persisted value on the row to decide validation/next_date
        // behavior for *this* request.
        $existing_test = get_framework_control_test_by_id($test_id);

        // controls: freshly-submitted array wins, otherwise fall back to the
        // currently-persisted set (empty array if $existing_test didn't resolve --
        // update_framework_control_test() treats an empty $controls as "keep
        // existing" anyway, so this never wipes on a bad/missing test_id).
        $controls = $controls_submitted
            ? $_POST['controls']
            : (is_array($existing_test) ? ($existing_test['controls'] ?? []) : []);

        $audit_initiation_offset_raw = isset($_POST['audit_initiation_offset']) ? trim((string)$_POST['audit_initiation_offset']) : '';
        $audit_initiation_offset = null;

        // schedule_exceptions may arrive as a JSON-encoded string (a single
        // form field) or as a native nested array (form-array encoding).
        // A non-array/non-decodable value is treated as "not supplied" by
        // parse_test_schedule_fields(), which preserves update_framework_control_test()'s
        // null-means-leave-existing-exceptions-untouched contract.
        $schedule_exceptions_raw = $_POST['schedule_exceptions'] ?? null;
        if (is_string($schedule_exceptions_raw)) {
            $decoded = json_decode($schedule_exceptions_raw, true);
            $schedule_exceptions_raw = is_array($decoded) ? $decoded : null;
        }
        // The anchor date arrives display-formatted from the modal (cadence_anchor_date
        // is a datepicker like last_date/next_date) — convert to canonical ISO before
        // validating/persisting it; the DB and cadence engine are ISO-canonical.
        //
        // Parsed the same way as last_date/next_date above, and for the same reason:
        // the display-format-only converter turned an ISO anchor into null, which
        // parse_test_schedule_fields() reads as "not supplied". On a test that is
        // ALREADY on a calendar schedule that null falls back to the persisted anchor,
        // so rescheduling one via ISO kept the old anchor and still answered 200.
        // false (blank or the zero date) keeps meaning "not supplied" — the null
        // parse_test_schedule_fields() expects — so only a genuinely unparseable value
        // is refused.
        $cadence_anchor_date_iso = null;
        if (!empty($_POST['cadence_anchor_date'])) {
            $cadence_anchor_date_iso = parse_submitted_api_date($_POST['cadence_anchor_date'], $date_format);
            if ($cadence_anchor_date_iso === null) {
                json_response(400, submitted_date_error_message($_POST['cadence_anchor_date'], $lang['AnchorDate'] . ' (cadence_anchor_date)', $date_format, $human_date_format), NULL);
                return;
            }
            if ($cadence_anchor_date_iso === false) $cadence_anchor_date_iso = null;
        }

        $schedule_fields = parse_test_schedule_fields([
            'schedule_type'        => $_POST['schedule_type'] ?? null,
            'cadence_unit'         => $_POST['cadence_unit'] ?? null,
            'cadence_interval'     => $_POST['cadence_interval'] ?? null,
            'cadence_anchor_date'  => $cadence_anchor_date_iso,
            'schedule_exceptions'  => $schedule_exceptions_raw,
        ]);

        // The schedule_type this request is effectively saving: the submitted value, or
        // (when omitted) whatever is already persisted on the row.
        $effective_schedule_type = $schedule_fields['schedule_type'] ?? (is_array($existing_test) ? ($existing_test['schedule_type'] ?? null) : null);

        // The effective (submitted-or-persisted) cadence values -- a field omitted from
        // this request means "keep the existing persisted value" (parse_test_schedule_fields()'s
        // null contract, same as update_framework_control_test() itself). Used both for the
        // calendar-completeness validation below and for the update_framework_control_test()
        // call further down.
        $effective_cadence_unit = $schedule_fields['cadence_unit'] ?? (is_array($existing_test) ? ($existing_test['cadence_unit'] ?? null) : null);
        $effective_cadence_interval = $schedule_fields['cadence_interval'] ?? (is_array($existing_test) ? ($existing_test['cadence_interval'] ?? null) : null);
        $effective_cadence_anchor_date = $schedule_fields['cadence_anchor_date'] ?? (is_array($existing_test) ? ($existing_test['cadence_anchor_date'] ?? null) : null);

        // Shared with createTest() -- schedule_type allow-list, calendar cadence
        // completeness, past-anchor-date rejection, and audit lead-in offset bounds
        // (includes/compliance.php's validate_test_schedule_fields()). The add path
        // silently defaults an invalid schedule_type to 'calendar' (there's no
        // persisted row yet to fall back to); on update there IS a persisted row, so
        // an invalid submitted value is rejected outright rather than silently
        // overwriting a valid persisted schedule_type with garbage -- that's why the
        // allow-list check below validates the *raw* submitted schedule_type, while
        // the calendar-completeness/offset checks validate the *effective*
        // (submitted-or-persisted) values. The past-anchor check only re-validates a
        // freshly *submitted* anchor (cadence_anchor_date_submitted) -- an anchor that
        // fell back to an already-persisted value isn't re-checked, otherwise an
        // update that never touches the anchor could start failing on a schedule that
        // was valid when it was saved.
        $schedule_validation_error = validate_test_schedule_fields([
            'raw_schedule_type'             => $schedule_fields['schedule_type'],
            'effective_schedule_type'       => $effective_schedule_type,
            'cadence_unit'                  => $effective_cadence_unit,
            'cadence_interval'              => $effective_cadence_interval,
            'cadence_anchor_date'           => $effective_cadence_anchor_date,
            'cadence_anchor_date_submitted' => $schedule_fields['cadence_anchor_date'] !== null,
            'test_frequency'                => $test_frequency,
            'audit_initiation_offset_raw'   => $audit_initiation_offset_raw,
        ]);

        if ($schedule_validation_error !== null) {
            $error = true;
            $error_msg = $schedule_validation_error;
        } elseif ($effective_schedule_type !== 'manual' && $audit_initiation_offset_raw !== '') {
            // validate_test_schedule_fields() already confirmed the raw offset is a
            // valid non-negative value in bounds -- just cast. (Manual schedules leave
            // $audit_initiation_offset at its null default, same as before -- the offset
            // is ignored entirely for Manual.)
            $audit_initiation_offset = (int)$audit_initiation_offset_raw;
        }

        if (!$name) {

            $error = true;
            $error_msg = _lang('FieldRequired', array("field"=>$lang['TestName']));

        }

        // Same rule as create (test_tester_valid(), includes/compliance.php):
        // a real, enabled user. This used to be a bare truthiness check, which
        // accepted a stale or invented id as readily as it rejected 0.
        if (!test_tester_valid($tester)) {

            $error = true;
            $error_msg = _lang('FieldRequired', array("field"=>$lang['Tester']));

        }

        if ($test_frequency < 0) {
            $error = true;
            $error_msg = $lang['InvalidTestFrequency'];
        }

        // Offset bounds already validated (and $audit_initiation_offset already cast)
        // above via validate_test_schedule_fields() -- see the comment there.

        if ($approximate_time < 0) {
            $error = true;
            $error_msg = $lang['InvalidApproximateTime'];
        }

        if (!is_valid_test_method($test_method)) {
            $error = true;
            $error_msg = $lang['InvalidTestMethod'];
        }

        // Segregation-of-duties guard: the tester cannot also be an approver of their
        // own test. Validated against the effective (possibly just-submitted) tester.
        if (test_tester_conflicts_with_approvers($tester, $approvers)) {
            $error = true;
            $error_msg = $lang['TesterCannotBeApprover'];
        }
        // Roster gate (server-side): every submitted approver must hold approve_tests.
        elseif (!approvers_all_hold_approve_tests($approvers)) {
            $error = true;
            $error_msg = $lang['ApproverNotEligible'];
        }

        // ≥1 control gate, only when controls was FRESHLY submitted this request --
        // mirrors the approvers roster gate's submitted-only re-validation above. A
        // passthrough of already-persisted controls is not re-validated.
        if ($controls_submitted && !test_controls_valid($controls)) {
            $error = true;
            $error_msg = $lang['AtLeastOneControlRequired'];
        }

        if (!$last_date) {
            $last_date = false;
        } else {
            if (strtotime($last_date) > $today_dt) {
                $error = true;
                $error_msg = $lang['InvalidLastTestDate'];
            }
        }

        if (!$next_date) {
            $next_date = false;
        } else {
//            if (strtotime($next_date) < $today_dt) {
//                $error = true;
//                $error_msg = $lang['InvalidNextTestDate'];
//            }
        }

        if ($last_date && $next_date && strtotime($next_date) < strtotime($last_date)) {

            $error = true;
            $error_msg = $lang['InvalidNextTestDateLastTestDateOrder'];

        }
        
        // Calendar-scheduled tests get their next_date from the cadence engine (anchor +
        // cadence + exceptions), not the interval-style last_date/test_frequency math below
        // — passing false lets update_framework_control_test() compute it via
        // compute_calendar_next_date().
        if ($effective_schedule_type === 'calendar') {
            $next_date = false;
        } else {
            // last_date + frequency, NOT clamped to today -- see
            // resolve_interval_next_date() (includes/audit_schedule.php) for why
            // the old clamp was erasing overdue state on unrelated saves.
            $next_date = resolve_interval_next_date($last_date, $test_frequency, $next_date);
        }

        if ($error !== true) {

            // Update a framework control test
            update_framework_control_test(
                $test_id, $tester, $test_frequency, $name, $objective, $test_steps, $approximate_time,
                $expected_results, $last_date, $next_date, false, $additional_stakeholders, $teams, $tags,
                $audit_initiation_offset,
                $schedule_fields['schedule_type'], $schedule_fields['cadence_unit'],
                $schedule_fields['cadence_interval'], $schedule_fields['cadence_anchor_date'],
                $schedule_fields['schedule_exceptions'],
                $test_method, $sample, $required_evidence, $approvers, $controls
            );

            // display an alert
            set_alert(true, "good", $lang['TestSuccessUpdated']);
            json_response(200, get_alert(true), null);

        } else {

            // display an alert
            set_alert(true, "bad", $error_msg);
            json_response(400, get_alert(true), null);

        }
    }
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
            // cadence_anchor_date feeds the Edit modal's datepicker input (like
            // last_date/next_date above), so format it the same way; empty/unset stays "".
            $test['cadence_anchor_date'] = format_date($test['cadence_anchor_date'] ?? '');

            // Purify the rich-text fields at this output boundary — they feed the
            // test edit form, which renders them raw into the WYSIWYG editor.
            $test['objective'] = purify_rich_text_output($test['objective'] ?? '');
            $test['test_steps'] = purify_rich_text_output($test['test_steps'] ?? '');
            $test['expected_results'] = purify_rich_text_output($test['expected_results'] ?? '');
            // Phase 3a fields (Define Tests redesign): sample/required_evidence are
            // rich-text like objective/test_steps/expected_results above, so purify the
            // same way. test_method is a plain enum scalar and approvers is already a
            // plain int-id array (from get_framework_control_test_by_id()) -- both pass
            // through as-is.
            $test['sample'] = purify_rich_text_output($test['sample'] ?? '');
            $test['required_evidence'] = purify_rich_text_output($test['required_evidence'] ?? '');

            // Resolve approver user IDs to display names (id => name), the same
            // get_name_by_value('user', ...) lookup used elsewhere for a single
            // submitted_by/updated_by id. Needed by the procedure-expand UI so it
            // doesn't have to round-trip through the roster for names it already has ids for.
            $test['approver_names'] = [];
            foreach ($test['approvers'] as $approver_id) {
                $test['approver_names'][$approver_id] = get_name_by_value('user', (int)$approver_id);
            }

            // Schedule fields (schedule_type, cadence_unit, cadence_interval,
            // cadence_anchor_date) are already present via `t1`.* in
            // get_framework_control_test_by_id(). Add the persisted
            // per-occurrence exceptions so the edit modal can repopulate them.
            $test['schedule_exceptions'] = get_test_schedule_exceptions($id);

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

/****************************************************************
 * FUNCTION: GET COMPLIANCE APPROVER ROSTER                      *
 * Lightweight roster (value/name) of every user holding the     *
 * approve_tests permission -- backs the Phase 3a test-definition *
 * form's approver multi-select. Gated on define_tests (same      *
 * permission that gates createTest/the Define Tests page), not   *
 * approve_tests itself -- the caller building the test doesn't    *
 * need to be an approver to see who the eligible approvers are.  *
 ****************************************************************/
function get_compliance_approver_roster() {
    global $lang, $escaper;

    if (!check_permission('define_tests')) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $roster = array_map(fn($u) => ['value' => $u['value'], 'name' => $u['name']], get_users_with_permission('approve_tests'));

    json_response(200, 'success', $roster);
}

/****************************************************************
 * FUNCTION: PREVIEW A CALENDAR AUDIT CADENCE SCHEDULE           *
 * Computes occurrence dates for a candidate schedule (cadence   *
 * unit/interval/anchor + per-occurrence exceptions) without     *
 * persisting anything. Backs the schedule editor's live preview.*
 ****************************************************************/
function getSchedulePreviewResponse() {
    global $lang, $escaper;

    // Same permission the schedule fields are gated behind on the sibling
    // compliance test endpoints (getTestResponse/updateTestResponse).
    if (!check_permission("compliance")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForCompliance']), null);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $parsed = parse_schedule_preview_request($body);

    $occurrences = audit_schedule_occurrences(
        $parsed['cadence_anchor_date'],
        $parsed['cadence_unit'],
        $parsed['cadence_interval'],
        $parsed['start'],
        $parsed['end'],
        $parsed['exceptions']
    );

    json_response(200, "success", ['occurrences' => $occurrences]);
}

/*******************************************************
 * FUNCTION: RETURN JSON DATA FOR INITIATE AUDITS TREE *
 *******************************************************/
function getInitiateTestAuditsResponse() {

    global $lang;
    global $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance")) {

        $filter_by_text         = $_GET["filter_by_text"] ?? '';
        $filter_by_status       = empty($_GET["filter_by_status"]) ? [] : $_GET["filter_by_status"];
        $filter_by_frequency    = $_GET["filter_by_frequency"] ?? '';
        $filter_by_framework    = empty($_GET["filter_by_framework"]) ? [] : $_GET["filter_by_framework"];
        $filter_by_control      = $_GET["filter_by_control"] ?? '';

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

        } else {
            
            $separation_enabled = false;

        }

        // If framework was loaded
        if (empty($_GET['id'])) {

            // Prepend the Unassigned node if explicitly selected in the filter
            // and there are frameworkless controls with tests to show.
            if (in_array('0', $filter_by_framework)) {
                $unassigned_controls = get_initiate_unassigned_controls_by_filter($filter_by_text, $filter_by_frequency, $filter_by_control);
                if (!empty($unassigned_controls)) {
                    $results[] = [
                        'id'              => 'framework_0',
                        'state'           => 'closed',
                        'name'            => "<span class='framework-name text-info'>{$escaper->escapeHtml($lang['Unassigned'])}</span>",
                        'test_frequency'  => '',
                        'last_audit_date' => '',
                        'next_audit_date' => '',
                        'status'          => '',
                        'action'          => '',
                    ];
                }
            }

            // Get active frameworks
            $frameworks = get_initiate_frameworks_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control);

            foreach ($frameworks as $framework) {

                if ($separation_enabled && !in_array($framework['value'], $compliance_separation_access_info['frameworks'])) {

                    continue;

                }

                if (isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1) {

                    $action = "
                        <div class='text-center'>
                            <button data-id='{$framework['value']}' type='button' class='btn btn-dark initiate-framework-audit-btn' >{$escaper->escapeHtml($lang['InitiateFrameworkAudit'])}</button>
                        </div>
                    ";

                } else {

                    $action = "";

                }

                $results[] = array(
                    'id' => 'framework_'.$framework['value'],
                    'state' => 'closed',
                    'name' => "<a class='framework-name text-info' data-id='{$framework['value']}' href='' title='{$escaper->escapeHtml($lang['Framework'])}'>{$escaper->escapeHtml($framework['name'])}</a>",
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework['last_audit_date'])),
                    'test_frequency' => $escaper->escapeHtml($framework['desired_frequency']),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework['next_audit_date'])),
                    'status' => $escaper->escapeHtml($framework['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => $action
                );
            }
            
        // If the Unassigned node was clicked — load frameworkless controls
        } elseif ($_GET['id'] === 'framework_0') {

            $framework_controls = get_initiate_unassigned_controls_by_filter($filter_by_text, $filter_by_frequency, $filter_by_control);

            foreach ($framework_controls as $framework_control) {

                // Frameworkless controls have no framework assignment and therefore no team
                // assignment. They are not governed by team separation and are visible to
                // any user with the base compliance permission.

                if (isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1) {

                    $action = "
                        <div class='text-center'>
                            <button data-id='{$framework_control['id']}' type='button' class='btn btn-dark initiate-control-audit-btn' >{$escaper->escapeHtml($lang['InitiateControlAudit'])}</button>
                        </div>
                    ";

                } else {

                    $action = "";

                }

                $results[] = [
                    'id'              => "control_0_{$framework_control['id']}",
                    'state'           => 'closed',
                    'name'            => "<a class='control-name text-info' data-id='{$framework_control['id']}' href='' title='" . $escaper->escapeHtml($lang['Control']) . "'>" . $escaper->escapeHtml($framework_control['short_name']) . "</a>",
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework_control['last_audit_date'])),
                    'test_frequency'  => $escaper->escapeHtml($framework_control['desired_frequency']),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework_control['next_audit_date'])),
                    'status'          => $escaper->escapeHtml($framework_control['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action'          => $action,
                ];

            }

        // If a framework node was clicked
        } elseif (stripos($_GET['id'], "framework_") !== false) {

            $framework_value = (int)str_replace("framework_", "", $_GET['id']);
            $framework_controls = get_initiate_controls_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control, $framework_value);

            foreach ($framework_controls as $framework_control) {

                if ($separation_enabled && !in_array($framework_control['id'], $compliance_separation_access_info['framework_controls'])) {
                    
                    continue;

                }
                
                if (isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1) {
                    
                    $action = "
                        <div class='text-center'>
                            <button data-id='{$framework_control['id']}' type='button' class='btn btn-dark initiate-control-audit-btn' >{$escaper->escapeHtml($lang['InitiateControlAudit'])}</button>
                        </div>
                    ";

                } else {
                    
                    $action = "";

                }

                $results[] = array(
                    'id' => "control_".$framework_value."_".$framework_control['id'],
                    'state' => 'closed',
                    'name' => "<a class='control-name text-info' data-id='{$framework_control['id']}' href='' title='".$escaper->escapeHtml($lang['Control'])."'>".$escaper->escapeHtml($framework_control['short_name'])."</a>",
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework_control['last_audit_date'])),
                    'test_frequency' => $escaper->escapeHtml($framework_control['desired_frequency']),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework_control['next_audit_date'])),
                    'status' => $escaper->escapeHtml($framework_control['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => $action
                );

            }
            
        // If a frameworkless control node was clicked — load its tests directly
        } elseif (str_starts_with($_GET['id'], 'control_0_')) {

            $framework_and_control = str_replace("control_", "", $_GET['id']); // produces "0_<cid>"
            $control_id = (int)explode("_", $framework_and_control)[1];

            $framework_control_tests = get_initiate_unassigned_tests_by_control($control_id);

            foreach ($framework_control_tests as $framework_control_test) {

                // Frameworkless controls and their tests have no team assignment; they are
                // exempt from team separation and visible to any user with compliance access.

                if (isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1) {

                    $action = "
                        <div class='text-center'>
                            <button data-id='{$framework_control_test['id']}' type='button' class='btn btn-dark initiate-test-btn' >{$escaper->escapeHtml($lang['InitiateTest'])}</button>
                        </div>
                    ";

                } else {

                    $action = "";

                }

                $results[] = [
                    // Rebuild the node id from the integer-cast control id (framework is 0 here),
                    // never from the raw request string -- reflecting $_GET['id'] verbatim was a
                    // reflected-XSS sink when this response is rendered as a document.
                    'id'              => "test_0_{$control_id}_{$framework_control_test['id']}",
                    'state'           => 'open',
                    'name'            => "<a class='test-name text-info' data-id='{$framework_control_test['id']}' href='" . build_url() . "/' title='" . $escaper->escapeHtml($lang['Test']) . "'>" . $escaper->escapeHtml($framework_control_test['name']) . "</a>",
                    'test_frequency'  => $escaper->escapeHtml($framework_control_test['test_frequency']),
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework_control_test['last_date'])),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework_control_test['next_date'])),
                    'status'          => $escaper->escapeHtml($framework_control_test['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action'          => $action,
                ];

            }

        } elseif (stripos($_GET['id'], "control_") !== false) {

            $framework_and_control = str_replace("control_", "", $_GET['id']);
            $framework_id = (int)explode("_", $framework_and_control)[0];
            $control_id = (int)explode("_", $framework_and_control)[1];

            $framework_control_tests = get_initiate_tests_by_filter($filter_by_text, $filter_by_status, $filter_by_frequency, $filter_by_framework, $filter_by_control, $framework_id, $control_id);

            foreach ($framework_control_tests as $framework_control_test) {

                if ($separation_enabled && !in_array($framework_control_test['id'], $compliance_separation_access_info['framework_control_tests'])) {
                    
                    continue;

                }

                if (isset($_SESSION["initiate_audits"]) && $_SESSION["initiate_audits"] == 1) {

                    $action = "
                        <div class='text-center'>
                            <button data-id='{$framework_control_test['id']}' type='button' class='btn btn-dark initiate-test-btn' >{$escaper->escapeHtml($lang['InitiateTest'])}</button>
                        </div>
                    ";

                } else {

                    $action = "";

                }

                $results[] = array(
                    // Rebuild the node id from the integer-cast framework/control ids, never from
                    // the raw request string -- reflecting $_GET['id'] verbatim was a reflected-XSS
                    // sink when this response is rendered as a document.
                    'id' => "test_".$framework_id."_".$control_id."_".$framework_control_test['id'],
                    'state' => 'open',
                    'name' => "<a class='test-name text-info' data-id='{$framework_control_test['id']}' href='".build_url()."/' title='".$escaper->escapeHtml($lang['Test'])."'>".$escaper->escapeHtml($framework_control_test['name'])."</a>",
                    'test_frequency' => $escaper->escapeHtml($framework_control_test['test_frequency']),
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework_control_test['last_date'])),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework_control_test['next_date'])),
                    'status' => $escaper->escapeHtml($framework_control_test['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => $action
                );

            }
        }

        // Serve as JSON so the response is never sniffed/rendered as HTML. With the correct
        // Content-Type (plus the X-Content-Type-Options: nosniff set by add_security_headers),
        // any reflected markup is inert even before the per-field escaping above.
        header("Content-Type: application/json");
        // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output; all fields individually escaped
        echo json_encode($results);

    } else {

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
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumn = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : -1;
        $columnName = isset($columnNames[$orderColumn]) ? $columnNames[$orderColumn] : false;
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderDir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) == "asc" ? "asc" : "desc";

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
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
                $reopen_button = "<button class='reopen btn btn-primary' data-id='{$test_audit['id']}'>".$escaper->escapeHtml($lang['Reopen'])."</button>";
            } else $reopen_button = "";

            $tags_view = "";
            if ($test_audit['tags']) {
                foreach(str_getcsv($test_audit['tags'], ',', '"', '') as $tag) {
                    $tags_view .= "<span class=\"badge bg-secondary me-2\" role=\"button\" aria-disabled=\"true\">" . $escaper->escapeHtml($tag) . "</span>";
                }
            } else {
                $tags_view .= "";
            } 

            $data[] = [
                "<div ><a href='".build_url("compliance/view_test.php?id=".$test_audit['id'])."' class='text-left'>".$escaper->escapeHtml($test_audit['name'])."</a><input type='hidden' class='background-class' data-background='{$background_class}'></div>",
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
        // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output; build_url() called with hardcoded path
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
function getActiveTestAuditsResponse() {

    global $lang;
    global $escaper;

    // If the user has compliance permissions
    if (check_permission("compliance")) {

        $draw = $escaper->escapeHtml($_POST['draw']);

        // should check if sorting is enabled
        if (isset($_POST['order'])) {

            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            $orderColumn = (int)$_POST['order'][0]['column'];
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            $orderDir = strtolower($_POST['order'][0]['dir']) == "asc" ? "asc" : "desc";

        // if not, we should set the default value
        } else {

            $orderColumn = 0;
            $orderDir = "asc";

        }

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
                $delete_button = "<button class='btn btn-primary delete-btn' data-id='{$test['id']}' >".$escaper->escapeHtml($lang['Delete'])."</button>";
            else $delete_button = "";

            $tags_view = "";
            if ($test['tags']) {
                foreach(str_getcsv($test['tags'], ',', '"', '') as $tag) {
                    $tags_view .= "<span class=\"badge bg-secondary me-2\">" . $escaper->escapeHtml($tag) . "</span>";
                }
            } else {
                $tags_view .= "";
            }

            $data[] = [
                "<div><a href='".build_url("compliance/testing.php?id=".$test['id'])."' class='text-left'>".$escaper->escapeHtml($test['name'])."</a><input type='hidden' class='background-class' data-background='{$next_date_background_class}'></div>",
                "<div>".(int)$test['test_frequency']. " " .$escaper->escapeHtml($test['test_frequency'] > 1 ? $lang['days'] : $lang['Day'])."</div>",
                "<div>".$escaper->escapeHtml($test['tester_name'])."</div>",
                "<div>".$escaper->escapeHtml(get_stakeholder_names($test['additional_stakeholders'], 2))."</div>",                
                "<div>".$escaper->purifyHtml($test['objective'])."</div>",
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
        // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output; build_url() called with hardcoded path
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
        write_log((int)$test_audit_id + 1000, $_SESSION['uid'] ?? 0, $message, "test_audit");

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

        set_alert(true, "good", "Reopen Test Audit");
        json_response(200, get_alert(true), null);
    }
    else
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForCompliance']));
        json_response(400, get_alert(true), NULL);
    }
}

/********************************************
 * FUNCTION: CUSTOMIZATION ADD CUSTOM FIELD *
 ********************************************/
function customization_addCustomField()
{
    global $lang;

    // Check that this is an admin user
    if (!is_admin())
    {
        json_response(403, $lang['AdminPermissionRequired'], NULL);
        return;
    }

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
    global $lang;

    // Check that this is an admin user
    if (!is_admin())
    {
        json_response(403, $lang['AdminPermissionRequired'], NULL);
        return;
    }

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
    global $lang;

    // Check that this is an admin user
    if (!is_admin())
    {
        json_response(403, $lang['AdminPermissionRequired'], NULL);
        return;
    }

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
        $document['control_ids'] = explode(',', $document['control_ids'] ?? '');
        $document['framework_ids'] = explode(',', $document['framework_ids'] ?? '');
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
function getTabularDocumentsResponse() {
     
    global $escaper, $lang;
    
    // If the user has governance permissions
    if (check_permission("governance")) {

        $type = $_GET['type'];
        $document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        // If this is request to view all versions of selected document.
        if ($document_id) {

            // Get current document
            $current_document = get_document_by_id($document_id);
            $version = $current_document['file_version'];

            // Get documents with versions
            $documents = get_document_versions_by_id($document_id);
            
            foreach ($documents as $index => &$document) {

                $document['id'] = $document['id'] . "_" . $document['file_version'];
                $document['state'] = "open";
                $document['document_type'] = $escaper->escapeHtml($document['document_type']);
                $document['document_name'] = "<a class='text-info' href='" . build_url("governance/download.php?id={$escaper->escapeHtml($document['unique_name'])}") . "' >{$escaper->escapeHtml($document['document_name'])} ({$document['file_version']})</a>";
                $document['submitted_by'] = $escaper->escapeHtml(get_name_by_value('user', (int)$document['submitted_by']));
                $document['updated_by'] = $escaper->escapeHtml(get_name_by_value('user', (int)$document['updated_by']));
                $document['status'] = $escaper->escapeHtml(get_name_by_value('document_status', $document['status']));
                
                // if the version is the original version, the creation_date should be the date that the document is created.
                if ($index == 0) {

                    $document['creation_date'] = format_date($document['creation_date']);

                // if the version is the late version, the creation_date should be the date that the file is uploaded.
                } else {

                    $document['creation_date'] = format_date($document['file_upload_time']);
                    
                }

                $document['approval_date'] = format_date($document['approval_date']);
                $document['actions'] = "
                    <div class='text-center nowrap'>
                ";

                if (!empty($_SESSION['delete_documentation']) && $version != $document['file_version']) {
                    $document['actions'] .= "
                        <a class='document--delete mx-1' data-version='{$document['file_version']}' data-id='" . ((int)$document['id']) . "' data-bs-toggle='modal' data-bs-target= '#document-delete-modal' id='document-delete-modal-btn' ><i class='fa fa-trash'></i></a>
                    ";
                }
                $document['actions'] .= "
                    </div>
                ";
            }
            
        // If this is request to view document list.
        } else {

            $filterRules = isset($_GET["filterRules"]) ? json_decode($_GET["filterRules"],true) : array();
            $filtered_documents = array();
            $documents = get_documents($type);
            foreach ($documents as &$document) {
                $frameworks = get_frameworks_by_ids($document["framework_ids"] ?? '');
                $framework_names = implode(", ", array_map(function($framework) {
                    return $framework['name'];
                }, $frameworks));

                $control_ids = explode(",", $document["control_ids"] ?? '');
                $controls = get_framework_controls_by_filter("all", "all", "all", "all", "all", "all", "all", "all", "", $control_ids);
                $control_names = implode(", ", array_map(function($control) {
                    return $control['short_name'];
                }, $controls));

                // document filtering
                if (count($filterRules)>0) {
                    foreach ($filterRules as $filter) {
                        $value = $filter['value'];
                        switch ($filter['field']) {
                            case "document_name":
                                if (stripos($document['document_name'], $value) === false) {
                                    continue 3;
                                }
                                break;
                            case "document_type":
                                if (stripos($document['document_type'], $value) === false) {
                                    continue 3;
                                }
                                break;
                            case "framework_names":
                                if (stripos($framework_names, $value) === false) {
                                    continue 3;
                                }
                                break;
                            case "control_names":
                                if (stripos($control_names, $value) === false) {
                                    continue 3;
                                }
                                break;
                            case "submitted_by":
                                if (stripos(get_name_by_value('user', (int)$document['submitted_by']), $value) === false) {
                                    continue 3;
                                }
                                break;
                            case "updated_by":
                                if (stripos(get_name_by_value('user', (int)$document['updated_by']), $value) === false) {
                                    continue 3;
                                }
                                break;
                            case "creation_date":
                                if(stripos(format_date($document['creation_date']), $value) === false) {
                                    continue 3;
                                }
                                break;
                            case "approval_date":
                                if (stripos(format_date($document['approval_date']), $value) === false) {
                                    continue 3;
                                }
                                break;
                            case "status":
                                if (stripos(get_name_by_value('document_status', $document['status']), $value) === false) {
                                    continue 3;
                                }
                                break;
                        }
                    }
                }

                $document['state'] = "closed";
                $document['document_type'] = $escaper->escapeHtml($document['document_type']);
                $document['document_name'] = "<a class='text-info' href='" . build_url("governance/download.php?id={$escaper->escapeHtml($document['unique_name'])}") . "' >{$escaper->escapeHtml($document['document_name'])}</a>";
                $document['status'] = $escaper->escapeHtml(get_name_by_value('document_status', $document['status']));
                $document['framework_names'] = $escaper->escapeHtml($framework_names);
                $document['control_names'] = $escaper->escapeHtml($control_names);
                $document['submitted_by'] = $escaper->escapeHtml(get_name_by_value('user', (int)$document['submitted_by']));
                $document['updated_by'] = $escaper->escapeHtml(get_name_by_value('user', (int)$document['updated_by']));
                $document['creation_date'] = format_date($document['creation_date']);
                $document['approval_date'] = format_date($document['approval_date']);
                $document['actions'] = "
                    <div class='text-center nowrap'>
                ";
                if (!empty($_SESSION['modify_documentation'])) {
                    $document['actions'] .= "
                        <a class='document--edit mx-1' data-id='" . ((int)$document['id']) . "'><i class='fa fa-edit'></i></a>
                    ";
                }
                if (!empty($_SESSION['delete_documentation'])) {
                    $document['actions'] .= "
                        <a class='document--delete mx-1' data-id='" . ((int)$document['id']) . "' data-type='{$document['document_type']}'><i class='fa fa-trash'></i></a>
                    ";
                }
                $document['actions'] .= "
                    </div>
                ";
                $filtered_documents[] = $document;
            }
            $documents = $filtered_documents;
        }

        // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output; build_url() called with hardcoded path and pre-escaped unique_name
        echo json_encode($documents);
        exit;
    } else {

        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);

    }
}

/*******************************************************
 * FUNCTION: GET DATA FOR MITIGATION CONTROL DATATABLE *
 *******************************************************/
 
function get_mitigation_control_info(){
    global $lang;
    global $escaper;

    $control_id = (int)$_GET['control_id'];
    $height     = (int)$_GET['scroll_top'];
    
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
            <td colspan="5">' . $escaper->purifyHtml( $description ) . '</td>
            </tr>
            <tr>
            <td align="right"><strong>' . $escaper->escapeHtml($lang['SupplementalGuidance']) . '</strong>: </td>
            <td colspan="5">' . $escaper->purifyHtml( $supplemental_guidance ) . '</td>
            </tr>
            <tr>
            <td align="right"><strong>' . $escaper->escapeHtml($lang['MappedControlFrameworks']) . '</strong>: </td>
            <td colspan="5">' .$mapping_framework_table . '</td>
            </tr>
        </tbody>
    </table>';

    $data = [];
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

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }
    
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
        
        $tooltip_html .=  '<a href="'. build_url('management/view.php?id=' . $escaper->escapeHtml(convert_to_risk_id($risk['id']))) .'" style="" ><b>' . $escaper->escapeHtml(try_decrypt($risk['subject'])) . '</b></a><hr>';
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
    if (check_permission("riskmanagement")) {

        $user = get_user_by_id($_SESSION['uid']);
        $settings = json_decode($user["custom_plan_mitigation_display_settings"] ?? '', true);
        $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
        $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
        $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
        $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
        $columns = [];

        foreach($columns_setting as $column) {
            if(stripos($column[0], "custom_field_") !== false) {
                if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
            } else if($column[1] == 1) {
                $columns[] = $column[0];
            }
        }
        if(!count($columns)){
            $columns = array("id","risk_status","subject","calculated_risk","submission_date","mitigation_planned","management_review");
        }

        $draw = $escaper->escapeHtml($_POST['draw']);

        $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
        $length = $_POST['length'] ? (int)$_POST['length'] : 10;
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumnName = isset($_POST['columns'][$orderColumn]['name']) ? $_POST['columns'][$orderColumn]['name'] : null;;
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderDir = !empty($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
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
            
            $residual_color = get_risk_color($risk['residual_risk']);

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
            $mitigation_planned = planned_mitigation(convert_to_risk_id($risk['id']), $risk['mitigation_id'], "PlanYourMitigations");
            $management_review = management_review(convert_to_risk_id($risk['id']), $risk['mgmt_review'], $next_review, true, "PlanYourMitigations");
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
                                $custom_values = getCustomFieldValuesByRiskId(convert_to_risk_id($risk['id']));
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
                        $id = (int) convert_to_risk_id($risk['id']);
                        $data_row[] = "<div data-id='{$id}' class='open-risk'><a class='open-in-new-tab' href='../management/view.php?id={$id}&active=PlanYourMitigations#mitigation' target='_blank'>{$id}</a></div>";
                        $filter_data[$column] = $id;
                        break;
                    case "risk_status":
                        $data_row[] = $escaper->escapeHtml($risk['status']);
                        $filter_data[$column] = $risk['status'];
                        break;
                    case "calculated_risk":
                        $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder' style='position:relative;'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeCssColor($color) . "\"></span></div></div>";
                        $filter_data[$column] = $risk['calculated_risk'];
                        break;
                    case "residual_risk":
                        $data_row[] = "
                            <div class='{$escaper->escapeHtml($residual_color)}'>
                                <div class='risk-cell-holder' style='position:relative;'>
                                    {$escaper->escapeHtml($risk['residual_risk'])}
                                    <span class='risk-color' style='background-color:{$escaper->escapeCssColor($residual_color)}'></span>
                                </div>
                            </div>
                        ";
                        $filter_data[$column] = $risk['residual_risk'];
                        break;
                    case "submission_date":
                        $data_row[] = $escaper->escapeHtml($submission_date);
                        $filter_data[$column] = $submission_date;
                        break;
                    case "mitigation_planned":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_to_risk_id($risk['id'])) ." class=\"text-center open-mitigation mitigation active-cell\" >".$mitigation_planned."</div>";
                        $filter_data[$column] = $mitigation_planned;
                        break;
                    case "management_review":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_to_risk_id($risk['id'])) ." class=\"text-center open-review management active-cell\">".$management_review."</div>";
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
                    case 'comments':
                    case 'risk_assessment':
                    case 'additional_notes':
                    case 'current_solution':
                    case 'security_recommendations':
                    case 'security_requirements':
                        $filter_data[$column] = try_decrypt($risk[$column]);
                        $data_row[] = $escaper->purifyHtml($filter_data[$column]);
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
                            $currency = get_currency_symbol();
                            $mitigation_cost = $currency . $mitigation_min_cost . " to " . $currency . $mitigation_max_cost;
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
                    case "risk_tags":
                        $tags = "";
                        $filter_data[$column] = '';
                        if ($risk['risk_tags']) {
                            $filter_data[$column] = str_getcsv($risk['risk_tags'], ',', '"', '');
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
                        // Passing null to parameter 1 of type string in stripos is deprecated.
                        if(stripos($filter_data[$column_name] ?? "", $val) === false){
                            $success = false;
                        }
                        break;
                    case "risk_tags":
                        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                        if ($filter_data['risk_tags']) {
                            $tag_match = false;
                            // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                            foreach ($filter_data['risk_tags'] as $tag) {
                                $tag_match = $tag_match || stripos($tag, $val) !== false;
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
        // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output for DataTables; all columns escaped via escapeHtml()/purifyHtml() in switch block
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
        $settings = json_decode($user["custom_perform_reviews_display_settings"] ?? '', true);
        $risk_colums_setting = isset($settings["risk_colums"])?$settings["risk_colums"]:[];
        $mitigation_colums_setting = isset($settings["mitigation_colums"])?$settings["mitigation_colums"]:[];
        $review_colums_setting = isset($settings["review_colums"])?$settings["review_colums"]:[];
        $columns_setting = array_merge($risk_colums_setting, $mitigation_colums_setting, $review_colums_setting);
        $columns = [];

        foreach($columns_setting as $column) {
            if(stripos($column[0], "custom_field_") !== false) {
                if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
            } else if($column[1] == 1) {
                $columns[] = $column[0];
            }
        }
        if(!count($columns)){
            $columns = array("id","risk_status","subject","calculated_risk","submission_date","mitigation_planned","management_review");
        }

        $draw = $escaper->escapeHtml($_POST['draw']);

        $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
        $length = $_POST['length'] ? (int)$_POST['length'] : 10;

        // In case there's no column selected that is orderable the order won't be sent from the client
        if (!empty($_POST['order'])) {

            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            $orderColumnName = isset($_POST['columns'][$orderColumn]['name']) ? $_POST['columns'][$orderColumn]['name'] : null;
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            $orderDir = !empty($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        } else {

            // Default ordering by id ascending if no order is specified
            $orderColumnName = 'id';
            $orderDir = "asc";

        }

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
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

            $residual_color = get_risk_color($risk['residual_risk']);

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
            $mitigation_planned = planned_mitigation(convert_to_risk_id($risk['id']), $risk['mitigation_id'], "PerformManagementReviews");
            $management_review = management_review(convert_to_risk_id($risk['id']), $risk['mgmt_review'], $next_review, true, "PerformManagementReviews");
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
                                $custom_values = getCustomFieldValuesByRiskId(convert_to_risk_id($risk['id']));
                                $text = "";
                                // Get value of custom filed
                                foreach($custom_values as $custom_value)
                                {
                                    // Check if this custom value is for the active field
                                    if($custom_value['field_id'] == $field_id) {
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
                        $id = convert_to_risk_id($risk['id']);
                        $data_row[] = "<div data-id='{$id}' class='open-risk'><a class='open-in-new-tab' href='../management/view.php?id={$id}&active=PerformManagementReviews#review' target='_blank'>{$id}</a></div>";
                        $filter_data[$column] = $id;
                        break;
                    case "risk_status":
                        $data_row[] = $escaper->escapeHtml($risk['status']);
                        $filter_data[$column] = $risk['status'];
                        break;
                    case "calculated_risk":
                        $data_row[] = "<div class='" . $escaper->escapeHtml($color) . "'><div class='risk-cell-holder' style='position:relative;'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class='risk-color' style='background-color:" . $escaper->escapeCssColor($color) . "'></span></div></div>";
                        $filter_data[$column] = $risk['calculated_risk'];
                        break;
                    case "residual_risk":
                        $data_row[] = "
                            <div class='{$escaper->escapeHtml($residual_color)}'>
                                <div class='risk-cell-holder' style='position:relative;'>
                                    {$escaper->escapeHtml($risk['residual_risk'])}
                                    <span class='risk-color' style='background-color:{$escaper->escapeCssColor($residual_color)}'></span>
                                </div>
                            </div>
                        ";
                        $filter_data[$column] = $risk['residual_risk'];
                        break;
                    case "submission_date":
                        $data_row[] = $escaper->escapeHtml($submission_date);
                        $filter_data[$column] = $submission_date;
                        break;
                    case "mitigation_planned":
                        $data_row[] = "<div data-id=" . $escaper->escapeHtml(convert_to_risk_id($risk['id'])) . " class='text-center open-mitigation mitigation active-cell' >" . $mitigation_planned . "</div>";
                        $filter_data[$column] = $mitigation_planned;
                        break;
                    case "management_review":
                        $data_row[] = "<div data-id=" . $escaper->escapeHtml(convert_to_risk_id($risk['id'])) . " class='text-center open-review management active-cell'>" . $management_review . "</div>";
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
                    case 'comments':
                    case 'risk_assessment':
                    case 'additional_notes':
                    case 'current_solution':
                    case 'security_recommendations':
                    case 'security_requirements':
                        $filter_data[$column] = try_decrypt($risk[$column]);
                        $data_row[] = $escaper->purifyHtml($filter_data[$column]);
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
                            $currency = get_currency_symbol();
                            $mitigation_cost = $currency . $mitigation_min_cost . " to " . $currency . $mitigation_max_cost;
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
                    case "risk_tags":
                        $tags = "";
                        $filter_data[$column] = '';
                        if ($risk['risk_tags']) {
                            $filter_data[$column] = str_getcsv($risk['risk_tags'], '|', '"', '');
                            foreach($filter_data[$column] as $tag) {
                                $tags .= "<button class='btn btn-secondary btn-sm' style='pointer-events: none;margin: 1px;padding: 4px 12px;' role='button' aria-disabled='true'>" . $escaper->escapeHtml($tag) . "</button>";
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
                        // Passing null to parameter 1 of type string in stripos is deprecated.
                        if(stripos($filter_data[$column_name] ?? "", $val) === false){
                            $success = false;
                        }
                        break;
                    case "risk_tags":
                        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                        if ($filter_data['risk_tags']) {
                            $tag_match = false;
                            // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                            foreach ($filter_data['risk_tags'] as $tag) {
                                $tag_match = $tag_match || stripos($tag, $val) !== false;
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
        // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output for DataTables; all columns escaped via escapeHtml()/purifyHtml() in switch block
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
        $settings = json_decode($user["custom_reviewregularly_display_settings"] ?? '', true);
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
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumnName = isset($_POST['columns'][$orderColumn]['name']) ? $_POST['columns'][$orderColumn]['name'] : null;
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderDir = !empty($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        $column_filters = [];
        for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
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

        $risk_id = [];
        $subject = [];
        $status = [];
        $calculated_risk = [];
        $color = [];
        $dayssince = [];
        $next_review = [];
        $next_review_html = [];

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
            $residual_color = get_risk_color($risk['residual_risk']);
            $dayssince = $review['dayssince'];
            $next_review = $review['next_review'];
            $next_review_html = $review['next_review_html'];
            $submission_date = date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']));
            $mitigation_planned = planned_mitigation(convert_to_risk_id($risk['id']), $risk['mitigation_id'],"ReviewRisksRegularly");
            $management_review = management_review(convert_to_risk_id($risk['id']), $risk['mgmt_review'], $next_review, true, "ReviewRisksRegularly");
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
                                $custom_values = getCustomFieldValuesByRiskId(convert_to_risk_id($risk['id']));
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
                        $id = convert_to_risk_id($risk_id);
                        $data_row[] = "<div data-id='{$id}' class='open-risk'><a target='_blank' class='open-in-new-tab' href='../management/view.php?id={$id}&active=ReviewRisksRegularly#review'>{$id}</a></div>";
                        $filter_data[$column] = $id;
                        break;
                    case "risk_status":
                        $data_row[] = $escaper->escapeHtml($status);
                        $filter_data[$column] = $status;
                        break;
                    case "calculated_risk":
                        $data_row[] = "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder' style='position:relative;'>" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeCssColor($color) . "\"></span></div></div>";
                        $filter_data[$column] = $calculated_risk;
                        break;
                    case "residual_risk":
                        $data_row[] = "
                            <div class='{$escaper->escapeHtml($residual_color)}'>
                                <div class='risk-cell-holder' style='position:relative;'>
                                    {$escaper->escapeHtml($risk['residual_risk'])}
                                    <span class='risk-color' style='background-color:{$escaper->escapeCssColor($residual_color)}'></span>
                                </div>
                            </div>
                        ";
                        $filter_data[$column] = $risk['residual_risk'];
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
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_to_risk_id($risk['id'])) ." class=\"text-center open-mitigation mitigation active-cell\" >".$mitigation_planned."</div>";
                        $filter_data[$column] = $mitigation_planned;
                        break;
                    case "management_review":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_to_risk_id($risk['id'])) ." class=\"text-center open-review management active-cell\">".$management_review."</div>";
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
                    case 'comments':
                    case 'risk_assessment':
                    case 'additional_notes':
                    case 'current_solution':
                    case 'security_recommendations':
                    case 'security_requirements':
                        $filter_data[$column] = try_decrypt($risk[$column]);
                        $data_row[] = $escaper->purifyHtml($filter_data[$column]);
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
                            $currency = get_currency_symbol();
                            $mitigation_cost = $currency . $mitigation_min_cost . " to " . $currency . $mitigation_max_cost;
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
                    case "review_date":
                        $filter_data[$column] = format_datetime($risk['review_date'], "", "H:i");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "planning_date":
                        $filter_data[$column] = format_datetime($risk['planning_date'], "", "");
                        $data_row[] = $escaper->escapeHtml($filter_data[$column]);
                        break;
                    case "next_review_date":
                        $data_row[] = "<div data-id=". $escaper->escapeHtml(convert_to_risk_id($risk_id)) ." class=\"text-center open-review\" >".$next_review_html."</div>";
                        $filter_data[$column] = $next_review;
                        break;
                    case "risk_tags":
                        $tags = "";
                        $filter_data[$column] = '';
                        if ($risk['risk_tags']) {
                            $filter_data[$column] = str_getcsv($risk['risk_tags'], '|', '"', '');
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
                        // Passing null to parameter 1 of type string in stripos is deprecated.
                        if(stripos($risk[$column_name] ?? "", $val) === false){
                            $success = false;
                        }
                        break;
                    case "risk_tags":
                        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                        if ($risk['risk_tags']) {
                            $tag_match = false;
                            // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                            foreach ($risk['risk_tags'] as $tag) {
                                $tag_match = $tag_match || stripos($tag, $val) !== false;
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
        // @phan-suppress-next-line SecurityCheck-XSS -- json_encode() output for DataTables; all columns escaped via escapeHtml()/purifyHtml() in switch block
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
function getReviewsWithDateIssuesDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has risk management permissions
    if (check_permission("riskmanagement"))
    {
        $draw = (int)$escaper->escapeHtml($_GET['draw']);

        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $order_column = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
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
                "<div data-id=". $escaper->escapeHtml(convert_to_risk_id($risk_id)) ." class='open-risk'><a target=\"_blank\" href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_to_risk_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_to_risk_id($risk_id)) . "</a></div>",
                $escaper->escapeHtml($subject),
                "<div data-id=". $escaper->escapeHtml(convert_to_risk_id($risk_id)) ." class=\"text-center\" >".$escaper->escapeHtml($next_review)."</div>",
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
            // Updating a review's next-review date is a risk modification, so
            // it must require modify_risks — mirroring reopenForm() and the
            // other risk-write handlers. riskmanagement is a read permission
            // and must not, on its own, authorize this write.
            if (!has_permission("modify_risks")) {
                set_alert(true, "bad", $escaper->escapeHtml($lang['RiskUpdatePermissionMessage']));
                json_response(400, get_alert(true), NULL);
            }

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

        // Fail-closed per-type authorization (SR-1794 / SR-751). Every tag type
        // must be explicitly gated via the shared map; check_tag_type_permission()
        // denies any type without a mapping, so `test`/`test_audit`/`questionnaire_risk`
        // — previously served ungated by the fall-through else — are now gated too.
        if (!check_tag_type_permission($type)) {
            $requirement = tag_type_permission_requirement($type);
            $deny_message = $requirement !== null ? $lang[$requirement['deny_lang']] : $lang['YouNeedToSpecifyATypeParameter'];
            set_alert(true, "bad", $deny_message);
            json_response(400, get_alert(true), NULL);
            return;
        }

        $options = [];
        foreach(getTagsOfType($type) as $tag) {
            $options[] = array('label' => $tag['tag'], 'value' => (int)$tag['id']);
        }

        json_response(200, null, $options);
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

        // Fail-closed per-type authorization (SR-1794 / SR-751): require access to
        // EVERY requested type via the shared map, and deny the whole request if any
        // is not permitted. This closes the sibling gaps this handler had — it
        // previously left `questionnaire_risk` and the incident_management_* types
        // ungated.
        foreach ($types as $requested_type) {
            if (!check_tag_type_permission($requested_type)) {
                $requirement = tag_type_permission_requirement($requested_type);
                $deny_message = $requirement !== null ? $lang[$requirement['deny_lang']] : $lang['YouNeedToSpecifyATypeParameter'];
                set_alert(true, "bad", $deny_message);
                json_response(400, get_alert(true), NULL);
                return;
            }
        }

        $options = [];
        foreach(getTagsOfTypes($types) as $tag) {
            // usage_count lets a picker order by how reused a tag is (the test
            // tag field does); callers that don't care can ignore it.
            $options[] = array('label' => $tag['tag'], 'value' => (int)$tag['id'], 'usage_count' => (int)($tag['usage_count'] ?? 0));
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
        write_log(1000, $_SESSION['uid'] ?? 0, _lang('RiskLevelAuditLog', array(
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

/***************************************************
 * FUNCTION: GET ASSOCIATED EXCEPTIONS AS TREEGRID *
 ***************************************************/
function get_associated_exceptions_as_treegrid_api() {
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
    $risk_id = $_GET['id'];
    $result = get_associated_exceptions_as_treegrid($risk_id, $type);
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

    // Purify the rich-text fields at this output boundary — they feed the
    // exception edit modal, which renders them raw into the WYSIWYG editor.
    $exception['description'] = purify_rich_text_output($exception['description'] ?? '');
    $exception['justification'] = purify_rich_text_output($exception['justification'] ?? '');

    $exception['name'] = $escaper->escapeHtml($exception['name']);
    $exception["{$type}_name"] = $escaper->escapeHtml($exception['parent_name']);
    $exception['framework_name'] = $escaper->escapeHtml(try_decrypt($exception['framework_name']));
    $exception["type"] = $type;
    $exception["type_text"] = $escaper->escapeHtml($lang[ucfirst($type)]);
    $exception['document_exceptions_status'] = $escaper->escapeHtml($exception['document_exceptions_status']);
    $exception['owner'] = $escaper->escapeHtml($exception['owner']);
    $exception['additional_stakeholders'] = $escaper->escapeHtml(get_stakeholder_names($exception['additional_stakeholders'], 4));
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

    if($exception['unique_name'])
        $exception['file_download'] = "<a class='text-info' href=\"".build_url("governance/download.php?id=".$escaper->escapeHtml($exception['unique_name']))."\" >".$escaper->escapeHtml($exception['file_name']). " (".$exception['file_version'].")" ."</a>";
    else $exception['file_download'] = "";

    foreach($exception as $key => $value) {
        if (!$value)
            $exception[$key] = "--";
    }

    json_response(200, null, $exception);
}
function create_document_api() {
    global $lang;
    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission('add_documentation')) {
        set_alert(true, "bad", $lang['NoAddDocumentationPermission']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    $submitter = (int)$_SESSION['uid'];
    $document_type = $_POST['document_type'];
    $document_name = $_POST['document_name'];
    $framework_ids = empty($_POST['framework_ids']) ? [] : $_POST['framework_ids'];
    $control_ids   = empty($_POST['control_ids']) ? [] : $_POST['control_ids'];
    $parent        = $_POST['parent'] ?? 0;
    $status        = $_POST['status'];
    $creation_date = get_standard_date_from_default_format($_POST['creation_date']);
    $creation_date = ($creation_date && $creation_date!="0000-00-00") ? $creation_date : date("Y-m-d");
    $last_review_date   = get_standard_date_from_default_format($_POST['last_review_date']);
    $review_frequency = (int)$_POST['review_frequency'];
    $next_review_date   = get_standard_date_from_default_format($_POST['next_review_date']);
    $approval_date   = get_standard_date_from_default_format($_POST['approval_date']);
    $document_owner = (int)$_POST['document_owner'];
    $additional_stakeholders   = empty($_POST['additional_stakeholders']) ? [] : $_POST['additional_stakeholders'];
    $approver = (int)$_POST['approver'];
    $team_ids     = empty($_POST['team_ids']) ? [] : $_POST['team_ids'];

    // Check if the document name is null
    if (!$document_type || !$document_name)
    {
        // Display an alert
        set_alert(true, "bad", "The document type and name cannot be empty.");
        json_response(400, get_alert(true), NULL);
        return;
    }
    // Otherwise
    else
    {
        // Insert a new document
        $document_id = add_document($submitter, $document_type, $document_name, implode(',', $control_ids), $framework_ids, $parent, $status, $creation_date, $last_review_date, $review_frequency, $next_review_date, $approval_date, $document_owner, implode(',', $additional_stakeholders), $approver, implode(',', $team_ids));
        if($document_id)
        {
            // Display an alert
            set_alert(true, "good", $lang['DocumentAdded']);
            json_response(200, get_alert(true), array('type' => $document_type));
            return;
        } else {
            // Display an alert
            json_response(400, get_alert(true), NULL);
            return;
        }
    }
}
function update_document_api() {
    global $lang;
    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission('modify_documentation')) {
        set_alert(true, "bad", $lang['NoModifyDocumentationPermission']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    $id                         = $_POST['document_id'];
    $updater                    = (int)$_SESSION['uid'];
    $document_type              = $_POST['document_type'];
    $document_name              = $_POST['document_name'];
    $framework_ids              = empty($_POST['framework_ids']) ? [] : $_POST['framework_ids'];
    $control_ids                = empty($_POST['control_ids']) ? [] : $_POST['control_ids'];

    // Normalize the framework and control ids to be integers
    $framework_ids = array_map('intval', $framework_ids);
    $control_ids   = array_map('intval', $control_ids);

    // Open the database connection
    $db = db_open();

    $where_parts = [];
    $params = [];

    // Add the framework filter if framework ids are provided
    if (!empty($framework_ids)) {

        // Create a string of placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($framework_ids), '?'));
        $where_parts[] = "framework IN ($placeholders)";

        // bind as ints
        foreach ($framework_ids as $framework_id) {
            $params[] = $framework_id;
        }
    }

    // Add the control filter if control ids are provided
    if (!empty($control_ids)) {

        // Create a string of placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($control_ids), '?'));
        $where_parts[] = "control_id IN ($placeholders)";

        // bind as ints
        foreach ($control_ids as $control_id) {
            $params[] = $control_id;
        }
    }

    $sql = "
        SELECT 
            DISTINCT control_id
        FROM 
            framework_control_mappings
        WHERE 
            1=1
    ";

    if (!empty($where_parts)) {
        $sql .= "
            AND " . implode(" AND ", $where_parts);
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $control_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Close the database connection
    db_close($db);

    $control_ids                = empty($_POST['control_ids']) ? [] : $control_ids;

    $parent                     = (int)$_POST['parent'];
    $status                     = $_POST['status'];
    $creation_date              = get_standard_date_from_default_format($_POST['creation_date']);
    $creation_date              = ($creation_date && $creation_date!="0000-00-00") ? $creation_date : date("Y-m-d");
    $last_review_date           = get_standard_date_from_default_format($_POST['last_review_date']);
    $review_frequency           = (int)$_POST['review_frequency'];
    $next_review_date           = get_standard_date_from_default_format($_POST['next_review_date']);
    $approval_date              = get_standard_date_from_default_format($_POST['approval_date']);
    $document_owner             = (int)$_POST['document_owner'];
    $additional_stakeholders    = empty($_POST['additional_stakeholders']) ? [] : $_POST['additional_stakeholders'];
    $approver                   = (int)$_POST['approver'];
    $team_ids                   = empty($_POST['team_ids']) ? [] : $_POST['team_ids'];

    // Check if the document name is null
    if (!$document_type || !$document_name)
    {
        // Display an alert
        set_alert(true, "bad", "The document name cannot be empty.");
        json_response(400, get_alert(true), NULL);
        return;
    }
    // Otherwise
    else
    {
        // Update document
        $result = update_document($id, $updater, $document_type, $document_name, $control_ids, $framework_ids, $parent, $status, $creation_date, $last_review_date, $review_frequency, $next_review_date, $approval_date, $document_owner, implode(',', $additional_stakeholders), $approver, implode(',', $team_ids));
        if($result)
        {
            // Display an alert
            set_alert(true, "good", $lang['DocumentUpdated']);
            json_response(200, get_alert(true), array('type' => $document_type));
            return;
        } else {
            set_alert(true, "bad", $lang['FailedToUpdateItem']);
            json_response(400, get_alert(true), NULL);
            return;

        }
    }
}
function delete_document_api() {
    global $lang;
    if (!check_permission("governance")) {
        set_alert(true, "bad", $lang['NoPermissionForGovernance']);
        json_response(400, get_alert(true), NULL);
        return;
    } elseif (!check_permission('delete_documentation')) {
        set_alert(true, "bad", $lang['NoDeleteDocumentationPermission']);
        json_response(400, get_alert(true), NULL);
        return;
    }
    $id             = $_POST['document_id'];
    $version        = $_POST['version'];
    $document_type  = $_POST['document_type'];
    if($result = delete_document($id, $version)){
        set_alert(true, "good", $lang['DocumentDeleted']);
        json_response(200, get_alert(true), array('type' => $document_type));
    } else {
        json_response(400, get_alert(true), NULL);
    }
    return;
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
    $framework = isset($_POST['framework']) && ctype_digit($_POST['framework']) ? (int)$_POST['framework'] : 0;
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
    $description = purify_html($_POST['description']);
    $justification = purify_html($_POST['justification']);

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
        $id = create_exception($name, $status, $policy, $framework, $control, $owner, $additional_stakeholders, $creation_date, $review_frequency, $next_review_date, $approval_date, $approver, $approved, $description, $justification, $associated_risks);
    } catch(Exception $e) {
        write_debug_log($e, 'error');
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
    $framework = isset($_POST['framework']) && ctype_digit($_POST['framework']) ? (int)$_POST['framework'] : 0;
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
    $description = purify_html($_POST['description']);
    $justification = purify_html($_POST['justification']);

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
    $approver = (!empty($_POST['approver']) && ctype_digit($_POST['approver']) && get_user_by_id((int)$_POST['approver'])) ? (int)$_POST['approver'] : false;

    $old_exception = get_exception($id);

    $approved = $old_exception['approved'];
    if ($approval_date && $approval_date !== "0000-00-00") {
        if (strtotime($approval_date) > $today_dt) {
            set_alert(true, "bad", $lang['InvalidApprovalDate']);

            json_response(400, get_alert(true), NULL);
            return;
        }

        //Can only be approved if the user has the approve_exception permission
        if (boolval($approver) && check_permission_exception('approve')) {
            $approved = true;
        }
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
            $framework,
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
        // type_1: policy, type_2: control
        json_response(200, get_alert(true), array('approved_original' => $approved_original, 'approved' => $approved, 'type' => $policy ? "type_1" : "type_2"));
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

function unapprove_exception_api() {
    
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

        unapprove_exception($id);

        set_alert(true, "good", $lang['ExceptionWasUnApprovedSuccessfully']);
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

/***************************************
 * FUNCTION: GET EXCEPTIONS STATUS API *
 ***************************************/
function get_exceptions_status_api() {

    global $lang, $escaper;

    if (!check_permission("governance")) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForGovernance']));
        json_response(400, get_alert(true), NULL);
        return;
    }

    // Get exceptions status
    $exceptions_status = get_exceptions_status();

    json_response(200, null, $exceptions_status);

}

/*******************************
 * FUNCTION: GET AUDIT LOG API *
 *******************************/
function get_audit_logs_api() 
{
    global $escaper, $lang;

    if (is_admin()) 
    {
        $days = get_param("get", "days", 7);
        $log_type = get_param("get", "log_type", NULL);
        
        // If log_type is string, try to make array by comman and trim all values
        if($log_type)
        {
            $log_type = str_getcsv($log_type, ',', '"', '');
        }
        else
        {
            $log_type = NULL;
        }

        // Audit messages are now stored as plain text (the prior write-time
        // escapeHtml was reverted in favor of escape-at-render). Mirror the
        // sink-side encoding that get_exceptions_audit_log_api applies so
        // consumers receive HTML-safe text on the wire.
        json_response(200, null, array_map(function($log) use ($escaper) {
                return array(
                    'timestamp' => date(get_default_datetime_format("g:i A T"), strtotime($log['timestamp'])),
                    'username' => $log['user_fullname'],
                    'message' => $escaper->escapeHtml($log['message'])
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

/****************************
 * FUNCTION: CVE LOOKUP API *
 ****************************/
function cve_lookup_api() {

    global $escaper, $lang;

    // Require a valid SimpleRisk session
    if (!is_session_authenticated()) {
        unauthenticated_access();
        return;
    }

    $cve_id = isset($_GET['cve_id']) ? trim($_GET['cve_id']) : '';
    $cve_pattern = '/^CVE-\d{4}-\d{4,7}$/i';
    if (!preg_match($cve_pattern, $cve_id)) {
        json_response(400, 'Invalid CVE ID format.', NULL);
        return;
    }

    $cve_id = strtoupper(preg_replace('/[^A-Z0-9-]/', '', $cve_id));
    if (!preg_match($cve_pattern, $cve_id)) {
        json_response(400, 'Invalid CVE ID.', NULL);
        return;
    }

    $url = 'https://services.nvd.nist.gov/rest/json/cves/2.0?cveId=' . $cve_id;

    // Set the HTTP options
    $http_options = [
        'method' => 'GET',
        'header' => [
            "Content-Type: application/x-www-form-urlencoded",
        ],
        'timeout' => 15,
    ];

    // If SSL certificate checks are enabled for external requests
    if (ssl_external_verify_enabled())
    {
        // Verify the SSL host and peer
        $validate_ssl = true;
    } else {
        $validate_ssl = false;
    }

    // Get the response
    $response = fetch_url_content("curl", $http_options, $validate_ssl, $url);
    $return_code = $response['return_code'];

    $response_data = $response['response'] ?? '';

    if ($return_code !== 200 || !is_string($response_data) || $response_data === '') {
        set_alert(true, "bad", $escaper->escapeHtml($lang['FailedToFetchCVEInformation']));
        json_response(400, get_alert(true), NULL);
        return;
    }
    
    $data = json_decode($response_data, true);
    if ($data === null) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['FailedToFetchCVEInformation']));
        json_response(400, get_alert(true), NULL);
        return;
    }

    json_response(200, null, $data);
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

/*******************************************
 * FUNCTION: ASSET GROUP CRUD - LIST ALL   *
 *******************************************/
function listAssetGroups()
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $db = db_open();
    $stmt = $db->prepare("SELECT id, name FROM `asset_groups` ORDER BY name");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    json_response(200, "SUCCESS", ['asset_groups' => $groups]);
}

/***********************************************
 * FUNCTION: ASSET GROUP CRUD - CREATE         *
 ***********************************************/
function createAssetGroupCrud()
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $name = get_param("POST", "name", null);
    if (!$name) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyANameParameter']), NULL);
        return;
    }

    if (get_value_by_name('asset_groups', $name)) {
        json_response(400, $escaper->escapeHtml($lang['AssetGroupNameAlreadyInUse']), NULL);
        return;
    }

    $selected_assets = $_POST['selected_assets'] ?? [];

    try {
        $id = create_asset_group($name, $selected_assets);
    } catch (Exception $e) {
        error_log($e);
        $id = false;
    }

    if ($id) {
        json_response(200, $escaper->escapeHtml($lang['AssetGroupCreatedSuccessfully']), ['id' => $id]);
    } else {
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemCreatingTheAssetGroup']), NULL);
    }
}

/***********************************************
 * FUNCTION: ASSET GROUP CRUD - GET BY ID      *
 ***********************************************/
function getAssetGroupById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    try {
        $group = get_asset_group($id);
    } catch (Exception $e) {
        error_log($e);
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemGettingTheAssetGroup']), NULL);
        return;
    }

    if (empty($group)) {
        json_response(404, "NOT FOUND: Unable to find an asset group with the specified id.", NULL);
        return;
    }

    json_response(200, "SUCCESS", ['asset_group' => $group]);
}

/***********************************************
 * FUNCTION: ASSET GROUP CRUD - UPDATE         *
 ***********************************************/
function updateAssetGroupById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? get_param("POST", "id", 0));
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    // Load current group to use as defaults for omitted fields
    try {
        $current = get_asset_group($id);
    } catch (Exception $e) {
        error_log($e);
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemGettingTheAssetGroup']), NULL);
        return;
    }

    if (empty($current)) {
        json_response(404, "NOT FOUND: Unable to find an asset group with the specified id.", NULL);
        return;
    }

    $name = get_param("POST", "name", null) ?: $current['name'];
    $selected_assets = isset($_POST['selected_assets'])
        ? $_POST['selected_assets']
        : array_column($current['selected_assets'], 'id');

    // Validate name uniqueness (allow same name if it's this group's own name)
    $id_check = get_value_by_name('asset_groups', $name);
    if ($id_check != $id && $id_check !== null) {
        json_response(400, $escaper->escapeHtml($lang['AssetGroupNameAlreadyInUse']), NULL);
        return;
    }

    try {
        update_asset_group($id, $name, $selected_assets);
    } catch (Exception $e) {
        error_log($e);
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemUpdatingTheAssetGroup']), NULL);
        return;
    }

    json_response(200, $escaper->escapeHtml($lang['AssetGroupUpdatedSuccessfully']), ['id' => $id]);
}

/***********************************************
 * FUNCTION: ASSET GROUP CRUD - DELETE         *
 ***********************************************/
function deleteAssetGroupById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    // Verify the group exists
    $current = get_asset_group($id);
    if (empty($current)) {
        json_response(404, "NOT FOUND: Unable to find an asset group with the specified id.", NULL);
        return;
    }

    try {
        $deleted = delete_asset_group($id);
    } catch (Exception $e) {
        error_log($e);
        $deleted = false;
    }

    if (!$deleted) {
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemDeletingTheAssetGroup']), NULL);
        return;
    }

    json_response(200, $escaper->escapeHtml($lang['AssetGroupDeletedSuccessfully']), NULL);
}

/***********************************************
 * FUNCTION: ASSET GROUP CRUD - GET ASSETS     *
 ***********************************************/
function getAssetGroupAssets($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $current = get_asset_group($id);
    if (empty($current)) {
        json_response(404, "NOT FOUND: Unable to find an asset group with the specified id.", NULL);
        return;
    }

    $assets = get_assets_of_asset_group($id);
    json_response(200, "SUCCESS", ['assets' => $assets]);
}

/***********************************************
 * FUNCTION: ASSET GROUP CRUD - ADD ASSETS     *
 ***********************************************/
function addAssetsToAssetGroup($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? get_param("POST", "id", 0));
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $asset_ids = $_POST['asset_ids'] ?? [];
    if (empty($asset_ids)) {
        json_response(400, "BAD REQUEST: asset_ids[] is required.", NULL);
        return;
    }

    // Load current group
    try {
        $current = get_asset_group($id);
    } catch (Exception $e) {
        error_log($e);
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemGettingTheAssetGroup']), NULL);
        return;
    }

    if (empty($current)) {
        json_response(404, "NOT FOUND: Unable to find an asset group with the specified id.", NULL);
        return;
    }

    // Merge current asset IDs with new ones (deduplicated)
    $current_ids = array_column($current['selected_assets'], 'id');
    $merged_ids = array_values(array_unique(array_merge($current_ids, array_map('intval', $asset_ids))));

    try {
        update_asset_group($id, $current['name'], $merged_ids);
    } catch (Exception $e) {
        error_log($e);
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemUpdatingTheAssetGroup']), NULL);
        return;
    }

    json_response(200, $escaper->escapeHtml($lang['AssetGroupUpdatedSuccessfully']), ['id' => $id]);
}

/***********************************************
 * FUNCTION: ASSET GROUP CRUD - REMOVE ASSET   *
 ***********************************************/
function removeAssetFromAssetGroupById($id = null, $asset_id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? 0);
    $asset_id = (int)($asset_id ?? 0);
    if (!$id || !$asset_id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $current = get_asset_group($id);
    if (empty($current)) {
        json_response(404, "NOT FOUND: Unable to find an asset group with the specified id.", NULL);
        return;
    }

    try {
        $removed = remove_asset_from_asset_group($asset_id, $id);
    } catch (Exception $e) {
        error_log($e);
        $removed = false;
    }

    if (!$removed) {
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemRemovingTheAssetFromAssetGroup']), NULL);
        return;
    }

    json_response(200, $escaper->escapeHtml($lang['AssetRemovedFromAssetGroupSuccessfully']), NULL);
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

    if (check_permission("asset") || check_permission("assessments") || check_permission("riskmanagement") || check_permission('im_incidents')) {
        $id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;
        $type = isset($_GET['type']) ? $_GET['type'] : null;
        $selected_only = isset($_GET['selected_only']) ? $_GET['selected_only'] : false;
        json_response(200, null, get_assets_and_asset_groups_of_type($id, $type, $selected_only));
    } else {
        global $lang;

        set_alert(true, "bad", $lang['NoPermissionForAssetAssetGroupList']);
        json_response(400, get_alert(true), NULL);
    }
}
function get_asset_group_options_by_control() {

    if (check_permission("asset") || check_permission("governance")) {
        $control_id = isset($_GET['control_id']) ? (int)$_GET['control_id'] : false;
        $control_maturity = isset($_GET['control_maturity']) ? (int)$_GET['control_maturity'] : false;
        json_response(200, null, get_assets_and_asset_groups_by_control_for_dropdown($control_id, $control_maturity));
    } else {
        global $lang;
        set_alert(true, "bad", $lang['NoPermissionForAssetAssetGroupList']);
        json_response(400, get_alert(true), NULL);
        return;
    }
}

function get_asset_group_options_noauth() {
    if (get_setting("ASSESSMENT_ASSET_SHOW_AVAILABLE") && check_questionnaire_get_token()) {
        json_response(200, null, get_assets_and_asset_groups_of_type());
    } else {
        global $lang;
        set_alert(true, "bad", $lang['NoPermissionForAssetAssetGroupList']);
        json_response(400, get_alert(true), NULL);
    }
}

/***************************************
 * FUNCTION: GET MANAGER ID BY USER ID *
 ***************************************/
function getManagerByUserAPI()
{
    global $lang, $escaper;

    // SR-1779: scope the /user/manager lookup to its only legitimate callers —
    // the add/edit risk forms (Owner → Owner's-Manager auto-fill). Without this
    // any authenticated user can enumerate every user's reporting line by id.
    if (!check_permission("submit_risks") && !check_permission("modify_risks")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

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

    // Check if the user has permission to add saved risk reports
    if (!check_permission("add_saved_risk_reports")) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionAddSavedRiskReports']));
        
        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    
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
    
    // Check if the user has permission to delete saved selections
    if (!check_permission("delete_saved_risk_reports")) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionDeleteSavedRiskReports']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

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

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }
    
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

            // In case there's no column selected that is orderable the order won't be sent from the client
            if (!empty($_GET['order'])) {

                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
                $orderColumn = (int)$_GET['order'][0]['column'];
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
                $orderDir = strtolower($_GET['order'][0]['dir']) == "asc" ? "asc" : "desc";

            } else {

                $orderColumn = 0; // Default to the first column
                $orderDir = "asc";
                
            }
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
                    "<a class='open-in-new-tab' href='../management/view.php?id=" . $escaper->escapeHtml($risk['id']) . "' target='_blank'>" . $escaper->escapeHtml($risk['id']) . "</a>",
                    $escaper->escapeHtml($risk['subject']),
                    "<div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class='risk-color " . $escaper->escapeHtml($risk['color']) . "' style='background-color:" . $escaper->escapeCssColor($risk['color']) . "'></span></div>",
                    "<div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['residual_risk']) . "<span class='risk-color " . $escaper->escapeHtml($risk['residual_color']) . "' style='background-color:" . $escaper->escapeCssColor($risk['residual_color']) . "'></span></div>"
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

            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            $orderColumn = (int)$_POST['order'][0]['column'];
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
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
            $unique_keys = [];

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

        // Check that it PARSES before requiring it, and replace it if it does
        // not.
        //
        // This endpoint is the recovery path for a broken instance, and it used
        // to require the Upgrade Extra as its first act. A syntax error in that
        // file is an uncatchable fatal -- require cannot be wrapped in
        // try/catch for a parse error -- so a damaged extra killed the one
        // endpoint capable of downloading a replacement. The extra was
        // unrepairable by the mechanism whose entire job is repairing things.
        //
        // token_get_all() with TOKEN_PARSE throws a catchable ParseError, which
        // is the difference between detecting this and dying on it, and core's
        // own download_extra() reinstalls without needing the extra loaded.
        $upgrade_extra_path = realpath(__DIR__ . '/../extras/upgrade/index.php');
        $upgrade_extra_parses = true;
        try {
            $upgrade_extra_source = @file_get_contents($upgrade_extra_path);
            if ($upgrade_extra_source === false) {
                $upgrade_extra_parses = false;
            } else {
                token_get_all($upgrade_extra_source, TOKEN_PARSE);
            }
        } catch (\ParseError $e) {
            $upgrade_extra_parses = false;
            write_debug_log('one_click_upgrade: the installed Upgrade Extra does not parse (' . $e->getMessage() . '); attempting to reinstall it.', 'error');
        }

        if (!$upgrade_extra_parses) {
            echo $escaper->escapeHtml($lang['UpgradeExtraDamagedReinstalling'] ?? 'The installed Upgrade Extra is damaged. Downloading a fresh copy.') . "<br />\n";

            $reinstall = download_extra('upgrade');

            // download_extra()'s contract is "a string return means installed".
            $reinstalled = is_string($reinstall);
            if ($reinstalled) {
                // Only trust it if the replacement actually parses.
                try {
                    $replacement = @file_get_contents($upgrade_extra_path);
                    token_get_all($replacement === false ? '' : $replacement, TOKEN_PARSE);
                } catch (\ParseError $e) {
                    $reinstalled = false;
                }
            }

            if (!$reinstalled) {
                write_debug_log('one_click_upgrade: could not reinstall the damaged Upgrade Extra.', 'error');
                echo $escaper->escapeHtml($lang['UpgradeExtraDamagedFailed'] ?? 'The Upgrade Extra is damaged and could not be replaced automatically. Reinstall it from the Extras page, or restore simplerisk/extras/upgrade/ from a backup, then try again.') . "<br />\n";
                return;
            }

            echo $escaper->escapeHtml($lang['UpgradeExtraDamagedRepaired'] ?? 'The Upgrade Extra was replaced with a working copy.') . "<br />\n";
        }

        // Require the upgrade extra file
        require_once($upgrade_extra_path);

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

        // Two-phase upgrade: the JavaScript calls this endpoint once with
        // step=update_extra to download the latest upgrade extra (so the next
        // request loads new code from disk), then makes a direct POST to
        // /extras/upgrade/ for the backup and upgrade steps — eliminating the
        // dependency on this old core's call_extra_api_functionality for
        // long-running operations that can trigger nginx proxy timeouts.
        //
        // Handled BEFORE the "nothing to update" return below, and the download
        // is deliberately NOT gated on the extra being out of date.
        //
        // The reason is recovery. The Upgrade Extra is how a broken instance is
        // repaired, so it has to be repairable itself — and the way that has
        // always been done is to publish a new copy and let the instance pull
        // it. Only downloading when the installed version compares older breaks
        // that in the one case it is needed most: an instance sitting on the
        // current version with a damaged copy of it would refuse to refresh,
        // and the mechanism meant to un-strand it is the thing that is stranded.
        // Re-fetching a copy it already has costs one small download; not being
        // able to costs the recovery path.
        if (isset($_POST['step']) && $_POST['step'] === 'update_extra') {
            if ($is_upgrade_mode_extra) {
                stream_write(_lang('UpdateExtrasExtraUpdateStarted', array('extra' => 'upgrade')));
                list($status, $result) = call_extra_api_functionality('upgrade', 'upgrade', 'app');
                if ($status !== 200) {
                    if (is_array($result) && !empty($result['status_message'])) { stream_write_error($result['status_message']); }
                    stream_write_error($lang['BackupFailed'] ?? 'Upgrade Extra update failed.');
                } elseif (is_array($result) && !empty($result['status_message'])) {
                    stream_write($result['status_message']);
                }
            }
            // Phase 1 done — return so the JavaScript can call /extras/upgrade/
            // directly for backup and upgrade (phase 2).
            return;
        }

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
                if (is_array($result) && !empty($result['status_message'])) { stream_write_error($result['status_message']); }
                stream_write_error($lang['BackupFailed']);
                return;
            }
            // Print the success message
            stream_write($result['status_message']);

            // Do the database backup
            stream_write($lang['BackupStartDatabase']);
            list($http_status, $result) = call_extra_api_functionality('upgrade', 'backup', 'db');
            // The DB backup endpoint flushes whitespace keep-alives to prevent proxy
            // timeouts, which commits HTTP 200 headers early. Read status from the
            // JSON body field instead of the HTTP status code.
            $status = (is_array($result) && isset($result['status'])) ? (int)$result['status'] : $http_status;

            // Check the results
            if ($status !== 200) {
                // Print the error message if the call failed for some reason and stop the upgrade
                if (is_array($result) && !empty($result['status_message'])) { stream_write_error($result['status_message']); }
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
                if (is_array($result) && !empty($result['status_message'])) { stream_write_error($result['status_message']); }
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
                    if (is_array($result) && !empty($result['status_message'])) { stream_write_error($result['status_message']); }
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

                // The post-chain conversions run inside upgrade_database(),
                // after the chain returns -- not here, and no longer in the
                // base case of its recursion, which was unreachable at the end
                // of a real upgrade. Its gate deliberately also accepts
                // "the database is on the newest known release", because
                // APP_VERSION is stale on THIS path: upgrade_application()
                // swapped the files earlier in this same request and a constant
                // cannot be redefined.
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
                        if (is_array($result) && !empty($result['status_message'])) { stream_write_error($result['status_message']); }
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

    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    $draw   = $escaper->escapeHtml($_POST['draw']);
    $score_used = isset($_GET['score_used']) && $_GET['score_used'] === 'residual' ? 'residual' : 'inherent';

    $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
    $length = $_POST['length'] ? (int)$_POST['length'] : 10;
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderColumnName = isset($_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderDir = isset($_POST['order'][0]['dir']) && strtoupper($_POST['order'][0]['dir']) === 'ASC'? "ASC" : 'DESC';
    $column_filters = [];
    for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
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
                    ROUND(`scoring`.`calculated_risk` - (`scoring`.`calculated_risk` * IF(IFNULL(`mtg`.`mitigation_percent`,0) > 0, mtg.mitigation_percent, IFNULL(MAX(`ctrl`.`mitigation_percent`), 0)) / 100), 2) AS residual_score
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
            "<a class='open-in-new-tab' href=\"../management/view.php?id=" . $escaper->escapeHtml($risk['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($risk['id'])."</a>",
            $escaper->escapeHtml($risk['status']),
            $escaper->escapeHtml($subject),
            "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['score']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeCssColor($color) . "\"></span></div></div>",
            $escaper->escapeHtml($submission_date),
            $mitigation_planned, // mitigation plan
            // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
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
                // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
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
        $name = isset($_POST['new_project']) ? trim($_POST['new_project']) : "";
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
    // Editing an existing project is a manage-projects action, not an
    // add-projects one. add_projects gates only the creation of new
    // projects (see add_project_api above); modifying an existing record
    // belongs to the manage_projects authority used by update / status /
    // order below. Flagged via HackerOne; the previous gate let an
    // add-only user mutate any existing project by id.
    if(isset($_SESSION["manage_projects"]) && $_SESSION["manage_projects"] == 1){
        $name = isset($_POST['name']) ? trim($_POST['name']) : "";
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
            // Save custom field values (no-op if customization extra is disabled)
            call_extra_function(
                'customization_extra',
                __DIR__ . '/../extras/customization/index.php',
                'save_custom_field_values',
                [$value, "project"]
            );

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
    // Reading an existing project's full record is a manage-projects
    // action, not an add-projects one. add_projects gates only the
    // creation of new projects; viewing any existing project by id
    // belongs to the manage_projects authority. Flagged via HackerOne;
    // the previous gate let an add-only user enumerate and read any
    // project record by id.
    if(isset($_SESSION["manage_projects"]) && $_SESSION["manage_projects"] == 1){
        $result = get_project($value);
        $result['name'] = try_decrypt($result['name']);
        $result['due_date'] = format_date($result['due_date']);

        if (!empty($result['custom_values'])) {
            foreach ($result['custom_values'] as &$custom_value) {
                switch ($custom_value['field_type']) {
                    case 'shorttext':
                    case 'longtext':
                        // If encryption for this field is enabled, decrypt value
                        if ($custom_value['encryption']) {
                            $custom_value['value'] = try_decrypt($custom_value['value']);
                        }
                        break;
                    case 'date':
                        $custom_value['value'] = $custom_value['value'] ? format_date($custom_value['value']) : '';
                        break;
                    case 'multidropdown':
                    case 'user_multidropdown':
                        $custom_value['value'] = $custom_value['value'] ? explode(',', $custom_value['value']) : '';
                        break;
                }
            }
        }

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

                // Delete custom project data (no-op if customization extra is disabled)
                call_extra_function(
                    'customization_extra',
                    __DIR__ . '/../extras/customization/index.php',
                    'delete_custom_data_by_row_id',
                    [$value, "project"]
                );

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
function addRiskCatalogAPI() {

    global $lang, $escaper;

    if (is_admin()) {

        $data = array(
            "number" => !empty($_POST['number']) ? trim($_POST['number']) : "",
            "grouping" => isset($_POST['risk_grouping']) ? $_POST['risk_grouping'] : 0,
            "name" => !empty($_POST['name']) ? trim($_POST['name']) : "",
            "description" => isset($_POST['description']) ? $_POST['description'] : "",
            "function" => isset($_POST['risk_function']) ? $_POST['risk_function'] : 0,
        );

        if (!$data["number"]) {

            // Display an alert
            set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['Risk'])));
            json_response(400, get_alert(true), NULL);

        } else if (!$data["name"]) {

            // Display an alert
            set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['RiskEvent'])));
            json_response(400, get_alert(true), NULL);

        } else {

            // Add Risk Catalog
            add_risk_catalog($data);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang["ANewRiskCatalogItemWasAddedSuccessfully"]));
            json_response(200, get_alert(true), NULL);

        }

    } else {

        unauthorized_access();

    }
}

/********************************
 * FUNCTION: ADD THREAT CATALOG *
 ********************************/
function addThreatCatalogAPI() {

    global $lang, $escaper;

    if (is_admin()) {
        
        $data = array(
            "number" => !empty($_POST['number']) ? trim($_POST['number']) : "",
            "grouping" => isset($_POST['threat_grouping']) ? $_POST['threat_grouping'] : 0,
            "name" => !empty($_POST['name']) ? trim($_POST['name']) : "",
            "description" => isset($_POST['description']) ? $_POST['description'] : "",
        );

        if (!$data["number"]) {

            // Display an alert
            set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['Threat'])));
            json_response(400, get_alert(true), NULL);

        } elseif (!$data["name"]) {

            // Display an alert
            set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['ThreatEvent'])));
            json_response(400, get_alert(true), NULL);

        } else {

            // Add Threat Catalog
            add_threat_catalog($data);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang["ANewThreatCatalogItemWasAddedSuccessfully"]));
            json_response(200, get_alert(true), NULL);

        }
        
    } else {

        unauthorized_access();

    }
}

/*********************************
 * FUNCTION: UPDATE RISK CATALOG *
 *********************************/
function updateRiskCatalogAPI() {

    global $lang, $escaper;

    if (is_admin()) {

        $data = array(
            "id" => isset($_POST['id']) ? $_POST['id'] : "",
            "number" => !empty($_POST['number']) ? trim($_POST['number']) : "",
            "grouping" => isset($_POST['risk_grouping']) ? $_POST['risk_grouping'] : 0,
            "name" => !empty($_POST['name']) ? trim($_POST['name']) : "",
            "description" => isset($_POST['description']) ? $_POST['description'] : "",
            "function" => isset($_POST['risk_function']) ? $_POST['risk_function'] : 0,
        );

        if (!$data["id"]) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang["TheDataIDWasNotAValidValue"]));
            json_response(400, get_alert(true), NULL);

        } elseif (!$data["number"]) {

            // Display an alert
            set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['Risk'])));
            json_response(400, get_alert(true), NULL);

        } elseif (!$data["name"]) {
            
            // Display an alert
            set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['RiskEvent'])));
            json_response(400, get_alert(true), NULL);

        } else {

            // Update Risk Catalog
            update_risk_catalog($data);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang["AnExistingRiskCatalogItemWasUpdatedSuccessfully"]));
            json_response(200, get_alert(true), NULL);

        }

    } else {

        unauthorized_access();

    }
}

/***********************************
 * FUNCTION: UPDATE THREAT CATALOG *
 ***********************************/
function updateThreatCatalogAPI() {

    global $lang, $escaper;

    if (is_admin()) {

        $data = array(
            "id" => isset($_POST['id']) ? $_POST['id'] : "",
            "number" => !empty($_POST['number']) ? trim($_POST['number']) : "",
            "grouping" => isset($_POST['threat_grouping']) ? $_POST['threat_grouping'] : 0,
            "name" => !empty($_POST['name']) ? trim($_POST['name']) : "",
            "description" => isset($_POST['description']) ? $_POST['description'] : "",
        );

        if (!$data["id"]) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang["TheDataIDWasNotAValidValue"]));
            json_response(400, get_alert(true), NULL);

        } elseif (!$data["number"]) {

            // Display an alert
            set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['Threat'])));
            json_response(400, get_alert(true), NULL);

        } elseif (!$data["name"]) {

            // Display an alert
            set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['ThreatEvent'])));
            json_response(400, get_alert(true), NULL);

        } else {

            // Update Threat Catalog
            update_threat_catalog($data);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang["AnExistingThreatCatalogItemWasUpdatedSuccessfully"]));
            json_response(200, get_alert(true), NULL);

        }

    } else {

        unauthorized_access();

    }
}

/*********************************
 * FUNCTION: DELETE RISK CATALOG *
 *********************************/
function deleteRiskCatalogAPI() {

    global $lang, $escaper;

    if (is_admin()) {

        $id = isset($_POST['id']) ? $_POST['id'] : "";

        if (!$id) {

            // Display an alert
            set_alert(true, "bad", "The data ID was not a valid value.  Please try again.");

        } else {

            delete_risk_catalog($id);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['AnExistingRiskCatalogItemWasDeletedSuccessfully']));

        }

        json_response(200, get_alert(true), NULL);
        return true;

    } else {

        unauthorized_access();

    }
}

/***********************************
 * FUNCTION: DELETE THREAT CATALOG *
 ***********************************/
function deleteThreatCatalogAPI() {

    global $lang, $escaper;

    if (is_admin()) {

        $id = isset($_POST['id']) ? $_POST['id'] : "";
        
        if (!$id) {

            // Display an alert
            set_alert(true, "bad", "The data ID was not a valid value.  Please try again.");

        } else {

            delete_threat_catalog($id);
            
            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['AnExistingThreatCatalogItemWasDeletedSuccessfully']));

        }

        json_response(200, get_alert(true), NULL);
        return true;

    } else {

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
        // SR-1870: reject any column name that isn't a plain [A-Za-z0-9_] token
        // before storing it (it is later echoed into a data-name attribute / JS).
        if (!custom_display_columns_are_valid([$_POST["risk_columns"], $_POST["mitigation_columns"], $_POST["review_columns"]])) {
            set_alert(true, "bad", $lang['NoDataAvailable']);
            json_response(400, get_alert(true), NULL);
            return;
        }
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
        // SR-1870: reject any column name that isn't a plain [A-Za-z0-9_] token
        // before storing it (it is later echoed into a data-name attribute / JS).
        if (!custom_display_columns_are_valid([$_POST["risk_columns"], $_POST["mitigation_columns"], $_POST["review_columns"]])) {
            set_alert(true, "bad", $lang['NoDataAvailable']);
            json_response(400, get_alert(true), NULL);
            return;
        }
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
        // SR-1870: reject any column name that isn't a plain [A-Za-z0-9_] token
        // before storing it (it is later echoed into a data-name attribute / JS).
        if (!custom_display_columns_are_valid([$_POST["risk_columns"], $_POST["mitigation_columns"], $_POST["review_columns"]])) {
            set_alert(true, "bad", $lang['NoDataAvailable']);
            json_response(400, get_alert(true), NULL);
            return;
        }
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
        
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $order_column = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
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
                    $row['id'] = "<div class='open-risk'><a target=\"_blank\" href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_to_risk_id($file['risk_id'])) . "\">" . $escaper->escapeHtml(convert_to_risk_id($file['risk_id'])) . "</a></div>";
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

        // Refused outright on a demo instance, and this one destroys data if it
        // isn't. Each branch below replaces an existing attachment by uploading
        // the fixed copy and then deleting the old row — but under DEMO_MODE the
        // upload helpers accept the file and store nothing while still reporting
        // success, so the delete would run against a replacement that was never
        // written. The original file would be gone for good.
        //
        // This is an admin-only maintenance tool, so a demo visitor cannot reach
        // it (demo accounts are not administrators) — but the operator signing in
        // to their own demo can, which is exactly when losing the demo's files
        // would hurt.
        if (demo_mode()) {
            set_alert(true, "bad", $lang['ActionDisabledOnDemoInstance']);
            json_response(400, get_alert(true), NULL);
        }

        $type = isset($_POST['type']) && in_array($_POST['type'], ['risk', 'compliance', 'questionnaire']) ? $_POST['type'] : false;

        if (!$type) {
            set_alert(true, "bad", $lang['YouNeedToSpecifyATypeParameter']);
            json_response(400, get_alert(true), NULL);
        }

        // If the user wants to upload a `questionnaire` type file, check if the assessment extra file exists
        if ($type === 'questionnaire') {
            if(file_exists(realpath(__DIR__ . '/../extras/assessments/index.php'))) {
                // Include the file
                require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));
            } else {
                set_alert(true, "bad", $lang['NoPermissionForAssessments']);
                json_response(400, get_alert(true), NULL);
            }
        }

        $unique_name = $_POST['unique_name'];

        $file_info = get_encoding_issue_file_info($type, $unique_name);

        if (!$file_info) {
            set_alert(true, "bad", $lang['InvalidUniqueName']);
            json_response(400, get_alert(true), NULL);
        }

        $log_type = null;

        switch($type) {
            case 'risk':
                $log_type = 'risk';
                $error = upload_file($file_info['risk_id'], $_FILES['file'], $file_info['view_type']);
                if ($error === 1) {
                    delete_db_file($unique_name);
                } else {
                    json_response(400, $escaper->escapeHtml($error), NULL);
                }
            break;
            case 'compliance':
                $log_type = 'test_audit';
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
                $log_type = 'questionnaire';
                $files = array(
                    'name' => [$_FILES['file']['name']],
                    'type' => [$_FILES['file']['type']],
                    'tmp_name' => [$_FILES['file']['tmp_name']],
                    'size' => [$_FILES['file']['size']],
                    'error' => [$_FILES['file']['error']]
                );

                // It's ok to use the same logic for files attached to the answer or the questionnaire as in case of the file attached to the questionnaire
                // the files `template_id`, `question_id` and `parent_question_id` will be 0 anyway(this is the default what's used whe those parameters aren't present)
                // The tainted member of $files is the user-supplied original filename
                // ($_FILES['file']['name']). Inside upload_questionnaire_files() it is
                // used ONLY for pathinfo(...PATHINFO_EXTENSION) (a string op, no FS
                // access) and as a parameterized DB column; the file content is read
                // from the server-generated tmp_name and stored as a DB blob under a
                // random generate_token(30) unique_name. No filesystem path is built
                // from user input, so the PathTraversal flow is not reachable.
                // @phan-suppress-next-line SecurityCheck-PathTraversal -- user filename only reaches pathinfo() (string) + a parameterized DB column; content is stored as a DB blob under a random unique_name, never a user-controlled FS path
                $result = upload_questionnaire_files($file_info['tracking_id'], $files, $file_info['template_id'], $file_info['question_id'], $file_info['parent_question_id']);

                // Check if there was an error
                if($result['status'] !== true && is_array($result['data'])){
                    json_response(400, $escaper->escapeHtml($result['data'][0]), NULL);
                    return;
                } else { // Delete the original file if everything went well with the upload
                    delete_assessment_file($file_info['id']);
                }
            break;
        }
        $setting_name = "file_encoding_issues_count_{$type}";

        $old_count = (int)get_setting($setting_name);
        $count = $old_count - 1;

        // Refresh the numbers in the database
        if ($count > 0) {
            update_or_insert_setting($setting_name, $count);
            write_log(0, $_SESSION['uid'] ?? 0, _lang('EncodingIssueCountUpdated', ['type' => $type, 'old_count' => $old_count, 'count' => $count]), $log_type);
        } else {
            // If all files of this type are supposedly fixed check if they really are
            refresh_file_encoding_issue_counts($type);
        }
    } else {
        unauthorized_access();
    }
}
/***************************************************************************************
 * END OF SECTION CONTAINING FUNCTIONS DEDICATED TO FIXING FILE UPLOAD ENCODING ISSUES *
 ***************************************************************************************/



/*****************************************************
 * FUNCTION: REPORTS - All Open Risks Assigned to Me *
 * The My Open Risk Report datatable's API function  *
 *****************************************************/
function my_open_risk_datatable() {
    global $escaper;

    $draw   = $escaper->escapeHtml($_POST['draw']);

    $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
    $length = $_POST['length'] ? (int)$_POST['length'] : 10;
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderColumnName = isset($_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderDir = isset($_POST['order'][0]['dir']) && strtoupper($_POST['order'][0]['dir']) === 'ASC'? "ASC" : 'DESC';
    $column_filters = [];
    for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
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
            SELECT SQL_CALC_FOUND_ROWS a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * IF(IFNULL(mg.mitigation_percent,0) > 0, mg.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2) as residual_risk
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
            SELECT SQL_CALC_FOUND_ROWS a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * IF(IFNULL(mg.mitigation_percent,0) > 0, mg.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2) as residual_risk
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
            "<a class='open-in-new-tab' href='../management/view.php?id=" . $escaper->escapeHtml($risk['id']) . "' target='_blank'>" . $escaper->escapeHtml($risk['id']) . "</a>",
            $escaper->escapeHtml($risk['status']),
            $escaper->escapeHtml($subject),
            "<div class='" . $escaper->escapeHtml($color) . "'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class='risk-color' style='background-color:" . $escaper->escapeCssColor($color) . "'></span></div></div>",
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
    global $escaper, $lang;

    if (!check_permission("riskmanagement")) {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForRiskManagement']), NULL);
        return;
    }

    $draw   = $escaper->escapeHtml($_POST['draw']);

    $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
    $length = $_POST['length'] ? (int)$_POST['length'] : 10;
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderColumnName = isset($_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
    // @phan-suppress-next-line PhanTypeMismatchDimFetch
    $orderDir = isset($_POST['order'][0]['dir']) && strtoupper($_POST['order'][0]['dir']) === 'ASC'? "ASC" : 'DESC';
    $column_filters = [];
    for ( $i=0 ; $i<count($_POST['columns']) ; $i++ ) {
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        if ( isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '' ) {
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
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
        SELECT SQL_CALC_FOUND_ROWS a.calculated_risk, b.*, ROUND((a.calculated_risk - (a.calculated_risk * IF(IFNULL(mg.mitigation_percent,0) > 0, mg.mitigation_percent, IFNULL(MAX(IF(mtc.validation_mitigation_percent > 0, mtc.validation_mitigation_percent, fc.mitigation_percent)), 0)) / 100)), 2) as residual_risk
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
            "<a class='open-in-new-tab' href=\"../management/view.php?id=" . $escaper->escapeHtml($risk['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($risk['id'])."</a>",
            $escaper->escapeHtml($risk['status']),
            $escaper->escapeHtml($subject),
            "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeCssColor($color) . "\"></span></div></div>",
            "<div class='".$escaper->escapeHtml($residual_color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['residual_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeCssColor($residual_color) . "\"></span></div></div>",
            $escaper->escapeHtml($comment_date),
            // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
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

        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumn = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumnName = isset($_GET['columns'][$orderColumn]['name']) ? $_GET['columns'][$orderColumn]['name'] : null;
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
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
    global $lang, $escaper;

    // This endpoint feeds the admin-only Contributing Risks scoring
    // configuration page (admin/risk_configuration.php). Restrict it to
    // administrators, matching its add/update/delete sibling handlers.
    if (!is_admin()) {
        set_alert(true, "bad", $escaper->escapeHtml($lang["AdminPermissionRequired"]));
        json_response(400, get_alert(true), null);
    }

    $table = get_param("POST", "table", "likelihood");
    if($table == "likelihood")
        $table_list = display_contributing_risks_likelihood_table_list();
    else
        $table_list = display_contributing_risks_impact_table_list();
    echo $table_list;exit;
}

/***************************************
 * FUNCTION: SAVE GRAPHICAL SELECTIONS *
 ***************************************/
function saveGraphicalSelectionsForm() {

    global $lang, $escaper;

    // Check if the user has permission to add saved risk reports
    if (!check_permission("add_saved_risk_reports")) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionAddSavedRiskReports']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $type = get_param("post", "selection_type");
    $name = get_param("post", "selection_name");

    // Check if the type isn't empty
    if (empty($type)) {

        set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['Type'])));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);

    }

    // Restrict selection_type to the known enum (the UI only ever sends
    // 'private'/'public'). Blocks storing an arbitrary 'type' value.
    if (!is_valid_graphical_selection_type($type)) {

        // Pass the raw lang string — get_alert(true) escapes it once at read time
        // (pre-escaping here would double-encode; see alerts.php).
        set_alert(true, "bad", $lang['InvalidParams']);

        // Return a JSON response
        json_response(400, get_alert(true), NULL);

    }

    // Check if the name isn't empty or trimmed empty
    if (empty($name) || trim($name) == "") {

        set_alert(true, "bad", _lang('FieldRequired', array("field"=>$lang['Name'])));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);

    }
    
    // Check if this name already existing
    if(check_exisiting_graphical_selection_name($_SESSION['uid'], $name)) {

        set_alert(true, "bad", $lang['TheNameAlreadyExists']);
        json_response(400, get_alert(true), []);

    } else {

        $graphic_form_data = $_POST;
        if (isset($graphic_form_data['__csrf_magic'])) {

            unset($graphic_form_data['__csrf_magic']);

        }

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

    // Check if the user has permission to delete saved selections
    if (!check_permission("delete_saved_risk_reports")) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionDeleteSavedRiskReports']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

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

/*******************************************
 * FUNCTION: API COMPLIANCEFORGESCF STATUS *
 *******************************************/
function api_complianceforgescf_status()
{
    // If the user calling this is an admin
    if (is_admin())
    {
        $data = [];
        // If the SCF is loading
        if (get_setting("scf_status") == "loading")
        {
            // Set the update status to loading
            $data["loading"] = true;
        }
        else $data["loading"] = false;

        // Return a 200 response
        return json_response(200, "ComplianceForgeSCF Update Status", $data);
    }
    else
    {
        // Return a 403 response
        return json_response(403, "Forbidden", null);
    }
}

/*******************************************
 * FUNCTION: API COMPLIANCEFORGESCF ENABLE *
 *******************************************/
function api_complianceforgescf_enable()
{
    // If the user calling this is an admin
    if (is_admin())
    {
        // If the ComplianceForge SCF Extra is disabled
        if (!complianceforge_scf_extra())
        {
            // Allow this to run as long as necessary
            ini_set('max_execution_time', 0);

            // Required file
            $required_file = realpath(__DIR__ . '/../extras/complianceforgescf/index.php');

            // If the file exists
            if (file_exists($required_file)) {
                // Include the required file
                require_once($required_file);

                // If a PHP session is not active (ie. called through the API)
                if (session_status() !== PHP_SESSION_ACTIVE)
                {
                    // Enable the ComplianceForge SCF Extra but don't run the asynchronous updates
                    // We need to do this because there is no session when this is called through the API
                    enable_complianceforge_scf_extra(false);
                }
                // Otherwise a PHP session is active (ie. called by a user)
                else
                {
                    // Enable the ComplianceForge SCF Extra but don't run the asynchronous updates
                    // We need to do this because there is no session when this is called through the API
                    enable_complianceforge_scf_extra(true);
                }
            }

            // Return a 200 response
            return json_response(200, "ComplianceForgeSCF Loading Complete", null);
        }
        else return json_response(200, "ComplianceForgeSCF Was Already Enabled", null);
    }
    else
    {
        // Return a 403 response
        return json_response(403, "Forbidden", null);
    }
}

/********************************************
 * FUNCTION: API COMPLIANCEFORGESCF DISABLE *
 ********************************************/
function api_complianceforgescf_disable()
{
    // If the user calling this is an admin
    if (is_admin())
    {
        // If the ComplianceForge SCF Extra is enabled
        if (complianceforge_scf_extra())
        {
            // Allow this to run as long as necessary
            ini_set('max_execution_time', 0);

            // Required file
            $required_file = realpath(__DIR__ . '/../extras/complianceforgescf/index.php');

            // If the file exists
            if (file_exists($required_file)) {
                // Include the required file
                require_once($required_file);

                // Enable the ComplianceForge SCF Extra
                disable_complianceforge_scf_extra();
            }

            // Return a 200 response
            return json_response(200, "ComplianceForgeSCF Disable Complete", null);
        }
        else return json_response(200, "ComplianceForgeSCF Was Already Disabled", null);
    }
    else
    {
        // Return a 403 response
        return json_response(403, "Forbidden", null);
    }
}

/**********************************************
 * FUNCTION: CREATE A ASSET FROM EXTERNAL APP *
 **********************************************/
/*************************************
 * FUNCTION: ASSET CRUD - GET BY ID  *
 *************************************/
function getAssetById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $asset = get_asset_by_id($id);
    if (empty($asset)) {
        json_response(404, "NOT FOUND: Unable to find an asset with the specified id.", NULL);
        return;
    }

    if (!check_access_for_asset($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    if (encryption_extra()) {
        $asset['ip']      = try_decrypt($asset['ip']);
        $asset['name']    = try_decrypt($asset['name']);
        $asset['details'] = try_decrypt($asset['details']);
    }

    json_response(200, "SUCCESS", ['asset' => $asset]);
}

/**************************************
 * FUNCTION: ASSET CRUD - CREATE      *
 **************************************/
function createAsset()
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $name = get_param("POST", "name", null);
    if (!$name) {
        json_response(400, $escaper->escapeHtml($lang['AssetNameIsRequired']), NULL);
        return;
    }

    $ip               = get_param("POST", "ip", "");
    $value            = get_param("POST", "value", "");
    $location         = $_POST['location'] ?? [];
    $teams            = $_POST['team'] ?? [];
    $details          = get_param("POST", "details", "");
    $tags             = $_POST['tags'] ?? [];
    $verified         = (bool)get_param("POST", "verified", 0);
    $associated_risks = $_POST['associated_risks'] ?? [];

    $control_maturity = $_POST['control_maturity'] ?? [];
    $control_id       = $_POST['control_id'] ?? [];
    $mapped_controls  = [];
    foreach ($control_maturity as $index => $maturity) {
        if (!empty($control_id[$index])) {
            $mapped_controls[] = [$maturity, $control_id[$index]];
        }
    }

    foreach ($tags as $tag) {
        if (strlen($tag) > 255) {
            json_response(400, $escaper->escapeHtml($lang['MaxTagLengthWarning']), NULL);
            return;
        }
    }

    $asset_id = add_asset($ip, $name, $value, $location, $teams, $details, $tags, $verified, $mapped_controls, $associated_risks);

    if ($asset_id) {
        json_response(200, $escaper->escapeHtml($lang['AssetWasAddedSuccessfully']), ['id' => $asset_id]);
    } else {
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemAddingTheAsset']), NULL);
    }
}

/**************************************
 * FUNCTION: ASSET CRUD - UPDATE      *
 **************************************/
function updateAssetById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    // PHP only auto-populates $_POST for POST requests; parse the body for PATCH.
    // Never gate this on empty($_POST) -- csrf-magic leaves the CSRF token in
    // $_POST on any session-authenticated call, which makes that guard false and
    // silently drops the entire body while still answering 200.
    parse_non_post_body_into_post();

    $id = (int)($id ?? get_param("POST", "id", 0));
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!asset_exists_by_id($id)) {
        json_response(404, "NOT FOUND: Unable to find an asset with the specified id.", NULL);
        return;
    }

    if (!check_access_for_asset($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $ip               = get_param("POST", "ip", null) ?: null;
    $name             = get_param("POST", "name", null) ?: null;
    $value            = get_param("POST", "value", null);
    $location         = isset($_POST['location']) ? $_POST['location'] : null;
    $teams            = isset($_POST['team']) ? $_POST['team'] : null;
    $details          = get_param("POST", "details", null) ?: null;
    $tags             = isset($_POST['tags']) ? $_POST['tags'] : null;
    $verified         = isset($_POST['verified']) ? (bool)$_POST['verified'] : null;
    // null, not [] -- every other field here already uses null to mean "the
    // caller did not name this, leave it alone", and update_asset() skips nulls.
    // [] does NOT mean the same thing: update_asset_risks_associations() deletes
    // every risks_to_assets row for the asset before it looks at the incoming
    // list, so the old [] default silently dropped an asset's entire risk
    // association set on any PATCH that did not re-send it.
    $associated_risks = isset($_POST['associated_risks']) ? $_POST['associated_risks'] : null;

    $control_maturity = $_POST['control_maturity'] ?? [];
    $control_id       = $_POST['control_id'] ?? [];
    $mapped_controls  = null;
    if (!empty($control_maturity)) {
        $mapped_controls = [];
        foreach ($control_maturity as $index => $maturity) {
            if (!empty($control_id[$index])) {
                $mapped_controls[] = [$maturity, $control_id[$index]];
            }
        }
    }

    if ($tags !== null) {
        foreach ($tags as $tag) {
            if (strlen($tag) > 255) {
                json_response(400, $escaper->escapeHtml($lang['MaxTagLengthWarning']), NULL);
                return;
            }
        }
    }

    $success = update_asset($id, $ip, $name, $value, $location, $teams, $details, $tags, $verified, $mapped_controls, $associated_risks);

    if ($success !== false) {
        json_response(200, $escaper->escapeHtml($lang['AssetWasUpdatedSuccessfully']), ['id' => $id]);
    } else {
        json_response(400, $escaper->escapeHtml($lang['ThereWasAProblemUpdatingTheAsset']), NULL);
    }
}

/**************************************
 * FUNCTION: ASSET CRUD - DELETE      *
 **************************************/
function deleteAssetById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!asset_exists_by_id($id)) {
        json_response(404, "NOT FOUND: Unable to find an asset with the specified id.", NULL);
        return;
    }

    if (!check_access_for_asset($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    delete_asset($id);
    json_response(200, $escaper->escapeHtml($lang['AssetWasDeletedSuccessfully']), NULL);
}

/***********************************************
 * FUNCTION: ASSET CRUD - GET ASSOCIATIONS     *
 ***********************************************/
function getAssetAssociations($id = null)
{
    global $escaper, $lang;

    if (!check_permission("asset")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!asset_exists_by_id($id)) {
        json_response(404, "NOT FOUND: Unable to find an asset with the specified id.", NULL);
        return;
    }

    if (!check_access_for_asset($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), NULL);
        return;
    }

    $risk_associations = get_risk_connectivity_for_asset($id);
    json_response(200, "SUCCESS", ['risks' => $risk_associations]);
}

/*********************************************
 * FUNCTION: COMPLIANCE CRUD - GET TEST      *
 *********************************************/
function getTestById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("compliance")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_test($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisTest']), NULL);
        return;
    }

    $test = get_framework_control_test_by_id($id);
    if (empty($test['id'])) {
        json_response(404, "NOT FOUND: Unable to find a test with the specified id.", NULL);
        return;
    }

    // Purify the rich-text fields at this output boundary, the same way
    // getTestResponse() does for the edit form. This endpoint feeds the
    // read-only View modal, which renders them as HTML to keep the author's
    // formatting -- so unpurified stored markup here would be a stored-XSS
    // sink. Everything else the modal shows goes out through .text().
    foreach (['objective', 'test_steps', 'expected_results', 'sample', 'required_evidence'] as $__rich) {
        $test[$__rich] = purify_rich_text_output($test[$__rich] ?? '');
    }

    // Resolve ids to display names for the read-only View modal. Done at this
    // boundary rather than inside get_framework_control_test_by_id(), whose
    // other callers (the edit form, the schedule engine) want the ids.
    // $escape=false: these go out as JSON and the client renders them with
    // .text(), so escaping here would show literal entities for a team called
    // "Risk & Compliance".
    $test['team_names'] = get_names_by_values('team', $test['teams'] ?? [], 0, false);
    $test['approver_names'] = get_names_by_values('user', $test['approvers'] ?? [], 0, false);
    $test['additional_stakeholder_names'] = get_names_by_values('user', $test['additional_stakeholders'] ?? [], 0, false);

    // The same plain-language cadence line the grid row shows, so the modal and
    // the row can't disagree about what the schedule is.
    $test['schedule_summary'] = format_test_schedule_summary($test);

    json_response(200, "Test retrieved successfully.", ['test' => $test]);
}

/***********************************************
 * FUNCTION: COMPLIANCE CRUD - CREATE TEST     *
 ***********************************************/
function createTest()
{
    global $escaper, $lang;

    if (!check_permission("define_tests")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $name                  = get_param("POST", "name", "");
    $framework_control_id  = (int)get_param("POST", "framework_control_id", 0);
    $tester                = (int)get_param("POST", "tester", 0);
    $test_frequency        = (int)get_param("POST", "test_frequency", 0);

    if (!$name) {
        json_response(400, "The test name cannot be empty.", NULL);
        return;
    }

    // The form marks Tester required and defaults it to the current user, so
    // this only ever fires for a caller that bypassed the form -- which is the
    // point: without it, a create with no tester stored 0, and update has
    // always refused the same value. See test_tester_valid()
    // (includes/compliance.php).
    if (!test_tester_valid($tester)) {
        json_response(400, $escaper->escapeHtml(_lang('FieldRequired', array("field" => $lang['Tester']))), NULL);
        return;
    }

    // Phase 4a (common tests): controls is a junction (test_control_map) -- a test
    // maps to N controls. Coerce to an array the same way approvers is below (a
    // scalar `controls=5` would otherwise flow into test_controls_valid()'s
    // sanitize_int_array() oddly rather than cleanly). A lone framework_control_id
    // with no controls[] submitted is accepted as a back-compat single-element set.
    $controls = is_array($_POST['controls'] ?? null) ? $_POST['controls'] : [];
    if (empty($controls) && $framework_control_id) {
        $controls = [$framework_control_id];
    }

    if (!test_controls_valid($controls)) {
        json_response(400, $escaper->escapeHtml($lang['AtLeastOneControlRequired']), NULL);
        return;
    }

    $objective                = get_param("POST", "objective", "");
    $test_steps               = get_param("POST", "test_steps", "");
    $approximate_time         = (int)get_param("POST", "approximate_time", 0);
    $expected_results         = get_param("POST", "expected_results", "");
    $additional_stakeholders  = get_param("POST", "additional_stakeholders", "");
    // last_date/next_date used to be bound VERBATIM into DATE columns, so the
    // app's own display format ('06/19/2026') stored '0000-00-00' while this
    // endpoint still answered 201 with a real id -- a test created with no
    // schedule at all, and nothing in the response saying so. Note this handler
    // already converted cadence_anchor_date (below), so it was inconsistent
    // about date parsing WITHIN ITSELF.
    //
    // parse_submitted_api_date() (includes/audit_schedule.php) accepts ISO *and*
    // the configured display format and reports garbage separately from blank.
    // A blank/omitted field keeps this handler's existing defaults: the zero
    // date for last_date (which add_framework_control_test() writes as "never
    // tested"), and false for next_date (which resolve_interval_next_date()
    // reads as "nothing submitted").
    $date_format              = get_default_date_format();

    $human_date_format        = get_setting('default_date_format');

    $raw_last_date = get_param("POST", "last_date", "");
    $last_date = parse_submitted_api_date($raw_last_date, $date_format);
    if ($last_date === null) {
        json_response(400, submitted_date_error_message($raw_last_date, $lang['LastTestDate'] . ' (last_date)', $date_format, $human_date_format), NULL);
        return;
    }
    if ($last_date === false) $last_date = "0000-00-00";

    $raw_next_date = get_param("POST", "next_date", "");
    $next_date = parse_submitted_api_date($raw_next_date, $date_format);
    if ($next_date === null) {
        json_response(400, submitted_date_error_message($raw_next_date, $lang['NextTestDate'] . ' (next_date)', $date_format, $human_date_format), NULL);
        return;
    }

    $teams                    = isset($_POST['teams']) ? $_POST['teams'] : [];
    $tags                     = isset($_POST['tags']) ? $_POST['tags'] : [];

    // Cadence schedule fields (Define Tests redesign, Issue 6) -- reuses the
    // same parse_test_schedule_fields()/validation shape updateTestResponse()
    // (POST /compliance/update_test, includes/api.php) applies, so create and
    // update never drift on what "a complete calendar schedule" or "today-or-
    // later anchor" mean. Unlike update, create has no persisted row to fall
    // back on: an *omitted* schedule_type stays null, preserving the existing
    // no-schedule-fields API-caller contract (ComplianceCrudTest /
    // ComplianceTestScheduleTest create tests without ever mentioning
    // schedule_type, and must keep working with no cadence validation at all).
    // An explicitly-submitted invalid value is rejected outright -- parity
    // with updateTestResponse()'s persisted-row case -- rather than silently
    // coerced to 'calendar' the way the legacy compliance/index.php add path
    // does (that path has no "omitted means leave it alone" caller to protect).
    $schedule_exceptions_raw = $_POST['schedule_exceptions'] ?? null;
    if (is_string($schedule_exceptions_raw)) {
        $decoded = json_decode($schedule_exceptions_raw, true);
        $schedule_exceptions_raw = is_array($decoded) ? $decoded : null;
    }

    // cadence_anchor_date arrives display-formatted (the Add Test modal's
    // datepicker), same convention as update_test/the legacy add path --
    // convert to canonical ISO before validating/persisting.
    //
    // Parsed with parse_submitted_api_date() like last_date/next_date above. The
    // anchor was the one date field 8bbb8d40c2 missed on this handler, so it kept
    // the display-format-only converter and its silent failures: an ISO anchor
    // became null, which parse_test_schedule_fields() reads as "not supplied", and
    // an impossible one ('02/31/2026') was rolled forward and anchored a whole
    // recurring schedule to a date nobody chose. false (blank or the zero date)
    // still means "not supplied".
    $cadence_anchor_date_iso = null;
    if (!empty($_POST['cadence_anchor_date'])) {
        $cadence_anchor_date_iso = parse_submitted_api_date($_POST['cadence_anchor_date'], $date_format);
        if ($cadence_anchor_date_iso === null) {
            json_response(400, submitted_date_error_message($_POST['cadence_anchor_date'], $lang['AnchorDate'] . ' (cadence_anchor_date)', $date_format, $human_date_format), NULL);
            return;
        }
        if ($cadence_anchor_date_iso === false) $cadence_anchor_date_iso = null;
    }

    $schedule_fields = parse_test_schedule_fields([
        'schedule_type'        => $_POST['schedule_type'] ?? null,
        'cadence_unit'         => $_POST['cadence_unit'] ?? null,
        'cadence_interval'     => $_POST['cadence_interval'] ?? null,
        'cadence_anchor_date'  => $cadence_anchor_date_iso,
        'schedule_exceptions'  => $schedule_exceptions_raw,
    ]);

    $schedule_type = $schedule_fields['schedule_type'];

    if ($test_frequency < 0) {
        json_response(400, $escaper->escapeHtml($lang['InvalidTestFrequency']), NULL);
        return;
    }

    $audit_initiation_offset_raw = isset($_POST['audit_initiation_offset']) ? trim((string)$_POST['audit_initiation_offset']) : '';

    // Shared with updateTestResponse() -- schedule_type allow-list, calendar
    // cadence completeness, past-anchor-date rejection, and audit lead-in
    // offset bounds (includes/compliance.php's validate_test_schedule_fields()).
    // Create has no persisted row to fall back on, so the "raw" and
    // "effective" schedule_type are the same value, and a calendar schedule's
    // anchor date is always freshly submitted (never falling back to a
    // persisted value the way update's can).
    $schedule_validation_error = validate_test_schedule_fields([
        'raw_schedule_type'             => $schedule_type,
        'effective_schedule_type'       => $schedule_type,
        'cadence_unit'                  => $schedule_fields['cadence_unit'],
        'cadence_interval'              => $schedule_fields['cadence_interval'],
        'cadence_anchor_date'           => $schedule_fields['cadence_anchor_date'],
        'cadence_anchor_date_submitted' => true,
        'test_frequency'                => $test_frequency,
        'audit_initiation_offset_raw'   => $audit_initiation_offset_raw,
    ]);

    if ($schedule_validation_error !== null) {
        json_response(400, $escaper->escapeHtml($schedule_validation_error), NULL);
        return;
    }

    // Manual schedules never auto-initiate, so the audit lead-in offset is forced
    // to null regardless of what was submitted -- matching updateTestResponse() and
    // the pre-refactor add_framework_control_test() path. validate_test_schedule_fields()
    // deliberately skips offset bounds-checking for Manual, so without this guard a
    // Manual-schedule create could persist an unchecked raw offset (e.g. negative),
    // which get_framework_control_test_by_id() would then read as auto-initiation configured.
    $audit_initiation_offset = ($schedule_type !== 'manual' && $audit_initiation_offset_raw !== '') ? (int)$audit_initiation_offset_raw : null;

    if ($approximate_time < 0) {
        json_response(400, $escaper->escapeHtml($lang['InvalidApproximateTime']), NULL);
        return;
    }

    // No persisted row yet to "leave untouched" -- an omitted schedule_exceptions
    // normalizes to "no exceptions" rather than parse_test_schedule_fields()'s
    // null ("leave existing untouched") contract, which only makes sense on update.
    $schedule_exceptions = $schedule_fields['schedule_exceptions'] ?? [];

    // Phase 3a test-definition fields (Define Tests redesign). test_method/sample/
    // required_evidence are plain scalars, threaded the same way objective/test_steps
    // are above. approvers is a junction (framework_control_test_approvers), threaded
    // the same way teams/tags are.
    $test_method        = get_param("POST", "test_method", "");
    $sample             = get_param("POST", "sample", "");
    $required_evidence  = get_param("POST", "required_evidence", "");
    // Coerce to an array (a scalar `approvers=5` would otherwise hit the array
    // type hint on test_tester_conflicts_with_approvers() and 500 instead of 400).
    $approvers          = is_array($_POST['approvers'] ?? null) ? $_POST['approvers'] : [];

    if (!is_valid_test_method($test_method)) {
        json_response(400, $escaper->escapeHtml($lang['InvalidTestMethod']), NULL);
        return;
    }

    // Segregation-of-duties guard: the tester cannot also be an approver of their
    // own test.
    if (test_tester_conflicts_with_approvers($tester, $approvers)) {
        json_response(400, $escaper->escapeHtml($lang['TesterCannotBeApprover']), NULL);
        return;
    }
    // Roster gate (server-side, not just the UI): every submitted approver must
    // currently hold the approve_tests responsibility.
    if (!approvers_all_hold_approve_tests($approvers)) {
        json_response(400, $escaper->escapeHtml($lang['ApproverNotEligible']), NULL);
        return;
    }

    $test_id = add_framework_control_test(
        $tester, $test_frequency, $name, $objective, $test_steps,
        $approximate_time, $expected_results, $framework_control_id,
        $additional_stakeholders, $last_date, $next_date, $teams, $tags,
        $audit_initiation_offset, $schedule_type,
        $schedule_fields['cadence_unit'], $schedule_fields['cadence_interval'],
        $schedule_fields['cadence_anchor_date'], $schedule_exceptions,
        $test_method, $sample, $required_evidence, $approvers, $controls
    );

    if ($test_id) {
        json_response(201, "Test created successfully.", ['id' => (int)$test_id]);
    } else {
        json_response(500, "Failed to create the test.", NULL);
    }
}

/***********************************************
 * FUNCTION: COMPLIANCE CRUD - UPDATE TEST     *
 ***********************************************/
function updateTestById($id = null)
{
    global $escaper, $lang;

    // PHP only auto-populates $_POST for POST requests, so a PATCH body has to be
    // parsed by hand. See parse_non_post_body_into_post() for why this must never
    // be gated on empty($_POST): csrf-magic leaves the CSRF token in $_POST on any
    // session-authenticated call, the guard is already false, the body is never
    // parsed, and every submitted field falls back to its persisted value while
    // the endpoint still answers 200. This logic used to be inlined here, which is
    // exactly why the fix never reached the sibling handlers -- keep it shared.
    parse_non_post_body_into_post();

    if (!check_permission("edit_tests")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_test($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisTest']), NULL);
        return;
    }

    $test = get_framework_control_test_by_id($id);
    if (empty($test['id'])) {
        json_response(404, "NOT FOUND: Unable to find a test with the specified id.", NULL);
        return;
    }

    // Use false for omitted fields so update_framework_control_test preserves existing values
    $tester                   = isset($_POST['tester'])                   ? (int)$_POST['tester']                  : false;
    $test_frequency           = isset($_POST['test_frequency'])           ? (int)$_POST['test_frequency']          : false;
    $name                     = isset($_POST['name'])                     ? $_POST['name']                         : false;
    $objective                = isset($_POST['objective'])                ? $_POST['objective']                    : false;
    $test_steps               = isset($_POST['test_steps'])               ? $_POST['test_steps']                   : false;
    $approximate_time         = isset($_POST['approximate_time'])         ? (int)$_POST['approximate_time']        : false;
    $expected_results         = isset($_POST['expected_results'])         ? $_POST['expected_results']             : false;
    $framework_control_id     = isset($_POST['framework_control_id'])     ? (int)$_POST['framework_control_id']   : false;
    $additional_stakeholders  = isset($_POST['additional_stakeholders'])  ? $_POST['additional_stakeholders']     : false;
    $teams                    = isset($_POST['teams'])                    ? $_POST['teams']                        : false;
    $tags                     = isset($_POST['tags'])                     ? $_POST['tags']                         : [];
    $audit_initiation_offset  = isset($_POST['audit_initiation_offset'])  ? (int)$_POST['audit_initiation_offset'] : false;

    // last_date/next_date used to be bound VERBATIM into DATE columns, so the
    // app's own display format -- '06/19/2026', what the datepickers show and
    // what getTestResponse() answers with -- stored '0000-00-00' while this
    // endpoint answered 200. parse_submitted_api_date()
    // (includes/audit_schedule.php) accepts ISO *and* the configured display
    // format, and separates "blank" from "not a date" so garbage can be refused
    // rather than silently flattened. The sibling POST /compliance/update_test
    // handler has converted all along; create and update finally agree.
    //
    // The `false` sentinel is preserved in both directions:
    //   omitted          -> false -> update_framework_control_test() keeps the
    //                       stored value (or, for next_date, recomputes it from
    //                       a calendar schedule).
    //   submitted blank  -> '0000-00-00', an explicit clear -- which is what
    //                       binding the empty string already did.
    $date_format = get_default_date_format();

    $human_date_format = get_setting('default_date_format');

    $last_date = false;
    if (isset($_POST['last_date'])) {
        $last_date = parse_submitted_api_date($_POST['last_date'], $date_format);
        if ($last_date === null) {
            json_response(400, submitted_date_error_message($_POST['last_date'], $lang['LastTestDate'] . ' (last_date)', $date_format, $human_date_format), NULL);
            return;
        }
        if ($last_date === false) $last_date = '0000-00-00';
    }

    $next_date = false;
    if (isset($_POST['next_date'])) {
        $next_date = parse_submitted_api_date($_POST['next_date'], $date_format);
        if ($next_date === null) {
            json_response(400, submitted_date_error_message($_POST['next_date'], $lang['NextTestDate'] . ' (next_date)', $date_format, $human_date_format), NULL);
            return;
        }
        if ($next_date === false) $next_date = '0000-00-00';
    }

    // Phase 3a test-definition fields (Define Tests redesign). test_method/sample/
    // required_evidence follow the same false-means-keep-existing idiom as the fields
    // above -- update_framework_control_test() already has a false-sentinel fallback
    // for each of them.
    $test_method_submitted    = isset($_POST['test_method']);
    $test_method              = $test_method_submitted            ? $_POST['test_method']                  : false;
    $sample                   = isset($_POST['sample'])            ? $_POST['sample']                       : false;
    $required_evidence        = isset($_POST['required_evidence']) ? $_POST['required_evidence']            : false;

    if ($test_method_submitted && !is_valid_test_method($test_method)) {
        json_response(400, $escaper->escapeHtml($lang['InvalidTestMethod']), NULL);
        return;
    }

    // approvers is a junction table (framework_control_test_approvers) that
    // update_framework_control_test() ALWAYS overwrites -- unlike the fields above,
    // it has no false-means-keep-existing sentinel. A partial PATCH that omits
    // `approvers` must not silently wipe the SoD approver roster, so fall back to
    // the test's currently-persisted approvers (already fetched above) when the
    // field wasn't submitted in this request. A submitted-but-scalar `approvers`
    // (not `approvers[]`) coerces to the persisted list rather than hitting the
    // array type hint on test_tester_conflicts_with_approvers() (clean, fails safe).
    $approvers = (isset($_POST['approvers']) && is_array($_POST['approvers'])) ? $_POST['approvers'] : $test['approvers'];

    // Segregation-of-duties guard: the tester cannot also be an approver of their
    // own test. Validated against the effective tester -- the just-submitted value
    // if this request is changing it, otherwise the currently-persisted tester.
    $effective_tester = ($tester !== false) ? $tester : $test['tester'];

    if (test_tester_conflicts_with_approvers($effective_tester, $approvers)) {
        json_response(400, $escaper->escapeHtml($lang['TesterCannotBeApprover']), NULL);
        return;
    }
    // Roster gate (server-side): only validate eligibility when approvers were
    // FRESHLY submitted in this PATCH -- a passthrough of already-persisted
    // approvers must not be re-checked (an approver whose role later lost
    // approve_tests would otherwise block an unrelated partial update).
    if (isset($_POST['approvers']) && !approvers_all_hold_approve_tests($approvers)) {
        json_response(400, $escaper->escapeHtml($lang['ApproverNotEligible']), NULL);
        return;
    }

    // Phase 4a (common tests): controls is a junction (test_control_map) that
    // update_framework_control_test() ALWAYS overwrites when non-empty -- like
    // approvers above, a partial PATCH that omits `controls` must not silently
    // wipe the persisted control set, so fall back to the test's currently-
    // persisted controls (already fetched above) when the field wasn't submitted.
    // A submitted-but-scalar `controls` (not `controls[]`) coerces to the
    // persisted list rather than hitting sanitize_int_array()'s expectations
    // (clean, fails safe).
    $controls = (isset($_POST['controls']) && is_array($_POST['controls'])) ? $_POST['controls'] : $test['controls'];

    // ≥1 control gate, only when controls was FRESHLY submitted this request --
    // mirrors the approvers roster gate's submitted-only re-validation above.
    if (isset($_POST['controls']) && !test_controls_valid($controls)) {
        json_response(400, $escaper->escapeHtml($lang['AtLeastOneControlRequired']), NULL);
        return;
    }

    update_framework_control_test(
        $id, $tester, $test_frequency, $name, $objective, $test_steps,
        $approximate_time, $expected_results, $last_date, $next_date,
        $framework_control_id, $additional_stakeholders, $teams, $tags,
        $audit_initiation_offset,
        // Schedule fields (schedule_type/cadence_unit/cadence_interval/cadence_anchor_date/
        // schedule_exceptions) are not yet wired to this CRUD PATCH endpoint -- null
        // preserves each field's existing persisted value, same as before this call
        // passed no arguments for these positions at all.
        null, null, null, null, null,
        $test_method, $sample, $required_evidence, $approvers, $controls
    );

    json_response(200, "Test updated successfully.", NULL);
}

/***********************************************
 * FUNCTION: COMPLIANCE CRUD - DELETE TEST     *
 ***********************************************/
function deleteTestById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("delete_tests")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_test($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisTest']), NULL);
        return;
    }

    $test = get_framework_control_test_by_id($id);
    if (empty($test['id'])) {
        json_response(404, "NOT FOUND: Unable to find a test with the specified id.", NULL);
        return;
    }

    delete_framework_control_test($id);
    json_response(200, "Test deleted successfully.", NULL);
}

/***********************************************
 * FUNCTION: COMPLIANCE - RETIRE TEST          *
 * Gated by can_retire_tests() (edit_tests OR  *
 * delete_tests) -- Phase 1 Task 8 of the      *
 * Define Tests redesign.                      *
 ***********************************************/
/***********************************************************************
 * FUNCTION: DETACH A TEST FROM ONE CONTROL                             *
 * Removes a single (test, control) pairing -- the row the Define Tests *
 * grid actually renders -- without touching the test itself. A test    *
 * shared across several controls stays on the others.                  *
 *                                                                      *
 * Modelled as DELETE on a collection member rather than an RPC verb:   *
 * the thing being removed is a membership, and the id pair names it    *
 * exactly, so a caller never has to send (or recompute) the whole      *
 * controls list. The handler holds up its end of that below by         *
 * deleting the single row under a lock, rather than replacing the      *
 * list -- see the comment there.                                       *
 *                                                                      *
 * Refuses to remove the LAST control: test_controls_valid() requires   *
 * at least one, and a test mapped to none would exist but be           *
 * unreachable from a grid that groups by control. Retire or delete is  *
 * the honest action there, so the refusal says so.                     *
 ***********************************************************************/
function detachTestFromControl($id = null, $control_id = null)
{
    global $escaper, $lang;

    if (!check_permission("edit_tests")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? 0);
    $control_id = (int)($control_id ?? 0);
    if (!$id || !$control_id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_test($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisTest']), NULL);
        return;
    }

    $test = get_framework_control_test_by_id($id);
    if (empty($test['id'])) {
        json_response(404, "NOT FOUND: Unable to find a test with the specified id.", NULL);
        return;
    }

    // Re-read the mapping under a row lock and delete just the one row, inside a
    // transaction. The obvious implementation -- diff the controls list and hand
    // the remainder to save_junction_values() -- looks like it removes one
    // mapping but actually DELETEs every row for the test and re-INSERTs the
    // survivors, i.e. a read-modify-write of the whole list. Two admins
    // detaching different controls at once would then each write a list computed
    // before the other landed, and one detach would silently come back.
    //
    // Locking the rows also makes the at-least-one-control rule airtight rather
    // than advisory: without it, two concurrent calls can each read two mappings,
    // each conclude one will remain, and both delete.
    $db = db_open();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT `framework_control_id` FROM `test_control_map` WHERE `test_id` = :test_id FOR UPDATE");
        $stmt->bindParam(":test_id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $controls = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));

        if (!in_array($control_id, $controls, true)) {
            $db->rollBack();
            db_close($db);
            json_response(404, "NOT FOUND: That test is not mapped to the specified control.", NULL);
            return;
        }

        if (!test_controls_valid(array_values(array_diff($controls, [$control_id])))) {
            $db->rollBack();
            db_close($db);
            json_response(409, $escaper->escapeHtml($lang['CannotRemoveTestsOnlyControl']), NULL);
            return;
        }

        $stmt = $db->prepare("DELETE FROM `test_control_map` WHERE `test_id` = :test_id AND `framework_control_id` = :control_id");
        $stmt->bindParam(":test_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
        $stmt->execute();

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        db_close($db);
        write_debug_log("detachTestFromControl failed for test {$id} / control {$control_id}: " . $e->getMessage(), "error");
        json_response(500, "INTERNAL SERVER ERROR: Unable to remove the test from the control.", NULL);
        return;
    }

    db_close($db);

    // _lang_raw, not _lang: audit messages are STORED raw and escaped once at
    // render (get_audit_trail_html() / get_audit_logs_api() both escape the whole
    // message). Escaping here too would double-encode a test name containing
    // & or a quote -- the exact mojibake the _lang/_lang_raw split exists to stop.
    $message = _lang_raw('TestRemovedFromControlAuditLogMessage', array(
        'test_name' => $test['name'],
        'test_id' => $id,
        'control_id' => $control_id,
        'user' => $_SESSION['user'],
    ));
    write_log((int)$id + 1000, $_SESSION['uid'] ?? 0, $message, "test");

    json_response(200, "Test removed from the control successfully.", NULL);
}

function retireTestById($id = null)
{
    global $escaper, $lang;

    if (!can_retire_tests()) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_test($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisTest']), NULL);
        return;
    }

    $test = get_framework_control_test_by_id($id);
    if (empty($test['id'])) {
        json_response(404, "NOT FOUND: Unable to find a test with the specified id.", NULL);
        return;
    }

    retire_framework_control_test($id);
    json_response(200, "Test retired successfully.", NULL);
}

/***********************************************
 * FUNCTION: COMPLIANCE - RESTORE TEST         *
 * Gated by can_retire_tests() (edit_tests OR  *
 * delete_tests) -- Phase 1 Task 8 of the      *
 * Define Tests redesign.                      *
 ***********************************************/
function restoreTestById($id = null)
{
    global $escaper, $lang;

    if (!can_retire_tests()) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_test($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisTest']), NULL);
        return;
    }

    $test = get_framework_control_test_by_id($id);
    if (empty($test['id'])) {
        json_response(404, "NOT FOUND: Unable to find a test with the specified id.", NULL);
        return;
    }

    restore_framework_control_test($id);
    json_response(200, "Test restored successfully.", NULL);
}

/***********************************************
 * FUNCTION: COMPLIANCE - TEST AUDIT HISTORY   *
 * Every audit ever run for one test, newest    *
 * first -- the Define Tests grid's History     *
 * row action.                                  *
 *                                              *
 * Read-only, so it needs no more privilege     *
 * than viewing the test itself: compliance     *
 * permission + the same per-test team access   *
 * check createAudit() uses. A test the caller  *
 * can't reach returns 403 BEFORE the           *
 * existence check, so the response can't be    *
 * used to probe which test ids exist.          *
 ***********************************************/
function getTestAuditHistoryById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("compliance")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_test($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisTest']), NULL);
        return;
    }

    $test = get_framework_control_test_by_id($id);
    if (empty($test['id'])) {
        json_response(404, "NOT FOUND: Unable to find a test with the specified id.", NULL);
        return;
    }

    json_response(200, "Test audit history retrieved successfully.", [
        'test_id' => $id,
        'test_name' => $test['name'] ?? '',
        'audits' => get_test_audit_history($id),
    ]);
}

/***********************************************
 * FUNCTION: COMPLIANCE CRUD - GET AUDIT      *
 ***********************************************/
function getAuditById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("compliance")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_audit($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisAudit']), NULL);
        return;
    }

    $audit = get_framework_control_test_audit_by_id($id);
    if (empty($audit['id'])) {
        json_response(404, "NOT FOUND: Unable to find an audit with the specified id.", NULL);
        return;
    }

    json_response(200, "Audit retrieved successfully.", ['audit' => $audit]);
}

/***********************************************
 * FUNCTION: COMPLIANCE CRUD - CREATE AUDIT   *
 ***********************************************/
function createAudit()
{
    global $escaper, $lang;

    if (!check_permission("initiate_audits")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $test_id = (int)get_param("POST", "test_id", 0);
    if (!$test_id) {
        json_response(400, "A test_id is required.", NULL);
        return;
    }

    if (!check_access_for_test($test_id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisTest']), NULL);
        return;
    }

    $test = get_framework_control_test_by_id($test_id);
    if (empty($test['id'])) {
        json_response(404, "NOT FOUND: Unable to find a test with the specified id.", NULL);
        return;
    }

    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];

    // Use the configured initiated_audit_status setting, cast to int to prevent injection
    $initiated_audit_status = (int)(get_setting("initiated_audit_status") ?: 0);

    // Phase 4b: initiate_test_audit() hands back the audit id it acted on via
    // $audit_id -- either a freshly-inserted row, or (window-dedup guard hit)
    // the id of the already-open audit for this test's current due-window.
    // Prefer that over a fresh "ORDER BY id DESC" re-select: a bare re-select
    // can't distinguish "I just created this" from "someone else's audit for
    // this test happens to have a higher id" and, on the no-op path, would
    // grab whatever audit is newest rather than the one the guard actually
    // matched (a phantom id). Only fall back to the re-select if the guard
    // somehow left $audit_id unset (defensive -- initiate_test_audit() always
    // sets it on both the insert and no-op paths).
    $audit_id = null;
    $name = initiate_test_audit($test_id, $initiated_audit_status, $tags, true, $audit_id);
    $audit_id = (int)$audit_id;

    if (!$audit_id) {
        $db = db_open();
        $stmt = $db->prepare("SELECT id FROM `framework_control_test_audits` WHERE test_id = :test_id ORDER BY id DESC LIMIT 1");
        $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
        $stmt->execute();
        $audit_id = (int)$stmt->fetchColumn();
        db_close($db);
    }

    if (!$audit_id) {
        json_response(500, "Unable to determine the initiated audit id.", NULL);
        return;
    }

    json_response(201, "Audit initiated successfully.", ['id' => $audit_id, 'test_name' => $name]);
}

/***********************************************
 * FUNCTION: COMPLIANCE CRUD - UPDATE AUDIT   *
 ***********************************************/
function updateAuditById($id = null)
{
    global $escaper, $lang;

    // PHP only auto-populates $_POST for POST requests; parse the body for PATCH.
    // Never gate this on empty($_POST) -- csrf-magic leaves the CSRF token in
    // $_POST on any session-authenticated call, which makes that guard false and
    // silently drops the entire body (test_result, status, ...) while still
    // answering 200 "Audit updated successfully".
    parse_non_post_body_into_post();

    if (!check_permission("modify_audits")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_audit($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisAudit']), NULL);
        return;
    }

    $audit = get_framework_control_test_audit_by_id($id);
    if (empty($audit['id'])) {
        json_response(404, "NOT FOUND: Unable to find an audit with the specified id.", NULL);
        return;
    }

    $status      = isset($_POST['status'])      ? (int)$_POST['status']      : (int)$audit['status'];
    $test_result = isset($_POST['test_result'])  ? (string)$_POST['test_result'] : ($audit['test_result'] ?? '');
    $tester      = isset($_POST['tester'])       ? (int)$_POST['tester']      : (int)$audit['tester'];
    $summary     = isset($_POST['summary'])      ? $_POST['summary']          : ($audit['summary'] ?? '');
    $teams       = isset($_POST['teams'])        ? $_POST['teams']            : ($audit['teams'] ?? []);
    $tags        = isset($_POST['tags'])         ? $_POST['tags']             : [];

    // test_date used to be bound VERBATIM into a DATE column, so the app's own
    // display format -- '06/19/2026', what the UI shows and therefore what a
    // caller naturally sends -- stored '0000-00-00' while this endpoint still
    // answered 200. Same silent-success shape as the dropped-PATCH-body bug one
    // field over. parse_submitted_api_date() (includes/audit_schedule.php)
    // accepts ISO *and* the display format and reports garbage separately from
    // blank, so an unparseable value is refused instead of flattened.
    //
    // Only a SUBMITTED value is parsed. The omitted-field fallback re-uses the
    // stored column value, which is already canonical -- including the
    // '0000-00-00' an initiated-but-never-performed audit carries, which must
    // keep flowing through unchanged rather than 400 every re-save.
    if (isset($_POST['test_date'])) {
        $test_date_format = get_default_date_format();
        $test_date = parse_submitted_api_date($_POST['test_date'], $test_date_format);
        if ($test_date === null) {
            json_response(400, submitted_date_error_message($_POST['test_date'], $lang['TestDate'] . ' (test_date)', $test_date_format, get_setting('default_date_format')), NULL);
            return;
        }
        // false === submitted blank: an explicit "no test date", stored as the
        // zero date the DATE NOT NULL column uses for exactly that.
        if ($test_date === false) {
            $test_date = '0000-00-00';
        }
    } else {
        $test_date = $audit['test_date'] ?? date('Y-m-d');
    }

    if (!is_valid_test_result_name($test_result)) {
        json_response(400, "Invalid test_result. Allowed values: Pass, Inconclusive, Fail.", NULL);
        return;
    }

    save_test_result($id, $status, $test_result, $tester, $test_date, $teams, $summary, $tags);
    json_response(200, "Audit updated successfully.", NULL);
}

/*******************************************************************
 * FUNCTION: COMPLIANCE - APPROVE AUDIT (Phase 3b Task 5)            *
 * POST /compliance/audits/{id}/approve                              *
 *                                                                     *
 * Full gate stack (a missing gate here is a security hole, so all    *
 * five run in order and each short-circuits on failure):             *
 *   1. check_permission('approve_tests')       -- 403                *
 *   2. id present                              -- 400                *
 *   3. check_access_for_audit($id)             -- 403 (team sep)     *
 *   4. audit exists                            -- 404                *
 *   5. user_is_approver_of_audit($id, uid)     -- 403                *
 *   6. uid !== audit's tester                  -- 403 (SoD)          *
 *   7. get_audit_approval_state($id)==='pending' -- 409               *
 * approve_audit() itself re-checks 'pending' under an atomic          *
 * UPDATE...WHERE approval_state='pending' guard, so a concurrent      *
 * double-approve can still only win once even though gate 7 above     *
 * is a plain read (TOCTOU-safe by construction in approve_audit()).   *
 *******************************************************************/
function approveAuditById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("approve_tests")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_audit($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisAudit']), NULL);
        return;
    }

    $audit = get_framework_control_test_audit_by_id($id);
    if (empty($audit['id'])) {
        json_response(404, "NOT FOUND: Unable to find an audit with the specified id.", NULL);
        return;
    }

    $uid = (int)($_SESSION['uid'] ?? 0);

    if (!user_is_approver_of_audit($id, $uid)) {
        json_response(403, $escaper->escapeHtml($lang['NotAnApproverOfThisAudit']), NULL);
        return;
    }

    if ($uid === (int)$audit['tester']) {
        json_response(403, $escaper->escapeHtml($lang['ApproverCannotBeTester']), NULL);
        return;
    }

    if (!audit_is_awaiting_approval($id)) {
        json_response(409, $escaper->escapeHtml($lang['AuditNotAwaitingApproval']), NULL);
        return;
    }

    if (approve_audit($id, $uid)) {
        json_response(200, $escaper->escapeHtml($lang['AuditApproved']), NULL);
    } else {
        // Lost a race against a concurrent approve/reject -- the audit is no
        // longer in 'pending' by the time approve_audit()'s atomic UPDATE ran.
        json_response(409, $escaper->escapeHtml($lang['AuditNotAwaitingApproval']), NULL);
    }
}

/*******************************************************************
 * FUNCTION: COMPLIANCE - REJECT AUDIT (Phase 3b Task 5)             *
 * POST /compliance/audits/{id}/reject                               *
 * Body: comment (required, non-empty)                                *
 *                                                                     *
 * Same gate stack as approveAuditById() (see above) plus a required   *
 * `comment` body field. On success, notifies the audit's tester       *
 * in-app (source='workflow') that the close was rejected, linking     *
 * back to the audit's testing page.                                   *
 *******************************************************************/
function rejectAuditById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("approve_tests")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_audit($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisAudit']), NULL);
        return;
    }

    $audit = get_framework_control_test_audit_by_id($id);
    if (empty($audit['id'])) {
        json_response(404, "NOT FOUND: Unable to find an audit with the specified id.", NULL);
        return;
    }

    $uid = (int)($_SESSION['uid'] ?? 0);

    if (!user_is_approver_of_audit($id, $uid)) {
        json_response(403, $escaper->escapeHtml($lang['NotAnApproverOfThisAudit']), NULL);
        return;
    }

    if ($uid === (int)$audit['tester']) {
        json_response(403, $escaper->escapeHtml($lang['ApproverCannotBeTester']), NULL);
        return;
    }

    if (!audit_is_awaiting_approval($id)) {
        json_response(409, $escaper->escapeHtml($lang['AuditNotAwaitingApproval']), NULL);
        return;
    }

    $comment = trim((string)get_param("POST", "comment", ""));
    if ($comment === '') {
        json_response(400, $escaper->escapeHtml($lang['RejectCommentRequired']), NULL);
        return;
    }

    if (!reject_audit($id, $uid, $comment)) {
        // Lost a race against a concurrent approve/reject.
        json_response(409, $escaper->escapeHtml($lang['AuditNotAwaitingApproval']), NULL);
        return;
    }

    // Notify the tester their submitted close was rejected. source='workflow'
    // is the only NOTIFICATION_SOURCES value available to Core runtime code;
    // link points back at the audit's testing page (build_url() base-URL-safe
    // for subpath installs, same idiom used throughout compliance.php/api.php).
    $tester_id = (int)$audit['tester'];
    if ($tester_id > 0) {
        create_notification_for_user_ids(
            source:     'workflow',
            title:      _lang_raw('NotificationAuditRejectedTitle', ['test_audit_name' => $audit['name']]),
            body:       _lang_raw('NotificationAuditRejectedBody', ['test_audit_name' => $audit['name'], 'comment' => $comment]),
            link:       build_url("compliance/testing.php?id=" . $id),
            user_ids:   [$tester_id],
            created_by: $uid,
            expires_at: null
        );
    }

    json_response(200, $escaper->escapeHtml($lang['AuditRejected']), NULL);
}

/***********************************************
 * FUNCTION: COMPLIANCE CRUD - DELETE AUDIT   *
 ***********************************************/
function deleteAuditById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("delete_audits")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForCompliance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    if (!check_access_for_audit($id)) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForThisAudit']), NULL);
        return;
    }

    $audit = get_framework_control_test_audit_by_id($id);
    if (empty($audit['id'])) {
        json_response(404, "NOT FOUND: Unable to find an audit with the specified id.", NULL);
        return;
    }

    delete_test_audit($id);
    json_response(200, "Audit deleted successfully.", NULL);
}

/*************************************************
 * FUNCTION: GOVERNANCE CRUD - GET FRAMEWORK     *
 *************************************************/
function getFrameworkById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("governance")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $framework = get_framework($id);
    if (!$framework) {
        json_response(404, "NOT FOUND: Unable to find a framework with the specified id.", NULL);
        return;
    }

    json_response(200, "Framework retrieved successfully.", ['framework' => $framework]);
}

/***************************************************
 * FUNCTION: GOVERNANCE CRUD - CREATE FRAMEWORK    *
 ***************************************************/
function createFrameworkCrud()
{
    global $escaper, $lang;

    if (!check_permission("add_new_frameworks")) {
        json_response(403, $escaper->escapeHtml($lang['NoAddFrameworkPermission']), NULL);
        return;
    }

    $name        = get_param("POST", "name", "");
    $description = get_param("POST", "description", "");
    $parent      = (int)get_param("POST", "parent", 0);

    // Active (1) / Inactive (2), defaulting to Active. Whitelisted for the same
    // reason updateFrameworkById() whitelists it: `frameworks`.`status` only ever
    // means those two things, and add_framework() would happily INSERT any other
    // integer -- producing a row that no status filter in the product can reach.
    $status      = (int)get_param("POST", "status", 1);
    if ($status !== 1 && $status !== 2) {
        json_response(400, "The framework status must be 1 (active) or 2 (inactive).", NULL);
        return;
    }

    // The two Statement of Applicability fields (spec §5.4a). Absent means "not
    // supplied", not "empty": both are optional at creation time, and the SoA
    // export prompts for whichever is still missing when it is run.
    $scope_statement = isset($_POST['scope_statement']) ? (string)$_POST['scope_statement'] : false;
    $inclusion       = isset($_POST['default_inclusion_justification'])
        ? (string)$_POST['default_inclusion_justification']
        : false;

    // CLONE (Task 64). An optional source framework whose control mappings the
    // new framework is to be given. Everything else about a clone -- the name,
    // the description, the parent, the status, the default inclusion
    // justification -- arrives as ordinary create params, because a clone IS a
    // create: the Define Control Frameworks page pre-fills the Add Framework
    // modal from the source and the user reviews and submits it through this
    // endpoint, exactly the way Clone control pre-fills the Add Control modal
    // (Task 24). The only thing the client cannot carry in the form is the
    // mapping set, so that -- and only that -- is what this parameter names.
    //
    // The permission checked is the one already checked above: cloning creates a
    // framework, so `add_new_frameworks` is the grant. Reading the source's
    // mappings needs nothing further -- `governance`, which every caller of this
    // endpoint already holds, is what the controls table itself is gated on.
    $clone_from = (int)get_param("POST", "clone_from", 0);
    if ($clone_from < 0) {
        $clone_from = 0;
    }
    if ($clone_from && !get_framework($clone_from)) {
        // Refused BEFORE the framework is created: answering 404 after the
        // insert would leave a framework behind that the caller was told did
        // not get made.
        json_response(404, "NOT FOUND: Unable to find a framework with the specified clone_from id.", NULL);
        return;
    }

    if (!$name) {
        json_response(400, "The framework name cannot be empty.", NULL);
        return;
    }

    $framework_id = add_framework($name, $description, $parent, $status);
    if ($framework_id === false) {
        json_response(409, "A framework with that name already exists.", NULL);
        return;
    }

    // Written after the insert rather than through add_framework(): that function
    // has nine call sites across Core and three Extras (the SCF importer among
    // them), none of which have an SoA to state, so its signature stays as it is.
    if ($scope_statement !== false || $inclusion !== false) {
        try {
            update_framework_soa_fields((int)$framework_id, $scope_statement, $inclusion);
        } catch (InvalidArgumentException $e) {
            // The framework itself was created; only the over-long SoA field was
            // refused. Say so explicitly rather than reporting a bare 201, so the
            // caller does not assume a value landed that did not.
            json_response(400, $escaper->escapeHtml($e->getMessage()), ['id' => (int)$framework_id]);
            return;
        }
    }

    // The mapping copy is one transactional INSERT ... SELECT, so it either
    // copies the whole set or copies nothing -- there is no half-cloned state to
    // report. What there IS is the case where the framework was created and the
    // copy then failed, and that has to be said out loud with the id attached:
    // the alternative is a caller told "created" who finds an empty framework,
    // or told "failed" who finds a real one. Deleting the framework to undo it
    // would be worse -- delete_frameworks() detaches documents, exceptions and
    // audits, which is a great deal of collateral for a failure whose cause is
    // unknown at this point.
    $mappings_copied = 0;
    if ($clone_from) {
        try {
            $mappings_copied = clone_framework_control_mappings($clone_from, (int)$framework_id);
        } catch (Throwable $e) {
            write_debug_log(
                "Cloning framework {$clone_from} into {$framework_id}: the mapping copy failed -- " . $e->getMessage(),
                'error'
            );
            json_response(
                500,
                "The framework was created, but its control mappings could not be copied.",
                ['id' => (int)$framework_id, 'mappings_copied' => 0]
            );
            return;
        }
    }

    json_response(201, "Framework created successfully.", [
        'id' => (int)$framework_id,
        'mappings_copied' => $mappings_copied,
    ]);
}

/***************************************************
 * FUNCTION: GOVERNANCE CRUD - UPDATE FRAMEWORK    *
 ***************************************************/
/*****************************************************************
 * FUNCTION: PARSE A NON-POST BODY (PATCH/DELETE) INTO $_POST    *
 *****************************************************************
 * PHP only auto-populates $_POST for POST requests, so a PATCH body has to
 * be parsed by hand.
 *
 * This must NOT be gated on empty($_POST) -- that was updateTestById()'s
 * original bug (see reference_patch_body_parse_bug in project memory,
 * fixed 2026-07-19): csrf_startup() (includes/functions.php) unconditionally
 * copies the CSRF-TOKEN header into $_POST[$name] regardless of HTTP verb,
 * so by the time a PATCH handler runs, $_POST already has one key -- an
 * empty($_POST) guard reads false, the body is never parsed, and EVERY
 * submitted field then falls back to its persisted value (or, for the two
 * callers below, the update is refused outright with "No updatable fields
 * were provided" even though real fields were sent) -- discovered here
 * while wiring Task 8's Edit framework/Edit control modals, which always
 * send that header (design-system.md §8's CSRF rule).
 *
 * Parses whenever the verb isn't POST and MERGES the result into $_POST
 * (array_merge($_POST, $parsed_body)), so submitted values win for the keys
 * they define. This does NOT protect the existing CSRF token key from being
 * overwritten if the body happens to define the same key -- the merge lets
 * the body win there too. That has no security effect today: csrf_check()
 * (vendor/simplerisk/csrf-magic) only validates a token for POST requests
 * and returns true unconditionally for PATCH/DELETE, and it runs before
 * this helper ever does, so nothing downstream re-checks whatever ends up
 * in $_POST[$name] for these verbs. If a future PATCH/DELETE endpoint ever
 * needs the CSRF key to survive a same-named body field, protect it
 * explicitly at that call site rather than assuming this helper does it.
 * Supports both encodings for the same reason updateTestById() does:
 * answering success to a body we silently misunderstood is the same bug
 * reached a different way.
 */
function parse_non_post_body_into_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        return;
    }
    $raw_body = file_get_contents('php://input');
    if (!is_string($raw_body) || $raw_body === '') {
        return;
    }
    $parsed_body = null;
    if (str_starts_with(ltrim($raw_body), '{')) {
        $decoded = json_decode($raw_body, true);
        if (is_array($decoded)) {
            $parsed_body = $decoded;
        }
    }
    if ($parsed_body === null) {
        parse_str($raw_body, $parsed_body);
    }
    if (is_array($parsed_body)) {
        $_POST = array_merge($_POST, $parsed_body);
    }
}

function updateFrameworkById($id = null)
{
    global $escaper, $lang;

    parse_non_post_body_into_post();

    if (!check_permission("modify_frameworks")) {
        json_response(403, $escaper->escapeHtml($lang['NoModifyFrameworkPermission']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $framework = get_framework($id);
    if (!$framework) {
        json_response(404, "NOT FOUND: Unable to find a framework with the specified id.", NULL);
        return;
    }

    $name        = isset($_POST['name'])        ? $_POST['name']               : false;
    $description = isset($_POST['description']) ? $_POST['description']        : false;
    $parent      = isset($_POST['parent'])      ? (int)$_POST['parent']        : false;

    // Statement of Applicability fields (spec §5.4a). An absent key leaves the
    // stored value alone; an empty string is a deliberate clear. That distinction
    // is what lets a caller PATCH only the name without blanking a scope
    // statement it never sent.
    $scope_statement = isset($_POST['scope_statement']) ? (string)$_POST['scope_statement'] : false;
    $inclusion       = isset($_POST['default_inclusion_justification'])
        ? (string)$_POST['default_inclusion_justification']
        : false;

    // Active (1) / Inactive (2) -- the SAME two values `frameworks`.`status` has
    // always held, and the same two POST /governance/frameworks already accepts.
    // Deactivating is how a framework is retired WITHOUT deleting it, and deleting
    // is irreversible (nothing ever sets `framework_controls`.`deleted` back to 0),
    // so leaving this off PATCH left the destructive route as the only one the
    // Define Control Frameworks page could reach.
    //
    // Whitelisted to exactly {1, 2} rather than cast-and-store: update_framework_status()
    // branches on those two values and silently does NOTHING for any other integer
    // while still writing an audit-log line, so a stray 0 or 3 would be reported as a
    // successful update that changed nothing.
    $status = false;
    if (isset($_POST['status'])) {
        $status = (int)$_POST['status'];
        if ($status !== 1 && $status !== 2) {
            json_response(400, "The framework status must be 1 (active) or 2 (inactive).", NULL);
            return;
        }
    }

    if ($name === false && $description === false && $parent === false
        && $scope_statement === false && $inclusion === false && $status === false) {
        json_response(400, "No updatable fields were provided.", NULL);
        return;
    }

    // Validate the SoA fields BEFORE update_framework() runs, so an over-long
    // scope statement cannot leave the name half-updated behind a 400.
    if ($scope_statement !== false || $inclusion !== false) {
        try {
            update_framework_soa_fields($id, $scope_statement, $inclusion);
        } catch (InvalidArgumentException $e) {
            json_response(400, $escaper->escapeHtml($e->getMessage()), NULL);
            return;
        }
    }

    // The name/description/parent update is skipped entirely when the request only
    // carried SoA fields: update_framework() requires a name, and passing the
    // stored one back through it would re-encrypt and rewrite the row for nothing.
    if ($name !== false || $description !== false || $parent !== false) {
        if (!update_framework($id, $name !== false ? $name : $framework['name'], $description, $parent)) {
            json_response(500, "Failed to update the framework.", NULL);
            return;
        }
    }

    // Applied LAST, and through update_framework_status() rather than a plain
    // UPDATE, so this behaves exactly like the drag-between-tabs gesture the
    // redesign removed: deactivating cascades to the whole subtree, activating
    // walks the ancestor chain back on. Running it after update_framework() means
    // a request that changes parent AND status re-parents first, so the ancestor
    // walk follows the parent the caller just asked for, not the one it replaced.
    //
    // Only when the value actually changes: update_framework_status() writes an
    // "activated by"/"deactivated by" audit-trail entry unconditionally, and an
    // unrelated rename must not manufacture a status-change record.
    if ($status !== false && (int)$framework['status'] !== $status) {
        update_framework_status($status, $id);
    }

    json_response(200, "Framework updated successfully.", NULL);
}

/***************************************************
 * FUNCTION: GOVERNANCE CRUD - DELETE FRAMEWORK    *
 ***************************************************/
function deleteFrameworkById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("delete_frameworks")) {
        json_response(403, $escaper->escapeHtml($lang['NoDeleteFrameworkPermission']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $framework = get_framework($id);
    if (!$framework) {
        json_response(404, "NOT FOUND: Unable to find a framework with the specified id.", NULL);
        return;
    }

    delete_frameworks($id);
    json_response(200, "Framework deleted successfully.", NULL);
}

/***********************************************
 * FUNCTION: GOVERNANCE CRUD - GET CONTROL     *
 ***********************************************/
function getControlById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("governance")) {
        json_response(403, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $control = get_framework_control($id);
    if (!$control) {
        json_response(404, "NOT FOUND: Unable to find a control with the specified id.", NULL);
        return;
    }

    json_response(200, "Control retrieved successfully.", ['control' => $control]);
}

/***********************************************
 * FUNCTION: GOVERNANCE CRUD - CREATE CONTROL  *
 ***********************************************/
function createControlCrud()
{
    global $escaper, $lang;

    if (!check_permission("add_new_controls")) {
        json_response(403, $escaper->escapeHtml($lang['NoAddControlPermission']), NULL);
        return;
    }

    $short_name = get_param("POST", "short_name", "");
    if (!$short_name) {
        json_response(400, $escaper->escapeHtml($lang['TheControlNameCannotBeEmpty']), NULL);
        return;
    }

    $control = [
        'short_name'               => trim($short_name),
        'long_name'                => get_param("POST", "long_name", ""),
        'description'              => get_param("POST", "description", ""),
        'supplemental_guidance'    => get_param("POST", "supplemental_guidance", ""),
        'control_owner'            => (int)get_param("POST", "control_owner", 0),
        'control_class'            => (int)get_param("POST", "control_class", 0),
        'control_phase'            => (int)get_param("POST", "control_phase", 0),
        'control_number'           => get_param("POST", "control_number", ""),
        'control_current_maturity' => (int)get_param("POST", "control_current_maturity", 0),
        'control_desired_maturity' => (int)get_param("POST", "control_desired_maturity", 0),
        'control_priority'         => (int)get_param("POST", "control_priority", 0),
        'control_type'             => isset($_POST['control_type']) ? $_POST['control_type'] : [],
        'control_status'           => (int)get_param("POST", "control_status", 2),
        'family'                   => (int)get_param("POST", "family", 0),
        'mitigation_percent'       => (int)get_param("POST", "mitigation_percent", 0),
        // Previously hardcoded to [] regardless of what the Add Control
        // modal's mapping tables submitted (Task 20's known gap) -- now
        // parsed from the request via the same pure helpers
        // CreateControlMappingsTest exercises directly. Safe to persist
        // unconditionally here (unlike updateControlById() below in this
        // file, which deliberately OMITS these keys instead of parsing them
        // -- add_framework_control() only calls save_control_to_assets()
        // when 'mapped_assets' is non-empty, so there is no prior mapping
        // state a create could ever wipe; an update, on the other hand,
        // would delete-then-reinsert against a control that already has
        // rows, so parsing an update's mapping submission is a materially
        // different, NOT-yet-done change deliberately left alone here).
        'map_frameworks'           => parse_control_map_frameworks_request($_POST),
        'mapped_assets'            => parse_control_mapped_assets_request($_POST),
    ];

    $control_id = add_framework_control($control);
    if ($control_id) {
        json_response(201, "Control created successfully.", ['id' => (int)$control_id]);
    } else {
        json_response(500, "Failed to create the control.", NULL);
    }
}

/***********************************************
 * FUNCTION: GOVERNANCE CRUD - UPDATE CONTROL  *
 ***********************************************/
function updateControlById($id = null)
{
    global $escaper, $lang;

    // See parse_non_post_body_into_post()'s docblock above (updateFrameworkById) --
    // same empty($_POST)-guard bug, same fix.
    parse_non_post_body_into_post();

    if (!check_permission("modify_controls")) {
        json_response(403, $escaper->escapeHtml($lang['NoModifyControlPermission']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $existing = get_framework_control($id);
    if (!$existing) {
        json_response(404, "NOT FOUND: Unable to find a control with the specified id.", NULL);
        return;
    }

    $control = [
        'short_name'               => isset($_POST['short_name'])               ? trim($_POST['short_name'])               : $existing['short_name'],
        'long_name'                => isset($_POST['long_name'])                ? $_POST['long_name']                      : $existing['long_name'],
        'description'              => isset($_POST['description'])              ? $_POST['description']                    : $existing['description'],
        'supplemental_guidance'    => isset($_POST['supplemental_guidance'])    ? $_POST['supplemental_guidance']          : $existing['supplemental_guidance'],
        'control_owner'            => isset($_POST['control_owner'])            ? (int)$_POST['control_owner']             : (int)$existing['control_owner'],
        'control_class'            => isset($_POST['control_class'])            ? (int)$_POST['control_class']             : (int)$existing['control_class'],
        'control_phase'            => isset($_POST['control_phase'])            ? (int)$_POST['control_phase']             : (int)$existing['control_phase'],
        'control_number'           => isset($_POST['control_number'])           ? $_POST['control_number']                 : $existing['control_number'],
        'control_current_maturity' => isset($_POST['control_current_maturity']) ? (int)$_POST['control_current_maturity'] : (int)($existing['control_current_maturity'] ?? 0),
        'control_desired_maturity' => isset($_POST['control_desired_maturity']) ? (int)$_POST['control_desired_maturity'] : (int)($existing['control_desired_maturity'] ?? 0),
        'control_priority'         => isset($_POST['control_priority'])         ? (int)$_POST['control_priority']          : (int)$existing['control_priority'],
        'control_status'           => isset($_POST['control_status'])           ? (int)$_POST['control_status']            : (int)$existing['control_status'],
        'family'                   => isset($_POST['family'])                   ? (int)$_POST['family']                    : (int)$existing['family'],
        'mitigation_percent'       => isset($_POST['mitigation_percent'])       ? (int)$_POST['mitigation_percent']        : (int)$existing['mitigation_percent'],
        // 'control_type', 'map_frameworks' and 'mapped_assets' are set BELOW,
        // and only when the request actually carried a submission for them.
        // They must not appear in this literal at all -- see the block after it.
    ];

    // ---- Mapping changes: omission means PRESERVE, an explicit marker means
    // ---- REPLACE (including with nothing).
    //
    // These two keys used to be omitted unconditionally, for a real reason:
    // update_framework_control() (includes/governance.php) gates on
    // isset($control['mapped_assets']) before calling save_control_to_assets(),
    // isset([]) is TRUE in PHP, and that function is delete-then-insert. A
    // hardcoded [] therefore did not skip the call -- it WIPED every asset and
    // asset-group mapping the control had, on every PATCH, including one that
    // only touched short_name. tests/api/GovernanceControlAssetMappingTest.php
    // is the guard for that and still passes: a request that says nothing about
    // mappings still says nothing about them here.
    //
    // But omission-means-preserve is only safe while no caller can legitimately
    // clear a mapping, and that stopped being true when the Edit Control modal
    // started collecting mapping changes. A user could open a control, change
    // its Mapped Control Frameworks, save, and have the change silently
    // discarded -- the same defect as the create path's hardcoded [], one verb
    // over. So "none" now needs a representation that is distinguishable from
    // "not mentioned", and a form cannot provide one on its own: an empty table
    // and an absent widget serialize identically.
    //
    // The marker inputs are what distinguish them. display_mapping_framework_edit()
    // / display_mapping_asset_edit() emit them next to the tables they describe,
    // and only when the field is actually rendered -- so a Customization-Extra
    // layout that drops the widget, or any API client that simply does not send
    // the marker, keeps preserve-by-omission untouched.
    // Control types are the THIRD mapping table on this path and were missed
    // when the other two were fixed, with a worse failure mode: their rewrite in
    // update_framework_control() is inline rather than behind an isset()-gated
    // save_* call, so the DELETE ran unconditionally and the empty default here
    // meant every PATCH that did not mention control_type wiped the control's
    // types outright. Both halves are fixed -- the domain function now preserves
    // when the key is absent, and the key is only set here when the request
    // actually carried types or the marker.
    //
    // isset($_POST['control_type']) alone is enough for the ADD direction (a
    // multiselect with a selection submits it), but not for the CLEAR direction:
    // a multiselect with nothing selected submits nothing at all, which is
    // byte-identical to an API client that never mentioned control types. The
    // control_type_submitted marker display_control_type_edit() emits is what
    // separates them, exactly as the two mapping-table markers do.
    $frameworks_submitted  = !empty($_POST['map_frameworks_submitted']);
    $assets_submitted      = !empty($_POST['mapped_assets_submitted']);
    $control_type_submitted = isset($_POST['control_type']) || !empty($_POST['control_type_submitted']);

    if ($control_type_submitted) {
        $control['control_type'] = isset($_POST['control_type']) ? (array)$_POST['control_type'] : [];
    }

    // PARSED ONCE, INTO A LOCAL. The prune below needs this same set, and reading
    // it back off $control there left the two uses correlated only through
    // $frameworks_submitted -- which Phan cannot follow across two separate `if`
    // blocks, so it saw a possibly-absent 'map_frameworks' offset. A local also
    // rules out the far worse repair of writing `$control['map_frameworks'] ?? []`
    // at the prune: an empty set there means "the user cleared every mapping" and
    // would delete them all.
    $submitted_frameworks = $frameworks_submitted ? parse_control_map_frameworks_request($_POST) : null;

    if ($submitted_frameworks !== null) {
        $control['map_frameworks'] = $submitted_frameworks;
    }
    if ($assets_submitted) {
        $control['mapped_assets'] = parse_control_mapped_assets_request($_POST);
    }

    if (!update_framework_control($id, $control)) {
        json_response(500, "Failed to update the control.", NULL);
        return;
    }

    // save_control_to_frameworks() is INSERT ... ON DUPLICATE KEY UPDATE with
    // no DELETE, so on its own it can only ever ADD a framework mapping -- a
    // row the user deleted in the modal would come back. The removal half is
    // done here rather than inside update_framework_control() on purpose: the
    // other caller of that function (updateControlResponse(), the legacy
    // add/update_control endpoints) sets map_frameworks unconditionally, even
    // to [], so making the prune unconditional there would recreate the exact
    // isset([]) wipe the guard above exists to prevent -- on the framework side
    // instead of the asset side. Only a request that carried the marker has
    // proven its submission is the complete set.
    //
    // Assets need no equivalent: save_control_to_assets() is already
    // delete-then-insert, which is why passing it an empty set was dangerous in
    // the first place and is exactly right now that the empty set is explicit.
    if ($submitted_frameworks !== null) {
        delete_control_to_frameworks_except($id, $submitted_frameworks);
    }

    json_response(200, "Control updated successfully.", NULL);
}

/***********************************************
 * FUNCTION: GOVERNANCE CRUD - DELETE CONTROL  *
 ***********************************************/
function deleteControlById($id = null)
{
    global $escaper, $lang;

    if (!check_permission("delete_controls")) {
        json_response(403, $escaper->escapeHtml($lang['NoDeleteControlPermission']), NULL);
        return;
    }

    $id = (int)($id ?? $_GET['id'] ?? 0);
    if (!$id) {
        json_response(400, $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']), NULL);
        return;
    }

    $existing = get_framework_control($id);
    if (!$existing) {
        json_response(404, "NOT FOUND: Unable to find a control with the specified id.", NULL);
        return;
    }

    delete_framework_control($id);
    json_response(200, "Control deleted successfully.", NULL);
}

function create_asset_api(){
    global $escaper, $lang;
    if (check_permission("asset")){
        $name               = isset($_POST['asset_name']) ? $_POST['asset_name'] : NULL;
        $ip                 = isset($_POST['ip']) ? $_POST['ip'] : "";
        $value              = isset($_POST['value']) ? $_POST['value'] : "";
        $location           = empty($_POST['location']) ? [] : $_POST['location'];
        $teams              = empty($_POST['team']) ? [] : $_POST['team'];
        $details            = isset($_POST['details']) ? $_POST['details'] : "";
        $tags               = empty($_POST['tags']) ? [] : $_POST['tags'];
        $verified           = $_POST['verified'] ? boolval($_POST['verified']) : 0;
        $control_maturity   = empty($_POST['control_maturity']) ? [] : $_POST['control_maturity'];
        $control_id         = empty($_POST['control_id']) ? [] : $_POST['control_id'];
        $associated_risks   = empty($_POST['associated_risks']) ? [] : $_POST['associated_risks'];

        $mapped_controls = array();
        foreach($control_maturity as $index=>$maturity){
            if($control_id[$index]) $mapped_controls[] = array($maturity, $control_id[$index]);
        }

        foreach($tags as $tag){
            if (strlen($tag) > 255) {
                $message = $escaper->escapeHtml($lang['MaxTagLengthWarning']);
                return json_response(400, $message, NULL);
            }
        }

        if(!is_null($name) && $name != "")
        {
            // Add the asset
            $success = add_asset($ip, $name, $value, $location, $teams, $details, $tags, $verified, $mapped_controls, $associated_risks);

            // If the asset add was successful
            if ($success)
            {
                // Display an alert
                $message = $escaper->escapeHtml($lang['AssetWasAddedSuccessfully']);
                return json_response(200, $message, NULL);
            }
            else
            {
                $message = $escaper->escapeHtml($lang['ThereWasAProblemAddingTheAsset']);
                return json_response(400, $message, NULL);
            }
        }
        else
        {
            // Display an alert
            $message = $escaper->escapeHtml($lang['AssetNameIsRequired']);
            return json_response(400, $message, NULL);
        }
    } else {
        $message = $escaper->escapeHtml($lang['NoPermissionForAsset']);
        return json_response(400, $message, NULL);
    }
}
/**********************************************
 * FUNCTION: DELETE A ASSET FROM EXTERNAL APP *
 **********************************************/
function delete_asset_api(){
    global $escaper, $lang;
    $message = null;
    if (check_permission("asset")){
        $id = isset($_POST['id']) ? (int)$_POST['id'] : NULL;
        if(!is_null($id) && $id != "") {
            delete_asset($id);
            $message = $escaper->escapeHtml($lang['AssetWasDeletedSuccessfully']);
            return json_response(200, $message, NULL);
        } else {
            $message = $escaper->escapeHtml($lang['ThereWasAProblemDeletingTheAsset']);
            return json_response(400, $message, NULL);
        }
        return json_response(200, "deleteaseet", $message);
    } else {
        $message = $escaper->escapeHtml($lang['NoPermissionForAsset']);
        return json_response(400, $message, NULL);
    }
}

/*******************************
 * FUNCTION: GET DATATABLE API *
 *******************************/
/*******************************************************************************
 * FUNCTION: DATATABLE RESPONSE FOR VIEW                                         *
 * Shared server-side DataTables JSON response for a `$field_settings_views`      *
 * view. The CALLER is responsible for enforcing the view's module permission     *
 * BEFORE invoking this. Each resource route that reaches it — /compliance/       *
 * audits/*, /governance/documents, /governance/exceptions — gates on its module  *
 * permission first, so there is no longer a generic ungated datatable entry      *
 * point (SR-1721). A default-deny guard rejects any unknown/absent view.         *
 *******************************************************************************/
function datatable_response_for_view($view) {

    global $field_settings, $field_settings_views;

    // Default-deny an unknown or absent view.
    if (empty($view) || empty($field_settings_views[$view])) {
        json_response(404, "Unknown datatable view.", null);
        return;
    }

    // @phan-suppress-next-line PhanTypeArraySuspiciousNullable
    $type = $field_settings_views[$view]['view_type'];

    //don't need customization now, but keep it yet.
    $customization = customization_extra();
    
    $selected_fields = display_settings_get_display_settings_for_view($view);
    
    // Validating and defaulting for the paging data
    $start = !empty($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = !empty($_POST['length']) ? (int)$_POST['length'] : 10;
    
    // In case there's no column selected that is orderable the order won't be sent from the client
    if (!empty($_POST['order'])) {

        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderDir = strtoupper($_POST['order'][0]['dir']) == "ASC" ? "ASC" : "DESC";

        // Get and validate the order column
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 0;
        $orderColumnName =
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        !empty($_POST['columns'][$orderColumnIndex]['name'])
        // @phan-suppress-next-line PhanTypeMismatchDimFetch
        && in_array($_POST['columns'][$orderColumnIndex]['name'], $selected_fields)
        && (
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            (!empty($field_settings[$type][$_POST['columns'][$orderColumnIndex]['name']]) && $field_settings[$type][$_POST['columns'][$orderColumnIndex]['name']]['orderable'])
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            || str_starts_with($_POST['columns'][$orderColumnIndex]['name'], 'custom_field_')
            )
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            ? $_POST['columns'][$orderColumnIndex]['name']
            : 'id';
    } else {

        // so we're defaulting to ordering by the asset's id
        $orderColumnName = 'id';
        $orderDir = "ASC";

    }
    
    $column_filters = [];
    // @phan-suppress-next-line PhanTypeArraySuspiciousNullable
    for ($i = 0; $i < count($_POST['columns']); $i++) {

        // Gathering filter data for only the fields that are either set as searchable in the field settings
        // or a custom field which is searchable by default
        // when unselect all in the multi select of the table header, $_POST['columns'][$i]['search']['value'] is not set but it is also one of the custom cases that we input filter value. So we should consider that case as column_filters too.
        if (
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            !empty($_POST['columns'][$i]['name']) &&
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            (!empty($_POST['columns'][$i]['search']['value']) || !isset($_POST['columns'][$i]['search']['value'])) &&
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            in_array($_POST['columns'][$i]['name'], $selected_fields) &&
            (
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
                (!empty($field_settings[$type][$_POST['columns'][$i]['name']]['searchable']) && $field_settings[$type][$_POST['columns'][$i]['name']]['searchable'])
                ||
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
                ($customization && str_starts_with($_POST['columns'][$i]['name'], 'custom_field_'))
            )
        ) {
            // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
            if (isset($_POST['columns'][$i]['search']['value'])) {
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
                $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
            } else {
                // @phan-suppress-next-line PhanTypeMismatchDimFetch,PhanTypeArraySuspiciousNullable,PhanTypeArraySuspiciousNull,PhanTypePossiblyInvalidDimOffset
                $column_filters[$_POST['columns'][$i]['name']] = null;
            }
        }
    }
    
    // Get data for datatable
    $data = get_data_for_datatable($view, $selected_fields, $start, $length, $orderColumnName, $orderDir, $column_filters);

    $result = array(
        'draw' => (int)$_POST['draw'],
        'data' => $data['rows'],
        'recordsTotal' => $data['recordsTotal'],
        'recordsFiltered' => $data['recordsFiltered'],
    );
    
    // @phan-suppress-next-line SecurityCheck-XSS -- JSON response for DataTables; data comes from get_data_for_datatable() with proper server-side processing
    echo json_encode($result);
    exit;

}

/**********************************************
 * FUNCTION: ACTIVATE OR DEACTIVATE EXTRA API *
 **********************************************/
/**
 * Shared by both branches of activateDeactivateExtraApi() -- prevents the
 * 2x2 source/stale dispatch from drifting between the activate and
 * deactivate copies.
 *
 * @return array{0: int, 1: string, 2: ?array}
 */
function encryption_pipeline_in_flight_response(array $inflight, string $action): array
{
    global $lang, $escaper;

    $stale = $inflight['stale'] ?? false;

    if ($inflight['source'] === 'state') {
        if ($stale) {
            write_debug_log("Encryption Extra {$action} refused — encryption_activation_state has been 'in_progress' for an unusually long time with no matching queue_tasks row; the background queue worker may not be running.", 'warning');
            return [409, $lang['EncryptionPipelineStalledState'], null];
        }
        write_debug_log("Encryption Extra {$action} refused — encryption_activation_state is 'in_progress'.", 'notice');
        return [409, $lang['EncryptionPipelineInProgress'], null];
    }

    if ($stale) {
        write_debug_log("Encryption Extra {$action} refused — task #{$inflight['id']} ({$inflight['task_type']}) has been {$inflight['status']} since {$inflight['created_at']} without progressing; the background queue worker may not be running.", 'warning');
        return [409, _lang('EncryptionPipelineStalledTask', ['id' => $inflight['id'], 'type' => $inflight['task_type'], 'status' => $inflight['status']]), null];
    }

    write_debug_log("Encryption Extra {$action} refused — task #{$inflight['id']} ({$inflight['task_type']}) is currently {$inflight['status']}.", 'notice');
    // EncryptionPipelineInProgressTask predates this fix and is already
    // translated into ~35 locales using the bare {id}/{type} placeholder
    // convention (a real, separate convention this file also uses for
    // client-side JS templating -- see EncryptionStageProgress). _lang()
    // only substitutes {$param}/${param}/$param, never bare {param}, so
    // rewriting the English string to the $-prefixed form would fix
    // English while leaving every translation's bare placeholders
    // substituting nothing -- and Translation-type changes to those
    // locale files are gated to the Crowdin service account, not this
    // PR. Substituting the bare tokens directly fixes the real value for
    // every locale, English included, without touching any lang file.
    $message = str_replace(
        ['{id}', '{type}'],
        [$escaper->escapeHtml((string)$inflight['id']), $escaper->escapeHtml((string)$inflight['task_type'])],
        $lang['EncryptionPipelineInProgressTask']
    );
    return [409, $message, null];
}

function activateDeactivateExtraApi() {
    global $escaper, $lang;

    // Check that this is an admin user
    if (!is_admin())
    {
        json_response(403, $lang['AdminPermissionRequired'], NULL);
        return;
    }

    $extra_type = isset($_POST['extra_type']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['extra_type']) : "";

    // Validate the slug against the Settings Hub catalog (the source of truth
    // for what extra_name slugs the Hub knows about). Build a slug→entry map
    // up front so we can also look up the display name and locate the on-disk
    // directory + enable/disable handler names in one pass.
    require_once(realpath(__DIR__ . '/settings_catalog.php'));
    require_once(realpath(__DIR__ . '/extras.php'));
    $catalog_by_slug = [];
    foreach (settings_catalog() as $entry) {
        if (!empty($entry['extra_name'])) {
            $catalog_by_slug[$entry['extra_name']] = $entry;
        }
    }
    if (!isset($catalog_by_slug[$extra_type])) {
        return json_response(400, "Invalid extra type specified.", NULL);
    }

    // The catalog's extra_name is now the on-disk directory name. The
    // enable/disable handler function infix comes from extra_handler_slug
    // (which matches the dir name for most Extras, with a few overrides
    // documented in extras.php). The state-check function name comes from
    // extra_state_check_function for the same reason.
    $dir          = $extra_type;
    $handler_slug = extra_handler_slug($dir);

    // Resolve on-disk directory.
    $path = realpath(__DIR__ . '/../extras/' . $dir . '/index.php');
    if (!$path) {
        return json_response(404, "The specified extra does not exist on the server.", NULL);
    }

    // Include the Extra so its enable_/disable_/state functions are defined.
    // $extra_type is regex-stripped to [a-zA-Z0-9_\-]+ at line 14230, then
    // validated via isset($catalog_by_slug[$extra_type]) against the
    // hard-coded Settings Hub catalog at line 14244, then realpath()'d on
    // line 14257 — only on-disk paths under simplerisk/extras/<known>/index.php
    // can reach this require_once. Phan can't trace that allow-list through
    // the array-key lookup, so the suppression carries the reasoning.
    // @phan-suppress-next-line SecurityCheck-PathTraversal -- $extra_type validated against hard-coded catalog allowlist via isset() before realpath() construction; only known Extra names can reach here
    require_once($path);

    // Display name from the catalog entry's label_key.
    $entry = $catalog_by_slug[$extra_type];
    $display_name = $lang[$entry['label_key']] ?? $entry['label_key'];

    // State check uses the canonical state-check function name (matches
    // functions.php). For 4 historical Extras (separation, authentication,
    // import-export, complianceforgescf) this is NOT just "<dir>_extra".
    $state_fn = extra_state_check_function($dir);

    // If the user wants to activate the extra
    if (isset($_POST['activate'])) {
        $handler = "enable_{$handler_slug}_extra";

        if (!function_exists($handler)) {
            return json_response(500, "Activation handler does not exist");
        }

        // Encrypted Database Extra activation needs LOCK TABLES on the
        // SimpleRisk schema to safely encrypt-and-swap each table. The
        // grant ships with fresh installs and is back-filled by the
        // 20260519-001 -> 20260709-001 upgrade, but customers on locked-
        // down installs may need to grant it manually. Refuse activation
        // with a clear error containing the exact GRANT statement to run.
        // Fire the gate BEFORE the idempotency check so re-activation
        // attempts also surface the missing-privilege guidance.
        //
        // Also refuse if any encryption pipeline (activate, deactivate,
        // restore) is currently in flight — two concurrent pipelines
        // against the same DB will corrupt data. Two sources of truth
        // are checked for belt-and-suspenders: the state setting (written
        // at the very start of each pipeline) and the queue_tasks table
        // (covers the window between API call and the first state write,
        // and any state that drifted out of sync).
        if ($extra_type === 'encryption') {
            require_once(realpath(__DIR__ . '/../extras/encryption/privilege_check.php'));
            require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
            $db_priv = db_open();
            $has_priv = has_lock_tables_privilege($db_priv);
            if (!$has_priv) {
                db_close($db_priv);
                $grant_user = encryption_required_grantee_string();
                $grant_db   = '`' . str_replace('`', '``', DB_DATABASE) . '`';
                $required_grant = "GRANT LOCK TABLES ON {$grant_db}.* TO {$grant_user};";
                write_debug_log("Encryption Extra activation refused — LOCK TABLES privilege missing for {$grant_user}.", 'notice');
                return json_response(412, $lang['EncryptionMissingLockTablesPrivilege'], [
                    'required_grant' => $required_grant,
                ]);
            }

            $inflight = encryption_pipeline_in_flight($db_priv);
            db_close($db_priv);

            if ($inflight !== null) {
                [$status_code, $message, $data] = encryption_pipeline_in_flight_response($inflight, 'activation');
                return json_response($status_code, $message, $data);
            }
        }

        $status_message = _lang('ActivatedExtra', array("extra_type" => $display_name));

        // Idempotent: if already enabled, return success without re-running the handler.
        // The handler calls prevent_extra_double_submit() which invokes refresh() (redirect
        // + exit) when the extra is already in the target state — that breaks API callers.
        if (function_exists($state_fn) && $state_fn()) {
            return json_response(200, $status_message);
        }

        //Enable the Extra
        $result = $handler();

        // New return-aware handling — opt-in via array return. Handlers that
        // return ['ok' => false, 'reason' => ...] arrays signal a guard
        // failure (missing privilege, already-running, enqueue failed). They
        // have already called set_alert() with operator-facing detail; we
        // translate the reason to an HTTP status so the JS doesn't start
        // polling on a failure. Handlers that return null/void are legacy
        // and keep the existing 200-on-call behavior.
        if (is_array($result) && array_key_exists('ok', $result) && $result['ok'] === false) {
            $reason = (string)($result['reason'] ?? 'unknown');
            $status_code = match ($reason) {
                'missing_privilege'  => 412,
                'already_running'    => 409,
                'enqueue_failed'     => 500,
                'openssl_missing'    => 412,
                default              => 400,
            };
            // Leave the alert in place — the operator will see it on the
            // next page render. Surface the reason in `data` so the JS can
            // decide whether to start polling or just show the toast.
            return json_response($status_code, $lang['ActivationGuardFailed'] ?? 'Activation could not start. See the alert in the page header for details.', [
                'reason' => $reason,
            ]);
        }

        // Consume any queued toast so it doesn't leak to the next navigation;
        // the API response itself carries the success message for the JS modal.
        get_alert(true, true);
        return json_response(200, $status_message);
    }

    // If the user wants to deactivate the extra
    if (isset($_POST['deactivate'])) {
        $handler = "disable_{$handler_slug}_extra";

        if (!function_exists($handler)) {
            return json_response(500, "Deactivation handler does not exist");
        }

        $status_message = _lang('DeactivatedExtra', array("extra_type" => $display_name));

        // Refuse deactivation if an encryption pipeline is currently in
        // flight — same two-source-of-truth check as the activate branch.
        if ($extra_type === 'encryption') {
            require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
            $db_deact_check = db_open();
            $inflight = encryption_pipeline_in_flight($db_deact_check);
            db_close($db_deact_check);

            if ($inflight !== null) {
                [$status_code, $message, $data] = encryption_pipeline_in_flight_response($inflight, 'deactivation');
                return json_response($status_code, $message, $data);
            }
        }

        // Idempotent: if already disabled, return success without re-running the handler.
        if (function_exists($state_fn) && !$state_fn()) {
            return json_response(200, $status_message);
        }

        //Disable the Extra
        $result = $handler();

        // New return-aware handling — see the activate branch above for the
        // contract. Handlers opting in return ['ok' => false, 'reason' => ...]
        // arrays for guard failures; legacy handlers return null/void and
        // keep the existing 200-on-call behavior.
        if (is_array($result) && array_key_exists('ok', $result) && $result['ok'] === false) {
            $reason = (string)($result['reason'] ?? 'unknown');
            $status_code = match ($reason) {
                'missing_privilege'  => 412,
                'already_running'    => 409,
                'enqueue_failed'     => 500,
                'openssl_missing'    => 412,
                default              => 400,
            };
            return json_response($status_code, $lang['ActivationGuardFailed'] ?? 'Activation could not start. See the alert in the page header for details.', [
                'reason' => $reason,
            ]);
        }

        // Consume any queued toast (see comment above).
        get_alert(true, true);
        return json_response(200, $status_message);
    }

    return json_response(400, "No action specified.", NULL);
}

?>