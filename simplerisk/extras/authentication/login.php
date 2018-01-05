<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

    // Include required functions file
    require_once(realpath(__DIR__ . '/../../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/index.php'));

    // Add various security headers
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");

    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
    session_name('SimpleRisk');
    session_start();

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
        if(is_array($attribute_value)){
            $attribute_value = $attribute_value[0];
        }

        // Get the name the user authenticated with
        $saml_username = $as->getAuthData('saml:sp:NameID');
        $saml_username = $saml_username['Value'];               

        // Write the debug log
        write_debug_log("Username Attribute: " . $USERNAME_ATTRIBUTE);
        write_debug_log("Attribute Value: " . $attribute_value);
        write_debug_log("Authentication Username: " . $saml_username);

        // If we are supposed to authenticate the username
        if ($SAML_USERNAME_MATCH == "username")
        {
            // Open the database connection
            $db = db_open();

            // If strict user validation is disabled
            if (get_setting('strict_user_validation') == 0)
            {
                    // Get the uid for the username
                    $stmt = $db->prepare("SELECT value FROM user WHERE enabled = 1 AND LOWER(convert(`username` using utf8)) = LOWER(:user)");
            }
            else
            {
                    write_debug_log("Authentication Username: " . $saml_username);
                    // Get the uid for the username
                    $stmt = $db->prepare("SELECT value FROM user WHERE enabled = 1 AND username = :user");
            }

            $stmt->bindParam(":user", $saml_username, PDO::PARAM_STR, 200);
            $stmt->execute();
            $array = $stmt->fetch();
            $uid = $array['value'];

            write_debug_log("UID: " . $uid);

            // Close the database connection
            db_close($db);

            // If the saml username is a valid user
            if ($uid != 0)
            {
                // Write the debug log
                write_debug_log("Valid SAML user in SimpleRisk.");
                write_debug_log("Granting access for UID \"" . $uid . "\" USERNAME \"" . $saml_username . "\".");

                // Set the user permissions
                set_user_permissions($saml_username, $saml_username, false);

                // Grant the user access
                grant_access();

                // Redirect to the reports index
                header("Location: ../../reports");
            }
            // Otherwise
            else
            {
                // Write the debug log
                write_debug_log("Not a valid SAML user in SimpleRisk.");

                // Redirect to the main page
                header("Location: ../../");
            }
        }
        // If we are supposed to authenticate the attribute
        else if ($SAML_USERNAME_MATCH == "attribute")
        {                  
            
            // Open the database connection
            $db = db_open();

            // If strict user validation is disabled
            if (get_setting('strict_user_validation') == 0)
            {
                // Get the uid for the username
                $stmt = $db->prepare("SELECT value FROM user WHERE enabled = 1 AND LOWER(convert(`username` using utf8)) = LOWER(:user)");
            }
            else
            {
                // Get the uid for the username
                $stmt = $db->prepare("SELECT value FROM user WHERE enabled = 1 AND username = :user");
            }

            $stmt->bindParam(":user", $attribute_value, PDO::PARAM_STR, 200);
            $stmt->execute();
            $array = $stmt->fetch();
            $uid = $array['value'];

            // Close the database connection
            db_close($db);


            // If the username attribute is the same as the username
            if ($uid)
            {
                // Write the debug log
                write_debug_log("Valid SAML user in SimpleRisk.");

                // Set the user permissions
                set_user_permissions($attribute_value, $attribute_value, false);

                // Grant the user access
                grant_access();

                // Redirect to the reports index
                header("Location: ../../reports");
            }
            // Otherwise
            else 
            {
                // Write the debug log
                write_debug_log("Not a valid SAML user in SimpleRisk.");

                // Redirect to the main page
                header("Location: ../../");
            }
        }
    else
    {
        // Write the debug log
        write_debug_log("Not sure which value to match on.");

        // Redirect to the main page
        header("Location: ../../");
    }
}

?>
