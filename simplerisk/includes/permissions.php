<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/alerts.php'));

// Include the language file
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

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

// The list of currently existing permissions.
// Leaving here for backwards compatibility
$possible_permissions = get_possible_permissions();


/*************************************
 * FUNCTION: CHECK PERMISSION ACCESS *
 *************************************/
function check_permission_access()
{
	// If access to the application is not authorized for this session
	if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
	{
		write_debug_log("Access to the application is not authorized for this session.");
		return false;
	}
	// If access to the application is authorized for this session
	else
	{
		write_debug_log("Access to the application is authorized for this session.");
		return true;
	}
}

/***************************************
 * FUNCTION: ENFORCE PERMISSION ACCESS *
 ***************************************/
function enforce_permission_access()
{
        // If access is not authorized
        if (!check_permission_access())
        {
		// Store the requested URL in the session so we can redirect the user back to it after authentication
		set_unauthenticated_redirect();

		write_debug_log("Redirecting back to the login page.");

		// Redirect the user to the login page
                header("Location: ../index.php");

		// Stop any further processing
                exit(0);
        }
}

/************************************
 * FUNCTION: CHECK PERMISSION ADMIN *
 ************************************/
function check_permission_admin()
{ 
	// If access to admin is not authorized for this session
	if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
	{
		write_debug_log("The currently authenticated session does not have admin privileges.");
		return false;
	}
	// If access to admin is authorized for this session
	else
	{
		write_debug_log("The currently authenticated session does have admin privileges.");
		return true;
	}
}

/**************************************
 * FUNCTION: ENFORCE PERMISSION ADMIN *
 **************************************/
function enforce_permission_admin()
{
        // If admin is not authorized
        if (!check_permission_admin())
        {
                write_debug_log("Redirecting back to the login page.");

                // Redirect the user to the login page
                header("Location: ../index.php");

                // Stop any further processing
                exit(0);
        }
}

/*****************************************
 * FUNCTION: CHECK PERMISSION GOVERNANCE *
 *****************************************/
function check_permission_governance()
{
        // Check if governance is authorized
        if (!isset($_SESSION["governance"]) || $_SESSION["governance"] != 1)
        {
		write_debug_log("The currently authenticated session does not have governance privileges.");
                return false;
        }
	else
	{
		write_debug_log("The currently authenticated session does have governance privileges.");
		return true;
	}
}

/*******************************************
 * FUNCTION: ENFORCE PERMISSION GOVERNANCE *
 *******************************************/
function enforce_permission_governance()
{
        // If governance is not authorized
        if (!check_permission_governance())
        {
                header("Location: ../index.php");
                exit(0);
        }
}

/*********************************************
 * FUNCTION: CHECK PERMISSION RISKMANAGEMENT *
 *********************************************/
function check_permission_riskmanagement()
{
	// Check if riskmanagement is authorized
	if (!isset($_SESSION["riskmanagement"]) || $_SESSION["riskmanagement"] != 1)
	{
		write_debug_log("The currently authenticated session does not have risk management privileges.");
		return false;
	}
	else
	{
		write_debug_log("The currently authenticated session does have risk management privileges.");
		return true;
	}
}

/***********************************************
 * FUNCTION: ENFORCE PERMISSION RISKMANAGEMENT *
 ***********************************************/
function enforce_permission_riskmanagement()
{
        // If riskmanagement is not authorized
	if (!check_permission_riskmanagement())
        {
                header("Location: ../index.php");
                exit(0);
        }
}

/*****************************************
 * FUNCTION: CHECK PERMISSION COMPLIANCE *
 *****************************************/
function check_permission_compliance()
{
        // Check if compliance is authorized
        if (!isset($_SESSION["compliance"]) || $_SESSION["compliance"] != 1)
        {
		write_debug_log("The currently authenticated session does not have compliance privileges.");
                return false;
        }
	else
	{
		write_debug_log("The currently authenticated session does have compliance privileges.");
		return true;
	}
}

/*******************************************
 * FUNCTION: ENFORCE PERMISSION COMPLIANCE *
 *******************************************/
function enforce_permission_compliance()
{
        // If compliance is not authorized
        if (!check_permission_compliance())
        {
                header("Location: ../index.php");
                exit(0);
        }
}

/*************************************
 * FUNCTION: CHECK PERMISSION ASSET *
 *************************************/
function check_permission_asset()
{
        // Check if asset is authorized
        if (!isset($_SESSION["asset"]) || $_SESSION["asset"] != 1)
        {
		write_debug_log("The currently authenticated session does not have asset privileges.");
                return false;
        }
	else
	{
		write_debug_log("The currently authenticated session does have asset privileges.");
		return true;
	}
}

/*******************************************
 * FUNCTION: ENFORCE PERMISSION ASSET *
 *******************************************/
function enforce_permission_asset()
{
        // If asset is not authorized
        if (!check_permission_asset())
        {
                header("Location: ../index.php");
                exit(0);
        }
}

/******************************************
 * FUNCTION: CHECK PERMISSION ASSESSMENTS *
 ******************************************/
function check_permission_assessments()
{
	// Check if assessments is authorized
	if (!isset($_SESSION["assessments"]) || $_SESSION["assessments"] != 1)
	{
		write_debug_log("The currently authenticated session does not have assessment privileges.");
		return false;
	}
	else
	{
		write_debug_log("The currently authenticated session does have assessment privileges.");
		return true;
	}
}

/********************************************
 * FUNCTION: ENFORCE PERMISSION ASSESSMENTS *
 ********************************************/
function enforce_permission_assessments()
{
    // If asset is not authorized
    if (!check_permission_assessments())
    {
        header("Location: ../index.php");
        exit(0);
    }
}

/*************************************
 * FUNCTION: CHECK PERMISSION ASSET *
 *************************************/
$exception_permissions = ['view' ,'create' ,'update' ,'delete' ,'approve'];
function check_permission_exception($function)
{
    global $exception_permissions;
    return in_array($function, $exception_permissions)
            && isset($_SESSION["{$function}_exception"])
            && $_SESSION["{$function}_exception"] == 1;
}

function enforce_permission_exception($function)
{
    // If exception is not authorized
    if (!check_permission_exception($function))
    {
        header("Location: ../index.php");
        exit(0);
    }
}

/********************************************************************************
 * FUNCTION: CHECK QUESTIONNAIRE GET TOKEN                                      *
 * Checks if the 'GET' parameter 'token' is a valid questionnaire token.        *
 * The function is built in a way to only check the database once per request   *
 * to reduce response time.                                                     *
 ********************************************************************************/
function check_questionnaire_get_token() {

    if (!isset($_GET['token']))
        return false;

    $global_var_name = 'is_valid_questionnaire_token_' . $_GET['token'];

    if (isset($GLOBALS[$global_var_name]))
        return $GLOBALS[$global_var_name];

    if (assessments_extra()) {
        require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

        $GLOBALS[$global_var_name] = is_valid_questionnaire_token($_GET['token']);
        return $GLOBALS[$global_var_name];
    }

    $GLOBALS[$global_var_name] = false;
    return false;
}

/****************************************
 * FUNCTION: HAS PERMISSION             *
 * Checks if the user has $permission.  *
 ****************************************/
function has_permission($permission) {
    return $permission && isset($_SESSION[$permission]) && $_SESSION[$permission] == 1;
}

/**************************************
 * FUNCTION: GET POSSIBLE PERMISSIONS *
 **************************************/
function get_possible_permissions() {
    
    // For backwards compatibility
    if (!table_exists('permissions')) {
        return [
            'governance',
            'riskmanagement',
            'compliance',
            'assessments',
            'asset',
            'admin',
            'review_veryhigh',
            'accept_mitigation',
            'review_high',
            'review_medium',
            'review_low',
            'review_insignificant',
            'submit_risks',
            'modify_risks',
            'plan_mitigations',
            'close_risks',
            'add_new_frameworks',
            'modify_frameworks',
            'delete_frameworks',
            'add_new_controls',
            'modify_controls',
            'delete_controls',
            'add_documentation',
            'modify_documentation',
            'delete_documentation',
            'comment_risk_management',
            'comment_compliance',
            'view_exception',
            'create_exception',
            'update_exception',
            'delete_exception',
            'approve_exception',
            'add_projects',
            'delete_projects',
            'manage_projects',
            'define_tests',
            'edit_tests',
            'delete_tests',
            'initiate_audits',
            'modify_audits',
            'reopen_audits',
            'delete_audits'
        ];
    }
    
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("SELECT `key` FROM `permissions`;");
    $stmt->execute();
    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Close the database connection
    db_close($db);
    
    return $perms;
}

function get_possible_permission_ids() {
    
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("SELECT `id` FROM `permissions`;");
    $stmt->execute();
    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Close the database connection
    db_close($db);
    
    return $perms;
}

function set_default_role($role_id) {
    // Open the database connection
    $db = db_open();
    
    // To update in a single db call to prevent having no default or having two defaults at the same time
    $stmt = $db->prepare("UPDATE `role` r SET `r`.`default` = CASE WHEN `r`.`value` = :role_id THEN 1 ELSE NULL END ORDER BY `r`.`default` DESC;");
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
}

function get_default_role() {
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("SELECT * FROM `role` WHERE `default` IS NOT NULL;");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    return $role;
}

/*************************************
 * FUNCTION: GET PERMISSIONS OF USER *
 *************************************/
function get_grouped_permissions($user_id = false) {
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("
        SELECT
        	`pg`.`name` as permission_group_name,
            `pg`.`id` as permission_group_id,
            `pg`.`description` as permission_group_description,
            `p`.`id` as permission_id,
          	`p`.`name` as permission_name,
            `p`.`description` as permission_description,
        	`p`.`key`" . ($user_id ? ",(`p2u`.`user_id` IS NOT NULL) as selected" : "") . "
        FROM
        	`permission_groups` pg
            INNER JOIN `permission_to_permission_group` p2pg ON `p2pg`.`permission_group_id` = `pg`.`id`
        	INNER JOIN `permissions` p ON `p2pg`.`permission_id` = `p`.`id` " .
        ($user_id ? "LEFT JOIN `permission_to_user` p2u ON `p`.`id` = `p2u`.`permission_id` AND `p2u`.`user_id` = :user_id" : "") . "
        GROUP BY
        	`pg`.`id`, `p`.`id`
        ORDER BY
        	`pg`.`order`, `p`.`order`;
    ");
        
        if ($user_id) {
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $perms = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
        
        // Close the database connection
        db_close($db);
        
        return $perms;
}

function update_permissions($user_id, $permissions) {
    
    $current_permissions = get_permission_ids_of_user($user_id);
    
    if (save_junction_values('permission_to_user', 'user_id', $user_id, 'permission_id', $permissions)) {
        
        // Calculate what permissions are removed from the user and what permissions are added
        $permissions_to_remove = array_diff($current_permissions, $permissions);
        $permissions_to_add = array_diff($permissions, $current_permissions);
        
        // No audit logging is needed if nothing changed
        if ($permissions_to_add || $permissions_to_remove) {
            
            $permission_changes = [];
            if ($permissions_to_add) {
                $permission_changes[] = _lang('PermissionUpdateAuditLogAdded', array('permissions_added' => get_names_by_multi_values('permissions', $permissions_to_add, false, ', ', true)), false);
            }
            if ($permissions_to_remove) {
                $permission_changes[] = _lang('PermissionUpdateAuditLogRemoved', array('permissions_removed' => get_names_by_multi_values('permissions', $permissions_to_remove, false, ', ', true)), false);
            }
            
            $message = _lang('UserPermissionUpdateAuditLog',
                array(
                    'user' => $_SESSION['user'],
                    'username' => get_name_by_value("user", $user_id),
                    'permissions_from' => get_names_by_multi_values('permissions', $current_permissions, false, ', ', true),
                    'permissions_to' => get_names_by_multi_values('permissions', $permissions, false, ', ', true),
                    'permission_changes' => implode(", ", $permission_changes)
                ),
                false
                );
            
            write_log((int)$user_id + 1000, $_SESSION['uid'], $message, 'user');
        }
    }
}

/*************************************
 * FUNCTION: GET PERMISSIONS OF USER *
 *************************************/
function get_permissions_of_user($user_id) {
    // Open the database connection
    $db = db_open();
    
    // For backwards compatibility
    if (!table_exists('permissions')) {
        global $possible_permissions;
        
        $permission_selects = [];
        foreach ($possible_permissions as $permission) {
            // We can only do this because it only happens when we get back permission keys we defined from code
            $permission_selects[] = "SELECT `value` as user_id, '$permission' AS name FROM `user` WHERE `$permission` = 1 OR `admin` = 1";
        }
        
        $permissions_from_part = implode(" UNION ALL ", $permission_selects);
        
        $stmt = $db->prepare("
            SELECT
                DISTINCT `perms`.`name`
            FROM
                `user` u
                LEFT JOIN ($permissions_from_part) perms ON `u`.`value` = `perms`.`user_id`
            WHERE
                `u`.`value` = :user_id;
        ");
    } else {
        $stmt = $db->prepare("
            SELECT
                `key`
            FROM
                `permissions` p
                INNER JOIN `permission_to_user` p2u ON `p`.`id` = `p2u`.`permission_id`
            WHERE
                `p2u`.`user_id` = :user_id;
        ");
    }
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Close the database connection
    db_close($db);
    
    return $perms;
}

/****************************************
 * FUNCTION: GET PERMISSION IDS OF USER *
 ****************************************/
function get_permission_ids_of_user($user_id) {
    
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("
        SELECT
            `p`.`id`
        FROM
            `permissions` p
            INNER JOIN `permission_to_user` p2u ON `p`.`id` = `p2u`.`permission_id`
        WHERE
            `p2u`.`user_id` = :user_id;
    ");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Close the database connection
    db_close($db);
    
    return $perms;
}

/*********************************
 * FUNCTION: USER HAS PERMISSION *
 *********************************/
function user_has_permission($user_id, $permission_key) {

    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("
        SELECT
            5
        FROM
            `permissions` p
            INNER JOIN `permission_to_user` p2u ON `p`.`id` = `p2u`.`permission_id`
        WHERE
            `p2u`.`user_id` = :user_id
            AND `p`.`key` = :permission_key;
    ");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":permission_key", $permission_key, PDO::PARAM_STR);
    
    $stmt->execute();

    $result = $stmt->fetchColumn();

    // Close the database connection
    db_close($db);

    return isset($result) && (int)$result === 5;
    
}
?>
