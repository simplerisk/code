<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/cvss.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/tf_idf_enrichment.php'));

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/****************************
 * FUNCTION: GET FRAMEWORKS *
 * $status
 *      1: active
 *      2: inactive
 ****************************/
function get_frameworks($status = false, $decrypt_name=true, $decrypt_description=true, $order = 'order')
{
    global $escaper;

    // Open the database connection
    $db = db_open();

    if($status === false){
        $stmt = $db->prepare("SELECT a.value id, a.* FROM frameworks a ORDER BY `order` ASC");
    }else{
        $stmt = $db->prepare("SELECT a.value id, a.* FROM frameworks a WHERE `status`=:status ORDER BY `order` ASC");
        $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    }
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

    // For each framework
    foreach ($array as $key => &$framework)
    {
        if($decrypt_name)
        {
            // Try to decrypt the framework name
            $framework['name'] = try_decrypt($framework['name']);
        }
        
        if($decrypt_description)
        {
            // Try to decrypt the framework description
            $framework['description'] = try_decrypt($framework['description']);
        }
    }

    // If the order is name
    if ($order == "name")
    {
        // Get the name keys
        $keys = array_column($array, 'name');

        // Sort the array by name
        array_multisort($keys, SORT_ASC, $array);
    }
    
    // Close the database connection
    db_close($db);

    return $array;
}

/*********************************
 * FUNCTION: MAKE TREE STRUCTURE *
 *********************************/
function makeTree($olds, $parent, &$news, &$count=0){
    foreach($olds as $old){
        if($old['parent'] == $parent){
            makeTree($olds, $old['value'], $old, $count);
            if(!isset($news['children']))
                $news['children'] = array();
            $count++;
            array_push($news['children'], $old);
        }
    }
}

/***********************************************
 * FUNCTION: GET FRAMEWORK DATA IN TREE FORMAT *
 ***********************************************/
function get_frameworks_as_treegrid($status) {
    global $escaper;

    $complianceforge_scf_framework_id = complianceforge_scf_extra() ? (int)get_setting('complianceforge_scf_framework_id', 0) : 0;

    $frameworks = get_frameworks($status);

    foreach($frameworks as &$framework){
        $framework_value = (int)$framework['value'];
        $framework['name'] = $escaper->escapeHtml($framework['name']);
        $framework['actions'] = "
            <div class='d-flex justify-content-center align-items-center w-100'>
                <a class='framework-block--edit' data-id='{$framework_value}'>
                    <i class='fa fa-edit'></i>
                </a>" .
        (  // The root complianceforge framework can't be deleted
            $complianceforge_scf_framework_id && $complianceforge_scf_framework_id === $framework_value ? "" : "
                <a class='framework-block--delete' data-id='{$framework_value}'>
                    <i class='fa fa-trash'></i>
                </a>"
        ) . "
            </div>";
    }
    // unset the loop's variable if it was used for addressing the elements by reference
    unset($framework);

    if($status == 1) {
        $results = [];
        $count = 0;
        makeTree($frameworks, 0, $results, $count);
        return ['totalCount' => $count, 'rows' => isset($results['children']) ? $results['children'] : []];
    } else {
        return ['totalCount' => count($frameworks), 'rows' => [...$frameworks]];
    }
}

/*********************************
 * FUNCTION: GET FRAMEWORK BY ID *
 *********************************/
function get_framework($framework_id){
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT * FROM `frameworks` WHERE `value` = :framework_id");
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);

    $stmt->execute();
    
    $framework = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    if($framework){
        // Try to decrypt the framework name
        $framework['name'] = try_decrypt($framework['name']);
        
        // Try to decrypt the framework description
        $framework['description'] = try_decrypt($framework['description']);
        // If customization extra is enabled
        if(customization_extra())
        {
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            $custom_values = get_custom_value_by_row_id($framework['value'], "framework");
            $framework['custom_values'] = $custom_values;
        }

        return $framework;
    }
    else{
        return false;
    }
}

/***************************************************
 * FUNCTION: GET PARENT FRAMEWORKS BY FRAMEWORK ID *
 ***************************************************/
function get_parent_frameworks($frameworks, $framework_id, &$news){
    if($framework_id == 0){
        return;
    }
    foreach($news as $newRow)
    {
        if($framework_id == $newRow['value']){
            return;
        }
    }
    foreach($frameworks as $framework){
        if($framework['value'] == $framework_id){
            array_unshift($news, $framework);
            get_parent_frameworks($frameworks, $framework['parent'], $news);
            break;
        }
    }
}

/*************************************
 * FUNCTION: UPDATE FRAMEWORK STATUS *
 *************************************/
function update_framework_status($status, $framework_id)
{
    $frameworks = get_frameworks();
    
    // Open the database connection
    $db = db_open();
    
    $result_ids = [];
    
    $framework = get_framework($framework_id);

    // If framework is inactive
    if($status == 2){
        $results = array();
        makeTree($frameworks, $framework_id, $results);
        array_walk_recursive($results,  function($value, $key) use($status, $db, &$result_ids){
            if($key == "value"){
                
                // Query the database
                $stmt = $db->prepare("UPDATE `frameworks` SET `status` = :status WHERE `value` = :framework_id");
                $stmt->bindParam(":framework_id", $value, PDO::PARAM_INT);
                $stmt->bindParam(":status", $status, PDO::PARAM_INT);
                
                // Update status
                $stmt->execute();
                
                $result_ids[] = $value;
            }
        });
        // Query the database
        $stmt = $db->prepare("UPDATE `frameworks` SET `status` = :status WHERE `value` = :framework_id");
        $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
        $stmt->bindParam(":status", $status, PDO::PARAM_INT);
        $stmt->execute();
        
        $result_ids[] = $framework_id;
    }
    // If framework is active
    elseif($status == 1){
        $results = array();
        
        get_parent_frameworks($frameworks, $framework['parent'], $results);
        
        if($results){
            array_push($results, $framework);
            array_walk_recursive($results,  function($value, $key) use($status, $db, &$result_ids){
                if($key == "value"){

                    // Query the database
                    $stmt = $db->prepare("UPDATE `frameworks` SET `status` = :status WHERE `value` = :framework_id");
                    $stmt->bindParam(":framework_id", $value, PDO::PARAM_INT);
                    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
                    
                    // Update status
                    $stmt->execute();
                    
                    $result_ids[] = $value;
                }
            });
            if($results[0]['parent'] != 0){
                // Query the database
                $stmt = $db->prepare("UPDATE `frameworks` SET `parent`=0 WHERE `value` = :framework_id");
                $stmt->bindParam(":framework_id", $results[0]['value'], PDO::PARAM_INT);
                $stmt->execute();
            }
        }else{
            // Query the database
            $stmt = $db->prepare("UPDATE `frameworks` SET `parent`=0, `status` = :status WHERE `value` = :framework_id");
            $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
            $stmt->bindParam(":status", $status, PDO::PARAM_INT);
            $stmt->execute();

            $result_ids[] = $framework_id;
        }

    }

    // Close the database connection
    db_close($db);

    if($status == 1){
        $message = "A framework named \"{$framework['name']}\" was activated by the \"" . $_SESSION['user'] . "\" user.";
    }
    elseif($status == 2){
        $message = "A framework named \"{$framework['name']}\" was deactivated by the \"" . $_SESSION['user'] . "\" user.";
    }
    write_log($framework_id+1000, $_SESSION['uid'], $message, 'framework');

    return $result_ids;
}

/*************************************
 * FUNCTION: UPDATE FRAMEWORK PARENT *
 *************************************/
function update_framework_parent($parent, $framework_id)
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `frameworks` SET `parent` = :parent WHERE `value` = :framework_id");
    $stmt->bindParam(":parent", $parent, PDO::PARAM_INT);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);

    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************
 * FUNCTION: GET FRAMEWORKS COUNT *
 **********************************/
function get_frameworks_count($status)
{
    $db = db_open();
    $stmt = $db->prepare("SELECT count(*) FROM `frameworks` WHERE `status` = :status;");
    $stmt->bindParam(":status", $status);
    $stmt->execute();

    // Store the list in the array
    $count = (int)$stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return $count;
}

/**********************************
 * FUNCTION: GET FRAMEWORKS COUNT *
 **********************************/
function get_framework_controls_count($deleted = false) {
    $db = db_open();
    $stmt = $db->prepare("SELECT count(1) FROM `framework_controls` WHERE `deleted` = :deleted;");
    $stmt->bindParam(":deleted", $deleted);
    $stmt->execute();
    
    $count = (int)$stmt->fetchColumn();
    
    db_close($db);
    return $count;
}

/********************************
 * FUNCTION: GET FRAMEWORK TABS *
 ********************************/
function get_framework_tabs($status) {
    global $lang, $escaper;
    
    echo "
        <table class='framework-table-{$status}'>
            <thead>
                <th data-options=\"field:'name'\" width='20%'>{$escaper->escapeHtml($lang['FrameworkName'])}</th>
                <th data-options=\"field:'description'\" width='70%'>{$escaper->escapeHtml($lang['FrameworkDescription'])}</th>
                <th data-options=\"field:'actions'\" width='10%'>{$escaper->escapeHtml($lang['Actions'])}</th>
            </thead>
        </table>
    ";
} 

/**************************************************
 * FUNCTION: GET FRAMEWORK CONTROLS DROPDOWN DATA *
 **************************************************/
function get_framework_controls_dropdown_data()
{
    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT
            `fc`.`id`, `fc`.`short_name`, `fc`.`long_name`
        FROM
            `framework_controls` fc
        WHERE
            `fc`.`deleted` = 0
    ORDER BY
        `fc`.`short_name`
        ;
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    // Get the list in the array
    $controls = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $controls;
}
 
/************************************
 * FUNCTION: GET FRAMEWORK CONTROLS *
 ************************************/
function get_framework_controls($control_ids=false)
{

    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT t1.*, t2.name control_class_name, t3.name control_priority_name, t4.name family_short_name, t5.name control_phase_name, t6.name control_owner_name, IFNULL(GROUP_CONCAT(DISTINCT t7.name), '') framework_names, IFNULL(GROUP_CONCAT(DISTINCT t7.value), '') framework_ids, group_concat(distinct ctype.value) control_type_ids
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `control_priority` t3 on t1.control_priority=t3.value
            LEFT JOIN `family` t4 on t1.family=t4.value
            LEFT JOIN `control_phase` t5 on t1.control_phase=t5.value
            LEFT JOIN `user` t6 on t1.control_owner=t6.value
            LEFT JOIN `framework_control_mappings` m ON t1.id=m.control_id
            LEFT JOIN `frameworks` t7 ON m.framework=t7.value AND t7.status=1
            LEFT JOIN `framework_control_type_mappings` t8 on t1.id=t8.control_id
            LEFT JOIN `control_type` ctype on ctype.value=t8.control_type_id
        WHERE
            t1.deleted=0
    ";

    if($control_ids !== false) {
        // Sanitizing input
        $control_ids_arr = [];
        foreach(explode(',',$control_ids) as $control_id)
            if (ctype_digit($control_id))
                $control_ids_arr[] = $control_id;

        $sql .= " AND FIND_IN_SET(t1.id, '" . implode(',',$control_ids_arr) . "') ";
    }

    $sql .= " GROUP BY t1.id; ";
    $stmt = $db->prepare($sql);
    $stmt->execute();

    // Get the list in the array
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // To speed up, use control names if control_ids param is not empty
    if($control_ids !== false)
    {
        foreach ($controls as $key => &$control)
        {
            $framework_names_arr = explode(",", $control['framework_names']);
            $control['framework_names'] = array();
            foreach($framework_names_arr as $framework_name){
                if($framework_name){
                    $control['framework_names'][] = try_decrypt($framework_name);
                }
            }
            $control['framework_names'] = implode(", ", $control['framework_names']);
        }
    }
    else
    {
        $frameworks = get_frameworks(1);
        foreach ($controls as $key => &$control)
        {
            // Get framework names from framework Ids string
            $framework_ids_arr = explode(",", $control['framework_ids']);
            $control['framework_names'] = array();
            foreach($framework_ids_arr as $framework_id){
                foreach($frameworks as $framework){
                    if($framework_id == $framework['value'])
                    {
                        $control['framework_names'][] = $framework['name'];
                        break;
                    }
                }
            }
            $control['framework_names'] = implode(", ", $control['framework_names']);


        }
    }

    // Close the database connection
    db_close($db);

    return $controls;
}

/**********************************************
 * FUNCTION: GET FRAMEWORK CONTROLS BY FILTER *
 **********************************************/
function get_framework_controls_by_filter($control_class="all", $control_phase="all", $control_owner="all", $control_family="all", $control_framework="all", $control_priority="all", $control_type="all", $control_status="all", $control_text="", $control_ids = "all")
{
    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT t1.*, GROUP_CONCAT(DISTINCT f.value) framework_ids, GROUP_CONCAT(DISTINCT f.name) framework_names, t2.name control_class_name, t3.name control_phase_name, t4.name control_priority_name, t5.name family_short_name, t6.name control_owner_name, t7.name control_maturity_name, t8.name desired_maturity_name, group_concat(distinct ctype.value) control_type_ids, GROUP_CONCAT(DISTINCT m.reference_name) reference_name
        FROM `framework_controls` t1 
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            LEFT JOIN `frameworks` f on m.framework=f.value AND f.status=1
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `control_phase` t3 on t1.control_phase=t3.value
            LEFT JOIN `control_priority` t4 on t1.control_priority=t4.value
            LEFT JOIN `family` t5 on t1.family=t5.value
            LEFT JOIN `user` t6 on t1.control_owner=t6.value
            LEFT JOIN `control_maturity` t7 on t1.control_maturity=t7.value
            LEFT JOIN `control_maturity` t8 on t1.desired_maturity=t8.value
            LEFT JOIN `framework_control_type_mappings` t9 on t1.id=t9.control_id
            LEFT JOIN `control_type` ctype on ctype.value=t9.control_type_id
        WHERE t1.deleted=0
    ";
    
    // If control class ID is requested.
    if($control_class && is_array($control_class)){
        $where = [0];
        $where_ids = [];
        foreach($control_class as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "(t2.value is NULL OR t2.value='')";
                }
                else
                {
                    $where_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(t2.value, '".implode(",", $where_ids)."')";
        
        $sql .= " AND (". implode(" OR ", $where) . ")";
    }
    elseif($control_class == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }
    
    // If control phase ID is requested.
    if($control_phase && is_array($control_phase)){
        $where = [0];
        $where_ids = [];
        foreach($control_phase as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "(t3.value is NULL OR t3.value='')";
                }
                else
                {
                    $where_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(t3.value, '".implode(",", $where_ids)."')";
        $sql .= " AND (". implode(" OR ", $where) . ")";
    }
    elseif($control_phase == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }
    
    // If control priority ID is requested.
    if($control_priority && is_array($control_priority)){
        $where = [0];
        $where_ids = [];
        foreach($control_priority as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "(t4.value is NULL OR t4.value='')";
                }
                else
                {
                    $where_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(t4.value, '".implode(",", $where_ids)."')";
        $sql .= " AND (". implode(" OR ", $where) . ")";
    }
    elseif($control_priority == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }

    // If control family ID is requested.
    if($control_family && is_array($control_family)){
        $where = [0];
        $where_ids = [];
        foreach($control_family as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "(t5.value is NULL OR t5.value='')";
                }
                else
                {
                    $where_ids[] = $val;
                }
            }
        }

        if (!empty($where_ids)) {
            $where[] = "t5.value IN (".implode(",", $where_ids).")";
        }

        $sql .= " AND (". implode(" OR ", $where) . ")";
    }
    elseif($control_family == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }
    
    // If control owner ID is requested.
    if($control_owner && is_array($control_owner)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_owner as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "(t6.value is NULL OR t6.value='')";
                }
                else
                {
                    $where_or_ids[] = $val;
                }
            }
        }

        if (!empty($where_or_ids)) {
            $where[] = "FIND_IN_SET(t6.value, '".implode(",", $where_or_ids)."')";
        }

        $sql .= " AND (". implode(" OR ", $where) . ")";
    }
    elseif($control_owner == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }
    
    // If control framework ID is requested.
    if($control_framework && is_array($control_framework)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_framework as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "m.control_id is NULL";
                }
                else
                {
                    $where_or_ids[] = $val;
                }
            }
        }
        if (!empty($where_or_ids)) {
            $where[] = "m.framework IN (".implode(",", $where_or_ids).")";
        }
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    }
    elseif($control_framework == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }

    // If control type ID is requested.
    if($control_type && is_array($control_type)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_type as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "ctype.value is NULL";
                }
                else
                {
                    $where_or_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(ctype.value, '".implode(",", $where_or_ids)."')";
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    }
    elseif($control_type == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }

    // If control status is requested.
    if($control_status && is_array($control_status)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_status as $val){
            $val = (int)$val;
            $where_or_ids[] = $val;
        }
        $where[] = "FIND_IN_SET(t1.control_status, '".implode(",", $where_or_ids)."')";

        $sql .= " AND (". implode(" OR ", $where) . ")";

    }
    elseif($control_status == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }

    // If control ID is requested.
    if($control_ids && is_array($control_ids)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_ids as $val){
            $val = (int)$val;
            if($val)
            {
                $where_or_ids[] = $val;
            }
        }
        $where[] = "FIND_IN_SET(t1.id, '".implode(",", $where_or_ids)."')";
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    }
    elseif($control_ids == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }

    $sql .= " GROUP BY t1.id ORDER BY t1.id; ";

    $stmt = $db->prepare($sql);

    $stmt->execute();
    // Controls by filter except framework
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Final results
    $filtered_controls = array();
    
    $frameworks = get_frameworks(1);

    foreach ($controls as $key => $control)
    {
        // Get framework names from framework Ids string
        $framework_ids = explode(",", (string)$control['framework_ids']);
        
        $decrypted_framework_names = [];
        foreach($framework_ids as $framework_id)
        {
            if(!empty($frameworks[$framework_id]['name'])){
                $decrypted_framework_names[] = $frameworks[$framework_id]['name'];
            }
        }
        
        $control['framework_names'] = implode(", ", $decrypted_framework_names);

        // Filter by search text
        if(
            !$control_text 
            || (stripos((string)$control['short_name'], $control_text) !== false) 
            || (stripos((string)$control['long_name'], $control_text) !== false) 
            || (stripos((string)$control['description'], $control_text) !== false) 
            || (stripos((string)$control['supplemental_guidance'], $control_text) !== false) 
            || (stripos((string)$control['control_number'], $control_text) !== false)
            || (stripos((string)$control['control_class_name'], $control_text) !== false) 
            || (stripos((string)$control['control_phase_name'], $control_text) !== false) 
            || (stripos((string)$control['control_priority_name'], $control_text) !== false) 
            || (stripos((string)$control['family_short_name'], $control_text) !== false) 
            || (stripos((string)$control['control_owner_name'], $control_text) !== false) 
            || (stripos((string)$control['framework_names'], $control_text) !== false)
            || (stripos((string)$control['reference_name'], $control_text) !== false)
        )
        {
            $filtered_controls[] = $control;
        }

    }

    // Close the database connection
    db_close($db);

    return $filtered_controls;
}

/************************************
 * FUNCTION: ADD NEW FRAMEWORK      *
 ************************************/
function add_framework($name, $description, $parent=0, $status=1){
    // Open the database connection
    $db = db_open();
    
    // Get latest order
    $stmt = $db->prepare("SELECT max(`order`) as `maxOrder` FROM `frameworks` where status=:status");
    $stmt->bindParam(":status", $status);
    $stmt->execute();
    $row = $stmt->fetch();
    if(isset($row[0])){
        $order = $row[0] + 1;
    }else{
        $order = 0;
    }
    
    // Sanitizing input that comes from the WYSIWYG editor or outside sources
    $description = purify_html($description);

    $try_encrypt_name = try_encrypt($name);
    $try_encrypt_descryption = try_encrypt($description);

    // Check if the framework exists
    $stmt = $db->prepare("SELECT * FROM `frameworks` where name=:name");
    $stmt->bindParam(":name", $try_encrypt_name);
    $stmt->execute();
    $row = $stmt->fetch();
    if(isset($row[0])){
        return false;
    }

    // Create a framework
    $stmt = $db->prepare("INSERT INTO `frameworks` (`name`, `description`, `parent`, `status`, `order`) VALUES (:name, :description, :parent, :status, :order)");
    $stmt->bindParam(":name", $try_encrypt_name, PDO::PARAM_STR, 100);
    $stmt->bindParam(":description", $try_encrypt_descryption, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":parent", $parent, PDO::PARAM_INT);
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->bindParam(":order", $order, PDO::PARAM_INT);
    $stmt->execute();
    
    $framework_id = $db->lastInsertId();

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // If there is error in saving custom asset values, return false
        if(save_custom_field_values($framework_id, "framework") != true)
        {
            delete_frameworks($framework_id);
            return false;
        }
    }

    $message = "A new framework named \"{$name}\" was created by username \"" . $_SESSION['user'] . "\".";
    write_log((int)$framework_id + 1000, $_SESSION['uid'], $message, "framework");
    
    // Close the database connection
    db_close($db);

    return $framework_id;
}


/********************************************************************************
 * FUNCTION: DETECT CIRCULAR PARENT REFERENCE                                   *
 * Detecting whether with the new parent there would be a circular reference.   *
 * Circular reference in this case means that a going up in the                 *
 * list of parents we'd eventually find the framework we started from.          *
 * Returns true if there'd be a circular reference, false otherwise.            *
 ********************************************************************************/
function detect_circular_parent_reference($framework_id, $parent) {

    $db = db_open();

    $ancestor = $parent;
    $result = false;

    // Go through the list of ancestors
    do {
        $stmt = $db->prepare("SELECT `parent` FROM `frameworks` WHERE `value` = :ancestor");
        $stmt->bindParam(":ancestor", $ancestor, PDO::PARAM_INT);
        $stmt->execute();
        $ancestor = (int)$stmt->fetchColumn();

        // Exit when we either found ourself among the ancestors
        if ($ancestor === (int)$framework_id) {
            $result = true;
            break;
        }
    } while ($ancestor); // or reached the root

    db_close($db);

    return $result;
}

/******************************
 * FUNCTION: UPDATE FRAMEWORK *
 ******************************/
function update_framework($framework_id, $name, $description=false, $parent=false){

    global $lang;

    if (isset($name)) {
        $name = trim($name);

        if (!$name) {
            set_alert(true, "bad", $lang['FrameworkNameCantBeEmpty']);
            return false;
        }
    }

    $encrypted_name = try_encrypt($name);

    // Open the database connection
    $db = db_open();

    // Check if the name is already taken by another framework
    $stmt = $db->prepare("SELECT 1 FROM `frameworks` WHERE `name` = :name AND `value` <> :framework_id;");
    $stmt->bindParam(":name", $encrypted_name);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if($result) {
        set_alert(true, "bad", $lang['FrameworkNameExist']);
        return false;
    }

    // Check if the user is going to setup a circular reference
    if ($parent && detect_circular_parent_reference($framework_id, $parent)) {
        set_alert(true, "bad", $lang['FrameworkCantBeItsOwnParent']); //No you don't! Circular reference detected...
        return false;
    }

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        if (!save_custom_field_values($framework_id, "framework")) {
            return false;
        }
    }

    $framework = get_framework($framework_id);

    $framework['name'] = $encrypted_name;
    // Sanitizing input that comes from the WYSIWYG editor or outside sources
    $framework['description'] = $description === false ? try_encrypt($framework['description']) : try_encrypt(purify_html($description));
    $framework['parent'] = $parent === false ? $framework['parent'] : $parent;

    // Create a framework
    $stmt = $db->prepare("UPDATE `frameworks` SET `name`=:name, `description`=:description, `parent`=:parent WHERE value=:framework_id;");
    $stmt->bindParam(":name", $framework['name'], PDO::PARAM_STR, 100);
    $stmt->bindParam(":description", $framework['description'], PDO::PARAM_STR, 1000);
    $stmt->bindParam(":parent", $framework['parent'], PDO::PARAM_INT);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();

    $message = "A framework named \"{$name}\" was updated by username \"" . $_SESSION['user'] . "\".";
    write_log((int)$framework_id + 1000, $_SESSION['uid'], $message, "framework");
    
    // Close the database connection
    db_close($db);

    return true;
}

/***********************************************
 * FUNCTION: GET CHILD FRAMEWORKS BY PARENT ID *
 ***********************************************/
function get_child_frameworks($parent_id, $status="all")
{
    // Open the database connection
    $db = db_open();

    $sql = "SELECT t1.* FROM `frameworks` t1 WHERE t1.parent=:parent_id ";
    
    if($status != "all"){
        $sql .= " AND status=:status; ";
    }else{
        $sql .= ";";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":parent_id", $parent_id, PDO::PARAM_INT);
    if($status != "all"){
        $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    }
    $stmt->execute();

    $results = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);
    
    return $results;
}

/***************************************************
 * FUNCTION: GET ALL CHILD FRAMEWORKS BY PARENT ID *
 ***************************************************/
function get_all_child_frameworks($parent_id, $status=false, $decrypt=true)
{
    $frameworks = get_frameworks($status, $decrypt);
    $child_frameworks = [];
    get_all_childs($frameworks, $parent_id, $child_frameworks, "value");
    
    return $child_frameworks;
}

/********************************************
 * FUNCTION: DELETE FRAMEWORKS BY PARENT ID *
 ********************************************/
function delete_frameworks($framework_id){
    $framework = get_framework($framework_id);
    // Check framework ID is valid
    if($framework)
    {
        $parent = $framework['parent'];
        $name = $framework['name'];
        // Open the database connection
        $db = db_open();

        // Delete framework by ID
        $stmt = $db->prepare("DELETE FROM `frameworks` WHERE value=:value");
        $stmt->bindParam(":value", $framework_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update parents for child frameworks
        $frameworks = get_child_frameworks($framework_id);
        foreach($frameworks as $framework){
            $stmt = $db->prepare("UPDATE `frameworks` SET `parent`=:parent WHERE `value` = :framework_id ");
            
            $stmt->bindParam(":parent", $parent, PDO::PARAM_INT);
            $stmt->bindParam(":framework_id", $framework['value'], PDO::PARAM_INT);
            
            // Execute the database query
            $stmt->execute();
        }

        // Close the database connection
        db_close($db);

        // If customization extra is enabled, delete custom_framework_data related with framework ID
        if(customization_extra())
        {
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            delete_custom_data_by_row_id($framework_id, "framework");
        }

        $message = "A framework named \"{$name}\" was deleted by username \"" . $_SESSION['user'] . "\".";
        write_log((int)$framework_id + 1000, $_SESSION['uid'], $message, "framework");

        // Removing residual junction table entries
        cleanup_after_delete("frameworks");

        return true;
    }
    // Check framework ID doesn't exist
    else
    {
        return false;
    }

}

/************************************
 * FUNCTION: UPDATE FRAMEWORK ORDER *
 ************************************/
function update_framework_orders($framework_ids){
    // Open the database connection
    $db = db_open();

    foreach($framework_ids as $key => $framework_id){
        // If this is the team table
        $stmt = $db->prepare("UPDATE `frameworks` SET `order` = :order WHERE `value` = :framework_id ");
        
        $stmt->bindParam(":order", $key, PDO::PARAM_INT);
        $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
        
        // Execute the database query
        $stmt->execute();
    }
    
    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: ADD NEW FRAMEWORK      *
 ************************************/
function add_framework_control($control){

    global $lang, $escaper;
    
    $short_name = isset($control['short_name']) ? $control['short_name'] : "";
    $long_name = isset($control['long_name']) ? $control['long_name'] : "";
    $description = isset($control['description']) ? $control['description'] : "";
    $supplemental_guidance = isset($control['supplemental_guidance']) ? $control['supplemental_guidance'] : "";
    $framework_ids = !empty($control['framework_ids']) ? (is_array($control['framework_ids']) ? $control['framework_ids'] : explode(",", $control['framework_ids'])) : [];
    $control_owner = isset($control['control_owner']) ? (int)$control['control_owner'] : 0;
    $control_class = isset($control['control_class']) ? (int)$control['control_class'] : 0;
    $control_phase = isset($control['control_phase']) ? (int)$control['control_phase'] : 0;
    $control_number = isset($control['control_number']) ? $control['control_number'] : "";
    $control_current_maturity = isset($control['control_current_maturity']) ? $control['control_current_maturity'] : get_setting("default_current_maturity");
    $control_desired_maturity = isset($control['control_desired_maturity']) ? $control['control_desired_maturity'] : get_setting("default_desired_maturity");
    $control_priority = isset($control['control_priority']) ? (int)$control['control_priority'] : 0;
    $control_type = isset($control['control_type']) ? $control['control_type'] : [1];
    $control_status = isset($control['control_status']) ? (int)$control['control_status'] : 1;
    $family = isset($control['family']) ? (int)$control['family'] : 0;
    $mitigation_percent = isset($control['mitigation_percent']) ? (int)$control['mitigation_percent'] : 0;
    
    // Open the database connection
    $db = db_open();

    // Create a framework
    $stmt = $db->prepare("INSERT INTO `framework_controls` (`short_name`, `long_name`, `description`, `supplemental_guidance`, `control_owner`, `control_class`, `control_phase`, `control_number`, `control_maturity`, `desired_maturity`, `control_priority`, `family`, `mitigation_percent`, `control_status`) VALUES (:short_name, :long_name, :description, :supplemental_guidance, :control_owner, :control_class, :control_phase, :control_number, :control_current_maturity, :control_desired_maturity, :control_priority, :family, :mitigation_percent, :control_status)");
    $stmt->bindParam(":short_name", $short_name, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":long_name", $long_name, PDO::PARAM_STR);
    $stmt->bindParam(":description", $description, PDO::PARAM_STR);
    $stmt->bindParam(":supplemental_guidance", $supplemental_guidance, PDO::PARAM_STR);
    $stmt->bindParam(":control_owner", $control_owner, PDO::PARAM_INT);
    $stmt->bindParam(":control_class", $control_class, PDO::PARAM_INT);
    $stmt->bindParam(":control_phase", $control_phase, PDO::PARAM_INT);
    $stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR);
    $stmt->bindParam(":control_current_maturity", $control_current_maturity, PDO::PARAM_INT);
    $stmt->bindParam(":control_desired_maturity", $control_desired_maturity, PDO::PARAM_INT);
    $stmt->bindParam(":control_priority", $control_priority, PDO::PARAM_INT);
    $stmt->bindParam(":family", $family, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_percent", $mitigation_percent, PDO::PARAM_INT);
    $stmt->bindParam(":control_status", $control_status, PDO::PARAM_INT);
    $stmt->execute();
    
    $control_id = $db->lastInsertId();

    // Update the control keywords
    get_keywords_for_control($control_id, true);

    // Update the control to document mappings for the control but don't care about the response
    $endpoint = "/api/v2/governance/controls/topdocuments?id={$control_id}&refresh=true";
    @call_simplerisk_api_endpoint($endpoint, "GET", false, 1);

    if(count($control_type) > 0) {
        foreach ($control_type as $type) {
            $stmt = $db->prepare("INSERT INTO `framework_control_type_mappings` (`control_id`, `control_type_id`) VALUES (:control_id, :control_type_id)");
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->bindParam(":control_type_id", $type, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
    if(isset($control['map_frameworks'])&&count($control['map_frameworks'])>0) save_control_to_frameworks($control_id, $control['map_frameworks']);
    else if(count($framework_ids)>0) save_control_to_framework_by_ids($control_id, $framework_ids);

    // Update affected assets and asset groups
    if(isset($control['mapped_assets']) && is_array($control['mapped_assets'])) save_control_to_assets($control_id, $control['mapped_assets']);

    // Close the database connection
    db_close($db);

    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        // If there is error in saving custom asset values, return false
        if(save_custom_field_values($control_id, "control") != true)
        {
            delete_framework_control($control_id);
            return false;
        }
    }

    $user = isset($_SESSION['user'])?$_SESSION['user']:"";
    $uid = isset($_SESSION['uid'])?$_SESSION['uid']:"";
    $message = "A new control named \"{$short_name}\" was created by username \"" . $user . "\".";
    write_log((int)$control_id + 1000, $uid, $message, "control");
    
    return $control_id;
}

/********************************************
 * FUNCTION: UPDATE FRAMEWORK CONTROL BY ID *
 ********************************************/
function update_framework_control($control_id, $control){
    $short_name = isset($control['short_name']) ? $control['short_name'] : "";
    $long_name = isset($control['long_name']) ? $control['long_name'] : "";
    $description = isset($control['description']) ? $control['description'] : "";
    $supplemental_guidance = isset($control['supplemental_guidance']) ? $control['supplemental_guidance'] : "";
    $framework_ids = !empty($control['framework_ids']) ? (is_array($control['framework_ids']) ? $control['framework_ids'] : explode(",", $control['framework_ids'])) : [];
    $control_owner = isset($control['control_owner']) ? (int)$control['control_owner'] : 0;
    $control_class = isset($control['control_class']) ? (int)$control['control_class'] : 0;
    $control_phase = isset($control['control_phase']) ? (int)$control['control_phase'] : 0;
    $control_number = isset($control['control_number']) ? $control['control_number'] : "";
    $control_current_maturity = isset($control['control_current_maturity']) ? (int)$control['control_current_maturity'] : 0;
    $control_desired_maturity = isset($control['control_desired_maturity']) ? (int)$control['control_desired_maturity'] : 0;
    $control_priority = isset($control['control_priority']) ? (int)$control['control_priority'] : 0;
    $control_type = isset($control['control_type']) ? $control['control_type'] : [];
    $control_status = isset($control['control_status']) ? (int)$control['control_status'] : 1;
    $family = isset($control['family']) ? (int)$control['family'] : 0;
    $mitigation_percent = isset($control['mitigation_percent']) ? (int)$control['mitigation_percent'] : 0;

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("UPDATE `framework_controls` SET `short_name`=:short_name, `long_name`=:long_name, `description`=:description, `supplemental_guidance`=:supplemental_guidance, `control_owner`=:control_owner, `control_class`=:control_class, `control_phase`=:control_phase, `control_number`=:control_number, `control_maturity`=:control_current_maturity, `desired_maturity`=:control_desired_maturity, `control_priority`=:control_priority, `family`=:family, `mitigation_percent`=:mitigation_percent, `control_status`=:control_status WHERE id=:id;");
    $stmt->bindParam(":short_name", $short_name, PDO::PARAM_STR, 100);
    $stmt->bindParam(":long_name", $long_name, PDO::PARAM_STR);
    $stmt->bindParam(":description", $description, PDO::PARAM_STR);
    $stmt->bindParam(":supplemental_guidance", $supplemental_guidance, PDO::PARAM_STR);
    $stmt->bindParam(":control_owner", $control_owner, PDO::PARAM_INT);
    $stmt->bindParam(":control_class", $control_class, PDO::PARAM_INT);
    $stmt->bindParam(":control_phase", $control_phase, PDO::PARAM_INT);
    $stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR);
    $stmt->bindParam(":control_current_maturity", $control_current_maturity, PDO::PARAM_INT);
    $stmt->bindParam(":control_desired_maturity", $control_desired_maturity, PDO::PARAM_INT);
    $stmt->bindParam(":control_priority", $control_priority, PDO::PARAM_INT);
    $stmt->bindParam(":family", $family, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_percent", $mitigation_percent, PDO::PARAM_INT);
    $stmt->bindParam(":control_status", $control_status, PDO::PARAM_INT);
    $stmt->bindParam(":id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $stmt = $db->prepare("DELETE FROM `framework_control_type_mappings` WHERE `control_id` = :control_id");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Update the control keywords
    get_keywords_for_control($control_id, true);

    // Update the control to document mappings for the control but don't care about the response
    $endpoint = "/api/v2/governance/controls/topdocuments?id={$control_id}&refresh=true";
    @call_simplerisk_api_endpoint($endpoint, "GET", false, 1);

    if(count($control_type) > 0) {
        foreach ($control_type as $type) {
            $stmt = $db->prepare("INSERT INTO `framework_control_type_mappings` (`control_id`, `control_type_id`) VALUES (:control_id, :control_type_id)");
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->bindParam(":control_type_id", $type, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
    // Close the database connection
    db_close($db);
    
    if(isset($control['map_frameworks'])) save_control_to_frameworks($control_id, $control['map_frameworks']);
    else if(count($framework_ids)>0) save_control_to_framework_by_ids($control_id, $framework_ids);

    // Update affected assets and asset groups
    if(isset($control['mapped_assets'])) save_control_to_assets($control_id, $control['mapped_assets']);
    
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        save_custom_field_values($control_id, "control");
    }

    $user = isset($_SESSION['user'])?$_SESSION['user']:"";
    $uid = isset($_SESSION['uid'])?$_SESSION['uid']:"";
    $message = "A control named \"{$short_name}\" was updated by username \"" . $user . "\".";
    write_log((int)$control_id + 1000, $uid, $message, "control");
    
    // Add residual risk scoring history
    add_residual_risk_scoring_histories_for_control($control_id);
    
    return true;
}

/***************************************************************
 * FUNCTION: ADD RESIDUAL RISK SCORING HISTORIES FOR A CONTROL *
 ***************************************************************/
function add_residual_risk_scoring_histories_for_control($control_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT DISTINCT(risk_id) FROM `mitigations` m INNER JOIN `mitigation_to_controls` mtc ON m.id=mtc.mitigation_id WHERE mtc.control_id=:control_id; ");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    $risk_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach($risk_ids as $risk_id){
        // Add residual risk score
        $residual_risk = get_residual_risk((int)$risk_id + 1000);
        add_residual_risk_scoring_history($risk_id, $residual_risk);
    }

    // Close the database connection
    db_close($db);
}

/**************************************
 * FUNCTION: DELETE FRAMEWORK CONTROL *
 **************************************/
function delete_framework_control($control_id){
    // Open the database connection
    $db = db_open();
    $control = get_framework_control($control_id);

    // Check if test used this control
    $stmt = $db->prepare("SELECT count(*) cnt FROM `framework_control_tests` WHERE framework_control_id=:control_id");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    if($test["cnt"] > 0)
    {
        // Delete the table value
        $stmt = $db->prepare("UPDATE `framework_controls` SET deleted=1 WHERE id=:id");
        $stmt->bindParam(":id", $control_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    else
    {
        // Delete the table value
        $stmt = $db->prepare("DELETE FROM `framework_controls` WHERE id=:id");
        $stmt->bindParam(":id", $control_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Removing residual junction table entries
        cleanup_after_delete("framework_controls");
    }
    
    $stmt = $db->prepare("DELETE FROM `framework_control_type_mappings` WHERE `control_id` = :control_id");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete all current control asset relations
    $stmt = $db->prepare("DELETE FROM `control_to_assets` WHERE control_id=:control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    // Delete all current control asset group relations
    $stmt = $db->prepare("DELETE FROM `control_to_asset_groups` WHERE control_id=:control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // If customization extra is enabled, delete custom_control_data related with framework ID
    if(customization_extra())
    {
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        delete_custom_data_by_row_id($control_id, "control");
    }

    $user = isset($_SESSION['user'])?$_SESSION['user']:"";
    $uid = isset($_SESSION['uid'])?$_SESSION['uid']:"";
    if (empty($control)) {
        $message = "A missing control (ID:{$control_id}) was cleaned up by user '{$user}'.";
    } else {
        $message = "A control named '{$control['short_name']}' was deleted by user '{$user}'.";
    }
    write_log((int)$control_id + 1000, $uid, $message, "control");

    // Add residual risk scoring history
    add_residual_risk_scoring_histories_for_control($control_id);
}

/*****************************************
 * FUNCTION: GET FRAMEWORK CONTROL BY ID *
 *****************************************/
function get_framework_control($id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT t1.*, IFNULL(GROUP_CONCAT(DISTINCT m.framework), '') framework_ids, t2.name control_class_name, t3.name control_priority_name, t4.name family_short_name, group_concat(distinct ctype.value) control_type_ids
        FROM `framework_controls` t1 
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `control_priority` t3 on t1.control_priority=t3.value
            LEFT JOIN `family` t4 on t1.family=t4.value
            LEFT JOIN `framework_control_type_mappings` t5 on t1.id=t5.control_id
            LEFT JOIN `control_type` ctype on ctype.value=t5.control_type_id
        WHERE t1.id=:id
        GROUP BY t1.id;
        "
    );
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $control = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($control) {
        // If customization extra is enabled
        if(customization_extra()) {
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            $custom_values = get_custom_value_by_row_id($id, "control");
            $control['custom_values'] = $custom_values;
        }
    } else {
        $control = [];
    }
    // Close the database connection
    db_close($db);
    
    return $control;
}

/**********************************************
 * FUNCTION: GET AVAILABLE CONTROL CLASS List *
 **********************************************/
function getAvailableControlClassList($control_framework=""){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    if($control_framework && is_array($control_framework)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_framework as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "m.control_id is NULL";
                }
                else
                {
                    $where_or_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(m.framework, '".implode(",", $where_or_ids)."')";
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    } else{
        $sql .= " AND 1 ";
    }
    $sql .= "
        GROUP BY
            t2.value
    ";
    
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $results = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);

    return $results;
}

/**********************************************
 * FUNCTION: GET AVAILABLE CONTROL PHASE List *
 **********************************************/
function getAvailableControlPhaseList($control_framework=""){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_phase` t2 on t1.control_phase=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    if($control_framework && is_array($control_framework)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_framework as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "m.control_id is NULL";
                }
                else
                {
                    $where_or_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(m.framework, '".implode(",", $where_or_ids)."')";
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    } else{
        $sql .= " AND 1 ";
    }
    $sql .= "
        GROUP BY
            t2.value
    ";
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $results = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);

    return $results;
}

/**********************************************
 * FUNCTION: GET AVAILABLE CONTROL OWNER List *
 **********************************************/
function getAvailableControlOwnerList($control_framework=""){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.value, t2.username, t2.name
        FROM `framework_controls` t1 
            LEFT JOIN `user` t2 on t1.control_owner=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    if($control_framework && is_array($control_framework)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_framework as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "m.control_id is NULL";
                }
                else
                {
                    $where_or_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(m.framework, '".implode(",", $where_or_ids)."')";
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    } else{
        $sql .= " AND 1 ";
    }
    $sql .= "
        GROUP BY
            t2.value
    ";
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $results = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);

    return $results;
}

/***********************************************
 * FUNCTION: GET AVAILABLE CONTROL FAMILY LIST *
 ***********************************************/
function getAvailableControlFamilyList($control_framework=""){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `family` t2 on t1.family=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    if($control_framework && is_array($control_framework)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_framework as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "m.control_id is NULL";
                }
                else
                {
                    $where_or_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(m.framework, '".implode(",", $where_or_ids)."')";
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    } else{
        $sql .= " AND 1 ";
    }
    $sql .= "
        GROUP BY
            t2.value
    ";
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $results = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);

    return $results;
}

/**************************************************
 * FUNCTION: GET AVAILABLE CONTROL FRAMEWORK LIST *
 **************************************************/
function getAvailableControlFrameworkList($alphabetical_order=false){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.*
        FROM `frameworks` t1
            LEFT JOIN `framework_control_mappings` m ON m.framework=t1.value
            LEFT JOIN `framework_controls` t2 ON m.control_id=t2.id AND t2.deleted=0
        WHERE t1.`status`=1 
        GROUP BY t1.value
        ;
    ";

    // Get available framework list
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $frameworks = $stmt->fetchAll();
    
    // Try decrypt
//    foreach($results as &$result){
//        $result['name'] = try_decrypt($result['name']);
//        $result['description'] = try_decrypt($result['description']);
//    }
    
    // Close the database connection
    db_close($db);
    
    $all_frameworks = get_frameworks(1);
    $all_parent_frameworks = array();

    foreach($frameworks as $framework)
    {
        $parent_frameworks = array();
        get_parent_frameworks($all_frameworks, $framework['value'], $parent_frameworks);
        $all_parent_frameworks = array_merge($all_parent_frameworks, $parent_frameworks);
    }

    $results = array();
    $ids = array();
    if($alphabetical_order == true) usort($all_parent_frameworks, function($a, $b){return strcmp($a["name"], $b["name"]);});
    // Get unique array
    foreach($all_parent_frameworks as $result){
        if(!in_array($result['value'], $ids))
        {
            $results[] = $result;
            $ids[] = $result['value'];
        }
    }

    return $results;
}

/*******************************************************
 * FUNCTION: GET HAS BEEN AUDIT FRAMEWORK CONTROL LIST *
 *******************************************************/
function getHasBeenAuditFrameworkControlList()
{
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id value, t1.short_name name
        FROM 
            `framework_controls` t1 
            LEFT JOIN `framework_control_test_audits` t2 ON t1.id=t2.framework_control_id
        WHERE
             t2.id IS NOT NULL
        GROUP BY 
            t1.id
        ;
    ";

    // Get available framework list
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);

    return $results;
}

/***********************************************
 * FUNCTION: GET HAS BEEN AUDIT FRAMEWORK LIST *
 ***********************************************/
function getHasBeenAuditFrameworkList(){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.value, t1.name, t1.description
        FROM `frameworks` t1
            LEFT JOIN `framework_control_mappings` m ON t1.value=m.framework
            LEFT JOIN `framework_controls` t2 ON m.control_id=t2.id AND t2.deleted=0
            LEFT JOIN `framework_control_test_audits` t3 ON t2.id=t3.framework_control_id
        WHERE
             t3.id IS NOT NULL
        GROUP BY 
            t1.value
        ;
    ";

    // Get available framework list
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Try decrypt
    foreach($results as &$result){
        $result['name'] = try_decrypt($result['name']);
        $result['description'] = try_decrypt($result['description']);
    }
    
    // Close the database connection
    db_close($db);

    return $results;
}

/*************************************************
 * FUNCTION: GET AVAILABLE CONTROL PRIORITY LIST *
 *************************************************/
function getAvailableControlPriorityList($control_framework=""){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_priority` t2 on t1.control_priority=t2.value 
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    if($control_framework && is_array($control_framework)){
        $where = [0];
        $where_or_ids = [];
        foreach($control_framework as $val){
            $val = (int)$val;
            if($val)
            {
                // If unassigned option.
                if($val == -1)
                {
                    $where[] = "m.control_id is NULL";
                }
                else
                {
                    $where_or_ids[] = $val;
                }
            }
        }
        $where[] = "FIND_IN_SET(m.framework, '".implode(",", $where_or_ids)."')";
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    } else{
        $sql .= " AND 1 ";
    }
    $sql .= "
        GROUP BY
            t2.value
    ORDER BY
        CAST(t2.name AS UNSIGNED), t2.name ASC
    ";
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $results = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);

    return $results;
}

/*********************************************
 * FUNCTION: GET AVAILABLE CONTROL TYPE LIST *
 *********************************************/
function getAvailableControlTypeList($control_framework=""){
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("");
    
    $stmt->execute();

    $results = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);

    return $results;
}

/**************************************************
 * FUNCTION: GET DOCUMENT VERSIONS BY DOCUMENT ID *
 **************************************************/
function get_document_versions_by_id($id) {

    // Open the database connection
    $db = db_open();
    if (team_separation_extra()) {

        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents("t1" , false);

    } else {
        
        $where = " 1";

    }

    $sql = "
        SELECT
            t1.*, t2.version file_version, t2.unique_name, t2.timestamp file_upload_time, t3.value as status
        FROM 
            `documents` t1 
            INNER JOIN `compliance_files` t2 ON t1.id=t2.ref_id AND t2.ref_type='documents'
            LEFT JOIN `document_status` t3 ON t1.document_status=t3.value
        WHERE 
            t1.id=:id AND {$where}
        ORDER BY 
            t2.version;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);

    return $results;

}

/*****************************************
 * FUNCTION: GET DOCUMENT BY DOCUMENT ID *
 *****************************************/
function get_document_by_id($id)
{
    // Open the database connection
    $db = db_open();
    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents("t1" , false);
    } else $where = " 1";

    $sql = "
        SELECT t1.*, t2.version file_version, t2.unique_name, t2.name file_name, t2.size file_size, t3.value as status,
            GROUP_CONCAT(DISTINCT f.value) framework_ids, 
            GROUP_CONCAT(DISTINCT fc.id) control_ids
        FROM `documents` t1 
            LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
            LEFT JOIN `document_status` t3 ON t1.document_status=t3.value
            LEFT JOIN `document_framework_mappings` dfm ON t1.id=dfm.document_id
            LEFT JOIN `frameworks` f ON dfm.framework_id=f.value
            LEFT JOIN `document_control_mappings` dcm ON t1.id=dcm.document_id AND dcm.selected=1
            LEFT JOIN `framework_controls` fc ON dcm.control_id=fc.id
        WHERE t1.id=:id AND {$where}
        GROUP BY t1.id
        ;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    return $result;
}

/********************************************
 * FUNCTION: GET DOCUMENTS BY DOCUMENT TYPE *
 ********************************************/
function get_documents($type="")
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.*, t2.version file_version, t2.unique_name, t3.value as status,
            GROUP_CONCAT(DISTINCT f.value) framework_ids, 
            GROUP_CONCAT(DISTINCT fc.id) control_ids
        FROM `documents` t1 
	        LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
            LEFT JOIN `document_status` t3 ON t1.document_status=t3.value
            LEFT JOIN `document_framework_mappings` dfm ON t1.id=dfm.document_id
            LEFT JOIN `frameworks` f ON dfm.framework_id=f.value
            LEFT JOIN `document_control_mappings` dcm ON t1.id=dcm.document_id AnD dcm.selected=1
            LEFT JOIN `framework_controls` fc ON dcm.control_id=fc.id
    ";
    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents("t1");
    } else $where = " WHERE 1";
    if($type) {
        $sql .= $where . " AND t1.document_type=:type";
    } else {
         $sql .= $where;
    }
    $sql .= " GROUP BY t1.id ORDER BY t1.document_type, t1.document_name";

    $stmt = $db->prepare($sql);
    if($type) $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $results;
}

/************************************
 * FUNCTION: MAKE TREE OPTIONS HTML *
 ************************************/
function make_tree_options_html($options, $parent, &$html, $indent="", $selected=0){
    global $escaper;

    foreach($options as $option){
        if($option['parent'] == $parent){
            if($selected == $option['value']){
                $html .= "<option selected value='{$option['value']}'>{$indent}{$escaper->escapeHtml($option['name'])}</option>\n";
            }
            else{
                $html .= "<option value='{$option['value']}'>{$indent}{$escaper->escapeHtml($option['name'])}</option>\n";
            }
            make_tree_options_html($options, $option['value'], $html, "{$indent}&nbsp;&nbsp;", $selected);
        }
    }
}

/******************************
 * FUNCTION: ADD NEW DOCUMENT *
 ******************************/
function add_document($submitted_by, $document_type, $document_name, $control_ids, $framework_ids, $parent, $status, $creation_date, $last_review_date, $review_frequency, $next_review_date, $approval_date, $document_owner, $additional_stakeholders, $approver, $team_ids){
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();
    
    // Check if the framework exists
    $stmt = $db->prepare("SELECT * FROM `documents` where document_name=:document_name AND document_type=:document_type ; ");
    $stmt->bindParam(":document_name", $document_name);
    $stmt->bindParam(":document_type", $document_type);
    $stmt->execute();
    $row = $stmt->fetch();
    if(isset($row[0])){
        set_alert(true, "bad", $escaper->escapeHtml($lang['DocumentNameExist']));
        return false;
    }
    // Create a document
    $stmt = $db->prepare("INSERT INTO `documents` (`submitted_by`, `document_type`, `document_name`, `parent`, `document_status`, `file_id`, `creation_date`, `last_review_date`, `review_frequency`, `next_review_date`, `approval_date`, `document_owner`, `additional_stakeholders`, `approver`, `team_ids`) VALUES (:submitted_by, :document_type, :document_name, :parent, :status, :file_id, :creation_date, :last_review_date, :review_frequency, :next_review_date, :approval_date, :document_owner, :additional_stakeholders, :approver, :team_ids)");
    $stmt->bindParam(":submitted_by", $submitted_by, PDO::PARAM_INT);
    $stmt->bindParam(":document_type", $document_type, PDO::PARAM_STR);
    $stmt->bindParam(":document_name", $document_name, PDO::PARAM_STR);
    $stmt->bindParam(":parent", $parent, PDO::PARAM_INT);
    $stmt->bindParam(":status", $status, PDO::PARAM_STR);
    $init_file_id = 0;
    $stmt->bindParam(":file_id", $init_file_id, PDO::PARAM_INT);
    $stmt->bindParam(":creation_date", $creation_date, PDO::PARAM_STR);
    $stmt->bindParam(":last_review_date", $last_review_date, PDO::PARAM_STR);
    $stmt->bindParam(":review_frequency", $review_frequency, PDO::PARAM_INT);
    $stmt->bindParam(":next_review_date", $next_review_date, PDO::PARAM_STR);
    $stmt->bindParam(":approval_date", $approval_date, PDO::PARAM_STR);
    $stmt->bindParam(":document_owner", $document_owner, PDO::PARAM_INT);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR);
    $stmt->bindParam(":approver", $approver, PDO::PARAM_INT);
    $stmt->bindParam(":team_ids", $team_ids, PDO::PARAM_STR);

    $stmt->execute();

    $document_id = $db->lastInsertId();

    // Split the control_ids string into an array
    $ids = array_map('trim', explode(',', $control_ids));

    // Loop and insert each mapped control id into the document_control_mappings table
    foreach ($ids as $control_id)
    {
        if (is_numeric($control_id))
        {
            $stmt = $db->prepare("INSERT INTO `document_control_mappings` (`document_id`, `control_id`, `selected`) VALUES (:document_id, :control_id, 1)");
            $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Close the database connection
    db_close($db);

    // Save document frameworks
    save_junction_values("document_framework_mappings", "document_id", $document_id, "framework_id", $framework_ids);

    // Save document framework controls
    save_junction_values("document_control_mappings", "document_id", $document_id, "control_id", $control_ids);

    // If submitted files are existing, save files
    if(!empty($_FILES['file'])){
        $files = $_FILES['file'];
        list($status, $file_ids, $errors) = upload_compliance_files($document_id, "documents", $files);
        if($file_ids){
            $file_id = $file_ids[0];
        }
    }

    // Check if error was happening in uploading files
    if(!empty($errors)) {
        // Delete added document if failed to upload a document file
        delete_document($document_id);
        $errors = array_unique($errors);
        foreach ($errors as $error) {
            set_alert(true, "bad", $error);
        }
        return false;
    } elseif(empty($file_id)) {
        // Delete added document if failed to upload a document file
        delete_document($document_id);
        set_alert(true, "bad", $lang['FailedToUploadFile']);
        return false;
    } else {
        $stmt = $db->prepare("UPDATE `documents` SET file_id=:file_id WHERE id=:document_id ");
        $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->execute();

        $submitted_by_name = get_user_name($submitted_by);
        $message = _lang('AuditLog_DocumentCreate', array('document_name' => $document_name, 'user_name' => $submitted_by_name), false);
        write_log(1000, $submitted_by, $message, "document");

        // Update the document keywords
        get_keywords_for_document($document_id, true);

        // Update the document to control mappings for the document but don't care about the response
        $endpoint = "/api/v2/governance/documents/topcontrols?id={$document_id}&refresh=true";
        @call_simplerisk_api_endpoint($endpoint, "GET", false, 1);

        // If notification is enabled
        if (notification_extra()) {
            // Include the notification extra
            require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

            // Send the notification
            notify_new_document($document_id);
        }

        return $document_id;
    }
}

/*****************************
 * FUNCTION: UPDATE DOCUMENT *
 *****************************/
function update_document($document_id, $updated_by, $document_type, $document_name, $control_ids, $framework_ids, $parent, $status, $creation_date, $last_review_date, $review_frequency, $next_review_date, $approval_date, $document_owner, $additional_stakeholders, $approver, $team_ids, $audit_log=true){
    global $lang, $escaper;
    
    // Open the database connection
    $db = db_open();
    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents(false , false);
    } else $where = " 1";
    $sql = "SELECT * FROM `documents` where document_name=:document_name AND document_type=:document_type AND id<>:id AND {$where}; ";

    // Check if the document exists
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":document_name", $document_name, PDO::PARAM_STR);
    $stmt->bindParam(":document_type", $document_type, PDO::PARAM_STR);
    $stmt->bindParam(":id", $document_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    if(isset($row[0])){
        set_alert(true, "bad", $escaper->escapeHtml($lang['DocumentNameExist']));
        return false;
    }

    // Check permission for update this document with team separation
    $sql = "SELECT * FROM `documents` where id = :id AND {$where}; ";

    // Check if the document exists
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $document_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    if(!$row[0]){
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoModifyDocumentationPermission']));
        return false;
    }

    // Get the existing values for this document
    $row = get_document_by_id($document_id);

    // Create an array of before values
    $before = [
        'updated_by' => (int)$row['updated_by'],
    	'document_type' => $row['document_type'],
    	'document_name' => $row['document_name'],
    	'control_ids' => $row['control_ids'],
    	'framework_ids' => $row['framework_ids'],
    	'parent' => (int)$row['parent'],
    	'document_status' => $row['document_status'],
    	'creation_date' => $row['creation_date'],
    	'last_review_date' => $row['last_review_date'],
    	'review_frequency' => (int)$row['review_frequency'],
    	'next_review_date' => $row['next_review_date'],
    	'approval_date' => $row['approval_date'],
    	'document_owner' => (int)$row['document_owner'],
    	'additional_stakeholders' => $row['additional_stakeholders'],
    	'approver' => (int)$row['approver'],
    	'team_ids' => $row['team_ids'],
    ];

    // Create an array of after values
    $after = [
        'updated_by' => (int)$updated_by,
        'document_type' => $document_type,
        'document_name' => $document_name,
        'control_ids' => implode(',', $control_ids),
        'framework_ids' => implode(',', $framework_ids),
        'parent' => (int)$parent,
        'document_status' => $status,
        'creation_date' => $creation_date,
        'last_review_date' => $last_review_date,
        'review_frequency' => (int)$review_frequency,
        'next_review_date' => $next_review_date,
        'approval_date' => $approval_date,
        'document_owner' => (int)$document_owner,
        'additional_stakeholders' => $additional_stakeholders,
        'approver' => (int)$approver,
        'team_ids' => $team_ids,
    ];

    // If the notification extra is enabled then get the changes in a format the extra can use too
    if (notification_extra()) {
        [$changes, $changes_arr] = get_changes('document', $before, $after, 3);
    } else {
        $changes = get_changes('document', $before, $after);
    }

    // Update a document
    $stmt = $db->prepare("UPDATE `documents` SET `updated_by` = :updated_by, `document_type`=:document_type, `document_name`=:document_name, `parent`=:parent, `document_status`=:document_status, `creation_date`=:creation_date, `last_review_date`=:last_review_date, `review_frequency`=:review_frequency, `next_review_date`=:next_review_date, `approval_date`=:approval_date, `document_owner`=:document_owner, `additional_stakeholders`=:additional_stakeholders , `approver`=:approver, `team_ids`=:team_ids WHERE id=:document_id; ");
    $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
    $stmt->bindParam(":updated_by", $updated_by, PDO::PARAM_INT);
    $stmt->bindParam(":document_type", $document_type, PDO::PARAM_STR);
    $stmt->bindParam(":document_name", $document_name, PDO::PARAM_STR);
    $stmt->bindParam(":parent", $parent, PDO::PARAM_INT);
    $stmt->bindParam(":document_status", $status, PDO::PARAM_STR);
    $stmt->bindParam(":creation_date", $creation_date, PDO::PARAM_STR);
    $stmt->bindParam(":last_review_date", $last_review_date, PDO::PARAM_STR);
    $stmt->bindParam(":review_frequency", $review_frequency, PDO::PARAM_INT);
    $stmt->bindParam(":next_review_date", $next_review_date, PDO::PARAM_STR);
    $stmt->bindParam(":approval_date", $approval_date, PDO::PARAM_STR);
    $stmt->bindParam(":document_owner", $document_owner, PDO::PARAM_STR);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR);
    $stmt->bindParam(":approver", $approver, PDO::PARAM_INT);
    $stmt->bindParam(":team_ids", $team_ids, PDO::PARAM_STR);
    $stmt->execute();

    // Deselect existing mappings for this document
    $stmt = $db->prepare("UPDATE `document_control_mappings` SET `selected`=0, `ai_run` = 0 WHERE `document_id`=:document_id;");
    $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
    $stmt->execute();

    // Split the control_ids string into an array
    $ids = array_map('trim', explode(',', $control_ids));

    // Loop and insert each mapped control id into the document_control_mappings table
    foreach ($ids as $control_id)
    {
        if (is_numeric($control_id))
        {
            $stmt = $db->prepare("
                INSERT INTO `document_control_mappings` (`document_id`, `control_id`, `selected`)
                VALUES (:document_id, :control_id, 1)
                ON DUPLICATE KEY UPDATE `selected` = 1
            ");
            $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Close the database connection
    db_close($db);

    // Update the document keywords
    get_keywords_for_document($document_id, true);

    // Update the document to control mappings for the document but don't care about the response
    $endpoint = "/api/v2/governance/documents/topcontrols?id={$document_id}&refresh=true";
    @call_simplerisk_api_endpoint($endpoint, "GET", false, 1);

    // Save document frameworks
    save_junction_values("document_framework_mappings", "document_id", $document_id, "framework_id", $framework_ids);

    // Save document framework controls
    save_junction_values("document_control_mappings", "document_id", $document_id, "control_id", $control_ids);

    // If submitted files are existing, save files
    if(!empty($_FILES['file'])){
        $document = get_document_by_id($document_id);
        $version = $document['file_version'] + 1;

        $files = $_FILES['file'];
        list($status, $file_ids, $errors) = upload_compliance_files($document_id, "documents", $files, $version);
        if($file_ids){
            $file_id = $file_ids[0];
        }
    }

    // Check if error was happen in uploading files
    if(!empty($errors)){
        $errors = array_unique($errors);
        set_alert(true, "bad", implode(", ", $errors));
        return false;
    }elseif(!empty($file_id)){
        $stmt = $db->prepare("UPDATE `documents` SET file_id=:file_id WHERE id=:document_id ");
        $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Only notify of the changes if there's any 
    if ($changes) {
        $updated_by_name = get_user_name($updated_by);
        $message = _lang('AuditLog_DocumentUpdates', array('document_name' => $document_name, 'document_id' => $document_id, 'user_name' => $updated_by_name, 'changes' => $changes), false);
        write_log(1000, $updated_by, $message, "document");

        // If notification is enabled
        if (notification_extra()) {
            // Include the notification extra
            require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

            // Send the notification
            notify_document_update($document_id, ['changes' => $changes_arr]);
        }
    }

    return $document_id;
}

/*****************************
 * FUNCTION: DELETE DOCUMENT *
 *****************************/
function delete_document($document_id, $version=null)
{
    global $lang, $escaper;
    
    // Open the database connection
    $db = db_open();

    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents(false , false);
    } else $where = " 1";

    // Check permission for delete this document with team separation
    $sql = "SELECT * FROM `documents` where id = :id AND {$where}; ";

    // Check if the document exists
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $document_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row){
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoDeleteDocumentationPermission']));
        return false;
    }

    // Deletes documents only to have this version number
    if($version)
    {
        $stmt = $db->prepare("DELETE FROM compliance_files WHERE ref_id=:document_id AND ref_type='documents' AND version=:version; ");
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->bindParam(":version", $version, PDO::PARAM_INT);
        $stmt->execute();

        // Run AI on this document again
        $stmt = $db->prepare("UPDATE `document_control_mappings` SET `ai_run` = 0 WHERE document_id=:document_id; ");
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    // Deletes all documents by document ID
    else
    {
        $stmt = $db->prepare("DELETE FROM compliance_files WHERE ref_id=:document_id AND ref_type='documents'; ");
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $db->prepare("DELETE FROM documents WHERE id=:document_id; ");
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $db->prepare("DELETE FROM `document_control_mappings` WHERE document_id=:document_id; ");
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->execute();

        cleanup_after_delete("documents");
    }

    $message = "The existing document ID \"".$document_id."\" was deleted by the \"" . $_SESSION['user'] . "\" user.";
    write_log(1000, $_SESSION['uid'], $message, "document");
    
    // Close the database connection
    db_close($db);
    
    return true;
}

/*****************************************
 * FUNCTION: GET DOCUMENT HIERARCHY TABS *
 *****************************************/
function get_document_hierarchy_tabs($type="")
{
    global $lang;
    global $escaper;
    
    echo "
        <table class='document-table' id='document-hierarchy-table'>
            <thead >
                <th data-options=\"field:'document_name'\" width='25%'>{$escaper->escapeHtml($lang['DocumentName'])}</th>
                <th data-options=\"field:'document_type'\" width='10%'>{$escaper->escapeHtml($lang['DocumentType'])}</th>
                <th data-options=\"field:'framework_names'\" width='20%'>{$escaper->escapeHtml($lang['ControlFrameworks'])}</th>
                <th data-options=\"field:'control_names'\" width='20%'>{$escaper->escapeHtml($lang['Controls'])}</th>
                <th data-options=\"field:'creation_date'\" width='9%'>{$escaper->escapeHtml($lang['CreationDate'])}</th>
                <th data-options=\"field:'approval_date'\" width='9%'>{$escaper->escapeHtml($lang['ApprovalDate'])}</th>
                <th data-options=\"field:'status'\" width='7%'>{$escaper->escapeHtml($lang['Status'])}</th>
            </thead>
        </table>
    ";
} 

/***************************************
 * FUNCTION: GET DOCUMENT TABULAR TABS *
 ***************************************/
function get_document_tabular_tabs($type, $document_id=0)
{
    global $lang;
    global $escaper;
    
    echo "
        <table class='document-table' id='{$type}-table'>
            <thead>
                <th data-options=\"field:'document_name'\" width='23%'>{$escaper->escapeHtml($lang['DocumentName'])}</th>
                <th data-options=\"field:'document_type'\" width='10%'>{$escaper->escapeHtml($lang['DocumentType'])}</th>
                <th data-options=\"field:'framework_names'\" width='18%'>{$escaper->escapeHtml($lang['ControlFrameworks'])}</th>
                <th data-options=\"field:'control_names'\" width='18%'>{$escaper->escapeHtml($lang['Controls'])}</th>
                <th data-options=\"field:'creation_date'\" width='9%'>{$escaper->escapeHtml($lang['CreationDate'])}</th>
                <th data-options=\"field:'approval_date'\" width='9%'>{$escaper->escapeHtml($lang['ApprovalDate'])}</th>
                <th data-options=\"field:'status'\" width='6%'>{$escaper->escapeHtml($lang['Status'])}</th>
                <th data-options=\"field:'actions'\" width='7%'>{$escaper->escapeHtml($lang['Actions'])}</th>
            </thead>
        </table>
    ";
}
 
/***********************************************
 * FUNCTION: GET DOCUMENTS DATA IN TREE FORMAT *
 ***********************************************/
function get_documents_as_treegrid($type){
    global $lang, $escaper;
    $filterRules = isset($_GET["filterRules"])?json_decode($_GET["filterRules"],true):array();
    $filtered_documents = array();
    $documents = get_documents($type);
    foreach($documents as &$document){
        $frameworks = get_frameworks_by_ids($document["framework_ids"] ?? "");
        $framework_names = implode(", ", array_map(function($framework){
            return $framework['name'];
        }, $frameworks));

        $control_ids = explode(",", $document["control_ids"] ?? "");
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
                        if( stripos(get_name_by_value('document_status', $document['status']), $value) === false ){
                            continue 3;
                        }
                        break;
                }
            }
        }

        $document['value'] = $document['id'];
        $document['document_type'] = $escaper->escapeHtml($document['document_type']);
        $document['document_name'] = "<a class='text-info' href='" . build_url("governance/download.php?id=" . $document['unique_name']) . "' >".$escaper->escapeHtml($document['document_name'])."</a>";
        $document['framework_ids'] = $escaper->escapeHtml($document['framework_ids']);
        $document['framework_names'] = $escaper->escapeHtml($framework_names);
        $document['control_ids'] = $escaper->escapeHtml($document['control_ids']);
        $document['control_names'] = $escaper->escapeHtml($control_names);
        $document['status'] = $escaper->escapeHtml(get_name_by_value('document_status', $document['status']));
        $document['creation_date'] = format_date($document['creation_date']);
        $document['approval_date'] = format_date($document['approval_date']);
        $document['actions'] = "
            <div class='text-center nowrap'>
                <a class='framework-block--edit mx-1' data-id='".((int)$document['id'])."'><i class='fa fa-edit'></i></a>
                <a class='framework-block--delete mx-1' data-id='".((int)$document['id'])."'><i class='fa fa-trash'></i></a></div>";
        $filtered_documents[] = $document;
    }

    // If there're documents filtered out
    if(count($filterRules) > 0 && count($filtered_documents) != count($documents)) {
        // remove the parents to make every element a 'root' element to make sure they're properly displayed
        $filtered_documents = array_map(function($n) {
            $n['parent'] = 0;
            return $n;
        }, $filtered_documents);
    }

    $results = array();
    $count = 0;
    makeTree($filtered_documents, 0, $results, $count);
    if(isset($results['children'][0])){
        $results['children'][0]['totalCount'] = $count;
    }
    return isset($results['children']) ? $results['children'] : [];
}

/************************************
 * FUNCTION: GET FRAMEWORK CONTROLS *
 ************************************/
function get_framework_controls_long_name($control_ids=false)
{
    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT t1.long_name
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `control_priority` t3 on t1.control_priority=t3.value
            LEFT JOIN `family` t4 on t1.family=t4.value
            LEFT JOIN `control_phase` t5 on t1.control_phase=t5.value
            LEFT JOIN `user` t6 on t1.control_owner=t6.value
        WHERE
            t1.deleted=0
    ";
    if($control_ids !== false)
    {
        $sql .= " AND FIND_IN_SET(t1.id, '{$control_ids}') ";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    // Get the list in the array
    $controls = $stmt->fetchAll();

    // For each $control
    foreach ($controls as $key => $control)
    {
        $long_name = $control;
    }

    // Close the database connection
    db_close($db);

    return $long_name;
}

function display_expandable_framework_names($framework_names_in, $cutoff) {

    global $lang, $escaper;

    $framework_names_in = $escaper->escapeHtml($framework_names_in);
    
    $framework_names = explode(",", $framework_names_in);
    if (count($framework_names) <= $cutoff)
        return $framework_names_in;

    $html = "<span>";

    foreach($framework_names as $idx => $name) {
        $html .= "<span" .($idx > $cutoff - 1 ? " class='the_rest' style='display:none'" : "") . ">" . ($idx != 0 ? ", ":"") . $escaper->escapeHtml($name) . "</span>";
    }

    $html .= "<a href='#' onclick=\"$(this).parent().find('.the_rest').toggle();return false;\" class='btn btn-sm the_rest' style='margin-left: 5px;'>" . _lang('ShowXMore', array('x' => count($framework_names) - $cutoff)) . "</a>";
    $html .= "<a href='#' onclick=\"$(this).parent().find('.the_rest').toggle();return false;\" class='btn btn-sm the_rest' style='margin-left: 5px;display:none'>" . $escaper->escapeHtml($lang['ShowLess']) . "</a>";

    $html .= "</span>";

    return $html;
}

/********************************
 * FUNCTION: GET EXCEPTION DATA *
 ********************************/
function get_exception($id){

    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.*, t2.version file_version, t2.unique_name, t2.name file_name, t2.size file_size, t1.status as document_exceptions_status
        FROM `document_exceptions` t1 
            LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
        WHERE t1.value=:id
    ";

    // Query the database
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $exception = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $exception;
}

/**********************************
 * FUNCTION: GET EXCEPTION STATUS *
 **********************************/
function get_exceptions_status() {

    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT 
            des.*
        FROM 
            `document_exceptions_status` des;
    ";

    // Query the database
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $exceptions_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $exceptions_status;

}

/********************************
 * FUNCTION: GET EXCEPTION DATA *
 ********************************/
function get_exception_for_display($id, $type){

    // Open the database connection
    $db = db_open();

    $type_based_sql_parts = [];
    if ($type == 'policy') {
        $type_based_sql_parts[] = 'p.document_name as parent_name';
        $type_based_sql_parts[] = 'left join documents p on de.policy_document_id = p.id';
        $type_based_sql_parts[] = 'p.document_type = \'policies\'';
    } else {
        $type_based_sql_parts[] = 'c.short_name as parent_name';
        $type_based_sql_parts[] = 'left join framework_controls c on de.control_framework_id = c.id';
        $type_based_sql_parts[] = 'c.id is not null';
    }

    $sql = "
        select
            {$type_based_sql_parts[0]},
            de.name,
            o.name as owner,
            de.additional_stakeholders,
            de.associated_risks,
            de.creation_date,
            de.review_frequency,
            de.next_review_date,
            de.approval_date,
            a.name as approver,
            de.description,
            de.justification,
            f.version file_version,
            f.unique_name,
            f.name file_name,
            des.name as document_exceptions_status,
            fr.name as framework_name
        from
            document_exceptions de
            {$type_based_sql_parts[1]}
            left join user o on o.value = de.owner
            left join user a on a.value = de.approver
            left join compliance_files f on de.file_id=f.id
            left join document_exceptions_status des on de.status = des.value
            left join frameworks fr on fr.value = de.framework_id
        where
            {$type_based_sql_parts[2]}
            and de.value = :id;";

    // Query the database
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $exception = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $exception;
}


/***********************************************
 * FUNCTION: GET EXCEPTION DATA IN TREE FORMAT *
 ***********************************************/
function get_exceptions_as_treegrid($type){

    global $lang, $escaper;

    // Set filter rules if they are set and not too long
    if (isset($_GET["filterRules"]) && strlen($_GET["filterRules"]) <= 10000) {

        // Set the json_decode depth to 10 to avoid issues with deeply nested structures
        $filterRules = json_decode($_GET["filterRules"], true, 10);

        if (!is_array($filterRules)) {
            $filterRules = [];
        }

        // Limit total rules: at most 5 rules
        $filterRules = array_slice($filterRules, 0, 5);

        // Limit per-rule value length
        foreach ($filterRules as &$rule) {
            if (isset($rule['value']) && is_string($rule['value'])) {
                $rule['value'] = substr($rule['value'], 0, 100);
            }
        }
        unset($rule);
        
    } else {
        $filterRules = [];
    }

    // Open the database connection
    $db = db_open();

    $policy_sql_base = "
        select
            p.id as id,
            p.document_name as parent_name,
            'policy' as type,
            de.*,
            des.name as document_exceptions_status
        from document_exceptions de
            left join documents p on de.policy_document_id = p.id
            left join document_exceptions_status des on de.status = des.value
        where
            p.document_type = 'policies'";

    $control_sql_base = "
        select
            c.id as id,
            c.short_name as parent_name,
            'control' as type,
            de.*,
            des.name as document_exceptions_status
        from document_exceptions de
            left join framework_controls c on de.control_framework_id = c.id
            left join document_exceptions_status des on de.status = des.value
        where
            c.id is not null";

    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents("p", false);
    } else $where = " 1";

    $policy_sql_base .= " AND ".$where;

    if ($type == 'policy') {
        $sql = "{$policy_sql_base} and de.approved = 1 order by p.document_name, de.name;";
    } elseif ($type == 'control') {
        $sql = "{$control_sql_base} and de.approved = 1 order by c.short_name, de.name;";
    } else {
        $sql = "select * from ({$policy_sql_base} union all {$control_sql_base}) u where u.approved = 0 order by u.parent_name, u.name;";
    }

    // Query the database
    $stmt = $db->prepare($sql);

    $stmt->execute();

    $exceptions = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    $exception_tree = [];

    $update = check_permission_exception('update');
    $approve = check_permission_exception('approve');
    $delete = check_permission_exception('delete');

    foreach($exceptions as $id => $group){
        $branch = [];

        $all_approved = true;
        $branch_type = false;
        $parent_name = "";

        foreach($group as $row){

            if (count($filterRules) > 0) {
                foreach ($filterRules as $filter) {
                    $value = $filter['value'];
                    switch ($filter['field']) {
                        case "name":
                            if (stripos($row['name'], $value) === false) {
                                continue 3;
                            }
                            break;
                        case "description":
                            if (stripos(strip_tags_and_extra_whitespace($row['description']), $value) === false) {
                                continue 3;
                            }
                            break;
                        case "justification":
                            if (stripos(strip_tags_and_extra_whitespace($row['justification']), $value) === false) {
                                continue 3;
                            }
                            break;
                        case "next_review_date":
                            if( stripos(format_date($row['next_review_date']), $value) === false ){
                                continue 3;
                            }
                            break;
                        case "status":
                            if (!empty($value) && ($row['status'] != $value)) {
                                continue 3;
                            }
                            break;
                        default: 
                            break;
                    }
                }
            }
            
            $parent_name = $row['parent_name'];
            $row['children'] = [];

            $row['name'] = "<span class='exception-name'><a class='text-info' href='#' data-id='".((int)$row['value'])."' data-type='{$row['type']}'>{$escaper->escapeHtml($row['name'])}</a></span>";

            // The variable to be used in treegrid filtering for status
            $row['status_value'] = $row['status'];
            $row['status'] = $escaper->escapeHtml($row['document_exceptions_status']);

            if ($type === "unapproved" && $approve)
                $approve_action = "<a class='exception--approve' data-id='".((int)$row['value'])."' data-type='{$row['type']}'><i class='fa fa-check'></i></a>&nbsp;&nbsp;&nbsp;";
            else $approve_action = "";

            if ($update)
                $updateAction = "<a class='exception--edit' data-id='".((int)$row['value'])."' data-type='{$row['type']}'><i class='fa fa-edit'></i></a>&nbsp;&nbsp;&nbsp;";
            else $updateAction = "";

            if ($delete)
                $deleteAction = "<a class='exception--delete' data-id='".((int)$row['value'])."' data-type='{$row['type']}' data-approved='" . ($row['approved'] ? 'true' : 'false') . "'><i class='fa fa-trash'></i></a>"; 
            else $deleteAction = "";

            $row['actions'] = "<div class='text-center'>{$approve_action}{$updateAction}{$deleteAction}</div>";

            if (!$branch_type)
                $branch_type = $row['type'];

            $all_approved &= $row['approved'];

            $branch[] = $row;
        }
        if ($delete)
            $parentAction = "<div class='text-center'><a class='exception-batch--delete' data-id='".((int)$id)."' data-type='{$branch_type}' data-all-approved='" . ($all_approved ? 'true' : 'false') . "' data-approved='" . ($type !== "unapproved" ? 'true' : 'false') . "'><i class='fa fa-trash'></i></a></div>";
        else $parentAction = "";

        $exception_tree[] = array('value' => $type . "-" . $id, 'name' => $escaper->escapeHtml($parent_name) . " (" . count($branch) . ")", 'children' => $branch, 'actions' => $parentAction);
    }

    return $exception_tree;
}

/**********************************************************
 * FUNCTION: GET ASSOCIATED EXCEPTION DATA IN TREE FORMAT *
 **********************************************************/
function get_associated_exceptions_as_treegrid($risk_id, $type) {

    global $lang, $escaper;

    $risk_id = (int)$risk_id - 1000; // Convert the risk ID to the original ID by removing the 1000 offset

    // Open the database connection
    $db = db_open();

    $policy_sql_base = "
        select
            p.id as id,
            p.document_name as parent_name,
            'policy' as type,
            de.*,
            des.name as document_exceptions_status
        from document_exceptions de
            left join documents p on de.policy_document_id = p.id
            left join document_exceptions_status des on de.status = des.value
        where
            p.document_type = 'policies'
        and 
            FIND_IN_SET({$risk_id}, de.associated_risks) > 0";

    $control_sql_base = "
        select
            c.id as id,
            c.short_name as parent_name,
            'control' as type,
            de.*,
            des.name as document_exceptions_status
        from document_exceptions de
            left join framework_controls c on de.control_framework_id = c.id
            left join document_exceptions_status des on de.status = des.value
        where
            c.id is not null
        and 
            FIND_IN_SET({$risk_id}, de.associated_risks) > 0";

    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents("p", false);
    } else $where = " 1";

    $policy_sql_base .= " AND ".$where;

    if ($type == 'policy') {
        $sql = "{$policy_sql_base} and de.approved = 1 order by p.document_name, de.name;";
    } elseif ($type == 'control') {
        $sql = "{$control_sql_base} and de.approved = 1 order by c.short_name, de.name;";
    } else {
        $sql = "select * from ({$policy_sql_base} union all {$control_sql_base}) u where u.approved = 0 order by u.parent_name, u.name;";
    }

    // Query the database
    $stmt = $db->prepare($sql);

    $stmt->execute();

    $exceptions = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    $exception_tree = [];

    $update = check_permission_exception('update');
    $approve = check_permission_exception('approve');
    $delete = check_permission_exception('delete');

    foreach($exceptions as $id => $group){
        $branch = [];

        $all_approved = true;
        $branch_type = false;
        foreach($group as $row){
            $parent_name = $row['parent_name'];
            $row['children'] = [];

            $row['name'] = "<span class='exception-name'><a class='text-info' href='#' data-id='".((int)$row['value'])."' data-type='{$row['type']}'>{$escaper->escapeHtml($row['name'])}</a></span>";
            $row['status'] = $escaper->escapeHtml($row['document_exceptions_status']);

            if ($type === "unapproved" && $approve)
                $approve_action = "<a class='exception--approve' data-id='".((int)$row['value'])."' data-type='{$row['type']}'><i class='fa fa-check'></i></a>&nbsp;&nbsp;&nbsp;";
            else $approve_action = "";

            if ($update)
                $updateAction = "<a class='exception--edit' data-id='".((int)$row['value'])."' data-type='{$row['type']}'><i class='fa fa-edit'></i></a>&nbsp;&nbsp;&nbsp;";
            else $updateAction = "";

            if ($delete)
                $deleteAction = "<a class='exception--delete' data-id='".((int)$row['value'])."' data-type='{$row['type']}' data-approved='" . ($row['approved'] ? 'true' : 'false') . "'><i class='fa fa-trash'></i></a>"; 
            else $deleteAction = "";

            $row['actions'] = "<div class='text-center'>{$approve_action}{$updateAction}{$deleteAction}</div>";

            if (!$branch_type)
                $branch_type = $row['type'];

            $all_approved &= $row['approved'];
            $branch[] = $row;
        }
        if ($delete)
            $parentAction = "<div class='text-center'><a class='exception-batch--delete' data-id='".((int)$id)."' data-type='{$branch_type}' data-all-approved='" . ($all_approved ? 'true' : 'false') . "' data-approved='" . ($type !== "unapproved" ? 'true' : 'false') . "'><i class='fa fa-trash'></i></a></div>";
        else $parentAction = "";

        $exception_tree[] = array('value' => $type . "-" . $id, 'name' => $escaper->escapeHtml($parent_name) . " (" . count($branch) . ")", 'children' => $branch, 'actions' => $parentAction);
    }

    return $exception_tree;
}

/********************************
 * FUNCTION: GET EXCEPTION TABS *
 ********************************/
function get_exception_tabs($type)
{
    global $lang, $escaper;

    echo "
        <table id='exception-table-{$type}' class='easyui-treegrid exception-table'>
            <thead>
                <th data-options=\"field:'name'\" width='25%'>".$escaper->escapeHtml($lang[ucfirst ($type) . "ExceptionName"])."</th>
                <th data-options=\"field:'status'\" width='8%'>".$escaper->escapeHtml($lang['Status'])."</th>
                <th data-options=\"field:'description'\" width='25%'>".$escaper->escapeHtml($lang['Description'])."</th>
                <th data-options=\"field:'justification'\" width='24%'>".$escaper->escapeHtml($lang['Justification'])."</th>
                <th data-options=\"field:'next_review_date', align: 'center'\" width='10%'>".$escaper->escapeHtml($lang['NextReviewDate'])."</th>
                <th data-options=\"field:'actions'\" width='8%'>".$escaper->escapeHtml($lang['Actions'])."</th>
            </thead>
        </table>
    ";
}

/*******************************************
 * FUNCTION: GET ASSOCIATED EXCEPTION TABS *
 *******************************************/
function get_associated_exception_tabs($type) {

    global $lang, $escaper;

    echo "
        <table id='associated-exception-table-{$type}' class='easyui-treegrid exception-table'>
            <thead>
                <th data-options=\"field:'name'\" width='25%'>{$escaper->escapeHtml($lang[ucfirst ($type) . "ExceptionName"])}</th>
                <th data-options=\"field:'status'\" width='8%'>{$escaper->escapeHtml($lang['Status'])}</th>
                <th data-options=\"field:'description'\" width='25%'>{$escaper->escapeHtml($lang['Description'])}</th>
                <th data-options=\"field:'justification'\" width='24%'>{$escaper->escapeHtml($lang['Justification'])}</th>
                <th data-options=\"field:'next_review_date', align: 'center'\" width='18%'>{$escaper->escapeHtml($lang['NextReviewDate'])}</th>
            </thead>
        </table>
    ";

}

function create_exception($name, $status, $policy, $framework, $control, $owner, $additional_stakeholders, $creation_date, $review_frequency, $next_review_date, $approval_date, $approver, $approved, $description, $justification, $associated_risks) {

    $db = db_open();

    // Create an exception
    $stmt = $db->prepare("
        INSERT INTO
            `document_exceptions` (
                `name`,
                `policy_document_id`,
                `framework_id`,
                `control_framework_id`,
                `owner`,
                `additional_stakeholders`,
                `creation_date`,
                `review_frequency`,
                `next_review_date`,
                `approval_date`,
                `approver`,
                `approved`,
                `description`,
                `justification`,
                `associated_risks`,
                `status`
            )
        VALUES (
            :name,
            :policy_document_id,
            :framework_id,
            :control_framework_id,
            :owner,
            :additional_stakeholders,
            :creation_date,
            :review_frequency,
            :next_review_date,
            :approval_date,
            :approver,
            :approved,
            :description,
            :justification,
            :associated_risks,
            :status
        );"
    );

    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":policy_document_id", $policy, PDO::PARAM_INT);
    $stmt->bindParam(":framework_id", $framework, PDO::PARAM_INT);
    $stmt->bindParam(":control_framework_id", $control, PDO::PARAM_INT);
    $stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR);
    $stmt->bindParam(":creation_date", $creation_date, PDO::PARAM_STR);
    $stmt->bindParam(":review_frequency", $review_frequency, PDO::PARAM_INT);
    $stmt->bindParam(":next_review_date", $next_review_date, PDO::PARAM_STR);
    $stmt->bindParam(":approval_date", $approval_date, PDO::PARAM_STR);
    $stmt->bindParam(":approver", $approver, PDO::PARAM_INT);
    $stmt->bindParam(":approved", $approved, PDO::PARAM_INT);
    $stmt->bindParam(":description", $description, PDO::PARAM_STR);
    $stmt->bindParam(":justification", $justification, PDO::PARAM_STR);
    $stmt->bindParam(":associated_risks", $associated_risks, PDO::PARAM_STR);
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->execute();

    $id = $db->lastInsertId();

    // Close the database connection
    db_close($db);

    write_log($id, $_SESSION['uid'], _lang('ExceptionAuditLogCreate', array('exception_name' => $name, 'user' => $_SESSION['user']), false), 'exception');


    // If submitted files are existing, save files
    if(!empty($_FILES['file'])){
        $files = $_FILES['file'];
        list($status, $file_ids, $errors) = upload_compliance_files($id, "exceptions", $files);
        if($file_ids){
            $file_id = $file_ids[0];
        }
    }
    // Check if error was happen in uploading files
    if(!empty($errors))
    {
        // Delete added document if failed to upload a document file
        delete_exception($id);
        $errors = array_unique($errors);
        foreach ($errors as $error) {
            set_alert(true, "bad", $error);
        }
        return false;
    }elseif(!empty($file_id)){
        $stmt = $db->prepare("UPDATE `document_exceptions` SET file_id=:file_id WHERE value=:id");
        $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    return $id;
}

function update_exception($name, $status, $policy, $framework, $control, $owner, $additional_stakeholders, $creation_date, $review_frequency, $next_review_date, $approval_date, $approver, $approved, $description, $justification, $associated_risks, $id) {


    $original = getExceptionForChangeChecking($id);

    $db = db_open();

    // Create an exception
    $stmt = $db->prepare("
        UPDATE
            `document_exceptions` SET
                `name` = :name,
                `policy_document_id` = :policy_document_id,
                `framework_id` = :framework_id,
                `control_framework_id` = :control_framework_id,
                `owner` = :owner,
                `additional_stakeholders` = :additional_stakeholders,
                `creation_date` = :creation_date,
                `review_frequency` = :review_frequency,
                `next_review_date` = :next_review_date,
                `approval_date` = :approval_date,
                `approver` = :approver,
                `approved` = :approved,
                `description` = :description,
                `justification` = :justification,
                `associated_risks` = :associated_risks,
                `status` = :status
        WHERE `value` = :id;"
    );

    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":policy_document_id", $policy, PDO::PARAM_INT);
    $stmt->bindParam(":framework_id", $framework, PDO::PARAM_INT);
    $stmt->bindParam(":control_framework_id", $control, PDO::PARAM_INT);
    $stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
    $stmt->bindParam(":additional_stakeholders", $additional_stakeholders, PDO::PARAM_STR);
    $stmt->bindParam(":creation_date", $creation_date, PDO::PARAM_STR);
    $stmt->bindParam(":review_frequency", $review_frequency, PDO::PARAM_INT);
    $stmt->bindParam(":next_review_date", $next_review_date, PDO::PARAM_STR);
    $stmt->bindParam(":approval_date", $approval_date, PDO::PARAM_STR);
    $stmt->bindParam(":approver", $approver, PDO::PARAM_INT);
    $stmt->bindParam(":approved", $approved, PDO::PARAM_INT);
    $stmt->bindParam(":description", $description, PDO::PARAM_STR);
    $stmt->bindParam(":justification", $justification, PDO::PARAM_STR);
    $stmt->bindParam(":associated_risks", $associated_risks, PDO::PARAM_STR);
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    $updated = getExceptionForChangeChecking($id);

    $changes = getChangesInException($original, $updated);

    if (!empty($changes)) {
        write_log($id, $_SESSION['uid'], _lang('ExceptionAuditLogUpdate', array('exception_name' => $name, 'user' => $_SESSION['user'], 'changes' => implode(', ', $changes)), false), 'exception');
    }

    // If submitted files are existing, save files
    if(!empty($_FILES['file'])){
        $exception = get_exception($id);
        $version = $exception['file_version'] + 1;
        $files = $_FILES['file'];
        list($status, $file_ids, $errors) = upload_compliance_files($id, "exceptions", $files, $version);
        if($file_ids){
            $file_id = $file_ids[0];
        }
    }

    // Check if error was happen in uploading files
    if(!empty($errors))
    {
        $errors = array_unique($errors);
        foreach ($errors as $error) {
            set_alert(true, "bad", $error);
        }
        return false;
    }elseif(!empty($file_id)){
        $stmt = $db->prepare("UPDATE `document_exceptions` SET file_id=:file_id WHERE value=:id");
        $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
    }
    return true;
}

function getExceptionForChangeChecking($id) {
    $db = db_open();

    $sql = "
        SELECT
            (CASE
                WHEN de.policy_document_id > 0 THEN (SELECT p.document_name FROM documents p WHERE de.policy_document_id = p.id)
                WHEN de.control_framework_id > 0 THEN (SELECT c.short_name FROM framework_controls c WHERE de.control_framework_id = c.id)
            END)  AS parent_name,
            de.name,
            des.name AS status,
            GROUP_CONCAT(r.subject SEPARATOR ', ') AS associated_risks,
            o.name AS owner,
            de.additional_stakeholders,
            de.creation_date,
            de.review_frequency,
            de.next_review_date,
            de.approval_date,
            a.name AS approver,
            de.description,
            de.justification
        FROM
            document_exceptions de
            LEFT JOIN user o ON o.value = de.owner
            LEFT JOIN user a ON a.value = de.approver
            LEFT JOIN document_exceptions_status des ON des.value = de.status
            LEFT JOIN risks r ON FIND_IN_SET(r.id, de.associated_risks) > 0
        WHERE
            de.value=:id
        GROUP BY
            de.value
    ;";

    // Query the database
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $exception = $stmt->fetch(PDO::FETCH_ASSOC);

    $exception['additional_stakeholders'] = get_stakeholder_names($exception['additional_stakeholders'], 999);
    $exception['creation_date'] = format_date($exception['creation_date']);
    $exception['next_review_date'] = format_date($exception['next_review_date']);
    $exception['approval_date'] = format_date($exception['approval_date']);

    foreach($exception as $key => $value) {
        if (strlen($value ?? '') == 0)
            $exception[$key] = "";
    }

    return $exception;

    // Close the database connection
    db_close($db);
}

function getChangesInException($original, $updated) {
    $changes = [];
    foreach($original as $key => $value) {
        if ($value !== $updated[$key]) {
            $changes[] = _lang('ExceptionAuditLogUpdateChange', array('key' => $key, 'value' => $value, 'new_value' => $updated[$key]), false);
        }
    }
    return $changes;
}

function approve_exception($id) {

    $db = db_open();

    $stmt = $db->prepare("select name, value, next_review_date, review_frequency from `document_exceptions` where `value`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $approved_exception = $stmt->fetch();

    $approver = (int)$_SESSION['uid'];

    // Calculate next review date: today's date + review_frequency
    $today = time();
    $next_review_date = strtotime("+{$approved_exception['review_frequency']} day", $today);
    $next_review_date = date("Y-m-d", $next_review_date);
    
    // approve the exception
    $stmt = $db->prepare("UPDATE `document_exceptions` SET `approved`=1, `approval_date`=CURDATE(), `approver`=:approver, `next_review_date`=:next_review_date where `value`=:id;");
    $stmt->bindParam(":approver", $approver, PDO::PARAM_INT);
    $stmt->bindParam(":next_review_date", $next_review_date, PDO::PARAM_STR);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    write_log($approved_exception['value'], $_SESSION['uid'], _lang('ExceptionAuditLogApprove', array('exception_name' => $approved_exception['name'], 'user' => $_SESSION['user'])), 'exception');
}

function unapprove_exception($id) {

    $db = db_open(); 
    
    $stmt = $db->prepare("select name, value from `document_exceptions` where `value`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $unapproved_exception = $stmt->fetch();

    // unapprove the exception
    $stmt = $db->prepare("UPDATE `document_exceptions` SET `approved`=0, `approver` = 0, `approval_date`='' where `value`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    write_log($unapproved_exception['value'], $_SESSION['uid'], _lang('ExceptionAuditLogUnapprove', array('exception_name' => $unapproved_exception['name'], 'user' => $_SESSION['user'])), 'exception');
}

function delete_exception($id) {

    $db = db_open();

    $stmt = $db->prepare("select name, value from `document_exceptions` where `value`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $deleted_exception = $stmt->fetch();

    $stmt = $db->prepare("DELETE FROM compliance_files WHERE ref_id=:document_id AND ref_type='exceptions'; ");
    $stmt->bindParam(":document_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the exception
    $stmt = $db->prepare("DELETE from `document_exceptions` where `value`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    write_log($deleted_exception['value'], $_SESSION['uid'], _lang('ExceptionAuditLogDelete', array('exception_name' => $deleted_exception['name'], 'user' => $_SESSION['user'])), 'exception');
}

function batch_delete_exception($id, $type, $approved) {

    $db = db_open();

    $where_clause = "`approved` = :approved and `" . ($type == 'policy' ? 'policy_document_id' : 'control_framework_id') . "`=:id";

    // get the ids for audit logs
    $stmt = $db->prepare("select name, value from `document_exceptions` where {$where_clause};");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":approved", $approved, PDO::PARAM_INT);
    $stmt->execute();

    $deleted_exceptions = $stmt->fetchAll();

    // Delete the exceptions
    $stmt = $db->prepare("DELETE from `document_exceptions` where {$where_clause};");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":approved", $approved, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    $user = $_SESSION['user'];
    foreach($deleted_exceptions as $deleted_exception) {
        write_log($deleted_exception['value'], $_SESSION['uid'], _lang('ExceptionAuditLogDelete', array('exception_name' => $deleted_exception['name'], 'user' => $user)), 'exception');
    }
}

function get_exceptions_audit_log($days){

    $db = db_open();

    $stmt = $db->prepare("SELECT timestamp, message FROM audit_log WHERE (`timestamp` > CURDATE()-INTERVAL :days DAY) AND log_type='exception' ORDER BY timestamp DESC");
    $stmt->bindParam(":days", $days, PDO::PARAM_INT);

    $stmt->execute();

    $logs = $stmt->fetchAll();

    db_close($db);

    return $logs;
}

/***************************************
 * FUNCTION: SAVE CONTROL TO FRAMEWORK *
 ***************************************/
function save_control_to_frameworks($control_id, $map_frameworks)
{
    // Open the database connection
    $db = db_open();

    // Delete all current control framework relations
    $stmt = $db->prepare("DELETE FROM `framework_control_mappings` WHERE control_id=:control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    foreach($map_frameworks as $row){
        $framework_id = $row[0];
        $reference_name = $row[1];
        if(!get_exist_mapping_control_framework($control_id, $framework_id)){
            $stmt = $db->prepare("INSERT INTO `framework_control_mappings`(control_id, framework, reference_name) VALUES (:control_id, :framework_id, :reference_name)");
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
            $stmt->bindParam(":reference_name", $reference_name, PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    // Close the database connection
    db_close($db);  
}
/*********************************************
 * FUNCTION: SAVE CONTROL TO FRAMEWORK BY ID *
 *********************************************/
function save_control_to_framework_by_ids($control_id, $framework_ids)
{
    // Open the database connection
    $db = db_open();

    // Delete all current control framework relations
    $stmt = $db->prepare("DELETE FROM `framework_control_mappings` WHERE control_id=:control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    if($framework_ids)
    {
        // If framework_ids is not array, make it array value
        if(!is_array($framework_ids))
        {
            $framework_ids = explode(",", $framework_ids);
        }
        $control = get_framework_control($control_id);
        $reference_name = isset($control['control_number'])?$control['control_number']:"";

        $inserted = false;
        $insert_query = "INSERT INTO `framework_control_mappings` (control_id, framework, reference_name) VALUES ";
        foreach($framework_ids as $framework_id)
        {
            $framework_id = (int)$framework_id;
            if($framework_id && !get_exist_mapping_control_framework($control_id, $framework_id))
            {
                $inserted = true;
                $insert_query .= "(:control_id, {$framework_id}, :reference_name),";
                write_debug_log("Adding SimpleRisk control id \"" . $control_id . "\" to framework id \"" . $framework_id . "\".");
            }
        }
        $insert_query = trim($insert_query, ",");

        if($inserted)
        {
            $stmt = $db->prepare($insert_query);
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->bindParam(":reference_name", $reference_name, PDO::PARAM_STR);
            $stmt->execute();
        }

    }

    // Close the database connection
    db_close($db);  
}

function add_control_to_framework($control_id, $framework_id, $reference_name=null)
{
    if($framework_id > 0 && $control_id > 0)
    {
        // Open the database connection
        $db = db_open();

        // Delete all current control framework relations
        $stmt = $db->prepare("DELETE FROM `framework_control_mappings` WHERE control_id=:control_id AND framework=:framework_id AND reference_name=:reference_name;");
        $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
        $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
        $stmt->bindParam(":reference_name", $reference_name, PDO::PARAM_STR);
        $stmt->execute();
        
        $control = get_framework_control($control_id);

        // If there wasn't a reference name
        if ($reference_name === null)
        {
            // Set the control number
            $control_number = isset($control['control_number'])?$control['control_number']:"";
        }
        else $control_number = $reference_name;
        if(!get_exist_mapping_control_framework($control_id, $framework_id)){
            $stmt = $db->prepare("INSERT INTO `framework_control_mappings`(control_id, framework, reference_name) VALUES (:control_id, :framework_id, :control_number); ");
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
            $stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR);
            $stmt->execute();
        }

        write_debug_log("Adding SimpleRisk control id \"" . $control_id . "\" to framework id \"" . $framework_id . "\".");

        // Close the database connection
        db_close($db);  
    }
}

/********************************************
 * FUNCTION: REMOVE FRAMEWORK FROM CONTROLS *
 ********************************************/
/*function remove_framework_from_controls($framework_id)
{
    // Open the database connection
    $db = db_open();

    write_debug_log("Removing SimpleRisk framework id \"" . $framework_id . "\" from existing controls.");

    // Remove the framework_id value from the control
    $stmt = $db->prepare("
        UPDATE
          framework_controls
        SET
          framework_ids = TRIM(
            BOTH ','
            FROM
              REPLACE(
                REPLACE(
                  CONCAT(',', REPLACE(framework_ids, ',', ',,'), ','),
                          CONCAT(',', :framework_id, ','),
                  ''
                ),
                ',,',
                ','
              )
          )
        WHERE
          FIND_IN_SET(:framework_id, framework_ids)
    ");
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();

        // Close the database connection
        db_close($db);
}*/
function remove_framework_from_controls($framework_id)
{
    // Open the database connection
    $db = db_open();

    $framework_id = (int)$framework_id;
    $stmt = $db->prepare("DELETE FROM `framework_control_mappings` WHERE framework=:framework_id;");
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    write_debug_log("Removing SimpleRisk framework id \"" . $framework_id . "\" from existing controls.");

    // Close the database connection
    db_close($db);
}
/********************************************
 * FUNCTION: GET FRAMEWORKS BY IDs *
 ********************************************/
function get_frameworks_by_ids($framework_ids)
{
    // Open the database connection
    $db = db_open();
    // Query the database
    $stmt = $db->prepare("SELECT * FROM `frameworks` WHERE FIND_IN_SET(`value`,:framework_ids)");
    $stmt->bindParam(":framework_ids", $framework_ids, PDO::PARAM_STR);

    $stmt->execute();
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    foreach($frameworks as &$framework){
        // Try to decrypt the framework name
        $framework['name'] = try_decrypt($framework['name']);
        
        // Try to decrypt the framework description
        $framework['description'] = try_decrypt($framework['description']);
    }
    return $frameworks;
}

/********************************************
 * FUNCTION: GET MAPPING CONTROL FRAMEWORKS *
 ********************************************/
function get_mapping_control_frameworks($control_id) {

    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT 
            t1.*,
            t2.name framework_name, 
            t2.description framework_description 
        FROM 
            `framework_control_mappings` t1
        LEFT JOIN `frameworks` t2 ON t1.framework = t2.value
        WHERE t1.control_id = :control_id 
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // decrypt data
    foreach ($frameworks as &$framework) {

        // Try to decrypt the framework name
        $framework['framework_name'] = try_decrypt($framework['framework_name']);
        
        // Try to decrypt the framework description
        $framework['framework_description'] = try_decrypt($framework['framework_description']);

    }

    // Close the database connection
    db_close($db);

    return $frameworks;

}
/*************************************************
 * FUNCTION: GET EXIST MAPPING CONTROL FRAMEWORK *
 *************************************************/
function get_exist_mapping_control_framework($control_id, $framework_id)
{
    // Open the database connection
    $db = db_open();
    $sql = "SELECT * FROM `framework_control_mappings`  WHERE control_id = :control_id AND framework=:framework_id;";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);
    return $mappings;
}

/******************************
 * FUNCTION: GET CONTROL GAPS *
 ******************************/
function get_control_gaps($framework_id = null, $maturity = "all_maturity", $order_field=false, $order_dir=false)
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT m.reference_name as control_number, t1.short_name, t2.name control_class_name, t3.name control_phase_name, t5.name family_short_name, t7.name control_maturity_name, t8.name desired_maturity_name, t1.control_maturity, t1.desired_maturity
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `control_phase` t3 on t1.control_phase=t3.value
            LEFT JOIN `family` t5 on t1.family=t5.value
            LEFT JOIN `control_maturity` t7 on t1.control_maturity=t7.value
            LEFT JOIN `control_maturity` t8 on t1.desired_maturity=t8.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
    ";

    // Change the query based on the requested maturity
    switch($maturity)
    {
        case "below_maturity":
            $sql .= " WHERE t1.deleted=0 AND t1.control_maturity < t1.desired_maturity AND m.framework=:framework_id";
            break;
        case "at_maturity":
            $sql .= " WHERE t1.deleted=0 AND t1.control_maturity = t1.desired_maturity AND m.framework=:framework_id";
            break;
        case "above_maturity":
            $sql .= " WHERE t1.deleted=0 AND t1.control_maturity > t1.desired_maturity AND m.framework=:framework_id";
            break;
        default:
            $sql .= " WHERE t1.deleted=0 AND m.framework=:framework_id";
            break;
    }

    switch($order_field)
    {
        case "control_number";
            $sql .= " ORDER BY control_number {$order_dir} ";
        break;
        case "associated_frameworks";
            // If encryption extra is disabled, sort by query
            if(!encryption_extra())
            {
                $sql .= " ORDER BY framework_names {$order_dir} ";
            }
        break;
        case "control_family";
            $sql .= " ORDER BY t5.name {$order_dir} ";
        break;
        case "control_phase";
            $sql .= " ORDER BY t3.name {$order_dir} ";
        break;
        case "control_current_maturity";
            $sql .= " ORDER BY t7.name {$order_dir} ";
        break;
        case "control_desired_maturity";
            $sql .= " ORDER BY t8.name {$order_dir} ";
        break;
    }
    $sql .= ";";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();

    $control_gaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // closed the database connection
    db_close($db);

    return $control_gaps;
}

/****************************************
 * FUNCTION: DISPLAY ADD FRAMEWORK FORM *
 ****************************************/
function display_add_framework() {

    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra()) {

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("framework");
        $inactive_fields = get_inactive_fields("framework");

        display_detail_framework_fields_add($active_fields);
        display_detail_framework_fields_add($inactive_fields);

    // If the customization extra is disabled, shows fields by default fields
    } else {

        display_framework_name_edit();

        display_framework_parent_edit();

        display_framework_description_edit();

    }
}

/****************************************************
* FUNCTION: DISPLAY DETAIL FRAMEWORK FIELDS FOR ADD *
*****************************************************/
function display_detail_framework_fields_add($fields) {

    foreach($fields as $field) {

        if($field['is_basic'] == 1) {

            if($field['active'] == 0) {

                $display = false;

            } else {

                $display = true;

            }
            
            switch($field['name']) {
                case 'FrameworkName':
                    display_framework_name_edit($display);
                    break;
                case 'ParentFramework':
                    display_framework_parent_edit($display);
                    break;
                case 'FrameworkDescription':
                    display_framework_description_edit($display);
                    break;
            }

        } else {

            if($field['active'] == 0) {
                continue;
            }
            
            // If customization extra is enabled
            if(customization_extra()) {

                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                display_custom_field_edit($field, [], "label");
                
            }
        }
    }
}

/***********************************
* FUNCTION: DISPLAY FRAMEWORK NAME *
************************************/
function display_framework_name_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='framework_name'>{$escaper->escapeHtml($lang['FrameworkName'])}<span class='required'>*</span> : </label>
            <input type='text' required name='framework_name' autocomplete='off' maxlength='100' class='form-control' title='{$escaper->escapeHtml($lang['FrameworkName'])}'/>
        </div>
    ";

}

/*************************************
* FUNCTION: DISPLAY FRAMEWORK PARENT *
**************************************/
function display_framework_parent_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='parent'>{$escaper->escapeHtml($lang['ParentFramework'])} : </label>
            <div class='parent_frameworks_container w-100'></div>
        </div>
    ";

}

/*************************************
* FUNCTION: DISPLAY FRAMEWORK PARENT *
**************************************/
function display_framework_description_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group row'" . ($display ? "" : " style='display: none;'") . ">
            <label for='framework_description'>{$escaper->escapeHtml($lang['FrameworkDescription'])} : </label>
            <div class='w-100'>
                <textarea name='framework_description' value='' class='form-control' rows='6' style='width:100%;' title='{$escaper->escapeHtml($lang['FrameworkDescription'])}'></textarea>
            </div>
        </div>
    ";

}

/**************************************
 * FUNCTION: DISPLAY ADD CONTROL FORM *
 **************************************/
function display_add_control() {

    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra()) {

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("control", "", 1);
        $inactive_fields = get_inactive_fields("control", "", 1);

        display_detail_control_fields_add($active_fields);
        display_detail_control_fields_add($inactive_fields);

    // If the customization extra is disabled, shows fields by default fields
    } else {

        display_control_name_edit();

        display_control_longname_edit();

        display_control_description_edit();

        display_supplemental_guidance_edit();

        display_control_owner_edit();

        display_mapping_framework_edit();

        display_mapping_asset_edit();

        display_control_class_edit();

        display_control_phase_edit();

        display_control_number_edit2();

        display_current_maturity_edit();

        display_desired_maturity_edit();

        display_control_priority_edit();

        display_control_family_edit();

        display_control_type_edit();

        display_control_status_edit();

        display_control_mitigation_percent_edit();

    }
}

/**************************************************
* FUNCTION: DISPLAY DETAIL CONTROL FIELDS FOR ADD *
***************************************************/
function display_detail_control_fields_add($fields) {

    foreach ($fields as $field) {

        if ($field['is_basic'] == 1) {
            
            if ($field['active'] == 0) {
                $display = false;
            } else {
                $display = true;
            }
            
            switch ($field['name']) {
                case 'ControlShortName':
                    display_control_name_edit($display);
                    break;
                case 'ControlLongName':
                    display_control_longname_edit($display);
                    break;
                case 'ControlDescription':
                    display_control_description_edit($display);
                    break;
                case 'SupplementalGuidance':
                    display_supplemental_guidance_edit($display);
                    break;
                case 'ControlOwner':
                    display_control_owner_edit($display);
                    break;
                case 'MappedControlFrameworks':
                    display_mapping_framework_edit($display);
                    break;
                case 'MappedAssets':
                    display_mapping_asset_edit($display);
                    break;
                case 'ControlClass':
                    display_control_class_edit($display);
                    break;
                case 'ControlPhase':
                    display_control_phase_edit($display);
                    break;
                case 'ControlNumber':
                    display_control_number_edit2($display);
                    break;
                case 'CurrentControlMaturity':
                    display_current_maturity_edit($display);
                    break;
                case 'DesiredControlMaturity':
                    display_desired_maturity_edit($display);
                    break;
                case 'ControlPriority':
                    display_control_priority_edit($display);
                    break;
                case 'ControlFamily':
                    display_control_family_edit($display);
                    break;
                case 'ControlType':
                    display_control_type_edit($display);
                    break;
                case 'ControlStatus':
                    display_control_status_edit($display);
                    break;
                case 'MitigationPercent':
                    display_control_mitigation_percent_edit($display);
                    break;
            }

        } else {

            if ($field['active'] == 0) {
                continue;
            }
            
            // If customization extra is enabled
            if(customization_extra()) {

                // Include the extra
                require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                display_custom_field_edit($field, [], "label");

            }
        }
    }
}

/*********************************
* FUNCTION: DISPLAY CONTROL NAME *
**********************************/
function display_control_name_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='short_name'>{$escaper->escapeHtml($lang['ControlShortName'])}<span class='required'>*</span> : </label>
            <input type='text' name='short_name' value='' class='form-control' maxlength='100' required title='{$escaper->escapeHtml($lang['ControlShortName'])}'>
        </div>
    ";

}

/**************************************
* FUNCTION: DISPLAY CONTROL LONG NAME *
***************************************/
function display_control_longname_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='long_name'>{$escaper->escapeHtml($lang['ControlLongName'])} : </label>
            <input type='text' name='long_name' value='' class='form-control' maxlength='65500' title='{$escaper->escapeHtml($lang['ControlLongName'])}'>
        </div>
    ";

}

/****************************************
* FUNCTION: DISPLAY CONTROL DESCRIPTION *
*****************************************/
function display_control_description_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='description'>{$escaper->escapeHtml($lang['ControlDescription'])} : </label>
            <textarea name='description' value='' class='form-control' rows='6' style='width:100%;' maxlength='65500' title='{$escaper->escapeHtml($lang['ControlDescription'])}'></textarea>
        </div>
    ";

}

/******************************************
* FUNCTION: DISPLAY SUPPLEMENTAL GUIDANCE *
*******************************************/
function display_supplemental_guidance_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='supplemental_guidance'>{$escaper->escapeHtml($lang['SupplementalGuidance'])} : </label>
            <textarea name='supplemental_guidance' value='' class='form-control' rows='6' style='width:100%;' title='{$escaper->escapeHtml($lang['SupplementalGuidance'])}'></textarea>
        </div>
    ";

}

/**********************************
* FUNCTION: DISPLAY CONTROL OWNER *
***********************************/
function display_control_owner_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='enabled_users'>{$escaper->escapeHtml($lang['ControlOwner'])} : </label>" . 
            create_dropdown("enabled_users", NULL, "control_owner", true, false, true, "title='{$escaper->escapeHtml($lang['ControlOwner'])}'", $escaper->escapeHtml($lang['Unassigned'])) . "
        </div>
    ";

}

/**********************************************
* FUNCTION: DISPLAY CONTROL MAPPING FRAMEWORK *
***********************************************/
function display_mapping_framework_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <div class='row align-items-center'>
                <label class='col-10 col-form-label' for=''>{$escaper->escapeHtml($lang['MappedControlFrameworks'])} : </label>
                <div class='col-2 text-end col-form-label'>
                    <a href='javascript:void(0);' class='btn btn-primary btn-sm control-block--add-mapping'>{$escaper->escapeHtml($lang['AddMapping'])}</a>
                </div>
            </div>
            <div class='bg-light border p-3'>
                <table width='100%' class='table table-bordered mapping_framework_table mb-0'>
                    <thead>
                        <tr>
                            <th width='60%'>{$escaper->escapeHtml($lang['Framework'])}<span class='mapping-framework-required-mark required d-none'>*</span></th>
                            <th width='35%'>{$escaper->escapeHtml($lang['Control'])}<span class='mapping-framework-required-mark required d-none'>*</span></th>
                            <th>{$escaper->escapeHtml($lang['Actions'])}</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    ";

}

/******************************************
* FUNCTION: DISPLAY CONTROL MAPPING ASEET *
*******************************************/
function display_mapping_asset_edit($display = true) {
    
    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <div class='row text-align-center'>
                <label class='col-10 col-form-label' for=''>{$escaper->escapeHtml($lang['MappedAssets'])} : </label>
                <div class='col-2 text-end col-form-label'>
                    <a href='javascript:void(0);' class='btn btn-primary btn-sm control-block--add-asset'>{$escaper->escapeHtml($lang['AddMapping'])}</a>
                </div>
            </div>
            <div class='bg-light border p-3'>
                <table width='100%' class='table table-bordered mapping_asset_table mb-0'>
                    <thead>
                        <tr>
                            <th width='25%'>{$escaper->escapeHtml($lang['CurrentMaturity'])}<span class='mapping-asset-required-mark required d-none'>*</span></th>
                            <th width='70%'>{$escaper->escapeHtml($lang['Asset'])}<span class='mapping-asset-required-mark required d-none'>*</span></th>
                            <th>{$escaper->escapeHtml($lang['Actions'])}</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    ";

}

/**********************************
* FUNCTION: DISPLAY CONTROL CLASS *
***********************************/
function display_control_class_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='control_class'>{$escaper->escapeHtml($lang['ControlClass'])} : </label>" . 
            create_dropdown("control_class", NULL, "control_class", true, false, true, "title='{$escaper->escapeHtml($lang['ControlClass'])}'", $escaper->escapeHtml($lang['Unassigned'])) . "
        </div>
    ";

}

/**********************************
* FUNCTION: DISPLAY CONTROL PHASE *
***********************************/
function display_control_phase_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='control_phase'>{$escaper->escapeHtml($lang['ControlPhase'])} : </label>" . 
            create_dropdown("control_phase", NULL, "control_phase", true, false, true, "title='{$escaper->escapeHtml($lang['ControlPhase'])}'", $escaper->escapeHtml($lang['Unassigned'])) . "
        </div>
    ";

}

/***********************************
* FUNCTION: DISPLAY CONTROL NUMBER *
************************************/
function display_control_number_edit2($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='control_number'>{$escaper->escapeHtml($lang['ControlNumber'])} : </label>
            <input type='text' name='control_number' value='' class='form-control' maxlength='100' title='{$escaper->escapeHtml($lang['ControlNumber'])}'>
        </div>
    ";

}

/*********************************************
* FUNCTION: DISPLAY CURRENT CONTROL MATURITY *
**********************************************/
function display_current_maturity_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='control_current_maturity'>{$escaper->escapeHtml($lang['CurrentControlMaturity'])} : </label>" . 
            create_dropdown("control_maturity", get_setting("default_current_maturity"), "control_current_maturity", true, false, true, "title='{$escaper->escapeHtml($lang['CurrentControlMaturity'])}'") . "
        </div>
    ";

}

/*********************************************
* FUNCTION: DISPLAY DESIRED CONTROL MATURITY *
**********************************************/
function display_desired_maturity_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='control_desired_maturity'>{$escaper->escapeHtml($lang['DesiredControlMaturity'])} : </label>" . 
            create_dropdown("control_maturity", get_setting("default_current_maturity"), "control_desired_maturity", true, false, true, "title='{$escaper->escapeHtml($lang['DesiredControlMaturity'])}'") . "
        </div>
    ";

}

/*************************************
* FUNCTION: DISPLAY CONTROL PRIORITY *
**************************************/
function display_control_priority_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='control_priority'>{$escaper->escapeHtml($lang['ControlPriority'])} : </label>" . 
            create_dropdown("control_priority", NULL, "control_priority", true, false, true, "title='{$escaper->escapeHtml($lang['ControlPriority'])}'", $escaper->escapeHtml($lang['Unassigned'])) . "
        </div>
    ";

}

/***********************************
* FUNCTION: DISPLAY CONTROL FAMILY *
************************************/
function display_control_family_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='family'>{$escaper->escapeHtml($lang['ControlFamily'])} : </label>" . 
            create_dropdown("family", NULL, "family", true, false, true, "title='{$escaper->escapeHtml($lang['ControlFamily'])}'", $escaper->escapeHtml($lang['Unassigned'])) . "
        </div>
    ";

}

/*********************************
* FUNCTION: DISPLAY CONTROL TYPE *
**********************************/
function display_control_type_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='control_type'>{$escaper->escapeHtml($lang['ControlType'])} : </label>
            <div class='w-100'>" . 
                create_multiple_dropdown("control_type", array(1), returnHtml: true, customHtml: "title='{$escaper->escapeHtml($lang['ControlType'])}'") . "
            </div>
        </div>
    ";

}

/***********************************
* FUNCTION: DISPLAY CONTROL STATUS *
************************************/
function display_control_status_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='control_status'>{$escaper->escapeHtml($lang['ControlStatus'])} : </label>
            <select name='control_status' class='form-select' title='{$escaper->escapeHtml($lang['ControlStatus'])}'>
                <option value='1'>{$escaper->escapeHtml($lang['Pass'])}</option>
                <option value='0'>{$escaper->escapeHtml($lang['Fail'])}</option>
            </select>
        </div>
    ";

}

/***********************************************
* FUNCTION: DISPLAY CONTROL MITIGATION PERCENT *
************************************************/
function display_control_mitigation_percent_edit($display = true) {

    global $lang, $escaper;

    echo "
        <div class='form-group'" . ($display ? "" : " style='display: none;'") . ">
            <label for='mitigation_percent'>{$escaper->escapeHtml($lang['MitigationPercent'])} : </label>
            <input type='number' min='0' max='100' name='mitigation_percent' value='0' class='form-control' title='{$escaper->escapeHtml($lang['MitigationPercent'])}'>
        </div>
    ";

}

/***************************************************
* FUNCTION: DISPLAY DETAIL CONTROL FIELDS FOR VIEW *
****************************************************/
function display_detail_control_fields_view($panel_name, $fields, $control) {

    global $lang, $escaper;

    $html = "";

    foreach ($fields as $field) {

        // Check if this field is main field and details in left panel
        if ($field['panel_name'] == $panel_name && $field['tab_index'] == 2) {

            if ($field['is_basic'] == 1) {

                if ($field['active'] == 0) {
                    continue;
                }

                $field['name'] = str_replace("_view", "", $field['name'], $field['name']);

                switch ($field['name']) {
                    case 'ControlShortName':
                        $html .= display_control_name_view($control['short_name'], $panel_name);
                        break;
                    case 'ControlLongName':
                        $html .= display_control_longname_view($control['long_name'], $panel_name);
                        break;
                    case 'ControlDescription':
                        $html .= display_control_description_view($control['description'], $panel_name);
                        break;
                    case 'SupplementalGuidance':
                        $html .= display_supplemental_guidance_view($control['supplemental_guidance'], $panel_name);
                        break;
                    case 'ControlOwner':
                        $html .= display_control_owner_view($control['control_owner_name'], $panel_name);
                        break;
                    case 'MappedControlFrameworks':
                        $html .= display_mapping_framework_view($control['id'], $panel_name);
                        break;
                    case 'MappedAssets':
                        $html .= display_mapping_asset_view($control['id'], $panel_name);
                        break;
                    case 'ControlClass':
                        $html .= display_control_class_view($control['control_class_name'], $panel_name);
                        break;
                    case 'ControlPhase':
                        $html .= display_control_phase_view($control['control_phase_name'], $panel_name);
                        break;
                    case 'ControlNumber':
                        $html .= display_control_number_view2($control['control_number'], $panel_name);
                        break;
                    case 'CurrentControlMaturity':
                        $html .= display_current_maturity_view($control['control_maturity_name'], $panel_name);
                        break;
                    case 'DesiredControlMaturity':
                        $html .= display_desired_maturity_view($control['desired_maturity_name'], $panel_name);
                        break;
                    case 'ControlPriority':
                        $html .= display_control_priority_view($control['control_priority_name'], $panel_name);
                        break;
                    case 'ControlFamily':
                        $html .= display_control_family_view($control['family_short_name'], $panel_name);
                        break;
                    case 'ControlType':
                        $html .= display_control_type_view($control['control_type_ids'], $panel_name);
                        break;
                    case 'ControlStatus':
                        $html .= display_control_status_view($control['control_status'], $panel_name);
                        break;
                    case 'MitigationPercent':
                        $html .= display_control_mitigation_percent_view($control['mitigation_percent'], $panel_name);
                        break;
                }

            } else {

                if ($field['active'] == 0) {
                    continue;
                }
                
                // If customization extra is enabled
                if (customization_extra()) {

                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                    $custom_value = get_plan_custom_field_name_by_row_id($field, $control["id"], "control");

                    if ($panel_name=="top" || $panel_name=="bottom") {
                        $span1 = "col-2";
                        $span2 = "col-10";
                    } else {
                        $span1 = "col-4";
                        $span2 = "col-8";
                    }

                    $html .= "
                        <div class='row mb-2 {$panel_name}'>
                            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($field['name'])} : </label></div>
                            <div class='{$span2}'>{$escaper->escapeHtml($custom_value)}</div>
                        </div>
                    ";

                }
            }
        }
    }

    return $html;

}

/**************************************
* FUNCTION: DISPLAY CONTROL NAME VIEW *
***************************************/
function display_control_name_view($short_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlShortName'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($short_name)}</div>
        </div>
    ";

    return $html;

}

/**************************************
* FUNCTION: DISPLAY CONTROL LONG NAME *
***************************************/
function display_control_longname_view($long_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlLongName'])} : </label></div>
            <div class='{$span2} control-longname'>{$escaper->escapeHtml($long_name)}</div>
        </div>
    ";

    return $html;

}

/****************************************
* FUNCTION: DISPLAY CONTROL DESCRIPTION *
*****************************************/
function display_control_description_view($description, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['Description'])} : </label></div>
            <div class='{$span2} control-description'>{$escaper->purifyHtml($description)}</div>
        </div>
    ";

    return $html;

}

/******************************************
* FUNCTION: DISPLAY SUPPLEMENTAL GUIDANCE *
*******************************************/
function display_supplemental_guidance_view($supplemental_guidance, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['SupplementalGuidance'])} : </label></div>
            <div class='{$span2} control-supplemental-guidance'>{$escaper->purifyHtml($supplemental_guidance)}</div>
        </div>
    ";
    
    return $html;

}

/**********************************
* FUNCTION: DISPLAY CONTROL OWNER *
***********************************/
function display_control_owner_view($control_owner_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlOwner'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($control_owner_name)}</div>
        </div>
    ";

    return $html;

}

/**********************************************
* FUNCTION: DISPLAY CONTROL MAPPING FRAMEWORK *
***********************************************/
function display_mapping_framework_view($control_id, $panel_name="") {

    global $lang, $escaper;

    $mapped_frameworks = get_mapping_control_frameworks($control_id);

    $html = "
        <div class='mb-2'>
            <label>{$escaper->escapeHtml($lang['MappedControlFrameworks'])} : </label>
            <div class='bg-light border p-3'>
                <table width='100%' class='table table-bordered mb-0'>
                    <tr>
                        <th width='65%'>{$escaper->escapeHtml($lang['Framework'])}</th>
                        <th width='35%'>{$escaper->escapeHtml($lang['Control'])}</th>
                    </tr>
    ";

    foreach ($mapped_frameworks as $framework) {
        $html .= "
                    <tr>
                        <td>{$escaper->escapeHtml($framework['framework_name'])}</td>
                        <td>{$escaper->escapeHtml($framework['reference_name'])}</td>
                    </tr>
        ";
    }

    $html .= "
                </table>
            </div>
        </div>
    ";

    return $html;

}

/******************************************
* FUNCTION: DISPLAY CONTROL MAPPING ASSET *
*******************************************/
function display_mapping_asset_view($control_id, $panel_name="") {

    global $lang, $escaper;

    $mapped_assets = get_control_to_assets($control_id);

    $html = "
        <div class='mb-2'>
            <label>{$escaper->escapeHtml($lang['MappedAssets'])} : </label>
            <div class='bg-light border p-3'>
                <table width='100%' class='table table-bordered mb-0'>
                    <tr>
                        <th width='45%'>{$escaper->escapeHtml($lang['CurrentMaturity'])}</th>
                        <th width='55%'>{$escaper->escapeHtml($lang['Asset'])}</th>
                    </tr>
    ";

    foreach ($mapped_assets as $assets) {

        $asset_names = [];

        if ($assets['asset_name']) $asset_names[] = $escaper->escapeHtml($assets['asset_name']);
        if ($assets['asset_group_name']) $asset_names[] = "<b>" . $escaper->escapeHtml($assets['asset_group_name']) . "</b>";
        $html .= "
                    <tr>
                        <td>{$escaper->escapeHtml($assets['control_maturity_name'])}</td>
                        <td>" . (implode(",", $asset_names )) . "</td>
                    </tr>
        ";

    }

    $html .= "
                </table>
            </div>
        </div>
    ";

    return $html;

}

/**********************************
* FUNCTION: DISPLAY CONTROL CLASS *
***********************************/
function display_control_class_view($control_class_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlClass'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($control_class_name)}</div>
        </div>
    ";

    return $html;

}

/**********************************
* FUNCTION: DISPLAY CONTROL PHASE *
***********************************/
function display_control_phase_view($control_phase_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlPhase'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($control_phase_name)}</div>
        </div>
    ";
    
    return $html;

}

/***********************************
* FUNCTION: DISPLAY CONTROL NUMBER *
************************************/
function display_control_number_view2($control_number, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlNumber'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($control_number)}</div>
        </div>
    ";

    return $html;

}

/*********************************************
* FUNCTION: DISPLAY CURRENT CONTROL MATURITY *
**********************************************/
function display_current_maturity_view($control_maturity_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['CurrentControlMaturity'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($control_maturity_name)}</div>
        </div>
    ";

    return $html;

}

/*********************************************
* FUNCTION: DISPLAY DESIRED CONTROL MATURITY *
**********************************************/
function display_desired_maturity_view($desired_maturity_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['DesiredControlMaturity'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($desired_maturity_name)}</div>
        </div>
    ";

    return $html;

}

/*************************************
* FUNCTION: DISPLAY CONTROL PRIORITY *
**************************************/
function display_control_priority_view($control_priority_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlPriority'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($control_priority_name)}</div>
        </div>
    ";

    return $html;

}

/***********************************
* FUNCTION: DISPLAY CONTROL FAMILY *
************************************/
function display_control_family_view($family_short_name, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlFamily'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($family_short_name)}</div>
        </div>
    ";

    return $html;

}

/*********************************
* FUNCTION: DISPLAY CONTROL TYPE *
**********************************/
function display_control_type_view($control_type_ids, $panel_name="") {

    global $lang, $escaper;

    $control_types = get_names_by_multi_values("control_type", $control_type_ids);

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlType'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($control_types)}</div>
        </div>
    ";

    return $html;

}

/***********************************
* FUNCTION: DISPLAY CONTROL STATUS *
************************************/
function display_control_status_view($control_status, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $status_text = array("1" => $escaper->escapeHtml($lang["Pass"]), "0" => $escaper->escapeHtml($lang["Fail"]));
    
    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlStatus'])} : </label></div>
            <div class='{$span2}'>{$status_text[$control_status]}</div>
        </div>
    ";
    
    return $html;

}

/***********************************************
* FUNCTION: DISPLAY CONTROL MITIGATION PERCENT *
************************************************/
function display_control_mitigation_percent_view($mitigation_percent, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['MitigationPercent'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($mitigation_percent)}</div>
        </div>
    ";

    return $html;
    
}

/******************************
 * FUNCTION: FRAMEWORK EXISTS *
 ******************************/
function framework_exists($framework_name)
{
    // Open the database connection
    $db = db_open();

    // Get the list of existing frameworks in SimpleRisk
    $stmt = $db->prepare("SELECT `value`,`name` FROM `frameworks`;");
    $stmt->execute();
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // For each framework
    foreach($frameworks as $framework)
    {
        // If the framework name matches the one we were provided
        if (try_decrypt($framework['name']) === $framework_name)
        {
            // We found the framework so return the framework id
            return $framework['value'];
        }
    }

    // We never found the framework so return false
    return false;
}

/******************************************
 * FUNCTION: GET FRAMEWORKS BY CONTROL ID *
 ******************************************/
function get_frameworks_by_control_id($control_id)
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT framework FROM `framework_control_mappings` WHERE `control_id` = :control_id");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);

    $stmt->execute();

    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the array of frameworks for the control
    return $frameworks;
}

/**********************************************
 * FUNCTION: GET DOCUMENT TO CONTROL MAPPINGS *
 **********************************************/
function get_document_to_control_mappings($document_id, $refresh = false)
{
    // Get the document
    $document = get_document_by_id($document_id);

    // If the document doesn't exist, return false
    if (empty($document))
    {
        write_debug_log("Document ID $document_id not found. Exiting.");
        return false;
    }

    try
    {
        // Open the database connection
        $db = db_open();

        // Check if existing mappings exist
        $stmt = $db->prepare("SELECT * FROM `document_control_mappings` WHERE `document_id` = :document_id ORDER BY `score` DESC");
        $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch the results
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If there are no results or we want to refresh
        if (empty($results) || $refresh)
        {
            write_debug_log("Refreshing mappings for Document ID: {$document_id}");

            // Get document keywords
            $document_keywords = get_keywords_for_document($document_id);

            if (empty($document_keywords)) {
                write_debug_log("No keywords found for document ID: {$document_id}");
                return false;
            }

            $docKeywords = $document_keywords['data']['keywords'];

            // Get the list of control ids
            $stmt = $db->prepare("SELECT `id` FROM `framework_controls`;");
            $stmt->execute();
            $controlIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Initialize TF-IDF variables
            $tfidfMatrix = [];
            $documentFrequency = [];
            $numDocuments = 0;

            // First pass: collect document frequency data from all controls
            write_debug_log("Collecting document frequency data from all controls");
            $validControlIds = [];
            $controlKeywordsCollection = [];

            foreach ($controlIds as $control_id) {
                $control_keywords = get_keywords_for_control($control_id);

                if (empty($control_keywords) || empty($control_keywords['data']['keywords'])) {
                    write_debug_log("No keywords found for control ID: {$control_id}");
                    continue;
                }

                $controlKeywords = $control_keywords['data']['keywords'];
                $controlKeywordsCollection[$control_id] = $controlKeywords;
                $validControlIds[] = $control_id;

                // Count document frequency
                foreach (array_keys($controlKeywords) as $term) {
                    $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
                }

                $numDocuments++;
            }

            // Add document to the collection for IDF calculation
            foreach (array_keys($docKeywords) as $term) {
                $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
            }
            $numDocuments++; // Include the document itself

            write_debug_log("Found $numDocuments total documents (including controls) for IDF calculation");

            // Build document TF-IDF vector
            $docVector = [];
            foreach ($docKeywords as $term => $tf) {
                $idf = log($numDocuments / ($documentFrequency[$term] ?? 1));
                $docVector[$term] = $tf * $idf;
            }

            // Match against each control
            $keywordMatches = [];
            $tfIdfScores = [];
            $maxKeywordMatch = 0;

            foreach ($validControlIds as $control_id) {
                $controlKeywords = $controlKeywordsCollection[$control_id];

                // Calculate keyword match count
                $keyword_match = 0;
                foreach ($docKeywords as $keyword => $count) {
                    if (isset($controlKeywords[$keyword])) {
                        $keyword_match += min($count, $controlKeywords[$keyword]);
                    }
                }

                $keywordMatches[$control_id] = $keyword_match;
                if ($keyword_match > $maxKeywordMatch) {
                    $maxKeywordMatch = $keyword_match;
                }

                // Build control TF-IDF vector
                $controlVector = [];
                foreach ($controlKeywords as $term => $tf) {
                    $idf = log($numDocuments / ($documentFrequency[$term] ?? 1));
                    $controlVector[$term] = $tf * $idf;
                }

                // Calculate TF-IDF similarity
                $tfidf_similarity = cosineSimilarity($docVector, $controlVector);
                $tfIdfScores[$control_id] = $tfidf_similarity;

                write_debug_log("Control ID {$control_id}: Keyword match: {$keyword_match}, TF-IDF similarity: {$tfidf_similarity}");
            }

            // Update database with scores
            foreach ($validControlIds as $control_id) {
                // Get scores for this control
                $tfidf_similarity = $tfIdfScores[$control_id];
                $keyword_match = $keywordMatches[$control_id];

                // Normalize keyword match
                $normalized_keyword_score = $maxKeywordMatch > 0 ? $keyword_match / $maxKeywordMatch : 0;

                // Calculate final score as average of TF-IDF and normalized keyword match
                $final_score = ($tfidf_similarity + $normalized_keyword_score) / 2;

                $stmt = $db->prepare("
                    INSERT INTO `document_control_mappings`
                        (`document_id`, `control_id`, `score`, `tfidf_similarity`, `keyword_match`)
                    VALUES
                        (:document_id, :control_id, :score, :tfidf_similarity, :keyword_match)
                    ON DUPLICATE KEY UPDATE
                        score = :score, tfidf_similarity = :tfidf_similarity, 
                        keyword_match = :keyword_match, timestamp = NOW()");

                $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
                $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
                $stmt->bindParam(":score", $final_score, PDO::PARAM_STR);
                $stmt->bindParam(":tfidf_similarity", $tfidf_similarity, PDO::PARAM_STR);
                $stmt->bindParam(":keyword_match", $keyword_match, PDO::PARAM_INT);
                $stmt->execute();

                write_debug_log("Scoring Control ID $control_id: TF-IDF Similarity = $tfidf_similarity, Keyword Match = $keyword_match, Final Score = $final_score");
            }

            // Retrieve updated results
            $stmt = $db->prepare("SELECT * FROM `document_control_mappings` WHERE `document_id` = :document_id ORDER BY `score` DESC;");
            $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            write_debug_log("Cached mappings found for Document ID $document_id. Returning cached results.");
        }

        write_debug_log("Finished processing for Document ID $document_id. Returning " . count($results) . " mappings.");
        return $results;
    } catch (Exception $e) {
        write_debug_log("Error in get_document_to_control_mappings: " . $e->getMessage());
        return false;
    } finally {
        db_close($db);
    }
}

/**********************************************
 * FUNCTION: GET CONTROL TO DOCUMENT MAPPINGS *
 **********************************************/
function get_control_to_document_mappings($control_id, $refresh = false)
{
    // Get the control
    $control = get_framework_control($control_id);

    // If the control doesn't exist, return false
    if (empty($control))
    {
        write_debug_log("Control ID $control_id not found. Exiting.");
        return false;
    }
    // If the control exists
    else
    {
        write_debug_log("Starting get_control_to_document_mappings for Control ID: " . $control_id);

        try
        {
            // Open the database connection
            $db = db_open();

            // Query the database
            $stmt = $db->prepare("SELECT * FROM `document_control_mappings` WHERE `control_id` = :control_id ORDER BY `score` DESC");
            $stmt->bindParam(':control_id', $control_id, PDO::PARAM_INT);
            $stmt->execute();

            // Fetch the results
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If there are no results or we want to refresh
            if (empty($results) || $refresh)
            {
                // Get the keywords for the control
                $control_keywords_data = get_keywords_for_control($control_id);
                $controlKeywords = $control_keywords_data['data']['keywords'];
                $controlKeywordCount = $control_keywords_data['data']['keyword_count'];

                // Get the list of all governance documents
                $stmt = $db->prepare("SELECT `id`, `keywords`, `keyword_count` FROM `compliance_files`;");
                $stmt->execute();
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // If there are no files
                if (empty($files))
                {
                    // Set the results to an empty array
                    $results = [];
                }
                else
                {
                    // Initialize function values
                    $tfidfMatrix = [];
                    $documentFrequency = [];
                    $keywordMatches = [];
                    $maxKeywordMatch = 0;
                    $numDocuments = 0;

                    // Iterate through each file
                    foreach ($files as $file)
                    {
                        // Get the Document ID and keywords
                        $document_id = $file['id'];
                        $docKeywords = json_decode($file['keywords'], true) ?? [];

                        if (empty($docKeywords))
                        {
                            write_debug_log("Document ID $document_id has no keywords. Skipping.");
                            continue;
                        }

                        $keyword_match = 0;
                        foreach ($controlKeywords as $keyword => $count)
                        {
                            if (isset($docKeywords[$keyword]))
                            {
                                $keyword_match += min($count, $docKeywords[$keyword]);
                            }
                        }

                        $keywordMatches[$document_id] = $keyword_match;
                        if ($keyword_match > $maxKeywordMatch)
                        {
                            $maxKeywordMatch = $keyword_match;
                        }

                        foreach ($docKeywords as $term => $tf)
                        {
                            $tfidfMatrix[$document_id][$term] = $tf;
                            $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
                        }

                        $numDocuments++;
                    }

                    // Apply IDF to document vectors
                    foreach ($tfidfMatrix as $document_id => &$vector) {
                        foreach ($vector as $term => &$tf) {
                            $idf = log($numDocuments / ($documentFrequency[$term] ?? 1));
                            $tf *= $idf;
                        }
                    }

                    // Build control TF-IDF vector
                    $controlVector = [];
                    foreach ($controlKeywords as $term => $tf) {
                        $idf = log($numDocuments / ($documentFrequency[$term] ?? 1));
                        $controlVector[$term] = $tf * $idf;
                    }

                    // Compute similarity scores
                    $scores = [];
                    foreach ($tfidfMatrix as $document_id => $docVector) {
                        $tfidf_similarity = cosineSimilarity($docVector, $controlVector);
                        $keyword_match = $keywordMatches[$document_id];
                        $normalized_keyword_score = $maxKeywordMatch > 0 ? $keyword_match / $maxKeywordMatch : 0;
                        $final_score = ($tfidf_similarity + $normalized_keyword_score) / 2;

                        $scores[] = [
                            'document_id' => $document_id,
                            'control_id' => $control_id,
                            'tfidf_similarity' => $tfidf_similarity,
                            'keyword_match' => $keyword_match,
                            'score' => $final_score
                        ];

                        $stmt = $db->prepare("
                            INSERT INTO `document_control_mappings` 
                            (`document_id`, `control_id`, `score`, `tfidf_similarity`, `keyword_match`) 
                            VALUES (:document_id, :control_id, :score, :tfidf_similarity, :keyword_match) 
                            ON DUPLICATE KEY UPDATE 
                            score = :score, 
                            tfidf_similarity = :tfidf_similarity, 
                            keyword_match = :keyword_match, 
                            timestamp = NOW()");
                        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
                        $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
                        $stmt->bindParam(":score", $final_score, PDO::PARAM_STR);
                        $stmt->bindParam(":tfidf_similarity", $tfidf_similarity, PDO::PARAM_STR);
                        $stmt->bindParam(":keyword_match", $keyword_match, PDO::PARAM_INT);
                        $stmt->execute();

                        write_debug_log("Scoring Document ID $document_id: TF-IDF Similarity = $tfidf_similarity, Keyword Match = $keyword_match, Final Score = $final_score");
                    }

                    // Reload updated mappings
                    $stmt = $db->prepare("SELECT * FROM `document_control_mappings` WHERE `control_id` = :control_id ORDER BY `score` DESC;");
                    $stmt->bindParam(':control_id', $control_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                write_debug_log("Cached mappings found for Control ID $control_id. Returning cached results.");
            }

            db_close($db);
            write_debug_log("Finished processing for Control ID $control_id. Returning " . count($results) . " mappings.");
            return $results;
        } catch (Exception $e) {
            write_debug_log("Error in get_document_to_control_mappings: " . $e->getMessage());
            return false;
        } finally {
            db_close($db);
        }
    }
}

/***************************************
 * FUNCTION: GET KEYWORDS FOR DOCUMENT *
 ***************************************/
function get_keywords_for_document($document_id, $refresh = false)
{
    // Get the document
    $document = get_document_by_id($document_id);

    // If the document doesn't exist
    if (empty($document))
    {
        // Create a result
        $result = [
            'status_code' => 404,
            'status_message' => 'Document not found',
            'data' => []
        ];
    }
    // If the document exists but the user doesn't have access
    else if (!check_access_for_document($document_id))
    {
        // Create a result
        $result = [
            'status_code' => 403,
            'status_message' => 'FORBIDDEN: The user does not have the required permission to perform this action.',
            'data' => []
        ];
    }
    // If the document exists and we have access to it
    else
    {
        // Open the database connection
        $db = db_open();

        // If we want to refresh the keywords
        if ($refresh)
        {
            // Reset the keywords and keyword count for the control
            $stmt = $db->prepare("UPDATE compliance_files SET `keywords` = null, `keyword_count` = 0 WHERE BINARY unique_name=:unique_name");
            $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
            $stmt->execute();
        }

        // Get the file from the database
        $unique_name = $document['unique_name'];
        $stmt = $db->prepare("SELECT `content`, `keywords`, `keyword_count` FROM compliance_files WHERE BINARY unique_name=:unique_name");
        $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
        $stmt->execute();

        // Store the results in an array
        $file = $stmt->fetch();

        // If the file doesn't exist
        if (empty($file))
        {
            // Create a result
            $result = [
                'status_code' => 404,
                'status_message' => 'File not found',
                'data' => []
            ];
        }
        // If the file exists and already has keywords calculated for it
        else if (!empty($file['keywords']))
        {
            // Create a result
            $result = [
                'status_code' => 200,
                'status_message' => 'Keywords already exist.  Returning cached values.',
                'data' => [
                    'keywords' => json_decode($file['keywords'], true),
                    'keyword_count' => $file['keyword_count']
                ]
            ];
        }
        // If the file exists but does not have keywords calculated for it
        else
        {
            try
            {
                write_debug_log("Analyzing the contents of Document ID: " . $document_id);

                // Get the file content
                $content = $file['content'];

                // Convert the content to text
                $document_text = get_text_from_document_content($content);

                // Get the significant terms for the document
                write_debug_log("Calculating significant terms from the document.  This may take a while.");
                $keywords = extractSignificantTerms($document_text, 150);
                write_debug_log("Significant Terms: " . json_encode($keywords));

                // Get the keyword matches for the document
                $keyword_occurrences = countKeywordOccurrencesPerKeyword($document_text, $keywords);
                $keyword_occurrences_json = json_encode($keyword_occurrences);
                write_debug_log("Keyword matches for Document ID {$document_id}: " . $keyword_occurrences_json);

                // Get the keyword count for the document
                $keyword_count = array_sum($keyword_occurrences);
                write_debug_log("Keyword count for Document ID {$document_id}: " . $keyword_count);

                // Update the file with the keywords and keyword count
                $stmt = $db->prepare("UPDATE compliance_files SET keywords = :keywords, keyword_count = :keyword_count WHERE BINARY unique_name = :unique_name");
                $stmt->bindParam(":keywords", $keyword_occurrences_json, PDO::PARAM_STR);
                $stmt->bindParam(":keyword_count", $keyword_count, PDO::PARAM_INT);
                $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                $stmt->execute();

                // Create a result
                $result = [
                    'status_code' => 200,
                    'status_message' => 'New keywords created successfully.',
                    'data' => [
                        'keywords' => $keyword_occurrences,
                        'keyword_count' => $keyword_count
                    ]
                ];
            } catch (Exception $e)
            {
                write_debug_log("Error in get_keywords_for_document: " . $e->getMessage());

                // Create a result
                $result = [
                    'status_code' => 500,
                    'status_message' => 'Error processing document.',
                    'data' => []
                ];
            } finally {
                // Close the database connection
                db_close($db);
            }
        }

        // Close the database connection
        db_close($db);
    }

    // Return the result
    return $result;
}

/**************************************
 * FUNCTION: GET KEYWORDS FOR CONTROL *
 **************************************/
function get_keywords_for_control($control_id, $refresh = false)
{
    // Get the control
    $control = get_framework_control($control_id);

    // If the control doesn't exist
    if (empty($control))
    {
        // Create a result
        $result = [
            'status_code' => 404,
            'status_message' => 'Control not found',
            'data' => []
        ];
    }
    // If the control exists
    else
    {
        // Open the database connection
        $db = db_open();

        // If we want to refresh the keywords
        if ($refresh)
        {
            // Reset the keywords and keyword count for the control
            $stmt = $db->prepare("UPDATE framework_controls SET keywords = null, keyword_count = 0 WHERE id = :control_id");
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // If the control exists and already has keywords calculated for it
        if (!empty($control['keywords']))
        {
            // Create a result
            $result = [
                'status_code' => 200,
                'status_message' => 'Keywords already exist.  Returning cached values.',
                'data' => [
                    'keywords' => json_decode($control['keywords'], true),
                    'keyword_count' => $control['keyword_count']
                ]
            ];
        }
        // If the control exists but does not have keywords calculated for it
        else
        {
            try
            {
                write_debug_log("Analyzing the contents of Control ID: " . $control_id);

                // Get the control text and calculate the control term frequency
                $control_text = $control['short_name'] . ': ' . $control['description'];
                write_debug_log("Calculating significant terms from the control.  This may take a while.");
                $keywords = extractSignificantTerms($control_text, 150);
                write_debug_log("Significant Terms: " . json_encode($keywords));

                // Get the keyword matches for the control
                $keyword_occurrences = countKeywordOccurrencesPerKeyword($control_text, $keywords);
                $keyword_occurrences_json = json_encode($keyword_occurrences);
                write_debug_log("Keyword matches for Control ID {$control_id}: " . $keyword_occurrences_json);

                // Get the keyword count for the control
                $keyword_count = array_sum($keyword_occurrences);
                write_debug_log("Keyword count for Control ID {$control_id}: " . $keyword_count);

                // Update the control with the keywords and keyword count
                $stmt = $db->prepare("UPDATE framework_controls SET keywords = :keywords, keyword_count = :keyword_count WHERE id = :control_id");
                $stmt->bindParam(":keywords", $keyword_occurrences_json, PDO::PARAM_STR);
                $stmt->bindParam(":keyword_count", $keyword_count, PDO::PARAM_INT);
                $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
                $stmt->execute();

                // Create a result
                $result = [
                    'status_code' => 200,
                    'status_message' => 'New keywords created successfully.',
                    'data' => [
                        'keywords' => $keyword_occurrences,
                        'keyword_count' => $keyword_count
                    ]
                ];
            } catch (Exception $e)
            {
                write_debug_log("Error in get_keywords_for_control: " . $e->getMessage());

                // Create a result
                $result = [
                    'status_code' => 500,
                    'status_message' => 'Error processing control.',
                    'data' => []
                ];
            }
        }

        // Close the database connection
        db_close($db);
    }

    // Return the result
    return $result;
}

/********************************************
 * FUNCTION: GET TEXT FROM DOCUMENT CONTENT *
 ********************************************/
function get_text_from_document_content($content)
{
    try
    {
        // Write the content to a temporary file
        $temp_file = tempnam(sys_get_temp_dir(), 'doc_');
        file_put_contents($temp_file, $content);

        // Get the mime type of the file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $temp_file);

        // If this is a text file
        if (strpos($mime_type, 'text') !== false)
        {
            write_debug_log("Determined that the file is a text file.");

            // Just use the text
            $document_text = file_get_contents($temp_file);
        }
        // If it is not a text file
        else
        {
            // Try to process the file as a Word document
            try
            {
                write_debug_log("Attempting to process as a Word document...");

                // Read the Word document
                $phpWord = PhpOffice\PhpWord\IOFactory::load($temp_file, 'Word2007');

                // Extract the text from the Word document
                $document_text = extract_text_content($phpWord);
            } catch (\Exception $e)
            {
                write_debug_log("Error: " . $e->getMessage());

                // If the file is not a Word document, try to process it as a PDF
                try
                {
                    write_debug_log("Attempting to process as PDF...");

                    // Read the PDF document
                    $pdf = new \Smalot\PdfParser\Parser();
                    $pdfDocument = $pdf->parseFile($temp_file);

                    // Extract the text from the PDF document
                    $document_text = $pdfDocument->getText();
                } catch (\Exception $e)
                {
                    // If the file is not a PDF document, set the document text to null
                    $document_text = null;
                    write_debug_log("Error: " . $e->getMessage());
                    write_debug_log("Unable to process the file.  Leaving the text as null.");
                }
            }
        }

        // Delete the temporary file
        unlink($temp_file);
    } catch (Exception $e)
    {
        write_debug_log("Error in get_text_from_document_content: " . $e->getMessage());

        // Set the document text to null
        $document_text = null;
    }

    write_debug_log("Extracted Text: " . $document_text);

    // Return the document text
    return $document_text;
}

/*************************************************
 * FUNCTION: GET DOCUMENT CONTENT BY DOCUMENT ID *
 *************************************************/
function get_document_content_by_document_id($document_id)
{
    // Open the database connection
    $db = db_open();
    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents("t1" , false);
    } else $where = " 1";

    $sql = "
        SELECT t2.content AS content
        FROM `documents` t1 
            LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
        WHERE t1.id=:document_id AND {$where}
        ;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $result;
}

/***************************************
 * FUNCTION: GET DOCUMENTS TO CONTROLS *
 ***************************************/
function get_documents_to_controls($sort_order = 0, $order_field = false, $order_dir = false, $start = false, $length = false, $column_filters = [], $document_id = null)
{
    // Open the database connection
    $db = db_open();

    $sort_query = ' ORDER BY cf.name ASC'; // Default fallback

    // If sort_field is defined, set sort query
    if ($order_field)
    {
        $order_dir = $order_dir == "asc" ? "asc" : "desc";
        switch ($order_field)
        {
            case "document_id":
                $sort_query = " ORDER BY dtc.document_id {$order_dir} ";
                break;
            case "document":
                $sort_query = " ORDER BY cf.name {$order_dir} ";
                break;
            case "control_id":
                $sort_query = " ORDER BY dtc.control_id {$order_dir} ";
                break;
            case "control_number":
                $sort_query = " ORDER BY fc.control_number {$order_dir}";
                break;
            case "control_short_name":
                $sort_query = " ORDER BY fc.short_name {$order_dir}";
                break;
            case "selected":
                $sort_query = " ORDER BY dtc.selected {$order_dir} ";
                break;
            case "score":
                $sort_query = " ORDER BY dtc.score {$order_dir} ";
                break;
            case "tfidf_similarity":
                $sort_query = " ORDER BY dtc.tfidf_similarity {$order_dir} ";
                break;
            case "keyword_match":
                $sort_query = " ORDER BY dtc.keyword_match {$order_dir} ";
                break;
            case "ai_match":
                $sort_query = " ORDER BY dtc.ai_match {$order_dir} ";
                break;
            case "ai_confidence":
                $sort_query = " ORDER BY dtc.ai_confidence {$order_dir} ";
                break;
            case "ai_reasoning":
                $sort_query = " ORDER BY dtc.ai_reasoning {$order_dir} ";
                break;
            default:
                $sort_query = ' ORDER BY cf.name ASC';
        }
    }

    // If the document_id is not null
    if ($document_id !== null)
    {
        $where_query = "WHERE `dtc`.`document_id` = :document_id";
    }
    else $where_query = "WHERE `dtc`.`document_id` IS NOT NULL";

    // Add column filters to WHERE clause
    $filter_params = [];
    if (!empty($column_filters))
    {
        foreach ($column_filters as $column_name => $val)
        {
            $param_name = str_replace('.', '_', $column_name) . '_filter';

            switch ($column_name)
            {
                case "document":
                    $where_query .= " AND `d`.`document_name` LIKE :{$param_name}";
                    $filter_params[$param_name] = "%{$val}%";
                    break;
                case "control_number":
                    $where_query .= " AND `fc`.`control_number` LIKE :{$param_name}";
                    $filter_params[$param_name] = "%{$val}%";
                    break;
                case "control_short_name":
                    $where_query .= " AND `fc`.`short_name` LIKE :{$param_name}";
                    $filter_params[$param_name] = "%{$val}%";
                    break;
                case "selected":
                    $selected_val = (strtolower($val) == "yes") ? 1 : 0;
                    $where_query .= " AND `dtc`.`selected` = :{$param_name}";
                    $filter_params[$param_name] = $selected_val;
                    break;
                case "ai_match":
                    $ai_match_val = (strtolower($val) == "yes") ? 1 : 0;
                    $where_query .= " AND `dtc`.`ai_match` = :{$param_name}";
                    $filter_params[$param_name] = $ai_match_val;
                    break;
                case "ai_confidence":
                    // Remove % sign if present for numeric comparison
                    $numeric_val = str_replace('%', '', $val);
                    $where_query .= " AND `dtc`.`ai_confidence` LIKE :{$param_name}";
                    $filter_params[$param_name] = "%{$numeric_val}%";
                    break;
                case "ai_reasoning":
                    $where_query .= " AND `dtc`.`ai_reasoning` LIKE :{$param_name}";
                    $filter_params[$param_name] = "%{$val}%";
                    break;
                case "matching":
                    if (stripos("DefiniteMatch", $val) !== false)
                    {
                        $where_query .= " AND ((dtc.ai_run = 1 AND dtc.ai_match = 1) OR (dtc.ai_run = 0 AND dtc.score >= 0.9))";
                    }
                    else if (stripos("LikelyMatch", $val) !== false)
                    {
                        $where_query .= " AND (dtc.ai_run = 0 AND dtc.score >= 0.7 AND dtc.score < 0.9)";
                    }
                    else if (stripos("PossibleMatch", $val) !== false)
                    {
                        $where_query .= " AND (dtc.ai_run = 0 AND dtc.score >= 0.4 AND dtc.score < 0.7)";
                    }
                    else if (stripos("UnlikelyMatch", $val) !== false)
                    {
                        $where_query .= " AND (dtc.ai_run = 0 AND dtc.score >= 0.3 AND dtc.score < 0.4)";
                    }
                    else if (stripos("NotAMatch", $val) !== false)
                    {
                        $where_query .= " AND ((dtc.ai_run = 1 AND dtc.ai_match = 0) OR (dtc.ai_run = 0 AND dtc.score < 0.3))";
                    }
                    else if (stripos("ReviewManually", $val) !== false)
                    {
                        $where_query .= " AND NOT (
                            (dtc.ai_run = 1 AND dtc.ai_match = 1) OR
                            (dtc.ai_run = 1 AND dtc.ai_match = 0) OR
                            (dtc.ai_run = 0 AND dtc.score >= 0.9) OR
                            (dtc.ai_run = 0 AND dtc.score >= 0.7) OR
                            (dtc.ai_run = 0 AND dtc.score >= 0.4) OR
                            (dtc.ai_run = 0 AND dtc.score >= 0.3) OR
                            (dtc.ai_run = 0 AND dtc.score < 0.3)
                        )";
                    }
                    break;
                case "recommendation":
                    if (stripos("AddControlToPolicy", $val) !== false)
                    {
                        $where_query .= " AND ((dtc.ai_run = 1 AND dtc.selected = 0 AND dtc.ai_match = 1) 
                           OR (dtc.ai_run = 0 AND dtc.selected = 0 AND dtc.score >= 0.9))";
                    }
                    else if (stripos("ConsiderAddingControl", $val) !== false)
                    {
                        $where_query .= " AND (dtc.ai_run = 0 AND dtc.selected = 0 AND dtc.score >= 0.3 AND dtc.score < 0.9)";
                    }
                    else if (stripos("RemoveControlFromPolicy", $val) !== false)
                    {
                        $where_query .= " AND ((dtc.ai_run = 1 AND dtc.selected = 1 AND dtc.ai_match = 0) 
                           OR (dtc.ai_run = 0 AND dtc.selected = 1 AND dtc.score < 0.3))";
                    }
                    else if (stripos("NoActionRequired", $val) !== false)
                    {
                        $where_query .= " AND NOT (
                            (dtc.ai_run = 1 AND dtc.selected = 0 AND dtc.ai_match = 1) OR
                            (dtc.ai_run = 1 AND dtc.selected = 1 AND dtc.ai_match = 0) OR
                            (dtc.ai_run = 0 AND dtc.selected = 0 AND dtc.score >= 0.9) OR
                            (dtc.ai_run = 0 AND dtc.selected = 0 AND dtc.score >= 0.3) OR
                            (dtc.ai_run = 0 AND dtc.selected = 1 AND dtc.score < 0.3)
                        )";
                    }
                    break;
                default:
                    // Handle other column filters if needed
                    break;
            }
        }
    }

    // If team separation is enabled
    if (team_separation_extra())
    {
        // Load the team separation functions
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Get the user teams query for documents
        $where_query .= " AND " . get_user_teams_query_for_documents("d", false);
    }

    // Initialize limit query
    $limit_query = "";

    // If a start and length are specified
    if ($start !== false && $length !== false)
    {
        $limit_query = " LIMIT :start, :length";
    }

    // Get the count query with filters but without limits
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM `document_control_mappings` dtc
        INNER JOIN `documents` `d` ON `dtc`.`document_id` = `d`.`id`
        INNER JOIN (
            SELECT ref_id, MAX(version) AS max_version
            FROM compliance_files
            WHERE ref_type = 'documents'
            GROUP BY ref_id
        ) cf_latest ON d.id = cf_latest.ref_id
        INNER JOIN compliance_files cf
            ON cf.ref_id = cf_latest.ref_id
            AND cf.version = cf_latest.max_version
            AND cf.ref_type = 'documents'
        INNER JOIN `framework_controls` `fc` ON `dtc`.`control_id` = `fc`.`id`
        {$where_query};
    ");

    // Bind document_id parameter if needed
    if ($document_id !== null)
    {
        $stmt->bindValue(':document_id', (int)$document_id, PDO::PARAM_INT);
    }

    // Bind filter parameters
    foreach ($filter_params as $param => $value)
    {
        $stmt->bindValue(":{$param}", $value, is_numeric($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $total = $stmt->fetchColumn();

    // The rest of the function remains the same, just add binding for filter parameters
    $stmt = $db->prepare("
        SELECT `dtc`.*, `d`.`document_name`, `cf`.`unique_name`, `cf`.`name`, `fc`.`control_number`, `fc`.`short_name` AS control_short_name,
        CASE
            WHEN `dtc`.`ai_run` = 1 AND `dtc`.`ai_match` = 1 THEN 'DefiniteMatch'
            WHEN `dtc`.`ai_run` = 1 AND `dtc`.`ai_match` = 0 THEN 'NotAMatch'
            when `dtc`.`ai_run` = 0 AND `dtc`.`score` >= 0.9 THEN 'DefiniteMatch'
            WHEN `dtc`.`ai_run` = 0 AND `dtc`.`score` >= 0.7 THEN 'LikelyMatch'
            WHEN `dtc`.`ai_run` = 0 AND `dtc`.`score` >= 0.4 THEN 'PossibleMatch'
            WHEN `dtc`.`ai_run` = 0 AND `dtc`.`score` >= 0.3 THEN 'UnlikelyMatch'
            WHEN `dtc`.`ai_run` = 0 AND `dtc`.`score` < 0.3 THEN 'NotAMatch'
            ELSE 'ReviewManually'
        END AS matching,
        CASE
            WHEN `dtc`.`ai_run` = 1 AND `dtc`.`selected` = 0 AND `dtc`.`ai_match` = 1 THEN 'AddControlToPolicy'
            WHEN `dtc`.`ai_run` = 1 AND `dtc`.`selected` = 1 AND `dtc`.`ai_match` = 0 THEN 'RemoveControlFromPolicy'
            WHEN `dtc`.`ai_run` = 0 AND `dtc`.`selected` = 0 AND `dtc`.`score` >= 0.9 THEN 'AddControlToPolicy'
            WHEN `dtc`.`ai_run` = 0 AND `dtc`.`selected` = 0 AND `dtc`.`score` >= 0.3 THEN 'ConsiderAddingControl'
            WHEN `dtc`.`ai_run` = 0 AND `dtc`.`selected` = 1 AND `dtc`.`score` < 0.3 THEN 'RemoveControlFromPolicy'
            ELSE 'NoActionRequired'
        END AS recommendation
        FROM `document_control_mappings` dtc
        INNER JOIN `documents` `d` ON `dtc`.`document_id` = `d`.`id`
        INNER JOIN (
            SELECT ref_id, MAX(version) AS max_version
            FROM compliance_files
            WHERE ref_type = 'documents'
            GROUP BY ref_id
        ) cf_latest ON d.id = cf_latest.ref_id
        INNER JOIN compliance_files cf
            ON cf.ref_id = cf_latest.ref_id
            AND cf.version = cf_latest.max_version
            AND cf.ref_type = 'documents'
        INNER JOIN `framework_controls` `fc` ON `dtc`.`control_id` = `fc`.`id`
        {$where_query} {$sort_query} {$limit_query};
    ");

    // If a start and length are specified
    if ($start !== false && $length !== false)
    {
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
    }

    // If the document_id is not null
    if ($document_id !== null)
    {
        $stmt->bindValue(':document_id', (int)$document_id, PDO::PARAM_INT);
    }

    // Bind filter parameters
    foreach ($filter_params as $param => $value)
    {
        $stmt->bindValue(":{$param}", $value, is_numeric($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the results
    return [
        'data' => $data,
        'total' => $total
    ];
}

/******************************************
 * FUNCTION: DISPLAY UPDATE FRAMEWORK MODAL *
 ******************************************/
function display_update_framework_modal($where = "governance") {

    global $lang, $escaper;

    echo "
        <div id='framework--update' class='modal fade' tabindex='-1' aria-labelledby='risk-catalog--add' aria-hidden='true'>
            <div class='modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable'>
                <div class='modal-content'>
                    <form id='update-framework-form' class='' action='#' method='post' autocomplete='off'>
                        <input type='hidden' class='framework_id' name='framework_id' value=''> 
                        <input type='hidden' name='update_framework' value='true'>
                        <input type='hidden' name='where' value='{$escaper->escapeHtml($where)}'>
                        <div class='modal-header'>
                            <h4 class='modal-title'>{$escaper->escapeHtml($lang['FrameworkEditHeader'])}</h4>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
    ";
                            display_add_framework();
    echo "
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-secondary' data-bs-dismiss='modal' aria-label='Close'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                            <button type='submit' id='update_framework' class='btn btn-submit'>{$escaper->escapeHtml($lang['Update'])}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    ";

}

/******************************************
 * FUNCTION: DISPLAY UPDATE CONTROL MODAL *
 ******************************************/
function display_update_control_modal($where = "governance") {

    global $lang, $escaper;

    echo "
        <div id='control--update' class='modal fade' tabindex='-1' aria-labelledby='control--update' aria-hidden='true'>
            <div class='modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <form class='' id='update-control-form' method='post' autocomplete='off'>
                        <input type='hidden' class='control_id' name='control_id' value=''> 
                        <input type='hidden' name='update_control' value='true'> 
                        <input type='hidden' name='where' value='{$escaper->escapeHtml($where)}'>
                        <div class='modal-header'>
                            <h4 class='modal-title'>{$escaper->escapeHtml($lang['ControlEditHeader'])}</h4>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
    ";
                            display_add_control();
    echo "
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-secondary' data-bs-dismiss='modal' aria-label='Close'>{$escaper->escapeHtml($lang['Cancel'])}</button>
                            <button type='submit' id='update_control' class='btn btn-submit'>{$escaper->escapeHtml($lang['Update'])}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    ";
}

/******************************************
 * FUNCTION: DISPLAY ADD MAPPING ROW      *
 ******************************************/
function display_add_mapping_row() {

    global $lang, $escaper;

    echo "
        <div id='add_mapping_row' class='hide'>
            <table>
                <tr>
                    <td>
    ";
                        create_dropdown("frameworks", NULL,"map_framework_id[]", true, false, false, "required title='{$escaper->escapeHtml($lang['Framework'])}'"); 
    echo "
                    </td>
                    <td>
                        <input type='text' name='reference_name[]' value='' class='form-control' maxlength='100' required title='{$escaper->escapeHtml($lang["Control"])}'>
                    </td>
                    <td class='text-center'>
                        <a href='javascript:void(0);' class='control-block--delete-mapping' title='{$escaper->escapeHtml($lang["Delete"])}'><i class='fa fa-trash'></i></a>
                    </td>
                </tr>
            </table>
        </div>
    ";

}

/******************************************
 * FUNCTION: DISPLAY ADD ASSET ROW        *
 ******************************************/
function display_add_asset_row() {

    global $lang, $escaper;

    echo "
        <div id='add_asset_row' class='hide'>
            <table>
                <tr>
                    <td>
    ";
                        create_dropdown("control_maturity", "", "asset_maturity[]", true, false, false, "required title='{$escaper->escapeHtml($lang['CurrentMaturity'])}'"); 
    echo "
                    </td>
                    <td>
                        <select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='{$escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder'])}' required title='{$escaper->escapeHtml($lang["Asset"])}'></select>
                    </td>
                    <td class='text-center'>
                        <a href='javascript:void(0);' class='control-block--delete-asset' title='{$escaper->escapeHtml($lang["Delete"])}'><i class='fa fa-trash'></i></a>
                    </td>
                </tr>
            </table>
        </div>
    ";

}

?>