<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/services.php'));

// Include the language file
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/***************************************************
 * FUNCTION: AVAILABLE EXTRAS                      *
 * Returns an array of available SimpleRisk Extras *
 ***************************************************/
function available_extras()
{
    // The available SimpleRisk Extras
    $extras = array(
        array("short_name" => "upgrade", "long_name" => "Upgrade Extra"),
        array("short_name" => "authentication", "long_name" => "Custom Authentication Extra"),
        array("short_name" => "encryption", "long_name" => "Encrypted Database Extra"),
        array("short_name" => "import-export", "long_name" => "Import-Export Extra"),
        array("short_name" => "notification", "long_name" => "Email Notification Extra"),
        array("short_name" => "separation", "long_name" => "Team-Based Separation Extra"),
        array("short_name" => "assessments", "long_name" => "Risk Assessment Extra"),
        array("short_name" => "api", "long_name" => "API Extra"),
        array("short_name" => "complianceforgescf", "long_name" => "ComplianceForge SCF Extra"),
        array("short_name" => "customization", "long_name" => "Customization Extra"),
        array("short_name" => "advanced_search", "long_name" => "Advanced Search Extra"),
        array("short_name" => "jira", "long_name" => "Jira Extra"),
        array("short_name" => "ucf", "long_name" => "Unified Compliance Framework (UCF) Extra"),
        array("short_name" => "incident_management", "long_name" => "Incident Management Extra"),
        array("short_name" => "organizational_hierarchy", "long_name" => "Organizational Hierarchy Extra")
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
				default:
					return "N/A";
			}
		}
		else return "N/A";
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

	// Display the table header
	echo "<p><h4>" . $escaper->escapeHtml($lang['SimpleRiskExtras']) . "</h4></p>\n";
	echo "<table width=\"100%\" class=\"table table-bordered table-condensed\">\n";
	echo "<thead>\n";
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b><u>Extra Name</u></b></td>\n";
	echo "  <td width=\"10px\"><b><u>Purchased</u></b></td>\n";
	echo "  <td width=\"10px\"><b><u>Installed</u></b></td>\n";
	echo "  <td width=\"10px\"><b><u>Activated</u></b></td>\n";
	echo "  <td width=\"60px\"><b><u>Version</u></b></td>\n";
	echo "  <td width=\"60px\"><b><u>Latest Version</u></b></td>\n";
	echo "  <td width=\"60px\"><b><u>Action</u></b></td>\n";
	echo "</tr>\n";
	echo "</thead>\n";
	echo "<tbody>\n";

	// Upgrade Extra
	$purchased = true;
	$installed = core_is_installed("upgrade");
	$activated = true;
	$version = core_extra_current_version("upgrade");
	$latest_version = latest_version("upgrade");
	$action_button = core_get_action_button("upgrade", $purchased, $installed, $activated, $version, $latest_version);
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Upgrade</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\" checked /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\" checked /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// ComplianceForge SCF Extra
	$purchased = true;
	$installed = core_is_installed("complianceforgescf");
	$activated = complianceforge_scf_extra();
	$version = core_extra_current_version("complianceforgescf");
	$latest_version = latest_version("complianceforgescf");
	$action_button = core_get_action_button("complianceforgescf", $purchased, $installed, $activated, $version, $latest_version);
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>ComplianceForge SCF</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\" checked /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\" checked /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";

	// Custom Authentication Extra
	$purchased = core_is_purchased("authentication");
	$installed = core_is_installed("authentication");
	$activated = custom_authentication_extra();
	$version = core_extra_current_version("authentication");
	$latest_version = latest_version("authentication");
	$action_button = core_get_action_button("authentication", $purchased, $installed, $activated, $version, $latest_version);
	if ($purchased && $activated)
	{
		$activated_link = "&nbsp;&nbsp;<a href=\"authentication.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
	}
	else $activated_link = "";
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Custom Authentication</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " />" . $activated_link . "</td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// Customization Extra
	$purchased = core_is_purchased("customization");
	$installed = core_is_installed("customization");
	$activated = customization_extra();
	$version = core_extra_current_version("customization");
	$latest_version = latest_version("customization");
	$action_button = core_get_action_button("customization", $purchased, $installed, $activated, $version, $latest_version);
	if ($purchased && $activated)
	{
		$activated_link = "&nbsp;&nbsp;<a href=\"customization.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
	}
	else $activated_link = "";
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Customization</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " />" . $activated_link . "</td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// Encrypted Database Extra
	$purchased = core_is_purchased("encryption");
	$installed = core_is_installed("encryption");
	$activated = encryption_extra();
	$version = core_extra_current_version("encryption");
	$latest_version = latest_version("encryption");
	$action_button = core_get_action_button("encryption", $purchased, $installed, $activated, $version, $latest_version);
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Encrypted Database</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// Import-Export Extra
	$purchased = core_is_purchased("import-export");
	$installed = core_is_installed("import-export");
	$activated = import_export_extra();
	$version = core_extra_current_version("import-export");
	$latest_version = latest_version("import-export");
	$action_button = core_get_action_button("import-export", $purchased, $installed, $activated, $version, $latest_version);
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Import / Export</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

    // Incident Management Extra
    $purchased = core_is_purchased("incident_management");
    $installed = core_is_installed("incident_management");
    $activated = incident_management_extra();
    $version = core_extra_current_version("incident_management");
    $latest_version = latest_version("incident_management");
    $action_button = core_get_action_button("incident_management", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Incident Management</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

	// Notification Extra
	$purchased = core_is_purchased("notification");
	$installed = core_is_installed("notification");
	$activated = notification_extra();
	$version = core_extra_current_version("notification");
	$latest_version = latest_version("notification");
	$action_button = core_get_action_button("notification", $purchased, $installed, $activated, $version, $latest_version);
	if ($purchased && $activated)
	{
		$activated_link = "&nbsp;&nbsp;<a href=\"notification.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
	}
	else $activated_link = "";
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>E-mail Notification</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " />" . $activated_link . "</td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// Separation Extra
	$purchased = core_is_purchased("separation");
	$installed = core_is_installed("separation");
	$activated = team_separation_extra();
	$version = core_extra_current_version("separation");
	$latest_version = latest_version("separation");
	$action_button = core_get_action_button("separation", $purchased, $installed, $activated, $version, $latest_version);
	if ($purchased && $activated)
	{
		$activated_link = "&nbsp;&nbsp;<a href=\"separation.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
	}
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Team-Based Separation</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// Assessments Extra
	$purchased = core_is_purchased("assessments");
	$installed = core_is_installed("assessments");
	$activated = assessments_extra();
	$version = core_extra_current_version("assessments");
	$latest_version = latest_version("assessments");
	$action_button = core_get_action_button("assessments", $purchased, $installed, $activated, $version, $latest_version);
    if ($purchased && $activated)
    {
            $activated_link = "&nbsp;&nbsp;<a href=\"assessments.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
    }
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Risk Assessments</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// API Extra
	$purchased = core_is_purchased("api");
	$installed = core_is_installed("api");
	$activated = api_extra();
	$version = core_extra_current_version("api");
	$latest_version = latest_version("api");
	$action_button = core_get_action_button("api", $purchased, $installed, $activated, $version, $latest_version);
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>API</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// Advanced Search Extra
	$purchased = core_is_purchased("advanced_search");
	$installed = core_is_installed("advanced_search");
	$activated = advanced_search_extra();
	$version = core_extra_current_version("advanced_search");
	$latest_version = latest_version("advanced_search");
	$action_button = core_get_action_button("advanced_search", $purchased, $installed, $activated, $version, $latest_version);
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Advanced Search</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// Jira Extra
	$purchased = core_is_purchased("jira");
	$installed = core_is_installed("jira");
	$activated = jira_extra();
	$version = core_extra_current_version("jira");
	$latest_version = latest_version("jira");
	$action_button = core_get_action_button("jira", $purchased, $installed, $activated, $version, $latest_version);
    if ($purchased && $activated)
    {
            $activated_link = "&nbsp;&nbsp;<a href=\"jira.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
    }
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Jira</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// UCF Extra
	$purchased = core_is_purchased("ucf");
	$installed = core_is_installed("ucf");
	$activated = ucf_extra();
	$version = core_extra_current_version("ucf");
	$latest_version = latest_version("ucf");
	$action_button = core_get_action_button("ucf", $purchased, $installed, $activated, $version, $latest_version);
    if ($purchased && $activated)
    {
            $activated_link = "&nbsp;&nbsp;<a href=\"ucf.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
    }
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Unified Compliance Framework (UCF)</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	// Organizational Hierarchy Extra
	$purchased = core_is_purchased("organizational_hierarchy");
	$installed = core_is_installed("organizational_hierarchy");
	$activated = organizational_hierarchy_extra();
	$version = core_extra_current_version("organizational_hierarchy");
	$latest_version = latest_version("organizational_hierarchy");
	$action_button = core_get_action_button("organizational_hierarchy", $purchased, $installed, $activated, $version, $latest_version);
	if ($purchased && $activated)
	{
	    $activated_link = "&nbsp;&nbsp;<a href=\"organizational_hierarchy.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
	}
	echo "<tr>\n";
	echo "  <td width=\"115px\"><b>Organizational Hierarchy</b></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
	echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
	echo "</tr>\n";

	echo "</tbody>\n";
	echo "</table>\n";
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
    $data = array(
        'action' => 'check_purchase',
        'instance_id' => $instance_id,
        'api_key' => $services_api_key,
        'extra_name' => $extra,
    );

    write_debug_log("Checking for purchase of " . $extra . " for instance ID " . $instance_id);

    // Ask the service if the extra is purchased
    $results = simplerisk_service_call($data);

    // If the SimpleRisk service call returned false
    if (!$results)
    {
        write_debug_log("Unable to communicate with the SimpleRisk services API");

        // Return false
        return false;
    }
    // If we have valid results from the service call
    else
    {
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
    }

    // If the Extra has been purchased
    if ($purchased)
    {
        // If the Extra is not installed
        if (!$installed)
        {
            // Make the Install action button
            $action_button = "<form style=\"display: inline;\" name=\"install_extras\" method=\"post\" action=\"\"><button type=\"submit\" name=\"" . $button_name . "\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Install']) ."</button></form>";
        }
        // Otherwise, the Extra is installed
        else
        {
            // If the current version is not the latest
            if ($version < $latest_version)
            {
                // Make the Upgrade action button
                $action_button = "<form style=\"display: inline;\" name=\"install_extras\" method=\"post\" action=\"\"><button type=\"submit\" name=\"" . $button_name . "\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Upgrade']) ."</button></form>";
            }
            // Otherwise, the Extra is the latest version
            else
            {
                // If the Extra is not activated
                if (!$activated)
                {
                    $action_button = "<form style=\"display: inline;\" name=\"install_extras\" method=\"post\" action=\"" . $action_link . "\"><button type=\"submit\" name=\"activate_extra\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Activate']) ."</button></form>";
                }
            }
        }
    }
    // Otherwise, the Extra has not been purchased
    else
    {
        $action_button = "<form style=\"display: inline;\" action=\"https://www.simplerisk.com/extras\" target=\"_blank\" method=\"post\"><button type=\"submit\" name=\"purchase_extra\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Purchase']) . "</button></form>";
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
		$url = 'https://updates.simplerisk.com/extra_compatibility.xml';

		// Get the current version of SimpleRisk
		$simplerisk_version = current_version("app");
		write_debug_log("The current version of SimpleRisk is " . $simplerisk_version);

		// Get the current version of the extra
		$extra_version = core_extra_current_version($extra);
		write_debug_log("Current version of this extra is " . $extra_version);

		// Configure the proxy server if one exists
		$method = "GET";
		$header = "content-type: application/x-www-form-urlencoded\r\n";
		$context = set_proxy_stream_context($method, $header);

		write_debug_log("Fetching content from the extra compatibility page.");

		// Set the default socket timeout to 5 seconds
		ini_set('default_socket_timeout', 5);

		// Get the file headers for the URL
		$file_headers = @get_headers($url, 1);

		// If we were unable to connect to the URL
		if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found')
		{
			write_debug_log("Unable to connect to " . $url);
			return false;
		}
		// We were able to connect to the URL
		else
		{
			// Get the content of the extra compatibility page
			$extra_compatibility_page = file_get_contents($url, false, $context);

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

?>
