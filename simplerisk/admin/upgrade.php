<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/config.php'));
    require_once(realpath(__DIR__ . '/../includes/upgrade.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRiskDBUpgrade');
        session_start();
    }

    // Include the language file
    require_once(language_file());
    require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

    // Check for session timeout or renegotiation
    session_check();

    // If the user requested a logout
    if (isset($_GET['logout']) && $_GET['logout'] == "true")
    {
        // Log the user out
        upgrade_logout();
    }

    // If the login form was posted
    if (isset($_POST['submit']))
    {
        $user = $_POST['user'];
        $pass = $_POST['pass'];
        // If the user is valid
        if (is_valid_user($user, $pass, true))
        {
            // Set the user permissions
            set_user_permissions($user, true);
            
            // Check if the user is an admin
            if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
            {
                // Grant access
                $_SESSION["access"] = "granted";
            }
            // The user is not an admin
            else
            {
                // Display an alert
                set_alert(true, "bad", "You need to log in as an administrative user in order to upgrade the database.");
                // Deny access
                $_SESSION["access"] = "denied";
            }
        }
        // The user was not valid
        else
        {
            // Display an alert
            set_alert(true, "bad", "Invalid username or password.");
            // Deny access
            $_SESSION["access"] = "denied";
        }
    }
    // If an API key is set and is valid
    if (isset($_GET['key']) && check_valid_key($_GET['key']))
    {
        // Grant access
        $_SESSION["access"] = "granted";
        // API key is admin
        $_SESSION["admin"] = "1";
    }
?>

<html ng-app="SimpleRisk">
  <head>
      <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
      <title>SimpleRisk: Enterprise Risk Management Simplified</title>
      <script src="../js/jquery.min.js"></script>

      <!-- build:css vendor/vendor.min.css -->
      <link rel="stylesheet" type="text/css" href="../css/bootstrap.min.css" media="screen" />
      <!-- endbuild -->
      <!-- build:css style.min.css -->
      <link rel="stylesheet" type="text/css" href="../css/style.css" media="screen" />
      <!-- endbuild -->

      <link rel="stylesheet" href="../css/bootstrap.css">
      <link rel="stylesheet" href="../css/bootstrap-responsive.css">

      <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
      <link rel="stylesheet" href="../css/theme.css">
      <?php
          setup_alert_requirements("..");
      ?>      
  </head>

  <body ng-controller="MainCtrl" class="login--page">
    
    <header class="l-header">
      <div class="navbar">
        <div class="navbar-inner">
          <div class="container-fluid">
            <a class="brand" href="https://www.simplerisk.com/"><img src="../images/logo@2x.png" alt="SimpleRisk Logo" /></a>
            <div class="navbar-content pull-right">
              <ul class="nav"> 
                <li>
                  <a href="upgrade.php">Database Upgrade Script</a>
                </li>
                <li>
                  <a href="upgrade.php?logout=true">Logout</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </header>
<?php
    // Get any alert messages
    get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span12">
          <div class="row-fluid">
            <div class="span12">
              <div class="login-wrapper clearfix">
<?php
    // If access was not granted display the login form
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        // Display the login form
        display_login_form();
    }
    // Otherwise access was granted so check if the user is an admin
    else if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
        {
        // If CONTINUE was not pressed
        if (!isset($_POST['upgrade_database']))
        {
            // Display the upgrade information
            display_upgrade_info();
        }
        // Otherwise, CONTINUE was pressed
        else
        {
            echo "<div class=\"container-fluid\">\n";
            echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span9\">\n";
            echo "<div class=\"well\">\n";

            // Upgrade the database
            upgrade_database();

            // Convert tables to InnoDB
            convert_tables_to_innodb();

            // Convert tables to utf8_general_ci
            convert_tables_to_utf8();

            // Display the clear cache warning
            display_cache_clear_warning();

            echo "<br /><br />!-- " . $escaper->escapeHtml($lang['UPGRADECOMPLETED']) . " --!\n";

            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
        }
    }
?>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
