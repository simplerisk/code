<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/config.php'));
require_once(realpath(__DIR__ . '/extras.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

/*************************************
 * FUNCTION: SIMPLERISK HEALTH CHECK *
 *************************************/
function simplerisk_health_check()
{
	global $escaper, $lang;

	// Set the time limit on the health check to 5 minutes
	set_time_limit(300);

	// Set the default socket timeout for healthchecks to 5 seconds
	ini_set('default_socket_timeout', 5);

	// Get the SimpleRisk instance ID
	$instance_id = get_setting("instance_id");

	// Get the OS version information
	$os_version = getOSInformation();

	// Get the database version
	$db_version = get_database_version();

	// Get the current and latest versions
	$current_app_version = current_version("app");
	$latest_app_version = latest_version("app");
	$current_db_version = current_version("db");
	$latest_db_version = latest_version("db");

	// Check that we are running the latest version of the SimpleRisk application
	$check_app_version = check_app_version($current_app_version, $latest_app_version);

	// Check that we are running the latest version of the SimpleRisk database
	$check_db_version = check_db_version($current_db_version, $latest_db_version);

	// Check that the application and database versions are the same
	$check_same_app_and_db = check_same_app_and_db($current_app_version, $current_db_version);

	// Check that USE_DATABASE_FOR_SESSION is set to true
	$check_use_database_for_session = check_use_database_for_session();

	// Check that the automation cron is configured and running
	$cron_configured = check_cron_configured();

	// Check the Extra versions match the SimpleRisk version
	$check_extra_versions = check_extra_versions($current_app_version);

	// Search for any broken extras
	$check_extra_versions_result = 1;
	foreach ($check_extra_versions as $extras)
	{
		// If the extension result is 0
		if ($extras['result'] === 0)
		{
			// Set the overall result to 0
			$check_extra_versions_result = 0;
		}
	}

	// Check the SimpleRisk Base URL
	$check_simplerisk_base_url = check_simplerisk_base_url();

    // Check the SimpleRisk Base URL DNS
    $check_simplerisk_base_url_dns = check_simplerisk_base_url_dns();

	// Check the SimpleRisk database connectivity
	$check_database_connectivity = check_database_connectivity();

	// Check that SimpleRisk can communicate with the API
	$check_api_connectivity = check_api_connectivity();

	// Check that SimpleRisk can connect to the services platforms
	$check_web_connectivity = check_web_connectivity();

	// Search for any broken connectivity
	$check_web_connectivity_result = 1;
	foreach ($check_web_connectivity as $connectivity)
	{
		// If the connectivity result is 0
		if ($connectivity['result'] === 0)
		{
			// Set the overall result to 0
			$check_web_connectivity_result = 0;
		}
	}

	// Check that this is PHP 7
	$check_php_version = check_php_version();

	// Check the PHP memory_limit
	$check_php_memory_limit = check_php_memory_limit();

	// Check the PHP max_input_vars
	$check_php_max_input_vars = check_php_max_input_vars();

	// Check the necessary PHP extensions are installed
	$check_php_extensions = check_php_extensions();

	// Search for any missing PHP extensions
	$check_php_extensions_result = 1;
	foreach ($check_php_extensions as $extension)
	{
		// If the extension result is 0
		if ($extension['result'] === 0)
		{
			// Set the overall result to 0
			$check_php_extensions_result = 0;
		}
	}

    // Check the mysql version
    $check_mysql_version = check_mysql_version();

	// Check the current database size and free space
	$check_mysql_size = check_mysql_size();

	// Check if MySQL STRICT SQL mode is enabled
	$check_strict_sql_mode = check_strict_sql_mode();

	// Check if MySQL NO_ZERO_DATE mode is enabled
	$check_no_zero_date = check_no_zero_date();

	// Check if MySQL ONLY_FULL_GROUP_BY mode is enabled
	$check_only_full_group_by = check_only_full_group_by();

	// Check if SELECT permission is enabled
	$check_mysql_permission_select = check_mysql_permission("SELECT");

	// Check if INSERT permission is enabled
	$check_mysql_permission_insert = check_mysql_permission("INSERT");

	// Check if UPDATE permission is enabled
	$check_mysql_permission_update = check_mysql_permission("UPDATE");

	// Check if DELETE permission is enabled
	$check_mysql_permission_delete = check_mysql_permission("DELETE");

	// Check if CREATE permission is enabled
	$check_mysql_permission_create = check_mysql_permission("CREATE");

	// Check if DROP permission is enabled
	$check_mysql_permission_drop = check_mysql_permission("DROP");

	// Check if REFERENCES permission is enabled
	$check_mysql_permission_references = check_mysql_permission("REFERENCES");

	// Check if INDEX permission is enabled
	$check_mysql_permission_index = check_mysql_permission("INDEX");

	// Check if ALTER permission is enabled
	$check_mysql_permission_alter = check_mysql_permission("ALTER");

	// Check the simplerisk directory permissions
	$check_simplerisk_directory_permissions = check_simplerisk_directory_permissions();

    // Search for any bad directory permissions
    $check_simplerisk_directory_permissions_result = 1;
    foreach ($check_simplerisk_directory_permissions as $permission)
    {
        // If the permission result is 0
        if ($permission['result'] === 0)
        {
            // Set the overall result to 0
            $check_simplerisk_directory_permissions_result = 0;
        }
    }

    echo "
        <div class='mt-2'>
            <nav class='nav nav-tabs'>
                <a class='nav-link active' data-bs-target='#summary' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['Summary'])}</a>
                <a class='nav-link' data-bs-target='#simplerisk' data-bs-toggle='tab'>Simplerisk</a>
                <a class='nav-link' data-bs-target='#extras' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['Extras'])}</a>
                <a class='nav-link' data-bs-target='#connectivity' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['Connectivity'])}</a>
                <a class='nav-link' data-bs-target='#php' data-bs-toggle='tab'>PHP</a>
                <a class='nav-link' data-bs-target='#mysql' data-bs-toggle='tab'>MySQL</a>
                <a class='nav-link' data-bs-target='#permissions' data-bs-toggle='tab'>{$escaper->escapeHtml($lang['Permissions'])}</a>
            </nav>
        </div>        
        <div class='tab-content'>
			<div class='tab-pane active col-12' id='summary' tabindex='0'>
				<div class='card-body my-2 border font-16'>
                <h4>{$escaper->escapeHtml($lang['HealthCheckSummary'])}</h4>
    ";

    // Versions Summary
    if ($check_app_version['result'] === 1 && $check_db_version['result'] === 1 && $check_same_app_and_db['result'] === 1 && $check_use_database_for_session['result'] === 1 && $cron_configured['result'] === 1)
    {
        health_check_good($lang['SimpleRiskCore']);
    }
    else health_check_bad($lang['SimpleRiskCore']);
    
    // Extras Summary
    if ($check_extra_versions_result === 1)
    {
        health_check_good($lang['Extras']);
    }
    else health_check_bad($lang['Extras']);
    
    // Connectivity Summary
    if ($check_simplerisk_base_url['result'] === 1 && $check_simplerisk_base_url_dns['result'] ===1 && $check_database_connectivity['result'] === 1 && $check_api_connectivity['result'] === 1 && $check_web_connectivity_result === 1)
    {
        health_check_good($lang['Connectivity']);
    }
    else health_check_bad($lang['Connectivity']);
    
    // PHP Summary
    if ($check_php_version['result'] === 1 && $check_php_memory_limit['result'] === 1 && $check_php_max_input_vars['result'] === 1 && $check_php_extensions_result === 1)
    {
    	health_check_good("PHP");
    }
    else health_check_bad("PHP");
    
    // MySQL Summary
    if ($check_mysql_version['result'] === 1 && $check_no_zero_date['result'] === 1 && $check_only_full_group_by['result'] === 1 && $check_mysql_permission_select['result'] === 1 && $check_mysql_permission_insert['result'] === 1 && $check_mysql_permission_update['result'] === 1 && $check_mysql_permission_delete['result'] === 1 && $check_mysql_permission_create['result'] === 1 && $check_mysql_permission_drop['result'] === 1 && $check_mysql_permission_references['result'] === 1 && $check_mysql_permission_index['result'] === 1 && $check_mysql_permission_alter['result'] === 1)
    {
    	health_check_good("MySQL");
    }
    else health_check_bad("MySQL");
    
    // Permissions Summary
    if ($check_simplerisk_directory_permissions_result === 1)
    {
        health_check_good($lang['Permissions']);
    }
    else health_check_bad($lang['Permissions']);

    echo "
        <div class='row'>&nbsp;</div>
        <div class='row'>
            <div class='col-md-12'><h4>" . $escaper->escapeHtml($lang['InstanceDetails']) . "</h4></div>
        </div>
        <div class='row'>  
            <div class='col-md-2'><h5>OS Version:</h5></div>
            <div class='col-md-10'>" . $escaper->escapeHtml($os_version['pretty_name']) . "</div>
        </div>
        <div class='row'>  
            <div class='col-md-2'><h5>PHP Version:</h5></div>
            <div class='col-md-10'>" . $escaper->escapeHtml(phpversion()) . "</div>
        </div>
        <div class='row'>  
            <div class='col-md-2'><h5>Database Version:</h5></div>
            <div class='col-md-10'>" . $escaper->escapeHtml($db_version) . "</div>
        </div>
        <div class='row'>  
            <div class='col-md-2'><h5>SimpleRisk Version:</h5></div>
            <div class='col-md-10'>" . $escaper->escapeHtml($current_app_version) . "</div>
        </div>
        <div class='row'>  
            <div class='col-md-2'><h5>Default Language:</h5></div>
            <div class='col-md-10'>" . $escaper->escapeHtml(get_setting("default_language")) . "</div>
        </div>
        <div class='row'>  
            <div class='col-md-2'><h5>Session Language:</h5></div>
            <div class='col-md-10'>" . $escaper->escapeHtml($_SESSION['lang']) . "</div>
        </div>
        <div class='row'>  
            <div class='col-md-2'><h5>SimpleRisk Instance ID:</h5></div>
            <div class='col-md-10'>" . $escaper->escapeHtml($instance_id) . "</div>
        </div>
        <div class='row'>  
            <div class='col-md-2'><h5>Public IP Address:</h5></div>
            <div class='col-md-10'>" . $escaper->escapeHtml(get_public_ip()) . "</div>
        </div>
    ";

    echo "
        </div></div>
        <div class='tab-pane col-12' id='simplerisk' tabindex='0'>
		<div class='card-body my-2 border font-16'>
            <h4>{$escaper->escapeHtml($lang['SimpleRiskVersion'])}</h4>";
    display_health_check_results($check_app_version);
	display_health_check_results($check_db_version);
	display_health_check_results($check_same_app_and_db);
    echo "
          <h4 class='mt-2'>{$escaper->escapeHtml($lang['Configurations'])}</h4>";
    display_health_check_results($check_use_database_for_session);
	display_health_check_results($cron_configured);
    echo "
        </div></div>
        <div class='tab-pane col-12' id='extras' tabindex='0'>
		<div class='card-body my-2 border font-16'>
            <h4>{$escaper->escapeHtml($lang['SimpleRiskExtras'])}</h4>";
    display_health_check_array_results($check_extra_versions);
    echo "
        </div></div>
        <div class='tab-pane col-12' id='connectivity' tabindex='0'>
		<div class='card-body my-2 border font-16'>
            <h4>{$escaper->escapeHtml($lang['Connectivity'])}</h4>";
    display_health_check_results($check_simplerisk_base_url);
    display_health_check_results($check_simplerisk_base_url_dns);
    display_health_check_results($check_database_connectivity);
    display_health_check_results($check_api_connectivity);
    display_health_check_array_results($check_web_connectivity);
    echo "
        </div></div>
        <div class='tab-pane col-12' id='php' tabindex='0'>
		<div class='card-body my-2 border font-16'>
            <h4>PHP</h4>";
    display_health_check_results($check_php_version);
    display_health_check_results($check_php_memory_limit);
    display_health_check_results($check_php_max_input_vars);
    display_health_check_array_results($check_php_extensions);
    echo "
        </div></div>
        <div class='tab-pane col-12' id='mysql' tabindex='0'>
		<div class='card-body my-2 border font-16'>
   	        <h4>MySQL</h4>";
    display_health_check_results($check_mysql_version);
    display_health_check_results($check_mysql_size);
    display_health_check_results($check_strict_sql_mode);
    display_health_check_results($check_no_zero_date);
    display_health_check_results($check_only_full_group_by);
    display_health_check_results($check_mysql_permission_select);
    display_health_check_results($check_mysql_permission_insert);
    display_health_check_results($check_mysql_permission_update);
    display_health_check_results($check_mysql_permission_delete);
    display_health_check_results($check_mysql_permission_create);
    display_health_check_results($check_mysql_permission_drop);
    display_health_check_results($check_mysql_permission_references);
    display_health_check_results($check_mysql_permission_index);
    display_health_check_results($check_mysql_permission_alter);
    echo "
        </div></div>
        <div class='tab-pane col-12' id='permissions' tabindex='0'>
		<div class='card-body my-2 border font-16'>
          <h4>{$escaper->escapeHtml($lang['FileAndDirectoryPermissions'])}</h4>";
    display_health_check_array_results($check_simplerisk_directory_permissions);
    echo "
        </div>
      </div>
	  </div>
    ";
}

/******************************************
 * FUNCTION: DISPLAY HEALTH CHECK RESULTS *
 ******************************************/
function display_health_check_results($health_check)
{
	// If the result was good
	if ($health_check['result'] === 1)
	{
		health_check_good($health_check['text']);
	}
	else
	{
		health_check_bad($health_check['text']);
	}
}

/************************************************
 * FUNCTION: DISPLAY HEALTH CHECK ARRAY RESULTS *
 ************************************************/
function display_health_check_array_results($health_check_array)
{
	foreach($health_check_array as $health_check)
	{
		display_health_check_results($health_check);
	}
}

/*******************************
 * FUNCTION: HEALTH CHECK GOOD *
 *******************************/
function health_check_good($text)
{
	global $escaper;

	echo "<img src=\"../images/check-mark-8-16.png\" />&nbsp&nbsp;" . $escaper->escapeHtml($text) . "<br />";
}

/******************************
 * FUNCTION: HEALTH CHECK BAD *
 ******************************/
function health_check_bad($text)
{
	global $escaper;

        echo "<img src=\"../images/x-mark-5-16.png\" />&nbsp;&nbsp;" . $escaper->escapeHtml($text) . "<br />";
}

/*******************************
 * FUNCTION: CHECK APP VERSION *
 *******************************/
function check_app_version($current_app_version, $latest_app_version)
{
    // If the current and latest versions are the same
    if ($current_app_version === $latest_app_version)
    {
        return array("result" => 1, "text" => "Running the current version (" . $current_app_version . ") of the SimpleRisk application.");
    }
    // If the current version is less than the latest version
    else if ($current_app_version < $latest_app_version)
    {
        return array("result" => 0, "text" => "Running an outdated version (" . $current_app_version . ") of the SimpleRisk application.  The latest application version is {$latest_app_version}.");
    }
    // If the current version is greater than the latest version
    else if ($current_app_version > $latest_app_version)
    {
        return array("result" => 1, "text" => "Running version (" . $current_app_version . ") of the SimpleRisk application.  The latest application version is {$latest_app_version}.  You must be from the future.");
    }
}

/******************************
 * FUNCTION: CHECK DB VERSION *
 ******************************/
function check_db_version($current_db_version, $latest_db_version)
{
    // If the current and latest versions are the same
    if ($current_db_version === $latest_db_version)
    {
        return array("result" => 1, "text" => "Running the current version (" . $current_db_version . ") of the SimpleRisk database.");
    }
    // If the current version is less than the latest version
    else if ($current_db_version < $latest_db_version)
    {
        return array("result" => 0, "text" => "Running an outdated version (" . $current_db_version . ") of the SimpleRisk database.  The latest database version is {$latest_db_version}.");
    }
    // If the current version is greater than the latest version
    else if ($current_db_version > $latest_db_version)
    {
        return array("result" => 1, "text" => "Running version (" . $current_db_version . ") of the SimpleRisk database.  The latest database version is {$latest_db_version}.  You must be from the future.");
    }
}

/***********************************
 * FUNCTION: CHECK SAME APP AND DB *
 ***********************************/
function check_same_app_and_db($current_app_version, $current_db_version)
{
	// If the current versions of the app and db are the same
	if ($current_app_version === $current_db_version)
	{
		return array("result" => 1, "text" => "The SimpleRisk application and database are at the same versions (" . $current_db_version . ").");
	}
	else
	{
		return array("result" => 0, "text" => "The SimpleRisk application (" . $current_app_version . ") and database (" . $current_db_version . ") versions are not the same.");
	}
}

/****************************************************
 * FUNCTION: CHECK SIMPLERISK DIRECTORY PERMISSIONS *
 ****************************************************/
function check_simplerisk_directory_permissions()
{
        // Create an empty array
        $array = array();

	// Get the hosting tier
	$hosting_tier = get_setting("hosting_tier");

	// If the hosting tier is set and is set to trial or micro or small
	if ($hosting_tier != false && ($hosting_tier == "trial" || $hosting_tier == "micro" || $hosting_tier == "small"))
	{
		$array[] = array("result" => 1, "text" => "Permissions have been automatically set to their proper values.");
	}
	else
	{
		$simplerisk_dir = realpath(__DIR__ . '/..');

		// If the simplerisk directory is writeable
		if (is_writeable($simplerisk_dir))
		{
			$array[] = array("result" => 1, "text" => "The SimpleRisk directory (" . $simplerisk_dir . ") is writeable by the web user.");
		}
		else
		{
			$array[] = array("result" => 0, "text" => "The SimpleRisk directory (" . $simplerisk_dir . ") is not writeable by the web user.");
		}

		$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($simplerisk_dir), RecursiveIteratorIterator::SELF_FIRST);

		foreach ($objects as $name => $object)
		{
			// Do not check the directory above the SimpleRisk directory
			if ($name != $simplerisk_dir . "/..")
			{
				// If the directory is writeable
				if (!is_writeable($name))
				{
					$array[] = array("result" => 0, "text" => $name . " is not writeable by the web user.");
				}
			}
		}
	}

	return $array;
}

/************************************
 * FUNCTION: CHECK WEB CONNECTIVITY *
 ************************************/
function check_web_connectivity()
{
	// Configure the proxy server if one exists
	$method = "GET";
	$header = "content-type: Content-Type: application/x-www-form-urlencoded";
	set_proxy_stream_context($method, $header);

	// URLs to check
	$urls = array("https://register.simplerisk.com", "https://services.simplerisk.com", "https://scf.simplerisk.com", "https://olbat.github.io", "https://github.com", "https://raw.githubusercontent.com", "https://simplerisk-downloads.s3.amazonaws.com");

	// Create an empty array
	$array = array();

	// Check the URLs
	foreach ($urls as $url)
	{
		write_debug_log("Healthcheck for URL: " . $url);

		// Get the headers for the URL
		$file_headers = @get_headers($url, 1);

		if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found')
		{
			write_debug_log("SimpleRisk was unable to connect to " . $url);
			$array[] = array("result" => 0, "text" => "SimpleRisk was unable to connect to " . $url . ".");
		}
		else
		{
			write_debug_log("SimpleRisk connected to " . $url);
			$array[] = array("result" => 1, "text" => "SimpleRisk connected to " . $url . ".");
		}
	}

	return $array;
}

/*********************************
 * FUNCTION: CHECK MYSQL VERSION *
 *********************************/
function check_mysql_version()
{
    // Open the database connection
    $db = db_open();

    // Get the database version information
    $stmt = $db->prepare("SELECT VERSION() as version;");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $version = $row['version'];

    // Close the database connection
    db_close($db);

    // MariaDB version looks like "10.5.12-MariaDB-log"
    // MySQL version looks like "8.0.23"

    // If the database is MariaDB
    if (preg_match('/MariaDB/', $version))
    {
        // The instance is running MariaDB and the check should fail
        $array = array("result" => 0, "text" => "Your SimpleRisk instance is running on MariaDB, which is no longer a supported configuration.  Please migrate to MySQL as soon as possible.");
    }
    // Otherwise this is MySQL
    else
    {
        // Split the version by the decimals
        $version_array = explode('.', $version);
        $major_version = $version_array[0];

        // If the version is MySQL 8 or higher
        if ($major_version >= 8)
        {
            $array = array("result" => 1, "text" => "Your SimpleRisk instance is running on MySQL version {$version}.");
        }
        // If this is an older version of MySQL and the check should fail
        else
        {
            $array = array("result" => 0, "text" => "Your SimpleRisk instance is running a MySQL version older than 8.0, which is no longer a supported configuration.  Please migrate to a newer version of MySQL as soon as possible.");
        }
    }

    // Return the array
    return $array;
}

/************************************
 * FUNCTION: CHECK MYSQL PERMISSION *
 ************************************/
function check_mysql_permission($permission)
{
    global $escaper;

    // Open a database connection
    $db = db_open();

    // Query for the permission
    //$stmt = $db->prepare("SELECT " . $permission . " FROM mysql.db WHERE user='" . DB_USERNAME . "';");
    $stmt = $db->prepare("SHOW GRANTS FOR CURRENT_USER;");
    $stmt->execute();
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Set permission found to false
    $permission_found = false;

    foreach ($array as $row)
    {
        // If the row contains the permission
        if (preg_match("/" . $permission . "/", $row[0]))
        {
            // The health check passed
            $array = array("result" => 1, "text" => "The '" . $escaper->escapeHtml($permission) . "' permssion has been set for the '" . $escaper->escapeHtml(DB_USERNAME) . "' user.");

            // Set the permission found to true
            $permission_found = true;
        }
    }

    // If we did not find the permission
    if ($permission_found == false)
    {
        $array = array("result" => 0, "text" => "The '" . $escaper->escapeHtml($permission) . "' permssion is not set for the '" . $escaper->escapeHtml(DB_USERNAME) . "' user.");
    }

    return $array;
}

/**********************************
 * FUNCTION: CHECK PHP EXTENSIONS *
 **********************************/
function check_php_extensions()
{
	// List of extensions to check for
	$extensions = array("pdo", "pdo_mysql", "json", "phar", "zlib", "mbstring", "ldap", "dom", "curl", "posix", "zip", "gd", "intl");

	// Create an empty array
	$array = array();

	// For each extension
	foreach ($extensions as $extension)
	{
		if (extension_loaded($extension))
		{
			$array[] = array("result" => 1, "text" => "The PHP \"" . $extension . "\" extension is loaded.");
		}
		else
		{
			$array[] = array("result" => 0, "text" => "The PHP \"" . $extension . "\" extension is not loaded.");
		}
	}

	return $array;
}

/***************************************
 * FUNCTION: CHECK SIMPLERISK BASE URL *
 ***************************************/
function check_simplerisk_base_url()
{
	// Get the SimpleRisk Base URL value from the database
	$simplerisk_base_url = get_setting('simplerisk_base_url');

	// Get the current Base URL value
	$isHTTPS = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on");
	$port = (isset($_SERVER['SERVER_PORT']) && ((!$isHTTPS && $_SERVER['SERVER_PORT'] != "80") || ($isHTTPS && $_SERVER['SERVER_PORT'] != "443")));
	$port = ($port) ? ":" . $_SERVER['SERVER_PORT'] : "";
	$base_url = ($isHTTPS ? "https://" : "http://") . $_SERVER['SERVER_NAME'] . $port;
	$dir_path = realpath(dirname(dirname(__FILE__)));
	$document_root = realpath($_SERVER["DOCUMENT_ROOT"]);
	$app_root = str_replace($document_root,"",$dir_path);
	$app_root = str_replace(DIRECTORY_SEPARATOR ,"/",$app_root);
	$base_url .= $app_root;

	// If the base URL stored in settings and the one we are using are the same
	if ($simplerisk_base_url == $base_url)
	{
		return array("result" => 1, "text" => "Your SimpleRisk Base URL matches the URL you are using to connect to SimpleRisk.");
	}
	else
	{
		return array("result" => 0, "text" => "Your SimpleRisk Base URL does not match the URL you are using to connect to SimpleRisk.");
	}
}

/*****************************************
 * FUNCTION: CHECK DATABASE CONNECTIVITY *
 *****************************************/
function check_database_connectivity()
{
	// Try opening a database connection
	$db = db_open();
	if ($db !== null)
	{
		// Close the database connection
		db_close($db);

		return array("result" => 1, "text" => "Communicated with the SimpleRisk database successfully.");
	}
	else
	{
		return array("result" => 0, "text" => "Unable to communicate with the SimpleRisk database.");
	}
}

/************************************
 * FUNCTION: CHECK API CONNECTIVITY *
 ************************************/
function check_api_connectivity()
{
	// If the curl_init function does not exist
	if (!function_exists('curl_init'))
	{
		return array("result" => 0, "text" => "The php-curl library is not installed so we are unable to test the API connectivity.");
	}
	else
	{
		// Get the SimpleRisk base URL
		$simplerisk_base_url = get_setting("simplerisk_base_url");

		// Create the whoami URL
		$url = $simplerisk_base_url . "/api/whoami";

		// Set the HTTP options
		$http_options = [
			'method' => 'GET',
			'header' => [
				"Cookie: " . session_name() . "=" . session_id(),
				"Content-Type: application/x-www-form-urlencoded",
			],
			'timeout' => 5,
		];

		// If SSL certificate checks are enabled for the SimpleRisk API
		if (get_setting('ssl_certificate_check_simplerisk') == 1)
		{
			// Verify the SSL host and peer
			$validate_ssl = true;
		}
		else $validate_ssl = false;

		// Make a curl request to the whoami API endpoint
		$response = fetch_url_content("curl", $http_options, $validate_ssl, $url);
		$return_code = $response['return_code'];

		// If the request was successful
		if ($return_code === 200)
		{
			// If SSL validation is disabled
			if (!$validate_ssl)
			{
				return array("result" => 1, "text" => "Communicated with the SimpleRisk API successfully, but SSL certificate checks are disabled.");
			}
			else
			{
				return array("result" => 1, "text" => "Communicated with the SimpleRisk API successfully.");
			}
		}
		else
		{
			// If SSL validation is disabled
			if (!$validate_ssl)
			{
				return array("result" => 0, "text" => "Unable to communicate with the SimpleRisk API even with SSL certificate checks disabled.");
			}
			else
			{
				return array("result" => 0, "text" => "Unable to communicate with the SimpleRisk API.  To debug, try disabling SSL certificate checks for SimpleRisk API requests.");
			}
		}
	}
}

/***********************************
 * FUNCTION: CHECK STRICT SQL MODE *
 ***********************************/
function check_strict_sql_mode()
{
        // Open a database connection
        $db = db_open();

	// Query for the current SQL mode
	$stmt = $db->prepare("SELECT @@sql_mode;");
	$stmt->execute();
	$array = $stmt->fetch();
	$sql_mode = $array['@@sql_mode'];

	// Close the database connection
	db_close($db);

	// If the row contains STRICT_TRANS_TABLES
	if (preg_match("/.*STRICT_TRANS_TABLES.*/", $sql_mode))
	{
		return array("result" => 0, "text" => "SimpleRisk will not work properly with STRICT_TRANS_TABLES enabled.");
	}
	else
	{
		return array("result" => 1, "text" => "Verified that STRICT_TRANS_TABLES is not enabled for MySQL.");
	}
}

/********************************
 * FUNCTION: CHECK NO ZERO DATE *
 ********************************/
function check_no_zero_date()
{
        // Open a database connection
        $db = db_open();
        
        // Query for the current SQL mode
        $stmt = $db->prepare("SELECT @@sql_mode;");
        $stmt->execute();
        $array = $stmt->fetch();
        $sql_mode = $array['@@sql_mode'];
        
        // Close the database connection
        db_close($db);
        
        // If the row contains NO_ZERO_DATE
        if (preg_match("/.*NO_ZERO_DATE.*/", $sql_mode))
        {       
		return array("result" => 0, "text" => "SimpleRisk will not work properly with NO_ZERO_DATE enabled.");
        }
        else    
        {       
		return array("result" => 1, "text" => "Verified that NO_ZERO_DATE is not enabled for MySQL.");
        }
}

/******************************
 * FUNCTION: CHECK MYSQL SIZE *
 ******************************/
function check_mysql_size()
{
	// Open a database connection
	$db = db_open();

	 // Query for the size and free space
	$stmt = $db->prepare("SELECT table_schema, sum( data_length + index_length ) / 1024 / 1024 size, sum( data_free )/ 1024 / 1024 free FROM information_schema.TABLES WHERE table_schema='".DB_DATABASE."';");
	$stmt->execute();
	$array = $stmt->fetch();
	$size = $array['size'];
	$free = $array['free'];

	// Close the database connection
	db_close($db);

	// Get the percent remaining
	//$remaining_percent = $free / $size;

	return array("result" => 1, "text" => "SimpleRisk is using " . round($size, 2) . " MB of disk space.");
}

/**************************************
 * FUNCTION: CHECK ONLY FULL GROUP BY *
 **************************************/
function check_only_full_group_by()
{
        // Open a database connection
        $db = db_open();
        
        // Query for the current SQL mode
        $stmt = $db->prepare("SELECT @@sql_mode;");
        $stmt->execute();
        $array = $stmt->fetch();
        $sql_mode = $array['@@sql_mode'];
        
        // Close the database connection
        db_close($db);
        
        // If the row contains ONLY_FULL_GROUP_BY
        if (preg_match("/.*ONLY_FULL_GROUP_BY.*/", $sql_mode))
        {       
		return array("result" => 0, "text" => "SimpleRisk will not work properly with ONLY_FULL_GROUP_BY enabled.");
        }
        else    
        {       
		return array("result" => 1, "text" => "Verified that ONLY_FULL_GROUP_BY is not enabled for MySQL.");
        }
}

/*******************************
 * FUNCTION: CHECK PHP VERSION *
 *******************************/
function check_php_version()
{
	// Get the version of PHP
	if (!defined('PHP_VERSION_ID'))
	{
		$version = explode('.', PHP_VERSION);

		define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
	}

	// If PHP is at least 7
	if (PHP_VERSION_ID >= 70000)
	{
		return array("result" => 1, "text" => "SimpleRisk is running under PHP version " . phpversion() . ".");
	}
	// If this is PHP 5.x
	else if (PHP_VERSION_ID >= 50000 && PHP_VERSION_ID < 60000)
	{
		return array("result" => 0, "text" => "SimpleRisk will no longer run properly under PHP version " . phpversion() . ".  Please upgrade to PHP 7.");
	}
	else
	{
		return array("result" => 0, "text" => "SimpleRisk requires PHP 7 to run properly.");
	}
}

/*********************************
 * FUNCTION: CHECK EXTRA VERSION *
 *********************************/
function check_extra_versions($current_app_version)
{
	global $escaper;

        // Create an empty array
        $array = array();

        // If the instance is registered
        if (get_setting('registration_registered') != 0)
        {
		// If the upgrade extra exists
		if (file_exists(realpath(__DIR__ . '/../extras/upgrade/index.php')))
		{
			// Load the upgrade extra
			require_once(realpath(__DIR__ . '/../extras/upgrade/index.php'));

			// Get the list of available SimpleRisk Extras
			$extras = available_extras();

			// Check all purchases in one web service call
			$purchases = core_check_all_purchases();

			// For each available Extra
			foreach ($extras as $extra)
			{
				// If this is the Upgrade or ComplianceForge SCF Extra
				if ($extra['short_name'] == "upgrade" || $extra['short_name'] == "complianceforgescf")
				{
					// Set purchased to true
					$purchased = true;
					$expired = false;
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

						// If the expiration date is not set
						if ($expires == "0000-00-00 00:00:00")
						{
							$expired = false;
						}
						// If the expiration date has passed
						else if ($expires < date('Y-m-d h:i:s'))
						{
							$expired = true;
						}
						else $expired = false;
					}
					else $expires = "N/A";
				}


				// If the extra is purchased
				if ($purchased)
				{
					// If the extra is installed
					if (core_is_installed($extra['short_name']))
					{
						// If the extra license has not expired
						if (!$expired)
						{
							$array[] = array("result" => 1, "text" => "The SimpleRisk " . $escaper->escapeHtml($extra['long_name']) . " has been purchased and installed.");
						}
						// The license has expired
						else
						{
							$array[] = array("result" => 0, "text" => "Your license for the SimpleRisk " . $escaper->escapeHtml($extra['long_name']) . " has expired.");
						}

						// If this extra is compatible with this version of SimpleRisk
						if (extra_simplerisk_version_compatible($extra['short_name']))
						{
							$array[] = array("result" => 1, "text" => "The currently installed " . $escaper->escapeHtml($extra['long_name']) . " is compatible with this version of SimpleRisk.");
						}
						// This extra is not compatible
						else
						{
							$array[] = array("result" => 0, "text" => "The currently installed " . $escaper->escapeHtml($extra['long_name']) . " is not compatible with this version of SimpleRisk.");
						}

						// If we have the current version of the Extra
						if (core_extra_current_version($extra['short_name']) == latest_version($extra['short_name']))
						{
							$array[] = array("result" => 1, "text" => "You are running the most recent version of the " . $escaper->escapeHtml($extra['long_name']) . ".");
						}
						// We have an older version of the Extra
						else if (core_extra_current_version($extra['short_name']) < latest_version($extra['short_name']))
						{
							$array[] = array("result" => 0, "text" => "A newer version of the " . $escaper->escapeHtml($extra['long_name']) . " is available.");
						}
                        // If we have a newer version of the Extra
                        else if (core_extra_current_version($extra['short_name']) > latest_version($extra['short_name']))
                        {
                            $array[] = array("result" => 1, "text" => "You are running version " . core_extra_current_version($extra['short_name']) . " of the " . $escaper->escapeHtml($extra['long_name']) . ".  The current version is " . latest_version($extra['short_name']) . ".  You must be from the future.");
                        }
					}
					// The extra is not installed
					else
					{
						$array[] = array("result" => 0, "text" => "The SimpleRisk " . $escaper->escapeHtml($extra['long_name']) . " has been purchased but is not installed.");
					}
				}
			}
		}
	}

	return $array;
}

/********************************************
 * FUNCTION: CHECK USE DATABASE FOR SESSION *
 ********************************************/
function check_use_database_for_session()
{
	// If USE_DATABASE_FOR_SESSIONS is defined
	if (defined('USE_DATABASE_FOR_SESSIONS'))
	{
		// If USE_DATABASE_FOR_SESSIONS is set to false
		if (USE_DATABASE_FOR_SESSIONS == "false")
		{
			return array("result" => 0, "text" => "The USE_DATABASE_FOR_SESSIONS value is set to false in the config.php file.  SimpleRisk will function normally, however, this creates an issue with the one-click upgrade process.  We recommend setting the USE_DATABASE_FOR_SESSIONS to true.");
		}
		// If USE_DATABASE_FOR_SESSIONS is set to true
		else if (USE_DATABASE_FOR_SESSIONS == "true")
		{
			return array("result" => 1, "text" => "Using the database to store PHP session information.");
		}
	}
	else return array("result" => 0, "text" => "Unable to determine a value for USE_DATABASE_FOR_SESSIONS in the config.php file.");
}

/***********************************
 * FUNCTION: CHECK CRON CONFIGURED *
 ***********************************/
function check_cron_configured()
{
	// Get the cron_last_run setting
	$cron_last_run = get_setting("cron_last_run");

	// If the cron_last_run is false
	if ($cron_last_run === false)
	{
		// The cron hasn't been configured yet
		return array("result" => 0, "text" => "The automation cron hasn't been configured yet. Check the 'Backups' tab under Configure-> Settings to learn more.");
	}
	else
	{
		// If the cron was run within the past 3600 seconds (1 hour)
		if (time() - $cron_last_run <= 3600)
		{
			return array("result" => 1, "text" => "The automation cron is configured correctly.");
		}
		else
		{
			return array("result" => 0, "text" => "The automation cron hasn't run in the past hour. Check the 'Backups' tab under Configure-> Settings to learn more.");
		}
	}
}

/************************************
 * FUNCTION: CHECK PHP MEMORY LIMIT *
 ************************************/
function check_php_memory_limit()
{
	// Get the currently set memory limit
	$memory_limit = ini_get('memory_limit');

	// If the memory limit is not set
	if ($memory_limit === false)
	{
		return array("result" => 0, "text" => "No memory_limit value is set in the php.ini file and PHP is likely using the default value which is less than the current size of the SimpleRisk application.  SimpleRisk will function normally, however, this creates an issue with the one-click upgrade process.  We recommend setting the memory_limit value to 256M or higher.");
	}
	// If the memory limit is set to unlimited
	else if ($memory_limit == -1)
	{
		return array("result" => 1, "text" => "The memory_limit value in the php.ini file is set to -1.  This provides unlimited memory to PHP, which should be acceptable for the SimpleRisk application.");
	}
	// Otherwise
	else
	{
		// If the memory limit is a number followed by characters
		if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches))
		{
			// If the memory limit is in megabytes
			if ($matches[2] == 'M')
			{
				// Get the memory limit in bytes
				$memory_limit_bytes = $matches[1] * 1024 * 1024;
			}
			// If the memory limit is in kilobytes
			else if ($matches[2] == 'K')
			{
				// Get the memory limit in bytes
				$memory_limit_bytes = $matches[1] * 1024;
			}
			// If the memory limit is in Gigabytes
			else if ($matches[2] == 'G')
			{
				// Get the memory limit in bytes
				$memory_limit_bytes = $matches[1] * 1024 * 1024 * 1024;
			}
		}

		// Set the current SimpleRisk size in bytes
		$simplerisk_size_bytes = 180 * 1024 * 1024;

		// If the memory limit is less than the SimpleRisk size
		if ($memory_limit_bytes < $simplerisk_size_bytes)
		{
			return array("result" => 0, "text" => "The memory_limit value in the php.ini file is set to " . $memory_limit . ", which is less than the current size of the SimpleRisk application.  SimpleRisk will function normally, however, this creates an issue with the one-click upgrade process.  We recommend setting the memory_limit value to 256M or higher.");
		}
		// The memory limit is higher than the SimpleRisk size
		else
		{
			return array("result" => 1, "text" => "The memory_limit value in the php.ini file is set to " . $memory_limit . ".");
		}
	}
}

/**************************************
 * FUNCTION: CHECK PHP MAX INPUT VARS *
 **************************************/
function check_php_max_input_vars()
{
        // Get the currently set max_iput_vars
        $max_input_vars = ini_get('max_input_vars');

	// If the max_input_vars is not set
	if ($max_input_vars === false)
	{
		return array("result" => 0, "text" => "The max_input_vars value in the php.ini file is not explicitly set.  The default value of 1000 is too low and the SimpleRisk Dynamic Risk Report will not function properly with this configuration.  We recommend setting the max_input_vars to 3000.");
	}
	// If the max_input_vars is set
	else
	{
        	// If the max_input_vars is a number followed by characters
        	if (preg_match('/^(\d+)$/', $max_input_vars, $matches))
        	{
			// If the max_input_vars is 1000
			if ($max_input_vars == 1000)
			{
				return array("result" => 0, "text" => "The max_input_vars value in the php.ini file is set to the default value of 1000.  The SimpleRisk Dynamic Risk Report will not function properly with this configuration.  We recommend setting the max_input_vars to 3000.");
			}
			// If the max_input_vars is less than 3000
			else if ($max_input_vars < 3000)
			{
				return array("result" => 0, "text" => "The max_input_vars value in the php.ini file is set to {$max_input_vars}, which could cause issues with the SimpleRisk Dynamic Risk Report.  We recommend setting the max_input_vars to 3000.");
			}
			// If the max_input_vars is 3000 or higher
			else if ($max_input_vars >= 3000)
			{
				return array("result" => 1, "text" => "The max_input_vars value in the php.ini file is set to {$max_input_vars}.");
			}
		}
	}
}

/*******************************************
 * FUNCTION: CHECK SIMPLERISK BASE URL DNS *
 *******************************************/
function check_simplerisk_base_url_dns()
{
    // Get the server name
    $server_name = $_SERVER['SERVER_NAME'];

    // Regular expression to match a domain
    $domain_regex = "/^(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/";

    // If the server name is a domain
    if (filter_var($server_name, FILTER_VALIDATE_DOMAIN))
    {
        // See if we can get a DNS record for the domain
        $result = dns_get_record($server_name);

        // If a result was not returned
        if ($result === false)
        {
            return array("result" => 0, "text" => "The detected server name is a valid domain, but a DNS lookup was not successful.");
        }
        else
        {
            return array("result" => 1, "text" => "The detected server name is a valid domain and a DNS lookup was successful.");
        }
    }
    // If the server name is an IP address
    else if (filter_var($server_name, FILTER_VALIDATE_IP))
    {
        return array("result" => 1, "text" => "The detected server name is an IP address.");
    }
    else
    {
        // This is not a domain or IP and should be rejected
        return array("result" => 0, "text" => "The detected server name is not a valid domain or IP address.");
    }

    // If the base URL stored in settings and the one we are using are the same
    if ($simplerisk_base_url == $base_url)
    {
        return array("result" => 1, "text" => "Your SimpleRisk Base URL matches the URL you are using to connect to SimpleRisk.");
    }
    else
    {
        return array("result" => 0, "text" => "Your SimpleRisk Base URL does not match the URL you are using to connect to SimpleRisk.");
    }
}

/*************************************************
 * FUNCTION: UNABLE TO COMMUNICATE WITH DATABASE *
 *************************************************/
function unable_to_communicate_with_database() {
	
    $nocache_token = generate_token(5);
?>

<!DOCTYPE html>
<html dir="ltr" lang="en" xml:lang="en">
	<head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <!-- Favicon icon -->
        <link rel='shortcut icon' href='../favicon.ico' />
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="../css/style.min.css?<?= $nocache_token ?>" />
        <!-- jQuery Javascript -->
        <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $nocache_token ?>" id="script_jquery"></script>
        <!-- Bootstrap tether Core JavaScript -->
        <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js?<?= $nocache_token ?>" defer></script>
    </head>
    <body>
        <div class="preloader">
            <div class="lds-ripple">
                <div class="lds-pos"></div>
                <div class="lds-pos"></div>
            </div>
        </div>
        <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="none" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full" data-function="assessment">
            <header class="topbar" data-navbarbg="skin5">
                <nav class="navbar top-navbar navbar-expand-md navbar-dark">
                    <div class="navbar-header">
                        <a class="navbar-brand" href="https://www.simplerisk.com">
                            <img src="../images/logo@2x.png" alt="homepage" class="logo"/>
                        </a>
                    </div>
              		<div class="navbar-collapse collapse show" id="navbarSupportedContent" data-navbarbg="skin5">
                        <!-- Right side toggle and nav items -->
						<ul class="navbar-nav float-end ms-auto">
			  				<li class="nav-item dropdown">
                				<a href="index.php" style='color: var(--sr-light)'>Database Installation Script</a>
              				</li>
            			</ul>
          			</div>
                </nav>
            </header>
            <!-- ============================================================== -->
            <!-- Page wrapper  -->
            <div class="page-wrapper">
            	<div class="scroll-content">
            		<div class="content-wrapper">
                        <!-- container - It's the direct container of all the -->
                        <div class="content container-fluid">
							<h1 class="text-center welcome--msg">Unable to Communicate with the Database</h1>
							<br />
							<div>
								<p>SimpleRisk is unable to communicate with the database.  If SimpleRisk was already installed, try the following troubleshooting steps:</p>
								<ul>
									<li>Double-check your database credentials in the config.php file</li>
									<li>Try manually connecting to the database using the command '<i>mysql -h &lt;hostname&gt; -u &lt;username&gt; -p</i>' and specifying the password when prompted</li>
									<li>Contact support and provide a copy of any relevant messages from your web server's error log</li>
								</ul>
							</div>

                        </div>
                        <!-- End of content -->
                        <footer class="footer text-center">
                  			Copyright 2025 SimpleRisk, Inc. All rights reserved.
                		</footer>
                	</div>
                	<!-- End of content-wrapper -->
        		</div>
        		<!-- End of scroll-content -->
          	</div>
          <!-- End Page wrapper  -->
        </div>
        <!-- End Wrapper -->

    	<script>
        	$(function() {
        		// Fading out the preloader once everything is done rendering
        		$(".preloader").fadeOut();
            });
    	</script>
    </body>
</html>
<?php
}
?>