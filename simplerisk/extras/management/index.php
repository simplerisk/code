<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of a non-SimpleRisk    *
 * user with the proper API key to retrieve metrics on the system,  *
 * view the database backup file, and rotate the API key as needed. *
 * This Extra should never be provided to a customer and is only    *
 * used by SimpleRisk, LLC in order to manage customer accounts.    *
 ********************************************************************/

// Extra Version
define('MANAGEMENT_EXTRA_VERSION', '20180104-001');

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/services.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_management_extra_database();

// Enable the extra
enable_management_extra();

// If the key is set and is valid
if (isset($_GET['key']) && check_valid_key($_GET['key']))
{
	// Check if key should be rotated
	if (isset($_GET['rotate_key']) && $_GET['rotate_key'] == "true")
	{
		rotate_key();
	}

	// Check if the database backup should be displayed
	if (isset($_GET['backup']) && $_GET['backup'] == "true")
        {
                backup();
	}

	// Check if the simplerisk metrics should be displayed
	if (isset($_GET['metrics']) && $_GET['metrics'] == "true")
        {
                metrics();
	}

	// Check if an upgrade is necessary and, if so, upgrade
	if (isset($_GET['upgrade']) && $_GET['upgrade'] == "true")
	{
		upgrade();
	}

	// Check if the extras need to be upgraded and, if so, upgrade
	if (isset($_GET['upgrade_extras']) && $_GET['upgrade_extras'] == "true")
	{
		upgrade_extras();
	}

	// Check if the extras should be enabled
	if (isset($_GET['enable_extras']) && $_GET['enable_extras'] == "true")
	{
		enable_extras();
	}

        // Check if the extras should be disabled
        if (isset($_GET['disable_extras']) && $_GET['disable_extras'] == "true")
        {
                disable_extras();
        }
}

/*************************************
 * FUNCTION: ENABLE MANAGEMENT EXTRA *
 *************************************/
function enable_management_extra()
{
        // Open the database connection
        $db = db_open();

        // Query the database
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'management', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
        $stmt->execute();

        // Close the database connection
        db_close($db);

	// If the API key doesn't exist
	if (!get_management_api_key())
	{
		// Create the API key
		create_management_api_key();
	}

	// Register the SimpleRisk Instance
	register_simplerisk_instance();

	// Download the upgrade extra
	download_extra("upgrade");
}

/******************************************
 * FUNCTION: REGISTER SIMPLERISK INSTANCE *
 ******************************************/
function register_simplerisk_instance()
{
        // If SimpleRisk is not registered
        if (get_setting('registration_registered') == 0)
        {
                // Set the registration values
                $name = "SimpleRisk Managed Instance";

		// If we are able to get a host name
		if (gethostname() != false)
		{
			// The company is the host name
                	$company = gethostname();

		}
		// Otherwise, company is N/A
		else $company = "N/A";

                $title = "N/A";
                $phone = "N/A";
                $email = "N/A";

                // Add the registration
                $result = add_registration($name, $company, $title, $phone, $email);
        }
}

/***************************************
 * FUNCTION: CREATE MANAGEMENT API KEY *
 ***************************************/
function create_management_api_key()
{
        $akey = generate_token(40);

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name`='api_key', `value`= :akey");
        $stmt->bindParam(":akey", $akey, PDO::PARAM_STR, 40);
        $stmt->execute();

        // Close the database connection
        db_close($db);

	echo $akey;
}

/************************************
 * FUNCTION: GET MANAGEMENT API KEY *
 ************************************/
function get_management_api_key()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT value FROM `settings` WHERE `name`='api_key'");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // If the array is empty
        if (empty($array))
        {
		// Return false
                return false;
        }
        else return $array[0]['value'];
}

/*****************************
 * FUNCTION: CHECK VALID KEY *
 *****************************/
function check_valid_key($key)
{
	//If the key is correct
	if ($key == get_management_api_key())
	{
		return true;
	}
	else return false;
}

/************************
 * FUNCTION: ROTATE KEY *
 ************************/
function rotate_key()
{
	// Open the database connection
	$db = db_open();

        // Delete the old API key
        $stmt = $db->prepare("DELETE FROM `settings` WHERE `name`='api_key'");
        $stmt->execute();

        // Close the database connection
        db_close($db);

	// Create the new API key
	create_management_api_key();
}

/***************************
 * FUNCTION: ENABLE EXTRAS *
 ***************************/
function enable_extras()
{
        // If the API Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../api')))
        {
		// Include the API Extra
		require_once(realpath(__DIR__ . '/../api/index.php'));

		// Enable the API Extra
		enable_api_extra();
	}

        // If the Assessments Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../assessments')))
        {
                // Include the Assessments Extra
                require_once(realpath(__DIR__ . '/../assessments/index.php'));

		// Enable the Assessments Extra
		enable_assessments_extra();
        }

        // If the Authentication Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../authentication')))
        {
                // Include the Authentication Extra
                require_once(realpath(__DIR__ . '/../authentication/index.php'));

		// Enable the Authentication Extra
		enable_authentication_extra();
        }

        // If the Encryption Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../encryption')))
        {
                // Include the Encryption Extra
                require_once(realpath(__DIR__ . '/../encryption/index.php'));

		// DO NOT Enable the Encryption Extra
		//enable_encryption_extra();
        }

        // If the Import Export Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../import-export')))
        {
                // Include the Import Export Extra
                require_once(realpath(__DIR__ . '/../import-export/index.php'));

		// Enable the Import Export Extra
		enable_import_export_extra();
        }

        // If the Notification Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../notification')))
        {
                // Include the Notification Extra
                require_once(realpath(__DIR__ . '/../notification/index.php'));
        
		// Enable the Notification Extra
		enable_notification_extra();
        }

        // If the Team Separation Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../separation')))
        {
                // Include the Team Separation Extra
                require_once(realpath(__DIR__ . '/../separation/index.php'));
        
		// Enable the Team Separation Extra
		enable_team_separation_extra();
        }
}

/****************************
 * FUNCTION: DISABLE EXTRAS *
 ****************************/
function disable_extras()
{
        // If the API Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../api')))
        {
                // Include the API Extra
                require_once(realpath(__DIR__ . '/../api/index.php'));

                // Disable the API Extra
                disable_api_extra();
        }

        // If the Assessments Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../assessments')))
        {
                // Include the Assessments Extra
                require_once(realpath(__DIR__ . '/../assessments/index.php'));

                // Disable the Assessments Extra
                disable_assessments_extra();
        }

        // If the Authentication Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../authentication')))
        {
                // Include the Authentication Extra
                require_once(realpath(__DIR__ . '/../authentication/index.php'));

                // Disable the Authentication Extra
                disable_authentication_extra();
        }

        // If the Encryption Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../encryption')))
        {
                // Include the Encryption Extra
                require_once(realpath(__DIR__ . '/../encryption/index.php'));

                // DO NOT Disable the Encryption Extra
                //disable_encryption_extra();
        }

        // If the Import Export Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../import-export')))
        {
                // Include the Import Export Extra
                require_once(realpath(__DIR__ . '/../import-export/index.php'));

                // Disable the Import Export Extra
                disable_import_export_extra();
        }

        // If the Notification Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../notification')))
        {
                // Include the Notification Extra
                require_once(realpath(__DIR__ . '/../notification/index.php'));

                // Disable the Notification Extra
                disable_notification_extra();
        }

        // If the Team Separation Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../separation')))
        {
                // Include the Team Separation Extra
                require_once(realpath(__DIR__ . '/../separation/index.php'));

                // Disable the Team Separation Extra
                disable_team_separation_extra();
        }
}

/********************
 * FUNCTION: BACKUP *
 ********************/
function backup()
{
	// Include the required files
	require_once(realpath(__DIR__ . '/../../includes/config.php'));

	// Sanitize the mysqldump command
	$cmd = "mysqldump --opt --lock-tables=false -h " . escapeshellarg(DB_HOSTNAME) . " -u " . escapeshellarg(DB_USERNAME) . " -p" . escapeshellarg(DB_PASSWORD) . " " . escapeshellarg(DB_DATABASE);

	// Execute the mysqldump
	system($cmd);
}

/*********************
 * FUNCTION: METRICS *
 *********************/
function metrics()
{
	global $escaper;

	// Open the database connection
        $db = db_open();

        header("Content-type: text/xml; charset=UTF-8");

	echo "<?xml version=\"1.0\" encoding=\"utf8\"?>";
        echo "<metrics>";
        echo "<risks>";

        $stmt = $db->prepare("SELECT COUNT(id) FROM risks WHERE status != 'Closed'");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $open = $array[0][0];

        echo "<metric>";
        echo "<name>open</name>";
        echo "<value>" . $escaper->escapeHtml($open) . "</value>";
        echo "</metric>";

        $stmt = $db->prepare("SELECT COUNT(id) FROM risks WHERE status = 'Closed'");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $closed = $array[0][0];

        echo "<metric>";
        echo "<name>closed</name>";
        echo "<value>" . $escaper->escapeHtml($closed) . "</value>";
        echo "</metric>";

        $total = $open + $closed;

        echo "<metric>";
        echo "<name>total</name>";
        echo "<value>" . $escaper->escapeHtml($total) . "</value>";
        echo "</metric>";
        echo "</risks>";
        echo "<users>";

        $stmt = $db->prepare("SELECT COUNT(value) FROM user");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $total = $array[0][0];

        echo "<metric>";
        echo "<name>total</name>";
        echo "<value>" . $escaper->escapeHtml($total) . "</value>";
        echo "</metric>";

        $stmt = $db->prepare("SELECT last_login FROM user ORDER BY last_login desc");
        $stmt->execute();
        $array = $stmt->fetchAll();
        $last_login = $array[0][0];

        echo "<metric>";
        echo "<name>last_login</name>";
        echo "<value>" . $escaper->escapeHtml($last_login) . "</value>";
        echo "</metric>";
        echo "</users>";
	echo "<system>";
	echo "<metric>";
	echo "<name>app_version</name>";
	echo "<value>" . $escaper->escapeHtml(current_version("app")) . "</value>";
	echo "</metric>";
        echo "<metric>";
        echo "<name>db_version</name>";
        echo "<value>" . $escaper->escapeHtml(current_version("db")) . "</value>";
        echo "</metric>";

	// If the Upgrade Extra directory exists
	if (is_dir(realpath(__DIR__ . '/../upgrade')))
	{
		// Require the upgrade Extra
		require_once(realpath(__DIR__ . '/../upgrade/index.php'));

        	echo "<metric>";
        	echo "<name>upgrade_extra_version</name>";
        	echo "<value>" . $escaper->escapeHtml(upgrade_extra_version()) . "</value>";
        	echo "</metric>";
        	echo "<metric>";
        	echo "<name>authentication_extra_version</name>";
        	echo "<value>" . $escaper->escapeHtml(authentication_extra_version()) . "</value>";
        	echo "</metric>";
        	echo "<metric>";
        	echo "<name>encryption_extra_version</name>";
        	echo "<value>" . $escaper->escapeHtml(encryption_extra_version()) . "</value>";
        	echo "</metric>";
        	echo "<metric>";
        	echo "<name>importexport_extra_version</name>";
        	echo "<value>" . $escaper->escapeHtml(importexport_extra_version()) . "</value>";
        	echo "</metric>";
        	echo "<metric>";
        	echo "<name>notification_extra_version</name>";
        	echo "<value>" . $escaper->escapeHtml(notification_extra_version()) . "</value>";
        	echo "</metric>";
        	echo "<metric>";
        	echo "<name>separation_extra_version</name>";
        	echo "<value>" . $escaper->escapeHtml(separation_extra_version()) . "</value>";
        	echo "</metric>";
        	echo "<metric>";
        	echo "<name>assessments_extra_version</name>";
        	echo "<value>" . $escaper->escapeHtml(assessments_extra_version()) . "</value>";
        	echo "</metric>";
        	echo "<metric>";
        	echo "<name>api_extra_version</name>";
        	echo "<value>" . $escaper->escapeHtml(api_extra_version()) . "</value>";
        	echo "</metric>";
	}

	echo "</system>";
        echo "</metrics>";

        // Close the database connection
        db_close($db);
}

/*********************
 * FUNCTION: UPGRADE *
 *********************/
function upgrade()
{
	// Get the current application version
	$current_version = current_version("app");

	// Get the next application version
	$next_version = next_app_version($current_version);

	// If the current version is not the latest
	if ($next_version != "")
	{
		echo "Update required<br />\n";

		// Get the file name for the next version to ugprade to
		$file_name = "simplerisk-" . $next_version;

                // Delete current files
                echo "Deleting existing files.<br />\n";
		$file = sys_get_temp_dir() . "/" . $file_name . ".tgz";
		if (file_exists($file)) delete_file($file);
		$file = sys_get_temp_dir() . "/" . $file_name . ".tar";
		if (file_exists($file)) delete_file($file);
		$file = sys_get_temp_dir() . "/config.php";
		if (file_exists($file)) delete_file($file);

		// Download the file to tmp
		echo "Downloading the latest version.<br />\n";
		file_put_contents(sys_get_temp_dir() . "/" . $file_name . ".tgz", fopen("https://github.com/simplerisk/bundles/raw/master/" . $file_name . ".tgz", 'r'));

		// Path to the SimpleRisk directory
		$simplerisk_dir = realpath(__DIR__ . "/../../");

		// Backup the config file to tmp
		echo "Backing up the config file.<br />\n";
		copy ($simplerisk_dir . "/includes/config.php", sys_get_temp_dir() . "/config.php");

		// Decompress from gz
		echo "Decompressing the downloaded file.<br />\n";
		$p = new PharData(sys_get_temp_dir() . "/" . $file_name . ".tgz");
		$p->decompress();

		// Extract the tar to the tmp directory
		echo "Extracting the downloaded file.<br />\n";
		$phar = new PharData(sys_get_temp_dir() . "/" . $file_name . ".tar");
		$phar->extractTo(sys_get_temp_dir() . "/", null, true);

		// Overwrite the old version with the new version
		echo "Copying the new files over the old.<br />\n";
		recurse_copy(sys_get_temp_dir() . "/simplerisk", $simplerisk_dir);

		// Copy the old config file back
		echo "Replacing the config file with the original.<br />\n";
		copy (sys_get_temp_dir() . "/config.php", $simplerisk_dir . "/includes/config.php");

		// Clean up files
		echo "Cleaning up temporary files.<br />\n";
		$file = sys_get_temp_dir() . "/" . $file_name . ".tgz";
		delete_file($file);
		$file = sys_get_temp_dir() . "/" . $file_name . ".tar";
		delete_file($file);
		$dir = sys_get_temp_dir() . "/simplerisk";
		delete_dir($dir);
		$file = sys_get_temp_dir() . "/config.php";
		delete_file($file);
	}
	else echo "You are already at the latest version of SimpleRisk.\n";
}

/****************************
 * FUNCTION: UPGRADE EXTRAS *
 ****************************/
function upgrade_extras()
{
	// If the Upgrade Extra directory exists
	if (is_dir(realpath(__DIR__ . '/../upgrade')))
	{
		// Download the Upgrade Extra
		$result = download_extra("upgrade");
	}

        // If the API Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../api')))
        {
		// Download the API Extra
		$result = download_extra("api");
        }

        // If the Assessments Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../assessments')))
        {
		// Download the Assessments Extra
		$result = download_extra("assessments");
        }

        // If the Authentication Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../authentication')))
        {
		// Download the Authentication Extra
		$result = download_extra("authentication");
        }

        // If the Encryption Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../encryption')))
        {
		// Download the Encryption Extra
		$result = download_extra("encryption");
        }

        // If the Import Export Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../import-export')))
        {
		// Download the Import-Export Extra
		$result = download_extra("import-export");
        }

        // If the Notification Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../notification')))
        {
		// Download the Notification Extra
		$result = download_extra("notification");
        }

        // If the Team Separation Extra directory exists
        if (is_dir(realpath(__DIR__ . '/../separation')))
        {
		// Download the Separation Extra
		$result = download_extra("separation");
        }
}

/******************************
 * FUNCTION: NEXT APP VERSION *
 ******************************/
function next_app_version($current_version)
{
	$version_page = file('https://updates.simplerisk.com/upgrade_path.xml');

	$regex_pattern = "/<simplerisk-" . $current_version . ">(.*)<\/simplerisk-" . $current_version . ">/";

	foreach ($version_page as $line)
	{
		if (preg_match($regex_pattern, $line, $matches))
		{
			$next_version = $matches[1];
		}
	}

	// Return the next version
	return $next_version;
}

?>
