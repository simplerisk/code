<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/bootstrap.php'));
require_once(realpath(__DIR__ . '/cvss.php'));
require_once(realpath(__DIR__ . '/services.php'));
require_once(realpath(__DIR__ . '/alerts.php'));
require_once(realpath(__DIR__ . '/tf_idf_enrichment.php'));
require_once(realpath(__DIR__ . '/Components/DocumentTextHandler.php'));
require_once(realpath(__DIR__ . '/queues.php'));
require_once(realpath(__DIR__ . '/extras.php'));
// Declared directly rather than relied on transitively (CLAUDE.md, "Function
// Reachability Across Files"): framework_acquisition_paths() below calls
// has_permission() (permissions.php) and is_admin() /
// complianceforge_scf_extra() / import_export_extra() (functions.php). Neither
// file requires this one, so there is no cycle to open.
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/functions.php'));
// settings_catalog_entry_for_extra() and resolve_extra_affordance(), which
// framework_acquisition_path_states() below asks for the locked-affordance
// decision. Same reachability rule: this file is included from entry points
// (governance/index.php, the API) that pull in nothing else of the Settings
// Hub, and settings_catalog.php declares functions only, so requiring it here
// costs nothing and an undeclared call would be a fatal rather than a bad
// answer.
require_once(realpath(__DIR__ . '/settings_catalog.php'));
// APPLICABILITY_DEVIATION_STATES, read by count_excluded_controls(). Declared
// directly rather than relied on transitively, per CLAUDE.md's reachability
// rule: this file is included from entry points that pull in nothing else of
// the applicability domain, and an undeclared constant is a fatal error rather
// than a wrong answer.
require_once(realpath(__DIR__ . '/applicability.php'));

// Include the language file
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

use SimpleRisk\DocumentHandlers\DocumentTextExtractor;
use SimpleRisk\DocumentHandlers\UnsupportedDocumentException;
use SimpleRisk\DocumentHandlers\DocumentTooLargeException;

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

    // Include the required file
    require_once(realpath(__DIR__ . '/../extras/complianceforgescf/index.php'));
    $scf_framework_id = complianceforge_scf_extra() ? (int)get_scf_framework_id(null,true) : 0;

    $frameworks = get_frameworks($status);

    foreach($frameworks as &$framework){
        $framework_value = (int)$framework['value'];
        $framework['name'] = $escaper->escapeHtml($framework['name']);
        // The description column is rendered as raw HTML in the treegrid (it's a
        // WYSIWYG field), so purify it at this render boundary as defense-in-depth.
        $framework['description'] = purify_rich_text_output($framework['description'] ?? '');
        $framework['actions'] = "
            <div class='d-flex justify-content-center align-items-center w-100'>
                <a class='framework-block--edit' data-id='{$framework_value}'>
                    <i class='fa fa-edit'></i>
                </a>" .
        (  // The root complianceforge framework can't be deleted
            $scf_framework_id && $scf_framework_id === $framework_value ? "" : "
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

/****************************************************************
 * FUNCTION: BUILD FRAMEWORK RAIL ROWS                            *
 * Purpose-built shape for the Define Control Frameworks page's   *
 * framework rail (Task 22, api_v2_governance_frameworks_rail()   *
 * below) -- flattens a list of framework rows (each carrying     *
 * `value`/`parent`/`name`, the shape get_frameworks() returns)    *
 * into rail order: parent-before-child (depth-first), preserving  *
 * the incoming array's order as SIBLING order, with a `depth` for *
 * indentation. This function makes no sorting decision of its own *
 * -- it groups by `parent` in whatever order the caller hands it,  *
 * so the caller's array order becomes each parent's child order.   *
 * Task 27: api_v2_governance_frameworks_rail() now sorts its input *
 * alphabetically by name (natural, case-insensitive) before        *
 * calling this, which is what produces alphabetical siblings --    *
 * this function is unaware that's happening and would just as      *
 * happily preserve `order`-column order if a future caller passed  *
 * that instead.                                                    *
 *                                                                  *
 * Unlike the legacy get_frameworks_as_treegrid() above (which only *
 * nests when $status===1 and returns a flat, unindented list for  *
 * every other status -- a real behavior difference, verified by   *
 * reading the code, not assumed), this nests regardless of which  *
 * status was requested: a status filter narrows WHICH frameworks   *
 * are in scope, it has no bearing on whether the ones that remain  *
 * still form a tree.                                               *
 *                                                                  *
 * A framework whose parent isn't present in $frameworks -- because *
 * a status filter excluded it, e.g. an Inactive child of an Active *
 * parent under the Inactive-only rail view -- is treated as a      *
 * depth-0 root rather than silently dropped: an orphaned child      *
 * rendered under a hidden parent would be unreachable, so it        *
 * surfaces at the top level instead of vanishing.                   *
 *                                                                    *
 * Pure: no DB, no globals, no output -- directly unit-testable       *
 * against fixture rows.                                              *
 ****************************************************************/
function build_framework_rail_rows(array $frameworks): array {
    $known_values = [];
    foreach ($frameworks as $f) {
        $known_values[(int)$f['value']] = true;
    }

    $by_parent = [];
    foreach ($frameworks as $f) {
        $parent = (int)($f['parent'] ?? 0);
        if ($parent !== 0 && !isset($known_values[$parent])) {
            // Orphan: this row's parent isn't in the scoped set -- treat as root.
            $parent = 0;
        }
        $by_parent[$parent][] = $f;
    }

    $flat = [];
    $visit = function ($parent_value, $depth) use (&$visit, &$by_parent, &$flat) {
        foreach ($by_parent[$parent_value] ?? [] as $f) {
            $flat[] = [
                'value' => (int)$f['value'],
                'name'  => (string)$f['name'],
                'depth' => $depth,
            ];
            $visit((int)$f['value'], $depth + 1);
        }
    };
    $visit(0, 0);

    return $flat;
}

/*******************************************************************
 * FUNCTION: SORT FRAMEWORKS BY NAME                                *
 * Task 27 -- the framework rail's own order: alphabetical          *
 * siblings, case-insensitive, natural/numeric-aware ("ISO 27002"   *
 * sorts after "ISO 27001", not before "ISO 27010" the way a        *
 * byte-wise sort would put it -- strnatcasecmp() is both in one    *
 * call). A flat pre-sort pass over whatever list it's handed --    *
 * it has no notion of parent/child at all. Feeding its output      *
 * into build_framework_rail_rows() (which groups by `parent` in    *
 * the order it's given) is what turns "the whole list sorted by    *
 * name" into "siblings sorted by name within each parent's group", *
 * without ever flattening the tree to do it: a stable sort (PHP's  *
 * usort() has been stable since 8.0) preserves each parent's       *
 * children in their post-sort relative order when they're later    *
 * partitioned by `parent`.                                         *
 *                                                                   *
 * Pure: no DB, no globals -- directly unit-testable.                *
 *******************************************************************/
function sort_frameworks_by_name(array $frameworks): array {
    usort($frameworks, function ($a, $b) {
        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
    return $frameworks;
}

/*************************************************************************
 * FUNCTION: GET SCF ORIGIN FRAMEWORK IDS                                *
 * Task 27 -- the Define Control Frameworks page's rail marks a          *
 * framework with an "SCF" origin chip when it was created from the      *
 * ComplianceForge SCF Extra's authoritative-source import               *
 * (extras/complianceforgescf). The link lives in                        *
 * scf_authoritative_sources.simplerisk_framework_id, an EXTRA-ONLY      *
 * table -- CLAUDE.md's Core/Extra DB boundary forbids Core querying it  *
 * unguarded, since a customer without the Extra would hit a missing-    *
 * table error. Both checks matter and aren't redundant:                 *
 *   - is_extra_installed() -- the Extra's index.php is present on disk  *
 *   - table_exists()       -- its schema has actually been created      *
 * An Extra can be installed (shipped on disk) without ever having been  *
 * activated, in which case create_complianceforge_scf_tables() has      *
 * never run and the table genuinely doesn't exist yet. Either check     *
 * failing degrades to an empty list -- no chips -- rather than an       *
 * error.                                                                 *
 *************************************************************************/
function get_scf_origin_framework_ids(): array {
    if (!is_extra_installed('complianceforgescf') || !table_exists('scf_authoritative_sources')) {
        return [];
    }

    $db = db_open();
    $stmt = $db->prepare(
        "SELECT DISTINCT simplerisk_framework_id FROM scf_authoritative_sources
         WHERE enabled = 1 AND simplerisk_framework_id IS NOT NULL"
    );
    $stmt->execute();
    $ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'simplerisk_framework_id'));
    db_close($db);

    return $ids;
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
        // NOTE: this is a pure getter reused by write paths (update_framework()
        // preserves an unchanged description by reading it back here), so it must
        // NOT purify — that would silently re-persist a normalized value on an
        // unrelated field update. Rich-text purification for display happens at the
        // OUTPUT boundaries via purify_rich_text_output() (treegrid + API responses).
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
    global $escaper;
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

    $message = '';
    if($status == 1){
        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset -- $framework rows include 'name' from the upstream query
        $message = "A framework named \"" . $escaper->escapeHtml($framework['name']) . "\" was activated by the \"" . $escaper->escapeHtml($_SESSION['user'] ?? 'unknown') . "\" user.";
    }
    elseif($status == 2){
        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
        $message = "A framework named \"" . $escaper->escapeHtml($framework['name']) . "\" was deactivated by the \"" . $escaper->escapeHtml($_SESSION['user'] ?? 'unknown') . "\" user.";
    }
    write_log($framework_id+1000, (int)($_SESSION['uid'] ?? 0), $message, 'framework');

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
        SELECT t1.*, GROUP_CONCAT(DISTINCT f.value) framework_ids, GROUP_CONCAT(DISTINCT f.name) framework_names, t2.name control_class_name, t3.name control_phase_name, t4.name control_priority_name, t5.name family_short_name, t6.name control_owner_name, t7.name control_maturity_name, t8.name desired_maturity_name, group_concat(distinct ctype.value) control_type_ids, GROUP_CONCAT(distinct ctype.name) control_type_names, CASE t1.control_status WHEN 1 THEN 'Pass' WHEN 0 THEN 'Fail' ELSE 'Not Tested' END control_status_name, GROUP_CONCAT(DISTINCT m.reference_name) reference_name, GROUP_CONCAT(DISTINCT m.reference_text) reference_text
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
            || (stripos((string)$control['reference_text'], $control_text) !== false)
            || (stripos((string)$control['control_maturity_name'], $control_text) !== false)
            || (stripos((string)$control['desired_maturity_name'], $control_text) !== false)
            || (stripos((string)$control['mitigation_percent'], $control_text) !== false)
            || (stripos((string)$control['control_type_names'], $control_text) !== false)
            || (stripos((string)$control['control_status_name'], $control_text) !== false)
        )
        {
            $filtered_controls[] = $control;
            continue;
        }

        // Search for the Mapped Assets Content
        $mapped_assets_match = false;
        $mapped_assets = get_control_to_assets((int)$control['id']);
        foreach ($mapped_assets as $mapped_asset) {
            if (stripos((string)$mapped_asset['control_maturity_name'], $control_text) !== false) {
                $mapped_assets_match = true;
                break;
            }

            $asset_names_array = [];
            if (!empty($mapped_asset['asset_name'])) {
                $asset_names_array[] = $mapped_asset['asset_name'];
            }
            if (!empty($mapped_asset['asset_group_name'])) {
                $asset_names_array[] = $mapped_asset['asset_group_name'];
            }
            $asset_names = implode(",", $asset_names_array );

            if (stripos((string)$asset_names, $control_text) !== false) {
                $mapped_assets_match = true;
                break;
            }
        }

        if ($mapped_assets_match) {
            $filtered_controls[] = $control;
            continue;
        }

        // Search for the Custom Fields Content if customization extra is enabled
        if (customization_extra()) {
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            $custom_search_fields = [];
            $active_fields = get_active_fields("control", "", 2);
            foreach ($active_fields as $field) {
                if ((int)$field['is_basic'] === 0 && (int)$field['active'] === 1 && (int)$field['tab_index'] === 2) {
                    $custom_search_fields[] = $field;
                }
            }

            $custom_fields_match = false;
            if (!empty($custom_search_fields)) {
                foreach ($custom_search_fields as $field) {
                    $custom_value = get_plan_custom_field_name_by_row_id($field, $control["id"], "control");
                    $custom_value_text = trim(strip_tags((string)$custom_value));
                    if ($custom_value_text !== "" && stripos($custom_value_text, $control_text) !== false) {
                        $custom_fields_match = true;
                        break;
                    }
                }
            }

            if ($custom_fields_match) {
                $filtered_controls[] = $control;
                continue;
            }
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
    global $escaper;
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
    $stmt = $db->prepare("INSERT INTO `frameworks` (`name`, `description`, `parent`, `status`, `order`) VALUES (?, ?, ?, ?, ?)");
    $insert_args = [$try_encrypt_name, $try_encrypt_descryption, (int)$parent, (int)$status, (int)$order];
    $stmt->execute($insert_args);

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

    $message = "A new framework named \"" . $escaper->escapeHtml($name) . "\" was created by username \"" . $escaper->escapeHtml($_SESSION['user'] ?? 'unknown') . "\".";
    write_log((int)$framework_id + 1000, (int)($_SESSION['uid'] ?? 0), $message, "framework");

    // Close the database connection
    db_close($db);

    trigger_workflow_event('framework.created', [
        'framework_id' => $framework_id,
        'name'         => $name,
    ]);

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

    global $lang, $escaper;

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
    // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset -- $framework rows include 'description' from the upstream query
    $framework['description'] = $description === false ? try_encrypt($framework['description']) : try_encrypt(purify_html($description));
    $framework['parent'] = $parent === false ? $framework['parent'] : $parent;

    // Create a framework
    $stmt = $db->prepare("UPDATE `frameworks` SET `name`=:name, `description`=:description, `parent`=:parent WHERE value=:framework_id;");
    $stmt->bindParam(":name", $framework['name'], PDO::PARAM_STR, 100);
    $stmt->bindParam(":description", $framework['description'], PDO::PARAM_STR, 1000);
    $stmt->bindParam(":parent", $framework['parent'], PDO::PARAM_INT);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();

    $message = "A framework named \"" . $escaper->escapeHtml($name) . "\" was updated by username \"" . $escaper->escapeHtml($_SESSION['user']) . "\".";
    write_log((int)$framework_id + 1000, $_SESSION['uid'] ?? 0, $message, "framework");

    // Close the database connection
    db_close($db);

    trigger_workflow_event('framework.updated', [
        'framework_id' => $framework_id,
        'name'         => $name,
    ]);

    return true;
}

/**
 * `scope_statement` and `default_inclusion_justification` are TEXT columns, and
 * MySQL measures TEXT in BYTES — so the guard below uses strlen(), not
 * mb_strlen(). Mirrors APPLICABILITY_NARRATIVE_MAX_BYTES (includes/applicability.php),
 * which guards the per-control `narrative` for exactly the same reason; it is
 * redeclared here rather than required because governance.php does not otherwise
 * depend on the applicability domain file.
 */
const FRAMEWORK_SOA_FIELD_MAX_BYTES = 65535;

/****************************************************
 * FUNCTION: UPDATE FRAMEWORK SOA FIELDS            *
 ****************************************************
 * Stores the two framework-level facts a Statement of Applicability needs on its
 * cover (spec §5.4a):
 *
 *   scope_statement                  — the scope the framework is certified against
 *   default_inclusion_justification  — how inclusion was determined, for every
 *                                      control that is simply applicable
 *
 * WHY THEY LIVE ON THE FRAMEWORK. ISO/IEC 27001:2022 clause 6.1.3(d) wants an SoA
 * that states its scope and justifies both inclusions and exclusions. Exclusions
 * are per control and carry their own narrative (`framework_control_applicability`),
 * but inclusion is the DEFAULT — "applicable" is never stored — so demanding a
 * per-control rationale for it would mean 1,535 identical fields nobody would fill
 * in. One editable sentence per framework fills the SoA's Justification column for
 * every applicable control instead.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE TWO FIELDS HAVE DIFFERENT RENDERING CONTRACTS. THIS IS DELIBERATE.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * `scope_statement` IS RICH TEXT (HugeRTE), like `frameworks`.`description` two
 * rows above it in the same modal. It is cover PROSE — paragraphs, and very
 * often the list of entities, sites and systems in scope — and it is rendered
 * ONCE, at the top of the document, by sinks that can all render HTML (the
 * screen inserts it, and Dompdf renders it). So it is PURIFIED HERE, on write,
 * exactly as update_framework()'s `$description` is. Consumers render it RAW —
 * and purify again at their own boundary as defence in depth (build_soa_cover(),
 * includes/soa.php), because a row written before this field became rich text,
 * or written straight into the database, has never been through this path.
 *
 * A statement whose markup renders NOTHING is stored as '' — see
 * rich_text_is_blank() (includes/functions.php). Select-all-delete in HugeRTE
 * submits `<p><br></p>`, which HTMLPurifier keeps; storing that would leave a
 * framework whose scope statement is present-but-invisible, and permanently
 * suppress the SoA's missing-scope prompt.
 *
 * `default_inclusion_justification` IS STILL RAW PLAIN TEXT — stored VERBATIM
 * (trimmed only): no purify_html(), no escaping. It is not cover prose. It is
 * the sentence the SoA's Justification COLUMN prints, once per applicable
 * control — ~1,500 times on an SCF-sized framework, inside a spreadsheet cell
 * and inside a 28%-wide Dompdf table column, both of which would have to flatten
 * markup back out again. Its consumers MUST escape at their own sink
 * ($escaper->escapeHtml() in PHP, .text() in JS); escaping it here would
 * double-encode a justification containing "R&D < 500 users" at that sink. Do
 * not "tidy" the pair into symmetry — tests/unit/FrameworkScopeStatementRichTextTest.php
 * asserts the asymmetry in both directions.
 *
 * NOT ENCRYPTED: the Encrypted Database Extra's field map covers exactly
 * `frameworks`.`name` and `frameworks`.`description` (extras/encryption/index.php).
 * Encrypting these two without registering them there would leave them ciphertext
 * after a deactivation/restore — unreadable, with no error.
 *
 * `false` means "leave this field alone" — the same convention update_framework()
 * uses for `$description`. An empty string is a deliberate CLEAR, so a customer can
 * remove a wrong statement and get the SoA export's prompt-when-missing back.
 *
 * A no-op writes NO audit entry. The edit modal re-submits every field on every
 * save, so auditing unconditionally would file a "scope statement changed" entry
 * each time somebody renamed the framework, burying the real changes an auditor
 * came to read — the same reasoning set_applicability() applies to a clear that
 * removed nothing.
 *
 * AUTHORIZATION IS THE CALLER'S JOB, as everywhere else in this file: the API
 * layer gates on `modify_frameworks` before calling in.
 *
 * @param  int          $framework_id
 * @param  string|false $scope_statement                 false to leave unchanged.
 * @param  string|false $default_inclusion_justification false to leave unchanged.
 * @return bool  True when the stored values match what was asked for (including
 *               the nothing-to-do case); false when the framework does not exist.
 * @throws InvalidArgumentException when a value is longer than the column can
 *                                  hold AFTER purification. Nothing is written.
 */
function update_framework_soa_fields($framework_id, $scope_statement = false, $default_inclusion_justification = false) {

    global $escaper;

    $framework_id = (int)$framework_id;

    if ($framework_id <= 0) {
        return false;
    }

    // THE SCOPE STATEMENT IS RICH TEXT — purified here, on write, before anything
    // else looks at it. Everything below (the byte cap, the change comparison,
    // the audit entry) therefore measures WHAT WILL BE STORED rather than what
    // was typed, which is the only length a TEXT column and an auditor care
    // about. See this function's rendering-contract note above for why its
    // sibling field is deliberately left alone.
    if ($scope_statement !== false) {

        $scope_statement = purify_html(trim((string)$scope_statement));

        // `<p><br></p>` is what an emptied HugeRTE box submits, and HTMLPurifier
        // keeps it. Stored as-is it would be a scope statement that renders as
        // nothing yet tests as present — which permanently suppresses the SoA's
        // missing-scope prompt. Collapse it to the deliberate-clear state.
        if (rich_text_is_blank($scope_statement)) {
            $scope_statement = '';
        }
    }

    $fields = [
        'scope_statement'                 => $scope_statement,
        'default_inclusion_justification' => $default_inclusion_justification,
    ];

    // Validate everything before opening a connection: an over-long value must
    // cost a rejection, not a half-applied write. MySQL would otherwise answer
    // with a raw PDO exception under strict mode, or — worse, without it —
    // silently truncate an audited compliance statement.
    //
    // THE <textarea> maxlength IS GONE for the scope statement — a rich-text
    // widget stores markup, so a byte cap over the raw box no longer maps to
    // anything the user typed — which makes this the ONLY enforcement left. It
    // refuses, with a message the modal shows inline; it never truncates.
    foreach ($fields as $column => $value) {

        if ($value === false) {
            continue;
        }

        if (strlen(trim((string)$value)) > FRAMEWORK_SOA_FIELD_MAX_BYTES) {
            // The rejected value is deliberately not echoed back: this message
            // becomes an API status_message, and the page shows those in a toast,
            // which renders HTML (see CLAUDE.md).
            throw new InvalidArgumentException(
                "The {$column} is longer than " . FRAMEWORK_SOA_FIELD_MAX_BYTES . " bytes."
            );
        }
    }

    $db = db_open();

    $stmt = $db->prepare(
        "SELECT `scope_statement`, `default_inclusion_justification` FROM `frameworks` WHERE `value` = :framework_id"
    );
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {

        db_close($db);

        write_debug_log("SoA fields requested for framework {$framework_id}, which does not exist.", 'warning');

        return false;
    }

    // Only the fields that were asked for AND actually differ get written, so a
    // re-save of identical values is a true no-op down to the audit trail.
    $changed = [];

    foreach ($fields as $column => $value) {

        if ($value === false) {
            continue;
        }

        $value = trim((string)$value);

        if ($value !== (string)($current[$column] ?? '')) {
            $changed[$column] = $value;
        }
    }

    if (!$changed) {

        db_close($db);

        return true;
    }

    $assignments = [];
    $params      = [':framework_id' => $framework_id];

    foreach ($changed as $column => $value) {
        // The column names are this function's own literals, never caller input.
        $assignments[]           = "`{$column}` = :{$column}";
        $params[':' . $column]   = $value;
    }

    $stmt = $db->prepare("UPDATE `frameworks` SET " . implode(', ', $assignments) . " WHERE `value` = :framework_id;");
    $stmt->execute($params);

    db_close($db);

    // `frameworks`.`name` is encrypted when the Encrypted Database Extra is on, so
    // read it through get_name_by_value() rather than selecting it directly.
    $framework_name = $escaper->escapeHtml(get_name_by_value('frameworks', $framework_id, ''));
    $actor          = $escaper->escapeHtml($_SESSION['user'] ?? 'unknown');
    $field_names    = $escaper->escapeHtml(implode(', ', array_keys($changed)));

    // The +1000 offset is write_log()'s convention for a non-risk subject; it
    // subtracts 1000 back out before storing. The message names WHICH fields
    // changed but never their contents — an SoA scope statement can run to
    // thousands of characters.
    write_log(
        $framework_id + 1000,
        (int)($_SESSION['uid'] ?? 0),
        "The Statement of Applicability fields ({$field_names}) of framework \"{$framework_name}\" were updated by username \"{$actor}\".",
        "framework"
    );

    write_debug_log(
        "Updated SoA fields (" . implode(', ', array_keys($changed)) . ") on framework {$framework_id}.",
        'info'
    );

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
    global $escaper;
    $framework = get_framework($framework_id);
    // Check framework ID is valid
    if($framework)
    {
        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset -- $framework rows include 'parent' and 'name' from the upstream query
        $parent = $framework['parent'];
        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
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

        // Delete custom_framework_data related with framework ID (no-op if customization extra is disabled)
        call_extra_function(
            'customization_extra',
            __DIR__ . '/../extras/customization/index.php',
            'delete_custom_data_by_row_id',
            [$framework_id, "framework"]
        );

        $message = "A framework named \"" . $escaper->escapeHtml($name) . "\" was deleted by username \"" . $escaper->escapeHtml($_SESSION['user'] ?? 'unknown') . "\".";
        write_log((int)$framework_id + 1000, (int)($_SESSION['uid'] ?? 0), $message, "framework");

        // Removing residual junction table entries
        cleanup_after_delete("frameworks");

        trigger_workflow_event('framework.deleted', [
            'framework_id' => $framework_id,
            'name'         => $name,
        ]);

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

    // Sanitizing input that comes from the WYSIWYG editor or outside sources
    $description = purify_html($description);
    $supplemental_guidance = purify_html($supplemental_guidance);

    $framework_ids = !empty($control['framework_ids']) ? (is_array($control['framework_ids']) ? $control['framework_ids'] : explode(",", $control['framework_ids'])) : [];
    $control_owner = isset($control['control_owner']) ? (int)$control['control_owner'] : 0;
    $control_class = isset($control['control_class']) ? (int)$control['control_class'] : 0;
    $control_phase = isset($control['control_phase']) ? (int)$control['control_phase'] : 0;
    $control_number = isset($control['control_number']) ? $control['control_number'] : "";
    $control_current_maturity = isset($control['control_current_maturity']) ? $control['control_current_maturity'] : get_setting("default_current_maturity");
    $control_desired_maturity = isset($control['control_desired_maturity']) ? $control['control_desired_maturity'] : get_setting("default_desired_maturity");
    $control_priority = isset($control['control_priority']) ? (int)$control['control_priority'] : 0;
    // Standalone (control_type 1) is the default type for a control that arrives
    // without one, and the default is LOAD-BEARING -- do not "align" it with
    // update_framework_control()'s empty default. The bulk creators
    // (extras/complianceforgescf, extras/ucf, extras/import-export) never set
    // control_type, so this is the only thing that gives an SCF/UCF/imported
    // control a type at all, and it matches both the upgrade that backfilled
    // every pre-existing control with type 1 (includes/upgrade.php) and the Add
    // Control modal's pre-selected value (display_control_type_edit()). Making
    // it [] would leave those controls untyped and therefore invisible to the
    // control-type filter and its facet counts.
    //
    // The API create paths (addControlResponse()/createControlCrud()) pass an
    // explicit [] when the form selected nothing, which is a deliberate "no
    // types" and correctly bypasses this default -- creating nothing can't
    // destroy anything, so create needs no preserve-vs-clear distinction the way
    // update does.
    $control_type = isset($control['control_type']) ? (array)$control['control_type'] : [1];
    // Not Tested (2) is the default for new controls — see the governance dashboard
    // design. SCF installs and Import-Export inherit this (they never set it).
    $control_status = isset($control['control_status']) ? (int)$control['control_status'] : 2;
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

    // Update the control to document mappings for the control
    $queue_task_payload = [
        'triggered_at'  => time(),
        'control_id'    => (int)$control_id,
        'refresh'       => true,
    ];
    queue_task($db, 'core_control_update', $queue_task_payload, 25, 5, 3600);

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
    $message = "A new control named \"" . $escaper->escapeHtml($short_name) . "\" was created by username \"" . $escaper->escapeHtml($user) . "\".";
    write_log((int)$control_id + 1000, $uid, $message, "control");

    trigger_workflow_event('control.created', [
        'control_id'    => $control_id,
        'short_name'    => $short_name,
        'control_owner' => $control_owner,
    ]);

    return $control_id;
}

/********************************************
 * FUNCTION: UPDATE FRAMEWORK CONTROL BY ID *
 ********************************************/
function update_framework_control($control_id, $control){
    global $escaper;
    $short_name = isset($control['short_name']) ? $control['short_name'] : "";
    $long_name = isset($control['long_name']) ? $control['long_name'] : "";
    $description = isset($control['description']) ? $control['description'] : "";
    $supplemental_guidance = isset($control['supplemental_guidance']) ? $control['supplemental_guidance'] : "";

    // Sanitizing input that comes from the WYSIWYG editor or outside sources
    $description = purify_html($description);
    $supplemental_guidance = purify_html($supplemental_guidance);

    $framework_ids = !empty($control['framework_ids']) ? (is_array($control['framework_ids']) ? $control['framework_ids'] : explode(",", $control['framework_ids'])) : [];
    $control_owner = isset($control['control_owner']) ? (int)$control['control_owner'] : 0;
    $control_class = isset($control['control_class']) ? (int)$control['control_class'] : 0;
    $control_phase = isset($control['control_phase']) ? (int)$control['control_phase'] : 0;
    $control_number = isset($control['control_number']) ? $control['control_number'] : "";
    $control_current_maturity = isset($control['control_current_maturity']) ? (int)$control['control_current_maturity'] : 0;
    $control_desired_maturity = isset($control['control_desired_maturity']) ? (int)$control['control_desired_maturity'] : 0;
    $control_priority = isset($control['control_priority']) ? (int)$control['control_priority'] : 0;
    // Control types are a MANY-TO-MANY (framework_control_type_mappings), written
    // below as delete-then-insert. Omission therefore has to mean PRESERVE, not
    // "replace with nothing": this used to default to [] and run the DELETE
    // unconditionally, so a caller that never mentioned control_type -- including
    // a PATCH that only touched short_name -- silently wiped every type the
    // control had. Same trap Task 20 closed for the framework and asset mappings
    // one function over, missed here because this table's rewrite is inline
    // rather than behind an isset()-gated save_* call.
    //
    // An explicit but EMPTY set still clears, which is what makes the Edit
    // modal's "deselect every type" reach the database. Distinguishing the two
    // is the caller's job -- see updateControlById() (includes/api.php) and the
    // control_type_submitted marker display_control_type_edit() emits.
    $control_type_submitted = isset($control['control_type']);
    $control_type = $control_type_submitted ? (array)$control['control_type'] : [];
    // Preserve the existing status when the caller doesn't supply one (an update
    // of other fields must not silently reset control_status).
    if (isset($control['control_status'])) {
        $control_status = (int)$control['control_status'];
    } else {
        $existing = get_framework_control($control_id);
        $control_status = isset($existing['control_status']) ? (int)$existing['control_status'] : 2;
    }
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
    
    // Update the control to document mappings for the control
    $queue_task_payload = [
        'triggered_at'  => time(),
        'control_id'    => (int)$control_id,
        'refresh'       => true,
    ];
    queue_task($db, 'core_control_update', $queue_task_payload, 25, 5, 3600);

    // The DELETE and the re-INSERT are ONE operation and share ONE guard. The
    // DELETE used to run unconditionally, outside any guard at all, which is
    // exactly why an unmentioned control_type was destructive rather than
    // merely ignored.
    if ($control_type_submitted) {
        $stmt = $db->prepare("DELETE FROM `framework_control_type_mappings` WHERE `control_id` = :control_id");
        $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
        $stmt->execute();

        foreach ($control_type as $type) {
            $type = (int)$type;
            if ($type <= 0) {
                continue;
            }
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
    
    // Save custom field values for control (no-op if customization extra is disabled)
    call_extra_function(
        'customization_extra',
        __DIR__ . '/../extras/customization/index.php',
        'save_custom_field_values',
        [$control_id, "control"]
    );

    $user = isset($_SESSION['user'])?$_SESSION['user']:"";
    $uid = isset($_SESSION['uid'])?$_SESSION['uid']:"";
    $message = "A control named \"" . $escaper->escapeHtml($short_name) . "\" was updated by username \"" . $escaper->escapeHtml($user) . "\".";
    write_log((int)$control_id + 1000, $uid, $message, "control");

    trigger_workflow_event('control.updated', [
        'control_id'    => $control_id,
        'short_name'    => $short_name,
        'control_owner' => $control_owner,
    ]);

    // Add residual risk scoring history
    add_residual_risk_scoring_histories_for_control($control_id);
    
    return true;
}

/***************************************************************
 * FUNCTION: ADD RESIDUAL RISK SCORING HISTORIES FOR A CONTROL *
 ***************************************************************/
function add_residual_risk_scoring_histories_for_control(int $control_id): void
{
    add_residual_risk_scoring_histories_for_controls([$control_id]);
}

/**************************************************************
 * FUNCTION: ADD RESIDUAL RISK SCORING HISTORIES FOR CONTROLS *
 **************************************************************/
function add_residual_risk_scoring_histories_for_controls(array $control_ids)
{
    if (empty($control_ids)) return;

    $db = db_open();

    try {
        // Step 1: Get all distinct risk_ids for the given controls in a single query
        $placeholders = implode(',', array_fill(0, count($control_ids), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT r.id AS risk_id
            FROM mitigations m
            INNER JOIN mitigation_to_controls mtc ON m.id = mtc.mitigation_id
            INNER JOIN risks r ON r.id = m.risk_id
            WHERE mtc.control_id IN ($placeholders)
        ");
        $stmt->execute($control_ids);
        $risk_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // Step 2: Loop over the risk_ids and add residual risk scoring history
        foreach ($risk_ids as $risk_id) {
            // Adjust ID if needed (your +1000 logic)
            $residual_risk = get_residual_risk((int)$risk_id + 1000);
            add_residual_risk_scoring_history($risk_id, $residual_risk);
        }

    } finally {
        db_close($db);
    }
}

/**************************************
 * FUNCTION: DELETE FRAMEWORK CONTROL *
 **************************************/
function delete_framework_control(int $control_id)
{
    // Call the batch function passing a single control_id value
    delete_framework_controls_batch([$control_id]);
}

/******************************************************
 * FUNCTION: FRAMEWORK CONTROLS DELETE CLASSIFICATION *
 ******************************************************
 * Reads the rows delete_framework_controls_batch() classifies, and the ONE
 * fact that classification turns on: whether a control carries any row in
 * test_control_map.
 *
 * WHY THIS IS A FUNCTION. Deleting a control is not one outcome, it is two
 * (see the split in delete_framework_controls_batch() below): a control WITH
 * test history is retained in a deleted state, a control WITHOUT is removed
 * permanently. A confirmation that tells the user which of those is about to
 * happen therefore has to compute the same split the write will -- and a
 * second copy of "EXISTS(... test_control_map ...)" written for the preview is
 * exactly the count-disagrees-with-what-happens defect this page has already
 * been through three times (Tasks 29 / 37 / 40). On a reversible write that
 * was a consistency bug; on a delete it is data loss. So both callers read
 * this, and there is one definition of the split.
 *
 * $db is passed IN rather than opened here so the writer can classify inside
 * its own transaction, under the same connection that will do the deleting.
 * $lock adds the `FOR UPDATE` the writer needs and the reader must not take:
 * a preview that row-locked 1,535 controls would block the very writes it is
 * previewing.
 *
 * @return array<int, array{id:int, short_name:?string, has_tests:bool}> keyed by control id.
 */
function framework_controls_delete_classification(PDO $db, array $control_ids, bool $lock = false): array
{
    if (empty($control_ids)) return [];

    $control_ids = array_values(array_map('intval', $control_ids));
    $placeholders = implode(',', array_fill(0, count($control_ids), '?'));

    $stmt = $db->prepare("
        SELECT id, short_name,
        EXISTS(SELECT 1 FROM test_control_map WHERE framework_control_id = fc.id) AS has_tests
        FROM framework_controls AS fc
        WHERE id IN ($placeholders)
        " . ($lock ? "FOR UPDATE" : "") . "
    ");
    $stmt->execute($control_ids);

    $classified = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $classified[(int)$row['id']] = [
            'id'         => (int)$row['id'],
            'short_name' => $row['short_name'],
            'has_tests'  => (bool)$row['has_tests'],
        ];
    }

    return $classified;
}

/**********************************************
 * FUNCTION: FRAMEWORK CONTROLS DELETE SPLIT  *
 **********************************************
 * The soft/hard split a delete of $control_ids WOULD produce, without
 * producing it. This is what a destructive confirmation shows.
 *
 * `missing` is ids that name no row at all -- a stale client selection, or a
 * control another session already removed. delete_framework_controls_batch()
 * does not fail on those (it logs them as a cleanup), so they are reported
 * separately rather than folded into either half: they are neither retained
 * nor permanently removed, because there is nothing there.
 *
 * @return array{named:int, found:int, soft:int, hard:int, missing:int}
 */
function framework_controls_delete_split(array $control_ids): array
{
    $control_ids = array_values(array_unique(array_map('intval', $control_ids)));

    if (empty($control_ids)) {
        return ['named' => 0, 'found' => 0, 'soft' => 0, 'hard' => 0, 'missing' => 0];
    }

    $db = db_open();
    try {
        $classified = framework_controls_delete_classification($db, $control_ids, false);
    } finally {
        db_close($db);
    }

    $soft = 0;
    $hard = 0;
    foreach ($classified as $c) {
        if ($c['has_tests']) $soft++; else $hard++;
    }

    return [
        'named'   => count($control_ids),
        'found'   => count($classified),
        'soft'    => $soft,
        'hard'    => $hard,
        'missing' => count($control_ids) - count($classified),
    ];
}

/*********************************************
 * FUNCTION: DELETE FRAMEWORK CONTROLS BATCH *
 *********************************************/
function delete_framework_controls_batch(array $control_ids)
{
    global $escaper;
    if (empty($control_ids)) return;

    // Initialize controls lookup
    $controls_lookup = [];

    $db = db_open();
    $db->beginTransaction();

    try {
        // Step 1: Fetch all control info before deleting anything.
        // Same classification -- same SQL, same has_tests rule -- that
        // framework_controls_delete_split() shows the user in the
        // confirmation, so what the confirmation promised and what this
        // commits cannot be two different computations. Locked here, unlocked
        // there: the writer is about to modify these rows.
        $placeholders = implode(',', array_fill(0, count($control_ids), '?'));
        $controls = array_values(framework_controls_delete_classification($db, $control_ids, true));

        // Build lookup for logging
        foreach ($controls as $c) {
            $controls_lookup[$c['id']] = $c['short_name'];
        }

        // Step 2: get related queue_tasks
        $stmt = $db->prepare("
            SELECT id, JSON_UNQUOTE(JSON_EXTRACT(payload, '$.control_id')) AS control_id
            FROM queue_tasks
            WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.control_id')) IN ($placeholders)
              AND status IN ('in_progress', 'pending')
        ");
        $stmt->execute($control_ids);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $task_ids = array_column($tasks, 'id');
        if (!empty($task_ids)) {
            $in_tasks = implode(',', array_map('intval', $task_ids));

            // Step 3: cancel promises in bulk
            $db->exec("UPDATE promises SET status='canceled' WHERE queue_task_id IN ($in_tasks) AND status IN ('in_progress','pending')");

            // Step 4: cancel the queue_tasks themselves
            $db->exec("UPDATE queue_tasks SET status='canceled' WHERE id IN ($in_tasks)");
        }

        // Step 5: delete tmp_files for all control IDs using prepared placeholders
        $names = [];
        $params = [];

        foreach ($control_ids as $id) {
            $names[] = '?';
            $params[] = "control_{$id}";

            $names[] = '?';
            $params[] = "matches_{$id}";
        }

        $placeholders = implode(',', $names);
        $stmt = $db->prepare("DELETE FROM tmp_files WHERE name IN ($placeholders)");
        $stmt->execute($params);

        // Step 6: Delete the related framework_control_tests for all control IDs
        $soft_delete_ids = [];
        $hard_delete_ids = [];

        foreach ($controls as $c) {
            if ($c['has_tests']) {
                $soft_delete_ids[] = $c['id'];
            } else {
                $hard_delete_ids[] = $c['id'];
            }
        }

        // Soft delete
        if ($soft_delete_ids) {
            $placeholders = implode(',', array_fill(0, count($soft_delete_ids), '?'));
            $stmt = $db->prepare("UPDATE framework_controls SET deleted=1 WHERE id IN ($placeholders)");
            $stmt->execute($soft_delete_ids);
        }

        // Hard delete
        if ($hard_delete_ids) {
            $hard_placeholders = implode(',', array_fill(0, count($hard_delete_ids), '?'));

            $stmt = $db->prepare("DELETE FROM framework_controls WHERE id IN ($hard_placeholders)");
            $stmt->execute($hard_delete_ids);

            // Cleanup relations for hard deleted controls
            foreach (['framework_control_type_mappings','control_to_assets','control_to_asset_groups'] as $table) {
                $stmt = $db->prepare("DELETE FROM `$table` WHERE control_id IN ($hard_placeholders)");
                $stmt->execute($hard_delete_ids);
            }
        }

        // If customization extra is enabled, delete custom_control_data related with control IDs
        if (customization_extra() && !empty($control_ids)) {
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            delete_custom_data_by_row_ids($control_ids, "control");
        }

        // Commit the database changes
        if ($db->inTransaction()) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    } finally {
        db_close($db);
    }

    // Preserve residual risk history for the deleted controls
    add_residual_risk_scoring_histories_for_controls($control_ids);

    // Remove residual junction table entries
    cleanup_after_delete("framework_controls");

    // Queue tasks, logging, and custom data cleanup
    foreach ($control_ids as $id) {
        $short_name = $controls_lookup[$id] ?? null;
        $user = $_SESSION['user'] ?? "";
        $uid = $_SESSION['uid'] ?? "";
        $message = empty($short_name)
            ? "A missing control (ID:{$id}) was cleaned up by user '" . $escaper->escapeHtml($user) . "'."
            : "A control named '" . $escaper->escapeHtml($short_name) . "' was deleted by user '" . $escaper->escapeHtml($user) . "'.";
        write_log((int)$id + 1000, $uid, $message, "control");
        write_debug_log($message, "info");

        trigger_workflow_event('control.deleted', [
            'control_id' => $id,
            'short_name' => $short_name ?? '',
        ]);
    }
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

/*******************************************************
 * FUNCTION: CONTROL FRAMEWORK SCOPE SQL               *
 *******************************************************/
/**
 * The `control_framework` scoping fragment shared by all six
 * getAvailableControl*List() functions AND by the two count helpers below
 * (get_control_facet_unassigned_counts() / get_control_status_counts()).
 *
 * This used to be copy-pasted, byte for byte, into each *List() function.
 * It is now a single definition because the Define Control Frameworks filter
 * sheet renders a count chip next to each option: a chip whose count is
 * scoped even slightly differently from the list it annotates is a chip that
 * lies. One definition makes that drift impossible rather than merely
 * unlikely.
 *
 * Assumes the caller's FROM clause aliases `framework_control_mappings` as
 * `m` (every caller does) -- the -1 "Unassigned framework" sentinel is
 * "this control is mapped to no framework at all", i.e. `m.control_id is
 * NULL`, which is the same spelling get_framework_controls_by_filter() uses
 * for its own framework facet.
 *
 * Injection-safe: every value is (int)-cast before it reaches the string, so
 * the interpolated FIND_IN_SET list can only ever be digits, commas and
 * minus signs.
 *
 * Pure: string in, string out. No DB, no globals, no output.
 *
 * @param mixed $control_framework Array of framework IDs (-1 == unassigned),
 *                                 or anything falsy/non-array for "no scope".
 * @return string SQL fragment beginning with " AND ".
 */
function control_framework_scope_sql($control_framework) {
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

        return " AND (". implode(" OR ", $where) . ")";
    }

    return " AND 1 ";
}

/**********************************************
 * FUNCTION: GET AVAILABLE CONTROL CLASS List *
 **********************************************/
function getAvailableControlClassList($control_framework="", $with_counts=false){
    // Open the database connection
    $db = db_open();

    // $with_counts is opt-in and OFF by default: getFrameworkControlsDatatable()
    // (includes/api.php) calls this function at its original 1-arg call site and
    // returns its result verbatim as `classList` -- that handler is registered on
    // BOTH api/v1/index.php and api/v2/index.php and documented in swagger, so its
    // response shape must not change for existing (possibly external) consumers.
    // The default branch below is therefore byte-identical to the SQL this
    // function ran before the Define Control Frameworks filter sheet needed a
    // per-option count chip (data-count) -- only
    // getControlFiltersByFrameworksResponse() opts in by passing true. That
    // handler is ALSO reachable from api/v1/index.php (same function, both
    // versions dispatch to it) -- rebuild_control_filters's response shape was
    // already changed for both by the typeList addition below, so this opt-in
    // adds no new v1-exposure beyond what that addition already introduced; it
    // does not get the "v2-only" pass the datatable handler's calls get, it's
    // just already-accepted-risk territory rather than new territory.
    // GROUP BY t2.value is unaffected by adding the COUNT --
    // an aggregate is always allowed alongside a GROUP BY, and t2.value is
    // control_class's primary key so selecting t2.name too stays
    // ONLY_FULL_GROUP_BY-safe (functional dependency), same as before.
    if ($with_counts) {
        $sql = "
            SELECT t2.*, COUNT(DISTINCT t1.id) AS `count`
            FROM `framework_controls` t1
                LEFT JOIN `control_class` t2 on t1.control_class=t2.value
                LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            WHERE t2.value is not null AND t1.deleted=0";
    } else {
        $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    }
    $sql .= control_framework_scope_sql($control_framework);
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
function getAvailableControlPhaseList($control_framework="", $with_counts=false){
    // Open the database connection
    $db = db_open();

    // $with_counts is opt-in and OFF by default -- see
    // getAvailableControlClassList() above for why (getFrameworkControlsDatatable()
    // relies on this function's original, uncounted response shape).
    if ($with_counts) {
        $sql = "
            SELECT t2.*, COUNT(DISTINCT t1.id) AS `count`
            FROM `framework_controls` t1
                LEFT JOIN `control_phase` t2 on t1.control_phase=t2.value
                LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            WHERE t2.value is not null AND t1.deleted=0";
    } else {
        $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_phase` t2 on t1.control_phase=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    }
    $sql .= control_framework_scope_sql($control_framework);
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
function getAvailableControlOwnerList($control_framework="", $with_counts=false){
    // Open the database connection
    $db = db_open();

    // $with_counts is opt-in and OFF by default -- see
    // getAvailableControlClassList() above for why (getFrameworkControlsDatatable()
    // relies on this function's original, uncounted response shape).
    if ($with_counts) {
        $sql = "
            SELECT t2.value, t2.username, t2.name, COUNT(DISTINCT t1.id) AS `count`
            FROM `framework_controls` t1
                LEFT JOIN `user` t2 on t1.control_owner=t2.value
                LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            WHERE t2.value is not null AND t1.deleted=0";
    } else {
        $sql = "
        SELECT t2.value, t2.username, t2.name
        FROM `framework_controls` t1 
            LEFT JOIN `user` t2 on t1.control_owner=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    }
    $sql .= control_framework_scope_sql($control_framework);
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
function getAvailableControlFamilyList($control_framework="", $with_counts=false){
    // Open the database connection
    $db = db_open();

    // $with_counts is opt-in and OFF by default -- see
    // getAvailableControlClassList() above for why (getFrameworkControlsDatatable()
    // relies on this function's original, uncounted response shape).
    if ($with_counts) {
        $sql = "
            SELECT t2.*, COUNT(DISTINCT t1.id) AS `count`
            FROM `framework_controls` t1
                LEFT JOIN `family` t2 on t1.family=t2.value
                LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            WHERE t2.value is not null AND t1.deleted=0";
    } else {
        $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `family` t2 on t1.family=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    }
    $sql .= control_framework_scope_sql($control_framework);
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
function getHasBeenAuditFrameworkControlList($type = "test_audit") {

    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id value, t1.short_name name
        FROM 
            `framework_controls` t1 
    ";

    if ($type == "test_audit") {

        // Route through the audit_control_map snapshot (not the audit's dead
        // scalar framework_control_id) so a common-test audit counts as "has
        // been audited" under EVERY control it maps to, not just the min one.
        $sql .= "
            LEFT JOIN `audit_control_map` acm2 ON t1.id=acm2.framework_control_id
            LEFT JOIN `framework_control_test_audits` t2 ON t2.id=acm2.audit_id
        ";

    } else if ($type == "test") {

        $sql .= "
            LEFT JOIN `test_control_map` tcm2 ON t1.id=tcm2.framework_control_id
            LEFT JOIN `framework_control_tests` t2 ON t2.id=tcm2.test_id
        ";

    } else if ($type == "document") {

        $sql .= "
            LEFT JOIN `document_control_mappings` dcm ON t1.id=dcm.control_id AND dcm.selected=1
            LEFT JOIN `documents` t2 ON dcm.document_id=t2.id
        ";

    }

    $sql .= "  
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
function getHasBeenAuditFrameworkList($type = "test_audit") {

    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.value, t1.name, t1.description
        FROM 
            `frameworks` t1
            LEFT JOIN `framework_control_mappings` m ON t1.value=m.framework
            LEFT JOIN `framework_controls` t2 ON m.control_id=t2.id AND t2.deleted=0
    ";

    if ($type == "test_audit") {

        // Route through the audit_control_map snapshot so a common-test audit
        // counts under EVERY framework one of its snapshot controls belongs to.
        $sql .= "
            LEFT JOIN `audit_control_map` acm3 ON t2.id=acm3.framework_control_id
            LEFT JOIN `framework_control_test_audits` t3 ON t3.id=acm3.audit_id
        ";

    } else if ($type == "test") {

        $sql .= "
            LEFT JOIN `test_control_map` tcm3 ON t2.id=tcm3.framework_control_id
            LEFT JOIN `framework_control_tests` t3 ON t3.id=tcm3.test_id
        ";

    } else if ($type == "document") {

        $sql .= "
            LEFT JOIN `document_framework_mappings` dfm ON t1.value=dfm.framework_id
            LEFT JOIN `documents` t3 ON dfm.document_id=t3.id
        ";

    }

    $sql .= "            
        WHERE
            t3.id IS NOT NULL
        GROUP BY 
            t1.value;
    ";

    // Get available framework list
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Try decrypt
    foreach ($results as &$result) {

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
function getAvailableControlPriorityList($control_framework="", $with_counts=false){
    // Open the database connection
    $db = db_open();

    // $with_counts is opt-in and OFF by default -- see
    // getAvailableControlClassList() above for why (getFrameworkControlsDatatable()
    // relies on this function's original, uncounted response shape).
    if ($with_counts) {
        $sql = "
            SELECT t2.*, COUNT(DISTINCT t1.id) AS `count`
            FROM `framework_controls` t1
                LEFT JOIN `control_priority` t2 on t1.control_priority=t2.value
                LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            WHERE t2.value is not null AND t1.deleted=0";
    } else {
        $sql = "
        SELECT t2.*
        FROM `framework_controls` t1 
            LEFT JOIN `control_priority` t2 on t1.control_priority=t2.value 
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    }
    $sql .= control_framework_scope_sql($control_framework);
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

    // Control type is a many-to-many (framework_control_type_mappings), unlike
    // class/phase/family/owner/priority's scalar FK column on framework_controls
    // -- join through the mapping table to reach `control_type`, then apply the
    // same control_framework scoping (via framework_control_mappings) and the
    // same COUNT(DISTINCT t1.id) shape the sibling *List() functions use above,
    // so the Define Control Frameworks filter sheet's Type select gets a real
    // option list with counts instead of the empty result this used to return.
    $sql = "
        SELECT t2.*, COUNT(DISTINCT t1.id) AS `count`
        FROM `framework_controls` t1
            LEFT JOIN `framework_control_type_mappings` fctm on t1.id=fctm.control_id
            LEFT JOIN `control_type` t2 on fctm.control_type_id=t2.value
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t2.value is not null AND t1.deleted=0";
    $sql .= control_framework_scope_sql($control_framework);
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

/*************************************************************
 * FUNCTION: CONTROL UNASSIGNED FACET DEFINITIONS            *
 *************************************************************/
/**
 * The join + "is unassigned" predicate for every id-based control facet that
 * offers an Unassigned(-1) option.
 *
 * The predicates below are transcribed from ONE place only:
 * get_framework_controls_by_filter() (this file), which is what actually runs
 * when the user picks Unassigned in the filter sheet. That function spells
 * unassigned as "(tN.value is NULL OR tN.value='')" for the five scalar-FK
 * facets and as "ctype.value is NULL" for the many-to-many type facet. The
 * counts below MUST use the identical predicate -- a count derived from a
 * third definition (e.g. "t1.family = 0") would disagree with the very
 * filter its chip annotates on exactly the rows that matter: a control whose
 * FK points at a lookup row that no longer exists.
 *
 * NOTE this is deliberately NOT the complement of the *List() functions'
 * "WHERE t2.value is not null". Those functions discard the unassigned
 * bucket by construction, which is why Unassigned has never had a count.
 *
 * The join aliases the facet's lookup table as `t2` regardless of which
 * alias get_framework_controls_by_filter() happens to give it, because each
 * count is its own single-facet query.
 *
 * Pure: no DB, no globals, no output.
 *
 * @return array facet key => ['join' => string, 'unassigned' => string]
 */
function control_unassigned_facet_definitions() {
    return [
        'family' => [
            'join'       => "LEFT JOIN `family` t2 on t1.family=t2.value",
            'unassigned' => "(t2.value is NULL OR t2.value='')",
        ],
        'owner' => [
            'join'       => "LEFT JOIN `user` t2 on t1.control_owner=t2.value",
            'unassigned' => "(t2.value is NULL OR t2.value='')",
        ],
        'class' => [
            'join'       => "LEFT JOIN `control_class` t2 on t1.control_class=t2.value",
            'unassigned' => "(t2.value is NULL OR t2.value='')",
        ],
        'phase' => [
            'join'       => "LEFT JOIN `control_phase` t2 on t1.control_phase=t2.value",
            'unassigned' => "(t2.value is NULL OR t2.value='')",
        ],
        'priority' => [
            'join'       => "LEFT JOIN `control_priority` t2 on t1.control_priority=t2.value",
            'unassigned' => "(t2.value is NULL OR t2.value='')",
        ],
        // Control type is a many-to-many through framework_control_type_mappings,
        // so "unassigned" means "no mapping row at all" -- and
        // get_framework_controls_by_filter() writes that as a bare
        // "ctype.value is NULL", with no ='' arm. Transcribed as-is.
        'type' => [
            'join'       => "LEFT JOIN `framework_control_type_mappings` fctm on t1.id=fctm.control_id\n                LEFT JOIN `control_type` t2 on fctm.control_type_id=t2.value",
            'unassigned' => "t2.value is NULL",
        ],
    ];
}

/*************************************************************
 * FUNCTION: GET CONTROL FACET UNASSIGNED COUNTS             *
 *************************************************************/
/**
 * How many controls fall in each facet's Unassigned(-1) bucket, scoped to the
 * selected framework(s) exactly the way getAvailableControl*List() scopes the
 * options it returns (see control_framework_scope_sql()).
 *
 * Feeds the data-count chip on the filter sheet's synthesized -1 option --
 * the one bucket the *List() functions can never report, because their
 * "WHERE t2.value is not null" discards it.
 *
 * @param mixed $control_framework Same shape the *List() functions take.
 * @return array facet key => int count
 */
function get_control_facet_unassigned_counts($control_framework="") {
    // Open the database connection
    $db = db_open();

    $counts = [];
    foreach (control_unassigned_facet_definitions() as $facet => $definition) {
        $sql = "
            SELECT COUNT(DISTINCT t1.id) AS `count`
            FROM `framework_controls` t1
                " . $definition['join'] . "
                LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
            WHERE " . $definition['unassigned'] . " AND t1.deleted=0";
        $sql .= control_framework_scope_sql($control_framework);

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $counts[$facet] = (int)$stmt->fetchColumn();
    }

    // Close the database connection
    db_close($db);

    return $counts;
}

/*************************************************************
 * FUNCTION: CONTROL STATUS TOKEN MAP                        *
 *************************************************************/
/**
 * The canonical map from the three UI status tokens to the
 * framework_controls.control_status values they mean.
 *
 * This lives here, in the same file as get_framework_controls_by_filter()
 * (the function that turns those values into a WHERE clause), so the filter
 * and the count chip can never be reading two different maps.
 * controls_table_status_to_db() in api/v2/includes/governance_controls.php
 * delegates to this rather than restating the 1/0/2 literals.
 *
 * Pure: no DB, no globals, no output.
 *
 * @return array token => control_status value
 */
function control_status_token_map() {
    return ['pass' => 1, 'fail' => 0, 'not_tested' => 2];
}

/*************************************************************
 * FUNCTION: CONTROL STATUS COUNTS BY TOKEN                  *
 *************************************************************/
/**
 * Shape raw "control_status => count" rows into the three UI status tokens.
 *
 * A control_status that is NULL, or any value outside the three mapped ones,
 * lands in NO bucket -- deliberately. get_framework_controls_by_filter()
 * matches status with FIND_IN_SET(t1.control_status, '...'), which yields
 * NULL for a NULL column and 0 for an unmapped value, so filtering by any of
 * the three tokens never returns such a row either. Counting it anywhere
 * would make the chip disagree with its own filter, which is the one thing
 * these chips must never do.
 *
 * Pure: no DB, no globals, no output.
 *
 * @param array $rows Rows with 'control_status' and 'count' keys.
 * @return array token => int count (all three tokens always present)
 */
function control_status_counts_by_token($rows) {
    $by_value = [];
    foreach ((array)$rows as $row) {
        if (!isset($row['control_status'])) {
            continue;
        }
        $value = (int)$row['control_status'];
        $by_value[$value] = (isset($by_value[$value]) ? $by_value[$value] : 0) + (int)$row['count'];
    }

    $counts = [];
    foreach (control_status_token_map() as $token => $value) {
        $counts[$token] = isset($by_value[$value]) ? $by_value[$value] : 0;
    }

    return $counts;
}

/*************************************************************
 * FUNCTION: GET CONTROL STATUS COUNTS                       *
 *************************************************************/
/**
 * How many controls sit in each of the three status buckets, framework-scoped
 * the same way every other filter-sheet option list is.
 *
 * The Status facet has no lookup table to build a list from -- its three
 * values are computed tokens -- so rebuild_control_filters had no aggregate
 * to report. This is that aggregate.
 *
 * @param mixed $control_framework Same shape the *List() functions take.
 * @return array token => int count
 */
function get_control_status_counts($control_framework="") {
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.control_status, COUNT(DISTINCT t1.id) AS `count`
        FROM `framework_controls` t1
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t1.deleted=0";
    $sql .= control_framework_scope_sql($control_framework);
    $sql .= "
        GROUP BY
            t1.control_status
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return control_status_counts_by_token($rows);
}

/*************************************************************
 * FUNCTION: CONTROL MATURITY BUCKET TOKENS                  *
 *************************************************************/
/**
 * The three maturity buckets a control can fall into, in the order the filter
 * sheet lists them.
 *
 * Same vocabulary the rest of the product already speaks:
 * governance_maturity_gap_table($bucket) (includes/reporting.php) takes
 * exactly these three strings for the governance dashboard's Below/At/Above
 * Maturity widgets. Task 34's Maturity column, its filter facet and its count
 * chips all read this one list rather than restating the literals.
 *
 * Pure: no DB, no globals, no output.
 *
 * @return array
 */
function control_maturity_bucket_tokens() {
    return ['below', 'at', 'above'];
}

/*************************************************************
 * FUNCTION: CONTROL MATURITY BUCKET                         *
 *************************************************************/
/**
 * Which bucket one control's current maturity falls into relative to its
 * desired maturity: 'below', 'at', 'above', or '' when there is no target to
 * compare against.
 *
 * This is the ONLY definition of that comparison. Three surfaces read it --
 * the table's Maturity chip (governance-frameworks.js mirrors this in JS),
 * the maturity filter (controls_table_apply_maturity_applicability(),
 * api/v2/includes/governance_controls.php) and the filter sheet's count chips
 * (get_control_maturity_counts() below, which buckets rows by calling THIS
 * function rather than by a second CASE expression in SQL). A chip counted by
 * one rule and filtered by another would disagree on exactly the rows that
 * matter, which is the failure the Task 29 count helpers were built to avoid.
 *
 * A desired maturity of 0 (or NULL) means "no target set" -- not "target
 * zero". Such a control lands in NO bucket, which is why the table renders an
 * em dash for it rather than a chip: with nothing to compare against, "at" and
 * "above" are both unfounded claims. NULL current maturity coerces to 0, the
 * same coercion controls_table_shape_row() applies before the value is ever
 * displayed.
 *
 * Pure: no DB, no globals, no output.
 *
 * @param mixed $control_maturity Current maturity (int-ish, may be NULL)
 * @param mixed $desired_maturity Desired maturity (int-ish, may be NULL)
 * @return string One of 'below' | 'at' | 'above', or '' for "no target"
 */
function control_maturity_bucket($control_maturity, $desired_maturity) {
    $desired = (int)$desired_maturity;
    if ($desired < 1) {
        return '';
    }

    $current = (int)$control_maturity;
    if ($current < $desired) {
        return 'below';
    }
    if ($current > $desired) {
        return 'above';
    }

    return 'at';
}

/*************************************************************
 * FUNCTION: CONTROL MATURITY COUNTS BY BUCKET               *
 *************************************************************/
/**
 * Shape raw {control_maturity, desired_maturity} rows into the three bucket
 * counts, using control_maturity_bucket() itself -- so the chip and the filter
 * are the same code path, not two transcriptions of the same rule.
 *
 * Controls with no desired maturity land in no bucket at all, matching what
 * filtering by any of the three buckets returns for them (nothing).
 *
 * Pure: no DB, no globals, no output.
 *
 * @param array $rows
 * @return array bucket token => int count (all three tokens always present)
 */
function control_maturity_counts_by_bucket($rows) {
    $counts = array_fill_keys(control_maturity_bucket_tokens(), 0);

    foreach ((array)$rows as $row) {
        $bucket = control_maturity_bucket(
            isset($row['control_maturity']) ? $row['control_maturity'] : null,
            isset($row['desired_maturity']) ? $row['desired_maturity'] : null
        );
        if ($bucket !== '') {
            $counts[$bucket]++;
        }
    }

    return $counts;
}

/*************************************************************
 * FUNCTION: GET CONTROL MATURITY COUNTS                     *
 *************************************************************/
/**
 * How many controls sit in each maturity bucket, framework-scoped the same way
 * every other filter-sheet option list and count is
 * (control_framework_scope_sql()).
 *
 * Like the Status facet, Maturity has no lookup table to build an option list
 * from -- its three values are computed -- so rebuild_control_filters had no
 * aggregate to report for it. This is that aggregate.
 *
 * The bucketing deliberately happens in PHP rather than as a SQL CASE: the
 * comparison rule then has exactly one definition (control_maturity_bucket()),
 * shared with the filter itself. The row set is one small projection per
 * framework scope (id + two smallint columns), fetched once when the selected
 * framework changes -- not per table reload.
 *
 * @param mixed $control_framework Same shape the *List() functions take.
 * @return array bucket token => int count
 */
function get_control_maturity_counts($control_framework="") {
    // Open the database connection
    $db = db_open();

    // DISTINCT because the framework-scope join multiplies a control by the
    // number of frameworks it is mapped to; control_maturity/desired_maturity
    // are functionally dependent on t1.id, so the triple is unique per control.
    $sql = "
        SELECT DISTINCT t1.id, t1.control_maturity, t1.desired_maturity
        FROM `framework_controls` t1
            LEFT JOIN `framework_control_mappings` m on t1.id=m.control_id
        WHERE t1.deleted=0";
    $sql .= control_framework_scope_sql($control_framework);

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return control_maturity_counts_by_bucket($rows);
}

/**************************************************
 * FUNCTION: GET DOCUMENT VERSIONS BY DOCUMENT ID *
 **************************************************/
function get_document_versions_by_id($id) {

    // Open the database connection
    $db = db_open();
    $where = call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_documents',
        ['t1', false],
        ' 1'
    );

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
    $where = call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_documents',
        ['t1', false],
        ' 1'
    );

    $sql = "
        SELECT t1.*, t2.version file_version, t2.unique_name, t2.name file_name, t2.size file_size, t3.value as status,
            GROUP_CONCAT(DISTINCT f.value) framework_ids,
            GROUP_CONCAT(DISTINCT fc.id) control_ids,
            GROUP_CONCAT(DISTINCT dasm.user_id) additional_stakeholders,
            GROUP_CONCAT(DISTINCT dtm.team_id) team_ids
        FROM `documents` t1
            LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
            LEFT JOIN `document_status` t3 ON t1.document_status=t3.value
            LEFT JOIN `document_framework_mappings` dfm ON t1.id=dfm.document_id
            LEFT JOIN `frameworks` f ON dfm.framework_id=f.value
            LEFT JOIN `document_control_mappings` dcm ON t1.id=dcm.document_id AND dcm.selected=1
            LEFT JOIN `framework_controls` fc ON dcm.control_id=fc.id
            LEFT JOIN `document_additional_stakeholder_mappings` dasm ON t1.id=dasm.document_id
            LEFT JOIN `document_team_mappings` dtm ON t1.id=dtm.document_id
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
            GROUP_CONCAT(DISTINCT fc.id) control_ids,
            GROUP_CONCAT(DISTINCT dasm.user_id) additional_stakeholders,
            GROUP_CONCAT(DISTINCT dtm.team_id) team_ids
        FROM `documents` t1
            LEFT JOIN `compliance_files` t2 ON t1.file_id=t2.id
            LEFT JOIN `document_status` t3 ON t1.document_status=t3.value
            LEFT JOIN `document_framework_mappings` dfm ON t1.id=dfm.document_id
            LEFT JOIN `frameworks` f ON dfm.framework_id=f.value
            LEFT JOIN `document_control_mappings` dcm ON t1.id=dcm.document_id AND dcm.selected=1
            LEFT JOIN `framework_controls` fc ON dcm.control_id=fc.id
            LEFT JOIN `document_additional_stakeholder_mappings` dasm ON t1.id=dasm.document_id
            LEFT JOIN `document_team_mappings` dtm ON t1.id=dtm.document_id
    ";
    $where = call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_documents',
        ['t1'],
        ' WHERE 1'
    );
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

/**************************
 * FUNCTION: ADD DOCUMENT *
 **************************/
function add_document($submitted_by, $document_type, $document_name, $control_ids, $framework_ids, $parent, $status, $creation_date, $last_review_date, $review_frequency, $next_review_date, $approval_date, $document_owner, $additional_stakeholders, $approver, $team_ids, $user=null){
    global $lang, $escaper;

    // If no user was specified, try the session uid, otherwise default to 0
    if (!$user) {
        $user = $_SESSION['uid'] ?? 0;
    }

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
    $stmt = $db->prepare("INSERT INTO `documents` (`submitted_by`, `document_type`, `document_name`, `parent`, `document_status`, `file_id`, `creation_date`, `last_review_date`, `review_frequency`, `next_review_date`, `approval_date`, `document_owner`, `approver`) VALUES (:submitted_by, :document_type, :document_name, :parent, :status, :file_id, :creation_date, :last_review_date, :review_frequency, :next_review_date, :approval_date, :document_owner, :approver)");
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
    $stmt->bindParam(":approver", $approver, PDO::PARAM_INT);

    $stmt->execute();

    $document_id = $db->lastInsertId();

    // Normalize control_ids to array
    if (is_array($control_ids)) {
        $ids = $control_ids;
    } elseif (is_string($control_ids) && $control_ids !== '') {
        $ids = array_map('trim', explode(',', $control_ids));
    } else {
        $ids = [];
    }

    // Normalize framework_ids to array
    if (is_array($framework_ids)) {
        $frameworks = $framework_ids;
    } elseif (is_string($framework_ids) && $framework_ids !== '') {
        $frameworks = array_map('trim', explode(',', $framework_ids));
    } else {
        $frameworks = [];
    }

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

    // Save document frameworks
    save_junction_values("document_framework_mappings", "document_id", $document_id, "framework_id", $framework_ids);

    // Save additional stakeholders
    save_junction_values("document_additional_stakeholder_mappings", "document_id", $document_id, "user_id", $additional_stakeholders);

    // Save teams
    save_junction_values("document_team_mappings", "document_id", $document_id, "team_id", $team_ids);

    // If submitted files are existing, save files
    if(!empty($_FILES['file'])){
        $files = $_FILES['file'];
        list($status, $file_ids, $errors) = upload_compliance_files($document_id, "documents", $files, 1, $user);
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
        $return_value = false;
    } elseif(empty($file_id)) {
        // Delete added document if failed to upload a document file
        delete_document($document_id);
        set_alert(true, "bad", $lang['FailedToUploadFile']);
        $return_value = false;
    } else {
        $stmt = $db->prepare("UPDATE `documents` SET file_id=:file_id WHERE id=:document_id ");
        $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
        $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
        $stmt->execute();

        $submitted_by_name = get_user_name($submitted_by);
        $message = _lang_raw('AuditLog_DocumentCreate', array('document_name' => $document_name, 'user_name' => $submitted_by_name));
        write_log(1000, $submitted_by, $message, "document");

        // Queue the core document add task
        $queue_task_payload = [
            'triggered_at'  => time(),
            'document_id'   => (int)$document_id,
            'refresh'       => true,
        ];
        queue_task($db, 'core_document_update', $queue_task_payload, 100, 5, 3600);

        // Send the notification (no-op if notification extra is disabled)
        call_extra_function(
            'notification_extra',
            __DIR__ . '/../extras/notification/index.php',
            'notify_new_document',
            [$document_id]
        );

        $return_value = $document_id;
    }

    // Close the database connection
    db_close($db);

    if ($return_value) {
        trigger_workflow_event('document.created', [
            'document_id'   => $return_value,
            'document_name' => $document_name,
            'document_type' => $document_type,
            'submitted_by'  => $submitted_by,
        ]);
    }

    // Return the return value
    return $return_value;
}

/*****************************
 * FUNCTION: UPDATE DOCUMENT *
 *****************************/
function update_document($document_id, $updated_by, $document_type, $document_name, $control_ids, $framework_ids, $parent, $status, $creation_date, $last_review_date, $review_frequency, $next_review_date, $approval_date, $document_owner, $additional_stakeholders, $approver, $team_ids, $audit_log=true){
    global $lang, $escaper;
    
    // Open the database connection
    $db = db_open();
    $where = call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_documents',
        [false, false],
        ' 1'
    );
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
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row){
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoModifyDocumentationPermission']));
        return false;
    }

    // Get the existing values for this document
    $row = get_document_by_id($document_id);

    // Create an array of before values
    $before = [
        'submitted_by' => (int)$row['submitted_by'],
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
        'submitted_by' => (int)$row['submitted_by'],
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
    $changes_arr = [];
    if (notification_extra()) {
        [$changes, $changes_arr] = get_changes('document', $before, $after, 3);
    } else {
        $changes = get_changes('document', $before, $after);
    }

    // Update a document
    $stmt = $db->prepare("UPDATE `documents` SET `updated_by` = :updated_by, `document_type`=:document_type, `document_name`=:document_name, `parent`=:parent, `document_status`=:document_status, `creation_date`=:creation_date, `last_review_date`=:last_review_date, `review_frequency`=:review_frequency, `next_review_date`=:next_review_date, `approval_date`=:approval_date, `document_owner`=:document_owner, `approver`=:approver WHERE id=:document_id; ");
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
    $stmt->bindParam(":approver", $approver, PDO::PARAM_INT);
    $stmt->execute();

    // Deselect existing mappings for this document
    $stmt = $db->prepare("UPDATE `document_control_mappings` SET `selected`=0, `ai_run` = 0 WHERE `document_id`=:document_id;");
    $stmt->bindParam(":document_id", $document_id, PDO::PARAM_INT);
    $stmt->execute();

    // Split the control_ids string into an array
    $ids = array_map('trim', $control_ids);

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

    // Queue the core document add task
    $queue_task_payload = [
        'triggered_at'  => time(),
        'document_id'   => (int)$document_id,
        'refresh'       => true,
    ];
    queue_task($db, 'core_document_update', $queue_task_payload, 100, 5, 3600);

    // Close the database connection
    db_close($db);

    // Save document frameworks
    save_junction_values("document_framework_mappings", "document_id", $document_id, "framework_id", $framework_ids);

    // Save additional stakeholders
    save_junction_values("document_additional_stakeholder_mappings", "document_id", $document_id, "user_id", $additional_stakeholders);

    // Save teams
    save_junction_values("document_team_mappings", "document_id", $document_id, "team_id", $team_ids);

    // If submitted files are existing, save files
    if(!empty($_FILES['file'])){
        $document = get_document_by_id($document_id);
        $version = $document['file_version'] + 1;

        $files = $_FILES['file'];
        list($status, $file_ids, $errors) = upload_compliance_files($document_id, "documents", $files, $version, (int)$updated_by);
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
        $message = _lang_raw('AuditLog_DocumentUpdates', array('document_name' => $document_name, 'document_id' => $document_id, 'user_name' => $updated_by_name, 'changes' => $changes));
        write_log(1000, $updated_by, $message, "document");

        // Send the notification (no-op if notification extra is disabled)
        call_extra_function(
            'notification_extra',
            __DIR__ . '/../extras/notification/index.php',
            'notify_document_update',
            [$document_id, ['changes' => $changes_arr]]
        );
    }

    trigger_workflow_event('document.updated', [
        'document_id'   => $document_id,
        'document_name' => $document_name,
        'document_type' => $document_type,
        'updated_by'    => $updated_by,
    ]);

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

    $where = call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_documents',
        [false, false],
        ' 1'
    );

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

    $message = "The existing document ID \"".$document_id."\" was deleted by the \"" . $escaper->escapeHtml($_SESSION['user']) . "\" user.";
    write_log(1000, $_SESSION['uid'] ?? 0, $message, "document");

    // Close the database connection
    db_close($db);

    // Only fire for full document deletion, not version-only deletion
    if (!$version) {
        trigger_workflow_event('document.deleted', [
            'document_id'   => $document_id,
            'document_name' => $row['document_name'] ?? '',
        ]);
    }

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
                <th data-options=\"field:'document_name'\" width='22%'>{$escaper->escapeHtml($lang['DocumentName'])}</th>
                <th data-options=\"field:'document_type'\" width='8%'>{$escaper->escapeHtml($lang['DocumentType'])}</th>
                <th data-options=\"field:'framework_names'\" width='15%'>{$escaper->escapeHtml($lang['ControlFrameworks'])}</th>
                <th data-options=\"field:'control_names'\" width='15%'>{$escaper->escapeHtml($lang['Controls'])}</th>
                <th data-options=\"field:'submitted_by'\" width='9%'>{$escaper->escapeHtml($lang['Submitter'])}</th>
                <th data-options=\"field:'updated_by'\" width='9%'>{$escaper->escapeHtml($lang['UpdatedBy'])}</th>
                <th data-options=\"field:'creation_date'\" width='8%'>{$escaper->escapeHtml($lang['CreationDate'])}</th>
                <th data-options=\"field:'approval_date'\" width='8%'>{$escaper->escapeHtml($lang['ApprovalDate'])}</th>
                <th data-options=\"field:'status'\" width='6%'>{$escaper->escapeHtml($lang['Status'])}</th>
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
                <th data-options=\"field:'document_name'\" width='20%'>{$escaper->escapeHtml($lang['DocumentName'])}</th>
                <th data-options=\"field:'document_type'\" width='8%'>{$escaper->escapeHtml($lang['DocumentType'])}</th>
                <th data-options=\"field:'framework_names'\" width='14%'>{$escaper->escapeHtml($lang['ControlFrameworks'])}</th>
                <th data-options=\"field:'control_names'\" width='14%'>{$escaper->escapeHtml($lang['Controls'])}</th>
                <th data-options=\"field:'submitted_by'\" width='8%'>{$escaper->escapeHtml($lang['Submitter'])}</th>
                <th data-options=\"field:'updated_by'\" width='8%'>{$escaper->escapeHtml($lang['UpdatedBy'])}</th>
                <th data-options=\"field:'creation_date'\" width='8%'>{$escaper->escapeHtml($lang['CreationDate'])}</th>
                <th data-options=\"field:'approval_date'\" width='8%'>{$escaper->escapeHtml($lang['ApprovalDate'])}</th>
                <th data-options=\"field:'status'\" width='6%'>{$escaper->escapeHtml($lang['Status'])}</th>
                <th data-options=\"field:'actions'\" width='6%'>{$escaper->escapeHtml($lang['Actions'])}</th>
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
                    case "submitted_by":
                        if( stripos(get_name_by_value('user', (int)$document['submitted_by']), $value) === false ){
                            continue 3;
                        }
                        break;
                    case "updated_by":
                        if( stripos(get_name_by_value('user', (int)$document['updated_by']), $value) === false ){
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
        $document['submitted_by'] = $escaper->escapeHtml(get_name_by_value('user', (int)$document['submitted_by']));
        $document['updated_by'] = $escaper->escapeHtml(get_name_by_value('user', (int)$document['updated_by']));
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
    $long_name = null;
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
            de.value as exception_id,
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
            de.value as exception_id,
            des.name as document_exceptions_status
        from document_exceptions de
            left join framework_controls c on de.control_framework_id = c.id
            left join document_exceptions_status des on de.status = des.value
        where
            c.id is not null";

    $where = call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_documents',
        ['p', false],
        ' 1'
    );

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
                        case "exception_id":
                            if (stripos(($row['exception_id'] + 1000), $value) === false) {
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

            $row['exception_id'] = $escaper->escapeHtml($row['exception_id'] + 1000);

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

            $all_approved = $all_approved && $row['approved'];

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

    $where = call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_documents',
        ['p', false],
        ' 1'
    );

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

            $all_approved = $all_approved && $row['approved'];
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
                <th data-options=\"field:'name'\" width='24%'>{$escaper->escapeHtml($lang[ucfirst ($type) . "ExceptionName"])}</th>
                <th data-options=\"field:'exception_id'\" width='7%'>{$escaper->escapeHtml($lang['ID'])}</th>
                <th data-options=\"field:'status'\" width='7%'>{$escaper->escapeHtml($lang['Status'])}</th>
                <th data-options=\"field:'description'\" width='23%'>{$escaper->escapeHtml($lang['Description'])}</th>
                <th data-options=\"field:'justification'\" width='23%'>{$escaper->escapeHtml($lang['Justification'])}</th>
                <th data-options=\"field:'next_review_date', align: 'center'\" width='9%'>{$escaper->escapeHtml($lang['NextReviewDate'])}</th>
                <th data-options=\"field:'actions'\" width='7%'>{$escaper->escapeHtml($lang['Actions'])}</th>
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

    // Sanitizing input that comes from the WYSIWYG editor or outside sources
    $description = purify_html($description);
    $justification = purify_html($justification);

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

    write_log($id, $_SESSION['uid'] ?? 0, _lang('ExceptionAuditLogCreate', array('exception_name' => $name, 'user' => $_SESSION['user'])), 'exception');


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

    trigger_workflow_event('exception.created', [
        'exception_id' => $id,
        'name'         => $name,
        'owner'        => $owner,
    ]);

    return $id;
}

function update_exception($name, $status, $policy, $framework, $control, $owner, $additional_stakeholders, $creation_date, $review_frequency, $next_review_date, $approval_date, $approver, $approved, $description, $justification, $associated_risks, $id) {

    global $escaper;

    // Sanitizing input that comes from the WYSIWYG editor or outside sources
    $description = purify_html($description);
    $justification = purify_html($justification);

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
        write_log($id, $_SESSION['uid'] ?? 0, _lang_raw('ExceptionAuditLogUpdate', array('exception_name' => $escaper->escapeHtml($name), 'user' => $escaper->escapeHtml($_SESSION['user']), 'changes' => implode(', ', $changes))), 'exception');
    }

    trigger_workflow_event('exception.updated', [
        'exception_id' => $id,
        'name'         => $name,
        'owner'        => $owner,
    ]);

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
    // Exception description/justification are WYSIWYG (rich-text) fields; emit
    // them as plain text so the audit message doesn't carry literal "<p>"/
    // "&nbsp;". The change comparison runs on the raw values, so a
    // formatting-only edit is still detected and logged.
    $richtext_fields = ['description', 'justification'];
    $changes = [];
    foreach($original as $key => $value) {
        if ($value !== $updated[$key]) {
            $new_value = $updated[$key];
            if (in_array($key, $richtext_fields, true)) {
                $value = html_to_plain_text($value);
                $new_value = html_to_plain_text($new_value);
            }
            $changes[] = _lang('ExceptionAuditLogUpdateChange', array('key' => $key, 'value' => $value, 'new_value' => $new_value));
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

    write_log($approved_exception['value'], $_SESSION['uid'] ?? 0, _lang('ExceptionAuditLogApprove', array('exception_name' => $approved_exception['name'], 'user' => $_SESSION['user'])), 'exception');

    trigger_workflow_event('exception.approved', [
        'exception_id' => $id,
        'name'         => $approved_exception['name'],
        'approver'     => $approver,
    ]);
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

    write_log($unapproved_exception['value'], $_SESSION['uid'] ?? 0, _lang('ExceptionAuditLogUnapprove', array('exception_name' => $unapproved_exception['name'], 'user' => $_SESSION['user'])), 'exception');

    trigger_workflow_event('exception.unapproved', [
        'exception_id' => $id,
        'name'         => $unapproved_exception['name'],
    ]);
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

    write_log($deleted_exception['value'], $_SESSION['uid'] ?? 0, _lang('ExceptionAuditLogDelete', array('exception_name' => $deleted_exception['name'], 'user' => $_SESSION['user'])), 'exception');

    trigger_workflow_event('exception.deleted', [
        'exception_id' => $id,
        'name'         => $deleted_exception['name'],
    ]);
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
        write_log($deleted_exception['value'], $_SESSION['uid'] ?? 0, _lang('ExceptionAuditLogDelete', array('exception_name' => $deleted_exception['name'], 'user' => $user)), 'exception');
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

/*****************************************************************************
 * FUNCTION: PARSE CONTROL MAP-FRAMEWORKS REQUEST                            *
 *                                                                            *
 * Pure (no DB, no globals): turns the Add/Update Control modal's parallel   *
 * `map_framework_id[]` / `reference_name[]` / `reference_subject[]` /      *
 * `reference_text[]` arrays into the row shape                             *
 * save_control_to_frameworks() expects --                                  *
 * [[framework_id, reference_name, reference_text, reference_subject], ...].*
 * Mirrors the legacy addControlResponse()/updateControlResponse()          *
 * (includes/api.php) parsing exactly, extracted into a standalone function *
 * so it's directly testable (those two are unreachable in a test process   *
 * -- they end in json_response(), which calls exit()) and reusable from    *
 * createControlCrud().                                                     *
 *                                                                            *
 * THE SUBJECT IS THE FOURTH ELEMENT, NOT THE THIRD. Appending rather than  *
 * inserting keeps every existing producer of this row shape valid --       *
 * includes/api.php's two legacy handlers and the Import/Export Extra all   *
 * build 3-element rows, and save_control_to_frameworks() reads the fourth  *
 * with a null-coalesce. Inserting it in the middle would have silently     *
 * written each of those callers' reference_text into the subject column.   *
 *                                                                            *
 * Defensive: a row with no framework id is skipped rather than persisted   *
 * with a falsy framework value, and a reference field missing at the same  *
 * index (a malformed/truncated submission) does not emit a PHP notice or   *
 * fatal on an undefined offset.                                            *
 *                                                                            *
 * AN ABSENT ELEMENT IS NULL, NOT "". This function used to default all three *
 * reference fields to "", and that "" is a VALUE -- so it DEFEATED both of   *
 * save_control_to_frameworks()'s COALESCE guards. A request that sent        *
 * map_frameworks_submitted plus map_framework_id[] but a short or absent     *
 * reference_text[] / reference_subject[] cleared those columns on every row  *
 * past the end of the array. That is the whole "an absent value is read as an*
 * instruction to clear" defect, arriving one layer up from the domain        *
 * functions that were hardened against it.                                   *
 *                                                                            *
 * The point that settles it: this function MANUFACTURES the "" itself when   *
 * the index is absent. The caller never expressed an intent to clear -- the  *
 * parser fabricated that intent out of absence. The convention's            *
 * "'' clears / null preserves" rule exists to honour a CALLER's explicit     *
 * empty value, not an internal helper's invented one.                        *
 *                                                                            *
 * Clearing is fully preserved: a caller who genuinely wants to clear sends   *
 * reference_text[3]="", which passes isset() and still arrives here (and at  *
 * the COALESCE) as "". Only an ABSENT index becomes null. Nothing is lost.   *
 *                                                                            *
 * reference_name is the EXCEPTION and stays "". It is varchar(255) NOT NULL  *
 * and is the third column of the mapping table's UNIQUE key, so it is the    *
 * ROW SELECTOR rather than a value being written -- there is no null with    *
 * which to mean "unsaid", and save_control_to_frameworks() never lists it in *
 * its ON DUPLICATE KEY UPDATE clause at all. Unlike its two siblings it also *
 * has no control_number fallback to reach for: this function is pure by      *
 * contract (no DB, no globals), so it cannot look the control's number up.   *
 * An absent reference_name is a malformed request, and its "" produces a     *
 * JUNK ROW rather than destroying an existing one -- the non-destructive     *
 * shape, not the data-loss shape this family is about. Left alone            *
 * deliberately; see save_control_to_framework_by_ids() for the same call.    *
 *                                                                            *
 * The Add/Update Control modal never took either path: display_add_mapping_  *
 * row() and getFrameworkControlResponse() (includes/api.php) both emit a     *
 * parallel reference_subject[] and reference_text[] input for every row, so  *
 * the form always submits all four arrays at equal length, and an empty      *
 * <textarea> serializes as "" rather than as nothing -- so emptying a field  *
 * in the UI still clears the column, exactly as before.                      *
 *                                                                            *
 * @param array $post the raw request array (e.g. $_POST)                   *
 * @return array<int, array{0:mixed,1:string,2:?string,3:?string}>          *
 *****************************************************************************/
function parse_control_map_frameworks_request(array $post): array
{
    $framework_ids = isset($post['map_framework_id']) && is_array($post['map_framework_id']) ? $post['map_framework_id'] : [];
    $reference_names = isset($post['reference_name']) && is_array($post['reference_name']) ? $post['reference_name'] : [];
    $reference_texts = isset($post['reference_text']) && is_array($post['reference_text']) ? $post['reference_text'] : [];
    $reference_subjects = isset($post['reference_subject']) && is_array($post['reference_subject']) ? $post['reference_subject'] : [];

    $map_frameworks = [];
    foreach ($framework_ids as $index => $framework_id) {
        if (!$framework_id) {
            continue;
        }
        // "" only for reference_name, and only because NOT NULL + UNIQUE-key
        // membership leaves no null to mean "unsaid". See the banner.
        $reference_name = isset($reference_names[$index]) ? $reference_names[$index] : "";
        $reference_text = isset($reference_texts[$index]) ? $reference_texts[$index] : null;
        $reference_subject = isset($reference_subjects[$index]) ? $reference_subjects[$index] : null;
        $map_frameworks[] = [$framework_id, $reference_name, $reference_text, $reference_subject];
    }
    return $map_frameworks;
}

/*****************************************************************************
 * FUNCTION: PARSE CONTROL MAPPED-ASSETS REQUEST                             *
 *                                                                            *
 * Pure (no DB, no globals): turns the Add/Update Control modal's parallel   *
 * `asset_maturity[]` / `assets_asset_groups[]` arrays into the row shape    *
 * save_control_to_assets() expects -- [[maturity, [values...]], ...].      *
 * Mirrors the legacy addControlResponse()/updateControlResponse()          *
 * parsing, with one added guard legacy didn't have: a row whose            *
 * `assets_asset_groups` entry isn't itself an array (a malformed/          *
 * hand-crafted request, since a real <select multiple> always serializes   *
 * to an array) is skipped rather than handed to save_control_to_assets(),  *
 * whose inner `foreach` would otherwise iterate the characters of a        *
 * string.                                                                  *
 *                                                                            *
 * @param array $post the raw request array (e.g. $_POST)                   *
 * @return array<int, array{0:mixed,1:array<int,string>}>                   *
 *****************************************************************************/
function parse_control_mapped_assets_request(array $post): array
{
    $asset_maturities = isset($post['asset_maturity']) && is_array($post['asset_maturity']) ? $post['asset_maturity'] : [];
    $assets_asset_groups = isset($post['assets_asset_groups']) && is_array($post['assets_asset_groups']) ? $post['assets_asset_groups'] : [];

    $mapped_assets = [];
    foreach ($asset_maturities as $index => $maturity) {
        $values = isset($assets_asset_groups[$index]) ? $assets_asset_groups[$index] : null;
        if (!empty($values) && is_array($values)) {
            $mapped_assets[] = [$maturity, $values];
        }
    }
    return $mapped_assets;
}

/***************************************
 * FUNCTION: SAVE CONTROL TO FRAMEWORK *
 ***************************************/
function save_control_to_frameworks($control_id, $map_frameworks)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        INSERT INTO framework_control_mappings (
            control_id,
            framework,
            reference_name,
            reference_text,
            reference_subject
        )
        VALUES (
            :control_id,
            :framework,
            :reference_name,
            :reference_text,
            :reference_subject
        )
        ON DUPLICATE KEY UPDATE
            -- OMISSION MEANS PRESERVE for the clause text too, as of this change.
            --
            -- Every producer that HAS a text for a row still sends it, and an
            -- empty <textarea> serializes as '' rather than as nothing, so the
            -- Add/Update Control modal's behaviour is untouched: emptying the
            -- field still clears the column. What changes is the row that says
            -- NOTHING -- which arrives as NULL and is now left alone.
            --
            -- That row is reachable: includes/api.php's two legacy handlers
            -- (POST /governance/add_control, POST /governance/update_control --
            -- published v1 AND v2 endpoints) build their rows from parallel
            -- map_framework_id[] / reference_name[] / reference_text[] arrays,
            -- and an API client sending a reference_text[] SHORTER than its
            -- framework list, or omitting it entirely, blanked the clause text on
            -- every row past the end of the array. Same defect as
            -- save_control_to_framework_by_ids() and
            -- add_framework_control_to_framework() -- an absent value read as an
            -- instruction to clear -- arriving one layer up.
            reference_text = COALESCE(VALUES(reference_text), reference_text),
            -- Same rule for the subject, and it came first.
            --
            -- COALESCE, not a bare VALUES(): a row that says NOTHING about the
            -- subject arrives here as NULL, and three callers still build
            -- 3-element rows -- includes/api.php's two legacy control handlers
            -- and the Import/Export Extra's CSV import. Without this, re-importing
            -- a control CSV would silently blank every framework title the SoA
            -- prints, on mappings the import never intended to touch. An explicit
            -- '' still clears it, which is what a user emptying the field means.
            --
            -- BOTH GUARDS ARE NOW REACHED BY EVERY PRODUCER. They are still
            -- defeated, by design, by a caller that sends '' rather than omitting
            -- the element -- because '' is a value and clearing is what it means.
            -- What used to defeat them ACCIDENTALLY was two callers that
            -- MANUFACTURED a '' out of an absent array index, so a caller who had
            -- said nothing was made to say clear:
            --   * parse_control_map_frameworks_request() below -- fixed, an absent
            --     reference_text[] / reference_subject[] element is now null.
            --   * the Import/Export Extra's CSV control import -- fixed in the
            --     Extra (extras/import-export/index.php), which hardcoded '' for
            --     the clause text on every row it built and so blanked the text on
            --     every mapping a control re-import touched.
            -- Do not reintroduce either default: an INVENTED empty value is not a
            -- caller expressing intent, and that distinction is the whole rule.
            reference_subject = COALESCE(VALUES(reference_subject), reference_subject)
    ");

    foreach($map_frameworks as $row){
        $framework = $row[0];
        $reference_name = $row[1];
        $reference_text = $row[2];
        // FOURTH element, and OPTIONAL. Three producers of this row shape
        // predate the subject column (includes/api.php's two legacy control
        // handlers and the Import/Export Extra) and still build 3-element rows;
        // they must keep working, and must not have their reference_text land
        // in the subject. See parse_control_map_frameworks_request().
        $reference_subject = $row[3] ?? null;

        // If there is no framework, continue to the next row
        if (!$framework) {
            continue;
        }

        $stmt->execute([
            ':control_id'     => $control_id,
            ':framework'      => $framework,
            ':reference_name' => $reference_name,
            ':reference_text' => $reference_text,
            ':reference_subject' => $reference_subject
        ]);
    }
    // Close the database connection
    db_close($db);  
}

/*****************************************************************************
 * FUNCTION: DELETE CONTROL-TO-FRAMEWORK MAPPINGS NOT IN THE SUBMITTED SET    *
 *                                                                            *
 * The removal half of save_control_to_frameworks(), which is                 *
 * INSERT ... ON DUPLICATE KEY UPDATE and therefore only ever ADDS. Without   *
 * this, a control's framework membership is one-way: the Edit modal can put  *
 * a control into a framework but never take it out, and a mapping row the    *
 * user deleted reappears on the next open.                                   *
 *                                                                            *
 * Deliberately NOT called from update_framework_control(). Its other caller  *
 * (the legacy updateControlResponse()) always sets `map_frameworks`, even to *
 * an empty array, so pruning there would delete every mapping on a request   *
 * that never rendered the widget -- the same isset([]) data-loss trap        *
 * updateControlById()'s omission guard exists to avoid. Only a caller that   *
 * can prove its submission is the COMPLETE set should call this.             *
 *                                                                            *
 * An empty `$map_frameworks` is a legitimate instruction to remove them all, *
 * not a no-op -- that is the whole point of being told explicitly.           *
 *                                                                            *
 * @param int   $control_id                                                   *
 * @param array $map_frameworks rows as parse_control_map_frameworks_request()*
 *                              returns them: [framework_id, name, text]      *
 *****************************************************************************/
function delete_control_to_frameworks_except($control_id, $map_frameworks)
{
    $control_id = (int)$control_id;

    // Cast every id: they are interpolated into the IN () list as integers
    // because PDO cannot bind a variable-length list, and a framework id that
    // arrived as a string from $_POST must not reach the SQL as one.
    $keep = [];
    foreach ((array)$map_frameworks as $row) {
        $framework_id = (int)(is_array($row) ? ($row[0] ?? 0) : $row);
        if ($framework_id > 0) {
            $keep[$framework_id] = $framework_id;
        }
    }

    $db = db_open();

    if ($keep) {
        $sql = "DELETE FROM `framework_control_mappings` WHERE `control_id` = :control_id AND `framework` NOT IN (" . implode(',', $keep) . ");";
    } else {
        $sql = "DELETE FROM `framework_control_mappings` WHERE `control_id` = :control_id;";
    }

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    db_close($db);
}

/*****************************************************************************
 * FUNCTION: SAVE CONTROL TO FRAMEWORK BY IDS                                *
 *****************************************************************************
 * Ensures this control is a member of each of these frameworks. MEMBERSHIP
 * ONLY -- this is the arm that runs when the caller gave a bare list of
 * framework ids and NO citation information at all.
 *
 * Both call sites are that arm: add_framework_control() and
 * update_framework_control() each call it as
 * `save_control_to_framework_by_ids($control_id, $framework_ids)` on the
 * `else if (count($framework_ids) > 0)` branch -- reached only when the request
 * carried no `map_frameworks` rows. Neither has ever passed a third or fourth
 * argument, so the defaults below are what actually runs in production.
 *
 * OMISSION MEANS LEAVE IT ALONE, and for this function that is stronger than
 * "preserve the column": a framework the control is ALREADY mapped into is
 * skipped entirely. A caller that named no reference has nothing to say about
 * the clause a framework cites this control under, and `reference_name` is part
 * of the table's only UNIQUE key -- so inventing one is not a harmless no-op,
 * it targets a DIFFERENT ROW.
 *
 * That is the bug this replaces, and it had two distinct failure modes:
 *
 *   1. DUPLICATE JUNK ROWS, the common one. `$reference_name` defaulted to ""
 *      and was then rewritten to the control's own `control_number`. On any
 *      catalogue whose controls come from somewhere other than the framework
 *      being cited -- every SCF-derived one, which is most of what SimpleRisk
 *      ships -- that is NOT the framework's reference: the stored row says
 *      "5.1" and the insert says "GOV-01". Different key, so the ON DUPLICATE
 *      branch never ran and MySQL inserted a SECOND mapping row. Measured on
 *      the development dataset: 1,435 of 4,437 mappings carry a reference_name
 *      that differs from their control's number, so a bare add/update of any of
 *      those controls doubled its mapping rows. The Statement of Applicability
 *      aggregates `reference_name` per framework (soa_reference_map(),
 *      includes/soa.php), so the junk row put the SCF code back into the
 *      Reference column beside the ISO clauses -- re-introducing, through the
 *      data, exactly the RULE 2b defect that file exists to fix.
 *
 *   2. BLANKED CLAUSE TEXT, when the invented reference DID match the stored
 *      one (the other 3,002 mappings). Then the ON DUPLICATE branch ran and the
 *      unconditional `reference_text = VALUES(reference_text)` wrote the ""
 *      default over the clause text -- text an analyst typed into the control
 *      edit modal, or the AI control-reference job sourced from the
 *      authoritative document. Same defect as
 *      add_framework_control_to_framework() one function down, and it likewise
 *      undid that job's own COALESCE guard.
 *
 * WHEN A REFERENCE *IS* NAMED the function behaves like its sibling: it
 * insert-or-updates the (control, framework, reference) row, and an omitted
 * `$reference_text` coalesces so it preserves rather than blanks. An explicit
 * "" still CLEARS the text -- absence and emptiness are deliberately different
 * instructions here, the same convention save_control_to_frameworks() and
 * add_framework_control_to_framework() already settled on.
 *
 * @param int          $control_id
 * @param array|string $framework_ids array, or a comma-separated list
 * @param string|null  $reference_name null/false = not supplied; on a FRESH row
 *                                     the control's own number is used instead
 * @param string|null  $reference_text null/false = not supplied (preserve);
 *                                     "" = clear
 *****************************************************************************/
function save_control_to_framework_by_ids($control_id, $framework_ids, $reference_name = null, $reference_text = null)
{
    // If no framework IDs were specified exit. Before the database is opened --
    // the old order opened a connection and then returned without closing it.
    if (!$framework_ids) {
        return;
    }

    // Create an array of framework IDs
    if (!is_array($framework_ids)) {
        $framework_ids = explode(",", $framework_ids);
    }

    // false is ABSENCE, not "". PDO binds false as an empty string, and `?? false`
    // is this codebase's dominant idiom for "no value", so a caller reading an
    // optional payload field would otherwise clear the column by accident. Same
    // guard as add_framework_control_to_framework().
    if ($reference_name === false) {
        $reference_name = null;
    }
    if ($reference_text === false) {
        $reference_text = null;
    }

    // Did the caller tell us WHICH clause this is? Neither call site does.
    $reference_supplied = ($reference_name !== null);

    // Open the database connection
    $db = db_open();

    // The frameworks this control is ALREADY mapped into, read once. Only needed
    // on the no-reference path, where they are skipped outright.
    $already_mapped = [];
    if (!$reference_supplied) {
        $stmt = $db->prepare("SELECT DISTINCT `framework` FROM `framework_control_mappings` WHERE `control_id` = :control_id;");
        $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $mapped_framework) {
            $already_mapped[(int)$mapped_framework] = true;
        }

        // The reference name written on a FRESH row when none was supplied: the
        // control's own number. `reference_name` is varchar(255) NOT NULL and part
        // of the UNIQUE key, so NULL is not available and "" is the floor. The
        // control's number is the same fallback build_soa_rows() prints
        // (includes/soa.php) for a control the framework cites without a
        // reference -- at worst the wrong catalogue's code, which is still
        // traceable, where blank is not.
        $control = get_framework_control($control_id);
        $reference_name = $control['control_number'] ?? "";
    }

    $stmt = $db->prepare("
        INSERT IGNORE INTO framework_control_mappings (
            control_id,
            framework,
            reference_name,
            reference_text
        ) VALUES (
            :control_id,
            :framework_id,
            :reference_name,
            :reference_text
        )
        ON DUPLICATE KEY UPDATE
            -- COALESCE, not a bare VALUES(): a caller that says NOTHING about the
            -- clause text arrives here as NULL and must leave the stored text as
            -- it found it. An explicit '' is still a value and still clears.
            --
            -- `reference_name` is deliberately NOT in this list. It is part of the
            -- table's only UNIQUE key, so this branch can only have been reached
            -- by a row whose reference_name already equals VALUES(reference_name);
            -- assigning it was a provable no-op that read as though the column
            -- could be renamed here, which it cannot.
            reference_text = COALESCE(VALUES(reference_text), reference_text)
    ");

    foreach ($framework_ids as $framework_id) {
        $framework_id = (int)$framework_id;
        if (!$framework_id) {
            continue;
        }

        // Already a member, and we were told nothing about the citation. The
        // membership this call exists to assert is already true, so there is
        // nothing to write -- see the banner.
        if (isset($already_mapped[$framework_id])) {
            write_debug_log(
                "Mapping left alone: control {$control_id} is already mapped to framework {$framework_id} and no reference was supplied",
                'debug'
            );
            continue;
        }

        $stmt->execute([
            ':control_id' => $control_id,
            ':framework_id' => $framework_id,
            ':reference_name' => $reference_name,
            ':reference_text' => $reference_text
        ]);

        // A rowcount of 1=insert, 2=update
        if ($stmt->rowCount() > 0) {
            write_debug_log(
                "Saved mapping: control {$control_id}, framework {$framework_id}, reference '{$reference_name}'",
                'info'
            );
        }
    }

    // Close the database connection
    db_close($db);
}

/*****************************************************************************
 * FUNCTION: ADD A FRAMEWORK CONTROL TO A FRAMEWORK                          *
 *****************************************************************************
 * Creates -- or refreshes -- one `framework_control_mappings` row: this
 * framework cites this control, under this clause number.
 *
 * THIS IS THE BULK RE-APPLY PATH, and that is what settles the write
 * semantics below. Every caller is framework-application machinery, not a
 * human editing one row: the SCF Extra's install/upgrade job and its
 * "apply this authoritative source" action, the SCF upgrade migrations, and
 * the UCF Extra's import. They run over an entire catalogue -- thousands of
 * mappings per framework -- and they run AGAIN every time a customer
 * re-applies or upgrades a framework.
 *
 * OMISSION MEANS PRESERVE. `$reference_text` defaults to null, and the
 * ON DUPLICATE KEY UPDATE coalesces, so a caller that says nothing about the
 * clause text leaves whatever is stored exactly as it found it. The default
 * used to be "", which PDO binds as a value, so the unconditional
 * `reference_text = VALUES(reference_text)` blanked the column on every
 * control already mapped to the framework -- and NONE of the callers pass a
 * text, so re-applying any SCF or UCF framework erased the lot. It also
 * silently undid the COALESCE guard in the AI control-reference enhancement
 * job (extras/artificial_intelligence/jobs/ai_control_reference_enhance.php),
 * because extras/complianceforgescf/index.php calls this function and then
 * enqueues that job against the row it just blanked. There is no history on
 * this table and the Statement of Applicability quotes this column, so the
 * loss is silent and unrecoverable.
 *
 * AN EXPLICIT '' STILL CLEARS, and that asymmetry is deliberate. The whole
 * value of a null default is that absence and emptiness stop being the same
 * instruction; collapsing '' back into "preserve" would leave no way to say
 * "this framework cites this clause with no normative text" at all. It is
 * also the convention the sibling writer already settled on -- see
 * save_control_to_frameworks()'s `reference_subject`, where omission
 * preserves and an explicit '' clears because that is a user emptying the
 * field. `false` is treated as absence, not as '': it is this codebase's
 * other idiom for "no value" (the AI job's payload defaults are all
 * `?? false`), PDO would bind it as '', and no caller would ever express
 * "clear this" as a boolean.
 *
 * `reference_subject` IS NOT IN EITHER LIST, deliberately. On the update
 * branch the framework's own title for the control is not this function's
 * business -- it is sourced by the AI job and by the control edit modal, and
 * a bulk re-apply that knows nothing about it must not touch it. On a fresh
 * insert it takes the column default, NULL, which is the honest value: NULL
 * means "this framework has not told us its title" and the SoA falls back to
 * the SimpleRisk control name for that row. The same now goes for a fresh
 * insert's `reference_text`.
 *
 * @param int         $control_id     SimpleRisk framework control id.
 * @param int         $framework_id   Framework the control is being cited by.
 * @param string|null $reference_name The framework's clause number. Null falls
 *                                    back to the control's own control_number.
 *                                    Part of the table's UNIQUE key.
 * @param string|null $reference_text The clause's normative text. Null (or
 *                                    false) leaves a stored value untouched.
 *****************************************************************************/
function add_framework_control_to_framework($control_id, $framework_id, $reference_name = null, $reference_text = null)
{
    if ($framework_id <= 0 || $control_id <= 0) {
        return;
    }

    $db = db_open();

    $control = get_framework_control($control_id);

    if ($reference_name === null) {
        $reference_name = $control['control_number'] ?? "";
    }

    // Normalise "no value" to a real SQL NULL before it reaches the COALESCE.
    // PDO binds false as '', which the COALESCE would read as content and
    // write -- the one character this whole guard exists to stop.
    if ($reference_text === false) {
        $reference_text = null;
    }

    $stmt = $db->prepare("
        INSERT INTO framework_control_mappings (
            control_id,
            framework,
            reference_name,
            reference_text
        )
        VALUES (
            :control_id,
            :framework,
            :reference_name,
            :reference_text
        )
        ON DUPLICATE KEY UPDATE
            reference_text = COALESCE(VALUES(reference_text), reference_text)
    ");

    $stmt->execute([
        ':control_id'     => $control_id,
        ':framework'      => $framework_id,
        ':reference_name' => $reference_name,
        ':reference_text' => $reference_text
    ]);

    // The text's LENGTH, not the text. What the log has to answer is "did this
    // call preserve or write", and that is answerable from presence alone --
    // whereas the value is paragraph-length normative prose and this line is
    // emitted once per mapping, thousands of times per framework applied.
    write_debug_log(
        "Saved SimpleRisk control ID '{$control_id}' to framework ID '{$framework_id}' with reference name '{$reference_name}'. Reference text: " .
        ($reference_text === null
            ? 'none supplied, so any stored text is kept'
            : 'supplied, ' . strlen((string)$reference_text) . ' characters') . ".",
        "debug"
    );

    db_close($db);
}

/*****************************************************************************
 * FUNCTION: CLONE A FRAMEWORK'S CONTROL MAPPINGS                            *
 *****************************************************************************
 * Gives a NEWLY CREATED framework the same `framework_control_mappings` rows
 * the source framework has (Task 64). Josh's purpose statement is what settles
 * the scope of this: "you might want to use the same controls as one framework,
 * but scope it differently" — the clone is a fresh scoping canvas over the SAME
 * control set, so the control set is precisely what is copied and nothing else.
 *
 * WHAT IS COPIED, AND WHY ALL THREE CITATION COLUMNS. `reference_name`,
 * `reference_text` and `reference_subject` are the FRAMEWORK's own citation of
 * the control — the clause number it cites it by, the normative statement, and
 * the framework's own title for it as the Statement of Applicability prints it.
 * They are the point of a mapping, not decoration: a clone that copied only
 * `control_id` would produce a framework with the right controls and no idea
 * what any of them are called in it. `reference_name` is also part of the
 * table's UNIQUE key (`control_id`, `framework`, `reference_name`), so a control
 * cited twice under two clause numbers is TWO rows and both must survive.
 *
 * WHAT IS DELIBERATELY NOT COPIED: `framework_control_applicability`. Copying
 * the exclusions would defeat the feature (the whole point is to re-scope), and
 * it would fabricate audit history — those rows carry `decided_by`/`decided_at`,
 * so a verbatim copy records a named person making a scoping decision about a
 * framework that did not exist when they made it. It falls out cleanly from the
 * storage rule rather than needing a special case: absence of a row IS
 * "applicable" (includes/applicability.php), so a clone with no rows starts with
 * every control applicable, which is the correct starting state for a canvas.
 * The way to keep that true is simply never to touch that table here.
 *
 * MAPPINGS TO SOFT-DELETED CONTROLS COPY TOO — no join, no filter. A control
 * deleted while carrying test history is KEPT in a `deleted=1` state
 * (delete_framework_controls_batch()), and the rail's own count already excludes
 * those (get_framework_control_counts() INNER JOINs on `fc.deleted = 0`), so
 * copying them changes no number the user sees. What it does buy is that the
 * clone and its source behave identically if such a control is ever restored,
 * instead of the clone quietly having a different control set than the framework
 * it says it was cloned from.
 *
 * ONE STATEMENT, AND THAT IS THE TRANSACTION. A half-cloned framework is worse
 * than no clone, so the copy is a single INSERT ... SELECT inside an explicit
 * transaction: either every mapping lands or none does. This is also why there
 * is no per-row loop and no cap. Task 54 found delete_framework_controls_batch()
 * fires one synchronous trigger_workflow_event() per control POST-commit at
 * ~327ms/control, which is why it needed a measured 2,000-row cap and
 * set_time_limit(600); the create side has NO equivalent — `framework_control_
 * mappings` carries no trigger and no workflow event, so the cost here is the
 * insert and nothing else. MEASURED on the dev instance against framework 133,
 * the largest mapping set in the product at 1,534 rows: 52.7ms for the whole
 * copy, ~0.034ms/row. There is nothing a cap would protect against at that rate,
 * and a cap picked anyway would be exactly the kind of constant this branch has
 * twice watched go stale.
 *
 * AUTHORIZATION IS THE CALLER'S JOB, as everywhere else in this file. The API
 * layer gates on `add_new_frameworks` — the permission that gates CREATING a
 * framework — before calling in, because a clone is a create.
 *
 * @param  int $source_framework_id the framework whose mappings to copy
 * @param  int $target_framework_id the framework to copy them onto
 * @return int the number of mapping rows written
 * @throws Throwable on a DB failure, with nothing written
 */
function clone_framework_control_mappings($source_framework_id, $target_framework_id) {

    $source_framework_id = (int)$source_framework_id;
    $target_framework_id = (int)$target_framework_id;

    // Cloning a framework onto itself would be a no-op at best (every row
    // collides with itself on the UNIQUE key) and is never something a caller
    // means, so it is refused rather than silently absorbed.
    if ($source_framework_id <= 0 || $target_framework_id <= 0 || $source_framework_id === $target_framework_id) {
        return 0;
    }

    $db = db_open();
    $db->beginTransaction();

    try {

        // INSERT IGNORE, not a bare INSERT: the target is a framework the caller
        // just created, so there is nothing to collide with — but if a future
        // caller ever clones onto a populated framework, a duplicate citation
        // must be skipped rather than abort the whole copy.
        $stmt = $db->prepare("
            INSERT IGNORE INTO `framework_control_mappings`
                (`control_id`, `framework`, `reference_name`, `reference_text`, `reference_subject`)
            SELECT `control_id`, :target, `reference_name`, `reference_text`, `reference_subject`
            FROM `framework_control_mappings`
            WHERE `framework` = :source
        ");
        $stmt->execute([':target' => $target_framework_id, ':source' => $source_framework_id]);

        $copied = $stmt->rowCount();

        $db->commit();

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        db_close($db);

        throw $e;
    }

    db_close($db);

    write_debug_log(
        "Cloned {$copied} control mapping(s) from framework {$source_framework_id} to framework {$target_framework_id}.",
        'info'
    );

    return $copied;
}

/********************************************
 * FUNCTION: REMOVE FRAMEWORK FROM CONTROLS *
 ********************************************/
/*function remove_framework_from_controls($framework_id)
{
    // Open the database connection
    $db = db_open();

    write_debug_log("Removing SimpleRisk framework id \"" . $framework_id . "\" from existing controls.", 'info');

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
    write_debug_log("Removing SimpleRisk framework id \"" . $framework_id . "\" from existing controls.", 'info');

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
            fcm.*,
            f.value framework_id,
            f.name framework_name, 
            f.description framework_description
        FROM 
            `framework_control_mappings` fcm
        JOIN `frameworks` f ON fcm.framework = f.value
        WHERE fcm.control_id = :control_id
        ORDER BY f.name, fcm.reference_name;
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
function get_exist_mapping_control_framework($control_id, $framework_id, $reference_name="")
{
    // Open the database connection
    $db = db_open();
    $sql = "SELECT * FROM `framework_control_mappings`  WHERE control_id = :control_id AND framework=:framework_id AND reference_name=:reference_name;";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
    $stmt->bindParam(":reference_name", $reference_name, PDO::PARAM_STR);
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
        case "control_number":
            $sql .= " ORDER BY control_number {$order_dir} ";
        break;
        case "associated_frameworks":
            // If encryption extra is disabled, sort by query
            if(!encryption_extra())
            {
                $sql .= " ORDER BY framework_names {$order_dir} ";
            }
        break;
        case "control_family":
            $sql .= " ORDER BY t5.name {$order_dir} ";
        break;
        case "control_phase":
            $sql .= " ORDER BY t3.name {$order_dir} ";
        break;
        case "control_current_maturity":
            $sql .= " ORDER BY t7.name {$order_dir} ";
        break;
        case "control_desired_maturity":
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

/**********************************************************************
 * FUNCTION: GET GOVERNANCE MATURITY GAP BY FAMILY (dashboard radar) *
 **********************************************************************/
// Per-family average current vs desired control maturity, backing the
// governance dashboard's "Current vs Desired Maturity by Control Family" radar.
// Mirrors display_control_maturity_spider_chart() but honours the dashboard
// framework-scope contract: null = All Frameworks (every non-deleted control
// counted once — no mapping join, so a control mapped to several frameworks is
// not double-counted), [] = none selected (returns [] before any DB call),
// [id] = only controls mapped to that framework. Returns one row per family:
// ['family' => name, 'current' => avg(round 1), 'desired' => avg(round 1)].
function get_governance_maturity_gap_by_family($framework_ids = null)
{
    if ($framework_ids !== null && empty($framework_ids)) { return []; }

    $db = db_open();
    $join = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $join = "INNER JOIN `framework_control_mappings` m ON m.control_id = t1.id AND m.framework IN ({$ph})";
        $params = array_values($framework_ids);
    }
    // Only controls that belong to a real family produce a radar spoke.
    $stmt = $db->prepare("
        SELECT f.name AS family,
               AVG(t1.control_maturity) AS current_avg,
               AVG(t1.desired_maturity) AS desired_avg
        FROM `framework_controls` t1
            {$join}
            LEFT JOIN `family` f ON t1.family = f.value
        WHERE t1.deleted = 0 AND t1.family IS NOT NULL AND t1.family <> 0
        GROUP BY t1.family, f.name
        ORDER BY f.name ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $out = [];
    foreach ($rows as $r) {
        if ($r['family'] === null || $r['family'] === '') { continue; }
        $out[] = [
            'family'  => $r['family'],
            'current' => round((float) $r['current_avg'], 1),
            'desired' => round((float) $r['desired_avg'], 1),
        ];
    }
    return $out;
}

/*******************************************************************
 * FUNCTION: GET GOVERNANCE MATURITY GAP ITEMS (dashboard tables) *
 *******************************************************************/
// Controls in a maturity-gap bucket for the governance dashboard's Below/At/
// Above Maturity tables. $bucket: 'below' (current < desired), 'at' (equal),
// 'above' (current > desired). Same framework-scope contract as
// get_governance_maturity_gap_by_family(). Returns
// ['id','control_number','short_name','current_maturity','desired_maturity']
// rows; id feeds the control-editor deep-link.
function get_governance_maturity_gap_items($bucket, $framework_ids = null, $limit = 100)
{
    if ($framework_ids !== null && empty($framework_ids)) { return []; }

    switch ($bucket) {
        case 'below': $cond = 't1.control_maturity < t1.desired_maturity'; break;
        case 'above': $cond = 't1.control_maturity > t1.desired_maturity'; break;
        case 'at':
        default:      $cond = 't1.control_maturity = t1.desired_maturity'; break;
    }

    $db = db_open();
    $join = '';
    $params = [];
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $join = "INNER JOIN `framework_control_mappings` m ON m.control_id = t1.id AND m.framework IN ({$ph})";
        $params = array_values($framework_ids);
    }
    $stmt = $db->prepare("
        SELECT DISTINCT t1.id, t1.control_number, t1.short_name,
               t1.control_maturity AS current_maturity, t1.desired_maturity AS desired_maturity,
               cm.name AS current_maturity_name, dm.name AS desired_maturity_name
        FROM `framework_controls` t1
            {$join}
            LEFT JOIN `control_maturity` cm ON t1.control_maturity = cm.value
            LEFT JOIN `control_maturity` dm ON t1.desired_maturity = dm.value
        WHERE t1.deleted = 0 AND {$cond}
        ORDER BY t1.control_number ASC, t1.short_name ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $rows;
}

/****************************************
 * FUNCTION: DISPLAY ADD FRAMEWORK FORM *
 ****************************************
 * $include_soa renders the "Statement of applicability" card (spec §5.4a).
 *
 * It is a parameter rather than an unconditional section because this function
 * has THREE callers and only two of them can persist those fields. The Define
 * Control Frameworks page's Add and Edit modals post to the v2 CRUD endpoints
 * (createFrameworkCrud() / updateFrameworkById()), which read them. The Initiate
 * Audits page's Edit modal (compliance/audit_initiation.php) submits the whole
 * form to the legacy POST /governance/update_framework, whose handler does not —
 * and its opener (governance.js) prefills only name/description/parent, so a card
 * rendered there would submit two EMPTY fields and blank a stored scope statement
 * on every unrelated rename. display_update_framework_modal() therefore passes
 * false for that page.
 *
 * $include_status renders the Active/Inactive field and is a SEPARATE parameter
 * for the same reason, reached the same way: updateFrameworkResponse() — the
 * legacy handler behind the Initiate Audits page's Edit modal — does not read
 * `status`, and governance.js does not prefill it, so a select rendered there
 * would sit on its "Active" default for an inactive framework and then discard
 * whatever the user chose. Two flags rather than one because they are two
 * independent facts about a caller, even though today's three callers happen to
 * answer both the same way.
 *
 * $seed_soa_defaults prefills the SoA card's default inclusion justification and
 * is the one flag the three callers DO answer differently: only the create form
 * passes it. This function renders the body of the Add modal AND of the Edit
 * modal, so "is this the create form?" is not derivable from the other two flags
 * — display_update_framework_modal('governance') answers them exactly as
 * governance/index.php's Add modal does. See
 * display_framework_inclusion_justification_edit() for what the seed is and why
 * the Edit modal must not receive it.
 *
 * $id_prefix NAMESPACES EVERY FIELD ID THIS FUNCTION EMITS, and is what keeps
 * the Add and the Edit modal from colliding. This function renders the body of
 * BOTH, into the same document, so a field id that is a bare column name is a
 * duplicate id -- and document.getElementById() / a bare `#id` selector resolve
 * to the FIRST match, so an Edit-modal <label for> reached the ADD modal's
 * field, as did any unscoped script or test locator, silently. Callers pass
 * 'add_' or 'update_' after the modal they are rendering.
 *
 * The prefixed ids are ALSO exactly the ids the page's WYSIWYG bootstrap
 * already assigned by hand -- $("#framework--add [name=framework_description]")
 * .attr("id", "add_framework_description") in governance/index.php and
 * compliance/audit_initiation.php -- so those lines are now no-ops rather than
 * a second, conflicting naming scheme. Do not "tidy" the prefixes into
 * something else without changing them and the editor/e2e selectors that hang
 * off the resulting `<id>_ifr` iframes together.
 *
 * The default is '' rather than a generated value so ids stay stable across
 * renders; the four call sites all pass one.
 */
function display_add_framework($include_soa = true, $include_status = true, $seed_soa_defaults = false, $id_prefix = '') {

    global $lang, $escaper;

    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra()) {

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("framework");
        $inactive_fields = get_inactive_fields("framework");

        // The admin-configured field order can freely interleave core and
        // custom fields (Customization Extra), so it can't be split into the
        // fixed General/Placement sections below -- everything lands in one
        // card instead. display_control_*_edit()'s own sr-qfield markup
        // (design-system.md §8) still applies per field either way.
        echo "<section class='sr-qcard'><div class='sr-qcard-head'><span class='sr-qcard-icon'><i class='fa fa-file-lines' aria-hidden='true'></i></span><h3>{$escaper->escapeHtml($lang['General'])}</h3></div><div class='sr-qcard-body'><div class='sr-qgrid'>";
        display_detail_framework_fields_add($active_fields, $id_prefix);
        display_detail_framework_fields_add($inactive_fields, $id_prefix);
        // Appended after the loops for the same reason the SoA card sits outside
        // them: status is a core column, not a customizable field, so it is in
        // neither get_active_fields('framework') nor get_inactive_fields('framework')
        // and would otherwise vanish for every customer running the Customization
        // Extra — taking the only non-destructive way to retire a framework with it.
        if ($include_status) {
            display_framework_status_edit(true, $id_prefix);
        }
        echo "</div></div></section>";

        // Outside the loop above on purpose: the SoA fields are core, not
        // customizable, so they are in neither get_active_fields('framework') nor
        // get_inactive_fields('framework') and would otherwise disappear for every
        // customer running the Customization Extra.
        if ($include_soa) {
            display_framework_soa_card($seed_soa_defaults, $id_prefix);
        }

    // If the customization extra is disabled, shows fields by default fields
    } else {

        echo "
            <section class='sr-qcard'>
                <div class='sr-qcard-head'>
                    <span class='sr-qcard-icon'><i class='fa fa-file-lines' aria-hidden='true'></i></span>
                    <h3>{$escaper->escapeHtml($lang['General'])}</h3>
                </div>
                <div class='sr-qcard-body'>
                    <div class='sr-qgrid'>
        ";
                        display_framework_name_edit(true, $id_prefix);
                        if ($include_status) {
                            display_framework_status_edit(true, $id_prefix);
                        }
                        display_framework_description_edit(true, $id_prefix);
        echo "
                    </div>
                </div>
            </section>
            <section class='sr-qcard'>
                <div class='sr-qcard-head'>
                    <span class='sr-qcard-icon'><i class='fa fa-sitemap' aria-hidden='true'></i></span>
                    <h3>{$escaper->escapeHtml($lang['Placement'])}</h3>
                </div>
                <div class='sr-qcard-body'>
                    <div class='sr-qgrid'>
        ";
                        display_framework_parent_edit(true, $id_prefix);
        echo "
                    </div>
                </div>
            </section>
        ";

        if ($include_soa) {
            display_framework_soa_card($seed_soa_defaults, $id_prefix);
        }

    }
}

/****************************************************
* FUNCTION: DISPLAY DETAIL FRAMEWORK FIELDS FOR ADD *
*****************************************************/
function display_detail_framework_fields_add($fields, $id_prefix = '') {

    foreach($fields as $field) {

        if($field['is_basic'] == 1) {

            if($field['active'] == 0) {

                $display = false;

            } else {

                $display = true;

            }
            
            switch($field['name']) {
                case 'FrameworkName':
                    display_framework_name_edit($display, $id_prefix);
                    break;
                case 'ParentFramework':
                    display_framework_parent_edit($display, $id_prefix);
                    break;
                case 'FrameworkDescription':
                    display_framework_description_edit($display, $id_prefix);
                    break;
            }

        } else {

            if($field['active'] == 0) {
                continue;
            }

            // Display the custom field edit (no-op if customization extra is disabled)
            call_extra_function(
                'customization_extra',
                __DIR__ . '/../extras/customization/index.php',
                'display_custom_field_edit',
                [$field, [], "label", false, "", $id_prefix]
            );
        }
    }
}

/***********************************
* FUNCTION: DISPLAY FRAMEWORK NAME *
************************************/
function display_framework_name_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'framework_name');

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['FrameworkName'])}<span class='required'>*</span></label>
            <input type='text' required id='{$id}' name='framework_name' autocomplete='off' maxlength='100' class='form-control' title='{$escaper->escapeHtml($lang['FrameworkName'])}'/>
        </div>
    ";

}

/*************************************
* FUNCTION: DISPLAY FRAMEWORK STATUS *
**************************************
* Active (1) / Inactive (2) — the two values `frameworks`.`status` has always
* held. Deactivating is the NON-destructive way to retire a framework, and the
* only other route off this page is Delete, which cannot be undone (nothing ever
* sets `framework_controls`.`deleted` back to 0).
*
* The pre-redesign page set this by dragging a row between the Active and
* Inactive tabs; the tabs went away with the tabbed shell and nothing replaced
* the gesture, so the field is how a status change is expressed now. The values
* are POSTed as `status` and read by createFrameworkCrud()/updateFrameworkById()
* (includes/api.php), which whitelist them to exactly these two.
*
* Deliberately NOT rendered on the Initiate Audits page's Edit modal — see
* display_add_framework()'s $include_status parameter for why.
*
* The id is namespaced by $id_prefix, not omitted. It used to be omitted, on the
* reasoning that display_add_framework() renders this markup into BOTH modals so
* any id would be a duplicate — true of a FIXED id, and the reason the field's
* <label for> then pointed at nothing at all. A per-modal id is unique and
* labellable, which is what the prefix buys; everything that READS the field
* still addresses it by name within its own modal, and that stays correct.
**************************************/
function display_framework_status_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'framework_status');

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['Status'])}</label>
            <select id='{$id}' name='status' class='form-select' title='{$escaper->escapeHtmlAttr($lang['Status'])}'>
                <option value='1' selected>{$escaper->escapeHtml($lang['Active'])}</option>
                <option value='2'>{$escaper->escapeHtml($lang['Inactive'])}</option>
            </select>
            <span class='sr-qhint'>{$escaper->escapeHtml($lang['FrameworkStatusHint'])}</span>
        </div>
    ";

}

/*************************************
* FUNCTION: DISPLAY FRAMEWORK PARENT *
**************************************
* The only field here whose control is NOT server-rendered: the <select> is
* fetched on modal show from GET /governance/parent_frameworks_dropdown (and
* /selected_parent_frameworks_dropdown for the Edit modal) and dropped into the
* container below.
*
* Those two endpoints are published on v1 AND v2 and their response is a
* documented contract, so the id cannot be baked into the HTML they return --
* and it must not be, since the same markup lands in two different modals. The
* container therefore CARRIES the id its injected select is to be given, in
* data-sr-field-id, and the three call sites that perform the injection
* (governance/index.php, governance-frameworks.js's openFrameworkForEdit(),
* governance.js for the Initiate Audits page) stamp it on. That keeps the id
* decision here, next to the <label for> that depends on it.
**************************************/
function display_framework_parent_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'framework_parent');

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['ParentFramework'])}</label>
            <div class='parent_frameworks_container w-100' data-sr-field-id='{$id}'></div>
        </div>
    ";

}

/*************************************
* FUNCTION: DISPLAY FRAMEWORK PARENT *
**************************************/
function display_framework_description_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    // 'add_framework_description' / 'update_framework_description' -- the exact
    // ids governance/index.php and compliance/audit_initiation.php already
    // assigned by hand before initialising the WYSIWYG editor on this textarea.
    // Rendering them here makes those .attr('id', ...) calls no-ops instead of a
    // competing scheme, and keeps the hugerte iframe's derived `<id>_ifr` (which
    // the e2e page object addresses) unchanged.
    $id = $escaper->escapeHtmlAttr($id_prefix . 'framework_description');

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['FrameworkDescription'])}</label>
            <textarea id='{$id}' name='framework_description' value='' class='form-control' rows='6' style='width:100%;' title='{$escaper->escapeHtml($lang['FrameworkDescription'])}'></textarea>
        </div>
    ";

}

/*******************************************************
* FUNCTION: DISPLAY FRAMEWORK SOA SCOPE STATEMENT      *
********************************************************
* The scope the framework is certified against — the first thing on an SoA cover
* page (spec §5.4a, ISO/IEC 27001:2022 clause 6.1.3(d)).
*
* A WYSIWYG FIELD (HugeRTE), like the framework description two rows above it.
* It was a plain <textarea> on the reasoning that the value was raw plain text
* which nothing rendered as HTML — true when it was written, and no longer the
* right trade. This is the one paragraph of free prose on the whole document,
* and in practice it is "the ISMS covers:" followed by a list of entities,
* sites and systems; a plain box could express that only as run-on lines that
* CSS `white-space` held together, and lost the structure entirely in the PDF.
*
* The editor is attached by governance/index.php (init_compact_editor(), the
* half-width-card variant — design-system.md §14b), which is why the id matters:
* 'add_scope_statement' / 'update_scope_statement', namespaced by $id_prefix
* like every other field this modal pair renders.
*
* NO maxlength. A rich-text widget stores MARKUP, so a byte cap over the raw box
* stops corresponding to anything the user typed — the attribute would let a
* short statement in a wrapper hit the limit, and would count nothing the user
* could see. The cap moves entirely to update_framework_soa_fields(), which
* measures the PURIFIED value and refuses rather than truncating; the modal
* surfaces that refusal inline. The value is never silently trimmed.
*
* The <textarea> stays in the markup: it is what HugeRTE binds to and what it
* writes back into, so `[name=scope_statement]` keeps working for the payload
* builder, for a customer running without JS, and for the e2e locators.
*******************************************************/
function display_framework_scope_statement_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'scope_statement');

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['IsmsScopeStatement'])}</label>
            <textarea id='{$id}' name='scope_statement' class='form-control' rows='3' style='width:100%;' title='{$escaper->escapeHtmlAttr($lang['IsmsScopeStatement'])}'></textarea>
            <span class='sr-qhint'>{$escaper->escapeHtml($lang['IsmsScopeStatementHint'])}</span>
        </div>
    ";

}

/*******************************************************
* FUNCTION: DISPLAY FRAMEWORK INCLUSION JUSTIFICATION  *
********************************************************
* One sentence that justifies every control that is simply applicable. Inclusion
* is the DEFAULT and is never stored per control, so this fills the SoA's
* Justification column for all of them.
*
* The sentence names the DRIVER — a risk assessment — rather than the framework.
* "Included because it is part of this framework" is circular and is exactly what
* ISO 6.1.3 does not accept: controls are derived from risk treatment, and Annex A
* is used afterwards as a completeness cross-check.
*
* $seed PREFILLS THE TEXTAREA with that sentence, and is passed only by the
* CREATE form. It was a placeholder on both forms until Task 67, on the reasoning
* that an unreviewed sentence must not reach a customer's SoA by inaction — which
* was right about the risk and wrong about the outcome: a placeholder is not a
* value, so 19 of the 20 frameworks on the development instance carried NULL, and
* every applicable control with no linked risks printed a BLANK justification
* cell. A blank cell is not a more honest document than a boilerplate one; it is
* the single most common finding against a hand-maintained SoA. So the sentence
* is now offered as an editable starting point, in the one place where a person
* is already being asked about the framework and can change or clear it.
*
* SEEDED ON CREATE ONLY. The Edit modal renders the same markup — display_add_-
* framework() emits both — and openFrameworkForEdit() (governance-frameworks.js)
* overwrites every SoA textarea with the stored value, including with '', so a
* seed there would be a value that flickers and, if that GET ever raced, one that
* could be saved over a framework the customer had deliberately cleared.
*
* The placeholder attribute STAYS. A textarea with content never shows it, so it
* is dead on the seeded form and still does its job on the Edit form, which is
* where a cleared field needs a hint about what belongs in it.
*
* NULL IS STILL REACHABLE, and still means "never asked": clearing the seeded box
* on the create form sends no key at all (frameworkSoaPayload(), governance-
* frameworks.js), so the column stays NULL and the SoA's missing-fields prompt
* still fires for that framework. Seeding changes the DEFAULT answer, not the
* customer's ability to decline to give one.
*******************************************************/
function display_framework_inclusion_justification_edit($display = true, $seed = false, $id_prefix = '') {

    global $lang, $escaper;

    // Escaped exactly once, at this sink. $lang holds raw text and the value
    // lands between the tags rather than in an attribute.
    $seeded = $seed ? $escaper->escapeHtml($lang['DefaultInclusionJustificationPlaceholder']) : '';

    $id = $escaper->escapeHtmlAttr($id_prefix . 'default_inclusion_justification');

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['DefaultInclusionJustification'])}</label>
            <textarea id='{$id}' name='default_inclusion_justification' class='form-control' rows='3' maxlength='" . FRAMEWORK_SOA_FIELD_MAX_BYTES . "' style='width:100%;' placeholder='{$escaper->escapeHtmlAttr($lang['DefaultInclusionJustificationPlaceholder'])}' title='{$escaper->escapeHtmlAttr($lang['DefaultInclusionJustification'])}'>{$seeded}</textarea>
            <span class='sr-qhint'>{$escaper->escapeHtml($lang['DefaultInclusionJustificationHint'])}</span>
        </div>
    ";

}

/*******************************************************
* FUNCTION: DISPLAY FRAMEWORK SOA CARD                 *
********************************************************
* The "Statement of applicability" card shared by the Add and Edit framework
* modals. Rendered outside the Customization Extra's field loop on purpose: these
* two are not customizable fields, so they are not in get_active_fields('framework')
* and would simply vanish for every customer who has that Extra enabled.
*
* $seed_inclusion prefills the inclusion justification with its default sentence
* and is passed only by the CREATE form — see
* display_framework_inclusion_justification_edit() for why the Edit modal must
* not get it. The SCOPE STATEMENT is deliberately never seeded: there is no
* boilerplate that is true of somebody else's ISMS scope, which is the same
* reason Task 64's framework clone leaves it blank.
*******************************************************/
function display_framework_soa_card($seed_inclusion = false, $id_prefix = '') {

    global $lang, $escaper;

    echo "
        <section class='sr-qcard'>
            <div class='sr-qcard-head'>
                <span class='sr-qcard-icon'><i class='fa fa-clipboard-check' aria-hidden='true'></i></span>
                <h3>{$escaper->escapeHtml($lang['StatementOfApplicability'])}</h3>
            </div>
            <div class='sr-qcard-body'>
                <div class='sr-qgrid'>
    ";
                    display_framework_scope_statement_edit(true, $id_prefix);
                    display_framework_inclusion_justification_edit(true, $seed_inclusion, $id_prefix);
    echo "
                </div>
            </div>
        </section>
    ";

}

/**************************************
 * FUNCTION: DISPLAY ADD CONTROL FORM *
 **************************************
 * $id_prefix namespaces every field id this function emits — see
 * display_add_framework() for the full reasoning. Same story: this renders the
 * body of BOTH the Add and the Edit control modal into one document, so a bare
 * column name as an id is a duplicate id. Callers pass 'add_' or 'update_'.
 **************************************/
function display_add_control($id_prefix = '') {

    global $lang, $escaper;

    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra()) {

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("control", "", 1);
        $inactive_fields = get_inactive_fields("control", "");

        // Same reasoning as display_add_framework()'s customization-extra
        // branch: the admin-configured field order can freely interleave
        // core and custom fields, so it can't be split into the fixed
        // Identity/Ownership/Classification/Mappings sections below --
        // everything lands in one card instead.
        echo "<section class='sr-qcard'><div class='sr-qcard-head'><span class='sr-qcard-icon'><i class='fa fa-id-card' aria-hidden='true'></i></span><h3>{$escaper->escapeHtml($lang['General'])}</h3></div><div class='sr-qcard-body'><div class='sr-qgrid'>";
        display_detail_control_fields_add($active_fields, $id_prefix);
        display_detail_control_fields_add($inactive_fields, $id_prefix);
        echo "</div></div></section>";

    // If the customization extra is disabled, shows fields by default fields
    } else {

        echo "
            <section class='sr-qcard'>
                <div class='sr-qcard-head'>
                    <span class='sr-qcard-icon'><i class='fa fa-id-card' aria-hidden='true'></i></span>
                    <h3>{$escaper->escapeHtml($lang['Identity'])}</h3>
                </div>
                <div class='sr-qcard-body'>
                    <div class='sr-qgrid'>
        ";
                        display_control_name_edit(true, $id_prefix);
                        display_control_longname_edit(true, $id_prefix);
                        display_control_number_edit2(true, $id_prefix);
                        display_control_description_edit(true, $id_prefix);
        echo "
                    </div>
                </div>
            </section>
            <section class='sr-qcard'>
                <div class='sr-qcard-head'>
                    <span class='sr-qcard-icon'><i class='fa fa-user-tie' aria-hidden='true'></i></span>
                    <h3>{$escaper->escapeHtml($lang['OwnershipAndMaturity'])}</h3>
                </div>
                <div class='sr-qcard-body'>
                    <div class='sr-qgrid'>
        ";
                        display_control_owner_edit(true, $id_prefix);
                        display_control_status_edit(true, $id_prefix);
                        display_current_maturity_edit(true, $id_prefix);
                        display_desired_maturity_edit(true, $id_prefix);
                        display_control_mitigation_percent_edit(true, $id_prefix);
        echo "
                    </div>
                </div>
            </section>
            <section class='sr-qcard'>
                <div class='sr-qcard-head'>
                    <span class='sr-qcard-icon'><i class='fa fa-tags' aria-hidden='true'></i></span>
                    <h3>{$escaper->escapeHtml($lang['Classification'])}</h3>
                </div>
                <div class='sr-qcard-body'>
                    <div class='sr-qgrid'>
        ";
                        display_control_class_edit(true, $id_prefix);
                        display_control_phase_edit(true, $id_prefix);
                        display_control_priority_edit(true, $id_prefix);
                        display_control_family_edit(true, $id_prefix);
                        display_control_type_edit(true, $id_prefix);
        echo "
                    </div>
                </div>
            </section>
            <section class='sr-qcard'>
                <div class='sr-qcard-head'>
                    <span class='sr-qcard-icon'><i class='fa fa-diagram-project' aria-hidden='true'></i></span>
                    <h3>{$escaper->escapeHtml($lang['MappingsAndGuidance'])}</h3>
                </div>
                <div class='sr-qcard-body'>
                    <div class='sr-qgrid'>
        ";
                        display_supplemental_guidance_edit(true, $id_prefix);
                        display_mapping_framework_edit();
                        display_mapping_asset_edit();
        echo "
                    </div>
                </div>
            </section>
        ";

    }
}

/**************************************************
* FUNCTION: DISPLAY DETAIL CONTROL FIELDS FOR ADD *
***************************************************/
function display_detail_control_fields_add($fields, $id_prefix = '') {

    foreach ($fields as $field) {

        if ($field['is_basic'] == 1) {
            
            if ($field['active'] == 0) {
                $display = false;
            } else {
                $display = true;
            }
            
            switch ($field['name']) {
                case 'ControlShortName':
                    display_control_name_edit($display, $id_prefix);
                    break;
                case 'ControlLongName':
                    display_control_longname_edit($display, $id_prefix);
                    break;
                case 'ControlDescription':
                    display_control_description_edit($display, $id_prefix);
                    break;
                case 'SupplementalGuidance':
                    display_supplemental_guidance_edit($display, $id_prefix);
                    break;
                case 'ControlOwner':
                    display_control_owner_edit($display, $id_prefix);
                    break;
                case 'MappedControlFrameworks':
                    display_mapping_framework_edit($display);
                    break;
                case 'MappedAssets':
                    display_mapping_asset_edit($display);
                    break;
                case 'ControlClass':
                    display_control_class_edit($display, $id_prefix);
                    break;
                case 'ControlPhase':
                    display_control_phase_edit($display, $id_prefix);
                    break;
                case 'ControlNumber':
                    display_control_number_edit2($display, $id_prefix);
                    break;
                case 'CurrentControlMaturity':
                    display_current_maturity_edit($display, $id_prefix);
                    break;
                case 'DesiredControlMaturity':
                    display_desired_maturity_edit($display, $id_prefix);
                    break;
                case 'ControlPriority':
                    display_control_priority_edit($display, $id_prefix);
                    break;
                case 'ControlFamily':
                    display_control_family_edit($display, $id_prefix);
                    break;
                case 'ControlType':
                    display_control_type_edit($display, $id_prefix);
                    break;
                case 'ControlStatus':
                    display_control_status_edit($display, $id_prefix);
                    break;
                case 'MitigationPercent':
                    display_control_mitigation_percent_edit($display, $id_prefix);
                    break;
            }

        } else {

            if ($field['active'] == 0) {
                continue;
            }

            // Display the custom field edit (no-op if customization extra is disabled)
            call_extra_function(
                'customization_extra',
                __DIR__ . '/../extras/customization/index.php',
                'display_custom_field_edit',
                [$field, [], "label", false, "", $id_prefix]
            );
        }
    }
}

/*********************************
* FUNCTION: DISPLAY CONTROL NAME *
**********************************/
function display_control_name_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'short_name');

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['ControlShortName'])}<span class='required'>*</span></label>
            <input type='text' id='{$id}' name='short_name' value='' class='form-control' maxlength='100' required title='{$escaper->escapeHtml($lang['ControlShortName'])}'>
        </div>
    ";

}

/**************************************
* FUNCTION: DISPLAY CONTROL LONG NAME *
***************************************/
function display_control_longname_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'long_name');

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['ControlLongName'])}</label>
            <input type='text' id='{$id}' name='long_name' value='' class='form-control' maxlength='65500' title='{$escaper->escapeHtml($lang['ControlLongName'])}'>
        </div>
    ";

}

/****************************************
* FUNCTION: DISPLAY CONTROL DESCRIPTION *
*****************************************/
function display_control_description_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    // 'add_control_description' / 'update_control_description' -- the ids
    // governance/index.php and compliance/audit_initiation.php already assigned
    // by hand before initialising the WYSIWYG editor here. Note the id does NOT
    // mirror the field's name ('description'): matching what those pages
    // already use keeps the hugerte iframe's derived `<id>_ifr` unchanged, which
    // is what the editor bootstrap and the e2e page object address.
    $id = $escaper->escapeHtmlAttr($id_prefix . 'control_description');

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['ControlDescription'])}</label>
            <textarea id='{$id}' name='description' value='' class='form-control' rows='6' style='width:100%;' maxlength='65500' title='{$escaper->escapeHtml($lang['ControlDescription'])}'></textarea>
        </div>
    ";

}

/******************************************
* FUNCTION: DISPLAY SUPPLEMENTAL GUIDANCE *
*******************************************/
function display_supplemental_guidance_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    // Same WYSIWYG-bootstrap reasoning as display_control_description_edit():
    // 'add_supplemental_guidance' / 'update_supplemental_guidance'.
    $id = $escaper->escapeHtmlAttr($id_prefix . 'supplemental_guidance');

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['SupplementalGuidance'])}</label>
            <textarea id='{$id}' name='supplemental_guidance' value='' class='form-control' rows='6' style='width:100%;' title='{$escaper->escapeHtml($lang['SupplementalGuidance'])}'></textarea>
        </div>
    ";

}

/**********************************
* FUNCTION: DISPLAY CONTROL OWNER *
***********************************/
function display_control_owner_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $id_prefix . 'control_owner';

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$escaper->escapeHtmlAttr($id)}'>{$escaper->escapeHtml($lang['ControlOwner'])}</label>" .
            create_dropdown("enabled_users", NULL, "control_owner", true, false, true, "title='{$escaper->escapeHtml($lang['ControlOwner'])}'", $escaper->escapeHtml($lang['Unassigned']), id: $id) . "
        </div>
    ";

}

/**********************************************
* FUNCTION: DISPLAY CONTROL MAPPING FRAMEWORK *
***********************************************/
function display_mapping_framework_edit($display = true) {

    global $lang, $escaper;

    // The marker input below is what lets updateControlById() (includes/api.php)
    // tell "the caller sent an empty set" from "the caller said nothing about
    // mappings" -- a distinction the form itself cannot express, because an
    // empty <tbody> and a widget that was never rendered serialize identically
    // (as nothing). Omission has to keep meaning PRESERVE: update_framework_control()
    // gates on isset(), isset([]) is TRUE, and the asset save is
    // delete-then-insert, so a request that merely FORGOT to mention mappings
    // must not be read as one asking to clear them.
    //
    // Emitted only when the field is actually shown. A Customization-Extra
    // layout that turns this field off renders it hidden and un-editable, and a
    // hidden widget has no business asserting authority over stored mappings.
    $submitted_marker = $display ? "<input type='hidden' name='map_frameworks_submitted' value='1'>" : "";

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            {$submitted_marker}
            <div class='row align-items-center'>
                <!-- No for=\"\": this labels a TABLE widget, not a single control, and
                     an empty for resolves to nothing -- which is exactly what the
                     four CRUD modals' label audit flags. A <label> is only valid
                     against a labelable element, so there is nothing here to point
                     it at. -->
                <label class='col-10 col-form-label sr-qlabel'>{$escaper->escapeHtml($lang['MappedControlFrameworks'])}</label>
                <div class='col-2 text-end col-form-label'>
                    <a href='javascript:void(0);' class='btn btn-primary btn-sm control-block--add-mapping'>{$escaper->escapeHtml($lang['AddMapping'])}</a>
                </div>
            </div>
            <div class='bg-light border p-3'>
                <table width='100%' class='table table-bordered mapping_framework_table mb-0'>
                    <thead>
                        <tr>
                            <th width='24%'>{$escaper->escapeHtml($lang['Framework'])}<span class='mapping-framework-required-mark required d-none'>*</span></th>
                            <th width='14%'>{$escaper->escapeHtml($lang['Control'])}<span class='mapping-framework-required-mark required d-none'>*</span></th>
                            <th width='22%' title='{$escaper->escapeHtml($lang['ReferenceSubjectHint'])}'>{$escaper->escapeHtml($lang['ReferenceSubject'])}</th>
                            <th width='32%'>{$escaper->escapeHtml($lang['ReferenceText'])}</th>
                            <th width='8%'>{$escaper->escapeHtml($lang['Actions'])}</th>
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

    // The marker input below is what lets updateControlById() (includes/api.php)
    // tell "the caller sent an empty set" from "the caller said nothing about
    // mappings" -- a distinction the form itself cannot express, because an
    // empty <tbody> and a widget that was never rendered serialize identically
    // (as nothing). Omission has to keep meaning PRESERVE: update_framework_control()
    // gates on isset(), isset([]) is TRUE, and the asset save is
    // delete-then-insert, so a request that merely FORGOT to mention mappings
    // must not be read as one asking to clear them.
    //
    // Emitted only when the field is actually shown. A Customization-Extra
    // layout that turns this field off renders it hidden and un-editable, and a
    // hidden widget has no business asserting authority over stored mappings.
    $submitted_marker = $display ? "<input type='hidden' name='mapped_assets_submitted' value='1'>" : "";

    echo "
        <div class='sr-qfield sr-qfield--full'" . ($display ? "" : " style='display: none;'") . ">
            {$submitted_marker}
            <div class='row text-align-center'>
                <!-- No for=\"\": see display_mapping_framework_edit(). -->
                <label class='col-10 col-form-label sr-qlabel'>{$escaper->escapeHtml($lang['MappedAssets'])}</label>
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
function display_control_class_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $id_prefix . 'control_class';

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$escaper->escapeHtmlAttr($id)}'>{$escaper->escapeHtml($lang['ControlClass'])}</label>" .
            create_dropdown("control_class", NULL, "control_class", true, false, true, "title='{$escaper->escapeHtml($lang['ControlClass'])}'", $escaper->escapeHtml($lang['Unassigned']), id: $id) . "
        </div>
    ";

}

/**********************************
* FUNCTION: DISPLAY CONTROL PHASE *
***********************************/
function display_control_phase_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $id_prefix . 'control_phase';

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$escaper->escapeHtmlAttr($id)}'>{$escaper->escapeHtml($lang['ControlPhase'])}</label>" .
            create_dropdown("control_phase", NULL, "control_phase", true, false, true, "title='{$escaper->escapeHtml($lang['ControlPhase'])}'", $escaper->escapeHtml($lang['Unassigned']), id: $id) . "
        </div>
    ";

}

/***********************************
* FUNCTION: DISPLAY CONTROL NUMBER *
************************************/
function display_control_number_edit2($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'control_number');

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['ControlNumber'])}</label>
            <input type='text' id='{$id}' name='control_number' value='' class='form-control' maxlength='100' title='{$escaper->escapeHtml($lang['ControlNumber'])}'>
        </div>
    ";

}

/*********************************************
* FUNCTION: DISPLAY CURRENT CONTROL MATURITY *
**********************************************/
function display_current_maturity_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $id_prefix . 'control_current_maturity';

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$escaper->escapeHtmlAttr($id)}'>{$escaper->escapeHtml($lang['CurrentControlMaturity'])}</label>" .
            create_dropdown("control_maturity", get_setting("default_current_maturity"), "control_current_maturity", true, false, true, "title='{$escaper->escapeHtml($lang['CurrentControlMaturity'])}'", id: $id) . "
        </div>
    ";

}

/*********************************************
* FUNCTION: DISPLAY DESIRED CONTROL MATURITY *
**********************************************/
function display_desired_maturity_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $id_prefix . 'control_desired_maturity';

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$escaper->escapeHtmlAttr($id)}'>{$escaper->escapeHtml($lang['DesiredControlMaturity'])}</label>" .
            create_dropdown("control_maturity", get_setting("default_desired_maturity"), "control_desired_maturity", true, false, true, "title='{$escaper->escapeHtml($lang['DesiredControlMaturity'])}'", id: $id) . "
        </div>
    ";

}

/*************************************
* FUNCTION: DISPLAY CONTROL PRIORITY *
**************************************/
function display_control_priority_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $id_prefix . 'control_priority';

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$escaper->escapeHtmlAttr($id)}'>{$escaper->escapeHtml($lang['ControlPriority'])}</label>" .
            create_dropdown("control_priority", NULL, "control_priority", true, false, true, "title='{$escaper->escapeHtml($lang['ControlPriority'])}'", $escaper->escapeHtml($lang['Unassigned']), id: $id) . "
        </div>
    ";

}

/***********************************
* FUNCTION: DISPLAY CONTROL FAMILY *
************************************/
function display_control_family_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $id_prefix . 'family';

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$escaper->escapeHtmlAttr($id)}'>{$escaper->escapeHtml($lang['ControlFamily'])}</label>" .
            create_dropdown("family", NULL, "family", true, false, true, "title='{$escaper->escapeHtml($lang['ControlFamily'])}'", $escaper->escapeHtml($lang['Unassigned']), id: $id) . "
        </div>
    ";

}

/*********************************
* FUNCTION: DISPLAY CONTROL TYPE *
**********************************/
function display_control_type_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    // Same marker idiom as display_mapping_framework_edit() /
    // display_mapping_asset_edit(), and for the same reason: a multiselect with
    // nothing selected submits NOTHING, so "the user cleared every control type"
    // and "this request never mentioned control types" are the same bytes on the
    // wire. updateControlById() (includes/api.php) has to be able to tell them
    // apart, because control types are rewritten delete-then-insert and omission
    // must keep meaning PRESERVE -- otherwise a PATCH that only touched
    // short_name wipes them, which is precisely the bug this marker fixes.
    //
    // Emitted only when the field is actually shown. A Customization-Extra
    // layout that turns this field off renders it hidden and un-editable, and a
    // hidden widget has no business asserting authority over stored mappings.
    $submitted_marker = $display ? "<input type='hidden' name='control_type_submitted' value='1'>" : "";

    $id = $id_prefix . 'control_type';

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            {$submitted_marker}
            <label class='sr-qlabel' for='{$escaper->escapeHtmlAttr($id)}'>{$escaper->escapeHtml($lang['ControlType'])}</label>
            <div class='w-100'>" .
                create_multiple_dropdown("control_type", array(1), returnHtml: true, customHtml: "title='{$escaper->escapeHtml($lang['ControlType'])}'", id: $id) . "
            </div>
        </div>
    ";

}

/***********************************
* FUNCTION: DISPLAY CONTROL STATUS *
************************************/
function display_control_status_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'control_status');

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['ControlStatus'])}</label>
            <select id='{$id}' name='control_status' class='form-select' title='{$escaper->escapeHtml($lang['ControlStatus'])}'>
                <option value='1'>{$escaper->escapeHtml($lang['Pass'])}</option>
                <option value='0'>{$escaper->escapeHtml($lang['Fail'])}</option>
                <option value='2' selected>{$escaper->escapeHtml($lang['NotTested'])}</option>
            </select>
        </div>
    ";

}

/***********************************************
* FUNCTION: DISPLAY CONTROL MITIGATION PERCENT *
************************************************/
function display_control_mitigation_percent_edit($display = true, $id_prefix = '') {

    global $lang, $escaper;

    $id = $escaper->escapeHtmlAttr($id_prefix . 'mitigation_percent');

    echo "
        <div class='sr-qfield'" . ($display ? "" : " style='display: none;'") . ">
            <label class='sr-qlabel' for='{$id}'>{$escaper->escapeHtml($lang['MitigationPercent'])}</label>
            <input type='number' id='{$id}' min='0' max='100' name='mitigation_percent' value='0' class='form-control' title='{$escaper->escapeHtml($lang['MitigationPercent'])}'>
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
                    case 'ControlID':
                        $html .= display_control_id_view($control['id'], $panel_name);
                        break;
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


/********************************
* FUNCTION: DISPLAY CONTROL ID *
*********************************/
function display_control_id_view($control_id, $panel_name="") {

    global $lang, $escaper;

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $display_control_id = (int)$control_id + 1000;
    $html = "
        <div class='row mb-2 {$panel_name}'>
            <div class='{$span1} text-right'><label>{$escaper->escapeHtml($lang['ControlID'])} : </label></div>
            <div class='{$span2}'>{$escaper->escapeHtml($display_control_id)}</div>
        </div>
    ";

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

    if ($panel_name=="top" || $panel_name=="bottom") {
        $span1 = "col-2";
        $span2 = "col-10";
    } else {
        $span1 = "col-4";
        $span2 = "col-8";
    }

    $collapse_id = "mapped-frameworks-collapse-" . (int)$control_id;
    $table_id    = "mapped-frameworks-table-" . (int)$control_id;

    // Get the count of mapped frameworks
    $count = get_control_framework_mappings_counts($control_id);

    return "
        <div class='mb-2'>
            <div class='row {$panel_name} cursor-pointer' role='button' data-bs-toggle='collapse' data-bs-target='#{$collapse_id}' data-control-id='{$control_id}'>
                <div class='{$span1} text-right'><label class='cursor-pointer'>{$escaper->escapeHtml($lang['MappedControlFrameworks'])} : </label></div>
                <div class='{$span2}'>
                    <span class='badge bg-secondary me-3 mapped-count'>{$escaper->escapeHtml($lang['Frameworks'])}: {$count['frameworks']} | {$escaper->escapeHtml($lang['Controls'])}: {$count['controls']}</span>
                    <i class='fa fa-chevron-right collapse-caret'></i>
                </div>
            </div>
            <div id='{$collapse_id}' class='collapse mt-2'>
                <div class='bg-light border p-3'>
                    <div class='text-muted loading-placeholder'>
                        Loading mapped frameworks…
                    </div>
                    <table id='{$table_id}' class='table table-bordered table-striped table-sm d-none mb-0' width='100%'>
                        <thead>
                            <tr>
                                <th>{$escaper->escapeHtml($lang['Framework'])}</th>
                                <th>{$escaper->escapeHtml($lang['Control'])}</th>
                                <th>{$escaper->escapeHtml($lang['ReferenceText'])}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    ";
}

/**************************************************
 * FUNCTION: GET CONTROL FRAMEWORK MAPPINGS COUNT *
 * Return a count of framework mappings for a     *
 * given control.                                 *
 **************************************************/
function get_control_framework_mappings_counts($control_id)
{
    $db = db_open();

    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT framework) AS frameworks,
            COUNT(DISTINCT reference_name) AS controls
        FROM `framework_control_mappings`
        WHERE control_id = :control_id;
    ");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    db_close($db);

    return [
        'control_id' => (int) $control_id,
        'frameworks' => (int) $result['frameworks'],
        'controls' => (int) $result['controls']
    ];
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

    $status_text = array("1" => $escaper->escapeHtml($lang["Pass"]), "0" => $escaper->escapeHtml($lang["Fail"]), "2" => $escaper->escapeHtml($lang["NotTested"]));
    
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
    $document = get_document_by_id($document_id);
    if (empty($document)) {
        write_debug_log("Document ID $document_id not found. Exiting.", 'warning');
        return false;
    }

    $db = db_open();

    try {
        // Fetch existing mappings if not refreshing
        if (!$refresh) {
            $stmt = $db->prepare("SELECT * FROM document_control_mappings WHERE document_id = :document_id ORDER BY score DESC");
            $stmt->execute([':document_id' => $document_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($results)) {
                write_debug_log("Cached mappings found for Document ID $document_id. Returning cached results.", 'debug');
                return $results;
            }
        }

        write_debug_log("Refreshing mappings for Document ID: {$document_id}", 'info');

        // Fetch document keywords
        $docKeywordsData = get_keywords_for_document($document_id);
        if (empty($docKeywordsData) || empty($docKeywordsData['data']['keywords'])) {
            write_debug_log("No keywords found for document ID: {$document_id}", 'info');
            return false;
        }
        $docKeywords = $docKeywordsData['data']['keywords'];

        // Fetch control IDs
        $stmt = $db->prepare("SELECT id FROM framework_controls");
        $stmt->execute();
        $controlIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Cache control keywords and TF-IDF vectors in memory
        $validControlIds = [];
        $controlKeywordsCollection = [];
        $controlVectors = [];
        $documentFrequency = [];
        $numDocuments = 0;

        foreach ($controlIds as $control_id) {
            $controlKeywordsData = get_keywords_for_control($control_id);
            if (empty($controlKeywordsData) || empty($controlKeywordsData['data']['keywords'])) continue;

            $controlKeywords = $controlKeywordsData['data']['keywords'];
            $controlKeywordsCollection[$control_id] = $controlKeywords;
            $validControlIds[] = $control_id;

            // Count document frequency
            foreach (array_keys($controlKeywords) as $term) {
                $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
            }
            $numDocuments++;
        }

        // Add document to document frequency
        foreach (array_keys($docKeywords) as $term) {
            $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
        }
        $numDocuments++;

        // Precompute TF-IDF vector for document
        $docVector = [];
        foreach ($docKeywords as $term => $tf) {
            $idf = log($numDocuments / ($documentFrequency[$term] ?? 1));
            $docVector[$term] = $tf * $idf;
        }
        unset($docKeywords); // free memory

        // Compute TF-IDF vectors for controls once
        foreach ($validControlIds as $control_id) {
            $controlVector = [];
            foreach ($controlKeywordsCollection[$control_id] as $term => $tf) {
                $idf = log($numDocuments / ($documentFrequency[$term] ?? 1));
                $controlVector[$term] = $tf * $idf;
            }
            $controlVectors[$control_id] = $controlVector;
        }
        unset($controlKeywordsCollection); // free memory

        // Compute keyword matches and final scores
        $maxKeywordMatch = 0;
        $keywordMatches = [];
        $tfIdfScores = [];

        foreach ($validControlIds as $control_id) {
            $keyword_match = 0;
            foreach ($docVector as $term => $tfidf) {
                if (isset($controlVectors[$control_id][$term])) $keyword_match++;
            }
            $keywordMatches[$control_id] = $keyword_match;
            $maxKeywordMatch = max($maxKeywordMatch, $keyword_match);

            $tfIdfScores[$control_id] = cosineSimilarity($docVector, $controlVectors[$control_id]);
        }
        unset($docVector, $controlVectors); // free memory

        // Update database with scores
        foreach ($validControlIds as $control_id) {
            $tfidf_similarity = $tfIdfScores[$control_id];
            $keyword_match = $keywordMatches[$control_id];
            $normalized_keyword_score = $maxKeywordMatch > 0 ? $keyword_match / $maxKeywordMatch : 0;
            $final_score = ($tfidf_similarity + $normalized_keyword_score) / 2;

            $stmt = $db->prepare("
                INSERT INTO document_control_mappings
                    (document_id, control_id, score, tfidf_similarity, keyword_match)
                VALUES
                    (:document_id, :control_id, :score, :tfidf_similarity, :keyword_match)
                ON DUPLICATE KEY UPDATE
                    score = :score, tfidf_similarity = :tfidf_similarity, 
                    keyword_match = :keyword_match, timestamp = NOW()
            ");

            $stmt->execute([
                ':document_id' => $document_id,
                ':control_id' => $control_id,
                ':score' => $final_score,
                ':tfidf_similarity' => $tfidf_similarity,
                ':keyword_match' => $keyword_match
            ]);
        }

        // Fetch updated mappings
        $stmt = $db->prepare("SELECT * FROM document_control_mappings WHERE document_id = :document_id ORDER BY score DESC");
        $stmt->execute([':document_id' => $document_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        write_debug_log("Finished TF-IDF processing for document {$document_id}, " . count($results) . " mappings stored.", 'info');
        return $results;

    } catch (Exception $e) {
        write_debug_log("Error in get_document_to_control_mappings: " . $e->getMessage(), 'error');
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
        write_debug_log("Control ID $control_id not found. Exiting.", 'warning');
        return false;
    }
    // If the control exists
    else
    {
        write_debug_log("Starting get_control_to_document_mappings for Control ID: " . $control_id, 'info');

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
                            write_debug_log("Document ID $document_id has no keywords. Skipping.", 'debug');
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

                        write_debug_log("Scoring Document ID $document_id: TF-IDF Similarity = $tfidf_similarity, Keyword Match = $keyword_match, Final Score = $final_score", 'debug');
                    }

                    // Reload updated mappings
                    $stmt = $db->prepare("SELECT * FROM `document_control_mappings` WHERE `control_id` = :control_id ORDER BY `score` DESC;");
                    $stmt->bindParam(':control_id', $control_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                write_debug_log("Cached mappings found for Control ID $control_id. Returning cached results.", 'debug');
            }

            db_close($db);
            write_debug_log("Finished processing for Control ID $control_id. Returning " . count($results) . " mappings.", 'info');
            return $results;
        } catch (Exception $e) {
            write_debug_log("Error in get_document_to_control_mappings: " . $e->getMessage(), 'error');
            return false;
        } finally {
            db_close($db);
        }
    }
}

/**
 * FUNCTION: UPDATE DOCUMENT KEYWORDS
 * Update the keywords and keyword count for a document in the database
 */
function update_document_keywords($document_id, array $keywords) {
    $document = get_document_by_id($document_id);
    if (empty($document)) return false;

    $unique_name = $document['unique_name'];
    $db = db_open();
    try {
        $keyword_json = json_encode($keywords);
        $keyword_count = array_sum($keywords);

        $stmt = $db->prepare("UPDATE compliance_files SET keywords = :keywords, keyword_count = :keyword_count WHERE BINARY unique_name = :unique_name");
        $stmt->bindParam(":keywords", $keyword_json, PDO::PARAM_STR);
        $stmt->bindParam(":keyword_count", $keyword_count, PDO::PARAM_INT);
        $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
        $stmt->execute();

        return true;
    } catch (Exception $e) {
        write_debug_log("Error in update_document_keywords: " . $e->getMessage(), 'error');
        return false;
    } finally {
        db_close($db);
    }
}

/***************************************
 * FUNCTION: GET KEYWORDS FOR DOCUMENT *
 ***************************************/
function get_keywords_for_document($document_id, $refresh = false)
{
    // Allow this to run as long as necessary
    ini_set('max_execution_time', 0);

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
        $stmt = $db->prepare("SELECT `content`, `name`, `type`, `keywords`, `keyword_count` FROM compliance_files WHERE BINARY unique_name=:unique_name");
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
                write_debug_log("Analyzing the contents of Document ID: " . $document_id, 'debug');

                // Get the file content
                $content = $file['content'];
                $mimeType = $file['type'];
                $fileName = $file['name'];

                // Use WordHandler to convert content to text
                $document_text = DocumentTextExtractor::extractText($content, $mimeType, $fileName);

                // Get the significant terms for the document
                write_debug_log("Calculating significant terms from the document.  This may take a while.", 'debug');
                $keywords = extractSignificantTerms($document_text);
                write_debug_log("Significant Terms: " . json_encode($keywords), 'debug');

                // Get the keyword matches for the document
                $keyword_occurrences = countKeywordOccurrencesPerKeyword($document_text, $keywords);
                $keyword_occurrences_json = json_encode($keyword_occurrences);
                write_debug_log("Keyword matches for Document ID {$document_id}: " . $keyword_occurrences_json, 'debug');

                // Get the keyword count for the document
                $keyword_count = array_sum($keyword_occurrences);
                write_debug_log("Keyword count for Document ID {$document_id}: " . $keyword_count, 'debug');

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
            } catch (UnsupportedDocumentException | DocumentTooLargeException $e) {
                // Mark only THIS file (by its unique_name) as having a processing
                // error — not the whole ref_id bundle, which would wrongly flag
                // supported sibling files sharing the ref_id.
                write_debug_log("Skipping file {$unique_name} of document {$document_id}: " . $e->getMessage(), "warning");

                $stmt = $db->prepare("UPDATE compliance_files SET keyword_processing_error = 1 WHERE BINARY unique_name = :unique_name");
                $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                $stmt->execute();

                $result = [
                    'status_code' => 415,
                    'status_message' => 'Unsupported or oversized document type.',
                    'data' => []
                ];
            } catch (Exception $e)
            {
                write_debug_log("Error in get_keywords_for_document: " . $e->getMessage(), 'error');

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
                write_debug_log("Analyzing the contents of Control ID: " . $control_id, 'debug');

                // Get the control text and calculate the control term frequency
                $control_text = $control['short_name'] . ': ' . $control['description'];
                write_debug_log("Calculating significant terms from the control.  This may take a while.", 'debug');
                $keywords = extractSignificantTerms($control_text);
                write_debug_log("Significant Terms: " . json_encode($keywords), 'debug');

                // Get the keyword matches for the control
                $keyword_occurrences = countKeywordOccurrencesPerKeyword($control_text, $keywords);
                $keyword_occurrences_json = json_encode($keyword_occurrences);
                write_debug_log("Keyword matches for Control ID {$control_id}: " . $keyword_occurrences_json, 'debug');

                // Get the keyword count for the control
                $keyword_count = array_sum($keyword_occurrences);
                write_debug_log("Keyword count for Control ID {$control_id}: " . $keyword_count, 'debug');

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
                write_debug_log("Error in get_keywords_for_control: " . $e->getMessage(), 'error');

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
            write_debug_log("Determined that the file is a text file.", 'debug');

            // Just use the text
            $document_text = file_get_contents($temp_file);
        }
        // If it is not a text file
        else
        {
            // Try to process the file as a Word document
            try
            {
                write_debug_log("Attempting to process as a Word document...", 'debug');

                // Read the Word document
                $phpWord = PhpOffice\PhpWord\IOFactory::load($temp_file, 'Word2007');

                // Extract the text from the Word document
                // @phan-suppress-next-line PhanUndeclaredFunction -- extract_text_content() is a runtime-loaded helper for PHPWord text extraction
                $document_text = extract_text_content($phpWord);
            } catch (\Exception $e)
            {
                write_debug_log("Error: " . $e->getMessage(), 'error');

                // If the file is not a Word document, try to process it as a PDF
                try
                {
                    write_debug_log("Attempting to process as PDF...", 'debug');

                    // Read the PDF document
                    $pdf = new \Smalot\PdfParser\Parser();
                    $pdfDocument = $pdf->parseFile($temp_file);

                    // Extract the text from the PDF document
                    $document_text = $pdfDocument->getText();
                } catch (\Exception $e)
                {
                    // If the file is not a PDF document, set the document text to null
                    $document_text = null;
                    write_debug_log("Error: " . $e->getMessage(), 'error');
                    write_debug_log("Unable to process the file.  Leaving the text as null.", 'warning');
                }
            }
        }

        // Delete the temporary file
        unlink($temp_file);
    } catch (Exception $e)
    {
        write_debug_log("Error in get_text_from_document_content: " . $e->getMessage(), 'error');

        // Set the document text to null
        $document_text = null;
    }

    write_debug_log("Extracted text: " . strlen((string)$document_text) . " bytes.", 'debug');

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
    $where = call_extra_function(
        'team_separation_extra',
        __DIR__ . '/../extras/separation/index.php',
        'get_user_teams_query_for_documents',
        ['t1', false],
        ' 1'
    );

    $sql = "
        SELECT t2.content AS content, t2.type, t2.name
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
            case "tfidf_match":
                $sort_query = " ORDER BY dtc.tfidf_match {$order_dir} ";
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
                case "tfidf_match":
                    $tfidf_match_val = (strtolower($val) == "yes") ? 1 : 0;
                    $where_query .= " AND `dtc`.`tfidf_match` = :{$param_name}";
                    $filter_params[$param_name] = $tfidf_match_val;
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
                    if (stripos($val, "DefiniteMatch") !== false)
                    {
                        $where_query .= " AND ((dtc.ai_run = 1 AND dtc.ai_match = 1) OR (dtc.ai_run = 0 AND dtc.score >= 0.9))";
                    }
                    else if (stripos($val, "LikelyMatch") !== false)
                    {
                        $where_query .= " AND (dtc.ai_run = 0 AND dtc.score >= 0.7 AND dtc.score < 0.9)";
                    }
                    else if (stripos($val, "PossibleMatch") !== false)
                    {
                        $where_query .= " AND (dtc.ai_run = 0 AND dtc.score >= 0.4 AND dtc.score < 0.7)";
                    }
                    else if (stripos($val, "UnlikelyMatch") !== false)
                    {
                        $where_query .= " AND (dtc.ai_run = 0 AND dtc.score >= 0.3 AND dtc.score < 0.4)";
                    }
                    else if (stripos($val, "NotAMatch") !== false)
                    {
                        $where_query .= " AND ((dtc.ai_run = 1 AND dtc.ai_match = 0) OR (dtc.ai_run = 0 AND dtc.score < 0.3))";
                    }
                    else if (stripos($val, "ReviewManually") !== false)
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
                    if (stripos($val, "AddControlToPolicy") !== false)
                    {
                        $where_query .= " AND ((dtc.ai_run = 1 AND dtc.selected = 0 AND dtc.ai_match = 1)
                           OR (dtc.ai_run = 0 AND dtc.selected = 0 AND dtc.score >= 0.9))";
                    }
                    else if (stripos($val, "ConsiderAddingControl") !== false)
                    {
                        $where_query .= " AND (dtc.ai_run = 0 AND dtc.selected = 0 AND dtc.score >= 0.3 AND dtc.score < 0.9)";
                    }
                    else if (stripos($val, "RemoveControlFromPolicy") !== false)
                    {
                        $where_query .= " AND ((dtc.ai_run = 1 AND dtc.selected = 1 AND dtc.ai_match = 0)
                           OR (dtc.ai_run = 0 AND dtc.selected = 1 AND dtc.score < 0.3))";
                    }
                    else if (stripos($val, "NoActionRequired") !== false)
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

    // Check if length is valid (not -1 for "Show All")
    // DataTables sends -1 when "Show All" is selected
    $use_limit = ($start !== false && $length !== false && $length > 0);

    // If a start and length are specified and valid
    if ($use_limit)
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

    // Only bind limit parameters if we're using them
    if ($use_limit)
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
        <div id='framework--update' class='modal fade sr-modal' tabindex='-1' aria-labelledby='framework--update-title' aria-hidden='true'>
            <div class='modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable'>
                <div class='modal-content'>
                    <form id='update-framework-form' class='' action='#' method='post' autocomplete='off'>
                        <input type='hidden' class='framework_id' name='framework_id' value=''>
                        <input type='hidden' name='update_framework' value='true'>
                        <input type='hidden' name='where' value='{$escaper->escapeHtml($where)}'>
                        <div class='modal-header'>
                            <span class='sr-modal-icon'><i class='fa fa-pen-to-square' aria-hidden='true'></i></span>
                            <h4 class='modal-title' id='framework--update-title'>{$escaper->escapeHtml($lang['FrameworkEditHeader'])}</h4>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
                            <div class='alert alert-danger d-none sr-modal-inline-error' role='alert'></div>
    ";
                            // Only the Define Control Frameworks page's edit path
                            // can persist the SoA fields OR the Active/Inactive
                            // status — see display_add_framework(). The Initiate
                            // Audits page's copy of this modal posts to the legacy
                            // POST /governance/update_framework, whose handler reads
                            // neither, so both are withheld there rather than shown
                            // as controls that quietly do nothing.
                            // 'update_' namespaces every field id this modal
                            // renders — display_add_framework() emits the SAME
                            // markup into the Add modal on the same page, so
                            // without a per-modal prefix each id would appear
                            // twice and every <label for> here would activate
                            // the Add modal's field. See display_add_framework().
                            display_add_framework($where === "governance", $where === "governance", false, 'update_');
    echo "
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-dark' data-bs-dismiss='modal' aria-label='Close'>{$escaper->escapeHtml($lang['Cancel'])}</button>
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
        <div id='control--update' class='modal fade sr-modal' tabindex='-1' aria-labelledby='control--update-title' aria-hidden='true'>
            <div class='modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered'>
                <div class='modal-content'>
                    <form class='' id='update-control-form' method='post' autocomplete='off'>
                        <input type='hidden' class='control_id' name='control_id' value=''>
                        <input type='hidden' name='update_control' value='true'>
                        <input type='hidden' name='where' value='{$escaper->escapeHtml($where)}'>
                        <div class='modal-header'>
                            <span class='sr-modal-icon'><i class='fa fa-pen-to-square' aria-hidden='true'></i></span>
                            <h4 class='modal-title' id='control--update-title'>{$escaper->escapeHtml($lang['ControlEditHeader'])}</h4>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
                            <div class='alert alert-danger d-none sr-modal-inline-error' role='alert'></div>
    ";
                            // 'update_' for the same reason the framework modal
                            // above passes it — see display_add_control().
                            display_add_control('update_');
    echo "
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-dark' data-bs-dismiss='modal' aria-label='Close'>{$escaper->escapeHtml($lang['Cancel'])}</button>
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
                        // id: false -- this is a ROW TEMPLATE, cloned once per
                        // mapping row, so any id it carried would be duplicated
                        // the moment a second row existed (and `[]` in an id is
                        // unusable as a `#` selector regardless). Rows are
                        // addressed by name within their own table.
                        create_dropdown("frameworks", NULL,"map_framework_id[]", true, false, false, "required title='{$escaper->escapeHtml($lang['Framework'])}'", id: false);
    echo "
                    </td>
                    <td>
                        <input type='text' name='reference_name[]' value='' class='form-control' maxlength='100' required title='{$escaper->escapeHtml($lang["Control"])}'>
                    </td>
                    <td>
                        <input type='text' name='reference_subject[]' value='' class='form-control' maxlength='1000' title='{$escaper->escapeHtml($lang["ReferenceSubjectHint"])}' placeholder='{$escaper->escapeHtml($lang["ReferenceSubject"])}'>
                    </td>
                    <td>
                        <textarea rows='3' cols='50' name='reference_text[]' class='form-control' title='{$escaper->escapeHtml($lang["ReferenceText"])}'></textarea>
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
                        // id: false -- row template, same reasoning as
                        // display_add_mapping_row()'s framework <select>.
                        create_dropdown("control_maturity", "", "asset_maturity[]", true, false, false, "required title='{$escaper->escapeHtml($lang['CurrentMaturity'])}'", id: false);
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

/*************************************************
 * FUNCTION: GET CONTROL CURRENT MATURITY COUNTS *
 *************************************************/
function get_control_current_maturity_counts($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }

    // Open the database connection
    $db = db_open();

    $join_clause = '';
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $join_clause = "
        INNER JOIN framework_control_mappings fcm ON fcm.control_id = fc.id
        INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1";
        $fw_clause = "AND f.value IN ({$ph})";
    }

    $stmt = $db->prepare("
        SELECT
            CASE
                WHEN cm.name IS NULL OR cm.name = '' THEN 'Unassigned'
                ELSE cm.name
            END AS maturity_name,
            MIN(cm.value) AS maturity_value,
            COUNT(DISTINCT fc.id) AS control_count
        FROM framework_controls fc
        LEFT JOIN control_maturity cm ON fc.control_maturity = cm.value
        {$join_clause}
        WHERE fc.deleted = 0 {$fw_clause}
        GROUP BY
            CASE
                WHEN cm.name IS NULL OR cm.name = '' THEN 'Unassigned'
                ELSE cm.name
            END
        ORDER BY cm.value ASC
    ");
    if (!empty($framework_ids)) { $stmt->execute(array_values($framework_ids)); } else { $stmt->execute(); }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $results;
}

/****************************************************************
 * FUNCTION: GET FRAMEWORK CONTROLS MATURITY STACKED CHART DATA *
 ****************************************************************/
function get_framework_controls_maturity_stacked_chart_data($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) {
        return [
            'labels' => [],
            'maturity_order' => [],
            'counts_by_maturity' => [],
        ];
    }

    $db = db_open();

    $stmt_maturities = $db->prepare("
        SELECT
            CASE
                WHEN cm.name IS NULL OR cm.name = '' THEN 'Unassigned'
                ELSE cm.name
            END AS maturity_name,
            MIN(IF(cm.value IS NULL, -1, cm.value)) AS sort_key
        FROM framework_controls fc
        LEFT JOIN control_maturity cm ON fc.control_maturity = cm.value
        WHERE fc.deleted = 0
        GROUP BY
            CASE
                WHEN cm.name IS NULL OR cm.name = '' THEN 'Unassigned'
                ELSE cm.name
            END
        ORDER BY sort_key ASC
    ");
    $stmt_maturities->execute();
    $maturity_order = array_column($stmt_maturities->fetchAll(PDO::FETCH_ASSOC), 'maturity_name');

    $fw_clause = '';
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$ph})";
    }

    $stmt = $db->prepare("
        SELECT
            f.value AS framework_id,
            f.name AS framework_name,
            CASE
                WHEN cm.name IS NULL OR cm.name = '' THEN 'Unassigned'
                ELSE cm.name
            END AS maturity_name,
            COUNT(DISTINCT fc.id) AS control_count
        FROM frameworks f
        INNER JOIN framework_control_mappings fcm ON f.value = fcm.framework
        INNER JOIN framework_controls fc ON fcm.control_id = fc.id AND fc.deleted = 0
        LEFT JOIN control_maturity cm ON fc.control_maturity = cm.value
        WHERE f.status = 1 {$fw_clause}
        GROUP BY
            f.value,
            f.name,
            CASE
                WHEN cm.name IS NULL OR cm.name = '' THEN 'Unassigned'
                ELSE cm.name
            END
        ORDER BY f.name ASC
    ");
    if (!empty($framework_ids)) { $stmt->execute(array_values($framework_ids)); } else { $stmt->execute(); }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    $framework_id_order = [];
    $labels = [];
    foreach ($rows as $row) {
        $fid = (int)$row['framework_id'];
        if (!in_array($fid, $framework_id_order, true)) {
            $framework_id_order[] = $fid;
            $labels[] = try_decrypt($row['framework_name']);
        }
    }

    $matrix = [];
    foreach ($framework_id_order as $fid) {
        $matrix[$fid] = array_fill_keys($maturity_order, 0);
    }
    foreach ($rows as $row) {
        $fid = (int)$row['framework_id'];
        if (isset($matrix[$fid])) {
            $matrix[$fid][$row['maturity_name']] = (int)$row['control_count'];
        }
    }

    $counts_by_maturity = [];
    foreach ($maturity_order as $m) {
        $counts_by_maturity[$m] = [];
        foreach ($framework_id_order as $fid) {
            $counts_by_maturity[$m][] = $matrix[$fid][$m] ?? 0;
        }
    }

    return [
        'labels' => $labels,
        'maturity_order' => $maturity_order,
        'counts_by_maturity' => $counts_by_maturity,
    ];
}

/****************************************************************
 * FUNCTION: GET GOVERNANCE CONTROL STATUS TOTALS                *
 * Distinct-control counts by control_status across the scoped   *
 * ACTIVE frameworks: passing (1) / failing (0) / not_tested (2).*
 * passing+failing+not_tested equals get_governance_total_controls() *
 * for the same scope. $framework_ids: null=all, []=none, [id]=that one. *
 ****************************************************************/
/**
 * GOVERNANCE DASHBOARD ONLY. The `f.status = 1` below is deliberate here and
 * load-bearing: every caller reaches this through
 * governance_dashboard_framework_filter() (includes/reporting.php:12432,
 * api/v2/includes/api.php:695), and a governance dashboard that summarised
 * RETIRED frameworks would be its own bug report.
 *
 * It is the wrong scope for anything that sits above the Define Control
 * Frameworks controls table, because that table scopes with
 * control_framework_scope_sql(), which never looks at framework status --
 * see get_control_scope_totals() below, which is what that page uses.
 *
 * @param array|null $framework_ids
 * @return array
 */
function get_governance_control_status_totals($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) {
        return ['passing' => 0, 'failing' => 0, 'not_tested' => 0];
    }
    $db = db_open();
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$ph})";
    }
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN fc.control_status = 1 THEN fc.id END) AS passing,
            COUNT(DISTINCT CASE WHEN fc.control_status = 0 THEN fc.id END) AS failing,
            COUNT(DISTINCT CASE WHEN fc.control_status IN (0, 1) THEN NULL ELSE fc.id END) AS not_tested
        FROM frameworks f
        INNER JOIN framework_control_mappings fcm ON f.value = fcm.framework
        INNER JOIN framework_controls fc ON fcm.control_id = fc.id AND fc.deleted = 0
        WHERE f.status = 1 {$fw_clause}
    ");
    if (!empty($framework_ids)) { $stmt->execute(array_values($framework_ids)); } else { $stmt->execute(); }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);
    return [
        'passing' => (int)($row['passing'] ?? 0),
        'failing' => (int)($row['failing'] ?? 0),
        'not_tested' => (int)($row['not_tested'] ?? 0),
    ];
}

/****************************************************************
 * FUNCTION: GET GOVERNANCE TOTAL CONTROLS                       *
 * Distinct control count across scoped active frameworks.       *
 ****************************************************************/
/**
 * GOVERNANCE DASHBOARD ONLY -- same reasoning as
 * get_governance_control_status_totals() above. Its one caller
 * (api/v2/includes/api.php:659) passes
 * governance_dashboard_framework_filter(). Use get_control_scope_totals()
 * for anything scoped to the Define Control Frameworks rail.
 *
 * @param array|null $framework_ids
 * @return int
 */
function get_governance_total_controls($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) { return 0; }
    $db = db_open();
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$ph})";
    }
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT fc.id)
        FROM frameworks f
        INNER JOIN framework_control_mappings fcm ON f.value = fcm.framework
        INNER JOIN framework_controls fc ON fcm.control_id = fc.id AND fc.deleted = 0
        WHERE f.status = 1 {$fw_clause}
    ");
    if (!empty($framework_ids)) { $stmt->execute(array_values($framework_ids)); } else { $stmt->execute(); }
    $count = (int)$stmt->fetchColumn();
    db_close($db);
    return $count;
}

/****************************************************************
 * FUNCTION: GET CONTROL SCOPE TOTALS                            *
 * Total / passing / failing / not_tested control counts for the *
 * Define Control Frameworks rail's current selection. Feeds the *
 * insights band's Controls / Pass / Fail / Not Tested tiles.    *
 * $framework_ids: null = the rail's "All frameworks", [] = none,*
 * [id] = that one framework.                                     *
 ****************************************************************/
/**
 * ONE aggregate, read four times -- not four counts that have to be kept in
 * step. All four numbers are projections of get_control_status_counts(), which
 * is the SAME aggregate the controls table's Status facet reads its pass /
 * fail / not_tested chips from, scoped by control_framework_scope_sql() like
 * every other count on that page.
 *
 * They did not agree before. The band used to read
 * get_governance_control_status_totals() / get_governance_total_controls(),
 * which add `WHERE f.status = 1` on top of the framework filter -- active
 * frameworks only -- and reach controls through an INNER JOIN on
 * framework_control_mappings. The table beneath the tiles does neither. Two
 * whole populations were therefore on screen in the table and missing from the
 * tiles: controls under an INACTIVE framework (zero at any scope, including
 * when that framework WAS the rail selection) and controls mapped to no
 * framework at all. Measured on the dev instance: the Controls tile read 1536
 * against 1547 rows in the table underneath it. Same defect Task 37 fixed for
 * the Below target tile, on four more tiles; Josh's decision is the same one --
 * the tiles scope to the rail selection, because the rail is what the user
 * just clicked and a tile that quietly re-scopes underneath it is a tile that
 * lies. See ControlScopeTotalsAgreementTest for the invariant.
 *
 * Those two dashboard functions are NOT changed and NOT called from here: all
 * three of their remaining callers go through
 * governance_dashboard_framework_filter(), where active-frameworks-only is the
 * intended semantics. This is an additional aggregate for a different surface,
 * not a redefinition of theirs.
 *
 * `total` is array_sum() of the three status buckets rather than its own
 * COUNT. control_status is NOT NULL DEFAULT 2 (migrate_control_status_not_null(),
 * includes/upgrade.php) and control_status_token_map()'s three values partition
 * every control in scope, so the sum IS the in-scope control count -- and
 * deriving it means the Controls tile cannot drift from the three tiles beside
 * it, which is exactly the class of bug being fixed. ControlScopeTotalsAgreementTest
 * pins the sum against the table's own row count, so a status value outside the
 * map would fail a test rather than silently shrink the tile.
 *
 * The empty-array guard stays here rather than being left to
 * control_framework_scope_sql(): that helper treats a falsy scope as "no
 * restriction", so an [] passed through would silently widen to ALL controls
 * instead of the "no frameworks selected" the sibling scoped functions mean.
 *
 * @param array|null $framework_ids
 * @return array ['total' => int, 'passing' => int, 'failing' => int, 'not_tested' => int]
 */
function get_control_scope_totals($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) {
        return ['total' => 0, 'passing' => 0, 'failing' => 0, 'not_tested' => 0];
    }

    $counts = get_control_status_counts($framework_ids === null ? "" : $framework_ids);

    return [
        'total'      => (int)array_sum($counts),
        'passing'    => (int)($counts['pass'] ?? 0),
        'failing'    => (int)($counts['fail'] ?? 0),
        'not_tested' => (int)($counts['not_tested'] ?? 0),
    ];
}

/****************************************************************
 * FUNCTION: GET FRAMEWORK CONTROL COUNTS                         *
 * Distinct non-deleted-control counts per framework, keyed by     *
 * framework id -- feeds the Define Control Frameworks rail's      *
 * per-row badge (Task 22). ONE query for every requested framework *
 * rather than N+1: the rail renders tens of frameworks, not one    *
 * query per row. Unlike get_governance_total_controls() above,     *
 * this is NOT scoped to active (f.status = 1) frameworks -- the    *
 * rail's own status filter (Active/Inactive/All) already decides    *
 * which frameworks are in the list this is called with, so a count *
 * scoped to active-only would silently zero out every Inactive      *
 * framework's own row regardless of what's actually mapped to it.   *
 *                                                                    *
 * A control mapped to a framework more than once (framework_control_ *
 * mappings has no uniqueness constraint on (framework, control_id)   *
 * today) counts once, via COUNT(DISTINCT ...) -- and a control       *
 * mapped to two different frameworks counts once in EACH framework's *
 * total, not shared between them (verified by                        *
 * GovernanceFrameworkControlCountsTest).                              *
 *                                                                     *
 * $framework_ids: framework ids to scope to. Empty returns [] --      *
 * nothing to count, and no reason to run a query with an empty        *
 * IN (...).                                                            *
 ****************************************************************/
function get_framework_control_counts(array $framework_ids): array {
    if (empty($framework_ids)) {
        return [];
    }
    $db = db_open();
    $ph = implode(',', array_fill(0, count($framework_ids), '?'));
    $stmt = $db->prepare("
        SELECT fcm.framework AS framework_id, COUNT(DISTINCT fcm.control_id) AS control_count
        FROM framework_control_mappings fcm
        INNER JOIN framework_controls fc ON fcm.control_id = fc.id AND fc.deleted = 0
        WHERE fcm.framework IN ({$ph})
        GROUP BY fcm.framework
    ");
    $stmt->execute(array_values($framework_ids));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $counts = [];
    foreach ($rows as $row) {
        $counts[(int)$row['framework_id']] = (int)$row['control_count'];
    }
    return $counts;
}

/****************************************************************
 * FUNCTION: GET GOVERNANCE PASSING PERCENT                      *
 * Passing controls as a whole-number percent of total controls  *
 * in scope.                                                      *
 ****************************************************************/
function get_governance_passing_percent($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) { return 0; }
    $totals = get_governance_control_status_totals($framework_ids);
    $total = get_governance_total_controls($framework_ids);
    if ($total <= 0) { return 0; }
    // floor, not round: 99.8% (e.g. 1466/1469) must read as 99%, not be rounded
    // up to a misleading 100% while failing/not-tested controls still exist. Only
    // a genuine all-passing set reaches 100%.
    return (int) floor($totals['passing'] / $total * 100);
}

/****************************************************************
 * FUNCTION: COUNT CONTROLS BELOW TARGET                          *
 * Distinct control count where the current maturity is below the *
 * desired maturity, scoped to the rail's selection. Feeds the    *
 * Define Control Frameworks insights band's "Below target" tile. *
 * $framework_ids: null = the rail's "All frameworks", [] = none, *
 * [id] = that one framework.                                      *
 ****************************************************************/
/**
 * Delegates to get_control_maturity_counts(), which is the SAME aggregate the
 * controls table's Maturity facet reads its "below" chip from -- so the tile
 * and the chip are one number computed once, not two numbers that happen to
 * agree.
 *
 * They did not agree. This used to run its own SQL with `WHERE f.status = 1`
 * on top of the framework filter, i.e. active frameworks only, while the facet
 * scopes with control_framework_scope_sql() like every other count on the page
 * -- which does not look at framework status at all. On "All frameworks" the
 * same control population therefore reported two different numbers on one
 * screen: the tile counted only controls mapped to an ACTIVE framework, the
 * chip counted every control including those under an inactive framework and
 * those mapped to no framework at all. Josh's decision is that the tile scopes
 * to the rail selection, not to active -- the rail is what the user just
 * clicked, and a tile that quietly re-scopes underneath it is a tile that
 * lies. See MaturityTileFacetAgreementTest for the invariant.
 *
 * The below/at/above rule itself is unchanged -- it was already
 * control_maturity_bucket()'s (current < desired, and no bucket at all without
 * a desired target), which is exactly what the old `fc.control_maturity <
 * fc.desired_maturity` expressed. Only the framework scope moved.
 *
 * The empty-array guard stays here rather than being left to
 * control_framework_scope_sql(): that helper treats a falsy scope as "no
 * restriction", so an [] passed through would silently widen to ALL controls
 * instead of the "no frameworks selected" the sibling scoped functions mean.
 *
 * @param array|null $framework_ids
 * @return int
 */
function count_controls_below_target($framework_ids = null) {
    if ($framework_ids !== null && empty($framework_ids)) { return 0; }

    $counts = get_control_maturity_counts($framework_ids === null ? "" : $framework_ids);

    return (int)($counts['below'] ?? 0);
}

/****************************************************************
 * FUNCTION: COUNT EXCLUDED CONTROLS                               *
 * Distinct count of controls marked not-applicable/inherited      *
 * within the scoped framework(s). Applicability is inherently     *
 * per-framework (a control can be excluded from one framework's   *
 * scope and still be in-scope for another), so this has no honest *
 * meaning across "All frameworks" -- callers must only invoke it  *
 * with a real framework scope (see the insights band resolver's   *
 * own All-frameworks carve-out, which renders an em dash there    *
 * instead of calling this at all).                                *
 *                                                                  *
 * THE COUNT AND THE FILTERED TABLE MUST BE ONE NUMBER. The tile     *
 * deep-links to ?applicability=not_applicable,inherited, whose rows *
 * come from get_framework_controls_by_filter() -- controls MAPPED   *
 * into the framework and not soft-deleted. So this counts decisions *
 * intersected with that same set, via the two INNER JOINs below:    *
 *                                                                    *
 *   - framework_control_mappings on (framework, control_id): a       *
 *     DORMANT decision, whose control is no longer mapped into the   *
 *     framework, is deliberately kept readable by the domain layer   *
 *     (get_framework_applicability_map()) because an auditor may     *
 *     still ask about last year's exclusion -- but it is not a row   *
 *     the table can show, so counting it here would make the tile    *
 *     link to a table that contradicts it. That is the exact         *
 *     chip-equals-filter defect Tasks 29/37/40 fixed on this page.   *
 *   - framework_controls on deleted = 0, for the same reason.        *
 *                                                                    *
 * COUNT(DISTINCT a.framework, a.control_id) rather than COUNT(*):     *
 * framework_control_mappings' UNIQUE key is (control_id, framework,   *
 * reference_name), so one control legitimately holds several mapping  *
 * rows in the same framework under different references -- and the    *
 * join would then count it once per row (the same reason              *
 * get_framework_control_counts() above counts DISTINCT).              *
 *                                                                     *
 * `framework_control_applicability` is a CORE table, created           *
 * unconditionally by migrate_control_applicability_schema()            *
 * (includes/upgrade.php), so it needs no table_exists() guard -- the    *
 * same position the domain layer takes (includes/applicability.php).    *
 *                                                                       *
 * $framework_ids: null or [] both return 0 -- null because there is no   *
 * honest all-frameworks answer, [] because no framework is selected.     *
 ****************************************************************/
function count_excluded_controls($framework_ids = null) {
    if ($framework_ids === null || empty($framework_ids)) { return 0; }

    // The -1 "Unassigned" sentinel the other facets accept is not a framework,
    // and a control belonging to no framework has no per-framework
    // applicability to count.
    $ids = array_values(array_unique(array_filter(array_map('intval', $framework_ids), static fn($v) => $v > 0)));
    if (empty($ids)) { return 0; }

    $db = db_open();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // COUNTING ROWS IS NOT COUNTING EXCLUSIONS ANY MORE. Every row in this table
    // was a deviation until Task 4 gave an APPLICABLE control somewhere to record
    // its own justification; an unfiltered count would report those controls as
    // excluded, and the tile would then disagree with the filter it links to —
    // the exact chip-equals-filter defect this function was written to close.
    // The vocabulary comes from the domain layer's own constant, so the tile
    // cannot drift from resolve_applicability()'s idea of a deviation.
    $states = implode(',', array_fill(0, count(APPLICABILITY_DEVIATION_STATES), '?'));
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT a.framework, a.control_id)
        FROM `framework_control_applicability` a
            INNER JOIN `framework_control_mappings` m ON m.framework = a.framework AND m.control_id = a.control_id
            INNER JOIN `framework_controls` fc ON fc.id = a.control_id AND fc.deleted = 0
        WHERE a.framework IN ({$placeholders}) AND a.state IN ({$states})
    ");
    $stmt->execute(array_merge($ids, APPLICABILITY_DEVIATION_STATES));
    $count = (int)$stmt->fetchColumn();
    db_close($db);

    return $count;
}

/****************************************************************
 * FUNCTION: GET CONTROL STATUS COUNTS BY ATTRIBUTE              *
 * Lookup-driven (LEFT JOIN from the lookup table) passing/      *
 * failing/not_tested control_status counts per level of a       *
 * whitelisted control attribute — every defined lookup level     *
 * appears as a row, even with zero controls at that level.       *
 * Mirrors get_control_pass_fail_counts_by_attribute() in         *
 * compliance.php but counts framework_controls.control_status    *
 * (1=Pass, 0=Fail, 2/NULL/other=Not Tested) instead of audit      *
 * test results. $framework_ids: null=all active, []=none.        *
 ****************************************************************/
function get_control_status_counts_by_attribute($attribute, $framework_ids = null) {
    // Whitelist attribute -> (column, lookup table). Fixed set, never user
    // input, so safe to interpolate as SQL identifiers.
    $allowed = ['family', 'control_class', 'control_phase', 'control_priority', 'control_maturity'];
    if (!in_array($attribute, $allowed, true)) {
        return [];
    }
    if ($framework_ids !== null && empty($framework_ids)) {
        return [];
    }
    $col = $lookup = $attribute;
    $order = ($attribute === 'family') ? 'l.name ASC' : 'l.value ASC';

    $db = db_open();
    $fw_clause = '';
    if (!empty($framework_ids)) {
        $ph = implode(',', array_fill(0, count($framework_ids), '?'));
        $fw_clause = "AND f.value IN ({$ph})";
    }
    $stmt = $db->prepare("
        SELECT l.value AS group_id, l.name AS group_name,
            COUNT(DISTINCT CASE WHEN fc.control_status = 1 THEN fc.id END) AS passing,
            COUNT(DISTINCT CASE WHEN fc.control_status = 0 THEN fc.id END) AS failing,
            COUNT(DISTINCT CASE WHEN fc.id IS NULL THEN NULL WHEN fc.control_status IN (0, 1) THEN NULL ELSE fc.id END) AS not_tested
        FROM `{$lookup}` l
        LEFT JOIN framework_controls fc
            ON fc.`{$col}` = l.value AND fc.deleted = 0
            AND fc.id IN (
                SELECT fcm.control_id FROM framework_control_mappings fcm
                INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1 {$fw_clause}
            )
        GROUP BY l.value, l.name
        ORDER BY {$order}
    ");
    if (!empty($framework_ids)) { $stmt->execute(array_values($framework_ids)); } else { $stmt->execute(); }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);
    return $rows;
}

/* ===========================================================================
 * FRAMEWORK ACQUISITION CHOOSER (Task 26)
 * ===========================================================================
 *
 * "+ Add framework" used to mean exactly one thing: type a name into the Add
 * Framework modal. There are three ways to acquire a framework in SimpleRisk
 * and only one of them was on this page, so the other two were discoverable
 * only by already knowing they existed and where they lived (two different
 * admin pages, neither of them under Governance).
 *
 * THE GATING RULE, AND IT IS THE REVERSE OF WHAT THIS FILE USED TO SAY.
 * PLEASE READ THIS BEFORE "FIXING" IT BACK.
 *
 * The old rule was: an affordance for an Extra the customer does not have is
 * NOT RENDERED -- not greyed out, not shown-then-error, not a teaser. The
 * chooser's SHAPE varied (one, two or three routes) rather than its
 * enabled-ness, and a customer with neither Extra got a plain button rather
 * than a one-item dropdown.
 *
 * The rule is now: SHOW WHAT'S POSSIBLE, AND MARK WHAT'S OUT OF REACH BECAUSE
 * IT ISN'T LICENSED. Burying a feature cannot create a sell opportunity -- a
 * customer who never sees that SimpleRisk can load the Secure Controls
 * Framework has no reason to ask for it, and the person who just opened
 * "+ Add framework" is the single best-qualified audience for that sentence.
 * So all three routes are ALWAYS rendered for a user who may create
 * frameworks, and the ones that cannot be walked are rendered LOCKED: greyed,
 * naming what is missing, and linking to the one next step that would unlock
 * them.
 *
 * This is a GENERAL rule and not a judgement about this one surface. The same
 * decision function (resolve_extra_affordance(), includes/settings_catalog.php)
 * and the same presentation component (the .sr-locked* classes in
 * scss/modules/_locked-affordance.scss) drive the Statement of Applicability's
 * Import-Export-backed PDF/XLSX exports. Two different-looking "you do not have
 * this Extra" treatments inside one feature area is the divergence both of
 * those pieces exist to prevent, so a change to one belongs in both.
 *
 * WHAT THE REVERSAL DOES NOT TOUCH: `add_new_frameworks`. A locked row is an
 * invitation to unlock something, and a permission the user cannot grant
 * themselves is not an invitation -- it is a wall. Without that permission the
 * chooser still renders NOTHING: no trigger, no menu, no greyed rows. Turning
 * a permission gate into a disabled row would say "this action exists here and
 * is merely unavailable", which is the wrong sentence for a permission.
 *
 * The old degenerate case -- exactly one route, so no menu at all, just the
 * plain "+ Add framework" button -- is GONE with the rule that produced it.
 * A permitted user now always gets the three-route menu, because the two rows
 * they cannot walk today are the two things they most need to know exist.
 *
 * CLONE IS NOT A FOURTH ROUTE, deliberately. Task 64's "clone this framework"
 * also produces a framework, so it looks like a candidate -- but it is a
 * PER-FRAMEWORK operation and this chooser is page-level. Offering it here
 * would mean asking "which one?" second, i.e. building a framework picker
 * inside a menu the rail is already sitting next to, and it would put the same
 * action in two places with different affordances. It stays where it is: a row
 * action on the framework you are pointing at.
 *
 * ADMIN, TOO, and this was a finding rather than a design choice. Both routes
 * land on pages that call render_header_and_sidebar(..., ['check_admin' =>
 * true]) -- admin/securecontrolsframework.php and admin/importexport.php --
 * so a non-admin who took either one would be bounced by enforce_permission()
 * with no way back. (getting_started_catalog() in reporting.php gates its own
 * CTAs to these two pages the same way, with 'gate' => 'admin'.)
 *
 * Under the new rule that no longer means HIDING those rows from a non-admin --
 * it means not handing them a LINK. A non-admin sees both Extra rows, greyed,
 * with one sentence saying an administrator can set it up, and no anchor at
 * all: state 'admin_required'. Telling a user to go download an Extra they have
 * no rights to download would be worse than the old silence, and an unlock link
 * that lands on enforce_permission() is exactly the "route that cannot be
 * walked" this paragraph was written about. What they get instead is the half
 * that is useful to them -- that the capability exists and who to ask.
 */

/**
 * The locked copy for each state a route can be in: the sentence that explains
 * WHY the row is greyed, and the label of the link that would fix it.
 *
 * Kept as lang KEYS rather than resolved strings so the decision function stays
 * pure and testable without the language file loaded, and so a reviewer can see
 * at a glance that no English literal reaches the renderer.
 *
 * 'admin_required' carries a note and NO link deliberately -- see the ADMIN
 * paragraph in the block comment above.
 *
 * TWO LEVELS, NOT ONE: the outer key is the acquisition path ('scf' / 'import')
 * and the inner key is that path's state ('registration_required', 'deactivated',
 * ...), so a caller reads it as `$copy[$path][$state]['note']`. The declared type
 * used to describe the inner map only, which made Phan resolve `$copy[$key][$state]`
 * to the `?string` of a note/link and report the two reads below as invalid
 * dim-fetches on a string.
 *
 * @return array<string, array<string, array{note: string, link: ?string}>>
 */
function framework_acquisition_locked_copy()
{
    return [
        'scf' => [
            // Nothing to register once you are registered, so these two states
            // share a destination (admin/register.php is both the registration
            // form and where core_display_upgrade_extras() renders the
            // per-Extra download buttons) and differ only in what they say.
            'registration_required' => ['note' => 'UnlockRegisterInstanceNote',   'link' => 'UnlockRegisterInstanceLink'],
            'ready_to_download'     => ['note' => 'UnlockDownloadScfNote',        'link' => 'UnlockDownloadScfLink'],
            'deactivated'           => ['note' => 'UnlockActivateScfNote',        'link' => 'UnlockActivateScfLink'],
            'admin_required'        => ['note' => 'UnlockNeedsAdministrator',     'link' => null],
        ],
        'import' => [
            'purchase'              => ['note' => 'RequiresImportExportExtra',    'link' => 'UnlockImportExportLink'],
            'deactivated'           => ['note' => 'UnlockActivateImportExportNote', 'link' => 'UnlockActivateImportExportLink'],
            'admin_required'        => ['note' => 'UnlockNeedsAdministrator',     'link' => null],
        ],
    ];
}

/**
 * THE DECISION, with nothing to reach for: what state each of the three
 * acquisition routes is in, in the order they are offered in.
 *
 * Always three routes for a permitted user, never fewer -- see the block
 * comment above for why that reversed. What varies is each route's STATE, and
 * whether it is walkable or locked.
 *
 * Pure on purpose. The seven inputs are exactly the seven facts the answer
 * depends on, so every combination -- including the ones the dev and CI
 * instances (registered, with both Extras activated) structurally cannot
 * produce -- is testable without a database, a session or a browser. See
 * tests/unit/FrameworkAcquisitionPathsTest.php.
 *
 * The two Extra routes' states come from resolve_extra_affordance()
 * (includes/settings_catalog.php), which is the SAME decision the Statement of
 * Applicability's export buttons ask, layered over the Settings Hub's own
 * compute_extra_tile_state(). Nothing about Extra state is decided here: this
 * function only orders the routes, applies the two gates that are specific to
 * this page (`add_new_frameworks` and admin), and attaches the copy.
 *
 * `add_new_frameworks` gates ALL THREE, including the Extra routes: enabling an
 * SCF framework and importing a framework from a spreadsheet both CREATE
 * frameworks, so a user who may not create one has no business being sent
 * somewhere that creates several -- and, per the block comment, a permission
 * wall is not an upsell.
 *
 * @param bool $can_add           has_permission('add_new_frameworks')
 * @param bool $is_admin          is_admin() -- both Extra routes are admin pages
 * @param bool $is_registered     get_setting('registration_registered') == 1
 * @param bool $scf_installed     is_extra_installed('complianceforgescf')
 * @param bool $scf_activated     complianceforge_scf_extra()
 * @param bool $import_installed  is_extra_installed('import-export')
 * @param bool $import_activated  import_export_extra()
 *
 * @return array<string, array{state: string, locked: bool, note_key: ?string, link_key: ?string, unlock_href: ?string, external: bool}>
 *         keyed and ordered 'manual', 'scf', 'import' -- or empty, with no
 *         permission.
 */
function framework_acquisition_path_states(
    $can_add,
    $is_admin,
    $is_registered,
    $scf_installed,
    $scf_activated,
    $import_installed,
    $import_activated
) {
    // No permission, no chooser at all -- not even a locked row, and so
    // (count() === 0) no trigger either.
    if (!$can_add) {
        return [];
    }

    $copy = framework_acquisition_locked_copy();

    // The manual route needs no Extra and no admin rights. It is never locked,
    // and it is the page's live action -- the one place the chooser spends any
    // emphasis at all.
    $states = [
        'manual' => [
            'state'       => 'activated',
            'locked'      => false,
            'note_key'    => null,
            'link_key'    => null,
            'unlock_href' => null,
            'external'    => false,
        ],
    ];

    $extras = [
        'scf'    => ['complianceforgescf', (bool) $scf_activated,    (bool) $scf_installed],
        'import' => ['import-export',      (bool) $import_activated, (bool) $import_installed],
    ];

    foreach ($extras as $key => [$extra_name, $activated, $installed]) {
        $entry = settings_catalog_entry_for_extra($extra_name);

        if ($is_admin) {
            $resolved = resolve_extra_affordance($entry ?? [], $activated, $installed, (bool) $is_registered);
            $state    = $resolved['state'];
            // Catalog paths are relative to simplerisk/; this page is one level
            // down. Page-relative rather than absolute so a subpath install
            // (https://host/simplerisk/) works without a base-URL lookup.
            $href = $resolved['path'] === null || $resolved['external']
                ? $resolved['path']
                : '../' . $resolved['path'];
            $external = $resolved['external'];
        } else {
            // Both destinations are check_admin pages. The row still renders --
            // that is the "show what's possible" half -- but with no anchor.
            $state    = 'admin_required';
            $href     = null;
            $external = false;
        }

        $locked = $state !== 'activated';

        $states[$key] = [
            'state'       => $state,
            'locked'      => $locked,
            'note_key'    => $locked ? ($copy[$key][$state]['note'] ?? null) : null,
            'link_key'    => $locked ? ($copy[$key][$state]['link'] ?? null) : null,
            'unlock_href' => $locked ? $href : null,
            'external'    => $locked ? (bool) $external : false,
        ];
    }

    return $states;
}

/**
 * The same decision, resolved against this request.
 *
 * There is no is_dir() here any more, and its absence is not an oversight: the
 * "activation flag set with no files behind it" guard it provided now lives
 * inside resolve_extra_affordance(), which refuses to call an Extra activated
 * unless is_extra_installed() agrees. Every consumer of that function inherits
 * the guard rather than each remembering a directory check of its own.
 *
 * @return array<string, array{state: string, locked: bool, note_key: ?string, link_key: ?string, unlock_href: ?string, external: bool}>
 */
function framework_acquisition_paths()
{
    return framework_acquisition_path_states(
        has_permission('add_new_frameworks'),
        is_admin(),
        get_setting('registration_registered') == 1,
        is_extra_installed('complianceforgescf'),
        complianceforge_scf_extra(),
        is_extra_installed('import-export'),
        import_export_extra()
    );
}

/**
 * The per-route presentation: label, hint and where it goes.
 *
 * `href` null means "this route opens a modal on the current page" -- only the
 * manual route does, and it is the reason the chooser is a DROPDOWN and not a
 * modal of its own: design-system.md §8 says a modal never opens another
 * modal, and "pick how, then fill in the form" is exactly that stack.
 *
 * The two hrefs are relative to the page rendering them (governance/index.php),
 * which is how the empty tile's onboarding links have always addressed these
 * pages, and is what keeps a subpath install (https://host/simplerisk/) working
 * without a base-URL lookup.
 *
 * NEITHER PIECE OF THE IMPORT-EXPORT ROUTE'S URL IS DECORATION.
 *
 * That page's Import tab creates a framework as a side effect of importing
 * CONTROLS: hand import_controls_with_mapping() a spreadsheet whose framework
 * column names a framework that does not exist yet and it calls add_framework()
 * for you (extras/import-export/index.php). So there is no `frameworks` import
 * TYPE, and none is needed -- but the type selector opens on `risks`, which
 * would land a user who asked to import a framework on the risk import form.
 * `?importoption=controls` is that Extra's own preselect hook, the same one a
 * failed control import already refreshes back through, and `#import` names the
 * tab for the 'tabs:logic' asset (header.php), whose $(function(){}) reads
 * location.hash and activates the matching data-bs-target tab on load.
 *
 * This route deliberately does NOT point at the Extra's GitHub framework
 * catalogue (`#frameworks`), which is where it pointed when the chooser was
 * first built. That catalogue is a set of spreadsheets generated once and never
 * refreshed; the route a customer needs from Define Control Frameworks is
 * importing a framework OF THEIR OWN.
 *
 * @return array<string, array{href: ?string, hint: string, icon: string, label: string}>
 */
function framework_acquisition_path_catalog()
{
    global $lang;

    return [
        'manual' => [
            'href'  => null,
            'hint'  => $lang['CreateFrameworkManuallyHint'],
            'icon'  => 'fa-pen-to-square',
            'label' => $lang['CreateFrameworkManually'],
        ],
        'scf' => [
            'href'  => '../admin/securecontrolsframework.php',
            'hint'  => $lang['GetFrameworkFromScfHint'],
            'icon'  => 'fa-shield-halved',
            'label' => $lang['GetFrameworkFromScf'],
        ],
        'import' => [
            'href'  => '../admin/importexport.php?importoption=controls#import',
            'hint'  => $lang['ImportFrameworkFromSpreadsheetHint'],
            'icon'  => 'fa-file-import',
            'label' => $lang['ImportFrameworkFromSpreadsheet'],
        ],
    ];
}

/**
 * Renders one acquisition chooser: the trigger and the menu of routes hanging
 * off it.
 *
 * Called twice on Define Control Frameworks -- once for the rail's dashed
 * "+ Add framework" row and once for the "No frameworks yet" tile's button --
 * so that the two offer the SAME routes rather than drifting into two
 * different acquisition UIs. Only the trigger's chrome differs, which is the
 * part that was already different before this task.
 *
 * NOTHING inside the menu carries an `id`: the page renders this twice and a
 * test asserts the page has zero duplicate ids (Task 50). Routes are addressed
 * by [data-sr-fw-acquire=<key>], the same way the modal fields are addressed by
 * [name=...] rather than by id.
 *
 * A LOCKED ROW IS A <div>, NOT A DISABLED .dropdown-item, and that is load
 * bearing twice over. Bootstrap's keyboard handler walks
 * `.dropdown-menu .dropdown-item` and focuses each in turn, and its
 * `.dropdown-item.disabled` rule sets `pointer-events: none` -- which would
 * make the unlock link inside the row unclickable, i.e. would turn the one
 * thing the row exists to offer into decoration. Dropping both classes from
 * locked rows keeps arrow-key navigation on the routes that can actually be
 * taken and leaves the nested <a> fully interactive and tabbable.
 *
 * @param string $trigger_id     id for the trigger button (the caller owns it)
 * @param string $trigger_class  class list for the trigger button
 * @param bool   $with_add_icon  render the dashed row's leading "+" glyph
 * @param array|null $paths_override  TESTS AND PREVIEWS ONLY. Production
 *        callers omit it and the renderer asks framework_acquisition_paths()
 *        itself, which is where the `add_new_frameworks` and admin gates live.
 *        The seam exists because the LOCKED states are unreachable on any
 *        instance that has the Extras installed and activated -- which is every
 *        dev and CI instance -- and turning an Extra off to see one is
 *        destructive (disable_import_export_extra() drops that Extra's tables;
 *        deactivating the SCF Extra tears down a 1,500-control catalogue). With
 *        it, the markup for every state can be asserted from the real renderer
 *        rather than from a hand-written copy of it that could drift.
 */
function display_framework_acquisition_chooser($trigger_id, $trigger_class, $with_add_icon = false, $paths_override = null)
{
    global $escaper, $lang;

    $paths = $paths_override === null ? framework_acquisition_paths() : $paths_override;

    // Not permitted to create frameworks at all: no trigger, no menu, nothing.
    // Not a locked row either -- see the block comment above.
    if (!$paths) {
        return;
    }

    $catalog   = framework_acquisition_path_catalog();
    $id_attr   = $escaper->escapeHtmlAttr($trigger_id);
    $class_att = $escaper->escapeHtmlAttr($trigger_class);
    $icon      = $with_add_icon
        ? "<span class='sr-qaddicon'><i class='fa fa-plus' aria-hidden='true'></i></span>"
        : '';
    $label     = $escaper->escapeHtml($lang['AddFramework']);

    // Bootstrap's own .dropdown wrapper, so the menu gets
    // its positioning context from the shipped framework rather than from a
    // rule of ours. .sr-table-card is overflow-x: visible by design (see the
    // note at the top of scss/modules/_governance-frameworks.scss), so the
    // menu is not clipped by the rail card it opens inside.
    //
    // data-bs-display='static' turns Popper OFF for this menu and lets CSS
    // place it (top: 100%; left: 0 against the .dropdown wrapper). That is a
    // deliberate choice, not a default: with Popper on, the menu FLIPPED above
    // the trigger and painted over the insights band. Popper picks its
    // boundary from the nearest clipping ancestors, and this page's app shell
    // hands it a degenerate one -- #main-wrapper is overflow: hidden with a
    // zero-height client rect -- so "is there room below?" is answered against
    // a box of no height and the answer is always no. Static placement is
    // deterministic instead, and viewport containment is then a CSS problem
    // (.sr-fw-acquire-menu's max-width) rather than a heuristic's, which is
    // also what makes it assertable across the responsive sweep.
    echo "
        <div class='dropdown sr-fw-acquire'>
          <button type='button' class='{$class_att}' id='{$id_attr}' data-bs-toggle='dropdown' data-bs-display='static' aria-expanded='false'>{$icon}{$label}</button>
          <div class='dropdown-menu sr-fw-acquire-menu' aria-labelledby='{$id_attr}'>
            <span class='sr-fw-acquire-head'>" . $escaper->escapeHtml($lang['HowDoYouWantToAddAFramework']) . "</span>";

    foreach ($paths as $key => $route) {
        $path  = $catalog[$key];
        $k     = $escaper->escapeHtmlAttr($key);
        $st    = $escaper->escapeHtmlAttr($route['state']);
        $glyph = $escaper->escapeHtmlAttr($path['icon']);

        // The locked explanation: one muted sentence, plus (when there is one)
        // the single next step that would unlock the route. The sentence is not
        // optional -- a greyed row with nothing to say reads as a failed load
        // rather than as an unmet prerequisite, which is the exact misreading
        // this treatment exists to prevent.
        $note = '';
        if ($route['locked'] && $route['note_key'] !== null) {
            $note = "
                <span class='sr-locked-note'>" . $escaper->escapeHtml($lang[$route['note_key']]);

            if ($route['link_key'] !== null && $route['unlock_href'] !== null) {
                // rel="noopener" only where it means something: 'external' is
                // true for exactly one destination (the Extras marketplace),
                // and opening an in-product admin page in a new tab would
                // strand the user's place on this page.
                $target = $route['external']
                    ? " target='_blank' rel='noopener noreferrer'"
                    : '';
                $note .= " <a class='sr-locked-link' href='"
                    . $escaper->escapeHtmlAttr($route['unlock_href']) . "'{$target}>"
                    . $escaper->escapeHtml($lang[$route['link_key']]) . "</a>";
            }

            $note .= "</span>";
        }

        $badge = $route['locked']
            ? " <span class='sr-locked-badge'><i class='fa fa-lock' aria-hidden='true'></i>"
                . $escaper->escapeHtml($lang['LockedAffordanceBadge']) . "</span>"
            : '';

        $body = "
              <span class='sr-fw-acquire-icon'><i class='fa {$glyph}' aria-hidden='true'></i></span>
              <span class='sr-fw-acquire-text'>
                <span class='sr-fw-acquire-label'>" . $escaper->escapeHtml($path['label']) . "{$badge}</span>
                <span class='sr-fw-acquire-hint'>" . $escaper->escapeHtml($path['hint']) . "</span>{$note}
              </span>";

        if ($route['locked']) {
            // Not a .dropdown-item, and not a <button>/<a> -- see the block
            // comment on this function. data-sr-locked-state carries the reason
            // so a test can assert WHICH lock a row is in without reading copy
            // Crowdin may translate.
            echo "
            <div class='sr-fw-acquire-item sr-locked' data-sr-fw-acquire='{$k}' data-sr-locked-state='{$st}' aria-disabled='true'>{$body}
            </div>";
        } elseif ($path['href'] === null) {
            // The modal route. Native data-bs-toggle, like the pre-chooser
            // button -- so governance-frameworks.js's one show.bs.modal
            // delegate stays the sole owner of the modal's blank-vs-clone
            // framing and there is no second, JS-only way to open it that could
            // skip that decision.
            echo "
            <button type='button' class='dropdown-item sr-fw-acquire-item' data-sr-fw-acquire='{$k}' data-sr-locked-state='{$st}' data-bs-toggle='modal' data-bs-target='#framework--add'>{$body}
            </button>";
        } else {
            echo "
            <a class='dropdown-item sr-fw-acquire-item' data-sr-fw-acquire='{$k}' data-sr-locked-state='{$st}' href='" . $escaper->escapeHtmlAttr($path['href']) . "'>{$body}
            </a>";
        }
    }

    echo "
          </div>
        </div>";
}

?>