<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include the SimpleRisk configuration file
require_once(realpath(__DIR__ . '/includes/config.php'));

// If the database hasn't been installed yet
if (defined('SIMPLERISK_INSTALLED') && SIMPLERISK_INSTALLED == "false")
{
    // Include the required installation file
    require_once(realpath(__DIR__ . '/includes/install.php'));

    // Call the SimpleRisk installation process
    simplerisk_installation();
}
// The SimpleRisk database has been installed
else
{
    // Include required functions file
    require_once(realpath(__DIR__ . '/includes/functions.php'));
    require_once(realpath(__DIR__ . '/includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/includes/display.php'));
    require_once(realpath(__DIR__ . '/includes/alerts.php'));
    require_once(realpath(__DIR__ . '/includes/extras.php'));
    require_once(realpath(__DIR__ . '/includes/install.php'));
    require_once(realpath(__DIR__ . '/vendor/autoload.php'));

    // Add various security headers
    add_security_headers();

    // Get the number of users in the database
    $db = db_open();
    $stmt = $db->prepare("SELECT count(value) as count FROM `user`;");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];
    db_close($db);

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        sess_gc(1440);
        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    // Ignoring detections related to language files
    // @phan-suppress-next-line SecurityCheck-PathTraversal
    require_once(language_file());

    // If the database has been installed but there are no users
    if ($count == 0)
    {
        // Create the default admin account
        create_default_admin_account();

        // Don't display the rest of the page
        exit();
    }
    // Otherwise go about the standard login process
    else
    {
        // Checking for the SAML logout status
        if (custom_authentication_extra() && isset($_REQUEST['LogoutState'])) {
            global $lang;
            // Parse the logout state
            $state = \SimpleSAML\Auth\State::loadState((string)$_REQUEST['LogoutState'], 'MyLogoutState');
            $ls = $state['saml:sp:LogoutStatus']; /* Only works for SAML SP */
            if ($ls['Code'] === 'urn:oasis:names:tc:SAML:2.0:status:Success' && !isset($ls['SubCode'])) {
                /* Successful logout. */
                set_alert(true, "good", $lang['SAMLLogoutSuccessful']);
            } else {
                /* Logout failed. Tell the user to close the browser. */
                set_alert(true, "bad", $lang['SAMLLogoutFailed']);
            }
        }
        // If the login form was posted
        if (isset($_POST['submit']))
        {
            $user = $_POST['user'];
            $pass = $_POST['pass'];

            // Check for expired lockouts
            check_expired_lockouts();

            // If the user is valid
            if (is_valid_user($user, $pass))
            {
                $uid = get_id_by_user($user);
                $array = get_user_by_id($uid);
                $_SESSION['user'] = $array['username'];

                // If the user needs to change their password upon login
                if($array['change_password'])
                {
                    $_SESSION['first_login_uid'] = $uid;

                    if (encryption_extra())
                    {
                        // Load the extra
                        require_once(realpath(__DIR__ . '/extras/encryption/index.php'));

                        // Get the current password encrypted with the temp key
                        check_user_enc($user, $pass);
                    }

                    // Put the posted password in the session before redirecting them to the reset page
                    $_SESSION['first_login_pass'] = $pass;

                    header("location: reset_password.php");
                    exit;
                }

                // Create the SimpleRisk instance ID if it doesn't already exist
                create_simplerisk_instance_id();

                // Set the user permissions
                set_user_permissions($user);

                // Ping the server
                ping_server();

                // Do a license check
                simplerisk_license_check();

                // Get base url
                $_SESSION['base_url'] = get_base_url();

                // Set login status
                login($user, $pass);
            }
            // If the user is not a valid user
            else {
                // In case the login attempt fails we're checking the cause.
                // If it's because the user 'Does Not Exist' we're doing a dummy
                // validation to make sure we're using the same time on a non-existant
                // user as we'd use on an existing
                if (get_user_type($user, false) === "DNE") {
                    fake_simplerisk_user_validity_check();
                }

                $_SESSION["access"] = "denied";

                // If case sensitive usernames are enabled
                if (get_setting("strict_user_validation") != 0)
                {
                    // Display an alert
                    set_alert(true, "bad", $escaper->escapeHtml($lang["InvalidUsernameOrPasswordCaseSensitive"]));
                }
                else set_alert(true, "bad", $escaper->escapeHtml($lang["InvalidUsernameOrPassword"]));

                // If the password attempt lockout is enabled
                if(get_setting("pass_policy_attempt_lockout") != 0)
                {
                    // Add the login attempt and block if necessary
                    add_login_attempt_and_block($user);
                }
            }
        }

        if (isset($_SESSION["access"]) && ($_SESSION["access"] == "1"))
        {
            // Select where to redirect the user next
            select_redirect();
        }

        // If the user has already authorized and we are authorizing with multi factor
        if (isset($_SESSION["access"]) && ($_SESSION["access"] == "mfa"))
        {
            // If a response has been posted
            if (isset($_POST['authenticate']))
            {
                // If the mfa token matches
                if (does_mfa_token_match())
                {
                    // If the encryption extra is enabled
                    if (encryption_extra())
                    {
                        // Load the extra
                        require_once(realpath(__DIR__ . '/extras/encryption/index.php'));

                        // Check user enc
                        check_user_enc($user, $pass);
                    }

                    // Grant the user access
                    grant_access();

                    // Select where to redirect the user next
                    select_redirect();
                }
            }
        }

        // If the user has already been authorized and we need to verify their mfa
        if (isset($_SESSION["access"]) && $_SESSION["access"] == "mfa_verify")
        {
            // If a response has ben posted
            if (isset($_POST['verify']))
            {
                // If the MFA verification process worked
                if (process_mfa_verify())
                {
                    // Convert the user to use the core MFA going forward
                    enable_mfa_for_uid();

                    // If the encryption extra is enabled
                    if (encryption_extra())
                    {
                        // Load the extra
                        require_once(realpath(__DIR__ . '/extras/encryption/index.php'));

                        // Check user enc
                        check_user_enc($user, $pass);
                    }

                    // Grant the user access
                    grant_access();

                    // Select where to redirect the user next
                    select_redirect();
                }
            }
        }

        // If the user has already authorized and we are authorizing with duo
        if (isset($_SESSION["access"]) && ($_SESSION["access"] == "duo"))
        {
            // If a response has been posted
            if (isset($_POST['sig_response']))
            {
                // Get the username and password and then unset the session values
                $user = $_SESSION['user'];
                $pass = $_SESSION['pass'];
                unset($_SESSION['user']);
                unset($_SESSION['pass']);

                // Include the custom authentication extra
                require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

                // Get the authentication settings
                $configs = get_authentication_settings();

                // For each configuration
                foreach ($configs as $config)
                {
                    // Set the name value pair as a variable
                    ${$config['name']} = $config['value'];
                }

                // Get the response back from Duo
                $resp = Duo\Web::verifyResponse($IKEY, $SKEY, get_duo_akey(), $_POST['sig_response']);

                // If the response is not null
                if ($resp != NULL)
                {
                    // Create the MFA secret for the uid
                    create_mfa_secret_for_uid();

                    // Set the session to indicate that the Duo auth was successful, but we need to verify the new MFA
                    $_SESSION["access"] = "mfa_verify";
                }
            }
        }
    }
	?>
	<!doctype html>
	<html ng-app="SimpleRisk">
	<head>
	  <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
	  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
	  <!-- build:css vendor/vendor.min.css -->
	  <!-- endbuild -->
	  <!-- build:css style.min.css -->
	  <link rel="stylesheet" type="text/css" href="css/style.css?<?php echo current_version("app"); ?>" media="screen" />
	  <!-- endbuild -->

	  <link rel="stylesheet" href="css/bootstrap.css?<?php echo current_version("app"); ?>">
	  <link rel="stylesheet" href="css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">

	  <link rel="stylesheet" href="vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
	  <link rel="stylesheet" href="css/theme.css?<?php echo current_version("app"); ?>">
  
	  <?php
		  // Use these jQuery scripts
		  $scripts = [
			'jquery.min.js',
		  ];

		  // Include the jquery javascript source
		  display_jquery_javascript($scripts);

		  display_bootstrap_javascript();

		  setup_favicon();
		  setup_alert_requirements();
	  ?>  
	</head>
	<body ng-controller="MainCtrl" class="login--page">
	  <?php view_top_menu("Home"); ?>

	  <?php
      // If the user has authenticated and now we need to authenticate with mfa
      if (isset($_SESSION["access"]) && $_SESSION["access"] == "mfa")
      {
          echo "<div class=\"row-fluid\">\n";
          echo "<div class=\"span9\">\n";
          echo "<form name='mfa' method='post' action=''>\n";

          // Perform a duo authentication request for the user
          display_mfa_authentication_page();

          echo "</form>\n";
          echo "</div>\n";
          echo "</div>\n";
      }
      // If the user needs to verify the new MFA
      else if(isset($_SESSION["access"]) && $_SESSION["access"] == "mfa_verify")
      {
          echo "<div class=\"row-fluid\">\n";
          echo "<div class=\"span9\">\n";
          echo "<form name='mfa' method='post' action=''>\n";

          // Display the MFA verification page
          display_mfa_verification_page();

          echo "</form>\n";
          echo "</div>\n";
          echo "</div>\n";
      }
	  // If the user has authenticated and now we need to authenticate with duo
	  else if (isset($_SESSION["access"]) && $_SESSION["access"] == "duo")
	  {
		echo "<div class=\"row-fluid\">\n";
		echo "<div class=\"span9\">\n";
		// echo "<div class=\"well\">\n";

		// Include the custom authentication extra
		require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

		// Store the user and password temporarily in the session
		$_SESSION['user'] = $_POST['user'];
		$_SESSION['pass'] = $_POST['pass'];

		// Perform a duo authentication request for the user
		duo_authentication($_SESSION["user"]);

		// echo "</div>\n";
		echo "</div>\n";
		echo "</div>\n";
	  }
	  // If the user has not authenticated
	  else if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
	  {
		echo "<div class=\"row-fluid\">\n";
		echo "<div class=\"span12\">\n";
		// echo "<div class=\"well\">\n";

		// Get any alert messages
		get_alert();

		echo "<div class=\"login-wrapper clearfix\">";
		echo "<h1 class=\"text-center welcome--msg\"> Enterprise Risk Management Simplified... </h1>";

		echo "<form name=\"authenticate\" method=\"post\" action=\"\" class=\"loginForm\">\n";
		echo "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
		echo "<tr><td colspan=\"2\"><label class=\"login--label\">" . $escaper->escapeHtml($lang['LogInHere']) . "</label></td></tr>\n";
		echo "<tr><td width=\"20%\"><label for=\"\">" . $escaper->escapeHtml($lang['Username']) . ":&nbsp;</label></td><td class=\"80%\"><input class=\"form-control input-medium\" name=\"user\" id=\"user\" type=\"text\" /></td></tr>\n";
		echo "<tr><td width=\"20%\"><label for=\"\">" . $escaper->escapeHtml($lang['Password']) . ":&nbsp;</label></td><td class=\"80%\"><input class=\"form-control input-medium\" name=\"pass\" id=\"pass\" type=\"password\" autocomplete=\"off\" /></td></tr>\n";
		echo "</table>\n";
		echo "<div class=\"form-actions\">\n";

		// If the custom authentication extra is enabled
		if (custom_authentication_extra())
		{
			// If SSO Login is enabled or not set yet
		    if(get_setting("GO_TO_SSO_LOGIN") === false || get_setting("GO_TO_SSO_LOGIN") === '1')
			{
				// Display the SSO login link
				echo "<tr><td colspan=\"2\"><label><a href=\"extras/authentication/login.php\">" . $escaper->escapeHtml($lang['GoToSSOLoginPage']) . "</a></label></td></tr>\n";
			}
		}

		echo "<a href=\"reset.php\">" . $escaper->escapeHtml($lang['ForgotYourPassword']) . "</a>";
		echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary pull-right\">" . $escaper->escapeHtml($lang['Login']) . "</button>\n";
		echo "<input class=\"btn btn-default pull-right\" value=\"" . $escaper->escapeHtml($lang['Reset']) . "\" type=\"reset\">\n";
		echo "</div>\n";
		echo "</form>\n";
		echo "</div>";


		// echo "</div>\n";
		echo "</div>\n";
		echo "</div>\n";
	  }
  }

  ?>

</body>
</html>
