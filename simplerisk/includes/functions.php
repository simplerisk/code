<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/cvss.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/extras.php'));
require_once(realpath(__DIR__ . '/authenticate.php'));
require_once(realpath(__DIR__ . '/healthcheck.php'));

// Include the language file
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Set the simplerisk timezone for any datetime functions
set_simplerisk_timezone();


/*
    A list of tables where the `name` field is encrypted AND used in
    functions that are getting name(s) by value(s). When querying the names of
    these tables the results should be ran through the 'try_decrypt()' function.
*/
$tables_where_name_is_encrypted = array('frameworks', 'projects', 'assets');

/* The regex pattern for template variables*/
$variable_regex_pattern = '/<span class="variable" data-id="([^"]+)">.+?<\/span>/';

/* The list of available extras. */
$available_extras = array(
    'advanced_search',
    'api',
    'assessments',
    'complianceforgescf',
    'authentication',
    'customization',
    'encryption',
    'import-export',
    'incident_management',
    'jira',
    'notification',
    'organizational_hierarchy',
    'separation',
    'ucf',
    'upgrade',
    'vulnmgmt',
);


/* A list of types that have teams assigned and are managed through functions updateTeamsOfItem, getTeamsOfItem, hasTeams */
$available_item_types = array('test', 'audit');

/*
This is the configuration for the function cleanup_after_delete()

Structure of the table configuration through an example:

    'location' => array( // Name of the table
        'id_field' => 'value', // id field name of the `location` table
        'junctions' => array( // List of junction tables for the `location` table
            'risk_to_location' => 'location_id' // Name of the junction table => id field's name that's containing the id values for the `location` table
        )
    ),
    // Added part of another table's configuration to show how the junction table should be added to both side's configuration
    'risks' => array(
        'id_field' => 'id',
        'tag_type' => 'risk', // the tag type as found in the $tag_types array. You can skip it if the table has no tags assigned
        'junctions' => array(
            'risk_to_location' => 'risk_id',
            // ...
        )
    ), 

When adding a new table please make sure to add all the related junction tables
you want to clear the associations from, when an entry is deleted from the table.
*/
$junction_config = array(
    'team' => array(
        'id_field' => 'value',
        'junctions' => array(
            'user_to_team' => 'team_id',
            'mitigation_to_team' => 'team_id',
            'risk_to_team' => 'team_id',
            'items_to_teams' => 'team_id',
            'business_unit_to_team' => 'team_id',
            'remote_team_mapping' => 'local_team_id',
        )
    ),
    'user' => array(
        'id_field' => 'value',
        'junctions' => array(
            'user_to_team' => 'user_id',
            'risk_to_additional_stakeholder' => 'user_id',
            'permission_to_user' => 'user_id' // There's no permission counterpart to this, as permissions can't be deleted 
        )
    ),
    'risks' => array(
        'id_field' => 'id',
        'tag_type' => 'risk',
        'junctions' => array(
            'risk_to_additional_stakeholder' => 'risk_id',
            'risk_to_location' => 'risk_id',
            'risk_to_team' => 'risk_id',
            'risk_to_technology' => 'risk_id',
            'risks_to_asset_groups' => 'risk_id',
            'risks_to_assets' => 'risk_id'
        )
    ),    
    'location' => array(
        'id_field' => 'value',
        'junctions' => array(
            'risk_to_location' => 'location_id'
        )
    ),     
    'technology' => array(
        'id_field' => 'value',
        'junctions' => array(
            'risk_to_technology' => 'technology_id'
        )
    ),
    'assets' => array(
        'id_field' => 'id',
        'tag_type' => 'asset',
        'junctions' => array(
            'risks_to_assets' => 'asset_id',
            'questionnaire_answers_to_assets' => 'asset_id',
            'assessment_answers_to_assets' => 'asset_id',
            'assets_asset_groups' => 'asset_id'
        )
    ),
    'asset_groups' => array(
        'id_field' => 'id',
        'junctions' => array(
            'risks_to_asset_groups' => 'asset_group_id',
            'questionnaire_answers_to_asset_groups' => 'asset_group_id',
            'assessment_answers_to_asset_groups' => 'asset_group_id',
            'assets_asset_groups' => 'asset_group_id'
        )
    ),
    'mitigations' => array(
        'id_field' => 'id',
        'junctions' => array(
            'mitigation_to_controls' => 'mitigation_id',
            'mitigation_to_team' => 'mitigation_id'
        )
    ),
    'assessment_answers' => array(
        'id_field' => 'id',
        'junctions' => array(
            'assessment_answers_to_assets' => 'assessment_answer_id',
            'assessment_answers_to_asset_groups' => 'assessment_answer_id'
        )
    ),
    'questionnaire_answers' => array(
        'id_field' => 'id',
        'tag_type' => 'questionnaire_answer',
        'junctions' => array(
            'questionnaire_answers_to_assets' => 'questionnaire_answer_id',
            'questionnaire_answers_to_asset_groups' => 'questionnaire_answer_id'
        )
    ),
    'framework_controls' => array(
        'id_field' => 'id',
        'junctions' => array(
            'framework_control_mappings' => 'control_id',
            'questionnaire_question_to_control' => 'control_id',
            'mitigation_to_controls' => 'control_id'
        )
    ),
    'questionnaire_questions' => array(
        'id_field' => 'id',
        'junctions' => array(
            'questionnaire_question_to_control' => 'question_id'
        )
    ),
    'frameworks' => array(
        'id_field' => 'value',
        'junctions' => array(
            'framework_control_mappings' => 'framework'
        )
    ),
    'business_unit' => array(
        'id_field' => 'id',
        'junctions' => array(
            'business_unit_to_team' => 'business_unit_id'
        )
    ),
    'role' => array(
        'id_field' => 'value',
        'junctions' => array(
            'role_responsibilities' => 'role_id',
            'remote_role_mapping' => 'local_role_id',
        )
    ),
    'permissions' => array(
        'id_field' => 'id',
        'junctions' => array(
            'role_responsibilities' => 'permission_id',
            'permission_to_user' => 'permission_id',
            'permission_to_permission_group' => 'permission_id'
        )
    ),
    'permission_groups' => array(
        'id_field' => 'id',
        'junctions' => array(
            'permission_to_permission_group' => 'permission_group_id'
        )
    ),
    'remote_team' => array(
        'id_field' => 'value',
        'junctions' => array(
            'remote_team_mapping' => 'remote_team_id'
        )
    ),
    'remote_role' => array(
        'id_field' => 'value',
        'junctions' => array(
            'remote_role_mapping' => 'remote_role_id'
        )
    ),
);

// Add new supported tag types here
// After adding a new supported type here, you also have to edit the getTagOptionsOfType and getTagOptionsOfTypes functions in the api.php
// as those have per type access permission checks.
// Also have to:
//      -add localization for tag types(example: 'TagType_risk' => 'Risk')
//      -add it to the $junction_config 
$tag_types = ['risk', 'asset', 'questionnaire_risk', 'questionnaire_answer', 'questionnaire_pending_risk', 'incident_management_source', 'incident_management_destination'];

/******************************
 * FUNCTION: DATABASE CONNECT *
 ******************************/
function db_open()
{
    if(isset($GLOBALS['db_global']) && $GLOBALS['db_global']){
        return $GLOBALS['db_global'];
    }
    // Connect to the database
    try {

        // Set the simplerisk timezone for any datetime functions
        $now = new DateTime();
        $mins = $now->getOffset() / 60;
        $sgn = ($mins < 0 ? -1 : 1);
        $mins = abs($mins);
        $hrs = floor($mins / 60);
        $mins -= $hrs * 60;
        $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

        // Set the default options array
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8, @@group_concat_max_len = 4294967295, time_zone='{$offset}'"
        );

        // If a database SSL certificate path has been defined
        if (defined("DB_SSL_CERTIFICATE_PATH") && DB_SSL_CERTIFICATE_PATH != '') {
            // Add the SSL certificate to the options array
            $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CERTIFICATE_PATH;
        }

        // Create the PDO object
        $GLOBALS['db_global'] = new PDO("mysql:charset=UTF8;dbname=".DB_DATABASE.";host=".DB_HOSTNAME.";port=".DB_PORT,DB_USERNAME,DB_PASSWORD, $options);

        // Get the value set for the timezone in the database
        $stmt = $GLOBALS['db_global']->prepare("SELECT `value` FROM `settings` WHERE `name` = 'default_timezone';");
        $stmt->execute();

        // Store the list in the array
        $default_timezone = $stmt->fetch(PDO::FETCH_COLUMN);

        // If no timezone is set, set it to CST
        if (!$default_timezone) $default_timezone = "America/Chicago";

        // Set the timezone for PHP date functions
        date_default_timezone_set($default_timezone);

    	// FOR DEBUGGING DATABASE CONNECTIONS ONLY
    	/*
    	$log_file = "/tmp/debug_log";
    	$connection = new PDO("mysql:charset=UTF8;dbname=".DB_DATABASE.";host=".DB_HOSTNAME.";port=".DB_PORT,DB_USERNAME,DB_PASSWORD, $options);
    	$stmt = $connection->prepare("SHOW VARIABLES LIKE 'max_connections';");
    	$stmt->execute();
    	$results = $stmt->fetch();
    	$max_connections = $results['Value'];
    	$stmt = $connection->prepare("SHOW STATUS WHERE `variable_name` = 'Threads_connected';");
    	$stmt->execute();
    	$results = $stmt->fetch();
    	$current_connections = $results['Value'];
    	error_log(date('c')." Database Connections: ".$current_connections . " / " . $max_connections."\n", 3, $log_file);
    	$connection = null;
    	*/

        return $GLOBALS['db_global'];
    }
    catch (PDOException $e)
    {
        //die("Database Connection Failed: " . $e->getMessage());

        // We were unable to connect to the SimpleRisk database
        require_once(realpath(__DIR__ . '/healthcheck.php'));
        unable_to_communicate_with_database();
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
        // $GLOBALS['db_global'] = null;
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

    // If this is the team table
    if (in_array($name, ["team", "dynamic_saved_selections", "assets", "asset_groups"])){
        // Order by name
        $stmt = $db->prepare("SELECT * FROM `{$name}` ORDER BY name");
    }
    elseif ($name == "framework_controls"){
        $stmt = $db->prepare("SELECT *, short_name name FROM `{$name}` ORDER BY name");
    }
    // If this is ldap_group_and_teams table
    elseif ($name == "ldap_group_and_teams")
    {
        $stmt = $db->prepare("SELECT t1.*, t2.name as team_name FROM `{$name}` t1 LEFT JOIN `team` t2 ON t1.team_id=t2.value ORDER BY t1.value");
    }    
    // Otherwise, order by value
    else 
    {
        $stmt = $db->prepare("SELECT * FROM `{$name}` ORDER BY value");
    }

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if($name == "assets")
    {
        foreach($array as &$row)
        {
            $row['ip'] = try_decrypt($row['ip']);
            $row['name'] = try_decrypt($row['name']);
            $row['details'] = try_decrypt($row['details']);
        }
    }

    if($name == "frameworks")
    {
        foreach($array as &$row)
        {
            $row['name'] = try_decrypt($row['name']);
            $row['description'] = try_decrypt($row['description']);
        }
    }

    // Close the database connection
    db_close($db);

    return $array;
}

/****************************************************************
 * FUNCTION: GET UNIQUE VALUES FROM TEXT ARRAY                  *
 * $all_values: array to have "name" and ("value" or "id") key
 ****************************************************************/
function get_unique_values_from_text_array($all_values, $text_array)
{
    $results = [""];
    foreach($all_values as $all_value){
        $value = isset($all_value['value']) ? $all_value['value'] : $all_value['id'];
        $name = $all_value['name'];
        foreach($text_array as $text)
        {
            if(stripos($text, $name) !== false){
                $results[] = ["value" => $value, "name" => $name];
                break;
            }
        }
    }
    
    return $results;
}

/**************************************************************************
 * FUNCTION: GET UNIQUE TEXTS FROM TEXT ARRAY                  *
 * Input : Name1{$delimiter2}Value1{$delimiter1} Name2_Value2{$delimiter1} 
 * Output: 
 *      [
 *          ["text"=>Name1, "value"=>Value1],
 *          ["text"=>Name2, "value"=>Value2],
 *          ...
 *      ]
 **************************************************************************/
function get_name_value_array_from_text_array($text_array, $delimiter1=",", $delimiter2="---", $decrypt=false, $sort_by_key=false)
{
    global $escaper, $lang;
    
    $unique_name_values = [];
    foreach($text_array as $text)
    {
        $unique_name_values = array_merge($unique_name_values, explode($delimiter1, $text));
    }
    
    $unique_name_values = array_unique(array_map("trim", $unique_name_values));
    $results = [];
    $texts = [];
    foreach($unique_name_values as $name_val)
    {
        $name_val = trim($name_val);
        
        $arr = explode($delimiter2, $name_val);
        if(!empty($arr[1]))
        {
            if($decrypt)
                $text = $escaper->escapeHtml(try_decrypt($arr[0]));
            else
                $text = $escaper->escapeHtml($arr[0]);
            $results[$arr[1]] = [
                "value" => base64_encode($arr[1]),
                "text" => $text,
            ];
            $texts[] = $text;
        }
        elseif(!empty($arr[0])){
            if($decrypt)
                $text = $escaper->escapeHtml(try_decrypt($arr[0]));
            else
                $text = $escaper->escapeHtml($arr[0]);
            $results[$arr[0]] = [
                "value" => base64_encode($arr[0]),
                "text" => $text,
            ];
            $texts[] = $text;
        }
    }
    if($sort_by_key) ksort($results);
    else array_multisort($texts, SORT_ASC, $results);

    return $results;
}

/*************************************
 * FUNCTION: SAVE DYNAMIC SELECTIONS *
 *************************************/
function save_dynamic_selections($type, $name, $custom_display_settings,$custom_selection_settings,$custom_column_filters)
{
    global $escaper, $lang;

    $custom_display_settings = json_encode($custom_display_settings);
    $custom_selection_settings = json_encode($custom_selection_settings);
    $custom_column_filters = json_encode($custom_column_filters);
    
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("INSERT INTO `dynamic_saved_selections` (`user_id`,`type`,`name`, `custom_display_settings`, `custom_selection_settings`, `custom_column_filters`) VALUES (:user_id, :type, :name, :custom_display_settings, :custom_selection_settings, :custom_column_filters); ");
    
    $stmt->bindParam(":user_id", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":custom_display_settings", $custom_display_settings, PDO::PARAM_STR);
    $stmt->bindParam(":custom_selection_settings", $custom_selection_settings, PDO::PARAM_STR);
    $stmt->bindParam(":custom_column_filters", $custom_column_filters, PDO::PARAM_STR);
    $stmt->execute();

    $id = $db->lastInsertId();

    // Close the database connection
    db_close($db);

    $message = "The selections for Dynamic Risk Report named \"" . $escaper->escapeHtml($name) . "\" was created by the \"" . $_SESSION['user'] . "\" user.";
    write_log(1000, $_SESSION['uid'], $message);

    return $id;
}

/********************************************
 * FUNCTION: DELETE DYNAMIC SELECTION BY ID *
 ********************************************/
function delete_dynamic_selection($id)
{
    global $escaper, $lang;

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DELETE FROM `dynamic_saved_selections` WHERE value=:value; ");
    
    $stmt->bindParam(":value", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    $message = "The selections for Dynamic Risk Report (ID : {$id}) was deleted by the \"" . $_SESSION['user'] . "\" user.";
    write_log(1000, $_SESSION['uid'], $message);
}

/***************************************************
 * FUNCTION: CHECK EXISTING DYNAMIC SELECTION NAME *
 ***************************************************/
function check_exisiting_dynamic_selection_name($user_id, $name)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `dynamic_saved_selections` WHERE name=:name AND (type='private' AND user_id=:user_id || type='public');");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    return $array ? true: false;
}

/****************************
 * FUNCTION: GET FULL TABLE *
 ****************************/
function get_full_table($name)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `{$name}`");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/***************************************
 * FUNCTION: TEAMS THE LOGIN USER IS A MEMBER OF*
 ***************************************/
function get_teams_by_login_user(){
    // Open the database connection
    $db = db_open();

    // Order by name
    if (!team_separation_extra()){
        $stmt = $db->prepare("SELECT * FROM `team` ORDER BY name");
    }else{
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the teams the user is assigned to
        $user_teams = implode(",", get_user_teams($_SESSION['uid']));

        $stmt = $db->prepare("
            SELECT
                *
            FROM
                `team`
            WHERE
                FIND_IN_SET(`value`, '{$user_teams}')
            ORDER BY
                `name`;
        ");
    }

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $stmt = $db->prepare("SELECT * FROM `{$table_name}` ORDER BY name");

    // Execute the database query
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Try decrypt if encrypted fields
    if ($table_name == "frameworks" || $table_name == "parent_frameworks" || $table_name == "projects")
    {
        // For each option
        foreach ($array as &$option)
        {
            // Try to decrypt it
            $option['name'] = try_decrypt($option['name']);
        }
        usort($array, function($a, $b){
            return strcmp( strtolower(trim($a['name'])), strtolower(trim($b['name'])));
        });
    }
    
    return $array;
}

/******************************
 * FUNCTION: GET CUSTOM TABLE *
 * 
 * Some info on the user management for some of the options.
 * "user": It will return all users, ignoring the organizational hierarchy extra for admin users, use the selected business unit for non-admin users 
 * "enabled/disabled_users": Will return the enabled/disabled users of the selected business unit(EVEN FOR ADMINS). Use it outside of admin-only area
 * "enabled/disabled_users_all": Will return the enabled/disabled users, ignoring the selected business unit. Use it ONLY inside of admin-only area
 * 
 ******************************/
function get_custom_table($type)
{
    // Open the database connection
    $db = db_open();

    // to notify that the result is supposed to be fetched as grouped
    $grouped = false;

    // Array of CVSS values
    $allowed_cvss_values = array('AccessComplexity', 'AccessVector', 'Authentication', 'AvailabilityRequirement', 'AvailImpact', 'CollateralDamagePotential', 'ConfidentialityRequirement', 'ConfImpact', 'Exploitability', 'IntegImpact', 'IntegrityRequirement', 'RemediationLevel', 'ReportConfidence', 'TargetDistribution');

    // If we want users
    if ($type == "user") {

        if (!is_admin() && organizational_hierarchy_extra()) {
            $stmt = $db->prepare("
                SELECT
                    `u`.*
                FROM
                    `user` u
                    INNER JOIN `user_to_team` u2t ON `u2t`.`user_id` = `u`.`value`
                    INNER JOIN `business_unit_to_team` bu2t ON `u2t`.`team_id` = `bu2t`.`team_id`
                WHERE
                    `bu2t`.`business_unit_id` = :selected_business_unit
                GROUP BY
                    `u`.`value`
                ORDER BY
                    `u`.`name`;
            ");
            
            if (!isset($_SESSION['selected_business_unit'])) {
                require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
                $selected_business_unit = get_selected_business_unit($_SESSION['uid']);
            } else {
                $selected_business_unit = $_SESSION['selected_business_unit'];
            }
            
            $stmt->bindParam(":selected_business_unit", $selected_business_unit, PDO::PARAM_INT);
            
        } else {
            $stmt = $db->prepare("SELECT * FROM `user` ORDER BY `name`;");
        }
    }
    // If we want enabled/disabled users or want the enabled users without caring for business units
    else if (in_array($type, ["enabled_users", "disabled_users", "enabled_users_all", "disabled_users_all"])) { // $type == "enabled_users" || $type == "disabled_users" || $type == "enabled_users_all") {
        if (in_array($type, ["enabled_users", "disabled_users"]) && organizational_hierarchy_extra()) {
            $stmt = $db->prepare("
                SELECT
                    `u`.*, GROUP_CONCAT(DISTINCT `t`.`value`) as teams
                FROM
                    `user` u
                    INNER JOIN `user_to_team` u2t_bu ON `u2t_bu`.`user_id` = `u`.`value`
                    INNER JOIN `business_unit_to_team` bu2t ON `u2t_bu`.`team_id` = `bu2t`.`team_id`
                    LEFT JOIN `user_to_team` u2t ON `u2t`.`user_id` = `u`.`value`
                    LEFT JOIN `team` t ON `u2t`.`team_id` = `t`.`value`
                WHERE
                    `bu2t`.`business_unit_id` = :selected_business_unit
                    AND `u`.`enabled` = :enabled
                GROUP BY
                    `u`.`value`
                ORDER BY
                    `u`.`name`;
            ");

            if (!isset($_SESSION['selected_business_unit'])) {
                require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
                $selected_business_unit = get_selected_business_unit($_SESSION['uid']);
            } else {
                $selected_business_unit = $_SESSION['selected_business_unit'];
            }

            $stmt->bindParam(":selected_business_unit", $selected_business_unit, PDO::PARAM_INT);

        } else {
            $stmt = $db->prepare("
                SELECT
                    `u`.*, GROUP_CONCAT(DISTINCT `t`.`value`) as teams
                FROM
                    `user` u
                    LEFT JOIN `user_to_team` u2t ON `u2t`.`user_id` = `u`.`value`
                    LEFT JOIN `team` t ON `u2t`.`team_id` = `t`.`value`
                WHERE
                    `u`.`enabled` = :enabled
                GROUP BY 
                    `u`.`value`
                ORDER BY
                    `u`.`name`;
            ");
        }
        $enabled = in_array($type, ["enabled_users", "enabled_users_all"]) ? 1 : 0;
        $stmt->bindParam(":enabled", $enabled, PDO::PARAM_INT);
    }
    // If we want a languages table
    else if ($type == "languages")
    {
        $stmt = $db->prepare("SELECT value, full as name FROM languages ORDER BY name");
    }
    // If we want a CVSS scoring table
    else if (in_array($type, $allowed_cvss_values))
    {
        $stmt = $db->prepare("SELECT * FROM CVSS_scoring WHERE metric_name = :type ORDER BY id");
        $stmt->bindParam(":type", $type, PDO::PARAM_STR, 30);
    }
    // If we want a family table
    else if ($type == "family")
    {
        $stmt = $db->prepare("SELECT value, name as name FROM family ORDER BY name");
    }
    // If we want a frameworks table
    else if ($type == "frameworks")
    {
        $stmt = $db->prepare("SELECT value, name FROM frameworks WHERE status=1 ORDER BY `order`");
    }
    // If we want a date_formats table
    else if ($type == "date_formats")
    {
        $stmt = $db->prepare("SELECT value, value as name FROM date_formats;");
    }
    // If we want a parent frameworks from frameworks table
    else if ($type == "parent_frameworks")
    {
        $stmt = $db->prepare("SELECT value, name FROM frameworks WHERE parent=0 ORDER BY name");
    }
    // If we want the framework controls
    else if ($type == "framework_controls")
    {
        $stmt = $db->prepare("SELECT `id` as value, `short_name` as name FROM `framework_controls` WHERE `deleted`=0 ORDER BY `short_name`;");
    }
    // If we want the framework controls
    else if ($type == "framework_control_tests")
    {
        $stmt = $db->prepare("SELECT `id` as value, `name` FROM `framework_control_tests` ORDER BY `name`;");
    }
    // If we want the tags used on risks
    else if ($type == "risk_tags")
    {
        $stmt = $db->prepare("
            SELECT
                `t`.`id` as value, `t`.`tag` as name
            FROM
                `tags` `t`
                INNER JOIN `tags_taggees` `tt` ON `t`.`id`=`tt`.`tag_id`
            WHERE
                `tt`.`type`='risk'
            GROUP BY `t`.`tag`
            ORDER BY `t`.`tag`;
        ");
    }
    // If we want the tags used on assets
    else if ($type == "asset_tags")
    {
        $stmt = $db->prepare("
            SELECT
                `t`.`id` as value, `t`.`tag` as name
            FROM
                `tags` `t`
                INNER JOIN `tags_taggees` `tt` ON `t`.`id`=`tt`.`tag_id`
            WHERE
                `tt`.`type`='asset'
            GROUP BY `t`.`tag`
            ORDER BY `t`.`tag`;
        ");
    }
    // If we want the test results(used for setting the test result)
    else if ($type == "test_results")
    {
        $stmt = $db->prepare("SELECT name as value, name FROM test_results ORDER BY name");
    }
    // If we want the test results(used for filtering on the test result)
    else if ($type == "test_results_filter")
    {
        $stmt = $db->prepare("SELECT value, name FROM test_results ORDER BY name");
    }
    else if ($type == "policies")
    {
        $stmt = $db->prepare("SELECT id as value, document_name as name FROM documents where document_type = 'policies' ORDER BY document_name");
    } elseif ($type == "team") {
        if (!is_admin() && organizational_hierarchy_extra()) {
            // If the Organizational Hierarchy is activated the function only returns the teams the
            // user's selected business unit allows. Unless it's an admin user as admins can see everything.
            $stmt = $db->prepare("
                SELECT
                    `t`.*
                FROM
                    `business_unit_to_team` bu2t
                    INNER JOIN `team` t ON `t`.`value` = `bu2t`.`team_id`
                    INNER JOIN `user` u ON `u`.`selected_business_unit` = `bu2t`.`business_unit_id`
                WHERE
                    `u`.`value` = :user_id
                ORDER BY
                    `t`.`name`;
            ");
            $uid = (int)$_SESSION['uid'];
            $stmt->bindParam(":user_id", $uid, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare("SELECT * FROM `team` ORDER BY name");
        }
    }
    else if ($type == "risk_catalog")
    {
        $stmt = $db->prepare("SELECT id as value, CONCAT(`number`, ' - ', `name`) AS name FROM `risk_catalog` ORDER BY `grouping`,`order`;");
    }
    else if ($type == "threat_catalog")
    {
        $stmt = $db->prepare("SELECT id as value, CONCAT (`number`, ' - ', `name`) AS name FROM `threat_catalog` ORDER BY `grouping`, `order`;");
    }
    else if (in_array($type, ["remote_team-SAML", "remote_role-SAML", "remote_team-LDAP"]) && custom_authentication_extra()) {
        list($table, $remote_type) = explode('-', $type);
        $stmt = $db->prepare("
            SELECT
                *
            FROM
                `{$table}`
            WHERE
                `type` = '{$remote_type}'
            ORDER BY
                `name`;
        ");
    }
    else if ($type == "data_classification")
    {
        $stmt = $db->prepare("SELECT id as value, name FROM `data_classification` ORDER BY `order`");
    } else if (in_array($type, ['risk_catalog_grouped', 'threat_catalog_grouped'])) {
        $catalog_type = $type === 'risk_catalog_grouped' ? 'risk' : 'threat';
        $grouped = true;
        $stmt = $db->prepare("
            SELECT
            	`g`.`name`,
                `c`.`id` AS value,
                CONCAT(`c`.`number`, ' - ', `c`.`name`) AS name
            FROM
                `{$catalog_type}_catalog` c
                LEFT JOIN `{$catalog_type}_grouping` g ON `c`.`grouping` = `g`.`value`
            ORDER BY
                `g`.`order`,
                `c`.`order`;
        ");
    }

    // Execute the database query
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll($grouped ? PDO::FETCH_GROUP|PDO::FETCH_ASSOC : PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Try decrypt if encrypted fields
    if ($type == "frameworks" || $type == "parent_frameworks" || $type == "projects")
    {
        // For each option
        foreach ($array as &$option)
        {
            // Try to decrypt it
            $option['name'] = try_decrypt($option['name']);
        }
        usort($array, function($a, $b){
            return strcmp( strtolower(trim($a['name'])), strtolower(trim($b['name'])));
        });
    }

    // Localize test results names
    if ($type == "test_results" || $type == "test_results_filter")
    {
        global $lang;
        // For each option
        foreach ($array as &$option)
        {
            // Try to localize it
            $option['name'] = $lang[$option['name']];
        }
    }

    return $array;
}

/************************************
 * FUNCTION: GET OPTIONS FROM TABLE *
 * Some info on the user management for some of the options.
 * "user": It will return all users, ignoring the organizational hierarchy extra for admin users, use the selected business unit for non-admin users 
 * "enabled/disabled_users": Will return the enabled/disabled users of the selected business unit(EVEN FOR ADMINS). Use it outside of admin-only area
 * "enabled/disabled_users_all": Will return the enabled/disabled users, ignoring the selected business unit. Use it ONLY inside of admin-only area
 ************************************/
function get_options_from_table($name)
{
    global $lang, $escaper;
    
    // If we want a table that should be ordered by name instead of value
    if (in_array($name, array("category", "technology",
        "location", "regulation", "projects", "file_types", "file_type_extensions",
        "planning_strategy", "close_reason", "status", "source", "import_export_mappings", "test_status"))) {

        $options = get_table_ordered_by_name($name);
    }
    else if (in_array($name, array("user", "team", "enabled_users", "disabled_users", "enabled_users_all", "disabled_users_all", "languages", "family", "date_formats",
            "parent_frameworks", "frameworks", "framework_controls", "risk_tags", "asset_tags", "test_results", "test_results_filter",
            "policies", "framework_control_tests", "risk_catalog", "threat_catalog", "risk_catalog_grouped", "threat_catalog_grouped", "remote_team-SAML", "remote_role-SAML", "remote_team-LDAP", "data_classification"))) {
        $options = get_custom_table($name);
    }
    // Otherwise
    else
    {
        // Get the list of options
        $options = get_table($name);
    }

    // Sort options array
    if($name == "parent_frameworks" || $name == "projects"){
        uasort($options, function($a, $b){
            if($a['name'] == $b['name']) return 0;
            return ($a['name'] < $b['name']) ? -1 : 1;
        });
    }

    return $options;
}


/*****************************
 * FUNCTION: GET RISK LEVELS *
 *****************************/
function get_risk_levels()
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT * FROM `risk_levels` ORDER BY value");
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
    $stmt = $db->prepare("SELECT * FROM review_levels GROUP BY id ORDER BY value");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/****************************************
 * FUNCTION: CONVERT COLOR NAME TO CODE *
 ****************************************/
function convert_color_code($color_name)
{
    // standard 147 HTML color names
    $colors  =  array(
        'aliceblue'=>'F0F8FF',
        'antiquewhite'=>'FAEBD7',
        'aqua'=>'00FFFF',
        'aquamarine'=>'7FFFD4',
        'azure'=>'F0FFFF',
        'beige'=>'F5F5DC',
        'bisque'=>'FFE4C4',
        'black'=>'000000',
        'blanchedalmond '=>'FFEBCD',
        'blue'=>'0000FF',
        'blueviolet'=>'8A2BE2',
        'brown'=>'A52A2A',
        'burlywood'=>'DEB887',
        'cadetblue'=>'5F9EA0',
        'chartreuse'=>'7FFF00',
        'chocolate'=>'D2691E',
        'coral'=>'FF7F50',
        'cornflowerblue'=>'6495ED',
        'cornsilk'=>'FFF8DC',
        'crimson'=>'DC143C',
        'cyan'=>'00FFFF',
        'darkblue'=>'00008B',
        'darkcyan'=>'008B8B',
        'darkgoldenrod'=>'B8860B',
        'darkgray'=>'A9A9A9',
        'darkgreen'=>'006400',
        'darkgrey'=>'A9A9A9',
        'darkkhaki'=>'BDB76B',
        'darkmagenta'=>'8B008B',
        'darkolivegreen'=>'556B2F',
        'darkorange'=>'FF8C00',
        'darkorchid'=>'9932CC',
        'darkred'=>'8B0000',
        'darksalmon'=>'E9967A',
        'darkseagreen'=>'8FBC8F',
        'darkslateblue'=>'483D8B',
        'darkslategray'=>'2F4F4F',
        'darkslategrey'=>'2F4F4F',
        'darkturquoise'=>'00CED1',
        'darkviolet'=>'9400D3',
        'deeppink'=>'FF1493',
        'deepskyblue'=>'00BFFF',
        'dimgray'=>'696969',
        'dimgrey'=>'696969',
        'dodgerblue'=>'1E90FF',
        'firebrick'=>'B22222',
        'floralwhite'=>'FFFAF0',
        'forestgreen'=>'228B22',
        'fuchsia'=>'FF00FF',
        'gainsboro'=>'DCDCDC',
        'ghostwhite'=>'F8F8FF',
        'gold'=>'FFD700',
        'goldenrod'=>'DAA520',
        'gray'=>'808080',
        'green'=>'008000',
        'greenyellow'=>'ADFF2F',
        'grey'=>'808080',
        'honeydew'=>'F0FFF0',
        'hotpink'=>'FF69B4',
        'indianred'=>'CD5C5C',
        'indigo'=>'4B0082',
        'ivory'=>'FFFFF0',
        'khaki'=>'F0E68C',
        'lavender'=>'E6E6FA',
        'lavenderblush'=>'FFF0F5',
        'lawngreen'=>'7CFC00',
        'lemonchiffon'=>'FFFACD',
        'lightblue'=>'ADD8E6',
        'lightcoral'=>'F08080',
        'lightcyan'=>'E0FFFF',
        'lightgoldenrodyellow'=>'FAFAD2',
        'lightgray'=>'D3D3D3',
        'lightgreen'=>'90EE90',
        'lightgrey'=>'D3D3D3',
        'lightpink'=>'FFB6C1',
        'lightsalmon'=>'FFA07A',
        'lightseagreen'=>'20B2AA',
        'lightskyblue'=>'87CEFA',
        'lightslategray'=>'778899',
        'lightslategrey'=>'778899',
        'lightsteelblue'=>'B0C4DE',
        'lightyellow'=>'FFFFE0',
        'lime'=>'00FF00',
        'limegreen'=>'32CD32',
        'linen'=>'FAF0E6',
        'magenta'=>'FF00FF',
        'maroon'=>'800000',
        'mediumaquamarine'=>'66CDAA',
        'mediumblue'=>'0000CD',
        'mediumorchid'=>'BA55D3',
        'mediumpurple'=>'9370D0',
        'mediumseagreen'=>'3CB371',
        'mediumslateblue'=>'7B68EE',
        'mediumspringgreen'=>'00FA9A',
        'mediumturquoise'=>'48D1CC',
        'mediumvioletred'=>'C71585',
        'midnightblue'=>'191970',
        'mintcream'=>'F5FFFA',
        'mistyrose'=>'FFE4E1',
        'moccasin'=>'FFE4B5',
        'navajowhite'=>'FFDEAD',
        'navy'=>'000080',
        'oldlace'=>'FDF5E6',
        'olive'=>'808000',
        'olivedrab'=>'6B8E23',
        'orange'=>'FFA500',
        'orangered'=>'FF4500',
        'orchid'=>'DA70D6',
        'palegoldenrod'=>'EEE8AA',
        'palegreen'=>'98FB98',
        'paleturquoise'=>'AFEEEE',
        'palevioletred'=>'DB7093',
        'papayawhip'=>'FFEFD5',
        'peachpuff'=>'FFDAB9',
        'peru'=>'CD853F',
        'pink'=>'FFC0CB',
        'plum'=>'DDA0DD',
        'powderblue'=>'B0E0E6',
        'purple'=>'800080',
        'red'=>'FF0000',
        'rosybrown'=>'BC8F8F',
        'royalblue'=>'4169E1',
        'saddlebrown'=>'8B4513',
        'salmon'=>'FA8072',
        'sandybrown'=>'F4A460',
        'seagreen'=>'2E8B57',
        'seashell'=>'FFF5EE',
        'sienna'=>'A0522D',
        'silver'=>'C0C0C0',
        'skyblue'=>'87CEEB',
        'slateblue'=>'6A5ACD',
        'slategray'=>'708090',
        'slategrey'=>'708090',
        'snow'=>'FFFAFA',
        'springgreen'=>'00FF7F',
        'steelblue'=>'4682B4',
        'tan'=>'D2B48C',
        'teal'=>'008080',
        'thistle'=>'D8BFD8',
        'tomato'=>'FF6347',
        'turquoise'=>'40E0D0',
        'violet'=>'EE82EE',
        'wheat'=>'F5DEB3',
        'white'=>'FFFFFF',
        'whitesmoke'=>'F5F5F5',
        'yellow'=>'FFFF00',
        'yellowgreen'=>'9ACD32');

    $color_name = strtolower($color_name);
    if (isset($colors[$color_name]))
    {
        return ('#' . $colors[$color_name]);
    }
    else
    {
        return ($color_name);
    }
}

/************************************
 * FUNCTION: UPDATE REVIEW SETTINGS *
 ************************************/
function update_review_settings($veryhigh, $high, $medium, $low, $insignificant)
{
    // Open the database connection
    $db = db_open();

    // Update the very high risk level
    $stmt = $db->prepare("UPDATE review_levels SET value=:value WHERE name='Very High'");
    $stmt->bindParam(":value", $veryhigh, PDO::PARAM_INT);
    $stmt->execute();

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

    // Update the insignificant risk level
    $stmt = $db->prepare("UPDATE review_levels SET value=:value WHERE name='Insignificant'");
    $stmt->bindParam(":value", $insignificant, PDO::PARAM_INT);
    $stmt->execute();

    // Audit log
    $risk_id = 1000;
    $message = "The review settings were modified by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return true;
}

/**********************************
 * FUNCTION: CREATE CVSS DROPDOWN *
 **********************************/
function create_cvss_dropdown($name, $selected = NULL, $blank = true)
{
    global $escaper;

    echo "<select id=\"" . $escaper->escapeHtml($name) . "\" name=\"" . $escaper->escapeHtml($name) . "\" class=\"form-field\" style=\"width:120px;\" onClick=\"javascript:showHelp('" . $escaper->escapeHtml($name) . "Help');updateScore();\">\n";

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

        echo "    <option value=\"" . $escaper->escapeHtml($value) . "\"" . $text . ">" . $escaper->escapeHtml($option['metric_value']) . "</option>\n";
    }

    echo "  </select>\n";
}

/*************************************
 * FUNCTION: CREATE NUMERIC DROPDOWN *
 *************************************/
function create_numeric_dropdown($name, $selected = NULL, $blank = true)
{
    global $escaper;

    echo "<select id=\"" . $escaper->escapeHtml($name) . "\" name=\"" . $escaper->escapeHtml($name) . "\" class=\"form-field\" style=\"width:50px;\" onClick=\"javascript:showHelp('" . $escaper->escapeHtml($name) . "Help');updateScore();\">\n";

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

        echo "    <option value=\"" . $escaper->escapeHtml($value) . "\"" . $text . ">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "  </select>\n";
}

/****************************************
 * FUNCTION: CREATE MULTIUSERS DROPDOWN *
 ****************************************/
function create_multiusers_dropdown($name, $selected = "", $custom_html = "", $returnHtml = false){
    global $escaper;

    // Make selected to array
    $selected = explode(",", $selected);
    if(!is_array($selected)){
        $selected = array();
    }

    $options = get_options_from_table("enabled_users");
    $str = "<select id=\"{$name}\" {$custom_html} name=\"{$name}[]\" multiple class=\"form-field form-control multiselect\" style=\"width:auto;\">\n";
    // For each option
    foreach ($options as $option)
    {
        // If the option is selected
        if (in_array($option['value'], $selected))
        {
            $text = " selected";
        }
        else $text = "";

        $str .= "    <option value=\"" . $escaper->escapeHtml($option['value']) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
    }
    $str .= "  </select>\n";

    if($returnHtml){
        return $str;
    }else{
        echo $str;
    }
}

/*****************************
 * FUNCTION: CREATE DROPDOWN *
 * Some info on the user management for some of the options.
 * "user": It will return all users, ignoring the organizational hierarchy extra for admin users, use the selected business unit for non-admin users 
 * "enabled/disabled_users": Will return the enabled/disabled users of the selected business unit(EVEN FOR ADMINS). Use it outside of admin-only area
 * "enabled/disabled_users_all": Will return the enabled/disabled users, ignoring the selected business unit. Use it ONLY inside of admin-only area
 *****************************/
function create_dropdown($name, $selected = NULL, $rename = NULL, $blank = true, $help = false, $returnHtml=false, $customHtml="", $blankText="--", $blankValue="", $useValue=true, $alphabetical_order = 0, $options = null)
{

    global $escaper;
    $str = "";
    // If we want to update the helper when selected
    if ($help == true)
    {
        $helper = "  onClick=\"javascript:showHelp('" . $escaper->escapeHtml($rename) . "Help');updateScore();\"";
    }
    else $helper = "";

    if ($rename != NULL)
    {
        $str .= "<select {$customHtml} id=\"" . $escaper->escapeHtml($rename) . "\" name=\"" . $escaper->escapeHtml($rename) . "\" class=\"form-field form-control\" style=\"width:auto;\"" . $helper . ">\n";
    }
    else $str .= "<select {$customHtml} id=\"" . $escaper->escapeHtml($name) . "\" name=\"" . $escaper->escapeHtml($name) . "\" class=\"form-field\" style=\"width:auto;\"" . $helper . ">\n";

    // Get the list of options
    if($options === NULL){
        $options = get_options_from_table($name);
        if($alphabetical_order == 1) usort($options, function($a, $b){return strcmp($a["name"], $b["name"]);});
    }

    // If the blank is true
    if ($blank == true)
    {
        array_unshift($options, ["value"=>$blankValue, "name"=>$blankText]);
    }

    foreach ($options as $key => $option)
    {
        if ($selected == $option['value']) {
            $text = " selected";
        } else {
            $text = "";
        }

        // If ID is used for option's value
        if($useValue)
        {
            $str .= "    <option value=\"" . $escaper->escapeHtml($option['value']) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
        }
        // If name is used for option's value
        else
        {
            if($blank == true && $key == 0){
                $str .= "    <option value=\"" . $escaper->escapeHtml($blankValue) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
            }else{
                $str .= "    <option value=\"" . $escaper->escapeHtml($option['name']) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
            }
        }
    }

    $str .= "  </select>\n";

    if($returnHtml){
        return $str;
    }else{
        echo $str;
    }
}

/**************************************
 * FUNCTION: CREATE MULTIPLE DROPDOWN *
 * Some info on the user management for some of the options.
 * "user": It will return all users, ignoring the organizational hierarchy extra for admin users, use the selected business unit for non-admin users 
 * "enabled/disabled_users": Will return the enabled/disabled users of the selected business unit(EVEN FOR ADMINS). Use it outside of admin-only area
 * "enabled/disabled_users_all": Will return the enabled/disabled users, ignoring the selected business unit. Use it ONLY inside of admin-only area
 **************************************/
function create_multiple_dropdown($name, $selected = NULL, $rename = NULL, $options = NULL, $blank = false, $blankText="--", $blankValue="", $useValue=true, $customHtml="",$alphabetical_order=0, $returnHtml=false)
{
    global $lang;
    global $escaper;
    $str = "";

    if ($rename != NULL)
    {
        $str .= "<select {$customHtml} multiple=\"multiple\" id=\"" . $escaper->escapeHtml($rename) . "\" name=\"" . $escaper->escapeHtml($rename) . "[]\">\n";
    }
    else {
        $str .= "<select {$customHtml} multiple=\"multiple\" id=\"" . $escaper->escapeHtml($name) . "\" name=\"" . $escaper->escapeHtml($name) . "[]\">\n";
    }

    // Get the list of options
    if($options === NULL){
        $options = get_options_from_table($name);
        if($alphabetical_order == 1) usort($options, function($a, $b){return strcmp($a["name"], $b["name"]);});
    }

    // If the blank is true
    if ($blank == true)
    {
        array_unshift($options, ["value"=>$blankValue, "name"=>$blankText]);
    }

    $is_selected_array = is_array($selected);

    // For each option
    foreach ($options as $option)
    {
        // Pattern is a team id surrounded by colons
        $regex_pattern = "/:" . $option['value'] .":/";

        // If the user belongs to the team or all was selected
        if ($selected == "all" ||
           ($is_selected_array && in_array($option['value'], $selected)) ||
           ($selected === null && !$option['value']) ||
           (!$is_selected_array && preg_match($regex_pattern, $selected, $matches)))
        {
            $text = " selected";
        }
        else $text = "";

        // If ID is used for option's value
        if($useValue)
        {
            $str .= "    <option value=\"" . $escaper->escapeHtml($option['value']) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
        }
        // If name is used for option's value
        else
        {
            $str .= "    <option value=\"" . $escaper->escapeHtml($option['name']) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
        }

    }

    $str .= "  </select>\n";
    if($returnHtml){
        return $str;
    }else{
        echo $str;
    }
}

/*****************************************
 * FUNCTION: GET RISK LEVEL DISPLAY NAME *
 *****************************************/
function get_risk_level_display_name($name)
{
    $var = $name."_risk";
    if(!empty($GLOBALS[$var])){
        return $GLOBALS[$var];
    }

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("SELECT * FROM `risk_levels` WHERE name=:name;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();
    // Close the database connection
    db_close($db);

    if($name == "Insignificant" || !$name)
    {
        $GLOBALS[$var] = "Insignificant";
    }
    else
    {
        $GLOBALS[$var] = isset($array['display_name']) ? $array['display_name'] : null;
    }

    return $GLOBALS[$var];
}

/****************************
 * FUNCTION: CALCULATE RISK *
 ****************************/
function calculate_risk($impact, $likelihood)
{
    if(empty($GLOBALS['count_of_impacts'])){
        $GLOBALS['count_of_impacts'] = count(get_table("impact"));
        $GLOBALS['count_of_likelihoods'] = count(get_table("likelihood"));
    }

    // If the impact or likelihood is valid
    if(!empty($GLOBALS['count_of_impacts']) && !empty($GLOBALS['count_of_likelihoods']) && in_array($impact, range(1, $GLOBALS['count_of_impacts'])) && in_array($likelihood, range(1,$GLOBALS['count_of_likelihoods'])))
    {
        // Get risk_model
        $risk_model = get_setting("risk_model");

        // Pick the risk formula
        if ($risk_model == 1)
        {
            // $max_risk = 35;
            $max_risk = ($GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts']) + (2 * $GLOBALS['count_of_impacts']);
            $risk = ($likelihood * $impact) + (2 * $impact);
        }
        else if ($risk_model == 2)
        {
            // $max_risk = 30;
            $max_risk = ($GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts']) + $GLOBALS['count_of_impacts'];
            $risk = ($likelihood * $impact) + $impact;
        }
        else if ($risk_model == 3)
        {
            // $max_risk = 25;
            $max_risk = $GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts'];
            $risk = $likelihood * $impact;
        }
        else if ($risk_model == 4)
        {
            // $max_risk = 30;
            $max_risk = $GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts'] + $GLOBALS['count_of_likelihoods'];
            $risk = ($likelihood * $impact) + $likelihood;
        }
        else if ($risk_model == 5)
        {
            // $max_risk = 35;
            $max_risk = ($GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts']) + (2 * $GLOBALS['count_of_likelihoods']);
            $risk = ($likelihood * $impact) + (2 * $likelihood);
        }
        else if ($risk_model == 6)
        {
            $max_risk = 10;
            $risk = get_stored_risk_score($impact, $likelihood);
        }

        // This puts it on a 1 to 10 scale similar to CVSS
        $risk = round($risk * (10 / $max_risk), 1);
    }
    // If the impact or likelihood were not specified risk is 10
    else $risk = get_setting('default_risk_score');

    return $risk ? $risk : 0;
}

/****************************
 * FUNCTION: GET RISK COLOR *
 ****************************/
function get_risk_color($risk)
{
    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("SELECT * FROM `risk_levels` WHERE value<=:value ORDER BY value DESC LIMIT 1");
    $stmt->bindParam(":value", $risk, PDO::PARAM_STR, 4);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // Find the color
    if(!$array){
        $color = "white";
    }else{
        $color = $array['color'];
    }


    return $color;
}

/****************************
 * FUNCTION: GET RISK COLOR BY PRE-DEFINED ARRAY*
 ****************************/
function get_risk_color_from_levels($risk, $levels)
{
    $result = array('name' => '', 'value' => 0);

    foreach($levels as $level){
        if($risk < $level['value']){
            continue;
        }
        if($result['value'] <= $level['value'] ){
            $result = $level;
        }
    }

    // Find the color
    if ($result['name']){
        $color = $result['color'];
    }
    else{
        $color = "white";
    }

    return $color;
}


/*********************************
 * FUNCTION: GET RISK LEVEL NAME *
 *********************************/
function get_risk_level_name($risk)
{
    global $lang;

    $key = "risk_level_name".$risk;
    
    if(!isset($GLOBALS[$key]))
    {
        // If the risk is not null
        if ($risk != "")
        {
            // Open the database connection
            $db = db_open();

            // Get the risk levels
            $stmt = $db->prepare("SELECT name, display_name FROM `risk_levels` WHERE value<=:risk ORDER BY value DESC LIMIT 1");
            $stmt->bindParam(":risk", $risk, PDO::PARAM_STR);
            $stmt->execute();

            // Store the list in the array
            $array = $stmt->fetch(PDO::FETCH_ASSOC);

            // Close the database connection
            db_close($db);

	    // If the returned array is not empty
	    if (!empty($array))
	    {
            	// If the risk level display name is in High, Medium, or Low
            	if ($array['display_name'] != "")
            	{
            	    $GLOBALS[$key] = $array['display_name'];
            	}
            	// If the risk level name is in High, Medium, or Low
            	elseif($array['name'] != "")
            	{
            	    $GLOBALS[$key] = $array['name'];
            	}
            	// Otherwise the risk is Insignificant
            	else $GLOBALS[$key] = "Insignificant";
	    }
	    else $GLOBALS[$key] = "Insignificant";
        }
        else
        {
            $GLOBALS[$key] = "";
        }
    }

    return $GLOBALS[$key];
}
/****************************
 * FUNCTION: GET RISK LEVEL NAME BY PRE-DEFINED ARRAY*
 ****************************/
function get_risk_level_name_from_levels($risk, $levels)
{
    global $lang;

    // If the risk is not null
    if ($risk != "")
    {
        $result = array('name' => '', 'display_name' => '','value' => 0);

        foreach($levels as $level){
            if($risk < $level['value']){
                continue;
            }
            if($result['value'] <= $level['value'] ){
                $result = $level;
            }
        }

        // If the risk level display name is in High, Medium, or Low
        if ($result['display_name'] != "")
        {
            return $result['display_name'];
        }
        // If the risk level name is in High, Medium, or Low
        elseif ($result['name'] != "")
        {
            return $result['name'];
        }
        // Otherwise the risk is Insignificant
        else return $lang['Insignificant'];
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

    //Get current risk mdel
    $stmt = $db->prepare("SELECT value from settings WHERE name='risk_model'");
    $stmt->bindParam(":risk_model", $risk_model, PDO::PARAM_INT);
    $stmt->execute();

    $current_risk_model = $stmt->fetchAll();

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
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":id", $risk['id'], PDO::PARAM_INT);
            $stmt->execute();

            // Add risk scoring history
            add_risk_scoring_history($risk['id'], $calculated_risk);

            // Add residual risk scoring history
            $residual_risk = get_residual_risk($risk['id']+1000);
            add_residual_risk_scoring_history($risk['id'], $residual_risk);
        }
    }

    $status = [
        '1' => 'Likelihood x Impact + 2(Impact)',
        '2' => 'Likelihood x Impact + Impact',
        '3' => 'Likelihood x Impact',
        '4' => 'Likelihood x Impact + Likelihood',
        '5' => 'Likelihood x Impact + 2(Likelihood)',
        '6' => 'Custom',
    ];

    // Audit log
    $risk_id = 1000;
    if ($current_risk_model[0]['value'] != $risk_model) {
        $message = "The risk formula was modified from '" . $status[$current_risk_model[0]['value']] . "' to '" . $status[$risk_model] . "' by user \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
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
    $id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT scoring_method FROM `risk_scoring` WHERE id = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $old_scoring_method = $stmt->fetchColumn();

    // If scoring method was changed
    if($old_scoring_method != $scoring_method)
    {
        // Update the scoring method for the given risk ID
        $stmt = $db->prepare("UPDATE risk_scoring SET scoring_method = :scoring_method WHERE id = :id");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        // Audit log
        $message = "Scoring method has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    // Close the database connection
    db_close($db);

    // Return the new scoring method
    return $scoring_method;
}

/**************************
 * FUNCTION: UPDATE TABLE *
 **************************/
function update_table($table, $name, $value, $length=20)
{
    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("UPDATE {$table} SET name=:name WHERE value=:value");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, $length);
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    if($stmt->rowCount())
    {
        // Audit log
        switch ($table)
        {
            case "impact":
                $risk_id = 1000;
                $message = "The impact naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);
                break;
            case "likelihood":
                $risk_id = 1000;
                $message = "The likelihood naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);
                break;
            case "mitigation_effort":
                $risk_id = 1000;
                $message = "The mitigation effort naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);
                break;
            default:
                $risk_id = 1000;
                $message = "The \"".$table."\" naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);
                break;
        }
    }

    // Close the database connection
    db_close($db);

    return $stmt->rowCount();
}
/********************************
 * FUNCTION: UPDATE TABLE BY ID *
 ********************************/
function update_table_by_id($table, $name, $id, $length=50)
{
    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("UPDATE {$table} SET name=:name WHERE id=:id");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, $length);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if($stmt->rowCount())
    {
        // Audit log
        $risk_id = 1000;
        $message = "The \"".$table."\" naming convention was modified by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    // Close the database connection
    db_close($db);

    return $stmt->rowCount();
}

/*************************
 * FUNCTION: ADD SETTING *
 *************************/
function add_setting($name, $value)
{

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("INSERT IGNORE INTO settings (`name`,`value`) VALUES (:name, :value);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":value", $value, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    $key = 'setting_'.$name;
    $GLOBALS[$key] = $value;
}

/***************************************
 * FUNCTION: UPDATE OR INSERT SETTINGS *
 ***************************************/
function update_or_insert_setting($name, $value)
{
    // Open the database connection
    $db = db_open();

    // Update the database version information

    $stmt = $db->prepare("REPLACE INTO `settings`(`name`, `value`) VALUES (:name, :value);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":value", $value, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    $key = 'setting_'.$name;
    $GLOBALS[$key] = $value;
    
    return true;
}

/****************************
 * FUNCTION: UPDATE SETTING *
 ****************************/
function update_setting($name, $value)
{
    // Open the database connection
    $db = db_open();

    // Delete existing setting value before adding.
    $stmt = $db->prepare("DELETE FROM `settings` WHERE name=:name");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 50);
    $stmt->execute();

    // Update the setting
    $stmt = $db->prepare("INSERT IGNORE INTO settings (`name`,`value`) VALUES (:name, :value);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 50);
    $stmt->bindParam(":value", $value, PDO::PARAM_STR, 200);
    $stmt->execute();
    
    $key = 'setting_'.$name;
    $GLOBALS[$key] = $value;

    // Audit log
    switch ($name)
    {
        case "max_upload_size":
            $risk_id = 1000;
            $message = "The maximum upload file size was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        default:
            $risk_id = 1000;
            $message = "A setting value named \"".$name."\" was updated by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
    }

    // Close the database connection
    db_close($db);
}

/****************************
 * FUNCTION: DELETE SETTING *
 ****************************/
function delete_setting($name)
{
    // Open the database connection
    $db = db_open();

    // Update the setting
    $stmt = $db->prepare("DELETE FROM `settings` WHERE name=:name;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 50);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    
    $key = 'setting_'.$name;
    if(isset($GLOBALS[$key]))
        unset($GLOBALS[$key]);
}

/*************************
 * FUNCTION: GET SETTING *
 *************************/
function get_setting($setting, $default=false)
{
    $key = 'setting_'.$setting;
    if(isset($GLOBALS[$key])){
        return $GLOBALS[$key];
    }

    // Open the database connection
    $db = db_open();

    // Get the setting
    $stmt = $db->prepare("SELECT * FROM settings where name=:setting");
    $stmt->bindParam(":setting", $setting, PDO::PARAM_STR, 100);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the array isn't empty
    if ($array)
    {
        // Set the value to the array value
        $value = trim($array[0]['value']);
    }
    else $value = false;

    if($value === false)
    {
        $result = $default;
    }
    else
    {
        $result = $value;
    }
    $GLOBALS[$key] = $result;
    
    return $GLOBALS[$key];
}

/************************************************************
 * FUNCTION: GET SETTINGS                                   *
 * Gets a list of settings and returns it as an associative *
 * array where the key is the name of the setting and       *
 * the value is the actual value of said setting            *
 ************************************************************/
function get_settings($settings) {

    if (!is_array($settings)) {
        $settings = explode(',', $settings);
    }

    $settings_in = [];
    foreach ($settings as $i => $setting)
    {
        $key = ":param".$i;
        $settings_in[] = $key;
        $params[$key] = $setting;
    }

    // making the comma separated list to be included in the sql
    $settings_in = implode(", ", $settings_in);

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("
        SELECT
            `name`,
            `value`
        FROM
            `settings`
        WHERE
            `name` IN ({$settings_in});
    ");
    $stmt->execute($params);

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // If the array isn't empty
    if ($array)
    {
        $results = [];
        foreach($array as $setting) {
            $results[$setting['name']] = $setting['value'];
        }
        return $results;
    }
    else return [];
}

/*******************************************************
 * FUNCTION: CONVERT DEFAULT DATE FORMAT TO PHP FORMAT *
 *******************************************************/
function get_default_date_format()
{
    $default_date_format = get_setting("default_date_format");
    $php_date_format = str_ireplace("YYYY", "Y", $default_date_format);
    $php_date_format = str_ireplace("MM", "m", $php_date_format);
    $php_date_format = str_ireplace("DD", "d", $php_date_format);
    return $php_date_format;
}

/******************************************************
 * FUNCTION: CONVERT DEFAULT DATE FORMAT TO JS FORMAT *
 ******************************************************/
function get_default_date_format_for_js()
{
    $default_date_format = get_setting("default_date_format");
    $js_date_format = str_ireplace("YYYY", "yy", $default_date_format);
    $js_date_format = str_ireplace("MM", "mm", $js_date_format);
    $js_date_format = str_ireplace("DD", "dd", $js_date_format);
    return $js_date_format;
}

/************************************************************
 * FUNCTION: CONVERT DEFAULT DATE TIME FORMAT TO PHP FORMAT *
 ************************************************************/
function get_default_datetime_format($time_format="H:i:s")
{
    $format = get_default_date_format();

    return $format." ".$time_format;
}

/*********************************************************************************
 * FUNCTION: GET FORMATTED DATE/DATETIME                                         *
 *                                                                               *
 * Use it only on dates got from the database, as strtotime is not suited to be  *
 * used on user input since it can't handle all the date formats we support.     *
 *                                                                               *
 * On user input use the `get_standard_date_from_default_format` function before *
 * writing into the database                                                     *
 *********************************************************************************/
function format_date($date, $default = "")
{
    // If the date is not 0000-00-00
    if ($date && $date != "0000-00-00" && $date != "0000-00-00 00:00:00")
    {
        // Set it to the proper format
        return strtotime($date) ? date(get_default_date_format(), strtotime($date)) : "";
    }
    else return $default;
}
function format_datetime($date, $default = "", $timeformat = "H:i:s")
{
    // If the date is not 0000-00-00
    if ($date && $date != "0000-00-00" && $date != "0000-00-00 00:00:00")
    {
        // Set it to the proper format
        return strtotime($date) ? date(get_default_datetime_format($timeformat), strtotime($date)) : "";
    }
    else return $default;
}

/******************************************************
 * FUNCTION: CONVERT EMPTY DATE VALUE TO EMTPY STRING *
 ******************************************************/
function trim_date($date, $default = "")
{
    // If the date is not 0000-00-00
    if ($date && stripos($date, "0000-00") === false)
    {
        // Set it to the proper format
        return $date;
    }
    else
    {
        return $default;
    }
}

/****************************************************************************
 * FUNCTION: GET STANDARD DATE FROM STRING FORMATTED BY DEFAULT DATE FORMAT *
 ****************************************************************************/
function get_standard_date_from_default_format($formatted_date, $time=false)
{
    // Return 0000-00-00 if formatted date is invalid or unset
    if(!$formatted_date || strpos($formatted_date, "0000")  !== false){
        return "0000-00-00";
    }

    // If time is requested
    if($time){
        // Get default date format
        $format = get_default_datetime_format("H:i:s");

        // Convert date string to Y-m-d H:i:s date
        $d = DateTime::createFromFormat($format, $formatted_date);
        $standard_date = $d ? $d->format('Y-m-d H:i:s') : "";
    }else{
        // Get default date format
        $format = get_default_date_format();

        // Convert date string to Y-m-d date
        $d = DateTime::createFromFormat($format, $formatted_date);
        $standard_date = $d ? $d->format('Y-m-d') : "";
    }

    return $standard_date;
}

/**********************
 * FUNCTION: ADD NAME *
 **********************/
function add_name($table, $name, $size=20)
{
    if(!$name){
        return false;
    }

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("INSERT INTO {$table} (`name`) VALUES (:name); ");
    // If size is null, no set param length
    if($size === null)
    {
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    }
    // If size is not null, no set param length
    else
    {
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, $size);
    }
    $stmt->execute();
    $insertedId = $db->lastInsertId();

    // Audit log
    switch ($table)
    {
        case "projects":
            $risk_id = 1000;
            $message = "A new project \"" . try_decrypt($name) . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "category":
            $risk_id = 1000;
            $message = "A new category \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "team":
            $risk_id = 1000;
            $message = "A new team \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "technology":
            $risk_id = 1000;
            $message = "A new technology \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "location":
            $risk_id = 1000;
            $message = "A new location \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "source":
            $risk_id = 1000;
            $message = "A new source \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "regulation":
            $risk_id = 1000;
            $message = "A new control regulation \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "planning_strategy":
            $risk_id = 1000;
            $message = "A new planning strategy \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "close_reason":
            $risk_id = 1000;
            $message = "A new close reason \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "file_types":
            $risk_id = 1000;
            $message = "A new upload file type \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "control_class":
            $risk_id = 1000;
            $message = "A new control_class \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        default:
            $risk_id = 1000;
            $message = "A new " . $table . " \"" . $name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
    }

    // Close the database connection
    db_close($db);

    return $insertedId;
}

/**********************************
 * FUNCTION: DELETE VALUE BY NAME *
 **********************************/
function delete_value_by_name($table, $name)
{
    // Open the database connection
    $db = db_open();

    // Delete the table value
    $stmt = $db->prepare("DELETE FROM $table WHERE name=:name");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}
/********************************
 * FUNCTION: DELETE VALUE BY ID *
 ********************************/
function delete_value_by_id($table, $id)
{
    // Open the database connection
    $db = db_open();

    // Delete the table value
    $stmt = $db->prepare("DELETE FROM $table WHERE id=:id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
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

    // Get the name to be deleted
    $name = get_name_by_value($table, $value);

    // Delete the table value
    $stmt = $db->prepare("DELETE FROM $table WHERE value=:value");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Audit log
    switch ($table)
    {
        case "projects":
            $risk_id = 1000;
            $message = "The existing project \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "user":
            $message = "The existing user \"" . $name . "\" was deleted by the \"" . $_SESSION['user'] . "\" user.";
            write_log($value + 1000, $_SESSION['uid'], $message, "user");
            break;
        case "category":
            $risk_id = 1000;
            $message = "The existing category \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "team":
            $risk_id = 1000;
            $message = "The existing team \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "technology":
            $risk_id = 1000;
            $message = "The existing technology \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "location":
            $risk_id = 1000;
            $message = "The existing location \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "source":
            $risk_id = 1000;
            $message = "The existing source \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "regulation":
            $risk_id = 1000;
            $message = "The existing control regulation \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "planning_strategy":
            $risk_id = 1000;
            $message = "The existing planning strategy \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "close_reason":
            $risk_id = 1000;
            $message = "The existing close reason \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "file_types":
            $risk_id = 1000;
            $message = "The existing upload file type \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "file_type_extensions":
            $risk_id = 1000;
            $message = "The existing upload extension \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "frameworks":
            $risk_id = 1000;
            $message = "The existing framework \"" . try_decrypt($name) . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
        case "test_status":
            $test_status_ids = get_test_status_ids();
            $query = "UPDATE `framework_control_test_audits` SET `framework_control_test_audits`.`status` = '0' WHERE ";
            for ($i=0; $i < sizeof($test_status_ids) ; $i++) {
                $query .= "`framework_control_test_audits`.`status` !='" . $test_status_ids[$i]['value'] . "' AND ";
            }
            $query .= " 1 ;" ;
            $stmt = $db->prepare($query);
            $stmt->execute();

            $risk_id = 1000;
            $message = "The existing test status \"" . try_decrypt($name) . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);

            break;
        default:
            $risk_id = 1000;
            $message = "The existing " . $table . " \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
            write_log($risk_id, $_SESSION['uid'], $message);
            break;
    }

    // Close the database connection
    db_close($db);

    return true;
}

/******************************************************
 * FUNCTION: GET TEST IDS FROM FRAMEWORK CONTROL TEST *
 ******************************************************/
function get_test_status_ids(){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT `value` FROM `test_status`");
    $stmt->execute();

    $array = $stmt->fetchAll();

    // closed the database connection
    db_close($db);
    return $array;
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

    // Audit log
    $username = get_name_by_value("user", $value);
    $message = "The user \"" . $username . "\" was enabled by the \"" . $_SESSION['user'] . "\" user.";
    write_log($value + 1000, $_SESSION['uid'], $message, "user");

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

    // Audit log
    $username = get_name_by_value("user", $value);
    $message = "The user \"" . $username . "\" was disabled by the \"" . $_SESSION['user'] . "\" user.";
    write_log($value + 1000, $_SESSION['uid'], $message, "user");

    // Close the database connection
    db_close($db);

    return true;
}

/************************
 * FUNCTION: USER EXIST *
 ************************/
function user_exist($user)
{
    // Open the database connection
    $db = db_open();

    // Find the user
    $stmt = $db->prepare("SELECT * FROM `user` WHERE `username`=:user");
    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);

    $stmt->execute();

    // Fetch the array
    $array = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);
    
    return !empty($array);
}

/****************************
 * FUNCTION: VALID USERNAME *
 ****************************/
function valid_username($username)
{
    // If the username is not blank
    if ($username != "")
    {
        // Return true
        return true;
    }
    // Otherwise, return false
    else return false;
}

/****************************
 * FUNCTION: VALID PASSWORD *
 ****************************/
function valid_password($password, $repeat_password, $user_id=false)
{
    // Check that the two passwords are the same
    if ($password == $repeat_password)
    {
        // If the password policy is enabled
        if (get_setting('pass_policy_enabled') == 1)
        {
            // If the password policy requirements are being met
            if (check_valid_min_chars($password) && check_valid_alpha($password) && check_valid_upper($password) && check_valid_lower($password) && check_valid_digits($password) && check_valid_specials($password) && check_current_password_age($user_id))
            {
                // Return 1
                return 1;
            }
            // Otherwise, return false
            else return false;
        }
        // Otherwise, return 1
        else return 1;
    }
    else
    {
        // Display an alert
        set_alert(true, "bad", "The new password entered does not match the confirm password entered.  Please try again.");

        // Return false
        return false;
    }
}

/***********************************
 * FUNCTION: CHECK VALID MIN CHARS *
 ***********************************/
function check_valid_min_chars($password)
{
    // Get the minimum characters
    $min_chars = get_setting('pass_policy_min_chars');

    // If the password length is >= the minimum characters
    if (strlen($password) >= $min_chars)
    {
        // Return true
        return true;
    }
    else
    {
        // Display an alert
        set_alert(true, "bad", "Unabled to update the password because it does not contain the minimum of ". $min_chars . " characters.");

       // Return false
       return false;
    }
}

/*******************************
 * FUNCTION: CHECK VALID ALPHA *
 *******************************/
function check_valid_alpha($password)
{
    // If alpha checking is enabled
    if (get_setting('pass_policy_alpha_required') == 1)
    {
        // If the password contains an alpha character
        if (preg_match('/[A-Za-z]+/', $password))
        {
            // Return true
            return true;
        }
            else
            {
                    // Display an alert
                    set_alert(true, "bad", "Unabled to update the password because it does not contain an alpha character.");

                    // Return false
                    return false;
            }
    }
    // Otherwise, return true
    else return true;
}

/*******************************
 * FUNCTION: CHECK VALID UPPER *
 *******************************/
function check_valid_upper($password)
{
        // If upper checking is enabled
        if (get_setting('pass_policy_upper_required') == 1)
        {
                // If the password contains an upper character
                if (preg_match('/[A-Z]+/', $password))
                {
                        // Return true
                        return true;
                }
                else
                {
                        // Display an alert
                        set_alert(true, "bad", "Unabled to update the password because it does not contain an uppercase character.");

                        // Return false
                        return false;
                }
        }
        // Otherwise, return true
        else return true;
}

/*******************************
 * FUNCTION: CHECK VALID LOWER *
 *******************************/
function check_valid_lower($password)
{
        // If lower checking is enabled
        if (get_setting('pass_policy_lower_required') == 1)
        {
                // If the password contains an lower character
                if (preg_match('/[a-z]+/', $password))
                {
                        // Return true
                        return true;
                }
                else
                {
                        // Display an alert
                        set_alert(true, "bad", "Unabled to update the password because it does not contain a lowercase character.");

                        // Return false
                        return false;
                }
        }
        // Otherwise, return true
        else return true;
}

/********************************
 * FUNCTION: CHECK VALID DIGITS *
 ********************************/
function check_valid_digits($password)
{
    // If digit checking is enabled
    if (get_setting('pass_policy_digits_required') == 1)
    {
        // If the password contains a digit
        if (preg_match("/[0-9]+/", $password))
        {
            // Return true
            return true;
        }
                else
                {
                        // Display an alert
                        set_alert(true, "bad", "Unabled to update the password because it does not contain a digit.");

                        // Return false
                        return false;
                }
    }
    // Otherwise, return true
    else return true;
}

/**********************************
 * FUNCTION: CHECK VALID SPECIALS *
 **********************************/
function check_valid_specials($password)
{
    // If special checking is enabled
    if (get_setting('pass_policy_special_required') == 1)
    {
        // If the password contains a special
        if (preg_match("/[^A-Za-z0-9]+/", $password))
            {
                    // Return true
                    return true;
            }
                else
                {
                    // Display an alert
                    set_alert(true, "bad", "Unabled to update the password because it does not contain a special character.");

                    // Return false
                    return false;
                }
    }
    // Otherwise, return true
    else return true;
}

/************************************
 * FUNCTION: UPDATE PASSWORD POLICY *
 ************************************/
function update_password_policy($strict_user_validation, $pass_policy_enabled, $min_characters, $alpha_required, $upper_required, $lower_required, $digits_required, $special_required, $pass_policy_attempt_lockout, $pass_policy_attempt_lockout_time, $pass_policy_min_age, $pass_policy_max_age, $pass_policy_reuse_limit)
{
    // Open the database connection
    $db = db_open();

    // Update the user policy
    $stmt = $db->prepare("UPDATE `settings` SET value=:strict_user_validation WHERE name='strict_user_validation'");
    $stmt->bindParam(":strict_user_validation", $strict_user_validation, PDO::PARAM_INT, 1);
    $stmt->execute();

    // Update the password policy
    $stmt = $db->prepare("UPDATE `settings` SET value=:pass_policy_enabled WHERE name='pass_policy_enabled'");
    $stmt->bindParam(":pass_policy_enabled", $pass_policy_enabled, PDO::PARAM_INT, 1);
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `settings` SET value=:min_characters WHERE name='pass_policy_min_chars'");
    $stmt->bindParam(":min_characters", $min_characters, PDO::PARAM_INT, 2);
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `settings` SET value=:alpha_required WHERE name='pass_policy_alpha_required'");
    $stmt->bindParam(":alpha_required", $alpha_required, PDO::PARAM_INT, 1);
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `settings` SET value=:upper_required WHERE name='pass_policy_upper_required'");
    $stmt->bindParam(":upper_required", $upper_required, PDO::PARAM_INT, 1);
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `settings` SET value=:lower_required WHERE name='pass_policy_lower_required'");
    $stmt->bindParam(":lower_required", $lower_required, PDO::PARAM_INT, 1);
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `settings` SET value=:digits_required WHERE name='pass_policy_digits_required'");
    $stmt->bindParam(":digits_required", $digits_required, PDO::PARAM_INT, 1);
    $stmt->execute();
    $stmt = $db->prepare("UPDATE `settings` SET value=:special_required WHERE name='pass_policy_special_required'");
    $stmt->bindParam(":special_required", $special_required, PDO::PARAM_INT, 1);
    $stmt->execute();

    $stmt = $db->prepare("UPDATE `settings` SET value=:pass_policy_attempt_lockout WHERE name='pass_policy_attempt_lockout';");
    $stmt->bindParam(":pass_policy_attempt_lockout", $pass_policy_attempt_lockout, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $db->prepare("UPDATE `settings` SET value=:pass_policy_attempt_lockout_time WHERE name='pass_policy_attempt_lockout_time';");
    $stmt->bindParam(":pass_policy_attempt_lockout_time", $pass_policy_attempt_lockout_time, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $db->prepare("UPDATE `settings` SET value=:pass_policy_min_age WHERE name='pass_policy_min_age';");
    $stmt->bindParam(":pass_policy_min_age", $pass_policy_min_age, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $db->prepare("UPDATE `settings` SET value=:pass_policy_max_age WHERE name='pass_policy_max_age';");
    $stmt->bindParam(":pass_policy_max_age", $pass_policy_max_age, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $db->prepare("UPDATE `settings` SET value=:pass_policy_reuse_limit WHERE name='pass_policy_reuse_limit';");
    $stmt->bindParam(":pass_policy_reuse_limit", $pass_policy_reuse_limit, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Audit log
    $risk_id = 1000;
    $message = "The password policy was updated by user \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Return true
    return true;
}

/**********************
 * FUNCTION: ADD USER *
 **********************/
function add_user($type, $user, $email, $name, $salt, $hash, $teams, $role_id, $admin, $multi_factor, $change_password, $manager, $permissions)
{
    $custom_display_settings = json_encode(array(
        'id',
        'subject',
        'calculated_risk',
        'submission_date',
        'mitigation_planned',
        'management_review'
    ));
    // Open the database connection
    $db = db_open();

    // Insert the new user
    $stmt = $db->prepare(
        "INSERT INTO
            user (
                `type`,
                `username`,
                `name`,
                `email`,
                `salt`,
                `password`,
                `role_id`,
                `admin`,
                `multi_factor`,
                `change_password`,
                `manager`,
                `custom_display_settings`
            )
        VALUES (
            :type,
            :user,
            :name,
            :email,
            :salt,
            :hash,
            :role_id,
            :admin,
            :multi_factor,
            :change_password,
            :manager,
            :custom_display_settings
        );
    ");
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->bindParam(":user", $user, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR);
    $stmt->bindParam(":salt", $salt, PDO::PARAM_STR);
    $stmt->bindParam(":hash", $hash, PDO::PARAM_STR);
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
    $stmt->bindParam(":multi_factor", $multi_factor, PDO::PARAM_INT);
    $stmt->bindParam(":change_password", $change_password, PDO::PARAM_INT);
    $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
    $stmt->bindParam(":custom_display_settings", $custom_display_settings, PDO::PARAM_STR);

    $stmt->execute();
    
    $user_id = $db->lastInsertId();

    // If it's an admin then make sure that all teams are assigned
    if ($admin) {
        $teams = get_all_team_values();
    }

    // Set user's teams
    set_teams_of_user($user_id, $teams);

    update_permissions($user_id, $permissions);
    
    // Audit log
    if(!empty($_SESSION['uid']))
    {
        $message = "The new user \"" . $user . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
        write_log((int)$user_id + 1000, $_SESSION['uid'], $message, 'user');
    }
    else
    {
        $message = "The new user \"" . $user . "\" was added.";
        write_log((int)$user_id + 1000, $user_id, $message, 'user');
    }

    // Close the database connection
    db_close($db);

    return $user_id;
}

/*************************
 * FUNCTION: UPDATE USER *
 *************************/
function update_user($user_id, $lockout, $type, $name, $email, $teams, $role_id, $language, $admin, $multi_factor, $change_password, $manager, $permissions=[]) {

    // Getting the pre-update version of the user for audit logging
    $pre_update_user = get_user_by_id($user_id, true);

    // Checking whether the user just got locked out
    // It's only true when the user wasn't locked, but with this call it'll be
    $user_got_locked = !is_user_locked_out($user_id) && (int)$lockout == 1;

    // If the language is empty
    if ($language == "")
    {
        // Set the value to null
        $language = NULL;
    }

    // Open the database connection
    $db = db_open();

    // Update the user
    $stmt = $db->prepare("
        UPDATE
            `user`
        SET
            `lockout`=:lockout,
            `type`=:type,
            `name`=:name,
            `email`=:email,
            `role_id`=:role_id,
            `lang` =:lang,
            `admin`=:admin,
            `multi_factor`=:multi_factor,
            `change_password`=:change_password,
            `manager`=:manager
        WHERE
            `value`=:user_id;
    ");
    
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":lockout", $lockout, PDO::PARAM_INT);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR);
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_STR);
    $stmt->bindParam(":lang", $language, PDO::PARAM_STR);
    $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
    $stmt->bindParam(":multi_factor", $multi_factor, PDO::PARAM_INT);
    $stmt->bindParam(":change_password", $change_password, PDO::PARAM_INT);
    $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);

    $stmt->execute();

    // If it's an admin then make sure that all teams are assigned
    if ($admin) {
        $teams = get_all_team_values();
    }

    // Update the user's teams
    set_teams_of_user($user_id, $teams);

    update_permissions($user_id, $permissions);
    
    // Close the database connection
    db_close($db);

    if ($user_got_locked) {
        kill_sessions_of_user($user_id);
    } else {
        // If the update affects the current logged in user
        if (isset($_SESSION['uid']) && $_SESSION['uid'] == $user_id && isset($_SESSION['user'])) {
            set_user_permissions($_SESSION['user']);
        }

        // Refresh the permissions in the active sessions of the user
        refresh_permissions_in_sessions_of_user($user_id);
    }

    // Audit log
    // Getting the post-update version of the user for audit logging
    $post_update_user = get_user_by_id($user_id, true);
    $changes = get_changes('user', $pre_update_user, $post_update_user);
    if(!empty($_SESSION['uid'])) {
        $message = _lang('UserUpdatedAuditLog', [
            'username' => "{$post_update_user['name']}({$post_update_user['username']})",
            'updater' => "{$_SESSION['name']}({$_SESSION['user']})",
            'changes' => $changes
        ], false);
        write_log((int)$user_id + 1000, $_SESSION['uid'], $message, 'user');
    } else {
        $message = _lang('UserUpdatedFromidPDataAuditLog', ['username' => "{$post_update_user['name']}({$post_update_user['username']})", 'changes' => $changes], false);
        write_log((int)$user_id + 1000, $user_id, $message, 'user');
    }
    return true;
}

/*************************************
 * FUNCTION: GET USER LOCKOUT STATUS *
 *************************************/
function is_user_locked_out($user_id) {
    // Open the database connection
    $db = db_open();

    // Get the user lockout information
    $stmt = $db->prepare("SELECT `lockout` FROM `user` WHERE `value` = :user_id");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $lockout_status = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return isset($lockout_status) && (int)$lockout_status === 1;
}

/************************************************************************
 * FUNCTION: REFRESH PERMISSIONS IN SESSIONS OF USER                    *
 * Forces a permission refresh on the active sessions of the user.      *
 * $uid: User id of the user whose sessions need to be force-refreshed. *
 ************************************************************************/
function refresh_permissions_in_sessions_of_user($uid) {

    $sid = session_id();

    $db = db_open();

    // Get the session ids that are not THIS session
    $stmt = $db->prepare("SELECT `id` FROM sessions where `id` <> :session_id and length(`data`) > 20;");
    $stmt->bindParam(":session_id", $sid);
    $stmt->execute();
    $session_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($session_ids)) {
        // Force-write current session changes
        session_write_close();

        // Iterate through the session ids
        foreach($session_ids as $session_id) {

            // Activate our target session
            session_id($session_id);
            session_start();

            // Refresh permissions if the user id matches the current user
            if (isset($_SESSION['uid']) && $_SESSION['uid'] == $uid && isset($_SESSION['user'])) {
                // Refresh user permissions for that session
                set_user_permissions($_SESSION['user']);
            }
            // Force-write current session changes
            session_write_close();
        }

        // Start our old session again
        session_id($sid);
        session_start();
    }

    db_close($db);
}

/**********************************
 * FUNCTION: GET USER BY USERNAME *
 **********************************/
function get_user_by_username($username, $include_permissions = false) {
    return get_user_by_id(get_id_by_user($username), $include_permissions);
}

/****************************
 * FUNCTION: GET USER BY ID *
 ****************************/
function get_user_by_id($id, $include_permissions = false)
{
    // Open the database connection
    $db = db_open();

    // Get the user information
    $stmt = $db->prepare("
        SELECT
            u.*, GROUP_CONCAT(DISTINCT `t`.`value`) as teams
        FROM
            `user` u
            LEFT JOIN `user_to_team` u2t ON `u2t`.`user_id` = `u`.`value`
            LEFT JOIN `team` t ON `u2t`.`team_id` = `t`.`value` OR `u`.`admin` = 1
        WHERE
            `u`.`value` = :value;
    ");
    $stmt->bindParam(":value", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    if (isset($array[0]) && isset($array[0]['value'])) {
        if ($array[0]['teams']) {
            $array[0]['teams'] = explode(',', $array[0]['teams']);
        }

        if ($include_permissions) {
            $array[0]['permissions'] = get_permissions_of_user($id);
        }

        return $array[0];
    }

    return false;
}

/****************************
 * FUNCTION: GET ID BY USER *
 ****************************/
function get_id_by_user($user)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Get the user information
        $stmt = $db->prepare("SELECT * FROM user WHERE LOWER(convert(`username` using utf8)) = LOWER(:user)");
    }
    else
    {
        $stmt = $db->prepare("SELECT * FROM user WHERE username = :user");
    }
    $stmt->bindParam(":user", $user, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return isset($array['value']) ? $array['value'] : 0;
}

/*******************************
 * FUNCTION: GET MAPPING VALUE *
 *******************************/
function core_get_mapping_value($prefix, $type, $mappings, $csv_line)
{
    // Create the search term
    $search_term = $prefix . $type;

    // Search mappings array for the search term
    $column = array_search($search_term, $mappings);

    // If the search term was mapped
    if ($column != false)
    {
        // Remove col_ to get the id value
        $key = (int)preg_replace("/^col_/", "", $column);

        // The value is located in that spot in the array
        $value = $csv_line[$key];

        // Return the value
        return trim($value);
    }
    else return null;
}

/*****************************
 * FUNCTION: GET OR ADD USER *
 *****************************/
function core_get_or_add_user($type, $mappings, $csv_line)
{
    // Get the mapping value
    $value = core_get_mapping_value("risks_", $type, $mappings, $csv_line);

    // Search the corresponding table for the value
    $value_id = get_value_by_name("user", $value);

    // If the value id was not found (the user does not exist)
    if (is_null($value_id))
    {
        // Get the value id for the Admin user instead
//        $value_id = get_value_by_name("user", "Admin");
        $value_id = 0;
    }

    // Return the value_id
    return $value_id;
}

/*****************************
 * FUNCTION: UPDATE PASSWORD *
 *****************************/
function update_password($user, $hash)
{
    // Open the database connection
    $db = db_open();

    // Update password
    $stmt = $db->prepare("UPDATE user SET password=:hash, last_password_change_date=NOW(), change_password=0 WHERE username=:user");
    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
    $stmt->bindParam(":hash", $hash, PDO::PARAM_STR, 60);
    $stmt->execute();

    //
    $uid = get_id_by_user($user);

    // Audit log
    $message = "Password was modified for the \"" . $user . "\" user.";
    write_log($uid + 1000, $uid, $message, "user");

    // Close the database connection
    db_close($db);

    return true;
}

/*************************
 * FUNCTION: SUBMIT RISK *
 *************************/
function submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source,  $category, $team, $technology, $owner, $manager, $assessment, $notes, $project_id = 0, $submitted_by=0, $submission_date=false, $additional_stakeholders=[], $risk_catalog_mapping=[], $threat_catalog_mapping=[], $template_group_id="")
{
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        if(!$template_group_id){
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];

        }
    }
    // In the database `submitted_by` is defaulted to 1 as that's the id of the pre-created Admin user, so it's defaulted to 1 here as well
    $submitted_by || ($submitted_by = $_SESSION['uid']) || ($submitted_by = 1);

    $owner || $owner = 0;

    // Limit the subject's length
    $maxlength = (int)get_setting('maximum_risk_subject_length', 300);
    if (strlen($subject) > $maxlength) {
        set_alert(true, "bad", _lang('RiskSubjectTruncated', ['limit' => $maxlength]));
        $subject = substr($subject, 0, $maxlength);
    }

    // Open the database connection
    $db = db_open();

    // Set numeric null to 0
    if ($location == NULL) $location = "";

    $risk_catalog_mapping = count($risk_catalog_mapping)?implode(",", $risk_catalog_mapping):"";
    $threat_catalog_mapping = count($threat_catalog_mapping)?implode(",", $threat_catalog_mapping):"";

    // Add the risk
    $sql = "INSERT INTO risks (`status`, `subject`, `reference_id`, `regulation`, `control_number`, `source`, `category`, `owner`, `manager`, `assessment`, `notes`, `project_id`, `submitted_by`, `submission_date`, `risk_catalog_mapping`, `threat_catalog_mapping`, `template_group_id`) VALUES (:status, :subject, :reference_id, :regulation, :control_number, :source, :category, :owner, :manager, :assessment, :notes, :project_id, :submitted_by, :submission_date, :risk_catalog_mapping, :threat_catalog_mapping, :template_group_id)";
    
    $try_encrypt_assessment = try_encrypt($assessment);
    $try_encrypt_notes = try_encrypt($notes);
    if($submission_date == false) $submission_date = date("Y-m-d H:i:s");

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":status", $status, PDO::PARAM_STR, 10);
    $encrypted_subject = try_encrypt($subject);
    $stmt->bindParam(":subject", $encrypted_subject, PDO::PARAM_STR);
    $stmt->bindParam(":reference_id", $reference_id, PDO::PARAM_STR, 20);
    $stmt->bindParam(":regulation", $regulation, PDO::PARAM_INT);
    $stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR, 50);
    $stmt->bindParam(":source", $source, PDO::PARAM_INT);
    $stmt->bindParam(":category", $category, PDO::PARAM_INT);
    $stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
    $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
    $stmt->bindParam(":assessment", $try_encrypt_assessment, PDO::PARAM_STR);
    $stmt->bindParam(":notes", $try_encrypt_notes, PDO::PARAM_STR);
    $stmt->bindParam(":project_id", $project_id, PDO::PARAM_STR);
    $stmt->bindParam(":submitted_by", $submitted_by, PDO::PARAM_INT);
    $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR);
    $stmt->bindParam(":risk_catalog_mapping", $risk_catalog_mapping, PDO::PARAM_STR);
    $stmt->bindParam(":threat_catalog_mapping", $threat_catalog_mapping, PDO::PARAM_STR);
    $stmt->bindParam(":template_group_id", $template_group_id, PDO::PARAM_INT);
    $stmt->execute();

    // Get the id of the risk
    $last_insert_id = $db->lastInsertId();

    // Save locations
    save_junction_values("risk_to_location", "risk_id", $last_insert_id, "location_id", $location);
    // Save teams
    save_junction_values("risk_to_team", "risk_id", $last_insert_id, "team_id", $team);
    // Save technologies
    save_junction_values("risk_to_technology", "risk_id", $last_insert_id, "technology_id", $technology);
    // Save additional stakeholders
    save_junction_values("risk_to_additional_stakeholder", "risk_id", $last_insert_id, "user_id", $additional_stakeholders);
    
    // Audit log
    $risk_id = (int)$last_insert_id + 1000;

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // If there is error in saving custom risk values, delete added risk and return false
        if(!save_risk_custom_field_values($risk_id))
        {
            // Delete just inserted risk
            delete_risk($last_insert_id);
            return false;
        }
    }

    if(jira_extra()) {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/jira/index.php'));

        CreateIssueForRisk($last_insert_id);
    }

    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        create_subject_order(isset($_SESSION['encrypted_pass']) && $_SESSION['encrypted_pass'] ? base64_decode($_SESSION['encrypted_pass']) : fetch_key());
    }


    // If there's no session we get the name of the submitter from the database
    $username = (isset($_SESSION) && !empty($_SESSION['user']) ? $_SESSION['user'] : get_name_by_value("user", $submitted_by));

    $message = "A new risk ID \"" . $risk_id . "\" was submitted by username \"" . $username . "\".";
    write_log($risk_id, $submitted_by, $message);

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
    return isset($array[0]['numeric_value']) ? $array[0]['numeric_value'] : 0;
}

/*********************************
 * FUNCTION: SUBMIT RISK SCORING *
 *********************************/
function submit_risk_scoring($last_insert_id, $scoring_method="5", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamage="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkill="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPDiscovery="10", $OWASPExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom="10", $ContributingLikelihood="", $ContributingImpacts=[])
{
    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {

        // Calculate the risk via classic method
        $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

        // Set default impact value 
        if(!$CLASSIC_impact)
        {
            $CLASSIC_impact = $GLOBALS['count_of_impacts'];
        }
        
        // Set default likelihood value 
        if(!$CLASSIC_likelihood)
        {
            $CLASSIC_likelihood = $GLOBALS['count_of_likelihoods'];
        }
        
        // Create the database query
        $stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact`) VALUES (:last_insert_id, :scoring_method, :calculated_risk, :CLASSIC_likelihood, :CLASSIC_impact)");
        $stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
        $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);

        // Add the risk score
        $stmt->execute();
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

        // Add the risk score
        $stmt->execute();
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

        // Add the risk score
        $stmt->execute();
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4){
        $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
        $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

        // Average the threat agent and vulnerability factors to get the likelihood
        $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;
    	if ($OWASP_likelihood >= 0 && $OWASP_likelihood < 3)
    	{
    		$OWASP_likelihood_name = "LOW";
    	}
    	else if ($OWASP_likelihood >= 3 && $OWASP_likelihood < 6)
    	{
    		$OWASP_likelihood_name = "MEDIUM";
    	}
    	else if ($OWASP_likelihood >= 6)
    	{
    		$OWASP_likelihood_name = "HIGH";
    	}

            $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
            $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

            // Average the technical and business impacts to get the impact
            $OWASP_impact = ($technical_impact + $business_impact)/2;
            if ($OWASP_impact >= 0 && $OWASP_impact < 3)
            {
                    $OWASP_impact_name = "LOW";
            }
            else if ($OWASP_impact >= 3 && $OWASP_impact < 6)
            {
                    $OWASP_impact_name = "MEDIUM";
            }
            else if ($OWASP_impact >= 6)
            {
                    $OWASP_impact_name = "HIGH";
            }

    	// Get the overall risk severity
    	if ($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "LOW")
    	{
    		// Set the calculated risk for a "Note" severity
    		$severity = "Note";
    		$calculated_risk = 0;
    	}
    	else if (($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "MEDIUM") || ($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "LOW"))
    	{
    		// Set the calculated risk for a "Low" severity as the average between Low and Medium
    		$severity = "Low";
    		$stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='Low' OR name='Medium') AS risk_level;");
    		$stmt->execute();
    		$risk_level = $stmt->fetch();
    		$calculated_risk = $risk_level['calculated_risk'];
    		$calculated_risk = round($risk_level['calculated_risk'], 1);
    	}
    	else if (($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "HIGH") || ($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "MEDIUM") || ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "LOW"))
    	{
    		// Set the calculated risk for a "Medium" severity as the average between Medium and High
    		$severity = "Medium";
    		$stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='Medium' OR name='High') AS risk_level;");
    		$stmt->execute();
    		$risk_level = $stmt->fetch();
    		$calculated_risk = $risk_level['calculated_risk'];
    		$calculated_risk = round($risk_level['calculated_risk'], 1);
    	}
    	else if (($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "HIGH") || ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "MEDIUM"))
    	{
    		// Set the calculated risk for a "High" severity as the average between High and Very High
    		$severity = "High";
    		$stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='High' OR name='Very High') AS risk_level;");
    		$stmt->execute();
    		$risk_level = $stmt->fetch();
    		$calculated_risk = round($risk_level['calculated_risk'], 1);
    	}
    	else if ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "HIGH")
    	{
    		// Set the calculated risk for a "Critical" severity
    		$severity = "Critical";
    		$calculated_risk = 10;
    	}

        // Calculate the overall OWASP risk score
        //$calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

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

        // Add the risk score
        $stmt->execute();
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5){
        // If the custom value is not between 0 and 10
        if (!(($custom >= 0) && ($custom <= 10)))
        {
            // Set the custom value to 10
            $custom = get_setting('default_risk_score');
        }

        // Calculated risk is the custom value
        $calculated_risk = $custom;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `Custom`) VALUES (:last_insert_id, :scoring_method, :calculated_risk, :Custom)");
        $stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);

        // Add the risk score
        $stmt->execute();
    }
    // If the scroing method is Contributing Risk (6)
    else if($scoring_method == 6){
        $max_likelihood = get_max_value("contributing_risks_likelihood");
        
        $ImpactSum = 0;
        foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
            $impacts = get_impact_values_from_contributing_risks_id($contributing_risk_id);
            $max_impact = max(array_column($impacts, 'value'));
            $weight = get_contributing_weight_by_id($contributing_risk_id);
            $ImpactSum += $weight * ($ContributingImpact * 5 / $max_impact);
        }
        
        // Set default Contributing Likelihood value
        $ContributingLikelihood = $ContributingLikelihood ? $ContributingLikelihood : $max_likelihood;
        $LikelihoodSum = $ContributingLikelihood * 5 / $max_likelihood;
        
        $calculated_risk = round($LikelihoodSum + $ImpactSum, 2);
        
        // Create the database query
        $stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `Contributing_Likelihood`) VALUES (:last_insert_id, :scoring_method, :calculated_risk, :Contributing_Likelihood)");
        $stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":Contributing_Likelihood", $ContributingLikelihood, PDO::PARAM_INT);
        $stmt->execute();
        
        // Save contributing impacts and contributing risk IDs
        foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
            // Create the database query
            $stmt = $db->prepare("INSERT INTO `risk_scoring_contributing_impacts` (`risk_scoring_id`, `contributing_risk_id`, `impact`) VALUES (:last_insert_id, :contributing_risk_id, :impact)");
            $stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":contributing_risk_id", $contributing_risk_id, PDO::PARAM_INT);
            $stmt->bindParam(":impact", $ContributingImpact, PDO::PARAM_INT);
            $stmt->execute();
        }
        
    }
    // Otherwise
    else
    {
        return false;
    }

    // Close the database connection
    db_close($db);

    // Add risk scoring history
    add_risk_scoring_history($last_insert_id, $calculated_risk);

    // Add residual risk scoring history
    $residual_risk = get_residual_risk($last_insert_id+1000);
    add_residual_risk_scoring_history($last_insert_id, $residual_risk);

    return true;
}

/**************************************
* FUNCTION: add_risk_scoring_history *
**************************************/
function add_risk_scoring_history($risk_id, $calculated_risk)
{
    // Open the database connection
    $db = db_open();

    // Check if row exists
    $stmt = $db->prepare("SELECT calculated_risk FROM risk_scoring_history WHERE risk_id = :risk_id order by last_update desc limit 1;");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if($result && $result[0] == $calculated_risk){
        return;
    }

    $last_update = date('Y-m-d H:i:s');
    // There is no entry like that, adding new one
    $stmt = $db->prepare("INSERT INTO risk_scoring_history (risk_id, calculated_risk, last_update) VALUES (:risk_id, :calculated_risk, :last_update);");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
    $stmt->bindParam(":last_update", $last_update, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************************
* FUNCTION: add_residual_risk_scoring_history *
***********************************************/
function add_residual_risk_scoring_history($risk_id, $residual_risk)
{
    // Open the database connection
    $db = db_open();

    // Check if row exists
    $stmt = $db->prepare("SELECT residual_risk FROM residual_risk_scoring_history WHERE risk_id = :risk_id order by last_update desc limit 1;");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if($result && $result[0] == $residual_risk){
        return;
    }

    $last_update = date('Y-m-d H:i:s');
    // There is no entry like that, adding new one
    $stmt = $db->prepare("INSERT INTO `residual_risk_scoring_history` (risk_id, residual_risk, last_update) VALUES (:risk_id, :residual_risk, :last_update);");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":residual_risk", $residual_risk, PDO::PARAM_STR);
    $stmt->bindParam(":last_update", $last_update, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************
 * FUNCTION: UPDATE CLASSIC SCORE *
 **********************************/
function update_classic_score($risk_id, $CLASSIC_likelihood, $CLASSIC_impact)
{
    // Get old calculated risk
    $old_calculated_risk = get_calculated_risk_by_id($risk_id);

    // Subtract 1000 from the risk_id
    $id = (int)$risk_id - 1000;

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

    // Display an alert
    set_alert(true, "good", "Risk scoring was updated successfully.");

    // Close the database connection
    db_close($db);

    // If risk score was changed
    if($old_calculated_risk != $calculated_risk)
    {
        // Add risk scoring history
        add_risk_scoring_history($id, $calculated_risk);

        // Add residual risk scoring history
        $residual_risk = get_residual_risk($id+1000);
        add_residual_risk_scoring_history($id, $residual_risk);

        // Audit log
        $message = "Risk score has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    return $calculated_risk;
}

/*******************************
 * FUNCTION: UPDATE CVSS SCORE *
 *******************************/
function update_cvss_score($risk_id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement)
{
    // Get old calculated risk
    $old_calculated_risk = get_calculated_risk_by_id($risk_id);

    // Subtract 1000 from the risk_id
    $id = (int)$risk_id - 1000;

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

    // Display an alert
    set_alert(true, "good", "Risk scoring was updated successfully.");

    // Close the database connection
    db_close($db);

    // If risk score was changed
    if($old_calculated_risk != $calculated_risk)
    {
        // Add risk scoring history
        add_risk_scoring_history($id, $calculated_risk);

        // Add residual risk scoring history
        $residual_risk = get_residual_risk($id+1000);
        add_residual_risk_scoring_history($id, $residual_risk);

        // Audit log
        $message = "Risk score has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    return $calculated_risk;
}

/********************************
 * FUNCTION: UPDATE DREAD SCORE *
 ********************************/
function update_dread_score($risk_id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability)
{
    // Get old calculated risk
    $old_calculated_risk = get_calculated_risk_by_id($risk_id);

    // Subtract 1000 from the risk_id
    $id = (int)$risk_id - 1000;

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

    // Display an alert
    set_alert(true, "good", "Risk scoring was updated successfully.");

    // Close the database connection
    db_close($db);

    // If risk score was changed
    if($old_calculated_risk != $calculated_risk)
    {
        // Add risk scoring history
        add_risk_scoring_history($id, $calculated_risk);

        // Add residual risk scoring history
        $residual_risk = get_residual_risk($id+1000);
        add_residual_risk_scoring_history($id, $residual_risk);

        // Audit log
        $message = "Risk score has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    return $calculated_risk;
}

/********************************
 * FUNCTION: UPDATE OWASP SCORE *
 ********************************/
function update_owasp_score($risk_id, $OWASPSkill, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPDiscovery, $OWASPExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation)
{
    // Get old calculated risk
    $old_calculated_risk = get_calculated_risk_by_id($risk_id);

    // Subtract 1000 from the risk_id
    $id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
    $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

    // Average the threat agent and vulnerability factors to get the likelihood
    $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;
        if ($OWASP_likelihood >= 0 && $OWASP_likelihood < 3)
        {
                $OWASP_likelihood_name = "LOW";
        }
        else if ($OWASP_likelihood >= 3 && $OWASP_likelihood < 6)
        {
                $OWASP_likelihood_name = "MEDIUM";
        }
        else if ($OWASP_likelihood >= 6)
        {
                $OWASP_likelihood_name = "HIGH";
        }

    $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
    $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

    // Average the technical and business impacts to get the impact
    $OWASP_impact = ($technical_impact + $business_impact)/2;
        if ($OWASP_impact >= 0 && $OWASP_impact < 3)
        {
                $OWASP_impact_name = "LOW";
        }
        else if ($OWASP_impact >= 3 && $OWASP_impact < 6)
        {
                $OWASP_impact_name = "MEDIUM";
        }
        else if ($OWASP_impact >= 6)
        {
                $OWASP_impact_name = "HIGH";
        }

        // Get the overall risk severity
        if ($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "LOW")
        {
                // Set the calculated risk for a "Note" severity
                $severity = "Note";
                $calculated_risk = 0;
        }
        else if (($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "MEDIUM") || ($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "LOW"))
        {
                // Set the calculated risk for a "Low" severity as the average between Low and Medium
                $severity = "Low";
                $stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='Low' OR name='Medium') AS risk_level;");
                $stmt->execute();
                $risk_level = $stmt->fetch();
                $calculated_risk = $risk_level['calculated_risk'];
                $calculated_risk = round($risk_level['calculated_risk'], 1);
        }
        else if (($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "HIGH") || ($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "MEDIUM") || ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "LOW"))
        {
                // Set the calculated risk for a "Medium" severity as the average between Medium and High
                $severity = "Medium";
                $stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='Medium' OR name='High') AS risk_level;");
                $stmt->execute();
                $risk_level = $stmt->fetch();
                $calculated_risk = $risk_level['calculated_risk'];
                $calculated_risk = round($risk_level['calculated_risk'], 1);
        }
        else if (($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "HIGH") || ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "MEDIUM"))
        {
                // Set the calculated risk for a "High" severity as the average between High and Very High
                $severity = "High";
                $stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='High' OR name='Very High') AS risk_level;");
                $stmt->execute();
                $risk_level = $stmt->fetch();
                $calculated_risk = round($risk_level['calculated_risk'], 1);
        }
        else if ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "HIGH")
        {
                // Set the calculated risk for a "Critical" severity
                $severity = "Critical";
                $calculated_risk = 10;
        }

    // Calculate the overall OWASP risk score
    //$calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

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

    // Display an alert
    set_alert(true, "good", "Risk scoring was updated successfully.");

    // Close the database connection
    db_close($db);

    // If risk score was changed
    if($old_calculated_risk != $calculated_risk)
    {
        // Add risk scoring history
        add_risk_scoring_history($id, $calculated_risk);

        // Add residual risk scoring history
        $residual_risk = get_residual_risk($id+1000);
        add_residual_risk_scoring_history($id, $residual_risk);

        // Audit log
        $message = "Risk score has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    return $calculated_risk;
}

/*********************************
 * FUNCTION: UPDATE CUSTOM SCORE *
 *********************************/
function update_custom_score($risk_id, $custom)
{
    // Get old calculated risk
    $old_calculated_risk = get_calculated_risk_by_id($risk_id);

    // Subtract 1000 from the risk_id
    $id = (int)$risk_id - 1000;

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

    // Display an alert
    set_alert(true, "good", "Risk scoring was updated successfully.");

    // Close the database connection
    db_close($db);

    // If risk score was changed
    if($old_calculated_risk != $calculated_risk)
    {
        // Add risk scoring history
        add_risk_scoring_history($id, $calculated_risk);

        // Add residual risk scoring history
        $residual_risk = get_residual_risk($id+1000);
        add_residual_risk_scoring_history($id, $residual_risk);

        // Audit log
        $message = "Risk score has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    return $calculated_risk;
}

/********************************************
 * FUNCTION: UPDATE CONTRIBUTING RISK SCORE *
 ********************************************/
function update_contributing_risk_score($risk_id, $ContributingLikelihood="", $ContributingImpacts=[])
{
    // Get old calculated risk
    $old_calculated_risk = get_calculated_risk_by_id($risk_id);

    // Subtract 1000 from the risk_id
    $id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    $max_likelihood = get_max_value("contributing_risks_likelihood");
    
    $ImpactSum = 0;
    foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
        $impacts = get_impact_values_from_contributing_risks_id($contributing_risk_id);
        $max_impact = max(array_column($impacts, 'value'));
        $weight = get_contributing_weight_by_id($contributing_risk_id);
        $ImpactSum += $weight * ($ContributingImpact * 5 / $max_impact);
    }
    
    // Set default Contributing Likelihood value
    $ContributingLikelihood = $ContributingLikelihood ? $ContributingLikelihood : $max_likelihood;
    $LikelihoodSum = $ContributingLikelihood * 5 / $max_likelihood;
    
    $calculated_risk = round($LikelihoodSum + $ImpactSum, 2);

    // Create the database query
    $stmt = $db->prepare("UPDATE risk_scoring SET calculated_risk=:calculated_risk, Contributing_Likelihood=:Contributing_Likelihood WHERE id=:id; ");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
    $stmt->bindParam(":Contributing_Likelihood", $ContributingLikelihood, PDO::PARAM_INT);
    // Add the risk score
    $stmt->execute();
    
    // Create the database query
    $stmt = $db->prepare("DELETE from risk_scoring_contributing_impacts WHERE risk_scoring_id=:id; ");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    // Delete existing all risk scoring contributing impacts
    $stmt->execute();
    
    // Save contributing impacts and contributing risk IDs
    foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
        // Create the database query
        $stmt = $db->prepare("INSERT INTO `risk_scoring_contributing_impacts` (`risk_scoring_id`, `contributing_risk_id`, `impact`) VALUES (:id, :contributing_risk_id, :impact); ");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":contributing_risk_id", $contributing_risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":impact", $ContributingImpact, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Display an alert
    set_alert(true, "good", "Risk scoring was updated successfully.");

    // Close the database connection
    db_close($db);

    // If risk score was changed
    if($old_calculated_risk != $calculated_risk)
    {
        // Add risk scoring history
        add_risk_scoring_history($id, $calculated_risk);

        // Add residual risk scoring history
        $residual_risk = get_residual_risk($id+1000);
        add_residual_risk_scoring_history($id, $residual_risk);

        // Audit log
        $message = "Risk score has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    return $calculated_risk;
}

/**************************************
 * FUNCTION: GET CALCULATE RISK BY ID *
 **************************************/
function get_calculated_risk_by_id($risk_id)
{
    $risk = get_risk_by_id($risk_id);
    if(isset($risk[0]['calculated_risk']))
    {
        $calculated_risk = $risk[0]['calculated_risk'];
    }
    else
    {
        $calculated_risk = 0;
    }

    return $calculated_risk;
}

/*********************************
 * FUNCTION: UPDATE RISK SCORING *
 *********************************/
function update_risk_scoring($risk_id, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkill, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPDiscovery, $OWASPExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood="", $ContributingImpacts=[])
{
    // Subtract 1000 from the id
    $id = (int)$risk_id - 1000;

    // Get old calculated risk
    $old_calculated_risk = get_calculated_risk_by_id($risk_id);

    // Open the database connection
    $db = db_open();

    // Get scoring method from db
    $stmt = $db->prepare("SELECT scoring_method FROM `risk_scoring` WHERE id = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $old_scoring_method = $stmt->fetchColumn();


    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
        // Calculate the risk via classic method
        $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

        // Create the database query
        $stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CLASSIC_likelihood=:CLASSIC_likelihood, CLASSIC_impact=:CLASSIC_impact WHERE id=:id; ");
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
        $stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CVSS_AccessVector=:CVSS_AccessVector, CVSS_AccessComplexity=:CVSS_AccessComplexity, CVSS_Authentication=:CVSS_Authentication, CVSS_ConfImpact=:CVSS_ConfImpact, CVSS_IntegImpact=:CVSS_IntegImpact, CVSS_AvailImpact=:CVSS_AvailImpact, CVSS_Exploitability=:CVSS_Exploitability, CVSS_RemediationLevel=:CVSS_RemediationLevel, CVSS_ReportConfidence=:CVSS_ReportConfidence, CVSS_CollateralDamagePotential=:CVSS_CollateralDamagePotential, CVSS_TargetDistribution=:CVSS_TargetDistribution, CVSS_ConfidentialityRequirement=:CVSS_ConfidentialityRequirement, CVSS_IntegrityRequirement=:CVSS_IntegrityRequirement, CVSS_AvailabilityRequirement=:CVSS_AvailabilityRequirement WHERE id=:id; ");
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
        $stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, DREAD_DamagePotential=:DREAD_DamagePotential, DREAD_Reproducibility=:DREAD_Reproducibility, DREAD_Exploitability=:DREAD_Exploitability, DREAD_AffectedUsers=:DREAD_AffectedUsers, DREAD_Discoverability=:DREAD_Discoverability WHERE id=:id; ");
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
        if ($OWASP_likelihood >= 0 && $OWASP_likelihood < 3)
        {
                $OWASP_likelihood_name = "LOW";
        }
        else if ($OWASP_likelihood >= 3 && $OWASP_likelihood < 6)
        {
                $OWASP_likelihood_name = "MEDIUM";
        }
        else if ($OWASP_likelihood >= 6)
        {
                $OWASP_likelihood_name = "HIGH";
        }

        $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
        $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

        // Average the technical and business impacts to get the impact
        $OWASP_impact = ($technical_impact + $business_impact)/2;
        if ($OWASP_impact >= 0 && $OWASP_impact < 3)
        {
                $OWASP_impact_name = "LOW";
        }
        else if ($OWASP_impact >= 3 && $OWASP_impact < 6)
        {
                $OWASP_impact_name = "MEDIUM";
        }
        else if ($OWASP_impact >= 6)
        {
                $OWASP_impact_name = "HIGH";
        }

        // Get the overall risk severity
        if ($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "LOW")
        {
                // Set the calculated risk for a "Note" severity
                $severity = "Note";
                $calculated_risk = 0;
        }
        else if (($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "MEDIUM") || ($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "LOW"))
        {
                // Set the calculated risk for a "Low" severity as the average between Low and Medium
                $severity = "Low";
                $stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='Low' OR name='Medium') AS risk_level;");
                $stmt->execute();
                $risk_level = $stmt->fetch();
                $calculated_risk = $risk_level['calculated_risk'];
                $calculated_risk = round($risk_level['calculated_risk'], 1);
        }
        else if (($OWASP_likelihood_name == "LOW" && $OWASP_impact_name == "HIGH") || ($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "MEDIUM") || ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "LOW"))
        {
                // Set the calculated risk for a "Medium" severity as the average between Medium and High
                $severity = "Medium";
                $stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='Medium' OR name='High') AS risk_level;");
                $stmt->execute();
                $risk_level = $stmt->fetch();
                $calculated_risk = $risk_level['calculated_risk'];
                $calculated_risk = round($risk_level['calculated_risk'], 1);
        }
        else if (($OWASP_likelihood_name == "MEDIUM" && $OWASP_impact_name == "HIGH") || ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "MEDIUM"))
        {
                // Set the calculated risk for a "High" severity as the average between High and Very High
                $severity = "High";
                $stmt = $db->prepare("SELECT AVG(value) AS calculated_risk FROM (SELECT value FROM risk_levels WHERE name='High' OR name='Very High') AS risk_level;");
                $stmt->execute();
                $risk_level = $stmt->fetch();
                $calculated_risk = round($risk_level['calculated_risk'], 1);
        }
        else if ($OWASP_likelihood_name == "HIGH" && $OWASP_impact_name == "HIGH")
        {
                // Set the calculated risk for a "Critical" severity
                $severity = "Critical";
                $calculated_risk = 10;
        }

        // Calculate the overall OWASP risk score
        //$calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

        // Create the database query
        $stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, OWASP_SkillLevel=:OWASP_SkillLevel, OWASP_Motive=:OWASP_Motive, OWASP_Opportunity=:OWASP_Opportunity, OWASP_Size=:OWASP_Size, OWASP_EaseOfDiscovery=:OWASP_EaseOfDiscovery, OWASP_EaseOfExploit=:OWASP_EaseOfExploit, OWASP_Awareness=:OWASP_Awareness, OWASP_IntrusionDetection=:OWASP_IntrusionDetection, OWASP_LossOfConfidentiality=:OWASP_LossOfConfidentiality, OWASP_LossOfIntegrity=:OWASP_LossOfIntegrity, OWASP_LossOfAvailability=:OWASP_LossOfAvailability, OWASP_LossOfAccountability=:OWASP_LossOfAccountability, OWASP_FinancialDamage=:OWASP_FinancialDamage, OWASP_ReputationDamage=:OWASP_ReputationDamage, OWASP_NonCompliance=:OWASP_NonCompliance, OWASP_PrivacyViolation=:OWASP_PrivacyViolation WHERE id=:id; ");
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
        $stmt = $db->prepare("UPDATE risk_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, Custom=:Custom WHERE id=:id; ");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
    }
    // If the scoring method is Contributing Risk (6)
    else if ($scoring_method == 6)
    {
        $calculated_risk = update_contributing_risk_score($id+1000, $ContributingLikelihood, $ContributingImpacts);
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

    // If scoring method was changed
    if($old_scoring_method != $scoring_method)
    {
        // Audit log
        $message = "Scoring method has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    // If risk score was changed
    if($old_calculated_risk != $calculated_risk)
    {
        // Add risk scoring history
        add_risk_scoring_history($id, $calculated_risk);

        // Add residual risk scoring history
        $residual_risk = get_residual_risk($id+1000);
        add_residual_risk_scoring_history($id, $residual_risk);

        // Audit log
        $message = "Risk score has been updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    return $calculated_risk;
}

/**************************************
 * FUNCTION: SAVE MITIGATION CONTROLS *
 **************************************/
function save_mitigation_controls($mitigation_id, $control_ids, $post = array())
{
    $control_ids = is_array($control_ids) ? $control_ids : explode(",", $control_ids);
    // Open the database connection
    $db = db_open();

    // Delete existing mitigation by risk ID
    $stmt = $db->prepare("DELETE FROM `mitigation_to_controls` WHERE mitigation_id = :mitigation_id");
    $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
    $stmt->execute();
    
    foreach($control_ids as $control_id)
    {
        $validation_details = isset($post["validation_details_".$control_id])?$post["validation_details_".$control_id]:"";
        $validation_owner = isset($post["validation_owner_".$control_id])?$post["validation_owner_".$control_id]:0;
        $validation_mitigation_percent = isset($post["validation_mitigation_percent_".$control_id])?$post["validation_mitigation_percent_".$control_id]:0;
        $stmt = $db->prepare("INSERT INTO `mitigation_to_controls`(mitigation_id, control_id, validation_details, validation_owner, validation_mitigation_percent) VALUES(:mitigation_id, :control_id, :validation_details, :validation_owner, :validation_mitigation_percent); ");
        $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
        $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
        $stmt->bindParam(":validation_details", $validation_details, PDO::PARAM_STR);
        $stmt->bindParam(":validation_owner", $validation_owner, PDO::PARAM_INT);
        $stmt->bindParam(":validation_mitigation_percent", $validation_mitigation_percent, PDO::PARAM_INT);
        $stmt->execute();

        // Sanitizing list of ids 
        $file_ids = !empty($post['file_ids_' . $control_id]) ? sanitize_int_array($post['file_ids_' . $control_id]) : [];
        refresh_files_for_validation($mitigation_id, $control_id, $file_ids);

        // If a artifact file was submitted
        if (!empty($_FILES['artifact-file-'.$control_id]))
        {
            $files = $_FILES['artifact-file-'.$control_id];
            // Upload any file that is submitted
            for($i=0; $i<count($files['name']); $i++){
                if($files['error'][$i] || $i==0){
                    continue;
                }
                $file = array(
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i],
                    'error' => $files['error'][$i],
                );
                // Upload any file that is submitted
                $error = upload_validation_file($mitigation_id, $control_id, $file);
                if($error != 1){
                    /**
                    * If error, stop uploading files;
                    */
                    break;
                }
            }

        }
    }

    // Close the database connection
    db_close($db);
}

/*******************************
 * FUNCTION: SUBMIT MITIGATION *
 *******************************/
function submit_mitigation($risk_id, $status, $post, $submitted_by_id=false)
{
    if($submitted_by_id === false){
        $submitted_by_id = $_SESSION['uid'];
    }
    // Subtract 1000 from id
    $id = (int)$risk_id - 1000;

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // Save custom fields
        save_risk_custom_field_values($risk_id);
    }

    $planning_strategy          = isset($post['planning_strategy']) ? (int)$post['planning_strategy'] : 0;
    $mitigation_effort          = isset($post['mitigation_effort']) ? (int)$post['mitigation_effort'] : 0;
    $mitigation_cost            = isset($post['mitigation_cost']) ? (int)$post['mitigation_cost'] : 0;
    $mitigation_owner           = isset($post['mitigation_owner']) ? (int)$post['mitigation_owner'] : 0;
    if(isset($post['mitigation_team']))
    {
        $mitigation_team = $post['mitigation_team'];
    }
    else
    {
        $mitigation_team = [];
    }
    $current_solution           = isset($post['current_solution']) ? $post['current_solution'] : "";
    $current_solution  = try_encrypt($current_solution);

    $security_requirements      = isset($post['security_requirements']) ? $post['security_requirements'] : "";
    $security_requirements  = try_encrypt($security_requirements);

    $security_recommendations   = isset($post['security_recommendations']) ? $post['security_recommendations'] : "";
    $security_recommendations   = try_encrypt($security_recommendations);

    $planning_date              = isset($post['planning_date']) ? $post['planning_date'] : "";
    $mitigation_date            = isset($post['mitigation_date']) ? $post['mitigation_date'] : date(get_default_datetime_format());

    // Convert to standard date
    $mitigation_date            = get_standard_date_from_default_format($mitigation_date, true);

    $mitigation_percent         = (isset($post['mitigation_percent']) && $post['mitigation_percent'] >= 0 && $post['mitigation_percent'] <= 100) ? $post['mitigation_percent'] : 0;
    
    $mitigation_controls        = empty($post['mitigation_controls']) ? [] : $post['mitigation_controls'];
//    $mitigation_controls        = is_array($mitigation_controls) ? implode(",", $mitigation_controls) : $mitigation_controls;

    if (!validate_date($planning_date, get_default_date_format()))
    {
        $planning_date = "0000-00-00";
    }
    // Otherwise, set the proper format for submitting to the database
    else
    {
        $planning_date = get_standard_date_from_default_format($planning_date);
    }

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Open the database connection
    $db = db_open();


    // Delete existing mitigation by risk ID
    $stmt = $db->prepare("DELETE FROM mitigations WHERE risk_id = :risk_id");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Add the mitigation
    $stmt = $db->prepare("INSERT INTO mitigations (`risk_id`, `planning_strategy`, `mitigation_effort`, `mitigation_cost`, `mitigation_owner`, `current_solution`, `security_requirements`, `security_recommendations`, `submitted_by`, `planning_date`, `submission_date`, `mitigation_percent`) VALUES (:risk_id, :planning_strategy, :mitigation_effort, :mitigation_cost, :mitigation_owner, :current_solution, :security_requirements, :security_recommendations, :submitted_by, :planning_date, :submission_date, :mitigation_percent)");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":planning_strategy", $planning_strategy, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_effort", $mitigation_effort, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_cost", $mitigation_cost, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_owner", $mitigation_owner, PDO::PARAM_INT);
    $stmt->bindParam(":current_solution", $current_solution, PDO::PARAM_STR);
    $stmt->bindParam(":security_requirements", $security_requirements, PDO::PARAM_STR);
    $stmt->bindParam(":security_recommendations", $security_recommendations, PDO::PARAM_STR);
    $stmt->bindParam(":submitted_by", $submitted_by_id, PDO::PARAM_INT);
    $stmt->bindParam(":planning_date", $planning_date, PDO::PARAM_STR, 10);
    $stmt->bindParam(":submission_date", $mitigation_date, PDO::PARAM_STR, 10);
    $stmt->bindParam(":mitigation_percent", $mitigation_percent, PDO::PARAM_INT);
    $stmt->execute();

    // Get the new mitigation id
    $mitigation_id = get_mitigation_id($id);
    
    // Save mitigation controls
    save_mitigation_controls($mitigation_id, $mitigation_controls, $post);
    
    // Save mitigation teams
    save_junction_values("mitigation_to_team", "mitigation_id", $mitigation_id, "team_id", $mitigation_team);

    // Update the risk status and last_update
    $stmt = $db->prepare("UPDATE risks SET status=:status, last_update=:last_update, mitigation_id=:mitigation_id WHERE id = :risk_id");
    $stmt->bindParam(":status", $status, PDO::PARAM_STR, 20);
    $stmt->bindParam(":last_update", $current_datetime, PDO::PARAM_STR, 20);
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);

    $stmt->execute();

    // If notification is enabled
    if (notification_extra())
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_new_mitigation($id);
    }

    // Audit log
    $message = "A mitigation was submitted for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);


    /***** upload files ******/
    // If the delete value exists
    if (!empty($post['delete']))
    {
        // For each file selected
        foreach ($post['delete'] as $file)
        {
            // Delete the file
            delete_db_file($file);
        }
    }
    $unique_names = empty($post['unique_names']) ? "" : $post['unique_names'];
    refresh_files_for_risk($unique_names, $id, 2);

    $error = 1;
    // If a file was submitted
    if (!empty($_FILES['file']))
    {
        // Upload any file that is submitted
        for($i=0; $i<count($_FILES['file']['name']); $i++){
            if($_FILES['file']['error'][$i] || $i==0){
                continue;
            }
            $file = array(
                'name' => $_FILES['file']['name'][$i],
                'type' => $_FILES['file']['type'][$i],
                'tmp_name' => $_FILES['file']['tmp_name'][$i],
                'size' => $_FILES['file']['size'][$i],
                'error' => $_FILES['file']['error'][$i],
            );
            // Upload any file that is submitted
            $error = upload_file($id, $file, 2);
            if($error != 1){
                /**
                * If error, stop uploading files;
                */
                break;
            }
        }

    }
    // Otherwise, success
    else $error = 1;
    /****** end uploading files *******/

    // Add residual risk score
    $residual_risk = get_residual_risk((int)$id + 1000);
    add_residual_risk_scoring_history($id, $residual_risk);

    return $error;
}
/*********************************
 * FUNCTION: SUBMIT UNMITIGATION *
 *********************************/
function submit_unmitigation($risk_id)
{
    // Subtract 1000 from id
    $id = (int)$risk_id - 1000;

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');
    $error = 1;

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT * FROM mitigations WHERE risk_id=:risk_id");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $mitigation_id = $row?$row["id"]:"";

    // Delete existing mitigation by risk ID
    $stmt = $db->prepare("DELETE FROM mitigations WHERE risk_id = :risk_id");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete mitigation controls
    $stmt = $db->prepare("DELETE FROM `mitigation_to_controls` WHERE mitigation_id = :mitigation_id");
    $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete mitigation teams
    $stmt = $db->prepare("DELETE FROM `mitigation_to_team` WHERE mitigation_id = :mitigation_id");
    $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
    $stmt->execute();

    $current_status = get_risk_status($risk_id);
    if($current_status == "Mitigation Planned") $status = "New";
    else $status = $current_status;
   
    // Update the risk status and last_update
    $stmt = $db->prepare("UPDATE risks SET status=:status, last_update=:last_update, mitigation_id='' WHERE id = :risk_id");
    $stmt->bindParam(":status", $status, PDO::PARAM_STR, 20);
    $stmt->bindParam(":last_update", $current_datetime, PDO::PARAM_STR, 20);
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);

    $stmt->execute();

    // Audit log
    $message = "A mitigation was deleted for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return $error;
}

/**************************************
 * FUNCTION: SUBMIT MANAGEMENT REVIEW *
 **************************************/
function submit_management_review($risk_id, $status, $review, $next_step, $reviewer, $comments, $next_review, $close=false, $submission_date = false)
{

    if(is_null($review)){
        $review = 0;
    }

    if(is_null($next_step)){
        $next_step = 0;
    }

    if(is_null($reviewer)){
        $reviewer = 0;
    }

    if(is_null($comments)){
        $comments = "";
    }

    if(is_null($next_review)){
        $next_review = "0000-00-00";
    }

    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Open the database connection
    $db = db_open();

    if(!$submission_date || !validate_date($submission_date)){
        $submission_date = date("Y-m-d H:i:s");
    }

    // Add the review
    $stmt = $db->prepare("INSERT INTO mgmt_reviews (`risk_id`, `review`, `reviewer`, `next_step`, `comments`, `next_review`, `submission_date`) VALUES (:risk_id, :review, :reviewer, :next_step, :comments, :next_review, :submission_date)");
    
    $try_encrypt_comments = try_encrypt($comments);

    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":review", $review, PDO::PARAM_INT);
    $stmt->bindParam(":reviewer", $reviewer, PDO::PARAM_INT);
    $stmt->bindParam(":next_step", $next_step, PDO::PARAM_INT);
    $stmt->bindParam(":comments", $try_encrypt_comments, PDO::PARAM_STR);
    $stmt->bindParam(":next_review", $next_review, PDO::PARAM_STR, 10);
    $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR, 20);

    $stmt->execute();

    // Get the new mitigation id
    $review_id = get_review_id($id);

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // Save custom fields
        save_risk_custom_field_values($risk_id, $review_id);
    }

    if(!is_null($status)){
        // Update the risk status and last_update
        $stmt = $db->prepare("UPDATE risks SET status=:status, last_update=:last_update, review_date=:review_date, mgmt_review=:mgmt_review WHERE id = :risk_id");
        $stmt->bindParam(":status", $status, PDO::PARAM_STR, 20);
        $stmt->bindParam(":last_update", $current_datetime, PDO::PARAM_STR, 20);
        $stmt->bindParam(":review_date", $current_datetime, PDO::PARAM_STR, 20);
        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":mgmt_review", $review_id, PDO::PARAM_INT);

        $stmt->execute();
    }

    // If this is not a risk closure
    if (!$close)
    {
        // If notification is enabled
        if (notification_extra())
        {
            // Include the notification extra
            require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

            // Send the notification
            notify_new_review($id);
        }

        // Audit log
        $message = "A management review was submitted for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    $review_id = $db->lastInsertId();
    
    // Close the database connection
    db_close($db);

    return $review_id;
}
/****************************************
 * FUNCTION: SUBMIT MANAGEMENT UNREVIEW *
 ****************************************/
function submit_management_unreview($risk_id)
{
    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Open the database connection
    $db = db_open();

    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;

    // Delete existing reivew by risk ID
    $stmt = $db->prepare("DELETE FROM mgmt_reviews WHERE risk_id = :risk_id");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $current_status = get_risk_status($risk_id);
    if($current_status == "Mgmt Reviewed") $status = "New";
    else $status = $current_status;

    // Update the risk status and last_update
    $stmt = $db->prepare("UPDATE risks SET status=:status, last_update=:last_update, review_date=:review_date, mgmt_review=0 WHERE id = :risk_id");
    $stmt->bindParam(":status", $status, PDO::PARAM_STR, 20);
    $stmt->bindParam(":last_update", $current_datetime, PDO::PARAM_STR, 20);
    $stmt->bindParam(":review_date", $current_datetime, PDO::PARAM_STR, 20);
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);

    $stmt->execute();
   
    // Audit log
    $message = "A management review was deleted for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);
    // Close the database connection
    db_close($db);

    return true;
}

/*************************
 * FUNCTION: UPDATE RISK *
 *************************/
function update_risk($risk_id, $is_api = false)
{
	global $lang, $escaper;
    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;

    $tags = get_param("POST", "tags", []);

    if ($tags) {
        foreach($tags as $tag){
            if (strlen($tag) > 255) {
                global $lang;

                return $lang['MaxTagLengthWarning'];
            }
        }
    }
    
    
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // Save custom fields
        if(!save_risk_custom_field_values($risk_id)) return $escaper->escapeHtml($lang['InvalidParams']);
    }

    $reference_id           = get_param("post", 'reference_id', false);
    $regulation             = get_param("post", "regulation", false);
    if($regulation !== false){
        $regulation = (int)$regulation;
    }
    $control_number         = get_param("post", "control_number", false);
    $location               = get_param("post", "location", []);
    
    $source                 = get_param("post", "source", false);
    if($source !== false){
        $source = (int)$source;
    }
    $category               = get_param("post", "category", false);
    if($category !== false){
        $category = (int)$category;
    }
    $team                   = get_param("post", "team", false);
    // If form data was submitted.
    if($is_api === false && $team === false){
        $team = [];
    }

    $additional_stakeholders = get_param("post", "additional_stakeholders", false);
    // If form data was submitted.
    if($is_api === false && $additional_stakeholders === false){
        $additional_stakeholders = "";
    }

    $technology             = get_param("post", "technology", false);
    // If form data was submitted.
    if($is_api === false && $technology === false){
        $technology = "";
    }

    $owner                  = get_param("post", "owner", false);
    if($owner !== false){
        $owner = (int)$owner;
    }
    $manager                = get_param("post", "manager", false);
    if($manager !== false){
        $manager = (int)$manager;
    }
    $assessment             = get_param("post", "assessment", false);
    if($assessment !== false){
        $assessment = try_encrypt($assessment);
    }
    $notes                  = get_param("post", "notes", false);
    if($notes !== false){
        $notes = try_encrypt($notes);
    }
    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    $submission_date        = get_param("post", "submission_date", false);
    $risk = get_risk_by_id($risk_id);
    if($submission_date != false){
        $submission_date        =  get_standard_date_from_default_format($submission_date);
        if($risk[0]){
            $existing_submission_date = date('Y-m-d', strtotime($risk[0]['submission_date']));
            if($existing_submission_date == $submission_date) $submission_date = $risk[0]['submission_date'];
        }
    } elseif($submission_date == ""){
        if($risk[0]){
            $existing_submission_date = date('Y-m-d', strtotime($risk[0]['submission_date']));
            $submission_date = $existing_submission_date;
        } else $submission_date = $current_datetime;
    }
    $risk_catalog_mapping = get_param("post", "risk_catalog_mapping", []);
	$risk_catalog_mapping = count($risk_catalog_mapping)?implode(",", $risk_catalog_mapping):"";

    $threat_catalog_mapping = get_param("post", "threat_catalog_mapping", []);
    $threat_catalog_mapping = count($threat_catalog_mapping)?implode(",", $threat_catalog_mapping):"";

    $data = array(
        "reference_id"      =>$reference_id,
        "regulation"        =>$regulation,
        "control_number"    =>$control_number,
        "source"            =>$source,
        "category"          =>$category,
        "owner"             =>$owner,
        "manager"           =>$manager,
        "assessment"        =>$assessment,
        "notes"             =>$notes,
        "last_update"       =>$current_datetime,
        "submission_date"   =>$submission_date,
        "risk_catalog_mapping"   =>$risk_catalog_mapping,
        "threat_catalog_mapping"   =>$threat_catalog_mapping
    );

    // Open the database connection
    $db = db_open();
    $risk = get_risk_by_id($risk_id);
    $updated_fields = [];

    $sql = "UPDATE risks SET ";
    foreach($data as $key => $value){
        if($value !== false)
            $sql .= " {$key}=:{$key}, ";
        // find updated field
        if($key=="assessment" || $key=="notes") {
            if(try_decrypt($value) != try_decrypt($risk[0][$key])) {
                $updated_fields[$key]["original"] = try_decrypt($risk[0][$key]);
                $updated_fields[$key]["updated"] = try_decrypt($value);
            }
        } else if($value != $risk[0][$key] && $key != "last_update") {
            switch($key)
            {
                default:
                    $original_value = $risk[0][$key];
                    $updated_value = $value;
                break;
                case "source":
                    $original_value = get_table_value_by_id("source", $risk[0][$key]);
                    $updated_value = get_table_value_by_id("source", $value);
                break;
                case "category":
                    $original_value = get_table_value_by_id("category", $risk[0][$key]);
                    $updated_value = get_table_value_by_id("category", $value);
                break;
                case "regulation":
                    $original_value = try_decrypt(get_table_value_by_id("frameworks", $risk[0][$key]));
                    $updated_value = try_decrypt(get_table_value_by_id("frameworks", $value));
                break;
                case "owner":
                case "manager":
                    $user_original = get_user_by_id($risk[0][$key]);
                    $user_updated = get_user_by_id($value);
                    $original_value = $user_original ? $user_original["name"] : '';
                    $updated_value = $user_updated ? $user_updated["name"] : '';
                break;
            }
            $updated_fields[$key]["original"] = $original_value;
            $updated_fields[$key]["updated"] = $updated_value;
        }
    }
    $sql = trim($sql, ", ");
    $sql .= " WHERE id = :id ";

    // Update the risk
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    foreach($data as $key => $value){
        if($value !== false){
            $stmt->bindParam(":{$key}", $data[$key]);
        }
        unset($value);
    }

    $stmt->execute();
    
    // Save locations
    save_junction_values("risk_to_location", "risk_id", $id, "location_id", $location);
    // Save teams
    save_junction_values("risk_to_team", "risk_id", $id, "team_id", $team);
    // Save technologies
    save_junction_values("risk_to_technology", "risk_id", $id, "technology_id", $technology);
    // Save additional stakeholders
    save_junction_values("risk_to_additional_stakeholder", "risk_id", $id, "user_id", $additional_stakeholders);

    updateTagsOfType($id, 'risk', $tags);

    if($is_api === false) {
        if (isset($_POST['assets_asset_groups'])) {
            $assets_asset_groups = is_array($_POST['assets_asset_groups']) ? $_POST['assets_asset_groups'] : [];
            // Update affected assets and asset groups
            process_selected_assets_asset_groups_of_type($id, $assets_asset_groups, 'risk');
        }
    } else {
        $affected_assets = get_param("POST", 'affected_assets');

        if ($affected_assets)
            import_assets_asset_groups_for_type($id, $affected_assets, 'risk');
    }

    // If notification is enabled
    if (notification_extra())
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_risk_update($id);
    }

    // Audit log
    if(count($updated_fields)) {
        $detail_updated = [];
        foreach ($updated_fields as $key => $value) {
            $detail_updated[] = "Field name : `".$key. "` (`".$value["original"]."`=>`".$value["updated"]."`)";
        }
        $updated_string = implode(", ", $detail_updated);
    } else $updated_string = "";
    $message = "Risk details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".\n".$updated_string;
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    // If the delete value exists
    if (!empty($_POST['delete']))
    {
      // For each file selected
      foreach ($_POST['delete'] as $file)
      {
        // Delete the file
        delete_db_file($file);
      }
    }
    $unique_names = empty($_POST['unique_names']) ? "" : $_POST['unique_names'];
    refresh_files_for_risk($unique_names, $id, 1);

    $success = 1;
    // If a file was submitted
    if (!empty($_FILES))
    {
      // Upload any file that is submitted
        for($i=0; $i<count($_FILES['file']['name']); $i++){
            if($_FILES['file']['error'][$i] || $i==0){
               continue;
            }
            $file = array(
                'name' => $_FILES['file']['name'][$i],
                'type' => $_FILES['file']['type'][$i],
                'tmp_name' => $_FILES['file']['tmp_name'][$i],
                'size' => $_FILES['file']['size'][$i],
                'error' => $_FILES['file']['error'][$i],
            );
            // Upload any file that is submitted
            // If there are errors, it returns error messages.
            $success = upload_file($id, $file, 1);
            if($success != 1){
                /**
                * If error, stop uploading files;
                */
                break;
            }
        }

//      $error = upload_file($id-1000, $_FILES['file'], 1);
    }
    // Otherwise, success
    else $success = 1;
    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

//        create_subject_order(isset($_SESSION['encrypted_pass']) && $_SESSION['encrypted_pass'] ? $_SESSION['encrypted_pass'] : fetch_key());
    }

    return $success;
}

/******************************************
 * FUNCTION: GET RESIDUAL RISK BY RISK ID *
 ******************************************/
function get_residual_risk($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $risk_id = (int)$risk_id - 1000;

    // Query the database
    $stmt = $db->prepare("
        SELECT t2.calculated_risk, GREATEST(IFNULL(t3.mitigation_percent, 0), IFNULL(MAX(t4.mitigation_percent), 0)) AS mitigation_percent
        FROM risks t1
            LEFT JOIN risk_scoring t2 ON t1.id=t2.id
            LEFT JOIN mitigations t3 ON t1.id=t3.risk_id
            LEFT JOIN mitigation_to_controls mtc ON t3.id=mtc.mitigation_id
            LEFT JOIN framework_controls t4 ON mtc.control_id=t4.id AND t4.deleted=0
        WHERE t1.id=:risk_id
        GROUP BY t1.id;
    ");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    $risk = $stmt->fetch(PDO::FETCH_ASSOC);

    $risk['calculated_risk'] = empty($risk['calculated_risk']) ? 0 : $risk['calculated_risk'];
    $risk['mitigation_percent'] = empty($risk['mitigation_percent']) ? 0 : $risk['mitigation_percent'];

    $residual_risk = round($risk['calculated_risk'] * (100-$risk['mitigation_percent']) / 100, 2);

    // Close the database connection
    db_close($db);

    return $residual_risk ? $residual_risk : "0.0";
}

/*********************************
 * FUNCTION: UPDATE RISK SUBJECT *
 *********************************/
function update_risk_subject($risk_id, $subject)
{
    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Get current datetime for last_update
    $current_datetime = date("Y-m-d H:i:s");

    // Update the risk
    $stmt = $db->prepare("UPDATE risks SET subject=:subject, last_update=:date WHERE id = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":subject", $subject, PDO::PARAM_STR);
    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
    $stmt->execute();

    // Audit log
    $message = "Risk subject was updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        create_subject_order(isset($_SESSION['encrypted_pass']) && $_SESSION['encrypted_pass'] ? base64_decode($_SESSION['encrypted_pass']) : fetch_key());
    }

    // If notification is enabled
    if (notification_extra())
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_risk_update($id);
    }
}

/************************
 * FUNCTION: CONVERT ID *
 ************************/
function convert_id($id)
{
    // Add 1000 to any id to make it at least 4 digits
    $id = (int)$id + 1000;

    return $id;
}

/****************************************
 * FUNCTION: CHECK IF A RISK EXIST BY ID*
 ****************************************/
function check_risk_by_id($id){
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $id = (int)$id - 1000;

    // Query the database
    $stmt = $db->prepare("SELECT b.* FROM risk_scoring a INNER JOIN risks b on a.id = b.id WHERE b.id=:id LIMIT 1");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    if($array){
        return true;
    }else{
        return false;
    }
}

/************************************
 * FUNCTION: CHECK RISK ID IS VALID *
 ************************************/
function check_risk_id($id)
{
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $id = (int)$id - 1000;

    // Query the database
    $stmt = $db->prepare("SELECT a.* FROM risks a WHERE a.id=:id;");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array ? true : false;
}

/****************************
 * FUNCTION: GET RISK BY ID *
 ****************************/
function get_risk_by_id($id)
{
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $id = (int)$id - 1000;

    // If the team separation extra is enabled
    if (team_separation_extra()) {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the separation query string
        $separation_query = get_user_teams_query("b", false, true);
    } else
        $separation_query = "";
    
    // Query the database
    $stmt = $db->prepare("
        SELECT
            a.*,
            group_concat(distinct CONCAT_WS('_', rsci.contributing_risk_id, rsci.impact)) as Contributing_Risks_Impacts,
            b.*,
            c.next_review,
            ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
            GROUP_CONCAT(DISTINCT t.tag ORDER BY t.tag ASC SEPARATOR '|') as risk_tags
            " . (jira_extra() ?
            ",ji.issue_key as jira_issue_key,
            ji.last_sync as jira_last_sync,
            ji.project_key as jira_project_key" : "") . ",
            group_concat(distinct rtl.location_id) location,
            group_concat(distinct l.name) location_names,
            group_concat(distinct rtt.team_id) team,
            group_concat(distinct team.name) team_names,
            group_concat(distinct rttg.technology_id) technology,
            group_concat(distinct technology.name) technology_names,
            group_concat(distinct rtas.user_id) additional_stakeholders,
            group_concat(distinct adsh.name) additional_stakeholder_names
        FROM
            risk_scoring a
            INNER JOIN risks b on a.id = b.id
            LEFT JOIN mgmt_reviews c on b.mgmt_review = c.id
            LEFT JOIN mitigations mg ON b.id = mg.risk_id
            LEFT JOIN `mitigation_to_controls` mtc ON mg.id = mtc.mitigation_id
            LEFT JOIN framework_controls fc ON mtc.control_id = fc.id AND fc.deleted=0
            LEFT JOIN risk_scoring_contributing_impacts rsci ON a.id=rsci.risk_scoring_id
            LEFT JOIN tags_taggees tt ON tt.taggee_id = b.id and tt.type = 'risk'
            LEFT JOIN tags t on t.id = tt.tag_id
            " . (jira_extra() ? "LEFT JOIN jira_issues ji on ji.risk_id = b.id" : "") . "

            LEFT JOIN risk_to_location rtl on b.id=rtl.risk_id
            LEFT JOIN location l on rtl.location_id=l.value
            LEFT JOIN risk_to_team rtt on b.id=rtt.risk_id
            LEFT JOIN team on rtt.team_id=team.value
            LEFT JOIN risk_to_technology rttg on b.id=rttg.risk_id
            LEFT JOIN technology on rttg.technology_id=technology.value
            LEFT JOIN risk_to_additional_stakeholder rtas on b.id=rtas.risk_id
            LEFT JOIN user adsh on rtas.user_id=adsh.value
        WHERE
            b.id=:id
            " . $separation_query . "
        GROUP BY
            b.id
        LIMIT 1;
    ");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array && $array[0]['id'] ? $array : [];
}

/********************************
 * FUNCTION: GET RISK TEAMS     *
 * Getting the teams of a risk  *
 ********************************/
function get_risk_teams($id) {

    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $id = (int)$id - 1000;

    // Query the database
    $stmt = $db->prepare("
    SELECT
        t.value
    FROM
        `risks` a 
        INNER JOIN `risk_to_team` rtt ON a.id=rtt.risk_id
        INNER JOIN `team` t ON rtt.team_id=t.value
    WHERE
        a.`id` = :id;
    ");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Close the database connection
    db_close($db);
    
    return $result && $result[0] ? implode(',', $result) : [];
}

/**********************************
 * FUNCTION: GET MITIGATION BY ID *
 **********************************/
function get_mitigation_by_id($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $risk_id = (int)$risk_id - 1000;

    // Query the database
    $stmt = $db->prepare("SELECT t1.*, 
            GROUP_CONCAT(DISTINCT mtc.control_id) mitigation_controls,
            t1.risk_id AS id,
            t1.id AS mitigation_id,
            t2.name AS planning_strategy_name,
            t3.name AS mitigation_effort_name,
            t4.min_value AS mitigation_min_cost, t4.max_value AS mitigation_max_cost,
            t5.name AS mitigation_owner_name,
            GROUP_CONCAT(DISTINCT t6.value) AS mitigation_team,
            GROUP_CONCAT(DISTINCT t6.name) AS mitigation_team_name,
            t7.name AS submitted_by_name
        FROM mitigations t1
            LEFT JOIN `mitigation_to_controls` mtc ON t1.id=mtc.mitigation_id
            LEFT JOIN planning_strategy t2 ON t1.planning_strategy=t2.value
            LEFT JOIN mitigation_effort t3 ON t1.mitigation_effort=t3.value
            LEFT JOIN asset_values t4 ON t1.mitigation_cost=t4.id
            LEFT JOIN user t5 ON t1.mitigation_owner=t5.value
            LEFT JOIN mitigation_to_team mtt ON t1.id=mtt.mitigation_id
            LEFT JOIN team t6 ON mtt.team_id=t6.value
            LEFT JOIN user t7 ON t1.submitted_by=t7.value
        WHERE t1.risk_id=:risk_id
        GROUP BY t1.id
        ;
    "
    );
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
 * FUNCTION: GET SUPPORTING FILES BY ID *
 ******************************/
function get_supporting_files($risk_id, $view_type)
{
    $risk_id = $risk_id-1000;

    // Open the database connection
    $db = db_open();

    // Get the file from the database
    $stmt = $db->prepare("SELECT name, unique_name FROM files WHERE risk_id=:id AND view_type=:view_type");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":view_type", $view_type, PDO::PARAM_INT);
    $stmt->execute();

    // Store the results in an array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/******************************
 * FUNCTION: GET REVIEW BY ID *
 ******************************/
function get_review_by_id($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $risk_id = (int)$risk_id - 1000;

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
        $array = strip_no_access_risks($array, null, "risk_id");
    }

    // If the array is empty
    if (empty($array))
    {
        return false;
    }
    else return $array;
}

/******************************
 * FUNCTION: GET CLOSE BY ID *
 ******************************/
function get_close_by_id($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $risk_id = (int)$risk_id - 1000;

    // Query the database
    $stmt = $db->prepare("SELECT * FROM closures WHERE risk_id=:risk_id ORDER BY closure_date DESC limit 1");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array))
    {
        return false;
    }
    else return $array;
}

/*******************************************
 * FUNCTION: GET RISKS UNASSIGNED PROJECTS *
 *******************************************/
function get_risks_unassigned_project()
{
    $db = db_open();

    // If we want to get all risks
    if (get_setting('plan_projects_show_all') == 1)
    {
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != 'Closed' AND (b.project_id IS NULL or b.project_id=0) GROUP BY b.id ORDER BY calculated_risk DESC;");
    }
// If we only want to get risks reviewed as consider for project
    else
    {
        $stmt = $db->prepare("
            SELECT a.calculated_risk, b.* 
            FROM risk_scoring a 
                LEFT JOIN risks b ON a.id = b.id 
                RIGHT JOIN (
                    SELECT c1.risk_id, next_step, date 
                    FROM mgmt_reviews c1 
                        RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 
                                ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date 
                    WHERE next_step = 2
                ) AS c ON a.id = c.risk_id 
            WHERE status != \"Closed\" AND (b.project_id IS NULL or b.project_id=0) 
            GROUP BY b.id
            ORDER BY calculated_risk DESC;
        ");
    }
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

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

/************************************
 * FUNCTION: GET PROJECT BY RISK ID *
 ************************************/
function get_project_by_risk_id($risk_id)
{
    $risk_id = $risk_id - 1000;
    $db = db_open();

    $stmt = $db->prepare("
        SELECT a.value, a.name
        FROM projects a INNER JOIN risks b ON a.value = b.project_id
        WHERE b.id=:risk_id;
    ");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $project = $stmt->fetch();

    db_close($db);
    
    // If project exists, decrypt project name
    if($project)
    {
        $project['name'] = try_decrypt($project['name']);
    }

    return $project;
}

/*************************************
 * FUNCTION: GET RISKS BY PROJECT ID *
 *************************************/
function get_risks_by_project_id($project_id)
{
    $db = db_open();
    // If we want to get all risks
    if (get_setting('plan_projects_show_all') == 1)
    {
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != 'Closed' AND b.project_id = :project_id GROUP BY b.id ORDER BY calculated_risk DESC;");
    }
    // If we only want to get risks reviewed as consider for project
    else
    {
        $stmt = $db->prepare("
            SELECT a.calculated_risk, b.* 
            FROM risk_scoring a 
                LEFT JOIN risks b ON a.id = b.id 
                RIGHT JOIN (
                    SELECT c1.risk_id, next_step, date 
                    FROM mgmt_reviews c1 
                        RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 
                                        ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date 
                    WHERE next_step = 2
                ) AS c ON a.id = c.risk_id 
            WHERE status != \"Closed\" AND b.project_id = :project_id
            GROUP BY b.id
            ORDER BY calculated_risk DESC;
        ");
    }
    $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

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

/***********************
 * FUNCTION: GET RISKS *
 ***********************/
function get_risks($sort_order=0, $order_field=false, $order_dir=false)
{
    
    // Open the database connection
    $db = db_open();
    
    // If sort_field is defined, set sort query
    if($order_field)
    {
        $order_dir = $order_dir=="asc" ? "asc" : "desc";
        switch($order_field)
        {
            case "id":
                $sort_query = " ORDER BY b.id {$order_dir} ";
            break;
            case "risk_status":
                $sort_query = " ORDER BY b.status {$order_dir} ";
            break;
            case "subject":
                if (encryption_extra())
                {
                    $sort_query = " ORDER BY b.order_by_subject {$order_dir} ";
                }else{
                    $sort_query = " ORDER BY b.subject {$order_dir} ";
                }
            break;
            case "calculated_risk":
                $sort_query = " ORDER BY a.calculated_risk {$order_dir} ";
            break;
            case "submission_date":
                $sort_query = " ORDER BY b.submission_date {$order_dir} ";
            break;
            case "days_open":
                $sort_query = " ORDER BY datediff(NOW(), b.submission_date) {$order_dir} ";
            break;
            case "regulation":
                $sort_query = " ORDER BY b.regulation {$order_dir} ";
            break;
            case "source":
                $sort_query = " ORDER BY v.name {$order_dir} ";
            break;
            case "category":
                $sort_query = " ORDER BY d.name {$order_dir} ";
            break;
            case "owner":
                $sort_query = " ORDER BY g.name {$order_dir} ";
            break;
            case "manager":
                $sort_query = " ORDER BY h.name {$order_dir} ";
            break;
            case "mitigation_cost":
                $sort_query = " ORDER BY s.min_value {$order_dir} ";
            break;
            case "submitted_by":
                $sort_query = " ORDER BY i.name {$order_dir} ";
            break;
            case "project":
                $sort_query = " ORDER BY project {$order_dir} ";
            break;
            case "next_review_date":
            case "management_review":
                $sort_query = "";
            break;
            case "mitigation_planned":
                $sort_query = " ORDER BY b.mitigation_id != 0 {$order_dir}, b.id ASC";
            break;
            default:
                $sort_query = '';
                // Only check if it's not a custom field
                if (stripos($order_field, "custom_field_") === false) {
                    
                    // If there're new fields added to the 1-3 queries they should be added above if they need special treatment
                    // or here if they did not
                    $static_allowed_fields = [
                        "additional_notes",
                        "additional_stakeholders",
                        "affected_asset_groups",
                        "affected_assets",
                        "closure_date",
                        "comments",
                        "current_solution",
                        "location",
                        "mitigation_accepted",
                        "mitigation_controls",
                        "mitigation_date",
                        "mitigation_effort",
                        "mitigation_max_cost",
                        "mitigation_min_cost",
                        "mitigation_owner",
                        "mitigation_team",
                        "next_review",
                        "next_step",
                        "planning_date",
                        "planning_strategy",
                        "regulation_id",
                        "residual_risk",
                        "risk_assessment",
                        "risk_tags",
                        "scoring_method",
                        "security_recommendations",
                        "security_requirements",
                        "team",
                        "technology"
                    ];
    
                    // TODO if sorting on a field isn't working then it should be considered to add above or add the whole table to the list in the below query 
                    $stmt = $db->prepare("
                        SELECT
                        	DISTINCT `COLUMN_NAME` 
                        FROM
                            `INFORMATION_SCHEMA`.`COLUMNS` 
                        WHERE
                            `TABLE_SCHEMA` = '" . DB_DATABASE . "'
                        	AND `TABLE_NAME` IN ('mitigations', 'risks', 'mgmt_reviews')
                    ");
                    $stmt->execute();
    
                    // Store the list in the array
                    $dynamic_allowed_fields = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);                
                    $allowed_fields = array_values(array_unique(array_merge($static_allowed_fields, $dynamic_allowed_fields)));
                    
                    if (in_array($order_field, $allowed_fields)) {
                        $sort_query = " ORDER BY `$order_field` {$order_dir} ";
                    }
                }
            break;
        }
    }


    // If this is the default, sort by risk
    if ($sort_order == 0)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review
                    , ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE
                    b.status != \"Closed\"
                GROUP BY b.id
                ORDER BY
                    a.calculated_risk DESC
            ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE
                    b.status != \"Closed\"  " . $separation_query . "
                GROUP BY b.id
                ORDER BY
                    a.calculated_risk DESC
            ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 1 = Show risks requiring mitigations
    else if ($sort_order == 1)
    {
        // Set default sort field
        if(empty($sort_query)){
            $sort_query = " ORDER BY a.calculated_risk DESC ";
        }
        
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
                    o.closure_date, j.name AS regulation, b.regulation regulation_id, b.assessment AS risk_assessment, b.notes AS additional_notes,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT location.name SEPARATOR '; ')
                        FROM
                            location, risk_to_location rtl
                        WHERE
                            rtl.risk_id=b.id AND rtl.location_id=location.value
                    ) AS location,
                    v.name AS source, 
                    d.name AS category,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name  SEPARATOR ', ')
                        FROM
                            team, risk_to_team rtt
                        WHERE
                            rtt.risk_id=b.id AND rtt.team_id=team.value
                    ) AS team,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ')
                        FROM
                            user u, risk_to_additional_stakeholder rtas
                        WHERE
                            rtas.risk_id=b.id AND rtas.user_id=u.value
                    ) AS additional_stakeholders,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT tech.name SEPARATOR ', ')
                        FROM
                            technology tech, risk_to_technology rttg
                        WHERE
                            rttg.risk_id=b.id AND rttg.technology_id=tech.value
                    ) AS technology,
                    g.name AS owner,
                    h.name AS manager,
                    a.scoring_method,
                    k.name AS project, 
                    DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open,
                    i.name AS submitted_by,
                    (
                        SELECT
                            GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR '|')
                        FROM
                            tags t, tags_taggees tt 
                        WHERE
                            tt.tag_id = t.id AND tt.taggee_id=b.id AND tt.type='risk'
                    ) AS risk_tags,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rta.asset_id SEPARATOR ', ')
                        FROM
                            risks_to_assets rta
                        WHERE
                            rta.risk_id=b.id
                    ) AS affected_assets,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rtag.asset_group_id SEPARATOR ', ')
                        FROM
                            risks_to_asset_groups rtag
                        WHERE
                            rtag.risk_id=b.id
                    ) AS affected_asset_groups,
                    q.name AS planning_strategy,
                    p.planning_date,
                    r.name AS mitigation_effort,
                    s.min_value AS mitigation_min_cost,
                    s.max_value AS mitigation_max_cost,
                    t.name AS mitigation_owner,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name SEPARATOR ', ')
                        FROM
                            team, mitigation_to_team mtt 
                        WHERE
                            mtt.mitigation_id=p.id AND mtt.team_id=team.value
                    ) AS mitigation_team,


                    NOT(ISNULL(mau.id)) mitigation_accepted, 
                    p.submission_date AS mitigation_date,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT fc.short_name SEPARATOR ', ')
                        FROM
                            `mitigation_to_controls` mtc INNER JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                        WHERE
                            mtc.mitigation_id=p.id 
                    ) AS mitigation_controls,
                    p.current_solution,
                    p.security_recommendations,
                    p.security_requirements,
                    m.name AS next_step,
                    l.comments
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations p ON b.id = p.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON p.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                    LEFT JOIN closures o ON b.close_id = o.id
                    LEFT JOIN frameworks j FORCE INDEX(PRIMARY) ON b.regulation = j.value
                    LEFT JOIN source v FORCE INDEX(PRIMARY) ON b.source = v.value
                    LEFT JOIN category d FORCE INDEX(PRIMARY) ON b.category = d.value
                    LEFT JOIN user g FORCE INDEX(PRIMARY) ON b.owner = g.value
                    LEFT JOIN user h FORCE INDEX(PRIMARY) ON b.manager = h.value
                    LEFT JOIN projects k FORCE INDEX(PRIMARY) ON b.project_id = k.value
                    LEFT JOIN user i FORCE INDEX(PRIMARY) ON b.submitted_by = i.value
                    LEFT JOIN planning_strategy q FORCE INDEX(PRIMARY) ON p.planning_strategy = q.value
                    LEFT JOIN mitigation_effort r FORCE INDEX(PRIMARY) ON p.mitigation_effort = r.value
                    LEFT JOIN asset_values s ON p.mitigation_cost = s.id
                    LEFT JOIN user t FORCE INDEX(PRIMARY) ON p.mitigation_owner = t.value
                    LEFT JOIN mitigation_accept_users mau ON b.id=mau.risk_id
                    LEFT JOIN mgmt_reviews l ON b.mgmt_review = l.id
                    LEFT JOIN next_step m FORCE INDEX(PRIMARY) ON l.next_step = m.value
                WHERE
                    b.mitigation_id = 0 and b.status != \"Closed\"
                GROUP BY b.id
                {$sort_query}
                ;
            ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
                    o.closure_date, j.name AS regulation, b.regulation regulation_id, b.assessment AS risk_assessment, b.notes AS additional_notes,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT location.name SEPARATOR '; ')
                        FROM
                            location, risk_to_location rtl
                        WHERE
                            rtl.risk_id=b.id AND rtl.location_id=location.value
                    ) AS location,
                    v.name AS source, 
                    d.name AS category,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name  SEPARATOR ', ')
                        FROM
                            team, risk_to_team rtt
                        WHERE
                            rtt.risk_id=b.id AND rtt.team_id=team.value
                    ) AS team,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ')
                        FROM
                            user u, risk_to_additional_stakeholder rtas
                        WHERE
                            rtas.risk_id=b.id AND rtas.user_id=u.value
                    ) AS additional_stakeholders,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT tech.name SEPARATOR ', ')
                        FROM
                            technology tech, risk_to_technology rttg
                        WHERE
                            rttg.risk_id=b.id AND rttg.technology_id=tech.value
                    ) AS technology,
                    g.name AS owner,
                    h.name AS manager,
                    a.scoring_method,
                    k.name AS project, 
                    DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open,
                    i.name AS submitted_by,
                    (
                        SELECT
                            GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR '|')
                        FROM
                            tags t, tags_taggees tt 
                        WHERE
                            tt.tag_id = t.id AND tt.taggee_id=b.id AND tt.type='risk'
                    ) AS risk_tags,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rta.asset_id SEPARATOR ', ')
                        FROM
                            risks_to_assets rta
                        WHERE
                            rta.risk_id=b.id
                    ) AS affected_assets,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rtag.asset_group_id SEPARATOR ', ')
                        FROM
                            risks_to_asset_groups rtag
                        WHERE
                            rtag.risk_id=b.id
                    ) AS affected_asset_groups,
                    q.name AS planning_strategy,
                    p.planning_date,
                    r.name AS mitigation_effort,
                    s.min_value AS mitigation_min_cost,
                    s.max_value AS mitigation_max_cost,
                    t.name AS mitigation_owner,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name SEPARATOR ', ')
                        FROM
                            team, mitigation_to_team mtt 
                        WHERE
                            mtt.mitigation_id=p.id AND mtt.team_id=team.value
                    ) AS mitigation_team,


                    NOT(ISNULL(mau.id)) mitigation_accepted, 
                    p.submission_date AS mitigation_date,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT fc.short_name SEPARATOR ', ')
                        FROM
                            `mitigation_to_controls` mtc INNER JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                        WHERE
                            mtc.mitigation_id=p.id 
                    ) AS mitigation_controls,
                    p.current_solution,
                    p.security_recommendations,
                    p.security_requirements,
                    m.name AS next_step,
                    l.comments
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations p ON b.id = p.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON p.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                    LEFT JOIN closures o ON b.close_id = o.id
                    LEFT JOIN frameworks j FORCE INDEX(PRIMARY) ON b.regulation = j.value
                    LEFT JOIN source v FORCE INDEX(PRIMARY) ON b.source = v.value
                    LEFT JOIN category d FORCE INDEX(PRIMARY) ON b.category = d.value
                    LEFT JOIN user g FORCE INDEX(PRIMARY) ON b.owner = g.value
                    LEFT JOIN user h FORCE INDEX(PRIMARY) ON b.manager = h.value
                    LEFT JOIN projects k FORCE INDEX(PRIMARY) ON b.project_id = k.value
                    LEFT JOIN user i FORCE INDEX(PRIMARY) ON b.submitted_by = i.value
                    LEFT JOIN planning_strategy q FORCE INDEX(PRIMARY) ON p.planning_strategy = q.value
                    LEFT JOIN mitigation_effort r FORCE INDEX(PRIMARY) ON p.mitigation_effort = r.value
                    LEFT JOIN asset_values s ON p.mitigation_cost = s.id
                    LEFT JOIN user t FORCE INDEX(PRIMARY) ON p.mitigation_owner = t.value
                    LEFT JOIN mitigation_accept_users mau ON b.id=mau.risk_id
                    LEFT JOIN mgmt_reviews l ON b.mgmt_review = l.id
                    LEFT JOIN next_step m FORCE INDEX(PRIMARY) ON l.next_step = m.value
                WHERE
                    b.mitigation_id = 0 and b.status != \"Closed\"  " . $separation_query . "
                GROUP BY b.id
                {$sort_query}
                ;
            ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 2 = Show risks requiring management review
    else if ($sort_order == 2)
    {
        // Set default sort field
        if(empty($sort_query)){
            $sort_query = " ORDER BY a.calculated_risk DESC ";
        }

        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
                    o.closure_date, j.name AS regulation, b.regulation regulation_id, b.assessment AS risk_assessment, b.notes AS additional_notes,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT location.name SEPARATOR '; ')
                        FROM
                            location, risk_to_location rtl
                        WHERE
                            rtl.risk_id=b.id AND rtl.location_id=location.value
                    ) AS location,
                    v.name AS source, 
                    d.name AS category,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name  SEPARATOR ', ')
                        FROM
                            team, risk_to_team rtt
                        WHERE
                            rtt.risk_id=b.id AND rtt.team_id=team.value
                    ) AS team,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ')
                        FROM
                            user u, risk_to_additional_stakeholder rtas
                        WHERE
                            rtas.risk_id=b.id AND rtas.user_id=u.value
                    ) AS additional_stakeholders,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT tech.name SEPARATOR ', ')
                        FROM
                            technology tech, risk_to_technology rttg
                        WHERE
                            rttg.risk_id=b.id AND rttg.technology_id=tech.value
                    ) AS technology,
                    g.name AS owner,
                    h.name AS manager,
                    a.scoring_method,
                    k.name AS project, 
                    DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open,
                    i.name AS submitted_by,
                    (
                        SELECT
                            GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR '|')
                        FROM
                            tags t, tags_taggees tt 
                        WHERE
                            tt.tag_id = t.id AND tt.taggee_id=b.id AND tt.type='risk'
                    ) AS risk_tags,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rta.asset_id SEPARATOR ', ')
                        FROM
                            risks_to_assets rta
                        WHERE
                            rta.risk_id=b.id
                    ) AS affected_assets,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rtag.asset_group_id SEPARATOR ', ')
                        FROM
                            risks_to_asset_groups rtag
                        WHERE
                            rtag.risk_id=b.id
                    ) AS affected_asset_groups,
                    q.name AS planning_strategy,
                    p.planning_date,
                    r.name AS mitigation_effort,
                    s.min_value AS mitigation_min_cost,
                    s.max_value AS mitigation_max_cost,
                    t.name AS mitigation_owner,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name SEPARATOR ', ')
                        FROM
                            team, mitigation_to_team mtt 
                        WHERE
                            mtt.mitigation_id=p.id AND mtt.team_id=team.value
                    ) AS mitigation_team,


                    NOT(ISNULL(mau.id)) mitigation_accepted, 
                    p.submission_date AS mitigation_date,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT fc.short_name SEPARATOR ', ')
                        FROM
                            `mitigation_to_controls` mtc INNER JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                        WHERE
                            mtc.mitigation_id=p.id 
                    ) AS mitigation_controls,
                    p.current_solution,
                    p.security_recommendations,
                    p.security_requirements,
                    m.name AS next_step,
                    l.comments
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations p ON b.id = p.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON p.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                    LEFT JOIN closures o ON b.close_id = o.id
                    LEFT JOIN frameworks j FORCE INDEX(PRIMARY) ON b.regulation = j.value
                    LEFT JOIN source v FORCE INDEX(PRIMARY) ON b.source = v.value
                    LEFT JOIN category d FORCE INDEX(PRIMARY) ON b.category = d.value
                    LEFT JOIN user g FORCE INDEX(PRIMARY) ON b.owner = g.value
                    LEFT JOIN user h FORCE INDEX(PRIMARY) ON b.manager = h.value
                    LEFT JOIN projects k FORCE INDEX(PRIMARY) ON b.project_id = k.value
                    LEFT JOIN user i FORCE INDEX(PRIMARY) ON b.submitted_by = i.value
                    LEFT JOIN planning_strategy q FORCE INDEX(PRIMARY) ON p.planning_strategy = q.value
                    LEFT JOIN mitigation_effort r FORCE INDEX(PRIMARY) ON p.mitigation_effort = r.value
                    LEFT JOIN asset_values s ON p.mitigation_cost = s.id
                    LEFT JOIN user t FORCE INDEX(PRIMARY) ON p.mitigation_owner = t.value
                    LEFT JOIN mitigation_accept_users mau ON b.id=mau.risk_id
                    LEFT JOIN mgmt_reviews l ON b.mgmt_review = l.id
                    LEFT JOIN next_step m FORCE INDEX(PRIMARY) ON l.next_step = m.value
                WHERE
                    b.mgmt_review = 0 and b.status != \"Closed\"
                GROUP BY
                    b.id
                {$sort_query}
                ;
            ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
                    o.closure_date, j.name AS regulation, b.regulation regulation_id, b.assessment AS risk_assessment, b.notes AS additional_notes,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT location.name SEPARATOR '; ')
                        FROM
                            location, risk_to_location rtl
                        WHERE
                            rtl.risk_id=b.id AND rtl.location_id=location.value
                    ) AS location,
                    v.name AS source, 
                    d.name AS category,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name  SEPARATOR ', ')
                        FROM
                            team, risk_to_team rtt
                        WHERE
                            rtt.risk_id=b.id AND rtt.team_id=team.value
                    ) AS team,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ')
                        FROM
                            user u, risk_to_additional_stakeholder rtas
                        WHERE
                            rtas.risk_id=b.id AND rtas.user_id=u.value
                    ) AS additional_stakeholders,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT tech.name SEPARATOR ', ')
                        FROM
                            technology tech, risk_to_technology rttg
                        WHERE
                            rttg.risk_id=b.id AND rttg.technology_id=tech.value
                    ) AS technology,
                    g.name AS owner,
                    h.name AS manager,
                    a.scoring_method,
                    k.name AS project, 
                    DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open,
                    i.name AS submitted_by,
                    (
                        SELECT
                            GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR '|')
                        FROM
                            tags t, tags_taggees tt 
                        WHERE
                            tt.tag_id = t.id AND tt.taggee_id=b.id AND tt.type='risk'
                    ) AS risk_tags,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rta.asset_id SEPARATOR ', ')
                        FROM
                            risks_to_assets rta
                        WHERE
                            rta.risk_id=b.id
                    ) AS affected_assets,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rtag.asset_group_id SEPARATOR ', ')
                        FROM
                            risks_to_asset_groups rtag
                        WHERE
                            rtag.risk_id=b.id
                    ) AS affected_asset_groups,
                    q.name AS planning_strategy,
                    p.planning_date,
                    r.name AS mitigation_effort,
                    s.min_value AS mitigation_min_cost,
                    s.max_value AS mitigation_max_cost,
                    t.name AS mitigation_owner,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name SEPARATOR ', ')
                        FROM
                            team, mitigation_to_team mtt 
                        WHERE
                            mtt.mitigation_id=p.id AND mtt.team_id=team.value
                    ) AS mitigation_team,


                    NOT(ISNULL(mau.id)) mitigation_accepted, 
                    p.submission_date AS mitigation_date,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT fc.short_name SEPARATOR ', ')
                        FROM
                            `mitigation_to_controls` mtc INNER JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                        WHERE
                            mtc.mitigation_id=p.id 
                    ) AS mitigation_controls,
                    p.current_solution,
                    p.security_recommendations,
                    p.security_requirements,
                    m.name AS next_step,
                    l.comments
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations p ON b.id = p.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON p.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                    LEFT JOIN closures o ON b.close_id = o.id
                    LEFT JOIN frameworks j FORCE INDEX(PRIMARY) ON b.regulation = j.value
                    LEFT JOIN source v FORCE INDEX(PRIMARY) ON b.source = v.value
                    LEFT JOIN category d FORCE INDEX(PRIMARY) ON b.category = d.value
                    LEFT JOIN user g FORCE INDEX(PRIMARY) ON b.owner = g.value
                    LEFT JOIN user h FORCE INDEX(PRIMARY) ON b.manager = h.value
                    LEFT JOIN projects k FORCE INDEX(PRIMARY) ON b.project_id = k.value
                    LEFT JOIN user i FORCE INDEX(PRIMARY) ON b.submitted_by = i.value
                    LEFT JOIN planning_strategy q FORCE INDEX(PRIMARY) ON p.planning_strategy = q.value
                    LEFT JOIN mitigation_effort r FORCE INDEX(PRIMARY) ON p.mitigation_effort = r.value
                    LEFT JOIN asset_values s ON p.mitigation_cost = s.id
                    LEFT JOIN user t FORCE INDEX(PRIMARY) ON p.mitigation_owner = t.value
                    LEFT JOIN mitigation_accept_users mau ON b.id=mau.risk_id
                    LEFT JOIN mgmt_reviews l ON b.mgmt_review = l.id
                    LEFT JOIN next_step m FORCE INDEX(PRIMARY) ON l.next_step = m.value
                WHERE
                    b.mgmt_review = 0 and b.status != \"Closed\"  {$separation_query}
                GROUP BY
                    b.id
                {$sort_query}
                ;
            ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 3 = Show risks by review date
    else if ($sort_order == 3)
    {
        // Set default sort field
        if(empty($sort_query)){
            $sort_query = " ORDER BY b.review_date ASC ";
        }

        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk,
                    b.*,
                    c.next_review,
                    ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
                    DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open,
                    o.closure_date, j.name AS regulation, b.regulation regulation_id, b.assessment AS risk_assessment, b.notes AS additional_notes,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT location.name SEPARATOR '; ')
                        FROM
                            location, risk_to_location rtl
                        WHERE
                            rtl.risk_id=b.id AND rtl.location_id=location.value
                    ) AS location,
                    v.name AS source, 
                    d.name AS category,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name  SEPARATOR ', ')
                        FROM
                            team, risk_to_team rtt
                        WHERE
                            rtt.risk_id=b.id AND rtt.team_id=team.value
                    ) AS team,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ')
                        FROM
                            user u, risk_to_additional_stakeholder rtas
                        WHERE
                            rtas.risk_id=b.id AND rtas.user_id=u.value
                    ) AS additional_stakeholders,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT tech.name SEPARATOR ', ')
                        FROM
                            technology tech, risk_to_technology rttg
                        WHERE
                            rttg.risk_id=b.id AND rttg.technology_id=tech.value
                    ) AS technology,
                    g.name AS owner,
                    h.name AS manager,
                    a.scoring_method,
                    k.name AS project, 
                    i.name AS submitted_by,
                    (
                        SELECT
                            GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR '|')
                        FROM
                            tags t, tags_taggees tt 
                        WHERE
                            tt.tag_id = t.id AND tt.taggee_id=b.id AND tt.type='risk'
                    ) AS risk_tags,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rta.asset_id SEPARATOR ', ')
                        FROM
                            risks_to_assets rta
                        WHERE
                            rta.risk_id=b.id
                    ) AS affected_assets,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rtag.asset_group_id SEPARATOR ', ')
                        FROM
                            risks_to_asset_groups rtag
                        WHERE
                            rtag.risk_id=b.id
                    ) AS affected_asset_groups,
                    q.name AS planning_strategy,
                    p.planning_date,
                    r.name AS mitigation_effort,
                    s.min_value AS mitigation_min_cost,
                    s.max_value AS mitigation_max_cost,
                    t.name AS mitigation_owner,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name SEPARATOR ', ')
                        FROM
                            team, mitigation_to_team mtt 
                        WHERE
                            mtt.mitigation_id=p.id AND mtt.team_id=team.value
                    ) AS mitigation_team,


                    NOT(ISNULL(mau.id)) mitigation_accepted, 
                    p.submission_date AS mitigation_date,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT fc.short_name SEPARATOR ', ')
                        FROM
                            `mitigation_to_controls` mtc INNER JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                        WHERE
                            mtc.mitigation_id=p.id 
                    ) AS mitigation_controls,
                    p.current_solution,
                    p.security_recommendations,
                    p.security_requirements,
                    m.name AS next_step,
                    l.comments
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations p ON b.id = p.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON p.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                    LEFT JOIN closures o ON b.close_id = o.id
                    LEFT JOIN frameworks j FORCE INDEX(PRIMARY) ON b.regulation = j.value
                    LEFT JOIN source v FORCE INDEX(PRIMARY) ON b.source = v.value
                    LEFT JOIN category d FORCE INDEX(PRIMARY) ON b.category = d.value
                    LEFT JOIN user g FORCE INDEX(PRIMARY) ON b.owner = g.value
                    LEFT JOIN user h FORCE INDEX(PRIMARY) ON b.manager = h.value
                    LEFT JOIN projects k FORCE INDEX(PRIMARY) ON b.project_id = k.value
                    LEFT JOIN user i FORCE INDEX(PRIMARY) ON b.submitted_by = i.value
                    LEFT JOIN planning_strategy q FORCE INDEX(PRIMARY) ON p.planning_strategy = q.value
                    LEFT JOIN mitigation_effort r FORCE INDEX(PRIMARY) ON p.mitigation_effort = r.value
                    LEFT JOIN asset_values s ON p.mitigation_cost = s.id
                    LEFT JOIN user t FORCE INDEX(PRIMARY) ON p.mitigation_owner = t.value
                    LEFT JOIN mitigation_accept_users mau ON b.id=mau.risk_id
                    LEFT JOIN mgmt_reviews l ON b.mgmt_review = l.id
                    LEFT JOIN next_step m FORCE INDEX(PRIMARY) ON l.next_step = m.value
                WHERE b.status != \"Closed\"
                GROUP BY b.id
                {$sort_query}
            ;");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk,
                    b.*,
                    c.next_review,
                    ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(p.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
                    DATEDIFF(IF(b.status != 'Closed', NOW(), o.closure_date) , b.submission_date) days_open,
                    o.closure_date, j.name AS regulation, b.regulation regulation_id, b.assessment AS risk_assessment, b.notes AS additional_notes,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT location.name SEPARATOR '; ')
                        FROM
                            location, risk_to_location rtl
                        WHERE
                            rtl.risk_id=b.id AND rtl.location_id=location.value
                    ) AS location,
                    v.name AS source, 
                    d.name AS category,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name  SEPARATOR ', ')
                        FROM
                            team, risk_to_team rtt
                        WHERE
                            rtt.risk_id=b.id AND rtt.team_id=team.value
                    ) AS team,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ')
                        FROM
                            user u, risk_to_additional_stakeholder rtas
                        WHERE
                            rtas.risk_id=b.id AND rtas.user_id=u.value
                    ) AS additional_stakeholders,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT tech.name SEPARATOR ', ')
                        FROM
                            technology tech, risk_to_technology rttg
                        WHERE
                            rttg.risk_id=b.id AND rttg.technology_id=tech.value
                    ) AS technology,
                    g.name AS owner,
                    h.name AS manager,
                    a.scoring_method,
                    k.name AS project, 
                    i.name AS submitted_by,
                    (
                        SELECT
                            GROUP_CONCAT(t.tag ORDER BY t.tag ASC SEPARATOR '|')
                        FROM
                            tags t, tags_taggees tt 
                        WHERE
                            tt.tag_id = t.id AND tt.taggee_id=b.id AND tt.type='risk'
                    ) AS risk_tags,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rta.asset_id SEPARATOR ', ')
                        FROM
                            risks_to_assets rta
                        WHERE
                            rta.risk_id=b.id
                    ) AS affected_assets,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT rtag.asset_group_id SEPARATOR ', ')
                        FROM
                            risks_to_asset_groups rtag
                        WHERE
                            rtag.risk_id=b.id
                    ) AS affected_asset_groups,
                    q.name AS planning_strategy,
                    p.planning_date,
                    r.name AS mitigation_effort,
                    s.min_value AS mitigation_min_cost,
                    s.max_value AS mitigation_max_cost,
                    t.name AS mitigation_owner,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT team.name SEPARATOR ', ')
                        FROM
                            team, mitigation_to_team mtt 
                        WHERE
                            mtt.mitigation_id=p.id AND mtt.team_id=team.value
                    ) AS mitigation_team,


                    NOT(ISNULL(mau.id)) mitigation_accepted, 
                    p.submission_date AS mitigation_date,
                    (
                        SELECT
                            GROUP_CONCAT(DISTINCT fc.short_name SEPARATOR ', ')
                        FROM
                            `mitigation_to_controls` mtc INNER JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                        WHERE
                            mtc.mitigation_id=p.id 
                    ) AS mitigation_controls,
                    p.current_solution,
                    p.security_recommendations,
                    p.security_requirements,
                    m.name AS next_step,
                    l.comments
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations p ON b.id = p.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON p.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                    LEFT JOIN closures o ON b.close_id = o.id
                    LEFT JOIN frameworks j FORCE INDEX(PRIMARY) ON b.regulation = j.value
                    LEFT JOIN source v FORCE INDEX(PRIMARY) ON b.source = v.value
                    LEFT JOIN category d FORCE INDEX(PRIMARY) ON b.category = d.value
                    LEFT JOIN user g FORCE INDEX(PRIMARY) ON b.owner = g.value
                    LEFT JOIN user h FORCE INDEX(PRIMARY) ON b.manager = h.value
                    LEFT JOIN projects k FORCE INDEX(PRIMARY) ON b.project_id = k.value
                    LEFT JOIN user i FORCE INDEX(PRIMARY) ON b.submitted_by = i.value
                    LEFT JOIN planning_strategy q FORCE INDEX(PRIMARY) ON p.planning_strategy = q.value
                    LEFT JOIN mitigation_effort r FORCE INDEX(PRIMARY) ON p.mitigation_effort = r.value
                    LEFT JOIN asset_values s ON p.mitigation_cost = s.id
                    LEFT JOIN user t FORCE INDEX(PRIMARY) ON p.mitigation_owner = t.value
                    LEFT JOIN mitigation_accept_users mau ON b.id=mau.risk_id
                    LEFT JOIN mgmt_reviews l ON b.mgmt_review = l.id
                    LEFT JOIN next_step m FORCE INDEX(PRIMARY) ON l.next_step = m.value
                WHERE b.status != \"Closed\" " . $separation_query . "
                GROUP BY b.id
                {$sort_query}
            ;");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 4 = Show risks that are closed
    else if ($sort_order == 4)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE
                    b.status = \"Closed\"
                GROUP BY b.id
                ORDER BY
                    calculated_risk DESC
            ");

//            $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status = \"Closed\" ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database

            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE
                    b.status = \"Closed\"  {$separation_query}
                GROUP BY b.id
                ORDER BY
                    calculated_risk DESC

            ");

//            $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status = \"Closed\" " . $separation_query . " ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 5 = Show open risks that should be considered for projects
    else if ($sort_order == 5)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, c1.next_review, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 2) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE b.status != \"Closed\"
            GROUP BY b.id
            ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id
                LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                RIGHT JOIN (SELECT c1.risk_id, c1.next_review, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 2) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE b.status != \"Closed\" " . $separation_query . "
            GROUP BY b.id
            ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 6 = Show open risks accepted until next review
    else if ($sort_order == 6)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review
            FROM risk_scoring a
                LEFT JOIN risks b ON a.id = b.id
                RIGHT JOIN (SELECT c1.risk_id, c1.next_review, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 1) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE b.status != \"Closed\"
            GROUP BY b.id
            ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review
            FROM risk_scoring a
                LEFT JOIN risks b ON a.id = b.id
                LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                RIGHT JOIN (SELECT c1.risk_id, c1.next_review, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 1) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE b.status != \"Closed\" " . $separation_query . "
            GROUP BY b.id
            ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 7 = Show open risks to submit as production issues
    else if ($sort_order == 7)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, c1.next_review, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 3) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE b.status != \"Closed\"
            GROUP BY b.id
            ORDER BY calculated_risk DESC; ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a
                LEFT JOIN risks b ON a.id = b.id 
                LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                RIGHT JOIN (SELECT c1.risk_id, c1.next_review , next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 3) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
            WHERE b.status != \"Closed\" " . $separation_query . "
            GROUP BY b.id
            ORDER BY calculated_risk DESC; ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 8 = Show all open risks assigned to this user by risk level
    else if ($sort_order == 8)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("
                SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE
                    b.status != \"Closed\" AND (owner = :uid OR manager = :uid)
                GROUP BY b.id
                ORDER BY
                    calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("
                SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE
                    b.status != \"Closed\" AND (owner = :uid OR manager = :uid) " . $separation_query . "
                GROUP BY b.id
                ORDER BY
                    calculated_risk DESC
            ");
        }

        $stmt->bindParam(":uid", $_SESSION['uid'], PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 9 = Show open risks scored by CVSS Scoring
    else if ($sort_order == 9)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
        // Query the database
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id WHERE b.status != \"Closed\" AND a.scoring_method = 2 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id                 
                LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
 WHERE b.status != \"Closed\" AND a.scoring_method = 2 " . $separation_query . " GROUP BY b.id ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 10 = Show open risks scored by Classic Scoring
    else if ($sort_order == 10)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
    $stmt = $db->prepare("SELECT a.calculated_risk, a.CLASSIC_likelihood, a.CLASSIC_impact, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id WHERE b.status != \"Closed\" AND a.scoring_method = 1 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, a.CLASSIC_likelihood, a.CLASSIC_impact, b.* FROM risk_scoring a 
                    JOIN risks b ON a.id = b.id 
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                WHERE b.status != \"Closed\" AND a.scoring_method = 1 " . $separation_query . " GROUP BY b.id ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 11 = Show All Risks by Date Submitted
    else if ($sort_order == 11)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.status, b.submission_date, group_concat(distinct d.name) AS team, c.name FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN user c ON b.submitted_by = c.value LEFT JOIN risk_to_team rtt ON b.id=rtt.risk_id LEFT JOIN team d ON rtt.team_id=d.value GROUP BY b.id ORDER BY DATE(b.submission_date) DESC ; ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", true, false);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.status, b.submission_date, group_concat(DISTINCT d.name SEPARATOR ', ') AS team, c.name FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN user c ON b.submitted_by = c.value LEFT JOIN risk_to_team rtt ON b.id=rtt.risk_id LEFT JOIN team d ON rtt.team_id=d.value LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id " . $separation_query . " GROUP BY b.id ORDER BY DATE(b.submission_date) DESC ; ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 12 = Show management reviews by date
    else if ($sort_order == 12)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
        // Query the database
        $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS review, e.name AS next_step FROM risks a JOIN mgmt_reviews b ON a.id = b.risk_id JOIN user c ON b.reviewer = c.value LEFT JOIN review d ON b.review = d.value LEFT JOIN next_step e ON b.next_step = e.value ORDER BY DATE(b.submission_date) DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", true, false);

            // Query the database
            $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS review, e.name AS next_step FROM risks a 
                LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON a.id = rtas.risk_id
                JOIN mgmt_reviews b ON a.id = b.risk_id JOIN user c ON b.reviewer = c.value LEFT JOIN review d ON b.review = d.value LEFT JOIN next_step e ON b.next_step = e.value " . $separation_query . " GROUP BY a.id ORDER BY DATE(b.submission_date) DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 13 = Show mitigations by date
    else if ($sort_order == 13)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS planning_strategy, e.name AS mitigation_effort, b.mitigation_cost, f.name AS mitigation_owner, group_concat(distinct g.name) AS mitigation_team FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value LEFT JOIN mitigation_to_team mtt ON b.id=mtt.mitigation_id LEFT JOIN team g ON mtt.team_id=g.value GROUP BY a.id ORDER BY DATE(b.submission_date) DESC; ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", true, false);

            // Query the database
            $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS planning_strategy, e.name AS mitigation_effort, b.mitigation_cost, f.name AS mitigation_owner, group_concat(distinct g.name) AS mitigation_team FROM risks a 
                LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON a.id = rtas.risk_id
                JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value LEFT JOIN mitigation_to_team mtt ON b.id=mtt.mitigation_id LEFT JOIN team g ON mtt.team_id=g.value " . $separation_query . " GROUP BY a.id ORDER BY DATE(b.submission_date) DESC; ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 14 = Show open risks scored by DREAD Scoring
    else if ($sort_order == 14)
    {
            // If the team separation extra is not enabled
            if (!team_separation_extra())
            {
                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 3 ORDER BY calculated_risk DESC");
            }
            else
            {
                // Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                // Get the separation query string
                $separation_query = get_user_teams_query("b", false, true);

                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 3 " . $separation_query . " 
                    GROUP BY b.id 
                    ORDER BY calculated_risk DESC");
            }

            $stmt->execute();

            // Store the list in the array
            $array = $stmt->fetchAll();
    }

    // 15 = Show open risks scored by OWASP Scoring
    else if ($sort_order == 15)
    {
            // If the team separation extra is not enabled
            if (!team_separation_extra())
            {
            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 4 ORDER BY calculated_risk DESC");
            }
            else
            {
                // Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                // Get the separation query string
                $separation_query = get_user_teams_query("b", false, true);

                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 4 " . $separation_query . "
                    GROUP BY b.id
                    ORDER BY calculated_risk DESC");
            }

            $stmt->execute();

            // Store the list in the array
            $array = $stmt->fetchAll();
    }

    // 16 = Show open risks scored by Custom Scoring
    else if ($sort_order == 16)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id WHERE b.status != \"Closed\" AND a.scoring_method = 5 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id 
                LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                WHERE b.status != \"Closed\" AND a.scoring_method = 5 " . $separation_query . " GROUP BY b.id 
                ORDER BY calculated_risk DESC;");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 17 = Show closed risks by date
    else if ($sort_order == 17)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, group_concat(DISTINCT c.name SEPARATOR ', ') AS team, d.name AS user, b.closure_date, e.name AS close_reason, f.calculated_risk FROM risks a LEFT JOIN closures b ON a.close_id = b.id LEFT JOIN risk_to_team rtt ON a.id=rtt.risk_id LEFT JOIN team c ON rtt.team_id=c.value LEFT JOIN user d ON b.user_id = d.value LEFT JOIN close_reason e ON b.close_reason = e.value LEFT JOIN risk_scoring f ON a.id = f.id WHERE a.status='Closed' GROUP BY a.id ORDER BY b.closure_date DESC ; ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, group_concat(DISTINCT c.name SEPARATOR ', ') AS team, d.name AS user, b.closure_date, e.name AS close_reason, f.calculated_risk FROM risks a LEFT JOIN closures b ON a.close_id = b.id LEFT JOIN risk_to_team rtt ON a.id=rtt.risk_id LEFT JOIN team c ON rtt.team_id=c.value 
            LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
            LEFT JOIN user d ON b.user_id = d.value LEFT JOIN close_reason e ON b.close_reason = e.value LEFT JOIN risk_scoring f ON a.id = f.id WHERE a.status='Closed' " . $separation_query . " GROUP BY a.id ORDER BY b.closure_date DESC; ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 18 = Get open risks by team
    else if ($sort_order == 18)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, group_concat(DISTINCT c.name SEPARATOR ', ') AS team, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN risk_to_team rtt ON a.id=rtt.risk_id LEFT JOIN team c ON rtt.team_id=c.value WHERE status != 'Closed' GROUP BY a.id ORDER BY team, b.calculated_risk DESC; ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, group_concat(DISTINCT c.name SEPARATOR ', ') AS team, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN risk_to_team rtt ON a.id=rtt.risk_id LEFT JOIN team c ON rtt.team_id=c.value LEFT JOIN risk_to_additional_stakeholder rtas ON a.id = rtas.risk_id WHERE status != 'Closed' " . $separation_query . " GROUP BY a.id ORDER BY team, b.calculated_risk DESC; ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 19 = Get open risks by technology
    else if ($sort_order == 19)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, c.name AS technology, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN risk_to_technology rttg ON a.id=rttg.risk_id LEFT JOIN technology c ON rttg.technology_id = c.value WHERE a.status != 'Closed' GROUP BY a.id, c.value ORDER BY c.value, b.calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, c.name AS technology, a.submission_date, b.calculated_risk FROM risks a 
                LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
                LEFT JOIN risk_to_additional_stakeholder rtas ON a.id = rtas.risk_id
                LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN risk_to_technology rttg ON a.id=rttg.risk_id LEFT JOIN technology c ON rttg.technology_id = c.value WHERE status != 'Closed' " . $separation_query . " GROUP BY a.id, c.value ORDER BY c.value, b.calculated_risk DESC");
        }

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

        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("
                SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE
                    b.status != \"Closed\" AND a.calculated_risk >= :high
                GROUP BY
                    b.id
                ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("
                SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN risk_to_team rtt ON b.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON b.id = rtas.risk_id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN mitigation_to_controls mtc ON mg.id = mtc.mitigation_id
                    LEFT JOIN framework_controls fc ON mtc.control_id=fc.id AND fc.deleted=0
                WHERE
                    b.status != \"Closed\" AND a.calculated_risk >= :high " . $separation_query . "
                GROUP BY
                    b.id
                ORDER BY
                    calculated_risk DESC");
        }

        $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 21 = Get all risks
    else if ($sort_order == 21)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT * FROM risks ORDER BY id ASC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query(false, true, false);

            // Query the database
            $stmt = $db->prepare("SELECT a.* FROM risks a 
                    LEFT JOIN risk_to_team rtt ON a.id = rtt.risk_id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON a.id = rtas.risk_id
                    " . $separation_query . " GROUP BY a.id ORDER BY a.id ASC;
            ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // Close the database connection
    db_close($db);

    if(is_array($array)){
        foreach($array as &$row){
            $row['subject'] = isset($row['subject']) ? try_decrypt($row['subject']) : "";
            $row['assessment'] = isset($row['assessment']) ? try_decrypt($row['assessment']) : "";
            $row['notes'] = isset($row['notes']) ? try_decrypt($row['notes']) : "";
        }
        unset($row);
    }

    return $array;
}

/****************************
 * FUNCTION: GET RISK TABLE *
 ****************************/
function get_risk_table($sort_order=0, $activecol="")
{
    global $lang;
    global $escaper;

    // Get risks
    // $count = get_risks_count($sort_order);

    // Get the list of mitigations
    $risks = get_risks($sort_order);
    $count = count($risks);

    // number of rows to show per page
    $rowsperpage = 10;

    // find out total pages
    $totalpages = ceil($count / $rowsperpage);

    // get the current page or set a default
    if (isset($_GET['currentpage']) && is_numeric($_GET['currentpage'])) {
       // cast var as int
       $currentpage = (int) $_GET['currentpage'];
    } else {
       // default page num
       $currentpage = 1;
    } // end if

    // if current page is greater than total pages...
    if ($currentpage > $totalpages) {
       // set current page to last page
       $currentpage = $totalpages;
    } // end if
    // if current page is less than first page...
    if ($currentpage < 1) {
       // set current page to first page
       $currentpage = 1;
    } // end if

    // the offset of the list, based on current page
    $offset = ($currentpage - 1) * $rowsperpage;

    $all_style = '';
    if(isset($_GET['currentpage']) && $_GET['currentpage'] == 'all') {
        $offset = 0;
        $rowsperpage = $count;
        $currentpage = -1;
        $all_style = 'class="active"';
    }

    echo "<table class=\"table table-bordered table-striped table-condensed sortable\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."#</th>\n";
    echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
    // If current page is All Open Risks by Team by Risk Level
    if($sort_order == 22){
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['Team']) ."</th>\n";
    }

    echo "<th align=\"center\" width=\"80px\">". $escaper->escapeHtml($lang['InherentRisk']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['Submitted']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\" class=\"mitigation-head\">". $escaper->escapeHtml($lang['MitigationPlanned']) ."</th>\n";
    echo "<th align=\"center\" width=\"160px\">". $escaper->escapeHtml($lang['ManagementReview']) ."</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    $review_levels = get_review_levels();
    $risk_levels = get_risk_levels();
    
    // For each risk
    for ($i=$offset; $i<min($rowsperpage+$offset, $count); $i++)
    {
        // Get the risk
        $risk = $risks[$i];

        // Get the risk color
        $color = get_risk_color_from_levels($risk['calculated_risk'], $risk_levels);

        echo "<tr data-id='" . $escaper->escapeHtml(convert_id($risk['id'])) . "'>\n";

        // if this is All Open Risks by Team by Risk Level page
        if($sort_order == 22){
            echo "<td align=\"left\" width=\"50px\" class='open-risk'><a target=\"blank\" href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk['id'])) . "\">" . $escaper->escapeHtml(convert_id($risk['id'])) . "</a></td>\n";
        }else{
            echo "<td align=\"left\" width=\"50px\" class='open-risk'><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk['id'])) . "\">" . $escaper->escapeHtml(convert_id($risk['id'])) . "</a></td>\n";
        }

        echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($risk['status']) . "</td>\n";
        echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($risk['subject']) . "</td>\n";

        // if this is All Open Risks by Team by Risk Levle page
        if($sort_order == 22){
            echo "<td align=\"center\" >". $escaper->escapeHtml($risk['team_name']) ."</td>\n";
        }
        echo "<td align=\"center\" class=\"" . $escaper->escapeHtml($color) . " risk-cell \">" . $escaper->escapeHtml($risk['calculated_risk']) . " <span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></td>\n";
        echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']))) . "</td>\n";

        // If the active column is management
        if ($activecol == 'management')
        {
            // Active cell is management
            $mitigation = "";
            $management = "active-cell";
        }
        // If the active column is mitigation
        else if ($activecol == 'mitigation')
        {
            // Active cell is mitigation
            $mitigation = "active-cell";
            $management = "";
        }
        // Otherwise
        else
        {
            // No active cell
            $mitigation = "";
            $management = "";
        }
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


        echo "<td align=\"center\" width=\"100px\" class=\"text-center open-mitigation mitigation ".$mitigation."\">" . planned_mitigation(convert_id($risk['id']), $risk['mitigation_id']) . "</td>\n";
        echo "<td align=\"center\" width=\"100px\" class=\"text-center open-review management ".$management."\">" . management_review(convert_id($risk['id']), $risk['mgmt_review'], $next_review) . "</td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";

    echo "<div class=\"pagination clearfix\"><ul class=\"pull-right\">";
    // range of num links to show
    $range = 3;


    if (!empty ($risks))
    {

        // if not on page 1, don't show back links
        if ($currentpage > 1) {
           // show << link to go back to page 1
           echo "<li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage=1' class=\"no-bg\"><i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i></a></li>";
           // get previous page num
           $prevpage = $currentpage - 1;
           // show < link to go back to 1 page
           echo " <li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage={$prevpage}' class=\"no-bg\"><i class=\"fa fa-chevron-left\"></i></a></li> ";
        } else {// end if
           echo " <li><a href='javascript:void();' class=\"no-bg\"><i class=\"fa fa-chevron-left\"></i></a></li> ";
        }

        // loop to show links to range of pages around current page
        for ($x = ($currentpage - $range); $x < (($currentpage + $range) + 1); $x++) {
           // if it's a valid page number...
           if (($x > 0) && ($x <= $totalpages)) {
              // if we're on current page...
              if ($x == $currentpage) {
                 // 'highlight' it but don't make a link
                 echo "<li class=\"active\"><a href=\"#\">{$x}</a></li>";
              // if not current page...
              } else {
                 // make it a link
                 echo " <li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage={$x}'>{$x}</a></li> ";
              } // end else
           } // end if
        } // end for

        // if not on last page, show forward and last page links
        if ($currentpage != $totalpages) {
           // get next page
           $nextpage = $currentpage + 1;
            // echo forward link for next page
           echo " <li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage={$nextpage}' class=\"no-bg\"><i class=\"fa fa-chevron-right\"></i></a></li> ";
           // echo forward link for lastpage
          echo "<li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage={$totalpages}' class=\"no-bg\"><i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i></a></li>";
        } else { // end if
           echo " <li><a href='javascript:void(0);' class=\"no-bg\"><i class=\"fa fa-chevron-right\"></i></a></li> ";
        }
        /****** end build pagination links ******/
    }

    echo " <li {$all_style}><a href='{$_SERVER['SCRIPT_NAME']}?currentpage=all'>All</a></li> ";

    echo "</ul></div>";

    return true;
}

/***************************************
 * FUNCTION: GET SUBMITTED RISKS TABLE *
 ***************************************/
function get_submitted_risks_table()
{
    global $lang;
    global $escaper;

        // Get risks
        $risks = get_risks(11);

        echo "<table id=\"submitted_risk\" class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['SubmissionDate']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['CalculatedRisk']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['Team']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['SubmittedBy']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // For each risk
        foreach ($risks as $risk)
        {
            // Get the risk color
            $color = get_risk_color($risk['calculated_risk']);

            echo "<tr>\n";
            echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk['id'])) . "\">" . $escaper->escapeHtml(convert_id($risk['id'])) . "</a></td>\n";
            echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($risk['subject']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
            echo "<td class=\"risk-cell\" align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"150px\">" . $escaper->escapeHtml($risk['calculated_risk']) . " <span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . " \"></span> </td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['status']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['team']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['name']) . "</td>\n";
            echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "
        <script>
            $(document).ready(function(){
                $('#submitted_risk thead tr:eq(0)').clone(true).appendTo($('#submitted_risk thead'));
                $('#submitted_risk  thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    $( 'input, select', this ).on( 'keyup change', function () {
                        if ( riskTable.column(i).search() !== this.value ) {
                            riskTable.column(i).search( this.value ).draw();
                        }
                    });
                });
                var riskTable = $('#submitted_risk').DataTable( {
                    paging: false,
                    orderCellsTop: true,
                    fixedHeader: true,
                    dom : 'lrti',
                    order: [[2, 'desc']],
                });
             });
        </script>
        ";

        return true;
}

/***********************************
 * FUNCTION: GET MITIGATIONS TABLE *
 ***********************************/
function get_mitigations_table()
{
    global $lang;
    global $escaper;

        // Get risks
        $risks = get_risks(13);

        echo "<table id=\"mitigations_risk\" class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['MitigationDate']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['PlanningStrategy']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['MitigationEffort']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['MitigationCost']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['MitigationOwner']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['MitigationTeam']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['SubmittedBy']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // For each risk
        foreach ($risks as $risk)
        {
            echo "<tr>\n";
            echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk['id'])) . "\">" . $escaper->escapeHtml(convert_id($risk['id'])) . "</a></td>\n";
            echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($risk['subject']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['planning_strategy']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['mitigation_effort']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(get_asset_value_by_id($risk['mitigation_cost'])) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['mitigation_owner']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['mitigation_team']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['name']) . "</td>\n";
            echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "
        <script>
            $(document).ready(function(){
                $('#mitigations_risk thead tr:eq(0)').clone(true).appendTo($('#mitigations_risk thead'));
                $('#mitigations_risk  thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    $( 'input, select', this ).on( 'keyup change', function () {
                        if ( riskTable.column(i).search() !== this.value ) {
                            riskTable.column(i).search( this.value ).draw();
                        }
                    });
                });
                var riskTable = $('#mitigations_risk').DataTable( {
                    paging: false,
                    orderCellsTop: true,
                    fixedHeader: true,
                    dom : 'lrti',
                    order: [[2, 'desc']],
                });
             });
        </script>
        ";

        return true;
}

/*************************************
 * FUNCTION: GET REVIEWED RISK TABLE *
 *************************************/
function get_reviewed_risk_table($sort_order=12)
{
    global $lang;
    global $escaper;

        // Get risks
        $risks = get_risks($sort_order);

        echo "<table id=\"reviewed_risk\" class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['ReviewDate']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Review']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['NextStep']) ."</th>\n";
        echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['Reviewer']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // For each risk
        foreach ($risks as $risk)
        {
            echo "<tr>\n";
            echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk['id'])) . "\">" . $escaper->escapeHtml(convert_id($risk['id'])) . "</a></td>\n";
            echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($risk['subject']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['review']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['next_step']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['name']) . "</td>\n";
            echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "
        <script>
            $(document).ready(function(){
                $('#reviewed_risk thead tr:eq(0)').clone(true).appendTo($('#reviewed_risk thead'));
                $('#reviewed_risk  thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    $( 'input, select', this ).on( 'keyup change', function () {
                        if ( riskTable.column(i).search() !== this.value ) {
                            riskTable.column(i).search( this.value ).draw();
                        }
                    });
                });
                var riskTable = $('#reviewed_risk').DataTable( {
                    paging: false,
                    orderCellsTop: true,
                    fixedHeader: true,
                    dom : 'lrti',
                    order: [[2, 'desc']],
                });
             });
        </script>
        ";

        return true;
}

/***************************************
 * FUNCTION: GET CLOSED RISKS TABLE *
 ***************************************/
function get_closed_risks_table($sort_order=17)
{
    global $lang;
    global $escaper;

    // Get risks
    $risks = get_risks($sort_order);

    echo "<table id=\"closeded_risk\" class=\"table table-bordered table-condensed sortable\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
    echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['CalculatedRisk']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['Team']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['DateClosed']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['ClosedBy']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['CloseReason']) ."</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // For each risk
    foreach ($risks as $risk)
    {
        // Get the risk color
        $color = get_risk_color($risk['calculated_risk']);
        
        echo "<tr>\n";
        echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk['id'])) . "\">" . $escaper->escapeHtml(convert_id($risk['id'])) . "</a></td>\n";
        echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($risk['subject']) . "</td>\n";
        echo "<td class=\"risk-cell\" align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"150px\">" . $escaper->escapeHtml($risk['calculated_risk']) . " <span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . " \"></span> </td>\n";
                echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['team']) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . (!$risk['closure_date'] ? "" : $escaper->escapeHtml(date("YmdHis", strtotime($risk['closure_date'])))) . "\">"
            . ( !$risk['closure_date'] ? $lang["Unknown"] : $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['closure_date']))) ) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['user']) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['close_reason']) . "</td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";
    echo "
        <script>
            $(document).ready(function(){
                $('#closeded_risk thead tr:eq(0)').clone(true).appendTo($('#closeded_risk thead'));
                $('#closeded_risk  thead tr:eq(1) th').each( function (i) {
                    var title = $(this).text();
                    $(this).html(''); // To clear the title out of the header cell
                    $('<input type=\"text\">').attr('name', title).attr('placeholder', title).appendTo($(this));
                    $( 'input, select', this ).on( 'keyup change', function () {
                        if ( riskTable.column(i).search() !== this.value ) {
                            riskTable.column(i).search( this.value ).draw();
                        }
                    });
                });
                var riskTable = $('#closeded_risk').DataTable( {
                    paging: false,
                    orderCellsTop: true,
                    fixedHeader: true,
                    dom : 'lrti',
                    order: [[4, 'desc']],
                });
             });
        </script>
        ";

    return true;
}

/**********************************
 * FUNCTION: GET RISK TEAMS TABLE *
 **********************************/
function get_risk_teams_table()
{
    global $lang;
    global $escaper;

    // Get risks
    $risks = get_risks(18);

    // Set the current team to empty
    $current_team = "";
    
    // For each team
    foreach ($risks as $risk)
    {
        $risk_id = (int)$risk['id'];
        $subject = $risk['subject'];
        $team = $risk['team'];
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
                echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"\">". $escaper->escapeHtml($current_team) ."</font></center></th>\n";
                echo "</tr>\n";
                echo "<tr>\n";
                echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
                echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
                echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
                echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
                echo "</tr>\n";
                echo "</thead>\n";
            echo "<tbody>\n";
        }

        // Display the risk information
                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
                echo "</tr>\n";
    }
}

/*****************************************
 * FUNCTION: GET RISK TECHNOLOGIES TABLE *
 *****************************************/
function get_risk_technologies_table($sort_order=19)
{
    global $lang, $escaper;

    // Get risks
    $risks = get_risks($sort_order);

    // Set the current technology to empty
    $current_technology = "";

    // For each technology
    foreach ($risks as $risk)
    {
        $risk_id = (int)$risk['id'];
        $subject = $risk['subject'];
        $technology = $risk['technology'];
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
            echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"\">". $escaper->escapeHtml($current_technology) ."</font></center></th>\n";
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
            echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
            echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
            echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";
        }

        // Display the risk information
        echo "<tr>\n";
        echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
        echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
        echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
        echo "</tr>\n";
    }
}

/************************************
 * FUNCTION: GET RISK SCORING TABLE *
 ************************************/
function get_risk_scoring_table()
{
    global $lang;
    global $escaper;

    echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($lang['ClassicRiskScoring']) ."</font></center></th>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(10);

        // For each risk
        foreach ($risks as $risk)
        {
            $subject = $risk['subject'];
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($lang['CVSSRiskScoring']) ."</font></center></th>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(9);

        // For each risk
        foreach ($risks as $risk)
        {
                $subject = $risk['subject'];
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($lang['DREADRiskScoring']) ."</font></center></th>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(14);

        // For each risk
        foreach ($risks as $risk)
        {
                $subject = $risk['subject'];
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($lang['OWASPRiskScoring']) ."</font></center></th>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(15);

        // For each risk
        foreach ($risks as $risk)
        {
                $subject = $risk['subject'];
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "<br />\n";

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($lang['CustomRiskScoring']) ."</font></center></th>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
        echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
        echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
        echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get risks marked as consider for projects
        $risks = get_risks(16);

        // For each risk
        foreach ($risks as $risk)
        {
                $subject = $risk['subject'];
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                echo "<tr>\n";
                echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
    global $escaper;

    // Get projects
    $projects = get_projects();

    // For each project
    foreach ($projects as $project)
    {
        $id = (int)$project['value'];
        $name = $project['name'];
        $order = (int)$project['order'];

        // If the project is not 0 (ie. Unassigned Risks)
        if ($id != 0)
        {
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">" . $escaper->escapeHtml($name) . "</font></center></th>\n";
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
            echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
            echo "<th align=\"left\" width=\"100px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
            echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Get risks marked as consider for projects
            $risks = get_risks(5);

            // For each risk
            foreach ($risks as $risk)
            {
                $subject = $risk['subject'];
                $risk_id = (int)$risk['id'];
                $project_id = (int)$risk['project_id'];
                $color = get_risk_color($risk['calculated_risk']);

                // If the risk is assigned to that project id
                if ($id == $project_id)
                {
                    echo "<tr>\n";
                    echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                    echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                    echo "<td align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"100px\">" . $escaper->escapeHtml($risk['calculated_risk']) . "</td>\n";
                    echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
    global $escaper;

        // Get projects
        $projects = get_projects();

    echo "<form action=\"\" method=\"post\">\n";
    echo "<input type=\"submit\" name=\"update_order\" value=\"". $escaper->escapeHtml($lang[ 'Update']) ."\" /><br /><br />\n";
    echo "<ul id=\"prioritize\">\n";

        // For each project
        foreach ($projects as $project)
        {
        $id = (int)$project['value'];
        $name = $project['name'];
        $order = $project['order'];

        // If the project is not 0 (ie. Unassigned Risks)
        if ($id != 0 && $project['status'] != 3)
        {
            echo "<li class=\"ui-state-default\" id=\"sort_" . $escaper->escapeHtml($id) . "\">\n";
            echo "<span>&#x21C5;</span>&nbsp;" . $escaper->escapeHtml($name) . "\n";
            echo "<input type=\"hidden\" id=\"order" . $escaper->escapeHtml($id) . "\" name=\"order_" . $escaper->escapeHtml($id) . "\" value=\"" . $escaper->escapeHtml($order) . "\" />\n";
            echo "<input type=\"hidden\" name=\"ids[]\" value=\"" . $escaper->escapeHtml($id) . "\" />\n";
            echo "</li>\n";
        }
    }

    echo "</ul>\n";
    echo "<br /><input type=\"submit\" name=\"update_order\" value=\"". $escaper->escapeHtml($lang[ 'Update']) ."\" />\n";
    echo "</form>\n";

    return true;
}

/********************************
 * FUNCTION: GET PROJECT STATUS *
 ********************************/
function get_project_status()
{
    global $lang;
    global $escaper;

        // Get projects
        $projects = get_projects();

    echo "<form action=\"\" method=\"post\">\n";
    echo "<div id=\"statustabs\">\n";
    echo "<ul>\n";
        echo "<li><a href=\"#statustabs-1\">". $escaper->escapeHtml($lang['ActiveProjects']) ."</a></li>\n";
        echo "<li><a href=\"#statustabs-2\">". $escaper->escapeHtml($lang['OnHoldProjects']) ."</a></li>\n";
        echo "<li><a href=\"#statustabs-3\">". $escaper->escapeHtml($lang['CompletedProjects']) ."</a></li>\n";
        echo "<li><a href=\"#statustabs-4\">". $escaper->escapeHtml($lang['CancelledProjects']) ."</a></li>\n";
    echo "</ul>\n";

    // For each of the project status types
    for ($i=1; $i <=4; $i++)
    {
        echo "<div id=\"statustabs-".$i."\">\n";
        echo "<ul id=\"statussortable-".$i."\" class=\"connectedSortable ui-helper-reset\">\n";

            foreach ($projects as $project)
            {
                    $id = (int)$project['value'];
                    $name = $project['name'];
            $status = $project['status'];

            // If the status is the same as the current project status and the name is not Unassigned Risks
            if ($status == $i && $name != "Unassigned Risks")
            {

                                echo "<li id=\"" . $escaper->escapeHtml($id) . "\" class=\"project\">" . $escaper->escapeHtml($name) . "\n";
                                echo "<input class=\"assoc-project-with-status\" type=\"hidden\" id=\"project" . $escaper->escapeHtml($id) . "\" name=\"project_" . $escaper->escapeHtml($id) . "\" value=\"" . $escaper->escapeHtml($status) . "\" />\n";
                                echo "<input id=\"all-project-ids\" class=\"all-project-ids\" type=\"hidden\" name=\"projects[]\" value=\"" . $escaper->escapeHtml($id) . "\" />\n";
                                echo "</li>\n";
            }
        }

            echo "</ul>\n";
            echo "</div>\n";
        }

    echo "</div>\n";
    echo "<br /><input type=\"submit\" name=\"update_project_status\" value=\"" . $escaper->escapeHtml($lang['UpdateProjectStatuses']) ."\" />\n";
    echo "</form>\n";

        return true;
}

/**********************************
 * FUNCTION: CHANGE PROJECT PRIORITY *
 **********************************/
function update_project_priority($ids)
{
        // Open the database connection
        $db = db_open();
        $i = 1;
        foreach ($ids as $key => $id)
        {
                $stmt = $db->prepare("UPDATE projects SET `order` = :order WHERE `value` = :id");
                $stmt->bindParam(":order", $i, PDO::PARAM_INT);
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);

                $stmt->execute();
                $i++;
        }

        // Close the database connection
        db_close($db);

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
 * FUNCTION: CLONE RISK PROJECT *
 *********************************/
function clone_risk_project($project_id, $risk_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM risks WHERE id = :risk_id");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();
//        var_dump($array[0]['project_id']);
//        exit;

    if (!empty ($array) && $array[0]['project_id'] != 0)
    {
            $stmt = $db->prepare("INSERT INTO risks (`status`, `subject`, `reference_id`, `regulation`, `control_number`, `source`, `category`, `technology`, `owner`, `manager`, `assessment`, `notes`, `submitted_by`, last_update, review_date, mitigation_id, mgmt_review, project_id) SELECT `status`, `subject`, `reference_id`, `regulation`, `control_number`, `source`, `category`, `technology`, `owner`, `manager`, `assessment`, `notes`, `submitted_by`, last_update, review_date, mitigation_id, mgmt_review, :project_id as project_id FROM risks WHERE id = :risk_id");
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
            $stmt->execute();

            $last_insert_id = $db->lastInsertId();

            // Clone location
            $stmt = $db->prepare("INSERT INTO risk_to_location(`risk_id`, `location_id`) SELECT :new_risk_id, location_id FROM risk_to_location WHERE risk_id = :risk_id;");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

            // Clone team
            $stmt = $db->prepare("INSERT INTO risk_to_team(`risk_id`, `team_id`) SELECT :new_risk_id, team_id FROM risk_to_team WHERE risk_id = :risk_id;");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

            // Clone technology
            $stmt = $db->prepare("INSERT INTO risk_to_technology(`risk_id`, `technology_id`) SELECT :new_risk_id, technology_id FROM risk_to_technology WHERE risk_id = :risk_id;");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

            // Clone additional stakeholders
            $stmt = $db->prepare("INSERT INTO risk_to_additional_stakeholder(`risk_id`, `user_id`) SELECT :new_risk_id, user_id FROM risk_to_additional_stakeholder WHERE risk_id = :risk_id;");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

            // Clone risk scoring
            $stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact`) SELECT :new_risk_id as id, `scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact` FROM risk_scoring WHERE id = :risk_id");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

            // Clone mitigations
            $stmt = $db->prepare("INSERT INTO mitigations (`risk_id`, `planning_strategy`, `mitigation_effort`, `mitigation_cost`, `mitigation_owner`, `mitigation_team`, `current_solution`, `security_requirements`, `security_recommendations`, `submitted_by`) SELECT :new_risk_id as risk_id, `planning_strategy`, `mitigation_effort`, `mitigation_cost`, `mitigation_owner`, `mitigation_team`, `current_solution`, `security_requirements`, `security_recommendations`, `submitted_by` FROM mitigations WHERE risk_id = :risk_id");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

            // Clone reviews
            $stmt = $db->prepare("INSERT INTO mgmt_reviews (`risk_id`, `review`, `reviewer`, `next_step`, `comments`, `next_review`) SELECT :new_risk_id as risk_id, `review`, `reviewer`, `next_step`, `comments`, `next_review` FROM mgmt_reviews WHERE risk_id =:risk_id ");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

    } else
    {
            $stmt = $db->prepare("UPDATE risks SET `project_id` = :project_id WHERE `id` = :risk_id");
            $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);

            $stmt->execute();
    }
    // Close the database connection
    db_close($db);

    return true;
}


/*********************************
 * FUNCTION: UPDATE RISK PROJECT *
 *********************************/
function update_risk_project($project_id, $risk_id)
{
    global $lang;
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE risks SET `project_id` = :project_id WHERE `id` = :risk_id");
    $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);

    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Audit log
    write_log($risk_id + 1000, $_SESSION['uid'],
        _lang('RiskProjectAssociationAuditLog',
            array(
                'risk_id' => $risk_id + 1000,
                'project_name' => get_name_by_value('projects', $project_id, $lang['UnassignedRisks']),
                'user' => $_SESSION['user']
            )
        )
    );

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

/******************************************
 * FUNCTION: GET PROJECTS COUNT BY STATUS *
 ******************************************/
function get_projects_count($status)
{
    $projects = count_by_status($status);
    if ($status == 1)
    {
          echo $projects[0]['count'];
    }
    else
    {
          echo $projects[0]['count'];
    }
}

/********************************************
 * FUNCTION: UPDATE PROJECTS HTML BY STATUS *
 ********************************************/
function get_project_tabs($status, $template_group_id="")
{
    global $lang;
    global $escaper;

    display_project_table_header();

    $projects = get_projects();

    if ($status == 1)
    {
        array_unshift($projects, ['value' => 0, 'name' => $escaper->escapeHtml($lang['UnassignedRisks']), 'status' => 1]);
    } 
    
    $index = 0;
    $str = "";
    $row_width = "1301";
    $custom_field_count = 0;
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $customization = true;
        if(!$template_group_id){
            $group = get_default_template_group("project");
            $template_group_id = $group["id"];

        }
        $active_fields = get_active_fields("project", $template_group_id);
        foreach($active_fields as $field){
            if($field['is_basic'] != 1) $custom_field_count++;
        }
    } else $customization = false;
    $row_width += $custom_field_count * 150;
    foreach ($projects as $project)
    {
        if ($project['status'] == $status)
        {
            $id = (int)$project['value'];
            $name = $project['name'];

            // If unassigned risks
            if (!$id)
            {
                $delete = '';
                $no_sort = 'id = "no-sort"';
                $name = $escaper->escapehtml($lang['UnassignedRisks']);
                $due_date = "";
                $consultant = "";
                $business_owner = "";
                $data_classification = "";

                // Get risks for this project
                $risks = get_risks_unassigned_project();
                $priority = "";
                $edit_link = "";
            }
            // If project ID was defined
            else
            {
                if(isset($_SESSION["delete_projects"]) && $_SESSION["delete_projects"] == 1) {
                    $delete = '<a href="javascript:void(0);" class="project-block--delete pull-right" data-id="'.$escaper->escapeHtml($id).'"><i class="fa fa-trash"></i></a>';
                } else $delete ='';
                $no_sort = '';
                $name = $escaper->escapeHtml($name);
                $due_date = format_date($project['due_date']);
                $consultant = get_user_name($project['consultant']);
                $business_owner = get_user_name($project['business_owner']);
                $data_classification = get_table_value_by_id("data_classification", $project['data_classification']);

                // Get risks for this project
                $risks = get_risks_by_project_id($id);
                $index++;
                $priority = $index;
                $edit_link = '<a href="javascript:void(0);" class="project-block--edit pull-right" data-id="'.$escaper->escapeHtml($id).'" data-name="'.$name.'"><i class="fa fa-edit"></i></a>';
            }
            
            // Get count of risks for this project
            $count = count($risks);

            $str .= '<div class="project-block clearfix" '.$no_sort.' style="width:'.$row_width.'px">';
                $str .= '<div class="project-block--header clearfix" data-project="'.$escaper->escapeHtml($id).'">
                <div class="project-block--priority pull-left">'.$escaper->escapeHtml($priority).'</div>';
                if($customization == true){
                    foreach($active_fields as $field)
                    {
                        if($field['is_basic'] == 1)
                        {
                            switch($field['name']){
                                case 'ProjectName':
                                    $str .= '<div class="project-block--name pull-left">'. $name .'</div>';
                                break;
                                case 'DueDate':
                                    $str .= '<div class="project-block--field pull-left">'. $due_date .'</div>';
                                break;
                                case 'Consultant':
                                    $str .= '<div class="project-block--field pull-left">'. $consultant .'</div>';
                                break;
                                case 'BusinessOwner':
                                    $str .= '<div class="project-block--field pull-left">'. $business_owner .'</div>';
                                break;
                                case 'DataClassification':
                                    $str .= '<div class="project-block--field pull-left">'. $data_classification .'</div>';
                                break;
                            }
                        } 
                        else {
                            $custom_field_count++;
                            $text = get_plan_custom_field_name_by_row_id($field, $id, "project");
                            $str .= '<div class="project-block--field pull-left">'. $text .'</div>';
                        }
                    }

                } else {
                    $str .= '
                        <div class="project-block--name pull-left">'. $name .'</div>
                        <div class="project-block--field pull-left">'. $due_date .'</div>
                        <div class="project-block--field pull-left">'. $consultant .'</div>
                        <div class="project-block--field pull-left">'. $business_owner .'</div>
                        <div class="project-block--field pull-left">'. $data_classification .'</div>
                    ';
                }
                $str .= '
                    <div class="project-block--risks pull-left"><span>'.$count.'</span><a href="#" class="view--risks">'.$escaper->escapeHtml($lang['ViewRisk']).'</a>'.$delete.$edit_link.'</div>
                </div>';

                $str .= '<div class="risks">';

                // For each risk
                foreach ($risks as $risk)
                {
                    $subject = try_decrypt($risk['subject']);
                    $risk_id = (int)$risk['id'];
                    $project_id = (int)$risk['project_id'];
                    $color = get_risk_color($risk['calculated_risk']);

                    $risk_number = (int)$risk_id + 1000;

                    $str .= '<div class="risk clearfix">
                            <div class="pull-left risk--title"  data-risk="'.$escaper->escapeHtml($risk_id).'"><a href="../management/view.php?id=' . $escaper->escapeHtml(convert_id($risk_id)) . '" target="_blank">#'.$risk_number.' '.$escaper->escapeHtml($subject).'</a></div>
                            <div class="pull-right risk--score"> ' . $escaper->escapeHtml($lang['InherentRisk']) . ' : <span class="label label-danger" style="background-color: '. $escaper->escapeHtml($color) .'">'.$risk['calculated_risk'].'</span> </div>
                            </div>';
                }

                $str .= "</div>\n";

            $str .= "</div>\n";
        }
    }
    return $str;

    //echo "</div>\n";
    //echo "<br /><input type=\"submit\" name=\"update_projects\" value=\"". $escaper->escapeHtml($lang['SaveRisksToProjects']) ."\" />\n";
    //echo "</form>\n";
}

/**************************************************
 * FUNCTION: GET PROJECTS COUNT FROM DB BY STATUS *
 **************************************************/
function count_by_status($status)
{
    $db = db_open();


    $stmt = $db->prepare("SELECT count(*) as count FROM projects WHERE `status` = $status");


    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
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

    // For each project
    foreach ($array as $key => $project)
    {
        // Try to decrypt the project name
        $array[$key]['name'] = try_decrypt($project['name']);
    }

    // Close the database connection
    db_close($db);

    return $array;
}

/*******************************
 * FUNCTION: GET PROJECT RISKS *
 *******************************/
function get_project_risks($project_id)
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT * FROM risks WHERE project_id = :project_id");
        $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Return the array of risks
    return $array;
}

/*******************************
 * FUNCTION: GET TAG VALUE  *
 *******************************/
function getTextBetweenTags($string, $tagname) {
    $pattern = "/<$tagname ?.*>(.*)<\/$tagname>/";
    preg_match($pattern, $string, $matches);
    return isset($matches[1]) ? $matches[1] : $string;
}

/***********************************
 * FUNCTION: GET DELETE RISK TABLE *
 ***********************************/
function get_delete_risk_table()
{
    global $lang;
    global $escaper;

    // Get risks
    $risks = get_risks(21);

    echo "<table class=\"table table-bordered table-condensed sortable\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" width=\"75\"><input type=\"checkbox\" onclick=\"checkAll(this)\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['Delete']) . "</th>\n";
    echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
    echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // For each risk
    foreach ($risks as $risk)
    {
        $risk_id = $risk['id'];
        $subject = $risk['subject'];
        $status = $risk['status'];

        echo "<tr>\n";
        echo "<td align=\"center\">\n";
        echo "<input type=\"checkbox\" name=\"risks[]\" value=\"" . $escaper->escapeHtml($risk['id']) . "\" />\n";
        echo "</td>\n";
        echo "<td align=\"left\" width=\"50px\"><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
        echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($status) . "</td>\n";
        echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";
}

/*******************************
 * FUNCTION: MANAGEMENT REVIEW *
 *******************************/
function management_review($risk_id, $mgmt_review, $next_review, $is_html = true, $active="ReviewRisksRegularly")
{
    global $lang;
    global $escaper;

    // If the review hasn't happened
    if ($mgmt_review == "0")
    {
        $html = "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) ."&type=2&action=editreview&active={$active}\">". $escaper->escapeHtml($lang['No']) ."</a>";
        $text = $lang['No'];
    }
    else
    {
        if($next_review != $lang['PASTDUE'] ){
            // If review doensn't past due.
            $html = "<a class=\"management yes\" href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) ."&type=2&action=editreview&active={$active}\">".$escaper->escapeHtml($lang['Yes']).'</a>';
            $text = $lang['Yes'];
        }else{
            // If review past due.
            $html = "<a class=\"management pastdue\" href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) ."&type=2&action=editreview&active={$active}\">".$escaper->escapeHtml($lang['PASTDUE']).'</a>';
            $text = $lang['PASTDUE'];
        }
    }
    
    if($is_html)
    {
        return $html;
    }
    else
    {
        return $text;
    }
}
/*
    Return logic is the same as the above function, only returning the texts, no html tags.
    Used for sorting.
*/
function management_review_text_only($mgmt_review_id, $next_review) {
    global $lang, $escaper;

    return $escaper->escapeHtml($lang[$mgmt_review_id == "0" ? 'No' : ($next_review != $lang['PASTDUE'] ? 'Yes' : 'PASTDUE')]);

}
/********************************
 * FUNCTION: PLANNED MITIGATION *
 ********************************/
function planned_mitigation($risk_id, $mitigation_id, $active="ReviewRisksRegularly")
{
    global $lang;
    global $escaper;

    // If the review hasn't happened
    if (!$mitigation_id)
    {
        $value = "<a href=\"../management/view.php?type=1&id=" . $escaper->escapeHtml($risk_id) . "&action=editmitigation&active={$active}\">". $escaper->escapeHtml($lang['No']) ."</a>";
    }
    else
    {
        $value = "<a class=\"mitigation yes\" href=\"../management/view.php?type=1&id=" . $escaper->escapeHtml($risk_id) . "&active={$active}\">".$escaper->escapeHtml($lang['Yes'])."</a>";
    }

    return $value;
}

/*******************************
 * FUNCTION: GET VALUE BY NAME *
 *******************************/
function get_value_by_name($table, $name, $return_name = false)
{
    $table_key = $table."_get_value_by_name";
    $value = false;
    if(isset($GLOBALS[$table_key])){
        foreach($GLOBALS[$table_key] as $row){
            if(strtolower($row['name']) == strtolower($name)){
                $value = isset($row['value']) ? $row['value'] : $row['id'];
                break;
            }
        }
    }

    if(!$value || !isset($GLOBALS[$table_key])){
        // Open the database connection
        $db = db_open();

        // Get the user information
        $stmt = $db->prepare("SELECT * FROM {$table}");
        $stmt->execute();

        // Store the list in the array
        $GLOBALS[$table_key] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Close the database connection
        db_close($db);

        global $tables_where_name_is_encrypted;

        if (in_array($table, $tables_where_name_is_encrypted)) {
            foreach($GLOBALS[$table_key] as &$row){
                $row['name'] = try_decrypt($row['name']);
            }
        }

        foreach($GLOBALS[$table_key] as &$row){
            if(strtolower($row['name']) == strtolower($name)){
                $value = isset($row['value']) ? $row['value'] : $row['id'];
                break;
            }
        }
    }

    // If the array is empty
    if ($value === false && $return_name)
    {
        // If want to return name for non-exist name
        return $name;
    }elseif($value === false && !$return_name){
        // If don't want to return name for non-exist name
        return null;
    }
    // Otherwise, return the first value in the array
    else return $value;
}

/*******************************
 * FUNCTION: GET NAME BY VALUE *
 *******************************/
function get_name_by_value($table, $value, $default = "", $use_id = false)
{
    global $tables_where_name_is_encrypted;
    
    $global_name_key = "name_by_value_".$table.$value;
    
    // If this query was already run, get name from cache
    if(isset($GLOBALS[$global_name_key]))
    {
        $name = $GLOBALS[$global_name_key];
        
        // If name is not null, return cache value
        if($name !== null)
        {
            return $name;
        }
        // If name is null, return default value
        else
        {
            return $default;
        }
    }
    // If this is first, run query to get name 
    else
    {
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT name FROM {$table} WHERE " .($use_id ? "id" : "value") . "=:value LIMIT 1");
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If we get a value back from the query
        if (isset($array[0]['name']))
        {
            // Try decrypt if necessary
            if (in_array($table, $tables_where_name_is_encrypted))
                $name = try_decrypt($array[0]['name']);
            // Return that value
            else
                $name = $array[0]['name'];
                
            $GLOBALS[$global_name_key] = $name;
        }
        // Otherwise, return an empty string
        else 
        {
            $name = $default;
            $GLOBALS[$global_name_key] = null;
        }
        
        return $name;
    }
}

/***************************************
 * FUNCTION: GET NAMEs BY MULTI VALUES *
 ***************************************/
function get_names_by_multi_values($table, $values, $return_array=false, $impolode_separator=", ", $use_id=false) {

    if (is_array($values))
        $values = implode(",", $values);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT name FROM {$table} WHERE FIND_IN_SET(" . ($use_id ? "id" : "value") . ", :values);");
    $stmt->bindParam(":values", $values, PDO::PARAM_STR);

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Close the database connection
    db_close($db);

    // If we get a value back from the query
    if ($array) {
        global $tables_where_name_is_encrypted;
        // Try decrypt if necessary
        if (in_array($table, $tables_where_name_is_encrypted)) {

            // For each entry
            foreach ($array as &$entry) {
                // Try to decrypt it
                $entry = try_decrypt($entry);
            }
        }

        // Return that value
        return $return_array ? $array : implode($impolode_separator, $array);
    }
    // Otherwise, return an empty string/array
    else return $return_array ? [] : "";
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
        return $array[0]['metric_value'];
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

    if ($array) {
        return $array[0]['id'];
    } else {
        return false;
    }
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
function dayssince($date, $date2 = null)
{
    // Set the first date to the provided value
    $datetime1 = new DateTime($date);

    // If the second date is null
    if ($date2 == null)
    {
        // Set it to the current date and time
        $datetime2 = new DateTime("now");
    }
    // Otherwise
    else
    {
        $datetime2 = new DateTime($date2);
    }

    // Get the difference between the two dates
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

/**
* Get next review date by risk scoring
*
* @param mixed $risk_id
*/
function get_next_review_default($risk_id){
    global $escaper;

    $id = intval($risk_id) + 1000;
    $risk = get_risk_by_id($id);
    if($risk)
    {
        if(get_setting('next_review_date_uses') == "ResidualRisk")
        {
            $next_review = next_review_by_score($risk[0]['residual_risk']);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $next_review = next_review_by_score($risk[0]['calculated_risk']);
        }

    }
    else
    {
        $next_review = "0000-00-00";
    }

    return $escaper->escapeHtml($next_review);
}

/**********************************
 * FUNCTION: GET NEXT REVIEW DATE *
 **********************************/
function next_review($risk_level, $id, $next_review, $html = true, $review_levels = array(), $submission_date = false, $return_standard_format = false)
{
    global $lang;
    global $escaper;

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
            if($submission_date === false){
                $last_review = get_last_review($id);
            }else{
                $last_review = $submission_date;
            }

            // Get the review levels
            if(!$review_levels){
                $review_levels = get_review_levels();
            }

            $very_high_display_name = get_risk_level_display_name('Very High');
            $high_display_name      = get_risk_level_display_name('High');
            $medium_display_name    = get_risk_level_display_name('Medium');
            $low_display_name       = get_risk_level_display_name('Low');
            $insignificant_display_name = get_risk_level_display_name('Insignificant');

            // If very high risk
            if ($risk_level === $very_high_display_name)
            {
                // Get days to review very high risks
                $days = $review_levels[0]['value'];
            }
            // If high risk
            else if ($risk_level == $high_display_name)
            {
                // Get days to review high risks
                $days = $review_levels[1]['value'];
            }
            // If medium risk
            else if ($risk_level == $medium_display_name)
            {
                // Get days to review medium risks
                $days = $review_levels[2]['value'];
            }
            // If low risk
            else if ($risk_level == $low_display_name)
            {
                // Get days to review low risks
                $days = $review_levels[3]['value'];
            }
            // If insignificant risk
//            else if ($color == "white")
            else
            {
                // Get days to review insignificant risks
                $days = $review_levels[4]['value'];
            }

            // Next review date
            $last_review = new DateTime($last_review);
            $next_review = $last_review->add(new DateInterval('P'.$days.'D'));
        }
        // A custom next review date was used
        else if($next_review == $lang['PASTDUE']){

        }else{
            $next_review = new DateTime($next_review);
        }

        // If the next review date is after today
        if ($next_review != $lang['PASTDUE'] && (strtotime($next_review->format('Y-m-d')) + 24*3600) > time())
        {
            $date_format = $return_standard_format ? 'Y-m-d' : get_default_date_format();
            
            $text = $next_review->format($date_format);
        }
        else $text = $lang['PASTDUE'];
    }

    // If we want to include the HTML code
    if ($html == true)
    {
        // Convert the database ID to a risk ID
        $risk_id = convert_id($id);

        // Add the href tag to make it HTML
        $html = "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=2&action=editreview\">" . $escaper->escapeHtml($text) . "</a>";

        // Return the HTML code
        return $html;
    }
    // Otherwise just return the text
    else return $escaper->escapeHtml($text);
}

/**********************************
 * FUNCTION: NEXT REVIEW BY SCORE *
 **********************************/
function next_review_by_score($calculated_risk)
{
    // Get risk level name by score
    $level = get_risk_level_name($calculated_risk);

        // Get the review levels
    $review_levels = get_review_levels();

    $very_high_display_name = get_risk_level_display_name('Very High');
    $high_display_name      = get_risk_level_display_name('High');
    $medium_display_name    = get_risk_level_display_name('Medium');
    $low_display_name       = get_risk_level_display_name('Low');
    $insignificant_display_name = get_risk_level_display_name('Insignificant');

    // If very high risk
    if ($level == $very_high_display_name)
    {
        // Get days to review high risks
        $days = $review_levels[0]['value'];
    }
    // If high risk
    else if ($level == $high_display_name)
    {
        // Get days to review high risks
        $days = $review_levels[1]['value'];
    }
    // If medium risk
    else if ($level == $medium_display_name)
    {
        // Get days to review medium risks
        $days = $review_levels[2]['value'];
    }
    // If low risk
    else if ($level == $low_display_name)
    {
        // Get days to review low risks
        $days = $review_levels[3]['value'];
    }
    // If insignificant risk
//    else if ($color == "white")
    else
    {
        // Get days to review insignificant risks
        $days = $review_levels[4]['value'];
    }

    // Next review date
    $today = new DateTime('NOW');
    $next_review = $today->add(new DateInterval('P'.$days.'D'));
    $default_date_format = get_default_date_format();
    $next_review = $next_review->format($default_date_format);

    // Return the next review date
    return $next_review;
}

/************************
 * FUNCTION: CLOSE RISK *
 ************************/
function close_risk($risk_id, $user_id, $status, $close_reason, $note, $closure_date = false)
{
    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Add the closure
    if($closure_date !== false){
        $stmt = $db->prepare("INSERT INTO closures (`risk_id`, `user_id`, `close_reason`, `note`, `closure_date`) VALUES (:risk_id, :user_id, :close_reason, :note, :closure_date)");
        $stmt->bindParam(":closure_date", $closure_date, PDO::PARAM_STR, 20);
    }else{
        $stmt = $db->prepare("INSERT INTO closures (`risk_id`, `user_id`, `close_reason`, `note`) VALUES (:risk_id, :user_id, :close_reason, :note)");
    }

    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":close_reason", $close_reason, PDO::PARAM_INT);
    $stmt->bindParam(":note", $note, PDO::PARAM_STR);

    $stmt->execute();

    // Get the new mitigation id
    $close_id = $db->lastInsertId();



    // Update the risk
      $stmt = $db->prepare("UPDATE risks SET status=:status,last_update=:date,close_id=:close_id WHERE id = :id");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":status", $status, PDO::PARAM_STR, 50);
    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
    $stmt->bindParam(":close_id", $close_id, PDO::PARAM_INT);
    $stmt->execute();

    // If notification is enabled
    if (notification_extra())
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_risk_close($id);
    }

    // Audit log
    $message = "Risk ID \"" . $risk_id . "\" was marked as closed by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

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
function reopen_risk($risk_id)
{
    // Subtract 1000 from id
    $id = (int)$risk_id - 1000;

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Open the database connection
    $db = db_open();

    // Update the risk
    $stmt = $db->prepare("UPDATE risks SET status=\"Reopened\",last_update=:date,close_id=\"0\" WHERE id = :id");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
    $stmt->execute();

    // Audit log
    $message = "Risk ID \"" . $risk_id . "\" was reopened by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return true;
}

/*************************
 * FUNCTION: ADD COMMENT *
 *************************/
function add_comment($risk_id, $user_id, $comment)
{
    // Subtract 1000 from id
    $id = (int)$risk_id - 1000;

    $try_encrypt_comments = try_encrypt($comment);

    // Open the database connection
    $db = db_open();

     // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Add the closure
    $stmt = $db->prepare("INSERT INTO comments (`risk_id`, `user`, `comment`, `date`) VALUES (:risk_id, :user, :comment, :date)");

    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":comment", $try_encrypt_comments, PDO::PARAM_STR);
    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);

    $stmt->execute();

    // Update the risk
    $stmt = $db->prepare("UPDATE risks SET last_update=:date WHERE id = :id");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
    $stmt->execute();

    // If notification is enabled
    if (notification_extra())
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_risk_comment($id, $comment);
    }

    // Audit log
    $message = "A comment was added to risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

        // Close the database connection
        db_close($db);

        return true;
}

/**************************
 * FUNCTION: GET COMMENTS *
 **************************/
function get_comments($id, $html = true)
{
    global $escaper;

    // Subtract 1000 from id
    $id = (int)$id - 1000;

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
    if($html == true){
        foreach ($comments as $comment)
        {
            $text = try_decrypt($comment['comment']);
            $date = date(get_default_datetime_format("g:i A T"), strtotime($comment['date']));
            $user = $comment['name'];
            if($text != null){
                echo "<p class=\"comment-block\">\n";
                echo "<b>" . $escaper->escapeHtml($date) ." by ". $escaper->escapeHtml($user) ."</b><br />\n";
                echo $escaper->escapeHtml($text);
                        //echo substr($escaper->escapeHtml($text), 0, strpos($escaper->escapeHtml($text),"</p>"));
                echo "</p>\n";
            }
        }
        return true;
    } else {
        return $comments;
    }
}

/*****************************
 * FUNCTION: GET AUDIT TRAIL *
 *****************************/
function get_audit_trail($id = NULL, $days = 7, $log_type=NULL)
{
    // If the ID is greater than 1000 or NULL
    if ($id > 1000 || $id === NULL)
    {
        // Open the database connection
        $db = db_open();
        
        $query = " 
            SELECT t1.timestamp, t1.message, t1.log_type, t1.user_id, t2.name user_fullname 
            FROM audit_log t1
                LEFT JOIN user t2 ON t1.user_id=t2.value
        ";

        // If the ID is greater than 1000
        if ($id > 1000)
        {
            // Subtract 1000 from id
            $id = (int)$id - 1000;

            // If log_type is NULL, shows all logs
            if($log_type === NULL){
                $query .= " WHERE risk_id=:risk_id AND (`timestamp` > CURDATE()-INTERVAL :days DAY) ORDER BY timestamp DESC;";
                // Get the full audit trail
                $stmt = $db->prepare($query);
            }
            else
            {
                if(is_array($log_type))
                {
                    $log_type_array = $log_type;
                }
                else
                {
                    $log_type_array = array($log_type);
                }

                $query .= " WHERE risk_id=:risk_id AND (`timestamp` > CURDATE()-INTERVAL :days DAY) AND FIND_IN_SET(log_type, :log_type)  ORDER BY timestamp DESC;";
                $stmt = $db->prepare($query);
                $log_type_str = implode(",", $log_type_array);
                $stmt->bindParam(":log_type", $log_type_str, PDO::PARAM_STR);
            }

            $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":days", $days, PDO::PARAM_INT);
        }
        // If the ID is NULL
        else if ($id === NULL)
        {
            // If log_type is NULL, shows all logs
            if($log_type === NULL){
                $query .= " WHERE (`timestamp` > CURDATE()-INTERVAL :days DAY) ORDER BY timestamp DESC; ";
                // Get the full audit trail
                $stmt = $db->prepare($query);
                $stmt->bindParam(":days", $days, PDO::PARAM_INT);
            }
            else
            {
                if(is_array($log_type))
                {
                    $log_type_array = $log_type;
                }
                else
                {
                    $log_type_array = array($log_type);
                }
                $query .= " WHERE (`timestamp` > CURDATE()-INTERVAL :days DAY) AND FIND_IN_SET(log_type, :log_type) ORDER BY timestamp DESC; ";

                $stmt = $db->prepare($query);
                $log_type_str = implode(",", $log_type_array);
                $stmt->bindParam(":log_type", $log_type_str, PDO::PARAM_STR);
                $stmt->bindParam(":days", $days, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
       // Store the list in the array
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Close the database connection
        db_close($db);
        
        foreach ($logs as &$log)
        {
            $log['message'] = try_decrypt($log['message']);
        }

        // Return true
        return $logs;
    }
    // Otherwise this is not a valid ID
    else
    {
        // Return false
        return [];
    }
    
}

/*******************************
 * FUNCTION: UPDATE MITIGATION *
 *******************************/
function update_mitigation($risk_id, $post)
{
    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // Save custom fields
        save_risk_custom_field_values($risk_id);
    }

    $planning_strategy  = isset($post['planning_strategy']) ? (int)$post['planning_strategy'] : 0;
    $mitigation_effort  = isset($post['mitigation_effort']) ? (int)$post['mitigation_effort'] : 0;
    $mitigation_cost    = isset($post['mitigation_cost']) ?(int)$post['mitigation_cost'] : 0;
    $mitigation_owner   = isset($post['mitigation_owner']) ? (int)$post['mitigation_owner'] : 0;
    $mitigation_team   = isset($post['mitigation_team']) ? $post['mitigation_team'] : [];

    $current_solution           = isset($post['current_solution']) ? $post['current_solution'] : "";
    $current_solution  = try_encrypt($current_solution);

    $security_requirements      = isset($post['security_requirements']) ? $post['security_requirements'] : "";
    $security_requirements  = try_encrypt($security_requirements);

    $security_recommendations   = isset($post['security_recommendations']) ? $post['security_recommendations'] : "";
    $security_recommendations   = try_encrypt($security_recommendations);

    $planning_date      = isset($post['planning_date']) ? $post['planning_date'] : "";
    $mitigation_percent = (isset($post['mitigation_percent']) && $post['mitigation_percent'] >= 0 && $post['mitigation_percent'] <= 100) ? $post['mitigation_percent'] : 0;
    $mitigation_controls = empty($post['mitigation_controls']) ? [] : $post['mitigation_controls'];
//    $mitigation_controls = is_array($mitigation_controls) ? implode(",", $mitigation_controls) : $mitigation_controls;

    if (!validate_date($planning_date, get_default_date_format()))
    {
        $planning_date = "0000-00-00";
    }
    // Otherwise, set the proper format for submitting to the database
    else
    {
        $planning_date = get_standard_date_from_default_format($planning_date);
    }

    // To be able to import submission date
    $submission_date = isset($post['mitigation_date']) && validate_date($post['mitigation_date'], get_default_datetime_format()) ? get_standard_date_from_default_format($post['mitigation_date'], true) : false;

    // get current mitigation
    $mitigation = get_mitigation_by_id($risk_id);
    $data = array(
        "planning_strategy" => $planning_strategy,
        "mitigation_effort" => $mitigation_effort,
        "mitigation_cost" => $mitigation_cost,
        "mitigation_owner" => $mitigation_owner,
        "current_solution" => $current_solution,
        "security_requirements" => $security_requirements,
        "planning_date" => $planning_date,
        "mitigation_percent" => $mitigation_percent
    );
    $updated_fields = [];
    foreach($data as $key => $value){
        if($key=="current_solution" || $key=="security_requirements" || $key=="security_recommendations") {
            if(try_decrypt($value) != try_decrypt($mitigation[0][$key])) {
                $updated_fields[$key]["original"] = try_decrypt($mitigation[0][$key]);
                $updated_fields[$key]["updated"] = try_decrypt($value);
            }
        } else if($value != $mitigation[0][$key]) {
            switch($key)
            {
                default:
                    $original_value = $mitigation[0][$key];
                    $updated_value = $value;
                break;
                case "planning_strategy":
                    $original_value = get_table_value_by_id("planning_strategy", $mitigation[0][$key]);
                    $updated_value = get_table_value_by_id("planning_strategy", $value);
                break;
                case "mitigation_effort":
                    $original_value = get_table_value_by_id("mitigation_effort", $mitigation[0][$key]);
                    $updated_value = get_table_value_by_id("mitigation_effort", $value);
                break;
                case "mitigation_cost":
                    $original_value = get_asset_value_by_id($mitigation[0][$key]);
                    $updated_value = get_asset_value_by_id($value);
                break;
                case "mitigation_owner":
                    $owner_original = get_user_by_id($mitigation[0][$key]);
                    $owner_updated = get_user_by_id($value);
                    $original_value = $owner_original ? $owner_original["name"] : '';
                    $updated_value = $owner_updated ? $owner_updated["name"] : '';
                break;
            }
            $updated_fields[$key]["original"] = $original_value;
            $updated_fields[$key]["updated"] = $updated_value;
        }
    }

    // Open the database connection
    $db = db_open();

    // Get current datetime for last_update
    $current_datetime = date("Y-m-d H:i:s");

    // Update the risk
    $stmt = $db->prepare("
        UPDATE
            mitigations
        SET " . ($submission_date ? "submission_date=:submission_date," : "") . "
            last_update=:date,
            planning_strategy=:planning_strategy,
            mitigation_effort=:mitigation_effort,
            mitigation_cost=:mitigation_cost,
            mitigation_owner=:mitigation_owner,
            current_solution=:current_solution,
            security_requirements=:security_requirements,
            security_recommendations=:security_recommendations,
            planning_date=:planning_date,
            mitigation_percent=:mitigation_percent
        WHERE
            risk_id=:id;
    ");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);

    if ($submission_date)
        $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR);

    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
    $stmt->bindParam(":planning_strategy", $planning_strategy, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_effort", $mitigation_effort, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_cost", $mitigation_cost, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_owner", $mitigation_owner, PDO::PARAM_INT);
    $stmt->bindParam(":current_solution", $current_solution, PDO::PARAM_STR);
    $stmt->bindParam(":security_requirements", $security_requirements, PDO::PARAM_STR);
    $stmt->bindParam(":security_recommendations", $security_recommendations, PDO::PARAM_STR);
    $stmt->bindParam(":planning_date", $planning_date, PDO::PARAM_STR, 10);
    $stmt->bindParam(":mitigation_percent", $mitigation_percent, PDO::PARAM_INT);
//    $stmt->bindParam(":mitigation_controls", $mitigation_controls, PDO::PARAM_STR, 500);
    $stmt->execute();
    
    // Save mitigation controls
    $mitigation_id = get_mitigation_id($id);
    save_mitigation_controls($mitigation_id, $mitigation_controls, $post);
        
    // Save mitigation teams
    save_junction_values("mitigation_to_team", "mitigation_id", $mitigation_id, "team_id", $mitigation_team);

        
    // If notification is enabled
    if (notification_extra())
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_mitigation_update($id);
    }

    // Audit log
    if(count($updated_fields)) {
        $detail_updated = [];
        foreach ($updated_fields as $key => $value) {
            $detail_updated[] = "Field name : `".$key. "` (`".$value["original"]."`=>`".$value["updated"]."`)";
        }
        //$updated_string = implode($detail_updated,", ");
        $updated_string = implode(", ",$detail_updated);
    } else $updated_string = "";
    $message = "Risk mitigation details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".\n".$updated_string;
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);


    /***** upload files ******/
    // If the delete value exists
    if (!empty($post['delete']))
    {
        // For each file selected
        foreach ($post['delete'] as $file)
        {
            // Delete the file
            delete_db_file($file);
        }
    }
    // if(!empty($post['unique_names'])){
    //     refresh_files_for_risk($post['unique_names'], $id, 2);
    // }
    $unique_names = empty($post['unique_names']) ? "" : $post['unique_names'];
    refresh_files_for_risk($unique_names, $id, 2);

    $error = 1;
    // If a file was submitted
    if (!empty($_FILES))
    {
        // Upload any file that is submitted
        for($i=0; $i<count($_FILES['file']['name']); $i++){
            if($_FILES['file']['error'][$i] || $i==0){
                continue;
            }
            $file = array(
                'name' => $_FILES['file']['name'][$i],
                'type' => $_FILES['file']['type'][$i],
                'tmp_name' => $_FILES['file']['tmp_name'][$i],
                'size' => $_FILES['file']['size'][$i],
                'error' => $_FILES['file']['error'][$i],
            );
        // Upload any file that is submitted
            $error = upload_file($id, $file, 2);
            if($error != 1){
                /**
                * If error, stop uploading files;
                */
                break;
            }
        }

    }
    // Otherwise, success
    else $error = 1;
    /****** end uploading files *******/

    // Add residual risk score
    $residual_risk = get_residual_risk((int)$id + 1000);
    add_residual_risk_scoring_history($id, $residual_risk);

    return $error;
}

/**************************
 * FUNCTION: GET REVIEWS *
 **************************/
function get_reviews($risk_id, $template_group_id="")
{
    global $lang;
    global $escaper;

    // Subtract 1000 from id
    $id = (int)$risk_id - 1000;

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

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        if(!$template_group_id) {
            $group = get_default_template_group("risk");
            $template_group_id = $group["id"];
        }
        $active_fields = get_active_fields("risk", $template_group_id);
        foreach($active_fields as $key => $field){
            if($field['name'] == 'NextReviewDate'){
                unset($active_fields[$key]);
            }
        }
    }

    foreach ($reviews as $review)
    {
        $review_date = date(get_default_datetime_format("g:i A T"), strtotime($review['submission_date']));
        $comment = try_decrypt($review['comments']);

        // If customization extra is enabled
        if(customization_extra())
        {
            echo "<div class=\"row-fluid\">\n";
                // Left Panel
                echo "<div class=\"span5 left-panel\">\n";
                    display_main_review_fields_by_panel_view('left', $active_fields, $risk_id, $review['id'], $review_date, $review['reviewer'], $review['review'], $review['next_step'], "", $comment);
                    echo "&nbsp;";
                echo "</div>";

                // Right Panel
                echo "<div class=\"span5 right-panel\">\n";
                    display_main_review_fields_by_panel_view('right', $active_fields, $risk_id, $review['id'], $review_date, $review['reviewer'], $review['review'], $review['next_step'], "", $comment);
                    echo "&nbsp;";
                echo "</div>";
            echo "</div>";

            // Bottom panel
            echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 bottom-panel\">";
                    display_main_review_fields_by_panel_view('bottom', $active_fields, $risk_id, $review['id'], $review_date, $review['reviewer'], $review['review'], $review['next_step'], "", $comment);
                    echo "&nbsp;";
                echo "</div>";
            echo "</div>";
        }
        else
        {
            echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span5 left-panel\">\n";
                    display_review_date_view($review_date);

                    display_reviewer_view($review['reviewer']);

                    display_review_view($review['review']);

                    display_next_step_view($review['next_step'], $risk_id);

                    display_comments_view($comment);
                echo "</div>";
            echo "</div>";
        }

    }

    return true;
}

/****************************
 * FUNCTION: LATEST VERSION *
 ****************************/
function latest_version($param) {
    $latest_versions = latest_versions();
    
    if (isset($latest_versions[$param]))
        return $latest_versions[$param];
    else
        return "";
}

/****************************************************************************
 * FUNCTION: LATEST VERSIONS                                                *
 * Gets the list of the latest versions and caches it, so if it's needed    *
 * multiple times in the same request it will still only be loaded once     * 
 ****************************************************************************/
function latest_versions() {

    if(isset($GLOBALS['latest_versions_cached'])){
        return $GLOBALS['latest_versions_cached'];
    }

    // Url for SimpleRisk current versions
    if (defined('UPDATES_URL'))
    {   
        $url = UPDATES_URL . '/Current_Version.xml';
    }
    else $url = 'https://updates.simplerisk.com/Current_Version.xml';
    write_debug_log("Checking latest versions at " . $url);

    // Configure the proxy server if one exists
    $method = "GET";
    $header = "content-type: Content-Type: application/x-www-form-urlencoded";
    $context = set_proxy_stream_context($method, $header);

    // Set the default socket timeout to 5 seconds
    ini_set('default_socket_timeout', 5);

    // Get the file headers for the URL
    $file_headers = @get_headers($url, 1);

    // If we were unable to connect to the URL
    if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found')
    {           
        write_debug_log("SimpleRisk was unable to connect to " . $url);

        // Return 0 for the latest versions
        $GLOBALS['latest_versions_cached'] = 0;
        return $GLOBALS['latest_versions_cached'];
    }
    // We were able to connect to the URL
    else
    {
        write_debug_log("SimpleRisk connected to " . $url);

        // Load the versions file
        if (defined('UPDATES_URL'))
        {   
            $version_page = file_get_contents(UPDATES_URL . '/Current_Version.xml', null, $context);
        }
        else $version_page = file_get_contents('https://updates.simplerisk.com/Current_Version.xml', null, $context);

        // Convert it to be an array
        $latest_versions = json_decode(json_encode(new SimpleXMLElement($version_page)), true);

        // Adding aliases, as the values not always requested with the same name the XML serves it
        $latest_versions['import-export'] = $latest_versions['importexport'];
        $latest_versions['app'] = $latest_versions['appversion'];
        $latest_versions['db'] = $latest_versions['dbversion'];

        // Return the latest versions
        $GLOBALS['latest_versions_cached'] = $latest_versions;
        return $GLOBALS['latest_versions_cached'];
    }
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
function write_log($risk_id, $user_id, $message, $log_type="risk")
{
    // Subtract 1000 from id
    $risk_id = (int)$risk_id - 1000;

    // If the user_id value is not set
    if (!isset($user_id))
    {
        $user_id = 0;
    }
    
    $user_id = (int)$user_id;

    // Open the database connection
    $db = db_open();

    $current_time = date("Y-m-d H:i:s");

    // Get the comments
    $stmt = $db->prepare("INSERT INTO audit_log (timestamp, risk_id, user_id, message, log_type) VALUES (:timestamp, :risk_id, :user_id, :message, :log_type)");

    $stmt->bindParam(":timestamp", $current_time, PDO::PARAM_STR, 20);
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":log_type", $log_type, PDO::PARAM_STR, 100);

    $encrypted_message = try_encrypt($message);
    $stmt->bindParam(":message", $encrypted_message, PDO::PARAM_STR);

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
    global $escaper;

    // Configure the proxy server if one exists
    set_proxy_stream_context();

    $announcements = "<ul>\n";

    if (defined('UPDATES_URL'))
    {   
        $announcement_file = file(UPDATES_URL . '/announcements.xml');
    }
    else $announcement_file = file('https://updates.simplerisk.com/announcements.xml');

    $regex_pattern = "/<announcement>(.*)<\/announcement>/";

    foreach ($announcement_file as $line)
    {
        if (preg_match($regex_pattern, $line, $matches))
        {
            $announcements .= "<li>" . $escaper->escapeHtml($matches[1]) . "</li>\n";
        }
    }

    $announcements .= "</ul>";

    // Return the announcement
    return $announcements;
}

/***************************
 * FUNCTION: LANGUAGE FILE *
 ***************************/
function language_file($force_default=false)
{
    // If the session hasn't been defined yet
    // Making it fall through if called from the command line to load the default
    if (!isset($_SESSION) && PHP_SAPI !== 'cli' && !$force_default)
    {
        // Return an empty language file
        return realpath(__DIR__ . '/../languages/empty.php');
    }
    // If the language is set for the user
    elseif (isset($_SESSION['lang']) && $_SESSION['lang'] != "")
    {
        // Use the users language
        return realpath(__DIR__ . '/../languages/' . $_SESSION['lang'] . '/lang.' . $_SESSION['lang'] . '.php');
    }
    else
    {
        // Set the default language to null
        $default_language = null;

        // Try connecting to the database
        try
        {
            $db = new PDO("mysql:charset=UTF8;dbname=".DB_DATABASE.";host=".DB_HOSTNAME.";port=".DB_PORT,DB_USERNAME,DB_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        }
        catch (PDOException $e)
        {
            $default_language = "en";
        }

        // If we can connect to the database
        if (is_null($default_language))
        {
            // Get the default language
            $default_language = get_setting("default_language");
            if (!$default_language) $default_language = "en";
        }

        // If the default language is set
        if ($default_language != false)
        {
            // Use the default language
            return realpath(__DIR__ . '/../languages/' . $default_language . '/lang.' . $default_language . '.php');
        }
        // Otherwise, use english
        else return realpath(__DIR__ . '/../languages/en/lang.en.php');
    }
}

/*****************************************
 * FUNCTION: CUSTOM AUTHENTICATION EXTRA *
 *****************************************/
function custom_authentication_extra()
{
    if(isset($GLOBALS['authentication_extra'])){
        return $GLOBALS['authentication_extra'];
    }

    $setting = get_setting('custom_auth');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['authentication_extra'] = true;
        }
        else $GLOBALS['authentication_extra'] = false;
    }
    else $GLOBALS['authentication_extra'] = false;

    return $GLOBALS['authentication_extra'];
}

/*********************************
 * FUNCTION: CUSTOMIZATION EXTRA *
 *********************************/
function customization_extra()
{
    if(isset($GLOBALS['customization_extra'])){
        return $GLOBALS['customization_extra'];
    }

    $setting = get_setting('customization');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['customization_extra'] = true;
        }
        else $GLOBALS['customization_extra'] = false;
    }
    else $GLOBALS['customization_extra'] = false;

    return $GLOBALS['customization_extra'];
}

/***********************************
 * FUNCTION: TEAM SEPARATION EXTRA *
 ***********************************/
function team_separation_extra()
{
    if(isset($GLOBALS['separation_extra'])){
        return $GLOBALS['separation_extra'];
    }

    $setting = get_setting('team_separation');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['separation_extra'] = true;
        }
        else $GLOBALS['separation_extra'] = false;
    }
    else $GLOBALS['separation_extra'] = false;

    return $GLOBALS['separation_extra'];
}

/********************************
 * FUNCTION: NOTIFICATION EXTRA *
 ********************************/
function notification_extra()
{
    if(isset($GLOBALS['notification_extra'])){
        return $GLOBALS['notification_extra'];
    }

    $setting = get_setting('notifications');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['notification_extra'] = true;
        }
        else $GLOBALS['notification_extra'] = false;
    }
    else $GLOBALS['notification_extra'] = false;

    return $GLOBALS['notification_extra'];
}

/*********************************
 * FUNCTION: IMPORT EXPORT EXTRA *
 *********************************/
function import_export_extra()
{
    if(isset($GLOBALS['importexport_extra'])){
        return $GLOBALS['importexport_extra'];
    }

    $setting = get_setting('import_export');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['importexport_extra'] = true;
        }
        else $GLOBALS['importexport_extra'] = false;
    }
    else $GLOBALS['importexport_extra'] = false;

    return $GLOBALS['importexport_extra'];
}

/***************************************
 * FUNCTION: INCIDENT MANAGEMENT EXTRA *
 ***************************************/
function incident_management_extra()
{
    if(isset($GLOBALS['incident_management_extra'])){
        return $GLOBALS['incident_management_extra'];
    }

    $setting = get_setting('incident_management');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['incident_management_extra'] = true;
        }
        else $GLOBALS['incident_management_extra'] = false;
    }
    else $GLOBALS['incident_management_extra'] = false;

    return $GLOBALS['incident_management_extra'];
}

/***********************
 * FUNCTION: API EXTRA *
 ***********************/
function api_extra()
{
    if(isset($GLOBALS['api_extra'])){
        return $GLOBALS['api_extra'];
    }
    
    $setting = get_setting('api');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['api_extra'] = true;
        }
        else $GLOBALS['api_extra'] = false;
    }
    else $GLOBALS['api_extra'] = false;

    return $GLOBALS['api_extra'];
}

/*******************************
 * FUNCTION: ASSESSMENTS EXTRA *
 *******************************/
function assessments_extra()
{
    if(isset($GLOBALS['assessments_extra'])){
        return $GLOBALS['assessments_extra'];
    }   

    $setting = get_setting('assessments');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['assessments_extra'] = true;
        }
        else $GLOBALS['assessments_extra'] = false;
    }   
    else $GLOBALS['assessments_extra'] = false;

    return $GLOBALS['assessments_extra'];
}

/***********************************
 * FUNCTION: COMPLIANCEFORGE EXTRA *
 ***********************************/
function complianceforge_extra()
{
    if(isset($GLOBALS['complianceforge_extra'])){
        return $GLOBALS['complianceforge_extra'];
    }

    $setting = get_setting('complianceforge');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['complianceforge_extra'] = true;
        }
        else $GLOBALS['complianceforge_extra'] = false;
    }
    else $GLOBALS['complianceforge_extra'] = false;

    return $GLOBALS['complianceforge_extra'];
}

/***************************************
 * FUNCTION: COMPLIANCEFORGE SCF EXTRA *
 ***************************************/
function complianceforge_scf_extra()
{
    if(isset($GLOBALS['complianceforge_scf_extra'])){
        return $GLOBALS['complianceforge_scf_extra'];
    }

    $setting = get_setting('complianceforge_scf');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['complianceforge_scf_extra'] = true;
        }
        else $GLOBALS['complianceforge_scf_extra'] = false;
    }
    else $GLOBALS['complianceforge_scf_extra'] = false;

    return $GLOBALS['complianceforge_scf_extra'];
}

/******************************
 * FUNCTION: GOVERNANCE EXTRA *
 ******************************/
function governance_extra()
{
    if(isset($GLOBALS['governance_extra'])){
        return $GLOBALS['governance_extra'];
    }   
    
    $setting = get_setting('governance');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['governance_extra'] = true;
        }
        else $GLOBALS['governance_extra'] = false;
    }
    else $GLOBALS['governance_extra'] = false;

    return $GLOBALS['governance_extra'];
}


/***********************************
 * FUNCTION: ADVANCED SEARCH EXTRA *
 ***********************************/
function advanced_search_extra() {
    if(isset($GLOBALS['advanced_search_extra'])){
        return $GLOBALS['advanced_search_extra'];
    }   

    $setting = get_setting('advanced_search');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['advanced_search_extra'] = true;
        }
        else $GLOBALS['advanced_search_extra'] = false;
    }
    else $GLOBALS['advanced_search_extra'] = false;

    return $GLOBALS['advanced_search_extra'];
}

/************************
 * FUNCTION: JIRA EXTRA *
 ************************/
function jira_extra() {
    if(isset($GLOBALS['jira_extra'])){
        return $GLOBALS['jira_extra'];
    }

    $setting = get_setting('jira');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['jira_extra'] = true;
        }
        else $GLOBALS['jira_extra'] = false;
    }
    else $GLOBALS['jira_extra'] = false;

    return $GLOBALS['jira_extra'];
}

/***********************
 * FUNCTION: UCF EXTRA *
 ***********************/
function ucf_extra() {
    if(isset($GLOBALS['ucf_extra'])){
        return $GLOBALS['ucf_extra'];
    }

    $setting = get_setting('ucf');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['ucf_extra'] = true;
        }
        else $GLOBALS['ucf_extra'] = false;
    }
    else $GLOBALS['ucf_extra'] = false;

    return $GLOBALS['ucf_extra'];
}

/********************************************
 * FUNCTION: ORGANIZATIONAL HIERARCHY EXTRA *
 ********************************************/
function organizational_hierarchy_extra() {
    if(isset($GLOBALS['organizational_hierarchy_extra'])){
        return $GLOBALS['organizational_hierarchy_extra'];
    }

    $setting = get_setting('organizational_hierarchy');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['organizational_hierarchy_extra'] = true;
        }
        else $GLOBALS['organizational_hierarchy_extra'] = false;
    }
    else $GLOBALS['organizational_hierarchy_extra'] = false;

    return $GLOBALS['organizational_hierarchy_extra'];
}

/****************************
 * FUNCTION: VULNMGMT EXTRA *
 ****************************/
function vulnmgmt_extra() {
    if(isset($GLOBALS['extra_vulnmgmt'])){
        return $GLOBALS['extra_vulnmgmt'];
    }

    $setting = get_setting('extra_vulnmgmt');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['extra_vulnmgmt'] = true;
        }
	else $GLOBALS['extra_vulnmgmt'] = false;
    }
    else $GLOBALS['extra_vulnmgmt'] = false;

    return $GLOBALS['extra_vulnmgmt'];
}

/****************************************
 * FUNCTION: CHECK INSTALLED PHP-MCRYPT *
 ****************************************/
function installed_mcrypt(){
    return extension_loaded("mcrypt");
}

/*****************************************
 * FUNCTION: CHECK INSTALLED PHP-OPENSSL *
 *****************************************/
function installed_openssl(){
    return extension_loaded("openssl");
}

/******************************
 * FUNCTION: ENCRYPTION EXTRA *
 ******************************/
function encryption_extra()
{
    if(isset($GLOBALS['encryption_extra'])){
        return $GLOBALS['encryption_extra'];
    }
    
    $setting = get_setting('encryption');

    // If the setting is not empty
    if (!empty($setting))
    {
        // If the setting is true or "true" or 1
        if ($setting === true || $setting === "true" || $setting === 1 || $setting === "1")
        {
            // The extra is enabled
            $GLOBALS['encryption_extra'] = true;
        }
        else $GLOBALS['encryption_extra'] = false;
    }
    else $GLOBALS['encryption_extra'] = false;

    return $GLOBALS['encryption_extra'];
}

/*************************
 * FUNCTION: UPLOAD FILE *
 *************************/
function upload_file($risk_id, $file, $view_type = 1)
{
    // Open the database connection
    $db = db_open();

    // Get the list of allowed file types
    $stmt = $db->prepare("SELECT `name` FROM `file_types`");
    $stmt->execute();
    $file_types = $stmt->fetchAll();

    // Get the list of allowed file extensions
    $stmt = $db->prepare("SELECT `name` FROM `file_type_extensions`");
    $stmt->execute();
    $file_type_extensions = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Create an array of allowed types
    foreach ($file_types as $key => $row)
    {
        $allowed_types[] = $row['name'];
    }

    // Create an array of allowed extensions
    foreach ($file_type_extensions as $key => $row)
    {
        $allowed_extensions[] = $row['name'];
    }

    // If a file was submitted and the name isn't blank
    if (isset($file) && $file['name'] != "")
    {
        // If the file type is appropriate
        if (in_array($file['type'], $allowed_types))
        {
            // If the file extension is appropriate
            if (in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), array_map('strtolower', $allowed_extensions)))
            {
            // Get the maximum upload file size
            $max_upload_size = get_setting("max_upload_size");

            // If the file size is less than 5MB
            if ($file['size'] < $max_upload_size)
            {
                // If there was no error with the upload
                if ($file['error'] == 0)
                {
                    // Read the file
                    $content = fopen($file['tmp_name'], 'rb');

                    // Create a unique file name
                    $unique_name = generate_token(30);

                    // Open the database connection
                    $db = db_open();

                    // Store the file in the database
                    $stmt = $db->prepare("INSERT INTO files (risk_id, view_type, name, unique_name, type, size, user, content) VALUES (:risk_id, :view_type, :name, :unique_name, :type, :size, :user, :content)");
                    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                    $stmt->bindParam(":view_type", $view_type, PDO::PARAM_INT);
                    $stmt->bindParam(":name", $file['name'], PDO::PARAM_STR, 30);
                    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                    $stmt->bindParam(":type", $file['type'], PDO::PARAM_STR, 30);
                    $stmt->bindParam(":size", $file['size'], PDO::PARAM_INT);
                    $stmt->bindParam(":user", $_SESSION['uid'], PDO::PARAM_INT);
                    $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
                    $stmt->execute();

                    // Close the database connection
                    db_close($db);

                    // Return a success
                    return 1;

                }
                // Otherwise
                else
                {
                    switch ($file['error'])
                    {
                        case 1:
                            return "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                            break;
                        case 2:
                            return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                            break;
                        case 3:
                            return "The uploaded file was only partially uploaded.";
                            break;
                        case 4:
                            return "No file was uploaded.";
                            break;
                        case 6:
                            return "Missing a temporary folder.";
                            break;
                        case 7:
                            return "Failed to write file to disk.";
                            break;
                        case 8:
                            return "A PHP extension stopped the file upload.";
                            break;
                        default:
                            return "There was an error with the file upload.";
                    }
                }
            }
            else return "The uploaded file was too big to store in the database.  A SimpleRisk administrator can modify the maximum file upload size under \"File Upload Settings\" under the \"Configure\" menu.  You may also need to modify the 'upload_max_filesize' and 'post_max_size' values in your php.ini file.";
            }
            else return "The file extension of the uploaded file (" . pathinfo($file['name'], PATHINFO_EXTENSION) . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
        }
        else return "The file type of the uploaded file (" . $file['type'] . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
    }
    else return 1;
}

/*************************
 * FUNCTION: DELETE FILE *
 *************************/
function delete_db_file($unique_name)
{
    // Open the database connection
    $db = db_open();

    // Delete the file from the database
    $stmt = $db->prepare("DELETE FROM files WHERE unique_name=:unique_name");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    return 1;
}

/*************************
 * FUNCTION: Delete some files except current unique names *
 *************************/
function refresh_files_for_risk($unique_names, $risk_id, $view_type = 1)
{
    if(!$unique_names){
        $unique_names = array();
    }
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM files WHERE risk_id=:risk_id and view_type=:view_type");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":view_type", $view_type, PDO::PARAM_INT);
    $stmt->execute();
    $array = $stmt->fetchAll();
    $deleteIds = array();
    foreach($array as $row){
        if(!in_array($row['unique_name'], $unique_names)){
            $deleteIds[] = $row['id'];
        }
    }
    foreach($deleteIds as $deleteId){
        // Delete the file from the database
        $stmt = $db->prepare("DELETE FROM files WHERE id=:id");
        $stmt->bindParam(":id", $deleteId, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    return 1;
}

/***************************
 * FUNCTION: DOWNLOAD FILE *
 ***************************/
function download_file($unique_name, $file_type = "file")
{
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the file from the database
    if($file_type == "file") {
        $stmt = $db->prepare("SELECT * FROM files WHERE BINARY unique_name=:unique_name");
        $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    } else if ($file_type == "validation_file") {
        $stmt = $db->prepare("SELECT * FROM validation_files WHERE id=:unique_name");
        $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_INT);
    }

    $stmt->execute();
    // Store the results in an array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array))
    {
        // Do nothing
        exit;
    }
    else
    {
        // If team separation is enabled
        if (team_separation_extra())
        {
            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // If the user has access to view the risk
            if (($file_type == "file" && extra_grant_access($_SESSION['uid'], (int)$array['risk_id'] + 1000)) || ($file_type == "validation_file"))
            {
                // Display the file
                header("Content-length: " . $array['size']);
                header("Content-type: " . $array['type']);
                header("Content-Disposition: attachment; filename=" . $escaper->escapeUrl($array['name']));
                echo $array['content'];
                exit;
            }
        }
        // Otherwise display the file
        else
        {
            header("Content-length: " . $array['size']);
            header("Content-type: " . $array['type']);
            header("Content-Disposition: attachment; filename=" . $escaper->escapeUrl($array['name']));
            echo $array['content'];
            exit;
        }
    }
}

/**************************************
 * FUNCTION: SUPPORTING DOCUMENTATION *
 * TYPE 1 = Risk File                 *
 * TYPE 2 = Mitigation File           *
 **************************************/
function supporting_documentation($id, $mode = "view", $view_type = 1)
{
    global $lang;
        global $escaper;

    // Convert the ID to a database risk id
    $id = $id-1000;

    // Open the database connection
    $db = db_open();

    // Get the file from the database
    $stmt = $db->prepare("SELECT name, unique_name FROM files WHERE risk_id=:id AND view_type=:view_type");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":view_type", $view_type, PDO::PARAM_INT);
    $stmt->execute();

    // Store the results in an array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the mode is view
    if ($mode == "view")
    {


        // If the array is empty
        if (empty($array))
        {
            echo "<input style=\"cursor: default;\" type=\"text\" value=\"". $escaper->escapeHtml($lang['None']) ."\" disabled=\"disabled\">";
        }
        else
        {
            // For each entry in the array
            foreach ($array as $file)
            {
                echo "<div class =\"doc-link edit-mode\"><a href=\"download.php?id=" . $escaper->escapeHtml($file['unique_name']) . "\" >" . $escaper->escapeHtml($file['name']) . "</a></div>\n";
            }
        }


    }
    // If the mode is edit
    else if ($mode == "edit")
    {
        // If the array is empty
        if (empty($array))
        {
            // echo "<input type=\"file\" name=\"file\" />\n";
            echo '<div class="file-uploader">';
            echo "
                  <script>
                      var max_upload_size = " . $escaper->escapeJs(get_setting('max_upload_size', 0)) . ";
                      var fileTooBigMessage = '" . $escaper->escapeJs($lang['FileIsTooBigToUpload']) . "';
                  </script>";
            echo '<label for="file-upload" class="btn active-textfield">'.$escaper->escapeHtml($lang['ChooseFile']).'</label> <span class="file-count-html"><span class="file-count">0</span> '.$escaper->escapeHtml($lang['FileAdded']).'</span>';
            echo "<p><font size=\"2\"><strong>Max ". $escaper->escapeHtml(round(get_setting('max_upload_size')/1024/1024)) ." Mb</strong></font></p>";
            echo '<ul class="file-list">';

            echo '</ul>';
            echo '<input type="file" name="file[]" id="file-upload" class="hidden-file-upload active" />';
            echo '</div>';

        }
        else
        {
            $documentHtml = "";
            // For each entry in the array
            foreach ($array as $file)
            {
//                $documentHtml .= "<div class =\"doc-link\">
//                    <a href=\"download.php?id=" . $escaper->escapeHtml($file['unique_name']) . "\" target=\"_blank\" />" . $escaper->escapeHtml($file['name']) . "</a>&nbsp;&nbsp;--&nbsp;" . $escaper->escapeHtml($lang['Delete']) . "?<input class=\"delete-link-check active-textfield\" type=\"checkbox\" name=\"delete[]\" value=\"" . $escaper->escapeHtml($file['unique_name']) . "\" /></div>\n";
                $documentHtml .= "<li>
                    <div class='file-name'><a href=\"download.php?id=" . $escaper->escapeHtml($file['unique_name']) . "\" target=\"_blank\" />" . $escaper->escapeHtml($file['name']) . "</a></div>
                    <a href='#' class='remove-file' ><i class='fa fa-times'></i></a>
                    <input type='hidden' name='unique_names[]' value='".$escaper->escapeHtml($file['unique_name'])."'>
                </li>";
            }


            // echo "<input type=\"file\" name=\"file\" />\n";
            if(count($array)>1){
                $count = '<span class="file-count">'. count($array)."</span> Files";
            }else{
                $count = '<span class="file-count">'. count($array)."</span> File";
            }
            echo '
                <div class="file-uploader">
                <script>
                    var max_upload_size = ' . $escaper->escapeJs(get_setting('max_upload_size', 0)) . ';
                    var fileTooBigMessage = "' . $escaper->escapeJs($lang['FileIsTooBigToUpload']) . '"; 
                </script>
                <label for="file-upload" class="btn active-textfield">Choose File</label> <span class="file-count-html">'.$count.' Added</span>
                    <ul class="exist-files">
                        '.$documentHtml.'
                    </ul>
                    <ul class="file-list">
                    </ul>
                    <input type="file" name="file[]" id="file-upload" class="hidden-file-upload active" />
                </div>
            ';
        }
    }
}

/*************************************
 * FUNCTION: GET SCORING METHOD NAME *
 *************************************/
function get_scoring_method_name($scoring_method)
{
    switch ($scoring_method)
    {
        case 1:
            return "Classic";
        case 2:
            return "CVSS";
        case 3:
            return "DREAD";
        case 4:
            return "OWASP";
        case 5:
            return "Custom";
        case 6:
            return "Contributing Risk";
    }
}

/***************************
 * FUNCTION: VALIDATE DATE *
 ***************************/
function validate_date($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

/**************************
 * FUNCTION: DELETE RISKS *
 **************************/
function delete_risks($risks)
{
        // Return true by default
        $return = true;

        // For each risk
        foreach ($risks as $risk)
        {
            $risk_id = (int) $risk;

            // Delete the asset
            $success = delete_risk($risk_id);

            // If it was not a success return false
            if (!$success) $return = false;
        }

        // Return success or failure
        return $return;
}

/*************************
 * FUNCTION: DELETE RISK *
 *************************/
function delete_risk($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Remove closures for the risk
    $stmt = $db->prepare("DELETE FROM `closures` WHERE `risk_id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
        $return = $stmt->execute();

    // Remove comments for the risk
    $stmt = $db->prepare("DELETE FROM `comments` WHERE `risk_id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Remove files for the risk
    $stmt = $db->prepare("DELETE FROM `files` WHERE `risk_id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Remove management reviews for the risk
    $stmt = $db->prepare("DELETE FROM `mgmt_reviews` WHERE `risk_id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Remove mitigations for the risk
    $stmt = $db->prepare("DELETE FROM `mitigations` WHERE `risk_id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Remove the risk scoring for the risk
    $stmt = $db->prepare("DELETE FROM `risk_scoring` WHERE `id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Remove the risk
    $stmt = $db->prepare("DELETE FROM `risks` WHERE `id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();
        
    // Remove the risk scoring history
    $stmt = $db->prepare("DELETE FROM `risk_scoring_history` WHERE `risk_id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Remove the residual risk scoring history
    $stmt = $db->prepare("DELETE FROM `residual_risk_scoring_history` WHERE `risk_id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    cleanup_after_delete("risks");
    cleanup_after_delete("mitigations");

    // If customization extra is enabled, delete custom_risk_data related with risk ID
    if(customization_extra())
    {
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        delete_custom_data_by_row_id($risk_id, "risk");
    }

    // Close the database connection
    db_close($db);

    // Audit log
    $risk_id = (int)$risk_id + 1000;
    $message = "Risk ID \"" . $risk_id . "\" was DELETED by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Return success or failure
    return $return;
}

/*******************************
 * FUNCTION: GET RISKS BY TEAM *
 *******************************/
function get_risks_by_team($team)
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT a.id FROM `risks` a INNER JOIN `risk_to_team` rtt ON a.id=rtt.risk_id WHERE rtt.team_id = :team; ");
    $stmt->bindParam(":team", $team, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}

/*******************************
 * FUNCTION: COMPLETED PROJECT *
 *******************************/
function completed_project($project_id)
{
    // Check if the user has access to close risks
    if (isset($_SESSION["close_risks"]) && $_SESSION["close_risks"] == 1)
    {
        // Get the risks for the project
        $risks = get_project_risks($project_id);

        // For each risk in the project
        foreach ($risks as $risk)
        {
            // If the risks status is not Closed
            if ($risk['status'] != "Closed")
            {
                $id = (int)$risk['id'] + 1000;
                $status = "Closed";
                $close_reason = 1;
                $project = get_name_by_value("projects", $project_id);
                $note = "Risk was closed when the \"" . $project_id . "\" project was marked as Completed.";

                // Close the risk
                close_risk($id, $_SESSION['uid'], $status, $close_reason, $note);
            }
                }

        return 1;
        }
    else return 0;
}

/********************************
 * FUNCTION: INCOMPLETE PROJECT *
 ********************************/
function incomplete_project($project_id)
{
    // Get the risks for the project
    $risks = get_project_risks($project_id);

    // For each risk in the project
    foreach ($risks as $risk)
    {
        // If the risk status is Closed
        if ($risk['status'] == "Closed")
        {
            $id = (int)$risk['id'] + 1000;

            // Reopen the risk
            reopen_risk($id);
        }
    }
}

/*****************************
 * FUNCTION: WRITE DEBUG LOG *
 *****************************/
function write_debug_log($value)
{
    // If a global variable for debug logging is not already set
    if (!isset($GLOBALS['debug_logging']))
    {
        // Get the current debug setting from the database
        $GLOBALS['debug_logging'] = get_setting("debug_logging");
    }

    // If a global variable for the debug log file is not already set
    if (!isset($GLOBALS['debug_log_file']))
    {
        // Get the current debug setting from the database
        $GLOBALS['debug_log_file']  = get_setting("debug_log_file");
    }

    // Set debug logging to the global variable
    $debug_logging = $GLOBALS['debug_logging'];
    $log_file = $GLOBALS['debug_log_file'];

    // If DEBUG is enabled
    if ($debug_logging == 1)
    {
        // If the value is not an array
        if (!is_array($value))
        {
	        $root_path = str_replace('/', '\\', realpath(__DIR__ . '/../'));
            $log_path = str_replace('/', '\\', realpath(dirname($log_file)));
            if(strpos($log_path, $root_path) === false && $log_path != ""){
                // Write to the error log
                $return = error_log(date('c')." ".$value."\n", 3, $log_file);
            }
        }
        // If the value is an array
        else
        {
            // For each key value pair in the array
            foreach ($value as $key => $newvalue)
            {
                // If the newvalue is an array
                if (is_array($newvalue))
                {
                    write_debug_log("Array key: " . $key);

                    // Recursively call the write_debug_log function on it
                    write_debug_log($newvalue);
                }
                // If the newvalue is not an array
                else
                {
                    // Call the write_debug_log function on the key value pair
                    write_debug_log($key . " => " . $newvalue);
                }
            }
        }
    }
    // DEBUG is disabled
    else
    {
        // Set a global variable for debug_logging
        $GLOBALS['debug_logging'] = false;
    }
}

/******************************
 * FUNCTION: ADD REGISTRATION *
 ******************************/
function add_registration($name="", $company="", $title="", $phone="", $email="", $fname="", $lname="")
{
    global $lang;

    // Create the SimpleRisk instance ID if it doesn't already exist
    $instance_id = create_simplerisk_instance_id();

    // Create the data to send
    $data = array(
        'action' => 'register_instance',
        'instance_id' => $instance_id,
        'name' => $name,
        'company' => $company,
        'title' => $title,
        'phone' => $phone,
        'email' => $email,
        'fname' => $fname,
        'lname' => $lname,
    );

    // Register instance with the web service
    $results = simplerisk_service_call($data);

    // If the result is false or an empty results array was returned
    if (!$results || !is_array($results)) {
        write_debug_log("The result of the SimpleRisk services call was false or an empty array was returned");

        set_alert(true, "bad", $lang['FailedToRegisterInstance']);

        // Return a failure
        return 0;
    }
    else
    {
        write_debug_log("Successfully made the SimpleRisk service call");
        set_alert(true, "good", "Successfully made the SimpleRisk service call");
    }

    // For each line in the results returned from the SimpleRisk service call
    foreach ($results as $line)
    {
        if (preg_match("/<api_key>(.*)<\/api_key>/", $line, $matches))
        {
            write_debug_log("An API key was returned from the SimpleRisk services tier");
            set_alert(true, "good", "An API key was returned from the SimpleRisk services tier");

            $services_api_key = $matches[1];

            // Open the database connection
            $db = db_open();

            // Add the registration
            add_setting("registration_name", $name);
            add_setting("registration_company", $company);
            add_setting("registration_title", $title);
            add_setting("registration_phone", $phone);
            add_setting("registration_email", $email);
            add_setting("registration_fname", $fname);
            add_setting("registration_lname", $lname);
            add_setting("services_api_key", $services_api_key);
            update_or_insert_setting("registration_registered", 1);

            // Download the upgrade extra
            $result = download_extra("upgrade");

            // Close the database connection
            db_close($db);

            // Return the result
            return $result;
        } elseif (preg_match("/<result>(.*)<\/result>/", $line, $matches)) {
            switch($matches[1]) {
                case "Not Purchased":
                    // Display an alert
                    set_alert(true, "bad", $lang['RequestedExtraIsNotPurchased']);

                    // Return a failure
                    return 0;

                case "Invalid Extra Name":
                    // Display an alert
                    set_alert(true, "bad", $lang['RequestedExtraDoesNotExist']);

                    // Return a failure
                    return 0;

                case "Unmatched IP Address":
                    // Display an alert
                    set_alert(true, "bad", $lang['InstanceWasRegisteredWithDifferentIp']);

                    // Return a failure
                    return 0;

                case "Instance Disabled":
                    // Display an alert
                    set_alert(true, "bad", $lang['InstanceIsDisabled']);

                    // Return a failure
                    return 0;

                case "Invalid Instance or Key":
                case "failure":
                    // Display an alert
                    set_alert(true, "bad", $lang['InvalidInstanceIdOrKey']);

                    // Return a failure
                    return 0;

                default:
                    set_alert(true, "bad", $lang['FailedToRegisterInstance']);

                    // Return a failure
                    return 0;
            }
        }
    }

    // Return a failure
    return 0;
}

/*********************************
 * FUNCTION: UPDATE REGISTRATION *
 *********************************/
function update_registration($name="", $company="", $title="", $phone="", $email="", $fname="", $lname="")
{
    global $lang;

    // Get the instance id
    $instance_id = get_setting("instance_id");

    // Get the services API key
    $services_api_key = get_setting("services_api_key");

    // Create the data to send
    $data = array(
        'action' => 'update_instance',
        'instance_id' => $instance_id,
        'api_key' => $services_api_key,
        'name' => $name,
        'company' => $company,
        'title' => $title,
        'phone' => $phone,
        'email' => $email,
    'fname' => $fname,
    'lname' => $lname,
    );

    // Register instance with the web service
    $result = simplerisk_service_call($data);

    if (!$result || !is_array($result) || !preg_match("/<result>(.*)<\/result>/", $result[0], $matches)) {
        set_alert(true, "bad", $lang['FailedToUpdateInstance']);

        // Return a failure
        return 0;
    }

    switch($matches[1]) {
        case "Not Purchased":
            // Display an alert
            set_alert(true, "bad", $lang['RequestedExtraIsNotPurchased']);

            // Return a failure
            return 0;

        case "Invalid Extra Name":
            // Display an alert
            set_alert(true, "bad", $lang['RequestedExtraDoesNotExist']);

            // Return a failure
            return 0;

        case "Unmatched IP Address":
            // Display an alert
            set_alert(true, "bad", $lang['InstanceWasRegisteredWithDifferentIp']);

            // Return a failure
            return 0;

        case "Instance Disabled":
            // Display an alert
            set_alert(true, "bad", $lang['InstanceIsDisabled']);

            // Return a failure
            return 0;

        case "Invalid Instance or Key":
        case "failure":
            // Display an alert
            set_alert(true, "bad", $lang['InvalidInstanceIdOrKey']);

            // Return a failure
            return 0;

        case "success":
            // Open the database connection
            $db = db_open();

            // Update the registration
            $stmt = $db->prepare("UPDATE `settings` SET value=:name WHERE name='registration_name'");
            $stmt->bindParam(":name", $name, PDO::PARAM_STR, 200);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE `settings` SET value=:company WHERE name='registration_company'");
            $stmt->bindParam(":company", $company, PDO::PARAM_STR, 200);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE `settings` SET value=:title WHERE name='registration_title'");
            $stmt->bindParam(":title", $title, PDO::PARAM_STR, 200);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE `settings` SET value=:phone WHERE name='registration_phone'");
            $stmt->bindParam(":phone", $phone, PDO::PARAM_STR, 200);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE `settings` SET value=:email WHERE name='registration_email'");
            $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE `settings` SET value=:fname WHERE name='registration_fname'");
            $stmt->bindParam(":fname", $fname, PDO::PARAM_STR, 200);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE `settings` SET value=:lname WHERE name='registration_lname'");
            $stmt->bindParam(":lname", $lname, PDO::PARAM_STR, 200);
            $stmt->execute();

            // Download the update extra
            $result = download_extra("upgrade");

            // Close the database connection
            db_close($db);

            // Return the result
            return $result;
        default:
            set_alert(true, "bad", $lang['FailedToUpdateInstance']);

            // Return a failure
            return 0;
    }

    // Return a failure
    return 0;
}

/********************************
 * FUNCTION: UPDATE RISK STATUS *
 ********************************/
function update_risk_status($risk_id, $status)
{
    // Adjust the risk id
    $id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Update the status
    if( $status == "Closed" && check_risk_by_id($risk_id)){

        if (isset($_SESSION["close_risks"]) && $_SESSION["close_risks"] == 1) {
            // Get current datetime for last_update
            $current_datetime = date('Y-m-d H:i:s');
            $reviewer   = $_SESSION['uid'];
            $review     = 0;
            $next_step  = 0;
            $next_review = "0000-00-00";
            $try_encrypt = try_encrypt("--");

            $stmt = $db->prepare("INSERT INTO mgmt_reviews (`risk_id`, `review`, `reviewer`, `next_step`, `comments`, `next_review`, `submission_date`) VALUES (:risk_id, :review, :reviewer, :next_step, :comments, :next_review, :submission_date)");

            $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":review", $review, PDO::PARAM_INT);
            $stmt->bindParam(":reviewer", $reviewer, PDO::PARAM_INT);
            $stmt->bindParam(":next_step", $next_step, PDO::PARAM_INT);
            $stmt->bindParam(":comments", $try_encrypt, PDO::PARAM_STR);
            $stmt->bindParam(":next_review", $next_review, PDO::PARAM_STR, 10);
            $stmt->bindParam(":submission_date", $current_datetime, PDO::PARAM_STR, 20);

            $stmt->execute();

            // Get the new mitigation id
            $review_id = get_review_id($id);

            // If customization extra is enabled
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

                // Save custom fields
                save_risk_custom_field_values($risk_id, $review_id);
            }

            // Update the risk status and last_update
            $stmt = $db->prepare("UPDATE risks SET status=:status, last_update=:last_update, review_date=:review_date, mgmt_review=:mgmt_review WHERE id = :risk_id");
            $stmt->bindParam(":status", $status, PDO::PARAM_STR, 20);
            $stmt->bindParam(":last_update", $current_datetime, PDO::PARAM_STR, 20);
            $stmt->bindParam(":review_date", $current_datetime, PDO::PARAM_STR, 20);
            $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":mgmt_review", $review_id, PDO::PARAM_INT);

            $stmt->execute();
            $close_reason = 2; // default vaule is 2: System Retired.
            $note = "--";
            // Close the risk
            close_risk($risk_id, $reviewer, $status, $close_reason, $note);
        } else {
            global $lang;

            // Close the database connection
            db_close($db);

            set_alert(true, "bad", $lang['NoPermissionForClosingRisks']);
            return;
        }
        
    } else {
        $stmt = $db->prepare("UPDATE risks SET `status`=:status WHERE `id`=:id");
        $stmt->bindParam(":status", $status, PDO::PARAM_STR, 50);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $risk = get_risk_by_id($risk_id);
    // Check if the risk exists
    if(!empty($risk[0])){
        $subject = try_decrypt($risk[0]["subject"]);
        $message = "A risk status for subject \"{$subject}\" was changed by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);
    }

    return true;
}

/*************************
 * FUNCTION: TRY DECRYPT *
 *************************/
function try_decrypt($value)
{
    // If the value is empty string
    if(!$value)
    {
        return $value;
    }
    // If the encryption extra is enabled
    elseif (encryption_extra())
    {
        // Load encryption extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        if(!isset($_SESSION['encrypted_pass']) || !$_SESSION['encrypted_pass']){
            // If there's no session, try to get the password from the init.php
            $password = fetch_key();

            if ($password) {
                // If we could, then use it
                $decrypted_value = decrypt($password, $value);
            } else {
                $decrypted_value = "XXXX";
            }
        }
        else{
            // Decrypt the value
            $decrypted_value = decrypt(base64_decode($_SESSION['encrypted_pass']), $value);
        }
    }
    // Otherwise return the value
    else $decrypted_value=$value;

    // Return the decrypted value
    return $decrypted_value;
}

/*************************
 * FUNCTION: TRY ENCRYPT *
 *************************/
function try_encrypt($value)
{
    // If the encryption extra is enabled
    if (encryption_extra()) {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        if(!isset($_SESSION['encrypted_pass']) || !$_SESSION['encrypted_pass']){
            // If there's no session, try to get the password from the init.php
            $password = fetch_key();

            if ($password) {
                // If we could, then use it
                $encrypted_value = encrypt($password, $value);
            } else {
                $encrypted_value = $value;
            }
        }
        else{
            // Encrypt the value
            $encrypted_value = encrypt(base64_decode($_SESSION['encrypted_pass']), $value);
        }

        return $encrypted_value;
    }
    // Otherwise return the value
    else return $value;
}

/*****************************
 * FUNCTION: GET CURRENT URL *
 *****************************/
function get_current_url()
{
    // Get the base url
    $base_url = get_base_url();

    // Set the current url to the base url plus the request uri
    $url = $base_url . get_encoded_request_uri();

    // Return the URL
    return $url;
}

/**************************
 * FUNCTION: GET BASE URL *
 **************************/
function get_base_url()
{
    // Get the simplerisk_base_url from the settings table
    $simplerisk_base_url = get_setting("simplerisk_base_url");

    // If the simplerisk_base_url is not set
    if (!$simplerisk_base_url)
    {
        // If the base_url is set in the session
        if (isset($_SESSION) && array_key_exists('base_url', $_SESSION))
        {
            // Use the value set in the session
            $base_url = $_SESSION['base_url'];

            // Add the simplerisk_base_url to the settings table
            add_setting("simplerisk_base_url", $base_url);
        }
        // Otherwise try to create a base url based on the current url
        else
        {
            // Check if we are using the HTTPS protocol
            $isHTTPS = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on");

            // Set the port
            $port = (isset($_SERVER['SERVER_PORT']) && ((!$isHTTPS && $_SERVER['SERVER_PORT'] != "80") || ($isHTTPS && $_SERVER['SERVER_PORT'] != "443")));
            $port = ($port) ? ":" . $_SERVER['SERVER_PORT'] : "";

            // Set the current URL
            $base_url = ($isHTTPS ? "https://" : "http://") . $_SERVER['SERVER_NAME'] . $port;

            $dir_path = realpath(dirname(dirname(__FILE__)));
            $document_root = realpath($_SERVER["DOCUMENT_ROOT"]);
            $app_root = str_replace($document_root,"",$dir_path);
            $app_root = str_replace(DIRECTORY_SEPARATOR ,"/",$app_root);
            $base_url .= $app_root;


            // Append the script name
            //$base_url = $base_url . $_SERVER['SCRIPT_NAME'];

            // HTML Encode the base url
            //$base_url = htmlspecialchars( $base_url, ENT_QUOTES, 'UTF-8' );

            // Get the directory name part of the path
            //$base_url = pathinfo($base_url)['dirname'];

            // Filter out authentication extra from the base url
            //$base_url = str_replace("/extras/authentication", "", $base_url);

            // Add the simplerisk_base_url to the settings table
            add_setting("simplerisk_base_url", $base_url);
        }
    }
    // Otherwise
    else
    {
        $base_url = $simplerisk_base_url;
    }

    // Return the base_url value
    return $base_url;
}

/*****************************
 * FUNCTION: SELECT REDIRECT *
 *****************************/
function select_redirect()
{
    // If a maximum age for the password is set
    if(get_setting("pass_policy_max_age") != 0)
    {
        // If the user needs to reset their password
        if(check_password_max_time($_SESSION['uid']) === "CHANGE")
        {
            // Use the password max age redirect
            password_max_age_redirect();
        }
        // Otherwise use the registration redirect
        else registration_redirect();
    }
    // Otherwise use the registration redirect
    else registration_redirect();
}

/***************************************
 * FUNCTION: PASSWORD MAX AGE REDIRECT *
 ***************************************/
function password_max_age_redirect()
{
    // Send an alert
    set_alert(true, "bad", "Your password is too old and needs to be changed.");

    // Redirect to change_password page
    header("Location: account/change_password.php");
}

/***********************************
 * FUNCTION: REGISTRATION REDIRECT *
 ***********************************/
function registration_redirect()
{
    // If the SimpleRisk instance is not registered
    if (get_setting('registration_registered') == 0)
    {
        // If the user is an admin user
        if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
        {
            // If the registration notice has not been disabled
            if (get_setting("disable_registration_notice") == false)
            {
                // Set the alert
                set_alert(true, "good", "You haven't registered SimpleRisk yet.  Register now to be able to back up and upgrade with the click of a button.");

                // Redirect to the register page
                header("Location: admin/register.php");
            }
            // Otherwise
            else
            {
                // If a specific url was requested before authentication
                if (isset($_SESSION['requested_url']))
                {
                    // Set the requested URL
                    $requested_url = $_SESSION['requested_url'];

                    // Clear the session variable
                    unset($_SESSION['requested_url']);

                    // Redirect to the requested location
                    header("Location: " . $requested_url);
                    exit(0);
                }
                // Otherwise
                else
                {
                    // Redirect to the reports index
                    header("Location: reports/index.php");
                }
            }
        }
        // Otherwise
        else
        {
            // If a specific url was requested before authentication
            if (isset($_SESSION['requested_url']))
            {
                // Set the requested URL
                $requested_url = $_SESSION['requested_url'];

                // Clear the session variable
                unset($_SESSION['requested_url']);

                // Redirect to the requested location
                header("Location: " . $requested_url);
                exit(0);
            }
            // Otherwise
            else
            {
                // Redirect to the reports index
                header("Location: reports/index.php");
            }
        }
    }
    // Otherwise
    else
    {
        // If a specific url was requested before authentication
        if (isset($_SESSION['requested_url']))
        {
            // Set the requested URL
            $requested_url = $_SESSION['requested_url'];

            // Clear the session variable
            unset($_SESSION['requested_url']);

            // Redirect to the requested location
            header("Location: " . $requested_url);
            exit(0);
        }
        // Otherwise
        else
        {
            // Redirect to the reports index
            header("Location: reports/index.php");
        }
    }
}

/******************************
 * FUNCTION: JS STRING ESCAPE *
 ******************************/
function js_string_escape($string)
{
    global $escaper;
    $string = $escaper->escapeHtml($string);
    $string = str_replace("&#039;", "'", $string);
    return $string;
}


/******************************
 * FUNCTION: CHECK TEAM ACCESS *
 * $risk_id: Risk ID from front
 ******************************/
function check_access_for_risk($risk_id)
{
    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        if (!extra_grant_access($_SESSION['uid'], $risk_id))
        {
            // Do not allow the user to update the risk
            $access = false;
        }
        // Otherwise, allow the user to update the risk
        else $access = true;
    }
    // Otherwise, allow the user to update the risk
    else $access = true;

    return $access;
}

/********************************
* FUNCTION: CALCULATE DATE DIFF *
* Params: dates that can be in  *
* any format and for            *
* diff_format:                  *
* %y = year                     *
* %m = month                    *
* %d = day                      *
* %h = hours                    *
* %i = minutes                  *
* %s = seconds                  *
* Example of usage:             *
* calculate_date_diff(          *
* "2015-12-23 11:36:49",        *
* "2016-12-06 14:36:49",        *
* "%a days and %h hours");      *
********************************/
function calculate_date_diff($first_date, $second_date, $diff_format = '%a')
{
    $datetime_1 = date_create($first_date);
    $datetime_2 = date_create($second_date);

    $interval = date_diff($datetime_1, $datetime_2);

    return $interval->format($diff_format);
}

/*************************************
 * FUNCTION: CHECK PASSWORD MAX TIME *
 *************************************/
function check_password_max_time($user_id)
{
    $db = db_open();
    $password_max_time = get_setting('pass_policy_max_age');

    // Get last password change date
    $stmt = $db->prepare("SELECT last_password_change_date FROM user WHERE value=:user_id;");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();
    db_close($db);
    $last_password_change_date = $stmt->fetchAll(PDO::FETCH_ASSOC);
    try{
        if(isset($last_password_change_date) && count($last_password_change_date[0]) == 1) {
            if((int)calculate_date_diff(date("Y-m-d h:i:s"), $last_password_change_date[0]['last_password_change_date'], "%d") < (int)$password_max_time){
                return TRUE;
            }else{
                return "CHANGE";
            }
        }else{
            throw new Exception("last_password_change_date is empty or ir returned too much results to fetch them correctly.");
        }
    }catch(Exception $e){
        echo 'Exception thrown: ' . $e->getLine() . " : " . $e->getMessage() . PHP_EOL;
        return FALSE;
    }
}

/**********************************************
 * FUNCTION: GET SALT AND PASSWORD BY USER ID *
 **********************************************/
function get_salt_and_password_by_user_id($user_id){
    $db = db_open();
    $stmt = $db->prepare("SELECT salt, password FROM user WHERE value=:user_id;");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $res = array("salt" => $result[0]["salt"], "password" => $result[0]["password"]);
    db_close($db);
    return $res;
}

/****************************************
 * FUNCTION: CHECK CURRENT PASSWORD AGE *
 ****************************************/
function check_current_password_age($user_id = false)
{
    if($user_id === false){
        return true;
    }
    // Get the minimum password age
    $min_password_age = get_setting("pass_policy_min_age");

    // If the minimum age policy is enabled
    if ($min_password_age != 0)
    {
        // Open the database connection
        $db = db_open();

        // Get the last time the password for this user was updated
        $stmt = $db->prepare("SELECT last_password_change_date FROM user WHERE value=:user_id;");
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $value = $stmt->fetch();
        $last_password_change_date = strtotime($value['last_password_change_date']);

        // Close the database connection
        db_close($db);

        // Get the min password age date by subtracting today from the number of days x 86400
        $min_password_age_date = time() - ($min_password_age * 86400);

        // If the last time the password was changed is older than the min password age
        if ($last_password_change_date < $min_password_age_date)
        {
            return true;
        }
        else
        {
            // Display an alert
            set_alert(true, "bad", "Unabled to update the password because the minimum age of ". $min_password_age . " days has not elapsed.");

            // Return false
            return false;
        }
    }
    // Otherwise, the minimum age policy is disabled so return true
    else return true;
}

/************************************************************************************************
 * FUNCTION: GET LANGUAGES WITH VARIABLES                                                       *
 * $key: Key of the localization                                                                *
 * $params: Parameters that should be replaced into the localization string                     *
 * $escape: Specify whether the params should be escaped.                                       *
 * Should be false if the string will be escaped when displayed, to prevent double-escaping.    *
 ************************************************************************************************/
function _lang($__key, $__params=array(), $__escape=true){
    global $lang;

    if ($__escape) {
        global $escaper;

        foreach($__params as &$__param){
            $__param = $escaper->escapeHtml($__param);
        }
    }

    $__return = $lang[$__key];

    // Have to sort the keys from longest to shortest to make sure not replacing 
    // $user instead of $username when encountering the pattern of {$username}
    uksort($__params, function ($b, $a) {
        return (strlen($a) == strlen($b) ? strcmp($a, $b) : strlen($a) - strlen($b));
    });
    
    foreach($__params as $key => $value) {
        // It has to work for all the variable types found in the language files
        $__return = str_replace('{$' . $key .'}', $value, $__return);
        $__return = str_replace('${' . $key .'}', $value, $__return);
        $__return = str_replace('$' . $key, $value, $__return);
    }

    return $__return;
}

/****************************************
 * FUNCTION: GET PASSWORD REQUEST MESSAGES *
 ****************************************/
function getPasswordReqeustMessages($user_id = false){
    global $lang;

    $messages = array();

    if (get_setting('pass_policy_enabled') == 1){
        // Get condition for min chars
        $min_chars = get_setting('pass_policy_min_chars');
        $params = array(
            'min_chars' => $min_chars
        );
        $messages[] = _lang('ConditionMessageForMinChar', $params);

        // Get condition for alpa string
        if (get_setting('pass_policy_alpha_required') == 1){
            $messages[] = _lang('ConditionMessageForAlpha');
        }

        // Get condition for uppercase
        if (get_setting('pass_policy_upper_required') == 1){
            $messages[] = _lang('ConditionMessageForUppercase');
        }

        // Get condition for lowercase
        if (get_setting('pass_policy_lower_required') == 1){
            $messages[] = _lang('ConditionMessageForLowercase');
        }

        // Get condition for digits
        if (get_setting('pass_policy_digits_required') == 1){
            $messages[] = _lang('ConditionMessageForDigit');
        }

        // Get condition for special chars
        if (get_setting('pass_policy_special_required') == 1){
            $messages[] = _lang('ConditionMessageForSpecialchar');
        }

        // Get condition for password age
        $min_password_age = get_setting("pass_policy_min_age");
        if ($min_password_age != 0){
            $params = array(
                'min_password_age' => $min_password_age
            );
            $messages[] = _lang('ConditionMessageForMinPasswordAge', $params);
        }
    }

    return $messages;

}

/****************************************
 * FUNCTION: GET USER ID BY PARAM *
 * MATCH UID, USERNAME, NAME
 ****************************************/
function get_user_value_from_name_or_id($name_or_id){
    if(empty($GLOBALS['users'])){
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("select * from `user`");
        $stmt->execute();

        // Store the list in the array
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $GLOBALS['users'] = $users;

        // Close the database connection
        db_close($db);
    }else{
        $users = $GLOBALS['users'];
    }

    $value = 0;

    // Check if the param is uid
    foreach($users as $user){
        if($user['value'] == $name_or_id){
            $value = $user['value'];
            return $value;
        }
    }

    // Check if the param is username
    foreach($users as $user){
        if($user['username'] == $name_or_id){
            $value = $user['value'];
            return $value;
        }
    }

    // Check if the param is name
    foreach($users as $user){
        if($user['name'] == $name_or_id){
            $value = $user['value'];
            return $value;
        }
    }

    return $value;

}

/***********************************
 * FUNCTION: GET SCORING HISTORIES *
 ***********************************/
function get_scoring_histories($risk_id = null)
{
    // Open the database connection
    $db = db_open();
    // If the risk id is not null
    if ($risk_id != null)
    {
        // Convert the risk id to the internal format
        $risk_id = (int)$risk_id - 1000;

        // Get risk scoring histories by risk id
        if (!team_separation_extra())
        {
            $sql = "SELECT risk_id+1000 as risk_id,calculated_risk,last_update FROM `risk_scoring_history` WHERE risk_id=:risk_id ORDER BY last_update";
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b");
            
            $sql = "SELECT a.risk_id+1000 as risk_id, a.calculated_risk, a.last_update FROM `risk_scoring_history` a JOIN risks b ON a.risk_id=b.id LEFT JOIN risk_to_team rtt on b.id=rtt.risk_id LEFT JOIN risk_to_additional_stakeholder rtas ON b.id=rtas.risk_id WHERE a.risk_id=:risk_id AND {$separation_query} GROUP BY a.id ORDER BY a.last_update";
        }
        

        $stmt = $db->prepare($sql);
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();
        $histories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // If the risk id is null
    else
    {
        // Get risk scoring histories for all risks
        if (!team_separation_extra()){
            $sql = "SELECT risk_id+1000 as risk_id,calculated_risk,last_update FROM `risk_scoring_history` ORDER BY risk_id,last_update";
        }else{
            // If enabled team seperation.

            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("t2", true, false);

            $sql = "
                SELECT t1.risk_id+1000 as risk_id, t1.calculated_risk, t1.last_update
                FROM `risk_scoring_history` t1
                    LEFT JOIN `risks` t2 on t1.risk_id=t2.id
                    LEFT JOIN `risk_to_team` rtt on t2.id=rtt.risk_id
                    LEFT JOIN `risk_to_additional_stakeholder` rtas on t2.id=rtas.risk_id
                ". $separation_query ."
                GROUP BY
                    t1.id
                ORDER BY
                    t1.risk_id, t1.last_update";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $histories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Close the database connection
    db_close($db);

    // Return the scoring history
    return $histories;
}

/********************************************
 * FUNCTION: GET RESIDUAL SCORING HISTORIES *
 ********************************************/
function get_residual_scoring_histories($risk_id = null)
{
    // Open the database connection
    $db = db_open();

    // If the risk id is not null
    if ($risk_id != null)
    {
        // Convert the risk id to the internal format
        $risk_id = (int)$risk_id - 1000;

        if (!team_separation_extra())
        {
            // Get risk scoring histories by risk id
            $sql = "SELECT risk_id+1000 as risk_id,residual_risk,last_update FROM `residual_risk_scoring_history` WHERE risk_id=:risk_id ORDER BY last_update";
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b");
            
            // Get risk scoring histories by risk id
            $sql = "SELECT a.risk_id+1000 as risk_id, a.residual_risk, a.last_update FROM `residual_risk_scoring_history` a JOIN risks b ON a.risk_id=b.id LEFT JOIN risk_to_team rtt on b.id=rtt.risk_id LEFT JOIN risk_to_additional_stakeholder rtas ON b.id=rtas.risk_id WHERE a.risk_id=:risk_id AND {$separation_query} GROUP BY a.id ORDER BY a.last_update";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();
        $histories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    // If the risk id is null
    else
    {
        // Get risk scoring histories for all risks
        if (!team_separation_extra()){
            $sql = "SELECT risk_id+1000 as risk_id,residual_risk,last_update FROM `residual_risk_scoring_history` ORDER BY risk_id,last_update";
        }else{
            // If enabled team seperation.

            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("t2", true, false);

            $sql = "
                SELECT t1.risk_id+1000 as risk_id, t1.residual_risk, t1.last_update
                FROM `residual_risk_scoring_history` t1
                    LEFT JOIN `risks` t2 on t1.risk_id=t2.id
                    LEFT JOIN `risk_to_team` rtt on t2.id=rtt.risk_id
                    LEFT JOIN `risk_to_additional_stakeholder` rtas on t2.id=rtas.risk_id
                ". $separation_query ."
                GROUP BY
                    t1.id
                ORDER BY
                    t1.risk_id, t1.last_update";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $histories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Close the database connection
    db_close($db);

    // Return the scoring history
    return $histories;
}

/****************************************
 * FUNCTION: CHECK IF SUBMITTED *
 ****************************************/
function is_submitted(){
    if(isset($_POST) && count($_POST)){
        return true;
    }else{
        return false;
    }
}

/****************************************
 * FUNCTION: CHECK IF EXTERNAL PROCESS EXISTS *
 ****************************************/
function is_process($name){
    $cmd = $name;
    exec($cmd, $output, $result);
    if((int)$result !== 127){
        return true;
    }else{
        return false;
    }
}

/***********************************
 * FUNCTION: CREATE `OR` QUERY STRING *
 ***********************************/
function generate_or_query($options, $filedName, $rename = false)
{
    // String starts as empty
    $string = "";

    foreach ($options as $option)
    {
        $option = intval($option);
        if($filedName == "team")
        {
            // If we need to rename the field name
            if ($rename != false)
            {
                $string .= " FIND_IN_SET('{$option}', {$rename}.{$filedName}) OR ";
            }
            // Otherwise append the field name to the string
            else $string .= " FIND_IN_SET('{$option}', {$filedName}) OR ";
        }
        else
        {
            // If we need to rename the field name
            if ($rename != false)
            {
                $string .= $rename . ".{$filedName} = '" . $option . "' OR ";
            }
            // Otherwise append the field name to the string
            else $string .= "`{$filedName}` = '". $option . "' OR ";
        }
    }

    $string .= " 0 ";

    // Return the string
    return $string;
}

/***********************************
 * FUNCTION: GET FILE TYPE LIST*
 ***********************************/
function get_file_types()
{
    // Open the database connection
    $db = db_open();

    // Get the list of allowed file types
    $stmt = $db->prepare("SELECT `name` FROM `file_types`");
    $stmt->execute();

    // Get the result
    $result = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Create an array of allowed types
    foreach ($result as $key => $row)
    {
        $allowed_types[] = $row['name'];
    }

    return $allowed_types;
}

/***********************************
 * FUNCTION: GET AVERAGE SCORE OVER TIME *
 ***********************************/
function get_risks_score_averages($time = "day")
{
    // Check the status
    switch ($time)
    {
        // By day
        case "day":
                $groupby_query = " GROUP BY DATE_FORMAT(b.last_update, '%Y-%m-%d'), IF(a.status='Closed', 0, 1)  ";
                $select_time_query = "  DATE_FORMAT(b.last_update, '%Y-%m-%d') timeAtPoint  ";
                break;
        // By month
        case "month":
                $groupby_query = " GROUP BY DATE_FORMAT(b.last_update, '%Y-%m'), IF(a.status='Closed', 0, 1) ";
                $select_time_query = "  DATE_FORMAT(b.last_update, '%Y-%m') timeAtPoint  ";
                break;
        case "year":
        // By year
                $groupby_query = " GROUP BY DATE_FORMAT(b.last_update, '%Y'), IF(a.status='Closed', 0, 1) ";
                $select_time_query = " DATE_FORMAT(b.last_update, '%Y') timeAtPoint  ";
                break;
        // By day
        default:
                $groupby_query = " GROUP BY DATE_FORMAT(b.last_update, '%Y-%m-%d' ), IF(a.status='Closed', 0, 1) ";
                $select_time_query = " DATE_FORMAT(b.last_update, '%Y-%m-%d') timeAtPoint  ";
                break;
    }


    // Open the database connection
    $db = db_open();

    // If the team separation extra is enabled
    if (!team_separation_extra())
    {
        $query = "SELECT {$select_time_query}, SUM(b.calculated_risk) calculated_risk, count(a.id) number_of_risks, IF(a.status='Closed', 0, 1) status
            FROM risks a INNER JOIN `risk_scoring_history` b ON a.id=b.risk_id ";

    }
    else
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the separation query string
        $separation_query = get_user_teams_query("a");

        $query = "SELECT {$select_time_query}, SUM(b.calculated_risk) calculated_risk, count(a.id) number_of_risks, IF(a.status='Closed', 0, 1) status
            FROM (SELECT a.* FROM `risks` a LEFT JOIN `risk_to_team` rtt on a.id=rtt.risk_id LEFT JOIN `risk_to_additional_stakeholder` rtas on a.id=rtas.risk_id WHERE {$separation_query} GROUP BY a.id) a INNER JOIN `risk_scoring_history` b ON a.id=b.risk_id ";
            
    }

    $query .= $groupby_query;
    $query .= " ORDER BY timeAtPoint ";

    // Get the list of allowed file types
    $stmt = $db->prepare($query);
    $stmt->execute();

    // Get the result
    $rows = $stmt->fetchAll();

    $risk_scores = array();

    foreach($rows as $row){
        $timeAtPoint = $row['timeAtPoint'];
        if($time == "month"){
            $timeAtPoint .= "-01";
        }elseif($time == "year"){
            $timeAtPoint .= "-01-01";
        }

        if(!isset($risk_scores[$timeAtPoint])){
            $risk_scores[$timeAtPoint] = array(
                'opened' => 0,
                'closed' => 0,
                'score' => 0
            );
        }

        if($row['status'] == 1){
            $risk_scores[$timeAtPoint]['opened'] = $row['number_of_risks'];
        }else{
            $risk_scores[$timeAtPoint]['closed'] = $row['number_of_risks'];
        }

        $risk_scores[$timeAtPoint]['score'] += round($row['calculated_risk'], 1);

    }

    // Close the database connection
    db_close($db);

    return $risk_scores;
}

/***********************************
 * FUNCTION: SET CUSTOM DISPLAY SETTINGS *
 ***********************************/
function save_custom_display_settings()
{
    $custom_display_settings = json_encode($_SESSION['custom_display_settings']);

    // Open the database connection
    $db = db_open();

    // Update user
    $stmt = $db->prepare("UPDATE user SET custom_display_settings=:custom_display_settings WHERE value=:value");
    $stmt->bindParam(":custom_display_settings", $custom_display_settings, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":value", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***********************************
 * FUNCTION: RESET CUSTOM DISPLAY SETTINGS *
 ***********************************/
function reset_custom_display_settings()
{
    $_SESSION['custom_display_settings'] = array(
        'id',
        'subject',
        'calculated_risk',
        'submission_date',
        'mitigation_planned',
        'management_review'
    );
    $custom_display_settings = json_encode($_SESSION['custom_display_settings']);

    // Open the database connection
    $db = db_open();

    // Update user
    $stmt = $db->prepare("UPDATE user SET custom_display_settings=:custom_display_settings WHERE value=:value");
    $stmt->bindParam(":custom_display_settings", $custom_display_settings, PDO::PARAM_STR, 100);
    $stmt->bindParam(":value", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*******************************************
 * FUNCTION: GET TECHNOLOGY NAMES FROM IDS *
 *******************************************/
function get_technology_names($ids="")
{
    if(!$ids){
        return "";
    }

    $idArray = explode(",", $ids);
    foreach($idArray as &$id){
        $id = intval($id);
    }
    unset($id);

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT name FROM technology WHERE value in (" . implode(",", $idArray) . "); ");
    $stmt->execute();

    // Store the list in the array
    $results = $stmt->fetchAll();
    // Close the database connection
    db_close($db);

    $names = array();

    foreach($results as $result){
        $names[] = $result['name'];
    }

    return implode(", ", $names);
}

/********************************************
 * FUNCTION: GET STAKEHOLDER NAMES FROM IDS *
 ********************************************/
function get_stakeholder_names($ids="", $limit=4, $escape=false)
{
    global $escaper;

    if(!$ids){
        return "";
    }

    if (is_array($ids))
        $idArray = $ids;
    else
        $idArray = explode(",", $ids);

    foreach($idArray as &$id){
        $id = intval($id);
    }
    unset($id);

    // Open the database connection
    $db = db_open();

    // Update user
    $stmt = $db->prepare("SELECT name FROM user WHERE value in (" . implode(",", $idArray) . "); ");
    $stmt->execute();

    // Store the list in the array
    $users = $stmt->fetchAll();
    // Close the database connection
    db_close($db);

    $names = array();
    $count = 0;
    foreach($users as $user){
        $names[] = $escape ? $escaper->escapeHtml($user['name']) : $user['name'];
        $count += 1;
        if ($count == $limit)
            break;
    }

    return implode(", ", $names) . (count($users) > $limit ? ", ...": "");
}

/***********************************************************************
 * FUNCTION: GET NAMES BY VALUES                                       *
 * Gets the names from the specified $table for the specified $values. *
 * If there're more results than the the $limit it'll only display     *
 * $limit number of results and append "..." at the end.               *
 * Pass 0 or false as the limit to display every names.                *
 * You can also skip escaping in case it's going into the DB           *
 * or will be escaped down the line(to prevent double-escaping)        *
 * Set $force_id to true if the $table has `id` instead of `value`     *
 ***********************************************************************/
function get_names_by_values($table, $values, $limit=4, $escape=true, $force_id=false)
{
    global $escaper;

    if(!$values){
        return "";
    }

    $valueArray = array_map('intval', is_array($values) ? $values : explode(",", $values));

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT name FROM $table WHERE " . ($force_id ? "id" : "value") . " in (" . implode(",", $valueArray) . ");");
    $stmt->execute();

    // Store the list in the array
    $results = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    $names = array();
    global $tables_where_name_is_encrypted;
    $should_decrypt = in_array($table, $tables_where_name_is_encrypted);

    $results_to_display = $limit ? array_slice($results, 0, $limit) : $results;
    
    foreach($results_to_display as $result){
        // Try decrypt if necessary
        if ($should_decrypt)
            $result['name'] = try_decrypt($result['name']);

        $names[] = $escape ? $escaper->escapeHtml($result['name']) : $result['name'];
    }

    return implode(", ", $names) . ($limit && count($results) > $limit ? ", ...": "");
}

/*************************
 * FUNCTION: PING SERVER *
 *************************/
function ping_server()
{
    global $escaper;

    // Set the default path
    $path = "?";

        // Get the instance ID
        $instance_id = get_setting("instance_id");

        // If the instance ID is not false
        if ($instance_id != false)
        {
        // Add the instance ID to the path
                $path .= "instance_id=" . $instance_id;
        }
        else $path .= "instance_id=";

    // Get the timezone
    $timezone = date_default_timezone_get();

    // Add the timezone to the path
    $path .= "&timezone=" . $timezone;

        // Open the database connection
        $db = db_open();

    // Get the total number of risks
        $stmt = $db->prepare("SELECT COUNT(id) FROM risks");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $risks = $array[0][0];

    // Add the risks to the path
    $path .= "&risks=" . $risks;

    // Get the total number of users
        $stmt = $db->prepare("SELECT COUNT(value) FROM user");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $users = $array[0][0];

    // Add the users to the path
    $path .= "&users=" . $users;

    // Get the application version
    $app_version = $escaper->escapeHtml(current_version("app"));

    // Add the app version to the path
    $path .= "&app_version=" . $app_version;

    // Get the database version
    $db_version = $escaper->escapeHtml(current_version("app"));

    // Add the database version to the path
    $path .= "&db_version=" . $db_version;

    // If the instance is registered
    if (get_setting('registration_registered') != 0)
    {
        // Load the upgrade.php file
        //require_once(realpath(__DIR__ . '/../extras/upgrade/index.php'));
        $path .= "&email_notification_installed=" . core_is_installed("notification");
        $path .= "&email_notification_enabled=" . notification_extra();
        $path .= "&email_notification_version=" . core_extra_current_version("notification");
        $path .= "&import_export_installed=" . core_is_installed("import-export");
        $path .= "&import_export_enabled=" . import_export_extra();
        $path .= "&import_export_version=" . core_extra_current_version("import-export");
        $path .= "&risk_assessment_installed=" . core_is_installed("assessments");
        $path .= "&risk_assessment_enabled=" . assessments_extra();
        $path .= "&risk_assessment_version=" . core_extra_current_version("assessments");
        $path .= "&team_separation_installed=" . core_is_installed("separation");
        $path .= "&team_separation_enabled=" . team_separation_extra();
        $path .= "&team_separation_version=" . core_extra_current_version("separation");
        $path .= "&custom_authentication_installed=" . core_is_installed("authentication");
        $path .= "&custom_authentication_enabled=" . custom_authentication_extra();
        $path .= "&custom_authentication_version=" . core_extra_current_version("authentication");
        $path .= "&customization_installed=" . core_is_installed("customization");
        $path .= "&customization_enabled=" . customization_extra();
        $path .= "&customization_version=" . core_extra_current_version("customization");
        $path .= "&api_installed=" . core_is_installed("api");
        $path .= "&api_enabled=" . api_extra();
        $path .= "&api_version=" . core_extra_current_version("api");
        $path .= "&encryption_installed=" . core_is_installed("encryption");
        $path .= "&encryption_enabled=" . encryption_extra();
        $path .= "&encryption_version=" . core_extra_current_version("encryption");
        $path .= "&complianceforgescf_installed=" . core_is_installed("complianceforgescf");
        $path .= "&complianceforgescf_enabled=" . complianceforge_scf_extra();
        $path .= "&complianceforgescf_version=" . core_extra_current_version("complianceforgescf");
        $path .= "&advanced_search_installed=" . core_is_installed("advanced_search");
        $path .= "&advanced_search_enabled=" . advanced_search_extra();
        $path .= "&advanced_search_version=" . core_extra_current_version("advanced_search");
        $path .= "&jira_installed=" . core_is_installed("jira");
        $path .= "&jira_enabled=" . jira_extra();
        $path .= "&jira_version=" . core_extra_current_version("jira");
        $path .= "&ucf_installed=" . core_is_installed("ucf");
        $path .= "&ucf_enabled=" . ucf_extra();
        $path .= "&ucf_version=" . core_extra_current_version("ucf");
        $path .= "&incident_management_installed=" . core_is_installed("incident_management");
        $path .= "&incident_management_enabled=" . incident_management_extra();
        $path .= "&incident_management_version=" . core_extra_current_version("incident_management");
        $path .= "&organizational_hierarchy_installed=" . core_is_installed("organizational_hierarchy");
        $path .= "&organizational_hierarchy_enabled=" . organizational_hierarchy_extra();
        $path .= "&organizational_hierarchy_version=" . core_extra_current_version("organizational_hierarchy");
	$path .= "&vulnmgmt_installed=" . core_is_installed("vulnmgmt");
	$path .= "&vulnmgmt_enabled=" . vulnmgmt_extra();
	$path .= "&vulnmgmt_version=" . core_extra_current_version("vulnmgmt");

        // If the organizational hierarchy extra is enabled
	if (organizational_hierarchy_extra())
	{
		// Get the count of business units
        	$stmt = $db->prepare("SELECT COUNT(id) FROM business_unit;");
        	$stmt->execute();
        	$array = $stmt->fetchAll();
        	$organizational_hierarchy_count = (int)$array[0][0];	

		// Add the count of business units to the path
		$path .= "&organizational_hierarchy_count=" . $organizational_hierarchy_count;
	}
    }

    // Close the database connection
    db_close($db);

    // Configure the proxy server if one exists
    $context = set_proxy_stream_context();

    // Set the default socket timeout to 5 seconds
    ini_set('default_socket_timeout', 5);

    // Url for SimpleRisk ping
    if (defined('PING_URL'))
    {
        $url = PING_URL . $path;
    }
    else $url = 'https://ping.simplerisk.com' . $path;

    // Make the https request
    file_get_contents($url, null, $context);
}

/*******************************************
 * FUNCTION: CREATE SIMPLERISK INSTANCE ID *
 *******************************************/
function create_simplerisk_instance_id()
{
    // Get the instance identifier
    $instance_id = get_setting("instance_id");

    // If the instance id is false
    if ($instance_id == false)
    {
        // Create a random instance id
        $instance_id = generate_token(50);
	add_setting("instance_id", $instance_id);

        // Return the instance_id
        return $instance_id;
    }
    // Otherwise, return the instance_id
    else return $instance_id;
}

/*******************************************
 * FUNCTION: STRIP LINE BREAKS FROM STRING *
 *******************************************/
function remove_line_breaks($string){
    $string = trim(preg_replace("/\s\s+|\r|\n/", ' ', $string));
    return $string;
}

/*******************************************************
 * FUNCTION: GET_PARAM - GET VALUE GET OR POST REQUEST *
 ******************************************************/
function get_param($method, $name, $default=""){
    $value = false;
    switch(strtoupper($method)){
        case "POST":
            $value = isset($_POST[$name]) ? $_POST[$name] : false;
        break;

        case "GET":
            $value = isset($_GET[$name]) ? $_GET[$name] : false;
        break;

        case "REQUEST":
            $value = isset($_REQUEST[$name]) ? $_GET[$name] : false;
        break;
    }

    if($value === false){
        $data = json_decode(file_get_contents('php://input'), true);
        if(is_array($data)){
            $value = isset($data[$name]) ? $data[$name] : false;
        }
    }

    if($value === false){
        $value = $default;
    }

    return $value;
}

/************************************
 * FUNCTION: IS SIMPLERISK DB TABLE *
 ************************************/
function is_simplerisk_db_table($table_name)
{
    // Initialize a tables array
    $tables = array();

    // Open the database connection
    $db = db_open();

    // Get list of tables
    $stmt = $db->prepare("SHOW TABLES;");
    $stmt->execute();
    $array = $stmt->fetchAll();

    foreach ($array as $value)
    {
        // Add the value to an array
        $table = $value[0];
        $tables[] = $table;
    }

    // Close the database connection
    db_close($db);

    // Return whether the table name is in the list of tables
    return in_array($table_name, $tables);
}

/**********************************
 * FUNCTION: REFRESH CURRENT PAGE *
 **********************************/
function refresh($url = false){
    if($url !== false){
        header('Location: '.$url);
    }else{
        header('Location: '.$_SERVER['REQUEST_URI']);
    }
    exit;
}

/****************************
 * FUNCTION: ADD NEW FAMILY *
 ****************************/
function add_family($short_name){
    if(!$short_name){
        return false;
    }

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("INSERT INTO `family` (`name`) VALUES (:short_name)");
    $stmt->bindParam(":short_name", $short_name, PDO::PARAM_STR, 20);
    $stmt->execute();
    $insertedId = $db->lastInsertId();

    $risk_id = 1000;
    $message = "A new family \"" . $short_name . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return $insertedId;
}

/****************************
 * FUNCTION: ADD NEW FAMILY *
 ****************************/
function update_family($value, $short_name){
    if(!$short_name){
        return false;
    }

    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("UPDATE `family` SET `name`=:short_name WHERE value=:value;");
    $stmt->bindParam(":short_name", $short_name, PDO::PARAM_STR, 20);
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    $risk_id = 1000;
    $message = "A new family \"" . $short_name . "\" was updated by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return true;
}

/***************************
 * FUNCTION: DELETE FAMILY *
 ***************************/
function delete_family($value)
{
    // Open the database connection
    $db = db_open();

    // Delete the table value
    $stmt = $db->prepare("DELETE FROM `family` WHERE value=:value");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    return true;
}

/*******************************************************
 * FUNCTION: GET CONVERTED STRING FROM TEMPLATE STRING *
 *******************************************************/
function get_string_from_template($template, $data){
    global $escaper;

    foreach($data as &$val){
        $val = $escaper->escapeHtml($val);
    }

    foreach($data as $key => $value) {
        $template = str_replace('{$' . $key .'}', $value, $template);
    }

    return $template;
}

/************************
 * FUNCTION: DELETE DIR *
 ************************/
function delete_dir($dir)
{
    $tmp = dirname(__FILE__);

    // If this is not Windows (directory paths don't start with /)
    if (strpos($tmp, '/', 0) !== false)
    {
        linux_delete_dir($dir);
    }
    // If this is Windows
    else
    {
        windows_delete_dir($dir);
    }
}

/*************************
 * FUNCTION: DELETE FILE *
 *************************/
function delete_file($file)
{
        $tmp = dirname(__FILE__);

        // If this is not Windows (directory paths don't start with /)
        if (strpos($tmp, '/', 0) !== false)
        {
            return linux_delete_file($file);
        }
        // If this is Windows
        else
        {
            return windows_delete_file($file);
        }
}

/******************************
 * FUNCTION: LINUX DELETE DIR *
 ******************************/
function linux_delete_dir($dir)
{
    $files = array_diff(scandir($dir), array('.','..'));

    foreach ($files as $file)
    {
        (is_dir("$dir/$file")) ? linux_delete_dir("$dir/$file") : linux_delete_file("$dir/$file");
        }

    return rmdir($dir);
}

/*******************************
 * FUNCTION: LINUX DELETE FILE *
 *******************************/
function linux_delete_file($file)
{
    // Delete a file in Linux
    $success = false;
    if(is_file($file)) $success = unlink($file);

    // Return the results
    return $success;
}

/********************************
 * FUNCTION: WINDOWS DELETE DIR *
 ********************************/
function windows_delete_dir($dir)
{
    // Recursively delete directory and its contents
    $success = exec("RMDIR /s \"" . $dir . "\"", $lines, $deleteError);

    // Return the results
    return $success;
}

/*********************************
 * FUNCTION: WINDOWS DELETE FILE *
 *********************************/
function windows_delete_file($file)
{
    $file = str_replace("/", "\\", $file);
    
    // Delete a file in Windows
    $success = exec("DEL /F/Q \"" . $file . "\"", $lines, $deleteError);

    // Return the results
    return $success;
}

/***************************
 * FUNCTION: TIMEZONE LIST *
 ***************************/
function timezone_list()
{
    static $timezones = null;

    if ($timezones === null) {
        $timezones = [];
        $offsets = [];
        $now = new DateTime('now', new DateTimeZone('UTC'));

        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $now->setTimezone(new DateTimeZone($timezone));
            $offsets[] = $offset = $now->getOffset();
            $timezones[$timezone] = '(' . format_UTC_offset($offset) . ') ' . format_timezone_name($timezone);
        }

        array_multisort($offsets, $timezones);
    }

    return $timezones;
}

/*******************************
 * FUNCTION: FORMAT UTC OFFSET *
 *******************************/
function format_UTC_offset($offset)
{
    $hours = intval($offset / 3600);
    $minutes = abs(intval($offset % 3600 / 60));
    return 'UTC' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
}

/**********************************
 * FUNCTION: FORMAT TIMEZONE NAME *
 **********************************/
function format_timezone_name($name)
{
    //$name = str_replace('/', ', ', $name);
    $name = str_replace('_', ' ', $name);
    $name = str_replace('St ', 'St. ', $name);
    return $name;
}

/***********************************************
 * FUNCTION: SET SESSION LAST ACTIVITY TIMEOUT *
 ***********************************************/
function set_session_last_activity_timeout()
{
        // Get the setting for the session activity timeout
        $session_activity_timeout = get_setting("session_activity_timeout");

        // If the setting doesn't exist
        if (!$session_activity_timeout)
        {
                // Set the session activity timeout to the value in the config file
                $session_activity_timeout = LAST_ACTIVITY_TIMEOUT;

                // If the session activity timeout isn't null
                if ($session_activity_timeout != null)
                {
                        // Add the value to the settings table
                        add_setting("session_activity_timeout", $session_activity_timeout);
                }
                // Otherwise
                else
                {
                        // Set the session activity timeout to a default of 3600 (1 hour)
                        add_setting("session_activity_timeout", "3600");
                }
        }
}

/*************************
 * FUNCTION: CSP ENABLED *
 *************************/
function csp_enabled()
{
    // Get the setting for the content security policy
    $content_security_policy = get_setting("content_security_policy");

    // If the content security policy is enabled
    if ($content_security_policy == 1)
    {
        // Return true
        return true;
    }
    // Otherwise, return false
    else return false;
}

/*****************************************
 * FUNCTION: SET CONTENT SECURITY POLICY *
 *****************************************/
function set_content_security_policy()
{
        // Get the setting for the content security policy
        $content_security_policy = get_setting("content_security_policy");

        // If the setting doesn't exist
        if (!$content_security_policy)
        {
                // Set the content security policy to the value in the config file
                $content_security_policy = CSP_ENABLED;

                // If the content security policy isn't null
                if ($content_security_policy != null)
                {
                        // Set the content security policy to 1 if true and 0 if not
                        $content_security_policy = ($content_security_policy == "true") ? 1 : 0;

                        // Add the value to the settings table
                        add_setting("content_security_policy", $content_security_policy);
                }
                // Otherwise
                else
                {
                        // Set the content security policy to false
                        add_setting("content_security_policy", "0");
                }
        }
}

/*******************************
 * FUNCTION: SET DEBUG LOGGING *
 *******************************/
function set_debug_logging()
{
        // Get the setting for the debug logging
        $debug_logging = get_setting("debug_logging");

        // If the setting doesn't exist
        if (!$debug_logging)
        {
                // Set the debug logging to the value in the config file
                $debug_logging = DEBUG;

                // If the debug logging isn't null
                if ($debug_logging != null)
                {
                        // Set the debug logging to 1 if true and 0 if not
                        $debug_logging = ($debug_logging == "true") ? 1 : 0;

                        // Add the value to the settings table
                        add_setting("debug_logging", $debug_logging);
                }
                // Otherwise
                else
                {
                        // Set the debug logging to false
                        add_setting("debug_logging", "0");
                }
        }
}

/********************************
 * FUNCTION: SET DEBUG LOG FILE *
 ********************************/
function set_debug_log_file()
{
        // Get the setting for the debug log file
        $debug_log_file = get_setting("debug_log_file");

        // If the setting doesn't exist
        if (!$debug_log_file)
        {
                // Set the debug log file to the value in the config file
                $debug_log_file = DEBUG_FILE;

                // If the debug log file isn't null
                if ($debug_log_file != null)
                {
                        // Add the value to the settings table
                        add_setting("debug_log_file", $debug_log_file);
                }
                // Otherwise
                else
                {
                        // Set the debug log file to /tmp/debug_log
                        add_setting("debug_log_file", "/tmp/debug_log");
                }
        }
}

/**********************************
 * FUNCTION: SET DEFAULT LANGUAGE *
 **********************************/
function set_default_language()
{
        // Get the setting for the default language
        $default_language = get_setting("default_language");

        // If the setting doesn't exist
        if (!$default_language)
        {
                // Set the default language to the value in the config file
                $default_language = LANG_DEFAULT;

                // If the default language isn't null
                if ($default_language != null)
                {
                        // Add the value to the settings table
                        add_setting("default_language", $default_language);
                }
                // Otherwise
                else
                {
                        // Set the default language to english
                        add_setting("default_language", "en");
                }
        }
}

/*********************************
 * FUNCTION: SET DEFAULT TIMEONE *
 *********************************/
function set_default_timezone()
{
        // Get the setting for the default timezone
        $default_timezone = get_setting("default_timezone");

        // If the setting doesn't exist
        if (!$default_timezone)
        {
                // Set the default timezone to the value currently set
                $default_timezone = date_default_timezone_get();

                // If the default timezone isn't null
                if ($default_timezone != null)
                {
                        // Add the value to the settings table
                        add_setting("default_timezone", $default_timezone);
                }
                // Otherwise
                else
                {
                        // Set the default timezone to America/Chicago
                        add_setting("default_timezone", "America/Chicago");
                }
        }
}

/******************************************
 * FUNCTION: SET UNAUTHENTICATED REDIRECT *
 ******************************************/
function set_unauthenticated_redirect()
{
    // Get the requested URL
    $requested_url = get_current_url();

    // Store it in the session
    $_SESSION['requested_url'] = $requested_url;
}

/***************************
 * FUNCTION: DELETE A ROLE *
 ***************************/
function delete_role($role_id) {

    $deleted_role_responsibilities = get_responsibilites_by_role_id($role_id);

    $default_role = get_default_role();
    $default_role_id = $default_role ? $default_role['value'] : 0;
    $default_responsibilities = $default_role ? get_responsibilites_by_role_id($default_role_id) : [];

    // Open the database connection
    $db = db_open();

    // Get all the users that have this role and their current permissions
    $stmt = $db->prepare("
            SELECT
                `u`.`value` AS value,
                `p2u`.`permission_id`
            FROM
                `user` u
                LEFT JOIN `permission_to_user` p2u ON `u`.`value` = `p2u`.`user_id`
            WHERE
                `u`.`role_id` = :role_id;
        ");
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->execute();
    $permissions_of_users = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);

    // Iterate through the list of users and update their permissions
    foreach($permissions_of_users as $user_id => $permissions) {

        // If a user has no permissions the $permissions contain invalid data
        if (!$permissions || !$permissions[0]) {
            $permissions = [];
        }

        // Add the new responsibilities and remove what needs to be removed
        $new_responsibilities = array_unique(array_merge(array_diff($permissions, $deleted_role_responsibilities), $default_responsibilities));

        // Update the user's permissions
        update_permissions($user_id, $new_responsibilities);

        // Update users to use their new role(default)
        $stmt = $db->prepare("UPDATE `user` SET `role_id` = :role_id WHERE value=:user_id;");
        $stmt->bindParam(":role_id", $default_role_id, PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Get the name before deleted
    $name = get_name_by_value('role', $role_id);

    // Delete the role
    $stmt = $db->prepare("DELETE FROM `role` WHERE value=:value; ");
    $stmt->bindParam(":value", $role_id, PDO::PARAM_INT);
    $stmt->execute();

    cleanup_after_delete('role');

    $risk_id = 1000;
    $message = "The existing role \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return true;
}

/*******************************************
 * FUNCTION: SAVE ROLE AND RESPONSIBILITES *
 *******************************************/
function save_role_responsibilities($role_id, $admin, $default, $responsibilities) {
    
    $db = db_open();
    
    // Store if the admin status of the role is changed
    $stmt = $db->prepare("SELECT `admin` FROM `role` WHERE `value` = :role_id;");
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->execute();
    $admin_old = $stmt->fetchColumn();
    $role_admin_status_changed = ($admin_old !== null && $admin_old === '1') !== $admin;

    if ($default) {
        set_default_role($role_id);
    }

    if ($role_admin_status_changed) {
        $stmt = $db->prepare("UPDATE `role` SET `admin` = :admin WHERE `value` = :role_id;");
        $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
        $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    if ($admin) {
        // Admins have ALL the responsibilities
        $responsibilities = get_possible_permission_ids();
    } else {
        // Removing entries that are not on the list of possible permissions to sanitize the input
        $responsibilities = array_intersect(get_possible_permission_ids(), $responsibilities);
    }

    $current_responsibilities = get_responsibilites_by_role_id($role_id);
    
    if (save_junction_values('role_responsibilities', 'role_id', $role_id, 'permission_id', $responsibilities)) {
        // Calculate what permissions are removed from the role and what permissions are added
        $responsibilities_to_remove = array_diff($current_responsibilities, $responsibilities);
        $responsibilities_to_add = array_diff($responsibilities, $current_responsibilities);

        // Get all the users that have this role and their current permissions
        $stmt = $db->prepare("
            SELECT
                `u`.`value` AS value,
                `p2u`.`permission_id`
            FROM
                `user` u
                LEFT JOIN `permission_to_user` p2u ON `u`.`value` = `p2u`.`user_id`
            WHERE
                `u`.`role_id` = :role_id;
        ");
        $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
        $stmt->execute();
        $permissions_of_users = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);

        // Iterate through the list of users and update their permissions
        foreach($permissions_of_users as $user_id => $permissions) {

            // If a user has no permissions the $permissions contain invalid data
            if (!$permissions || !$permissions[0]) {
                $permissions = [];
            }

            // If the admin status of the role changed
            if ($role_admin_status_changed) {
                // Update the user's admin status
                $stmt = $db->prepare("
                    UPDATE
                        `user`
                    SET
                        `admin`=:admin
                    WHERE
                        `value`=:user_id;
                ");

                $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);

                $stmt->execute();
            }

            // Add the new responsibilities and remove what needs to be removed
            $new_responsibilities = array_unique(array_diff(array_merge($permissions, $responsibilities_to_add), $responsibilities_to_remove));

            // Update the user's permissions
            update_permissions($user_id, $new_responsibilities);

            // If the update affects the current logged in user
            if (isset($_SESSION['uid']) && $_SESSION['uid'] == $user_id && isset($_SESSION['user'])) {
                set_user_permissions($_SESSION['user']);
            }

            // Refresh the permissions in the active sessions of the user
            refresh_permissions_in_sessions_of_user($user_id);
        }
    }

    // Close the database connection
    db_close($db);
}

/********************************************
 * FUNCTION: GET RESPONSIBILITES BY ROLE ID *
 ********************************************/
function get_role($role_id)
{
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("SELECT `value`, `name`, `admin`, (`r`.`default` IS NOT NULL) AS 'default' FROM `role` r WHERE `r`.`value`=:role_id");
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Get responsibilites
    $role = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($role) {
        $role = $role[0];
        $role['responsibilities'] = get_responsibilites_by_role_id($role_id);
        $role['admin'] = $role['admin'] === '1';
        $role['default'] = $role['default'] === '1';
    }

    // Close the database connection
    db_close($db);
    
    return $role;
}

/********************************************
 * FUNCTION: GET RESPONSIBILITES BY ROLE ID *
 ********************************************/
function get_responsibilites_by_role_id($role_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT permission_id FROM `role_responsibilities` WHERE `role_id`=:role_id");
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->execute();

    // Get responsibilites
    $array = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Close the database connection
    db_close($db);

    return $array;
}

/*****************************************
 * FUNCTION: ACCEPT OR REJECT MITIGATION *
 *****************************************/
function accept_mitigation_by_risk_id($risk_id, $accept)
{
    $risk_id = (int)$risk_id - 1000;
    $user_id = $_SESSION['uid'];
    // Open the database connection
    $db = db_open();

    // If accept mitigation, add a new record
    if($accept)
    {
        $stmt = $db->prepare("INSERT INTO `mitigation_accept_users`(`risk_id`, `user_id`, `created_at`) VALUES(:risk_id, :user_id, :created_at);");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $today = date("Y-m-d H:i:s");
        $stmt->bindParam(":created_at", $today, PDO::PARAM_STR);
        $stmt->execute();

        $message = "Mitigation for risk ID ". convert_id($risk_id) ." accepted by \"" . $_SESSION['user'] . "\" user.";
        write_log(convert_id($risk_id), $_SESSION['uid'], $message);
    }
    // If decline mitigation, delete a record
    else
    {
        $stmt = $db->prepare("DELETE FROM `mitigation_accept_users` WHERE `risk_id`=:risk_id AND `user_id`=:user_id;");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $message = "Mitigation for risk ID ". convert_id($risk_id) ." rejected by \"" . $_SESSION['user'] . "\" user.";
        write_log(convert_id($risk_id), $_SESSION['uid'], $message);
    }
    // Close the database connection
    db_close($db);
}

/*********************************************************
 * FUNCTION: GET ACCEPTED MITIGATION BY USER AND RISK ID *
 *********************************************************/
function get_accpeted_mitigation($risk_id)
{
    $risk_id = (int)$risk_id - 1000;
    $user_id = $_SESSION['uid'];
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.user_id, t1.risk_id, t1.created_at, t2.username FROM `mitigation_accept_users` t1 LEFT JOIN `user` t2 ON t1.user_id=t2.value WHERE t1.risk_id=:risk_id AND t1.user_id=:user_id;");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $info = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return $info;
}

/**************************************
 * FUNCTION: GET ACCEPTED MITIGATIONS *
 **************************************/
function get_accpeted_mitigations($risk_id)
{
    $risk_id = (int)$risk_id - 1000;
    $user_id = $_SESSION['uid'];
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.user_id, t1.risk_id, t1.created_at, t2.username, t2.name FROM `mitigation_accept_users` t1 LEFT JOIN `user` t2 ON t1.user_id=t2.value WHERE t1.risk_id=:risk_id;");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    $infos = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $infos;
}

/**************************************
* FUNCTION: VIEW ACCEPTED MITIGATIONS *
***************************************/
function view_accepted_mitigations($risk_id)
{
    $infos = get_accpeted_mitigations($risk_id);

    $message = "";

    foreach($infos as $info)
    {
        $name = isset($info['name']) ? $info['name'] : "Unknown User";
        $date = isset($info['created_at']) ? date(get_default_date_format(), strtotime($info['created_at'])) : "";
        $time = $info['created_at'] ? date("H:i", strtotime($info['created_at'])) : "";
        $message .= "<input disabled type=\"checkbox\" checked> &nbsp;&nbsp;&nbsp;"._lang("MitigationAcceptedByUserOnTime", ["name"=>$name, "date"=>$date, "time"=>$time])."<br>";

    }

    return $message;
}

/********************************************
* FUNCTION: SET ALL TEAMS TO ADMINISTRATORS *
*********************************************/
function set_all_teams_to_administrators() {

    // Open the database connection
    $db = db_open();

    // Get all teams
    $stmt = $db->prepare("SELECT `value` FROM `user` where `admin` = 1;");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Close the database connection
    db_close($db);

    $team_ids = get_all_team_values();

    foreach($admins as $admin) {
        set_teams_of_user($admin, $team_ids);
    }
}

/*************************************
 * FUNCTION: SET SIMPLERISK TIMEZONE *
 *************************************/
function set_simplerisk_timezone()
{
    // Get the value set for the timezone in the database
    $default_timezone = get_setting("default_timezone");

    // If no timezone is set, set it to CST
    if (!$default_timezone) $default_timezone = "America/Chicago";

    // Set the timezone for PHP date functions
    date_default_timezone_set($default_timezone);
}

/**********************************
 * FUNCTION: ADD SECURITY HEADERS *
 **********************************/
function add_security_headers($x_frame_options = true, $x_xss_protection = true, $x_content_type_options = true, $content_type = true, $content_security_policy = true)
{
	// If we want to send a X-Frame-Options header
	if ($x_frame_options)
	{
		header("X-Frame-Options: DENY");
	}

	// If we want to send a X-XSS-Protection header 
	if ($x_xss_protection)
	{
		header("X-XSS-Protection: 1; mode=block");
	}

	// If we want to send a X-Content-Type-Options header
	if ($x_content_type_options)
	{
		header("X-Content-Type-Options: nosniff");
	}

	// If we want to send a Content-Type header
	if ($content_type)
	{
		header("Content-Type: text/html; charset=utf-8");
	}

	// If we want to send a Content-Security-Policy header
	if ($content_security_policy)
	{
		// If we want to enable the Content Security Policy (CSP) - This may break Chrome
		if (csp_enabled())
		{
			// If the base URL is not set
			if (!isset($_SESSION) || !array_key_exists('base_url', $_SESSION))
			{
				// Get the base URL
				$simplerisk_base_url = get_setting("simplerisk_base_url");
			}
			// Otherwise, set the base URL
			else  $simplerisk_base_url = $_SESSION['base_url'];

			// If the simplerisk base url is valid
			if (filter_var($simplerisk_base_url, FILTER_VALIDATE_URL))
			{
				// Add the Content-Security-Policy header with the simplerisk base url
				header("Content-Security-Policy: default-src 'self'; style-src-elem 'unsafe-inline' *.googleapis.com cdn.jsdelivr.net " . $simplerisk_base_url . "; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval' *.googleapis.com *.highcharts.com *.jquery.com cdn.jsdelivr.net; font-src *.gstatic.com cdn.jsdelivr.net " . $simplerisk_base_url . "; img-src 'self' *.googleapis.com " . $simplerisk_base_url . " data:; connect-src 'self' *.simplerisk.com; frame-src 'self' *.duosecurity.com;");
			}
			// Otherwise add the Content-Security-Policy header without it
			else header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data:");
		}
		else header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data:");
	}
}

/******************************************
 * FUNCTION: CONVERT FILE SIZE INTO BYTES *
 ******************************************/
function convert_file_size_into_bytes($file_size)
{
    // Take a file size in the format ^\s*\d+\s*[kmg].* and extract the number and suffix
    if(preg_match("/^\s*(\d+)\s*([kmg])/i", $file_size, $matches))
    {
        $value = (int) $matches[1];
        $suffix = strtolower($matches[2]);
        switch($suffix)
        {
            case "g":
                $value *= 1024;
            case "m":
                $value *= 1024;
            case "k":
                $value *= 1024;
        }

        return $value;
    }

    // return false to indicate parsing failed
    return false;
}

/**************************************
 * FUNCTION: MYSQL MAX ALLOWED VALUES *
 **************************************/
function mysql_max_allowed_values()
{
    // Open the database connection
    $db = db_open();

    // Get the max allowed packet
    $stmt = $db->prepare("SHOW VARIABLES LIKE 'max_allowed_packet';");
    $stmt->execute();
    $max_allowed_packet = $stmt->fetch();
    $max_allowed_packet = $max_allowed_packet['Value'];

    // Get the innodb_log_file_size
    $stmt = $db->prepare("SHOW VARIABLES LIKE 'innodb_log_file_size';");
    $stmt->execute();
    $innodb_log_file_size = $stmt->fetch();
    $innodb_log_file_size = $innodb_log_file_size['Value'];
    $innodb_log_file_size = $innodb_log_file_size / 10;

    // Close the database connection
    db_close($db);

    // Return the smaller value
    return min($max_allowed_packet, $innodb_log_file_size);
}

/************************************
 * FUNCTION: PHP MAX ALLOWED VALUES *
 ************************************/
function php_max_allowed_values()
{
    // Get the smallest value between the upload_max_filesize, post_max_size, and memory_limit
    $php_max_upload_size = min(convert_file_size_into_bytes(ini_get('upload_max_filesize')), convert_file_size_into_bytes(ini_get('post_max_size')), convert_file_size_into_bytes(ini_get('memory_limit')));

    // Return the smallest value
    return $php_max_upload_size;
}

/********************************************
 * FUNCTION: GET VALUE STRING BY TABLE NAME *
 ********************************************/
function get_value_string_by_table($table)
{
    $values = [];
    $rows = get_full_table($table);
    if($rows){
        foreach($rows as $row)
        {
            $values[] = $row['value'];
        }
    }

    return implode(",", $values);
}

/******************************
 * FUNCTION: ADD IMPACE VALUE *
 ******************************/
function add_impact()
{
    global $lang, $escaper;

    $old_likelihood_value = get_likelihoods_count();
    $old_impact_value = get_impacts_count();

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT max(value) FROM `impact`;");
    $stmt->execute();
    $max_value = $stmt->fetch(PDO::FETCH_COLUMN);
    $value = $max_value+1;

    $name = "Impact ".$value;

    // Add a new impact value
    $stmt = $db->prepare("INSERT INTO `impact`(name, value) VALUES(:name, :value);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    write_log(1000, $_SESSION['uid'], "A new impact named \"".$escaper->escapeHtml($name)."\" was created by the \"" . $_SESSION['user'] . "\" user.");

    $new_likelihood_value = get_likelihoods_count();
    $new_impact_value = get_impacts_count();
    
    update_impact_likelihood( $old_impact_value, $new_impact_value, $old_likelihood_value, $new_likelihood_value );
    
    return $stmt->rowCount();
}

/*****************************************
 * FUNCTION: DELETE HIGHEST IMPACT VALUE *
 *****************************************/
function delete_impact()
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.value, t1.name FROM `impact` t1 JOIN (SELECT MAX(value) as max_value FROM `impact`) t2 WHERE t1.value=t2.max_value;");
    $stmt->execute();
    $array = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($array)
    {
        if( $array['value'] == 1 ){
            
        } else {
            $old_likelihood_value = get_likelihoods_count();
            $old_impact_value = get_impacts_count();
            
            // Delete a impact value            
            $stmt = $db->prepare("DELETE FROM `impact` WHERE value=:value;");
            $stmt->bindParam(":value", $array['value'], PDO::PARAM_INT);
            $stmt->execute();
            write_log(1000, $_SESSION['uid'], "An impact named \"".$escaper->escapeHtml($array['name'])."\" was deleted by the \"" . $_SESSION['user'] . "\" user.");
            
            $new_likelihood_value = get_likelihoods_count();
            $new_impact_value = get_impacts_count();
            
            update_impact_likelihood( $old_impact_value, $new_impact_value, $old_likelihood_value, $new_likelihood_value );
        }
        
    }

    // Close the database connection
    db_close($db);

    return $stmt->rowCount();
}

/**********************************
 * FUNCTION: ADD LIKELIHOOD VALUE *
 **********************************/
function add_likelihood()
{
    global $lang, $escaper;
 
    $old_likelihood_value = get_likelihoods_count();
    $old_impact_value = get_impacts_count();
    
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT max(value) FROM `likelihood`;");
    $stmt->execute();
    $max_value = $stmt->fetch(PDO::FETCH_COLUMN);
    $value = $max_value+1;
    $name = "Likelihood ".$value;

    // Add a new impact value
    $stmt = $db->prepare("INSERT INTO `likelihood`(name, value) VALUES(:name, :value);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    write_log(1000, $_SESSION['uid'], "A new likelihood named \"".$escaper->escapeHtml($name)."\" was created by the \"" . $_SESSION['user'] . "\" user.");

    $new_likelihood_value = get_likelihoods_count();
    $new_impact_value = get_impacts_count();

    update_impact_likelihood( $old_impact_value, $new_impact_value, $old_likelihood_value, $new_likelihood_value );

    return $stmt->rowCount();
}

/*********************************************
 * FUNCTION: DELETE HIGHEST LIKELIHOOD VALUE *
 *********************************************/
function delete_likelihood()
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.value, t1.name FROM `likelihood` t1 JOIN (SELECT MAX(value) as max_value FROM `likelihood`) t2 WHERE t1.value=t2.max_value;");
    $stmt->execute();
    $array = $stmt->fetch(PDO::FETCH_ASSOC);

    if($array)
    {       
        if( $array['value'] == 1 ){
            
        } else {
            $old_likelihood_value = get_likelihoods_count();
            $old_impact_value = get_impacts_count();
            
            // Delete a likelihood value
            $stmt = $db->prepare("DELETE FROM `likelihood` WHERE value=:value;");
            $stmt->bindParam(":value", $array['value'], PDO::PARAM_INT);
            $stmt->execute();
            write_log(1000, $_SESSION['uid'], "An likelihood named \"".$escaper->escapeHtml($array['name'])."\" was deleted by the \"" .$_SESSION['user'] . "\" user.");
            
            $new_likelihood_value = get_likelihoods_count();
            $new_impact_value = get_impacts_count();
            
            update_impact_likelihood( $old_impact_value, $new_impact_value, $old_likelihood_value, $new_likelihood_value );
        }
    }

    // Close the database connection
    db_close($db);

    return $stmt->rowCount();
}

/**********************
 * FUNCTION: IS ADMIN *
 **********************/
function is_admin($id = false)
{
    // If there's no user id provided OR we're checking the logged in user then it will work the way it did before
    if ($id === false || (isset($_SESSION['uid']) && (int)$id === (int)$_SESSION['uid'])) {
        // If the user is not logged in as an administrator
        if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
        {
            return false;
        }
        else return true;
    } else {
        $db = db_open();

        $stmt = $db->prepare("
            SELECT 
                `admin`
            FROM
                `user`
            WHERE
                `value` = :user_id;
        ");
        $stmt->bindParam(":user_id", $id, PDO::PARAM_INT);
        $stmt->execute();

        $admin = $stmt->fetchColumn();

        db_close($db);
        
        return $admin !== null && $admin === '1';
    }
}

/*************************************
 * FUNCTION: UPLOAD COMPLIANCE FILES *
 *************************************/
function upload_compliance_files($test_audit_id, $ref_type, $files, $version=1)
{
    $user = $_SESSION['uid'];
    
    // Open the database connection
    $db = db_open();
    
    // Get the list of allowed file types
    $stmt = $db->prepare("SELECT `name` FROM `file_types`");
    $stmt->execute();
    $file_types = $stmt->fetchAll();

    // Get the list of allowed file extensions
    $stmt = $db->prepare("SELECT `name` FROM `file_type_extensions`");
    $stmt->execute();
    $file_type_extensions = $stmt->fetchAll();

    // Create an array of allowed types
    foreach ($file_types as $key => $row)
    {
        $allowed_types[] = $row['name'];
    }

    // Create an array of allowed extensions
    foreach ($file_type_extensions as $key => $row)
    {
        $allowed_extensions[] = $row['name'];
    }
    
    $errors = array();

    $file_ids = [];

    foreach($files['name'] as $key => $name){
        if(!$name)
            continue;
            
        $file = array(
            'name' => $files['name'][$key],
            'type' => $files['type'][$key],
            'tmp_name' => $files['tmp_name'][$key],
            'size' => $files['size'][$key],
            'error' => $files['error'][$key],
        );
        
        if (strlen($file['name']) <= 100) {
        
            // If the file type is appropriate
            if (in_array($file['type'], $allowed_types))
            {
                // If the file extension is appropriate
                if (in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), array_map('strtolower', $allowed_extensions)))
                {
                // Get the maximum upload file size
                $max_upload_size = get_setting("max_upload_size");
    
                // If the file size is less than max size
                if ($file['size'] < $max_upload_size)
                {
                    // If there was no error with the upload
                    if ($file['error'] == 0)
                    {
                        // Read the file
                        $content = fopen($file['tmp_name'], 'rb');
    
                        // Create a unique file name
                        $unique_name = generate_token(30);
    
                        // Store the file in the database
                        $stmt = $db->prepare("INSERT compliance_files (ref_id, ref_type, name, unique_name, type, size, user, content, version) VALUES (:ref_id, :ref_type, :name, :unique_name, :type, :size, :user, :content, :version)");
                        $stmt->bindParam(":ref_id", $test_audit_id, PDO::PARAM_INT);
                        $stmt->bindParam(":ref_type", $ref_type, PDO::PARAM_STR);
                        $stmt->bindParam(":name", $file['name'], PDO::PARAM_STR, 30);
                        $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                        $stmt->bindParam(":type", $file['type'], PDO::PARAM_STR, 30);
                        $stmt->bindParam(":size", $file['size'], PDO::PARAM_INT);
                        $stmt->bindParam(":user", $user, PDO::PARAM_INT);
                        $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
                        $stmt->bindParam(":version", $version, PDO::PARAM_INT);
                        $stmt->execute();
                        
                        $file_ids[] = $db->lastInsertId();
                    }
                    // Otherwise
                    else
                    {
                        switch ($file['error'])
                        {
                            case 1:
                                $errors[] = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                                break;
                            case 2:
                                $errors[] = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                                break;
                            case 3:
                                $errors[] = "The uploaded file was only partially uploaded.";
                                break;
                            case 4:
//                                $errors[] = "No file was uploaded.";
                                break;
                            case 6:
                                $errors[] = "Missing a temporary folder.";
                                break;
                            case 7:
                                $errors[] = "Failed to write file to disk.";
                                break;
                            case 8:
                                $errors[] = "A PHP extension stopped the file upload.";
                                break;
                            default:
                                $errors[] = "There was an error with the file upload.";
                        }
                    }
                }
                else $errors[] = "The uploaded file was too big to store in the database.  A SimpleRisk administrator can modify the maximum file upload size under \"File Upload Settings\" under the \"Configure\" menu.  You may also need to modify the 'upload_max_filesize' and 'post_max_size' values in your php.ini file.";
                }
                else $errors[] = "The file extension of the uploaded file (" . pathinfo($file['name'], PATHINFO_EXTENSION) . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
            }
            else $errors[] = "The file type of the uploaded file (" . $file['type'] . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
        } else $errors[] = "The uploaded file name is longer than the allowed maximum (100 characters).";
    }

    // Close the database connection
    db_close($db);
    
    if($errors){
        return [false, [], $errors];
    }else{
        return [true, $file_ids, []];
    }
}
/*************************************
 * FUNCTION: UPLOAD EXCEPTION FILES *
 *************************************/
function upload_exception_files($test_audit_id, $ref_type, $files, $version=1)
{
    $user = $_SESSION['uid'];
    
    // Open the database connection
    $db = db_open();
    
    // Get the list of allowed file types
    $stmt = $db->prepare("SELECT `name` FROM `file_types`");
    $stmt->execute();
    $file_types = $stmt->fetchAll();

    // Get the list of allowed file extensions
    $stmt = $db->prepare("SELECT `name` FROM `file_type_extensions`");
    $stmt->execute();
    $file_type_extensions = $stmt->fetchAll();

    // Create an array of allowed types
    foreach ($file_types as $key => $row)
    {
        $allowed_types[] = $row['name'];
    }

    // Create an array of allowed extensions
    foreach ($file_type_extensions as $key => $row)
    {
        $allowed_extensions[] = $row['name'];
    }
    
    $errors = array();

    $file_ids = [];

    foreach($files['name'] as $key => $name){
        if(!$name)
            continue;
            
        $file = array(
            'name' => $files['name'][$key],
            'type' => $files['type'][$key],
            'tmp_name' => $files['tmp_name'][$key],
            'size' => $files['size'][$key],
            'error' => $files['error'][$key],
        );
        
        if (strlen($file['name']) <= 100) {
        
            // If the file type is appropriate
            if (in_array($file['type'], $allowed_types))
            {
                // If the file extension is appropriate
                if (in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), array_map('strtolower', $allowed_extensions)))
                {
                // Get the maximum upload file size
                $max_upload_size = get_setting("max_upload_size");
    
                // If the file size is less than max size
                if ($file['size'] < $max_upload_size)
                {
                    // If there was no error with the upload
                    if ($file['error'] == 0)
                    {
                        // Read the file
                        $content = fopen($file['tmp_name'], 'rb');
    
                        // Create a unique file name
                        $unique_name = generate_token(30);
    
                        // Store the file in the database
                        $stmt = $db->prepare("INSERT compliance_files (ref_id, ref_type, name, unique_name, type, size, user, content, version) VALUES (:ref_id, :ref_type, :name, :unique_name, :type, :size, :user, :content, :version)");
                        $stmt->bindParam(":ref_id", $test_audit_id, PDO::PARAM_INT);
                        $stmt->bindParam(":ref_type", $ref_type, PDO::PARAM_STR);
                        $stmt->bindParam(":name", $file['name'], PDO::PARAM_STR, 30);
                        $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                        $stmt->bindParam(":type", $file['type'], PDO::PARAM_STR, 30);
                        $stmt->bindParam(":size", $file['size'], PDO::PARAM_INT);
                        $stmt->bindParam(":user", $user, PDO::PARAM_INT);
                        $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
                        $stmt->bindParam(":version", $version, PDO::PARAM_INT);
                        $stmt->execute();
                        
                        $file_ids[] = $db->lastInsertId();
                    }
                    // Otherwise
                    else
                    {
                        switch ($file['error'])
                        {
                            case 1:
                                $errors[] = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                                break;
                            case 2:
                                $errors[] = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                                break;
                            case 3:
                                $errors[] = "The uploaded file was only partially uploaded.";
                                break;
                            case 4:
//                                $errors[] = "No file was uploaded.";
                                break;
                            case 6:
                                $errors[] = "Missing a temporary folder.";
                                break;
                            case 7:
                                $errors[] = "Failed to write file to disk.";
                                break;
                            case 8:
                                $errors[] = "A PHP extension stopped the file upload.";
                                break;
                            default:
                                $errors[] = "There was an error with the file upload.";
                        }
                    }
                }
                else $errors[] = "The uploaded file was too big to store in the database.  A SimpleRisk administrator can modify the maximum file upload size under \"File Upload Settings\" under the \"Configure\" menu.  You may also need to modify the 'upload_max_filesize' and 'post_max_size' values in your php.ini file.";
                }
                else $errors[] = "The file extension of the uploaded file (" . pathinfo($file['name'], PATHINFO_EXTENSION) . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
            }
            else $errors[] = "The file type of the uploaded file (" . $file['type'] . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
        } else $errors[] = "The uploaded file name is longer than the allowed maximum (100 characters).";
    }

    // Close the database connection
    db_close($db);
    
    if($errors){
        return [false, [], $errors];
    }else{
        return [true, $file_ids, []];
    }
}

/****************************
 * FUNCTION: GET USER TEAMS *
 ****************************/
function get_user_teams($user_id) {


    // Query the database
    if (!is_admin($user_id) && organizational_hierarchy_extra()) {
        // If the Organizational Hierarchy is activated only those teams should be returned that the user is assigned to
        // AND in the user's selected business unit. Unless it's an Admin user. Admins can see eerything.
        require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
        
        return get_teams_of_user_from_selected_business_unit($user_id, false);
    }
        
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("
        SELECT
            distinct `t`.`value`
        FROM
            `user` u
            LEFT JOIN `user_to_team` u2t ON `u2t`.`user_id` = `u`.`value`
            LEFT JOIN `team` t ON `u2t`.`team_id` = `t`.`value` OR `u`.`admin` = 1
        WHERE
            `u`.`value` = :user_id
            AND `t`.`value` IS NOT NULL;
    ");

    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $teams = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Close the database connection
    db_close($db);

    return $teams;
}

/***************************
 * FUNCTION: GET ALL USERS *
 ***************************/
function get_all_users()
{
	// Open the database connection
	$db = db_open();

	// Get the list of all users
	$stmt = $db->prepare("
		SELECT
			`value`, `username`
		FROM
			`user`;
	");
	$stmt->execute();
	$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Close the database connection
	db_close($db);

	// Return the list of all users
	return $all_users;
}

/*******************************
 * FUNCTION: GET USERS IN TEAM *
 *******************************/
function get_users_of_team($team_id) {
    // Commented implementation as the function is not used yet
    // and it wouldn't work properly with the Organizational Hierarchy extra
    
    /*// Open the database connection
    $db = db_open();

    // Get the user information
    $stmt = $db->prepare("
        SELECT
            `u`.`value`
        FROM
            `user` u
            INNER JOIN `user_to_team` u2t ON `u2t`.`user_id` = `u`.`value`
        WHERE
            `u`.`admin` = 1 OR `u2t`.`team_id`=:team_id
        GROUP BY 
            `u`.`value`;
    ");
    $stmt->bindParam(":team_id", $team_id, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Close the database connection
    db_close($db);

    return $users;*/
}

/*******************************
 * FUNCTION: SET USERS OF TEAM *
 *******************************/
function set_users_of_team($team_id, $user_ids) {
    // TODO Implement this function when working on the feature where we can update the users of the team
    // save_junction_values('user_to_team', 'team_id', $team_id, 'user_id', $user_ids);
}

/**************************************************************
 * FUNCTION: UPDATE USER TEAMS                                *
 * Updates the teams of a user without updating anything else *
 **************************************************************/
function set_teams_of_user($user_id, $team_ids) {

    $current_teams = get_user_teams($user_id);
    // Make sure we're dealing with arrays, even if they're empty
    if (!$current_teams) {
        $current_teams = [];
    }
    if (!$team_ids) {
        $team_ids = [];
    }

    if (save_junction_values('user_to_team', 'user_id', $user_id, 'team_id', $team_ids)) {

        // Calculate what teams are removed from the item and what teams are added
        $teams_to_remove = array_diff($current_teams, $team_ids);
        $teams_to_add = array_diff($team_ids, $current_teams);

        // No audit logging is needed if nothing changed
        if ($teams_to_add || $teams_to_remove) {

            $team_changes = [];
            if ($teams_to_add)
                $team_changes[] = _lang('TeamUpdateAuditLogAdded', array('teams_added' => implode(", ", get_names_by_multi_values('team', $teams_to_add, true))), false);
            if ($teams_to_remove)
                $team_changes[] = _lang('TeamUpdateAuditLogRemoved', array('teams_removed' => implode(", ", get_names_by_multi_values('team', $teams_to_remove, true))), false);

            $message = _lang('UserTeamUpdateAuditLog', array(
                    'user' => isset($_SESSION['user']) ? $_SESSION['user'] : 'admin', // In case of new users created by the custom authentication logic a user can be created before it has a session
                    'username' => get_name_by_value("user", $user_id),
                    'teams_from' => implode(", ", get_names_by_multi_values('team', $current_teams, true)),
                    'teams_to' => implode(", ", get_names_by_multi_values('team', $team_ids, true)),
                    'team_changes' => implode(", ", $team_changes)
                ), false
            );

            write_log((int)$user_id + 1000, isset($_SESSION['uid']) ? $_SESSION['uid'] : 0, $message, 'user');
        }
    }
}

/*********************************************
 * FUNCTION: ADD USER TO TEAMS               *
 * Adding user to multiple teams.            *
 * First parameter is a user id,             *
 * second parameter is an array of team ids. *
 *********************************************/
function add_user_to_teams($user_id, $team_ids) {
    // Commented implementation as the function is not used yet
    // and it wouldn't work properly with the Organizational Hierarchy extra
    /*set_teams_of_user($user_id,
        array_unique( // to remove duplicates
            array_merge( // to merge the existing and new teams
                get_user_teams($user_id),
                $team_ids
            )
        )
    );*/
}

/*********************************************
 * FUNCTION: REMOVE USER FROM TEAMS          *
 * Removing user from multiple teams.        *
 * First parameter is a user id,             *
 * second parameter is an array of team ids. *
 *********************************************/
function remove_user_from_teams($user_id, $team_ids) {
    // Commented implementation as the function is not used yet
    // and it wouldn't work properly with the Organizational Hierarchy extra
    /*set_teams_of_user($user_id,
        array_diff( // to remove the teams from the existing
            get_user_teams($user_id),
            $team_ids
        )
    );*/
}

/************************************************
 * FUNCTION: ADD USERS TO TEAM                  *
 * Adding multiple users to a team.             *
 * First parameter is the id of the team,       *
 * second parameter is an array of user ids.    *
 ************************************************/
function add_users_to_team($team_id, $user_ids) {
    // Commented implementation as the function is not used yet
    // and it wouldn't work properly with the Organizational Hierarchy extra
    /*set_users_of_team($team_id,
        array_unique( // to remove duplicates
            array_merge( // to merge the existing and new teams
                get_users_of_team($team_id),
                $user_ids
            )
        )
    );*/
}

/************************************************
 * FUNCTION: REMOVE USERS FROM TEAM             *
 * Removing multiple users from a team.         *
 * First parameter is the id of the team,       *
 * second parameter is an array of user ids.    *
 ************************************************/
function remove_users_from_team($team_id, $user_ids) {
    // Commented implementation as the function is not used yet
    // and it wouldn't work properly with the Organizational Hierarchy extra
    /*set_users_of_team($team_id,
        array_diff( // to remove the teams from the existing
            get_users_of_team($team_id),
            $user_ids
        )
    );*/
}

/************************************************************
 * FUNCTION: GET ALL TEAM VALUES                            *
 * It gets ALL TEAM VALUES regardless of Business Units.    *
 ************************************************************/
function get_all_team_values()
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT `value` FROM `team` ORDER BY `value`;");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Close the database connection
    db_close($db);

    // Return the list of teams 
    return $teams;
}

/****************************************************
 * FUNCTION: GET ALL TEAMS                          *
 * It gets ALL TEAMS regardless of Business Units.  *
 ****************************************************/
function get_all_teams() {
    // Open the database connection
    $db = db_open();
    
    // Query the database
    $stmt = $db->prepare("SELECT * FROM `team` ORDER BY `name`;");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    // Return the list of teams
    return $teams;
}

/************************************************
 * FUNCTION: GENERATE QUERY FOR TEAMS CONDITION *
 ************************************************/
function generate_teams_query($teams, $field_name, $check_unassign=true) {
    if($teams !== false) {
        if(!$teams) {
            $teams_query = " 0 ";
        } else {
            if (is_array($teams)) {
                $teams_list = implode(',', $teams);
                $teams_array = $teams;
            } else {
                $teams_list = $teams;
                $teams_array = explode(',', $teams);
            }

            $teams_query = " FIND_IN_SET({$field_name}, '{$teams_list}') ";
            if($check_unassign && in_array("0", $teams_array)) {
                $teams_query .= " OR {$field_name} IS NULL ";
            }
            $teams_query = " ({$teams_query}) ";
        }
    } else {
        $teams_query = " 1 ";
    }

    return $teams_query;
}

/***********************************
 * FUNCTION: GET TEAM QUERY STRING *
 ***********************************/
 /*
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
                $string .= "FIND_IN_SET('{$team}', {$rename}.team) OR " ;
            }
            // Otherwise append the team to the string
            else $string .= "FIND_IN_SET('{$team}', team) OR " ;
        }
    }
    
    $string .= " 0 ";
    
    // Return the string
    return $string;
}
*/

/**************************************
 * FUNCTION: UPDATE IMPACT LIKELIHOOD *
 **************************************/
function update_impact_likelihood( $old_impact_value, $new_impact_value, $old_likelihood_value, $new_likelihood_value )
{   
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();
    
    $impact_value = $new_impact_value * ($new_impact_value / $old_impact_value);
    $stmt = $db->prepare("UPDATE `risk_scoring` SET `CLASSIC_impact` = ROUND(CLASSIC_impact * (:new_impact_value / :old_impact_value)) , `CLASSIC_likelihood` = ROUND(CLASSIC_likelihood * (:new_likelihood_value / :old_likelihood_value));");
    $stmt->bindParam(":old_impact_value", $old_impact_value, PDO::PARAM_INT);
    $stmt->bindParam(":new_impact_value", $new_impact_value, PDO::PARAM_INT);
    $stmt->bindParam(":old_likelihood_value", $old_likelihood_value, PDO::PARAM_INT);
    $stmt->bindParam(":new_likelihood_value", $new_likelihood_value, PDO::PARAM_INT);
    
    $stmt->execute();

    // Close the database connection
    db_close($db);
    
    return true;
}

/******************************
 * FUNCTION: RESTRICTED EXTRA *
 ******************************/
function restricted_extra($extra_name)
{
    // Get the hosting tier setting
    $hosting_tier = get_setting('hosting_tier');

    // If the hosting tier is not set
    if (!$hosting_tier)
    {
        // Return false
        return false;
    }
    // Otherwise, the tier is set
    else
    {
        switch ($hosting_tier)
        {
            case 'internal':
                return internal_extra($extra_name);
            case 'trial':
                return trial_extra($extra_name);
            case 'small':
                return small_extra($extra_name);
            case 'medium':
                return medium_extra($extra_name);
            case 'large':
                return large_extra($extra_name);
            case 'reseller':
		return large_extra($extra_name);
            default:
                return true;
        }
    }
}

/****************************
 * FUNCTION: INTERNAL EXTRA *
 ****************************/
function internal_extra($extra_name)
{
	// Allow all Extras for internal
	return false;
}

/*************************
 * FUNCTION: TRIAL EXTRA *
 *************************/
function trial_extra($extra_name)
{
	// Allow all Extras for trials
	return false;
}

/*************************
 * FUNCTION: SMALL EXTRA *
 *************************/
function small_extra($extra_name)
{
    // Check the Extra permission
    switch($extra_name)
    {
        case 'advanced_search':
            // Don't Allow
            return true;
        case 'api':
            // Don't Allow
            return true;
        case 'complianceforgescf':
            // Allow
            return false;
        case 'customauth':
            // Don't Allow
            return true;
        case 'customization':
            // Don't Allow
            return true;
        case 'encryption':
            // Don't Allow
            return true;
        case 'importexport':
            // Allow
            return false;
        case 'incident_management':
            // Don't Allow
            return true;
        case 'jira':
            // Don't Allow
            return true;
        case 'notification':
            // Allow
            return false;
        case 'organizational_hierarchy':
            // Don't Allow
            return true;
        case 'riskassessment':
            // Allow
            return false;
        case 'separation':
            // Don't Allow
            return true;
    }
}

/**************************
 * FUNCTION: MEDIUM EXTRA *
 **************************/
function medium_extra($extra_name)
{
    // Check the Extra permission
    switch($extra_name)
    {
        case 'advanced_search':
            // Don't Allow
            return true;
        case 'api':
            // Don't Allow
            return true;
        case 'complianceforgescf':
            // Allow
            return false;
        case 'customauth':
            // Don't Allow
            return true;
        case 'customization':
            // Don't Allow
            return true;
        case 'encryption':
            // Don't Allow
            return true;
        case 'importexport':
            // Allow
            return false;
        case 'incident_management':
            // Don't Allow
            return true;
        case 'jira':
            // Don't Allow
            return true;
        case 'notification':
            // Allow
            return false;
        case 'organizational_hierarchy':
            // Don't Allow
            return true;
        case 'riskassessment':
            // Allow
            return false;
        case 'separation':
            // Allow
            return false;
    }
}

/*************************
 * FUNCTION: LARGE EXTRA *
 *************************/
function large_extra($extra_name)
{
    // Check the Extra permission
    switch($extra_name)
    {
        case 'advanced_search':
            // Allow
            return false;
        case 'api':
            // Allow
            return false;
        case 'complianceforgescf':
            // Allow
            return false;
        case 'customauth':
            // Allow
            return false;
        case 'customization':
            // Allow
            return false;
        case 'encryption':
            // Allow
            return false;
        case 'importexport':
            // Allow
            return false;
        case 'incident_management':
            // Don't Allow
            return true;
        case 'jira':
            // Allow
            return false;
        case 'organizational_hierarchy':
            // Don't Allow
            return true;
        case 'notification':
            // Allow
            return false;
        case 'riskassessment':
            // Allow
            return false;
        case 'separation':
            // Allow
            return false;
	case 'vulnmgmt':
	    // Allow
	    return false;
    }
}

/***************************
 * FUNCTION: ADD FILE TYPE *
 ***************************/
function add_file_type($name, $extension)
{
    // If no name was provided
    if (!$name || $name == "")
    {
        // Display an alert
        set_alert(false, "bad", "Please provide a valid file type name.");

        // Return false
        return false;
    }

    // If no extension was provided
    if (!$extension || $extension == "")
    {
        // Display an alert
        set_alert(false, "bad", "Please provide a valid file extension.");

        // Return false
        return false;
    }

    // Open the database connection
    $db = db_open();

    // Insert the new file type
    $stmt = $db->prepare("INSERT INTO `file_types` (`name`) VALUES (:name) ON DUPLICATE KEY UPDATE `name` = :name;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 250);
    $stmt->execute();

    // Insert the new file type extension
    $stmt = $db->prepare("INSERT INTO `file_type_extensions` (`name`) VALUES (:extension) ON DUPLICATE KEY UPDATE `name` = :extension;");
    $stmt->bindParam(":extension", $extension, PDO::PARAM_STR, 10);
    $stmt->execute();

    // Write an audit log entry
    $risk_id = 1000;
    $message = "A new upload file type of \"" . $name . "\" for extension \"" . $extension . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);
    
    // Close the database connection
    db_close($db);

    // Return true
    return true;
}

/************************************
 * FUNCTION: SAVE CONTRIBUTING RISK *
 ************************************/
function add_contributing_risk($subject, $weight)
{
    // Open the database connection
    $db = db_open();

    // Insert the new file type
    $stmt = $db->prepare("INSERT INTO `contributing_risks` (`subject`, `weight`) VALUES(:subject, :weight); ");
    $stmt->bindParam(":subject", $subject, PDO::PARAM_STR);
    $stmt->bindParam(":weight", $weight);
    $stmt->execute();
    $contributing_risks_id = $db->lastInsertId();

    $impacts = get_table("impact");
    foreach($impacts as $key=>$impact){
        $value = $key + 1;
        $stmt = $db->prepare("INSERT INTO `contributing_risks_impact` (`contributing_risks_id`, `value`, `name`) VALUES (:contributing_risks_id, :value, :name);");
        $stmt->bindParam(":contributing_risks_id", $contributing_risks_id);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->bindParam(":name", $impact['name'], PDO::PARAM_STR);
        $stmt->execute();

    }

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: SAVE CONTRIBUTING RISK *
 ************************************/
function update_contributing_risk($id, $subject, $weight)
{
    // Open the database connection
    $db = db_open();

    // Insert the new file type
    $stmt = $db->prepare("UPDATE `contributing_risks` SET `subject`=:subject, `weight`=:weight WHERE id=:id; ");
    $stmt->bindParam(":subject", $subject, PDO::PARAM_STR);
    $stmt->bindParam(":weight", $weight);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*************************************
 * FUNCTION: SAVE CONTRIBUTING RISKS *
 *************************************/
function save_contributing_risks($subjects, $weights, $existing_subjects=[], $existing_weights=[])
{
    global $lang, $escaper;

    $weight_sum = 0;
    foreach($weights as $weight){
        $weight_sum += $weight;
    }
    
    foreach($existing_weights as $existing_weight){
        $weight_sum += $existing_weight;
    }
    // If total weight isn't equal to 1
    if (abs($weight_sum - 1) >= 0.0001)
    {
        // Display an alert
        set_alert(false, "bad", $escaper->escapeHtml($lang['TotalContributingWeightsShouldBe1']));

        // Return false
        return false;
    }

    // Update existing contributing risks
    foreach($existing_weights as $id => $existing_weight){
        // Save contributing risk
        update_contributing_risk($id, $existing_subjects[$id], $existing_weights[$id]);
    }
    
    // Delete contributing risks
    $existing_ids = array_keys($existing_weights);
    // Open the database connection
    $db = db_open();
    // Delete contributing risks not inlcuding existing ids
    $stmt = $db->prepare("DELETE FROM `contributing_risks` WHERE FIND_IN_SET(id, :existing_ids) = 0; ");
    $existing_ids_string = implode(",", $existing_ids);
    $stmt->bindParam(":existing_ids", $existing_ids_string, PDO::PARAM_STR);
    $stmt->execute();

    // Delete contributing risks impact not inlcuding existing ids
    $stmt = $db->prepare("DELETE FROM `contributing_risks_impact` WHERE FIND_IN_SET(contributing_risks_id, :existing_ids) = 0; ");
    $existing_ids_string = implode(",", $existing_ids);
    $stmt->bindParam(":existing_ids", $existing_ids_string, PDO::PARAM_STR);
    $stmt->execute();
    // Close the database connection
    db_close($db);
    
    // Create new contributing risks
    foreach($weights as $key => $weight){
        // Add contributing risk
        add_contributing_risk($subjects[$key], $weights[$key]);
    }

    // Return true
    return true;
}

/*************************************
 * FUNCTION: GET CONTRIBUTING RISKS *
 *************************************/
function get_contributing_risks()
{
    global $lang, $escaper;
    
    // Open the database connection
    $db = db_open();

    // Order by name
    $stmt = $db->prepare("SELECT * FROM `contributing_risks`; ");

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}

/*************************************************************
 * FUNCTION: GET CONTRIBUTING WEIGHT BY CONTRIBUTING RISK ID *
 *************************************************************/
function get_contributing_weight_by_id($id)
{
    if(empty($GLOBALS['contributing_risks'])){
        $GLOBALS['contributing_risks'] = get_contributing_risks();
    }
    foreach($GLOBALS['contributing_risks'] as $contributing_risk){
        if($contributing_risk['id'] == $id){
            return $contributing_risk['weight'];
        }
    }
    return false;
}

/**************************************************************
 * FUNCTION: GET CONTRIBUTING ID BY CONTRIBUTING RISK SUBJECT *
 **************************************************************/
function get_contributing_id_by_subject($subject)
{
    if(empty($GLOBALS['contributing_risks'])){
        $GLOBALS['contributing_risks'] = get_contributing_risks();
    }
    foreach($GLOBALS['contributing_risks'] as $contributing_risk){
        if($contributing_risk['subject'] == $subject){
            return $contributing_risk['id'];
        }
    }
    return false;
}

/********************************************************************************
 * FUNCTION: GET CONTRIBUTING RISKS - MAX CONTRIBUTING IMPACT MAP               *
 * Get the array of all the contributing risk ids and their maximum associated  *
 * contributing impact values to be used as default when importing              *
 *******************************************************************************/
function get_contributing_risks_max_contributing_impact_map() {

    // Return the saved mapping to make sure it's only created once/request
    if(!empty($GLOBALS['contributing_risks_max_contributing_impact_map'])){
        return $GLOBALS['contributing_risks_max_contributing_impact_map'];
    }

    //Create the mapping if it wasn't created yet

    $db = db_open();
    $ContributingImpacts = [];

    // Iterate through all the contributing risks
    foreach(get_contributing_risks() as $contributing_risk){

        $contributing_risk_id = $contributing_risk['id'];

        // get the maximum impact value associated to that contributing risk
        $stmt = $db->prepare("SELECT MAX(`value`) FROM `contributing_risks_impact` WHERE `contributing_risks_id` = :contributing_risks_id;");
        $stmt->bindParam(":contributing_risks_id", $contributing_risk_id, PDO::PARAM_INT);
        $stmt->execute();
        $impact = $stmt->fetch(PDO::FETCH_COLUMN, 0);

        // Store the Contributing risk id -> Contributing impact
        $ContributingImpacts[$contributing_risk_id] = $impact;
    }
    db_close($db);

    // Store the mapping to make sure it's only built once/request
    $GLOBALS['contributing_risks_max_contributing_impact_map'] = $ContributingImpacts;
    return $GLOBALS['contributing_risks_max_contributing_impact_map'];
}

/*******************************************************************************
 * FUNCTION: GET CONTRIBUTING IMPACTS BY CONTRIBUTING SUBJECT AND IMPACT NAMES *
 *******************************************************************************/
function get_contributing_impacts_by_subjectimpact_names($subject_impact_names) {

    // Open the database connection
    $db = db_open();

    // Set initial value to Contributing Impacts(List of all the Contributing Risks and their max Contributing Impact values)
    $ContributingImpacts = get_contributing_risks_max_contributing_impact_map();

    // if subject and impact names is emtpty, return the default max values
    if(!$subject_impact_names) {
        return $ContributingImpacts;
    }

    $subject_impact_names_arr = explode(",", $subject_impact_names);
    foreach($subject_impact_names_arr as $subject_impact_name){
        list($subject, $impact_name) = explode("_", $subject_impact_name);
        $contributing_risk_id = get_contributing_id_by_subject($subject);

        // If it's not an existing contributing risk, then skip it
        if (!$contributing_risk_id) {
            continue;
        }

        // get contributing impact
        $stmt = $db->prepare("SELECT `value` FROM `contributing_risks_impact` WHERE contributing_risks_id = :contributing_risks_id  AND name = :name;");
        $stmt->bindParam(":contributing_risks_id", $contributing_risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $impact_name, PDO::PARAM_STR);
        $stmt->execute();

        $impact = $stmt->fetch(PDO::FETCH_COLUMN, 0);

        // If it does not exist, then use the default(the contributing impact for that contributing risk with the highest value)
        if(!$impact) {
            continue;
        }

        $ContributingImpacts[$contributing_risk_id] = $impact;
    }

    // Close the database connection
    db_close($db);

    return $ContributingImpacts;
}

/*******************************************************************************
 * FUNCTION: GET CONTRIBUTING IMPACTS BY CONTRIBUTING SUBJECT AND IMPACT VALUES *
 *******************************************************************************/
function get_contributing_impacts_by_subjectimpact_values($subject_impact_values)
{
    if($subject_impact_values)
    {
        $contributing_risks_impact_arr = explode(",", $subject_impact_values);
        $ContributingImpacts = [];
        foreach($contributing_risks_impact_arr as $contributing_riskid_and_impact){
            // $contributing_riskid_and_impact has no spliter "_"
            if(strpos($contributing_riskid_and_impact, "_") === false)
            {
                continue;
            }
            // $contributing_riskid_and_impact has spliter "_", set $ContributingImpacts array
            else{
                list($contributing_id, $impact) = explode("_", $contributing_riskid_and_impact);
                $ContributingImpacts[$contributing_id] = $impact;
            }
        }
    }
    else
    {
        $ContributingImpacts = [];
    }
    return $ContributingImpacts;
}

/**********************************************************************************
 * FUNCTION: GET CONTRIBUTING IMPACTS BY KEY FROM MULTI CONTRIBUTING RISK IMPACTS *
 **********************************************************************************/
function get_contributing_impacts_by_key_from_multi($AllContributingImpacts, $key)
{
    $ContributingImpacts = [];
    if (!empty($AllContributingImpacts)) {
        foreach($AllContributingImpacts as $contributing_risk_id => $AllContributingImpact){
            $ContributingImpacts[$contributing_risk_id] = $AllContributingImpact[$key];
        }
    }
    return $ContributingImpacts;
}

/*************************************************************************************
 * FUNCTION: GET LOCALIZED YES/NO BASED ON THE BOOL/INT VALUE PASSED TO THE FUNCTION *
 *************************************************************************************/
function localized_yes_no($val)
{
    global $lang;
    return boolval($val) ? $lang['Yes'] : $lang['No'];
}

/**************************
 * FUNCTION: TABLE EXISTS *
 **************************/
if (!function_exists('table_exists')) {
    function table_exists($table) {

        // Open the database connection
        $db = db_open();

        // Query the schema for the table
        $database = DB_DATABASE; //Have to make a variable as bindParam can't take parameter by reference
        $stmt = $db->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = :database AND table_name = :table;");
        $stmt->bindParam(":database", $database, PDO::PARAM_STR);
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the results
        $results = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return count($results) > 0;
    }
}

/***********************************
 * FUNCTION: FIELD EXISTS IN TABLE *
 ***********************************/
if (!function_exists('field_exists_in_table')) {
    function field_exists_in_table($field, $table) {

        // Open the database connection
        $db = db_open();

        // Query the field of the table
        $stmt = $db->prepare("
            SELECT
                1
            FROM
                information_schema.columns
            WHERE
                table_schema = :database
                AND table_name = :table
                AND column_name = :field;
        ");
        $database = DB_DATABASE; //Have to make a variable as bindParam can't take parameter by reference
        $stmt->bindParam(":database", $database, PDO::PARAM_STR);
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->bindParam(":field", $field, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the results
        $results = $stmt->rowCount();

        // Close the database connection
        db_close($db);

        return $results;
    }
}

/***********************************
 * FUNCTION: INDEX EXISTS ON TABLE *
 ***********************************/
if (!function_exists('index_exists_on_table')) {

    function index_exists_on_table($index_name, $table) {

        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("SHOW INDEX FROM `{$table}` WHERE `Key_name` = '{$index_name}';");
        $stmt->execute();

        // Fetch the results
        $results = $stmt->rowCount();

        // Close the database connection
        db_close($db);

        return $results;
    }
}

/*********************************************
 * FUNCTION: CHECK UPLOADED FILE SIZE ERRORS *
 ********************************************/
function checkUploadedFileSizeErrors() {
    global $lang, $escaper;

    // This check is here because if the user uploads a file that's size exceeds the `post_max_size` defined in the
    // php.ini then it'll wipe out the contents of the $_POST and cause a CSRF validation failure.
    // In this case we'll just simply refresh the page and display an error message.
    if (isset($_SERVER['REQUEST_METHOD'])&& $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['CONTENT_LENGTH']) && empty($_POST)) {

        $maxPostSize = trim(ini_get('post_max_size'));
        if ($maxPostSize != '') {
            $last = strtolower(
                $maxPostSize[strlen($maxPostSize) - 1]
            );
        } else {
            $last = '';
        }

        $maxPostSize = (int)$maxPostSize;
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $maxPostSize *= 1024;
                // fall through
            case 'm':
                $maxPostSize *= 1024;
                // fall through
            case 'k':
                $maxPostSize *= 1024;
                // fall through
        }

        if ($_SERVER['CONTENT_LENGTH'] > $maxPostSize) {
            set_alert(true, "bad", $lang['UploadingFileTooBig']);
            refresh();
        }
    }
}

/******************************************
 * FUNCTION: GET PHP EXECUTABLE FROM PATH *
 * Only works if the executable is on the *
 * path.                                  *
 ******************************************/
function getPHPExecutableFromPath() {
  $paths = explode(PATH_SEPARATOR, getenv('PATH'));
  foreach ($paths as $path) {
    // we need this for XAMPP (Windows)
    if (strstr($path, 'php.exe') && isset($_SERVER["WINDIR"]) && file_exists($path) && is_file($path)) {
        return $path;
    }
    else {
        $php_executable = $path . DIRECTORY_SEPARATOR . "php" . (isset($_SERVER["WINDIR"]) ? ".exe" : "");
        if (file_exists($php_executable) && is_file($php_executable)) {
           return $php_executable;
        }
    }
  }
  return FALSE; // not found
}

/*************************
 * FUNCTION: HASH EQUALS *
 *************************/
// This function does not exist in PHP < 5.6 so we define it here
if (!function_exists('hash_equals')) {

    /**
     * Timing attack safe string comparison
     * 
     * Compares two strings using the same time whether they're equal or not.
     * This function should be used to mitigate timing attacks; for instance, when testing crypt() password hashes.
     * 
     * @param string $known_string The string of known length to compare against
     * @param string $user_string The user-supplied string
     * @return boolean Returns TRUE when the two strings are equal, FALSE otherwise.
     */
    function hash_equals($known_string, $user_string)
    {
        if (func_num_args() !== 2) {
            // handle wrong parameter count as the native implentation
            trigger_error('hash_equals() expects exactly 2 parameters, ' . func_num_args() . ' given', E_USER_WARNING);
            return null;
        }
        if (is_string($known_string) !== true) {
            trigger_error('hash_equals(): Expected known_string to be a string, ' . gettype($known_string) . ' given', E_USER_WARNING);
            return false;
        }
        $known_string_len = strlen($known_string);
        $user_string_type_error = 'hash_equals(): Expected user_string to be a string, ' . gettype($user_string) . ' given'; // prepare wrong type error message now to reduce the impact of string concatenation and the gettype call
        if (is_string($user_string) !== true) {
            trigger_error($user_string_type_error, E_USER_WARNING);
            // prevention of timing attacks might be still possible if we handle $user_string as a string of diffent length (the trigger_error() call increases the execution time a bit)
            $user_string_len = strlen($user_string);
            $user_string_len = $known_string_len + 1;
        } else {
            $user_string_len = $known_string_len + 1;
            $user_string_len = strlen($user_string);
        }
        if ($known_string_len !== $user_string_len) {
            $res = $known_string ^ $known_string; // use $known_string instead of $user_string to handle strings of diffrent length.
            $ret = 1; // set $ret to 1 to make sure false is returned
        } else {
            $res = $known_string ^ $user_string;
            $ret = 0;
        }
        for ($i = strlen($res) - 1; $i >= 0; $i--) {
            $ret |= ord($res[$i]);
        }
        return $ret === 0;
    }

}

/*****************************************************************************
 * FUNCTION: PREVENT EXTRA DOUBLE SUBMIT                                     *
 * This function won't let the enable logic of the extra run                 *
 * when it's already enabled or the disable logic when it's already disabled *
 * $extra       = The name of the extra                                      *
 * $is_enable   = Whether the function is called from the extra's enable     *
 *****************************************************************************/
function prevent_extra_double_submit($extra, $is_enable) {

    global $lang;

    /*
        The encryption_extra() == $is_enable part might need some explanation:
        We only have to interrupt if
            - the extra is turned on and it's the enable function
            - the extra is turned off and it's the disable function
        thus it makes sense to compare the two and interrupt when they're equal.

        extra | enable | interrupt
        --------------------------
          1   |    1   |    1
          1   |    0   |    0
          0   |    1   |    0
          0   |    0   |    1
    */
    $interrupt =
        ($extra == "encryption" && (encryption_extra() == $is_enable)) ||
        ($extra == "custom_authentication" && (custom_authentication_extra() == $is_enable)) ||
        ($extra == "customization" && (customization_extra() == $is_enable)) ||
        ($extra == "team_separation" && (team_separation_extra() == $is_enable)) ||
        ($extra == "notification" && (notification_extra() == $is_enable)) ||
        ($extra == "import_export" && (import_export_extra() == $is_enable)) ||
        ($extra == "incident_management" && (incident_management_extra() == $is_enable)) ||
        ($extra == "api" && (api_extra() == $is_enable)) ||
        ($extra == "assessments" && (assessments_extra() == $is_enable)) ||
        ($extra == "complianceforge_scf" && (complianceforge_scf_extra() == $is_enable)) ||
        ($extra == "advanced_search" && (advanced_search_extra() == $is_enable)) ||
        ($extra == "jira" && (jira_extra() == $is_enable)) ||
        ($extra == "organizational_hierarchy" && (organizational_hierarchy_extra() == $is_enable)) ||
        ($extra == "ucf" && (ucf_extra() == $is_enable)) ||
	($extra == "extra_vulnmgmt" && (vulnmgmt_extra() == $is_enable));

    if ($interrupt) {
        set_alert(true, "bad", $lang['ExtraIsAlready' . ($is_enable ? 'Enabled': 'Disabled')]);
        refresh();
    }
}

/********************************************************************
 * FUNCTION: PREVENT FORM DOUBLE SUBMIT SCRIPT                      *
 * When the $forms is set, only the forms of the provided ids       *
 * will trigger the page-wide disablement of form submit buttons.   *
 ********************************************************************/
function prevent_form_double_submit_script($forms = false) {
    if ($forms) {
        echo "
            $(document).ready(function(){";
        foreach ($forms as $form) {
            echo "
                $('#{$form}').submit(function(evt) {
                    setTimeout(function(){ $(\"input[type='submit']\").prop('disabled', true); }, 1);
                    setTimeout(function(){ $(\"button[type='submit']\").prop('disabled', true); }, 1);
                    return true;
                });
            ";
        }
        echo "
            });";
    } else {
        echo "
            $(document).ready(function(){
                $('form').submit(function(evt) {
                    setTimeout(function(){ $(\"input[type='submit']\").prop('disabled', true); }, 1);
                    setTimeout(function(){ $(\"button[type='submit']\").prop('disabled', true); }, 1);
                    return true;
                });
            });\n";
    }
}

/*********************************
 * FUNCTION: GET RISK BY SUBJECT *
 *********************************/
function get_risk_by_subject($subject)
{
    // If the encrypted db extra is enabled
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
        return encryption_get_risk_by_subject($subject);
    }
    // If the encrypted db extra is not enabled
    else
    {
        // Open the database connection
        $db = db_open();

        // Search for a risk with this subject
        $stmt = $db->prepare("SELECT id FROM risks WHERE subject = :subject;");
        $stmt->bindParam(":subject", $subject, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If we have at least one result
        if (count($result) > 0)
        {
            // Return the first risk id
            return $result[0]['id'];
        }
        else return false;
    }

    // Return false
    return false;
}

/*******************************************
 * FUNCTION: GET TYPE OF COLUMN            *
 * Please note, that this function only    *
 * returns 'varchar' of type 'varchar(10)' *
 *******************************************/
function getTypeOfColumn($table, $column) {
    $db = db_open();

    $stmt = $db->prepare("SELECT `DATA_TYPE` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = '" . DB_DATABASE . "' AND `TABLE_NAME` = :table AND `COLUMN_NAME` = :column;");
    $stmt->bindParam(":table", $table, PDO::PARAM_STR);
    $stmt->bindParam(":column", $column, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetch(pdo::FETCH_COLUMN, 0);

    db_close($db);

    return $result ? $result : "";
}

/********************************
 * FUNCTION: GET TAGS OF TAGGEE *
 ********************************/
function getTagsOfTaggee($taggee_id, $type) {

    global $tag_types;

    if (!$taggee_id || !in_array($type, $tag_types))
        return;

    $db = db_open();

    //Load tags currently assigned to the taggee
    $stmt = $db->prepare("
        SELECT
            `t`.`tag`
        FROM
            `tags` t
            INNER JOIN `tags_taggees` tt ON `tt`.`tag_id` = `t`.`id`
        WHERE
            `tt`.`taggee_id` = :taggee_id and `tt`.`type` = :type;
    ");
    $stmt->bindParam(":taggee_id", $taggee_id, PDO::PARAM_STR);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    db_close($db);

    //Get the actual tag values(the $result array contains other info as well)
    return array_column($result, 'tag');
}

/**********************
 * FUNCTION: HAS TAGS *
 **********************/
function hasTags($taggee_id, $type) {

    global $tag_types;

    if (!$taggee_id || !in_array($type, $tag_types))
        return;

    $db = db_open();

    //Check if there're tags currently assigned to the taggee
    $stmt = $db->prepare("
        SELECT
            distinct(5)
        FROM
            `tags_taggees` tt
        WHERE
            `tt`.`taggee_id` = :taggee_id and `tt`.`type` = :type;
    ");
    $stmt->bindParam(":taggee_id", $taggee_id, PDO::PARAM_STR);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();

    db_close($db);

    return !empty($result);
}

/****************************************************************************
 * FUNCTION: UPDATE TAGS OF TYPE                                            *
 * Gets an id and a type to decide what the tag is assigned to(referenced   *
 * as `taggee`) and updates its tags. Type can be either `risk` or `asset`. *
 * Third parameter is an array. If the array is empty, all the tags will be *
 * removed from the taggee                                                  *
 ****************************************************************************/
function updateTagsOfType($taggee_id, $type, $tags) {

    global $tag_types;

    if (!$taggee_id || !in_array($type, $tag_types) || !is_array($tags))
        return false;

    //Get the actual tag values(the $result array contains other info as well)
    $tags_current = getTagsOfTaggee($taggee_id, $type);

    $db = db_open();

    // Clever usage of array_diffs to calculate what tags are removed from the taggee
    // and what tags are added
    $tags_to_remove = array_diff($tags_current, $tags);
    $tags_to_add = array_diff($tags, $tags_current);

    // If there're tags to remove
    if ($tags_to_remove) {

        //building an array of parameters to bind
        $params = array(":taggee_id" => $taggee_id, ":type" => $type);

        // building the list of strings to be used in the `in` part of the sql
        // to be able to bind the params
        // We need this to be able to delete all the connections to the removed
        // tags in one go, instead of using a loop
        $tags_to_remove_in = [];
        foreach ($tags_to_remove as $i => $tag)
        {
            $key = ":id".$i;
            $tags_to_remove_in[] = $key;
            $params[$key] = $tag;
        }

        // making the comma separated list to be included in the sql
        $tags_to_remove_in = implode(", ", $tags_to_remove_in);

        // Remove the entries from the junction table that connected the deleted tags to the taggee
        $stmt = $db->prepare("
            delete
                `tt`
            from
                `tags` t
                inner join `tags_taggees` tt on `tt`.`tag_id` = `t`.`id`
            where
                `tt`.`taggee_id` = :taggee_id and
                `tt`.`type` = :type and
                `t`.`tag` in ({$tags_to_remove_in});
        ");
        $stmt->execute($params);

        // Clean up every tags that aren't referenced by the junction table
        $stmt = $db->prepare("
            delete
                `t`
            from
                `tags` `t`
                left join `tags_taggees` `tt` on `tt`.`tag_id` = `t`.`id`
            where
                `tt`.`taggee_id` is null;
        ");
        $stmt->execute();
    }

    //If there're tags to add
    if ($tags_to_add) {
        //Sadly we can't do this in a single sql so we have to resort to looping
        foreach ($tags_to_add as $tag) {

            // Get the id of the tag (to either use it or to know that it's not
            // in the database yet)
            $stmt = $db->prepare("
                SELECT
                    `id`
                FROM
                    `tags` `t`
                WHERE `t`.`tag` = :tag;
            ");
            $stmt->bindParam(":tag", $tag, PDO::PARAM_STR);
            $stmt->execute();

            $tag_id = $stmt->fetchAll();

            if ($tag_id) {
                $tag_id = $tag_id[0];
                // If the tag is already in the database we just use the id to create
                // the connection between the taggee and the tag in the junction table
                $stmt = $db->prepare("
                    INSERT INTO
                        `tags_taggees` (`tag_id`, `taggee_id`, `type`)
                    VALUES
                        (:tag_id, :taggee_id, :type);
                ");
                $stmt->bindParam(":tag_id", $tag_id[0], PDO::PARAM_STR);
                $stmt->bindParam(":taggee_id", $taggee_id, PDO::PARAM_STR);
                $stmt->bindParam(":type", $type, PDO::PARAM_STR);
                $stmt->execute();
            } else {
                // If the tag isn't in the database yet, we have to create it and
                // using its id to create the connection to the taggee
                $stmt = $db->prepare("
                    INSERT INTO
                        `tags`(`tag`)
                    VALUES(:tag);
                    INSERT INTO
                        `tags_taggees` (`tag_id`, `taggee_id`, `type`)
                    VALUES
                        (LAST_INSERT_ID(), :taggee_id, :type);
                ");
                $stmt->bindParam(":tag", $tag, PDO::PARAM_STR);
                $stmt->bindParam(":taggee_id", $taggee_id, PDO::PARAM_STR);
                $stmt->bindParam(":type", $type, PDO::PARAM_STR);
                $stmt->execute();
                // We have to use it because of the LAST_INSERT_ID() in the previous query
                $stmt->closeCursor();
            }
        }
    }

    db_close($db);

    // No audit logging is needed if nothing changed
    // Also no audit logging is done when there's no session(like when a questionnaire is filled without a user actually logging in)
    if (isset($_SESSION) && isset($_SESSION['user']) && isset($_SESSION['uid']) && ($tags_to_add || $tags_to_remove)) {
        global $lang;

        $tag_changes = [];
        if ($tags_to_add)
            $tag_changes[] = _lang('TagUpdateAuditLogAdded', array('tags_added' => implode(", ", $tags_to_add)), false);
        if ($tags_to_remove)
            $tag_changes[] = _lang('TagUpdateAuditLogRemoved', array('tags_removed' => implode(", ", $tags_to_remove)), false);

        $message = _lang('TagUpdateAuditLog', array(
                'user' => $_SESSION['user'],
                'type' => $lang['TagType_' . $type],
                'id' => $taggee_id + ($type == 'risk' ? 1000 : 0),
                'tags_from' => implode(", ", $tags_current),
                'tags_to' => implode(", ", $tags),
                'tag_changes' => implode(", ", $tag_changes)
            ), false
        );

        write_log($taggee_id + 1000, $_SESSION['uid'], $message, $type);
    }

    return true;
}

/*******************************************
 * FUNCTION: GET TAGS OF TYPE              *
 * Gets tags assigned to a type of taggee. *
 * Type can be either `risk` or `asset`.   *
 *******************************************/
function getTagsOfType($type) {

    global $tag_types;

    if (!in_array($type, $tag_types))
        return [];

    $db = db_open();

    //Load tags currently assigned to a type of taggee
    $stmt = $db->prepare("
        SELECT
            `t`.`tag`, t.id
        FROM
            `tags` t
            INNER JOIN `tags_taggees` tt ON `tt`.`tag_id` = `t`.`id`
        WHERE
            `tt`.`type` = :type
        GROUP BY 
            t.id
        ORDER BY `t`.`tag` ASC;
    ");
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    return $tags;
}

/************************************************
 * FUNCTION: GET TAGS OF TYPES                  *
 * Gets tags assigned to list of taggee types.  *
 ************************************************/
function getTagsOfTypes($types = []) {
    
    global $tag_types;
    
    // Making sure we only accept the types we can work with
    $types = array_intersect($types, $tag_types);
    
    // If there's no type left
    if (empty($types)) {
        return [];
    }
        
    $db = db_open();
    
    //Load tags currently assigned to a type of taggee
    $stmt = $db->prepare("
        SELECT
            `t`.`tag`, t.id
        FROM
            `tags` t
            INNER JOIN `tags_taggees` tt ON `tt`.`tag_id` = `t`.`id`
        WHERE
            `tt`.`type` in ('" . implode("','", $types) . "')
        GROUP BY
            t.id
        ORDER BY `t`.`tag` ASC;
    ");
    
    $stmt->execute();
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    db_close($db);
    
    return $tags;
}

/*********************************************
 * FUNCTION: ARE TAGS EQUAL                  *
 * Gets tags assigned to a type of taggee    *
 * and compares them to the $tags parameter. *
 * Type can be either `risk` or `asset`.     *
 *********************************************/
function areTagsEqual($taggee_id, $type, $tags) {

    global $tag_types;

    if (!$taggee_id || !in_array($type, $tag_types) || !is_array($tags))
        return false;

    $db = db_open();

    //Load tags currently assigned to the taggee
    $stmt = $db->prepare("
        SELECT
            `t`.`tag`
        FROM
            `tags` t
            INNER JOIN `tags_taggees` tt ON `tt`.`tag_id` = `t`.`id`
        WHERE
            `tt`.`taggee_id` = :taggee_id and `tt`.`type` = :type;
    ");
    $stmt->bindParam(":taggee_id", $taggee_id, PDO::PARAM_STR);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    $tags_current = array_column($result, 'tag');

    db_close($db);

    return array_diff($tags_current, $tags) == array_diff($tags, $tags_current);
}

/*********************************
 * FUNCTION: REMOVE TAGS OF TYPE *
 *********************************/
function removeTagsOfTaggee($taggee_id, $type) {

    global $tag_types;

    if (!$taggee_id
        || !in_array($type, $tag_types)
        || !hasTags($taggee_id, $type))
        return;

    $db = db_open();

    // Remove the entries from the junction table that connected to the taggee
    $stmt = $db->prepare("
        delete
            `tt`
        from
            `tags` t
            inner join `tags_taggees` tt on `tt`.`tag_id` = `t`.`id`
        where
            `tt`.`taggee_id` = :taggee_id and
            `tt`.`type` = :type;
    ");
    $stmt->bindParam(":taggee_id", $taggee_id, PDO::PARAM_STR);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();


    // Clean up every tags that aren't referenced by the junction table
    $stmt = $db->prepare("
        delete
            `t`
        from
            `tags` `t`
            left join `tags_taggees` `tt` on `tt`.`tag_id` = `t`.`id`
        where
            `tt`.`taggee_id` is null;
    ");
    $stmt->execute();

    db_close($db);
}

/*******************************
 * FUNCTION: UPDATE RISK LEVEL *
 *******************************/
function update_risk_level($field, $value, $name) {
    $db = db_open();

    // Update the risk level
    $stmt = $db->prepare("UPDATE `risk_levels` SET {$field}=:{$field} WHERE name=:name");
    $stmt->bindParam(":{$field}", $value, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);

    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************
 * FUNCTION: INCLUDE CSRF MAGIC  *
 * Make sure to call this after  *
 * the session is properly setup *
 *********************************/
function include_csrf_magic() {

    function csrf_startup() {
        global $escaper;
        csrf_conf('rewrite-js', $escaper->escapeHtml(get_setting('simplerisk_base_url')).'/vendor/simplerisk/csrf-magic/csrf-magic.js');
    }
    csrf_init();
}

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

/*********************************
 * FUNCTION: GET SETTING BY NAME *
 *********************************/
function get_setting_by_name($name)
{
    return get_setting($name);
}

/**********************************
 * FUNCTION: GET SETTTING BY NAME *
 **********************************/
function get_settting_by_name($name)
{
    return get_setting($name);
}

/********************************************
 * FUNCTION: CHECK IF THIS IS BASE64 STRING *
 ********************************************/
function check_base64_string($string)
{
    if(trim(base64_encode(base64_decode($string)), "=") == trim($string, "="))
    {
        return true;
    }
    else
    {
        return false;
    }
}

/********************************************
 * FUNCTION: RETURN ALL CHILDS BY PARENT ID *
 ********************************************/
function get_all_childs($rows, $parent_id, &$childs=[], $id_key="id")
{
    foreach($rows as $row)
    {
        if($row['parent'] == $parent_id)
        {
            array_push($childs, $row);
            get_all_childs($rows, $row[$id_key], $childs, $id_key);
        }
    }
}

/****************************************************
 * FUNCTION: GET TEAMS OF ITEM                      *
 * Return the teams assigned to the item.           *
 * If $names is true it returns the values and the  *
 * names, otherwise it'll only return the values    *
 ****************************************************/
function getTeamsOfItem($item_id, $type, $names=false) {

    global $available_item_types;

    if (!$item_id || !in_array($type, $available_item_types))
        return [];

    $db = db_open();

    $sql = "
        SELECT
            `t`." . ($names ? "*" : "`value`") . "
        FROM
            `team` t
            INNER JOIN `items_to_teams` itt ON `itt`.`team_id` = `t`.`value` and `itt`.`type` = :type
        WHERE
            `itt`.`item_id` = :item_id;
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":item_id", $item_id, PDO::PARAM_STR);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();

    if ($names)
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    else
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    db_close($db);

    return $result;
}

/****************************************************
 * FUNCTION: GET ITEMS OF TEAM                      *
 * Return the items assigned to the team.           *
 * If $full is true it returns every fields of the  *
 * item, otherwise it'll only return the ids        *
 ****************************************************/
function getItemsOfTeam($team_id, $type, $full=false) {

    global $available_item_types;

    if (!$team_id || !in_array($type, $available_item_types))
        return [];

    $db = db_open();

    switch($type) {
        case 'audit':
            $item_table_name = 'framework_control_test_audits';
            $item_id_field_name = 'id';
            $teams_query = "SELECT GROUP_CONCAT(DISTINCT `team_id`) FROM `items_to_teams` WHERE `type` = :type AND `item_id` = `item`.`{$item_id_field_name}`";
            break;
        case 'test':
            $item_table_name = 'framework_control_tests';
            $item_id_field_name = 'id';
            $teams_query = "SELECT GROUP_CONCAT(DISTINCT `team_id`) FROM `items_to_teams` WHERE `type` = :type AND `item_id` = `item`.`{$item_id_field_name}`";
            break;
    }

    // Assemble the query
    $sql = "
        SELECT
            `item`." . 
            ($full ?  "*, ({$teams_query}) as teams" : "`{$item_id_field_name}`") . "
        FROM
            `{$item_table_name}` item
            INNER JOIN `items_to_teams` i2t ON `i2t`.`item_id` = `item`.`{$item_id_field_name}` and `i2t`.`type` = :type
        WHERE
            `i2t`.`team_id` = :team_id;
    ";

    //Load items currently assigned to the team
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":team_id", $team_id, PDO::PARAM_STR);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();

    if ($full)
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    else
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    db_close($db);

    return $result;
}

/*************************************************
 * FUNCTION: HAS TEAMS                           *
 * Checks if there're teams assigned to the item *
 *************************************************/
function hasTeams($item_id, $type) {

    global $available_item_types;

    if (!$item_id || !in_array($type, $available_item_types))
        return false;

    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            distinct(5)
        FROM
            `items_to_teams` itt
        WHERE
            `itt`.`item_id` = :item_id and `itt`.`type` = :type;
    ");
    $stmt->bindParam(":item_id", $item_id, PDO::PARAM_STR);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();

    db_close($db);

    return !empty($result);
}

/*************************************************
 * FUNCTION: HAS ITEMS                           *
 * Checks if there're items assigned to the team *
 *************************************************/
function hasItems($team_id, $type) {

    global $available_item_types;

    if (!$team_id || !in_array($type, $available_item_types))
        return false;

    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            distinct(5)
        FROM
            `items_to_teams` itt
        WHERE
            `itt`.`team_id` = :team_id and `itt`.`type` = :type;
    ");
    $stmt->bindParam(":team_id", $team_id, PDO::PARAM_STR);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();

    db_close($db);

    return !empty($result);
}

/********************************************************************
 * FUNCTION: UPDATE TEAMS OF ITEM                                   *
 * Gets an item id and a type to decide what the item is assigned   *
 * to and updates its teams. Available type values are listed in    *
 * the $available_item_types global variable.                       *
 * Third parameter is an array of team ids. If the array is         *
 * empty, all the teams will be removed from the item.              *
 ********************************************************************/
function updateTeamsOfItem($item_id, $type, $teams, $audit_log=true) {

    global $available_item_types;

    if (!$item_id || !in_array($type, $available_item_types) || !is_array($teams))
        return false;

    $teams_current = getTeamsOfItem($item_id, $type);

    $db = db_open();

    // Clever usage of array_diffs to calculate what teams are removed from the item
    // and what teams are added
    $teams_to_remove = array_diff($teams_current, $teams);
    $teams_to_add = array_diff($teams, $teams_current);

    // If there're teams to remove
    if ($teams_to_remove) {

        //building an array of parameters to bind
        $params = array(":item_id" => $item_id, ":type" => $type);

        // building the list of strings to be used in the `in` part of the sql
        // to be able to bind the params
        // We need this to be able to delete all the connections to the removed
        // teams in one go, instead of using a loop
        $teams_to_remove_in = [];
        foreach ($teams_to_remove as $i => $team) {
            $key = ":id".$i;
            $teams_to_remove_in[] = $key;
            $params[$key] = $team;
        }

        // making the comma separated list to be included in the sql
        $teams_to_remove_in = implode(", ", $teams_to_remove_in);

        // Remove the entries from the junction table that connected the teams to the item
        $stmt = $db->prepare("
            DELETE
                `itt`
            FROM
                `team` t
                INNER JOIN `items_to_teams` itt ON
                    `itt`.`team_id` = `t`.`value` AND
                    `itt`.`item_id` = :item_id AND
                    `itt`.`type` = :type
            WHERE
                `t`.`value` in ({$teams_to_remove_in});
        ");
        $stmt->execute($params);
    }

    //If there're teams to add
    if ($teams_to_add) {
        //Sadly we can't do this in a single sql so we have to resort to looping
        foreach ($teams_to_add as $team_id) {

            // We just use the id to create
            // the connection between the item and the team in the junction table
            $stmt = $db->prepare("
                INSERT INTO
                    `items_to_teams` (`team_id`, `item_id`, `type`)
                VALUES
                    (:team_id, :item_id, :type);
            ");
            $stmt->bindParam(":team_id", $team_id, PDO::PARAM_STR);
            $stmt->bindParam(":item_id", $item_id, PDO::PARAM_STR);
            $stmt->bindParam(":type", $type, PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    db_close($db);

    // No audit logging is needed if nothing changed
    if ($audit_log && ($teams_to_add || $teams_to_remove)) {
        global $lang;

        $team_changes = [];
        if ($teams_to_add)
            $team_changes[] = _lang('TeamUpdateAuditLogAdded', array('teams_added' => implode(", ", get_names_by_multi_values('team', $teams_to_add, true))), false);
        if ($teams_to_remove)
            $team_changes[] = _lang('TeamUpdateAuditLogRemoved', array('teams_removed' => implode(", ", get_names_by_multi_values('team', $teams_to_remove, true))), false);

        $message = _lang('TeamUpdateAuditLog', array(
                'user' => $_SESSION['user'],
                'type' => $lang['TeamType_' . $type],
                'id' => $item_id,
                'teams_from' => implode(", ", get_names_by_multi_values('team', $teams_current, true)),
                'teams_to' => implode(", ", get_names_by_multi_values('team', $teams, true)),
                'team_changes' => implode(", ", $team_changes)
            ), false
        );

        // In case it has to be something different than the $type
        switch($type) {
            case "audit":
                $audit_type = 'test_audit';
                break;
            default:
                $audit_type = $type;
                break;
        }

        write_log((int)$item_id + 1000, $_SESSION['uid'], $message, $audit_type);
    }

    return true;
}

/********************************************************************
 * FUNCTION: UPDATE ITEMS OF TEAM                                   *
 * Gets a team id and a type to decide what the team is assigned to *
 * and updates its items. Available type values are listed in       *
 * the $available_item_types global variable.                       *
 * Third parameter is an array of item ids. If the array is         *
 * empty, all the items will be removed from the team.              *
 ********************************************************************/
function updateItemsOfTeam($team_id, $type, $items) {

    global $available_item_types;

    if (!$team_id || !in_array($type, $available_item_types) || !is_array($items))
        return false;

    $items_current = getItemsOfTeam($team_id, $type);

    $db = db_open();

    // Clever usage of array_diffs to calculate what items are removed from the team
    // and what items are added
    $items_to_remove = array_diff($items_current, $items);
    $items_to_add = array_diff($items, $items_current);

    // If there're teams to remove
    if ($items_to_remove) {

        //building an array of parameters to bind
        $params = array(":team_id" => $team_id, ":type" => $type);

        // building the list of strings to be used in the `in` part of the sql
        // to be able to bind the params
        // We need this to be able to delete all the connections to the removed
        // items in one go, instead of using a loop
        $items_to_remove_in = [];
        foreach ($items_to_remove as $i => $item) {
            $key = ":id".$i;
            $items_to_remove_in[] = $key;
            $params[$key] = $item;
        }

        // making the comma separated list to be included in the sql
        $items_to_remove_in = implode(", ", $items_to_remove_in);

        // Remove the entries from the junction table that connected the teams to the item
        $stmt = $db->prepare("
            DELETE FROM
                `items_to_teams`
            WHERE
                `team_id` = :team_id AND
                `type` = :type AND
                `item_id` = in ({$items_to_remove_in});
        ");
        $stmt->execute($params);
    }

    //If there're teams to add
    if ($items_to_add) {

        $params = array(":team_id" => $team_id, ":type" => $type);
        $items_to_add_values = [];

        //Sadly we can't do this in a single sql so we have to resort to looping
        foreach ($items_to_add as $i => $item_id) {
            $key = ":item_id".$i;
            $params[$key] = $item_id;
            $items_to_add_values[] = "(:team_id, {$key}, :type)";
        }
        
        $items_to_add_values = implode(", ", $items_to_add_values);
        // We just use the id to create
        // the connection between the item and the team in the junction table
        $stmt = $db->prepare("
            INSERT INTO
                `items_to_teams` (`team_id`, `item_id`, `type`)
            VALUES
                {$items_to_add_values};
        ");
        $stmt->execute($params);
    }

    db_close($db);

    // No audit logging is needed if nothing changed
    if ($items_to_add || $items_to_remove) {
        global $lang;

        //$db = db_open();

        switch($type) {
            case 'audit':
                $audit_type = 'test_audit';
                $item_table_name = 'framework_control_test_audits';
                $use_id = true;
                break;
            case 'test':
                $audit_type = 'test';
                $item_table_name = 'framework_control_tests';
                $use_id = true;
                break;
        }

        $item_changes = [];
        if ($items_to_add)
            $item_changes[] = _lang('ItemUpdateAuditLogAdded', array('items_added' => implode(", ", get_names_by_multi_values($item_table_name, $items_to_add, true, '', $use_id))), false);
        if ($items_to_remove)
            $item_changes[] = _lang('ItemUpdateAuditLogRemoved', array('items_removed' => implode(", ", get_names_by_multi_values($item_table_name, $items_to_remove, true, '', $use_id))), false);

        $message = _lang('ItemUpdateAuditLog', array(
                'user' => $_SESSION['user'],
                'type' => $lang['TeamType_' . $type],
                'team' => get_name_by_value('team', $team_id),
                'items_from' => implode(", ", get_names_by_multi_values($item_table_name, $items_current, true, '', $use_id)),
                'items_to' => implode(", ", get_names_by_multi_values($item_table_name, $items, true, '', $use_id)),
                'item_changes' => implode(", ", $item_changes)
            ), false
        );

        write_log((int)$item_id + 1000, $_SESSION['uid'], $message, $audit_type);
    }

    return true;
}

function is_valid_impact_and_likelihood($impact, $likelihood) {
    
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            1
        FROM
            dual
        WHERE
            :impact BETWEEN 1 AND (SELECT MAX(`value`) FROM `impact`)
            AND
            :likelihood BETWEEN 1 AND (SELECT MAX(`value`) FROM `likelihood`);
    ");
    $stmt->bindParam(":impact", $impact, PDO::PARAM_INT);
    $stmt->bindParam(":likelihood", $likelihood, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_COLUMN, 0);
    
    db_close($db);
    
    return boolval($result);
}

function set_stored_risk_score($impact, $likelihood, $value, $update_risks = false) {

    $db = db_open();

    $stmt = $db->prepare("
        INSERT INTO
            `custom_risk_model_values`
                (`impact`, `likelihood`, `value`)
        VALUES
            (:impact, :likelihood, :value)
        ON DUPLICATE KEY UPDATE
            value=:value;
    ");
    $stmt->bindParam(":impact", $impact, PDO::PARAM_INT);
    $stmt->bindParam(":likelihood", $likelihood, PDO::PARAM_INT);
    $stmt->bindParam(":value", $value, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($update_risks) {
        // Get the list of all risks using the classic formula
        $stmt = $db->prepare("
            SELECT
                id
            FROM
                risk_scoring
            WHERE
                scoring_method = 1
                AND calculated_risk <> :value
                AND CLASSIC_impact = :impact
                AND CLASSIC_likelihood = :likelihood;
        ");
        $stmt->bindParam(":value", $value, PDO::PARAM_STR);
        $stmt->bindParam(":impact", $impact, PDO::PARAM_INT);
        $stmt->bindParam(":likelihood", $likelihood, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the risk_ids array
        $risk_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // For each risk using the classic formula
        foreach ($risk_ids as $risk_id)
        {
            // Update the value in the DB
            $stmt = $db->prepare("UPDATE risk_scoring SET calculated_risk = :calculated_risk WHERE id = :id");
            $stmt->bindParam(":calculated_risk", $value, PDO::PARAM_STR);
            $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

            // Add risk scoring history
            add_risk_scoring_history($risk_id, $value);

            // Add residual risk scoring history
            $residual_risk = get_residual_risk($risk_id+1000);
            add_residual_risk_scoring_history($risk_id, $residual_risk);
        }
    }
    db_close($db);
}

function get_stored_risk_score($impact, $likelihood) {

    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `value`
        FROM
            `custom_risk_model_values`
        WHERE
            `impact` = :impact AND
            `likelihood` = :likelihood;
    ");
    $stmt->bindParam(":impact", $impact, PDO::PARAM_INT);
    $stmt->bindParam(":likelihood", $likelihood, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_COLUMN, 0);

    db_close($db);

    return $result ? $result : 0;
}

/******************************************
 * FUNCTION: GET DYNAMIC SAVED SELECTIONS *
 ******************************************/
function get_dynamic_saved_selections($user_id)
{
    // Open the database connection
    $db = db_open();

    // If the requesting user is an admin then return all the saved selections
    // When returning other users' saved selections add the users' name to the saved selections' names
    // Results are ordered to have the user's own saved selections first,
    // then they're ordered to display private saved selections first then the publics 
    $stmt = $db->prepare("
        SELECT
        	`dss`.`value`,
            IF(`u`.`value` <> :user_id, CONCAT(`dss`.`name`, ' (', `u`.`name`, ')'), `dss`.`name`) as name,
            `dss`.`type`,
            `dss`.`user_id`,
            `dss`.`custom_display_settings`,
            `dss`.`custom_selection_settings`,
            `dss`.`custom_column_filters`
        FROM
        	`dynamic_saved_selections` dss
            INNER JOIN `user` u ON `u`.`value` = `dss`.`user_id`
        WHERE
        	`dss`.`type`='public'
            OR (`dss`.`type` = 'private' AND `dss`.`user_id` = :user_id)
            OR (SELECT `admin` FROM `user` WHERE `value` = :user_id) = 1
        ORDER BY
            `dss`.`user_id` <> :user_id, `dss`.`type` = 'public';
    ");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Get dynamic saved selections
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}

/**************************************************
 * FUNCTION: GET DYNAMIC SAVED SELECTION BY VALUE *
 **************************************************/
function get_dynamic_saved_selection($value)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT user_id, name, type, custom_display_settings, custom_selection_settings, custom_column_filters FROM `dynamic_saved_selections` WHERE `value`=:value;");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Get dynamic saved selections
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);

    return $row;
}

/********************************************************************************
 * FUNCTION: TRUNCATE TO (LENGTH)                                               *
 * Truncates $string to be $length long(including the ... appended to the end)  *
 ********************************************************************************/
function truncate_to($string, $length, $append='...') {
    return mb_strimwidth($string, 0, $length, $append);
}

/*************************************
 * FUNCTION: CHECK CLOSED RISK BY ID *
 *************************************/
function check_closed_risk_by_id($id)
{
    // Subtract 1000 from the id
    $id = (int)$id - 1000;

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("
    SELECT
        *
    FROM
        `risks`
    WHERE
        `id` = :id AND `status` = 'Closed';
    ");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    if($result){
        return true;
    }else{
        return false;
    }
}

/*
    Checking if the extra is already installed
*/
function is_extra_installed($extra) {
    global $available_extras;
    
    if (!in_array($extra, $available_extras))
        false;

    return file_exists(realpath(__DIR__ . "/../extras/$extra/index.php"));
}

//if (!function_exists('check_latest_version')) {
    /*************************************************
     * FUNCTION: CHECK IF THIS APP IS LATEST VERSION *
     *************************************************/
    function check_app_latest_version()
    {
        $current_app_version = current_version("app");
        $next_app_version = next_version($current_app_version);
        $db_version = current_version("db");

        $need_update_app = false;
        $need_update_db = false;
        
        // If the current version is not the latest
        if ($next_app_version != "") {
            $need_update_app = true;
        }
        
        // If the app version is not the same as the database version
        if ($current_app_version != $db_version) {
            $need_update_db = true;
        } elseif ($need_update_app && $next_app_version != $db_version) {
            $need_update_db = true;
        }
        
        // Check if there are update app or db version
        if($need_update_app || $need_update_db)
        {
            return false;
        }
        else
        {
            return true;
        }
    }
//}

/*******************************************************************************
 * FUNCTION: SET PROXY STREAM CONTEXT                                          *
 * This function takes the proxy settings from the Security tab in Settings    *
 * and sets the default stream context to use them before making a web request *
 *******************************************************************************/
function set_proxy_stream_context($method=null, $header=null, $content=null, $ssl_verify_off=false, $timeout=null)
{
    // Get the proxy_web_requests value
    $proxy_web_requests = get_setting("proxy_web_requests");

    // If proxy web requests is set
    if ($proxy_web_requests)
    {
	    write_debug_log("Proxy web requests is enabled");

        // Get the proxy configuration
        $proxy_verify_ssl_certificate = get_setting("proxy_verify_ssl_certificate");
        $proxy_host = get_setting("proxy_host");
        $proxy_port = get_setting("proxy_port");
        $proxy_authenticated = get_setting("proxy_authenticated");
        $proxy_user = get_setting("proxy_user");
        $proxy_pass = get_setting("proxy_pass");

        // Create the http context array
        $http_context = array(
            'proxy' => "tcp://$proxy_host:$proxy_port",
            'ignore_errors' => true,
            'request_fulluri' => true,
        );

	write_debug_log("HTTP Context - Proxy: " . $http_context['proxy']);
	write_debug_log("HTTP Context - Ignore Errors: " . $http_context['ignore_errors']);
	write_debug_log("HTTP Context - Request Full URI: " . $http_context['request_fulluri']);

    // Create the ssl context array
    $ssl_context = array(
        'SNI_enabled' => true
    );

	write_debug_log("SSL Context - SNI Enabled: " . $ssl_context['SNI_enabled']);

        // If this is an authenticated proxy
        if ($proxy_authenticated)
        {
	    write_debug_log("We are using an authenticated proxy");

            // Create the BASE64 encoded credentials
            $auth = base64_encode("$proxy_user:$proxy_pass");

            // Add the authenticated header to the http_context
            $http_context['header'] = "Proxy-Authorization: Basic $auth";

	    write_debug_log("HTTP Context - Header: " . $http_context['header']);
        }

        // If we want to turn off ssl verification
        if (!$proxy_verify_ssl_certificate || $ssl_verify_off == true)
        {
	    write_debug_log("SSL verification is disabled");

            $ssl_context['verify_peer'] = false;
            $ssl_context['verify_peer_name'] = false;
            $ssl_context['allow_self_signed'] = true;

	    write_debug_log("SSL Context - Verify Peer: " . $ssl_context['verify_peer']);
	    write_debug_log("SSL Context - Verify Peer Name: " . $ssl_context['verify_peer_name']);
	    write_debug_log("SSL Context - Allow Self Signed: " . $ssl_context['allow_self_signed']);
        }

        // If the function was provided a method
        if ($method)
        {
	    write_debug_log("A method was provided");

            // Set the provided method
            $http_context['method'] = $method;

	    write_debug_log("HTTP Context - Method: " . $http_context['method']);
        }

        // If the function was provided a header
        if ($header)
        {
	    write_debug_log("A header was provider");

            // If a http header is already set
            if (isset($http_context['header']))
            {
                // Append the provided header
                $http_context['header'] .= "\r\n" . $header;
            }
            // Otherwise
            else
            {
                // Set the provided header
                $http_context['header'] = $header;
            }

	    write_debug_log("HTTP Context - Header: " . $http_context['header']);
        }

        // If the function was provided content
        if ($content)
        {
	    write_debug_log("Content was provided");

            // Set the provided content
            $http_context['content'] = $content;

	    write_debug_log("HTTP Context - Content: " . $http_context['content']);
        }

	// If a timeout was provided
	if ($timeout)
	{
            write_debug_log("Timeout was provided");

	    // Set the provided timeout
	    $http_context['timeout'] = $timeout;

	    write_debug_log("HTTP Context - Timeout: " . $http_context['timeout']);
	}

        // Set the stream context
        $stream_context = array ('http' => $http_context, 'ssl' => $ssl_context);

	//write_debug_log("Stream Context: ");
	//write_debug_log($stream_context);

        // Return the default stream context resource
        return stream_context_set_default($stream_context);
    }
    // Otherwise, if the proxy is not enabled
    else
    {
        // Create array for the http context
        $http_context = array();

        // If the function was provided a method
        if ($method)
        {
            write_debug_log("A method was provided");

            // Set the provided method
            $http_context['method'] = $method;

            write_debug_log("HTTP Context - Method: " . $http_context['method']);
        }

        // If the function was provided a header
        if ($header)
        {
            write_debug_log("A header was provider");

            // If a http header is already set
            if (isset($http_context['header']))
            {
                // Append the provided header
                $http_context['header'] .= "\r\n" . $header;
            }
            // Otherwise
            else
            {
                // Set the provided header
                $http_context['header'] = $header;
            }

            write_debug_log("HTTP Context - Header: " . $http_context['header']);
        }        

        // If the function was provided content
        if ($content)
        {
            write_debug_log("Content was provided");

            // Set the provided content
            $http_context['content'] = $content;

            write_debug_log("HTTP Context - Content: " . $http_context['content']);
        }

        // If a timeout was provided
        if ($timeout)
        {
            write_debug_log("Timeout was provided");

            // Set the provided timeout
            $http_context['timeout'] = $timeout;

            write_debug_log("HTTP Context - Timeout: " . $http_context['timeout']);
        }

        // Create the ssl context array
        $ssl_context = array(
            'SNI_enabled' => true
        );
        write_debug_log("SSL Context - SNI Enabled: " . $ssl_context['SNI_enabled']);

        // If we want to turn off ssl verification
        if (get_setting("ssl_certificate_check") != 1)
        {
            write_debug_log("SSL verification is disabled");

            $ssl_context['verify_peer'] = false;
            $ssl_context['verify_peer_name'] = false;
            $ssl_context['allow_self_signed'] = true;

            write_debug_log("SSL Context - Verify Peer: " . $ssl_context['verify_peer']);
            write_debug_log("SSL Context - Verify Peer Name: " . $ssl_context['verify_peer_name']);
            write_debug_log("SSL Context - Allow Self Signed: " . $ssl_context['allow_self_signed']);
        }

        // Set the stream context
        $stream_context = array ('http' => $http_context, 'ssl' => $ssl_context);

        // Return the default stream context resource
        return stream_context_set_default($stream_context);
    }
}

/**********************************
 * FUNCTION: CONFIGURE CURL PROXY *
 **********************************/
function configure_curl_proxy($curl_handle)
{
	// Get the proxy_web_requests value
	$proxy_web_requests = get_setting("proxy_web_requests");

	// If proxy web requests is set
	if ($proxy_web_requests)
        {
                // Get the proxy configuration
                $proxy_verify_ssl_certificate = get_setting("proxy_verify_ssl_certificate");
                $proxy_host = get_setting("proxy_host");
                $proxy_port = get_setting("proxy_port");
                $proxy_authenticated = get_setting("proxy_authenticated");
                $proxy_user = get_setting("proxy_user");
                $proxy_pass = get_setting("proxy_pass");

                // Configure the proxy
                $proxy = "{$proxy_host}:{$proxy_port}";
                curl_setopt($curl_handle, CURLOPT_PROXY, $proxy);

                // If this is an authenticated proxy
                if ($proxy_authenticated)
                {
                        // Provide the username and password for authentication
                        $proxyauth = "{$proxy_user}:{$proxy_pass}";
                        curl_setopt($curl_handle, CURLOPT_PROXYUSERPWD, $proxyauth);
                }

		// If we do not want to verify the proxy SSL certificates
		if (!$proxy_verify_ssl_certificate)
		{
			// Do not verify the SSL host and peer
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
        }
        else curl_setopt($curl_handle, CURLOPT_PROXY, null);
}

/********************************************************************************************************
 * FUNCTION: SAVE JUNCTION VALUES                                                                       *
 * Used to update the associations between tables.                                                      *
 * Can be used both ways. In case of junction table `X_to_Y` it can be used to                          *
 * update X's associations to multiple Ys or to update Y's associations to multiple Xs.                 *
 * If the $second_field_name and $second_ids are not set, then the associations are removed             *
 * and no furher action is done. Useful on deletes to clean up the associations of the deleted item.    *
 ********************************************************************************************************/
function save_junction_values($tb_name, $first_field_name, $first_id, $second_field_name=false, $second_ids=[]) {
    // If the id is not set or it's not a number
    if (!$first_id || !is_numeric($first_id)) {
        return false;
    }

    $db = db_open();

    // Delete the current associations
    $stmt = $db->prepare("DELETE FROM `{$tb_name}` WHERE `{$first_field_name}` = :value;");
    $stmt->bindParam(":value", $first_id, PDO::PARAM_INT);
    $stmt->execute();

    // We just needed the existing associations cleaned up.
    if (!$second_ids) {
        db_close($db);
        return true;
    }

    if(!is_array($second_ids)) {
        $second_ids = explode(',', $second_ids);
    }

    // Sanitizing the array's values
    //$second_ids = array_values(array_filter($second_ids, 'sanitize_int_array'));
    $second_ids = sanitize_int_array($second_ids);

    // If wasn't empty before and it's empty now then the sanitizing caught something
    if (!$second_ids) {
        db_close($db);
        return false;
    }

    $values = [];
    foreach($second_ids as $second_id) {
        $values[] = "('{$first_id}', '{$second_id}')";
    }

    $sql = "
        INSERT IGNORE INTO
            `{$tb_name}`({$first_field_name}, {$second_field_name})
        VALUES
            " . implode(',', $values);

    $stmt = $db->prepare($sql);
    $stmt->execute();

    db_close($db);

    // Audit log
    $username = isset($_SESSION['user']) ? $_SESSION['user'] : 'admin'; // because it can happen that this function is used even before the user logs in, thus there's no session
    $message = "New data \"" . $first_id . "\" inserted to `{$first_field_name}` field of `{$tb_name}` table submitted by username \"" . $username . "\".";
    if(count($second_ids)) {
        $message .= "\nNew datas (" . implode(",",$second_ids) . ") inserted to `{$second_field_name}` field of `{$tb_name}` table submitted by username \"" . $username . "\".";
    }
    write_log(100, isset($_SESSION['uid']) ? $_SESSION['uid'] : 0, $message);

    return true;
}

/************************************************************************************************************
 * FUNCTION: CLEANUP AFTER DELETE                                                                           *
 * It is used to remove junction entries after an item is deleted from the table "$deleted_item_table".     *
 * The query is structured in a way that it can be used after multiple deletes as it's not targeting        *
 * junction entries for a specific id, it's deleting every junction entry that has no matching id           *
 * in the table "$deleted_item_table".                                                                      *
 * Please note, that because of the above you don't have to call this function after each delete in case    *
 * of a batch-delete, it's enough if it's called once the batch-delete is finished.                         *
 ************************************************************************************************************/
function cleanup_after_delete($deleted_item_table) {
    global $junction_config;

    // It's possible that there're tables that are only present when an extra is installed/enabled
    if (!array_key_exists($deleted_item_table, $junction_config) || !table_exists($deleted_item_table)) {
        return;
    }

    $db = db_open();

    // Getting the configuration for the table
    $config = $junction_config[$deleted_item_table];
    // Getting the name of its id field
    $deleted_item_table_id_field = $config['id_field'];
    
    $has_tags = isset($config['tag_type']) && !empty($config['tag_type']);

    // Iterating through the related junction tables
    foreach($config['junctions'] as $junction_table_name => $junction_deleted_item_id_field) {
        
        // It's possible that there're tables that are only present when an extra is installed/enabled
        if (!table_exists($junction_table_name)) {
            continue;
        }

        // Clean up every junction entries that aren't tied to a value in the configued table
        $stmt = $db->prepare("
            DELETE
                `junction`
            FROM
                `{$junction_table_name}` `junction`
                LEFT JOIN `{$deleted_item_table}` `tbl` ON `junction`.`{$junction_deleted_item_id_field}` = `tbl`.`{$deleted_item_table_id_field}`
            WHERE
                `tbl`.`{$deleted_item_table_id_field}` IS NULL;
        ");
        $stmt->execute();
    }

    if ($has_tags) {
        
        $tag_type = $config['tag_type'];
        // Clean up every tag junction entries that aren't tied to a value in the configued table
        // but make sure we're not deleting other types' junction entries
        $stmt = $db->prepare("
            DELETE
                `tt`
            FROM
                `tags_taggees` `tt`
                LEFT JOIN `{$deleted_item_table}` `tbl` ON `tt`.`type` = '{$tag_type}' AND `tt`.`taggee_id` = `tbl`.`{$deleted_item_table_id_field}`
            WHERE
                `tbl`.`{$deleted_item_table_id_field}` IS NULL
                AND `tt`.`type` = '{$tag_type}';
        ");
        $stmt->execute();
        
        // Clean up every tags that aren't referenced by the junction table
        $stmt = $db->prepare("
            DELETE
                `t`
            FROM
                `tags` `t`
                LEFT JOIN `tags_taggees` `tt` ON `tt`.`tag_id` = `t`.`id`
            WHERE
                `tt`.`taggee_id` IS NULL;
        ");
        $stmt->execute();
    }
    
    db_close($db);
}

/*************************************
 * FUNCTION: GET ENCODED REQUEST URI *
 *************************************/
function get_encoded_request_uri()
{
    $requested_uri = get_request_uri();
    $uri = parse_url($requested_uri, PHP_URL_PATH);

    if(isset($_GET) && count($_GET) > 0)
    {
        $uri .= "?" . http_build_query($_GET);
    }
    
    return $uri;
}
/*****************************
 * FUNCTION: GET REQUEST URI *
 *****************************/
function get_request_uri()
{
    //$requested_uri = $_SERVER["REQUEST_URI"];
    $dir_path = realpath(dirname(dirname(__FILE__)));
    $file_name = realpath($_SERVER["SCRIPT_FILENAME"]);
    $requested_uri = str_replace($dir_path,"",$file_name);
    $requested_uri = str_replace(DIRECTORY_SEPARATOR ,"/",$requested_uri);
    return $requested_uri;
}

/*************************************
 * FUNCTION: GET OPERATOR FROM VALUE *
    * >  : 0
    * >= : 1
    * =  : 2
    * <= : 3
    * <  : 4
 *************************************/
function get_operator_from_value($value)
{
    $operators = [">", ">=", "=", "<=", "<"];
    return empty($operators[$value]) ? false : $operators[$value];
}

/****************************************************************
 * FUNCTION: GET LATEST APP VERSION                             *
 * Used to get the app's latest version from the session,       *
 * but if it's not set, gets it from the simplerisk servers.    *
 ****************************************************************/
function get_latest_app_version() {
    if (!isset($_SESSION['latest_version_app']) || !$_SESSION['latest_version_app']) {
        $_SESSION['latest_version_app'] = latest_version('app');
    }

    return $_SESSION['latest_version_app'];
}

function setup_favicon($path_to_root = "") {   

    global $escaper;

    if ($path_to_root) {
        if($path_to_root[strlen($path_to_root)-1] !== '/') {
            $path_to_root .= '/';
        }
        $path_to_root = $escaper->escapeHtml($path_to_root);
    }

    echo "<link rel='shortcut icon' href='{$path_to_root}favicon.ico' />\n";
}

/**********************************************
 * FUNCTION: GET MITIGATION TEAM QUERY STRING *
 * As of 2020.02.28 it's not used anywhere,   *
 * left here for legacy code                  *
 **********************************************/
function get_mitigation_team_query_string($user_teams, $field_name)
{
    // Create an array based on the colon delimeter
    $teams = explode(":", $user_teams);
    $teams = array_unique(array_map('intval', $teams));

    // String starts as empty
    $string = " FIND_IN_SET({$field_name}, '". implode(",", $teams) ."') ";

    // Return the string
    return $string;
}

/********************************************
 * FUNCTION: GET mitigation_to_controls    *
 ********************************************/
function get_mitigation_to_controls($mitigation_id,$control_id)
{
    // Open the database connection
    $db = db_open();
    // Query the database
    $stmt = $db->prepare("SELECT * FROM `mitigation_to_controls` WHERE mitigation_id = :mitigation_id AND control_id = :control_id");
    $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);

    $stmt->execute();
    $frameworks = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $frameworks;
}

/*******************************
 * FUNCTION: GET RISK CATALOGS *
 *******************************/
function get_risk_catalogs()
{
    // Open the database connection
    $db = db_open();
    // Query the database
    $stmt = $db->prepare("
        SELECT
            `rc`.*,
            `rg`.`value` group_id,
            `rg`.`name` group_name,
            `rg`.`order` group_order,
            `rf`.`name` function_name
        FROM `risk_catalog` rc
            LEFT JOIN `risk_grouping` rg ON `rc`.`grouping` = `rg`.`value`
            LEFT JOIN `risk_function` rf ON `rc`.`function` = `rf`.`value`
        ORDER BY 
            `rg`.`order`,
            `rc`.`order`;
    ");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $result;
}

/*********************************
 * FUNCTION: GET THREAT CATALOGS *
 *********************************/
function get_threat_catalogs()
{
    // Open the database connection
    $db = db_open();
    // Query the database
    $stmt = $db->prepare("
        SELECT
            `tc`.*,
            `tg`.`value` group_id,
            `tg`.`name` group_name,
            `tg`.`order` group_order
        FROM `threat_catalog` tc
            LEFT JOIN `threat_grouping` tg ON `tc`.`grouping` = `tg`.`value`
        ORDER BY
            `tg`.`order`,
            `tc`.`order`;
    ");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $result;
}

/*******************************
 * FUNCTION: GET RISK CATALOG  *
 *******************************/
function get_risk_catalog($id)
{
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("SELECT * FROM `risk_catalog` WHERE `id` = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    // Close the database connection
    db_close($db);
    return $result;
}

/*********************************
 * FUNCTION: GET THREAT CATALOG  *
 *********************************/
function get_threat_catalog($id)
{
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("SELECT * FROM `threat_catalog` WHERE `id` = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    // Close the database connection
    db_close($db);
    return $result;
}

/***************************************
 * FUNCTION: UPDATE RISK CATALOG ORDER *
 ***************************************/
function update_risk_catalog_order($orders)
{
    // Open the database connection
    $db = db_open();
    foreach ($orders as $index => $row)
    {
        $stmt = $db->prepare("UPDATE `risk_catalog` SET `order` = :order WHERE `id` = :id");
        $stmt->bindParam(":order", $row[1], PDO::PARAM_INT);
        $stmt->bindParam(":id", $row[0], PDO::PARAM_INT);
        $stmt->execute();
    }
    // Close the database connection
    db_close($db);
    return true;
}

/*****************************************
 * FUNCTION: UPDATE THREAT CATALOG ORDER *
 *****************************************/
function update_threat_catalog_order($orders)
{
    // Open the database connection
    $db = db_open();
    foreach ($orders as $index => $row)
    {
        $stmt = $db->prepare("UPDATE `threat_catalog` SET `order` = :order WHERE `id` = :id");
        $stmt->bindParam(":order", $row[1], PDO::PARAM_INT);
        $stmt->bindParam(":id", $row[0], PDO::PARAM_INT);
        $stmt->execute();
    }
    // Close the database connection
    db_close($db);
    return true;
}

/******************************
 * FUNCTION: ADD RISK CATALOG *
 ******************************/
function add_risk_catalog($data)
{
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("SELECT MAX(`order`) as max_order FROM `risk_catalog` WHERE 1");
    $stmt->execute();
    $result = $stmt->fetch();
    $new_order = intval($result["max_order"]) + 1;
    $stmt = $db->prepare("INSERT INTO `risk_catalog` (`number`, `grouping`, `name`, `description`, `function`, `order`) VALUES (:number, :grouping, :name, :description, :function, :order)");
    $stmt->bindParam(":number", $data["number"], PDO::PARAM_STR);
    $stmt->bindParam(":grouping", $data["grouping"], PDO::PARAM_INT);
    $stmt->bindParam(":name", $data["name"], PDO::PARAM_STR);
    $stmt->bindParam(":description", $data["description"], PDO::PARAM_STR);
    $stmt->bindParam(":function", $data["function"], PDO::PARAM_INT);
    $stmt->bindParam(":order", $new_order, PDO::PARAM_INT);
    $stmt->execute();
    $risk_id = $db->lastInsertId();
    // Close the database connection
    db_close($db);
    return true;
}

/********************************
 * FUNCTION: ADD THREAT CATALOG *
 ********************************/
function add_threat_catalog($data)
{
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("SELECT MAX(`order`) as max_order FROM `threat_catalog` WHERE 1");
    $stmt->execute();
    $result = $stmt->fetch();
    $new_order = intval($result["max_order"]) + 1;
    $stmt = $db->prepare("INSERT INTO `threat_catalog` (`number`, `grouping`, `name`, `description`, `order`) VALUES (:number, :grouping, :name, :description, :order)");
    $stmt->bindParam(":number", $data["number"], PDO::PARAM_STR);
    $stmt->bindParam(":grouping", $data["grouping"], PDO::PARAM_INT);
    $stmt->bindParam(":name", $data["name"], PDO::PARAM_STR);
    $stmt->bindParam(":description", $data["description"], PDO::PARAM_STR);
    $stmt->bindParam(":order", $new_order, PDO::PARAM_INT);
    $stmt->execute();
    $threat_id = $db->lastInsertId();
    // Close the database connection
    db_close($db);
    return true;
}

/*********************************
 * FUNCTION: UPDATE RISK CATALOG *
 *********************************/
function update_risk_catalog($data)
{
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("UPDATE `risk_catalog` SET `number` = :number, `grouping` = :grouping, `name` = :name, `description` = :description, `function` = :function WHERE `id` = :id;");
    $stmt->bindParam(":id", $data["id"], PDO::PARAM_INT);
    $stmt->bindParam(":number", $data["number"], PDO::PARAM_STR);
    $stmt->bindParam(":grouping", $data["grouping"], PDO::PARAM_INT);
    $stmt->bindParam(":name", $data["name"], PDO::PARAM_STR);
    $stmt->bindParam(":description", $data["description"], PDO::PARAM_STR);
    $stmt->bindParam(":function", $data["function"], PDO::PARAM_INT);
    $stmt->execute();
    $risk_id = $db->lastInsertId();
    // Close the database connection
    db_close($db);
    return true;
}

/***********************************
 * FUNCTION: UPDATE THREAT CATALOG *
 ***********************************/
function update_threat_catalog($data)
{
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("UPDATE `threat_catalog` SET `number` = :number, `grouping` = :grouping, `name` = :name, `description` = :description WHERE `id` = :id;");
    $stmt->bindParam(":id", $data["id"], PDO::PARAM_INT);
    $stmt->bindParam(":number", $data["number"], PDO::PARAM_STR);
    $stmt->bindParam(":grouping", $data["grouping"], PDO::PARAM_INT);
    $stmt->bindParam(":name", $data["name"], PDO::PARAM_STR);
    $stmt->bindParam(":description", $data["description"], PDO::PARAM_STR);
    $stmt->execute();
    $threat_id = $db->lastInsertId();
    // Close the database connection
    db_close($db);
    return true;
}

/**********************************
 * FUNCTION: DELETE RISK CATALOG  *
 **********************************/
function delete_risk_catalog($id)
{
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("DELETE FROM `risk_catalog` WHERE `id` = :id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $risk_id = $db->lastInsertId();
    // Close the database connection
    db_close($db);
    return true;
}

/************************************
 * FUNCTION: DELETE THREAT CATALOG  *
 ************************************/
function delete_threat_catalog($id)
{
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("DELETE FROM `threat_catalog` WHERE `id` = :id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $threat_id = $db->lastInsertId();
    // Close the database connection
    db_close($db);
    return true;
}

/*****************************
 * FUNCTION: GET RISK STATUS *
 *****************************/
function get_risk_status($risk_id){
    $id = (int)$risk_id - 1000;
    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("SELECT * FROM `risks` WHERE `id` = :id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $risk = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    $status = $risk?$risk["status"]:"";

    return $status;

}

/***************************************
 * FUNCTION: FILE UPLOAD ERROR MESSAGE *
 ***************************************/
function file_upload_error_message($error)
{
	switch ($error)
	{
		// File exceeds the upload_max filesize directive
		case 1:
			$message = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
			break;
		// File exceed the MAX_FILE_SIZE directive in the HTML form
		case 2:
			$message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
			break;
		// File was only partially uploaded
		case 3:
			$message = "The uploaded file was only partially uploaded.";
			break;
		// No file was uploaded
		case 4:
			$message = "No file was uploaded.";
			break;
		// Temporary folder is missing
		case 6:
			$message = "Missing a temporary folder.";
			break;
		// Failed to write file to disk
		case 7:
			$message = "Failed to write file to disk.";
			break;
		// PHP extension stopped file upload
		case 8:
			$message = "A PHP extension stopped the file upload.";
			break;
		// Generic default error message
		default:
			$message = "There was an error with the file upload.";
	}

	// Write a message to the debug log
	write_debug_log($message);

	// Display an alert
        set_alert(true, "bad", $message);

	// Return the message
	return $message;
}

/***********************************************
 * FUNCTION: SAVE CUSTOM RISK DISPLAY SETTINGS *
 **********************************************/
function save_custom_risk_display_settings($field = "custom_plan_mitigation_display_settings", $data = [])
{
    $data_str = json_encode($data);
    
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("UPDATE `user` SET `{$field}` = :data_str WHERE `value` = :value");
    $stmt->bindParam(":data_str", $data_str, PDO::PARAM_STR);
    $stmt->bindParam(":value", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    return;
}

function get_user_name($user_id) {
    // Open the database connection
    $db = db_open();
    
    // Query the database
    $stmt = $db->prepare("SELECT `name` FROM `user` WHERE `value` = :user_id;");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $name = $stmt->fetch(PDO::FETCH_COLUMN);
    
    // Close the database connection
    db_close($db);
    
    return $name;
}

/************************************************************************
 * FUNCTION: GET USER WITH PERMISSION                                   *
 * Getting users with a permission, specified by the $permission_key    *
 ************************************************************************/
function get_users_with_permission($permission_key) {
    
    // Open the database connection
    $db = db_open();
    
    if (organizational_hierarchy_extra()) {
        $stmt = $db->prepare("
            SELECT
                `u`.*
            FROM
                `user` u
                INNER JOIN `user_to_team` u2t_bu ON `u2t_bu`.`user_id` = `u`.`value`
                INNER JOIN `business_unit_to_team` bu2t ON `u2t_bu`.`team_id` = `bu2t`.`team_id`
                INNER JOIN `permission_to_user` p2u ON `u`.`value` = `p2u`.`user_id`
                INNER JOIN `permissions` p ON `p`.`id` = `p2u`.`permission_id`
            WHERE
                `bu2t`.`business_unit_id` = :selected_business_unit
                AND `u`.`enabled` = 1
                AND `p`.`key` = :permission_key
            GROUP BY
                `u`.`value`
            ORDER BY
                `u`.`name`;
        ");

        if (!isset($_SESSION['selected_business_unit'])) {
            require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
            $selected_business_unit = get_selected_business_unit($_SESSION['uid']);
        } else {
            $selected_business_unit = $_SESSION['selected_business_unit'];
        }

        $stmt->bindParam(":selected_business_unit", $selected_business_unit, PDO::PARAM_INT);
        $stmt->bindParam(":permission_key", $permission_key, PDO::PARAM_STR);
    } else {
        $stmt = $db->prepare("
            SELECT
                `u`.*
            FROM
                `user` u
                INNER JOIN `permission_to_user` p2u ON `u`.`value` = `p2u`.`user_id`
                INNER JOIN `permissions` p ON `p`.`id` = `p2u`.`permission_id`
            WHERE
                `u`.`enabled` = 1
                AND `p`.`key` = :permission_key
            GROUP BY 
                `u`.`value`
            ORDER BY
                `u`.`name`;
        ");

        $stmt->bindParam(":permission_key", $permission_key, PDO::PARAM_STR);
    }
    
    $stmt->execute();

    // Store the list in the array
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    return $users;
}

/***********************************************************************************
 * NEXT SECTION CONTAINS FUNCTIONS DEDICATED TO FIXING FILE UPLOAD ENCODING ISSUES *
 ***********************************************************************************/
function has_files_with_encoding_issues($type = 'all') {
    $db = db_open();
    
    switch($type) {
        case 'compliance':
            $sql = "SELECT count(1) AS cnt FROM `compliance_files` WHERE `size` <> LENGTH(`content`);";
        break;
        case 'risk':
            $sql = "SELECT count(1) AS cnt FROM `files` WHERE `size` <> LENGTH(`content`);";
        break;
        case 'questionnaire':
            $sql = "SELECT count(1) AS cnt FROM `questionnaire_files` WHERE `size` <> LENGTH(`content`);";
        break;
        case 'all':
        default:
            $sql = "
                SELECT sum(u.cnt) FROM
                    (SELECT count(1) AS cnt FROM `compliance_files` WHERE `size` <> LENGTH(`content`)
                    UNION ALL
                    SELECT count(1) AS cnt FROM `files` WHERE `size` <> LENGTH(`content`)" .
                    (table_exists('questionnaire_files') ? "UNION ALL
                    SELECT count(1) AS cnt FROM `questionnaire_files` WHERE `size` <> LENGTH(`content`)" : "") . 
                ") u;
            ";
        break;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    $results = $stmt->fetch(PDO::FETCH_COLUMN);
    
    db_close($db);
    
    return $results && (int)$results > 0;
}

function get_files_with_encoding_issues($type = 'risk', $order_column = 0, $order_dir = "asc", $offset = 0, $page_size = -1) {
    
    $limit =  $page_size>0 ? " LIMIT {$offset}, {$page_size}" : "";

    $db = db_open();
    
    switch($type) {
        case 'compliance':

            if ($order_column == 2) {
                $order_column = "`u`.`name` {$order_dir}, `u`.`id` ASC";
            } elseif ($order_column == 1) {
                $order_column = "`u`.`ref_type` {$order_dir}";
            } else $order_column = "`u`.`name` {$order_dir}";

            $sql = "
                SELECT * FROM (
                    SELECT
                    	`f`.`ref_id` AS id,
                        `f`.`name` AS file_name,
                        `f`.`ref_type`,
                        `f`.`unique_name`,
                        `t`.`name`,
                        `t`.`status`
                    FROM
                    	`compliance_files` f
                    	INNER JOIN `framework_control_test_audits` t ON `f`.`ref_type` = 'test_audit' AND `f`.`ref_id` = `t`.`id`
                    WHERE
                    	`f`.`size` <> LENGTH(`content`)
                    UNION ALL
                    SELECT
                    	`f`.`ref_id` AS id,
                        `f`.`name` AS file_name,
                        `f`.`ref_type`,
                        `f`.`unique_name`,
                        `e`.`name`,
                        0 AS status
                    FROM
                    	`compliance_files` f
                    	INNER JOIN `document_exceptions` e ON `f`.`ref_type` = 'exceptions' AND `f`.`ref_id` = `e`.`value`
                    WHERE
                    	`f`.`size` <> LENGTH(`content`)
                    UNION ALL
                    SELECT
                    	`f`.`ref_id` AS id,
                        `f`.`name` AS file_name,
                        `f`.`ref_type`,
                        `f`.`unique_name`,
                        `d`.`document_name` AS name,
                        0 AS status
                    FROM
                    	`compliance_files` f
                    	INNER JOIN `documents` d ON `f`.`ref_type` = 'documents' AND `f`.`ref_id` = `d`.`id`
                    WHERE
                    	`f`.`size` <> LENGTH(`content`)
                ) u
                ORDER BY {$order_column}
            ";

        break;
        case 'risk':
            if ($order_column == 3) {
                $order_column = "`f`.`name` {$order_dir}, `r`.`id` ASC";
            } elseif ($order_column == 2) {
                $order_column = "`f`.`view_type` {$order_dir}";
            } elseif ($order_column == 1) {
                $order_column = encryption_extra() ? "`r`.`order_by_subject` {$order_dir}" : "`r`.`subject` {$order_dir}";
            } else $order_column = "`r`.`id` {$order_dir}";

            $sql = "
                SELECT
                    `r`.`id` as risk_id,
                    `r`.`subject`,
                    `f`.`unique_name`,
                    `f`.`name` AS file_name,
                    `f`.`view_type`
                FROM
                    `files` f
                    INNER JOIN `risks` r ON `r`.`id` = f.risk_id
                WHERE
                    `f`.`size` <> LENGTH(`f`.`content`)
                ORDER BY {$order_column}
            "; 
        break;
        case 'questionnaire':
            if ($order_column == 2) {
                $order_column = "`q`.`name` {$order_dir}, `t`.`id` ASC";
            } elseif ($order_column == 1) {
                $order_column = "`type` {$order_dir}";
            } else $order_column = "`q`.`name` {$order_dir}, `t`.`id` ASC";
            
            $sql = "
                SELECT
                    `t`.`token`,
                    `q`.`name`,
                    `f`.`unique_name`,
                    `f`.`name` AS file_name,
                    IF(`f`.`template_id` = 0 AND `f`.`question_id` = 0, 'Questionnaire', 'Answer') AS `type`
                FROM
                    `questionnaire_files` f
                    INNER JOIN `questionnaire_tracking` t ON `f`.`tracking_id` = `t`.`id`
                    INNER JOIN `questionnaires` q ON `t`.`questionnaire_id` = `q`.`id`
                WHERE
                    `f`.`size` <> LENGTH(`f`.`content`)
                ORDER BY {$order_column}
            ";
        break;
    }
    
    $stmt = $db->prepare("
        SELECT SQL_CALC_FOUND_ROWS t1.*
        FROM (
            {$sql}
        ) t1
        {$limit}
    ");
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetch()[0];
    
    db_close($db);
    
    return array($recordsTotal, $results);
}

function display_file_encoding_issues($type) {
    global $lang, $escaper;
    
    $tableID = "upload-encoding-issues-$type";
    
    echo "
        <table id=\"{$tableID}\" width=\"100%\" class=\"risk-datatable table table-bordered table-striped table-condensed\">
            <thead>
                <tr>";
    switch($type) {
        case 'risk':
            echo "
                    <th align='left' valign='top' width='5%'>".$escaper->escapeHtml($lang['ID'])."</th>
                    <th align='left' valign='top'>".$escaper->escapeHtml($lang['Subject'])."</th>
                    <th align='left' valign='top' width='10%'>".$escaper->escapeHtml($lang['AttachmentType'])."</th>";
            $data_list = ['id', 'subject', 'view_type'];
        break;
        case 'compliance':
            echo "
                    <th align='left' valign='top'>".$escaper->escapeHtml($lang['Name'])."</th>
                    <th align='left' valign='top' width='12%'>".$escaper->escapeHtml($lang['AttachmentType'])."</th>";
            $data_list = ['name', 'ref_type'];
        break;
        case 'questionnaire':
            echo "
                    <th align='left' valign='top'>".$escaper->escapeHtml($lang['QuestionnaireName'])."</th>
                    <th align='left' valign='top' width='12%'>".$escaper->escapeHtml($lang['AttachmentType'])."</th>";
            $data_list = ['name', 'type'];
        break;
    }
    
    echo "
                    <th align='left' valign='top' width='20%'>".$escaper->escapeHtml($lang['FileName'])."</th>
                    <th align='left' valign='top' width='17%'></th>
                    <th align='center' valign='top' width='5%'></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var datatableInstance_{$type} = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                pagingType: \"full_numbers\",
                dom : \"flrtip\",
                pageLength: pageLength,
                dom : \"flrti<'#view-all.view-all'>p\",
                createdRow: function(row, data, index){
                    var background = $('.background-class', $(row)).data('background');
                    $(row).find('td').addClass(background)
                },
                order: [[0, 'asc']],
                ajax: {
                    url: BASE_URL + '/api/upload_encoding_issue_fix/datatable?type=$type',
                    data: function(d){ },
                    complete: function(response){ }
                },
                columnDefs : [";
    foreach ($data_list as $target => $data) {
        echo "
                    {
                        'targets': [$target],
                        'data': '$data'
                    },";
    }

    echo "
                    {
                        'targets': [-3],
                        'data': 'file_name'
                    },
                    {
                        'targets': [-2],
                        'data': 'file_uploader',
                        'orderable': false
                    },
                    {
                        'targets': -1,
                        'data': null,
                        'defaultContent': '<button class=\"confirm upload-button\" style=\"padding: 2px 15px;\">" . $escaper->escapeHtml($lang['Upload']) . "</button>',
                        'orderable': false
                    }
                ]
            });
            
            // Add paginate options
            datatableInstance_{$type}.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');
                
                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
                
                if (datatableInstance_{$type}.page() == 0) {
                    // Reload the page when no more issues left so the page load code can
                    // run the wrap-up logic
                    if (datatableInstance_{$type}.rows( {page:'current'} ).count() == 0) {
                        setTimeout(function(){window.location=window.location;}, 1);
                    }
                } else {// get to the previous page in case we confirmed the last one from the page and it's not the first page
                    if (datatableInstance_{$type}.rows( {page:'current'} ).count() == 0) {
                        setTimeout(function(){datatableInstance_{$type}.page('previous').draw('page');}, 1);
                    }
                }
                
                $('#{$tableID} tbody').off('click', 'button.confirm');
                $('#{$tableID} tbody').on('click', 'button.confirm', function () {
                    var data = datatableInstance_{$type}.row($(this).closest('tr')).data();
                    var unique_name = data['unique_name'];
                    var file_upload = $('#file-upload-' + unique_name)[0];

                    if (!file_upload.files[0]) {
                        alert('" . $escaper->escapeHtml($lang['YouHaveToSelectAFileToUpload']) . "');
                        return false;
                    }

                    if (file_upload.files[0].size > " . (int)get_setting('max_upload_size') . ") {
                        alert('" . $escaper->escapeHtml($lang['UploadingFileTooBig']) . "');
                        return false;
                    }

                    var form_data = new FormData();
                    form_data.append('file', file_upload.files[0]);
                    form_data.append('type', '{$type}');
                    form_data.append('unique_name', unique_name);

                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/upload_encoding_issue_fix/file_upload',
                        cache: false,
                        contentType: false,
                        processData: false,
                        data : form_data,
                        success: function(data) {
                            if(data.status_message) {
                                showAlertsFromArray(data.status_message);
                            }
                            datatableInstance_{$type}.ajax.reload(null, false);
                        },
                        error: function(xhr,status,error) {
                            if(!retryCSRF(xhr, this)) {
                                if(xhr.responseJSON && xhr.responseJSON.status_message) {
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                });
            });

            // Add all text to View All button on bottom
            $('#{$tableID}_wrapper .view-all').html('".$escaper->escapeHtml($lang['ALL'])."');

            // View All
            $('#{$tableID}_wrapper .view-all').click(function() {
                var oSettings =  datatableInstance_{$type}.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance_{$type}.draw();
                $(this).addClass('current');
            });
                
            // Page event
            $('body').on('click', '#{$tableID}_paginate span > .paginate_button', function(){
                var index = $(this).attr('aria-controls').replace('DataTables_Table_', '');
                
                var oSettings =  datatableInstance_{$type}.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('#{$tableID}_wrapper .view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance_{$type}.draw();
                }
            });
        </script>
    ";
}

function get_encoding_issue_file_info($type, $unique_name) {
    $db = db_open();
    
    switch($type) {
        case 'compliance':
            $sql = "SELECT `id`, `ref_id`, `ref_type`, `version` FROM `compliance_files` WHERE `unique_name` = :unique_name;";
        break;
        case 'risk':
            $sql = "SELECT `risk_id`, `view_type` FROM `files` WHERE `unique_name` = :unique_name;";
        break;
        case 'questionnaire':
            $sql = "SELECT `id`, `tracking_id`, `template_id`, `parent_question_id`, `question_id` FROM `questionnaire_files` WHERE `unique_name` = :unique_name;";
        break;
    }

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR);
    $stmt->execute();
    
    $results = $stmt->fetch(PDO::FETCH_ASSOC);
    
    db_close($db);
    
    return $results;
}
/***************************************************************************************
 * END OF SECTION CONTAINING FUNCTIONS DEDICATED TO FIXING FILE UPLOAD ENCODING ISSUES *
 ***************************************************************************************/

/********************************************************************************
 * FUNCTION: ARRAY ORDERBY                                                      *
 * Reorders an array based on a column value                                    *
 * Ex: $sorted = array_orderby($data, 'volume', SORT_DESC, 'edition', SORT_ASC) *
 ********************************************************************************/
function array_orderby()
{
    $args = func_get_args();
    $data = array_shift($args);
    foreach ($args as $n => $field) {
        if (is_string($field)) {
            $tmp = array();
            foreach ($data as $key => $row)
                $tmp[$key] = $row[$field];
            $args[$n] = $tmp;
            }
    }
    $args[] = &$data;
    call_user_func_array('array_multisort', $args);
    return array_pop($args);
}
/********************************************
 * FUNCTION: GET DATABASE TABLE VALUE BY ID *
 *******************************************/
function get_table_value_by_id($table, $id)
{
    // Open the database connection
    $db = db_open();

    if(field_exists_in_table("value", $table)) {
        $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `value` = :id");
    } else {
        $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `id` = :id");
    }
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    db_close($db);
    return $result?$result["name"]:"";    
}
/************************************
 * FUNCTION: ADD CONTRIBUTING RISKS *
 ************************************/
function add_contributing_risks($table, $name, $contributing_risks_id="")
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    if($table == "likelihood"){
        $table_name = "contributing_risks_likelihood";
        $stmt = $db->prepare("SELECT max(`value`) FROM {$table_name}");
        $stmt->execute();
        $max_value = $stmt->fetch(PDO::FETCH_COLUMN);
        $value = $max_value+1;
        // Add a new value
        $stmt = $db->prepare("INSERT INTO {$table_name} (value, name) VALUES(:value, :name);");
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        $table_name = "contributing_risks_impact";
        $stmt = $db->prepare("SELECT max(`value`) FROM {$table_name} WHERE contributing_risks_id = :contributing_risks_id");
        $stmt->bindParam(":contributing_risks_id", $contributing_risks_id, PDO::PARAM_INT);
        $stmt->execute();
        $max_value = $stmt->fetch(PDO::FETCH_COLUMN);
        $value = $max_value+1;
        $stmt = $db->prepare("INSERT INTO {$table_name} (`contributing_risks_id`, `value`, `name`) VALUES (:contributing_risks_id, :value, :name);");
        $stmt->bindParam(":contributing_risks_id", $contributing_risks_id);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    write_log(1000, $_SESSION['uid'], "A new {$table} named \"".$escaper->escapeHtml($name)."\" was created by the \"" . $_SESSION['user'] . "\" user.");

    return $stmt->rowCount();
}
/***********************************************
 * FUNCTION: GET CONTRIBUTING RISKS LIKELIHOOD *
 ***********************************************/
function get_contributing_risks_likelihood_list()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `contributing_risks_likelihood` ORDER BY value DESC");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}
/*******************************************
 * FUNCTION: GET CONTRIBUTING RISKS IMPACT *
 ******************************************/
function get_contributing_risks_impact_list()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.*, t2.subject FROM `contributing_risks_impact` t1 LEFT JOIN `contributing_risks` t2 ON t1.contributing_risks_id = t2.id ORDER BY t2.id, value DESC");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}
/**********************************************************
 * FUNCTION: GET IMPACT VALUES FROM CONTRIBUTING RISKS ID *
 **********************************************************/
function get_impact_values_from_contributing_risks_id($id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `contributing_risks_impact` WHERE `contributing_risks_id` = :id ORDER BY id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}
/***************************
 * FUNCTION: GET MAX VALUE *
 ***************************/
function get_max_value($table, $field="value")
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT max({$field}) FROM {$table}");
    $stmt->execute();
    $max_value = $stmt->fetch(PDO::FETCH_COLUMN);

    return $max_value?$max_value:null;
}

/**
 * The `change_audit_log_localization_config` array holds the localization keys for field names used in the `get_changes()` function
 */
global $change_audit_log_localization_config;
$change_audit_log_localization_config = [
    'risk' => [],
    
    'test' => [ // as fields returned by the get_framework_control_test_by_id() function
        'test_frequency' => 'TestFrequency',
        'last_date' => 'LastTestDate',
        'next_date' => 'NextTestDate',
        'name' => 'TestName',
        'objective' => 'Objective',
        'test_steps' => 'TestSteps',
        'approximate_time' => 'ApproximateTime',
        'expected_results' => 'ExpectedResults',
        'desired_frequency' => 'DesiredFrequency',
        'status' => 'AuditStatus',
        'created_at' => 'CreatedDate',
        'additional_stakeholders' => 'AdditionalStakeholders',
        'tester_name' => 'Tester',
        'teams' => 'Teams',
    ],
    
    'audit' => [ // as fields returned by the get_framework_control_test_audit_by_id() function
        'test_frequency' => 'TestFrequency',
        'last_date' => 'LastTestDate',
        'next_date' => 'NextTestDate',
        'name' => 'TestName',
        'objective' => 'Objective',
        'test_steps' => 'TestSteps',
        'approximate_time' => 'ApproximateTime',
        'expected_results' => 'ExpectedResults',
        'desired_frequency' => 'DesiredFrequency',
        'status' => 'AuditStatus',
        'created_at' => 'CreatedDate',
        'tester_name' => 'Tester',
        'control_name' => 'ControlName',
        'control_owner' => 'ControlOwner',
        'framework_name' => 'FrameworkName',
        'test_result' => 'TestResult',
        'summary' => 'Summary',
        'test_date' => 'TestDate',
        'submitted_by' => 'SubmittedBy',
        'submission_date' => 'SubmissionDate',
        'additional_stakeholders' => 'AdditionalStakeholders',
        'teams' => 'Teams',
    ],

    'document' => [
        'submitted_by' => 'SubmittedBy',
        'updated_by' => 'UpdatedBy',
        'document_type' => 'DocumentType',
        'document_name' => 'DocumentName',
        'control_ids' => 'Controls',
        'framework_ids' => 'Frameworks',
        'parent' => 'ParentDocument',
        'document_status' => 'DocumentStatus',
        'creation_date' => 'CreationDate',
        'last_review_date' => 'LastReview',
        'review_frequency' => 'ReviewFrequency',
        'next_review_date' => 'NextReviewDate',
        'approval_date' => 'ApprovalDate',
        'document_owner' => 'DocumentOwner',
        'additional_stakeholders' => 'AdditionalStakeholders',
        'approver' => 'Approver',
        'team_ids' => 'Teams',
    ],

    'user' => [
        'enabled' => 'Enabled',
        'lockout' => 'AccountLockedOut',
        'type' => 'Type',
        'name' => 'FullName',
        'email' => 'EmailAddress',
        'role_id' => 'Role',
        'lang' => 'Language',
        'admin' => 'Admin',
        'multi_factor' => 'MultiFactorAuthentication',
        'change_password' => 'RequirePasswordChangeOnLogin',
        'manager' => 'Manager',
        'selected_business_unit' => 'BusinessUnit',
        'teams' => 'Teams',
        'permissions' => 'UserResponsibilities'
    ],
];

/**
 * Function: GET CHANGES
 * The function is used to get the list of changes of two objects(arrays) of `before` and `after` states 
 * Return value is based on the return_type parameter's value.
 *      1: Returns a string in the format of "`{$field_name}` (`{$before}` => `{$after}`)"
 *      2: Return the differences in an array in the format of [{'<changed field's name>': {'from': '<original value>', 'to': <new value>}}, ...]
 *      3: Both of the above in the format of [String, Array]
 * 
 * Example response: 
 *      1: "`Audit Status` (`Evidence Submitted / Pending Review` => `Pending Evidence from Control Owner`), `Tester` (`Admin` => `Josh Sokol`)"
 *      2: [{'Audit Status': {'from': 'Evidence Submitted / Pending Review', 'to': Pending Evidence from Control Owner}}, {'Tester': {'from': 'Admin', 'to': Josh Sokol}}]
 *      3: [
 *              "`Audit Status` (`Evidence Submitted / Pending Review` => `Pending Evidence from Control Owner`), `Tester` (`Admin` => `Josh Sokol`)",
 *              [{'Audit Status': {'from': 'Evidence Submitted / Pending Review', 'to': Pending Evidence from Control Owner}}, {'Tester': {'from': 'Admin', 'to': Josh Sokol}}]
 *         ]
 */
// When adding fields you can skip adding logic for fields that are displayed as-is, like name, subject, title, etc
// because they're just added to the differences as they are(if changed)
function get_changes($type, $before, $after, $return_type = 1) {

    if (!$before || !$after || !is_array($before) || !is_array($after)) {
        return '';
    }

    global $lang, $change_audit_log_localization_config;

    $diff_arr = [];
    $diff_str = [];

    foreach ($change_audit_log_localization_config[$type] as $field => $key) {
        if (isset($before[$field]) && isset($after[$field]) && $before[$field] !== $after[$field]) {

            // These can be handled together as the fields with the same name hold the same type of values
            if ($type === 'audit' || $type === 'test' || $type === 'document' || $type === 'user') {
                switch($field) {
                    case 'last_date':
                    case 'next_date':
                    case 'test_date':
                    case 'last_review_date':
                    case 'next_review_date':
                    case 'approval_date':
                        if ($before[$field]) {
                            $before[$field] = format_date($before[$field]);
                        }
                        if ($after[$field]) {
                            $after[$field] = format_date($after[$field]);
                        }
                    break;

                    case 'created_at':
                    case 'submission_date':
                    case 'creation_date':
                        if ($before[$field]) {
                            $before[$field] = format_datetime($before[$field]);
                        }
                        if ($after[$field]) {
                            $after[$field] = format_datetime($after[$field]);
                        }
                    break;

                    case 'control_owner':
                    case 'submitted_by':
                    case 'updated_by':
                    case 'document_owner':
                    case 'approver':
                    case 'manager':
                        if ($before[$field]) {
                            $before[$field] = get_name_by_value('user', $before[$field]);
                        }
                        else $before[$field] = "Unassigned";

                        if ($after[$field]) {
                            $after[$field] = get_name_by_value('user', $after[$field]);
                        }
                        else $after[$field] = "Unassigned";
                    break;

                    case 'additional_stakeholders':
                        if ($before[$field]) {
                            $before[$field] = get_names_by_multi_values('user', $before[$field]);
                        }
                        if ($after[$field]) {
                            $after[$field] = get_names_by_multi_values('user', $after[$field]);
                        }
                    break;

                    case 'teams':
                    case 'team_ids':
                        if ($before[$field]) {
                            $before[$field] = get_names_by_multi_values('team', $before[$field]);
                        }
                        if ($after[$field]) {
                            $after[$field] = get_names_by_multi_values('team', $after[$field]);
                        }
                    break;

                    case 'status':
                        if ($before[$field]) {
                            $before[$field] = get_name_by_value('test_status', $before[$field]);
                        }
                        if ($after[$field]) {
                            $after[$field] = get_name_by_value('test_status', $after[$field]);
                        }
                    break;

                    case 'document_status':
                        if ($before[$field]) {
                            $before[$field] = get_name_by_value('document_status', $before[$field]);
                        }
                        if ($after[$field]) {
                            $after[$field] = get_name_by_value('document_status', $after[$field]);
                        }
                    break;

                    case 'parent':
                        if ($before[$field]) {
                            // Get the document name
                            $document = get_document_by_id($before[$field]);
                            $before[$field] = $document['document_name'];
                        }
                        else $before[$field] = "--";

                        if ($after[$field]) {
                            // Get the document name
                            $document = get_document_by_id($after[$field]);
                            $after[$field] = $document['document_name'];
                                    }
                        else $after[$field] = "--";
                    break;

                    case 'framework_ids':
                        if ($before[$field]) {
                            $before[$field] = get_names_by_multi_values('frameworks', $before[$field]);
                        }
                        else $before[$field] = "--";

                        if ($after[$field]) {
                            $after[$field] = get_names_by_multi_values('frameworks', $after[$field]);
                        }
                        else $after[$field] = "--";
                    break;

                    case 'control_ids':
                        if ($before[$field]) {
                            $control_short_names = array();
                            $controls = get_framework_controls($before[$field]);
                            foreach($controls as $control)
                            {
                            	// Add the control name
                            	$control_short_names[] = $control['short_name'];
                            }
                            $before[$field] = implode(", ", $control_short_names);
                        }
                        else $before[$field] = "--";

                        if ($after[$field]) {
                            $control_short_names = array();
                            $controls = get_framework_controls($after[$field]);
                            foreach($controls as $control)
                            {
                                // Add the control name
                                $control_short_names[] = $control['short_name'];
                            }
                            $after[$field] = implode(", ", $control_short_names);
                        }
                        else $after[$field] = "--";
                    break;

                    case 'enabled':
                    case 'lockout':
                    case 'admin':
                    case 'multi_factor':
                    case 'change_password':
                        if (isset($before[$field])) {
                            $before[$field] = localized_yes_no($before[$field]);
                        }
                        if (isset($after[$field])) {
                            $after[$field] = localized_yes_no($after[$field]);
                        }
                    break;

                    case 'type':
                        $types = ['1' => 'SimpleRisk', '2' => 'LDAP', '3' => 'SAML'];
                        if ($before[$field]) {
                            $before[$field] = $types[$before[$field]];
                        }
                        if ($after[$field]) {
                            $after[$field] = $types[$after[$field]];
                        }
                    break;

                    case 'role_id':
                        if ($before[$field]) {
                            $before[$field] = get_name_by_value('role', $before[$field]);
                        }
                        if ($after[$field]) {
                            $after[$field] = get_name_by_value('role', $after[$field]);
                        }
                    break;

                    case 'lang':
                        if ($before[$field] || $after[$field]) {
                            // Open the database connection
                            $db = db_open();

                            // Update user
                            $stmt = $db->prepare("SELECT `name`, `full` FROM `languages`;");
                            $stmt->execute();

                            // Store the list in the array
                            $languages = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                            // Close the database connection
                            db_close($db);
                        }

                        if ($before[$field]) {
                            $before[$field] = $languages[$before[$field]];
                        } else $before[$field] = '-';
                        if ($after[$field]) {
                            $after[$field] = $languages[$after[$field]];
                        } else $after[$field] = '-';
                    break;

                    case 'selected_business_unit':
                        if (organizational_hierarchy_extra()) {
                            if ($before[$field]) {
                                $before[$field] = get_name_by_value('business_unit', $before[$field], '-', true);
                            }
                            if ($after[$field]) {
                                $after[$field] = get_name_by_value('business_unit', $after[$field], '-', true);
                            }
                        }
                    break;

                    case 'permissions':
                        if ($before[$field] || $after[$field]) {
                            // Open the database connection
                            $db = db_open();

                            // Update user
                            $stmt = $db->prepare("SELECT `key`, `name` FROM `permissions`;");
                            $stmt->execute();

                            // Store the list in the array
                            $permissions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                            // Close the database connection
                            db_close($db);
                        }

                        if ($before[$field]) {
                            $permission_names = [];
                            foreach ($before[$field] as $permission_key) {
                                $permission_names[] = $permissions[$permission_key];
                            }
                            $before[$field] = implode(', ', $permission_names);
                        } else $before[$field] = '-';

                        if ($after[$field]) {
                            $permission_names = [];
                            foreach ($after[$field] as $permission_key) {
                                $permission_names[] = $permissions[$permission_key];
                            }
                            $after[$field] = implode(', ', $permission_names);
                        } else $after[$field] = '-';
                    break;
                }
            }

            // return_type is 'array' or 'both'
            if ($return_type >= 2) {
                $diff_arr[$lang[$key]] = [
                    'from' => $before[$field],
                    'to' => $after[$field]
                ];
            }
            // return_type is 'string' or 'both'
            if ($return_type == 1 || $return_type == 3) {
                $diff_str[]= _lang('FieldChangeTemplate', [
                    'field_name' => $lang[$key],
                    'before' => $before[$field],
                    'after' => $after[$field]
                ], false);
            }
        }
    }

    if ($return_type == 1) {
        return implode(', ', $diff_str);
    }
    
    if ($return_type == 2) {
        return $diff_arr;
    }
    
    if ($return_type == 3) {
        return [implode(', ', $diff_str), $diff_arr];
    }
}

/*****************************
 * FUNCTION: CREATE ZIP FILE *
 *****************************/
function create_zip_file($source, $destination)
{
    // Set the memory limit to 1 GB
    ini_set('memory_limit', '1024M');

    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file)
        {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;

            $file = realpath($file);

            if (is_dir($file) === true)
            {
		// Add the directory to the zip
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            else if (is_file($file) === true)
            {
		// Add the file to the zip
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    }
    else if (is_file($source) === true)
    {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}

/****************************************
 * FUNCTION: GET RISK SUBJECTS FROM IDS *
 ****************************************/
function get_risk_subjects_by_ids($ids="", $limit=4, $escape=false, $separate="<br>")
{
    global $escaper;

    if(!$ids){
        return "";
    }

    if (is_array($ids))
        $idArray = $ids;
    else
        $idArray = explode(",", $ids);

    foreach($idArray as &$id){
        $id = intval($id);
    }
    unset($id);

    // Open the database connection
    $db = db_open();

    // Update user
    $stmt = $db->prepare("SELECT * FROM `risks` WHERE id in (" . implode(",", $idArray) . "); ");
    $stmt->execute();

    // Store the list in the array
    $risks = $stmt->fetchAll();
    // Close the database connection
    db_close($db);

    $subjects = array();
    $count = 0;
    foreach($risks as $risk){
        $subject = "(" . ($risk['id'] + 1000) . ") " . try_decrypt($risk['subject']);
        $subjects[] = $escape ? $escaper->escapeHtml($subject) : $subject;
        $count++;
        if ($count == $limit)
            break;
    }

    return implode($separate, $subjects) . (count($risks) > $limit ? $separate."...": "");
}


/********************************************************
* RENDER CRUD UI										*
* Using the provided table configuration it renders the	*
* core of the management UI for CRUD functionality		*
*********************************************************/
function renderCRUDUI($tableConfig) {
    
    global $lang, $escaper;
    
    echo "
        <div class='row-fluid table-selection'>
            <b>{$escaper->escapeHtml($lang['Select'])}: </b>
            <select id='table-sections'>";
    foreach($tableConfig as $table => $config){
        echo "
                <option value='{$table}'>{$escaper->escapeHtml($lang[$config['headerKey']])}</option>\n";
    }
    echo "
            </select>
        </div>
        <div class='row-fluid'>
            <div id='crud-wrapper' class='span12'>";
    $text_change = $escaper->escapeHtml($lang['Change']);
    $text_to = $escaper->escapeHtml($lang['to']);
    $text_update = $escaper->escapeHtml($lang['Update']);
    $text_add = $escaper->escapeHtml($lang['Add']);
    $text_delete = $escaper->escapeHtml($lang['Delete']);
    $text_deleteItem = $escaper->escapeHtml($lang['DeleteItemNamed']);
    $text_addItem = $escaper->escapeHtml($lang['AddNewItemNamed']);
    
    $display = true;
    foreach ($tableConfig as $table => $config) {
        
        echo "
                <div class='hero-unit' data-table_name='{$table}' style='" . ($display ? 'display: block;' : 'display: none;' ) . "'>\n
                    <h4>" . $escaper->escapeHtml($lang[$config['headerKey']]) . ":</h4>\n
                    " . $text_addItem . ":&nbsp;&nbsp;<input id='" . $table . "_new' type='text' maxlength='" . $config['lengthLimit'] . "' size='20' />&nbsp;&nbsp;<input type='submit' value=" .  $text_add . " data-action='add' /><br />\n
                    " . $text_change . "&nbsp;&nbsp;";
        create_dropdown($table, NULL, $table . "_update_from");
        echo $text_to . "&nbsp;<input id='" . $table . "_update_to' type='text' maxlength='" . $config['lengthLimit'] . "' size='20' />&nbsp;&nbsp;<input type='submit' value='" . $text_update . "' data-action='update' /><br />" . $text_deleteItem . ":&nbsp;&nbsp;";
        create_dropdown($table, NULL, $table . "_delete");
        echo "
                    &nbsp;&nbsp;<input type='submit' value='" . $text_delete . "' data-action='delete' />
                </div>";
        
        $display = false;
    }
    echo "
            </div>
        </div>
        <script>
        
            function refreshDropdown(dropdown, data) {
                dropdown.empty();
                dropdown.append($('<option>', {
                    value: 0,
                    text : '--'
                }));
                $.each(data, function (i, item) {
                    dropdown.append($('<option>', {
                        value: item.value,
                        text : item.name
                    }));
                });
            }
                        
            function crudAction() {
                        
                var div = $(this).closest('div');
                if (div) {
                    var tableName = div.data('table_name');
                    var action = $(this).data('action');
                        
                    if (tableName && action) {
                        $.ajax({
                            type: 'POST',
                            url: window.location.href,
                            data: (function() {
                                var d = new Object();
                                d.table_name = tableName;
                                d.action = action;
                        
                                switch(action) {
                                    case 'add':
                                        d.name = div.find('#' + tableName + '_new').val();
                                    break;
                                    case 'update':
                                        d.id = div.find('#' + tableName + '_update_from').val();
                                        d.name = div.find('#' + tableName + '_update_to').val();
                                    break;
                                    case 'delete':
                                        d.id = div.find('#' + tableName + '_delete').val();
                                    break;
                                }
                        
                                return d;
                            })(),
                        
                            success: function(data){
                                if(data.status_message){
                                    showAlertsFromArray(data.status_message);
                                }
                        
                                // Empty input boxes
                                div.find('#' + tableName + '_new').val('');
                                div.find('#' + tableName + '_update_to').val('');
                        
                                // Refresh dropdowns
                                refreshDropdown(div.find('#' + tableName + '_update_from'), data.data);
                                refreshDropdown(div.find('#' + tableName + '_delete'), data.data);
                            },
                            error: function(xhr,status,error){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                                if(!retryCSRF(xhr, this))
                                {
                                }
                            }
                        });
                    }
                }
            }
                        
            $(document).ready(function() {
                $('#crud-wrapper input[type=submit]').click(crudAction);
                        
                $('#table-sections').change(function(){
                    $('#crud-wrapper > .hero-unit').hide();
                    $('#crud-wrapper > [data-table_name=\'' + $(this).val()+'\']').show();
                })
            });
                        
        </script>";
}


/********************************************************************************************************************
* PROCESS CRUD ACTION																								*
* Provided with the $tableConfig and the other data coming from the UI rendered by the 'renderCRUDUI()' function	*
* this function can execute the required CRUD action																*
*********************************************************************************************************************/
function processCRUDAction($tableConfig, $action, $tableKey, $id, $name) {
    
    global $lang;
    
    if (in_array($action, array('add', 'update', 'delete')) && array_key_exists($tableKey, $tableConfig)) {
        
        if (in_array($action, array('add', 'update'))) {
            if (!isset($name) || !trim($name)) {
                set_alert(true, "bad", $lang['YouNeedToSpecifyANameParameter']);
                return false;
            } else {
                $name = trim($name);
                
                $lengthLimit = $tableConfig[$tableKey]['lengthLimit'];
                // Size check
                if (strlen($name) > $lengthLimit) {
                    // As we render the UI controls with the same limits we shouldn't see this message
                    set_alert(true, "bad", _lang('TheEnteredValueIsTooLong', ['limit' => $lengthLimit]));
                    return false;
                }
            }
        }

        if (in_array($action, array('update', 'delete'))) {
            if (!isset($id) || !trim($id) || !preg_match('/^\d+$/', trim($id))) {
                set_alert(true, "bad", $lang['YouNeedToSpecifyAnIdParameter']);
                return false;
            } else {
                $id = (int)trim($id);
            }
        }

        switch ($action) {
            case "add":
                // If the custom add function is set
                if (array_key_exists('customAddFunction', $tableConfig[$tableKey])) {
                    // Call it with the required parameters
                    $result = $tableConfig[$tableKey]['customAddFunction']($name);
                } else {
                    // Insert a new item
                    $result = add_name($tableConfig[$tableKey]['table'], $name);
                }
                
                // Display an alert
                if ($result) {
                    set_alert(true, "good", $lang['ANewItemWasAddedSuccessfully']);
                } else {
                    set_alert(true, "bad", $lang['FailedToAddNewItem']);
                }
            break;
                        
            case "update":
                // If the custom update function is set
                if (array_key_exists('customUpdateFunction', $tableConfig[$tableKey])) {
                    // Call it with the required parameters
                    $result = $tableConfig[$tableKey]['customUpdateFunction']($id, $name);
                } else {
                    $result = update_table($tableConfig[$tableKey]['table'], $name, $id);
                }
                
                // Display an alert
                if ($result) {
                    set_alert(true, "good", $lang['AnItemWasUpdatedSuccessfully']);
                } else {
                    set_alert(true, "bad", $lang['FailedToUpdateItem']);
                }
            break;
                        
            case "delete":
                // If the custom delete function is set
                if (array_key_exists('customDeleteFunction', $tableConfig[$tableKey])) {
                    // Call it with the required parameters
                    $result = $tableConfig[$tableKey]['customDeleteFunction']($id);
                } else {
                    $result = delete_value($tableConfig[$tableKey]['table'], $id);
                }
                
                // Display an alert
                if ($result) {
                    set_alert(true, "good", $lang['AnItemWasDeletedSuccessfully']);
                } else {
                    set_alert(true, "bad", $lang['FailedToDeleteItem']);
                }
            break;
        }
        
        // It's on purpose that it's getting the data by the key, so if it's a special table, then it can be treated as such
        return get_options_from_table($tableKey);
    } else {
        // Didn't want to put anything informative here as this message will only be
        // seen if someone is calling this with forged data
        set_alert(true, "bad", $lang['MissingConfiguration']);
        return false;
    }
}
/********************************
 * FUNCTION: CHECK IF VALID URL *
 ********************************/
function check_if_valid_url($url)
{
	// Return the result of the filter validate
	return filter_var($url, FILTER_VALIDATE_URL);
}

/***********************************
 * FUNCTION: CHECK IF URL RESPONDS *
 ***********************************/
function check_if_url_responds($url)
{
	// If this is a valid URL
	if (check_if_valid_url($url))
	{
		// Do a curl for the URL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'curlHeaderCallback');
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_exec($ch);
		$return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// If the URL call was successful
		if ($return_code == 200 || $return_code == 302 || $return_code == 304)
		{
			return true;
		}
	}

	// If the other checks don't pass, then return false
	return false;

}

/************************************
 * FUNCTION: UPLOAD VALIDATION FILE *
 ************************************/
function upload_validation_file($mitigation_id, $control_id, $file)
{
    // Open the database connection
    $db = db_open();

    // Get the list of allowed file types
    $stmt = $db->prepare("SELECT `name` FROM `file_types`");
    $stmt->execute();
    $file_types = $stmt->fetchAll();

    // Get the list of allowed file extensions
    $stmt = $db->prepare("SELECT `name` FROM `file_type_extensions`");
    $stmt->execute();
    $file_type_extensions = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Create an array of allowed types
    foreach ($file_types as $key => $row)
    {
        $allowed_types[] = $row['name'];
    }

    // Create an array of allowed extensions
    foreach ($file_type_extensions as $key => $row)
    {
        $allowed_extensions[] = $row['name'];
    }

    // If a file was submitted and the name isn't blank
    if (isset($file) && $file['name'] != "")
    {
        // If the file type is appropriate
        if (in_array($file['type'], $allowed_types))
        {
            // If the file extension is appropriate
            if (in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), array_map('strtolower', $allowed_extensions)))
            {
            // Get the maximum upload file size
            $max_upload_size = get_setting("max_upload_size");

            // If the file size is less than 5MB
            if ($file['size'] < $max_upload_size)
            {
                // If there was no error with the upload
                if ($file['error'] == 0)
                {
                    // Read the file
                    $content = fopen($file['tmp_name'], 'rb');

                    // Create a unique file name
                    $unique_name = generate_token(30);

                    // Open the database connection
                    $db = db_open();
                    $timestamp = date("Y-m-d H:i:s");

                    // Store the file in the database
                    $stmt = $db->prepare("INSERT INTO validation_files (mitigation_id, control_id, name, type, size, user, timestamp, content) VALUES (:mitigation_id, :control_id, :name, :type, :size, :user, :timestamp, :content)");
                    $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
                    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
                    $stmt->bindParam(":name", $file['name'], PDO::PARAM_STR, 30);
                    $stmt->bindParam(":type", $file['type'], PDO::PARAM_STR, 30);
                    $stmt->bindParam(":size", $file['size'], PDO::PARAM_INT);
                    $stmt->bindParam(":user", $_SESSION['uid'], PDO::PARAM_INT);
                    $stmt->bindParam(":timestamp", $timestamp, PDO::PARAM_STR);
                    $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
                    $stmt->execute();

                    // Close the database connection
                    db_close($db);

                    // Return a success
                    return 1;

                }
                // Otherwise
                else
                {
                    switch ($file['error'])
                    {
                        case 1:
                            return "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                            break;
                        case 2:
                            return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                            break;
                        case 3:
                            return "The uploaded file was only partially uploaded.";
                            break;
                        case 4:
                            return "No file was uploaded.";
                            break;
                        case 6:
                            return "Missing a temporary folder.";
                            break;
                        case 7:
                            return "Failed to write file to disk.";
                            break;
                        case 8:
                            return "A PHP extension stopped the file upload.";
                            break;
                        default:
                            return "There was an error with the file upload.";
                    }
                }
            }
            else return "The uploaded file was too big to store in the database.  A SimpleRisk administrator can modify the maximum file upload size under \"File Upload Settings\" under the \"Configure\" menu.  You may also need to modify the 'upload_max_filesize' and 'post_max_size' values in your php.ini file.";
            }
            else return "The file extension of the uploaded file (" . pathinfo($file['name'], PATHINFO_EXTENSION) . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
        }
        else return "The file type of the uploaded file (" . $file['type'] . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
    }
    else return 1;
}

/**********************************
 * FUNCTION: GET VALIDATION FILES *
 **********************************/
function get_validation_files($mitigation_id, $control_id)
{
    // Open the database connection
    $db = db_open();
    // Query the database
    $stmt = $db->prepare("SELECT id, name FROM validation_files WHERE mitigation_id=:mitigation_id AND control_id=:control_id");
    $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $files;
}

/**********************************************************
 * FUNCTION: Delete validation files except current files *
 **********************************************************/
function refresh_files_for_validation($mitigation_id, $control_id, $file_ids)
{
    //if(!count($file_ids)) return false;
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DELETE FROM validation_files WHERE mitigation_id=:mitigation_id and control_id=:control_id AND id NOT IN ('" . implode("','", $file_ids) . "')");
    $stmt->bindParam(":mitigation_id", $mitigation_id, PDO::PARAM_INT);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    return 1;
}

/*************************************
 * FUNCTION: CVSS2 BASE VECTOR SPLIT *
 *************************************/
function cvss2_base_vector_split($cvss_base_vector)
{
	// Create an empty CVSS array
	$cvss_array = [];

        // If a CVSS base vector is set
	if (isset($cvss_base_vector) && !is_null($cvss_base_vector) && $cvss_base_vector != "")
	{
		// Split the CVSS base vector by the slash
		$cvss = explode("/", $cvss_base_vector);

		// For each CVSS value
		foreach ($cvss as $vector)
        	{
                	// Split the vector by the colon
                	$vector_split = explode(":", $vector);
                	$vector_name = $vector_split[0];
                	$vector_value = (isset($vector_split[1]) ? $vector_split[1] : null);

                	switch($vector_name)
                	{
                        	case "AV":
					$cvss_array['AccessVector'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "N");
                        	        break;
                        	case "AC":
					$cvss_array['AccessComplexity'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "L");
                        	        break;
                        	case "Au":
					$cvss_array['Authentication'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "N");
                        	        break;
                        	case "C":
					$cvss_array['ConfidentialityImpact'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "C");
                        	        break;
                        	case "I":
					$cvss_array['IntegrityImpact'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "C");
                        	        break;
                        	case "A":
					$cvss_array['AvailabilityImpact'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "C");
                        	        break;
                 	}
		}
        }
        // Otherwise a CVSS base vector is not set
        else
        {       
                $cvss_array['AccessVector'] = "N";
                $cvss_array['AccessComplexity'] = "L";
                $cvss_array['Authentication'] = "N";
                $cvss_array['ConfidentialityImpact'] = "C";
                $cvss_array['IntegrityImpact'] = "C";
                $cvss_array['AvailabilityImpact'] = "C";
        }

	// Return the CVSS array
	return $cvss_array;
}

/*****************************************
 * FUNCTION: CVSS2 TEMPORAL VECTOR SPLIT *
 *****************************************/
function cvss2_temporal_vector_split($cvss_temporal_vector)
{
        // Create an empty CVSS array
        $cvss_array = [];

	// If a CVSS temporal vector is set
	if (isset($cvss_temporal_vector) && !is_null($cvss_temporal_vector) && $cvss_temporal_vector != "")
	{
        	// Split the CVSS temporal vector by the slash
        	$cvss = explode("/", $cvss_temporal_vector);

        	// For each CVSS value
        	foreach ($cvss as $vector)
        	{
                	// Split the vector by the colon
                	$vector_split = explode(":", $vector);
                	$vector_name = $vector_split[0];
                	$vector_value = (isset($vector_split[1]) ? $vector_split[1] : null);

                	switch($vector_name)
                	{
				case "E":
					$cvss_array['Exploitability'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "ND");
					break;
				case "RL":
					$cvss_array['RemediationLevel'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "ND");
					break;
				case "RC":
					$cvss_array['ReportConfidence'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "ND");
					break;
                 	}
        	}
	}
	// Otherwise, a CVSS temporal vector is not set
	else
	{
		// Set the temporal values to not defined
		$cvss_array['Exploitability'] = "ND";
		$cvss_array['RemediationLevel'] = "ND";
		$cvss_array['ReportConfidence'] = "ND";
	}

        // Return the CVSS array
        return $cvss_array;
}

/*************************************
 * FUNCTION: CVSS3 BASE VECTOR SPLIT *
 *************************************/
function cvss3_base_vector_split($cvss_base_vector)
{
        // Create an empty CVSS array
        $cvss_array = [];

        // If a CVSS base vector is set
        if (isset($cvss_base_vector) && !is_null($cvss_base_vector) && $cvss_base_vector != "")
        {
        	// Split the CVSS base vector by the slash
        	$cvss = explode("/", $cvss_base_vector);

        	// For each CVSS value
        	foreach ($cvss as $vector)
        	{
                	// Split the vector by the colon
                	$vector_split = explode(":", $vector);
                	$vector_name = $vector_split[0];
                	$vector_value = (isset($vector_split[1]) ? $vector_split[1] : null);

                	switch($vector_name)
                	{
                        	case "AV":
                        	        $cvss_array['AttackVector'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "N");
                        	        break;
                        	case "AC":
                        	        $cvss_array['AttackComplexity'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "L");
                        	        break;
				case "PR":
					$cvss_array['PrivilegesRequired'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "N");
					break;
				case "UI":
					$cvss_array['UserInteraction'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "N");
					break;
				case "S":
					$cvss_array['Scope'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "C");
					break;
                        	case "C":
                        	        $cvss_array['ConfidentialityImpact'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "C");
                        	        break;
                        	case "I":
                        	        $cvss_array['IntegrityImpact'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "C");
                        	        break;
                        	case "A":
                        	        $cvss_array['AvailabilityImpact'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "C");
                        	        break;
                 	}
		}
        }
	// Otherwise a CVSS base vector is not set
	else
	{
        	$cvss_array['AttackVector'] = "N";
		$cvss_array['AttackComplexity'] = "L";
		$cvss_array['PrivilegesRequired'] = "N";
		$cvss_array['UserInteraction'] = "N";
		$cvss_array['Scope'] = "C";
		$cvss_array['ConfidentialityImpact'] = "C";
		$cvss_array['IntegrityImpact'] = "C";
		$cvss_array['AvailabilityImpact'] = "C";
	}

        // Return the CVSS array
        return $cvss_array;
}

/*****************************************
 * FUNCTION: CVSS3 TEMPORAL VECTOR SPLIT *
 *****************************************/
function cvss3_temporal_vector_split($cvss_temporal_vector)
{
        // Create an empty CVSS array
        $cvss_array = [];

        // If a CVSS temporal vector is set
        if (isset($cvss_temporal_vector) && !is_null($cvss_temporal_vector) && $cvss_temporal_vector != "")
        {
                // Split the CVSS temporal vector by the slash
                $cvss = explode("/", $cvss_temporal_vector);

                // For each CVSS value
                foreach ($cvss as $vector)
                {
                        // Split the vector by the colon
                        $vector_split = explode(":", $vector);
                        $vector_name = $vector_split[0];
                        $vector_value = (isset($vector_split[1]) ? $vector_split[1] : null);

                        switch($vector_name)
                        {
                                case "E":
                                        $cvss_array['ExploitCodeMaturity'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "X");
                                        break;
                                case "RL":
                                        $cvss_array['RemediationLevel'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "X");
                                        break;
                                case "RC":
                                        $cvss_array['ReportConfidence'] = ((isset($vector_value) && !is_null($vector_value)) ? $vector_value : "X");
                                        break;
                        }
                }
        }
        // Otherwise, a CVSS temporal vector is not set
        else
        {
                // Set the temporal values to not defined
                $cvss_array['ExploitCodeMaturity'] = "X";
                $cvss_array['RemediationLevel'] = "X";
                $cvss_array['ReportConfidence'] = "X";
        }

        // Return the CVSS array
        return $cvss_array;
}

/*****************************
 * FUNCTION: ADD NEW PROJECT *
 *****************************/
function add_project($project){

    $name = isset($project['name']) ? try_encrypt($project['name']) : "";
    $due_date = isset($project['due_date']) ? $project['due_date'] : "";
    $consultant = isset($project['consultant']) ? $project['consultant'] : 0;
    $business_owner = isset($project['business_owner']) ? $project['business_owner'] : 0;
    $data_classification = isset($project['data_classification']) ? $project['data_classification'] : 0;


    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("INSERT INTO `projects` (`name`, `due_date`, `consultant`, `business_owner`, `data_classification`) VALUES (:name, :due_date, :consultant, :business_owner, :data_classification)");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":due_date", $due_date, PDO::PARAM_STR);
    $stmt->bindParam(":consultant", $consultant, PDO::PARAM_INT);
    $stmt->bindParam(":business_owner", $business_owner, PDO::PARAM_INT);
    $stmt->bindParam(":data_classification", $data_classification, PDO::PARAM_INT);
    $stmt->execute();
    
    $project_id = $db->lastInsertId();

    $message = "A new prject named \"{$name}\" was created by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, "project");
    
    // Close the database connection
    db_close($db);

    return $project_id;
}
/*****************************
 * FUNCTION: UPDATE PROJECT  *
 *****************************/
function update_project($proejct_id, $project){

    $name = isset($project['name']) ? try_encrypt($project['name']) : "";
    $due_date = isset($project['due_date']) ? $project['due_date'] : "";
    $consultant = isset($project['consultant']) ? $project['consultant'] : 0;
    $business_owner = isset($project['business_owner']) ? $project['business_owner'] : 0;
    $data_classification = isset($project['data_classification']) ? $project['data_classification'] : 0;


    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("UPDATE `projects` SET `name`=:name, `due_date`=:due_date, `consultant`=:consultant, `business_owner`=:business_owner, `data_classification`=:data_classification WHERE value=:id;)");
    $stmt->bindParam(":id", $proejct_id, PDO::PARAM_INT);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":due_date", $due_date, PDO::PARAM_STR);
    $stmt->bindParam(":consultant", $consultant, PDO::PARAM_INT);
    $stmt->bindParam(":business_owner", $business_owner, PDO::PARAM_INT);
    $stmt->bindParam(":data_classification", $data_classification, PDO::PARAM_INT);
    $stmt->execute();

    $message = "A prject named \"{$name}\" was updated by username \"" . $_SESSION['user'] . "\".";
    //write_log(1000, $_SESSION['uid'], $message, "project");
    
    // Close the database connection
    db_close($db);

    return true;
}
/*******************************
 * FUNCTION: GET PROJECT BY ID *
 *******************************/
function get_project($id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `projects` WHERE value=:id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $custom_values = get_custom_value_by_row_id($id, "project");
        $project['custom_values'] = $custom_values;
    }
    
    return $project;
}


/**********************************
 * FUNCTION: NAME EXISTS IN TABLE *
 **********************************/
function name_exists_in_table($name, $table, $where="")
{
    // Open the database connection
    $db = db_open();

    // Check if the name is in the database
    $sql = "SELECT * FROM {$table} WHERE name=:name";
    if($where) $sql .= " AND ".$where;
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return $row;
}

// To purify plain html, no template variables
function purify_html($html) {

    // To prevent doing unnecessary work
    if (empty($html)) {
        return $html;
    }

    // To make sure it's only created once even when ran in a loop
    if (!isset($GLOBALS['DEFAULT_HTML_PURIFIER'])) {
        $GLOBALS['DEFAULT_HTML_PURIFIER'] = new HTMLPurifier();
    }
    return $GLOBALS['DEFAULT_HTML_PURIFIER']->purify($html);
}

// To purify html with template variables
function purify_html_template($html) {
    // To make sure it's only created once even when ran in a loop
    if (!isset($GLOBALS['HTML_TEMPLATE_PURIFIER'])) {
        $config = HTMLPurifier_Config::createDefault();
        $def = $config->getHTMLDefinition(true);
        // Adding the 'id' data attribute to the allowed attribute list
        $def->addAttribute('span', 'data-id', 'Text');
        $GLOBALS['HTML_TEMPLATE_PURIFIER'] = new HTMLPurifier($config);
    }
    return $GLOBALS['HTML_TEMPLATE_PURIFIER']->purify($html);
}

// Populates the template with the variable's value, replacing the variable definitions with the actual value
// Only escaping when it's required as if the variable's value is html then it shouldn't be escaped
function populate_core_template_variable($template, $variable, $value, $escape = true) {
    if ($value) {
        if ($escape) {
            global $escaper;
            $value = $escaper->escapeHtml($value);
        }
        
        // Escaping the $ so the preg_replace is not treating $500 as the 500th capture group's value
        $value = str_replace('$', '\$', $value);
    } else {
        $value = '-';
    }
    
    return preg_replace('@<span class="variable" data-id="' . $variable . '">.+?</span>@', $value, $template);
}

// Strips tags and removes extra whitespaces
function strip_tags_and_extra_whitespace($html) {
    return trim(preg_replace("/[\r\n|\n|\s]{2,}/", "\n", strip_tags($html)));
}

// To create a selectize dropdown. Add your own configuration and change the javascript configuration of the selectize widget accordingly
// just please make sure the the already existing usecases keep working.
// Added the 'additional_info' variable to be able to pass on additional information without having to add myriads of extra parameters
function create_selectize_dropdown($type, $selected_values, $additional_info = false) {
    global $escaper, $lang;

    switch($type) {
        case 'risk_catalog' :
            $name = 'risk_catalog_mapping';
            $required = get_setting('risk_mapping_required') == 1;
            $option_type = 'risk_catalog_grouped';
            $multiple = true;
            $grouped = true;
            $placeholder = $lang['RiskCatalogDropdownPlaceholder'];
            break;
        case 'threat_catalog' :
            $name = 'threat_catalog_mapping';
            $required = false;
            $option_type = 'threat_catalog_grouped';
            $multiple = true;
            $grouped = true;
            $placeholder = $lang['ThreatCatalogDropdownPlaceholder'];
            break;
        case 'enabled_users' :
            $name = !empty($additional_info['name']) ? $additional_info['name'] : 'enabled_users';
            $required = !empty($additional_info['required']) ? $additional_info['required'] : false;
            $option_type = 'enabled_users';
            $multiple = !empty($additional_info['multiple']) ? $additional_info['multiple'] : false;
            $grouped = false;
            $placeholder = !empty($additional_info['placeholder']) ? $additional_info['placeholder'] : $lang['UserDropdownPlaceholder'];
            break;
    }

    $options = get_options_from_table($option_type);

    echo "
                <select" . ($required ? " required" : "") . " name='{$name}" . ($multiple ? "[]' multiple='multiple'" : "'") . " id='{$name}'></select>
                <script>
                    $(document).ready(function(){
                        $('[name=\"{$name}".($multiple ? "[]" : "")."\"]').selectize({
                            plugins: ['remove_button'],
                            searchField: " . ($grouped ? "['name', 'class']" : "'name'") . ",
                            valueField: 'value',
                            labelField: 'name',
                            create: false,
                            persist: false,
                            placeholder: '{$escaper->escapeHtml($placeholder)}',
                            options: [";
    if ($grouped) {
        $groups = [];
        foreach($options as $group_name => $group_entries) {
            $group_name = $escaper->escapeHtml($group_name ? $group_name : '[' . $lang['NoGroup'] . ']');
            $groups[] = $group_name;
            foreach($group_entries as $group_entry) {
                echo "
                                {class: '{$group_name}', value: '{$escaper->escapeHtml($group_entry['value'])}', name: '{$escaper->escapeHtml($group_entry['name'])}'},";
            }
        }

        echo "
                            ],
                            optgroupField: 'class',
                            optgroupLabelField: 'label',
                            optgroupValueField: 'value',
                            optgroups: [";

        foreach($groups as $group) {
            echo "
                                {value: '{$group}', label: '{$group}'},";
        }
    } else {
        foreach($options as $option) {
            echo "
                                {value: '{$escaper->escapeHtml($option['value'])}', name: '{$escaper->escapeHtml($option['name'])}'},";
        }
    }

    echo "
                            ],
    ";

    if (!$multiple) {
        echo "
                            maxItems: 1,
        ";
    }

    // Select the selected items
    if(!empty($selected_values)) {

        if (!is_array($selected_values)) {
            $selected_values = [$selected_values];
        }

        $selected_values = sanitize_int_array($selected_values);

        echo "
                            items: [" . implode(', ', $selected_values) . "],";
    }

    echo "
                            render: {
                                optgroup_header: function (data) {
                                    return $('<div>', {class: 'optgroup-header'}).html(data.label);
                                },
                                option: function (data) {
                                    return $('<div>', {class: 'option'}).html(data.name);
                                },
                                item: function (data) {
                                    // Returning an html as apparently the 'remove_button' plugin doesn't like when this function returns a dom element
                                    return $('<div>', {class: 'item'}).html(data.name)[0].outerHTML;
                                }
                            }
                        });
                    });
                </script>
    ";
}

// A function that filters out elements of the array that are not positive integers.
function sanitize_int_array($int_array) {

    if (empty($int_array) || !is_array($int_array)) {
        return [];
    }

    return array_filter($int_array, function($id){return ctype_digit((string)$id);});
}
function reassign_groupless_risk_catalogs($default_group_id = false) {

    $db = db_open();
    
    if (!$default_group_id) {
        // Get value of the default risk group
        $stmt = $db->prepare("SELECT `value` FROM `risk_grouping` WHERE `default` = 1");
        $stmt->execute();
        
        $default_group_id = (int)$stmt->fetchColumn();
    }

    // Find risk catalog items that have no group assigned
    $stmt = $db->prepare("
            SELECT
            	`id`
            FROM `risk_catalog` rc
            	LEFT JOIN `risk_grouping` rg ON `rg`.`value` = `rc`.`grouping`
            WHERE
            	`rg`.`value` IS NULL;
        ");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Assign risk catalog items that have no group assigned to this new default group
    foreach ($data as $id) {
        $stmt = $db->prepare("UPDATE `risk_catalog` SET `grouping` = :group WHERE `id` = :id;");
        $stmt->bindParam(":group", $default_group_id, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}

function reassign_groupless_threat_catalogs($default_group_id = false) {

    $db = db_open();

    if (!$default_group_id) {
        // Get value of the default risk group
        $stmt = $db->prepare("SELECT `value` FROM `threat_grouping` WHERE `default` = 1");
        $stmt->execute();

        $default_group_id = (int)$stmt->fetchColumn();
    }

    // Find threat catalog items that have no group assigned
    $stmt = $db->prepare("
            SELECT
            	`id`
            FROM `threat_catalog` tc
            	LEFT JOIN `threat_grouping` tg ON `tg`.`value` = `tc`.`grouping`
            WHERE
            	`tg`.`value` IS NULL;
        ");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Assign threat catalog items that have no group assigned to this new default group
    foreach ($data as $id) {
        $stmt = $db->prepare("UPDATE `threat_catalog` SET `grouping` = :group WHERE `id` = :id;");
        $stmt->bindParam(":group", $default_group_id, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}

/***************************************
 * FUNCTION: SAVE GRAPHICAL SELECTIONS *
 ***************************************/
function save_graphical_selections($type, $name, $graphic_form_data=[])
{
    global $escaper, $lang;

    $graphical_display_settings = json_encode($graphic_form_data);
    
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("INSERT INTO `graphical_saved_selections` (`user_id`, `type`, `name`, `graphical_display_settings`) VALUES (:user_id, :type, :name, :graphical_display_settings); ");
    
    $stmt->bindParam(":user_id", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":graphical_display_settings", $graphical_display_settings, PDO::PARAM_STR);
    $stmt->execute();

    $id = $db->lastInsertId();

    // Close the database connection
    db_close($db);

    $message = "The selections for Graphical Risk Analysis named \"" . $escaper->escapeHtml($name) . "\" was created by the \"" . $_SESSION['user'] . "\" user.";
    write_log(1000, $_SESSION['uid'], $message);

    return $id;
}

/********************************************
 * FUNCTION: DELETE GRAPHICAL SELECTION BY ID *
 ********************************************/
function delete_graphical_selection($id)
{
    global $escaper, $lang;

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DELETE FROM `graphical_saved_selections` WHERE value=:value; ");
    
    $stmt->bindParam(":value", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    $message = "The selections for Graphical Risk Analysis (ID : {$id}) was deleted by the \"" . $_SESSION['user'] . "\" user.";
    write_log(1000, $_SESSION['uid'], $message);
}

/***************************************************
 * FUNCTION: CHECK EXISTING GRAPHICAL SELECTION NAME *
 ***************************************************/
function check_exisiting_graphical_selection_name($user_id, $name)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `graphical_saved_selections` WHERE name=:name AND (type='private' AND user_id=:user_id || type='public');");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    return $array ? true: false;
}
/******************************************
 * FUNCTION: GET GRAPHICAL SAVED SELECTIONS *
 ******************************************/
function get_graphical_saved_selections($user_id)
{
    // Open the database connection
    $db = db_open();

    // If the requesting user is an admin then return all the saved selections
    // When returning other users' saved selections add the users' name to the saved selections' names
    // Results are ordered to have the user's own saved selections first,
    // then they're ordered to display private saved selections first then the publics 
    $stmt = $db->prepare("
        SELECT
            `dss`.`value`,
            IF(`u`.`value` <> :user_id, CONCAT(`dss`.`name`, ' (', `u`.`name`, ')'), `dss`.`name`) as name,
            `dss`.`type`,
            `dss`.`user_id`,
            `dss`.`graphical_display_settings`
        FROM
            `graphical_saved_selections` dss
            INNER JOIN `user` u ON `u`.`value` = `dss`.`user_id`
        WHERE
            `dss`.`type`='public'
            OR (`dss`.`type` = 'private' AND `dss`.`user_id` = :user_id)
            OR (SELECT `admin` FROM `user` WHERE `value` = :user_id) = 1
        ORDER BY
            `dss`.`user_id` <> :user_id, `dss`.`type` = 'public';
    ");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Get dynamic saved selections
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $array;
}

/**************************************************
 * FUNCTION: GET GRAPHICAL SAVED SELECTION BY VALUE *
 **************************************************/
function get_graphical_saved_selection($value)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT user_id, name, type, graphical_display_settings FROM `graphical_saved_selections` WHERE `value`=:value;");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Get dynamic saved selections
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);

    return $row;
}

/******************************************
 * FUNCTION: CREATE DEFAULT ADMIN ACCOUNT *
 ******************************************/
function create_default_admin_account()
{
    global $escaper;

    // If a value was POSTed
    if ($_POST && isset($_POST['verify_create_default_admin_account']))
    {
        // Verify that the default admin account values are proper
        $verify_create_default_admin_account = verify_create_default_admin_account();

        // If we were able to verify step 5 default admin account
        if ($verify_create_default_admin_account['success'])
        {
            // Get the POSTed Information
            $username = $_POST['username'];
            $full_name = $_POST['full_name'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $mailing_list = isset($_POST['mailing_list']) ? "true" : "false";

            // Create a unique salt
            $salt = "";
            $values = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
            for ($i = 0; $i < 20; $i++)
            {
                $salt .= $values[array_rand($values)];
            }

            // Hash the salt
            $salt_hash = '$2a$15$' . md5($salt);

            // Generate the password hash for admin user
            set_time_limit(120);
            $hash = crypt($password, $salt_hash);

            // Set other values for the default admin user
            $type = "simplerisk";
            $teams = [];
            $role_id = 1;
            $admin = 1;
            $multi_factor = 1;
            $change_password = 0;
            $manager = 0;

            // The admin user should have all possible permissions
            $permissions = get_possible_permission_ids();

            // Add the new default admin user
            add_user($type, $username, $email, $full_name, $salt, $hash, $teams, $role_id, $admin, $multi_factor, $change_password, $manager, $permissions);

            // Create the SimpleRisk instance ID if it doesn't already exist
            $instance_id = create_simplerisk_instance_id();

            // Register the instance
            $data = array(
                'action' => 'installer_registration',
                'instance_id' => $instance_id,
                'name' => $full_name,
                'email' => $email,
                'mailing_list' => $mailing_list,
            );
            $result = simplerisk_service_call($data);

            // Reload the login page
            header("Location: index.php");
        }
        // If we could not verify step 5 default admin account
        else
        {
            // Get the error message
            $error_message = $verify_create_default_admin_account['error_message'];
        }
    }

    // Page header
    echo "
<html ng-app=\"SimpleRisk\">
  <head>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"css/bootstrap.min.css\" media=\"screen\" />
      <link rel=\"stylesheet\" type=\"text/css\" href=\"css/style.css\" media=\"screen\" />
      <link rel=\"stylesheet\" href=\"css/bootstrap.css\">
      <link rel=\"stylesheet\" href=\"css/bootstrap-responsive.css\">
      <link rel=\"stylesheet\" href=\"vendor/components/font-awesome/css/fontawesome.min.css\">
      <link rel=\"stylesheet\" href=\"css/theme.css\">
  </head>

  <body ng-controller=\"MainCtrl\" class=\"login--page\">

    <header class=\"l-header\">
      <div class=\"navbar\">
        <div class=\"navbar-inner\">
          <div class=\"container-fluid\">
            <a class=\"brand\" href=\"https://www.simplerisk.com/\"><img src=\"images/logo@2x.png\" alt=\"SimpleRisk Logo\" /></a>
            <div class=\"navbar-content pull-right\">
              <ul class=\"nav\">
                <li>
                  <a href=\"index.php\">Default Admin Account Creation</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </header>
    <div class=\"container-fluid\">
      <div class=\"row-fluid\">
        <div class=\"span12\">
          <div class=\"login-wrapper clearfix\">
            <h1 class=\"text-center welcome--msg\">Default Admin Account Creation</h1>
              <form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">
    ";

    // If we have an error message
    if (isset($error_message))
    {
        // For each error message provided
        foreach ($error_message as $message)
        {
            health_check_bad($message);
        }
        echo "<br />\n";
    }

    // Admin account information table
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" colspan=\"2\"><label class=\"login--label\">Admin Account Information</label></th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    echo "<tr>\n";
    echo "<td><label for>Username:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"username\" value=\"" . (isset($_POST['username']) ? $escaper->escapeHtml($_POST['username']) : "") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Full Name:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"full_name\" value=\"" . (isset($_POST['full_name']) ? $escaper->escapeHtml($_POST['full_name']) : "") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Email Address:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"email\" value=\"" . (isset($_POST['email']) ? $escaper->escapeHtml($_POST['email']) : "") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Password:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"password\" size=\"30\" name=\"password\" value=\"\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Confirm Password:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"password\" size=\"30\" name=\"confirm_password\" value=\"\" /></td>\n";
    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";
    echo "<table>\n";
    echo "<tbody>\n";
    echo "<tr>\n";
    echo "<td style='padding: 10px'><input type='checkbox' id='mailing_list' name='mailing_list'" . (isset($_POST['mailing_list']) ? " checked" : "") . " /></td>\n";
    echo "<td style='padding: 10px'><label for='mailing_list'>Add me to the SimpleRisk mailing list for educational content and notifications about new releases.</label></td><td>\n";
    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";

    echo "<br /><input type=\"submit\" name=\"verify_create_default_admin_account\" value=\"CREATE\" />\n";
    echo "</form>\n";

    // Page trailer
    echo "
                </form>
              </div>
        </div>
      </div>
    </div>
  </body>

</html>
    ";
}

/*************************************************
 * FUNCTION: VERIFY CREATE DEFAULT ADMIN ACCOUNT *
 *************************************************/
function verify_create_default_admin_account()
{
    $error = false;

    // If the Username is empty
    if ($_POST['username'] == "")
    {
        $error_message[] = "The admin account must have a username.";
        $error = true;
    }

    // If the Full Name is empty
    if ($_POST['full_name'] == "")
    {
        $error_message[] = "Please specify a full name for the admin account.";
        $error = true;
    }

    // If the email address is not a proper email address format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error_message[] = "An invalid email address was specified.";
        $error = true;
    }

    // If the Password is empty
    if ($_POST['password'] == "")
    {
        $error_message[] = "The admin account must have a password.";
        $error = true;
    }

    // If the password and confirm password do not match
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error_message[] = "The Password and Confirm Password values do not match.  Please try again.";
        $error = true;
    }

    // If there were errors
    if ($error)
    {
        $result['success'] = false;
        $result['error_message'] = $error_message;
    }
    else
    {
        $result['success'] = true;
        $result['error_message'] = null;
    }

    // Return the validation result
    return $result;
}
/***********************************************************
 * FUNCTION: FILTERING STRING FOR SQL INJECTION PREVENTION *
 ***********************************************************/
function sqli_filter($string) {
    // Remove any characters that are not an upper, lower, digit or underscore
    $chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_";
    $pattern = "/[^" . preg_quote($chars, "/") . "]/";
    $string = preg_replace($pattern, "", $string);

    // Convert the string to uppercase
    $original_string = strtoupper($string);

    // Apply the filters to the original string
    $filtered_string = str_replace("UNION","",$original_string);
    $filtered_string = str_replace("COLLATE","",$filtered_string);
    $filtered_string = str_replace("DROP","",$filtered_string);
    //$filtered_string = str_replace("--","",$filtered_string);
    //$filtered_string = str_replace(";","",$filtered_string);
    //$filtered_string = str_replace("/*","",$filtered_string);
    //$filtered_string = str_replace("*/","",$filtered_string);
    //$filtered_string = str_replace("//","",$filtered_string);
    //$filtered_string = str_replace(" ","",$filtered_string);
    //$filtered_string = str_replace("#","",$filtered_string);
    //$filtered_string = str_replace("||","",$filtered_string);

    // If we made any changes to the original string
    if ($original_string != $filtered_string)
    {
        // Run it through the sqli_filter again
        // This prevents strings like "UNIUNIONON" from turning into "UNION" when the filter is run
        sqli_filter($filtered_string);
    }
    // If we have our final string return it
    else return $filtered_string;
}

/**********************************
 * FUNCTION: GET DATABASE VERSION *
 **********************************/
function get_database_version()
{
    // Open a database connection
    $db = db_open();

    // Get the database version information
    $stmt = $db->prepare("SELECT VERSION() as version;");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $db_version = $row['version'];

    // Close the database connection
    db_close($db);

    // Return the database version
    return $db_version;
}

/********************************
 * FUNCTION: GET OS INFORMATION *
 ********************************/
function getOSInformation()
{
    if (false == function_exists("shell_exec") || false == is_readable("/etc/os-release")) {
        $os_version = php_uname('s');
        $array = [
            'name' =>   $os_version,
            'version' => $os_version,
            'id' => $os_version,
            'id_like' => $os_version,
            'pretty_name' => $os_version,
        ];
        return $array;
    }

    $os         = shell_exec('cat /etc/os-release');
    $listIds    = preg_match_all('/.*=/', $os, $matchListIds);
    $listIds    = $matchListIds[0];

    $listVal    = preg_match_all('/=.*/', $os, $matchListVal);
    $listVal    = $matchListVal[0];

    array_walk($listIds, function(&$v, $k){
        $v = strtolower(str_replace('=', '', $v));
    });

    array_walk($listVal, function(&$v, $k){
        $v = preg_replace('/=|"/', '', $v);
    });

    return array_combine($listIds, $listVal);
}

/***************************
 * FUNCTION: GET PUBLIC IP *
 ***************************/
function get_public_ip()
{
    // Create the request URL
    $url = "https://icanhazip.com";

    // Initialize a curl request for the request URL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);

    // Configure the curl for proxy if one exists
    configure_curl_proxy($ch);

    // Follow Location headers that the server sends
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Return the actual result of the call
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Do not include the header in the output
    curl_setopt($ch, CURLOPT_HEADER, false);

    // Time out after 1 second of trying to connect
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

    // Make the curl request
    $response = curl_exec($ch);

    // Get the return code
    $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close the curl session
    curl_close($ch);

    // If the URL call was successful
    if ($return_code == 200)
    {
        // Return the response
        return $response;
    }
    // Otherwise, return false
    else return false;
}

/***********************************
 * FUNCTION: GET MYSQLDUMP COMMAND *
 ***********************************/
function get_mysqldump_command()
{
    // Open the database connection
    $db = db_open();

    // Get the database version information
    $stmt = $db->prepare("SELECT VERSION() as version;");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $version = $row['version'];

    // MariaDB version looks like "10.5.12-MariaDB-log"
    // MySQL version looks like "8.0.23"

    // Turn all mysqldump options off by default
    $column_statistics = false;
    $set_gtid_purged = false;
    $lock_tables = false;
    $skip_add_locks = false;
    $no_tablespaces = false;

    // If the database is MariaDB
    if (preg_match('/MariaDB/', $version))
    {
        // MariaDB uses the lock-tables option
        $lock_tables = true;

        // MariaDB uses the skip-add-locks option
        $skip_add_locks = true;

        // MariaDB uses the no-tablespaces option
        $no_tablespaces = true;
    }
    // Otherwise this is MySQL
    else
    {
        // MySQL uses the set-gtid-purged option
        $set_gtid_purged = true;

        // MySQL uses the lock-tables option
        $lock_tables = true;

        // MySQL uses the skip-add-locks option
        $skip_add_locks = true;

        // MySQL uses the no-tablespaces option
        $no_tablespaces = true;

        // Split the version by the decimals
        $version = explode('.', $version);
        $major_version = $version[0];
        $minor_version = $version[1].".".$version[2];

        // If the version is MySQL 8 or higher
        if ($major_version >= 8)
        {
            // MySQL >= 8 uses the column-statistics option
            $column_statistics = true;
        }
    }

    // If mysqldump does not exist
    if(!is_process('mysqldump'))
    {
        // Get the path from the SimpleRisk configuration
        $mysqldump_path = get_setting('mysqldump_path');
    }
    // Otherwise use the defined path to mysqldump
    else $mysqldump_path = "mysqldump";

    // Start the mysqldump command
    $mysqldump_command = escapeshellcmd($mysqldump_path) . " --opt";

    // If column_statistics is enabled
    if ($column_statistics)
    {
        // Append the column statistics option
        $mysqldump_command .= " --column-statistics=0";
    }

    // If lock_tables is enabled
    if ($lock_tables)
    {
        // Append the lock tables option
        $mysqldump_command .= " --lock-tables=false";
    }

    // If skip_add_locks is enabled
    if ($skip_add_locks)
    {
        // Append the skip add locks option
        $mysqldump_command .= " --skip-add-locks";
    }

    // If no_tablespaces is enabled
    if ($no_tablespaces)
    {
        // Append the no tablespaces option
        $mysqldump_command .= " --no-tablespaces";
    }

    // If set_gtid_purged is enabled
    if ($set_gtid_purged)
    {
        // Append the set gtid purged option
        $mysqldump_command .= " --set-gtid-purged=OFF";
    }

    // Append the database connection information
    $mysqldump_command .= "  -h " . escapeshellarg(DB_HOSTNAME) . " -u " . escapeshellarg(DB_USERNAME) . " -p" . escapeshellarg(DB_PASSWORD) . " " . escapeshellarg(DB_DATABASE);

    // Return the mysqldump command
    return $mysqldump_command;
}
/**********************************
 * FUNCTION: CONVERT DATA TO UTF8 *
 **********************************/
function utf8ize( $mixed ) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } elseif (is_string($mixed)) {
        return utf8_encode($mixed);
    }
    return $mixed;
}
/**************************************************
 * FUNCTION: SETTING CUSTOM RISKS AND ISSUES TAGS *
 **************************************************/
function setting_risks_and_issues_tags($risk_tags){

    // Query the database
    $db = db_open();
    $tag_ids = implode(",", $risk_tags);
    $stmt = $db->prepare("UPDATE `user` SET `custom_risks_and_issues_settings` = :tag_ids WHERE value = :user_id");
    $stmt->bindParam(":user_id", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->bindParam(":tag_ids", $tag_ids, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    return true;
}
?>
