<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once('../includes/functions.php');
	require_once('../includes/authenticate.php');

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRisk');

        // Include the language file
        require_once(language_file());

        require_once('../includes/csrf-magic/csrf-magic.php');

        // Check for session timeout or renegotiation
        session_check();

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Check if access is authorized
        if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
        {
                header("Location: ../index.php");
                exit(0);
        }
?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
    <link rel="stylesheet" href="../css/paypal.css">
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">
    <link rel="stylesheet" href="../css/paypal.css">
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="../index.php"><?php echo $lang['Home']; ?></a> 
              </li>
              <li>
                <a href="../management/index.php"><?php echo $lang['RiskManagement']; ?></a> 
              </li>
              <li>
                <a href="../reports/index.php"><?php echo $lang['Reporting']; ?></a> 
              </li>
              <li class="active">
                <a href="index.php"><?php echo $lang['Configure']; ?></a>
              </li>
            </ul>
          </div>
<?php
if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
{
          echo "<div class=\"btn-group pull-right\">\n";
          echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">".$_SESSION['name']."<span class=\"caret\"></span></a>\n";
          echo "<ul class=\"dropdown-menu\">\n";
          echo "<li>\n";
          echo "<a href=\"../account/profile.php\">" . $lang['MyProfile'] . "</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"../logout.php\">" . $lang['Logout'] . "</a>\n";
          echo "</li>\n";
          echo "</ul>\n";
          echo "</div>\n";
}
?>
        </div>
      </div>
    </div>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <ul class="nav  nav-pills nav-stacked">
            <li>
              <a href="index.php"><?php echo $lang['ConfigureRiskFormula']; ?></a> 
            </li>
            <li>
              <a href="review_settings.php"><?php echo $lang['ConfigureReviewSettings']; ?></a>
            </li>
            <li>
              <a href="add_remove_values.php"><?php echo $lang['AddAndRemoveValues']; ?></a> 
            </li>
            <li>
              <a href="user_management.php"><?php echo $lang['UserManagement']; ?></a> 
            </li>
            <li>
              <a href="custom_names.php"><?php echo $lang['RedefineNamingConventions']; ?></a> 
            </li>
            <li>
              <a href="audit_trail.php"><?php echo $lang['AuditTrail']; ?></a>
            </li>
            <li>
              <a href="extras.php"><?php echo $lang['Extras']; ?></a>
            </li>
            <li>
              <a href="announcements.php"><?php echo $lang['Announcements']; ?></a>
            </li>
            <li class="active">
              <a href="about.php"><?php echo $lang['About']; ?></a>        
            </li>
          </ul>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <p>The use of this software is subject to the terms of the <a href="http://mozilla.org/MPL/2.0/" target="newwindow">Mozilla Public License, v. 2.0</a>.</p>
                <p><h4>Application Version</h4>
                <ul>
                  <li>The latest Application version is <?php echo latest_version("app"); ?></li>
                  <li>You are running Application version <?php echo current_version("app"); ?></li>
                </ul>
                </p>
                <p><h4>Database Version</h4>
                <ul>
                  <li>The latest Database version is <?php echo latest_version("db"); ?></li>
                  <li>You are running Database version <?php echo current_version("db"); ?></li>
                </ul>
                </p>
                <p>You can download the most recent code <a href="http://www.simplerisk.org" target="newwindow">here</a>.</p>
              </div>
            </div>
          </div>
          <div class="row-fluid">
            <div class="span6">
              <div class="hero-unit">
                <p><a href="http://www.joshsokol.com" target="newwindow">Josh Sokol</a> wrote this Risk Management system after being fed up with the high-priced alternatives out there.  When your only options are spending tens of thousands of dollars or using a spreadsheet, good risk management is simply unattainable.</p>
                <p>Josh lives in Austin, TX and has four little ones starving for his time and attention.  If this tool is useful to you and you want to encourage him to keep his attention fixed on developing new features for you, perhaps you should consider donating via the PayPal form on the right.  It&#39;s also good karma.</p>
              </div>
            </div>
            <div class="span6">
              <div class="hero-unit">
                <!-- START PAYPAL FORM -->
                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" class="payformmargin">
                <input type="hidden" name="cmd" value="_xclick">
                <input type="hidden" name="business" value="josh@simplerisk.org">
                <input type="hidden" name="item_name" value="Donation for Risk Management Software">
                <input type="hidden" name="no_note" value="1">
                <input type="hidden" name="currency_code" value="USD">

                <table cellpadding="8" cellspacing="0" border="0"><tr><td valign="top" align="center" class="payformbox">

                <table cellpadding="3" cellspacing="0" border="0"><tr><td align="left">

                Enter amount:<br>
                <input type="text" name="amount" value="500.00" class="payform"><br>

                </td><td rowspan="3">

                <img src="../images/paypal-custom.gif" alt="Payments through Paypal"><br>

                </td></tr><tr><td align="left">

                <input type="hidden" name="on0" value="Project Details">

                Payment notes:<br>
                <textarea name="os0" rows="3" cols="17" class="payform"></textarea><br>

                </td></tr><tr><td align="left">

                <input type="submit" name="PaypalPayment" value="Send Payment" class="payformbutton"><br>

                </td></tr></table>
                </td></tr></table>

                </form>
                <!-- END PAYPAL FORM -->
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
