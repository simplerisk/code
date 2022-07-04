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
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

/****************************
 * FUNCTION: GET FRAMEWORKS *
 * $status
 *      1: active
 *      2: inactive
 ****************************/
function get_frameworks($status = false, $decrypt_name=true, $decrypt_description=true)
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
    global $escaper;

    $complianceforge_scf_framework_id = complianceforge_scf_extra() ? (int)get_setting('complianceforge_scf_framework_id', 0) : 0;

    $frameworks = get_frameworks($status);
    foreach($frameworks as &$framework){
        $framework_value = (int)$framework['value'];
        $framework['name'] = $escaper->escapeHtml($framework['name']);
        $framework['description'] = nl2br($escaper->escapeHtml($framework['description']));
        $framework['actions'] = "
            <div class=\"text-center\">
                <a class=\"framework-block--edit\" data-id=\"" . $framework_value . "\">
                    <i class=\"fa fa-edit\"></i>
                </a>"
                    . ($complianceforge_scf_framework_id && $complianceforge_scf_framework_id === $framework_value ? "" : "&nbsp;&nbsp;&nbsp;
                <a class=\"framework-block--delete\" data-id=\"" . $framework_value . "\">
                    <i class=\"fa fa-trash\"></i>
                </a>") . "
            </div>";
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
        foreach($frameworks as $framework){
            $results[] = $framework;
        }
        return $results;
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
    $stmt = $db->prepare("SELECT count(*) as count FROM frameworks WHERE `status` = $status");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array[0]['count'];
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
                onLoadSuccess: function(row, data){
    ";

    if(!empty($_SESSION['modify_frameworks'])) {
        echo "
                    \$(this).treegrid('enableDnd', row?row.value:null);";
    }

    echo "
                    if(data.length){
                        var totalCount = data[0].totalCount;
                    }else{
                        var totalCount = 0;
                    }
                    ".
                    (($status==1) ? "$('#active-frameworks-count').html(totalCount);" : "$('#inactive-frameworks-count').html(totalCount);")
                    ."
                    fixTreeGridCollapsableColumn();
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
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)) {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                    setTimeout(function(){
                                        location.reload();
                                    }, 1500);
                                }
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
        SELECT t1.*, GROUP_CONCAT(DISTINCT f.value) framework_ids, GROUP_CONCAT(DISTINCT f.name) framework_names, t2.name control_class_name, t3.name control_phase_name, t4.name control_priority_name, t5.name family_short_name, t6.name control_owner_name, t7.name control_maturity_name, t8.name desired_maturity_name, group_concat(distinct ctype.value) control_type_ids, GROUP_CONCAT(DISTINCT m_1.reference_name) reference_name
        FROM `framework_controls` t1 
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            LEFT JOIN `frameworks` f on m.framework=f.value AND f.status=1
            LEFT JOIN `framework_control_mappings` m_1 on t1.id=m_1.control_id
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
        $where[] = "FIND_IN_SET(t5.value, '".implode(",", $where_ids)."')";
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
        $where[] = "FIND_IN_SET(t6.value, '".implode(",", $where_or_ids)."')";
        
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
        $where[] = "FIND_IN_SET(m_1.framework, '".implode(",", $where_or_ids)."')";
        
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
        $framework_ids = explode(",", $control['framework_ids']);
        
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
            || (stripos($control['reference_name'], $control_text) !== false)
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

    if (isset($name) && !trim($name)) {
        set_alert(true, "bad", $lang['FrameworkNameCantBeEmpty']);
        return false;
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

    $framework = get_framework($framework_id);
    
    $framework['name'] = $encrypted_name;
    $framework['description'] = $description === false ? try_encrypt($framework['description']) : try_encrypt($description);
    $framework['parent'] = $parent === false ? $framework['parent'] : $parent;
    
    // Create a framework
    $stmt = $db->prepare("UPDATE `frameworks` SET `name`=:name, `description`=:description, `parent`=:parent WHERE value=:framework_id;");
    $stmt->bindParam(":name", $framework['name'], PDO::PARAM_STR, 100);
    $stmt->bindParam(":description", $framework['description'], PDO::PARAM_STR, 1000);
    $stmt->bindParam(":parent", $framework['parent'], PDO::PARAM_INT);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        save_custom_field_values($framework_id, "framework");
    }

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

    if(count($control_type) > 0) {
        foreach ($control_type as $type) {
            $stmt = $db->prepare("INSERT INTO `framework_control_type_mappings` (`control_id`, `control_type_id`) VALUES (:control_id, :control_type_id)");
            $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
            $stmt->bindParam(":control_type_id", $type, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
    if(isset($control['map_frameworks'])&&count($control['map_frameworks'])>0) save_control_to_frameworks($control_id, $control['map_frameworks']);
    if(count($framework_ids)>0) save_control_to_framework_by_ids($control_id, $framework_ids);

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
    if(count($framework_ids)>0) save_control_to_framework_by_ids($control_id, $framework_ids);
    
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
    $message = "A control named \"{$control['short_name']}\" was deleted by username \"" . $user . "\".";
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
        SELECT t1.*, IFNULL(GROUP_CONCAT(m.framework), '') framework_ids, t2.name control_class_name, t3.name control_priority_name, t4.name family_short_name, group_concat(distinct ctype.value) control_type_ids
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
    
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $custom_values = get_custom_value_by_row_id($id, "control");
        $control['custom_values'] = $custom_values;
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
        WHERE t2.id IS NOT NULL AND t1.`status`=1 
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
function get_document_versions_by_id($id)
{
    // Open the database connection
    $db = db_open();
    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents("t1" , false);
    } else $where = " 1";

    $sql = "
        SELECT t1.*, t2.version file_version, t2.unique_name, t3.value as status
        FROM `documents` t1 
            INNER JOIN `compliance_files` t2 ON t1.id=t2.ref_id AND t2.ref_type='documents'
            LEFT JOIN `document_status` t3 ON t1.document_status=t3.value
        WHERE t1.id=:id AND {$where}
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
    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where = get_user_teams_query_for_documents("t1" , false);
    } else $where = " 1";

    $sql = "
        SELECT t1.*, t2.version file_version, t2.unique_name, t3.value as status
        FROM `documents` t1 
            LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
            LEFT JOIN `document_status` t3 ON t1.document_status=t3.value
        WHERE t1.id=:id AND {$where}
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
        SELECT t1.*, t2.version file_version, t2.unique_name, t3.value as status
        FROM `documents` t1 
	    LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
            LEFT JOIN `document_status` t3 ON t1.document_status=t3.value
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
    $sql .= " ORDER BY t1.document_type, t1.document_name";

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
    $stmt = $db->prepare("INSERT INTO `documents` (`submitted_by`, `document_type`, `document_name`, `control_ids`, `framework_ids`, `parent`, `document_status`, `file_id`, `creation_date`, `last_review_date`, `review_frequency`, `next_review_date`, `approval_date`, `document_owner`, `additional_stakeholders`, `approver`, `team_ids`) VALUES (:submitted_by, :document_type, :document_name, :control_ids, :framework_ids, :parent, :status, :file_id, :creation_date, :last_review_date, :review_frequency, :next_review_date, :approval_date, :document_owner, :additional_stakeholders, :approver, :team_ids)");
    $stmt->bindParam(":submitted_by", $submitted_by, PDO::PARAM_INT);
    $stmt->bindParam(":document_type", $document_type, PDO::PARAM_STR);
    $stmt->bindParam(":document_name", $document_name, PDO::PARAM_STR);
    $stmt->bindParam(":control_ids", $control_ids, PDO::PARAM_STR);
    $stmt->bindParam(":framework_ids", $framework_ids, PDO::PARAM_STR);
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
    $stmt = $db->prepare("SELECT * FROM `documents` where id=:id;");
    $stmt->bindParam(":id", $document_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

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
        'control_ids' => $control_ids,
        'framework_ids' => $framework_ids,
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
    $stmt = $db->prepare("UPDATE `documents` SET `updated_by` = :updated_by, `document_type`=:document_type, `document_name`=:document_name, `control_ids`=:control_ids, `framework_ids`=:framework_ids, `parent`=:parent, `document_status`=:document_status, `creation_date`=:creation_date, `last_review_date`=:last_review_date, `review_frequency`=:review_frequency, `next_review_date`=:next_review_date, `approval_date`=:approval_date, `document_owner`=:document_owner, `additional_stakeholders`=:additional_stakeholders , `approver`=:approver, `team_ids`=:team_ids WHERE id=:document_id; ");
    $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
    $stmt->bindParam(":updated_by", $updated_by, PDO::PARAM_INT);
    $stmt->bindParam(":document_type", $document_type, PDO::PARAM_STR);
    $stmt->bindParam(":document_name", $document_name, PDO::PARAM_STR);
    $stmt->bindParam(":control_ids", $control_ids, PDO::PARAM_STR);
    $stmt->bindParam(":framework_ids", $framework_ids, PDO::PARAM_STR);
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
    $row = $stmt->fetch();
    if(!$row[0]){
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
    
    echo "<table  class='easyui-treegrid document-table'
            \">";
    echo "<thead >";
    echo "<th data-options=\"field:'document_name'\" width='25%'>".$escaper->escapeHtml($lang['DocumentName'])."</th>";
    echo "<th data-options=\"field:'document_type'\" width='10%'>".$escaper->escapeHtml($lang['DocumentType'])."</th>";
    echo "<th data-options=\"field:'framework_names'\" width='20%'>".$escaper->escapeHtml($lang['ControlFrameworks'])."</th>";
    echo "<th data-options=\"field:'control_names'\" width='20%'>".$escaper->escapeHtml($lang['Controls'])."</th>";
    echo "<th data-options=\"field:'creation_date'\" width='9%'>".$escaper->escapeHtml($lang['CreationDate'])."</th>";
    echo "<th data-options=\"field:'approval_date'\" width='9%'>".$escaper->escapeHtml($lang['ApprovalDate'])."</th>";
    echo "<th data-options=\"field:'status'\" width='7%'>".$escaper->escapeHtml($lang['Status'])."</th>";
    echo "</thead>\n";

    echo "</table>";
    echo "
        <style>
            body .tree-dnd-no{
                display: none;
            }
        </style>
        <script>
            $(function(){
                var tg = $('#document-hierachy-content .easyui-treegrid').treegrid({
                    iconCls: 'icon-ok',
                    animate: true,
                    collapsible: false,
                    fitColumns: true,
                    url: '".$_SESSION['base_url']."/api/governance/documents?type={$type}',
                    method: 'get',
                    idField: 'id',
                    treeField: 'document_name',
                    remoteFilter: true,
                    scrollbarSize: 0
                });
                tg.treegrid('enableFilter');
            });
        </script>
    ";
} 

/***************************************
 * FUNCTION: GET DOCUMENT TABULAR TABS *
 ***************************************/
function get_document_tabular_tabs($type, $document_id=0)
{
    global $lang;
    global $escaper;
    
    echo "<table  class='easyui-treegrid document-table' id='{$type}-table'>";
    echo "<thead >";
    echo "<th data-options=\"field:'document_name'\" width='25%'>".$escaper->escapeHtml($lang['DocumentName'])."</th>";
    echo "<th data-options=\"field:'document_type'\" width='10%'>".$escaper->escapeHtml($lang['DocumentType'])."</th>";
    echo "<th data-options=\"field:'framework_names'\" width='18%'>".$escaper->escapeHtml($lang['ControlFrameworks'])."</th>";
    echo "<th data-options=\"field:'control_names'\" width='18%'>".$escaper->escapeHtml($lang['Controls'])."</th>";
    echo "<th data-options=\"field:'creation_date'\" width='9%'>".$escaper->escapeHtml($lang['CreationDate'])."</th>";
    echo "<th data-options=\"field:'approval_date'\" width='9%'>".$escaper->escapeHtml($lang['ApprovalDate'])."</th>";
    echo "<th data-options=\"field:'status'\" width='6%'>".$escaper->escapeHtml($lang['Status'])."</th>";
    echo "<th data-options=\"field:'actions'\" width='5%'>&nbsp;</th>";
    echo "</thead>\n";

    echo "</table>";
    echo "
        <style>
            body .tree-dnd-no{
                display: none;
            }
        </style>
        <script>
            $(function(){
                var tg = $('#{$type}-table').treegrid({
                    iconCls: 'icon-ok',
                    animate: true,
                    collapsible: false,
                    fitColumns: true,
                    url: '".$_SESSION['base_url']."/api/governance/tabular_documents?type={$type}',
                    method: 'get',
                    idField: 'id',
                    treeField: 'document_name',
                    remoteFilter: true,
                    scrollbarSize: 0,
                    onLoadSuccess: function(row, data){
                    }
                });
                tg.treegrid('enableFilter', [{
                    field:'actions',
                    type:'label'
                }]);
            });
        </script>
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
                            continue 2;
                        }
                        break;
                    case "document_type":
                        if( stripos($document['document_type'], $value) === false ){
                            continue 2;
                        }
                        break;
                    case "framework_names":
                        if( stripos($framework_names, $value) === false ){
                            continue 2;
                        }
                        break;
                    case "control_names":
                        if( stripos($control_names, $value) === false ){
                            continue 2;
                        }
                        break;
                    case "creation_date":
                        if( stripos(format_date($document['creation_date']), $value) === false ){
                            continue 2;
                        }
                        break;
                    case "approval_date":
                        if( stripos(format_date($document['approval_date']), $value) === false ){
                            continue 2;
                        }
                        break;
                    case "status":
                        if( stripos($document['status'], $value) === false ){
                            continue 2;
                        }
                        break;
                }
            }
        }

        $document['value'] = $document['id'];
        $document['document_type'] = $escaper->escapeHtml($document['document_type']);
        $document['document_name'] = "<a href=\"".$_SESSION['base_url']."/governance/download.php?id=".$document['unique_name']."\" >".$escaper->escapeHtml($document['document_name'])."</a>";
        $document['framework_ids'] = $escaper->escapeHtml($document['framework_ids']);
        $document['framework_names'] = $escaper->escapeHtml($framework_names);
        $document['control_ids'] = $escaper->escapeHtml($document['control_ids']);
        $document['control_names'] = $escaper->escapeHtml($control_names);
        $document['status'] = $escaper->escapeHtml(get_name_by_value('document_status', $document['status']));
        $document['creation_date'] = format_date($document['creation_date']);
        $document['approval_date'] = format_date($document['approval_date']);
        $document['actions'] = "<div class=\"text-center\"><a class=\"framework-block--edit\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-edit\"></i></a>&nbsp;&nbsp;&nbsp;<a class=\"framework-block--delete\" data-id=\"".((int)$document['id'])."\"><i class=\"fa fa-trash\"></i></a></div>";
        $filtered_documents[] = $document;
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
        SELECT t1.*, t2.version file_version, t2.unique_name, t1.status as document_exceptions_status
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
            des.name as document_exceptions_status
        from
            document_exceptions de
            {$type_based_sql_parts[1]}
            left join user o on o.value = de.owner
            left join user a on a.value = de.approver
            left join compliance_files f on de.file_id=f.id
            left join document_exceptions_status des on de.status = des.value
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
        foreach($group as $row){
            $parent_name = $row['parent_name'];
            $row['children'] = [];

            $row['name'] = "<span class='exception-name'><a href='#' data-id='".((int)$row['value'])."' data-type='{$row['type']}'>{$escaper->escapeHtml($row['name'])}</a></span>";
	    $row['status'] = $escaper->escapeHtml($row['document_exceptions_status']);
            $row['description'] = $escaper->escapeHtml($row['description']);
            $row['justification'] = $escaper->escapeHtml($row['justification']);

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

    echo "<table id='exception-table-{$type}' class='easyui-treegrid exception-table'
            data-options=\"
                iconCls: 'icon-ok',
                animate: false,
                fitColumns: true,
                nowrap: true,
                url: '{$_SESSION['base_url']}/api/exceptions/tree?type={$type}',
                method: 'get',
                idField: 'value',
                treeField: 'name',
                scrollbarSize: 0,
                loadFilter: function(data, parentId) {
                    return data.data;
                },
                onLoadSuccess: function(row, data){
                    fixTreeGridCollapsableColumn();
                    //It's there to be able to have it collapsed on load
                    /*var tree = $('#exception-table-{$type}');
                    tree.treegrid('collapseAll');
                    tree.treegrid('options').animate = true;*/
                    
                    var totalCount = 0;
                    if((data && data.length))
                    {
                        for(var i = 0; i < data.length; i++)
                        {
                            var parent = data[i];
                            if((parent.children && parent.children.length))
                            {
                                totalCount += parent.children.length;
                            }
                        }
                    }
                    
                    $('#{$type}-exceptions-count').text(totalCount);

                    if (typeof wireActionButtons === 'function') {
                        wireActionButtons('{$type}');
                    }
                }
            \">";
    echo "<thead>";

    echo "<th data-options=\"field:'name'\" width='25%'>".$escaper->escapeHtml($lang[ucfirst ($type) . "ExceptionName"])."</th>";
    echo "<th data-options=\"field:'status'\" width='10%'>".$escaper->escapeHtml($lang['Status'])."</th>";
    echo "<th data-options=\"field:'description'\" width='25%'>".$escaper->escapeHtml($lang['Description'])."</th>";
    echo "<th data-options=\"field:'justification'\" width='25%'>".$escaper->escapeHtml($lang['Justification'])."</th>";
    echo "<th data-options=\"field:'next_review_date', align: 'center'\" width='10%'>".$escaper->escapeHtml($lang['NextReviewDate'])."</th>";
    echo "<th data-options=\"field:'actions'\" width='5%'>&nbsp;</th>";
    echo "</thead>\n";

    echo "</table>";
}

function create_exception($name, $status=1, $policy, $control, $owner, $additional_stakeholders, $creation_date, $review_frequency, $next_review_date, $approval_date, $approver, $approved, $description, $justification, $associated_risks) {

    $db = db_open();

    // Create an exception
    $stmt = $db->prepare("
        INSERT INTO
            `document_exceptions` (
                `name`,
                `policy_document_id`,
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

function update_exception($name, $status=1, $policy, $control, $owner, $additional_stakeholders, $creation_date, $review_frequency, $next_review_date, $approval_date, $approver, $approved, $description, $justification, $associated_risks, $id) {


    $original = getExceptionForChangeChecking($id);

    $db = db_open();

    // Create an exception
    $stmt = $db->prepare("
        UPDATE
            `document_exceptions` SET
                `name` = :name,
                `policy_document_id` = :policy_document_id,
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
        select
            (CASE
                WHEN de.policy_document_id > 0 THEN (select p.document_name from documents p where de.policy_document_id = p.id)
                WHEN de.control_framework_id > 0 THEN (select c.short_name from framework_controls c where de.control_framework_id = c.id)
            END)  as parent_name,
            de.name,
            o.name as owner,
            de.additional_stakeholders,
            de.creation_date,
            de.review_frequency,
            de.next_review_date,
            de.approval_date,
            a.name as approver,
            de.description,
            de.justification
        from
            document_exceptions de
            left join user o on o.value = de.owner
            left join user a on a.value = de.approver
        where
            de.value=:id;";

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
        if (strlen($value) == 0)
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
function get_mapping_control_frameworks($control_id)
{
    // Open the database connection
    $db = db_open();
    $sql = "
        SELECT t1.*,t2.name framework_name, t2.description framework_description  FROM `framework_control_mappings` t1
            LEFT JOIN `frameworks` t2 ON t1.framework = t2.value
            WHERE t1.control_id = :control_id 
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // decrypt data
    foreach($frameworks as &$framework){
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
        SELECT m.reference_name as control_number, t1.short_name, t2.name control_class_name, t3.name control_phase_name, t5.name family_short_name, t7.name control_maturity_name, t8.name desired_maturity_name
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
function display_add_framework()
{
    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra())
    {

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("framework");
        $inactive_fields = get_inactive_fields("framework");

        display_detail_framework_fields_add($active_fields);
        display_detail_framework_fields_add($inactive_fields);
    }
    // If the customization extra is disabled, shows fields by default fields
    else
    {
        display_framework_name_edit();

        display_framework_parent_edit();

        display_framework_description_edit();
   }
}
/****************************************************
* FUNCTION: DISPLAY DETAIL FRAMEWORK FIELDS FOR ADD *
*****************************************************/
function display_detail_framework_fields_add($fields)
{
    foreach($fields as $field)
    {
        if($field['is_basic'] == 1)
        {
            if($field['active'] == 0)
            {
                $display = false;
            }
            else
            {
                $display = true;
            }
            
            switch($field['name']){
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

        }
        else
        {
            if($field['active'] == 0)
            {
                continue;
            }
            
            // If customization extra is enabled
            if(customization_extra())
            {
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
function display_framework_name_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "<div class=\"row-fluid\"{$displayString}>
            <label for=\"\">".$escaper->escapeHtml($lang['FrameworkName'])."</label>
            <input type=\"text\" required name=\"framework_name\" value=\"\" class=\"form-control\" autocomplete=\"off\" maxlength=\"100\">
        </div>";
}
/*************************************
* FUNCTION: DISPLAY FRAMEWORK PARENT *
**************************************/
function display_framework_parent_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "<div class=\"row-fluid\"{$displayString}>
                <label for=\"\">".$escaper->escapeHtml($lang['ParentFramework'])."</label>
                <div class=\"parent_frameworks_container\">
                </div>
        </div>";
}
/*************************************
* FUNCTION: DISPLAY FRAMEWORK PARENT *
**************************************/
function display_framework_description_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo "<div class=\"row-fluid\"{$displayString}>
            <label for=\"\">".$escaper->escapeHtml($lang['FrameworkDescription'])."</label>
            <textarea name=\"framework_description\" value=\"\" class=\"form-control\" rows=\"6\" style=\"width:100%;\"></textarea>
        </div>";
}
/**************************************
 * FUNCTION: DISPLAY ADD CONTROL FORM *
 **************************************/
function display_add_control()
{
    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra())
    {

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("control", "", 1);
        $inactive_fields = get_inactive_fields("control", "", 1);

        display_detail_control_fields_add($active_fields);
        display_detail_control_fields_add($inactive_fields);
    }
    // If the customization extra is disabled, shows fields by default fields
    else
    {
        display_control_name_edit();

        display_control_longname_edit();

        display_control_description_edit();

        display_supplemental_guidance_edit();

        display_control_owner_edit();

        display_mapping_framework_edit();

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
function display_detail_control_fields_add($fields)
{
    foreach($fields as $field)
    {
        if($field['is_basic'] == 1)
        {
            if($field['active'] == 0)
            {
                $display = false;
            }
            else
            {
                $display = true;
            }
            
            switch($field['name']){
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

        }
        else
        {
            if($field['active'] == 0)
            {
                continue;
            }
            
            // If customization extra is enabled
            if(customization_extra())
            {
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
function display_control_name_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlShortName']).'</label>
            <input type="text" name="short_name" value="" class="form-control" maxlength="100" required>
        </div>';
}
/**************************************
* FUNCTION: DISPLAY CONTROL LONG NAME *
***************************************/
function display_control_longname_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlLongName']).'</label>
            <input type="text" name="long_name" value="" class="form-control" maxlength="65500">
        </div>';
}
/****************************************
* FUNCTION: DISPLAY CONTROL DESCRIPTION *
*****************************************/
function display_control_description_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlDescription']).'</label>
            <textarea name="description" value="" class="form-control" rows="6" style="width:100%;" maxlength="65500"></textarea>
        </div>';
}
/******************************************
* FUNCTION: DISPLAY SUPPLEMENTAL GUIDANCE *
*******************************************/
function display_supplemental_guidance_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['SupplementalGuidance']).'</label>
            <textarea name="supplemental_guidance" value="" class="form-control" rows="6" style="width:100%;"></textarea>
        </div>';
}
/**********************************
* FUNCTION: DISPLAY CONTROL OWNER *
***********************************/
function display_control_owner_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlOwner']).'</label>
            '.create_dropdown("enabled_users", NULL, "control_owner", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])).'
        </div>';
}
/**********************************************
* FUNCTION: DISPLAY CONTROL MAPPING FRAMEWORK *
***********************************************/
function display_mapping_framework_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <div class="well">
                <h5><span>'.$escaper->escapeHtml($lang['MappedControlFrameworks']).'
                <a href="javascript:void(0);" class="control-block--add-mapping" title="'.$escaper->escapeHtml($lang["Add"]).'"><i class="fa fa-plus"></i></a></span></h5>
                <table width="100%" class="table table-bordered mapping_framework_table">
                    <thead>
                        <tr>
                            <th width="50%">'.$escaper->escapeHtml($lang['Framework']).'</th>
                            <th width="35%">'.$escaper->escapeHtml($lang['Control']).'</th>
                            <th>'.$escaper->escapeHtml($lang['Actions']).'</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>';
}
/**********************************
* FUNCTION: DISPLAY CONTROL CLASS *
***********************************/
function display_control_class_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlClass']).'</label>
             '.create_dropdown("control_class", NULL, "control_class", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])).'
        </div>';
}
/**********************************
* FUNCTION: DISPLAY CONTROL PHASE *
***********************************/
function display_control_phase_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlPhase']).'</label>
            '.create_dropdown("control_phase", NULL, "control_phase", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])).'
        </div>';
}
/***********************************
* FUNCTION: DISPLAY CONTROL NUMBER *
************************************/
function display_control_number_edit2($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlNumber']).'</label>
            <input type="text" name="control_number" value="" class="form-control" maxlength="100">
        </div>';
}
/*********************************************
* FUNCTION: DISPLAY CURRENT CONTROL MATURITY *
**********************************************/
function display_current_maturity_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['CurrentControlMaturity']).'</label>
            '.create_dropdown("control_maturity", get_setting("default_current_maturity"), "control_current_maturity", true, false, true).'
        </div>';
}
/*********************************************
* FUNCTION: DISPLAY DESIRED CONTROL MATURITY *
**********************************************/
function display_desired_maturity_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['DesiredControlMaturity']).'</label>
            '.create_dropdown("control_maturity", get_setting("default_current_maturity"), "control_desired_maturity", true, false, true).'
        </div>';
}
/*************************************
* FUNCTION: DISPLAY CONTROL PRIORITY *
**************************************/
function display_control_priority_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlPriority']).'</label>
            '.create_dropdown("control_priority", NULL, "control_priority", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])).'
        </div>';
}
/***********************************
* FUNCTION: DISPLAY CONTROL FAMILY *
************************************/
function display_control_family_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlFamily']).'</label>
            '.create_dropdown("family", NULL, "family", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])).'
        </div>';
}
/*********************************
* FUNCTION: DISPLAY CONTROL TYPE *
**********************************/
function display_control_type_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlType']).'</label>';
            create_multiple_dropdown("control_type", array(1));
    echo '</div>';
}
/***********************************
* FUNCTION: DISPLAY CONTROL STATUS *
************************************/
function display_control_status_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['ControlStatus']).'</label>
            <select name="control_status" class="form-field form-control">
                <option value="1">'.$escaper->escapeHtml($lang['Pass']).'</option>
                <option value="0">'.$escaper->escapeHtml($lang['Fail']).'</option>
            </select>
        </div>';
}
/***********************************************
* FUNCTION: DISPLAY CONTROL MITIGATION PERCENT *
************************************************/
function display_control_mitigation_percent_edit($display = true)
{
    global $lang, $escaper;

    $display ? $displayString = "" : $displayString = " style=\"display: none;\"";

    echo '<div class="row-fluid" '.$displayString.'>
            <label for="">'.$escaper->escapeHtml($lang['MitigationPercent']).'</label>
            <input type="number" min="0" max="100" name="mitigation_percent" value="" class="form-control">
        </div>';
}
/***************************************************
* FUNCTION: DISPLAY DETAIL CONTROL FIELDS FOR VIEW *
****************************************************/
function display_detail_control_fields_view($panel_name, $fields, $control)
{
    global $lang, $escaper;
    $html = "";
    foreach($fields as $field)
    {
        // Check if this field is main field and details in left panel
        if($field['panel_name'] == $panel_name && $field['tab_index'] == 2)
        {
            if($field['is_basic'] == 1)
            {
                if($field['active'] == 0)
                {
                    continue;
                }
                $field['name'] = str_replace("_view", "", $field['name'], $field['name']);
                switch($field['name']){
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

            }
            else
            {
                if($field['active'] == 0)
                {
                    continue;
                }
                
                // If customization extra is enabled
                if(customization_extra())
                {
                    // Include the extra
                    require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                    $custom_value = get_plan_custom_field_name_by_row_id($field, $control["id"], "control");
                    if($panel_name=="top" || $panel_name=="bottom"){
                        $span1 = "span2";
                        $span2 = "span8";
                    } else {
                        $span1 = "span5";
                        $span2 = "span7";
                    }
                    $html .= "
                        <div class='row-fluid {$panel_name}'>
                            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($field['name'])."</strong>: </div>
                            <div class='{$span2}'>".$escaper->escapeHtml($custom_value)." </div>
                        </div>";
                }
            }
        }
    }
    return $html;
}
/**************************************
* FUNCTION: DISPLAY CONTROL NAME VIEW *
***************************************/
function display_control_name_view($short_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlShortName'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($short_name)." </div>
        </div>";
    return $html;

}
/**************************************
* FUNCTION: DISPLAY CONTROL LONG NAME *
***************************************/
function display_control_longname_view($long_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlLongName'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($long_name)." </div>
        </div>";
    return $html;
}
/****************************************
* FUNCTION: DISPLAY CONTROL DESCRIPTION *
*****************************************/
function display_control_description_view($description, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['Description'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($description)." </div>
        </div>";
    return $html;
}
/******************************************
* FUNCTION: DISPLAY SUPPLEMENTAL GUIDANCE *
*******************************************/
function display_supplemental_guidance_view($supplemental_guidance, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['SupplementalGuidance'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($supplemental_guidance)." </div>
        </div>";
    return $html;
}
/**********************************
* FUNCTION: DISPLAY CONTROL OWNER *
***********************************/
function display_control_owner_view($control_owner_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlOwner'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($control_owner_name)." </div>
        </div>";
    return $html;
}
/**********************************************
* FUNCTION: DISPLAY CONTROL MAPPING FRAMEWORK *
***********************************************/
function display_mapping_framework_view($control_id, $panel_name="")
{
    global $lang, $escaper;

    $mapped_frameworks = get_mapping_control_frameworks($control_id);
    $html = "<div class='row-fluid'>\n";
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
    return $html;
}
/**********************************
* FUNCTION: DISPLAY CONTROL CLASS *
***********************************/
function display_control_class_view($control_class_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlClass'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($control_class_name)." </div>
        </div>";
    return $html;
}
/**********************************
* FUNCTION: DISPLAY CONTROL PHASE *
***********************************/
function display_control_phase_view($control_phase_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlPhase'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($control_phase_name)." </div>
        </div>";
    return $html;
}
/***********************************
* FUNCTION: DISPLAY CONTROL NUMBER *
************************************/
function display_control_number_view2($control_number, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlNumber'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($control_number)." </div>
        </div>";
    return $html;
}
/*********************************************
* FUNCTION: DISPLAY CURRENT CONTROL MATURITY *
**********************************************/
function display_current_maturity_view($control_maturity_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['CurrentControlMaturity'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($control_maturity_name)." </div>
        </div>";
    return $html;
}
/*********************************************
* FUNCTION: DISPLAY DESIRED CONTROL MATURITY *
**********************************************/
function display_desired_maturity_view($desired_maturity_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['DesiredControlMaturity'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($desired_maturity_name)." </div>
        </div>";
    return $html;
}
/*************************************
* FUNCTION: DISPLAY CONTROL PRIORITY *
**************************************/
function display_control_priority_view($control_priority_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlPriority'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($control_priority_name)." </div>
        </div>";
    return $html;
}
/***********************************
* FUNCTION: DISPLAY CONTROL FAMILY *
************************************/
function display_control_family_view($family_short_name, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlFamily'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($family_short_name)." </div>
        </div>";
    return $html;
}
/*********************************
* FUNCTION: DISPLAY CONTROL TYPE *
**********************************/
function display_control_type_view($control_type_ids, $panel_name="")
{
    global $lang, $escaper;
    $control_types = get_names_by_multi_values("control_type", $control_type_ids);
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlType'])."</strong>: </div>
            <div class='{$span2}'>".$escaper->escapeHtml($control_types)." </div>
        </div>";
    return $html;
}
/***********************************
* FUNCTION: DISPLAY CONTROL STATUS *
************************************/
function display_control_status_view($control_status, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $status_text = array("1" => $escaper->escapeHtml($lang["Pass"]), "0" => $escaper->escapeHtml($lang["Fail"]));
    
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['ControlStatus'])."</strong>: </div>
            <div class='{$span2}'>".$status_text[$control_status]." </div>
        </div>";
    return $html;
}
/***********************************************
* FUNCTION: DISPLAY CONTROL MITIGATION PERCENT *
************************************************/
function display_control_mitigation_percent_view($mitigation_percent, $panel_name="")
{
    global $lang, $escaper;
    if($panel_name=="top" || $panel_name=="bottom"){
        $span1 = "span2";
        $span2 = "span8";
    } else {
        $span1 = "span5";
        $span2 = "span7";
    }
    $html = "
        <div class='row-fluid {$panel_name}'>
            <div class='{$span1} text-right'><strong>".$escaper->escapeHtml($lang['MitigationPercent'])."</strong>: </div>
            <div class='{$span2}''>".$escaper->escapeHtml($mitigation_percent)." </div>
        </div>";
    return $html;
}

?>
