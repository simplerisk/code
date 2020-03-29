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
	$current_app_version = current_version("app");
	$latest_app_version = latest_version("app");
	$current_db_version = current_version("db");
	$latest_db_version = latest_version("db");

	echo "<b><u>SimpleRisk Versions</u></b><br />";

	// Check that we are running the latest version of the SimpleRisk application
	check_app_version($current_app_version, $latest_app_version);

	// Check that we are running the latest version of the SimpleRisk database
	check_db_version($current_db_version, $latest_db_version);

	// Check that the application and database versions are the same
	check_same_app_and_db($current_app_version, $current_db_version);

	// Check the Extra versions match the SimpleRisk version
	check_extra_versions($current_app_version);

	echo "<br /><b><u>Connectivity</u></b><br />";

        // Check the SimpleRisk database connectivity
        check_database_connectivity();

        // Check that SimpleRisk can communicate with the API
        check_api_connectivity();

        // Check that SimpleRisk can connect to the services platform
        check_web_connectivity();

	echo "<br /><b><u>PHP</u></b><br />";

	// Check that this is PHP 7
	check_php_version();
	
	// Check the necessary PHP extensions are installed
	check_php_extensions();

	echo "<br /><b><u>MySQL</u></b><br />";

	// Check the current database size and free space
	check_mysql_size();

	// Check if MySQL STRICT SQL mode is enabled
	//check_strict_sql_mode();

	// Check if MySQL NO_ZERO_DATE mode is enabled
	check_no_zero_date();

	// Check if MySQL ONLY_FULL_GROUP_BY mode is enabled
	check_only_full_group_by();

        // Check if SELECT permission is enabled
        check_mysql_permission("SELECT");

        // Check if INSERT permission is enabled
        check_mysql_permission("INSERT");

        // Check if UPDATE permission is enabled
        check_mysql_permission("UPDATE");

        // Check if DELETE permission is enabled
        check_mysql_permission("DELETE");

        // Check if CREATE permission is enabled
        check_mysql_permission("CREATE");

        // Check if DROP permission is enabled
        check_mysql_permission("DROP");

        // Check if REFERENCES permission is enabled
        check_mysql_permission("REFERENCES");

        // Check if INDEX permission is enabled
        check_mysql_permission("INDEX");

        // Check if ALTER permission is enabled
        check_mysql_permission("ALTER");

	echo "<br /><b><u>File and Directory Permissions</u></b><br />";

	// Check the simplerisk directory permissions
	check_simplerisk_directory_permissions();
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
                health_check_good("Running the current version (" . $current_app_version . ") of the SimpleRisk application.");
        }
        else
        {
                health_check_bad("Running an outdated version (" . $current_app_version . ") of the SimpleRisk application.");
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
                health_check_good("Running the current version (" . $current_db_version . ") of the SimpleRisk database schema.");
        }
        else
        {
                health_check_bad("Running an outdated version (" . $current_db_version . ") of the SimpleRisk database schema.");
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
		health_check_good("The SimpleRisk application and database are at the same versions (" . $current_db_version . ").");
	}
	else
	{
		health_check_bad("The SimpleRisk application (" . $current_app_version . ") and database (" . $current_db_version . ") versions are not the same.");
	}
}

/****************************************************
 * FUNCTION: CHECK SIMPLERISK DIRECTORY PERMISSIONS *
 ****************************************************/
function check_simplerisk_directory_permissions()
{
	$simplerisk_dir = realpath(__DIR__ . '/..');

	// If the simplerisk directory is writeable
	if (is_writeable($simplerisk_dir))
	{
		health_check_good("The SimpleRisk directory (" . $simplerisk_dir . ") is writeable by the web user.");
	}
	else
	{
		health_check_bad("The SimpleRisk directory (" . $simplerisk_dir . ") is not writeable by the web user.");
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
				health_check_bad($name . " is not writeable by the web user.");
			}
		}
	}
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

	// Check the URLs 
	foreach ($urls as $url)
	{
		if (get_headers($url, 1))
		{
			health_check_good("SimpleRisk connected to " . $url . ".");
		}
		else
		{
			health_check_bad("SimpleRisk was unable to connect to " . $url . ".");
		}
	}
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
                        health_check_good("The '" . $escaper->escapeHtml($permission) . "' permssion has been set for the '" . $escaper->escapeHtml(DB_USERNAME) . "' user.");
                        
                        // Set the permission found to true
                        $permission_found = true;
                }
        }
        
        // If we did not find the permission
        if ($permission_found == false)
        {       
                health_check_bad("The '" . $escaper->escapeHtml($permission) . "' permssion is not set for the '" . $escaper->escapeHtml(DB_USERNAME) . "' user.");
        }
}

/**********************************
 * FUNCTION: CHECK PHP EXTENSIONS *
 **********************************/
function check_php_extensions()
{
	// List of extensions to check for
	$extensions = array("pdo", "pdo_mysql", "json", "phar", "zlib", "mbstring", "ldap", "dom");

	// For each extension
	foreach ($extensions as $extension)
	{
		if (extension_loaded($extension))
		{
			health_check_good("The PHP \"" . $extension . "\" extension is loaded.");
		}
		else
		{
			health_check_bad("The PHP \"" . $extension . "\" extension is not loaded.");
		}
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

		health_check_good("Communicated with the SimpleRisk database successfully.");
	}
	else
	{
		health_check_bad("Unable to communicate with the SimpleRisk database.");
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

	// Test the API URL
	$headers = get_headers($url);
	$code = substr($headers[0], 9, 3);

	// If the response code is success or unauthorized
	if ($code == 200 || $code = 401)
	{
		health_check_good("Communicated with the SimpleRisk API successfully.");
	}
	else
	{
		health_check_bad("Unable to communicate with the SimpleRisk API.");
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
		health_check_bad("SimpleRisk will not work properly with STRICT_TRANS_TABLES enabled.");
	}
	else
	{
		health_check_good("Verified that STRICT_TRANS_TABLES is not enabled for MySQL.");
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
                health_check_bad("SimpleRisk will not work properly with NO_ZERO_DATE enabled.");
        }
        else    
        {       
                health_check_good("Verified that NO_ZERO_DATE is not enabled for MySQL.");
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

	health_check_good("SimpleRisk is using " . round($size, 2) . " MB of disk space.");
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
                health_check_bad("SimpleRisk will not work properly with ONLY_FULL_GROUP_BY enabled.");
        }
        else    
        {       
                health_check_good("Verified that ONLY_FULL_GROUP_BY is not enabled for MySQL.");
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
		health_check_good("SimpleRisk is running under PHP 7.");
	}
	// If this is PHP 5.x
	else if (PHP_VERSION_ID >= 50000 && PHP_VERSION_ID < 60000)
	{
		health_check_bad("SimpleRisk will no longer run properly under PHP version 5.x.  Please upgrade to PHP 7.");
	}
	else
	{
		health_check_bad("SimpleRisk requires PHP 7 to run properly.");
	}
}



/*********************************
 * FUNCTION: CHECK EXTRA VERSION *
 *********************************/
function check_extra_versions($current_app_version)
{
	global $escaper;

        // If the instance is registered
        if (get_setting('registration_registered') != 0)
        {
		// If the upgrade extra exists
		if (file_exists(realpath(__DIR__ . '/../extras/upgrade/index.php')))
		{
			// Load the upgrade extra
			require_once(realpath(__DIR__ . '/../extras/upgrade/index.php'));

	                echo "<br /><b><u>SimpleRisk Extras</u></b><br />";

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
						health_check_good("The SimpleRisk " . $escaper->escapeHtml($extra['long_name']) . " has been purchased and installed.");

						// If this extra is compatible with this version of SimpleRisk
						if (extra_simplerisk_version_compatible($extra['short_name']))
						{
							health_check_good("The currently installed " . $escaper->escapeHtml($extra['long_name']) . " is compatible with this version of SimpleRisk.");
						}
						// This extra is not compatible
						else
						{
							health_check_bad("The currently installed " . $escaper->escapeHtml($extra['long_name']) . " is not compatible with this version of SimpleRisk.");
						}

						// If we have the current version of the Extra
						if (core_extra_current_version($extra['short_name']) == latest_version($extra['short_name']))
						{
							health_check_good("You are running the most recent version of the " . $escaper->escapeHtml($extra['long_name']) . ".");
						}
						// We do not have the current version of the Extra
						else
						{
							health_check_bad("A newer version of the " . $escaper->escapeHtml($extra['long_name']) . " is available.");
						}
					}
					// The extra is not installed
					else
					{
						health_check_bad("The SimpleRisk " . $escaper->escapeHtml($extra['long_name']) . " has been purchased but is not installed.");	
					}
				}
			}
		}
	}
}

?>
