<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/cvss.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/alerts.php'));

// Include the language file
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Set the simplerisk timezone for any datetime functions
set_simplerisk_timezone();

/******************************
 * FUNCTION: DATABASE CONNECT *
 ******************************/
function db_open()
{
    if(isset($GLOBALS['db']) && $GLOBALS['db']){
        return $GLOBALS['db'];
    }
    // Connect to the database
    try
    {
        $GLOBALS['db'] = new PDO("mysql:charset=UTF8;dbname=".DB_DATABASE.";host=".DB_HOSTNAME.";port=".DB_PORT,DB_USERNAME,DB_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $GLOBALS['db']->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES utf8");
        $GLOBALS['db']->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET CHARACTER SET utf8");

        // Set the simplerisk timezone for any datetime functions
        set_simplerisk_timezone();
        
        $now = new DateTime();
        $mins = $now->getOffset() / 60;
        $sgn = ($mins < 0 ? -1 : 1);
        $mins = abs($mins);
        $hrs = floor($mins / 60);
        $mins -= $hrs * 60;
        $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

        //Your DB Connection - sample
        $GLOBALS['db']->exec("SET time_zone='{$offset}';");

        return $GLOBALS['db'];
    }
    catch (PDOException $e)
    {
        printf("<br />SimpleRisk is unable to communicate with the database.  You should double-check your settings in the config.php file.  If the problem persists, you can try manually connecting to the database using the command '<i>mysql -h &lt;hostname&gt; -u &lt;username&gt; -p</i>' and specifying the password when prompted.  If the issue persists, contact support and provide a copy of any relevant messages from your web server's error log.<br />\n");
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

    // If this is the team table
    if ($name == "team"){
        // Order by name
        $stmt = $db->prepare("SELECT * FROM `{$name}` ORDER BY name");
    }
    // Otherwise, order by value
    else {
        $stmt = $db->prepare("SELECT * FROM `{$name}` ORDER BY value");
    }

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
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
        $user_teams = get_user_teams($_SESSION['uid']);

        if ($user_teams == "all")
        {
            $user_teams = get_all_teams();
        }

        // Get the team query string
        $separation_query = get_team_query_string($user_teams);
        $teams = explode(":", $user_teams);
        $teams = array_unique($teams);
        $teams_query = implode(",", $teams);

        $separation_query = " WHERE FIND_IN_SET(value, '{$teams_query}') ";

        $separation_query = str_replace(array("`team`", "team"), "`value`", $separation_query);
        $stmt = $db->prepare("SELECT * FROM `team` {$separation_query} ORDER BY `name`");
    }

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
    $stmt = $db->prepare("SELECT * FROM `{$table_name}` ORDER BY name");

    // Execute the database query
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

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
    }

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
        $stmt = $db->prepare("SELECT value, name FROM frameworks WHERE status=1 ORDER BY name");
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

    // Execute the database query
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

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
    }

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

/********************************
 * FUNCTION: UPDATE RISK LEVELS *
 ********************************/
function update_risk_levels($veryhigh, $high, $medium, $low)
{
    if (!(($low['value'] < $medium['value']) && ($medium['value'] < $high) && ($high['value'] < $veryhigh['value'])))
    {
        return false;
    }

    // Open the database connection
    $db = db_open();

    // Update the very high risk level
    $stmt = $db->prepare("UPDATE `risk_levels` SET value=:value, color=:color, display_name=:display_name WHERE name='Very High'");
    $stmt->bindParam(":value", $veryhigh['value'], PDO::PARAM_STR);
    $stmt->bindParam(":color", $veryhigh['color'], PDO::PARAM_STR);
    $stmt->bindParam(":display_name", $veryhigh['display_name'], PDO::PARAM_STR);
    $stmt->execute();

    // Update the high risk level
    $stmt = $db->prepare("UPDATE `risk_levels` SET value=:value, color=:color, display_name=:display_name WHERE name='High'");
    $stmt->bindParam(":value", $high['value'], PDO::PARAM_STR);
    $stmt->bindParam(":color", $high['color'], PDO::PARAM_STR);
    $stmt->bindParam(":display_name", $high['display_name'], PDO::PARAM_STR);
    $stmt->execute();

    // Update the medium risk level
    $stmt = $db->prepare("UPDATE `risk_levels` SET value=:value, color=:color, display_name=:display_name WHERE name='Medium'");
    $stmt->bindParam(":value", $medium['value'], PDO::PARAM_STR);
    $stmt->bindParam(":color", $medium['color'], PDO::PARAM_STR);
    $stmt->bindParam(":display_name", $medium['display_name'], PDO::PARAM_STR);
    $stmt->execute();

    // Update the low risk level
    $stmt = $db->prepare("UPDATE `risk_levels` SET value=:value, color=:color, display_name=:display_name WHERE name='Low'");
    $stmt->bindParam(":value", $low['value'], PDO::PARAM_STR);
    $stmt->bindParam(":color", $low['color'], PDO::PARAM_STR);
    $stmt->bindParam(":display_name", $low['display_name'], PDO::PARAM_STR);
    $stmt->execute();

    // Audit log
    $risk_id = 1000;
    $message = "Risk level scoring was modified by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return true;
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

/*****************************************
 * FUNCTION: CREATE SKATEHOLDER DROPDOWN *
 *****************************************/
function create_stakeholder_dropdown($selected = "", $name="additional_stakeholders[]"){
    global $escaper;

    // Make selected to array
    $selected = explode(",", $selected);
    if(!is_array($selected)){
        $selected = array();
    }

    $options = get_table_ordered_by_name('user');
    $str = "<select class=\"multiselect\" name=\"{$name}\" multiple class=\"form-field form-control\" style=\"width:auto;\">\n";
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
    echo $str;

    return;
}

/*****************************
 * FUNCTION: CREATE DROPDOWN *
 *****************************/
function create_dropdown($name, $selected = NULL, $rename = NULL, $blank = true, $help = false, $returnHtml=false, $customHtml="", $blankText="--", $blankValue="", $useValue=true)
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

    // If we want a table that should be ordered by name instead of value
    if ($name == "user" || $name == "category" || $name == "team" || $name == "technology" || $name == "location" || $name == "regulation" || $name == "projects" || $name == "file_types" || $name == "planning_strategy" || $name == "close_reason" || $name == "status" || $name == "source" || $name == "import_export_mappings" || $name == "test_status")
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
    // If we want a table of languages
    else if ($name == "languages")
    {
        $options = get_custom_table($name);
    }
    // If we want a table of family
    else if ($name == "family")
    {
        $options = get_custom_table($name);
    }
    // If we want parent frameworks
    else if($name == "parent_frameworks"){
        $options = get_custom_table($name);
    }
    // If we want date formats
    else if($name == "date_formats"){
        $options = get_custom_table($name);
    }
    // Otherwise
    else
    {
        // Get the list of options
        $options = get_table($name);
    }

    // Sort options array
    if($name == "parent_frameworks"){
        uasort($options, function($a, $b){
            if($a['name'] == $b['name']) return 0;
            return ($a['name'] < $b['name']) ? -1 : 1;
        });
    }

    // If the blank is true
    if ($blank == true)
    {
        array_unshift($options, ["value"=>$blankValue, "name"=>$blankText]);
    }

    foreach ($options as $key => &$option)
    {
        // If the option is selected
        if ($selected == $option['value'] || (!$selected && !$option['value'] && $option['value'] != 0) || $selected=='all')
        {
            $text = " selected";
        }
        else $text = "";

        // If ID is used for option's value
        if($useValue)
        {
            $str .= "    <option title='test' value=\"" . $escaper->escapeHtml($option['value']) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
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
 **************************************/
function create_multiple_dropdown($name, $selected = NULL, $rename = NULL, $options = NULL, $blank = false, $blankText="--", $blankValue="", $useValue=true, $customHtml="")
{
    global $lang;
    global $escaper;

    if ($rename != NULL)
    {
        echo "<select {$customHtml} multiple=\"multiple\" id=\"" . $escaper->escapeHtml($rename) . "\" name=\"" . $escaper->escapeHtml($rename) . "[]\">\n";
    }
    else {
        echo "<select {$customHtml} multiple=\"multiple\" id=\"" . $escaper->escapeHtml($name) . "\" name=\"" . $escaper->escapeHtml($name) . "[]\">\n";
    }

    // Get the list of options
    if($options === NULL){
        if($name == "frameworks"){
            $options = get_custom_table($name);
        }
        elseif($name == "family"){
            $options = get_custom_table($name);
        }
        else{
            $options = get_table($name);
        }
    }

    // If the blank is true
    if ($blank == true)
    {
        array_unshift($options, ["value"=>$blankValue, "name"=>$blankText]);
    }

    // For each option
    foreach ($options as $option)
    {
        // Pattern is a team id surrounded by colons
        $regex_pattern = "/:" . $option['value'] .":/";

        // If the user belongs to the team or all was selected
        if (preg_match($regex_pattern, $selected, $matches) || $selected == "all" || (!$selected && !$option['value']))
        {
            $text = " selected";
        }
        else $text = "";

        // If ID is used for option's value
        if($useValue)
        {
            echo "    <option value=\"" . $escaper->escapeHtml($option['value']) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
        }
        // If name is used for option's value
        else
        {
            echo "    <option value=\"" . $escaper->escapeHtml($option['name']) . "\"" . $text . ">" . $escaper->escapeHtml($option['name']) . "</option>\n";
        }

    }

    echo "  </select>\n";
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
//            $max_risk = 35;
            $max_risk = ($GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts']) + (2 * $GLOBALS['count_of_impacts']);
            $risk = ($likelihood * $impact) + (2 * $impact);
        }
        else if ($risk_model == 2)
        {
//            $max_risk = 30;
            $max_risk = ($GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts']) + $GLOBALS['count_of_impacts'];
            $risk = ($likelihood * $impact) + $impact;
        }
        else if ($risk_model == 3)
        {
//            $max_risk = 25;
            $max_risk = $GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts'];
            $risk = $likelihood * $impact;
        }
        else if ($risk_model == 4)
        {
//            $max_risk = 30;
            $max_risk = $GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts'] + $GLOBALS['count_of_likelihoods'];
            $risk = ($likelihood * $impact) + $likelihood;
        }
        else if ($risk_model == 5)
        {
//            $max_risk = 35;
            $max_risk = ($GLOBALS['count_of_likelihoods'] * $GLOBALS['count_of_impacts']) + (2 * $GLOBALS['count_of_likelihoods']);
            $risk = ($likelihood * $impact) + (2 * $likelihood);
        }

        // This puts it on a 1 to 10 scale similar to CVSS
        $risk = round($risk * (10 / $max_risk), 1);
    }
    // If the impact or likelihood were not specified risk is 10
    else $risk = get_setting('default_risk_score');

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
        $array = $stmt->fetch();

        // Close the database connection
        db_close($db);

        // If the risk level display name is in High, Medium, or Low
        if ($array['display_name'] != "")
        {
            return $array['display_name'];
        }
        // If the risk level name is in High, Medium, or Low
        elseif($array['name'] != "")
        {
            return $array['name'];
        }
        // Otherwise the risk is Insignificant
        else return "Insignificant";
    }
    // Return a null value
    return "";
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

            // Add risk scoring history
            add_risk_scoring_history($risk['id'], $calculated_risk);

            // Add residual risk scoring history
            $residual_risk = get_residual_risk($risk['id']+1000);
            add_residual_risk_scoring_history($risk['id'], $residual_risk);
        }
    }

    // Audit log
    $risk_id = 1000;
    $message = "The risk formula was modified by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

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
function update_table($table, $name, $value)
{
    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("UPDATE {$table} SET name=:name WHERE value=:value");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 20);
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
                break;
        }
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
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 50);
    $stmt->bindParam(":value", $value, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*************************
 * FUNCTION: GET SETTING *
 *************************/
function get_setting($setting, $default=false)
{
    // Open the database connection
    $db = db_open();

    // Get the risk levels
    $stmt = $db->prepare("SELECT * FROM settings where name=:setting");
    $stmt->bindParam(":setting", $setting, PDO::PARAM_STR, 100);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the array isn't empty
    if (!empty($array))
    {
        // Set the value to the array value
        $value = $array[0]['value'];
    }
    else $value = false;

    if($value === false)
    {
        return $default;
    }
    else
    {
        return $value;
    }
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

/************************************************************
 * FUNCTION: CONVERT DEFAULT DATE TIME FORMAT TO PHP FORMAT *
 ************************************************************/
function get_default_datetime_format($time_format="H:i:s")
{
    $format = get_default_date_format();

    return $format." ".$time_format;
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
                $risk_id = 1000;
                $message = "The existing user \"" . $name . "\" was deleted by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);
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
            case "frameworks":
                $risk_id = 1000;
                $message = "The existing framework \"" . try_decrypt($name) . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);
                break;
            default:
                break;
        }

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

    // Audit log
    $risk_id = 1000;
    $username = get_name_by_value("user", $value);
    $message = "The user \"" . $username . "\" was enabled by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

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
        $risk_id = 1000;
        $username = get_name_by_value("user", $value);
        $message = "The user \"" . $username . "\" was disabled by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);

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
    $stmt = $db->prepare("SELECT * FROM user WHERE name=:user");
    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);

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
function add_user($type, $user, $email, $name, $salt, $hash, $teams, $role_id, $governance, $riskmanagement, $compliance, $assessments, $asset, $admin, $review_veryhigh, $accept_mitigation, $review_high, $review_medium, $review_low, $review_insignificant, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor, $change_password, $add_new_frameworks, $modify_frameworks, $delete_frameworks, $add_new_controls, $modify_controls, $delete_controls, $other_options=[])
{
    // Open the database connection
    $db = db_open();

    // Insert the new user
    $sql = "INSERT INTO user (`type`, `username`, `name`, `email`, `salt`, `password`, `teams`, `role_id`, `governance`, `riskmanagement`, `compliance`, `assessments`, `asset`, `admin`, `review_veryhigh`, `accept_mitigation`, `review_high`, `review_medium`, `review_low`, `review_insignificant`, `submit_risks`, `modify_risks`, `plan_mitigations`, `close_risks`, `multi_factor`, `change_password`, `add_new_frameworks`, `modify_frameworks`, `delete_frameworks`, `add_new_controls`, `modify_controls`, `delete_controls`";
    foreach($other_options as $field => $value)
    {
        $sql .= ", `{$field}`";
    }
    $sql .= ") VALUES (:type, :user, :name, :email, :salt, :hash, :teams, :role_id, :governance, :riskmanagement, :compliance, :assessments, :asset, :admin, :review_veryhigh, :accept_mitigation, :review_high, :review_medium, :review_low, :review_insignificant, :submit_risks, :modify_risks, :plan_mitigations, :close_risks, :multi_factor, :change_password, :add_new_frameworks, :modify_frameworks, :delete_frameworks, :add_new_controls, :modify_controls, :delete_controls";
    foreach($other_options as $field => $value)
    {
        $sql .= ", :{$field}";
    }
    $sql .= "); ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR, 20);
    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 50);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
    $stmt->bindParam(":salt", $salt, PDO::PARAM_STR, 20);
    $stmt->bindParam(":hash", $hash, PDO::PARAM_STR, 60);
    $stmt->bindParam(":teams", $teams, PDO::PARAM_STR, 200);
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->bindParam(":governance", $governance, PDO::PARAM_INT);
    $stmt->bindParam(":riskmanagement", $riskmanagement, PDO::PARAM_INT);
    $stmt->bindParam(":compliance", $compliance, PDO::PARAM_INT);
    $stmt->bindParam(":assessments", $assessments, PDO::PARAM_INT);
    $stmt->bindParam(":asset", $asset, PDO::PARAM_INT);
    $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
    $stmt->bindParam(":review_veryhigh", $review_veryhigh, PDO::PARAM_INT);
    $stmt->bindParam(":accept_mitigation", $accept_mitigation, PDO::PARAM_INT);
    $stmt->bindParam(":review_high", $review_high, PDO::PARAM_INT);
    $stmt->bindParam(":review_medium", $review_medium, PDO::PARAM_INT);
    $stmt->bindParam(":review_low", $review_low, PDO::PARAM_INT);
    $stmt->bindParam(":review_insignificant", $review_insignificant, PDO::PARAM_INT);
    $stmt->bindParam(":submit_risks", $submit_risks, PDO::PARAM_INT);
    $stmt->bindParam(":modify_risks", $modify_risks, PDO::PARAM_INT);
    $stmt->bindParam(":plan_mitigations", $plan_mitigations, PDO::PARAM_INT);
    $stmt->bindParam(":close_risks", $close_risks, PDO::PARAM_INT);
    $stmt->bindParam(":multi_factor", $multi_factor, PDO::PARAM_INT);
    $stmt->bindParam(":change_password", $change_password, PDO::PARAM_INT);
    $stmt->bindParam(":add_new_frameworks", $add_new_frameworks, PDO::PARAM_INT);
    $stmt->bindParam(":modify_frameworks", $modify_frameworks, PDO::PARAM_INT);
    $stmt->bindParam(":delete_frameworks", $delete_frameworks, PDO::PARAM_INT);
    $stmt->bindParam(":add_new_controls", $add_new_controls, PDO::PARAM_INT);
    $stmt->bindParam(":modify_controls", $modify_controls, PDO::PARAM_INT);
    $stmt->bindParam(":delete_controls", $delete_controls, PDO::PARAM_INT);
    foreach($other_options as $field => $value)
    {
        $stmt->bindParam(":{$field}", $other_options[$field], PDO::PARAM_INT);
    }
    $stmt->execute();

    // Audit log
    $risk_id = 1000;
    $message = "The new user \"" . $user . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return true;
}

/*************************
 * FUNCTION: UPDATE USER *
 *************************/
function update_user($user_id, $lockout, $type, $name, $email, $teams, $role_id, $lang, $governance, $riskmanagement, $compliance, $assessments, $asset, $admin, $review_veryhigh, $accept_mitigation, $review_high, $review_medium, $review_low, $review_insignificant, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor, $change_password, $add_new_frameworks, $modify_frameworks, $delete_frameworks, $add_new_controls, $modify_controls, $delete_controls, $other_options=[])
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
    $sql = "UPDATE user set `lockout`=:lockout, `type`=:type, `name`=:name, `email`=:email, `teams`=:teams, `role_id`=:role_id, `lang` =:lang, `governance`=:governance, `riskmanagement`=:riskmanagement, `compliance`=:compliance, `assessments`=:assessments, `asset`=:asset, `admin`=:admin, `review_veryhigh`=:review_veryhigh, `accept_mitigation`=:accept_mitigation, `review_high`=:review_high, `review_medium`=:review_medium, `review_low`=:review_low, `review_insignificant`=:review_insignificant, `submit_risks`=:submit_risks, `modify_risks`=:modify_risks, `plan_mitigations`=:plan_mitigations, `close_risks`=:close_risks, `multi_factor`=:multi_factor, `change_password`=:change_password, `add_new_frameworks`=:add_new_frameworks, `modify_frameworks`=:modify_frameworks, `delete_frameworks`=:delete_frameworks, `add_new_controls`=:add_new_controls, `modify_controls`=:modify_controls, `delete_controls`=:delete_controls";
    foreach($other_options as $field => $value)
    {
        $sql .= ", `{$field}`=:{$field} ";
    }
    $sql .= " WHERE `value`=:user_id; ";
    
    $stmt = $db->prepare($sql);
    
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":lockout", $lockout, PDO::PARAM_INT);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR, 10);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 50);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
    $stmt->bindParam(":teams", $teams, PDO::PARAM_STR, 200);
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_STR, 200);
    $stmt->bindParam(":lang", $lang, PDO::PARAM_STR, 2);
    $stmt->bindParam(":governance", $governance, PDO::PARAM_INT);
    $stmt->bindParam(":riskmanagement", $riskmanagement, PDO::PARAM_INT);
    $stmt->bindParam(":compliance", $compliance, PDO::PARAM_INT);
    $stmt->bindParam(":assessments", $assessments,  PDO::PARAM_INT);
    $stmt->bindParam(":asset", $asset, PDO::PARAM_INT);
    $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
    $stmt->bindParam(":review_veryhigh", $review_veryhigh, PDO::PARAM_INT);
    $stmt->bindParam(":accept_mitigation", $accept_mitigation, PDO::PARAM_INT);
    $stmt->bindParam(":review_high", $review_high, PDO::PARAM_INT);
    $stmt->bindParam(":review_medium", $review_medium, PDO::PARAM_INT);
    $stmt->bindParam(":review_low", $review_low, PDO::PARAM_INT);
    $stmt->bindParam(":review_insignificant", $review_insignificant, PDO::PARAM_INT);
    $stmt->bindParam(":submit_risks", $submit_risks, PDO::PARAM_INT);
    $stmt->bindParam(":modify_risks", $modify_risks, PDO::PARAM_INT);
    $stmt->bindParam(":plan_mitigations", $plan_mitigations, PDO::PARAM_INT);
    $stmt->bindParam(":close_risks", $close_risks, PDO::PARAM_INT);
    $stmt->bindParam(":multi_factor", $multi_factor, PDO::PARAM_INT);
    $stmt->bindParam(":change_password", $change_password, PDO::PARAM_INT);
    $stmt->bindParam(":add_new_frameworks", $add_new_frameworks, PDO::PARAM_INT);
    $stmt->bindParam(":modify_frameworks", $modify_frameworks, PDO::PARAM_INT);
    $stmt->bindParam(":delete_frameworks", $delete_frameworks, PDO::PARAM_INT);
    $stmt->bindParam(":add_new_controls", $add_new_controls, PDO::PARAM_INT);
    $stmt->bindParam(":modify_controls", $modify_controls, PDO::PARAM_INT);
    $stmt->bindParam(":delete_controls", $delete_controls, PDO::PARAM_INT);
    foreach($other_options as $field => $value)
    {
        $stmt->bindParam(":{$field}", $other_options[$field], PDO::PARAM_INT);
    }
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // If the update affects the current logged in user
    if ($_SESSION['uid'] == $user_id)
    {
        // Update the session values
        $_SESSION['governance'] = (int)$governance;
        $_SESSION['riskmanagement'] = (int)$riskmanagement;
        $_SESSION['compliance'] = (int)$compliance;
        $_SESSION['assessments'] = (int)$assessments;
        $_SESSION['asset'] = (int)$asset;
        $_SESSION['admin'] = (int)$admin;
        $_SESSION['review_veryhigh'] = (int)$review_veryhigh;
        $_SESSION['accept_mitigation'] = (int)$accept_mitigation;
        $_SESSION['review_high'] = (int)$review_high;
        $_SESSION['review_medium'] = (int)$review_medium;
        $_SESSION['review_low'] = (int)$review_low;
        $_SESSION['review_insignificant'] = (int)$review_insignificant;
        $_SESSION['submit_risks'] = (int)$submit_risks;
        $_SESSION['modify_risks'] = (int)$modify_risks;
        $_SESSION['close_risks'] = (int)$close_risks;
        $_SESSION['plan_mitigations'] = (int)$plan_mitigations;
        $_SESSION['lang'] = $lang;

        $_SESSION['add_new_frameworks'] = (int)$add_new_frameworks;
        $_SESSION['modify_frameworks'] = (int)$modify_frameworks;
        $_SESSION['delete_frameworks'] = (int)$delete_frameworks;
        $_SESSION['add_new_controls'] = (int)$add_new_controls;
        $_SESSION['modify_controls'] = (int)$modify_controls;
        $_SESSION['delete_controls'] = (int)$delete_controls;
        foreach($other_options as $field => $value)
        {
            $_SESSION[$field] = (int)$value;
        }
    }

    // Audit log
    $risk_id = 1000;
    $username = get_name_by_value("user", $user_id);
    $message = "The existing user \"" . $username . "\" was modified by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

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

    return isset($array[0]) ? $array[0] : false;
}

/****************************
 * FUNCTION: GET ID BY USER *
 ****************************/
function get_id_by_user($user)
{
    // Open the database connection
    $db = db_open();

    // Get the user information
    $stmt = $db->prepare("SELECT * FROM user WHERE username = :user");
    $stmt->bindParam(":user", $user, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return $array['value'];
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
        return $value;
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

/*******************************
 * FUNCTION: GET VALUE BY NAME *
 *******************************/
function get_value_by_name($table, $name, $return_name = false)
{
    $value = false;
    if(isset($GLOBALS[$table])){
        foreach($GLOBALS[$table] as $row){
            if(strtolower($row['name']) == strtolower($name)){
                $value = isset($row['value']) ? $row['value'] : $row['id'];
                break;
            }
        }
    }

    if(!$value || !isset($GLOBALS[$table])){
        // Open the database connection
        $db = db_open();

        // Get the user information
        $stmt = $db->prepare("SELECT * FROM {$table}");
        $stmt->execute();

        // Store the list in the array
        $GLOBALS[$table] = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        if($table == 'frameworks'){
            foreach($GLOBALS[$table] as &$row){
                $row['name'] = try_decrypt($row['name']);
            }
        }

        foreach($GLOBALS[$table] as &$row){
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
    $risk_id = 1000;
    $message = "Password was modified for the \"" . $user . "\" user.";
    write_log($risk_id, $uid, $message);

    // Close the database connection
    db_close($db);

    return true;
}

/*************************
 * FUNCTION: SUBMIT RISK *
 *************************/
function submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source,  $category, $team, $technology, $owner, $manager, $assessment, $notes, $project_id = 0, $submitted_by=0, $submission_date=false, $additional_stakeholders="")
{
    $submitted_by || ($submitted_by = $_SESSION['uid']);
    // Open the database connection
    $db = db_open();

    // Set numeric null to 0
    if ($location == NULL) $location = 0;

    // Add the risk
    if($submission_date !== false){
        $sql = "INSERT INTO risks (`status`, `subject`, `reference_id`, `regulation`, `control_number`, `location`, `source`, `category`, `team`, `technology`, `owner`, `manager`, `assessment`, `notes`, `project_id`, `submitted_by`, `submission_date`, `additional_stakeholders`) VALUES (:status, :subject, :reference_id, :regulation, :control_number, :location, :source, :category, :team, :technology, :owner, :manager, :assessment, :notes, :project_id, :submitted_by, :submission_date, :additional_stakeholders)";
    }else{
        $sql = "INSERT INTO risks (`status`, `subject`, `reference_id`, `regulation`, `control_number`, `location`, `source`, `category`, `team`, `technology`, `owner`, `manager`, `assessment`, `notes`, `project_id`, `submitted_by`, `additional_stakeholders`) VALUES (:status, :subject, :reference_id, :regulation, :control_number, :location, :source, :category, :team, :technology, :owner, :manager, :assessment, :notes, :project_id, :submitted_by, :additional_stakeholders)";
    }

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":status", $status, PDO::PARAM_STR, 10);
    $stmt->bindParam(":subject", try_encrypt($subject), PDO::PARAM_STR, 1000);
    $stmt->bindParam(":reference_id", $reference_id, PDO::PARAM_STR, 20);
    $stmt->bindParam(":regulation", $regulation, PDO::PARAM_INT);
    $stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR, 20);
    $stmt->bindParam(":location", $location, PDO::PARAM_INT);
    $stmt->bindParam(":source", $source, PDO::PARAM_INT);
    $stmt->bindParam(":category", $category, PDO::PARAM_INT);
    $stmt->bindParam(":team", $team, PDO::PARAM_INT);
    $stmt->bindParam(":technology", $technology, PDO::PARAM_STR);
    $stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
    $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
    $stmt->bindParam(":assessment", try_encrypt($assessment), PDO::PARAM_STR);
    $stmt->bindParam(":notes", try_encrypt($notes), PDO::PARAM_STR);
    $stmt->bindParam(":project_id", $project_id, PDO::PARAM_STR);
    $stmt->bindParam(":submitted_by", $submitted_by, PDO::PARAM_INT);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR);
    if($submission_date !== false){
        $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR);
    }
    $stmt->execute();

    // Get the id of the risk
    $last_insert_id = $db->lastInsertId();

    // Audit log
    $risk_id = $last_insert_id + 1000;
    $message = "A new risk ID \"" . $risk_id . "\" was submitted by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $submitted_by, $message);

    // Close the database connection
    db_close($db);

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // Save custom fields
        save_risk_custom_field_values($risk_id);
    }

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
function submit_risk_scoring($last_insert_id, $scoring_method="5", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamage="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkill="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPDiscovery="10", $OWASPExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom="10")
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
    else if ($scoring_method == 4){
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

    // There is no entry like that, adding new one
    $stmt = $db->prepare("INSERT INTO risk_scoring_history (risk_id, calculated_risk, last_update) VALUES (:risk_id, :calculated_risk, :last_update);");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
    $stmt->bindParam(":last_update", date('Y-m-d H:i:s'), PDO::PARAM_STR);
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

    // There is no entry like that, adding new one
    $stmt = $db->prepare("INSERT INTO `residual_risk_scoring_history` (risk_id, residual_risk, last_update) VALUES (:risk_id, :residual_risk, :last_update);");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":residual_risk", $residual_risk, PDO::PARAM_STR);
    $stmt->bindParam(":last_update", date('Y-m-d H:i:s'), PDO::PARAM_STR);
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
function update_risk_scoring($risk_id, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkill, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPDiscovery, $OWASPExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom)
{
    // Subtract 1000 from the id
    $id = $risk_id - 1000;

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
    $mitigation_team            = isset($post['mitigation_team']) ? (int)$post['mitigation_team'] : 0;
    $current_solution           = isset($post['current_solution']) ? $post['current_solution'] : "";
    $current_solution           = try_encrypt($current_solution);

    $security_requirements      = isset($post['security_requirements']) ? $post['security_requirements'] : "";
    $security_requirements      = try_encrypt($security_requirements);

    $security_recommendations   = isset($post['security_recommendations']) ? $post['security_recommendations'] : "";
    $security_recommendations   = try_encrypt($security_recommendations);

    $planning_date              = isset($post['planning_date']) ? $post['planning_date'] : "";
    $mitigation_date            = isset($post['mitigation_date']) ? $post['mitigation_date']." 00:00:00" : date(get_default_datetime_format());
    // Convert to standard date
    $mitigation_date            = get_standard_date_from_default_format($mitigation_date, true);

    $mitigation_percent         = (isset($post['mitigation_percent']) && $post['mitigation_percent'] >= 0 && $post['mitigation_percent'] <= 100) ? $post['mitigation_percent'] : "";
    $mitigation_controls        = empty($post['mitigation_controls']) ? [] : $post['mitigation_controls'];
    $mitigation_controls        = is_array($mitigation_controls) ? implode(",", $mitigation_controls) : $mitigation_controls;

    if (!validate_date($planning_date, get_default_date_format()))
    {
        $planning_date = "0000-00-00";
    }
    // Otherwise, set the proper format for submitting to the database
    else
    {
//        $planning_date = date(get_default_date_format(), strtotime($planning_date));
        $planning_date = get_standard_date_from_default_format($planning_date);
    }

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Open the database connection
    $db = db_open();

    // Add the mitigation
    $stmt = $db->prepare("INSERT INTO mitigations (`risk_id`, `planning_strategy`, `mitigation_effort`, `mitigation_cost`, `mitigation_owner`, `mitigation_team`, `current_solution`, `security_requirements`, `security_recommendations`, `submitted_by`, `planning_date`, `submission_date`, `mitigation_percent`, `mitigation_controls`) VALUES (:risk_id, :planning_strategy, :mitigation_effort, :mitigation_cost, :mitigation_owner, :mitigation_team, :current_solution, :security_requirements, :security_recommendations, :submitted_by, :planning_date, :submission_date, :mitigation_percent, :mitigation_controls)");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":planning_strategy", $planning_strategy, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_effort", $mitigation_effort, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_cost", $mitigation_cost, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_owner", $mitigation_owner, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_team", $mitigation_team, PDO::PARAM_INT);
    $stmt->bindParam(":current_solution", $current_solution, PDO::PARAM_STR);
    $stmt->bindParam(":security_requirements", $security_requirements, PDO::PARAM_STR);
    $stmt->bindParam(":security_recommendations", $security_recommendations, PDO::PARAM_STR);
    $stmt->bindParam(":submitted_by", $submitted_by_id, PDO::PARAM_INT);
    $stmt->bindParam(":planning_date", $planning_date, PDO::PARAM_STR, 10);
    $stmt->bindParam(":submission_date", $mitigation_date, PDO::PARAM_STR, 10);
    $stmt->bindParam(":mitigation_percent", $mitigation_percent, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_controls", $mitigation_controls, PDO::PARAM_STR, 500);
    $stmt->execute();

    // Get the new mitigation id
    $mitigation_id = get_mitigation_id($id);

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
    $residual_risk = get_residual_risk($id + 1000);
    add_residual_risk_scoring_history($id, $residual_risk);

    return $current_datetime;
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

    // Add the review
    if($submission_date === false){
        $stmt = $db->prepare("INSERT INTO mgmt_reviews (`risk_id`, `review`, `reviewer`, `next_step`, `comments`, `next_review`) VALUES (:risk_id, :review, :reviewer, :next_step, :comments, :next_review)");
    }else{
        $stmt = $db->prepare("INSERT INTO mgmt_reviews (`risk_id`, `review`, `reviewer`, `next_step`, `comments`, `next_review`, `submission_date`) VALUES (:risk_id, :review, :reviewer, :next_step, :comments, :next_review, :submission_date)");
    }

    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":review", $review, PDO::PARAM_INT);
    $stmt->bindParam(":reviewer", $reviewer, PDO::PARAM_INT);
    $stmt->bindParam(":next_step", $next_step, PDO::PARAM_INT);
    $stmt->bindParam(":comments", try_encrypt($comments), PDO::PARAM_STR);
    $stmt->bindParam(":next_review", $next_review, PDO::PARAM_STR, 10);
    if($submission_date !== false){
        $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR, 20);
    }

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

    // Close the database connection
    db_close($db);

    return true;
}

/*************************
 * FUNCTION: UPDATE RISK *
 *************************/
function update_risk($risk_id, $is_api = false)
{
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // Save custom fields
        save_risk_custom_field_values($risk_id);
    }

    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;
    $reference_id           = get_param("post", 'reference_id', false);
    $regulation             = get_param("post", "regulation", false);
    if($regulation !== false){
        $regulation = (int)$regulation;
    }
    $control_number         = get_param("post", "control_number", false);
    $location               = get_param("post", "location", false);
    if($location !== false){
        $location = (int)$location;
    }
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
    if($is_api === false){
        if($team === false){
            $team = "";
        }else{
            $team = implode(",", $team);
        }
    }

    $additional_stakeholders = get_param("post", "additional_stakeholders", false);
    // If form data was submitted.
    if($is_api === false){
        if($additional_stakeholders === false){
            $additional_stakeholders = "";
        }else{
            $additional_stakeholders = implode(",", $additional_stakeholders);
        }
    }

    $technology             = get_param("post", "technology", false);
    // If form data was submitted.
    if($is_api === false){
        if($technology === false){
            $technology = "";
        }else{
            $technology = implode(",", $technology);
        }
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
    $assets                 = get_param("post", "assets", false);
    $submission_date        = get_param("post", "submission_date", false);
    if($submission_date !== false){
//        $submission_date        =  date("Y-m-d H:i:s", strtotime($submission_date));
        $submission_date        =  get_standard_date_from_default_format($submission_date);
    }

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    $data = array(
        "reference_id"      =>$reference_id,
        "regulation"        =>$regulation,
        "control_number"    =>$control_number,
        "location"          =>$location,
        "source"            =>$source,
        "category"          =>$category,
        "team"              =>$team,
        "technology"        =>$technology,
        "owner"             =>$owner,
        "manager"           =>$manager,
        "assessment"        =>$assessment,
        "notes"             =>$notes,
        "last_update"       =>$current_datetime,
        "submission_date"   =>$submission_date,
        "additional_stakeholders"=>$additional_stakeholders
    );

    // Open the database connection
    $db = db_open();

    $sql = "UPDATE risks SET ";
    foreach($data as $key => $value){
        if($value !== false)
            $sql .= " {$key}=:{$key}, ";
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

    // If notification is enabled
    if (notification_extra())
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_risk_update($id);
    }

    // Audit log
    $message = "Risk details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    // Tag the assets to the risk id
    tag_assets_to_risk($id, $assets);

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

        create_subject_order($_SESSION['encrypted_pass']);
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
    $risk_id = $risk_id - 1000;

    // Query the database
    $stmt = $db->prepare("
        SELECT t2.calculated_risk, GREATEST(IFNULL(t3.mitigation_percent, 0), IFNULL(MAX(t4.mitigation_percent), 0)) AS mitigation_percent
        FROM risks t1
            LEFT JOIN risk_scoring t2 ON t1.id=t2.id
            LEFT JOIN mitigations t3 ON t1.id=t3.risk_id
            LEFT JOIN framework_controls t4 ON FIND_IN_SET(t4.id, t3.mitigation_controls)
        WHERE t1.id=:risk_id
        GROUP BY t1.id
    ");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    $risk = $stmt->fetch(PDO::FETCH_ASSOC);

    $risk['calculated_risk'] = empty($risk['calculated_risk']) ? 0 : $risk['calculated_risk'];
    $risk['mitigation_percent'] = empty($risk['mitigation_percent']) ? 0 : $risk['mitigation_percent'];

    $residual_risk = round($risk['calculated_risk'] * (100-$risk['mitigation_percent']) / 100, 2);

    return $residual_risk ? $residual_risk : "0.0";
}

/*********************************
 * FUNCTION: UPDATE RISK SUBJECT *
 *********************************/
function update_risk_subject($risk_id, $subject)
{
    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;

    // Get current datetime for last_update
    $current_datetime = date("Y-m-d H:i:s");

    // Open the database connection
    $db = db_open();

    // Update the risk
    $stmt = $db->prepare("UPDATE risks SET subject=:subject, last_update=:date WHERE id = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":subject", $subject, PDO::PARAM_STR, 1000);
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

        create_subject_order($_SESSION['encrypted_pass']);
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
    $id = $id + 1000;

    return $id;
}

/****************************************
 * FUNCTION: CHECK IF A RISK EXIST BY ID*
 ****************************************/
function check_risk_by_id($id){
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $id = $id - 1000;

    // Query the database
    $stmt = $db->prepare("SELECT b.* FROM risk_scoring a INNER JOIN risks b on a.id = b.id WHERE b.id=:id LIMIT 1");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

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
    $id = $id - 1000;

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
    $id = $id - 1000;

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
        // Query the database
        $stmt = $db->prepare("SELECT a.*, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
        FROM risk_scoring a
            INNER JOIN risks b on a.id = b.id
            LEFT JOIN mgmt_reviews c on b.mgmt_review = c.id
            LEFT JOIN mitigations mg ON b.id = mg.risk_id
            LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
        WHERE b.id=:id 
        GROUP BY
            b.id
        LIMIT 1; ");
    }
    // Otherwise
    else
    {

        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the separation query string
        $separation_query = get_user_teams_query("b", false, true);

        // Query the database
        $stmt = $db->prepare("SELECT a.*, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a INNER JOIN risks b on a.id = b.id LEFT JOIN mgmt_reviews c on b.mgmt_review = c.id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
            WHERE b.id=:id " . $separation_query . " LIMIT 1;
        ");
    }

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array && $array[0]['id'] ? $array : [];
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
    $stmt = $db->prepare("SELECT t1.*, t1.risk_id AS id,
            t2.name as planning_strategy_name,
            t3.name as mitigation_effort_name,
            t4.min_value AS mitigation_min_cost, t4.max_value AS mitigation_max_cost,
            t5.name as mitigation_owner_name,
            t6.name as mitigation_team_name,
            t7.name as submitted_by_name
        FROM mitigations t1
            left join planning_strategy t2 on t1.planning_strategy=t2.value
            left join mitigation_effort t3 on t1.mitigation_effort=t3.value
            left join asset_values t4 on t1.mitigation_cost=t4.id
            left join user t5 on t1.mitigation_owner=t5.value
            left join team t6 on t1.mitigation_team=t6.value
            left join user t7 on t1.submitted_by=t7.value
        WHERE t1.risk_id=:risk_id"
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

/******************************
 * FUNCTION: GET CLOSE BY ID *
 ******************************/
function get_close_by_id($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Subtract 1000 from the id
    $risk_id = $risk_id - 1000;

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

/*****************************
 * FUNCTION: GET RISKS COUNT *
 *****************************/
function get_risks_count($sort_order)
{
    // Open the database connection
    $db = db_open();

    // 1 = Show risks requiring mitigations
    // If this is the default, sort by risk
    if ($sort_order == 0)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" " . $separation_query . " ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 1 = Show risks requiring mitigations
    else if ($sort_order == 1)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE mitigation_id = 0 AND status != \"Closed\" ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE mitigation_id = 0 AND status != \"Closed\" " . $separation_query . " ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 2 = Show risks requiring management review
    else if ($sort_order == 2)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
        // Query the database
        $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE mgmt_review = 0 AND status != \"Closed\" ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE mgmt_review = 0 AND status != \"Closed\" " . $separation_query . " ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 3 = Show risks by review date
    else if ($sort_order == 3)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id LEFT JOIN mgmt_reviews c ON b.mgmt_review = c.id WHERE status != \"Closed\" ORDER BY review_date ASC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

                    // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id LEFT JOIN mgmt_reviews c ON b.mgmt_review = c.id WHERE status != \"Closed\" " . $separation_query . " ORDER BY review_date ASC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status = \"Closed\" ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status = \"Closed\" " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 2) AS c ON a.id = c.risk_id WHERE status != \"Closed\" ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 2) AS c ON a.id = c.risk_id WHERE status != \"Closed\" " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 1) AS c ON a.id = c.risk_id WHERE status != \"Closed\" ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 1) AS c ON a.id = c.risk_id WHERE status != \"Closed\" " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 3) AS c ON a.id = c.risk_id WHERE status != \"Closed\" ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 3) AS c ON a.id = c.risk_id WHERE status != \"Closed\" " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND (owner = :uid OR manager = :uid) ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND (owner = :uid OR manager = :uid) " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 2 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 2 " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 1 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 1 " . $separation_query . " ORDER BY calculated_risk DESC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 11 = Show All Risks by Date Submitted
    else if ($sort_order == 11)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN user c ON b.submitted_by = c.value LEFT JOIN team d ON b.team = d.value ORDER BY DATE(b.submission_date) DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", true, false);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN user c ON b.submitted_by = c.value LEFT JOIN team d ON b.team = d.value " . $separation_query . " ORDER BY DATE(b.submission_date) DESC");
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
            $stmt = $db->prepare("SELECT a.id FROM risks a JOIN mgmt_reviews b ON a.id = b.risk_id JOIN user c ON b.reviewer = c.value LEFT JOIN review d ON b.review = d.value LEFT JOIN next_step e ON b.next_step = e.value ORDER BY DATE(b.submission_date) DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", true, false);

            // Query the database
            $stmt = $db->prepare("SELECT a.id FROM risks a JOIN mgmt_reviews b ON a.id = b.risk_id JOIN user c ON b.reviewer = c.value LEFT JOIN review d ON b.review = d.value LEFT JOIN next_step e ON b.next_step = e.value " . $separation_query . " ORDER BY DATE(b.submission_date) DESC");
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
            $stmt = $db->prepare("SELECT a.id FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value LEFT JOIN team g ON b.mitigation_team = g.value ORDER BY DATE(b.submission_date) DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", true, false);

            // Query the database
            $stmt = $db->prepare("SELECT a.id FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value LEFT JOIN team g ON b.mitigation_team = g.value " . $separation_query . " ORDER BY DATE(b.submission_date) DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 3 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 3 " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 4 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 4 " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 5 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 5 " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT a.id FROM risks a LEFT JOIN closures b ON a.close_id = b.id LEFT JOIN team c ON a.team = c.value LEFT JOIN user d ON b.user_id = d.value LEFT JOIN close_reason e ON b.close_reason = e.value LEFT JOIN risk_scoring f ON a.id = f.id WHERE a.status='Closed' ORDER BY b.closure_date DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id FROM risks a LEFT JOIN closures b ON a.close_id = b.id LEFT JOIN team c ON a.team = c.value LEFT JOIN user d ON b.user_id = d.value LEFT JOIN close_reason e ON b.close_reason = e.value LEFT JOIN risk_scoring f ON a.id = f.id WHERE a.status='Closed' " . $separation_query . " ORDER BY b.closure_date DESC");
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
            $stmt = $db->prepare("SELECT a.id FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN team c ON a.team = c.value WHERE status != 'Closed' ORDER BY a.team, b.calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN team c ON a.team = c.value WHERE status != 'Closed' " . $separation_query . " ORDER BY a.team, b.calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT a.id FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN technology c ON a.technology = c.value WHERE status != 'Closed' ORDER BY a.technology, b.calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN technology c ON a.technology = c.value WHERE status != 'Closed' " . $separation_query . " ORDER BY a.technology, b.calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND a.calculated_risk >= :high ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT b.id FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != \"Closed\" AND a.calculated_risk >= :high " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT * FROM risks " . $separation_query . " ORDER BY id ASC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 22 = Get all open risks by team level by score
    else if ($sort_order == 22)
    {
         $team_query = " AND 1 ";

        $params = array();
        // If at least one team was selected
        if(!empty($_GET['teams'])){
            $teams = explode(",", $_GET['teams']);

            $teamsArray = array();
            foreach($teams as $team){
                $params[] = $team;
                $teamsArray[] = "b.team = :param". (count($params)-1);
            }
            $team_query_string = implode(" OR ", $teamsArray);
            $team_query .= ' AND ('.$team_query_string.') ';
        }else{
            $team_query .= ' AND 0 ';
        }

        // If at least one owner was selected
        if(!empty($_GET['owners'])){
            $owners = explode(",", $_GET['owners']);

            $teamsArray = array();
            foreach($owners as $owner){
                $params[] = $owner;
                $teamsArray[] = "b.owner = :param". (count($params)-1);
            }
            $team_query_string = implode(" OR ", $teamsArray);
            $team_query .= ' AND ('.$team_query_string.') ';
        }else{
            $team_query .= ' AND 0 ';
        }

        // If at least one owner's manager was selected
        if(!empty($_GET['ownersmanagers'])){
            $ownersmanagers = explode(",", $_GET['ownersmanagers']);

            $teamsArray = array();
            foreach($ownersmanagers as $ownersmanager){
                $params[] = $ownersmanager;
                $teamsArray[] = "b.manager = :param". (count($params)-1);
            }
            $team_query_string = implode(" OR ", $teamsArray);
            $team_query .= ' AND ('.$team_query_string.') ';
        }else{
            $team_query .= ' AND 0 ';
        }
        $sql = "SELECT a.id
                FROM risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN team c ON b.team = c.value
                WHERE b.status != \"Closed\"  " . $team_query;

        // If the team separation extra is not enabled
        if (team_separation_extra())
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $sql .=  $separation_query;
        }

        $sql .= " ORDER BY calculated_risk DESC";

        // Query the database
        $stmt = $db->prepare($sql);

        // Set params for teams, owners, owner managers
        for($i=0; $i<count($params); $i++){
            $stmt->bindParam(":param".$i, $params[$i], PDO::PARAM_INT);
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

    }

    // Close the database connection
    db_close($db);

    // Return the number of elements in the array
    return count($array);
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
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != 'Closed' AND (b.project_id IS NULL or b.project_id=0) ORDER BY calculated_risk DESC;");
    }
// If we only want to get risks reviewed as consider for project
    else
    {
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 2) AS c ON a.id = c.risk_id WHERE status != \"Closed\" AND (b.project_id IS NULL or b.project_id=0) ORDER BY calculated_risk DESC");
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

/**********************************
 * FUNCTION: GET RISK FOR PROJECT *
 **********************************/
function get_risk_for_project($project_id)
{
    $db = db_open();

    // If we want to get all risks
    if (get_setting('plan_projects_show_all') == 1)
    {
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id WHERE status != 'Closed' AND b.project_id = :project_id ORDER BY calculated_risk DESC;");
    }
// If we only want to get risks reviewed as consider for project
    else
    {
		    $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 2) AS c ON a.id = c.risk_id WHERE status != \"Closed\" AND b.project_id = :project_id ORDER BY calculated_risk DESC");

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
function get_risks($sort_order=0)
{
    // Open the database connection
    $db = db_open();

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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("
                SELECT
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
                WHERE
                    b.mitigation_id = 0 and b.status != \"Closed\"
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
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
                WHERE
                    b.mitigation_id = 0 and b.status != \"Closed\"  " . $separation_query . "
                GROUP BY b.id
                ORDER BY
                    a.calculated_risk DESC
            ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 2 = Show risks requiring management review
    else if ($sort_order == 2)
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
                WHERE
                    b.mgmt_review = 0 and b.status != \"Closed\"
                GROUP BY
                    b.id
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
                    a.calculated_risk, b.*, c.next_review, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
                WHERE
                    b.mgmt_review = 0 and b.status != \"Closed\"  {$separation_query}
                GROUP BY
                    b.id
                ORDER BY
                    a.calculated_risk DESC

            ");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 3 = Show risks by review date
    else if ($sort_order == 3)
    {
        // If the team separation extra is not enabled
        if (!team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a
                LEFT JOIN risks b ON a.id = b.id
                LEFT JOIN mgmt_reviews c ON b.mgmt_review = c.id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
            WHERE b.status != \"Closed\"
            GROUP BY b.id
            ORDER BY review_date ASC; ");
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
                FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id LEFT JOIN mgmt_reviews c ON b.mgmt_review = c.id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
                WHERE b.status != \"Closed\" " . $separation_query . "
                GROUP BY b.id
                ORDER BY review_date ASC
            ");
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
            FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, c1.next_review, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 2) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                RIGHT JOIN (SELECT c1.risk_id, c1.next_review, next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 1) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
        if (team_separation_extra())
        {
            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
            FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, next_step, c1.next_review, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 3) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                LEFT JOIN risks b ON a.id = b.id RIGHT JOIN (SELECT c1.risk_id, c1.next_review , next_step, date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date WHERE next_step = 3) AS c ON a.id = c.risk_id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
        $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 2 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 2 " . $separation_query . " ORDER BY calculated_risk DESC");
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
    $stmt = $db->prepare("SELECT a.calculated_risk, a.CLASSIC_likelihood, a.CLASSIC_impact, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 1 ORDER BY calculated_risk DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.calculated_risk, a.CLASSIC_likelihood, a.CLASSIC_impact, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 1 " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.status, b.submission_date, group_concat(distinct d.name) AS team, c.name FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN user c ON b.submitted_by = c.value LEFT JOIN team d ON FIND_IN_SET(d.value, b.team) GROUP BY b.id ORDER BY DATE(b.submission_date) DESC ; ");
            }
            else
            {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                    // Get the separation query string
                    $separation_query = get_user_teams_query("b", true, false);

                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.id, b.subject, b.status, b.submission_date, group_concat(DISTINCT d.name SEPARATOR ', ') AS team, c.name FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN user c ON b.submitted_by = c.value LEFT JOIN team d ON FIND_IN_SET(d.value, b.team) " . $separation_query . " GROUP BY b.id ORDER BY DATE(b.submission_date) DESC ; ");
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
                $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS review, e.name AS next_step FROM risks a JOIN mgmt_reviews b ON a.id = b.risk_id JOIN user c ON b.reviewer = c.value LEFT JOIN review d ON b.review = d.value LEFT JOIN next_step e ON b.next_step = e.value " . $separation_query . " ORDER BY DATE(b.submission_date) DESC");
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
            $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS planning_strategy, e.name AS mitigation_effort, b.mitigation_cost, f.name AS mitigation_owner, g.name AS mitigation_team FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value LEFT JOIN team g ON b.mitigation_team = g.value ORDER BY DATE(b.submission_date) DESC");
            }
            else
            {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                    // Get the separation query string
                    $separation_query = get_user_teams_query("a", true, false);

                // Query the database
                $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS planning_strategy, e.name AS mitigation_effort, b.mitigation_cost, f.name AS mitigation_owner, g.name AS mitigation_team FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value LEFT JOIN team g ON b.mitigation_team = g.value " . $separation_query . " ORDER BY DATE(b.submission_date) DESC");
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
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 3 " . $separation_query . " ORDER BY calculated_risk DESC");
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
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 4 " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 5 ORDER BY calculated_risk DESC");
            }
            else
            {
                // Include the team separation extra
                require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                // Get the separation query string
                $separation_query = get_user_teams_query("b", false, true);

                // Query the database
                $stmt = $db->prepare("SELECT a.calculated_risk, b.* FROM risk_scoring a JOIN risks b ON a.id = b.id JOIN risk_scoring c on b.id = c.id WHERE b.status != \"Closed\" AND c.scoring_method = 5 " . $separation_query . " ORDER BY calculated_risk DESC");
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
            $stmt = $db->prepare("SELECT a.id, a.subject, group_concat(DISTINCT c.name SEPARATOR ', ') AS team, d.name AS user, b.closure_date, e.name AS close_reason, f.calculated_risk FROM risks a LEFT JOIN closures b ON a.close_id = b.id LEFT JOIN team c ON FIND_IN_SET(c.value, a.team) LEFT JOIN user d ON b.user_id = d.value LEFT JOIN close_reason e ON b.close_reason = e.value LEFT JOIN risk_scoring f ON a.id = f.id WHERE a.status='Closed' GROUP BY a.id ORDER BY b.closure_date DESC ; ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, group_concat(DISTINCT c.name SEPARATOR ', ') AS team, d.name AS user, b.closure_date, e.name AS close_reason, f.calculated_risk FROM risks a LEFT JOIN closures b ON a.close_id = b.id LEFT JOIN team c ON FIND_IN_SET(c.value, a.team) LEFT JOIN user d ON b.user_id = d.value LEFT JOIN close_reason e ON b.close_reason = e.value LEFT JOIN risk_scoring f ON a.id = f.id WHERE a.status='Closed' " . $separation_query . " GROUP BY a.id ORDER BY b.closure_date DESC; ");
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
        $stmt = $db->prepare("SELECT a.id, a.subject, group_concat(DISTINCT c.name SEPARATOR ', ') AS team, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN team c ON FIND_IN_SET(c.value, a.team) WHERE status != 'Closed' GROUP BY a.id ORDER BY a.team, b.calculated_risk DESC; ");
                }
                else
                {
                        // Include the team separation extra
                        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                        // Get the separation query string
                        $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, group_concat(DISTINCT c.name SEPARATOR ', ') AS team, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN team c ON FIND_IN_SET(c.value, a.team) WHERE status != 'Closed' " . $separation_query . " GROUP BY a.id ORDER BY a.team, b.calculated_risk DESC; ");
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
        $stmt = $db->prepare("SELECT a.id, a.subject, c.name AS technology, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN technology c ON a.technology = c.value WHERE status != 'Closed' ORDER BY a.technology, b.calculated_risk DESC");
                }
                else
                {
                        // Include the team separation extra
                        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                        // Get the separation query string
                        $separation_query = get_user_teams_query("a", false, true);

            // Query the database
            $stmt = $db->prepare("SELECT a.id, a.subject, c.name AS technology, a.submission_date, b.calculated_risk FROM risks a LEFT JOIN risk_scoring b ON a.id = b.id LEFT JOIN technology c ON a.technology = c.value WHERE status != 'Closed' " . $separation_query . " ORDER BY a.technology, b.calculated_risk DESC");
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
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
            $stmt = $db->prepare("SELECT * FROM risks " . $separation_query . " ORDER BY id ASC");
        }

        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();
    }

    // 22 = Get all open risks by team level by score
    else if ($sort_order == 22)
    {
        $team_query = " AND 1 ";

        $params = array();
        // If at least one team was selected
        if(!empty($_GET['teams'])){
            $teams = explode(",", $_GET['teams']);

            $teamsArray = array();
            foreach($teams as $team){
                $params[] = $team;
                $teamsArray[] = "b.team = :param". (count($params)-1);
            }
            $team_query_string = implode(" OR ", $teamsArray);
            $team_query .= ' AND ('.$team_query_string.') ';
        }else{
            $team_query .= ' AND 0 ';
        }

        // If at least one owner was selected
        if(!empty($_GET['owners'])){
            $owners = explode(",", $_GET['owners']);

            $teamsArray = array();
            foreach($owners as $owner){
                $params[] = $owner;
                $teamsArray[] = "b.owner = :param". (count($params)-1);
            }
            $team_query_string = implode(" OR ", $teamsArray);
            $team_query .= ' AND ('.$team_query_string.') ';
        }else{
            $team_query .= ' AND 0 ';
        }

        // If at least one owner's manager was selected
        if(!empty($_GET['ownersmanagers'])){
            $ownersmanagers = explode(",", $_GET['ownersmanagers']);

            $teamsArray = array();
            foreach($ownersmanagers as $ownersmanager){
                $params[] = $ownersmanager;
                $teamsArray[] = "b.manager = :param". (count($params)-1);
            }
            $team_query_string = implode(" OR ", $teamsArray);
            $team_query .= ' AND ('.$team_query_string.') ';
        }else{
            $team_query .= ' AND 0 ';
        }
        $sql = "SELECT a.calculated_risk, b.*, c.name team_name
                FROM risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN team c ON b.team = c.value
                WHERE b.status != \"Closed\"  " . $team_query;

        // If the team separation extra is not enabled
        if (team_separation_extra())
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("b", false, true);

            // Query the database
            $sql .=  $separation_query;
        }

        $sql .= " ORDER BY calculated_risk DESC";

        // Query the database
        $stmt = $db->prepare($sql);

        // Set params for teams, owners, owner managers
        for($i=0; $i<count($params); $i++){
            $stmt->bindParam(":param".$i, $params[$i], PDO::PARAM_INT);
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
//    $count = get_risks_count($sort_order);

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
    echo "<th align=\"center\" width=\"80px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['Submitted']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\" class=\"mitigation-head\">". $escaper->escapeHtml($lang['MitigationPlanned']) ."</th>\n";
    echo "<th align=\"center\" width=\"160px\">". $escaper->escapeHtml($lang['ManagementReview']) ."</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    $review_levels = get_review_levels();

    // For each risk
    for ($i=$offset; $i<min($rowsperpage+$offset, $count); $i++)
    {
        // Get the risk
        $risk = $risks[$i];

        // Get the risk color
        $color = get_risk_color($risk['calculated_risk']);

        echo "<tr data-id='" . $escaper->escapeHtml(convert_id($risk['id'])) . "'>\n";

        // if this is All Open Risks by Team by Risk Levle page
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
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("g:i A T"), strtotime($risk['submission_date']))) . "</td>\n";

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
function get_submitted_risks_table($sort_order=11)
{
    global $lang;
    global $escaper;

        // Get risks
        $risks = get_risks($sort_order);

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
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
    echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
    echo "<td class=\"risk-cell\" align=\"center\" bgcolor=\"" . $escaper->escapeHtml($color) . "\" width=\"150px\">" . $escaper->escapeHtml($risk['calculated_risk']) . " <span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . " \"></span> </td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['status']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['team']) . "</td>\n";
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['name']) . "</td>\n";
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
    global $escaper;

        // Get risks
        $risks = get_risks($sort_order);

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
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
            echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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

        echo "<table class=\"table table-bordered table-condensed sortable\">\n";
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
    echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
    echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['review']) . "</td>\n";
    echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['next_step']) . "</td>\n";
    echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['name']) . "</td>\n";
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
    global $escaper;

    // Get risks
    $risks = get_risks($sort_order);

    echo "<table class=\"table table-bordered table-condensed sortable\">\n";
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
        echo "<td align=\"center\" width=\"150px\">" . ( !$risk['closure_date'] ? $lang["Unknown"] : $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['closure_date']))) ) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['user']) . "</td>\n";
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml($risk['close_reason']) . "</td>\n";
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
    global $escaper;

    // Get risks
    $risks = get_risks($sort_order);

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
                echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($current_team) ."</font></center></th>\n";
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
                echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
                echo "</tr>\n";
    }
}

/*****************************************
 * FUNCTION: GET RISK TECHNOLOGIES TABLE *
 *****************************************/
function get_risk_technologies_table($sort_order=19)
{
        global $lang;
    global $escaper;

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
                        echo "<th bgcolor=\"#0088CC\" colspan=\"4\"><center><font color=\"#FFFFFF\">". $escaper->escapeHtml($current_technology) ."</font></center></th>\n";
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
                echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
                echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
        echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
                    echo "<td align=\"center\" width=\"150px\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
            $stmt = $db->prepare("INSERT INTO risks (`status`, `subject`, `reference_id`, `regulation`, `control_number`, `location`, `source`, `category`, `team`, `technology`, `owner`, `manager`, `assessment`, `notes`, `submitted_by`, last_update, review_date, mitigation_id, mgmt_review, project_id) SELECT `status`, `subject`, `reference_id`, `regulation`, `control_number`, `location`, `source`, `category`, `team`, `technology`, `owner`, `manager`, `assessment`, `notes`, `submitted_by`, last_update, review_date, mitigation_id, mgmt_review, :project_id as project_id FROM risks WHERE id = :risk_id");
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->bindParam(":project_id", $project_id, PDO::PARAM_INT);
            $stmt->execute();

            $last_insert_id = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO risk_scoring (`id`, `scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact`) SELECT :new_risk_id as id, `scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact` FROM risk_scoring WHERE id = :risk_id");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $db->prepare("INSERT INTO mitigations (`risk_id`, `planning_strategy`, `mitigation_effort`, `mitigation_cost`, `mitigation_owner`, `mitigation_team`, `current_solution`, `security_requirements`, `security_recommendations`, `submitted_by`) SELECT :new_risk_id as risk_id, `planning_strategy`, `mitigation_effort`, `mitigation_cost`, `mitigation_owner`, `mitigation_team`, `current_solution`, `security_requirements`, `security_recommendations`, `submitted_by` FROM mitigations WHERE risk_id = :risk_id");
            $stmt->bindParam(":new_risk_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->execute();

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

/******************************************
 * FUNCTION: GET PROJECTS COUNT BY STATUS *
 ******************************************/
function get_projects_count($status)
{
    $projects = count_by_status($status);
    if ($status == 1)
    {
          echo $projects[0]['count'] - 1;
    }
    else
    {
          echo $projects[0]['count'];
    }
}

/********************************************
 * FUNCTION: UPDATE PROJECTS HTML BY STATUS *
 ********************************************/
function get_project_tabs($status)
{
    global $lang;
    global $escaper;

    $projects = get_projects();

    if ($status == 1)
    {
        $i = null;
    } else
    {
        $i = 1;
    }

    foreach ($projects as $project)
    {
        if ($project['status'] == $status)
        {
            $id = (int)$project['value'];
            $name = $project['name'];

            // If unassigned risks
            if ($i == null)
            {
                $delete = '';
                $no_sort = 'id = "no-sort"';
                $name = $escaper->escapehtml($lang['UnassignedRisks']);

                // Get risks for this project
                $risks = get_risks_unassigned_project();
            }
            // If project ID was defined
            else
            {
                $delete = '<a href="javascript:voice(0);" class="project-block--delete pull-right" data-id="'.$escaper->escapeHtml($id).'"><i class="fa fa-trash"></i></a>';
                $no_sort = '';
                $name = $escaper->escapeHtml($name);

                // Get risks for this project
                $risks = get_risk_for_project($id);
            }

            // Get count of risks for this project
            $count = count($risks);

            echo '<div class="project-block clearfix" '.$no_sort.'>';
                echo '<div class="project-block--header clearfix" data-project="'.$escaper->escapeHtml($id).'">
                <div class="project-block--priority pull-left">'.$escaper->escapeHtml($i).'</div>
                <div class="project-block--name pull-left">'. $name .'</div>
                <div class="project-block--risks pull-left"><span>'.$count.'</span><a href="#" class="view--risks">'.$escaper->escapeHtml($lang['ViewRisk']).'</a>'.$delete.'</div>
                </div>';

                echo '<div class="risks">';

                // For each risk
                foreach ($risks as $risk)
                {
                    $subject = try_decrypt($risk['subject']);
                    $risk_id = (int)$risk['id'];
                    $project_id = (int)$risk['project_id'];
                    $color = get_risk_color($risk['calculated_risk']);

                    $risk_number = $risk_id + 1000;

                    echo '<div class="risk clearfix">
                            <div class="pull-left risk--title"  data-risk="'.$escaper->escapeHtml($risk_id).'"><a href="../management/view.php?id=' . $escaper->escapeHtml(convert_id($risk_id)) . '" target="_blank">#'.$risk_number.' '.$escaper->escapeHtml($subject).'</a></div>
                            <div class="pull-right risk--score"> ' . $escaper->escapeHtml($lang['InherentRisk']) . ' : <span class="label label-danger" style="background-color: '. $escaper->escapeHtml($color) .'">'.$risk['calculated_risk'].'</span> </div>
                            </div>';
                }

                echo "</div>\n";

            echo "</div>\n";
            $i++;
        }
    }

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

/*******************************
 * FUNCTION: GET REVIEWS TABLE *
 *******************************/
function get_reviews_table($sort_order=3)
{
    global $lang;
    global $escaper;

    $count = get_risks_count($sort_order);

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


    // Get the list of reviews
    $risks = get_risks($sort_order);

    // Initialize the arrays
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
            $date_next_review[] = $next_review[$key];
            $date_calculated_risk[] = $calculated_risk[$key];
        }
    }

    // Sort the need reviews array by next_review
    array_multisort($need_next_review, SORT_DESC, SORT_STRING, $need_calculated_risk, SORT_DESC, SORT_NUMERIC, $need_reviews);

    // Sort the reviews array by next_review
    array_multisort($date_next_review, SORT_ASC, SORT_STRING, $date_calculated_risk, SORT_DESC, SORT_NUMERIC, $reviews);

    // Merge the two arrays back together to a single reviews array
    $reviews = array_merge($need_reviews, $reviews);

    echo "<table class=\"table table-bordered table-striped table-condensed sortable table-margin-top\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" width=\"50px\">". $escaper->escapeHtml($lang['ID']) ."</th>\n";
    echo "<th align=\"left\" width=\"150px\">". $escaper->escapeHtml($lang['Status']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Subject']) ."</th>\n";
    echo "<th align=\"center\" width=\"65px\">". $escaper->escapeHtml($lang['Risk']) ."</th>\n";
    echo "<th align=\"center\" width=\"100px\">". $escaper->escapeHtml($lang['DaysOpen']) ."</th>\n";
    echo "<th align=\"center\" width=\"150px\">". $escaper->escapeHtml($lang['NextReviewDate']) ."</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

        // For each risk
        //foreach ($reviews as $review)
    for ($i=$offset; $i<min($rowsperpage+$offset, $count); $i++)
        {
        // Get the review
        $review = $reviews[$i];

                $risk_id = $review['risk_id'];
                $subject = $review['subject'];
                $status = $review['status'];
                $calculated_risk = $review['calculated_risk'];
                $color = $review['color'];
                $dayssince = $review['dayssince'];
                $next_review = $review['next_review'];
                $next_review_html = $review['next_review_html'];

                echo "<tr data-id='" . $escaper->escapeHtml(convert_id($risk_id)) . "' >\n";
                echo "<td align=\"left\" width=\"50px\" class='open-risk'><a href=\"../management/view.php?id=" . $escaper->escapeHtml(convert_id($risk_id)) . "\">" . $escaper->escapeHtml(convert_id($risk_id)) . "</a></td>\n";
                echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($status) . "</td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($subject) . "</td>\n";
                echo "<td align=\"center\" class=\"" . $escaper->escapeHtml($color) . " risk-cell\">" . $escaper->escapeHtml($calculated_risk) . "<span class=\"risk-color\" style=\"background-color:" . $escaper->escapeHtml($color) . "\"></span></td>\n";
                echo "<td align=\"center\" width=\"100px\">" . $escaper->escapeHtml($dayssince) . "</td>\n";
                echo "<td align=\"center\" width=\"150px\" class=\"text-center open-review \">" . $next_review_html . "</td>\n";
                echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";

        echo "<div class=\"pagination clearfix\"><ul class=\"pull-right\">";

        // range of num links to show
        $range = 3;


        if (!empty ($reviews))
        {
                // if not on page 1, don't show back links
        if ($currentpage > 1) {
           // show << link to go back to page 1
           echo " <li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage=1' class=\"no-bg\"><i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i></a></li> ";
           // get previous page num
           $prevpage = $currentpage - 1;
           // show < link to go back to 1 page
           echo " <li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage=$prevpage' class=\"no-bg\"><i class=\"fa fa-chevron-left\"></i></a></li> ";
        } else { // end if
           echo " <li><a href='javascript:void(0);' class=\"no-bg\"><i class=\"fa fa-chevron-left\"></i></a></li> ";
        }

        // loop to show links to range of pages around current page
        for ($x = ($currentpage - $range); $x < (($currentpage + $range) + 1); $x++) {
           // if it's a valid page number...
           if (($x > 0) && ($x <= $totalpages)) {
              // if we're on current page...
              if ($x == $currentpage) {
                 // 'highlight' it but don't make a link
                 echo " <li class=\"active\"><a href=\"#\">$x</a></li>";
              // if not current page...
              } else {
                 // make it a link
                 echo " <li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage=$x'>$x</a></li> ";
              } // end else
           } // end if
        } // end for

        // if not on last page, show forward and last page links
        if ($currentpage != $totalpages) {
           // get next page
           $nextpage = $currentpage + 1;
            // echo forward link for next page
           echo " <li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage=$nextpage' class=\"no-bg\"><i class=\"fa fa-chevron-right\"></i></a></li> ";
           // echo forward link for lastpage
          echo " <li><a href='{$_SERVER['SCRIPT_NAME']}?currentpage=$totalpages' class=\"no-bg\"><i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i></a></li> ";
        } else { // end if
           echo " <li><a href='javascript:void(0);' class=\"no-bg\"><i class=\"fa fa-chevron-right\"></i></a></li> ";
        }
        /****** end build pagination links ******/
        }

        echo " <li $all_style><a href='{$_SERVER['SCRIPT_NAME']}?currentpage=all'>All</a></li> ";

        echo "</ul></div>";

        return true;
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
function management_review($risk_id, $mgmt_review, $next_review)
{
    global $lang;
    global $escaper;

    // If the review hasn't happened
    if ($mgmt_review == "0")
    {
        $value = "<a href=\"../management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) ."\">". $escaper->escapeHtml($lang['No']) ."</a>";
    }else{
        $d = DateTime::createFromFormat('Y-m-d', $next_review);
//        if($d && $d->format('Y-m-d') === $next_review && (strtotime($next_review) + 24*3600) <= time() ){
        if($d && $d->format('Y-m-d') === $next_review ){
            // If review doensn't past due.
            $value = "<a class=\"management yes\" href=\"../management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) ."\">".$escaper->escapeHtml($lang['Yes']).'</a>';
        }else{
            // If review past due.
            $value = "<a class=\"management pastdue\" href=\"../management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) ."\">".$escaper->escapeHtml($lang['PASTDUE']).'</a>';
        }
    }

    return $value;
}

/********************************
 * FUNCTION: PLANNED MITIGATION *
 ********************************/
function planned_mitigation($risk_id, $mitigation_id)
{
    global $lang;
    global $escaper;

        // If the review hasn't happened
        if ($mitigation_id == "0")
        {
                $value = "<a href=\"../management/mitigate.php?type=1&id=" . $escaper->escapeHtml($risk_id) . "\">". $escaper->escapeHtml($lang['No']) ."</a>";
        }
        else
        {
                $value = "<a class=\"mitigation yes\" href=\"../management/view.php?type=1&id=" . $escaper->escapeHtml($risk_id) . "\">".$escaper->escapeHtml($lang['Yes'])."</a>";
        }

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
        return $array[0]['name'];
    }
    // Otherwise, return an empty string
    else return "";
}

/*******************************
 * FUNCTION: GET NAME BY VALUE *
 *******************************/
function get_names_by_multi_values($table, $values)
{

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT name FROM $table WHERE FIND_IN_SET(value, :values) ;");
    $stmt->bindParam(":values", $values, PDO::PARAM_STR);

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Close the database connection
    db_close($db);

    // If we get a value back from the query
    if ($array)
    {
        // Return that value
        return implode(", ", $array);
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
function next_review($risk_level, $id, $next_review, $html = true, $review_levels = array(), $submission_date = false)
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
            $text = $next_review->format(DATESIMPLE);
        }
        else $text = $lang['PASTDUE'];
    }

    // If we want to include the HTML code
    if ($html == true)
    {
	    // Convert the database ID to a risk ID
	    $risk_id = convert_id($id);

        // Add the href tag to make it HTML
        $html = "<a href=\"../management/mgmt_review.php?id=" . $escaper->escapeHtml($risk_id) . "\">" . $escaper->escapeHtml($text) . "</a>";

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
    $next_review = $next_review->format(DATESIMPLE);

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

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Open the database connection
    $db = db_open();

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
    $close_id = get_close_id($id);

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

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Open the database connection
    $db = db_open();

    // Add the closure
    $stmt = $db->prepare("INSERT INTO comments (`risk_id`, `user`, `comment`) VALUES (:risk_id, :user, :comment)");

    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":comment", try_encrypt($comment), PDO::PARAM_STR);

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
function get_comments($id)
{
    global $escaper;

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
}

/*****************************
 * FUNCTION: GET AUDIT TRAIL *
 *****************************/
function get_audit_trail($id = NULL, $days = 7, $log_type=NULL)
{
    global $escaper;

    // If the ID is greater than 1000 or NULL
    if ($id > 1000 || $id === NULL)
    {
        // Open the database connection
        $db = db_open();

        // If the ID is greater than 1000
        if ($id > 1000)
        {
            // Subtract 1000 from id
            $id = $id - 1000;

            // Get the comments for this specific ID
            $stmt = $db->prepare("SELECT timestamp, message FROM audit_log WHERE risk_id=:risk_id AND (`timestamp` > CURDATE()-INTERVAL :days DAY) AND log_type=:log_type ORDER BY timestamp DESC");

            $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":days", $days, PDO::PARAM_INT);
            $stmt->bindParam(":log_type", $log_type, PDO::PARAM_STR, 100);
        }
        // If the ID is NULL
        else if ($id === NULL)
        {
            // If log_type is NULL, shows all logs
            if($log_type === NULL){
                // Get the full audit trail
                $stmt = $db->prepare("SELECT timestamp, message FROM audit_log WHERE (`timestamp` > CURDATE()-INTERVAL :days DAY) ORDER BY timestamp DESC");
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
                $log_type_condition_array = [];
                foreach($log_type_array as $key => $val){
                    $log_type_condition_array[] = "log_type=:log_type_{$key}";
                }

                $log_type_where = implode(" OR ", $log_type_condition_array);

                // Get the full audit trail
                $stmt = $db->prepare("SELECT timestamp, message, log_type FROM audit_log WHERE (`timestamp` > CURDATE()-INTERVAL :days DAY) AND ({$log_type_where}) ORDER BY timestamp DESC");
                $stmt->bindParam(":days", $days, PDO::PARAM_INT);
                foreach($log_type_array as $key => $val){
                    $stmt->bindParam(":log_type_{$key}", trim($val), PDO::PARAM_STR, 100);
                }
            }
        }

        $stmt->execute();
       // Store the list in the array
        $logs = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        foreach ($logs as $log)
        {
            $text = try_decrypt($log['message']);
            $date = date(get_default_datetime_format("g:i A T"), strtotime($log['timestamp']));

            echo "<p>" . $escaper->escapeHtml($date) . " > " . $escaper->escapeHtml($text) . "</p>\n";
        }

        // Return true
        return true;
    }
    // Otherwise this is not a valid ID
    else
    {
        // Return false
        return false;
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

    $planning_strategy  = (int)$post['planning_strategy'];
    $mitigation_effort  = (int)$post['mitigation_effort'];
    $mitigation_cost    = (int)$post['mitigation_cost'];
    $mitigation_owner   = (int)$post['mitigation_owner'];
    $mitigation_team    = (int)$post['mitigation_team'];
    $current_solution   = try_encrypt($post['current_solution']);
    $security_requirements      = try_encrypt($post['security_requirements']);
    $security_recommendations   = try_encrypt($post['security_recommendations']);
    $planning_date      = $post['planning_date'];
    $mitigation_percent = (isset($post['mitigation_percent']) && $post['mitigation_percent'] >= 0 && $post['mitigation_percent'] <= 100) ? $post['mitigation_percent'] : 0;
    $mitigation_controls = empty($post['mitigation_controls']) ? [] : $post['mitigation_controls'];
    $mitigation_controls = is_array($mitigation_controls) ? implode(",", $mitigation_controls) : $mitigation_controls;

    if (!validate_date($planning_date, get_default_date_format()))
    {
        $planning_date = "0000-00-00";
    }
    // Otherwise, set the proper format for submitting to the database
    else
    {
//        $planning_date = date("Y-m-d", strtotime($planning_date));
        $planning_date = get_standard_date_from_default_format($planning_date);
    }


    // Get current datetime for last_update
    $current_datetime = date("Y-m-d H:i:s");

    // Open the database connection
    $db = db_open();

        // Update the risk
    $stmt = $db->prepare("UPDATE mitigations SET last_update=:date, planning_strategy=:planning_strategy, mitigation_effort=:mitigation_effort, mitigation_cost=:mitigation_cost, mitigation_owner=:mitigation_owner, mitigation_team=:mitigation_team, current_solution=:current_solution, security_requirements=:security_requirements, security_recommendations=:security_recommendations, planning_date=:planning_date, mitigation_percent=:mitigation_percent, mitigation_controls=:mitigation_controls WHERE risk_id=:id");

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
    $stmt->bindParam(":planning_strategy", $planning_strategy, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_effort", $mitigation_effort, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_cost", $mitigation_cost, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_owner", $mitigation_owner, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_team", $mitigation_team, PDO::PARAM_INT);
    $stmt->bindParam(":current_solution", $current_solution, PDO::PARAM_STR);
    $stmt->bindParam(":security_requirements", $security_requirements, PDO::PARAM_STR);
    $stmt->bindParam(":security_recommendations", $security_recommendations, PDO::PARAM_STR);
    $stmt->bindParam(":planning_date", $planning_date, PDO::PARAM_STR, 10);
    $stmt->bindParam(":mitigation_percent", $mitigation_percent, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_controls", $mitigation_controls, PDO::PARAM_STR, 500);
    $stmt->execute();
    // If notification is enabled
    if (notification_extra())
    {
        // Include the notification extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_mitigation_update($id);
    }

    // Audit log
    $message = "Risk mitigation details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
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
//        if(!empty($post['unique_names'])){
//            refresh_files_for_risk($post['unique_names'], $id, 2);
//        }
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
    $residual_risk = get_residual_risk($id + 1000);
    add_residual_risk_scoring_history($id, $residual_risk);

    return $current_datetime;
}

/**************************
 * FUNCTION: GET REVIEWS *
 **************************/
function get_reviews($risk_id)
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
        $active_fields = get_active_fields();
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
                    display_main_review_feilds_by_panel_view('left', $active_fields, $risk_id, $review['id'], $review_date, $review['reviewer'], $review['review'], $review['next_step'], "", $comment);
                    echo "&nbsp;";
                echo "</div>";

                // Right Panel
                echo "<div class=\"span5 right-panel\">\n";
                    display_main_review_feilds_by_panel_view('right', $active_fields, $risk_id, $review['id'], $review_date, $review['reviewer'], $review['review'], $review['next_step'], "", $comment);
                    echo "&nbsp;";
                echo "</div>";
            echo "</div>";

            // Bottom panel
            echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 bottom-panel\">";
                    display_main_review_feilds_by_panel_view('bottom', $active_fields, $risk_id, $review['id'], $review_date, $review['reviewer'], $review['review'], $review['next_step'], "", $comment);
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

                    display_next_step_view($review['next_step']);

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
function latest_version($param)
{
    $version_page = file('https://updates.simplerisk.com/Current_Version.xml');

    if ($param == "app")
    {
        $regex_pattern = "/<appversion>(.*)<\/appversion>/";
    }
    else if ($param == "db")
    {
        $regex_pattern = "/<dbversion>(.*)<\/dbversion>/";
    }
    else if ($param == "authentication")
    {
        $regex_pattern = "/<authentication>(.*)<\/authentication>/";
    }
    else if ($param == "encryption")
    {
        $regex_pattern = "/<encryption>(.*)<\/encryption>/";
    }
    else if ($param == "importexport")
    {
        $regex_pattern = "/<importexport>(.*)<\/importexport>/";
    }
    else if ($param == "notification")
    {
        $regex_pattern = "/<notification>(.*)<\/notification>/";
    }
    else if ($param == "separation")
    {
        $regex_pattern = "/<separation>(.*)<\/separation>/";
    }
    else if ($param == "upgrade")
    {
        $regex_pattern = "/<upgrade>(.*)<\/upgrade>/";
    }
    else if ($param == "assessments")
    {
        $regex_pattern = "/<assessments>(.*)<\/assessments>/";
    }
    else if ($param == "api")
    {
        $regex_pattern = "/<api>(.*)<\/api>/";
    }
    else if ($param == "complianceforge")
    {
        $regex_pattern = "/<complianceforge>(.*)<\/complianceforge>/";
    }
    else if ($param == "complianceforgescf")
    {
        $regex_pattern = "/<complianceforgescf>(.*)<\/complianceforgescf>/";
    }
    else if ($param == "customization")
    {
        $regex_pattern = "/<customization>(.*)<\/customization>/";
    }

    foreach ($version_page as $line)
    {
            if (preg_match($regex_pattern, $line, $matches))
            {
                    $latest_version = $matches[1];
            }
    }

    // Return the latest version
    return $latest_version;
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
    $risk_id = $risk_id - 1000;

    // If the user_id value is not set
    if (!isset($user_id))
    {
        $user_id = 0;
    }

    $current_time = date("Y-m-d H:i:s");

    // Open the database connection
    $db = db_open();

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

    $announcements = "<ul>\n";

        $announcement_file = file('https://updates.simplerisk.com/announcements.xml');

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
function language_file()
{
	// If the language is set for the user
	if (isset($_SESSION['lang']))
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
        // Open the database connection
        $db = db_open();

    // See if the custom authentication extra is available
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
        else if ($array[0]['value'] == "true")
        {
                return true;
        }
        else return false;
}

/*********************************
 * FUNCTION: CUSTOMIZATION EXTRA *
 *********************************/
function customization_extra()
{
    // Open the database connection
    $db = db_open();

    // See if the customization extra is available
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'customization'");
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
    else if ($array[0]['value'] == "true")
    {
          return true;
    }
    else return false;
}

/*****************************************
 * FUNCTION: GET SETTING VALUE BY NAME *
 *****************************************/
function get_settting_by_name($name){
    // Open the database connection
    $db = db_open();

    // See if the custom authentication extra is available
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = '{$name}'");
    $stmt->execute();

    // Get the results array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array))
    {
        // Return false
        return false;
    }
    else return $array[0]['value'];
}

/***********************************
 * FUNCTION: TEAM SEPARATION EXTRA *
 ***********************************/
function team_separation_extra()
{
        // Open the database connection
        $db = db_open();

    // See if the team separation extra is available
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
    else if ($array[0]['value'] == "true")
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

    // See if the notification extra is available
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
    else if ($array[0]['value'] == "true")
    {
        return true;
    }
    else return false;
}

/*********************************
 * FUNCTION: IMPORT EXPORT EXTRA *
 *********************************/
function import_export_extra()
{
        // Open the database connection
        $db = db_open();

    // See if the import export extra is available
        $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'import_export'");
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
        else if ($array[0]['value'] == "true")
        {
                return true;
        }
        else return false;
}

/***********************
 * FUNCTION: API EXTRA *
 ***********************/
function api_extra()
{
        // Open the database connection
        $db = db_open();

        // See if the api extra is available
        $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'api'");
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
        else if ($array[0]['value'] == "true")
        {
                return true;
        }
        else return false;
}

/*******************************
 * FUNCTION: ASSESSMENTS EXTRA *
 *******************************/
function assessments_extra()
{
    // Open the database connection
    $db = db_open();

    // See if the assessments extra is available
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'assessments'");
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
    else if ($array[0]['value'] == "true")
    {
        return true;
    }
    else return false;
}

/***********************************
 * FUNCTION: COMPLIANCEFORGE EXTRA *
 ***********************************/
function complianceforge_extra()
{
    // Open the database connection
    $db = db_open();

    // See if the complianceforge extra is available
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'complianceforge'");
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
    else if ($array[0]['value'] == "true")
    {
        return true;
    }
    else return false;
}

/***************************************
 * FUNCTION: COMPLIANCEFORGE SCF EXTRA *
 ***************************************/
function complianceforge_scf_extra()
{
    // Open the database connection
    $db = db_open();

    // See if the complianceforge extra is available
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'complianceforge_scf'");
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
    else if ($array[0]['value'] == "true")
    {
        return true;
    }
    else return false;
}

/******************************
 * FUNCTION: GOVERNANCE EXTRA *
 ******************************/
function governance_extra()
{
    // Open the database connection
    $db = db_open();

    // See if the governance extra is available
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'governance'");
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
    else if ($array[0]['value'] == "true")
    {
        return true;
    }
    else return false;
}

/****************************************
 * FUNCTION: CHECK INSTALLED PHP-MCRYPT *
 ****************************************/
function installed_mcrypt(){
    return extension_loaded("mcrypt");
}

/******************************
 * FUNCTION: ENCRYPTION EXTRA *
 ******************************/
function encryption_extra()
{
    if(isset($GLOBALS['encryption_extra'])){
        return $GLOBALS['encryption_extra'];
    }
    // Open the database connection
    $db = db_open();

    // See if the encryption extra is available
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `name` = 'encryption'");
    $stmt->execute();

    // Get the results array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If no value was found
    if (empty($array))
    {
        $GLOBALS['encryption_extra'] = false;
        return false;
    }
    // If the value is true
    else if ($array[0]['value'] == "true")
    {
        $GLOBALS['encryption_extra'] = true;
        return true;
    }
    else {
        $GLOBALS['encryption_extra'] = false;
        return false;
    }
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

    // Get the result
    $result = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Create an array of allowed types
    foreach ($result as $key => $row)
    {
        $allowed_types[] = $row['name'];
    }

    // If a file was submitted and the name isn't blank
    if (isset($file) && $file['name'] != "")
    {
        // If the file type is appropriate
        if (in_array($file['type'], $allowed_types))
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
function download_file($unique_name)
{
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the file from the database
    $stmt = $db->prepare("SELECT * FROM files WHERE BINARY unique_name=:unique_name");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
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
            if (extra_grant_access($_SESSION['uid'], $array['risk_id'] + 1000))
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

function checkApprove($risk_level){

    // Default is not approved
    $approved = false;

    $very_high_display_name = get_risk_level_display_name('Very High');
    $high_display_name      = get_risk_level_display_name('High');
    $medium_display_name    = get_risk_level_display_name('Medium');
    $low_display_name       = get_risk_level_display_name('Low');
    $insignificant_display_name = get_risk_level_display_name('Insignificant');

    // If the risk level is very high and they have permission
    if (($risk_level == $very_high_display_name) && ($_SESSION['review_veryhigh'] == 1))
    {
        // Review is approved
        $approved = true;
    }
    // If the risk level is high and they have permission
    else if (($risk_level == $high_display_name) && ($_SESSION['review_high'] == 1))
    {
        // Review is approved
        $approved = true;
    }
    // If the risk level is medium and they have permission
    else if (($risk_level == $medium_display_name) && ($_SESSION['review_medium'] == 1))
    {
        // Review is approved
        $approved = true;
    }
    // If the risk level is low and they have permission
    else if (($risk_level == $low_display_name) && ($_SESSION['review_low'] == 1))
    {
        // Review is approved
        $approved = true;
    }
    // If the risk level is insignificant and they have permission
    else if (($risk_level == $insignificant_display_name) && ($_SESSION['review_insignificant'] == 1))
    {
        // Review is approved
        $approved = true;
    }

    return $approved;
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
            echo '<label for="file-upload" class="btn active-textfield">Choose File</label> <span class="file-count-html"><span class="file-count">0</span> File Added</span>';
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
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
                    <a href='#' class='remove-file' ><i class='fa fa-remove'></i></a>
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

    // Remove asset mapping for the risk
    $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE `risk_id`=:id;");
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

        // Close the database connection
        db_close($db);

        // Audit log
        $risk_id = $risk_id + 1000;
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
    $stmt = $db->prepare("SELECT id FROM `risks` WHERE `team` = :team");
    $stmt->bindParam(":team", $team, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

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
                $id = $risk['id'] + 1000;
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
            $id = $risk['id'] + 1000;

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
    // Get the current debug setting
    $debug_logging = get_setting("debug_logging");

    // If DEBUG is enabled
    if ($debug_logging == 1)
    {
        // Log file to write to
        $log_file = get_setting("debug_log_file");

        // Write to the error log
        $return = error_log(date('c')." ".$value."\n", 3, $log_file);
    }
}

/******************************
 * FUNCTION: ADD REGISTRATION *
 ******************************/
function add_registration($name, $company, $title, $phone, $email)
{
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
    );

    // Register instance with the web service
    $results = simplerisk_service_call($data);
    $regex_pattern = "/<api_key>(.*)<\/api_key>/";

    foreach ($results as $line)
    {
            if (preg_match($regex_pattern, $line, $matches))
            {
                $services_api_key = $matches[1];

            // Open the database connection
            $db = db_open();

                // Add the registration
                $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('registration_name', :name), ('registration_company', :company), ('registration_title', :title), ('registration_phone', :phone), ('registration_email', :email), ('services_api_key', :services_api_key)");
                $stmt->bindParam(":name", $name, PDO::PARAM_STR, 200);
                $stmt->bindParam(":company", $company, PDO::PARAM_STR, 200);
                $stmt->bindParam(":title", $title, PDO::PARAM_STR, 200);
                $stmt->bindParam(":phone", $phone, PDO::PARAM_STR, 200);
                $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
            $stmt->bindParam(":services_api_key", $services_api_key, PDO::PARAM_STR, 50);
                $stmt->execute();

            // Mark the instance as registered
            $stmt = $db->prepare("UPDATE `settings` SET value=1 WHERE name='registration_registered';");
            $stmt->execute();

            // Download the upgrade extra
            $result = download_extra("upgrade");

            // Close the database connection
            db_close($db);

            // Return the result
            return $result;
            }
    }

        // Return a failure
        return 0;
}

/*********************************
 * FUNCTION: UPDATE REGISTRATION *
 *********************************/
function update_registration($name, $company, $title, $phone, $email)
{
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
        );

        // Register instance with the web service
        $results = simplerisk_service_call($data);
        $regex_pattern = "/<result>success<\/result>/";

        foreach ($results as $line)
        {
        // If the service returned a success
                if (preg_match($regex_pattern, $line, $matches))
                {
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

                        // Download the update extra
                        $result = download_extra("upgrade");

                // Close the database connection
                db_close($db);

            // Return the result
            return $result;
        }
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
    $id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Update the status
    if( $status == "Closed" && check_risk_by_id($risk_id)){
        // Get current datetime for last_update
        $current_datetime = date('Y-m-d H:i:s');
        $reviewer   = $_SESSION['uid'];
        $review     = 0;
        $next_step  = 0;
        $next_review = "0000-00-00";
        
        $stmt = $db->prepare("INSERT INTO mgmt_reviews (`risk_id`, `review`, `reviewer`, `next_step`, `comments`, `next_review`, `submission_date`) VALUES (:risk_id, :review, :reviewer, :next_step, :comments, :next_review, :submission_date)");
        
        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":review", $review, PDO::PARAM_INT);
        $stmt->bindParam(":reviewer", $reviewer, PDO::PARAM_INT);
        $stmt->bindParam(":next_step", $next_step, PDO::PARAM_INT);
        $stmt->bindParam(":comments", try_encrypt("--"), PDO::PARAM_STR);
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
    // If the encryption extra is enabled
    if (encryption_extra())
    {
        // Load encryption extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        if(!isset($_SESSION['encrypted_pass']) || !$_SESSION['encrypted_pass']){
            $decrypted_value = "XXXX";
        }
        else{
            // Decrypt the value
            $decrypted_value = decrypt($_SESSION['encrypted_pass'], $value);
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
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        // If the encrypted password is set
        if (isset($_SESSION['encrypted_pass']))
        {
            // Encrypt the value
            $encrypted_value = encrypt($_SESSION['encrypted_pass'], $value);

            // Return the encrypted value
            return $encrypted_value;
        }
        // Otherwise return the value
        else return $value;
    }
    // Otherwise return the value
    else return $value;
}

/*****************************
 * FUNCTION: GET CURRENT URL *
 *****************************/
function get_current_url()
{
    // Check if we are using the HTTPS protocol
        $isHTTPS = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on");

    // Set the port
        $port = (isset($_SERVER['SERVER_PORT']) && ((!$isHTTPS && $_SERVER['SERVER_PORT'] != "80") || ($isHTTPS && $_SERVER['SERVER_PORT'] != "443")));
        $port = ($port) ? ":" . $_SERVER['SERVER_PORT'] : "";

    // Set the current URL
        $url = ($isHTTPS ? "https://" : "http://") . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];

    // Return the URL
    return $url;
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
                    header("Location: reports");
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
                header("Location: reports");
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
            header("Location: reports");
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

/*******************************************
 * FUNCTION: CHECK IF PASSWORD CAN BE USED *
 *******************************************/
function check_password_can_be_used($user_id, $new_password, $user_salt){
    $db = db_open();
    //Get all user history
    $stmt = $db->prepare("SELECT salt, password, add_date FROM user_pass_history WHERE user_id=:user_id;");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pass_exists = false;
    $password_min_time = get_setting("pass_policy_min_age");
    foreach($data as $single_data){
        // For comparing
        $new_password_hash = generateHash($user_salt, $new_password);
        // Iterate over again with new password with use of old salt
        foreach($data as $single_data_2){
            echo "checking if " . $new_password_hash . " equals to " . $single_data_2["password"] . "<br />";
            if($new_password_hash == $single_data_2["password"]){
                $pass_exists = True;
                if((int)calculate_date_diff($single_data_2['add_date'], date("Y-m-d h:i:s"), "%d") > (int)$password_min_time){
                        // We can use the password
                        return True;
                }else{
                    echo "Password cannot be used.1";
                    return False;
                }
            }
        }

    }
    if($pass_exists == True){
        return False;
    }else{
        return True;
    }
    db_close();
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

/****************************************
 * FUNCTION: GET LANGUAGES WITH VARIABLES *
 ****************************************/
function _lang($key, $params=array()){
    global $lang, $escaper;

    foreach($params as &$param){
        $param = $escaper->escapeHtml($param);
    }

    extract($params, EXTR_OVERWRITE);

    $return = str_replace('"', '\"', $lang[$key]);

    eval("\$return = \"{$return}\";");

    return $return;
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
        $sql = "SELECT risk_id+1000 as risk_id,calculated_risk,last_update FROM `risk_scoring_history` WHERE risk_id=:risk_id ORDER BY last_update";
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
            // If enabled team seperation.

            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("t2", true, false);

            $sql = "
                SELECT t1.risk_id+1000 as risk_id, t1.calculated_risk, t1.last_update
                FROM `risk_scoring_history` t1
                    LEFT JOIN `risks` t2 on t1.risk_id=t2.id
                ". $separation_query ."
                ORDER BY
                    t1.risk_id, t1.last_update";
        }else{
            $sql = "SELECT risk_id+1000 as risk_id,calculated_risk,last_update FROM `risk_scoring_history` ORDER BY risk_id,last_update";
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

        // Get risk scoring histories by risk id
        $sql = "SELECT risk_id+1000 as risk_id,residual_risk,last_update FROM `residual_risk_scoring_history` WHERE risk_id=:risk_id ORDER BY last_update";
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
            // If enabled team seperation.

            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("t2", true, false);

            $sql = "
                SELECT t1.risk_id+1000 as risk_id, t1.residual_risk, t1.last_update
                FROM `residual_risk_scoring_history` t1
                    LEFT JOIN `risks` t2 on t1.risk_id=t2.id
                ". $separation_query ."
                ORDER BY
                    t1.risk_id, t1.last_update";
        }else{
            $sql = "SELECT risk_id+1000 as risk_id,residual_risk,last_update FROM `residual_risk_scoring_history` ORDER BY risk_id,last_update";
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
    if(exec($cmd)){
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

    $query = "SELECT {$select_time_query}, SUM(b.calculated_risk) calculated_risk, count(a.id) number_of_risks, IF(a.status='Closed', 0, 1) status
        FROM `risks` a INNER JOIN `risk_scoring_history` b ON a.id=b.risk_id ";

    // Open the database connection
    $db = db_open();

    // If the team separation extra is enabled
    if (team_separation_extra())
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the separation query string
        $separation_query = get_user_teams_query("a", false, true);

        $query .= $separation_query;
    }

    $query .= $groupby_query;
    $query .= " ORDER BY b.last_update ";

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
    $stmt->bindParam(":custom_display_settings", $custom_display_settings, PDO::PARAM_STR, 100);
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
function get_stakeholder_names($ids="")
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

    // Update user
    $stmt = $db->prepare("SELECT name FROM user WHERE value in (" . implode(",", $idArray) . "); ");
    $stmt->execute();

    // Store the list in the array
    $users = $stmt->fetchAll();
    // Close the database connection
    db_close($db);

    $names = array();

    foreach($users as $user){
        $names[] = $user['name'];
    }

    return implode(", ", $names);
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

    // Close the database connection
    db_close($db);

    // Make the https request
    $fp = fsockopen("tls://ping.simplerisk.com", 443, $errno, $errstr, 30);
    $out = "GET " . $path . " HTTP/1.1\r\n";
    $out .= "Host: ping.simplerisk.com\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($fp, $out);
    fclose($fp);
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
        // Open the database connection
        $db = db_open();

        // Create a random instance id
        $instance_id = generate_token(50);
        $stmt = $db->prepare("INSERT INTO `settings` VALUES ('instance_id', :instance_id)");
        $stmt->bindParam(":instance_id", $instance_id, PDO::PARAM_STR, 50);
        $stmt->execute();

        // Close the database connection
        db_close($db);

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

/***************************************
 * FUNCTION: UPDATE OR INSERT SETTINGS *
 ***************************************/
function update_or_insert_setting($name, $value)
{
    // Open the database connection
    $db = db_open();
    // Update the database version information

    $stmt = $db->prepare("REPLACE INTO `settings`(`name`, `value`) VALUES('" . $name . "', '" . $value . "');");
    $stmt->execute();

    // Close the database connection
    db_close($db);

    return true;
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
    $message = "A new family \"" . $name . "\" was updated by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // Close the database connection
    db_close($db);

    return;
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

    extract($data);

    $template = str_replace('"', '\"', $template);

    eval("\$string = \"". $template ."\";") ;

    return $string;
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
                linux_delete_file($file);
        }
        // If this is Windows
        else
        {
                windows_delete_file($file);
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
	$success = unlink($file);

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

/**********************************************
 * FUNCTION: SET SESSION RENEGOTIATION PERIOD *
 **********************************************/
function set_session_renegotiation_period()
{
        // Get the setting for the session renegotiation period
        $session_renegotiation_period = get_setting("session_renegotiation_period");

        // If the setting doesn't exist
        if (!$session_renegotiation_period)
        {
                // Set the session renegotiation period to the value in the config file
                $session_renegotiation_period = SESSION_RENEG_TIMEOUT;

                // If the session renegotiation period isn't null
                if ($session_renegotiation_period != null)
                {
                        // Add the value to the settings table
                        add_setting("session_renegotiation_period", $session_renegotiation_period);
                }
                // Otherwise
                else
                {
                        // Set the session renegotiation period to a default of 600 (10 minutes)
                        add_setting("session_renegotiation_period", "600");
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

/*******************************************
 * FUNCTION: SAVE ROLE AND RESPONSIBILITES *
 *******************************************/
function save_role_responsibilities($role_id, $responsibility_names)
{
    // Open the database connection
    $db = db_open();

    // Delete relations of role and responsibilities by role_id
    $stmt = $db->prepare("DELETE FROM `role_responsibilities` WHERE `role_id`=:role_id");
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->execute();

    foreach($responsibility_names as $responsibility_name){
        // Add a relation of role and responsibility
        $stmt = $db->prepare("INSERT INTO `role_responsibilities`(`role_id`, `responsibility_name`) VALUES(:role_id, :responsibility_name);");
        $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
        $stmt->bindParam(":responsibility_name", $responsibility_name, PDO::PARAM_STR, 100);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/********************************************
 * FUNCTION: GET RESPONSIBILITES BY ROLE ID *
 ********************************************/
function get_responsibilites_by_role_id($role_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT responsibility_name FROM `role_responsibilities` WHERE `role_id`=:role_id");
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
        $stmt->bindParam(":created_at", date("Y-m-d H:i:s"), PDO::PARAM_STR);
        $stmt->execute();
    }
    // If decline mitigation, delete a record
    else
    {
        $stmt = $db->prepare("DELETE FROM `mitigation_accept_users` WHERE `risk_id`=:risk_id AND `user_id`=:user_id;");
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
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

    $stmt = $db->prepare("SELECT t1.user_id, t1.risk_id, t1.created_at, t2.username FROM `mitigation_accept_users` t1 LEFT JOIN `user` t2 ON t1.user_id=t2.value WHERE t1.risk_id=:risk_id;");
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
        $username = isset($info['username']) ? $info['username'] : "Someone";
        $date = isset($info['created_at']) ? date(get_default_date_format(), strtotime($info['created_at'])) : "";
        $time = $info['created_at'] ? date("H:i", strtotime($info['created_at'])) : "";
        $message .= "<input disabled type=\"checkbox\" checked> &nbsp;&nbsp;&nbsp;"._lang("MitigationAcceptedByUserOnTime", ["username"=>$username, "date"=>$date, "time"=>$time])."<br>";

    }

    return $message;
}

/********************************************
* FUNCTION: SET ALL TEAMS TO ADMINISTRATORS *
*********************************************/
function set_all_teams_to_administrators()
{
    // Open the database connection
    $db = db_open();

    // Get all teams
    $stmt = $db->prepare("SELECT value FROM `team` ");
    $stmt->execute();
    $teamIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $teams = ":".implode("::", $teamIds).":";

    // Assign all teams permission to all administrator users.
    $stmt = $db->prepare("UPDATE `user` SET teams='{$teams}' WHERE role_id=1; ");
    $stmt->execute();

    // Close the database connection
    db_close($db);
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
function add_security_headers()
{
	// X-Frame-Options
	header("X-Frame-Options: DENY");

	// X-XSS-Protection
	header("X-XSS-Protection: 1; mode=block");

	// X-Content-Type-Options
	header("X-Content-Type-Options: nosniff");

	// Content-Type
	header("Content-Type: text/html; charset=utf-8");

	// If we want to enable the Content Security Policy (CSP) - This may break Chrome
	if (csp_enabled())
	{
        	// Add the Content-Security-Policy header
        	header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
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

/*********************************************************************************
 * FUNCTION: UPDATE ALL NEXT REVIEW DATES BASED ON NEXT_REVIEW_DATE_USES SETTING *
 *********************************************************************************/
function update_all_next_review_dates()
{
    global $lang, $escaper;

    $current_next_review_date_uses = get_setting("next_review_date_uses");

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.id review_id, t1.risk_id, t1.submission_date , t4.calculated_risk, ROUND((t4.calculated_risk - (t4.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
        FROM `mgmt_reviews` t1 
            INNER JOIN (SELECT risk_id, max(submission_date) submission_date FROM `mgmt_reviews` GROUP BY risk_id) t2 ON t1.risk_id=t2.risk_id AND t1.submission_date=t2.submission_date
            INNER JOIN `risks` t3 ON t1.risk_id=t3.id
            INNER JOIN risk_scoring t4 ON t3.id = t4.id
            LEFT JOIN mitigations mg ON t3.id = mg.risk_id
            LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls)
        GROUP BY t1.risk_id
        ;
    ");
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    $review_levels = get_review_levels();
    
    foreach($reviews as $review){
        // If next_review_date_uses setting is Residual Risk.
        if($current_next_review_date_uses == "ResidualRisk")
        {
            $residual_risk_level = get_risk_level_name($review['residual_risk']);
            $next_review = next_review($residual_risk_level, $review['risk_id'], "0000-00-00", false, $review_levels, $review["submission_date"]);
        }
        // If next_review_date_uses setting is Inherent Risk.
        else
        {
            $risk_level = get_risk_level_name($review['calculated_risk']);
            $next_review = next_review($risk_level, $review['risk_id'], "0000-00-00", false, $review_levels, $review["submission_date"]);
        }
        
        $stmt = $db->prepare("UPDATE `mgmt_reviews` SET next_review=:next_review WHERE id=:review_id;");
        $stmt->bindParam(":next_review", $next_review, PDO::PARAM_STR, 20);
        $stmt->bindParam(":review_id", $review['review_id'], PDO::PARAM_STR, 20);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
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

    // Get the result
    $result = $stmt->fetchAll();

    // Create an array of allowed types
    foreach ($result as $key => $row)
    {
        $allowed_types[] = $row['name'];
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
        
        
        // If the file type is appropriate
        if (in_array($file['type'], $allowed_types))
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
//                            $errors[] = "No file was uploaded.";
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
        else $errors[] = "The file type of the uploaded file (" . $file['type'] . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
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

/**********************************************
 * FUNCTION: GET MITIGATION TEAM QUERY STRING *
 **********************************************/
function get_mitigation_team_query_string($user_teams, $rename = false)
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
                $string .= "'{$team}' = {$rename}.mitigation_team OR " ;
            }
            // Otherwise append the team to the string
            else $string .= "'{$team}' = mitigation_team OR " ;
        }
    }
    
    $string .= " 0 ";
    
    // Return the string
    return $string;
}

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
			case 'trial':
				return trial_extra($extra_name);
				break;
			case 'small':
				return small_extra($extra_name);
				break;
			case 'medium':
				return medium_extra($extra_name);
				break;
			case 'large':
				return large_extra($extra_name);
				break;
			default:
				return true;
				break;
		}
	}
}

/*************************
 * FUNCTION: TRIAL EXTRA *
 *************************/
function trial_extra($extra_name)
{
	// Check the Extra permission
	switch($extra_name)
	{
		case 'api':
			// Allow
			return false;
                        break;
		case 'complianceforgescf':
			// Allow
			return false;
                        break;
		case 'customauth':
			// Allow
			return false;
                        break;
		case 'customization':
			// Allow
			return false;
                        break;
		case 'encryption':
			// Don't Allow
			return true;
                        break;
		case 'importexport':
			// Allow
			return false;
                        break;
		case 'notification':
			// Allow
			return false;
                        break;
		case 'riskassessment':
			// Allow
			return false;
                        break;
		case 'separation':
			// Allow
			return false;
			break;
	}
}

/*************************
 * FUNCTION: SMALL EXTRA *
 *************************/
function small_extra($extra_name)
{
        // Check the Extra permission
        switch($extra_name)
        {
                case 'api':
			// Don't Allow
                        return true;
                        break;
                case 'complianceforgescf':
			// Allow
                        return false;
                        break;
                case 'customauth':
			// Don't Allow
                        return true;
                        break;
                case 'customization':
			// Don't Allow
                        return true;
                        break;
                case 'encryption':
                        // Don't Allow
                        return true;
                        break;
                case 'importexport':
			// Allow
                        return false;
                        break;
                case 'notification':
			// Allow
                        return false;
                        break;
                case 'riskassessment':
			// Allow
                        return false;
                        break;
                case 'separation':
			// Don't Allow
                        return true;
                        break;
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
                case 'api':
                        // Don't Allow
                        return true;
                        break;
                case 'complianceforgescf':
                        // Allow
                        return false;
                        break;
                case 'customauth':
                        // Don't Allow
                        return true;
                        break;
                case 'customization':
                        // Don't Allow
                        return true;
                        break;
                case 'encryption':
                        // Don't Allow
                        return true;
                        break;
                case 'importexport':
                        // Allow
                        return false;
                        break;
                case 'notification':
                        // Allow
                        return false;
                        break;
                case 'riskassessment':
                        // Allow
                        return false;
                        break;
                case 'separation':
                        // Allow
                        return false;
                        break;
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
                case 'api':
                        // Allow
                        return false;
                        break;
                case 'complianceforgescf':
                        // Allow
                        return false;
                        break;
                case 'customauth':
                        // Allow
                        return false;
                        break;
                case 'customization':
                        // Allow
                        return false;
                        break;
                case 'encryption':
                        // Allow
                        return false;
                        break;
                case 'importexport':
                        // Allow
                        return false;
                        break;
                case 'notification':
                        // Allow
                        return false;
                        break;
                case 'riskassessment':
                        // Allow
                        return false;
                        break;
                case 'separation':
                        // Allow
                        return false;
                        break;
        }
}

?>
