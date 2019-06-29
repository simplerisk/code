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


/*
    A list of tables where the `name` field is encrypted AND used in
    functions that are getting name(s) by value(s). When querying the names of
    these tables the results should be ran through the 'try_decrypt()' function.
*/
$tables_where_name_is_encrypted = array('frameworks', 'projects');


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
 ************************************/
function get_options_from_table($name)
{
    global $lang, $escaper;
    
    // If we want a table that should be ordered by name instead of value
    if (in_array($name, array("user", "category", "team", "technology",
        "location", "regulation", "projects", "file_types", "file_type_extensions",
        "planning_strategy", "close_reason", "status", "source", "import_export_mappings", "test_status"))) {

        $options = get_table_ordered_by_name($name);
    }
    else if (in_array($name, array("enabled_users", "disabled_users", "languages", "family", "date_formats",
            "parent_frameworks", "frameworks", "framework_controls", "risk_tags", "asset_tags", "test_results", "test_results_filter", "policies"))) {
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
function create_multiusers_dropdown($name, $selected = ""){
    global $escaper;

    // Make selected to array
    $selected = explode(",", $selected);
    if(!is_array($selected)){
        $selected = array();
    }

    $options = get_options_from_table("enabled_users");
    $str = "<select class=\"multiselect\" id=\"{$name}\" name=\"{$name}[]\" multiple class=\"form-field form-control\" style=\"width:auto;\">\n";
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

    $options = get_options_from_table($name);

    // If the blank is true
    if ($blank == true)
    {
        array_unshift($options, ["value"=>$blankValue, "name"=>$blankText]);
    }

    foreach ($options as $key => $option)
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
        $options = get_options_from_table($name);
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
    if ($array)
    {
        // Set the value to the array value
        $value = trim($array[0]['value']);
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
 * FUNCTION: GET FORMATTED DATE                                                  *
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
    if ($date && $date != "0000-00-00")
    {
        // Set it to the proper format
        return strtotime($date) ? date(get_default_date_format(), strtotime($date)) : "";
    }
    else return $default;
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
    $sql = "INSERT INTO user (`type`, `username`, `name`, `email`, `salt`, `password`, `teams`, `role_id`, `governance`, `riskmanagement`, `compliance`, `assessments`, `asset`, `admin`, `review_veryhigh`, `accept_mitigation`, `review_high`, `review_medium`, `review_low`, `review_insignificant`, `submit_risks`, `modify_risks`, `plan_mitigations`, `close_risks`, `multi_factor`, `change_password`, `add_new_frameworks`, `modify_frameworks`, `delete_frameworks`, `add_new_controls`, `modify_controls`, `delete_controls`, `custom_display_settings`";
    foreach($other_options as $field => $value)
    {
        $sql .= ", `{$field}`";
    }
    $sql .= ") VALUES (:type, :user, :name, :email, :salt, :hash, :teams, :role_id, :governance, :riskmanagement, :compliance, :assessments, :asset, :admin, :review_veryhigh, :accept_mitigation, :review_high, :review_medium, :review_low, :review_insignificant, :submit_risks, :modify_risks, :plan_mitigations, :close_risks, :multi_factor, :change_password, :add_new_frameworks, :modify_frameworks, :delete_frameworks, :add_new_controls, :modify_controls, :delete_controls, ''";
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
    
    $user_id = $db->lastInsertId();

    // Audit log
    $risk_id = 1000;
    if(!empty($_SESSION['uid']))
    {
        $message = "The new user \"" . $user . "\" was added by the \"" . $_SESSION['user'] . "\" user.";
        write_log($risk_id, $_SESSION['uid'], $message);
    }
    else
    {
        $message = "The new user \"" . $user . "\" was added.";
        write_log($risk_id, $user_id, $message);
    }

    // Close the database connection
    db_close($db);

    return $user_id;
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
    
    $try_encrypt_assessment = try_encrypt($assessment);
    $try_encrypt_notes = try_encrypt($notes);

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":status", $status, PDO::PARAM_STR, 10);
    $encrypted_subject = try_encrypt($subject);
    $stmt->bindParam(":subject", $encrypted_subject, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":reference_id", $reference_id, PDO::PARAM_STR, 20);
    $stmt->bindParam(":regulation", $regulation, PDO::PARAM_INT);
    $stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR, 20);
    $stmt->bindParam(":location", $location, PDO::PARAM_INT);
    $stmt->bindParam(":source", $source, PDO::PARAM_INT);
    $stmt->bindParam(":category", $category, PDO::PARAM_INT);
    $stmt->bindParam(":team", $team, PDO::PARAM_STR);
    $stmt->bindParam(":technology", $technology, PDO::PARAM_STR);
    $stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
    $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
    $stmt->bindParam(":assessment", $try_encrypt_assessment, PDO::PARAM_STR);
    $stmt->bindParam(":notes", $try_encrypt_notes, PDO::PARAM_STR);
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
    $risk_id = (int)$last_insert_id + 1000;
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
        $max_likelihood = count(get_table("likelihood"));
        $max_impact = count(get_table("impact"));
        
        $ImpactSum = 0;
        foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
            $weight = get_contributing_weight_by_id($contributing_risk_id);
            $ImpactSum += $weight * $ContributingImpact;
        }

        // Set default Contributing Likelihood value
        $ContributingLikelihood = $ContributingLikelihood ? $ContributingLikelihood : $max_likelihood;
        // Set default Contributing Impact value
        $ImpactSum = $ImpactSum ? $ImpactSum : $max_impact;
        
        $calculated_risk = round(($ContributingLikelihood + $ImpactSum) / ($max_likelihood + $max_impact) * 10, 2);
        
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

    $max_likelihood = count(get_table("likelihood"));
    $max_impact = count(get_table("impact"));
    
    $ImpactSum = 0;
    foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
        $weight = get_contributing_weight_by_id($contributing_risk_id);
        $ImpactSum += $weight * $ContributingImpact;
    }
    
    // Set default Contributing Likelihood value
    $ContributingLikelihood = $ContributingLikelihood ? $ContributingLikelihood : $max_likelihood;
    // Set default Contributing Impact value
    $ImpactSum = $ImpactSum ? $ImpactSum : $max_impact;
    
    $calculated_risk = round(($ContributingLikelihood + $ImpactSum) / ($max_likelihood + $max_impact) * 10, 2);

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

        $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
        $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

        // Average the technical and business impacts to get the impact
        $OWASP_impact = ($technical_impact + $business_impact)/2;

        // Calculate the overall OWASP risk score
        $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

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
        if(is_array($post['mitigation_team']))
        {
            $mitigation_team = implode(",", $post['mitigation_team']);
        }
        else
        {
            $mitigation_team = $post['mitigation_team'];
        }
    }
    else
    {
        $mitigation_team = "";
    }
    $current_solution           = isset($post['current_solution']) ? $post['current_solution'] : "";
    $current_solution           = try_encrypt($current_solution);

    $security_requirements      = isset($post['security_requirements']) ? $post['security_requirements'] : "";
    $security_requirements      = try_encrypt($security_requirements);

    $security_recommendations   = isset($post['security_recommendations']) ? $post['security_recommendations'] : "";
    $security_recommendations   = try_encrypt($security_recommendations);

    $planning_date              = isset($post['planning_date']) ? $post['planning_date'] : "";
    $mitigation_date            = isset($post['mitigation_date']) ? $post['mitigation_date'] : date(get_default_datetime_format());

    // Convert to standard date
    $mitigation_date            = get_standard_date_from_default_format($mitigation_date, true);

    $mitigation_percent         = (isset($post['mitigation_percent']) && $post['mitigation_percent'] >= 0 && $post['mitigation_percent'] <= 100) ? $post['mitigation_percent'] : 0;
    $mitigation_controls        = empty($post['mitigation_controls']) ? [] : $post['mitigation_controls'];
    $mitigation_controls        = is_array($mitigation_controls) ? implode(",", $mitigation_controls) : $mitigation_controls;

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

    // Add the mitigation
    $stmt = $db->prepare("INSERT INTO mitigations (`risk_id`, `planning_strategy`, `mitigation_effort`, `mitigation_cost`, `mitigation_owner`, `mitigation_team`, `current_solution`, `security_requirements`, `security_recommendations`, `submitted_by`, `planning_date`, `submission_date`, `mitigation_percent`, `mitigation_controls`) VALUES (:risk_id, :planning_strategy, :mitigation_effort, :mitigation_cost, :mitigation_owner, :mitigation_team, :current_solution, :security_requirements, :security_recommendations, :submitted_by, :planning_date, :submission_date, :mitigation_percent, :mitigation_controls)");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":planning_strategy", $planning_strategy, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_effort", $mitigation_effort, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_cost", $mitigation_cost, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_owner", $mitigation_owner, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_team", $mitigation_team, PDO::PARAM_STR);
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

    if(!$submission_date){
        $submission_date = date("Y-m-d H:i:s");
    }

    // Subtract 1000 from risk_id
    $id = (int)$risk_id - 1000;

    // Get current datetime for last_update
    $current_datetime = date('Y-m-d H:i:s');

    // Open the database connection
    $db = db_open();

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

    $review_id = $db->lastInsertId();
    
    // Close the database connection
    db_close($db);

    return $review_id;
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

    $submission_date        = get_param("post", "submission_date", false);
    if($submission_date !== false){
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
    
    $tags = empty($_POST['tags']) ? array() : $_POST['tags'];
    if (!is_array($tags))
        $tags = explode(",", $tags);
    // Update tags
    updateTagsOfType($id, 'risk', $tags);

    if($is_api === false){
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
    $message = "Risk details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
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

        create_subject_order(isset($_SESSION['encrypted_pass']) && $_SESSION['encrypted_pass'] ? $_SESSION['encrypted_pass'] : fetch_key());
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
            LEFT JOIN framework_controls t4 ON FIND_IN_SET(t4.id, t3.mitigation_controls) AND t4.deleted=0
        WHERE t1.id=:risk_id
        GROUP BY t1.id;
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

        create_subject_order(isset($_SESSION['encrypted_pass']) && $_SESSION['encrypted_pass'] ? $_SESSION['encrypted_pass'] : fetch_key());
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

    // If the team separation extra is not enabled
    if (!team_separation_extra())
    {
        // Query the database
        $stmt = $db->prepare("
        SELECT
            a.*, group_concat(distinct CONCAT_WS('_', rsci.contributing_risk_id, rsci.impact)) as Contributing_Risks_Impacts, b.*, c.next_review,
            ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
            GROUP_CONCAT(DISTINCT t.tag ORDER BY t.tag ASC SEPARATOR ',') as risk_tags
        FROM risk_scoring a
            INNER JOIN risks b on a.id = b.id
            LEFT JOIN mgmt_reviews c on b.mgmt_review = c.id
            LEFT JOIN mitigations mg ON b.id = mg.risk_id
            LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
            LEFT JOIN risk_scoring_contributing_impacts rsci ON a.id=rsci.risk_scoring_id
            LEFT JOIN tags_taggees tt ON tt.taggee_id = b.id and tt.type = 'risk'
            LEFT JOIN tags t on t.id = tt.tag_id
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
        $stmt = $db->prepare("
            SELECT
                a.*, group_concat(distinct CONCAT_WS('_', rsci.contributing_risk_id, rsci.impact)) as Contributing_Risks_Impacts, b.*, c.next_review,
                ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
                GROUP_CONCAT(DISTINCT t.tag ORDER BY t.tag ASC SEPARATOR ',') as risk_tags
            FROM risk_scoring a INNER JOIN risks b on a.id = b.id LEFT JOIN mgmt_reviews c on b.mgmt_review = c.id
                LEFT JOIN mitigations mg ON b.id = mg.risk_id
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
                LEFT JOIN risk_scoring_contributing_impacts rsci ON a.id=rsci.risk_scoring_id
                LEFT JOIN tags_taggees tt ON tt.taggee_id = b.id and tt.type = 'risk'
                LEFT JOIN tags t on t.id = tt.tag_id
            WHERE b.id=:id " . $separation_query . "
            GROUP BY
            b.id
            LIMIT 1;
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
    $risk_id = (int)$risk_id - 1000;

    // Query the database
    $stmt = $db->prepare("SELECT t1.*, t1.risk_id AS id,
            t2.name as planning_strategy_name,
            t3.name as mitigation_effort_name,
            t4.min_value AS mitigation_min_cost, t4.max_value AS mitigation_max_cost,
            t5.name as mitigation_owner_name,
            group_concat(distinct t6.name) as mitigation_team_name,
            t7.name as submitted_by_name
        FROM mitigations t1
            left join planning_strategy t2 on t1.planning_strategy=t2.value
            left join mitigation_effort t3 on t1.mitigation_effort=t3.value
            left join asset_values t4 on t1.mitigation_cost=t4.id
            left join user t5 on t1.mitigation_owner=t5.value
            left join team t6 on FIND_IN_SET(t6.value, t1.mitigation_team)
            left join user t7 on t1.submitted_by=t7.value
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
            $stmt = $db->prepare("SELECT a.id FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value /* LEFT JOIN team g ON b.mitigation_team = g.value */ ORDER BY DATE(b.submission_date) DESC");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", true, false);

            // Query the database
            $stmt = $db->prepare("SELECT a.id FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value /* LEFT JOIN team g ON b.mitigation_team = g.value */ " . $separation_query . " ORDER BY DATE(b.submission_date) DESC");
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
function get_risks($sort_order=0, $order_field=false, $order_dir=false)
{
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
        }
        
    }

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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM
                    risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN (SELECT c1.risk_id, c1.next_review FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date) c ON a.id = c.risk_id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM risk_scoring a
                    LEFT JOIN risks b ON a.id = b.id
                    LEFT JOIN mgmt_reviews c ON b.mgmt_review = c.id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                SELECT a.calculated_risk, b.*, c.next_review, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk
                FROM risk_scoring a LEFT JOIN risks b ON a.id = b.id LEFT JOIN mgmt_reviews c ON b.mgmt_review = c.id
                    LEFT JOIN mitigations mg ON b.id = mg.risk_id
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
            $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS planning_strategy, e.name AS mitigation_effort, b.mitigation_cost, f.name AS mitigation_owner, group_concat(distinct g.name) AS mitigation_team FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value LEFT JOIN team g ON FIND_IN_SET(g.value, b.mitigation_team) GROUP BY a.id ORDER BY DATE(b.submission_date) DESC; ");
        }
        else
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // Get the separation query string
            $separation_query = get_user_teams_query("a", true, false);

            // Query the database
            $stmt = $db->prepare("SELECT a.subject, a.id, b.submission_date, c.name, d.name AS planning_strategy, e.name AS mitigation_effort, b.mitigation_cost, f.name AS mitigation_owner, group_concat(distinct g.name) AS mitigation_team FROM risks a JOIN mitigations b ON a.id = b.risk_id JOIN user c ON b.submitted_by = c.value LEFT JOIN planning_strategy d ON b.planning_strategy = d.value LEFT JOIN mitigation_effort e ON b.mitigation_effort = e.value LEFT JOIN user f ON b.mitigation_owner = f.value LEFT JOIN team g ON FIND_IN_SET(g.value, b.mitigation_team) " . $separation_query . " GROUP BY a.id ORDER BY DATE(b.submission_date) DESC; ");
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
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
            echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
            echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
        echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . (!$risk['closure_date'] ? "" : $escaper->escapeHtml(date("YmdHis", strtotime($risk['closure_date'])))) . "\">"
            . ( !$risk['closure_date'] ? $lang["Unknown"] : $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['closure_date']))) ) . "</td>\n";
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
                echo "<td align=\"center\" width=\"150px\" sorttable_customkey=\"" . $escaper->escapeHtml(date("YmdHis", strtotime($risk['submission_date']))) . "\">" . $escaper->escapeHtml(date(get_default_datetime_format("H:i"), strtotime($risk['submission_date']))) . "</td>\n";
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
function get_project_tabs($status)
{
    global $lang;
    global $escaper;

    $projects = get_projects();

    if ($status == 1)
    {
        array_unshift($projects, ['value' => 0, 'name' => $escaper->escapeHtml($lang['UnassignedRisks']), 'status' => 1]);
    } 
    
    $index = 0;
    
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

                // Get risks for this project
                $risks = get_risks_unassigned_project();
                $priority = "";
            }
            // If project ID was defined
            else
            {
                $delete = '<a href="javascript:voice(0);" class="project-block--delete pull-right" data-id="'.$escaper->escapeHtml($id).'"><i class="fa fa-trash"></i></a>';
                $no_sort = '';
                $name = $escaper->escapeHtml($name);

                // Get risks for this project
                $risks = get_risks_by_project_id($id);
                $index++;
                $priority = $index;
            }
            
            // Get count of risks for this project
            $count = count($risks);

            echo '<div class="project-block clearfix" '.$no_sort.'>';
                echo '<div class="project-block--header clearfix" data-project="'.$escaper->escapeHtml($id).'">
                <div class="project-block--priority pull-left">'.$escaper->escapeHtml($priority).'</div>
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

                    $risk_number = (int)$risk_id + 1000;

                    echo '<div class="risk clearfix">
                            <div class="pull-left risk--title"  data-risk="'.$escaper->escapeHtml($risk_id).'"><a href="../management/view.php?id=' . $escaper->escapeHtml(convert_id($risk_id)) . '" target="_blank">#'.$risk_number.' '.$escaper->escapeHtml($subject).'</a></div>
                            <div class="pull-right risk--score"> ' . $escaper->escapeHtml($lang['InherentRisk']) . ' : <span class="label label-danger" style="background-color: '. $escaper->escapeHtml($color) .'">'.$risk['calculated_risk'].'</span> </div>
                            </div>';
                }

                echo "</div>\n";

            echo "</div>\n";
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
    echo "<th align=\"center\" width=\"65px\">". $escaper->escapeHtml($lang['InherentRisk']) ."</th>\n";
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
        $value = "<a href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) ."&type=2&action=editreview\">". $escaper->escapeHtml($lang['No']) ."</a>";
    }else{
        if($next_review != $lang['PASTDUE'] ){
            // If review doensn't past due.
            $value = "<a class=\"management yes\" href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) ."&type=2&action=editreview\">".$escaper->escapeHtml($lang['Yes']).'</a>';
        }else{
            // If review past due.
            $value = "<a class=\"management pastdue\" href=\"../management/view.php?id=" . $escaper->escapeHtml($risk_id) ."&type=2&action=editreview\">".$escaper->escapeHtml($lang['PASTDUE']).'</a>';
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
    if (!$mitigation_id)
    {
        $value = "<a href=\"../management/view.php?type=1&id=" . $escaper->escapeHtml($risk_id) . "\">". $escaper->escapeHtml($lang['No']) ."</a>";
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
function get_name_by_value($table, $value, $default = "", $use_id = false)
{
    global $tables_where_name_is_encrypted;
    
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT name FROM $table WHERE " .($use_id ? "id" : "value") . "=:value LIMIT 1");
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
            return try_decrypt($array[0]['name']);

        // Return that value
        return $array[0]['name'];
    }
    // Otherwise, return an empty string
    else return $default;
}

/***************************************
 * FUNCTION: GET NAMEs BY MULTI VALUES *
 ***************************************/
function get_names_by_multi_values($table, $values, $return_array=false) {

    if (is_array($values))
        $values = implode(',', $values);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT name FROM $table WHERE FIND_IN_SET(value, :values);");
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
        return $return_array ? $array : implode(", ", $array);
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
    
    $try_encrypt_comments = try_encrypt($comment);

    // Open the database connection
    $db = db_open();

    // Add the closure
    $stmt = $db->prepare("INSERT INTO comments (`risk_id`, `user`, `comment`) VALUES (:risk_id, :user, :comment)");

    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":comment", $try_encrypt_comments, PDO::PARAM_STR);

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

                $query .= " WHERE risk_id=:risk_id AND (`timestamp` > CURDATE()-INTERVAL :days DAY) AND log_type IN (:log_type) ORDER BY timestamp DESC;";
                $stmt = $db->prepare($query);
                $log_type_str = implode(",", $log_type_array);
                $stmt->bindParam(":log_type", $log_type_str, PDO::PARAM_STR, 100);
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

                $query .= " WHERE (`timestamp` > CURDATE()-INTERVAL :days DAY) AND log_type IN ('".implode("','", $log_type_array)."') ORDER BY timestamp DESC; ";
                $stmt = $db->prepare($query);
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

    $planning_strategy  = (int)$post['planning_strategy'];
    $mitigation_effort  = (int)$post['mitigation_effort'];
    $mitigation_cost    = (int)$post['mitigation_cost'];
    $mitigation_owner   = (int)$post['mitigation_owner'];
    if(isset($post['mitigation_team']))
    {
        if(is_array($post['mitigation_team']))
        {
            $mitigation_team = implode(",", $post['mitigation_team']);
        }
        else
        {
            $mitigation_team = $post['mitigation_team'];
        }
    }
    else
    {
        $mitigation_team = "";
    }
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
    $stmt->bindParam(":mitigation_team", $mitigation_team, PDO::PARAM_STR);
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
    else if ($param == "advanced_search")
    {
        $regex_pattern = "/<advanced_search>(.*)<\/advanced_search>/";
    }

    $latest_version = "";

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
    $risk_id = (int)$risk_id - 1000;

    // If the user_id value is not set
    if (!isset($user_id))
    {
        $user_id = 0;
    }
    
    $user_id = (int)$user_id;

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
    // If the session hasn't been defined yet
    // Making it fall through if called from the command line to load the default
    if (!isset($_SESSION) && PHP_SAPI !== 'cli')
    {
        // Return an empty language file
        return realpath(__DIR__ . '/../languages/empty.php');
    }
    // If the language is set for the user
    elseif (isset($_SESSION['lang']))
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


/***********************************
 * FUNCTION: ADVANCED SEARCH EXTRA *
 ***********************************/
function advanced_search_extra() {
    return get_setting('advanced_search');
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
            if (in_array(pathinfo($file['name'], PATHINFO_EXTENSION), $allowed_extensions))
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
            if (extra_grant_access($_SESSION['uid'], (int)$array['risk_id'] + 1000))
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
            echo '<label for="file-upload" class="btn active-textfield">'.$escaper->escapeHtml($lang['ChooseFile']).'</label> <span class="file-count-html"><span class="file-count">0</span> '.$escaper->escapeHtml($lang['FileAdded']).'</span>';
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

    if (!$results || !is_array($results)) {
        set_alert(true, "bad", $lang['FailedToRegisterInstance']);

        // Return a failure
        return 0;
    }

    foreach ($results as $line)
    {
        if (preg_match("/<api_key>(.*)<\/api_key>/", $line, $matches))
        {
            $services_api_key = $matches[1];

            // Open the database connection
            $db = db_open();

            // Add the registration
            $stmt = $db->prepare("INSERT INTO `settings` (name, value) VALUES ('registration_name', :name), ('registration_company', :company), ('registration_title', :title), ('registration_phone', :phone), ('registration_email', :email), ('registration_fname', :fname), ('registration_lname', :lname), ('services_api_key', :services_api_key)");
            $stmt->bindParam(":name", $name, PDO::PARAM_STR, 200);
            $stmt->bindParam(":company", $company, PDO::PARAM_STR, 200);
            $stmt->bindParam(":title", $title, PDO::PARAM_STR, 200);
            $stmt->bindParam(":phone", $phone, PDO::PARAM_STR, 200);
            $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
            $stmt->bindParam(":fname", $fname, PDO::PARAM_STR, 200);
            $stmt->bindParam(":lname", $lname, PDO::PARAM_STR, 200);
            $stmt->bindParam(":services_api_key", $services_api_key, PDO::PARAM_STR, 50);
            $stmt->execute();

            // Mark the instance as registered
            $stmt = $db->prepare("INSERT INTO `settings` VALUES ('registration_registered', '1') ON DUPLICATE KEY UPDATE value='1';");
            $stmt->execute();

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
    // If the encryption extra is enabled
    if (encryption_extra())
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
            $encrypted_value = encrypt($_SESSION['encrypted_pass'], $value);
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
function _lang($__key, $__params=array(), $__escape=true){
    global $lang;

    if ($__escape) {
        global $escaper;

        foreach($__params as &$__param){
            $__param = $escaper->escapeHtml($__param);
        }
    }

    extract($__params, EXTR_OVERWRITE);

    $__return = str_replace('"', '\"', $lang[$__key]);

    eval("\$__return = \"{$__return}\";");

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
function get_stakeholder_names($ids="", $limit=4, $escape=true)
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

    // Close the database connection
    db_close($db);

    // If the instance is registered
    if (get_setting('registration_registered') != 0)
    {
        // Load the upgrade.php file
        require_once(realpath(__DIR__ . '/../extras/upgrade/index.php'));
        $path .= "&email_notification_installed=" . is_installed("notification");
	$path .= "&email_notification_enabled=" . notification_extra();
	$path .= "&email_notification_version=" . notification_extra_version();
        $path .= "&import_export_installed=" . is_installed("import-export");
        $path .= "&import_export_enabled=" . import_export_extra();
        $path .= "&import_export_version=" . importexport_extra_version();
        $path .= "&risk_assessment_installed=" . is_installed("assessments");
        $path .= "&risk_assessment_enabled=" . assessments_extra();
        $path .= "&risk_assessment_version=" . assessments_extra_version();
        $path .= "&team_separation_installed=" . is_installed("separation");
        $path .= "&team_separation_enabled=" . team_separation_extra();
        $path .= "&team_separation_version=" . separation_extra_version();
        $path .= "&custom_authentication_installed=" . is_installed("authentication");
        $path .= "&custom_authentication_enabled=" . custom_authentication_extra();
        $path .= "&custom_authentication_version=" . authentication_extra_version();
        $path .= "&customization_installed=" . is_installed("customization");
        $path .= "&customization_enabled=" . customization_extra();
        $path .= "&customization_version=" . customization_extra_version();
        $path .= "&api_installed=" . is_installed("api");
        $path .= "&api_enabled=" . api_extra();
        $path .= "&api_version=" . api_extra_version();
        $path .= "&encryption_installed=" . is_installed("encryption");
        $path .= "&encryption_enabled=" . encryption_extra();
        $path .= "&encryption_version=" . encryption_extra_version();
        $path .= "&complianceforgescf_installed=" . is_installed("complianceforgescf");
        $path .= "&complianceforgescf_enabled=" . complianceforge_scf_extra();
        $path .= "&complianceforgescf_version=" . complianceforge_scf_extra_version();
    }

    // Make the https request
    $fp = fsockopen("ssl://ping.simplerisk.com", 443, $errno, $errstr, 30);
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

/**************************************************************
 * FUNCTION: GET UPDATED ROLES BY ROLE ID AND NEW PERMISSIONS *
 **************************************************************/
function get_updated_roles($role_id, $new_responsibility_names)
{
    $old_responsibility_names = get_responsibilites_by_role_id($role_id);
    
    // Get added roles
    $added_permissions = [];
    foreach($new_responsibility_names as $new_responsibility_name){
        // if this permission is new, save this permission to added array
        if(!in_array($new_responsibility_name, $old_responsibility_names)){
            $added_permissions[] = $new_responsibility_name;
        }
    }
    
    // Get deleted roles
    $deleted_permissions = [];
    foreach($old_responsibility_names as $old_responsibility_name){
        // if this permission no exists in new permission names, save this permission to deleted array
        if(!in_array($old_responsibility_name, $new_responsibility_names)){
            $deleted_permissions[] = $old_responsibility_name;
        }
    }
    
    return array($added_permissions, $deleted_permissions);
}

/***************************
 * FUNCTION: DELETE A ROLE *
 ***************************/
function delete_role($role_id)
{
    $old_responsibility_names = get_responsibilites_by_role_id($role_id);
    
    // Open the database connection
    $db = db_open();

    // Get the name to be deleted
    $name = get_name_by_value('role', $role_id);

    // Delete the table value
    $stmt = $db->prepare("DELETE FROM `role` WHERE value=:value; ");
    $stmt->bindParam(":value", $role_id, PDO::PARAM_INT);
    $stmt->execute();

    $risk_id = 1000;
    $message = "The existing role \"" . $name . "\" was removed by the \"" . $_SESSION['user'] . "\" user.";
    write_log($risk_id, $_SESSION['uid'], $message);

    // If this role had permissions, remove permissions that this role had from users with this role 
    if($old_responsibility_names){
        $sql = "UPDATE `USER` SET ";
        foreach($old_responsibility_names as $index => $old_responsibility_name){
            if($index == count($old_responsibility_names)-1){
                $sql .= "`{$old_responsibility_name}`=0 ";
            }else{
                $sql .= "`{$old_responsibility_name}`=0, ";
            }
        }
        $sql .= " WHERE role_id=:role_id AND admin<>1; ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Close the database connection
    db_close($db);

    return true;
}

/*******************************************
 * FUNCTION: SAVE ROLE AND RESPONSIBILITES *
 *******************************************/
function save_role_responsibilities($role_id, $responsibility_names)
{
    // Get added and deleted permissions
    list($added_permissions, $deleted_permissions) = get_updated_roles($role_id, $responsibility_names);
    
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
    
    // Set added permissions to users with this role if there are added permissions
    if($added_permissions){
        $sql = "UPDATE `USER` SET ";
        foreach($added_permissions as $index => $added_permission){
            if($index == count($added_permissions)-1){
                $sql .= "`{$added_permission}`=1 ";
            }else{
                $sql .= "`{$added_permission}`=1, ";
            }
        }
        $sql .= " WHERE role_id=:role_id; ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
        $stmt->execute();
    } 

    // Remove deleted permissions from users with this role if there are deleted permissions
    if($deleted_permissions){
        $sql = "UPDATE `USER` SET ";
        foreach($deleted_permissions as $index => $deleted_permission){
            if($index == count($deleted_permissions)-1){
                $sql .= "`{$deleted_permission}`=0 ";
            }else{
                $sql .= "`{$deleted_permission}`=0, ";
            }
        }
        $sql .= " WHERE role_id=:role_id ";
        if(!in_array("admin", $deleted_permissions))
        {
            $sql .= " AND admin<>1; ";
        }
        else
        {
            $sql .= ";";
        }
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
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
            header("Content-Security-Policy: default-src 'self' 'unsafe-inline' *.highcharts.com *.googleapis.com *.gstatic.com *.jquery.com;");
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
function is_admin()
{
    // If the user is not logged in as an administrator
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
        return false;
    }
    else return true;
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
                if (in_array(pathinfo($file['name'], PATHINFO_EXTENSION), $allowed_extensions))
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

/*******************************
 * FUNCTION: GET USERS IN TEAM *
 *******************************/
function get_users_of_team($team)
{
    $team = ":{$team}:";

    // Open the database connection
    $db = db_open();

    // Get the user information
    $stmt = $db->prepare("SELECT * FROM user WHERE locate(:team, `teams`) > 0;");
    $stmt->bindParam(":team", $team, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/*******************************
 * FUNCTION: SET USERS OF TEAM *
 *******************************/
function set_users_of_team($user_ids, $team)
{
    $team_ = ":{$team}:";

    $db = db_open();

    // Remove users from team
    $stmt = $db->prepare("
        UPDATE
            `user`
        SET
            `teams`=replace(`teams`, :team, '')
        WHERE
            locate(:team, `teams`) > 0;");
    $stmt->bindParam(":team", $team_, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    add_users_to_team($user_ids, $team, false);

    // Audit log
    $user_names = get_names_by_values('user', $user_ids, 999, false);
    $team_name = get_name_by_value('team', $team);
    $message = _lang('SetUsersOfTeamAuditLog', array('team_name' => $team_name, 'user_names' => $user_names, 'username' => $_SESSION['user']));
    write_log(1000, $_SESSION['uid'], $message);
}


/**************************************************************
 * FUNCTION: UPDATE USER TEAMS                                *
 * Updates the teams of a user without updating anything else *
 **************************************************************/
function update_user_teams($user_id, $teams) {

    if (is_array($teams)) {
        $teams = ':' . implode('::', $teams) . ':';
    }

    $db = db_open();

    // Update the user
    $stmt = $db->prepare("UPDATE `user` SET `teams`=:teams where `value`=:user_id");
    $stmt->bindParam(":teams", $teams, PDO::PARAM_STR);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************************
 * FUNCTION: ADD USER TO TEAMS               *
 * Adding user to multiple teams.            *
 * First parameter is a user id,             *
 * second parameter is an array of team ids. *
 *********************************************/
function add_user_to_teams($user_id, $team_ids) {
    update_user_teams($user_id,
        array_map('intval', //to force them being int, thus safe to add back to the db
            array_values( //to make it a non-associative array
                array_unique( //to remove duplicates
                    array_filter( //to remove empty values
                        array_merge( //to merge the existing and new teams
                            explode(':', get_user_teams($user_id)),
                            $team_ids
                        )
                    )
                )
            )
        )
    );

    // Audit log
    $team_names = get_names_by_values('team', $team_ids, 999, false);
    $user_name = get_name_by_value('user', $user_id);
    $message = _lang('AddUserToTeamsAuditLog', array('team_names' => $team_names, 'user_name' => $user_name, 'username' => $_SESSION['user']));
    write_log(1000, $_SESSION['uid'], $message);
}

/*********************************************
 * FUNCTION: REMOVE USER FROM TEAMS          *
 * Removing user from multiple teams.        *
 * First parameter is a user id,             *
 * second parameter is an array of team ids. *
 *********************************************/
function remove_user_from_teams($user_id, $team_ids) {
    update_user_teams($user_id,
        array_map('intval', //to force them being int, thus safe to add back to the db
            array_values( //to make it a non-associative array
                array_unique( //to remove duplicates
                    array_filter( //to remove empty values
                        array_diff( //to remove the teams from the existing
                            explode(':', get_user_teams($user_id)),
                            $team_ids
                        )
                    )
                )
            )
        )
    );

    // Audit log
    $team_names = get_names_by_values('team', $team_ids, 999, false);
    $user_name = get_name_by_value('user', $user_id);
    $message = _lang('RemoveUserFromTeamsAuditLog', array('team_names' => $team_names, 'user_name' => $user_name, 'username' => $_SESSION['user']));
    write_log(1000, $_SESSION['uid'], $message);
}

/********************************************
 * FUNCTION: ADD USERS TO TEAM              *
 * Adding multiple users to a team.         *
 * First parameter is an array of user ids, *
 * second parameter is the id of the team.  *
 ********************************************/
function add_users_to_team($user_ids, $team, $audit_log=true) {

    // array_map used to make sure the array only contains int values
    // to make sure it's safe to just add it to the query string
    $user_ids_ = "'" . implode("','", array_map('intval', $user_ids)) . "'";
    $team_ = ":{$team}:";

    $db = db_open();

    // Update the user
    $stmt = $db->prepare("
        UPDATE
            `user`
        SET
            `teams`=concat(`teams`, :team)
        WHERE
            `value` IN ({$user_ids_}) and locate(:team, `teams`) = 0;");
    $stmt->bindParam(":team", $team_, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    if ($audit_log) {
        // Audit log
        $user_names = get_names_by_values('user', $user_ids, 999, false);
        $team_name = get_name_by_value('team', $team);
        $message = _lang('AddUsersToTeamAuditLog', array('team_name' => $team_name, 'user_names' => $user_names, 'username' => $_SESSION['user']));
        write_log(1000, $_SESSION['uid'], $message);
    }
}

/********************************************
 * FUNCTION: REMOVE USERS FROM TEAM         *
 * Removing multiple users from a team.     *
 * First parameter is an array of user ids, *
 * second parameter is the id of the team.  *
 ********************************************/
function remove_users_from_team($user_ids, $team) {

    // array_map used to make sure the array only contains int values
    // to make sure it's safe to just add it to the query string
    $user_ids_ = "'" . implode("','", array_map('intval', $user_ids)) . "'";
    $team_ = ":{$team}:";

    $db = db_open();

    // Update the user
    $stmt = $db->prepare("
        UPDATE
            `user`
        SET
            `teams`=replace(`teams`, :team, '')
        WHERE
            `value` IN ({$user_ids_}) and locate(:team, `teams`) > 0;");
    $stmt->bindParam(":team", $team_, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Audit log
    $user_names = get_names_by_values('user', $user_ids, 999, false);
    $team_name = get_name_by_value('team', $team);
    $message = _lang('RemoveUsersFromTeamAuditLog', array('team_name' => $team_name, 'user_names' => $user_names, 'username' => $_SESSION['user']));
    write_log(1000, $_SESSION['uid'], $message);
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
                $string .= " FIND_IN_SET('{$team}', {$rename}.mitigation_team) OR " ;
            }
            // Otherwise append the team to the string
            else $string .= " FIND_IN_SET('{$team}', mitigation_team) OR " ;
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
            case 'small':
                return small_extra($extra_name);
            case 'medium':
                return medium_extra($extra_name);
            case 'large':
                return large_extra($extra_name);
            default:
                return true;
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
            // Don't Allow
            return true;
        case 'importexport':
            // Allow
            return false;
        case 'notification':
            // Allow
            return false;
        case 'riskassessment':
            // Allow
            return false;
        case 'separation':
            // Allow
            return false;
        case 'advanced_search':
            // Allow
            return false;
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
        case 'notification':
            // Allow
            return false;
        case 'riskassessment':
            // Allow
            return false;
        case 'separation':
            // Don't Allow
            return true;
        case 'advanced_search':
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
        case 'notification':
            // Allow
            return false;
        case 'riskassessment':
            // Allow
            return false;
        case 'separation':
            // Allow
            return false;
        case 'advanced_search':
            // Don't Allow
            return true;
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
        case 'notification':
            // Allow
            return false;
        case 'riskassessment':
            // Allow
            return false;
        case 'separation':
            // Allow
            return false;
        case 'advanced_search':
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
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
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
    $stmt->bindParam(":subject", $subject, PDO::PARAM_STR, 100);
    $stmt->bindParam(":weight", $weight);
    $stmt->execute();

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
    $stmt->bindParam(":subject", $subject, PDO::PARAM_STR, 100);
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

/*******************************************************************************
 * FUNCTION: GET CONTRIBUTING IMPACTS BY CONTRIBUTING SUBJECT AND IMPACT NAMES *
 *******************************************************************************/
function get_contributing_impacts_by_subjectimpact_names($subject_impact_names)
{
    // if subject and impact names is emtpty, return []
    if(!$subject_impact_names)
    {
        return [];
    }
    
    // Set initial value to Contributing Impacts
    $ContributingImpacts = [];
    
    $subject_impact_names_arr = explode(",", $subject_impact_names);
    foreach($subject_impact_names_arr as $subject_impact_name){
        list($subject, $impact_name) = explode("_", $subject_impact_name);
        $contributing_risk_id = get_contributing_id_by_subject($subject);
        $impact = get_value_by_name("impact", $impact_name);
        $ContributingImpacts[$contributing_risk_id] = $impact;
    }
    
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
function table_exists($table) {

    // Open the database connection
    $db = db_open();

    // Query the schema for the table
    $database = DB_DATABASE;
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

/***********************************
 * FUNCTION: FIELD EXISTS IN TABLE *
 ***********************************/
function field_exists_in_table($field, $table) {

    // Open the database connection
    $db = db_open();

    // Query the field of the table
    $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE '{$field}';");
    $stmt->execute();

    // Fetch the results
    $results = $stmt->rowCount();

    // Close the database connection
    db_close($db);

    return $results;
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
                $maxPostSize{strlen($maxPostSize) - 1}
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
        ($extra == "api" && (api_extra() == $is_enable)) ||
        ($extra == "assessments" && (assessments_extra() == $is_enable)) ||
        ($extra == "complianceforge" && (complianceforge_extra() == $is_enable)) ||
        ($extra == "complianceforge_scf" && (complianceforge_scf_extra() == $is_enable)) ||
        ($extra == "advanced_search" && (advanced_search_extra() == $is_enable)) ||
        ($extra == "governance" && (governance_extra() == $is_enable));

    if ($interrupt) {
        set_alert(true, "bad", $lang['ExtraIsAlready' . ($is_enable ? 'Enabled': 'Disabled')]);
        refresh();
    }
}

/***********************************************
 * FUNCTION: PREVENT FORM DOUBLE SUBMIT SCRIPT *
 ***********************************************/
function prevent_form_double_submit_script() {
    echo "$(document).ready(function(){
            $('form').submit(function(evt) {
                setTimeout(function(){ $(\"input[type='submit']\").prop('disabled', true); }, 1);
                return true;
            });
        });\n";
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

    $stmt = $db->prepare("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = :table AND COLUMN_NAME = :column;");
    $stmt->bindParam(":table", $table, PDO::PARAM_STR);
    $stmt->bindParam(":column", $column, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetch();

    db_close($db);

    return $result?$result['DATA_TYPE']:"";
}

/********************************
 * FUNCTION: GET TAGS OF TAGGEE *
 ********************************/
function getTagsOfTaggee($taggee_id, $type) {

    if (!$taggee_id || ($type !== "risk" && $type !== "asset"))
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

    if (!$taggee_id || ($type !== "risk" && $type !== "asset"))
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

    if (!$taggee_id || ($type !== "risk" && $type !== "asset") || !is_array($tags))
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
    if ($tags_to_add || $tags_to_remove) {
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

    if ($type !== "risk" && $type !== "asset")
        return false;

    $db = db_open();

    //Load tags currently assigned to a type of taggee
    $stmt = $db->prepare("
        SELECT
            DISTINCT(`t`.`tag`)
        FROM
            `tags` t
            INNER JOIN `tags_taggees` tt ON `tt`.`tag_id` = `t`.`id`
        WHERE
            `tt`.`type` = :type
        ORDER BY `t`.`tag` ASC;
    ");
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    //Get the actual tag values(the $result array contains other info as well)
    $tags = array_column($result, 'tag');

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

    if (!$taggee_id || ($type !== "risk" && $type !== "asset") || !is_array($tags))
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

    if (!$taggee_id
        || ($type !== "risk" && $type !== "asset")
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
        csrf_conf('rewrite-js', $_SESSION['base_url'].'/includes/csrf-magic/csrf-magic.js');
    }

    require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));
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
//            print_r($row[$id_key]);exit;
            array_push($childs, $row);
            get_all_childs($rows, $row[$id_key], $childs, $id_key);
        }
    }
}

/******************************************************
 * FUNCTION: GET TEAMS OF ITEM                        *
 * Return the teams assigned to the item.             *
 * If $names is false it'll only return the values,   *
 * otherwise it returns both the values and the names *
 ******************************************************/
function getTeamsOfItem($item_id, $type, $names=false) {

    if (!$item_id || ($type !== "test" && $type !== "audit"))
        return;

    $db = db_open();

    //Load teams currently assigned to the item
    $stmt = $db->prepare("
        SELECT
            `t`." . ($names ? "*" : "`value`") . "
        FROM
            `team` t
            INNER JOIN `items_to_teams` itt ON `itt`.`team_id` = `t`.`value`
        WHERE
            `itt`.`item_id` = :item_id and `itt`.`type` = :type;
    ");
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

/*************************************************
 * FUNCTION: HAS TEAMS                           *
 * Checks if there're teams assigned to the item *
 *************************************************/
function hasTeams($item_id, $type) {

    if (!$item_id || ($type !== "test" && $type !== "audit"))
        return;

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

/****************************************************************
 * FUNCTION: UPDATE TEAMS OF TYPE                               *
 * Gets an id and a type to decide what the item is assigned to *
 * and updates its teams. Type can be either `test` or `audit`. *
 * Third parameter is an array. If the array is empty,          *
 * all the teams will be removed from the item.                 *
 ****************************************************************/
function updateTeamsOfType($item_id, $type, $teams) {

    if (!$item_id || ($type !== "test" && $type !== "audit") || !is_array($teams))
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
        foreach ($teams_to_remove as $i => $team)
        {
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
    if ($teams_to_add || $teams_to_remove) {
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
?>
