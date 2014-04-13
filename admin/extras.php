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

	// Default is no alert
	$alert = false;

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
          echo "<a href=\"../account/profile.php\">". $lang['MyProfile'] ."</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"../logout.php\">". $lang['Logout'] ."</a>\n";
          echo "</li>\n";
          echo "</ul>\n";
          echo "</div>\n";
}
?>
        </div>
      </div>
    </div>
<?php
        if ($alert == "good")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 greenalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
?>
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
            <li class="active">
              <a href="extras.php"><?php echo $lang['Extras']; ?></a>
            </li>
            <li>
              <a href="announcements.php"><?php echo $lang['Announcements']; ?></a>
            </li>
            <li>
              <a href="about.php"><?php echo $lang['About']; ?></a>        
            </li>
          </ul>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4>Custom Extras</h4>
                <p>It would be awesome if everything were free, right?  Hopefully the core SimpleRisk platform is able to serve all of your risk management needs.  But, if you find yourself still wanting more functionality, we&#39;ve developed a series of &quot;Extras&quot; that will do just that for just a few hundred bucks each for a perpetual license.
                </p>
                <table width="100%" class="table table-bordered table-condensed">
                <thead>
                <tr>
                  <td width="155px"><b><u>Extra Name</u></b></td>
                  <td><b><u>Description</u></b></td>
                  <td width="60px"><b><u>Enabled</u></b></td>
                </tr>
                </thead>
                <tbody>
                <tr>
                  <td width="155px"><b>Custom Authentication</b></td>
                  <td>Currently provides support for Active Directory Authentication and Duo Security multi-factor authentication, but will have other custom authentication types in the future.</td>
                  <td width="60px"><?php echo (custom_authentication_extra() ? 'Yes' : 'No'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>Team-Based Separation</b></td>
                  <td>Restriction of risk viewing to team members the risk is categorized as.</td>
                  <td width="60px"><?php echo (team_separation_extra() ? 'Yes' : 'No'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>Notifications</b></td>
                  <td>Sends email notifications when risks are submitted, updated, mitigated, or reviewed and may be run on a schedule to notify users of risks in the Unreviewed or Past Due state.</td>
                  <td width="60px"><?php echo (notification_extra() ? 'Yes' : 'No'); ?></td>
                </tr>
                <tr>
                  <td width="155px"><b>Encrypted Database</b></td>
                  <td>Encryption of sensitive text fields in the database.</td>
                  <td width="60px"><?php echo (encryption_extra() ? 'Yes' : 'No'); ?></td>
                </tr>
                <tbody>
                </table>
                <p>If you are interested in adding these or other custom functionality to your SimpleRisk installation, please send an e-mail to <a href="mailto:extras@simplerisk.org?Subject=Interest%20in%20SimpleRisk%20Extras" target="_top">extras@simplerisk.org</a>.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
