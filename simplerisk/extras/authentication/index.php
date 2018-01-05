<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to add   *
 * users who use LDAP credentials for authentication as well as the *
 * ability to add a second factor of authentication with Duo        *
 * Security.
 ********************************************************************/

// Extra Version
define('AUTHENTICATION_EXTRA_VERSION', '20180104-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/duo_php/duo_web.php'));
require_once(realpath(__DIR__ . '/toopher-php/lib/toopher_api.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_authentication_extra_database();

/*****************************************
 * FUNCTION: ENABLE AUTHENTICATION EXTRA *
 *****************************************/
function enable_authentication_extra()
{
	// Open the database connection
	$db = db_open();

	// Query the database
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'custom_auth', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
	$stmt->execute();

	// Add default values
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'TRUSTED_DOMAINS', `value` = 'sts.windows.net, login.windows.net, dev.simplerisk.com'");
    $stmt->execute();
    
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'BIND_FIRST', `value` = 'false'");
	$stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'BIND_ACCOUNT', `value` = 'CN=username,OU=Users,DC=Company,DC=Corp,DC=Domain,DC=COM'");
	$stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'BIND_ACCOUNT_PASS', `value` = ''");
	$stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'TLS', `value` = 'false'");
	$stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SASL', `value` = 'false'");
        $stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAP_VERSION', `value` = 'false'");
        $stmt->execute();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CHASE_REFERRALS', `value` = '3'");
        $stmt->execute();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAPHOST', `value` = 'yourldaphost.yourdomain.com'");
        $stmt->execute();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAPPORT', `value` = '389'");
        $stmt->execute();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'USERDN', `value` = 'OU=Users,DC=Company,DC=Corp,DC=Domain,DC=COM'");
        $stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'IKEY', `value` = ''");
	$stmt->execute();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SKEY', `value` = ''");
        $stmt->execute();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'HOST', `value` = ''");
        $stmt->execute();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CONSUMERKEY', `value` = ''");
        $stmt->execute();
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CONSUMERSECRET', `value` = ''");
        $stmt->execute();
	$stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'IDP'");
	$stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'USERNAME_ATTRIBUTE', `value` = 'uid'");
	$stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SAML_METADATA_URL', `value` = 'https://your.saml.provider.com/sso/saml/metadata'");
    $stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SAML_METADATA_XML', `value` = ''");
	$stmt->execute();
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SAML_USERNAME_MATCH', `value` = 'attribute'");
	$stmt->execute();

	// Import an existing configuration file and remove it
	import_and_remove_authentication_config_file();

	// Close the database connection
	db_close($db);

	// Create a Duo Auth application secret key
	create_duo_akey();
}

/******************************************
 * FUNCTION: DISABLE AUTHENTICATION EXTRA *
 ******************************************/
function disable_authentication_extra()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'custom_auth'");
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/*****************************
 * FUNCTION: UPDATE SETTINGS *
 *****************************/
if (!function_exists('update_settings')) {
function update_settings($configs)
{
    // Open the database connection
    $db = db_open();

    // If the TRUSTED_DOMAINS value is not empty
    if ($configs['TRUSTED_DOMAINS'] != "")
    {
        // Update the BIND_FIRST value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'TRUSTED_DOMAINS'");
        $stmt->bindParam(":value", $configs['TRUSTED_DOMAINS']);
        $stmt->execute();
    }

	// If the BIND_FIRST value is not empty
	if ($configs['BIND_FIRST'] != "")
	{
		// Update the BIND_FIRST value
		$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'BIND_FIRST'");
		$stmt->bindParam(":value", $configs['BIND_FIRST']);
		$stmt->execute();
	}

	// If the BIND_ACCOUNT value is not empty
	if ($configs['BIND_ACCOUNT'] != "")
	{
		// Update the BIND_ACCOUNT value
		$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'BIND_ACCOUNT'");
		$stmt->bindParam(":value", $configs['BIND_ACCOUNT']);
		$stmt->execute();
	}

	// If the BIND_ACCOUNT_PASS value is not empty
	if ($configs['BIND_ACCOUNT_PASS'] != "")
	{
		// Update the BIND_ACCOUNT_PASS value
		$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'BIND_ACCOUNT_PASS'");
		$stmt->bindParam(":value", $configs['BIND_ACCOUNT_PASS']);
		$stmt->execute();
	}

        // If the TLS value is not empty
	if ($configs['TLS'] != "")
	{
		// Update the TLS value
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'TLS'");
		$stmt->bindParam(":value", $configs['TLS']);
        	$stmt->execute();
	}

	// If the SASL value is not empty
	if ($configs['SASL'] != "")
	{
		// Update the SASL value
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SASL'");
        	$stmt->bindParam(":value", $configs['SASL']);
        	$stmt->execute();
	}

	// If the LDAP VERSION is not empty
	if ($configs['LDAP_VERSION'] != "")
	{
		// Update the LDAP VERSION
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'LDAP_VERSION'");
        	$stmt->bindParam(":value", $configs['LDAP_VERSION']);
        	$stmt->execute();
	}

	// If CHASE REFERRALS is not empty
	if ($configs['CHASE_REFERRALS'] != "")
	{
		// Update CHASE REFERRALS
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CHASE_REFERRALS'");
        	$stmt->bindParam(":value", $configs['CHASE_REFERRALS']);
        	$stmt->execute();
	}

	// If the LDAP HOST is not empty
	if ($configs['LDAPHOST'] != "")
	{
		// Update the LDAP HOST
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'LDAPHOST'");
        	$stmt->bindParam(":value", $configs['LDAPHOST']);
        	$stmt->execute();
	}

	// If the LDAP PORT is not empty
	if ($configs['LDAPPORT'] != "")
	{
		// Update the LDAP PORT
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'LDAPPORT'");
        	$stmt->bindParam(":value", $configs['LDAPPORT']);
        	$stmt->execute();
	}

	// If the USERDN is not empty
	if ($configs['USERDN'] != "")
	{
		// Update the USERDN
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'USERDN'");
        	$stmt->bindParam(":value", $configs['USERDN']);
        	$stmt->execute();
	}

	// If the IKEY is not empty
	if ($configs['IKEY'] != "")
	{
		// Update the IKEY
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'IKEY'");
       		$stmt->bindParam(":value", $configs['IKEY']);
        	$stmt->execute();
	}

	// If the SKEY is not empty
	if ($configs['SKEY'] != "")
	{
		// Update the SKEY
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SKEY'");
        	$stmt->bindParam(":value", $configs['SKEY']);
        	$stmt->execute();
	}

	// If the HOST is not empty
	if ($configs['HOST'] != "")
	{
		// Update the HOST
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'HOST'");
        	$stmt->bindParam(":value", $configs['HOST']);
        	$stmt->execute();
	}

	// If the CONSUMERKEY is not empty
	if ($configs['CONSUMERKEY'] != "")
	{
		// Update the CONSUMERKEY
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CONSUMERKEY'");
        	$stmt->bindParam(":value", $configs['CONSUMERKEY']);
        	$stmt->execute();
	}

	// If the CONSUMERSECRET is not empty
	if ($configs['CONSUMERSECRET'] != "")
	{
		// Update the CONSUMERSECRET
        	$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CONSUMERSECRET'");
        	$stmt->bindParam(":value", $configs['CONSUMERSECRET']);
        	$stmt->execute();
	}

	// If the USERNAME_ATTRIBUTE is not empty
	if ($configs['USERNAME_ATTRIBUTE'] != "")
	{
		// Update the USERNAME_ATTRIBUTE
		$stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'USERNAME_ATTRIBUTE'");
		$stmt->bindParam(":value", $configs['USERNAME_ATTRIBUTE']);
		$stmt->execute();
	}

    // If the SAML_METADATA_URL is not empty
    if (isset($configs['SAML_METADATA_URL']))
    {
        // Update the SAML_METADATA_URL
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SAML_METADATA_URL'");
        $stmt->bindParam(":value", $configs['SAML_METADATA_URL']);
        $stmt->execute();
    }

	// If the SAML_METADATA_XML is not empty
    if (isset($configs['SAML_METADATA_XML']))
    {
        // Update the SAML_METADATA_XML
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SAML_METADATA_XML'");
        $stmt->bindParam(":value", $configs['SAML_METADATA_XML']);
        $stmt->execute();
    }

    // If the SAML_USERNAME_MATCH is not empty
    if ($configs['SAML_USERNAME_MATCH'] != "")
    {
            // Update the SAML_USERNAME_MATCH
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SAML_USERNAME_MATCH'");
            $stmt->bindParam(":value", $configs['SAML_USERNAME_MATCH']);
            $stmt->execute();
    }

    // Close the database connection
    db_close($db);

	// Return true;
	return true;
}
}

/*****************************************
 * FUNCTION: GET AUTHENTICATION SETTINGS *
 *****************************************/
function get_authentication_settings()
{
        // Open the database connection
        $db = db_open();

	$stmt = $db->prepare("SELECT * FROM `settings` WHERE `name` = 'TRUSTED_DOMAINS' OR `name` = 'BIND_FIRST' OR `name` = 'BIND_ACCOUNT' OR `name` = 'BIND_ACCOUNT_PASS' OR `name` = 'TLS' OR `name` = 'SASL' OR `name` = 'LDAP_VERSION' OR `name` = 'CHASE_REFERRALS' OR `name` = 'LDAPHOST' OR `name` = 'LDAPPORT' OR `name` = 'USERDN' OR `name` = 'IKEY' OR `name` = 'SKEY' OR `name` = 'HOST' OR `name` = 'CONSUMERKEY' OR `name` = 'CONSUMERSECRET' OR `name` = 'USERNAME_ATTRIBUTE' OR `name` = 'SAML_METADATA_URL' OR `name` = 'SAML_METADATA_XML' OR `name` = 'SAML_USERNAME_MATCH'");
	$stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/******************************
 * FUNCTION: IS VALID AD USER *
 ******************************/
function is_valid_ad_user($user, $pass)
{
	// Do not allow blank passwords or the server may think we are performing an anonymous simple bind
	if ($pass == "") return false;

        // Get the authentication settings
        $configs = get_authentication_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

	// If we need to use the BIND account first to authenticate
	if ($BIND_FIRST == "true")
	{
		// Use the BIND account to authenticate with LDAP and then authenticate the user/pass
		$ldapbind = bind_authentication($user, $pass, $configs);
	}
	// Otherwise
	else
	{
		// Authenticate the user by attemping to bind with just the provided user/pass
		$ldapbind = direct_authentication($user, $pass, $configs);
	}

	// If we are bound to the LDAP server
	if ($ldapbind)
	{
		// Write the debug log
		write_debug_log("The user has been bound to the LDAP server.");

		// Return that it is a valid user
		return true;
	}
	// Otherwise, it's not a valid user
	else
	{
		// Write the debug log
		write_debug_log("Unable to bind the user to the LDAP server.");

		// Return that it is not a valid user
		return false;
	}
}

/*********************************
 * FUNCTION: BIND AUTHENTICATION *
 *********************************/
function bind_authentication($user, $pass, $configs)
{
	// Write the debug log
	write_debug_log("Using a BIND account to authenticate with LDAP.");

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

        // Default is not bound
        $ldapbind = false;

	// Connect to the LDAP server
	$ds = ldap_connect($LDAPHOST, $LDAPPORT)
		or die ("Could not connect to LDAP server.");

	// Set the LDAP protocol version
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $LDAP_VERSION);

	// Set whether to chase referrals
	ldap_set_option($ds, LDAP_OPT_REFERRALS, $CHASE_REFERRALS);

        // If we should use TLS
        if ($TLS == "true")
        {
                // Start TLS
                ldap_start_tls($ds);
        }

        // If SASL is enabled
        if ($SASL == "true")
        {
		// Bind to the LDAP server
                $bind = ldap_sasl_bind($ds, $BIND_ACCOUNT, $BIND_ACCOUNT_PASS, 'DIGEST-MD5');
        }
        // Otherwise
        else
        {
		// Bind to the LDAP server
                $bind = ldap_bind($ds, $BIND_ACCOUNT, $BIND_ACCOUNT_PASS);
        }

	// If we were able to bind using the BIND account
	if ($bind)
	{
		// Set the sAMAccountName we are looking for
		$samaccountname = "sAMAccountName=".$user;
		$filter = "(" . $samaccountname . ")";

	        // Write the debug log
        	write_debug_log("The BIND was successful.");
		write_debug_log("Running a search for " . $samaccountname);

		// Search LDAP for the user
		$result = ldap_search($ds, $USERDN, $filter)
				or die ("Error in search query: " . ldap_error($ds));

		// If the user was found
		if ($result != false)
		{
			// Write the debug log
			write_debug_log("The user was found.");

			// Get the entries for that result
			$data = ldap_get_entries($ds, $result);

			// Write the debug log
			write_debug_log("Obtained the following values for the user:\n" . print_r($data, true));

			// If we found an entry then we have the full path to the user
			if ($data['count'] > 0)
			{
				// Get the user dn
				$user_dn = $data[0]['dn'];

				// Write the debug log
				write_debug_log("Attempting to BIND using \"" . $user_dn . "\".");

        			// If SASL is enabled
        			if ($SASL == "true")
        			{
					// Try to bind with the newly found user and password
                			$ldapbind = ldap_sasl_bind($ds, $user_dn, $pass, 'DIGEST-MD5', NULL, $user);
        			}
        			// Otherwise
        			else
        			{
					// Try to bind with the newly found user and password
                			$ldapbind = ldap_bind($ds, $user_dn, $pass);
        			}
			}
		}
		// Otherwise, the user was not found
		else
		{
			// Write the debug log
			write_debug_log("The user was not found.");
		}
	}

        // Close the connections to the LDAP server
        ldap_close($ds);

        // Return whether or not we were bound to ldap
        return $ldapbind;
}

/***********************************
 * FUNCTION: DIRECT AUTHENTICATION *
 ***********************************/
function direct_authentication($user, $pass, $configs)
{
        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

        // Default is not bound
        $ldapbind = false;

        // Connect to the LDAP server
        $ds = ldap_connect($LDAPHOST, $LDAPPORT)
                or die ("Could not connect to LDAP server.");

        // Set the LDAP protocol version
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $LDAP_VERSION);

	// Set whether to chase referrals
        ldap_set_option($ds, LDAP_OPT_REFERRALS, $CHASE_REFERRALS);

        // If we should use TLS
        if ($TLS == "true")
        {
        	// Start TLS
                ldap_start_tls($ds);
        }

        // Get the user DN
        $dn = "CN=" . $user . "," . $USERDN;

        // If SASL is enabled
        if ($SASL == "true")
        {
        	$ldapbind = ldap_sasl_bind($ds, $dn, $pass, 'DIGEST-MD5', NULL, $user);
        }
        // Otherwise
        else
        {
        	// Bind to the LDAP server
                $ldapbind = ldap_bind($ds, $dn, $pass);
        }

        // Close the connection to the LDAP server
        ldap_close($ds);

	// Return whether or not we were bound to ldap
	return $ldapbind;
}

/*****************************
 * FUNCTION: CREATE DUO AKEY *
 *****************************/
function create_duo_akey()
{
	$akey = generate_token(40);

	// Open the database connection
	$db = db_open();

	// Query the database
	$stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name`='duo_akey', `value`= :akey");
        $stmt->bindParam(":akey", $akey, PDO::PARAM_STR, 40);
	$stmt->execute();

	// Close the database connection
	db_close($db);
}

/**************************
 * FUNCTION: GET DUO AKEY *
 **************************/
function get_duo_akey()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT value FROM `settings` WHERE `name`='duo_akey'");
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array[0]['value'];
}

/*************************************************
 * FUNCTION: MULTI FACTOR AUTHENTICATION OPTIONS *
 *************************************************/
function multi_factor_authentication_options($current_value)
{
        global $escaper;
        global $lang;

        echo "<input id=\"duo\"  type=\"radio\" name=\"multi_factor\" value=\"2\"" . ($current_value == 2 ? ' checked' : '') . " />&nbsp;Duo Security<br />\n";
        echo "<!--\n";
        echo "<input id=\"toopher\"  type=\"radio\" name=\"multi_factor\" value=\"3\"" . ($current_value == 3 ? ' checked' : '') . " />&nbsp;Toopher<br />\n";
        echo "-->\n";
        echo "<br />\n";
/*
        echo "<input class=\"hidden-radio\" id=\"duo\" type=\"radio\" name=\"multi_factor\" value=\"2\"";
        if ($current_value == 2) echo " checked";
        echo " /><label for=\"duo\">&nbsp;Duo Security</label><br />\n";
        echo "<!--\n";
        echo "<input class=\"hidden-radio\" id=\"toopher\" type=\"radio\" name=\"multi_factor\" value=\"3\"";
        if ($current_value == 3) echo " checked";
        echo " /><label for=\"toopher\">&nbsp;Toopher</label><br />\n";
        echo "-->\n";
*/
}

/**************************
 * FUNCTION: ENABLED AUTH *
 **************************/
function enabled_auth($username)
{
        // Open the database connection
        $db = db_open();

        // If strict user validation is disabled
        if (get_setting('strict_user_validation') == 0)
        {
		// Query the database
		$stmt = $db->prepare("SELECT multi_factor FROM user WHERE LOWER(convert(`username` using utf8)) = LOWER(:username)");
        }
        else
        {
		// Query the database
		$stmt = $db->prepare("SELECT multi_factor FROM user WHERE `username`= :username");
        }

        $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

	return $array[0]['multi_factor'];
}

/********************************
 * FUNCTION: DUO AUTHENTICATION *
 ********************************/
function duo_authentication($username)
{
        // Get the authentication settings
        $configs = get_authentication_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

	//generate sig request and then load up Duo javascript and iframe
	$sig_request = Duo\Web::signRequest($IKEY, $SKEY, get_duo_akey(), $username);

	echo "<script src=\"extras/authentication/duo_php/js/Duo-Web-v2.js\"></script>\n";
	echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"extras/authentication/duo_php/css/Duo-Frame.css\">\n";
	echo "<iframe id=\"duo_iframe\"
		data-host=\"" . $HOST . "\"
		data-sig-request=\"" . $sig_request . "\"
	></iframe>\n";
}

/************************************
 * FUNCTION: TOOPHER AUTHENTICATION *
 ************************************/
function toopher_authentication($username)
{
        // Get the authentication settings
        $configs = get_authentication_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

	$toopher = new ToopherAPI($CONSUMERKEY, $CONSUMERSECRET);
}

/******************************************
 * FUNCTION: UPDATE AUTHENTICATION CONFIG *
 ******************************************/
function update_authentication_config()
{
    $configs['TRUSTED_DOMAINS'] = isset($_POST['trusted_domains']) ? $_POST['trusted_domains'] : '';
	$configs['BIND_FIRST'] = isset($_POST['bind_first']) ? 'true' : 'false';
	$configs['BIND_ACCOUNT'] = $_POST['bind_account'];
	$configs['BIND_ACCOUNT_PASS'] = $_POST['bind_account_pass'];
	$configs['TLS'] = isset($_POST['tls']) ? 'true' : 'false';
    $configs['SASL'] = isset($_POST['sasl']) ? 'true' : 'false';
    $configs['CHASE_REFERRALS'] = isset($_POST['chase_referrals']) ? '1' : '0';
    $configs['LDAP_VERSION'] = (int)$_POST['ldap_version'];
    $configs['LDAPHOST'] = $_POST['ldap_host'];
    $configs['LDAPPORT'] = (int)$_POST['ldap_port'];
    $configs['USERDN'] = $_POST['userdn'];
    $configs['IKEY'] = $_POST['ikey'];
    $configs['SKEY'] = $_POST['skey'];
    $configs['HOST'] = $_POST['host'];
    $configs['CONSUMERKEY'] = $_POST['consumer_key'];
    $configs['CONSUMERSECRET'] = $_POST['consumer_secret'];
	$configs['USERNAME_ATTRIBUTE'] = $_POST['username_attribute'];
    $configs['SAML_METADATA_URL'] = $_POST['saml_metadata_url'];
    
    $configs['SAML_METADATA_XML'] = $_POST['saml_metadata_xml'];
    // If there is a file uploading.
    if(isset($_FILES['saml_metadata_file']) && $_FILES['saml_metadata_file']['size']){
        $file = $_FILES['saml_metadata_file'];
        $contents = file_get_contents($file['tmp_name']);
        
        $doc = @simplexml_load_string($contents);
        if($doc){
            $configs['SAML_METADATA_XML'] = $contents;
        }
    }
    
	$configs['SAML_USERNAME_MATCH'] = $_POST['username_match'];

	// Update the settings
	update_settings($configs);
}

/*******************************************
 * FUNCTION: CUSTOM AUTHENTICATION VERSION *
 *******************************************/
function custom_authentication_version()
{
	// Return the version
	return AUTHENTICATION_EXTRA_VERSION;
}

/************************************
 * FUNCTION: DISPLAY AUTHENTICATION *
 ************************************/
function display_authentication()
{
        global $escaper;
        global $lang;

        echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . custom_authentication_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

	// Get the authentication settings
	$configs = get_authentication_settings();

	// For each configuration
	foreach ($configs as $config)
	{
		// Set the name value pair as a variable
		${$config['name']} = $config['value'];
	}

	echo "<script>\n";
	echo "  function checkbox_bind_first()\n";
	echo "  {\n";
	echo "    elements = document.getElementsByClassName(\"bind_first\");\n";
	echo "    checkbox = document.getElementById(\"bind_first\");\n";
	echo "    if(checkbox.checked)\n";
	echo "    {\n";
	echo "      for(i=0; i<elements.length; i++)\n";
	echo "      {\n";
	echo "        elements[i].style.display = \"\";\n";
	echo "      }\n";
	echo "    }\n";
	echo "    else\n";
	echo "    {\n";
	echo "      for(i=0; i<elements.length; i++)\n";
	echo "      {\n";
	echo "        elements[i].style.display = \"none\";\n";
	echo "      }\n";
	echo "    }\n";
	echo "  }\n";
        echo "  function checkbox_username_match()\n";
        echo "  {\n";
        echo "    var radios = document.getElementsByName(\"username_match\");\n";
	echo "    var attribute_tr = document.getElementsByClassName(\"username_attribute\");\n";
	echo "    for (var i = 0, length = radios.length; i < length; i++) {\n";
	echo "      if (radios[i].checked) {\n";
	echo "        if (radios[i].value == 'attribute') {\n";
	echo "          attribute_tr[0].style.display = \"\";\n";
	echo "        } else attribute_tr[0].style.display = \"none\";\n";
	echo "        break;\n";
	echo "      }\n";
	echo "    }\n";
        echo "  }\n";
	echo "</script>\n";
    echo "<form name=\"authentication_extra\" enctype=\"multipart/form-data\" method=\"post\" action=\"\">\n";
    echo "<table width=\"100%\">\n";

        echo "<tr><td colspan=\"2\"><u>LDAP</u></td></tr>\n";
        echo "<tr>\n";
	echo "<td width='140px'>BIND FIRST:</td>\n";
	echo "<td><input type=\"checkbox\" name=\"bind_first\" id=\"bind_first\"" . ($BIND_FIRST == "true" ? " checked=\"yes\"" : "") . " onchange=\"javascript: checkbox_bind_first()\" /></td>\n";
	echo "</tr>\n";
	echo "<tr class=\"bind_first\"" . ($BIND_FIRST == "false" ? " style=\"display: none;\"" : "") . ">\n";
	echo "<td>BIND ACCOUNT:</td>\n";
	echo "<td><input type=\"text\" name=\"bind_account\" value=\"" . $escaper->escapeHtml($BIND_ACCOUNT) . "\" /></td>\n";
	echo "</tr>\n";
        echo "<tr class=\"bind_first\"" . ($BIND_FIRST == "false" ? " style=\"display: none;\"" : "") . ">\n";
        echo "<td>BIND ACCOUNT PASS:</td>\n";
        echo "<td><input type=\"password\" name=\"bind_account_pass\" value=\"\" placeholder=\"Change Current Value\" /></td>\n";
        echo "</tr>\n";
	echo "<tr>\n";
        echo "<td>TLS:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"tls\" id=\"tls\"" . ($TLS == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>SASL:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"sasl\" id=\"sasl\"" . ($SASL == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>CHASE REFERRALS:</td>\n";
        echo "<td><input type=\"checkbox\" name=\"chase_referrals\" id=\"chase_referrals\"" . ($CHASE_REFERRALS == "1" ? " checked=\"yes\"" : "") . " /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>LDAP VERSION:</td>\n";
        echo "<td>\n";
        echo "<select name=\"ldap_version\" id=\"ldap_version\">\n";
        echo "<option value=\"3\"" . ($LDAP_VERSION == "3" ? " selected" : "") . ">3</option>\n";
        echo "<option value=\"2\"" . ($LDAP_VERSION == "2" ? " selected" : "") . ">2</option>\n";
        echo "</select>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>LDAP HOST:</td>\n";
        echo "<td><input type=\"text\" name=\"ldap_host\" value=\"" . $escaper->escapeHtml($LDAPHOST) . "\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>LDAP PORT:</td>\n";
        echo "<td><input type=\"text\" name=\"ldap_port\" value=\"" . $escaper->escapeHtml($LDAPPORT) . "\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>USER DN:</td>\n";
        echo "<td><input type=\"text\" name=\"userdn\" value=\"" . $escaper->escapeHtml($USERDN) . "\" /></td>\n";
        echo "</tr>\n";
        echo "<tr><td colspan=\"2\">&nbsp;</td></tr>\n";
	echo "<tr><td colspan=\"2\"><u>SAML</u></td></tr>\n";
        echo "<tr>\n";
            echo "<td>".$lang['TrustedDomains'].":</td>\n";
            echo "<td><input name=\"trusted_domains\" value=\"" . (isset($TRUSTED_DOMAINS) ? $escaper->escapeHtml($TRUSTED_DOMAINS) : "") . "\" type=\"text\"></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>METADATA URL:</td>\n";
        echo "<td><input type=\"text\" name=\"saml_metadata_url\" value=\"" . $escaper->escapeHtml($SAML_METADATA_URL) . "\" /></td>\n";
        echo "<tr>\n";
        echo "<td>METADATA XML:</td>\n";
        echo "<td><textarea class=\"saml_metadata_xml\" name=\"saml_metadata_xml\">". $escaper->escapeHtml($SAML_METADATA_XML) ."</textarea></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>&nbsp;</td>\n";
        echo "<td><input type=\"file\" name=\"saml_metadata_file\"></td>\n";
        echo "</tr>\n";
        
	echo "<tr>\n";
	echo "<td>USERNAME MATCH:</td>\n";
	echo "<td><input type=\"radio\" name=\"username_match\" id=\"username_match\"" . ($SAML_USERNAME_MATCH == "username" ? " checked=\"checked\"" : "") . " value=\"username\" onchange=\"javascript: checkbox_username_match()\" />&nbsp;Authenticated Username&nbsp;&nbsp;<input type=\"radio\" name=\"username_match\" id=\"username_match\"" . ($SAML_USERNAME_MATCH == "attribute" ? " checked=\"checked\"" : "") . " value=\"attribute\" onchange=\"javascript: checkbox_username_match()\" />&nbsp;Authenticated Attribute</td>\n";
	echo "</tr>\n";
	echo "<tr class=\"username_attribute\"". ($SAML_USERNAME_MATCH == "username" ? " style=\"display: none;\"" : "") . ">\n";
	echo "<td>USERNAME ATTRIBUTE:</td>\n";
	echo "<td><input type=\"text\" name=\"username_attribute\" value=\"" . $escaper->escapeHtml($USERNAME_ATTRIBUTE) . "\" /></td>\n";
	echo "</tr>\n";
	echo "<tr><td colspan=\"2\">&nbsp;</td></tr>\n";
        echo "<tr><td colspan=\"2\"><u>Duo Security</u></td></tr>\n";
        echo "<tr>\n";
        echo "<td>IKEY:</td>\n";
        echo "<td><input type=\"text\" name=\"ikey\" value=\"" . $escaper->escapeHtml($IKEY) . "\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>SKEY:</td>\n";
        echo "<td><input type=\"password\" name=\"skey\" value=\"\" placeholder=\"Change Current Value\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>HOST:</td>\n";
        echo "<td><input type=\"text\" name=\"host\" value=\"" . $escaper->escapeHtml($HOST) . "\" /></td>\n";
        echo "</tr>\n";
        echo "<tr><td colspan=\"2\">&nbsp;</td></tr>\n";
        echo "<tr><td colspan=\"2\"><u>Toopher</u></td></tr>\n";
        echo "<tr>\n";
        echo "<td>CONSUMER KEY:</td>\n";
        echo "<td><input type=\"text\" name=\"consumer_key\" value=\"" . $escaper->escapeHtml($CONSUMERKEY) . "\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>CONSUMER SECRET:</td>\n";
        echo "<td><input type=\"password\" name=\"consumer_secret\" value=\"\" placeholder=\"Change Current Value\" /></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Submit']) . "</button>\n";
        echo "</div>\n";
        echo "</form>\n";
}

/******************************
 * FUNCTION: READ CONFIG FILE *
 ******************************/
if (!function_exists('read_config_file')) {
function read_config_file()
{
        // Location of the configuration file
        $config_file = realpath(__DIR__ . '/includes/config.php');

        // Open the file for reading
        $handle = fopen($config_file, 'r');

        // If we can read the file
        if ($handle)
        {
                // Create a configuration array
                $config_array = array();

                // Read each line in the file
                while ($line = fgets($handle))
                {
                        // If the line begins with define
                        if (preg_match('/^define\(\'*\'*/', $line))
                        {
                                // Grab the parameter and value
                                preg_match('/\((.*?)\,(.*?)\)/s', $line, $matches);
                                $param_name = $matches[1];
                                $param_value = $matches[2];

                                // Remove any double quotes
                                $param_name = str_replace('"', "", $param_name);
                                $param_value = str_replace('"', "", $param_value);

                                // Remove any single quotes
                                $param_name = str_replace('\'', "", $param_name);
                                $param_value = str_replace('\'', "", $param_value);

                                // Remove any spaces
                                $param_name = str_replace(' ', "", $param_name);
                                $param_value = str_replace(' ', "", $param_value);

                                $config_array[$param_name] = $param_value;
                        }
                }

                // Close the file
                fclose($handle);

                // Return the configuration array
                return $config_array;
        }
        else
        {
                // Return an error
                return 0;
        }
}
}

/**********************************************************
 * FUNCTION: IMPORT AND REMOVE AUTHENTICATION CONFIG FILE *
 **********************************************************/
function import_and_remove_authentication_config_file()
{
        global $escaper;

        // Location of the configuration file
        $config_file = realpath(__DIR__ . '/includes/config.php');

        // If a configuration file exists
        if (file_exists($config_file))
        {
                // Read the configuration file
                $configs = read_config_file();

                // Update the configuration in the settings table
                if (update_settings($configs))
                {
                        // Remove the configuration file
                        if (!delete_file($config_file))
                        {
				// Display an alert
				set_alert(true, "bad", "ERROR: Could not remove " . $config_file);
				// Get any alert messages
				//get_alert();
                        }
                }
        }
}

/********************************
 * FUNCTION: IS VALID SAML USER *
 ********************************/
function is_valid_saml_user($user)
{
	// Load the SimpleSAMLphp autoloader
	require_once(realpath(__DIR__ . '/simplesamlphp/lib/_autoload.php'));

	// Select the default authentication source
	$as = new SimpleSAML_Auth_Simple('default-sp');

	// Require authentication
	$as->requireAuth();

	// If no authentication took place
	if (!$as->isAuthenticated())
	{
                // Write the debug log
                write_debug_log("SAML user not authenticated.");

		return false;
	}
	// Otherwise
	else
	{
		// Write the debug log
		write_debug_log("SAML user authenticated.");

        	// Get the authentication settings
        	$configs = get_authentication_settings();

        	// For each configuration
        	foreach ($configs as $config)
        	{
                	// Set the name value pair as a variable
                	${$config['name']} = $config['value'];
        	}

		// Get the attributes
		$attributes = $as->getAttributes();
		$attribute_value = $attributes[$USERNAME_ATTRIBUTE];

		// Get the name the user authenticated with
		$saml_username = $as->getAuthData('saml:sp:NameID');
		$saml_username = $saml_username[0];

                // Write the debug log
		write_debug_log("Username Attribute: " . $USERNAME_ATTRIBUTE);
                write_debug_log("Attribute Value: " . $attribute_value);
                write_debug_log("Authentication Username: " . $saml_username);
                write_debug_log("Username to Match: " . $user);

		// If we are supposed to authenticate the username
		if ($SAML_USERNAME_MATCH == "username")
		{
			// If the saml username is the same as the username
			if (strict_user_validation($saml_username) == strict_user_validation($user))
			{
				return true;
			}
			else return false;
		}
		// If we are supposed to authenticate the attribute
		else if ($SAML_USERNAME_MATCH == "attribute")
		{
			// If the username attribute is the same as the username
			if (strict_user_validation($attribute_value) == strict_user_validation($user))
			{
				return true;
			}
			else return false;
		}
		else return false;
	}
}

/*************************
 * FUNCTION: SAML LOGOUT *
 *************************/
function saml_logout()
{
        // Load the SimpleSAMLphp autoloader
        require_once(realpath(__DIR__ . '/simplesamlphp/lib/_autoload.php'));

        // Select the default authentication source
        $as = new SimpleSAML_Auth_Simple('default-sp');

	// Log the user out
	$as->logout();
}

/***********************************
 * FUNCTION: GET SAML METADATA URL *
 ***********************************/
function get_saml_metadata_url()
{
    // Open the database connection
    $db = db_open();

	// Get the SAML metadata URL
	$stmt = $db->prepare("SELECT value FROM `settings` WHERE `name` = 'SAML_METADATA_URL'");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

	// Create the metadata_url_for array
	$metadata_url_for = array('default-sp' => $array['value']);

	// Return the metadata URL
	return $metadata_url_for;
}

/***********************************
 * FUNCTION: GET SAML METADATA XML *
 ***********************************/
function get_saml_metadata_xml(){
    // Open the database connection
    $db = db_open();

    // Get the SAML metadata URL
    $stmt = $db->prepare("SELECT value FROM `settings` WHERE `name` = 'SAML_METADATA_XML'");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // Create the metadata_url_for array
    $metadata_xml_for = array('default-sp' => $array['value']);

    // Return the metadata URL
    return $metadata_xml_for;
}

?>
