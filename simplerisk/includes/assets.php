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

		// Add the asset
		add_asset($ipv4addr, $name);
	}
}

/***********************
 * FUNCTION: ADD ASSET *
 ***********************/
function add_asset($ip, $name)
{
	// Trim whitespace from the name
	$name = trim($name);

        // Open the database connection
        $db = db_open();

	$stmt = $db->prepare("INSERT INTO `assets` (ip, name) VALUES (:ip, :name) ON DUPLICATE KEY UPDATE `name`=:name;");
        $stmt->bindParam(":ip", $ip, PDO::PARAM_STR, 15);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, 200);
        $return = $stmt->execute();

        // Close the database connection
        db_close($db);

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

        $stmt = $db->prepare("DELETE FROM `assets` WHERE `id`=:id;");
        $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
        $return = $stmt->execute();

        // Close the database connection
        db_close($db);

        // Return success or failure
        return $return;
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
	echo "<th align=\"left\" width=\"75\"><input type=\"checkbox\" onclick=\"checkAll(this)\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['Delete']) . "</th>\n";
	echo "<th align=\"left\">" . $escaper->escapeHtml($lang['AssetName']) . "</th>\n";
	echo "<th align=\"left\">" . $escaper->escapeHtml($lang['IPAddress']) . "</th>\n";
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
		echo "<td align=\"center\">\n";
		echo "<input type=\"checkbox\" name=\"assets[]\" value=\"" . $escaper->escapeHtml($asset['id']) . "\" />\n";
		echo "</td>\n";
		echo "<td>" . $escaper->escapeHtml($asset['name']) . "</td>\n";
		echo "<td>" . $escaper->escapeHtml($asset['ip']) . "</td>\n";
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
 * FUNCTION: TAG ASSETS TO RISK *
 ********************************/
function tag_assets_to_risk($risk_id, $assets)
{
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
			// Add the new assets for this risk
			$stmt = $db->prepare("INSERT INTO `risks_to_assets` (`risk_id`, `asset`) VALUES (:risk_id, :asset)");
			$stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
			$stmt->bindParam(":asset", $asset, PDO::PARAM_STR, 200);
			$stmt->execute();
		}
	}
	
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

	// Get the assets for the risk
	$assets = get_assets_for_risk($risk_id-1000);

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
        $stmt = $db->prepare("SELECT asset AS name FROM risks_to_assets a LEFT JOIN assets b ON a.asset = b.name WHERE b.name IS NULL");
        $stmt->execute();

        // Store the list in the assets array
        $assets = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // Return the assets array
        return $assets;
}


?>
