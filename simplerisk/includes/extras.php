<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/services.php'));

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/***************************************************
 * FUNCTION: AVAILABLE EXTRAS                      *
 * Returns an array of available SimpleRisk Extras *
 ***************************************************/
function available_extras()
{
    // The available SimpleRisk Extras
    $extras = array(
        array("short_name" => "advanced_search", "long_name" => "Advanced Search Extra"),
        array("short_name" => "api", "long_name" => "API Extra"),
        array("short_name" => "artificial_intelligence", "long_name" => "Artificial Intelligence Extra"),
        array("short_name" => "assessments", "long_name" => "Risk Assessment Extra"),
        array("short_name" => "authentication", "long_name" => "Custom Authentication Extra"),
        array("short_name" => "complianceforgescf", "long_name" => "ComplianceForge SCF Extra"),
        array("short_name" => "customization", "long_name" => "Customization Extra"),
        array("short_name" => "encryption", "long_name" => "Encrypted Database Extra"),
        array("short_name" => "import-export", "long_name" => "Import-Export Extra"),
        array("short_name" => "incident_management", "long_name" => "Incident Management Extra"),
        array("short_name" => "jira", "long_name" => "Jira Integration Extra"),
        array("short_name" => "notification", "long_name" => "Email Notification Extra"),
        array("short_name" => "organizational_hierarchy", "long_name" => "Organizational Hierarchy Extra"),
        array("short_name" => "separation", "long_name" => "Team-Based Separation Extra"),
        array("short_name" => "ucf", "long_name" => "Unified Compliance Framework (UCF) Extra"),
        array("short_name" => "upgrade", "long_name" => "Upgrade Extra"),
	    array("short_name" => "vulnmgmt", "long_name" => "Vulnerability Management Extra"),
    );

    // Return the array of available Extras
    return $extras;
}

/**********************************************************
 * FUNCTION: AVAILABLE EXTRA SHORT NAMES                  *
 * Returns the short names of available SimpleRisk Extras *
 **********************************************************/
function available_extra_short_names()
{
    // Get the list of available extras
    $extras = available_extras();

    // Get the values from the short_name column
    $extra_short_names = array_column($extras, "short_name");

    // Return the list of short name values
    return $extra_short_names;
}

/*********************************************************
 * FUNCTION: EXTRA CURRENT VERSION                       *
 * Returns the current version of the extra if installed *
 *********************************************************/
function core_extra_current_version($extra)
{
	// Get the list of available extra names
	$available_extras = available_extra_short_names();

	// If the provided extra name is not in the list of available extras
	if (!in_array($extra, $available_extras))
	{
		return "N/A";
	}
	// The provided extra name is in the list of available extras
	else
	{
		// Get the path to the extra
		$path = realpath(__DIR__ . "/../extras/$extra/index.php");

		// If the extra is installed
		if (file_exists($path))
		{
			// Include the extra
			require_once($path);

			// Return the extra version
			switch ($extra) {
                case "advanced_search":
                    return ADVANCED_SEARCH_EXTRA_VERSION;
                case "api":
                    return API_EXTRA_VERSION;
                case "artificial_intelligence":
                    return ARTIFICIAL_INTELLIGENCE_EXTRA_VERSION;
                case "assessments":
                    return ASSESSMENTS_EXTRA_VERSION;
                case "authentication":
                    return AUTHENTICATION_EXTRA_VERSION;
                case "complianceforgescf":
                    return COMPLIANCEFORGE_SCF_EXTRA_VERSION;
                case "customization":
                    return CUSTOMIZATION_EXTRA_VERSION;
                case "encryption":
                    return ENCRYPTION_EXTRA_VERSION;
                case "import-export":
                    return IMPORTEXPORT_EXTRA_VERSION;
                case "incident_management":
                    return INCIDENT_MANAGEMENT_EXTRA_VERSION;
                case "jira":
                    return JIRA_EXTRA_VERSION;
                case "notification":
                    return NOTIFICATION_EXTRA_VERSION;
                case "organizational_hierarchy":
                    return ORGANIZATIONAL_HIERARCHY_EXTRA_VERSION;
                case "separation":
                    return SEPARATION_EXTRA_VERSION;
                case "ucf":
                    return UCF_EXTRA_VERSION;
                case "upgrade":
                    return UPGRADE_EXTRA_VERSION;
                case "vulnmgmt":
                    return VULNMGMT_EXTRA_VERSION;
                default:
                    return "N/A";
			}
		}
		else return "N/A";
	}
}

/**********************************************************
 * FUNCTION: EXTRA ACTIVATED                              *
 * Returns whether the specified extra has been activated *
 **********************************************************/
function core_extra_activated($extra)
{
	// Return the extra activated
        switch ($extra) {
            case "advanced_search":
                return advanced_search_extra();
            case "api":
                return api_extra();
            case "artificial_intelligence":
                return artificial_intelligence_extra();
            case "assessments":
                return assessments_extra();
            case "authentication":
                return custom_authentication_extra();
            case "complianceforgescf":
                return complianceforge_scf_extra();
            case "customization":
                return customization_extra();
            case "encryption":
                return encryption_extra();
            case "import-export":
                return import_export_extra();
            case "incident_management":
                return incident_management_extra();
            case "jira":
                return jira_extra();
            case "notification":
                return notification_extra();
            case "organizational_hierarchy":
                return organizational_hierarchy_extra();
            case "separation":
                return team_separation_extra();
            case "ucf":
                return ucf_extra();
            case "upgrade":
                return true;
            case "vulnmgmt":
                return vulnmgmt_extra();
            default:
                return false;
        }
}

/********************************************************
 * FUNCTION: EXTRA ACTIVATED LINK                       *
 * Returns the link for a purchased and activated extra *
 ********************************************************/
function core_extra_activated_link($extra)
{
	global $lang;
	global $escaper;

    // Return the extra activated
    switch ($extra) {
        case "advanced_search":
            return "<a class='text-info m-l-10' href='advanced_search.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "api":
            return "<a class='text-info m-l-10' href='api.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "artificial_intelligence":
            return "<a class='text-info m-l-10' href='artificial_intelligence.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "assessments":
            return "<a class='text-info m-l-10' href='assessments.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "authentication":
            return "<a class='text-info m-l-10' href='authentication.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "complianceforgescf":
            return "<a class='text-info m-l-10' href='complianceforge_scf.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "customization":
            return "<a class='text-info m-l-10' href='customization.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "encryption":
            return "<a class='text-info m-l-10' href='encryption.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "import-export":
            return "<a class='text-info m-l-10' href='importexport.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "incident_management":
            return "<a class='text-info m-l-10' href='incidentmanagement.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "jira":
            return "<a class='text-info m-l-10' href='jira.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "notification":
            return "<a class='text-info m-l-10' href='notification.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "organizational_hierarchy":
            return "<a class='text-info m-l-10' href='organizational_hierarchy.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "separation":
            return "<a class='text-info m-l-10' href='separation.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "ucf":
            return "<a class='text-info m-l-10' href='ucf.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        case "upgrade":
                return "";
		case "vulnmgmt":
		    return "<a class='text-info m-l-10' href='vulnmgmt.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
        default:
            return "";
    }
}

/*******************************************************
 * FUNCTION: DISPLAY UPGRADE EXTRAS                    *
 * Displays the list of available and installed extras *
 * on the Register & Upgrade page.                     *
 *******************************************************/
function core_display_upgrade_extras()
{
	global $escaper;
	global $lang;

	// Check all purchases in one web service call
	$purchases = core_check_all_purchases();

	// Get the list of available extras
	$available_extras = available_extras();

	// Display the table header
	echo "
        <p><h4>" . $escaper->escapeHtml($lang['SimpleRiskExtras']) . "</h4></p>
        <table class='table table-striped border header'>
            <thead>
                <tr>
                    <th>Extra Name</th>
                    <th>Purchased</th>
                    <th>Expires</th>
                    <th>Installed</th>
                    <th>Activated</th>
                    <th>Version</th>
                    <th>Latest Version</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>";

    // If we were able to obtain the purchases
    if ($purchases != false)
    {
		// For each available extra
		foreach ($available_extras as $extra)
		{
			// If this is the Upgrade or ComplianceForge SCF Extra
			if ($extra['short_name'] == "upgrade" || $extra['short_name'] == "complianceforgescf")
			{
				// Set purchased to true
				$purchased = true;
				$expires = "Unlimited";
			}
			else
			{
				$extras_xml = $purchases->{"extras"};
				$extra_xml = $extras_xml->{$extra['short_name']};
				$purchased = (boolean)json_decode(strtolower($extra_xml->{"purchased"}->__toString()));
				$disabled = (boolean)json_decode(strtolower($extra_xml->{"disabled"}->__toString()));
				$deleted = (boolean)json_decode(strtolower($extra_xml->{"deleted"}->__toString()));

				// If the extra was purchased
				if ($purchased)
				{
					// Get the expiration date
					$expires = $extra_xml->{"expires"}->__toString();

					// If the exipration date is not set
					if ($expires == "0000-00-00 00:00:00")
					{
						$expires = $escaper->escapeHtml("N/A");
					}
					// If the expiration date has passed
					else if ($expires < date('Y-m-d h:i:s'))
					{
						$expires = "<font color='red'><b>Expired</b></font>";
					}
					else $expires = "<font color='green'><b>" . $escaper->escapeHtml(substr($expires, 0, 10)) . "</b></font>";
				}
				else $expires = "N/A";
			}

			// Check if the extra is installed
			$installed = core_is_installed($extra['short_name']);

			// Check if the extra is activated
			$activated = core_extra_activated($extra['short_name']);

			// If the extra is purchased and activated
			if ($purchased && $activated)
			{
				$activated_link = core_extra_activated_link($extra['short_name']);
			}
			else $activated_link = "";

			// Get the version information
			$version = core_extra_current_version($extra['short_name']);
			$latest_version = latest_version($extra['short_name']);

			// Get the action button
			$action_button = core_get_action_button($extra['short_name'], $purchased, $installed, $activated, $version, $latest_version);

			// Display the table row
			echo "
                <tr>
                    <td>{$escaper->escapeHtml($extra['long_name'])}</td>
                    <td><input class='form-check-input' type='checkbox'" . ($purchased ? " checked" : "") . " /></td>
                    <td>{$expires}</td>
                    <td><input class='form-check-input' type='checkbox'" . ($installed ? " checked" : "") . " /></td>
                    <td><input class='form-check-input' type='checkbox'" . ($activated ? " checked" : "") . " />{$activated_link}</td>
                    <td><b>{$escaper->escapeHtml($version)}</b></td>
                    <td><b>{$escaper->escapeHtml($latest_version)}</b></td>
                    <td><b>{$action_button}</b></td>
                </tr>";
		}
	}
	// We were unable to obtain the purchases from the server
	else
	{
		// Display the table row
		echo "  <tr>
                    <td colspan='8'><b>{$escaper->escapeHtml($lang['UnableToCommunicateWithTheSimpleRiskServer'])}</b></td>
                </tr>";
	}

	echo "  </tbody>
        </table>";
}

/***************************************************************
 * FUNCTION: IS INSTALLED                                      *
 * Displays whether the extra name provided has been installed *
 ***************************************************************/
function core_is_installed($extra_name) {
    global $available_extras;
    return in_array($extra_name, $available_extras) && file_exists(realpath(__DIR__ . "/../extras/{$extra_name}/index.php"));
}

/***************************************************************
 * FUNCTION: IS PURCHASED                                      *
 * Displays whether the extra name provided has been purchased *
 ***************************************************************/
function core_is_purchased($extra)
{
    //They're purchased by default
    if (in_array($extra, ['upgrade', 'complianceforgescf']))
        return true;

    if (!empty($GLOBALS['purchased_extras'])) {
        if (in_array($extra, $GLOBALS['purchased_extras'])) {
            return true;
        }
    } else {
        $GLOBALS['purchased_extras'] = [];
    }

    // Get the instance identifier
    $instance_id = get_setting("instance_id");

    // Get the services API key
    $services_api_key = get_setting("services_api_key");

    // Create the data to send
    $parameters = array(
        'action' => 'check_purchase',
        'instance_id' => $instance_id,
        'api_key' => $services_api_key,
        'extra_name' => $extra,
    );

    write_debug_log("Checking for purchase of " . $extra . " for instance ID " . $instance_id);

    // Ask the service if the extra is purchased
    $response = simplerisk_service_call($parameters);
    $return_code = $response['return_code'];

    // If the SimpleRisk service call returned false
    if ($return_code !== 200)
    {
        write_debug_log("Unable to communicate with the SimpleRisk services API");

        // Return false
        return false;
    }
    // If we have valid results from the service call
    else
    {
        $results = $response['response'];
        $results = array($results);
        $regex_pattern = "/<result>1<\/result>/";

        foreach ($results as $line)
        {
            // If the service returned a success
            if (preg_match($regex_pattern, $line, $matches))
            {
                $GLOBALS['purchased_extras'][] = $extra;
                return true;
            }
            else return false;
        }
    }
}

/***************************************************************
 * FUNCTION: CHECK ALL PURCHASES                               *
 * Calls the services API to get all purchases at once         *
 ***************************************************************/
function core_check_all_purchases()
{
    // Get the instance identifier
    $instance_id = get_setting("instance_id");

    // Get the services API key
    $services_api_key = get_setting("services_api_key");

    // Create the parameters to send
    $parameters = array(
        'action' => 'check_all_purchases',
        'instance_id' => $instance_id,
        'api_key' => $services_api_key,
    );

    write_debug_log("Checking for all purchases for instance ID " . $instance_id);

    // Configuration for the SimpleRisk service call
    if (defined('SERVICES_URL'))
    {
        $url = SERVICES_URL . "/index.php";
    } 
    else $url = "https://services.simplerisk.com/index.php";

    // Set the HTTP options
    $http_options = [
        'method' => 'POST',
        'header' => [
            "Content-Type: application/x-www-form-urlencoded",
        ],
    ];

    // If SSL certificate checks are enabled for external requests
    if (get_setting('ssl_certificate_check_external') == 1)
    {
        // Verify the SSL host and peer
        $validate_ssl = true;
    }
    else $validate_ssl = false;

    // Make the services call
    $response = fetch_url_content("stream", $http_options, $validate_ssl, $url, $parameters);
    $return_code = $response['return_code'];
    $result = $response['response'];

    // If we were unable to connect to the URL
    if($return_code !== 200)
    {
        write_debug_log("SimpleRisk was unable to connect to " . $url);

	    // Return false
	    return false;
    }
    // We were able to connect to the URL
    else
    {
        write_debug_log("SimpleRisk successfully connected to " . $url);

	    // Return the XML results
	    $xml = simplexml_load_string($result);
	    return $xml;
    }
}

/*************************************************************
 * FUNCTION: GET ACTION BUTTON                               *
 * Display an Install, Upgrade, Activate, or Purchase button *
 *************************************************************/
function core_get_action_button($extra_name, $purchased, $installed, $activated, $version, $latest_version)
{
    global $escaper;
    global $lang;

    // Default button is N/A
    $action_button = "N/A";

    // Check the Extra Name
    switch ($extra_name)
    {
        case "upgrade":
            $button_name = "get_upgrade_extra";
            break;
        case "complianceforge":
            $button_name = "get_complianceforge_extra";
            $action_link = "complianceforge.php";
            break;
        case "complianceforgescf":
            $button_name = "get_complianceforge_scf_extra";
            $action_link = "complianceforge_scf.php";
            break;
        case "authentication":
            $button_name = "get_authentication_extra";
            $action_link = "authentication.php";
            break;
        case "encryption":
            $button_name = "get_encryption_extra";
            $action_link = "encryption.php";
            break;
        case "import-export":
            $button_name = "get_importexport_extra";
            $action_link = "importexport.php";
            break;
        case "incident_management":
            $button_name = "get_incident_management_extra";
            $action_link = "incidentmanagement.php";
            break;
        case "notification":
            $button_name = "get_notification_extra";
            $action_link = "notification.php";
            break;
        case "separation":
            $button_name = "get_separation_extra";
            $action_link = "separation.php";
            break;
        case "assessments":
            $button_name = "get_assessments_extra";
            $action_link = "assessments.php";
            break;
        case "governance":
            $button_name = "get_governance_extra";
            $action_link = "governance.php";
            break;
        case "api":
            $button_name = "get_api_extra";
            $action_link = "api.php";
            break;
        case "customization":
            $button_name = "get_customization_extra";
            $action_link = "customization.php";
            break;
        case "advanced_search":
            $button_name = "get_advanced_search_extra";
            $action_link = "advanced_search.php";
            break;
        case "jira":
            $button_name = "get_jira_extra";
            $action_link = "jira.php";
            break;
        case "ucf":
            $button_name = "get_ucf_extra";
            $action_link = "ucf.php";
            break;
        case "organizational_hierarchy":
            $button_name = "get_organizational_hierarchy_extra";
            $action_link = "organizational_hierarchy.php";
            break;
	    case "vulnmgmt":
	        $button_name = "get_vulnmgmt_extra";
	        $action_link = "vulnmgmt.php";
            break;
        case "artificial_intelligence":
            $button_name = "get_artificial_intelligence_extra";
            $action_link = "artificial_intelligence.php";
            break;
    }

    // If the Extra has been purchased
    if ($purchased)
    {
        // If the Extra is not installed
        if (!$installed)
        {
            // Make the Install action button
            $action_button = "
                <form style='display: inline;' name='install_extras' method='post' action=''>
                    <button type='submit' name='" . $button_name . "' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Install']) . "</button>
                </form>";
        }
        // Otherwise, the Extra is installed
        else
        {
            // If the Extra is not activated
            if (!$activated)
            {
                // Display the Activate button
                $action_button = "
                <form style='display: inline;' name='install_extras' method='post' action='" . $action_link . "'>
                    <button type='submit' name='activate_extra' class='btn btn-submit'>". $escaper->escapeHtml($lang['Activate']) ."</button>
                </form>";
            }
        }
    }
    // Otherwise, the Extra has not been purchased
    else
    {
        $action_button = "
                <form style='display: inline;' action='https://www.simplerisk.com/extras' target='_blank' method='post'>
                    <button type='submit' name='purchase_extra' class='btn btn-submit'>" . $escaper->escapeHtml($lang['Purchase']) . "</button>
                </form>";
    }

    // Return the action button
    return $action_button;
}

/*******************************************************
 * FUNCTION: GATHER EXTRA UPGRADES                     *
 * Used by the one-click upgrade process to get a list *
 * of Extras that needs to be upgraded                 *
 *******************************************************/
function core_gather_extra_upgrades() {

    $available_extras = available_extra_short_names();

    $upgradeable = [];
    foreach($available_extras as $extra) {
        if (core_is_purchased($extra) &&
            core_is_installed($extra) &&
            core_extra_current_version($extra) < latest_version($extra)) {
            // Have to be upgraded    
            $upgradeable[] = $extra;
        }
    }
    
    return $upgradeable;
}

/****************************************************
 * FUNCTION: UPGRADE EXTRAS                         *
 * Used by the one-click upgrade process to upgrade *
 * Extras to the latest version                     *
 ****************************************************/
function core_upgrade_extras($extras_to_upgrade = false) {

    global $lang;

    if ($extras_to_upgrade === false)
        $extras_to_upgrade = core_gather_extra_upgrades();

    stream_write($lang['UpdateExtrasStarted']);
    foreach($extras_to_upgrade as $extra) {
        stream_write(_lang('UpdateExtrasExtraUpdateStarted', array('extra' => $extra)));
        if (!download_extra($extra, true)) {
            stream_write_error(_lang('UpdateExtrasUpdateExtraFailed', array('extra' => $extra)));
            return 0;
        }
    }
    stream_write($lang['UpdateExtrasSuccessful']);
    return 1;
}

/*********************************************************
 * FUNCTION: EXTRA SIMPLERISK VERSION COMPATIBILE        *
 * Checks to see if the Extra is compatible with the     *
 * running version of SimpleRisk                         *
 *********************************************************/
function extra_simplerisk_version_compatible($extra)
{
	write_debug_log("Checking version comptability for the \"" . $extra . "\" extra.");

	// Get the list of available extra names
	$available_extras = available_extra_short_names();

	// If the provided extra name is not in the list of available extras
	if (!in_array($extra, $available_extras))
	{
		// Return false
		return false;
	}
	// The provided extra name is in the list of available extras
	else
	{
		// URL for extra compatibility checks
		if (defined('UPDATES_URL'))
		{   
			$url = UPDATES_URL . '/extra_compatibility.xml';
		}
		else $url = 'https://raw.githubusercontent.com/simplerisk/updates.simplerisk.com/updates.simplerisk.com/extra_compatibility.xml';

		// Get the current version of SimpleRisk
		$simplerisk_version = current_version("app");
		write_debug_log("The current version of SimpleRisk is " . $simplerisk_version);

		// Get the current version of the extra
		$extra_version = core_extra_current_version($extra);
		write_debug_log("Current version of this extra is " . $extra_version);

        // Set the HTTP options
        $http_options = [
            'method' => 'GET',
            'header' => [
                "Content-Type: application/x-www-form-urlencoded",
            ],
        ];

        // If SSL certificate checks are enabled for the SimpleRisk API
        if (get_setting('ssl_certificate_check_external') == 1)
        {
            // Verify the SSL host and peer
            $validate_ssl = true;
        }
        else $validate_ssl = false;

		write_debug_log("Fetching content from the extra compatibility page.");

		// Set the default socket timeout to 5 seconds
		ini_set('default_socket_timeout', 5);

        // Make the services call
        $response = fetch_url_content("stream", $http_options, $validate_ssl, $url);
        $return_code = $response['return_code'];

		// If we were unable to connect to the URL
        if ($return_code !== 200)
		{
			write_debug_log("Unable to connect to " . $url);
			return false;
		}
		// We were able to connect to the URL
		else
		{
			// Get the content of the extra compatibility page
			$extra_compatibility_page = $response['response'];

			// Parse the XML
			$ob = simplexml_load_string($extra_compatibility_page);

			// Encode the XML as JSON
			$json = json_encode($ob);

			// Decode the JSON as an array
			$array = json_decode($json, true);

			// For each extra entry in the array
			foreach($array[$extra]['extra'] as $key => $value)
			{
				$array_extra_version = $value['@attributes']['version'];
				$array_simplerisk_version = $value['appversion'];

				// If we have a match
				if ($simplerisk_version == $array_simplerisk_version && $extra_version == $array_extra_version)
				{
					write_debug_log("The current version of SimpleRisk is compatible with this Extra.");
					return true;
				}
			}

			// If we never found our match
			write_debug_log("The current version of SimpleRisk is not compatible with this Extra.");
			return false;
		}
	}
}

/**************************************
 * FUNCTION: SIMPLERISK LICENSE CHECK *
 **************************************/
function simplerisk_license_check()
{
	write_debug_log("Running license check.");

	// Get if the instance is registered
	$registration_registered = get_setting('registration_registered');

	// If the registration is registered
	if ($registration_registered == 1)
	{
		write_debug_log("The instance is registered.");

        	// Get the hosting tier setting
        	$hosting_tier = get_setting('hosting_tier');

        	// If the hosting tier is not set then this is an on-premise instance
        	if (!$hosting_tier)
        	{
			write_debug_log("The instance is on-premise.");

			// Check the license against what is installed
			simplerisk_license_check_purchases();

			// Set the last checked date to now
			$now = time();
			update_setting("license_check_date", $now);
        	}
	}
}

/************************************************
 * FUNCTION: SIMPLERISK LICENSE CHECK PURCHASES *
 * Check that Extras align with licenses        *
 ************************************************/
function simplerisk_license_check_purchases()
{
	// Set a session value for the license check
	$_SESSION['license_check'] = "pass";

	// Check the purchases
	$purchases = core_check_all_purchases();

	// If we were able to obtain the purchases
	if ($purchases != false)
	{
		// Get the support information
		$support_xml = $purchases->{"support"};
		$support_purchased = (boolean)json_decode(strtolower($support_xml->{"purchased"}->__toString()));

		// If support is purchased
		if ($support_purchased == "true")
		{
			// Add the support license to the session
			$_SESSION['support'] = "true";
		}
		else $_SESSION['support'] = "false";

		// Get the list of available SimpleRisk Extras
		$extras = available_extras();

		// For each available Extra
		foreach ($extras as $extra)
		{
			// If this is not the Upgrade or ComplianceForge SCF Extra
			if ($extra['short_name'] != "upgrade" && $extra['short_name'] != "complianceforgescf")
			{
				// Get the license information
				$extras_xml = $purchases->{"extras"};
				$extra_xml = $extras_xml->{$extra['short_name']};
				$purchased = (boolean)json_decode(strtolower($extra_xml->{"purchased"}->__toString()));
				$expires = $extra_xml->{"expires"}->__toString();
				$disabled = (boolean)json_decode(strtolower($extra_xml->{"disabled"}->__toString()));
				$deleted = (boolean)json_decode(strtolower($extra_xml->{"deleted"}->__toString()));

				// Check if the extra is installed
				$installed = core_is_installed($extra['short_name']);

				// Check if the extra is activated
				$activated = core_extra_activated($extra['short_name']);

				// If the Extra is activated and should be disabled
				if ($activated && $disabled)
				{
					write_debug_log("SimpleRisk says this Extra should be disabled: " . $extra['short_name']);

					// Deactivate the Extra
					core_deactivate_extra($extra['short_name']);
				}

				// If the Extra is installed and should be deleted
				if ($installed && $deleted)
				{
					write_debug_log("SimpleRisk says this Extra should be deleted: " . $extra['short_name']);

					// Delete the Extra
					core_delete_extra($extra['short_name']);
				}

				// If the Extra is installed and activated
				if ($installed && $activated)
				{
					// If the expiration date is set
					if ($expires != "0000-00-00 00:00:00")
					{
						// If the expiration date has passed
						if ($expires < date('Y-m-d h:i:s'))
						{
							// Set the license to expired
							$expired = true;
						}
						else $expired = false;					
					}
					else $expired = false;

					// Get the name of the setting to check for a license failure
					$license_check_fail_date_name = "license_check_fail_date_" . $extra['short_name'];

					// Get the current date and time
					$now = time();
				
					// If the Extra is not purchased or has expired
					if (!$purchased || $expired)
					{
						write_debug_log("Extra not purchased or expired: " . $extra['short_name']);

						// Set the session value for the license check to failed
						$_SESSION['license_check'] = "fail";

						// Check if we already have a license failed date
						$license_check_fail_date = get_setting($license_check_fail_date_name);

						// If we do not have a license failed date
						if (!$license_check_fail_date)
						{
							// Set a license failed date
							update_setting($license_check_fail_date_name, $now);
						}
						// We do have a license failed date
						else
						{
        	                			// Get the number of days since the license failure
        	                			$difference = $now - $license_check_fail_date;
        	                			$days = round($difference / (60 * 60 * 24));

        	                			// If it has been 30 or more days since the license check failure
        	                			if ($days >= 30)
							{
								write_debug_log("Deactivating and deleting Extra: " . $extra['short_name']);

								// Deactivate the Extra
								core_deactivate_extra($extra['short_name']);

								// Delete the Extra
								core_delete_extra($extra['short_name']);
							}
						}
					}
					// If the Extra is purchased and has not expired
					else if ($purchased && !$expired)
					{
						write_debug_log("Removing license check failure for Extra: " . $extra['short_name']);

						// Delete the setting for a failed license date
						delete_setting($license_check_fail_date_name);
					}
				}
			}
		}
	}
}

/*********************************
 * FUNCTION: DEACTIVATE EXTRA    *
 * Deactivates a specified Extra *
 *********************************/
function core_deactivate_extra($extra)
{
        // Get the list of available extra names
        $available_extras = available_extra_short_names();

        // If the provided extra name is not in the list of available extras
        if (!in_array($extra, $available_extras))
        {
                return false;
        }
        // The provided extra name is in the list of available extras
        else
        {
                // Get the path to the extra
                $path = realpath(__DIR__ . "/../extras/$extra/index.php");

                // If the extra is installed
                if (file_exists($path))
                {
                        // Include the extra
                        require_once($path);

                        // Deactivate the Extra
                        switch ($extra) {
				case "advanced_search":
					disable_advanced_search_extra();
					return true;
				case "api":
					disable_api_extra();
					return true;
                case "artificial_intelligence":
                    disable_artificial_intelligence_extra();
                    return true;
				case "assessments":
					disable_assessments_extra();
					return true;
				case "authentication":
					disable_authentication_extra();
					return true;
				case "complianceforgescf":
					disable_complianceforge_scf_extra();
					return true;
				case "customization":
					disable_customization_extra();
					return true;
				case "encryption":
					disable_encryption_extra();
					return true;
				case "import-export":
					disable_import_export_extra();
					return true;
				case "incident_management":
					disable_incident_management_extra();
					return true;
				case "jira":
					disable_jira_extra();
					return true;
				case "notification":
					disable_notification_extra();
					return true;
				case "organizational_hierarchy":
					disable_organizational_hierarchy_extra();
					return true;
				case "separation":
					disable_team_separation_extra();
					return true;
				case "ucf":
					disable_ucf_extra();
					return true;
				case "upgrade":
					return false;
				case "vulnmgmt":
					disable_vulnmgmt_extra();
					return true;
				default:
					return false;
                        }
                }
                else return false;
        }
}

/*****************************
 * FUNCTION: DELETE EXTRA    *
 * Deletes a specified Extra *
 *****************************/
function core_delete_extra($extra)
{
        // Get the list of available extra names
        $available_extras = available_extra_short_names();

        // If the provided extra name is not in the list of available extras
        if (!in_array($extra, $available_extras))
        {
                return false;
        }
        // The provided extra name is in the list of available extras
        else
        {
                // Get the path to the extra
                $path = realpath(__DIR__ . "/../extras/$extra");

		// If the extra directory exists
		if (is_dir($path))
		{
			// Call the proper delete directory function for the OS
			delete_dir($path);
                }
                else return false;
        }
}

?>