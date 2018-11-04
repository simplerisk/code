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

/****************************
 * FUNCTION: GET FRAMEWORKS *
 ****************************/
function get_frameworks($status = false)
{
    global $escaper;

    // Open the database connection
    $db = db_open();
    if($status === false){
        $stmt = $db->prepare("SELECT * FROM frameworks ORDER BY `order` ASC");
    }else{
        $stmt = $db->prepare("SELECT * FROM frameworks WHERE `status`=:status ORDER BY `order` ASC");
        $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    }
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each framework
    foreach ($array as $key => &$framework)
    {
        // Try to decrypt the framework name
        $framework['name'] = try_decrypt($framework['name']);
        
        // Try to decrypt the framework description
        $framework['description'] = try_decrypt($framework['description']);
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
function get_frameworks_as_treegrid($status){
    global $lang;
    global $escaper;
    
    $frameworks = get_frameworks($status);
    foreach($frameworks as &$framework){
        $framework['name'] = $escaper->escapeHtml($framework['name']);
        $framework['description'] = nl2br($escaper->escapeHtml($framework['description']));
        $framework['actions'] = "<div class=\"text-center\"><a class=\"framework-block--edit\" data-id=\"".((int)$framework['value'])."\"><i class=\"fa fa-pencil-square-o\"></i></a>&nbsp;&nbsp;&nbsp;<a class=\"framework-block--delete\" data-id=\"".((int)$framework['value'])."\"><i class=\"fa fa-trash\"></i></a></div>";
    }
    $results = array();
    $count = 0;
    if($status == 1){
        makeTree($frameworks, 0, $results, $count);
        if(isset($results['children'][0])){
            $results['children'][0]['totalCount'] = $count;
        }
        return isset($results['children']) ? $results['children'] : [];
    }else{
        if(isset($frameworks[0])){
            $frameworks[0]['totalCount'] = count($frameworks);
        }
        return $frameworks;
    }
}

/*********************************
 * FUNCTION: GET FRAMEWORK BY ID *
 *********************************/
function get_framework($framework_id){
    global $escaper;

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
        return $framework;
    }
    else{
        return false;
    }
}


/***************************************************
 * FUNCTION: GET PARENT FRAMEWORKS BY FRAMEWORK ID *
 ***************************************************/
function get_parent_frameworks($frameworks, $parent, &$news){
    if($parent == 0){
        return;
    }
    foreach($frameworks as $framework){
        if($framework['value'] == $parent){
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
    $stmt = $db->prepare("SELECT count(*) as count FROM frameworks WHERE `status` = $status");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    echo $array[0]['count'];
}

/********************************
 * FUNCTION: GET FRAMEWORK TABS *
 ********************************/
function get_framework_tabs($status)
{
    global $lang;
    global $escaper;
    
    echo "<table class='easyui-treegrid framework-table'
            data-options=\"
                iconCls: 'icon-ok',
                animate: true,
                collapsible: false,
                fitColumns: true,
                url: '".$_SESSION['base_url']."/api/governance/frameworks?status={$status}',
                method: 'get',
                idField: 'value',
                treeField: 'name',
                scrollbarSize: 0,
                onLoadSuccess: function(row, data){\n";
                    if(!empty($_SESSION['modify_frameworks']))
                    {
                        echo "\$(this).treegrid('enableDnd', row?row.value:null);";
                    }
                    
                    echo "if(data.length){
                        var totalCount = data[0].totalCount;
                    }else{
                        var totalCount = 0;
                    }
                    ".
                    (($status==1) ? "$('#active-frameworks-count').html(totalCount);" : "$('#inactive-frameworks-count').html(totalCount);")
                    ."
                },
                onStopDrag: function(row){
                    var tag = document.elementFromPoint(mouseX - window.pageXOffset, mouseY - window.pageYOffset);
                    if($(tag).hasClass('status')){
                        var framework_id = row.value;
                        var status = $(tag).data('status');
                        $.ajax({
                            url: BASE_URL + '/api/governance/update_framework_status',
                            type: 'POST',
                            data: {framework_id : framework_id, status:status},
                            success : function (data){
                                setTimeout(function(){
                                    location.reload();
                                }, 1500)
                            },
                            error: function(xhr,status,error){
                                if(!retryCSRF(xhr, this))
                                {
                                }
                            }
                        });
                    }
                },
                onDrop: function(targetRow, sourceRow){
                    var parent = targetRow ? targetRow.value : 0;
                    var framework_id = sourceRow.value;
                      $.ajax({
                        url: BASE_URL + '/api/governance/update_framework_parent',
                        type: 'POST',
                        data: {parent : parent, framework_id:framework_id},
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                            }
                        }
                    });
                }
            \">";
    echo "<thead >";
    echo "<th data-options=\"field:'name'\" width='20%'>".$escaper->escapeHtml($lang['FrameworkName'])."</th>";
    echo "<th data-options=\"field:'description'\" width='70%'>".$escaper->escapeHtml($lang['FrameworkDescription'])."</th>";
    echo "<th data-options=\"field:'actions'\" width='10%'>&nbsp;</th>";
    echo "</thead>\n";

    echo "</table>";
    echo "
        <style>
            body .tree-dnd-no{
                display: none;
            }
        </style>
    ";
} 
 
/************************************
 * FUNCTION: GET FRAMEWORK CONTROLS *
 ************************************/
function get_framework_controls($control_ids=false)
{
    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT t1.*, t2.name control_class_name, t3.name control_priority_name, t4.name family_short_name, t5.name control_phase_name, t6.name control_owner_name
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

    // Get all active frameworks
    $frameworks = get_frameworks(1);
    
    // For each $control
    foreach ($controls as $key => &$control)
    {
        // Get framework names from framework Ids string
        $framework_id_array = explode(",", $control['framework_ids']);
        $control['framework_names'] = array();
        foreach($frameworks as $framework){
            if(in_array($framework['value'], $framework_id_array)){
                $control['framework_names'][] = $framework['name'];
            }
        }
        $control['framework_names'] = implode(", ", $control['framework_names']);
    }

    // Close the database connection
    db_close($db);

    return $controls;
}

/**********************************************
 * FUNCTION: GET FRAMEWORK CONTROLS BY FILTER *
 **********************************************/
function get_framework_controls_by_filter($control_class="all", $control_phase="all", $control_owner="all", $control_family="all", $control_framework="all", $control_priority="all", $control_text=""){

    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT t1.*, t2.name control_class_name, t3.name control_phase_name, t4.name control_priority_name, t5.name family_short_name, t6.name control_owner_name
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `control_phase` t3 on t1.control_phase=t3.value
            LEFT JOIN `control_priority` t4 on t1.control_priority=t4.value
            LEFT JOIN `family` t5 on t1.family=t5.value
            LEFT JOIN `user` t6 on t1.control_owner=t6.value
        WHERE t1.deleted=0
    ";
    
    // If control class ID is requested.
    if($control_class && is_array($control_class)){
        $where = [];
        foreach($control_class as $val){
            if(!$val)
                continue;
            $val = (int)$val;
            $where[] = "t2.value=".$val;

            // If unassigned option.
            if($val == -1)
            {
                $where[] = "(t2.value is NULL OR t2.value='')";
            }
            else
            {
                $where[] = "t2.value=".$val;
            }
        }
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
        $where = [];
        foreach($control_phase as $val){
            if(!$val)
                continue;
            $val = (int)$val;
            $where[] = "t3.value=".$val;

            // If unassigned option.
            if($val == -1)
            {
                $where[] = "(t3.value is NULL OR t3.value='')";
            }
            else
            {
                $where[] = "t3.value=".$val;
            }
        }
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
        $where = [];
        foreach($control_priority as $val){
            if(!$val)
                continue;
            $val = (int)$val;

            // If unassigned option.
            if($val == -1)
            {
                $where[] = "(t4.value is NULL OR t4.value='')";
            }
            else
            {
                $where[] = "t4.value=".$val;
            }
        }
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
        $where = [];
        foreach($control_family as $val){
            if(!$val)
                continue;
            $val = (int)$val;

            // If unassigned option.
            if($val == -1)
            {
                $where[] = "(t5.value is NULL OR t5.value='')";
            }
            else
            {
                $where[] = "t5.value=".$val;
            }
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
        $where = [];
        foreach($control_owner as $val){
            if(!$val)
                continue;
            $val = (int)$val;

            // If unassigned option.
            if($val == -1)
            {
                $where[] = "(t6.value is NULL OR t6.value='')";
            }
            else
            {
                $where[] = "t6.value=".$val;
            }
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
        $where = [];
        foreach($control_framework as $val){
            if(!$val)
                continue;
            $val = (int)$val;
            // If unassigned option.
            if($val == -1)
            {
                $where[] = "(t1.framework_ids is NULL OR t1.framework_ids='')";
            }
            else
            {
                $framework_filter_pattern1 = $val;
                $framework_filter_pattern2 = "%,".$val;
                $framework_filter_pattern3 = $val.",%";
                $framework_filter_pattern4 = "%,".$val.",%";

                $where[] = "(t1.framework_ids like '{$framework_filter_pattern1}' or t1.framework_ids like '{$framework_filter_pattern2}' or t1.framework_ids like '{$framework_filter_pattern3}' or t1.framework_ids like '{$framework_filter_pattern4}')";
            }
        }
        
        $sql .= " AND (". implode(" OR ", $where) . ")";

    }
    elseif($control_framework == "all"){
        $sql .= " AND 1 ";
    }
    else{
        $sql .= " AND 0 ";
    }
    
    $stmt = $db->prepare($sql);

    $stmt->execute();

    // Controls by filter except framework
    $controls = $stmt->fetchAll();
    
    // Final results
    $filtered_controls = array();

    // Get all active frameworks
    $frameworks = get_frameworks(1);
    
    foreach ($controls as $key => $control)
    {
        // Get framework names from framework Ids string
        $framework_id_array = explode(",", $control['framework_ids']);
        $control['framework_names'] = array();
        foreach($frameworks as $framework){
            if(in_array($framework['value'], $framework_id_array)){
                $control['framework_names'][] = $framework['name'];
            }
        }
        $control['framework_names'] = implode(", ", $control['framework_names']);

        // Filter by search text
        if(
            !$control_text 
            || (stripos($control['short_name'], $control_text) !== false) 
            || (stripos($control['long_name'], $control_text) !== false) 
            || (stripos($control['description'], $control_text) !== false) 
            || (stripos($control['supplemental_guidance'], $control_text) !== false) 
            || (stripos($control['control_number'], $control_text) !== false)
            || (stripos($control['control_class_name'], $control_text) !== false) 
            || (stripos($control['control_phase_name'], $control_text) !== false) 
            || (stripos($control['control_priority_name'], $control_text) !== false) 
            || (stripos($control['family_short_name'], $control_text) !== false) 
            || (stripos($control['control_owner_name'], $control_text) !== false) 
            || (stripos($control['framework_names'], $control_text) !== false) 
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

    $message = "A new framework named \"{$name}\" was created by username \"" . $_SESSION['user'] . "\".";
    write_log((int)$framework_id + 1000, $_SESSION['uid'], $message, "framework");
    
    // Close the database connection
    db_close($db);

    return $framework_id;
}

/******************************
 * FUNCTION: UPDATE FRAMEWORK *
 ******************************/
function update_framework($framework_id, $name, $description=false, $parent=false){
    $try_encrypt_name = try_encrypt($name);

    // Open the database connection
    $db = db_open();

    // Check if the framework exists
    $stmt = $db->prepare("SELECT * FROM `frameworks` where name=:name and value<>:framework_id");
    $stmt->bindParam(":name", $try_encrypt_name);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    if(isset($row[0])){
        return false;
    }

    $framework = get_framework($framework_id);
    
    $framework['name'] = try_encrypt($name);
    $framework['description'] = $description === false ? try_encrypt($framework['description']) : try_encrypt($description);
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
function get_child_frameworks($parent_id)
{
    // Open the database connection
    $db = db_open();

    $sql = "SELECT t1.* FROM `frameworks` t1 WHERE t1.parent=:parent_id;";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":parent_id", $parent_id, PDO::PARAM_INT);
    $stmt->execute();

    $results = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);
    
    return $results;
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
    
    $short_name = isset($control['short_name']) ? $control['short_name'] : "";
    $long_name = isset($control['long_name']) ? $control['long_name'] : "";
    $description = isset($control['description']) ? $control['description'] : "";
    $supplemental_guidance = isset($control['supplemental_guidance']) ? $control['supplemental_guidance'] : "";
    $framework_ids = isset($control['framework_ids']) ? (is_array($control['framework_ids']) ? implode(",", $control['framework_ids']) : $control['framework_ids']) : "";
    $control_owner = isset($control['control_owner']) ? (int)$control['control_owner'] : 0;
    $control_class = isset($control['control_class']) ? (int)$control['control_class'] : 0;
    $control_phase = isset($control['control_phase']) ? (int)$control['control_phase'] : 0;
    $control_number = isset($control['control_number']) ? $control['control_number'] : "";
    $control_priority = isset($control['control_priority']) ? (int)$control['control_priority'] : 0;
    $family = isset($control['family']) ? (int)$control['family'] : 0;
    $mitigation_percent = isset($control['mitigation_percent']) ? (int)$control['mitigation_percent'] : 0;
    
    // Open the database connection
    $db = db_open();

    // Create a framework
    $stmt = $db->prepare("INSERT INTO `framework_controls` (`short_name`, `long_name`, `description`, `supplemental_guidance`, `framework_ids`, `control_owner`, `control_class`, `control_phase`, `control_number`, `control_priority`, `family`, `mitigation_percent`) VALUES (:short_name, :long_name, :description, :supplemental_guidance, :framework_ids, :control_owner, :control_class, :control_phase, :control_number, :control_priority, :family, :mitigation_percent)");
    $stmt->bindParam(":short_name", $short_name, PDO::PARAM_STR, 100);
    $stmt->bindParam(":long_name", $long_name, PDO::PARAM_STR);
    $stmt->bindParam(":description", $description, PDO::PARAM_STR);
    $stmt->bindParam(":supplemental_guidance", $supplemental_guidance, PDO::PARAM_STR);
    $stmt->bindParam(":framework_ids", $framework_ids, PDO::PARAM_STR);
    $stmt->bindParam(":control_owner", $control_owner, PDO::PARAM_INT);
    $stmt->bindParam(":control_class", $control_class, PDO::PARAM_INT);
    $stmt->bindParam(":control_phase", $control_phase, PDO::PARAM_INT);
    $stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR);
    $stmt->bindParam(":control_priority", $control_priority, PDO::PARAM_INT);
    $stmt->bindParam(":family", $family, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_percent", $mitigation_percent, PDO::PARAM_INT);
    $stmt->execute();
    
    $control_id = $db->lastInsertId();

    // Close the database connection
    db_close($db);

    $message = "A new control named \"{$short_name}\" was created by username \"" . $_SESSION['user'] . "\".";
    write_log((int)$control_id + 1000, $_SESSION['uid'], $message, "control");
    
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
    $framework_ids = isset($control['framework_ids']) ? (is_array($control['framework_ids']) ? implode(",", $control['framework_ids']) : $control['framework_ids']) : "";
    $control_owner = isset($control['control_owner']) ? (int)$control['control_owner'] : 0;
    $control_class = isset($control['control_class']) ? (int)$control['control_class'] : 0;
    $control_phase = isset($control['control_phase']) ? (int)$control['control_phase'] : 0;
    $control_number = isset($control['control_number']) ? $control['control_number'] : "";
    $control_priority = isset($control['control_priority']) ? (int)$control['control_priority'] : 0;
    $family = isset($control['family']) ? (int)$control['family'] : 0;
    $mitigation_percent = isset($control['mitigation_percent']) ? (int)$control['mitigation_percent'] : 0;

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("UPDATE `framework_controls` SET `short_name`=:short_name, `long_name`=:long_name, `description`=:description, `supplemental_guidance`=:supplemental_guidance, `framework_ids`=:framework_ids, `control_owner`=:control_owner, `control_class`=:control_class, `control_phase`=:control_phase, `control_number`=:control_number, `control_priority`=:control_priority, `family`=:family, `mitigation_percent`=:mitigation_percent WHERE id=:id;");
    $stmt->bindParam(":short_name", $short_name, PDO::PARAM_STR, 100);
    $stmt->bindParam(":long_name", $long_name, PDO::PARAM_STR);
    $stmt->bindParam(":description", $description, PDO::PARAM_STR);
    $stmt->bindParam(":supplemental_guidance", $supplemental_guidance, PDO::PARAM_STR);
    $stmt->bindParam(":framework_ids", $framework_ids, PDO::PARAM_STR);
    $stmt->bindParam(":control_owner", $control_owner, PDO::PARAM_INT);
    $stmt->bindParam(":control_class", $control_class, PDO::PARAM_INT);
    $stmt->bindParam(":control_phase", $control_phase, PDO::PARAM_INT);
    $stmt->bindParam(":control_number", $control_number, PDO::PARAM_STR);
    $stmt->bindParam(":control_priority", $control_priority, PDO::PARAM_INT);
    $stmt->bindParam(":family", $family, PDO::PARAM_INT);
    $stmt->bindParam(":mitigation_percent", $mitigation_percent, PDO::PARAM_INT);
    $stmt->bindParam(":id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
    
    $message = "A control named \"{$short_name}\" was updated by username \"" . $_SESSION['user'] . "\".";
    write_log((int)$control_id + 1000, $_SESSION['uid'], $message, "control");
    
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

    $stmt = $db->prepare("SELECT DISTINCT(risk_id) FROM `mitigations` WHERE FIND_IN_SET(:control_id, mitigation_controls)");
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

/***************************************
 * FUNCTION: ADD NEW FRAMEWORK CONTROL *
 ***************************************/
function delete_framework_control($control_id){
    // Open the database connection
    $db = db_open();

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
    }
    
    // Close the database connection
    db_close($db);
    
    $control = get_framework_control($control_id);

    $message = "A control named \"{$control['short_name']}\" was deleted by username \"" . $_SESSION['user'] . "\".";
    write_log((int)$control_id + 1000, $_SESSION['uid'], $message, "control");

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
        SELECT t1.*, t2.name control_class_name, t3.name control_priority_name, t4.name family_short_name
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `control_priority` t3 on t1.control_priority=t3.value
            LEFT JOIN `family` t4 on t1.family=t4.value
        WHERE t1.id=:id"
    );
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $control = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    return $control;
}

/**********************************************
 * FUNCTION: GET AVAILABLE CONTROL CLASS List *
 **********************************************/
function getAvailableControlClassList(){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
        WHERE t2.value is not null
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
function getAvailableControlPhaseList(){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_phase` t2 on t1.control_phase=t2.value
        WHERE t2.value is not null
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
function getAvailableControlOwnerList(){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `user` t2 on t1.control_owner=t2.value
        WHERE t2.value is not null
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
function getAvailableControlFamilyList(){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `family` t2 on t1.family=t2.value
        WHERE t2.value is not null
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
function getAvailableControlFrameworkList(){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.framework_ids
        FROM `framework_controls` t1 
    ";
    
    // Get available control framework id list
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $idStrings = $stmt->fetchAll();
    
    $framework_ids = array();
    foreach($idStrings as $idsString){
        $ids = explode(",", $idsString['framework_ids']);
        foreach($ids as $id){
            if($id && !in_array($id, $framework_ids)){
                $framework_ids[] = (int)$id;
            }
        }
    }
    if($framework_ids){
        $sql = "
            SELECT *
            FROM `frameworks` 
            WHERE value in (". implode(",", $framework_ids) .") AND `status`=1 ;
        ";

        // Get available framework list
        $stmt = $db->prepare($sql);
        
        $stmt->execute();

        $results = $stmt->fetchAll();
    }else{
        $results = [];
    }
    
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
function getAvailableControlPriorityList(){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_priority` t2 on t1.control_priority=t2.value
        WHERE t2.value is not null
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

/**************************************************
 * FUNCTION: GET DOCUMENT VERSIONS BY DOCUMENT ID *
 **************************************************/
function get_document_versions_by_id($id)
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.*, t2.version file_version, t2.unique_name
        FROM `documents` t1 
            INNER JOIN `compliance_files` t2 ON t1.id=t2.ref_id AND t2.ref_type='documents'
        WHERE t1.id=:id
        ORDER BY t2.version
        ;
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

    $sql = "
        SELECT t1.*, t2.version file_version, t2.unique_name
        FROM `documents` t1 
            LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
        WHERE t1.id=:id
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

    if($type)
    {
        $sql = "
            SELECT t1.*, t2.version file_version, t2.unique_name
            FROM `documents` t1 
                LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
            WHERE t1.document_type=:type
            ORDER BY t1.document_type, t1.document_name
            ;
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    }
    // Get all documents
    else
    {
        $sql = "
            SELECT t1.*, t2.version file_version, t2.unique_name
            FROM `documents` t1 
                LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
            ORDER BY t1.document_type, t1.document_name
            ;
        ";
        $stmt = $db->prepare($sql);
    }
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
    global $lang;
    global $escaper;

    foreach($options as $option){
        if($option['parent'] == $parent){
            if($selected == $option['value']){
                $html .= "<option selected value='{$option['value']}'>".$indent.$escaper->escapeHtml($option['name'])."</option>\n";
            }
            else{
                $html .= "<option value='{$option['value']}'>".$indent.$escaper->escapeHtml($option['name'])."</option>\n";
            }
            make_tree_options_html($options, $option['value'], $html, $indent."&nbsp;&nbsp;", $selected);
        }
    }
}

/******************************
 * FUNCTION: ADD NEW DOCUMENT *
 ******************************/
function add_document($document_type, $document_name, $parent, $status, $creation_date){
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
    $stmt = $db->prepare("INSERT INTO `documents` (`document_type`, `document_name`, `parent`, `status`, `file_id`, `creation_date`) VALUES (:document_type, :document_name, :parent, :status, :file_id, :creation_date)");
    $stmt->bindParam(":document_type", $document_type, PDO::PARAM_STR);
    $stmt->bindParam(":document_name", $document_name, PDO::PARAM_STR);
    $stmt->bindParam(":parent", $parent, PDO::PARAM_INT);
    $stmt->bindParam(":status", $status, PDO::PARAM_STR);
    $init_file_id = 0;
    $stmt->bindParam(":file_id", $init_file_id, PDO::PARAM_INT);
    $stmt->bindParam(":creation_date", $creation_date, PDO::PARAM_STR);
    $stmt->execute();
    
    $document_id = $db->lastInsertId();

    // Close the database connection
    db_close($db);

    // If submitted files are existing, save files
    if(!empty($_FILES['file'])){
        $files = $_FILES['file'];
        list($status, $file_ids, $errors) = upload_compliance_files($document_id, "documents", $files);
        if($file_ids){
            $file_id = $file_ids[0];
        }
    }

    // Check if error was happen in uploading files
    if(!empty($errors))
    {
        // Delete added document if failed to upload a document file
        delete_document($document_id);
        $errors = array_unique($errors);
        set_alert(true, "bad", implode(", ", $errors));
        return false;
    }elseif(empty($file_id))
    {
        // Delete added document if failed to upload a document file
        delete_document($document_id);
        set_alert(true, "bad", $escaper->escapeHtml($lang['FailedToUploadFile']));
        return false;
    }else
    {
        $stmt = $db->prepare("UPDATE `documents` SET file_id=:file_id WHERE id=:document_id ");
        $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->execute();

        return $document_id;
    }
}

/*****************************
 * FUNCTION: UPDATE DOCUMENT *
 *****************************/
function update_document($document_id, $document_type, $document_name, $parent, $status, $creation_date){
    global $lang, $escaper;
    
    // Open the database connection
    $db = db_open();
    
    // Check if the framework exists
    $stmt = $db->prepare("SELECT * FROM `documents` where document_name=:document_name AND document_type=:document_type AND id<>:id; ");
    $stmt->bindParam(":document_name", $document_name, PDO::PARAM_STR);
    $stmt->bindParam(":document_type", $document_type, PDO::PARAM_STR);
    $stmt->bindParam(":id", $document_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    if(isset($row[0])){
        set_alert(true, "bad", $escaper->escapeHtml($lang['DocumentNameExist']));
        return false;
    }

    // Update a document
    $stmt = $db->prepare("UPDATE `documents` SET `document_type`=:document_type, `document_name`=:document_name, `parent`=:parent, `status`=:status, `creation_date`=:creation_date WHERE id=:document_id; ");
    $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
    $stmt->bindParam(":document_type", $document_type, PDO::PARAM_STR);
    $stmt->bindParam(":document_name", $document_name, PDO::PARAM_STR);
    $stmt->bindParam(":parent", $parent, PDO::PARAM_INT);
    $stmt->bindParam(":status", $status, PDO::PARAM_STR);
    $stmt->bindParam(":creation_date", $creation_date, PDO::PARAM_STR);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

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
//    echo $document_id."<br>";
//    echo $version."<br>";
//    exit;
    // Deletes documents only to have this version number
    if($version)
    {
        $stmt = $db->prepare("DELETE FROM compliance_files WHERE ref_id=:document_id AND ref_type='documents' AND version=:version; ");
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->bindParam(":version", $version, PDO::PARAM_INT);
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
    }
    
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
    
    echo "<table  class='easyui-treegrid document-table'
            data-options=\"
                iconCls: 'icon-ok',
                animate: true,
                collapsible: false,
                fitColumns: true,
                url: '".$_SESSION['base_url']."/api/governance/documents?type={$type}',
                method: 'get',
                idField: 'id',
                treeField: 'document_name',
                scrollbarSize: 0,
                onLoadSuccess: function(row, data){

                }
            \">";
    echo "<thead >";
    echo "<th data-options=\"field:'document_name'\" width='40%'>".$escaper->escapeHtml($lang['DocumentName'])."</th>";
    echo "<th data-options=\"field:'document_type'\" width='20%'>".$escaper->escapeHtml($lang['DocumentType'])."</th>";
    echo "<th data-options=\"field:'creation_date'\" width='20%'>".$escaper->escapeHtml($lang['CreationDate'])."</th>";
    echo "<th data-options=\"field:'status'\" width='20%'>".$escaper->escapeHtml($lang['Status'])."</th>";
//    echo "<th data-options=\"field:'actions'\" width='10%'>&nbsp;</th>";
    echo "</thead>\n";

    echo "</table>";
    echo "
        <style>
            body .tree-dnd-no{
                display: none;
            }
        </style>
    ";
} 

/***************************************
 * FUNCTION: GET DOCUMENT TABULAR TABS *
 ***************************************/
function get_document_tabular_tabs($type, $document_id=0)
{
    global $lang;
    global $escaper;
    
    echo "<table  class='easyui-treegrid document-table'
            data-options=\"
                iconCls: 'icon-ok',
                animate: true,
                collapsible: false,
                fitColumns: true,
                url: '".$_SESSION['base_url']."/api/governance/tabular_documents?type={$type}',
                method: 'get',
                idField: 'id',
                treeField: 'document_name',
                scrollbarSize: 0,
                onLoadSuccess: function(row, data){

                }
            \">";
    echo "<thead >";
    echo "<th data-options=\"field:'document_name'\" width='40%'>".$escaper->escapeHtml($lang['DocumentName'])."</th>";
    echo "<th data-options=\"field:'document_type'\" width='20%'>".$escaper->escapeHtml($lang['DocumentType'])."</th>";
    echo "<th data-options=\"field:'creation_date'\" width='20%'>".$escaper->escapeHtml($lang['CreationDate'])."</th>";
    echo "<th data-options=\"field:'status'\" width='10%'>".$escaper->escapeHtml($lang['Status'])."</th>";
    echo "<th data-options=\"field:'actions'\" width='10%'>&nbsp;</th>";
    echo "</thead>\n";

    echo "</table>";
    echo "
        <style>
            body .tree-dnd-no{
                display: none;
            }
        </style>
    ";
} 
 
/***********************************************
 * FUNCTION: GET DOCUMENTS DATA IN TREE FORMAT *
 ***********************************************/
function get_documents_as_treegrid($type){
    global $lang;
    global $escaper;
    
    $documents = get_documents($type);
    foreach($documents as &$document){
        $document['value'] = $document['id'];
        $document['document_type'] = $escaper->escapeHtml($document['document_type']);
        $document['document_name'] = "<a href=\"".$_SESSION['base_url']."/governance/download.php?id=".$document['unique_name']."\" >".$escaper->escapeHtml($document['document_name'])."</a>";
        $document['status'] = $escaper->escapeHtml($document['status']);
        $document['creation_date'] = ($document['creation_date'] != "0000-00-00" && $document['creation_date']) ? date(get_default_date_format(), strtotime($document['creation_date'])) : "";
        $document['actions'] = "<div class=\"text-center\"><a class=\"framework-block--edit\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-pencil-square-o\"></i></a>&nbsp;&nbsp;&nbsp;<a class=\"framework-block--delete\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-trash\"></i></a></div>";
    }
    $results = array();
    $count = 0;
    
    makeTree($documents, 0, $results, $count);
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
?>
