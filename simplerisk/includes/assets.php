<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));
require_once(language_file());
require_once(realpath(__DIR__ . '/displayassets.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

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
    exec(sprintf('ping -c 1 -W 1 %s', escapeshellarg($ip)), $res, $rval);
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
        $exists = encrypted_asset_exists($name);

        // Return the result
        return $exists;
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
    // $imported is set as true to prevent the `auto_verify_new_assets` setting
    // to turn our false to true
    return add_asset('', $name, 5, 0, 0, "", "", $verified, true);
}

/***********************
 * FUNCTION: ADD ASSET *
 ***********************/
function add_asset($ip, $name, $value=5, $location="", $teams="", $details = "", $tags = "", $verified = false, $imported = false)
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
        
        if (!$name)
            return false;

        // See if we need to encrypt values
        $ip = try_encrypt($ip);
        $name = try_encrypt($name);
        $details = try_encrypt($details);

        $auto_verify_new_assets = get_setting("auto_verify_new_assets");

        if (!$verified && $auto_verify_new_assets && !$imported) {
            $verified = true;
        }

        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("INSERT INTO `assets` (ip, name, value, location, teams, details, verified) VALUES (:ip, :name, :value, :location, :teams, :details, :verified) ON DUPLICATE KEY UPDATE `ip`=:ip, `value`=:value, `location`=:location, `teams`=:teams, `details`=:details, `verified`=:verified;");
        $stmt->bindParam(":ip", $ip, PDO::PARAM_STR);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
        $stmt->bindParam(":location", $location, PDO::PARAM_STR);
        $stmt->bindParam(":teams", $teams, PDO::PARAM_STR);
        $stmt->bindParam(":details", $details, PDO::PARAM_STR);
        $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
        $return = $stmt->execute();

        // If failed to insert, update the record
        if(!$stmt->rowCount())
        {
            $asset_id = 0;
        }
        else
        {
            $stmt = $db->prepare("SELECT id FROM `assets` WHERE `name`=:name AND `ip`=:ip AND `value`=:value AND `location`=:location AND `teams`=:teams AND `details`=:details AND `verified`=:verified;");
            $stmt->bindParam(":ip", $ip, PDO::PARAM_STR, 15);
            $stmt->bindParam(":name", $name, PDO::PARAM_STR, 200);
            $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
            $stmt->bindParam(":location", $location, PDO::PARAM_STR);
            $stmt->bindParam(":teams", $teams, PDO::PARAM_STR);
            $stmt->bindParam(":details", $details, PDO::PARAM_STR);
            $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
            $stmt->execute();
            $asset_id = $stmt->fetch(PDO::FETCH_COLUMN);
        }

        // Update the asset_id column in risks_to_assets
        //$stmt = $db->prepare("UPDATE `risks_to_assets` INNER JOIN `assets` ON `assets`.name = `risks_to_assets`.asset SET `risks_to_assets`.asset_id = `assets`.id;");
        //$stmt->execute();

        // Close the database connection
        db_close($db);

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

        if ($asset_id != 0) {
            updateTagsOfType($asset_id, 'asset', $tags);
        }

        // If the encryption extra is enabled, updates order_by_name
        if (encryption_extra()) {
            require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
            create_asset_name_order(base64_decode($_SESSION['encrypted_pass']));
        }

        $message = "An asset named \"" . try_decrypt($name) . "\" was added by username \"" . $_SESSION['user'] . "\".";
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

/*******************************
 * FUNCTION: DELETE ALL ASSETS *
 *******************************/
function delete_all_assets($verified)
{
    // Open the database connection
    $db = db_open();

    // Get all asset ID
    $stmt = $db->prepare("SELECT id FROM `assets` where `verified`=:verified;");
    $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
    $stmt->execute();
    $asset_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Close the database connection
    db_close($db);
    
    $asset_ids || $asset_ids=[];
    
    $return = delete_assets($asset_ids);

    // Return success or failure
    return $return;
}

/***************************
 * FUNCTION: DELETE ASSETS *
 ***************************/
function delete_assets($assets)
{
    // Return true by default
    $return = true;

    // For each asset
    foreach ($assets as $asset)
    {
        $asset_id = (int) $asset;

        // Delete the asset
        $success = delete_asset($asset_id);

        // If it was not a success return false
        if (!$success) $return = false;
    }

    // Return success or failure
    return $return;
}

/**************************
 * FUNCTION: DELETE ASSET *
 **************************/
function delete_asset($asset_id)
{
    // Open the database connection
    $db = db_open();

    $name = get_asset_name($asset_id);

    // Delete the assets entry
    $stmt = $db->prepare("DELETE FROM `assets` WHERE `id`=:id;");
    $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Remove junction table entries for the Asset
    foreach(['assets_asset_groups',
        'risks_to_assets',
        'assessment_answers_to_assets',
        'questionnaire_answers_to_assets'] as $junction_name) {

        if (!table_exists($junction_name))
            continue;

        $stmt = $db->prepare("
            delete from
                `$junction_name`
            where
                `asset_id`=:id;
        ");
        $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // If assessment extra is enabled and `questionnaire_answers_to_assets` table exists, remove entries for the Asset
    if(assessments_extra() && table_exists("questionnaire_answers_to_assets"))
    {
        $stmt = $db->prepare("
            delete from
                `questionnaire_answers_to_assets`
            where
                `asset_id`=:id;
        ");
        $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // If customization extra is enabled, delete custom_asset_data related with asset ID
    if(customization_extra())
    {
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        delete_custom_data_by_row_id($asset_id, "asset");
    }
    
    $message = "An asset named \"" . $name . "\" was deleted by username \"" . $_SESSION['user'] . "\".";
    write_log($asset_id , $_SESSION['uid'], $message, "asset");

    // Close the database connection
    db_close($db);

    removeTagsOfTaggee($asset_id, 'asset');

    // Return success or failure
    return $return;
}


/***************************
 * FUNCTION: VERIFY ASSETS *
 ***************************/
function verify_assets($assets)
{
    // Return true by default
    $return = true;

    // For each asset
    foreach ($assets as $asset)
    {
        $asset_id = (int) $asset;

        // Verify the asset
        $success = verify_asset($asset_id);

        // If it was not a success return false
        if (!$success) $return = false;
    }

    // Return success or failure
    return $return;
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

    $message = "An asset named \"" . get_asset_name($asset_id) . "\" was verified by username \"" . $_SESSION['user'] . "\".";
    write_log($asset_id , $_SESSION['uid'], $message, "asset");

    // Close the database connection
    db_close($db);

    // Return success or failure
    return $return;
}



/*********************************
 * FUNCTION: DISPLAY ASSET DETAIL*
 *********************************/
function display_asset_detail($id)
{
    global $escaper;
    global $lang;
    
    $asset = get_asset_by_id($id)[0];

    // If the IP address is not valid
        if (!preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $asset['ip']))
        {
            $asset['ip'] = "N/A";
        }

        // If the location is unspecified
        if ($asset['location'] == 0)
        {
            $asset['location'] = "N/A";
        }
        else $asset['location'] = get_name_by_value("location", $asset['location']);

        // If the team is unspecified
        if (!$asset['teams'])
        {
            $asset['teams'] = "N/A";
        }
        else $asset['teams'] = get_names_by_multi_values("team", $asset['teams']);
        
        $display = "
            <h4>". $escaper->escapeHtml($asset['name']) ."</h4>
            <br>
            <div class='row-fluid'>
                <div class='span3'>
                    ". $escaper->escapeHtml($lang['IPAddress']) .":
                </div>
                <div class='span9'>
                    ". $escaper->escapeHtml($asset['ip']) ."
                </div>
            </div><br>
            <div class='row-fluid'>
                <div class='span3'>
                    ". $escaper->escapeHtml($lang['AssetValuation']) .":
                </div>
                <div class='span9'>
                    ". $escaper->escapeHtml(get_asset_value_by_id($asset['value'])) ."
                </div>
            </div><br>
            <div class='row-fluid'>
                <div class='span3'>
                    ". $escaper->escapeHtml($lang['SiteLocation']) .":
                </div>
                <div class='span9'>
                    ". $escaper->escapeHtml($asset['location']) ."
                </div>
            </div><br>
            <div class='row-fluid'>
                <div class='span3'>
                    ". $escaper->escapeHtml($lang['Team']) .":
                </div>
                <div class='span9'>
                    ". $escaper->escapeHtml($asset['teams']) ."
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span3'>
                    ". $escaper->escapeHtml($lang['Verified']) .":
                </div>
                <div class='span9'>
                    ". $escaper->escapeHtml(localized_yes_no($asset['verified'])) ."
                </div>
            </div>
        ";

        echo $display;
}

/*********************************
 * FUNCTION: DISPLAY ASSET TABLE *
 *********************************/
function display_asset_table()
{
    global $lang;
    global $escaper;

    echo "<table id=\"verified_asset_table\" class=\"table table-bordered table-condensed sortable\">\n";

    // Display the table header
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\">&nbsp;</th>\n";
    
    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("asset");

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

    
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // print the body
    display_asset_table_body();

    echo "</tbody>\n";
    echo "</table>\n";
}


/*********************************
 * FUNCTION: GET ASSET TABLE BODY*
 *********************************/
function display_asset_table_body()
{
    global $lang;
    global $escaper;

    // Get the array of assets
    $assets = get_verified_assets();

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


    // For each asset
    foreach ($assets as $asset)
    {
        echo  "<tr id=\"tr-".$asset['id']."\">\n";
        echo  "<td align=\"center\" style='width:1%; white-space:nowrap; padding: 0px;'>\n";
        echo  "<button type='button' class='btn btn-danger btn-xs delete-asset' data-id='".$asset['id']."'>";
        echo  "<i class='fa fa-times' style='font-size:24px'></i>";
        echo  "</button>";
        echo  "<input id=\"".$asset['id']."\" style=\"display: none\" type=\"checkbox\" name=\"assets[]\" value=\"" . $escaper->escapeHtml($asset['id']) . "\" checked />";
        echo  "</td>\n";

        // If the customization extra, set custom values
        if($active_fields)
        {
            display_main_detail_asset_fields_td_view($active_fields, $asset);        
        }
        else
        {
            display_asset_name_td($asset['name']);
            display_asset_ip_address_td($asset['ip']);
            display_asset_valuation_td($asset['value']);
            display_asset_site_location_td($asset['location']);
            display_asset_team_td($asset['teams']);
            display_asset_details_td($asset['details']);
            display_asset_tags_td($asset['tags']);
        }
        
        echo  "</tr>\n";
    }
}



/********************************************
 * FUNCTION: DISPLAY UNVERIFIED ASSET TABLE *
 ********************************************/
function display_unverified_asset_table()
{
    global $lang;
    global $escaper;

    echo "<table id=\"unverified_asset_table\" class=\"table table-bordered table-condensed sortable\">\n";

    // Display the table header
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" colspan=\"2\">" . $escaper->escapeHtml($lang['VerifyOrDiscard']) . "</th>\n";

    // If the customization extra is enabled, shows fields by asset customization
    if (customization_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));

        $active_fields = get_active_fields("asset");

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

        $active_fields = false;
    }

    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // Get the array of assets
    $assets = get_unverified_assets();

    // For each asset
    foreach ($assets as $asset)
    {
        echo "<tr>\n";
        echo "<td align=\"center\" style='width:1%; white-space:nowrap; padding: 0px;'>\n";
        echo "<button type='button' class='btn btn-success btn-xs verify-asset' data-id='".$asset['id']."'>";
        echo "<i class='fa fa-check' style='font-size:24px;'></i>";
        echo "</button>";
        echo "<input id=\"".$asset['id']."\" style=\"display: none\" type=\"checkbox\" name=\"assets[]\" value=\"" . $escaper->escapeHtml($asset['id']) . "\" checked />";
        echo "</td>\n";
        echo "<td align=\"center\" style='width:1%; white-space:nowrap; padding: 0px;'>\n";
        echo "<button type='button' class='btn btn-danger btn-xs discard-asset' data-id='".$asset['id']."'>";
        echo "<i class='fa fa-times' style='font-size:24px'></i>";
        echo "</button>";
        echo "</td>\n";

        // If the customization extra, set custom values
        if($active_fields)
        {
            display_main_detail_asset_fields_td_view($active_fields, $asset);
        }
        else
        {
            display_asset_name_td($asset['name']);
            display_asset_ip_address_td($asset['ip']);
            display_asset_valuation_td($asset['value']);
            display_asset_site_location_td($asset['location']);
            display_asset_team_td($asset['teams']);
            display_asset_details_td($asset['details']);
            display_asset_tags_td($asset['tags']);
        }

        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";
}

/**************************************************
 * FUNCTION: CHECK IF THERE ARE ASSETS *
 **************************************************/
function has_assets($verified=null)
{
    // Open the database connection
    $db = db_open();

    if ($verified === null) {
        $stmt = $db->prepare("SELECT count(1) as cnt FROM `assets`;");
    }else {
        $stmt = $db->prepare("SELECT count(1) as cnt FROM `assets` where `verified` = :verified;");
        $stmt->bindParam(":verified", $verified, PDO::PARAM_INT);
    }
    $stmt->execute();

    $result = boolval($stmt->fetch()['cnt']);

    // Close the database connection
    db_close($db);

    return $result;
}

/**************************************************
 * FUNCTION: CHECK IF THERE ARE UNVERIFIED ASSETS *
 **************************************************/
function has_unverified_assets()
{
    return has_assets(false);
}

/**************************************************
 * FUNCTION: CHECK IF THERE ARE VERIFIED ASSETS *
 **************************************************/
function has_verified_assets()
{
    return has_assets(true);
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
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR '|') as tags
        FROM
            `assets` a
            LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id AND tt.type = 'asset'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        {$where}
        GROUP BY
            a.id
        ORDER BY
            " . (encryption_extra() ? "a.order_by_name" : "a.name") . "
    ;");
    $stmt->execute($params);

    // Store the list in the assets array
    $assets = $stmt->fetchAll();

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

    $stmt = $db->prepare("SELECT * FROM `assets` where id=:id ORDER BY name;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the assets array
    $asset = $stmt->fetchAll();

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

/**********************************
 * FUNCTION: GET UNENTERED ASSETS *
 **********************************/
function get_unentered_assets()
{
/*
        // Open the database connection
        $db = db_open();

        // Get the assets
        $stmt = $db->prepare("SELECT DISTINCT asset AS name FROM risks_to_assets WHERE asset_id = 0");
        $stmt->execute();

        // Store the list in the assets array
        $assets = $stmt->fetchAll();

        // Close the database connection
        db_close($db);
*/
    $assets = array();

        // Return the assets array
        return $assets;

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

/************************
 * FUNCTION: EDIT ASSET *
 ************************/
function edit_asset($id, $value, $location, $team, $details)
{
    $details = try_encrypt($details);
    
    // Open the database connection
    $db = db_open();

    // Update the asset
    $stmt = $db->prepare("UPDATE assets SET value = :value, location = :location, team = :team, details = :details WHERE id = :id");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
    $stmt->bindParam(":location", $location, PDO::PARAM_STR);
    $stmt->bindParam(":team", $team, PDO::PARAM_STR);
    $stmt->bindParam(":details", $details, PDO::PARAM_STR);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $name = get_asset_name($id);

    $message = "An asset named \"" . $name . "\" was modified by username \"" . $_SESSION['user'] . "\".";
    write_log($id, $_SESSION['uid'], $message, "asset");
    
    // Close the database connection
    db_close($db);
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

    $name = get_asset_name($id);

    $message = "An asset named \"" . $name . "\" was modified by username \"" . $_SESSION['user'] . "\".";
    write_log($id, $_SESSION['uid'], $message, "asset");
    
    // Close the database connection
    db_close($db);

    return true;
}

/**********************************
 * FUNCTION: IMPORT ASSET *
 * team: string splitted by comma
 *********************************/
function import_asset($ip, $name, $value, $location, $teams, $details, $tags, $verified)
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

	    return add_asset($ip, $name, $value, $location, $teams, $details, $tags, $verified, true);
    }

    if (asset_exists_exact($ip, $name, $value, $location, $teams, $details, $verified)
        && areTagsEqual($asset_id, 'asset', $tags)) {
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
 * FUNCTION: GET ASSET NAME  *
 *****************************/
function get_asset_name( $asset_id )
{
    $db = db_open();

    $stmt = $db->prepare("SELECT name from assets where id = :id");
    $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();

    $dd = $stmt->fetchAll();

    foreach ($dd as $key => $value) {
        $name = $value['name'];
    }

    db_close($db);

    return try_decrypt($name);

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
function display_asset_valuation_table()
{
    global $lang;
    global $escaper;

    // Open the database connection
    $db = db_open();

    echo "<table border=\"0\" cellspacing=\"5\" cellpadding=\"5\">\n";

    // Display the table header
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['ValueRange']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['MinimumValue']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['MaximumValue']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['ValuationLevelName']) . "</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // Get the asset values
    $stmt = $db->prepare("SELECT * FROM asset_values;");
    $stmt->execute();
    $values = $stmt->fetchAll();

    // For each asset value
    foreach ($values as $value)
    {
        // Minimum value for field
        $minimum = (int)$value['id'] - 1;

        echo "<tr>\n";
        echo "<td>" . $escaper->escapeHtml($value['id']) . "</td>\n";
        echo "<td><input id=\"dollarsign\" type=\"number\" min=\"" . $escaper->escapeHtml($minimum) . "\" name=\"min_value_" . $escaper->escapeHtml($value['id']) . "\" value=\"" . $escaper->escapeHtml($value['min_value']) . "\" onFocus=\"this.oldvalue = this.value;\" onChange=\"javascript:updateMinValue('" . $escaper->escapeHtml($value['id']) . "');this.oldvalue = this.value;\" /></td>\n";
        echo "<td><input id=\"dollarsign\" type=\"number\" min=\"" . $escaper->escapeHtml($minimum) . "\" name=\"max_value_" . $escaper->escapeHtml($value['id']) . "\" value=\"" . $escaper->escapeHtml($value['max_value']) . "\" onFocus=\"this.oldvalue = this.value;\" onChange=\"javascript:updateMaxValue('" . $escaper->escapeHtml($value['id']) . "');this.oldvalue = this.value;\" /></td>\n";
        echo "<td><input type=\"text\" name=\"valuation_level_name_" . $escaper->escapeHtml($value['id']) . "\" value=\"" . $escaper->escapeHtml($value['valuation_level_name']) . "\" /></td>\n";

        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";

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
        
    echo "<select id=\"" . $escaper->escapeHtml($id) . "\" name=\"" . $escaper->escapeHtml($name) . "\" {$customHtml} class=\"form-field\" style=\"width:auto;\" >\n";

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

    if(!empty($value)){
        if($value['min_value'] === $value['max_value']){
            $asset_value = get_setting("currency") . number_format($value['min_value']);
        }else{
            $asset_value = get_setting("currency") . number_format($value['min_value']) . " to " . get_setting("currency") . number_format($value['max_value']);
        }

        if (!$export && !empty($value['valuation_level_name']))
            $asset_value .= " ({$value['valuation_level_name']})";
    }else{
        $asset_value = "Undefined";
    }

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
    }
    // If the customization extra is disabled, shows fields by default fields
    else
    {
        display_asset_name_edit();

        display_asset_ip_address_edit();

        display_asset_valuation_edit();

        display_asset_site_location_edit();

        display_asset_team_edit();

        display_asset_details_edit();

        display_asset_tags_add();
    }
    
    echo "
        <style>
            #add-asset-container textarea{
                max-width: 300px;
            }
        </style>
    ";
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

    update_assets_of_asset_group($selected_assets, $id, $name);

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

/**********************************************************
 * FUNCTION: PROCESS SELECTED ASSETS ASSET GROUPS OF TYPE *
 * Processing the data coming from the widget used        *
 * for selecting assets and asset groups.                 *
 * $type_id: Id of the item we want to associate the      *
 * assets and asset groups with                           *
 * $assets_and_groups: data from the widget. Can          *
 * contain asset/asset group ids or names of new assets   *
 **********************************************************/
function process_selected_assets_asset_groups_of_type($type_id, $assets_and_groups, $type) {

    // Open the database connection
    $db = db_open();

    switch($type) {
        case 'risk':
            $assets_junction_name = 'risks_to_assets';
            $asset_groups_junction_name = 'risks_to_asset_groups';
            $junction_id_name = 'risk_id';
            $forced_asset_verification_state = null;
        break;
        case 'assessment_answer':
            $assets_junction_name = 'assessment_answers_to_assets';
            $asset_groups_junction_name = 'assessment_answers_to_asset_groups';
            $junction_id_name = 'assessment_answer_id';
            $forced_asset_verification_state = true;
        break;
        case 'questionnaire_risk':
            $assets_junction_name = 'questionnaire_risk_to_assets';
            $asset_groups_junction_name = 'questionnaire_risk_to_asset_groups';
            $junction_id_name = 'questionnaire_id';
            $forced_asset_verification_state = true;
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
    $stmt = $db->prepare("DELETE FROM `{$assets_junction_name}` WHERE {$junction_id_name} = :{$junction_id_name}");
    $stmt->bindParam(":{$junction_id_name}", $type_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $db->prepare("DELETE FROM `{$asset_groups_junction_name}` WHERE {$junction_id_name} = :{$junction_id_name}");
    $stmt->bindParam(":{$junction_id_name}", $type_id, PDO::PARAM_INT);
    $stmt->execute();    
    
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

            if ($forced_asset_verification_state === null) {
                $id = add_asset('', $name);
            } else {
                $id = add_asset_by_name_with_forced_verification($name, $forced_asset_verification_state);
            }
            $type = 'asset';                
        } else {
            //Invalid input
            continue;
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
            <div class='text-center actions-cell'>
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

        $asset['ip'] = try_decrypt($asset['ip']);
        
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
            <div class='text-center actions-cell'>
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

function get_assets_and_asset_groups_for_dropdown($risk_id = false) {

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

    if ($risk_id)
        $risk_id -= 1000;
    
    $sql = "
        SELECT
            *
        FROM (
            SELECT
                `a`.`id`,
                `a`.`name`,
                'asset' as class,
                " . (encryption_extra() ? "`a`.`order_by_name`" : "`a`.`name`") . " as ordr" .
    ($risk_id ? ",`rta`.`asset_id` IS NOT NULL as selected" : "") . "
            FROM
                `assets` a " .
    ($risk_id ? "LEFT OUTER JOIN `risks_to_assets` rta ON `rta`.`asset_id` = `a`.`id` and `rta`.`risk_id` = :risk_id" : "") . "
            WHERE
                `a`.`verified` = 1" . ($risk_id ? " or `rta`.`asset_id` IS NOT NULL" : "") . " {$team_based_separation_where_condition}
        UNION ALL
            SELECT
                `ag`.`id`,
                `ag`.`name`,
                'group' as class,
                " . (encryption_extra() ? "@rownum := @rownum + 1" : "`ag`.`name`") . " as ordr" .
    ($risk_id ? ",`rtag`.`asset_group_id` IS NOT NULL as selected" : "") . "
            FROM
                `asset_groups` ag " .
    ($risk_id ? "LEFT OUTER JOIN `risks_to_asset_groups` rtag ON `rtag`.`asset_group_id` = `ag`.`id` and `rtag`.`risk_id` = :risk_id " : "") . 
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

    if ($risk_id)
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);

    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    foreach($data as &$item)
        if ($item['class'] === 'asset')
            $item['name'] = try_decrypt($item['name']);

    return $data;
}

function get_assets_and_asset_groups_of_type_as_string($id, $type) {

    switch($type) {
        case 'assessment_answer':
            $assets_junction_name = 'assessment_answers_to_assets';
            $asset_groups_junction_name = 'assessment_answers_to_asset_groups';
            $junction_id_name = 'assessment_answer_id';
        break;
        case 'questionnaire_answer':
            if(!assessments_extra() || !assessments_extra("questionnaire_answers_to_assets") || !assessments_extra("questionnaire_answers_to_asset_groups"))
            {
                return;
            }
            $assets_junction_name = 'questionnaire_answers_to_assets';
            $asset_groups_junction_name = 'questionnaire_answers_to_asset_groups';
            $junction_id_name = 'questionnaire_answer_id';
        break;
        case 'questionnaire_risk':
            if(!assessments_extra()) return;
            $assets_junction_name = 'questionnaire_risk_to_assets';
            $asset_groups_junction_name = 'questionnaire_risk_to_asset_groups';
            $junction_id_name = 'questionnaire_id';
            $forced_asset_verification_state = true;
        break;
        default:
            return;
    }

    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `a`.`name`,
            'asset' as class
        FROM
            `$assets_junction_name` aata
            INNER JOIN `assets` a ON `a`.`id` = `aata`.`asset_id`
            and `aata`.`$junction_id_name` = :$junction_id_name
        UNION ALL
        SELECT
            `ag`.`name`,
            'group' as class
        FROM
            `$asset_groups_junction_name` aatag
            INNER JOIN `asset_groups` ag ON `ag`.`id` = `aatag`.`asset_group_id`
            and `aatag`.`$junction_id_name` = :$junction_id_name;"
    );
    $stmt->bindParam(":$junction_id_name", $id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    if ($data) {
        $affected_assets = [];
        
        foreach($data as $item) {
            if ($item['class'] === 'asset')
                $affected_assets[] = try_decrypt($item['name']);
            else $affected_assets[] = "[{$item['name']}]";
        }
        
        return implode(',', $affected_assets);
    }

    return "";
}

function get_assets_and_asset_groups_of_type($id, $type) {

    switch($type) {
        case 'risk':
            $id = $id - 1000;
            $assets_junction_name = 'risks_to_assets';
            $asset_groups_junction_name = 'risks_to_asset_groups';
            $junction_id_name = 'risk_id';
        break;
        case 'assessment_answer':
            $assets_junction_name = 'assessment_answers_to_assets';
            $asset_groups_junction_name = 'assessment_answers_to_asset_groups';
            $junction_id_name = 'assessment_answer_id';
        break;
        case 'questionnaire_answer':
            if(!assessments_extra() || !assessments_extra("questionnaire_answers_to_assets") || !assessments_extra("questionnaire_answers_to_asset_groups"))
            {
                return;
            }
            $assets_junction_name = 'questionnaire_answers_to_assets';
            $asset_groups_junction_name = 'questionnaire_answers_to_asset_groups';
            $junction_id_name = 'questionnaire_answer_id';
        break;
        case 'questionnaire_risk':
            if(!assessments_extra()) return;
            $assets_junction_name = 'questionnaire_risk_to_assets';
            $asset_groups_junction_name = 'questionnaire_risk_to_asset_groups';
            $junction_id_name = 'questionnaire_id';
            $forced_asset_verification_state = true;
        break;
        default:
            return;
    }

    $db = db_open();

    if (encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
    }

    $stmt = $db->prepare("
        SELECT
            *
        FROM (
            SELECT
                `a`.`id`,
                `a`.`name`,
                'asset' as class,
                " . (encryption_extra() ? "`a`.`order_by_name`" : "`a`.`name`") . " as ordr,
                `a`.`verified`
            FROM
                `assets` a
                INNER JOIN `$assets_junction_name` aj ON `aj`.`asset_id` = `a`.`id` and `aj`.`$junction_id_name` = :$junction_id_name
        UNION ALL
            SELECT
                `ag`.`id`,
                `ag`.`name`,
                'group' as class,
                " . (encryption_extra() ? "@rownum := @rownum + 1" : "`ag`.`name`") . " as ordr,
                '1' as verified
            FROM
                `asset_groups` ag
                INNER JOIN `$asset_groups_junction_name` agj ON `agj`.`asset_group_id` = `ag`.`id` and `agj`.`$junction_id_name` = :$junction_id_name
                " . (encryption_extra() ? "JOIN (SELECT @rownum := 0) rn" : "") . "
        ) u
        GROUP BY
            `u`.`class`, `u`.`id`
        ORDER BY
            `u`.`class` ASC, `u`.`ordr` ASC;
    ");

    $stmt->bindParam(":$junction_id_name", $id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    foreach($data as &$item)
        if ($item['class'] === 'asset')
            $item['name'] = try_decrypt($item['name']);

    return $data;
}

function get_list_of_asset_and_asset_group_names($risk_id, $formatted = false) {
    
    global $escaper;

    return array_map(function($item) use ($escaper, $formatted) {
                if ($formatted)
                    return "<span class='{$item['class']}'>" . $escaper->escapeHtml($item['name']) . "</span>";
                return $escaper->escapeHtml($item['name']);
            }, get_assets_and_asset_groups_of_type($risk_id, 'risk'));
    
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

?>
