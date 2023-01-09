<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/alerts.php'));

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

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

/******************************
 * FUNCTION: CHECK PERMISSION *
 ******************************/
function check_permission($permission)
{
	// If we have a valid session
	if (isset($_SESSION['user']) && $_SESSION['user'] != "")
	{
		$message = "The currently authenticated session for username \"" . $_SESSION['user'] . "\"";
	}
	else $message = "The currently unauthenticated session";

	// Check if the permission is authorized
	if (!isset($_SESSION[$permission]) || $_SESSION[$permission] != 1)
	{
		write_debug_log($message . " does not have the \"" . $permission . "\" permission.");
		return false;
	}
	else
	{
		write_debug_log($message . " has the \"" . $permission . "\" permission.");
		return true;
	}
}	

/********************************
 * FUNCTION: ENFORCE PERMISSION *
 ********************************/
function enforce_permission($permission)
{
	// If the permission is not authorized
	if (!check_permission($permission))
	{
		// Different actions for different permissions
		switch ($permission)
		{
			// If this is the access permission
			case "access":
				// Store the requested URL in the session so we can redirect the user back to it after authentication
				set_unauthenticated_redirect();

				write_debug_log("Redirecting back to the login page.");

				// Redirect the user to the login page
				header("Location: ../index.php");

				// Stop any further processing
				exit(0);
			default:
				write_debug_log("Redirecting back to the login page.");

				// Redirect the user to the login page
				header("Location: ../index.php");

				// Stop any further processing
				exit(0);
		}
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

/********************************
 * FUNCTION: UPDATE PERMISSIONS *
 ********************************/
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
                    'user' => isset($_SESSION['user']) ? $_SESSION['user'] : '', // because it can happen that this function is used by the custom authentication logic when there's no session yet
                    'username' => get_name_by_value("user", $user_id),
                    'permissions_from' => get_names_by_multi_values('permissions', $current_permissions, false, ', ', true),
                    'permissions_to' => get_names_by_multi_values('permissions', $permissions, false, ', ', true),
                    'permission_changes' => implode(", ", $permission_changes)
                ),
                false
                );
            
            write_log((int)$user_id + 1000, isset($_SESSION['uid']) ? $_SESSION['uid'] : 0, $message, 'user');
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

/*********************************
 * FUNCTION: ADD NEW PERMISSIONS *
 *********************************/
function add_new_permissions($permission_groups_and_permissions)
{
	// Open the database connection
	$db = db_open();

	// Create an array for the new permissions
	$new_permissions = [];

	// For each of the permission groups provided
	foreach ($permission_groups_and_permissions as $_ => $group)
	{
		// Pull out the group information
		$group_name = $group['name'];
		$group_description = $group['description'];
		$group_order = $group['order'];
		$permissions = $group['permissions'];

		// Create the permission group
		$stmt = $db->prepare("INSERT IGNORE INTO `permission_groups` (`name`, `description`, `order`) VALUES (:name, :description, :order);");
		$stmt->bindParam(":name", $group_name, PDO::PARAM_STR);
		$stmt->bindParam(":description", $group_description, PDO::PARAM_STR);
		$stmt->bindParam(":order", $group_order, PDO::PARAM_INT);
		$stmt->execute();

		// Get the permission group id
		$group_id = $db->lastInsertId();

		// Write debug log
		write_debug_log("Added new permission group with the following values:");
		write_debug_log("GROUP ID: " . $group_id);
		write_debug_log("NAME: " . $group_name);
		write_debug_log("DESCRIPTION: " . $group_description);
		write_debug_log("ORDER: " . $group_order);

		// Write audit log
		$message = "A new permission group named \"" . $group_name . "\" was added to the system.";
		write_log(1000, $_SESSION['uid'], $message, "user");

		// For each of the permissions in this permission group
		foreach ($permissions as $key => $permission)
		{
			// Pull out the permission information
			$permission_name = $permission['name'];
			$permission_description = $permission['description'];
			$permission_order = $permission['order'];

			// Create the permission
			$stmt = $db->prepare("INSERT IGNORE INTO `permissions` (`key`, `name`, `description`, `order`) VALUES (:key, :name, :description, :order);");
			$stmt->bindParam(":key", $key, PDO::PARAM_STR);
			$stmt->bindParam(":name", $permission_name, PDO::PARAM_STR);
			$stmt->bindParam(":description", $permission_description, PDO::PARAM_STR);
			$stmt->bindParam(":order", $permission_order, PDO::PARAM_INT);
			$stmt->execute();

			// Get the permission id
			$stmt = $db->prepare("SELECT `id` FROM `permissions` WHERE `name` = :name;");
			$stmt->bindParam(":name", $permission_name, PDO::PARAM_STR);
			$stmt->execute();
			$permission_id = $stmt->fetch(PDO::FETCH_ASSOC);
			$permission_id = $permission_id['id'];

			// Add the new permission to the new permissions array
			$new_permissions[] = $permission_id;

			// Write debug log
			write_debug_log("Added new permission with the following values:");
			write_debug_log("PERMISSION ID: " . $permission_id);
			write_debug_log("KEY: " . $key);
			write_debug_log("NAME: " . $permission_name);
			write_debug_log("DESCRIPTION: " . $permission_description);
			write_debug_log("ORDER: " . $permission_order);

			// Write audit log
			$message = "A new permission named \"" . $permission_name . "\" was added to the system.";
			write_log(1000, $_SESSION['uid'], $message, "user");

			// Add the permission to the permission group
			$stmt = $db->prepare("INSERT IGNORE INTO `permission_to_permission_group` (`permission_id`, `permission_group_id`) VALUES (:permission_id, :permission_group_id);");
			$stmt->bindParam(":permission_id", $permission_id);
			$stmt->bindParam(":permission_group_id", $group_id);
			$stmt->execute();

			// Write debug log
			write_debug_log("Added permission id \"" . $permission_id . "\" to group id \"" . $group_id . "\".");

			// Write audit log
			$message = "The \"" . $permission_name . "\" permission was added to the \"" . $group_name . "\" permission group.";
			write_log(1000, $_SESSION['uid'], $message, "user");
		}
	}

	// For each admin user
	$admin_users = get_admin_users();
	foreach ($admin_users as $user)
	{
		// Get the user values
		$user_id = (int)$user['value'];
		$username = $user['username'];

		// Get the current permissions of this user
		$current_permissions = get_permission_ids_of_user($user_id);

		// Add the new permission to the current permissions
		$updated_permissions = array_merge($current_permissions, $new_permissions);

		// Add the updated permissions to the admin user
		update_permissions($user_id, $updated_permissions);

		// If the update affects the current logged in user
		if ($_SESSION['uid'] == $user_id)
		{
			// Update the current user's permissions
			set_user_permissions($username);
		}

		// Refresh the permissions in the active sessions of the user
		refresh_permissions_in_sessions_of_user($user_id);

		// Write debug log
		write_debug_log("The new permissions were added to the \"" . $username . "\" user.");
	}

	// Automatically grant all permissions to roles granted admin
	$stmt = $db->prepare("
            INSERT IGNORE INTO
                `role_responsibilities`(`role_id`, `permission_id`)
            SELECT
                `r`.`value`,
                `p`.`id`
            FROM
                `role` r, `permissions` p
            WHERE
                `r`.`admin` = 1;
	");
	$stmt->execute();

	// Close the database connection
	db_close($db);
}

/********************************
 * FUNCTION: REMOVE PERMISSIONS *
 ********************************/
function remove_permissions($permission_groups_and_permissions)
{
	// Open the database connection
	$db = db_open();

        // Create an array for the removed permissions
        $removed_permissions = [];

	// For each of the permission groups provided
	foreach ($permission_groups_and_permissions as $_ => $group)
	{
		// Pull out the group information
		$group_name = $group['name'];
		$permissions = $group['permissions'];

		// For each of the permissions in this permission group
		foreach ($permissions as $key => $permission)
		{
			// Pull out the permission information
			$permission_name = $permission['name'];

			write_debug_log("Deleting permission named \"" . $permission_name . "\".");

                        // Get the permission id
                        $stmt = $db->prepare("SELECT `id` FROM `permissions` WHERE `name` = :name;");
                        $stmt->bindParam(":name", $permission_name, PDO::PARAM_STR);
                        $stmt->execute();
                        $permission_id = $stmt->fetch(PDO::FETCH_ASSOC);
                        $permission_id = $permission_id['id'];

                        // Add the new permission to the removed permissions array
                        $removed_permissions[] = $permission_id;

			// Delete the permission from the permission group
			$stmt = $db->prepare("
				DELETE FROM `permission_to_permission_group` WHERE `permission_id` = :permission_id;
			");
			$stmt->bindParam(":permission_id", $permission_id, PDO::PARAM_INT);
			$stmt->execute();

			// Delete the permission
			$stmt = $db->prepare("
				DELETE FROM `permissions` WHERE `name` = :name;
			");
			$stmt->bindParam(":name", $permission_name, PDO::PARAM_STR);
			$stmt->execute();

			// Write audit log
			$message = "The \"" . $permission_name . "\" permission was removed from the system.";
			write_log(1000, $_SESSION['uid'], $message, "user");
		}

		// After all permissions have been deleted, delete the permission group
		write_debug_log("Deleting permission group named \"" . $group_name . "\".");
		$stmt = $db->prepare("
			DELETE FROM `permission_groups`  WHERE `name` = :name;
		");
		$stmt->bindParam(":name", $group_name, PDO::PARAM_STR);
		$stmt->execute();

		// Write audit log
		$message = "The \"" . $group_name . "\" permission group was removed from the system.";
		write_log(1000, $_SESSION['uid'], $message, "user");
	}

	// Cleanup the permissions after the deletion
	cleanup_after_delete('permissions');
	cleanup_after_delete('permission_groups');

        // For each user
	$all_users = get_all_users();
        foreach ($all_users as $user)
        {
                // Get the user values
                $user_id = (int)$user['value'];
                $username = $user['username'];

                // Get the current permissions of this user
                //$current_permissions = get_permission_ids_of_user($user_id);

		// Remove the removed permissions from the current permissions
		//$updated_permissions = array_diff($current_permissions, $removed_permissions);

                // Remove the removed permissions from the user
                //update_permissions($user_id, $updated_permissions);

                // If the update affects the current logged in user
                if ($_SESSION['uid'] == $user_id)
                {
                        // Update the current user's permissions
                        set_user_permissions($username);
                }

                // Refresh the permissions in the active sessions of the user
                refresh_permissions_in_sessions_of_user($user_id);

                // Write debug log
                write_debug_log("The new permissions were added to the \"" . $username . "\" user.");
        }

	// Close the database connection
	db_close($db);
}

/*****************************
 * FUNCTION: GET ADMIN USERS *
 *****************************/
function get_admin_users()
{
	// Open the database connection
	$db = db_open();

	// Get all users with an admin role
	$stmt = $db->prepare("
		SELECT `value`, `username` FROM `user` WHERE admin = 1;
	");
	$stmt->execute();
	$admin_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Close the database connection
	db_close($db);

	// Return the list of admin users
	return $admin_users;
}

/************************************************
 * FUNCTION: CHECK REVIEW PERMISSION BY RISK ID *
 ************************************************/
function check_review_permission_by_risk_id($risk_id)
{
	// Get the calcualted risk for this risk id
	$calculated_risk = get_calculated_risk_by_id($risk_id);

	// Get the risk level name for this calculated risk
	$level = get_risk_level_name($calculated_risk);

	// Get the risk level display names
	$very_high_display_name = get_risk_level_display_name('Very High');
	$high_display_name      = get_risk_level_display_name('High');
	$medium_display_name    = get_risk_level_display_name('Medium');
	$low_display_name       = get_risk_level_display_name('Low');
	$insignificant_display_name = get_risk_level_display_name('Insignificant');

	// If the user has permission to review the current level
	if (($level == $very_high_display_name && has_permission("review_veryhigh")) || ($level == $high_display_name && has_permission("review_high")) || ($level == $medium_display_name && has_permission("review_medium")) || ($level == $low_display_name && has_permission("review_low")) || ($level == $insignificant_display_name && has_permission("review_insignificant")))
	{
		// Review is approved
		$approved = true;
	}
	// Otherwise the review is not approved
	else $approved = false;

	// Return the approved status
	return $approved;
}

?>
