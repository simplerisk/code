<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/HighchartsPHP/Highchart.php'));
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

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
        add_asset($ipv4addr, $name, $value, $location, $team);
    }
}

/***********************
 * FUNCTION: ADD ASSET *
 ***********************/
function add_asset($ip, $name, $value=5, $location=0, $team=0, $details = "")
{
    $details = try_encrypt($details);
    // Trim whitespace from the name, ip, and value
    $name = trim($name);
    $ip = trim($ip);
    $value = trim($value);

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("INSERT INTO `assets` (ip, name, value, location, team, details) VALUES (:ip, :name, :value, :location, :team, :details) ON DUPLICATE KEY UPDATE `ip`=:ip, `value`=:value, `location`=:location, `team`=:team, `details`=:details;");
    $stmt->bindParam(":ip", $ip, PDO::PARAM_STR, 15);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 200);
    $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
    $stmt->bindParam(":location", $location, PDO::PARAM_INT, 2);
    $stmt->bindParam(":team", $team, PDO::PARAM_INT, 2);
    $stmt->bindParam(":details", $details, PDO::PARAM_STR);
    $return = $stmt->execute();

    // If failed to insert, update the record
    if(!$stmt->rowCount())
    {
        $asset_id = 0;
    }
    else
    {
        $stmt = $db->prepare("SELECT id FROM `assets` WHERE `name`=:name AND `ip`=:ip AND `value`=:value AND `location`=:location AND `team`=:team AND `details`=:details;");
        $stmt->bindParam(":ip", $ip, PDO::PARAM_STR, 15);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, 200);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT, 2);
        $stmt->bindParam(":location", $location, PDO::PARAM_INT, 2);
        $stmt->bindParam(":team", $team, PDO::PARAM_INT, 2);
        $stmt->bindParam(":details", $details, PDO::PARAM_STR);
        $stmt->execute();
        $asset_id = $stmt->fetch(PDO::FETCH_COLUMN);
    }

    // Update the asset_id column in risks_to_assets
    $stmt = $db->prepare("UPDATE `risks_to_assets` INNER JOIN `assets` ON `assets`.name = `risks_to_assets`.asset SET `risks_to_assets`.asset_id = `assets`.id;");
    $stmt->execute();

    // Close the database connection
    db_close($db);

    $message = "An asset named \"{$name}\" was added by username \"" . $_SESSION['user'] . "\".";
    write_log($asset_id , $_SESSION['uid'], $message, "asset");
    
    // Return success or failure
    return $asset_id;
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

    // Delete the risks_to_assets entry
    $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE `asset_id`=:id;");
    $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    $message = "An asset named \"" . $name . "\" was deleted by username \"" . $_SESSION['user'] . "\".";
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
        if ($asset['team'] == 0)
        {
            $asset['team'] = "N/A";
        }
        else $asset['team'] = get_name_by_value("team", $asset['team']);
        
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
                    ". $escaper->escapeHtml($asset['team']) ."
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

    echo "<table class=\"table table-bordered table-condensed sortable\">\n";

    // Display the table header
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" width=\"75\"><input class=\"hidden-checkbox\" id=\"delete-all\" type=\"checkbox\" onclick=\"checkAll(this)\" /><label for=\"delete-all\" >" . $escaper->escapeHtml($lang['Delete']) . "</label></th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetName']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['IPAddress']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetValuation']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['SiteLocation']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['Team']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetDetails']) . "</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // Get the array of assets
    $assets = get_entered_assets();

    // For each asset
    foreach ($assets as $asset)
    {
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
        if ($asset['team'] == 0)
        {
            $asset['team'] = "N/A";
        }
        else $asset['team'] = get_name_by_value("team", $asset['team']);

        echo "<tr>\n";
        echo "<td align=\"center\">\n";
        echo "<input id=\"".$asset['id']."\" class=\"hidden-checkbox\" type=\"checkbox\" name=\"assets[]\" value=\"" . $escaper->escapeHtml($asset['id']) . "\" /> <label for=\"".$asset['id']."\"></label> \n";
        echo "</td>\n";
        echo "<td>" . $escaper->escapeHtml($asset['name']) . "</td>\n";
        echo "<td>" . $escaper->escapeHtml($asset['ip']) . "</td>\n";
        echo "<td>" . $escaper->escapeHtml(get_asset_value_by_id($asset['value'])) . "</td>\n";
        echo "<td>" . $escaper->escapeHtml($asset['location']) . "</td>\n";
        echo "<td>" . $escaper->escapeHtml($asset['team']) . "</td>\n";
        echo "<td>" . $escaper->escapeHtml(try_decrypt($asset['details'])) . "</td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";
}

/********************************
 * FUNCTION: GET ENTERED ASSETS *
 ********************************/
function get_entered_assets()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assets` ORDER BY name;");
    $stmt->execute();

    // Store the list in the assets array
    $assets = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the array of assets
    return $assets;
}

/********************************
 * FUNCTION: GET ENTERED ASSETS *
 ********************************/
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

/********************************
 * FUNCTION: TAG AFFECTED ASSETS TO RISK *
 ********************************/
function tag_assets_to_risk($risk_id, $assets, $entered_assets=false)
{
    if($entered_assets === false){
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
                if(in_array($asset, $entered_asset)){
                    $asset_id = $entered_asset['id'];
                    break;
                }
            }
            if(!$asset_id){
                $asset_id = add_asset('', $asset);
            }

            // Add the new assets for this risk
            $stmt = $db->prepare("INSERT INTO `risks_to_assets` (`risk_id`, `asset_id`, `asset`) VALUES (:risk_id, :asset_id, :asset)");
            $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
            $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
            $stmt->bindParam(":asset", $asset, PDO::PARAM_STR, 200);
            $stmt->execute();
        }
    }

    // Add the asset_id column to risks_to_assets
    $stmt = $db->prepare("UPDATE `risks_to_assets` INNER JOIN `assets` ON `assets`.name = `risks_to_assets`.asset SET `risks_to_assets`.asset_id = `assets`.id;");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************
 * FUNCTION: GET ASSETS FOR RISK *
 *********************************/
function get_assets_for_risk($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Get the assets
    $stmt = $db->prepare("SELECT asset FROM `risks_to_assets` WHERE risk_id = :risk_id ORDER BY asset");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the assets array
        $assets = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the assets array
    return $assets;
}

/********************************
 * FUNCTION: GET LIST OF ASSETS *
 ********************************/
function get_list_of_assets($risk_id, $trailing_comma = true)
{
    // Set the string to empty to start
    $string = "";

    $id = (int)$risk_id - 1000;
    
    // Get the assets for the risk
    $assets = get_assets_for_risk($id);

    // For each asset
    foreach ($assets as $asset)
    {
        $string .= $asset['asset'] . ", ";
    }

    // If we don't want a trailing comma
    if (!$trailing_comma)
    {
        $string = mb_substr($string, 0, -2);
    }

    // Return the string of assets
    return $string;
}

/**********************************
 * FUNCTION: GET UNENTERED ASSETS *
 **********************************/
function get_unentered_assets()
{
        // Open the database connection
        $db = db_open();

        // Get the assets
        $stmt = $db->prepare("SELECT DISTINCT asset AS name FROM risks_to_assets WHERE asset_id = 0");
        $stmt->execute();

        // Store the list in the assets array
        $assets = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

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

    echo "<table class=\"table table-bordered table-condensed sortable\">\n";

    // Display the table header
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetName']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['IPAddress']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetValuation']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['SiteLocation']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['Team']) . "</th>\n";
    echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetDetails']) . "</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // Get the array of assets
    $assets = get_entered_assets();

    // For each asset
    foreach ($assets as $asset)
    {
        // If the IP address is not valid
        if (!preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $asset['ip']))
        {
                $asset['ip'] = "N/A";
        }

        echo "<tr>\n";
        echo "<td>" . $escaper->escapeHtml($asset['name']) . "</td>\n";
        echo "<td>" . $escaper->escapeHtml($asset['ip']) . "</td>\n";
        echo "<td>\n";
        echo "<input type=\"hidden\" name=\"ids[]\" value=\"" . $escaper->escapeHtml($asset['id']) . "\" />\n";
        create_asset_valuation_dropdown("values[]", $asset['value']);
        echo "</td>\n";
        echo "<td>\n";
        create_dropdown("location", $asset['location'], "locations[]");
        echo "</td>\n";
        echo "<td>\n";
        create_dropdown("team", $asset['team'], "teams[]");
        echo "</td>\n";
        echo "<td>\n";
        echo "<textarea name='details[]'>". $escaper->escapeHtml(try_decrypt($asset['details'])) ."</textarea>\n";
        echo "</td>\n";
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
    $stmt->bindParam(":location", $location, PDO::PARAM_INT, 2);
    $stmt->bindParam(":team", $team, PDO::PARAM_INT, 2);
    $stmt->bindParam(":details", $details, PDO::PARAM_STR);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

    $name = get_asset_name($id);

    $message = "An asset named \"" . $name . "\" was modified by username \"" . $_SESSION['user'] . "\".";
    write_log($id, $_SESSION['uid'], $message, "asset");
    
        // Close the database connection
        db_close($db);
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

    return $name;

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
function update_asset_value($id, $min_value, $max_value)
{
        // Open the database connection
        $db = db_open();

    // Set the value for the level
    $stmt = $db->prepare("UPDATE asset_values SET min_value = :min_value, max_value = :max_value WHERE id = :id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT, 2);
    $stmt->bindParam(":min_value", $min_value, PDO::PARAM_INT, 11);
    $stmt->bindParam(":max_value", $max_value, PDO::PARAM_INT, 11);
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
function create_asset_valuation_dropdown($name, $selected = NULL)
{
	global $escaper;

        // Open the database connection
        $db = db_open();

        // Get the asset values
        $stmt = $db->prepare("SELECT * FROM asset_values;");
        $stmt->execute();
        $values = $stmt->fetchAll();

	echo "<select id=\"" . $escaper->escapeHtml($name) . "\" name=\"" . $escaper->escapeHtml($name) . "\" class=\"form-field\" style=\"width:auto;\" >\n";

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

		if ($value['min_value'] === $value['max_value'])
		{
			echo $escaper->escapeHtml(get_setting("currency")) . $escaper->escapeHtml(number_format($value['min_value']));
		}
		else
		{
			echo $escaper->escapeHtml(get_setting("currency")) . $escaper->escapeHtml(number_format($value['min_value'])) . " to " . $escaper->escapeHtml(get_setting("currency")) . $escaper->escapeHtml(number_format($value['max_value']));
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
function get_asset_value_by_id($id="")
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

/*********************************
 * FUNCTION : ASSETS FOR RISK ID *
 *********************************/
function assets_for_risk_id($risk_id)
{
        // Open the database connection
        $db = db_open();

        // Update the default asset valuation
        $stmt = $db->prepare("SELECT a.id, a.ip, a.name, a.value, a.location, a.team, a.created FROM `assets` a LEFT JOIN `risks_to_assets` b ON a.name = b.asset WHERE b.risk_id=:risk_id");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT, 11);
        $stmt->execute();

        $assets = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // Return the assets array
        return $assets;
}

/*****************************************
 * FUNCTION: ASSET VALUATION FOR RISK ID *
 *****************************************/
function asset_valuation_for_risk_id($risk_id)
{
    // Get the asset valuation array
    $asset_valuation_array = get_asset_valuation_array();

    // Get the assets for the risk
    $assets = assets_for_risk_id($risk_id);

    // Initialize the totals
    //$min_total = 0;
    $max_total = 0;

    // For each asset
    foreach ($assets as $asset)
    {
        // Get the asset value id
        $value = (int)$asset['value'];

        // Calculate the new total
        //$min_value = $asset_valuation_array[($value-1)]['min_value'];
        $max_value = $asset_valuation_array[($value-1)]['max_value'];
        //$min_total = $min_total + $min_value;
        $max_total = $max_total + $max_value;
    }

    // Return the asset valuation
    //return "$" . number_format($min_total) . " to $" . number_format($max_total);
    return $max_total;
}

?>
