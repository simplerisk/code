<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
require_once(realpath(__DIR__ . '/displayassets.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/*****************************
 * FUNCTION: DISCOVER ASSETS *
 *****************************/
function discover_assets($range)
{
    // Available IP array
        $AvailableIPs = array();

    // Check if the range is a single IP address
    if (preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $range))
    {
        if (ping_check($range))
        {
            $name = gethostbyaddr($range);
            $AvailableIPs[] = array("ip"=>$range, "name"=>$name);
        }

        // Add the live assets to the database
        add_assets($AvailableIPs);

        return $AvailableIPs;
    }
    // Check if it is a numerically expressed range
    if (preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)-(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $range))
    {
        // This could take a while so we increase the max execution time
            set_time_limit(300);

        // Break apart range by - delimiter
        $array = explode("-", $range);

        // Get the start and end IPs
        $start = $array[0];
        $end = $array[1];

        if ((ip2long($start) !== -1) && (ip2long($end) !== -1))
        {
            for ($ip = ip2long($start); $ip <= ip2long($end); $ip++)
            {
                if (ping_check(long2ip($ip)))
                {
                    $name = gethostbyaddr(long2ip($ip));
                    $AvailableIPs[] = array("ip"=>long2ip($ip), "name"=>$name);
                }
            }
        }

        // Add the live assets to the database
        add_assets($AvailableIPs);

        return $AvailableIPs;
    }
    // IP was not in a recognizable format
    else return false;
}

/************************
 * FUNCTION: PING CHECK *
 ************************/
function ping_check($ip)
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') { // Server OS is Windows
        $cmd = sprintf('ping -n 1 -w 1 %s', escapeshellarg($ip));
    } else { // Server OS is Linux
        $cmd = sprintf('ping -c 1 -W 1 %s', escapeshellarg($ip));

    }
    exec($cmd, $res, $rval);
    return $rval === 0;
}

/************************
 * FUNCTION: ADD ASSETS *
 ************************/
function add_assets($AvailableIPs)
{
    // For each IP
    foreach ($AvailableIPs as $ip)
    {
        $ipv4addr = $ip['ip'];
        $name = $ip['name'];

        // Set the default values for assets
        $value = get_default_asset_valuation();
        $location = 0;
        $team = 0;

        // Add the asset
        add_asset($ipv4addr, $name, $value, $location, $team, "", "", true);
    }
}

/**************************
 * FUNCTION: ASSET EXISTS *
 **************************/
function asset_exists($name)
{
    global $escaper;

    write_debug_log("Checking if asset named \"" . $escaper->escapeHtml($name) . "\" exists");

    // If the encryption extra is enabled
    if (encryption_extra())
    {
        write_debug_log("Encryption extra is enabled");

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        // Call the encrypted asset exists function
        return encrypted_asset_exists($name);
    }
    else
    {
        write_debug_log("Encryption extra is not enabled");
        
        // Open the database connection
        $db = db_open();
        
        // Check if the asset name is in the database
        $stmt = $db->prepare("SELECT id FROM `assets` WHERE name=:name;");
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->execute();

        // If it is then get the id and return the asset's id
        if ($stmt->rowCount() > 0) {

            $asset_id = $stmt->fetch(PDO::FETCH_COLUMN);

            // Close the database connection
            db_close($db);

            write_debug_log("Asset was found");
            return $asset_id;
        }

        // Close the database connection
        db_close($db);

        write_debug_log("Asset was not found");
        return false;
    }
}

/**
 * Checks if an asset exists with the provided id 
 * 
 * @param int $id ID we want to check for
 * @return boolean
 */
function asset_exists_by_id($id) {

    // If the user has access to the asset id
    if (check_access_for_asset($id))
    {
        // Open the database connection
        $db = db_open();

        // Check if the asset name is in the database
        $stmt = $db->prepare("SELECT 5 FROM `assets` WHERE id=:id;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchColumn();

        // Close the database connection
        db_close($db);

        return !empty($result) && (int)$result === 5;
    }
    // Otherwise return false
    else return false;
}

/**********************************
 * FUNCTION: ASSET EXISTS (EXACT) *
 **********************************/
function asset_exists_exact($ip, $name, $value, $location, $teams, $details, $verified)
{
    global $escaper;

    write_debug_log("Checking if asset named \"" . $escaper->escapeHtml($name) . "\" exists");

    // If the encryption extra is enabled
    if (encryption_extra())
    {
        write_debug_log("Encryption extra is enabled");

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        // Call the encrypted asset exists function
        $exists = encrypted_asset_exists_exact($ip, $name, $value, $location, $teams, $details, $verified);

        // Return the result
        return $exists;
    }
    else
    {
        write_debug_log("Encryption extra is not enabled");

        // Open the database connection
        $db = db_open();

        // Check if the asset is in the database
        $stmt = $db->prepare("SELECT id FROM `assets` WHERE `name`=:name AND `ip`=:ip AND `value`=:value AND `location`=:location AND `teams`=:teams AND `details`=:details AND `verified`=:verified;");
        $stmt->bindParam(":ip", $ip, PDO::PARAM_STR, 15);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, 200);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
        $stmt->bindParam(":location", $location, PDO::PARAM_STR);
        $stmt->bindParam(":teams", $teams, PDO::PARAM_STR);
        $stmt->bindParam(":details", $details, PDO::PARAM_STR);
        $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
        $stmt->execute();
        $assets = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the assets array contains at least one value
        if (count($assets) > 0)
        {
            write_debug_log("Asset was found");
            return true;
        }
        else
        {
            write_debug_log("Asset was not found");
            return false;
        }
    }
}


/********************************************************
 * FUNCTION: ADD ASSET BY NAME WITH FORCED VERIFICATION *
 * Used for adding an asset while defining its verified *
 * status, but without specifying the default values    *
 ********************************************************/
function add_asset_by_name_with_forced_verification($name, $verified = false) {
    // !!!!!!Update the values if the add_asset's default values change!!!!!!
    return add_asset('', $name, 5, 0, 0, "", "", $verified);
}

/***********************
 * FUNCTION: ADD ASSET *
 ***********************/
function add_asset($ip, $name, $value=5, $location="", $teams="", $details = "", $tags = "", $verified = false, $mapped_controls=[], $associated_risks = [], $imported = false)
{
    global $lang;

    // If the asset does not already exist
    if (!asset_exists($name)) {
    
        // Trim whitespace from the name, ip, and value
        $name   = trim($name);
        $ip     = trim($ip);
        $value  = trim($value);
        $location   = is_array($location) ? implode(',', $location) : $location;
        $teams   = is_array($teams) ? implode(',', $teams) : $teams;
        
        if (!$name) {
            return false;
        }

        // See if we need to encrypt values
        $ip_encrypted = try_encrypt($ip);
        $name_encrypted = try_encrypt($name);
        $details_encrypted = try_encrypt($details);

        $auto_verify_new_assets = get_setting("auto_verify_new_assets");

        if (!$verified && $auto_verify_new_assets && !$imported) {
            $verified = true;
        }

        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("INSERT INTO `assets` (ip, name, value, location, teams, details, verified) VALUES (:ip, :name, :value, :location, :teams, :details, :verified) ON DUPLICATE KEY UPDATE `ip`=:ip, `value`=:value, `location`=:location, `teams`=:teams, `details`=:details, `verified`=:verified;");
        $stmt->bindParam(":ip", $ip_encrypted, PDO::PARAM_STR);
        $stmt->bindParam(":name", $name_encrypted, PDO::PARAM_STR);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
        $stmt->bindParam(":location", $location, PDO::PARAM_STR);
        $stmt->bindParam(":teams", $teams, PDO::PARAM_STR);
        $stmt->bindParam(":details", $details_encrypted, PDO::PARAM_STR);
        $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
        $stmt->execute();

        $asset_id = $db->lastInsertId();

        // Close the database connection
        db_close($db);

        // Save control mappings
        if(is_array($mapped_controls)&&count($mapped_controls)>0) save_asset_to_controls($asset_id, $mapped_controls);

        // If customization extra is enabled
        if(customization_extra())
        {
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

            // If there is error in saving custom asset values, return false
            if(!save_custom_field_values($asset_id, "asset"))
            {
                delete_asset($asset_id);
                return false;
            }
        }

        updateTagsOfType($asset_id, 'asset', $tags);
        update_asset_risks_associations($asset_id, $associated_risks);

        if (notification_extra()) {
            require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

            // Send the notification about the updated risks
            foreach ($associated_risks as $risk_id) {
                notify_risk_update($risk_id);
            }
        }

        // If the encryption extra is enabled, updates order_by_name
        if (encryption_extra()) {
            require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
            update_name_order_for_asset($asset_id, $name);
        }

        $message = "Asset '{$name}' was added by user '{$_SESSION['user']}'.";
        write_log($asset_id , $_SESSION['uid'], $message, "asset");

        // Return success or failure
        return $asset_id;
    }
    // The asset already exists
    else
    {
        set_alert(true, "bad", $lang['ErrorAssetAlreadyExists']);
        return false;
    }
}
/**
* Returns the list of risk ids that are associated to the asset
*/
function get_associated_risks_for_asset($asset_id) {
    
    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("SELECT `risk_id` FROM `risks_to_assets` WHERE `asset_id` = :asset_id;");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $risk_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Close the database connection
    db_close($db);
    
    return $risk_ids;
}

function update_asset_risks_associations($asset_id, $associated_risks) {
    // Open the database connection
    $db = db_open();

    // Delete all associations for the asset
    $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE `asset_id` = :asset_id;");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();

    foreach($associated_risks as $risk_id){
        $stmt = $db->prepare("INSERT INTO `risks_to_assets` (asset_id, risk_id) VALUES (:asset_id, :risk_id);");
        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: SAVE CONTROL TO FRAMEWORK *
 ***************************************/
function save_control_to_assets($control_id, $mapped_assets)
{
    // Open the database connection
    $db = db_open();

    // Delete all current control asset relations
    $stmt = $db->prepare("DELETE FROM `control_to_assets` WHERE control_id=:control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();
    // Delete all current control asset group relations
    $stmt = $db->prepare("DELETE FROM `control_to_asset_groups` WHERE control_id=:control_id;");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    foreach($mapped_assets as $row){
        $control_maturity = $row[0];
        $assets_and_groups = $row[1];
        // For each asset or group
        foreach ($assets_and_groups as $value)
        {
            // Trim whitespaces
            $value = trim($value);
            
            // Selected an existing asset or group
            if (preg_match('/^([\d]+)_(group|asset)$/', $value, $matches)) {
                list(, $id, $type) = $matches;
            } elseif (preg_match('/^new_asset_(.*)$/', $value, $matches)) { // Entered the name of a new asset
                $name = trim($matches[1]);
                // Check if the asset already exists, but not verified(since it didnt show up in the widget)
                $id = asset_exists($name);

                if ($id) {
                    set_alert(true, "bad", _lang('ErrorAssetAlreadyExistsAsVerified', array('asset_name' => $name)));
                    continue;
                }
                // Add new asset
                $id = add_asset('', $name);
                $type = 'asset';                
            } else {
                //Invalid input
                continue;
            }

            if ($type=='asset' && !get_exist_mapping_asset_control($id, $control_id, $control_maturity)) {
                $stmt = $db->prepare("INSERT INTO `control_to_assets` (asset_id, control_id, control_maturity) VALUES (:asset_id, :control_id, :control_maturity)");
                $stmt->bindParam(":asset_id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
                $stmt->bindParam(":control_maturity", $control_maturity, PDO::PARAM_INT);
                $stmt->execute();
            } elseif ($type=='group' && !get_exist_mapping_asset_control($id, $control_id, $control_maturity, 'group')) {
                $stmt = $db->prepare("INSERT INTO `control_to_asset_groups` (asset_group_id, control_id, control_maturity) VALUES (:asset_group_id, :control_id, :control_maturity)");
                $stmt->bindParam(":asset_group_id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
                $stmt->bindParam(":control_maturity", $control_maturity, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

    }
    // Close the database connection
    db_close($db);  
}
/************************************
 * FUNCTION: SAVE ASSET TO CONTROLS *
 ************************************/
function save_asset_to_controls($asset_id, $control_mappings)
{
    // Open the database connection
    $db = db_open();

    // Delete all current asset control relations
    $stmt = $db->prepare("DELETE FROM `control_to_assets` WHERE asset_id=:asset_id;");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();
    
    foreach($control_mappings as $maturity_id => $control_ids){
        foreach($control_ids as $control_id){
            if(!get_exist_mapping_asset_control($asset_id, $control_id, $maturity_id)){
                $stmt = $db->prepare("INSERT INTO `control_to_assets` (asset_id, control_id, control_maturity) VALUES (:asset_id, :control_id, :control_maturity)");
                $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
                $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
                $stmt->bindParam(":control_maturity", $maturity_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

    }
    // Close the database connection
    db_close($db);
    return; 
}
/*********************************************
 * FUNCTION: GET EXIST MAPPING ASSET CONTROL *
 *********************************************/
function get_exist_mapping_asset_control($asset_or_group_id, $control_id, $control_maturity, $type = 'asset')
{
    // Open the database connection
    $db = db_open();
    if($type == 'group') {
        $tbl_name = 'control_to_asset_groups';
        $junction_id_name = 'asset_group_id';
    } else {
        $tbl_name = 'control_to_assets';
        $junction_id_name = 'asset_id';
    }
    $sql = "SELECT * FROM `{$tbl_name}`  WHERE {$junction_id_name} = :asset_or_group_id AND control_id = :control_id AND control_maturity = :control_maturity;";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":asset_or_group_id", $asset_or_group_id, PDO::PARAM_INT);
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->bindParam(":control_maturity", $control_maturity, PDO::PARAM_INT);
    $stmt->execute();
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);
    return $mappings;
}
/**********************************************
 * FUNCTION: GET MAPPING CONTROLS BY ASSET ID *
 **********************************************/
function get_mapping_controls_by_asset_id($asset_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT cta.*, cm.name control_maturity_name, c.short_name control_name
        FROM control_to_assets cta 
        LEFT JOIN control_maturity cm ON cta.control_maturity = cm.value
        LEFT JOIN framework_controls c ON c.id = cta.control_id
        WHERE asset_id=:asset_id ORDER BY id;
    ");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchALL(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $rows;
}
/**********************************************
 * FUNCTION: GET MAPPING ASSETS BY CONTROL ID *
 **********************************************/
function get_control_to_assets($control_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT t1.value control_maturity, t1.name control_maturity_name, GROUP_CONCAT(DISTINCT t2.asset_id), GROUP_CONCAT(DISTINCT assets.name) asset_name,
        GROUP_CONCAT(DISTINCT t3.asset_group_id), GROUP_CONCAT(DISTINCT ag.name) asset_group_name
        FROM control_maturity t1 
        LEFT JOIN control_to_assets t2 ON t1.value = t2.control_maturity
        LEFT JOIN assets ON assets.id = t2.asset_id
        LEFT JOIN control_to_asset_groups t3 ON t1.value = t3.control_maturity
        LEFT JOIN asset_groups ag ON ag.id = t3.asset_group_id
        WHERE t2.control_id=:control_id OR t3.control_id=:control_id
        GROUP BY t2.control_maturity ORDER BY t2.id;
        ");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchALL(PDO::FETCH_ASSOC);
    // decrypt data
    foreach($rows as &$row){
        $asset_name = explode(',', (string)$row['asset_name']);
        $asset_name_str = implode(", ", array_map(function($name){
            return try_decrypt($name);
        }, $asset_name));
        // Try to decrypt the asset name
        $row['asset_name'] = $asset_name_str;
    }

    // Close the database connection
    db_close($db);

    return $rows;
}


/**
 * Returns whether the asset has the exact same control mappings as the one provided
 * 
 * @param int $asset_id the id of the asset
 * @param array $control_mapping the control mapping in a format of {maturity_id1:[control_id1, control_id2, ...], ...}
 * @return boolean
 */
function asset_control_mapping_exact_match($asset_id, $control_mapping){
    
    $db = db_open();
    
    // Get the asset's control mapping
    $stmt = $db->prepare("				
        SELECT
            CONCAT('{', GROUP_CONCAT(DISTINCT `cm`.`data` ORDER BY `cm`.`data` SEPARATOR ','), '}')
        FROM
            (SELECT
            	`a`.`id` AS asset_id,
            	CONCAT(
                    '\"', `cta`.`control_maturity`, '\":', JSON_ARRAYAGG(`cta`.`control_id`)
                ) as data
            FROM
            	`assets` a
            	LEFT JOIN `control_to_assets` cta ON `cta`.`asset_id` = `a`.`id`
            WHERE
            	`cta`.`id` IS NOT NULL
                and `a`.`id` = :asset_id
            GROUP BY
            	`a`.`id`,
            	`cta`.`control_maturity`
            ORDER BY
            	`a`.`id`) cm
        GROUP BY `cm`.`asset_id`
    ");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();
    $asset_control_mapping = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    // If we're comparing two empty lists then it's an exact match
    if (empty($asset_control_mapping) && empty($control_mapping)) {
        return true;
    }

    // If one is empty, but the other isn't, then they can't be the same(obviously)
    if (empty($asset_control_mapping) != empty($control_mapping)) {
        return false;
    }

    // decode the string into an array
    $asset_control_mapping = json_decode($asset_control_mapping, true);

    // If the number of elements doesn't match, return false
    if (count($asset_control_mapping) != count($control_mapping)) {
        return false;
    }

    
    // Flatten the mnulti-dimensional arrays so we can easily compare the composition of the two
    // Example:  {1:[3, 5], 3:[3, 5]} => ["1:3", "1:5", "3:3", "3:5"]
    $flat_asset_control_mapping = [];
    $flat_control_mapping = [];
    foreach ($control_mapping as $maturity => $control_ids) {
        foreach ($control_ids as $control_id) {
            $flat_control_mapping []= "{$maturity}:{$control_id}";
        }
    }
    foreach ($asset_control_mapping as $maturity => $control_ids) {
        foreach ($control_ids as $control_id) {
            $flat_asset_control_mapping []= "{$maturity}:{$control_id}";
        }
    }

    // If the number of elements doesn't match, return false. It's ifferent from the previous similar check, because that only checked the number
    // of groups when it was grouped by maturity id. This one is the number of the all the controls involved.
    if (count($flat_asset_control_mapping) != count($flat_control_mapping)) {
        return false;
    }

    // To properly check the diff of two arrays we have to check both directions
    return array_diff($flat_asset_control_mapping, $flat_control_mapping) == array_diff($flat_control_mapping, $flat_asset_control_mapping);
}

/*******************************
 * FUNCTION: DELETE ALL ASSETS *
 *******************************/
function delete_all_assets($verified = false) {
    try {
        // Open the database connection
        $db = db_open();

        // Get the names of the affected assets to be able to log which ones got deleted/discarded
        $stmt = $db->prepare("SELECT `id`, `name` FROM `assets` WHERE `verified` = :verified;");
        $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
        $stmt->execute();
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($assets)) {
            return false;
        }

        // Delete the assets
        $stmt = $db->prepare("DELETE FROM `assets` WHERE `verified` = :verified;");
        $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        // If customization extra is enabled, delete custom_asset_data related with asset ID
        if(customization_extra()) {
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            foreach ($assets as $asset) {
                delete_custom_data_by_row_id($asset['id'], "asset");
            }
        }

        // Clean up after the delete
        cleanup_after_delete('assets');

        // Write a log for each asset deleted
        foreach ($assets as $asset) {
            $message = _lang($verified ? 'AssetDeletedLog' : 'AssetDiscardedLog', [
                'name' => try_decrypt($asset['name']),
                'user' => $_SESSION['user']
            ]);
            write_log($asset['id'], $_SESSION['uid'], $message, "asset");
        }
        return true;
    } catch (Exception $e) {
        // Log the exception and return false
        error_log($e);
        return false;
    }}

/**************************
 * FUNCTION: DELETE ASSET *
 **************************/
function delete_asset($asset_id) {
    
    // Open the database connection
    $db = db_open();

    // Get the name BEFORE deleting the asset
    $name = get_name_by_value('assets', $asset_id, "", true);

    // Delete the assets entry
    $stmt = $db->prepare("DELETE FROM `assets` WHERE `id`=:id;");
    $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Close the database connection
    db_close($db);

    // If customization extra is enabled, delete custom_asset_data related with asset ID
    if(customization_extra()) {
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        delete_custom_data_by_row_id($asset_id, "asset");
    }

    // Clean up after the delete
    cleanup_after_delete('assets');

    $message = _lang('AssetDeletedLog', [
        'name' => $name,
        'user' => $_SESSION['user']
    ]);

    write_log($asset_id, $_SESSION['uid'], $message, "asset");

    // Return success or failure
    return $return;
}


/*******************************
 * FUNCTION: VERIFY ALL ASSETS *
 *******************************/
function verify_all_assets() {
    try {
        // Open the database connection
        $db = db_open();
        
        // Get the names of the not verified assets to be able to log which ones got verified
        $stmt = $db->prepare("SELECT `id`, `name` FROM `assets` WHERE `verified` = 0;");
        $stmt->execute();
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($assets)) {
            return false;
        }

        // Verify the unverified assets
        $stmt = $db->prepare("UPDATE `assets` SET `verified` = 1 WHERE `verified` = 0;");
        $stmt->execute();

        // Close the database connection
        db_close($db);

        // Write a log for each asset verified
        foreach ($assets as $asset) {
            $message = _lang('AssetVerifiedLog', [
                'name' => try_decrypt($asset['name']),
                'user' => $_SESSION['user']
            ]);
            write_log($asset['id'], $_SESSION['uid'], $message, "asset");
        }

        return true;
    } catch (Exception $e) {
        // Log the exception and return false
        error_log($e);
        return false;
    }
}

/**************************
 * FUNCTION: VERIFY ASSET *
 **************************/
function verify_asset($asset_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("UPDATE `assets` SET `verified` = 1 WHERE `id` = :id");
    $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Close the database connection
    db_close($db);

    $message = _lang('AssetVerifiedLog', [
        'name' => get_name_by_value('assets', $asset_id, "", true),
        'user' => $_SESSION['user']
    ]);

    write_log($asset_id, $_SESSION['uid'], $message, "asset");

    // Return success or failure
    return $return;
}

/********************************
 * FUNCTION: GET ENTERED ASSETS *
 ********************************/
function get_entered_assets($verified=null)
{
    // Open the database connection
    $db = db_open();

    $params = [];

    if ($verified !== null) {
        $where = " WHERE `a`.`verified`=:verified";
        $params['verified'] = $verified;
    } else $where = " WHERE 1";

    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where .= get_user_teams_query_for_assets("a", false, true);
    }

    if (encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }

    $stmt = $db->prepare("
        SELECT
            a.*,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR ',') as tags
        FROM
            `assets` a
            LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id AND tt.type = 'asset'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        {$where}
        GROUP BY
            a.id
        ORDER BY
            a.id, " . (encryption_extra() ? "a.order_by_name" : "a.name") . "
    ;");
    $stmt->execute($params);

    // Store the list in the assets array
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get the name keys
    $keys = array_column($assets, 'name');

    // Sort the array by name
    array_multisort($keys, SORT_ASC, $assets);

    // Close the database connection
    db_close($db);

    // Return the array of assets
    return $assets;
}

/***********************************
 * FUNCTION: GET UNVERIFIED ASSETS *
 ***********************************/
function get_unverified_assets()
{
    return get_entered_assets(false);
}

/***********************************
 * FUNCTION: GET VERIFIED ASSETS *
 ***********************************/
function get_verified_assets()
{
    return get_entered_assets(true);
}

/*****************************
 * FUNCTION: GET ASSET BY ID *
 *****************************/
function get_asset_by_id($id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT a.*,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR ',') as tags
        FROM
            `assets` a
            LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id AND tt.type = 'asset'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        where a.id=:id
        GROUP BY
            a.id
        ORDER BY name;
        ");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the assets array
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the array of assets
    return $asset;
}

/*****************************************
 * FUNCTION: TAG AFFECTED ASSETS TO RISK *
 *****************************************/
function tag_assets_to_risk($risk_id, $assets, $entered_assets=false)
{
    if($entered_assets === false)
    {
        $entered_assets = get_entered_assets();
    }

    // Create an array from the assets
    $assets = explode(",", $assets);

    // Open the database connection
    $db = db_open();

    // Clear any current assets for this risk
    $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE risk_id = :risk_id");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // For each asset
    foreach ($assets as $asset)
    {
        // Trim whitespace
        $asset = trim($asset);
        
        // If the asset is not null
        if ($asset != "")
        {
            $asset_id = false;
            foreach($entered_assets as $entered_asset){
                if($asset == try_decrypt($entered_asset['name'])){
                    $asset_id = $entered_asset['id'];
                    break;
                }
            }
            if(!$asset_id){
                $asset_id = add_asset('', $asset);
            }

            // Add the new assets for this risk
            $stmt = $db->prepare("INSERT INTO `risks_to_assets` (`risk_id`, `asset_id`) VALUES (:risk_id, :asset_id)");
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Add the asset_id column to risks_to_assets
    //$stmt = $db->prepare("UPDATE `risks_to_assets` INNER JOIN `assets` ON `assets`.name = `risks_to_assets`.asset SET `risks_to_assets`.asset_id = `assets`.id;");
    //$stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************************
 * FUNCTION: DISPLAY EDIT ASSET TABLE *
 **************************************/
function display_edit_asset_table()
{
    global $lang;
    global $escaper;
    $tags_active = false;

    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("asset");

        foreach($active_fields as $field) {
            if($field['is_basic'] == 1 && $field['name'] === 'Tags') {
                $tags_active = true;
                break;
            }
        }

        $customization = true;
    } else {
        $customization = false;
        $tags_active = true;
    }

    if ($tags_active == true) {
        echo "<div class='tag-max-length-warning'>" . $escaper->escapeHtml($lang['MaxTagLengthWarning']) . "</div>\n";
    }

    echo "<table id=\"edit-assets-table\" class=\"table table-bordered table-condensed sortable\">\n";

    // Display the table header
    echo "<thead>\n";
    echo "<tr>\n";
    
    // If the customization extra is enabled, shows fields by asset customization
    if ($customization)
    {
        display_main_detail_asset_fields_th($active_fields);
    }
    // If the customization extra is disabled, Show default main fields
    else
    {
        display_asset_name_th();
        display_asset_ip_address_th();
        display_asset_valuation_th();
        display_asset_site_location_th();
        display_asset_team_th();
        display_asset_details_th();
        display_asset_tags_th();
    }
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['Verified']) . "</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // If the customization extra, set custom values
    if (customization_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $active_fields = get_active_fields("asset");
    }
    // If the customization extra is disabled, Show default main fields
    else
    {
        $active_fields = [];
    }

    // Get the array of assets
    $assets = get_entered_assets();

    // For each asset
    foreach ($assets as $asset)
    {
        // Get the asset IP decrypted
        $asset_ip = try_decrypt($asset['ip']);

        // If the IP address is not valid
        if (!preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $asset_ip))
        {
                $asset_ip = "N/A";
        }

        echo "<tr data-id=\"" . $escaper->escapeHtml($asset['id']) . "\">\n";
        
        // If the customization extra, set custom values
        if($active_fields)
        {
            display_main_detail_asset_fields_td_edit($active_fields, $asset);        
        }
        else
        {
            display_asset_name_td_edit($asset['id'], $asset['name']);
            display_asset_ip_address_td($asset['ip']);
            display_asset_valuation_td_edit($asset['id'], $asset['value']);
            display_asset_site_location_td_edit($asset['id'], $asset['location']);
            display_asset_team_td_edit($asset['id'], $asset['teams']);
            display_asset_details_td_edit($asset['id'], $asset['details']);
            display_asset_tags_td_edit($asset['id'], $asset['tags']);
        }
        echo "<td>" . $escaper->escapeHtml(localized_yes_no($asset['verified'])) . "</td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";
}

/********************************************************
 * FUNCTION: UPDATE ASSET FIELD VALUE OF THE FIELD NAME *
 ********************************************************/
function update_asset_field_value_by_field_name($id, $fieldName, $fieldValue)
{
    switch($fieldName){
        case "name":
            $fieldName = "name";
            $fieldValue = try_encrypt($fieldValue);
        break;
        case "value":
            $fieldName = "value";
        break;
        case "location":
            $fieldName = "location";
            $fieldValue = is_array($fieldValue) ? implode(",", $fieldValue) : $fieldValue;
        break;
        case "team":
            $fieldName = "teams";
            $fieldValue = is_array($fieldValue) ? implode(",", $fieldValue) : $fieldValue;
        break;
        case "details":
            $fieldName = "details";
            $fieldValue = try_encrypt($fieldValue);
        break;
        case "tags":
            $tags = empty($fieldValue) ? [] : $fieldValue;

            foreach($tags as $tag){
                if (strlen($tag) > 255) {
                    global $lang;
                    
                    set_alert(true, "bad", $lang['MaxTagLengthWarning']);
                    return false;
                }
            }

            return updateTagsOfType($id, 'asset', $tags);
        break;
        default:
            return false;
        break;
    }
    
    // Open the database connection
    $db = db_open();

    // Update the asset
    $stmt = $db->prepare("UPDATE assets SET `". $fieldName ."` = :value WHERE id = :id");
    $stmt->bindParam(":value", $fieldValue, PDO::PARAM_STR);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $name = get_name_by_value('assets', $id, "", true);
    $message = "Asset '{$name}' was modified by user '{$_SESSION['user']}'.";
    write_log($id, $_SESSION['uid'], $message, "asset");
    
    // Close the database connection
    db_close($db);

    return true;
}

/**********************************
 * FUNCTION: IMPORT ASSET *
 * team: string splitted by comma
 *********************************/
function import_asset($ip, $name, $value, $location, $teams, $details, $tags, $verified, $mapped_controls=[])
{
    // Trim whitespace from the name, ip, and value
    $name       = trim($name);
    $ip         = trim($ip);
    $value      = trim($value);
    $location   = $location ? trim($location) : "";
    $teams      = $teams ? trim($teams) : "";

    $asset_id   = asset_exists($name);

    if ($asset_id == false)
    {
	    write_debug_log("An asset named \"{$name} was not found so adding a new asset.");

	    return add_asset($ip, $name, $value, $location, $teams, $details, $tags, $verified, [], [], true);
    }

    if (asset_exists_exact($ip, $name, $value, $location, $teams, $details, $verified)
        && areTagsEqual($asset_id, 'asset', $tags) && asset_control_mapping_exact_match($asset_id, $mapped_controls)) {
        //return "noop"; // To notify the caller that no operation was done
        $exact = true;
    } else $exact = false;

    // Open the database connection
    $db = db_open();

    // Don't overwrite the non-encoded values because later we want to use those
    $enc_details = try_encrypt($details);
    $enc_ip = try_encrypt($ip);

    // Update the asset
    $stmt = $db->prepare("UPDATE assets SET ip = :ip, value = :value, location = :location, teams = :teams, details = :details, verified = :verified WHERE id = :asset_id");
    $stmt->bindParam(":ip", $enc_ip, PDO::PARAM_STR);
    $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
    $stmt->bindParam(":location", $location, PDO::PARAM_STR);
    $stmt->bindParam(":teams", $teams, PDO::PARAM_STR);
    $stmt->bindParam(":details", $enc_details, PDO::PARAM_STR);
    $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    if(!is_null($mapped_controls)) save_asset_to_controls($asset_id, $mapped_controls);

    updateTagsOfType($asset_id, 'asset', $tags);

    // Check if we have updated the asset
    if (!$exact) {
        $message = "An asset named \"" . $name . "\" was modified by username \"" . $_SESSION['user'] . "\".";
        write_log($asset_id, $_SESSION['uid'], $message, "asset");

        return $asset_id;
    }

    return $asset_id;
}

/*****************************
 * FUNCTION: ASSET MIN VALUE *
 *****************************/
function asset_min_value()
{
        // Open the database connection
        $db = db_open();

        // Update the asset
        $stmt = $db->prepare("SELECT min_value FROM asset_values WHERE id=1;");
        $stmt->execute();

        // Get the minimum value
        $min_value = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Return the minimum value
    return $min_value[0][0];
}

/*****************************
 * FUNCTION: ASSET MAX VALUE *
 *****************************/
function asset_max_value()
{
        // Open the database connection
        $db = db_open();

        // Update the asset
        $stmt = $db->prepare("SELECT max_value FROM asset_values WHERE id=10;");
        $stmt->execute();

        // Get the max value
        $max_value = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // Return the maximum value
        return $max_value[0][0];
}

/********************************
 * FUNCTION: UPDATE ASSET VALUE *
 ********************************/
function update_asset_value($id, $min_value, $max_value, $valuation_level_name = false)
{
    // Open the database connection
    $db = db_open();

    // Set the value for the level
    $stmt = $db->prepare("
        UPDATE
            `asset_values`
        SET
            `min_value` = :min_value,
            `max_value` = :max_value"
            . ($valuation_level_name !== false ? ",`valuation_level_name` = :valuation_level_name " : "") . "
        WHERE
            id = :id;
    ");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT, 2);
    $stmt->bindParam(":min_value", $min_value, PDO::PARAM_INT, 11);
    $stmt->bindParam(":max_value", $max_value, PDO::PARAM_INT, 11);

    if ($valuation_level_name !== false)
        $stmt->bindParam(":valuation_level_name", $valuation_level_name, PDO::PARAM_STR);

    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Return success
    return true;
}

/*********************************
 * FUNCTION: UPDATE ASSET VALUES *
 *********************************/
function update_asset_values($min_value, $max_value)
{
        // Open the database connection
        $db = db_open();

    // Get the increment
    $increment = round(($max_value - $min_value)/10);

    // Set the value for level 1
    $value = $min_value + $increment;
    update_asset_value(1, $min_value, $value);

    // For each value from 2 to 10
    for ($i=2; $i<=10; $i++)
    {
        // The minimum value is the current value + 1
        $min_value = $value + 1;

        // If this is not level 10
        if ($i != 10)
        {
            // The new value is the current value + the increment
            $value = $value + $increment;
        }
        else $value = $max_value;

        // Set the value for the other levels
        update_asset_value($i, $min_value, $value);
    }

        // Close the database connection
        db_close($db);

    // Return success
    return true;
}

/*******************************************
 * FUNCTION: DISPLAY ASSET VALUATION TABLE *
 *******************************************/
function display_asset_valuation_table() {

    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    echo "
        <table class='my-2' border='0' cellspacing='5' cellpadding='5'>
    ";

    // Display the table header
    echo "
            <thead>
                <tr>
                    <th class='text-center'>{$escaper->escapeHtml($lang['ValueRange'])}</th>
                    <th class='text-center'>{$escaper->escapeHtml($lang['MinimumValue'])}</th>
                    <th class='text-center'>{$escaper->escapeHtml($lang['MaximumValue'])}</th>
                    <th class='text-center'>{$escaper->escapeHtml($lang['ValuationLevelName'])}</th>
                </tr>
            </thead>
            <tbody>
    ";

    // Get the asset values
    $stmt = $db->prepare("SELECT * FROM asset_values;");
    $stmt->execute();
    $values = $stmt->fetchAll();

    // For each asset value
    foreach ($values as $value) {

        // Minimum value for field
        $minimum = (int)$value['id'] - 1;

        echo "
                <tr>
                    <td class='text-center'>{$escaper->escapeHtml($value['id'])}</td>
                    <td class='text-center'>
                        <input id='dollarsign' type='number' min='{$escaper->escapeHtml($minimum)}' name='min_value_{$escaper->escapeHtml($value['id'])}' value='{$escaper->escapeHtml($value['min_value'])}' onFocus='this.oldvalue = this.value;' onChange='javascript:updateMinValue('{$escaper->escapeHtml($value['id'])}');this.oldvalue = this.value;' class='form-control'/>
                    </td>
                    <td class='text-center'>
                        <input id='dollarsign' type='number' min='{$escaper->escapeHtml($minimum)}' name='max_value_{$escaper->escapeHtml($value['id'])}' value='{$escaper->escapeHtml($value['max_value'])}' onFocus='this.oldvalue = this.value;' onChange='javascript:updateMaxValue('{$escaper->escapeHtml($value['id'])}');this.oldvalue = this.value;'  class='form-control'/>
                    </td>
                    <td class='text-center'>
                        <input type='text' name='valuation_level_name_{$escaper->escapeHtml($value['id'])}' value='{$escaper->escapeHtml($value['valuation_level_name'])}'  class='form-control' placeholder='{$escaper->escapeHtml($lang['EnterAValuationLevelName'])}'/>
                    </td>
                </tr>
        ";

    }

    echo "
            </tbody>
        </table>
    ";

    // Close the database connection
    db_close($db);

}

/*********************************************
 * FUNCTION: CREATE ASSET VALUATION DROPDOWN *
 *********************************************/
function create_asset_valuation_dropdown($name, $selected = NULL, $id = NULL, $customHtml="")
{
    global $escaper;

    if(!$id){
        $id = $name;
    }
        // Open the database connection
        $db = db_open();

        // Get the asset values
        $stmt = $db->prepare("SELECT * FROM asset_values;");
        $stmt->execute();
        $values = $stmt->fetchAll();
        
    echo "<select id=\"" . $escaper->escapeHtml($id) . "\" name=\"" . $escaper->escapeHtml($name) . "\" {$customHtml} class=\"form-select\"  >\n";

        // For each asset value
        foreach ($values as $value)
        {
            // If the option is selected
            if ($selected == $value['id'])
            {
                $text = " selected";
            }
            else $text = "";

        echo "  <option value=\"" . $escaper->escapeHtml($value['id']) . "\"" . $text . ">";

        $valuation_level_name = "";
        if (!empty($value['valuation_level_name']))
            $valuation_level_name = " (" . $escaper->escapeHtml($value['valuation_level_name']) . ")";

        if ($value['min_value'] === $value['max_value'])
        {
            echo $escaper->escapeHtml(get_setting("currency")) . $escaper->escapeHtml(number_format($value['min_value'])) . $valuation_level_name;
        }
        else
        {
            echo $escaper->escapeHtml(get_setting("currency")) . $escaper->escapeHtml(number_format($value['min_value'])) . " to " . $escaper->escapeHtml(get_setting("currency")) . $escaper->escapeHtml(number_format($value['max_value'])) . $valuation_level_name;
        }

        echo "</option>\n";
    }

    echo "</select>\n";

    // Close the database connection
    db_close($db);
}

/********************************************
 * FUNCTION: UPDATE DEFAULT ASSET VALUATION *
 ********************************************/
function update_default_asset_valuation($value)
{
        // Open the database connection
        $db = db_open();

        // Update the default asset valuation
        $stmt = $db->prepare("UPDATE `settings` SET value=:value WHERE name='default_asset_valuation'");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
        $stmt->execute();

        // Close the database connection
        db_close($db);

    // Return true
    return true;
}

/*****************************************
 * FUNCTION: GET DEFAULT ASSET VALUATION *
 *****************************************/
function get_default_asset_valuation()
{
    // Open the database connection
    $db = db_open();

    // Update the default asset valuation
    $stmt = $db->prepare("SELECT value FROM `settings` WHERE name='default_asset_valuation'");
    $stmt->execute();

    $value = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the value
    return $value[0][0];
}

/***********************************
 * FUNCTION: GET ASSET ID BY VALUE *
 ***********************************/
function get_asset_id_by_value($value){
    $value = strtolower(str_replace(array('$', ','), '', $value));
    
    $min_max = explode("to", $value);
    $min = intval($min_max[0]);
    $max = isset($min_max[1]) ? intval($min_max[1]) : false;
    if(!isset($GLOBALS['default_asset_valuation'])){
        $GLOBALS['default_asset_valuation'] = get_default_asset_valuation();
    }

    if(!isset($GLOBALS['asset_values'])){
        // Open the database connection
        $db = db_open();

        // Update the default asset valuation
        $stmt = $db->prepare("SELECT * FROM `asset_values` ");
        $stmt->execute();
        // Close the database connection
        db_close($db);

        $GLOBALS['asset_values'] = $stmt->fetchAll();
    }
    
    $id = $GLOBALS['default_asset_valuation'];
    if($max === false){
        // if $value is single dollar.
        foreach($GLOBALS['asset_values'] as $asset_value){
            if($asset_value['min_value'] <= $min && $asset_value['max_value'] >= $min){
                $id = $asset_value['id'];
                break;
            }
        }
    }else{
        foreach($GLOBALS['asset_values'] as $asset_value){
            if($asset_value['min_value'] <= $min && $asset_value['max_value'] >= $max){
                $id = $asset_value['id'];
            }
        }
    }

    return $id;
}

/***********************************
 * FUNCTION: GET ASSET VALUE BY ID *
 ***********************************/
function get_asset_value_by_id($id="", $export=false)
{
    global $escaper;

    if (!empty($GLOBALS['asset_valuations_by_id'])) {
        if (!empty($GLOBALS['asset_valuations_by_id'][$id])) {
            return $GLOBALS['asset_valuations_by_id'][$id];
        }
    } else {
        $GLOBALS['asset_valuations_by_id'] = [];
    }

    if(!isset($GLOBALS['default_asset_valuation'])){
        $GLOBALS['default_asset_valuation'] = get_default_asset_valuation();
    }

    if(!isset($GLOBALS['asset_values'])){
        // Open the database connection
        $db = db_open();

        // Update the default asset valuation
        $stmt = $db->prepare("SELECT * FROM `asset_values` ");
        $stmt->execute();
        // Close the database connection
        db_close($db);

        $GLOBALS['asset_values'] = $stmt->fetchAll();
    }

    $value = "";
    foreach($GLOBALS['asset_values'] as $asset_value){
        if($asset_value['id'] == $id){
            $value = $asset_value;
            break;
        }
    }

    // If a value exists
    if (empty($value))
    {
        $id = $GLOBALS['default_asset_valuation'];

        foreach($GLOBALS['asset_values'] as $asset_value){
            if($asset_value['id'] == $id){
                $value = $asset_value;
                break;
            }
        }
    }

    if(!empty($value)) {
        if ($value['min_value'] === $value['max_value']) {
            $asset_value = get_setting("currency") . number_format($value['min_value']);
        } else {
            $asset_value = get_setting("currency") . number_format($value['min_value']) . " to " . get_setting("currency") . number_format($value['max_value']);
        }

        if (!$export && !empty($value['valuation_level_name'])) {
            $asset_value .= " ({$value['valuation_level_name']})";
        }
    }else{
        $asset_value = "Undefined";
    }

    $GLOBALS['asset_valuations_by_id'][$id] = $asset_value;

    // Return the asset value
    return $asset_value;
}

/***************************************
 * FUNCTION: GET ASSET VALUATION ARRAY *
 ***************************************/
function get_asset_valuation_array()
{
    // Open the database connection
    $db = db_open();

    // Update the default asset valuation
    $stmt = $db->prepare("SELECT * FROM `asset_values`");
    $stmt->execute();

    $asset_valuation_array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the array
    return $asset_valuation_array;
}

/*****************************************
 * FUNCTION: ASSET VALUATION FOR RISK ID *
 *****************************************/
function asset_valuation_for_risk_id($risk_id)
{
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            (SELECT
                COALESCE(sum(`av`.`max_value`), 0)
            FROM
                `risks_to_asset_groups` rtag
                INNER JOIN `assets_asset_groups` aag ON `rtag`.`asset_group_id` = `aag`.`asset_group_id`
                INNER JOIN `assets` a ON `aag`.`asset_id` = `a`.`id`
                INNER JOIN `asset_values` av ON `a`.`value` = `av`.`id`
            WHERE
                `rtag`.`risk_id`=:risk_id
                AND `a`.`id` NOT IN (SELECT `asset_id` FROM `risks_to_assets` WHERE `risk_id` = :risk_id))
            +
            (SELECT
                COALESCE(sum(`av`.`max_value`), 0)
            FROM
                `risks_to_assets` rta
                INNER JOIN `assets` a ON `rta`.`asset_id` = `a`.`id`
                INNER JOIN `asset_values` av ON `a`.`value` = `av`.`id`
            WHERE
                `rta`.`risk_id`=:risk_id)
        FROM dual;
    ");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    $value = $stmt->fetch()[0];

    db_close($db);
    
    return $value;   
}


/*********************************************
 * FUNCTION: ASSET VALUATION FOR ASSET GROUP *
 *********************************************/
function asset_valuation_for_asset_group($asset_group_id)
{
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            SUM(`av`.`max_value`)
        FROM
            `assets_asset_groups` aag
            INNER JOIN `assets` a ON `aag`.`asset_id` = `a`.`id`
            INNER JOIN `asset_values` av ON `a`.`value` = `av`.`id`
        WHERE
            `aag`.`asset_group_id` = :asset_group_id;
    ");
    $stmt->bindParam(":asset_group_id", $asset_group_id, PDO::PARAM_INT);
    $stmt->execute();

    $value = $stmt->fetch()[0];

    db_close($db);
    
    return $value;   
}


/************************************
 * FUNCTION: DISPLAY ADD ASSET FORM *
 ************************************/
function display_add_asset()
{
    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra())
    {
        write_debug_log("Customization extra is enabled");

        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("asset");
        $inactive_fields = get_inactive_fields("asset");

        display_main_detail_asset_fields_add($active_fields);
        display_main_detail_asset_fields_add($inactive_fields);

        echo "
            <script>
                $(function() {
                    $(\"#add-asset-container select[id^='custom_field'].multiselect\").multiselect({buttonWidth: '300px', enableFiltering: true, enableCaseInsensitiveFiltering: true});
                });
            </script>
        ";
    }
    // If the customization extra is disabled, shows fields by default fields
    else
    {
        display_asset_name_edit();

        display_asset_ip_address_edit();

        display_asset_valuation_edit();

        display_asset_site_location_edit();

        display_asset_team_edit();

        display_asset_associated_risks_add();

        display_asset_details_edit();

        display_asset_mapping_controls_edit();

        display_asset_tags_add();
    }
}


/*********************************************************
 * FUNCTION: CREATE ASSET GROUP                          *
 * $name: name of the asset group                        *
 * $selected_assets: The assets associated to the group  * 
 *********************************************************/
function create_asset_group($name, $selected_assets=false) {

    $db = db_open();

    $stmt = $db->prepare("
        INSERT INTO
            `asset_groups` (`name`)
        VALUES
            (:name);"
    );
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->execute();

    $id = $db->lastInsertId();

    db_close($db);

    update_assets_of_asset_group($selected_assets, $id, $name, true);

    return $id;
}

/*********************************************************
 * FUNCTION: UPDATE ASSET GROUP                          *
 * $id: id of the asset group                            *
 * $name: name of the asset group                        *
 * $selected_assets: The assets associated to the group  *
 *********************************************************/
function update_asset_group($id, $name, $selected_assets) {

    $db = db_open();

    $stmt = $db->prepare("
        UPDATE
            `asset_groups`
        SET
            `name`=:name
        WHERE
            `id`=:id;"
    );
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    if(!is_null($selected_assets)) update_assets_of_asset_group($selected_assets, $id, $name);

    db_close($db);
}

/********************************************
 * FUNCTION: DELETE ASSET GROUP             *
 * $asset_group_id: id of the asset group   *
 ********************************************/
function delete_asset_group($asset_group_id) {

    $name = get_name_by_value('asset_groups', $asset_group_id, false, true);

    if (!$name)
        return false;

    $db = db_open();

    // Delete the group
    $stmt = $db->prepare("
        DELETE FROM
            `asset_groups`
        WHERE
            `id`=:id;"
    );
    $stmt->bindParam(":id", $asset_group_id, PDO::PARAM_INT);
    $stmt->execute();

    db_close($db);

    // Delete leftover junction entries
    cleanup_after_delete('asset_groups');
    
    $message = _lang('AssetGroupDeleteAuditLog', array(
            'user' => $_SESSION['user'],
            'group_name' => $name,
            'id' => $asset_group_id
        ), false
    );

    write_log($asset_group_id + 1000, $_SESSION['uid'], $message, 'asset_group');

    return true;
}


/************************************************
 * FUNCTION: REMOVE ASSET FROM ASSET GROUP      *
 * $asset_id: id of the asset we want to remove *
 *            from the asset group              *
 * $asset_group_id: id of the asset group       *
 ************************************************/
function remove_asset_from_asset_group($asset_id, $asset_group_id) {

    $asset_group_name = get_name_by_value('asset_groups', $asset_group_id, false, true);
    $asset_name = get_name_by_value('assets', $asset_id, false, true);

    if (!$asset_group_name || !$asset_name)
        return false;

    $db = db_open();

    // Remove asset from the group
    $stmt = $db->prepare("
        DELETE FROM
            `assets_asset_groups`
        WHERE
            `asset_id`=:asset_id AND
            `asset_group_id`=:asset_group_id;"
    );
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->bindParam(":asset_group_id", $asset_group_id, PDO::PARAM_INT);
    $stmt->execute();

    db_close($db);

    $message = _lang('AssetGroupRemoveAssetAuditLog', array(
            'user' => $_SESSION['user'],
            'asset_name' => $asset_name,
            'asset_id' => $asset_id,
            'group_name' => $asset_group_name,
            'group_id' => $asset_group_id
        ), false
    );

    write_log($asset_group_id + 1000, $_SESSION['uid'], $message, 'asset_group');

    return true;
}

/*****************************
 * FUNCTION: GET ASSET GROUP *
 *****************************/
function get_asset_group($asset_group_id) {

    $db = db_open();

    if (encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }

    $stmt = $db->prepare("
        SELECT
            *
        FROM
            `asset_groups`
        WHERE
            `id` = :asset_group_id;
    ");
    $stmt->bindParam(":asset_group_id", $asset_group_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($result))
        return [];

    $result = $result[0];

    // Load assets currently assigned to the group
    $stmt = $db->prepare("
        SELECT
            `a`.id, `a`.`name`
        FROM
            `assets` a
            INNER JOIN `assets_asset_groups` aag ON `aag`.`asset_id` = `a`.`id` AND `aag`.`asset_group_id` = :asset_group_id
        ORDER BY
            " . (encryption_extra() ? "a.order_by_name" : "a.name") . ";
    ");
    $stmt->bindParam(":asset_group_id", $asset_group_id, PDO::PARAM_INT);
    $stmt->execute();
    $result['selected_assets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT
            `a`.id, `a`.`name`
        FROM
            `assets` a
            LEFT OUTER JOIN `assets_asset_groups` aag ON `aag`.`asset_id` = `a`.`id` AND `aag`.`asset_group_id` = :asset_group_id
        WHERE
            `aag`.`asset_id` IS NULL
        ORDER BY
            " . (encryption_extra() ? "a.order_by_name" : "a.name") . ";
    ");
    $stmt->bindParam(":asset_group_id", $asset_group_id, PDO::PARAM_INT);
    $stmt->execute();
    $result['available_assets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    foreach($result['selected_assets'] as &$asset)
        $asset['name'] = try_decrypt($asset['name']);
    
    foreach($result['available_assets'] as &$asset)
        $asset['name'] = try_decrypt($asset['name']);
    
    return $result;
}


/***************************************
 * FUNCTION: GET ASSETS OF ASSET GROUP *
 ***************************************/
function get_assets_of_asset_group($asset_group_id) {

    $db = db_open();

    if (encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }

    // Load assets currently assigned to the group
    $stmt = $db->prepare("
        SELECT
            `a`.*
        FROM
            `assets` a
            INNER JOIN `assets_asset_groups` aag ON `aag`.`asset_id` = `a`.`id`
        WHERE
            `aag`.`asset_group_id` = :asset_group_id
        ORDER BY
            " . (encryption_extra() ? "a.order_by_name" : "a.name") . ";
    ");
    $stmt->bindParam(":asset_group_id", $asset_group_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);
    
    foreach($result as &$asset) {
        $asset['name'] = try_decrypt($asset['name']);
        $asset['ip'] = try_decrypt($asset['ip']);
        $asset['details'] = try_decrypt($asset['details']);
    }

    return $result;
}

/*****************************************************************
 * FUNCTION: UPDATE ASSETS OF ASSET GROUP                        *
 * $assets: id list of the assets that are in the group          *
 * $asset_group_id: id of the asset group                        *
 * $asset_group_name: name of the asset group(for the audit log) *
 * $create: info on whether it was called on create or update.   *
 * If called for create it won't check what changed, won't       *
 * try to disassociate removed assets, just adds the new ones.   *
 *****************************************************************/
function update_assets_of_asset_group($assets, $asset_group_id, $asset_group_name, $create=false) {

    $db = db_open();

    if (!$create) {
        //Get the current assets
        $assets_current = array_column(get_assets_of_asset_group($asset_group_id), 'id');

        // Clever usage of array_diffs to calculate what assets are removed from the group
        // and what assets are added
        $assets_to_remove = array_diff($assets_current, $assets);
        $assets_to_add = array_diff($assets, $assets_current);

        // If there're assets to remove
        if ($assets_to_remove) {

            //building an array of parameters to bind
            $params = array(":asset_group_id" => $asset_group_id);

            // building the list of strings to be used in the `in` part of the sql
            // to be able to bind the params
            // We need this to be able to delete all the connections to the removed
            // assets in one go, instead of using a loop
            $assets_to_remove_in = [];
            foreach ($assets_to_remove as $i => $asset_id)
            {
                $key = ":id".$i;
                $assets_to_remove_in[] = $key;
                $params[$key] = $asset_id;
            }

            // making the comma separated list to be included in the sql
            $assets_to_remove_in = implode(", ", $assets_to_remove_in);

            // Remove the entries from the junction table that connected the deleted assets to the group
            $stmt = $db->prepare("
                delete
                    `aag`
                from
                    `assets` a
                    INNER JOIN `assets_asset_groups` aag ON `aag`.`asset_id` = `a`.`id`
                where
                    `aag`.`asset_group_id` = :asset_group_id and
                    `a`.`id` in ({$assets_to_remove_in});
            ");
            $stmt->execute($params);
        }
    } else {
        $assets_to_add = $assets;
    }

    //If there're assets to add
    if ($assets_to_add) {
        //building an array of parameters to bind
        $params = array(":asset_group_id" => $asset_group_id);

        // building the list of strings to be used in the `in` part of the sql
        // to be able to bind the params
        // We need this to be able to delete all the connections to the removed
        // assets in one go, instead of using a loop
        $assets_to_add_values = [];
        foreach ($assets_to_add as $i => $asset_id)
        {
            $key = ":id".$i;
            $assets_to_add_values[] = "({$key}, :asset_group_id)";
            $params[$key] = $asset_id;
        }

        // making the comma separated list to be included in the sql
        $assets_to_add_values = implode(", ", $assets_to_add_values);

        // Remove the entries from the junction table that connected the deleted assets to the group
        $stmt = $db->prepare("
            INSERT INTO
                `assets_asset_groups` (`asset_id`, `asset_group_id`)
            VALUES
                {$assets_to_add_values};
        ");
        $stmt->execute($params);
    }

    db_close($db);

    // No audit logging is needed if nothing changed
    if ($create || $assets_to_add || $assets_to_remove) {
        global $lang;

        $asset_changes = [];

        $assets = get_names_by_values('assets', $assets, false, false, true);
        $assets_to_add = get_names_by_values('assets', $assets_to_add, false, false, true);

        if ($assets_to_add)
            $asset_changes[] = _lang('AssetGroupUpdateAuditLogAdded', array('assets_added' => $assets_to_add), false);

        if (!$create) {
            $assets_to_remove = get_names_by_values('assets', $assets_to_remove, false, false, true);
            if ($assets_to_remove)
                $asset_changes[] = _lang('AssetGroupUpdateAuditLogRemoved', array('assets_removed' => $assets_to_remove), false);

            $assets_current = get_names_by_values('assets', $assets_current, false, false, true);

            $message = _lang('AssetGroupUpdateAuditLog', array(
                    'user' => $_SESSION['user'],
                    'group_name' => $asset_group_name,
                    'id' => $asset_group_id,
                    'assets_from' => $assets_current,
                    'assets_to' => $assets,
                    'asset_changes' => implode(", ", $asset_changes)
                ), false
            );
        } else {
            $message = _lang('AssetGroupCreateAuditLog', array(
                    'user' => $_SESSION['user'],
                    'group_name' => $asset_group_name,
                    'id' => $asset_group_id,
                    'assets_to' => $assets
                ), false
            );
        }

        write_log($asset_group_id + 1000, $_SESSION['uid'], $message, 'asset_group');
    }
}

/************************************************************************************************************
 * FUNCTION: PROCESS SELECTED ASSETS ASSET GROUPS OF TYPE                                                   *
 * Processing the data coming from the widget used for selecting assets and asset groups.                   *
 * $item_id: Id of the item we want to associate the assets and asset groups with                           *
 * $assets_and_groups: data from the widget. Can contain asset/asset group ids or names of new assets       *
 * $type: The type of the item the assets/asset groups are being assigned to                                *
 * Currently supported types: risk, assessment_answer, questionnaire_answer, questionnaire_risk, incident   *
 *                                                                                                          *
 * There's also the ability to enforce team separation logic for the assets in a way that prevents          *
 * accidental removal of assets in case the submitting user had no permission editing an asset that is      *
 * assigned to the item, but wasn't displayed(because the user has no permission to the asset) thus wasn't  *
 * sent to the server as a selected asset.                                                                  * 
 ************************************************************************************************************/
function process_selected_assets_asset_groups_of_type($item_id, $assets_and_groups, $type) {

    $db = db_open();

    // The logic is about items that have assets with teams assigned when team separation is enabled.
    // It can happen that a user opens an item to edit but don't have access to all the assets assigned to it.
    // In this case the user can edit the assets that they have access to, but leave the others intact(they're not even displayed)
    $team_separation_asset_saving_logic = false;

    // make the junction config that's maintained in the functions.php available here
    global $junction_config;
    
    // set the names of the junction tables and fields required for the query for the type provided
    switch($type) {
        case 'risk':
            if (!check_permission("riskmanagement")) {
                return;
            }
            $junction_config_type = 'risks';
            $assets_junction_name = 'risks_to_assets';
            $asset_groups_junction_name = 'risks_to_asset_groups';
            $forced_asset_verification_state = null;
            $team_separation_asset_saving_logic = true;
            break;
        case 'assessment_answer':
            if (!check_permission("assessments")) {
                return;
            }
            $junction_config_type = 'assessment_answers';
            $assets_junction_name = 'assessment_answers_to_assets';
            $asset_groups_junction_name = 'assessment_answers_to_asset_groups';
            $forced_asset_verification_state = true;
            break;
        case 'questionnaire_answer':
            if(!assessments_extra() || !check_permission("assessments") || !table_exists("questionnaire_answers_to_assets") || !table_exists("questionnaire_answers_to_asset_groups")) {
                return;
            }
            $junction_config_type = 'questionnaire_answers';
            $assets_junction_name = 'questionnaire_answers_to_assets';
            $asset_groups_junction_name = 'questionnaire_answers_to_asset_groups';
            $forced_asset_verification_state = true;
            break;
        case 'questionnaire_risk':
            if(!assessments_extra() || !check_permission("assessments")) {
                return;
            }
            $junction_config_type = 'questionnaire_risk_details';
            $assets_junction_name = 'questionnaire_risk_to_assets';
            $asset_groups_junction_name = 'questionnaire_risk_to_asset_groups';
            $forced_asset_verification_state = true;
            break;
        case 'incident':
            if(!incident_management_extra() || !check_permission("im_incidents")) {
                return;
            }
            $junction_config_type = 'incident_management_incidents';
            $assets_junction_name = 'incident_management_incident_to_assets';
            $asset_groups_junction_name = 'incident_management_incident_to_asset_groups';
            $forced_asset_verification_state = null;
            $team_separation_asset_saving_logic = true;
            break;
        default:
            return;
    }
    
    // Use the data setup in the junction configuration
    $asset_junction_item_id_name = $junction_config[$junction_config_type]['junctions'][$assets_junction_name];
    $asset_group_junction_item_id_name = $junction_config[$junction_config_type]['junctions'][$asset_groups_junction_name];
    $asset_junction_asset_id_name = $junction_config['assets']['junctions'][$assets_junction_name];
    $asset_group_junction_asset_group_id_name = $junction_config['asset_groups']['junctions'][$asset_groups_junction_name];

    // Save whether the separation extra is enabled so don't have to query the database all the time
    $separation = team_separation_extra();
    
    // Using the team separation saving logic only makes sense if the team separation extra is activated and the user isn't an admin
    $team_separation_asset_saving_logic &= $separation && !is_admin();


    $stmt = $db->prepare("DELETE FROM `{$asset_groups_junction_name}` WHERE {$asset_group_junction_item_id_name} = :item_id");
    $stmt->bindParam(":item_id", $item_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $assets = [];
    $new_assets = [];
    // For each asset or group
    foreach ($assets_and_groups as $value)
    {
        // Trim whitespaces
        $value = trim($value);
        
        // Selected an existing asset or group
        if (preg_match('/^([\d]+)_(group|asset)$/', $value, $matches)) {
            list(, $aag_id, $aag_type) = $matches;
        } elseif (preg_match('/^new_asset_(.*)$/', $value, $matches)) { // Entered the name of a new asset
            $name = trim($matches[1]);
            // Check if the asset already exists, but not verified(since it didnt show up in the widget)
            $aag_id = asset_exists($name);
            
            if ($aag_id) {
                set_alert(true, "bad", _lang('ErrorAssetAlreadyExistsAsVerified', array('asset_name' => $name)));
                continue;
            }
            
            if ($forced_asset_verification_state === null) {
                $aag_id = add_asset('', $name);
            } else {
                $aag_id = add_asset_by_name_with_forced_verification($name, $forced_asset_verification_state);
            }
            $aag_type = 'asset';
            $new_assets []= $aag_id;
        } else {
            //Invalid input
            continue;
        }
        
        if ($aag_type == 'asset') {
            // If it's an asset we're storing it for later to apply the team separation asset saving logic if needed
            $assets []= $aag_id;
        } elseif ($aag_type == 'group') {
            // Add the new group for this type
            $stmt = $db->prepare("INSERT INTO `$asset_groups_junction_name` (`$asset_group_junction_item_id_name`, `$asset_group_junction_asset_group_id_name`) VALUES (:type_id, :asset_group_id)");
            $stmt->bindParam(":asset_group_id", $aag_id, PDO::PARAM_INT);
            $stmt->bindParam(":type_id", $item_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
       
    // So the Issue we're solving below is that
    //     a,  there's no validation on whether the user updating the incident has access to the assets sent over to the server
    //         to be associated to the item(can be exploited by sending over IDs that the user have no permission to)
    //     b,  when a user who doesn't have permission to all the assets associated with the item edits the item
    //         can accidentally remove those assets because the logic just saves those that are sent over. To solve this
    //         we're adding back the assets the user has no permission to edit
    // If team separation is enabled
    if ($team_separation_asset_saving_logic) {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        
        $user_id = (int)$_SESSION['uid'];

        // If there're assets and we have to check if the user has access to them
        if (!empty($assets)) {
            // Sanitize the assets(remove assets sent from the client side that are not accessible by the user)
            // This logic would wrongly exclude the assets created through the widget if the 'Allow all users to see assets not assigned to a team' option isn't enabled
            // since they won't have a team assigned, so we're adding back the ids of the newly created assets
            // It's still needed to filter out attempts of adding pre-existing assets that the user has no permission to
            $stmt = $db->prepare("
                SELECT
                	`a`.`id`
                FROM `assets` a
                	LEFT JOIN `user_to_team` u2t ON FIND_IN_SET(`u2t`.`team_id`, `a`.`teams`)
                WHERE
                	(`u2t`.`user_id` = :user_id" . (get_setting('allow_all_to_asset_noassign_team') ? " OR `a`.`teams` = ''" : '') . ")
                    AND `a`.`id` IN (" . implode(',', $assets) . ")
                GROUP BY
                	`a`.`id`;
            ");
            
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $assets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Get all the asset ids that are associated to the item
        $stmt = $db->prepare("SELECT `asset_id` FROM `{$assets_junction_name}` WHERE `{$asset_junction_item_id_name}` = :item_id;");
        $stmt->bindParam(":item_id", $item_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Store the list of associated risk ids in the array
        $all_associated_assets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If there were associated assets in the first place
        if (!empty($all_associated_assets)) {
            // then get the assets the user actually had access to modify
            $stmt = $db->prepare("
                SELECT
                    `a`.`id`
                FROM `{$assets_junction_name}` aj
                    INNER JOIN `assets` a ON `aj`.`asset_id` = `a`.`id`
                WHERE
                    `aj`.`{$asset_junction_item_id_name}` = :item_id
                    AND " . get_user_teams_query_for_assets('a', false) . "
                GROUP BY
                    `a`.`id`
            ");
            $stmt->bindParam(":item_id", $item_id, PDO::PARAM_INT);
            $stmt->execute();
            $accessable_assets = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // If the user had access to any of the assets then to get the assets he/she doesn't have access to
            // we need the diff of the two lists
            if (!empty($accessable_assets)) {
                $not_accessable_assets = array_diff($all_associated_assets, $accessable_assets);
            } else {
                // But if the user doesn't have access to any of the already associated assets then it means
                // we have to add back all of them
                $not_accessable_assets = $all_associated_assets;
            }
            
            // Add the assets that weren't editable by the user back before saving
            $assets = array_merge($assets, $not_accessable_assets);
        }
    }

    // We add back the new assets to make sure they get assigned even though due to team separation settings
    // it may be possible that the user won't see the assets they just created(if the 'Allow all users to see assets not assigned to a team' option isn't enabled)
    $assets = array_unique(array_merge($assets, $new_assets));

    // Clear any current asset associations for this type
    $stmt = $db->prepare("DELETE FROM `{$assets_junction_name}` WHERE {$asset_junction_item_id_name} = :item_id");
    $stmt->bindParam(":item_id", $item_id, PDO::PARAM_INT);
    $stmt->execute();

    // re-create the asset associations for the item
    foreach ($assets as $asset_id) {
        $stmt = $db->prepare("INSERT INTO `$assets_junction_name` (`$asset_junction_item_id_name`, `$asset_junction_asset_id_name`) VALUES (:item_id, :asset_id)");
        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
        $stmt->bindParam(":item_id", $item_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);    
}

/***************************************************
 * FUNCTION: IMPORT SELECTED ASSETS ASSET GROUPS   *
 * Processing the data coming from the import      *
 * $type_id: Id of the risk                        *
 * $asset_and_group_names: data from the import.   *
 * Can contain asset/asset group names.            *
 * Group names are marked by being wrapped in      *
 * square brackets. For example: [group name 1]    *
 * $type: The type of the association              *
 ***************************************************/
function import_assets_asset_groups_for_type($type_id, $asset_and_group_names, $type) {

    // Open the database connection
    $db = db_open();

    switch($type) {
        case 'risk':
            $assets_junction_name = 'risks_to_assets';
            $asset_groups_junction_name = 'risks_to_asset_groups';
            $junction_id_name = 'risk_id';
            $forced_asset_verification_state = null;
        break;
        case 'questionnaire_answer':
            if(!assessments_extra() || !assessments_extra("questionnaire_answers_to_assets") || !assessments_extra("questionnaire_answers_to_asset_groups"))
            {
                return;
            }
            $assets_junction_name = 'questionnaire_answers_to_assets';
            $asset_groups_junction_name = 'questionnaire_answers_to_asset_groups';
            $junction_id_name = 'questionnaire_answer_id';
            $forced_asset_verification_state = true;
        break;
        
        default:
            return;
    }

    // Clear any current assets for this type
    $stmt = $db->prepare("DELETE FROM `$assets_junction_name` WHERE $junction_id_name = :$junction_id_name");
    $stmt->bindParam(":$junction_id_name", $type_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $db->prepare("DELETE FROM `$asset_groups_junction_name` WHERE $junction_id_name = :$junction_id_name");
    $stmt->bindParam(":$junction_id_name", $type_id, PDO::PARAM_INT);
    $stmt->execute();    

    // For each asset or group
    foreach (array_unique(explode(',', $asset_and_group_names)) as $name)
    {
        // Trim whitespaces
        $name = trim($name);

        if (preg_match('/^\[(.+)\]$/', $name, $matches)) {
            $name = trim($matches[1]);
            $type = 'group';
        } else $type = 'asset';

        if ($type=='asset') {
            $id = asset_exists($name);

            if (!$id)
                $id = add_asset('', $name);

        } elseif ($type=='group') {
            $id = get_value_by_name('asset_groups', $name);

            if (!$id)
                $id = create_asset_group($name);
        }

        if ($type=='asset') {
            // Add the new asset for this type
            $stmt = $db->prepare("INSERT INTO `$assets_junction_name` (`$junction_id_name`, `asset_id`) VALUES (:$junction_id_name, :asset_id)");
            $stmt->bindParam(":asset_id", $id, PDO::PARAM_INT);
        } elseif ($type=='group') {
            // Add the new group for this type
            $stmt = $db->prepare("INSERT INTO `$asset_groups_junction_name` (`$junction_id_name`, `asset_group_id`) VALUES (:$junction_id_name, :asset_group_id)");
            $stmt->bindParam(":asset_group_id", $id, PDO::PARAM_INT);
        }

        $stmt->bindParam(":$junction_id_name", $type_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);    
}

function get_asset_groups_table() {

    global $escaper;

    echo "<table id='asset-groups-table' class='easyui-treegrid asset-groups-table'
            data-options=\"
                iconCls: 'icon-ok',
                animate: false,
                fitColumns: true,
                nowrap: true,
                pagination: true,
                pageSize: 10,
                pageList: [5,10,20,100],
                url: '{$_SESSION['base_url']}/api/asset-group/tree',
                method: 'GET',
                idField: 'id',
                treeField: 'name',
                scrollbarSize: 0,
                loadFilter: function(data, parentId) {
                    return data.data;
                },
                onLoadSuccess: function(row, data){
                    //fixTreeGridCollapsableColumn();
                    //It's there to be able to have it collapsed on load
                    /*var tree = $('#asset-groups-table');
                    tree.treegrid('collapseAll');
                    tree.treegrid('options').animate = true;*/
                    if (data && data.total)
                        $('#asset-groups-count').text(data.total);
                }
            \">";
    echo "<thead>";
    
        // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra()) {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("asset");

        display_main_detail_asset_fields_treegrid_th($active_fields);
    }
    // If the customization extra is disabled, Show default main fields
    else {
        display_asset_name_treegrid_th();
        display_asset_ip_address_treegrid_th();
        display_asset_valuation_treegrid_th();
        display_asset_site_location_treegrid_th();
        display_asset_team_treegrid_th();
        display_asset_details_treegrid_th();
        display_asset_tags_treegrid_th();
        display_asset_actions_treegrid_th();
    }
    
    echo "</thead>\n";

    echo "</table>";
}

function get_asset_groups_for_treegrid($offset, $rows) {

    global $lang, $escaper;

    $result = [];

    $db = db_open();

    $stmt = $db->prepare("select count(*) from `asset_groups`;");
    $stmt->execute();
    $result["total"] = $stmt->fetch()[0];

    $stmt = $db->prepare("
        SELECT
            `ag`.*,
            IF(`aag`.`asset_group_id` IS NULL, 'open', 'closed') as state
        FROM
            `asset_groups` ag
            LEFT OUTER JOIN `assets_asset_groups` aag ON `aag`.`asset_group_id` = `ag`.`id`
        GROUP BY
            `ag`.`id`
        ORDER BY
            `ag`.`name`
        LIMIT $offset,$rows;");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $update_tooltip = $escaper->escapeHtml($lang['UpdateAssetGroupTooltip']);
    $delete_tooltip = $escaper->escapeHtml($lang['DeleteAssetGroupTooltip']);

    foreach($groups as &$group) {
        $group['name'] = $escaper->escapeHtml($group['name']);
        $group['actions'] = "
            <div class='actions-cell d-flex justify-content-center align-items-center w-100'>
                <a title='{$update_tooltip}' class='asset-group--update' data-id='{$group['id']}'><i class='fa fa-edit'></i></a>
                <a title='{$delete_tooltip}' class='asset-group--delete' data-id='{$group['id']}'><i class='fa fa-trash'></i></a>
            </div>";
    }

    $result["rows"] = $groups;

    db_close($db);

    return $result;
}

function get_assets_of_asset_group_for_treegrid($id){

    global $lang, $escaper;

    $result = [];

    if (encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }

    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            a.*,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR ', ') as tags
        FROM
            `assets` a
            INNER JOIN assets_asset_groups aag ON aag.asset_id = a.id and aag.asset_group_id = $id
            LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id AND tt.type = 'asset'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        GROUP BY
            a.id
        ORDER BY
            " . (encryption_extra() ? "a.order_by_name" : "a.name") . ";
    ");

    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $remove_tooltip = $escaper->escapeHtml($lang['RemoveAssetTooltip']);

    // If the customization extra, set custom values
    $customization_enabled = customization_extra();
    if ($customization_enabled)
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        $active_fields = get_active_fields("asset");
    }

    foreach($assets as $asset) {
        $asset['state'] = 'open';
        $asset['parent'] = $id;

        $asset['name'] = $escaper->escapeHtml(try_decrypt($asset['name']));

        // the second parameter of preg_match() should be of type STRING. So if $asset['ip'] is NULL or UNDEFINED, then it should be converted into EMPTY STRING.
        $asset['ip'] = try_decrypt($asset['ip']) ?? '';
        
        if (!preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $asset['ip']))
        {
            $asset['ip'] = "N/A";
        }

        $asset['location'] = $asset['location'] ? $escaper->escapeHtml(get_name_by_value("location", $asset['location'])) : "N/A";
        $asset['teams'] = $asset['teams'] ? $escaper->escapeHtml(get_name_by_value("team", $asset['teams'])) : "N/A";
        $asset['value'] = $escaper->escapeHtml(get_asset_value_by_id($asset['value']));

        $asset['details'] = $escaper->escapeHtml(try_decrypt($asset['details']));
        $asset['tags'] = $escaper->escapeHtml($asset['tags']);

        $asset['actions'] = "
            <div class='actions-cell d-flex justify-content-center align-items-center w-100'>
                <a title='{$remove_tooltip}' class='asset--remove' data-asset-id='{$asset['id']}' data-asset-group-id='{$id}'><i class='fa fa-times'></i></a>
            </div>";

        $asset['id'] = $asset['id'] . '-' . $id;

        // If customization extra is enabled
        if($customization_enabled)
        {
            $custom_values = get_custom_value_by_row_id($asset['id'], "asset");
            
            foreach($active_fields as $field)
            {
                if($field['is_basic'] !== 1)
                {
                    $value = "";
                    // Get value of custom field
                    foreach($custom_values as $custom_value)
                    {
                        if($custom_value['field_id'] == $field['id']){
                            $value = $custom_value['value'];
                            break;
                        }
                    }

                    if ($value) {
                        $asset[$field['id']] = get_custom_field_name_by_value($field['id'], $field['type'], $field['encryption'], $value);
                    }
                }
            }
        }

        $result[] = $asset;
    }

    db_close($db);

    return $result;
}

/**
 * Getting the list of verified assets and asset groups. If the id and type are provided then it sets the selected field to true for assets/asset groups that are selected.
 * You either specify NONE of the id or type to simply get the available assets and asset groups or specify BOTH to get the selected field populated.
 * 
 * @param string $type the type of the id
 * @param int $id the id of the item the function should return the selected state of the assets for
 * @param bool $selected_only whether we want the function to return only the selected assets(not verified assets will be returned too if they're selected)
 * @return array The list of all verified assets and asset groups. If the id and type are provided then sets the selected state for those that are selected for that item
 */
function get_assets_and_asset_groups_of_type($id = null, $type = null, $selected_only = false) {

    // Having this variable here so the code is easier to read later
    $has_id = $id !== null;
    
    // If the function got an id as a parameter then the type is required too
    if ($has_id && $type === null) {
        return [];
    }

    // If no type and id provided then we don't need this setup step
    if ($type !== null) {
        
        if (!in_array($type, ['risk', 'assessment_answer', 'questionnaire_answer', 'questionnaire_risk', 'incident'])) {
            return [];
        }
        
        global $junction_config;
        // set the names of the junction fields and fields required for the query for the type provided
        switch($type) {
            case 'risk':
                if (!check_permission("riskmanagement")) {
                    return [];
                }
                $id = $has_id ? $id - 1000 : null;
                $junction_config_type = 'risks';
                $assets_junction_name = 'risks_to_assets';
                $asset_groups_junction_name = 'risks_to_asset_groups';
                break;
            case 'assessment_answer':
                if (!check_permission("assessments")) {
                    return [];
                }
                $junction_config_type = 'assessment_answers';
                $assets_junction_name = 'assessment_answers_to_assets';
                $asset_groups_junction_name = 'assessment_answers_to_asset_groups';
                break;
            case 'questionnaire_answer':
                if(!assessments_extra() || !check_permission("assessments") || !table_exists("questionnaire_answers_to_assets") || !table_exists("questionnaire_answers_to_asset_groups")) {
                    return [];
                }
                $junction_config_type = 'questionnaire_answers';
                $assets_junction_name = 'questionnaire_answers_to_assets';
                $asset_groups_junction_name = 'questionnaire_answers_to_asset_groups';
                break;
            case 'questionnaire_risk':
                if(!assessments_extra() || !check_permission("assessments")) {
                    return [];
                }
                $junction_config_type = 'questionnaire_risk_details';
                $assets_junction_name = 'questionnaire_risk_to_assets';
                $asset_groups_junction_name = 'questionnaire_risk_to_asset_groups';
                break;
            case 'incident':
                if(!incident_management_extra() || !check_permission("im_incidents")) {
                    return [];
                }
                $junction_config_type = 'incident_management_incidents';
                $assets_junction_name = 'incident_management_incident_to_assets';
                $asset_groups_junction_name = 'incident_management_incident_to_asset_groups';
                break;
        }

        // Use the data setup in the 
        $asset_junction_id_name = $junction_config[$junction_config_type]['junctions'][$assets_junction_name];
        $asset_group_junction_id_name = $junction_config[$junction_config_type]['junctions'][$asset_groups_junction_name];
    }

    $db = db_open();
    $encryption = encryption_extra();

    if ($encryption) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }

    if (team_separation_extra() && isset($_SESSION['uid'])) {
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $team_based_separation_where_condition = " AND " . get_user_teams_query_for_assets('', false);
    } else {
        $team_based_separation_where_condition = '';
    }

    // If only have to return the selected items then an inner join is needed
    $join_type = $selected_only ? 'INNER' : 'LEFT OUTER';

    // So the things that are going on in this query:
    // If encryption is enabled the order is dictated by the order_by_name field instead of the name
    // If an id and type are provided then have to flag assets that are asigned to that item as selected
    // If team separation is enabled then have to add the query part for that
    $stmt = $db->prepare("
        SELECT
            *
        FROM (
            SELECT
                `a`.`id`,
                `a`.`name`,
                'asset' AS class,
                " . ($encryption ? "`a`.`order_by_name`" : "`a`.`name`") . " AS ordr,
                " . ($has_id ? "`aj`.`asset_id` IS NOT NULL" : '0') . " AS selected,
                `a`.`verified`
            FROM
                `assets` a
                " . ($has_id ? "{$join_type} JOIN `$assets_junction_name` aj ON `aj`.`asset_id` = `a`.`id` AND `aj`.`$asset_junction_id_name` = :id" : '') . "
            WHERE
                (`a`.`verified` = 1" . ($has_id ? " OR `aj`.`asset_id` IS NOT NULL" : '') . "){$team_based_separation_where_condition}
        UNION ALL
            SELECT
                `ag`.`id`,
                `ag`.`name`,
                'group' AS class,
                " . ($encryption ? "@rownum := @rownum + 1" : "`ag`.`name`") . " AS ordr,
                " . ($has_id ? "`agj`.`asset_group_id` IS NOT NULL" : '0') . " AS selected,
                '1' AS verified
            FROM
                `asset_groups` ag
                " . ($has_id ? "{$join_type} JOIN `$asset_groups_junction_name` agj ON `agj`.`asset_group_id` = `ag`.`id` AND `agj`.`$asset_group_junction_id_name` = :id" : '') . "
                " . ($encryption ? "JOIN (SELECT @rownum := 0) rn" : "") . "
        ) u
        GROUP BY
            `u`.`class`, `u`.`id`
        ORDER BY
            `u`.`class` ASC, `u`.`ordr` ASC;
    ");

    if ($has_id) {
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    if ($encryption) {
        foreach ($data as &$item) {
            if ($item['class'] === 'asset') {
                $item['name'] = try_decrypt($item['name']);
            }
        }
    }

    return $data;

}

function get_assets_and_asset_groups_by_control_for_dropdown($control_id = false, $control_maturity = false) {

    $db = db_open();

    if (encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }
    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $team_based_separation_where_condition = " AND " . get_user_teams_query_for_assets('', false);
    } else {
        $team_based_separation_where_condition = '';
    }

    $sql = "
        SELECT
            *
        FROM (
            SELECT
                `a`.`id`,
                `a`.`name`,
                'asset' as class,
                " . (encryption_extra() ? "`a`.`order_by_name`" : "`a`.`name`") . " as ordr" .
    ($control_id ? ",`cta`.`asset_id` IS NOT NULL as selected" : "") . "
            FROM
                `assets` a " .
    ($control_id ? "LEFT OUTER JOIN `control_to_assets` cta ON `cta`.`asset_id` = `a`.`id` and `cta`.`control_id` = :control_id" . ($control_maturity !== false ? " and cta.control_maturity = :control_maturity " : "") : "") . "
            WHERE
                `a`.`verified` = 1" . ($control_id ? " or `cta`.`asset_id` IS NOT NULL" : "") . " {$team_based_separation_where_condition}
        UNION ALL
            SELECT
                `ag`.`id`,
                `ag`.`name`,
                'group' as class,
                " . (encryption_extra() ? "@rownum := @rownum + 1" : "`ag`.`name`") . " as ordr" .
    ($control_id ? ",`ctag`.`asset_group_id` IS NOT NULL as selected" : "") . "
            FROM
                `asset_groups` ag " .
    ($control_id ? "LEFT OUTER JOIN `control_to_asset_groups` ctag ON `ctag`.`asset_group_id` = `ag`.`id` and `ctag`.`control_id` = :control_id " . ($control_maturity !== false ? " and ctag.control_maturity = :control_maturity " : "") : "") . 
    (encryption_extra() ? "JOIN (SELECT @rownum := 0) rn" : "") . "
        ) u
        ORDER BY
            `u`.`class`, `u`.`ordr`
        ;
    ";
    /*
        We have to play with the values in the ordr column as the type have to match so it works properly when ordering.
        If some are int and some are string, even the ints will be sorted as strings.
    
    */

    $stmt = $db->prepare($sql);

    if ($control_id)
        $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    if ($control_maturity !== false)
        $stmt->bindParam(":control_maturity", $control_maturity, PDO::PARAM_INT);

    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    foreach($data as &$item)
        if ($item['class'] === 'asset')
            $item['name'] = try_decrypt($item['name']);

    return $data;
}

function get_assets_and_asset_groups_of_type_as_string($id, $type) {

    $data = get_assets_and_asset_groups_of_type($id, $type, true);
    if ($data) {
        $affected_assets = [];
        foreach($data as $item) {
            $affected_assets[] = $item['class'] === 'asset' ? $item['name'] : "[{$item['name']}]";
        }
        return implode(',', $affected_assets);
    }

    return "";
}

function get_list_of_asset_and_asset_group_names($risk_id, $formatted = false) {
    global $escaper;

    return array_map(function($item) use ($escaper, $formatted) {
        if ($formatted) {
            return "<span class='{$item['class']}'>" . $escaper->escapeHtml($item['name']) . "</span>";
        }
        return $escaper->escapeHtml($item['name']);
    }, get_assets_and_asset_groups_of_type($risk_id, 'risk', true));
}

/****************************************
 * FUNCTION : ASSETS FOR RISK ID        *
 * THIS FUNCTION IS OBSOLETE!!          *
 * No usage found as of v20190331-001   *
 ****************************************/
function assets_for_risk_id($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Update the default asset valuation
    $stmt = $db->prepare("
        SELECT
            a.id,
            a.ip,
            a.name,
            a.value,
            a.location,
            a.teams,
            a.created,
            a.verified
        FROM
            `assets` a
        LEFT JOIN `risks_to_assets` b ON a.id = b.asset_id
        WHERE
            b.risk_id=:risk_id;
    ");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT, 11);
    $stmt->execute();

    $assets = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the assets array
    return $assets;
}

/****************************************
 * FUNCTION: GET ASSETS FOR RISK        *
 * THIS FUNCTION IS OBSOLETE!!          *
 * No usage found as of v20190331-001   *
 ****************************************/
function get_assets_for_risk($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Get the assets
    $stmt = $db->prepare("SELECT b.name as asset FROM `risks_to_assets` a JOIN `assets` b ON a.asset_id = b.id WHERE risk_id = :risk_id ORDER BY b.name");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the assets array
    $assets = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the assets array
    return $assets;
}

/*******************************************
 * FUNCTION: GET ASSET GROUPS FROM ASSET   *
 *******************************************/
function get_asset_groups_from_asset($asset_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT ag.*
        FROM `assets_asset_groups` aag
        LEFT JOIN `asset_groups` ag ON ag.id = aag.asset_group_id
        WHERE `asset_id` = :asset_id
    ");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();

    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);
    
    return $groups;   
}
/*******************************************
 * FUNCTION: GET ASSETS FROM ASSET GROUP   *
 *******************************************/
function get_assets_from_group($group_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT *
        FROM `assets_asset_groups` 
        WHERE asset_group_id = :group_id
    ");
    $stmt->bindParam(":group_id", $group_id, PDO::PARAM_INT);
    $stmt->execute();

    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);
    
    return $assets;   
}
/*******************************************
 * FUNCTION: GET ASSET IDS FROM GROUPS     *
 *******************************************/
function get_asset_ids_from_groups($group_ids)
{
    $asset_ids = [];
    foreach($group_ids as $group){
        $assets = get_assets_from_group($group);
//      $singleArray = array();
//      foreach ($asserts as $key => $value){
//          $singleArray[$key] = $value['asset_id'];
//      }
        $asset_ids = array_unique(array_merge($asset_ids,array_column($assets,"asset_id")));
    }
    return $asset_ids;

}

function get_assets_data_for_view_v2($view, $selected_fields, $verified = null, $start = 0, $length = 10, $orderColumn = 'id', $orderDir = 'ASC', $column_filters = []) {

    global $field_settings_views, $field_settings, $escaper, $lang;
    $customization = customization_extra();
    
    // If there's an edit section setup in the view settings then the view is editable
    $view_editable = !empty($field_settings_views[$view]['edit']);
    $view_edit_type_popup = $view_editable && $field_settings_views[$view]['edit']['type'] === 'popup';
    
    // Open the database connection
    $db = db_open();
    
    $params = [];
    $encryption = encryption_extra();
    
    $actions_column_info = !empty($field_settings_views[$view]['actions_column']) ? $field_settings_views[$view]['actions_column'] : false;
    if ($actions_column_info) {
        // Create an array of escaped localized strings so it doesn't have to be done for every assets
        $actions_tooltips = [
            'edit' => $escaper->escapeHtml($lang['Edit']),
            'verify' => $escaper->escapeHtml($lang['Verify']),
            'discard' => $escaper->escapeHtml($lang['Discard']),
            'delete' => $escaper->escapeHtml($lang['Delete']),
        ];
    }
    
    if (str_starts_with($orderColumn, 'custom_field_')) {
        $sql_orderable = false;
    } else {
        // Can only order fields in the sql if they're not a custom field and encryption isn't enabled or they're not encrypted or if it's specifically stated that it's sql orderable
        $sql_orderable =
        (!$encryption || !$field_settings['asset'][$orderColumn]['encrypted']) &&
        (!array_key_exists('force_php_ordering', $field_settings['asset'][$orderColumn]) || !$field_settings['asset'][$orderColumn]['force_php_ordering']);

        if ($sql_orderable) {
            $sql_order_column = $field_settings['asset'][$orderColumn]['order_column'];
        } else {
            // If encryption is turned on and there's an encrypted order column specified then use that column for ordering and mark it as sql orderable
            if ($encryption && $field_settings['asset'][$orderColumn]['encrypted'] && !empty($field_settings['asset'][$orderColumn]['encrypted_order_column'])) {
                $sql_order_column = $field_settings['asset'][$orderColumn]['encrypted_order_column'];
                $sql_orderable = true;
            } elseif(array_key_exists('force_php_ordering', $field_settings['asset'][$orderColumn]) && $field_settings['asset'][$orderColumn]['force_php_ordering']) {
                // technically it would be possible to order in sql, but the result would be wrong, have to order in PHP by the display string
                $orderColumn = $field_settings['asset'][$orderColumn]['order_column'];
            }
        }
    }
    
    if ($verified !== null) {
        $where = "WHERE `a`.`verified` = :verified";
        $params['verified'] = $verified;
    } else {
        $where = "WHERE 1";
    }
    
    if(team_separation_extra()){
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $where .= get_user_teams_query_for_assets("a", false, true);
    }
    
    // At this point it's safe to add the column directly into the sql as it was validated
    $order_by = $sql_orderable ?  "ORDER BY {$sql_order_column} {$orderDir}, `a`.`id` ASC" : "";
    
    // We can do the paging through sql if there's no filtering and we can do the ordering through sql as well
    $sql_paging = empty($column_filters) && $sql_orderable;
    if ($sql_paging) {
        // When requesting every results the $length is -1 so we only limit the results if $length is greater than 0
        if ($length > 0) {
            $paging = "LIMIT {$start}, {$length}";
        } else {
            // In this case the $sql_paging = true to not try doing the paging using php code
            // but we're not limiting the number of returned results as we want all of them
            $paging = '';
        }
    } else {
        // paging will be done using php code
        $paging = '';
    }

    list($select_parts, $join_parts) = field_settings_get_join_parts($view, $selected_fields);

    $sql = "
        SELECT SQL_CALC_FOUND_ROWS t1.*
        FROM (
            SELECT
                " . implode(',', $select_parts) . "
            FROM
                `assets` a
                " . implode(' ', $join_parts) . "
            {$where}
            GROUP BY
                `a`.`id`
            {$order_by}
        ) t1
        {$paging};
    ";
    // error_log("SQL: $sql");
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll();
    // error_log("ASSETS: " . json_encode($assets));
    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetch()[0];

    // Close the database connection
    db_close($db);

    $columns_with_filters = array_keys($column_filters);

    // Get and store the currency here and not every time in the loop
    // also, only do it if the value column is selected
    if (in_array('value', $selected_fields)) {
        $currency_sign = get_setting("currency");
    }

    $rows = [];
    $filtered = false;
    foreach($assets as &$asset) {

        $drop_row = false;
        // If customization data for the asset is present
        if ($customization && !empty($asset['field_data']) && $asset['field_data'] !== '[]') {
            // extract it as normal fields
            foreach (json_decode($asset['field_data'], true) as $field_data) {
                
                if (empty($asset["custom_field_{$field_data['field_id']}_display"])) {
                    // only the rest needs formatting and only those fields that are selected. In this case they're escaped as well
                    if (in_array("custom_field_{$field_data['field_id']}", $selected_fields)) {
                        $asset["custom_field_{$field_data['field_id']}"] = get_custom_field_name_by_value($field_data['field_id'], $field_data['type'], $field_data['encryption'], $field_data['value']);
                    }
                } else {
                    // select/multi-select fields are already on the asset, returned by the sql
                    // so the custom fields already in the data needs to be escaped
                    $asset["custom_field_{$field_data['field_id']}_display"] = $escaper->escapeHtml($asset["custom_field_{$field_data['field_id']}_display"]);
                    $asset["custom_field_{$field_data['field_id']}"] = explode(',', (string)$field_data['value']);
                }
            }

            // Custom fields are ordered by their display strings
            if (!$sql_orderable && str_starts_with($orderColumn, 'custom_field_') && array_key_exists("{$orderColumn}_display", $asset)) {
                $orderColumn = "{$orderColumn}_display";
            }
        }

        $row = ['id' => $asset['id']];

        foreach ($selected_fields as $selected_field_name) {
            $field_setting = !empty($field_settings['asset'][$selected_field_name]) ? $field_settings['asset'][$selected_field_name] : false;
            $value = '';
            $display = false;
            if (!empty($asset[$selected_field_name])) {
                // if it's not defined in the settings it's probably a custom field
                if ($customization && empty($field_settings['asset'][$selected_field_name]) && str_starts_with($selected_field_name, 'custom_field_')) {
                    // as of now with how the custom field's actual values' getting goes they're already encrypted and escaped at this point
                    $value = !empty($asset[$selected_field_name]) ? $asset[$selected_field_name] : '';
                    $display = isset($asset["{$selected_field_name}_display"]) ? $asset["{$selected_field_name}_display"] : false;
                } else {
                    $value = $asset[$selected_field_name];
                    $display = isset($asset["{$selected_field_name}_display"]) ? $asset["{$selected_field_name}_display"] : false;
                    if ($value && $encryption && !empty($field_setting['encrypted']) && $field_setting['encrypted']) {
                        $value = try_decrypt($value);
                    }

                    // For fields that need custom formatting
                    switch($selected_field_name) {
                        case "teams":
                        case "location":
                            $value = explode(',', $value);
                            break;
                        case "details":
                            $value = $escaper->purifyHtml($value);
                            break;
                        case 'tags':
                            if ($value) {
                                $tags = [];
                                foreach(explode("|", $value) as $tag) {
                                    $tags []= $escaper->escapeHtml($tag);
                                }
                                $value = $tags;
                            }
                            break;
                        case 'value':
                            $display = $escaper->escapeHtml(str_replace('{currency}', $currency_sign, $display));
                            break;
                        case 'created':
                            $display = $escaper->escapeHtml(format_datetime($value));
                            break;
                        case 'verified':
                            $display = $escaper->escapeHtml(localized_yes_no($value));
                            break;
                        case 'mapped_controls':
                            if (!empty($value) && $value !== '[]') {
                                $mapped_controls = [];
                                foreach (json_decode($value, true) as $mapping) {
                                    $mapped_controls = [...$mapped_controls, ...explode('|', $mapping['control_names'])];
                                }
                                
                                sort($mapped_controls);
                                $value = implode(', ', $mapped_controls);
                            } else {
                                $value = '';
                            }
                            break;
                        case 'associated_risks':
                            if (!empty($value) && $value !== '[]') {
                                $associated_risks = [];
                                foreach (json_decode($value, true) as $associated_risk) {
                                    $associated_risk_id = 1000 + (int)$associated_risk['value'];
                                    $associated_risks []= $escaper->escapeHtml("[{$associated_risk_id}]" . try_decrypt($associated_risk['name']));
                                }
                                $value = implode(', ', $associated_risks);
                            } else {
                                $value = '';
                            }
                            break;
                        default:
                            // Only have to escape non-custom fields as those are already escaped
                            $value = $escaper->escapeHtml($value);
                    }
                }
            } elseif(array_key_exists("{$selected_field_name}_display", $asset)) {
                // To make sure that even empty values are properly sent back
                $display = '';
            }

            $row[$selected_field_name] = $value;
            if ($display !== false) {
                $row["{$selected_field_name}_display"] = $display;
            }

            // Do the filtering.
            // stripos(is_array($value) ? implode('|', $value) : $value, $column_filters[$selected_field_name]) === false
            // The above line is used to be able to filter within both arrays and primitive values by making the array a single string separated by something that's not likely to be searched on
            if (!empty($columns_with_filters) && in_array($selected_field_name, $columns_with_filters)) {
                $filter_value = $display !== false ? $display : $value;
                if(stripos(is_array($filter_value) ? implode('|', $filter_value) : $filter_value, $column_filters[$selected_field_name]) === false) {
                    $drop_row = true;
                    $filtered = true;
                    // If the row is getting filtered out we can stop processing it
                    break;
                }
            }
        }

        // Add the row only if it's not filtered out
        if (!$drop_row) {

            // Only if the action column info is set for the view
            if ($actions_column_info) {

                // Only show the edit button if the view's edit type is popup, no need for the button for inline editing
                $asset_actions = $view_edit_type_popup ? ["<button type='button' class='btn btn-secondary btn-sm asset-row-action' style='margin:1px; padding: 4px 12px;' role='button' data-action='edit' title='{$actions_tooltips['edit']}'><i class='fa fa-edit'></i></button>"] : [];

                // Different actions are available based on whether we want the verified/unverified/all assets
                if ($verified === 1) {
                    // When we display the verified assets the delete button is available
                    $asset_actions []= "<button class='btn btn-secondary btn-sm asset-row-action' style='margin:1px; padding: 4px 12px;' role='button' data-action='delete' title='{$actions_tooltips['delete']}'><i class='fa fa-trash'></i></button>";
                } elseif ($verified === 0) {
                    // When we display the not verified assets both the verify and discard buttons are available
                    $asset_actions []= "<button class='btn btn-secondary btn-sm asset-row-action' style='margin:1px; padding: 4px 12px;' role='button' data-action='discard' title='{$actions_tooltips['discard']}'><i class='fa fa-trash'></i></button>";
                    $asset_actions []= "<button class='btn btn-secondary btn-sm asset-row-action' style='margin:1px; padding: 4px 12px;' role='button' data-action='verify' title='{$actions_tooltips['verify']}'><i class='fa fa-check'></i></button>";
                } else {
                    
                    // in case of displaying all assets the presence of the verify button is decided on a per row basis
                    if (!$asset['verified']) {
                        $asset_actions []= "<button class='btn btn-secondary btn-sm asset-row-action' style='margin:1px; padding: 4px 12px;' role='button' data-action='discard' title='{$actions_tooltips['discard']}'><i class='fa fa-trash'></i></button>";
                        $asset_actions []= "<button class='btn btn-secondary btn-sm asset-row-action' style='margin:1px; padding: 4px 12px;' role='button' data-action='verify' title='{$actions_tooltips['verify']}'><i class='fa fa-check'></i></button>";
                    } else {
                        $asset_actions []= "<button class='btn btn-secondary btn-sm asset-row-action' style='margin:1px; padding: 4px 12px;' role='button' data-action='delete' title='{$actions_tooltips['delete']}'><i class='fa fa-trash'></i></button>";
                    }
                }
                $row[$actions_column_info['field_name']] = "<span data-id='{$asset['id']}'>" . implode('', $asset_actions) . "</span>";
            }
            $rows []= $row;
        }
    }

    $recordsFiltered = $filtered ? count($rows) : $recordsTotal;

    if (!$sql_orderable) {
        usort($rows, function($a, $b) use ($orderDir, $orderColumn){
            // For identical custom fields we're sorting on the id, so the results' order is not changing randomly
            if ($a[$orderColumn] === $b[$orderColumn]) {
                return (int)$a['id'] - (int)$b['id'];
            }
            
            return strcasecmp($a[$orderColumn], $b[$orderColumn]) * ($orderDir === "ASC" ? 1 : -1);
        });
    }

    if (!$sql_paging) {
        // Requesting all results is marked by $length's value being -1. In that case we're not applying the below logic
        // only when $length is greater than 0
        if($length > 0) {
            $page_rows = [];
            $row_count = count($rows);
            for($i = $start; $i < $row_count && $i < $start + $length; $i++){
                $page_rows[] = $rows[$i];
            }
            $rows = $page_rows;
        }
    }

    $data = [
        'rows' => $rows,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
    ];

    return $data;
}

// will be used for the inline editing for the assets
//TODO: use the update_name_order_for_asset($id, $name) function if encryption is enabled and the name is updated
function update_asset_field_API_v2($view, $fieldName) {
    
    global $field_settings_views, $field_settings, $lang, $escaper;
    
    $selected_fields = display_settings_get_display_settings_for_view($view);
    
    // Check if the edited field is in the selected fields for the view
    // no editing for off-screen fields and it also makes sure the field is setup for the view
    if (!in_array($fieldName, $selected_fields)) {
        set_alert(true, "bad", $lang['EditFailed_NotSelected']);
        api_v2_json_result(400, get_alert(true), NULL);
    }
    
    $view_type = $field_settings_views[$view]['view_type'];
    
    
    // TODO: add check to see if field is editable
    // TODO: Unique fields
    // Check if the field is required and if it is, then whether it has a proper value set
    if (!empty($field_settings[$view_type][$fieldName]['required']) && $field_settings[$view_type][$fieldName]['required'] && empty($_POST['fieldValue'])) {
        set_alert(true, "bad", $lang['EditFailed_RequiredFieldEmpty']);
        api_v2_json_result(400, get_alert(true), NULL);
    }
    
    $id = (int)$_POST['id'];
    $fieldValue = $_POST['fieldValue'];
    $customization = customization_extra();
    
    // If this is custom field
    if(stripos($fieldName, "custom_field") !== false) {
        // If customization extra is enabled
        if($customization) {
            // Get the custom field id from the name
            $custom_field_id = str_replace('custom_field_', '', $fieldName);
            // Include the extra
            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
            if (!save_custom_field_values($id, "asset", [$custom_field_id => $fieldValue])) {
                api_v2_json_result(400, get_alert(true), NULL);
            }
        } else {
            set_alert(true, "bad", $lang['EditFailed_CustomFieldNeedsCustomization']);
            api_v2_json_result(400, get_alert(true), NULL);
        }
    } else { // Non-custom fields
        // Tags handled differently than other fields
        if ($fieldName === 'tags') {
            $tags = empty($fieldValue) ? [] : $fieldValue;
            
            foreach($tags as $tag){
                if (strlen($tag) > 255) {
                    global $lang;
                    
                    set_alert(true, "bad", $lang['MaxTagLengthWarning']);
                    api_v2_json_result(400, get_alert(true), NULL);
                }
            }
            
            updateTagsOfType($id, 'asset', $tags);
        } else {
            //$updated = update_asset_field_value_by_field_name($id, $fieldName, $fieldValue);
            
            // If encryption extra is activated, then encrypt the field's value it if needed
            if (encryption_extra() && !empty($field_settings[$view_type][$fieldName]['encrypted']) && $field_settings[$view_type][$fieldName]['encrypted']) {
                $fieldValue = try_encrypt($fieldValue);
            }
            
            // These fields are still comma selected ids, need to remove this part once they're properly converted to use junction tables
            // and have a separate section for them like for the tags
            if (($fieldName === "location" || $fieldName === "teams") && is_array($fieldValue)) {
                $fieldValue = implode(",", $fieldValue);
            }
            
            // Open the database connection
            $db = db_open();
            
            // Update the asset. At this point FieldName is already validated to be an existing field, so no security risk here
            $stmt = $db->prepare("UPDATE `assets` SET `{$fieldName}` = :value WHERE `id` = :id");
            $stmt->bindParam(":value", $fieldValue, PDO::PARAM_STR);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Close the database connection
            db_close($db);
        }
        
        $message = _lang("FieldUpdated_{$view_type}", ['fieldName' => $fieldName, 'name' => get_name_by_value('assets', $id, "", true), 'user' => $_SESSION['user']]);
        write_log($id, $_SESSION['uid'], $message, "asset");
    }
    
    /* Properly implement this part when finishing inline edits
     $asset = get_asset_by_id($id);
     set_alert(true, "good", $lang['AssetWasUpdatedSuccessfully']);
     if ($fieldName == "tags") {
     $options = [];
     foreach(getTagsOfType('asset') as $tag) {
     $options[] = array('label' => $tag['tag'], 'value' => $tag['id']);
     }
     json_response(200, get_alert(true), $options);
     } else {
     json_response(200, get_alert(true), null);
     }*/
}

// Used to update the asset through the API call
function update_asset_API_v2($view) {
    
    global $field_settings_views, $field_settings, $lang, $escaper;

    $view_type = $field_settings_views[$view]['view_type'];
    $id_field = $field_settings_views[$view]['id_field'];
    $id = (int)$_POST[$id_field];

    // If the asset name is alread taken, but not on this asset
    $asset_id_tmp = asset_exists($_POST['name']);
    if (!empty($_POST['name']) && $asset_id_tmp &&  $id !== $asset_id_tmp) {
        set_alert(true, "bad", _lang('EditFailed_FieldMustBeUnique', ['field' => 'name'], false));
        api_v2_json_result(400, get_alert(true), NULL);
    }
    
    // If customization is enabled then gather information about the custom fields
    if ($customization = customization_extra()) {
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $active_fields = get_active_fields($view_type);
        $mapped_custom_field_settings = [];
        $custom_field_data = [];
        foreach ($active_fields as $active_field) {
            // Skip this step for basic fields
            if ($active_field['is_basic']) {
                continue;
            }

            $mapped_custom_field_settings["custom_field_{$active_field['id']}"] = [
                'field_id' => $active_field['id'],
                'required' => $active_field['required'],
                'editable' => true, // Custom fields are always editable for now
            ];
        }
    }
    
    
    if ($encryption = encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }
    
    if ($notification = notification_extra()) {
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));
    }

    $tags = [];
    $mapped_controls = [];
    $associated_risks_new = [];

    $update_parts = [];
    $params = [":$id_field" => $id];
    // Do the field validation(like required fields not having a value) and collect the data for the update
    foreach (field_settings_get_localization($view, false, false) as $field_name => $field_text) {

        // Skipping checks for the ID field here
        if ($field_name === $id_field) {
            continue;
        }
        
        // Check if the field is required and if it is, then whether it has a proper value set
        if (empty($_POST[$field_name]) && ((!empty($field_settings[$view_type][$field_name]) && $field_settings[$view_type][$field_name]['required'])
            || ($customization && !empty($mapped_custom_field_settings[$field_name]) && $mapped_custom_field_settings[$field_name]['required']))) {
            set_alert(true, "bad", _lang('EditFailed_RequiredFieldEmpty', ['field' => $field_text]));
            api_v2_json_result(400, get_alert(true), NULL);
        }
        
        // check if the field is editable
        if (((!empty($field_settings[$view_type][$field_name]) && !$field_settings[$view_type][$field_name]['editable']) || ($customization && !empty($mapped_custom_field_settings[$field_name]) && !$mapped_custom_field_settings[$field_name]['editable']))) {
            // If not editable but it's sent somehow then this is an error
            if (isset($_POST[$field_name])) {
                set_alert(true, "bad", _lang('EditFailed_FieldNotEditable', ['field' => $field_text]));
                api_v2_json_result(400, get_alert(true), NULL);
            } else {
                // otherwise we're just skipping the processing of this field
                continue;
            }
        }

        $field_value = $_POST[$field_name] ?? null;
        // Storing values after validation to update the asset
        if ($customization && str_starts_with($field_name, 'custom_field_')) {
            // Storing the field's value so we can save that after the asset is updated
            $custom_field_data[$mapped_custom_field_settings[$field_name]['field_id']] = $field_value;
        } else {
            // These fields are still comma selected ids, need to remove this part once they're properly converted to use junction tables
            // and have a separate section for them like for the tags
            if (($field_name === "location" || $field_name === "teams") && is_array($field_value)) {
                $field_value = implode(",", $field_value);
            }

            switch ($field_name) {
                case 'tags':
                    // If it's empty, we need an empty array, rather than null that's the default behavior for missing data
                    $tags = $field_value ? $field_value : [];

                    foreach($tags as $tag){
                        if (strlen($tag) > 255) {
                            set_alert(true, "bad", $lang['MaxTagLengthWarning']);
                            api_v2_json_result(400, get_alert(true), NULL);
                        }
                    }
                    break;
                case 'mapped_controls':
                    $mapped_controls = empty($_POST['mapped_controls']) ? [] : $_POST['mapped_controls'];
                    break;
                case 'associated_risks':
                    // Storing the list of associated risks so we can update it once the asset itself is updated
                    // If it's empty, we need an empty array, rather than null that's the default behavior for missing data
                    $associated_risks_new = $field_value ? $field_value : [];

                    if ($notification) {
                        // Also, storing the current list of associated risks so we can calculate the list of risk changes for the risk update notification
                        $associated_risks_current = get_associated_risks_for_asset($id);

                        // Get what risks were removed or added, for the notification we can ignore those that weren't changed
                        $associated_risks_need_notified = array_unique(array_merge(array_diff($associated_risks_new, $associated_risks_current), array_diff($associated_risks_current, $associated_risks_new)));
                    }
                    break;
                default:
                    // Store the asset name for the audit log before the encryption
                    if ($view_type === 'asset' && $field_name === 'name') {
                        $asset_name = $field_value;
                    }

                    // Encrypt the field if needed
                    if ($encryption && $field_settings[$view_type][$field_name]['encrypted']) {
                        $field_value = try_encrypt($field_value);
                    }

                    // build the parts that'll be used to construct the update
                    $update_parts [] = "`{$field_name}` = :{$field_name}";
                    $params[":{$field_name}"] = $field_value;
                    break;
            }
        }
    }

    $db = db_open();

    $stmt = $db->prepare("UPDATE `assets` SET " . implode(',', $update_parts) . " WHERE {$id_field} = :{$id_field};");
    $stmt->execute($params);

    db_close($db);

    // Save control mappings
    save_asset_to_controls($id, $mapped_controls);
    // Update tags even when they didn't change as the time we'd win on not saving them is lost on the checks
    // so if we check and then still have to save we'd basically just wasted time on checking
    updateTagsOfType($id, $view_type, $tags);

    update_asset_risks_associations($id, $associated_risks_new);

    if ($notification && !empty($associated_risks_need_notified)) {
        // Only send the notification about the updated risks that were changed on the asset
        foreach ($associated_risks_need_notified as $risk_id) {
            notify_risk_update($risk_id);
        }
    }

    if ($customization && !save_custom_field_values($id, $view_type, $custom_field_data)) {
        // It will basically never happen as we're checking values before even getting to the saving part to make sure we're not saving only half of the data
        api_v2_json_result(400, get_alert(true), NULL);
    }

    //TODO only do this if the name changed
    if ($encryption) {
        update_name_order_for_asset($id, $asset_name);
    }

    set_alert(true, "good", $escaper->escapeHtml($lang['AssetWasUpdatedSuccessfully']));
    api_v2_json_result(200, get_alert(true), NULL);

    $message = _lang("UpdateSuccess_{$view_type}", ['name' => $asset_name, 'user' => $_SESSION['user']]);
    write_log($id, $_SESSION['uid'], $message, "asset");
}


function create_asset_API_v2($view) {
    
    global $field_settings_views, $field_settings, $lang, $escaper;
    
    $view_type = $field_settings_views[$view]['view_type'];
    
    // If the asset name is alread taken, but not on this asset
    if (!empty($_POST['name']) && asset_exists($_POST['name'])) {
        set_alert(true, "bad", _lang('EditFailed_FieldMustBeUnique', ['field' => 'name'], false));
        api_v2_json_result(400, get_alert(true), NULL);
    }
    
    // If customization is enabled then gather information about the custom fields
    if ($customization = customization_extra()) {
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        $active_fields = get_active_fields($view_type);
        $mapped_custom_field_settings = [];
        $custom_field_data = [];
        foreach ($active_fields as $active_field) {
            // Skip this step for basic fields
            if ($active_field['is_basic']) {
                continue;
            }
            
            $mapped_custom_field_settings["custom_field_{$active_field['id']}"] = [
                'field_id' => $active_field['id'],
                'required' => $active_field['required'],
                'editable' => true, // Custom fields are always editable for now
            ];
        }
    }
    
    if ($encryption = encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }
    
    if ($notification = notification_extra()) {
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));
    }
    
    $tags = [];
    $mapped_controls = [];
    $associated_risks = [];
    
    $insert_parts = ["`verified` = :verified"];
    $params = ["verified" => true];
    // Do the field validation(like required fields not having a value) and collect the data for the update
    foreach (field_settings_get_localization($view, false, false) as $field_name => $field_text) {
        
        // Skipping checks for the verified field here
        if ($field_name === 'verified') {
            continue;
        }
        
        // Check if the field is required and if it is, then whether it has a proper value set
        if (empty($_POST[$field_name]) && ((!empty($field_settings[$view_type][$field_name]) && $field_settings[$view_type][$field_name]['required'])
            || ($customization && !empty($mapped_custom_field_settings[$field_name]) && $mapped_custom_field_settings[$field_name]['required']))) {
            set_alert(true, "bad", _lang('EditFailed_RequiredFieldEmpty', ['field' => $field_text]));
            api_v2_json_result(400, get_alert(true), NULL);
        }

        $field_value = $_POST[$field_name] ?? null;
        // Storing values after validation to update the asset
        if ($customization && str_starts_with($field_name, 'custom_field_')) {
            // Storing the field's value so we can save that after the asset is updated
            $custom_field_data[$mapped_custom_field_settings[$field_name]['field_id']] = $field_value;
        } else {
            // These fields are still comma selected ids, need to remove this part once they're properly converted to use junction tables
            // and have a separate section for them like for the tags
            if (($field_name === "location" || $field_name === "teams") && is_array($field_value)) {
                $field_value = implode(",", $field_value);
            }
            
            switch ($field_name) {
                case 'tags':
                    // If it's empty, we need an empty array, rather than null that's the default behavior for missing data
                    $tags = $field_value ? $field_value : [];
                    
                    foreach($tags as $tag){
                        if (strlen($tag) > 255) {
                            set_alert(true, "bad", $lang['MaxTagLengthWarning']);
                            api_v2_json_result(400, get_alert(true), NULL);
                        }
                    }
                    break;
                case 'mapped_controls':
                    $mapped_controls = empty($_POST['mapped_controls']) ? [] : $_POST['mapped_controls'];
                    
                    /*foreach($mapped_controls as $mapped_control){
                        if($control_id[$index]) $mapped_controls[] = array($maturity, $control_id[$index]);
                    }*/
                    
                    
                    /*$control_maturity   = empty($_POST['control_maturity']) ? [] : $_POST['control_maturity'];
                    $control_id         = empty($_POST['control_id']) ? [] : $_POST['control_id'];
                    foreach($control_maturity as $index=>$maturity){
                        if($control_id[$index]) $mapped_controls[] = array($maturity, $control_id[$index]);
                    }*/
                    break;
                case 'associated_risks':
                    // Storing the list of associated risks so we can set it once the asset itself is created
                    // If it's empty, we need an empty array, rather than null that's the default behavior for missing data                    
                    if ($notification) {
                        $associated_risks = $field_value ? $field_value : [];
                    } else {
                        $associated_risks = [];
                    }
                    break;
                default:
                    // Store the asset name for the audit log before the encryption
                    if ($view_type === 'asset' && $field_name === 'name') {
                        $asset_name = $field_value;
                    }
                    
                    // Encrypt the field if needed
                    if ($encryption && $field_settings[$view_type][$field_name]['encrypted']) {
                        $field_value = try_encrypt($field_value);
                    }
                    
                    // build the parts that'll be used to construct the insert
                    $insert_parts [] = "`{$field_name}` = :{$field_name}";
                    $params[":{$field_name}"] = $field_value;
                    break;
            }
        }
    }
    
    $db = db_open();
    
    $stmt = $db->prepare("INSERT INTO `assets` SET " . implode(',', $insert_parts) . ";");
    $stmt->execute($params);

    $id = $db->lastInsertId();

    db_close($db);
    
    // Save control mappings
    save_asset_to_controls($id, $mapped_controls);

    // Update tags even when they didn't change as the time we'd win on not saving them is lost on the checks
    // so if we check and then still have to save we'd basically just wasted time on checking
    updateTagsOfType($id, $view_type, $tags);
    
    update_asset_risks_associations($id, $associated_risks);
    
    if ($notification && !empty($associated_risks)) {
        // Only send the notification about the updated risks that were changed on the asset
        foreach ($associated_risks as $risk_id) {
            notify_risk_update($risk_id);
        }
    }
    
    if ($customization && !save_custom_field_values($id, $view_type, $custom_field_data)) {
        // It will basically never happen as we're checking values before even getting to the saving part to make sure we're not saving only half of the data
        api_v2_json_result(400, get_alert(true), NULL);
    }
    
    if ($encryption) {
        update_name_order_for_asset($id, $asset_name);
    }

    set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
    api_v2_json_result(200, get_alert(true), NULL);
    
    $message = _lang("CreateSuccess_{$view_type}", ['name' => $asset_name, 'user' => $_SESSION['user']]);
    write_log($id, $_SESSION['uid'], $message, "asset");
}

/**
 * Processes the control mapping coming from the UI in the format of [['control_maturity' => maturity_id, 'control_id' => [control_id1, control_id2, ...]], ...]
 *
 * Returns it in the format of [maturity_id => [control_id1, control_id2, ...], ...]
 *
 * It groups by maturity id, merging the control id arrays associated to the same maturity.
 *
 * @param array $raw_mapped_controls
 * @return array
 */
function process_asset_control_mapping($raw_mapped_controls) {

    $temp_mapping = empty($raw_mapped_controls) ? [] : array_map(fn($mapped_control) => json_decode($mapped_control, true), $raw_mapped_controls);

    // merging individual rows, grouped by maturity
    $mapped_controls = [];
    foreach ($temp_mapping as $mapped_control) {
        $maturity_id = (int)$mapped_control['control_maturity'];
        $control_ids = array_map(fn($control_id) => (int)$control_id, $mapped_control['control_id']);
        if (isset($mapped_controls[$maturity_id])) {
            $mapped_controls[$maturity_id] = array_values(array_unique([...$mapped_controls[$maturity_id], ...$control_ids]));
        } else {
            $mapped_controls[$maturity_id] = $control_ids;
        }
    }

    return $mapped_controls;
}

/**
 * Validate the asset control mapping to prevent having the same control mapped to multiple maturities
 *
 * It assumes that the mapping is already preprocessed and in the format of [maturity_id => [control_id1, control_id2, ...], ...]
 *
 * @param array $mapped_controls an array in the format of [maturity_id => [control_id1, control_id2, ...], ...]
 * @return boolean whether the mapping is valid
 */
function validate_asset_control_mapping($mapped_controls) {
    
    if (empty($mapped_controls)) {
        return true;
    }
    
    $used_control_ids = [];
    foreach ($mapped_controls as $_ => $control_ids) {
        if (!empty(array_intersect($used_control_ids, $control_ids))) {
            return false;
        }
        $used_control_ids = array_values(array_unique([...$used_control_ids, ...$control_ids]));
    }
    return true;
}

?>