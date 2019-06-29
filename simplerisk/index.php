<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/includes/functions.php'));
require_once(realpath(__DIR__ . '/includes/authenticate.php'));
require_once(realpath(__DIR__ . '/includes/display.php'));
require_once(realpath(__DIR__ . '/includes/alerts.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

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
require_once(language_file());

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

            header("location: reset_password.php");
            exit;
        }

        // Create the SimpleRisk instance ID if it doesn't already exist
        create_simplerisk_instance_id();

        // Ping the server
        ping_server();

        // Set the user permissions
        set_user_permissions($user);

        // Get base url
        $base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}";
        $base_url = htmlspecialchars( $base_url, ENT_QUOTES, 'UTF-8' );
        $base_url = pathinfo($base_url)['dirname'];

        // Filter out authentication extra from the base url
        $base_url = str_replace("/extras/authentication", "", $base_url);
        $_SESSION['base_url'] = $base_url;

        // Set login status
        login($user, $pass);

      }
      // If the user is not a valid user
      else
      {
          $_SESSION["access"] = "denied";

          // Display an alert
          set_alert(true, "bad", "Invalid username or password.");

          // If the password attempt lockout is enabled
          if(get_setting("pass_policy_attempt_lockout") != 0)
          {
              // Add the login attempt and block if necessary
              add_login_attempt_and_block($user);
          }
      }
}

if (isset($_SESSION["access"]) && ($_SESSION["access"] == "granted"))
{
    // Select where to redirect the user next
    select_redirect();
}

// If the user has already authorized and we are authorizing with duo
if (isset($_SESSION["access"]) && ($_SESSION["access"] == "duo"))
{
    // If a response has been posted
    if (isset($_POST['sig_response']))
    {
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
?>
<html ng-app="SimpleRisk">
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <!-- build:css vendor/vendor.min.css -->
  <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" media="screen" />
  <!-- endbuild -->
  <!-- build:css style.min.css -->
  <link rel="stylesheet" type="text/css" href="css/style.css" media="screen" />
  <!-- endbuild -->

  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/bootstrap-responsive.css">

  <link rel="stylesheet" href="bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="css/theme.css">

  <script src="js/jquery.min.js"></script>
  
  <?php
      setup_alert_requirements();
  ?>  
</head>
<body ng-controller="MainCtrl" class="login--page">
  <?php view_top_menu("Home"); ?>

  <?php
  // If the user has authenticated and now we need to authenticate with duo
  if (isset($_SESSION["access"]) && $_SESSION["access"] == "duo")
  {
    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span9\">\n";
    // echo "<div class=\"well\">\n";

    // Include the custom authentication extra
    require_once(realpath(__DIR__ . '/extras/authentication/index.php'));

    // Perform a duo authentication request for the user
    duo_authentication($_SESSION["user"]);

    // echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
  }
  // If the user has not authenticated
  else if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
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
  ?>
  <script src="js/bootstrap.min.js"></script>
</body>
</html>
