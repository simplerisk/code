<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(language_file());
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/extras.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/*************************************
 * FUNCTION: SIMPLERISK HEALTH CHECK *
 *************************************/
function simplerisk_health_check()
{
        // Set the default socket timeout for healthchecks to 5 seconds
        ini_set('default_socket_timeout', 5);

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

        // Check the current database size and free space
        $check_mysql_size = check_mysql_size();

        // Check if MySQL STRICT SQL mode is enabled
        //$check_strict_sql_mode = check_strict_sql_mode();

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

	echo "<div class=\"wrap\">\n";
	echo "  <ul class=\"tabs group\">\n";
	echo "    <li><a class=\"active\" href=\"#/summary\">Summary</a></li>\n";
	echo "    <li><a href=\"#/versions\">Versions</a></li>\n";
	echo "    <li><a href=\"#/extras\">Extras</a></li>\n";
	echo "    <li><a href=\"#/connectivity\">Connectivity</a></li>\n";
	echo "    <li><a href=\"#/php\">PHP</a></li>\n";
	echo "    <li><a href=\"#/mysql\">MySQL</a></li>\n";
	echo "    <li><a href=\"#/permissions\">Permissions</a></li>\n";
	echo "  </ul>\n";
	echo "  <div id=\"content\">\n";
	
	// Summary Tab
	echo "    <div id=\"summary\" class=\"settings_tab\">\n";
	echo "      <b><u>Health Check Summary</u></b><br />";

	// Versions Summary
	if ($check_app_version['result'] === 1 && $check_db_version['result'] === 1 && $check_same_app_and_db['result'] === 1)
	{
		health_check_good("Versions");
	}
	else health_check_bad("Versions");

	// Extras Summary
	if ($check_extra_versions_result === 1)
	{
		health_check_good("Extras");
	}
	else health_check_bad("Extras");

	// Connectivity Summary
	if ($check_simplerisk_base_url['result'] === 1 && $check_database_connectivity['result'] === 1 && $check_api_connectivity['result'] === 1 && $check_web_connectivity_result === 1)
	{
		health_check_good("Connectivity");
	}
	else health_check_bad("Connectivity");

	// PHP Summary
	if ($check_php_version['result'] === 1 && $check_php_extensions_result === 1)
	{
		health_check_good("PHP");
	}
	else health_check_bad("PHP");

	// MySQL Summary
	if ($check_no_zero_date['result'] === 1 && $check_only_full_group_by['result'] === 1 && $check_mysql_permission_select['result'] === 1 && $check_mysql_permission_insert['result'] === 1 && $check_mysql_permission_update['result'] === 1 && $check_mysql_permission_delete['result'] === 1 && $check_mysql_permission_create['result'] === 1 && $check_mysql_permission_drop['result'] === 1 && $check_mysql_permission_references['result'] === 1 && $check_mysql_permission_index['result'] === 1 && $check_mysql_permission_alter['result'] === 1)
	{
		health_check_good("MySQL");
	}
	else health_check_bad("MySQL");

	// Permissions Summary
	if ($check_simplerisk_directory_permissions_result === 1)
	{
		health_check_good("Permissions");
        }
        else health_check_bad("Permissions");

	echo "    </div>\n";

	// SimpleRisk Versions Tab
	echo "    <div id=\"versions\" style=\"display: none;\" class=\"settings_tab\">\n";
        echo "      <b><u>SimpleRisk Versions</u></b><br />";
        display_health_check_results($check_app_version);
	display_health_check_results($check_db_version);
	display_health_check_results($check_same_app_and_db);
        echo "    </div>\n";

        // SimpleRisk Extras Tab
        echo "    <div id=\"extras\" style=\"display: none;\" class=\"settings_tab\">\n";
	echo "      <b><u>SimpleRisk Extras</u></b><br />";
	display_health_check_array_results($check_extra_versions);
	echo "    </div>\n";

	// SimpleRisk Connectivity Tab
        echo "    <div id=\"connectivity\" style=\"display: none;\" class=\"settings_tab\">\n";
        echo "<b><u>Connectivity</u></b><br />";
	display_health_check_results($check_simplerisk_base_url);
	display_health_check_results($check_database_connectivity);
	display_health_check_results($check_api_connectivity);
	display_health_check_array_results($check_web_connectivity);
	echo "    </div>\n";

	// SimpleRisk PHP Tab
        echo "    <div id=\"php\" style=\"display: none;\" class=\"settings_tab\">\n";
        echo "<b><u>PHP</u></b><br />";
	display_health_check_results($check_php_version);
	display_health_check_array_results($check_php_extensions);
	echo "    </div>\n";

	// SimpleRisk MySQL Tab
        echo "    <div id=\"mysql\" style=\"display: none;\" class=\"settings_tab\">\n";
        echo "<b><u>MySQL</u></b><br />";
	display_health_check_results($check_mysql_size);
	//display_health_check_results($check_strict_sql_mode);
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
	echo "    </div>\n";

	// SimpleRisk Permissions Tab
        echo "    <div id=\"permissions\" style=\"display: none;\" class=\"settings_tab\">\n";
        echo "<b><u>File and Directory Permissions</u></b><br />";
	display_health_check_array_results($check_simplerisk_directory_permissions);
	echo "    </div>\n";

	echo "  </div>\n";
	echo "</div>\n";
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
        else
        {
		return array("result" => 0, "text" => "Running an outdated version (" . $current_app_version . ") of the SimpleRisk application.");
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
		return array("result" => 1, "text" => "Running the current version (" . $current_db_version . ") of the SimpleRisk database schema.");
        }
        else
        {
		return array("result" => 0, "text" => "Running an outdated version (" . $current_db_version . ") of the SimpleRisk database schema.");
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
        $urls = array("https://register.simplerisk.com", "https://services.simplerisk.com", "https://updates.simplerisk.com", "https://olbat.github.io");

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
/*
                if (get_headers($url, 1))
                {
			write_debug_log("SimpleRisk connected to " . $url);
			$array[] = array("result" => 1, "text" => "SimpleRisk connected to " . $url . ".");
                }
                else
                {
			write_debug_log("SimpleRisk was unable to connect to " . $url);
			$array[] = array("result" => 0, "text" => "SimpleRisk was unable to connect to " . $url . ".");
                }
*/
        }

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
	$extensions = array("pdo", "pdo_mysql", "json", "phar", "zlib", "mbstring", "ldap", "dom");

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
	// Get the SimpleRisk base URL
	$base_url = $_SESSION['base_url'];

	// Create the whoami URL
	$url = $base_url . "/api/whoami";

	// Set the default proxy stream context
	$context = set_proxy_stream_context();

	// Set the default socket timeout to 5 seconds
	ini_set('default_socket_timeout', 5);

	// Get the file headers for the API URL
	$file_headers = @get_headers($url, 1);

	// If we were unable to connect to the URL
	if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found')
	{
		return array("result" => 0, "text" => "Unable to communicate with the SimpleRisk API.");
	}
	// We were able to connect to the URL
	else
	{
		$code = substr($file_headers[0], 9, 3);

		// If the response code is success or unauthorized
		if ($code == 200 || $code = 401)
		{
			return array("result" => 1, "text" => "Communicated with the SimpleRisk API successfully.");
		}
		else
		{
			return array("result" => 0, "text" => "Unable to communicate with the SimpleRisk API.");
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
		return array("result" => 1, "text" => "SimpleRisk is running under PHP 7.");
	}
	// If this is PHP 5.x
	else if (PHP_VERSION_ID >= 50000 && PHP_VERSION_ID < 60000)
	{
		return array("result" => 0, "text" => "SimpleRisk will no longer run properly under PHP version 5.x.  Please upgrade to PHP 7.");
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

			// For each available Extra
			foreach ($extras as $extra)
			{
				// If the extra is purchased
				if (core_is_purchased($extra['short_name']))
				{
					// If the extra is installed
					if (core_is_installed($extra['short_name']))
					{
						$array[] = array("result" => 1, "text" => "The SimpleRisk " . $escaper->escapeHtml($extra['long_name']) . " has been purchased and installed.");

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
						// We do not have the current version of the Extra
						else
						{
							$array[] = array("result" => 0, "text" => "A newer version of the " . $escaper->escapeHtml($extra['long_name']) . " is available.");
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

?>
