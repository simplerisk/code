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

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

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
    else 
    {
        // If internal request on browser, check csrf token
        if(!isset($_GET['key']))
        {
            // Load CSRF Magic
            require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));
        }
        else
        {
            if (api_extra())
            {
                require_once(realpath(__DIR__ . '/../extras/api/index.php'));
                
                if(!is_valid_key_by_uid($_GET['key'], $_SESSION['uid']))
                {
                    // Load CSRF Magic
                    require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));
                }
            }
            else
            {
                // Load CSRF Magic
                require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));
            }
        }

        return true;
    }
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
            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login']);
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
            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login']);
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
            // Create the new data array
            $data[] = array("uid" => $user['value'], "type" => $user['type'], "username" => $user['username'], "email" => $user['email'], "last_login" => $user['last_login']);
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
        
        $affected_assets_filter = empty($_GET['affected_assets_filter']) ? [] : $_GET['affected_assets_filter'];

        if($affected_assets_filter)
        {
            $processed_affected_assets_filter = array('group'=>[], 'asset'=>[]);
            foreach($affected_assets_filter as $asset_filter) {
                if (preg_match('/^([\d]+)_(group|asset)$/', $asset_filter, $matches)) {
                    list(, $id, $type) = $matches;
                    
                    array_push($processed_affected_assets_filter[$type], (int)$id);
                }
            }
        }
        else
        {
            $processed_affected_assets_filter = [];
        }

        $tags_filter = isset($_GET['tags_filter']) ? array_map('intval', $_GET['tags_filter']) : [];
        $start = (isset($_GET['start']) && $_GET['start']) ? $_GET['start'] : 0;
        $length = (isset($_GET['length']) && $_GET['length']) ? $_GET['length'] : 10;
        $rowCount = "";

        $review_levels = get_review_levels();

        // Query the risks
        $data = risks_query($status, $sort, $group, $processed_affected_assets_filter, $tags_filter, $rowCount, $start, $length);
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
		        "residual_risk"		=> $escaper->escapeHtml($risk['residual_risk']),
                "color"                 => get_risk_color($risk['calculated_risk']),
		        "residual_color"	=> get_risk_color($risk['residual_risk']),
                "submission_date"       => $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))),
                "review_date"           => $escaper->escapeHtml($risk['review_date']),
                "project"               => $escaper->escapeHtml($risk['project']),
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
function viewrisk()
{
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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
            $location = get_name_by_value("location", $risk[0]['location']);
            $source = get_name_by_value("source", $risk[0]['source']);
            $category = get_name_by_value("category", $risk[0]['category']);
            $team = get_name_by_value("team", $risk[0]['team']);
            $technology = get_technology_names($risk[0]['technology']);
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
                "category" => $category, "team" => $team,
                "technology" => $technology,
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
function viewmitigation()
{
    global $escaper, $lang;

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
function viewreview()
{
    global $escaper, $lang;
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        // Return a JSON response
        json_response(400, "You need to specify an id parameter.", NULL);
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
        
        $affected_assets_filter = empty($_POST['affected_assets_filter']) ? [] : $_POST['affected_assets_filter'];
        

        if (!empty($affected_assets_filter)) 
        {
            $processed_affected_assets_filter = array('group'=>[], 'asset'=>[]);
            foreach($affected_assets_filter as $asset_filter) {
                if (preg_match('/^([\d]+)_(group|asset)$/', $asset_filter, $matches)) {
                    list(, $id, $type) = $matches;

                    array_push($processed_affected_assets_filter[$type], (int)$id);
                }
            }
        }
        else
        {
            $processed_affected_assets_filter = [];
        }

        $tags_filter = isset($_POST['tags_filter']) ? array_map ('intval', $_POST['tags_filter']) : [];
        $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
        $length = $_POST['length'] ? (int)$_POST['length'] : 10;
        $group_value_from_db = $_POST['group_value'] ? $_POST['group_value'] : "";
        $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
        $orderColumnName = isset($_POST['columns'][$orderColumnIndex]['name']) ? $_POST['columns'][$orderColumnIndex]['name'] : null;
        $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : "asc";
        
        // Params in risks_by_teams page
        $risks_by_team = isset($_POST['risks_by_team']) ? true : false;
        $teams = isset($_POST['teams']) ? $_POST['teams'] : [];
        $owners = isset($_POST['owners']) ? $_POST['owners'] : [];
        $ownersmanagers = isset($_POST['ownersmanagers']) ? $_POST['ownersmanagers'] : [];

        $rowCount = 0;
        // Query the risks
        $risks = risks_query($status, $sort, $group, $processed_affected_assets_filter, $tags_filter, $rowCount, $start, $length, $group_value_from_db, "", [], $orderColumnName, $orderDir, $risks_by_team, $teams, $owners, $ownersmanagers);

//        $orderColumnIndex = $_POST['order'][0]['column'];
//        $orderDir = $_POST['order'][0]['dir'];
//        $orderColumnName = $_POST['columns'][$orderColumnIndex]['name'];
//        $sorted = false;
//        if($orderColumnName == "calculated_risk" || $orderColumnName == "id"){
//            $sorted = true;
            // Reset order for specific columns
//            usort($risks, function($a, $b) use ($orderDir, $orderColumnName)
//                {
//                    switch($orderColumnName){
//                        case "id":
//                            $aValue = trim($a['id']);
//                            $bValue = trim($b['id']);
//                        break;
//                        case "calculated_risk":
//                            $aValue = trim($a['calculated_risk']);
//                            $bValue = trim($b['calculated_risk']);
//                        break;
//                        case "residual_risk":
//                            $aValue = trim($a['residual_risk']);
//                            $bValue = trim($b['residual_risk']);
//                        break;
//                        default:
//                            return 0;
//                    }
//                    if($orderDir == 'asc'){
//                        return strcasecmp($aValue, $bValue);
//                    }else{
//                        return strcasecmp($bValue, $aValue);
//                    }
//                }
//            );
//        }

        $datas = array();
        foreach($risks as $row){
            $row['id'] = (int)$row['id'] + 1000;

            $data = array(
                "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($row['id']) . "\" target=\"_blank\">".$escaper->escapeHtml($row['id'])."</a>",
                $escaper->escapeHtml($row['status']),
                $escaper->escapeHtml($row['subject']),
                $escaper->escapeHtml($row['reference_id']),
                $escaper->escapeHtml($row['regulation']),
                $escaper->escapeHtml($row['control_number']),
                $escaper->escapeHtml($row['location']),
                $escaper->escapeHtml($row['source']),
                $escaper->escapeHtml($row['category']),
                $escaper->escapeHtml($row['team']),
                $escaper->escapeHtml($row['additional_stakeholders']),
                $escaper->escapeHtml($row['technology']),
                $escaper->escapeHtml($row['owner']),
                $escaper->escapeHtml($row['manager']),
                $escaper->escapeHtml($row['submitted_by']),
                $escaper->escapeHtml($row['scoring_method']),
                "<div class='".$escaper->escapeHtml($row['color'])."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($row['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($row['color']) . "\"></span></div></div>",
                "<div class='".$escaper->escapeHtml($row['residual_color'])."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($row['residual_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($row['residual_color']) . "\"></span></div></div>",
                $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($row['submission_date']))),
                $escaper->escapeHtml($row['review_date']),
                $escaper->escapeHtml($row['project']),
                planned_mitigation($row['id'], $row['mitigation_id']) , // mitigation plan
                management_review($row['id'], $row['mgmt_review'], $row['next_review_date']) , // management review
                $escaper->escapeHtml($row['days_open']),
                $row['next_review_date_html'],
                $escaper->escapeHtml($row['next_step']),
                "<div class='affected-asset-cell'>{$row['affected_assets']}</div>",
                $escaper->escapeHtml($row['risk_assessment']),
                $escaper->escapeHtml($row['additional_notes']),
                $escaper->escapeHtml($row['current_solution']),
                $escaper->escapeHtml($row['security_recommendations']),
                $escaper->escapeHtml($row['security_requirements']),
                $escaper->escapeHtml($row['planning_strategy']),
                $escaper->escapeHtml($row['planning_date']),
                $escaper->escapeHtml($row['mitigation_effort']),
                $escaper->escapeHtml($row['mitigation_cost']),
                $escaper->escapeHtml($row['mitigation_owner']),
                $escaper->escapeHtml($row['mitigation_team']),
                $escaper->escapeHtml($row['mitigation_date']),
                $escaper->escapeHtml($row['mitigation_control_names']),
                $escaper->escapeHtml($row['risk_tags']),
            );

            // If customization extra is enabled, add custom fields
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                $custom_values = getCustomFieldValuesByRiskId($row['id']);

                $active_fields = get_active_fields();
                foreach($active_fields as $active_field)
                {
                    // If main field, ignore.
                    if($active_field['is_basic'] == 1){
                        continue;
                    }
                    
                    $text = "";
                    
                    // Get value of custom filed
                    foreach($custom_values as $custom_value)
                    {
                        // Check if this custom value is for the active field
                        if($custom_value['field_id'] == $active_field['id']){
                            $value = $custom_value['value'];
                            
                            $text = get_custom_field_name_by_value($active_field['id'], $custom_value['field_type'], $value);
                            
                            break;
                        }
                    }

                    // Set custom values to dynamic risk reporting page
                    $data[] = $text;
                    
                }

            }
            
            $datas[] = $data;
        }

//        if(!$sorted){
//            usort($rows, function($a, $b) use ($orderDir, $orderColumnIndex)
//                {
//                    if($orderDir == 'asc'){
//                        return strcasecmp(trim($a[$orderColumnIndex]), trim($b[$orderColumnIndex]));
//                    }else{
//                        return strcasecmp(trim($b[$orderColumnIndex]), trim($a[$orderColumnIndex]));
//                    }
//                }
//            );
//        }


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
        
        $ContributingLikelihood = $risk[0]['Contributing_Likelihood'];
        $contributing_risks_impacts = $risk[0]['Contributing_Risks_Impacts'];
        if($contributing_risks_impacts){
            $ContributingImpacts = get_contributing_impacts_by_subjectimpact_values($contributing_risks_impacts);
        }else{
            $ContributingImpacts = [];
        }
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
        
        $ContributingLikelihood = "";
        $ContributingImpacts = [];
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
    global $escaper;
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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

    // Reopen the risk
    reopen_risk($id);

    $html = getTabHtml($id, 'overview');

    json_response(200, get_alert(true), $html);
}

/*************************************
 * FUNCTION: MANAGEMENT - REOPEN RISK *
 *************************************/
function overviewForm()
{
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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
function closeriskForm(){
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $access = check_access_for_risk($id);

    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){
        $status = "Closed";
        $close_reason = $_POST['close_reason'];
        $note = $_POST['note'];

        // Submit a review
        submit_management_review($id, $status, null, null, $_SESSION['uid'], $note, "0000-00-00", true);

        // Close the risk
        close_risk($id, $_SESSION['uid'], $status, $close_reason, $note);

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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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

    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $id = $_GET['id'];

    $access = check_access_for_risk($id);
    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){

        $error = update_risk($id);
        
        $risk = get_risk_by_id($id);

        /************************** Save Risk Score Method *********************************************/
        // Risk scoring method
        // 1 = Classic
        // 2 = CVSS
        // 3 = DREAD
        // 4 = OWASP
        // 5 = Custom


        // Classic Risk Scoring Inputs
        $scoring_method = (int)$_POST['scoring_method'];
        $CLASSIC_likelihood = (int)$_POST['likelihood'];
        $CLASSIC_impact =(int) $_POST['impact'];

//        if($risk[0]['scoring_method'] != $scoring_method || $risk[0]['CLASSIC_likelihood'] != $CLASSIC_likelihood || $risk[0]['CLASSIC_impact'] != $CLASSIC_impact ){
            // Classic Risk Scoring Inputs
    //            $CLASSIClikelihood = (int)$_POST['likelihood'];
    //            $CLASSICimpact =(int) $_POST['impact'];

            // CVSS Risk Scoring Inputs
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

            // DREAD Risk Scoring Inputs
            $DREADDamagePotential = (int)$_POST['DREADDamage'];
            $DREADReproducibility = (int)$_POST['DREADReproducibility'];
            $DREADExploitability = (int)$_POST['DREADExploitability'];
            $DREADAffectedUsers = (int)$_POST['DREADAffectedUsers'];
            $DREADDiscoverability = (int)$_POST['DREADDiscoverability'];

            // OWASP Risk Scoring Inputs
            $OWASPSkillLevel = (int)$_POST['OWASPSkillLevel'];
            $OWASPMotive = (int)$_POST['OWASPMotive'];
            $OWASPOpportunity = (int)$_POST['OWASPOpportunity'];
            $OWASPSize = (int)$_POST['OWASPSize'];
            $OWASPEaseOfDiscovery = (int)$_POST['OWASPEaseOfDiscovery'];
            $OWASPEaseOfExploit = (int)$_POST['OWASPEaseOfExploit'];
            $OWASPAwareness = (int)$_POST['OWASPAwareness'];
            $OWASPIntrusionDetection = (int)$_POST['OWASPIntrusionDetection'];
            $OWASPLossOfConfidentiality = (int)$_POST['OWASPLossOfConfidentiality'];
            $OWASPLossOfIntegrity = (int)$_POST['OWASPLossOfIntegrity'];
            $OWASPLossOfAvailability = (int)$_POST['OWASPLossOfAvailability'];
            $OWASPLossOfAccountability = (int)$_POST['OWASPLossOfAccountability'];
            $OWASPFinancialDamage = (int)$_POST['OWASPFinancialDamage'];
            $OWASPReputationDamage = (int)$_POST['OWASPReputationDamage'];
            $OWASPNonCompliance = (int)$_POST['OWASPNonCompliance'];
            $OWASPPrivacyViolation = (int)$_POST['OWASPPrivacyViolation'];

            // Custom Risk Scoring
            $custom = (float)$_POST['Custom'];
            
            // Contributing Risk Scoring
            $ContributingLikelihood = (int)$_POST['ContributingLikelihood'];
            $ContributingImpacts = $_POST['ContributingImpacts'];

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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }

    $id = $_GET['id'];
    $access = check_access_for_risk($id);

    // Check if the user has access to plan mitigations
    if (!isset($_SESSION["plan_mitigations"]) || $_SESSION["plan_mitigations"] != 1)
    {
        $plan_mitigations = false;

        $status = 400;
        $status_message = $lang['MitigationPermissionMessage'];
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
            $mitigation_date = submit_mitigation($id, $status, $_POST);
        }
        else
        {
            // Update mitigation and get the mitigation date back
            $mitigation_date = update_mitigation($id, $_POST);
        }

        $html = getTabHtml($id, 'details');

        $mitigation_percent = (int)$_POST['mitigation_percent'];

        ob_start();
        view_score_html($id, $risk[0]['calculated_risk'], $mitigation_percent);
        $score_wrapper_html = ob_get_contents();
        ob_end_clean();

        // Calculate residual risk score
        $data = ['score_wrapper_html' => $score_wrapper_html, 'html' => $html];

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

    $access = check_access_for_risk($id);
    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){

        $risk = get_risk_by_id($id);
        if (count($risk) != 0){
            $risk_level = get_risk_level_name($risk[0]['calculated_risk']);
        }else{
            $risk_level = "";
        }
        $approved = checkApprove($risk_level);
        if (!$approved)
        {
            // Display an alert
            set_alert(true, "bad", "You do not have permission to review " . $risk_level . " level risks.  Any reviews that you attempt to submit will not be recorded.  Please contact an administrator if you feel that you have reached this message in error.");
        }else{
            $status = "Mgmt Reviewed";
            $review = (int)$_POST['review'];
            $next_step = (int)$_POST['next_step'];
            $reviewer = $_SESSION['uid'];
            $comments = $_POST['comments'];
            $custom_date = $_POST['custom_date'];

            if ($custom_date == "yes")
            {
                $custom_review = $_POST['next_review'];

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

            if ($next_step == 2) {
                $project = !empty($_POST['project']) ? $_POST['project'] : 0;
                $prefix = 'new-projval-prfx-';
                if (startsWith($project, $prefix)) {//It's a new project's name
                    $name = substr($project, strlen($prefix));
                    $project = add_name("projects", try_encrypt($name));

                    if ($project)
                        set_alert(true, "good", $lang['SuccessCreateProject']);
                }

                if (ctype_digit((string)$project)) {
                    update_risk_project((int)$project, $id - 1000);
                    set_alert(true, "good", $lang['SuccessSetProject']);
                } else {
                    set_alert(true, "bad", $lang['ThereWasAProblemWithAddingTheProject']);
                }
            }
        }

        $html = getTabHtml($id, 'details');

        json_response(200, get_alert(true), $html);
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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $access = check_access_for_risk($id);

    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){

        $status_id = (int)$_POST['status'];

        // Get the name associated with the status
        $status = get_name_by_value("status", $status_id);

        // Check that the id is a numeric value
        if (is_numeric($id))
        {
            // Update the status of the risk
            update_risk_status($id, $status);

        }

        $html = getTabHtml($id, 'viewhtml');

        json_response(200, get_alert(true), $html);

    }else{

        set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);


    }

}

/*****************************************
 * FUNCTION: MANAGEMENT - SCORING ACTION *
 ****************************************/
function scoreactionForm()
{
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    $id = $_GET['id'];

    $access = check_access_for_risk($id);

    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){

        $new_subject = $_POST['subject'];
        if ($new_subject != '')
        {
            $subject = try_encrypt($new_subject);
            update_risk_subject($id, $subject);
//            set_alert(true, "good", "The subject has been successfully modified.");
        } else
        {
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
        set_alert(true, "bad", "You need to specify an id parameter.");

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
        set_alert(true, "bad", "You need to specify an id parameter.");

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
    // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", "You need to specify an id parameter.");

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
        $risk_id= $_GET{'id'};

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
        $risk_id= $_GET{'id'};

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
    if($new_subject !== false && $new_subject == ""){
        $status = "400";
        $status_message = $escaper->escapeHtml($lang['SubjectRiskCannotBeEmpty']);
        // Return a JSON response
        json_response($status, $status_message, NULL);
    }


    $access = check_access_for_risk($id);
    if(isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access){

        if($new_subject !== false){
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
    if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1)
    {
        $status = "401";
        $status_message = $escaper->escapeHtml($lang['RiskAddPermissionMessage']);
        $data = array();
    }elseif(!$subject){
        $status = "400";
        $status_message = $escaper->escapeHtml($lang['SubjectRiskCannotBeEmpty']);
        $data = array();
    }else{

        $status = "New";
        $reference_id = get_param("POST", 'reference_id');
        $regulation = (int)get_param("POST", 'regulation');
        $control_number = get_param("POST", 'control_number');
        $location = (int)get_param("POST", 'location');
        $source = (int)get_param("POST", 'source');
        $category = (int)get_param("POST", 'category');
        if(is_array(get_param("POST", 'team'))){
            $team = implode(",", get_param("POST", 'team'));
        }else{
            $team = get_value_string_by_table('team');
        }
        if(is_array(get_param("POST", 'technology'))){
            $technology = implode(",", get_param("POST", '$technology'));
        }else{
            $technology = "";
        }
        $owner = (int)get_param("POST", 'owner');
        $manager = (int)get_param("POST", 'manager');
        $assessment = get_param("POST", 'assessment');
        $notes = get_param("POST", 'notes');
        if(is_array(get_param("POST", 'additional_stakeholders'))){
            $additional_stakeholders = implode(",", get_param("POST", 'additional_stakeholders'));
        }else{
            $additional_stakeholders = "";
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

            create_subject_order(isset($_SESSION['encrypted_pass']) && $_SESSION['encrypted_pass'] ? $_SESSION['encrypted_pass'] : fetch_key());
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
        updateTagsOfType($last_insert_id, 'risk', get_param("POST", 'tags', []));

        // If the notification extra is enabled
        if (notification_extra())
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

            // Send the notification
            notify_new_risk($last_insert_id, $subject);
        }
        // There is an alert message
        $risk_id = (int)$last_insert_id + 1000;

        $status = 200;
        $status_message = "Risk ID " . $risk_id . " submitted successfully!";
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
        $status_message = "You need to specify an id parameter";
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
        $status_message = $lang['MitigationPermissionMessage'];
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
            'mitigation_team'           => get_param("POST", "mitigation_team", ""),
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
        $status_message = $lang['Success'];
        $data = array(
            'risk_id' => $id
        );
    }else{
        $status = 400;
        $status_message = $lang['RiskUpdatePermissionMessage'];
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
        $status_message = "You need to specify an id parameter";
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

        $approved = checkApprove($risk_level);
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

    $draw = $escaper->escapeHtml($_GET['draw']);
    $control_class = isset($_GET['control_class']) ? $_GET['control_class'] : [];
    $control_phase = isset($_GET['control_phase']) ? $_GET['control_phase'] : [];
    $control_family = isset($_GET['control_family']) ? $_GET['control_family'] : [];
    $control_owner = isset($_GET['control_owner']) ? $_GET['control_owner'] : [];
    $control_framework = isset($_GET['control_framework']) ? $_GET['control_framework'] : [];
    $control_priority = isset($_GET['control_priority']) ? $_GET['control_priority'] : [];
    $control_text = $_GET['control_text'];

    $controls = get_framework_controls_by_filter($control_class, $control_phase, $control_owner, $control_family, $control_framework, $control_priority, $control_text);
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
        $edit = '<a href="#" class="control-block--edit pull-right" title="'.$escaper->escapeHtml($lang["Edit"]).'" data-id="'.$escaper->escapeHtml($control['id']).'"><i class="fa fa-pencil-square-o"></i></a>';
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
        $delete = '<a href="javascript:voice(0);" class="control-block--delete pull-right" title="'.$escaper->escapeHtml($lang["Delete"]).'" data-id="'.$escaper->escapeHtml($control['id']).'"><i class="fa fa-trash"></i></a>';
        $html = "<div class='control-block item-block clearfix'>\n";
            $html .= "<div class='control-block--header clearfix' data-project=''>\n";

                $html .= "<div class='checkbox-in-div'>\n";
                    $html .= "<input type='checkbox' name='control_ids[]' value='".$escaper->escapeHtml($control['id'])."'>\n";
                $html .= "</div>\n";
                
                $html .= "<div class='control-block--row text-right'>\n";
                    $html .= $delete.$clone.$edit;
                $html .= "</div>\n";
                $html .= "<div class='control-block--row'>\n";
                    $html .= "<table width='100%'>\n";
                        $html .= "<tr>\n";
                            $html .= "<td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlLongName'])."</strong>: </td>\n";
                            $html .= "<td colspan='5'>".$escaper->escapeHtml($control['long_name'])."</td>\n";
                        $html .= "</tr>\n";
                        $html .= "<tr>\n";
                            $html .= "<td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlShortName'])."</strong>: </td>\n";
                            $html .= "<td width='22%' >".$escaper->escapeHtml($control['short_name'])."</td>\n";
                            $html .= "<td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlOwner'])."</strong>: </td>\n";
                            $html .= "<td width='22%'>".$escaper->escapeHtml($control['control_owner_name'])."</td>\n";
                            $html .= "<td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlFrameworks'])."</strong>: </td>\n";
                            $html .= "<td width='17%'>". display_expandable_framework_names($control['framework_names'], 8) ."</td>\n";
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
                            $html .= "<td colspan='5'>".nl2br($escaper->escapeHtml($control['description']))."</td>\n";
                        $html .= "</tr>\n";
                        $html .= "<tr>\n";
                            $html .= "<td align='right'><strong>".$escaper->escapeHtml($lang['SupplementalGuidance'])."</strong>: </td>\n";
                            $html .= "<td colspan='5'>".nl2br($escaper->escapeHtml($control['supplemental_guidance']))."</td>\n";
                        $html .= "</tr>\n";
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
    exit;
}

/********************************************************
 * FUNCTION: GET DATA FOR Mitigation CONTROLS DATATABLE *
 ********************************************************/
function getMitigationControlsDatatable(){
    global $lang;
    global $escaper;

    $draw = $escaper->escapeHtml($_GET['draw']);
    $control_ids = $_GET['control_ids'];
    $control_id_array = explode(",", $control_ids);

    $allControls = get_framework_controls();

    $controls = [];
    foreach($allControls as $control){
        if(in_array($control['id'], $control_id_array)){
            $controls[] = $control;
        }
    }

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
                            $html .= "<td width='22%' >".$escaper->escapeHtml($control['short_name'])."</td>\n";
                            $html .= "<td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlOwner'])."</strong>: </td>\n";
                            $html .= "<td width='22%'>".$escaper->escapeHtml($control['control_owner_name'])."</td>\n";
                            $html .= "<td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlFrameworks'])."</strong>: </td>\n";
                            $html .= "<td width='17%'>". display_expandable_framework_names($control['framework_names'], 8) ."</td>\n";
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
    exit;
}

/**********************************************
 * FUNCTION: GET DATA FOR FRAMEWORK DATATABLE *
 **********************************************/
function getFrameworksResponse()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission_governance())
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
        'framework_ids' => isset($_POST['frameworks']) ? $_POST['frameworks'] : "",
        'control_owner' => isset($_POST['control_owner']) ? (int)$_POST['control_owner'] : 0,
        'control_class' => isset($_POST['control_class']) ? (int)$_POST['control_class'] : 0,
        'control_phase' => isset($_POST['control_phase']) ? (int)$_POST['control_phase'] : 0,
        'control_number' => isset($_POST['control_number']) ? $_POST['control_number'] : "",
        'control_priority' => isset($_POST['control_priority']) ? (int)$_POST['control_priority'] : 0,
        'family' => isset($_POST['family']) ? (int)$_POST['family'] : 0,
        'mitigation_percent' => (isset($_POST['mitigation_percent']) && $_POST['mitigation_percent'] >= 0 && $_POST['mitigation_percent'] <= 100) ? (int)$_POST['mitigation_percent'] : 0
      );
      // Update the control
      update_framework_control($control_id, $control);

      // Display an alert
      set_alert(true, "good", "An existing control was updated successfully.");

      json_response(200, get_alert(true), []);
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
function updateFrameworkParentResponse()
{
    global $escaper, $lang;

    // If user has no permission for modify frameworks
    if(empty($_SESSION['modify_frameworks']))
    {
        $status_message = $escaper->escapeHtml($lang['NoModifyFrameworkPermission']);
    }
    // If user has permission for modify frameworks
    else
    {
        $parent  = (int)$_POST['parent'];
        $framework_id = (int)$_POST['framework_id'];
        update_framework_parent($parent, $framework_id);
        $status_message = "Updated framework status";
    }

    json_response(200, $status_message, []);
}

/*******************************************************************
 * FUNCTION: GET PARENT FRAMEWORKS DROPDOWN WITH NO SELECTED VALUE *
 *******************************************************************/
function getParentFrameworksDropdownResponse()
{
    global $lang, $escaper;
    
    $status = (int)$_GET['status'];

    // If the user has governance permissions
    if (check_permission_governance())
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
    if (check_permission_governance())
    {
        $type = $_GET['type'];

        $documents = get_documents($type);
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
    if (check_permission_governance())
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
    if (check_permission_governance())
    {
        $child_id = (int)$_GET['child_id'];
        $type = $_GET['type'];

        // Get child document
        $child_document = get_document_by_id($child_id);

        // Parent document ID
        $selected = $child_document['parent'];

        $documents = get_documents($type);

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

/***************************************************
 * FUNCTION: GET RELATED CONTROLS BY FRAMEWORK IDS *
 ***************************************************/
function getRelatedControlsByFrameworkIdsResponse()
{
    global $lang, $escaper;
    
    // If the user has governance permissions
    if (check_permission_governance())
    {
        $fids = get_param("get", "fids", "");
        if($fids)
        {
            $fids_arr = explode(",", $fids);
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
    if (check_permission_compliance())
    {
        $id     = (int)$_POST['id'];
        $type   = $_POST['type'];
        
        if($name = initiate_framework_control_tests($type, $id))
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
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoPermissionForCompliance']));
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
    if (check_permission_compliance())
    {
        $draw = $escaper->escapeHtml($_GET['draw']);

        $orderColumn = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        $orderColumnName = isset($_GET['columns'][$orderColumn]['name']) ? $_GET['columns'][$orderColumn]['name'] : null;;
        $orderDir = strtolower($_GET['order'][0]['dir']) == "asc" ? "asc" : "desc";

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
            
            $data[] = [
                $escaper->escapeHtml($audit_test['name']),
                $escaper->escapeHtml($audit_test['framework_names']),
                $audit_test['last_date'],
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
    if (check_permission_governance())
    {
        $id = $_GET['control_id'];
        $control = get_framework_control($id);
        json_response(200, "Get framework control by ID", ["control" => $control]);
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
    if (check_permission_governance())
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
    if (check_permission_compliance())
    {
        $draw = $escaper->escapeHtml($_GET['draw']);
        $control_framework = empty($_GET['control_framework']) ? [] : $_GET['control_framework'];

        $controls = get_framework_controls_by_filter("all", "all", "all", "all", $control_framework);
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
                                $html .= "<td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlLongName'])."</strong>: </td>\n";
                                $html .= "<td >".$escaper->escapeHtml($control['long_name'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td width='13%' align='right'><strong>".$escaper->escapeHtml($lang['ControlOwner'])."</strong>: </td>\n";
                                $html .= "<td >".$escaper->escapeHtml($control['control_owner_name'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlFrameworks'])."</strong>: </td>\n";
                                $html .= "<td>" . display_expandable_framework_names($control['framework_names'], 8) . "</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td width='13%' align='right' ><strong>".$escaper->escapeHtml($lang['ControlNumber'])."</strong>: </td>\n";
                                $html .= "<td>".$escaper->escapeHtml($control['control_number'])."</td>\n";
                            $html .= "</tr>\n";
                            $html .= "<tr>\n";
                                $html .= "<td align='right'><strong>". $escaper->escapeHtml($lang['Description']) ."</strong>: </td>\n";
                                $html .= "<td colspan='5'>". nl2br($escaper->escapeHtml($control['description'])) ."</td>\n";
                            $html .= "</tr>\n";
                        $html .= "</table>\n";
                    $html .= "</div>\n";

                    $html .= "<div class='text-right'>\n";
                        $html .= "<a href='#test--add' data-control-id='". $control['id'] ."' role='button' data-toggle='modal' class='btn add-test'>".$escaper->escapeHtml($lang['AddTest'])."</a>";
                    $html .= "</div>\n";

                    $html .= "<div class='framework-control-test-list'>\n";
                        $html .= "<table width='100%' class='table table-bordered table-striped table-condensed sortable'>\n";
                            $html .= "
                                <thead>
                                    <tr>
                                        <th>".$escaper->escapeHtml($lang['ID'])."</th>
                                        <th>".$escaper->escapeHtml($lang['TestName'])."</th>
                                        <th>".$escaper->escapeHtml($lang['Tester'])."</th>
                                        <th>".$escaper->escapeHtml($lang['AdditionalStakeholders'])."</th>
                                        <th>".$escaper->escapeHtml($lang['TestFrequency'])."</th>
                                        <th>".$escaper->escapeHtml($lang['LastTestDate'])."</th>
                                        <th>".$escaper->escapeHtml($lang['NextTestDate'])."</th>
                                        <th>".$escaper->escapeHtml($lang['ApproximateTime'])."</th>
                                        <th>&nbsp;</th>
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
                                    
                                    $last_date = format_date($test['last_date']);
                                    $next_date = format_date($test['next_date']);
                                    
                                    $html .= "
                                        <tr>
                                            <td>".$escaper->escapeHtml($test['id'])."</td>
                                            <td>".$escaper->escapeHtml($test['name'])."</td>
                                            <td>".$escaper->escapeHtml($test['tester_name'])."</td>
                                            <td>".$escaper->escapeHtml(get_stakeholder_names($test['additional_stakeholders'], 3))."</td>
                                            <td style='text-align:right'>".(int)$test['test_frequency']. " " .$escaper->escapeHtml($test['test_frequency'] > 1 ? $escaper->escapeHtml($lang['days']) : $escaper->escapeHtml($lang['Day']))."</td>
                                            <td>".$escaper->escapeHtml($last_date)."</td>
                                            <td>".$escaper->escapeHtml($next_date)."</td>
                                            <td style='text-align:right'>".(int)$test['approximate_time']. " " .$escaper->escapeHtml($test['approximate_time'] > 1 ? $escaper->escapeHtml($lang['minutes']) : $escaper->escapeHtml($lang['minute']))."</td>
                                            <td class='text-center'>
                                                <a data-id='".$escaper->escapeHtml($test['id'])."' class='edit-test' data-id=\"{$escaper->escapeHtml($test['id'])}\"><i class=\"fa fa-pencil-square-o\"></i></a>&nbsp;&nbsp;
                                                <a class='delete-row' data-toggle=\"modal\" data-id=\"{$escaper->escapeHtml($test['id'])}\"><i class=\"fa fa-trash\"></i></a>
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
    if (check_permission_compliance())
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
    if (check_permission_compliance())
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

                $results[] = array(
                    'id' => 'framework_'.$framework['value'],
                    'state' => 'closed',
                    'name' => "<a class='framework-name' data-id='{$framework['value']}' href='' title='".$escaper->escapeHtml($lang['Framework'])."'>".$escaper->escapeHtml($framework['name'])."</a>",
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework['last_audit_date'])),
                    'desired_frequency' => $escaper->escapeHtml($framework['desired_frequency']),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework['next_audit_date'])),
                    'status' => $escaper->escapeHtml($framework['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => "<div class='text-center'><button data-id='{$framework['value']}' class='initiate-framework-audit-btn' >".$escaper->escapeHtml($lang['InitiateFrameworkAudit'])."</button></div>"
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
                
                $results[] = array(
                    'id' => "control_".$framework_value."_".$framework_control['id'],
                    'state' => 'closed',
                    'name' => "<a class='control-name' data-id='{$framework_control['id']}' href='' title='".$escaper->escapeHtml($lang['Control'])."'>".$escaper->escapeHtml($framework_control['short_name'])."</a>",
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework_control['last_audit_date'])),
                    'desired_frequency' => $escaper->escapeHtml($framework_control['desired_frequency']),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework_control['next_audit_date'])),
                    'status' => $escaper->escapeHtml($framework_control['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => "<div class='text-center'><button data-id='{$framework_control['id']}' class='initiate-control-audit-btn' >".$escaper->escapeHtml($lang['InitiateControlAudit'])."</button></div>",
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

                $results[] = array(
                    'id' => "test_".$framework_and_control."_".$framework_control_test['id'],
                    'state' => 'open',
                    'name' => "<a class='test-name' data-id='{$framework_control_test['id']}' href='".$_SESSION['base_url']."/' title='".$escaper->escapeHtml($lang['Test'])."'>".$escaper->escapeHtml($framework_control_test['name'])."</a>",
                    'desired_frequency' => $escaper->escapeHtml($framework_control_test['desired_frequency']),
                    'last_audit_date' => $escaper->escapeHtml(format_date($framework_control_test['last_date'])),
                    'next_audit_date' => $escaper->escapeHtml(format_date($framework_control_test['next_date'])),
                    'status' => $escaper->escapeHtml($framework_control_test['status'] == 1 ? $lang['Active'] : $lang['Inactive']),
                    'action' => "<div class='text-center'><button data-id='{$framework_control_test['id']}' class='initiate-test-btn' >".$escaper->escapeHtml($lang['InitiateTest'])."</button></div>",
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
    if (check_permission_compliance())
    {
        $draw = $escaper->escapeHtml($_POST['draw']);

        $orderColumn = (int)$_POST['order'][0]['column'];
        $orderDir = strtolower($_POST['order'][0]['dir']) == "asc" ? "asc" : "desc";

        // Filter params
        $filters = array(
            "filter_text"   => $escaper->escapeHtml($_POST['filter_text']),
            "filter_control"        => empty($_POST['filter_control']) ? [] : $_POST['filter_control'],
            "filter_test_result"    => empty($_POST['filter_test_result']) ? [] : $_POST['filter_test_result'],
            "filter_framework"      => empty($_POST['filter_framework']) ? [] : $_POST['filter_framework'],
            "filter_start_audit_date"   => $_POST['filter_start_audit_date'] ? get_standard_date_from_default_format($_POST['filter_start_audit_date']) : "",
            "filter_end_audit_date"     => $_POST['filter_end_audit_date'] ? get_standard_date_from_default_format($_POST['filter_end_audit_date']) : "",
        );

        $columnNames = array(
            "test_name",
            "last_date",
            "control_name",
            "framework_name",
            "status",
            "test_result",
        );

        // Get past tests
        $past_test_audits = get_framework_control_test_audits(false, $columnNames[$orderColumn], $orderDir, $filters);

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

            $last_date = format_date($test_audit['last_date']);

            $data[] = [
                "<div ><a href='".$_SESSION['base_url']."/compliance/view_test.php?id=".$test_audit['id']."' class='text-left'>".$escaper->escapeHtml($test_audit['name'])."</a><input type='hidden' class='background-class' data-background='{$background_class}'></div>",
                "<div class=\"{}\">".$escaper->escapeHtml($last_date)."</div>",
                "<div >".$escaper->escapeHtml($test_audit['control_name'])."</div>",
                "<div >".$escaper->escapeHtml($test_audit['framework_name'])."</div>",
                "<div >".$escaper->escapeHtml($test_audit['audit_status_name'])."</div>",
                "<div >".$escaper->escapeHtml($test_audit['test_result'] ? $test_audit['test_result'] : "--")."</div>",
                "<div class='text-center'><button class='reopen' data-id='{$test_audit['id']}'>".$escaper->escapeHtml($lang['Reopen'])."</button></div>",
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
    if (check_permission_compliance())
    {
        $draw = $escaper->escapeHtml($_GET['draw']);

        $orderColumn = (int)$_GET['order'][0]['column'];
        $orderDir = strtolower($_GET['order'][0]['dir']) == "asc" ? "asc" : "desc";

        // Filter params
        $filters = array(
            "filter_text"       => $escaper->escapeHtml($_GET['filter_text']),
            "filter_framework"  => empty($_GET['filter_framework']) ? [] : $_GET['filter_framework'],
            "filter_status"     => empty($_GET['filter_status']) ? [] : $_GET['filter_status'],
            "filter_tester"     => empty($_GET['filter_tester']) ? [] : $_GET['filter_tester'],
        );

        $columnNames = array(
            "test_name",
            "test_frequency",
            "tester",
            "additional_stakeholders",
            "objective",
            "control_name",
            "framework_name",
            "status",
            "last_date",
            "next_date",
            "actions"
        );

        // Get active tests
        $active_tests = get_framework_control_test_audits(true, $columnNames[$orderColumn], $orderDir, $filters);

        $recordsTotal = count($active_tests);

        $data = array();

        foreach ($active_tests as $key=>$test)
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

            if(date("Y-m-d") <= $test['next_date']){
                $next_date_background_class = "green-background";
            }else{
                $next_date_background_class = "red-background";
            }

            $last_date = format_date($test['last_date']);
            $next_date = format_date($test['next_date']);

            $data[] = [
                "<div ><a href='".$_SESSION['base_url']."/compliance/testing.php?id=".$test['id']."' class='text-left'>".$escaper->escapeHtml($test['name'])."</a><input type='hidden' class='background-class' data-background='{$next_date_background_class}'></div>",
                "<div >".(int)$test['test_frequency']. " " .$escaper->escapeHtml($test['test_frequency'] > 1 ? $lang['days'] : $lang['Day'])."</div>",
                "<div >".$escaper->escapeHtml($test['tester_name'])."</div>",
                "<div >".$escaper->escapeHtml(get_stakeholder_names($test['additional_stakeholders'], 2))."</div>",                
                "<div >".$escaper->escapeHtml($test['objective'])."</div>",
                "<div >".$escaper->escapeHtml($test['control_name'])."</div>",
                "<div >".$escaper->escapeHtml($test['framework_name'])."</div>",
                "<div >".$escaper->escapeHtml($test['audit_status_name'])."</div>",
                "<div >".$escaper->escapeHtml($last_date)."</div>",
                "<div class='text-center '>".$escaper->escapeHtml($next_date)."</div>",
                "<div class='text-center'>
                    <button class='btn delete-btn' data-id='{$test['id']}' >".$escaper->escapeHtml($lang['Delete'])."</button>
                </div>"
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
    if (check_permission_compliance())
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
    if (check_permission_compliance())
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
    if (check_permission_compliance())
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

/********************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT CONTACTS DATATABLE *
 ********************************************************/
function assessment_extra_getAssessmentContacts(){
	// Check assessment extra is enabled
	if (assessments_extra())
	{
		// If the assessment extra file exists
		if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
		{
			// Include the file
			require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

			// Call the getAssessmentContacts function
			getAssessmentContacts();
		}
	}
}

/**********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE QUESTIONS DATATABLE *
 **********************************************************************/
function assessment_extra_getAssessmentQuestionnaireQuestions(){
    // Check assessment extra is enabled
    if (assessments_extra())
    {
        // If the assessment extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Call the getAssessmentQuestionnaireQuestions function
            getAssessmentQuestionnaireQuestions();
        }
    }
}

/*********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE TEMPLATE DATATABLE *
 *********************************************************************/
function assessment_extra_questionnaireTemplateDynamicAPI(){
    // Check assessment extra is enabled
    if (assessments_extra())
    {
        // If the assessment extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Call the questionnaireTemplateDynamicAPI function
            questionnaireTemplateDynamicAPI();
        }
    }
}

/************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE DATATABLE *
 ************************************************************/
function assessment_extra_questionnaireDynamicAPI(){
    // Check assessment extra is enabled
    if (assessments_extra())
    {
        // If the assessment extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Call the questionnaireDynamicAPI function
            questionnaireDynamicAPI();
        }
    }
}

/********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE RESULTS DATATABLE *
 ********************************************************************/
function assessment_extra_questionnaireResultsDynamicAPI(){
    // Check assessment extra is enabled
    if (assessments_extra())
    {
        // If the assessment extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Call the questionnaireResultsDynamicAPI function
            questionnaireResultsDynamicAPI();
        }
    }
}

/**********************************************
 * FUNCTION: SAVE QUESTIONNARE RESULT COMMENT *
 **********************************************/
function assessment_extra_saveQuestionnaireResultCommentAPI(){
    // Check assessment extra is enabled
    if (assessments_extra())
    {
        // If the assessment extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Call the saveQuestionnaireResultCommentAPI function
            saveQuestionnaireResultCommentAPI();
        }
    }
}

/*******************************************
 * FUNCTION: COPY ASSESSMENT QUESTIONNAIRE *
 *******************************************/
function assessment_extra_copyQuestionnaireAPI()
{
    // Check assessment extra is enabled
    if (assessments_extra())
    {
        // If the assessment extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Call the copyQuestionnaireAPI function
            copyQuestionnaireAPI();
        }
    }
}

/**************************************
 * FUNCTION: CUSTOMIZATION ADD OPTION *
 **************************************/
function customization_extra_addOption()
{
    // Check customization extra is enabled
    if (customization_extra())
    {
        // If the customization extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/customization/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            // Call the addOption function
            addOption();
        }
    }
}

/*****************************************
 * FUNCTION: CUSTOMIZATION DELETE OPTION *
 *****************************************/
function customization_extra_deleteOption()
{
    // Check customization extra is enabled
    if (customization_extra())
    {
        // If the customization extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/customization/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            // Call the addOption function
            deleteOption();
        }
    }
}

/*****************************************
 * FUNCTION: CUSTOMIZATION SAVE TEMPLATE *
 *****************************************/
function customization_extra_saveTemplate()
{
    // Check customization extra is enabled
    if (customization_extra())
    {
        // If the customization extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/customization/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            // Save template and get response
            saveTemplateResponse();
        }
    }
}

/***************************************************************
 * FUNCTION: AUTHENTICATION EXTRA GET LDAP GROUP DROPDOWN HTML *
 ***************************************************************/
function authentication_extra_ldap_group_dropdown()
{
    // Check authentication extra is enabled
    if (custom_authentication_extra())
    {
        // If the customization extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/authentication/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/authentication/index.php'));
            sleep(3);
            // Get ldap group dropdown html
            $html = ldap_groups_dropdown("ldap_group");
            
            json_response(200, "Ldap group dropdown", ['ldap_group' => $html]);
        }
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

/**********************************************************
 * FUNCTION: CREATE RISKS FROM QUESTIONNARE PENDING RISKS *
 **********************************************************/
function assessment_extra_createRisksFromQuestionnairePendingRisksAPI(){
    // Check assessment extra is enabled
    if (assessments_extra())
    {
        // If the assessment extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Call the saveRiskFromQuestionnairePendingRisksAPI function
            createRisksFromQuestionnairePendingRisksAPI();
        }
    }
}

/**************************************************
 * FUNCTION: GET QUESTIONNAIRE TEMPALTE QUESTIONS *
 **************************************************/
function assessment_extra_questionnaireTemplateQuestionsDynamicAPI(){
    // Check assessment extra is enabled
    if (assessments_extra())
    {
        // If the assessment extra file exists
        if (file_exists(realpath(__DIR__ . '/../extras/assessments/index.php')))
        {
            // Include the file
            require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

            // Call the questionnaireTemplateQuestionsDynamicAPI function
            questionnaireTemplateQuestionsDynamicAPI();
        }
    }
}

/********************************************
 * FUNCTION: GET RESPONSIBILITES BY ROLE ID *
 ********************************************/
function getResponsibilitiesByRoleIdForm(){
    $role_id = (int)$_GET['role_id'];
    
    // Get responsibilities by role ID
    $responsibilities = get_responsibilites_by_role_id($role_id);

    json_response(200, "Success", $responsibilities);
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
    if (check_permission_governance())
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
    if (check_permission_governance())
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $document = get_document_by_id($id);

        $document['creation_date'] = format_date($document['creation_date']);
        $document['review_date'] = format_date($document['review_date']);
        $document['control_ids'] = explode(',', $document['control_ids']);
        $document['framework_ids'] = explode(',', $document['framework_ids']);

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
    if (check_permission_governance())
    {
        $type = $_GET['type'];
        $document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        // Get current document
        $current_document = get_document_by_id($document_id);
        $version = $current_document['file_version'];
        
        // If this is request to view all versions of selected document.
        if($document_id)
        {
            // Get documents with versions
            $documents = get_document_versions_by_id($document_id);
            
            foreach($documents as &$document){
                $document['id'] = $document['id']."_".$document['file_version'];
                $document['state'] = "open";
                $document['document_type'] = $escaper->escapeHtml($document['document_type']);
                $document['document_name'] = "<a href=\"".$_SESSION['base_url']."/governance/download.php?id=".$document['unique_name']."\" >".$escaper->escapeHtml($document['document_name']). " (".$document['file_version'].")" ."</a>";
                $document['status'] = $escaper->escapeHtml($document['status']);
                $document['creation_date'] = format_date($document['creation_date']);
                $document['review_date'] = format_date($document['review_date']);
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
            $documents = get_documents($type);
            foreach($documents as &$document){
                $document['state'] = "closed";
                $document['document_type'] = $escaper->escapeHtml($document['document_type']);
                $document['document_name'] = "<a href=\"".$_SESSION['base_url']."/governance/download.php?id=".$document['unique_name']."\" >".$escaper->escapeHtml($document['document_name'])."</a>";
                $document['status'] = $escaper->escapeHtml($document['status']);
                $document['creation_date'] = format_date($document['creation_date']);
                $document['review_date'] = format_date($document['review_date']);
                $document['actions'] = "<div class=\"text-center\">&nbsp;&nbsp;&nbsp;";
                if(!empty($_SESSION['modify_documentation']))
                {
                    $document['actions'] .= "<a class=\"document--edit\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-pencil-square-o\"></i></a>&nbsp;&nbsp;&nbsp;";
                }
                if(!empty($_SESSION['delete_documentation']))
                {
                    $document['actions'] .= "<a class=\"document--delete\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-trash\"></i></a>&nbsp;&nbsp;&nbsp;";
                }
                $document['actions'] .= "</div>";
            }
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
    
    $control_info = '<table width="100%">
        <tbody>
            <tr>
                <td width="13%" align="right"><strong>' . $escaper->escapeHtml($lang['ControlShortName']) . '</strong>: </td>
                <td width="22%">'. $escaper->escapeHtml($control_short_name) .'</td>
                <td width="13%" align="right"><strong>' . $escaper->escapeHtml($lang['ControlOwner']) . '</strong>: </td>
                <td width="22%">'. $escaper->escapeHtml($control_owner) .'</td>
                <td width="13%" align="right"><strong>' . $escaper->escapeHtml($lang['ControlFramework']) . '</strong>: </td>
                <td width="17%">This is test</td>
            </tr>
            <tr>
                <td align="right"><strong>' . $escaper->escapeHtml($lang['ControlClass']) . '</strong>: </td>
                <td>'. $escaper->escapeHtml($control_class) .'</td>
                <td align="right"><strong>' . $escaper->escapeHtml($lang['ControlPhase']) . '</strong>: </td>
                <td>'. $escaper->escapeHtml($control_phase) .'</td>
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
    if (check_permission_riskmanagement())
    {
        $draw = $escaper->escapeHtml($_GET['draw']);

        $orderColumn = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        $orderColumnName = isset($_GET['columns'][$orderColumn]['name']) ? $_GET['columns'][$orderColumn]['name'] : null;;
        $orderDir = $escaper->escapeHtml($_GET['order'][0]['dir']);

        // Get risks requiring mitigations
        $risks = get_risks(1, $orderColumnName, $orderDir);

        $recordsTotal = count($risks);

        $data = array();
        
        $review_levels = get_review_levels();

        foreach ($risks as $key=>$risk)
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
            
            $color = get_risk_color($risk['calculated_risk']);

            $risk_level = get_risk_level_name($risk['calculated_risk']);
            $residual_risk_level = get_risk_level_name($risk['residual_risk']);

            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk")
            {
                $next_review = next_review($residual_risk_level, $risk['id'], $risk['next_review'], false, $review_levels, false, true);
            }
            // If next_review_date_uses setting is Inherent Risk.
            else
            {
                $next_review = next_review($risk_level, $risk['id'], $risk['next_review'], false, $review_levels, false, true);
            }
            
            $data[] = [
                "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class='open-risk'><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk['id'])) . "\">" . $escaper->escapeHtml(convert_id($risk['id'])) . "</a></div>",
                $escaper->escapeHtml($risk['status']),
                $escaper->escapeHtml($risk['subject']),
                "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>",

                $escaper->escapeHtml(date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']))),
                "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-mitigation mitigation active-cell\" >".planned_mitigation(convert_id($risk['id']), $risk['mitigation_id'])."</div>",
                "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-review management \">".management_review(convert_id($risk['id']), $risk['mgmt_review'], $next_review)."</div>",
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

/**************************************************************
 * FUNCTION: RETURN JSON DATA FOR MANAGEMENT REVIEW DATATABLE *
 **************************************************************/
function getManagementReviewsDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has risk management permissions
    if (check_permission_riskmanagement())
    {
        $draw = $escaper->escapeHtml($_GET['draw']);

        $orderColumn = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        $orderColumnName = isset($_GET['columns'][$orderColumn]['name']) ? $_GET['columns'][$orderColumn]['name'] : null;;
        $orderDir = $escaper->escapeHtml($_GET['order'][0]['dir']);

        // Get risks requiring mitigations
        $risks = get_risks(2, $orderColumnName, $orderDir);

        $recordsTotal = count($risks);

        $data = array();
        
        $review_levels = get_review_levels();

        foreach ($risks as $key=>$risk)
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
            
            $data[] = [
                "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class='open-risk'><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk['id'])) . "\">" . $escaper->escapeHtml(convert_id($risk['id'])) . "</a></div>",
                $escaper->escapeHtml($risk['status']),
                $escaper->escapeHtml($risk['subject']),
                "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($risk['calculated_risk']) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>",

                $escaper->escapeHtml(date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']))),
                "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-mitigation mitigation\" >".planned_mitigation(convert_id($risk['id']), $risk['mitigation_id'])."</div>",
                "<div data-id=". $escaper->escapeHtml(convert_id($risk['id'])) ." class=\"text-center open-review management active-cell\">".management_review(convert_id($risk['id']), $risk['mgmt_review'], $next_review)."</div>",
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

/*********************************************************
 * FUNCTION: RETURN JSON DATA FOR REVIEW RISKS DATATABLE *
 *********************************************************/
function getReviewRisksDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has risk management permissions
    if (check_permission_riskmanagement())
    {
        $draw = $escaper->escapeHtml($_GET['draw']);

        $orderColumn = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        $orderColumnName = isset($_GET['columns'][$orderColumn]['name']) ? $_GET['columns'][$orderColumn]['name'] : null;;
        $orderDir = $escaper->escapeHtml($_GET['order'][0]['dir']);

        // Get the list of reviews
        $risks = get_risks(3, $orderColumnName, $orderDir);

        $recordsTotal = count($risks);

        // Initialize the arrays
        $sorted_reviews = array();
        $need_reviews = array();
        $need_next_review = array();
        $need_calculated_risk = array();
        $reviews = array();
        $date_next_review = array();
        $date_calculated_risk = array();

        // Parse through each row in the array
        foreach ($risks as $key => $row)
        {
            // Create arrays for each value
            $risk_id[$key] = (int)$row['id'];
            $subject[$key] = $row['subject'];
            $status[$key] = $row['status'];
            $calculated_risk[$key] = $row['calculated_risk'];
            $color[$key] = get_risk_color($row['calculated_risk']);
            $risk_level = get_risk_level_name($row['calculated_risk']);
            $residual_risk_level = get_risk_level_name($row['residual_risk']);
            $dayssince[$key] = dayssince($row['submission_date']);

            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk")
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

            $sorted_reviews[] =  array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key]);

            // If the next review is UNREVIEWED or PAST DUE
            if ($next_review[$key] == "UNREVIEWED" || $next_review[$key] == $lang['PASTDUE'])
            {
                // Create an array of the risks needing immediate review
                $need_reviews[] = array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key]);
                $need_next_review[] = $next_review[$key];
                $need_calculated_risk[] = $calculated_risk[$key];
            }
            // Otherwise it is an actual review date
            else {
                // Create an array of the risks with future reviews
                $reviews[] = array('risk_id' => $risk_id[$key], 'subject' => $subject[$key], 'status' => $status[$key], 'calculated_risk' => $calculated_risk[$key], 'color' => $color[$key], 'dayssince' => $dayssince[$key], 'next_review' => $next_review[$key], 'next_review_html' => $next_review_html[$key]);
                // Convert next review to standard date fromat for sort
                $standard_next_review = get_standard_date_from_default_format($next_review[$key]);
                $date_next_review[] = $standard_next_review;
                $date_calculated_risk[] = $calculated_risk[$key];
            }
        }
        
        if($orderColumnName == "next_review"){
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
        

        $data = array();

        foreach ($reviews as $key=>$review)
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
            
            $risk_id = $review['risk_id'];
            $subject = $review['subject'];
            $status = $review['status'];
            $calculated_risk = $review['calculated_risk'];
            $color = $review['color'];
            $dayssince = $review['dayssince'];
            $next_review = $review['next_review'];
            $next_review_html = $review['next_review_html'];
            
            $data[] = [
                "<div data-id=". $escaper->escapeHtml(convert_id($risk_id)) ." class='open-risk'><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></div>",
                $escaper->escapeHtml($status),
                $escaper->escapeHtml($subject),
                "<div class='".$escaper->escapeHtml($color)."'><div class='risk-cell-holder'>" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></div></div>",

                $escaper->escapeHtml($dayssince),
                "<div data-id=". $escaper->escapeHtml(convert_id($risk_id)) ." class=\"text-center open-review\" >".$next_review_html."</div>",
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

/**************************
 * FUNCTION: VERIFY ASSET *
 **************************/
function assets_verify_asset()
{
    global $lang, $escaper;

        // If the id is not sent
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } else {
        // If the user has asset permissions
        if (check_permission_asset())
        {
            $id = (int)$_GET['id'];
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
    if (!isset($_GET['id']))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyAnIdParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    } else {
        // If the user has asset permissions
        if (check_permission_asset())
        {
            $id = (int)$_GET['id'];
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
        if (check_permission_asset())
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
        if (check_permission_asset())
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
    } else {
        // If the user has asset permissions
        if (check_permission_asset())
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
                    
                    $updated = save_asset_custom_field_by_field_id($id, $custom_field_id, $fieldValue);
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
                $asset = get_asset_by_id($id)[0];
                set_alert(true, "good", $escaper->escapeHtml($lang['AssetWasUpdatedSuccessfully']));
                if ($fieldName == "tags") {
                    $options = [];
                    foreach(getTagsOfType('asset') as $tag) {
                        $options[] = array('label' => $tag);
                    }
                    json_response(200, get_alert(true), $options);
                } else {
                    json_response(200, get_alert(true), null);
                }
            }
            // If failed for update
            else{
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
    ob_start();
    display_asset_table_body();
    $body = ob_get_contents();
    ob_end_clean();
    
    json_response(200, null, $body);
}

/*********************************************************
 * FUNCTION: RETURN JSON DATA FOR REVIEW RISKS DATATABLE *
 *********************************************************/
function getReviewsWithDateIssuesDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has risk management permissions
    if (check_permission_riskmanagement())
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
        if (check_permission_riskmanagement())
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

    global $lang, $escaper;

    // If the id is not sent
    if (!isset($_GET['type']) && $_GET['type'] !== 'risk' && $_GET['type'] !== 'asset')
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['YouNeedToSpecifyATypeParameter']));

        // Return a JSON response
        json_response(400, get_alert(true), NULL);
    }
    else
    {
        $type = $_GET['type'];

        if ($type === 'risk' && !check_permission_riskmanagement()) {
            set_alert(true, "bad", $lang['NoPermissionForRiskManagement']);
            json_response(400, get_alert(true), NULL);
            return;
        } elseif($type === 'asset' && !check_permission_asset()) {
            set_alert(true, "bad", $lang['NoPermissionForAsset']);
            json_response(400, get_alert(true), NULL);
            return;
        } else {
            $options = [];
            foreach(getTagsOfType($type) as $tag) {
                $options[] = array('label' => $tag);
            }

            json_response(200, null, $options);
        }
    }
}

/***********************************
 * FUNCTION: UPDATE RISK LEVEL API *
 ***********************************/
function update_risk_level_API() {

    global $lang, $escaper;

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
            $risk_levels[$level][$field] = $value;
        }
    } elseif(strlen($value) > 20) {
        set_alert(true, "bad", $lang['RiskLevelTooLongValueParameter']);

        // Return a JSON response
        json_response(400, get_alert(true), $originalValue);
        return;
    }

    // Check if low < medium < high < very high
    if (($risk_levels[0]['value'] < $risk_levels[1]['value']) && ($risk_levels[1]['value'] < $risk_levels[2]) && ($risk_levels[2]['value'] < $risk_levels[3]['value']))
    {
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
    // Otherwise, there was a problem with the order
    else
    {
        set_alert(true, "bad", $lang['RiskLevelInvalidValueOrder']);
        json_response(400, get_alert(true), $originalValue);
        return;
    }
}

/*****************************
 * FUNCTION: LIST EXCEPTIONS *
 *****************************/
function get_exceptions_as_treegrid_api()
{
    global $lang;

    if (!check_permission_governance()) {
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
    echo json_encode($result);
    exit;
}

/********************************
 * FUNCTION: GET EXCEPTION DATA *
 ********************************/
function get_exception_api()
{
    global $lang;

    if (!check_permission_governance()) {
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

    if (!check_permission_governance()) {
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
    $exception['owner'] = $escaper->escapeHtml($exception['owner']);
    $exception['additional_stakeholders'] = get_stakeholder_names($exception['additional_stakeholders']);
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

    foreach($exception as $key => $value) {
        if (strlen($value) == 0)
            $exception[$key] = "--";
    }

    json_response(200, null, $exception);
}

function create_exception_api() {

    global $lang;

    if (!check_permission_governance()) {
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
    $owner = (int)$_POST['owner'];
    $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
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
    $approver = (ctype_digit($_POST['approver']) && get_user_by_id((int)$_POST['owner'])) ? (int)$_POST['approver'] : false;
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
        $id = create_exception($name, $policy, $control, $owner, $additional_stakeholders, $creation_date, $review_frequency, $next_review_date, $approval_date, $approver, $approved, $description, $justification);
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

    if (!check_permission_governance()) {
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
    $owner = (int)$_POST['owner'];
    $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
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
    $approver = (ctype_digit($_POST['approver']) && get_user_by_id((int)$_POST['owner'])) ? (int)$_POST['approver'] : false;
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
        update_exception(
            $name,
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
            $id);

        set_alert(true, "good", $lang['ExceptionWasUpdatedSuccessfully']);

        //returning the created exception's type
        //the returned data is needed to know what tabs to refresh
        json_response(200, get_alert(true), array('approved' => $approved_original, 'type' => $policy ? "policy" : "control"));
        return;
    } catch(Exception $e) {
        error_log($e);
        set_alert(true, "bad", $lang['ThereWasAProblemUpdatingTheException']);
        json_response(400, get_alert(true), NULL);
        return;
    }
}

function approve_exception_api() {

    global $lang;

    if (!check_permission_governance()) {
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

    if (!check_permission_governance()) {
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

    if (!check_permission_governance()) {
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

    global $lang;

    if (!check_permission_governance()) {
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

    json_response(200, null, array_map(function($log) {
            return array(
                'timestamp' => date(get_default_datetime_format("g:i A T"), strtotime($log['timestamp'])),
                'message' => try_decrypt($log['message'])
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
            $log_type = array_map(function($log_type){
                return trim($log_type);
            }, explode(",", $log_type));
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

    if (check_permission_asset())
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

    if (!check_permission_asset()) {
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
        echo json_encode(get_asset_groups_for_treegrid($offset, $rows));
    } else { // The children
        echo json_encode(get_assets_of_asset_group_for_treegrid($id));
    }
}

function asset_group_info()
{
    global $lang, $escaper;

    if (!check_permission_asset()) {
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
        $group['name'] = $escaper->escapeHtml($group['name']);
        
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

    if (!check_permission_asset()) {
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

    if (!check_permission_asset()) {
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

    if (!check_permission_asset()) {
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

    if (!check_permission_asset()) {
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

    global $lang;

    if (check_permission_asset() || check_permission_assessments() || check_permission_riskmanagement())
    {
        $risk_id = isset($_GET['risk_id']) && ctype_digit($_GET['risk_id']) ? (int)$_GET['risk_id'] : false;
        json_response(200, null, get_assets_and_asset_groups_for_dropdown($risk_id));
    }
    else
    {
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



/****************************************************************
 * FUNCTION: ADVANCED SEARCH                                    *
 * Params(GET)                                                  *
 *  'q': The query string                                       *
 *  'highlight': Whether the query string                       *
 *  should be highlighted in the results(0 or 1, default is 1)  *
 ****************************************************************/
function advanced_search_api(){

    global $lang;

    if (is_dir(realpath(__DIR__ . '/../extras/advanced_search')) && advanced_search_extra()) {
        
        // Include the Import-Export Extra
        require_once(realpath(__DIR__ . '/../extras/advanced_search/index.php'));

        $highlight = !isset($_GET['highlight']) || (isset($_GET['highlight']) && boolval($_GET['highlight']));
        $teaser_size = isset($_GET['teaser_size']) && $_GET['teaser_size'] ? (int)$_GET['teaser_size'] : 100;
        $escape_type = isset($_GET['escape_type']) && $_GET['escape_type'] && in_array($_GET['escape_type'], ['html', 'js']) ? $_GET['escape_type'] : 'html';
        
        return json_response(200, null, do_advanced_search($_GET['q'], $teaser_size, $highlight, $escape_type));
    }

    set_alert(true, "bad", $lang['ActivateTheAdvancedSearchExtra']);
    return json_response(400, get_alert(true), array("manager" => $user["manager"]));

}

?>
