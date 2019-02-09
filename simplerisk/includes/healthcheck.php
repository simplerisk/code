<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(language_file());

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

	echo "<br /><b><u>Connectivity</u></b><br />";

        // Check the SimpleRisk database connectivity
        check_database_connectivity();

        // Check that SimpleRisk can communicate with the API
        check_api_connectivity();

        // Check that SimpleRisk can connect to the services platform
        check_web_connectivity();

	echo "<br /><b><u>PHP</u></b><br />";

	// Check that this is PHP 5
	//check_php_version();
	
	// Check the necessary PHP extensions are installed
	check_php_extensions();

	echo "<br /><b><u>MySQL</u></b><br />";

	// Check if MySQL STRICT SQL mode is enabled
	//check_strict_sql_mode();

	// Check if MySQL NO_ZERO_DATE mode is enabled
	check_no_zero_date();

	// Check if MySQL ONLY_FULL_GROUP_BY mode is enabled
	check_only_full_group_by();

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
	// URLs to check
	$urls = array("https://register.simplerisk.com", "https://services.simplerisk.com", "https://updates.simplerisk.com", "https://vfeed.simplerisk.com");

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

/**********************************
 * FUNCTION: CHECK PHP EXTENSIONS *
 **********************************/
function check_php_extensions()
{
	// List of extensions to check for
	$extensions = array("pdo", "pdo_mysql", "mcrypt", "json", "phar", "zlib", "mbstring", "ldap", "dom");

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

	// If PHP is at least 5 and less than 6
	if (PHP_VERSION_ID >= 50000 && PHP_VERSION_ID < 60000)
	{
		health_check_good("SimpleRisk is running under PHP 5.x.");
	}
	else
	{
		health_check_bad("SimpleRisk will not run properly under any version of PHP other than 5.x.");
	}
}

?>
