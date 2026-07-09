<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/bootstrap.php'));
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/services.php'));

// Include the language file
require_once(language_file());
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/*********************************
 * FUNCTION: CALL EXTRA FUNCTION *
 *********************************/
/**
 * Safely invoke a function defined in a SimpleRisk Extra.
 *
 * Guards against the three failure modes the bare pattern misses:
 *  - extra is enabled in the DB but the extras directory is missing on disk
 *  - extras directory exists but the file holds a version older than core (function not yet defined)
 *  - file loads but the function still isn't defined for any other reason
 *
 * Pass $extra_file = null when the file is loaded elsewhere (e.g. by the extra's index.php).
 * Returns $default (null by default) if any guard fails.
 *
 * Note: $args is evaluated at the call site before $extra_file is required, so
 * do not pass calls to extras-defined functions as args. Use plain variables,
 * scalars, arithmetic, or core-defined function calls only.
 */
function call_extra_function($extra_check_function, $extra_file, $function_name, array $args = [], $default = null) {
    if (!call_user_func($extra_check_function)) {
        return $default;
    }

    if ($extra_file !== null) {
        $resolved = realpath($extra_file);
        if ($resolved === false) {
            write_debug_log("Extra '{$extra_check_function}' is enabled but file is missing on disk: {$extra_file} (cannot call {$function_name}())", 'error');
            return $default;
        }
        require_once($resolved);
    }

    if (!function_exists($function_name)) {
        write_debug_log("Extra '{$extra_check_function}' is enabled but {$function_name}() is not defined — extras directory is likely older than core", 'warning');
        return $default;
    }

    return call_user_func_array($function_name, $args);
}

/***************************************************
 * FUNCTION: AVAILABLE EXTRAS                      *
 * Returns an array of available SimpleRisk Extras *
 ***************************************************/
// @phan-suppress-next-line PhanRedefineFunction -- alternate definition in extras/upgrade/backwards_compatibility.php is guarded by function_exists()
function available_extras()
{
    // The available SimpleRisk Extras
    $extras = array(
        array("short_name" => "advanced_search", "long_name" => "Advanced Search Extra"),
        array("short_name" => "api", "long_name" => "API Extra"),
        array("short_name" => "artificial_intelligence", "long_name" => "Artificial Intelligence Extra"),
        array("short_name" => "assessments", "long_name" => "Risk Assessment Extra"),
        array("short_name" => "authentication", "long_name" => "Custom Authentication Extra"),
        array("short_name" => "complianceforgescf", "long_name" => "Secure Controls Framework (SCF) Extra"),
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
        array("short_name" => "workflows", "long_name" => "Workflows Extra"),
    );

    // Return the array of available Extras
    return $extras;
}

/**********************************************************
 * FUNCTION: EXTRA DESCRIPTION KEYS                       *
 * Maps each Extra short_name to its description language *
 * key (lang.en.php). Any Extra omitted here falls back   *
 * to its long_name for display.                          *
 **********************************************************/
function extra_description_keys(): array
{
    return [
        'advanced_search'          => 'AdvancedSearchExtraDesc',
        'api'                      => 'APIExtraDesc',
        'artificial_intelligence'  => 'ArtificialIntelligenceExtraDesc',
        'assessments'              => 'AssessmentsExtraDesc',
        'authentication'           => 'CustomAuthenticationExtraDesc',
        'complianceforgescf'       => 'SCFExtraDesc',
        'customization'            => 'CustomizationExtraDesc',
        'encryption'               => 'EncryptionExtraDesc',
        'import-export'            => 'ImportExportExtraDesc',
        'incident_management'      => 'IncidentManagementExtraDesc',
        'jira'                     => 'JiraExtraDesc',
        'notification'             => 'NotificationExtraDesc',
        'organizational_hierarchy' => 'OrganizationManagementDesc',
        'separation'               => 'SeparationExtraDesc',
        'ucf'                      => 'UCFExtraDesc',
        'upgrade'                  => 'UpgradeExtraDesc',
        'vulnmgmt'                 => 'VulnerabilityManagementExtraDesc',
        'workflows'                => 'WorkflowsExtraDesc',
    ];
}

/**********************************************************
 * FUNCTION: AVAILABLE EXTRA SHORT NAMES                  *
 * Returns the short names of available SimpleRisk Extras *
 **********************************************************/
// @phan-suppress-next-line PhanRedefineFunction -- alternate definition in extras/upgrade/backwards_compatibility.php is guarded by function_exists()
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
                case "workflows":
                    return WORKFLOWS_EXTRA_VERSION;
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
            case "workflows":
                return workflows_extra();
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
            return "<a class='text-info m-l-10' href='securecontrolsframework.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
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
        case "workflows":
            return "<a class='text-info m-l-10' href='workflows.php'>" . $escaper->escapeHtml($lang['Configure']) . "</a>";
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

	// Reachable: get_current_version_for_extra() lives in licensing.php.
	require_once(realpath(__DIR__ . '/licensing.php'));

	// Read purchase state from the local license cache.
	// Returns [short_name => bool]; always an array, never false.
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
                    <th>Installed</th>
                    <th>Activated</th>
                    <th>Version</th>
                    <th>Latest Version</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>";

	// Free Extras per the licensing cache (server-authoritative; bundled
	// fallback on a cold cache). Resolved once, not per row.
	$free_extras = free_extra_short_names();

	// For each available extra
	foreach ($available_extras as $extra)
	{
		$short_name = $extra['short_name'];
		$purchased = $purchases[$short_name] ?? false;

		// Free Extras read as purchased regardless of paid-license state.
		if (in_array($short_name, $free_extras, true)) {
			$purchased = true;
		}

		// Check if the extra is installed
		$installed = core_is_installed($short_name);

		// Check if the extra is activated
		$activated = core_extra_activated($short_name);

		// If the extra is purchased and activated
		$activated_link = ($purchased && $activated) ? core_extra_activated_link($short_name) : "";

		// Get the version information. Prefer the current version the licensing
		// service cached for this Extra (licensed/free entries carry it); fall
		// back to the releases.xml fetch for Extras with no cache entry
		// (e.g. unlicensed), which return null here.
		$version = core_extra_current_version($short_name);
		$latest_version = get_current_version_for_extra($short_name) ?? latest_version($short_name);

		// Get the action button
		$action_button = core_get_action_button($short_name, $purchased, $installed, $activated, $version, $latest_version);

		// Display the table row
		echo "
                <tr>
                    <td>{$escaper->escapeHtml($extra['long_name'])}</td>
                    <td><input class='form-check-input' type='checkbox'" . ($purchased ? " checked" : "") . " /></td>
                    <td><input class='form-check-input' type='checkbox'" . ($installed ? " checked" : "") . " /></td>
                    <td><input class='form-check-input' type='checkbox'" . ($activated ? " checked" : "") . " />{$activated_link}</td>
                    <td><b>{$escaper->escapeHtml($version)}</b></td>
                    <td><b>{$escaper->escapeHtml($latest_version)}</b></td>
                    <td><b>{$action_button}</b></td>
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

/******************************************************
 * FUNCTION: EXTRA STATE CHECK FUNCTION                *
 ******************************************************/
/**
 * Returns the canonical state-check function name (e.g. "jira_extra")
 * for a given Extra directory name. For 4 historical Extras the
 * state-check function uses a different slug than the directory:
 *
 *   directory             state-check function
 *   --------------------  ---------------------------
 *   separation            team_separation_extra
 *   authentication        custom_authentication_extra
 *   import-export         import_export_extra
 *   complianceforgescf    complianceforge_scf_extra
 *
 * Every other Extra: function name is "<dir>_extra".
 */
function extra_state_check_function(string $dir_name): string
{
    static $overrides = [
        'separation'         => 'team_separation_extra',
        'authentication'     => 'custom_authentication_extra',
        'import-export'      => 'import_export_extra',
        'complianceforgescf' => 'complianceforge_scf_extra',
    ];
    return $overrides[$dir_name] ?? ($dir_name . '_extra');
}

/******************************************************
 * FUNCTION: EXTRA HANDLER SLUG                       *
 ******************************************************/
/**
 * Returns the slug used in enable_/disable_ handler function names for
 * a given Extra directory name. For most Extras this matches the state-
 * check slug (separation/import-export/complianceforgescf each use the
 * state-check slug for their enable/disable handlers too). The one
 * exception is the Custom Authentication Extra, whose state-check is
 * custom_authentication_extra() but whose enable/disable handlers are
 * enable_authentication_extra() / disable_authentication_extra() (the
 * directory name, not the state-check slug).
 */
function extra_handler_slug(string $dir_name): string
{
    static $overrides = [
        'separation'         => 'team_separation',
        'import-export'      => 'import_export',
        'complianceforgescf' => 'complianceforge_scf',
        // authentication: handler slug equals dir name — no override.
    ];
    return $overrides[$dir_name] ?? $dir_name;
}

/***************************************************************
 * FUNCTION: IS PURCHASED                                      *
 * Returns true iff the licensing service's last-seen response *
 * marks this Extra as effective for this instance. Reads      *
 * from the local cache (settings.license_check_response);     *
 * never hits the network. The cache is refreshed daily by     *
 * license_check_daily().                                      *
 ***************************************************************/
function core_is_purchased($extra)
{
    require_once(realpath(__DIR__ . '/licensing.php'));
    return get_effective_for_extra((string)$extra);
}

/***************************************************************
 * FUNCTION: CHECK ALL PURCHASES                               *
 * Returns an associative array of [short_name => bool]        *
 * where bool is the cached 'effective' flag for that Extra.   *
 * Reads from the local cache (settings.license_check_response)*
 * populated daily by license_check_daily(). Never hits the network.   *
 ***************************************************************/
function core_check_all_purchases()
{
    require_once(realpath(__DIR__ . '/licensing.php'));

    // Read the cached entries ONCE and build a short_name => effective lookup.
    // Calling get_effective_for_extra() per Extra would re-read the (now
    // uncached) settings.license_check_response row N times — one DB query per
    // available Extra. get_cached_license_entries() already filters to known
    // short-names, so this map only contains recognized Extras.
    $effective = [];
    foreach (get_cached_license_entries() as $entry) {
        if (isset($entry['extra_name'])) {
            $effective[$entry['extra_name']] = (bool)($entry['effective'] ?? false);
        }
    }

    $out = [];
    foreach (available_extra_short_names() as $name) {
        $out[$name] = $effective[$name] ?? false;
    }
    return $out;
}

/*************************************************************
 * FUNCTION: FREE EXTRA SHORT NAMES                          *
 *************************************************************/
// The set of Extra short-names that are free (never subject to the licensing
// enforcement gate). Single source of truth so the free-Extra set can't drift
// across the call sites that gate display/install on it.
//
// The Upgrade and SCF Extras are bundled-free with the product — they are ALWAYS
// free, regardless of cache state. This must hold even when the license cache is
// warm but does not flag them is_free (e.g. before the server populates the
// flag, or a partial cache), otherwise the Upgrade Extra — the upgrade mechanism
// itself — could be blocked from installing. The licensing cache's is_free flags
// are then unioned in, so the server stays authoritative for any ADDITIONAL free
// Extras beyond the bundled set.
function free_extra_short_names(): array
{
    require_once(realpath(__DIR__ . '/licensing.php'));

    $free = ['upgrade', 'complianceforgescf'];

    foreach (get_cached_license_entries() as $entry) {
        if (!empty($entry['is_free'])
            && isset($entry['extra_name'])
            && !in_array($entry['extra_name'], $free, true)) {
            $free[] = $entry['extra_name'];
        }
    }
    return $free;
}

/*************************************************************
 * FUNCTION: HAS PAID EXTRA                                  *
 *************************************************************/
// True iff the instance currently has at least one PAID Extra — an Extra the
// licensing cache marks 'effective' (a currently-valid license) that is not in
// the free set (Upgrade, SCF, and any server-flagged is_free Extra). Reads the
// local cache (settings.license_check_response) only; never hits the network.
//
// This is the entitlement behind the Phone Support menu item: standard support
// (portal/web/email) is included for everyone, but phone support is reserved
// for paying customers. Excluding free_extra_short_names() keeps the bundled
// free Extras from counting as a paid purchase.
function has_paid_extra(): bool
{
    require_once(realpath(__DIR__ . '/licensing.php'));

    $free = free_extra_short_names();

    foreach (get_cached_license_entries() as $entry) {
        $name = $entry['extra_name'] ?? '';
        if ($name !== ''
            && !empty($entry['effective'])
            && !in_array($name, $free, true)) {
            return true;
        }
    }
    return false;
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
    $button_name = "";
    $action_link = "";

    // Check the Extra Name
    switch ($extra_name)
    {
        case "upgrade":
            $button_name = "get_upgrade_extra";
            break;
        case "complianceforgescf":
            $button_name = "get_complianceforge_scf_extra";
            $action_link = "securecontrolsframework.php";
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
        case "workflows":
            $button_name = "get_workflows_extra";
            $action_link = "workflows.php";
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

    // Reachable: get_current_version_for_extra() lives in licensing.php.
    require_once(realpath(__DIR__ . '/licensing.php'));

    $available_extras = available_extra_short_names();

    $upgradeable = [];
    foreach($available_extras as $extra) {
        // Prefer the licensing service's cached current version; fall back to
        // the releases.xml fetch for Extras with no cache entry.
        $latest = get_current_version_for_extra($extra) ?? latest_version($extra);
        if (core_is_purchased($extra) &&
            core_is_installed($extra) &&
            core_extra_current_version($extra) < $latest) {
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
        $result = download_extra($extra, true);
        if (!is_string($result)) {
            $err_message = $result['reason'] ?? $result['error'] ?? null;
            if ($err_message !== null) {
                write_debug_log("download_extra failed for '{$extra}': {$err_message}", 'warning');
            }
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
	write_debug_log("Checking version comptability for the \"" . $extra . "\" extra.", 'info');

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
		write_debug_log("The current version of SimpleRisk is " . $simplerisk_version, 'info');

		// Get the current version of the extra
		$extra_version = core_extra_current_version($extra);
		write_debug_log("Current version of this extra is " . $extra_version, 'info');

        // Set the HTTP options
        $http_options = [
            'method' => 'GET',
            'header' => [
                "Content-Type: application/x-www-form-urlencoded",
            ],
        ];

        // If SSL certificate checks are enabled for the SimpleRisk API
        if (ssl_external_verify_enabled())
        {
            // Verify the SSL host and peer
            $validate_ssl = true;
        }
        else $validate_ssl = false;

		write_debug_log("Fetching content from the extra compatibility page.", 'info');

		// Set the default socket timeout to 5 seconds
		ini_set('default_socket_timeout', 5);

        // Make the services call
        $response = fetch_url_content("stream", $http_options, $validate_ssl, $url);
        if (!is_array($response))
        {
            write_debug_log("Unable to connect to " . $url, 'warning');
            return false;
        }
        $return_code = $response['return_code'];

		// If we were unable to connect to the URL
        if ($return_code !== 200)
		{
			write_debug_log("Unable to connect to " . $url, 'warning');
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
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable -- json_decode result from valid XML→JSON; xml validity confirmed by simplexml_load_string success
			foreach($array[$extra]['extra'] as $key => $value)
			{
				$array_extra_version = $value['@attributes']['version'];
				$array_simplerisk_version = $value['appversion'];

				// If we have a match
				if ($simplerisk_version == $array_simplerisk_version && $extra_version == $array_extra_version)
				{
					write_debug_log("The current version of SimpleRisk is compatible with this Extra.", 'info');
					return true;
				}
			}

			// If we never found our match
			write_debug_log("The current version of SimpleRisk is not compatible with this Extra.", 'warning');
			return false;
		}
	}
}

/**************************************
 * FUNCTION: SIMPLERISK LICENSE CHECK *
 **************************************/
function simplerisk_license_check()
{
	write_debug_log("Applying cached license state.", 'info');

	// Apply the cached enforcement_level to the session so existing UI that
	// reads $_SESSION['license_check'] reflects the current licensing policy.
	// Server-authoritative — no client-side grace.
	//
	// This is a pure read of the local cache (settings.license_check_response).
	// It does NOT call the licensing service: the cache is refreshed out-of-band
	// by the core_license_check queue job (scheduled by cron_queue_loader,
	// executed by cron_queue_worker, at most once per 24h), and once more
	// synchronously by register.php right after a fresh registration so a new
	// instance bootstraps its entitlements immediately. Keeping the login path
	// network-free makes sign-in fast and removes the licensing service from
	// the critical auth path entirely.
	require_once(realpath(__DIR__ . '/licensing.php'));
	apply_enforcement_level();
}

/**
 * Refresh the local licensing cache by firing one /license/check against
 * the licensing service. Returns whatever license_check_daily() returns (the
 * parsed /license/check response — see parse_license_check_response()).
 *
 * Callers that need per-Extra effective state should call
 * core_is_purchased() / get_effective_for_extra() instead — those read
 * from the cache this function populates.
 *
 * NOTE: The legacy implementation of this function also managed several
 * enforcement side effects: setting $_SESSION['license_check'] pass/fail,
 * setting $_SESSION['support'], tracking license_check_fail_date_* settings,
 * and auto-deactivating/deleting Extras after 30 days of license failure.
 * Those behaviors are intentionally dropped here; they belong in a dedicated
 * enforcement layer that reads from the cache via get_effective_for_extra().
 */
function simplerisk_refresh_license_cache()
{
    require_once(realpath(__DIR__ . '/licensing.php'));
    return license_check_daily();
}

/**
 * @deprecated Use simplerisk_refresh_license_cache(). Renamed during the
 * licensing refactor because this function no longer just "checks
 * purchases" — it triggers a fresh /license/check which uploads
 * metadata and retrieves the full entitlements + enforcement state.
 */
function simplerisk_license_check_purchases()
{
    return simplerisk_refresh_license_cache();
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
				case "workflows":
					disable_workflows_extra();
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