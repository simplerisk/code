<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));

        // Include Zend Escaper for HTML Output Encoding
        require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
        $escaper = new Zend\Escaper\Escaper('utf-8');

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

        require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

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

        // Check if the risk level update was submitted
        if (isset($_POST['update_review_settings']))
        {
                $high = (int)$_POST['high'];
                $medium = (int)$_POST['medium'];
                $low = (int)$_POST['low'];

                // Check if all values are integers
                if (is_int($high) && is_int($medium) && is_int($low))
                {
                        // Update the review settings
                        update_review_settings($high, $medium, $low);

                        // Audit log
                        $risk_id = 1000;
                        $message = "The review settings were modified by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

			$alert = "good";
			$alert_message = "The review settings have been updated successfully!";
                }
		// NOTE: This will never trigger as we bind $high, $medium, and $low to integer values
		else
		{
			$alert = "bad";
			$alert_message = "One of your review settings is not an integer value.  Please try again.";
		}
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
                <a href="../index.php"><?php echo $escaper->escapeHtml($lang['Home']); ?></a> 
              </li>
              <li>
                <a href="../management/index.php"><?php echo $escaper->escapeHtml($lang['RiskManagement']); ?></a> 
              </li>
              <li>
                <a href="../reports/index.php"><?php echo $escaper->escapeHtml($lang['Reporting']); ?></a> 
              </li>
              <li class="active">
                <a href="index.php"><?php echo $escaper->escapeHtml($lang['Configure']); ?></a>
              </li>
            </ul>
          </div>
<?php
if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
{
          echo "<div class=\"btn-group pull-right\">\n";
          echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($_SESSION['name']) . "<span class=\"caret\"></span></a>\n";
          echo "<ul class=\"dropdown-menu\">\n";
          echo "<li>\n";
          echo "<a href=\"../account/profile.php\">" . $escaper->escapeHtml($lang['MyProfile']) . "</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"../logout.php\">" . $escaper->escapeHtml($lang['Logout']) . "</a>\n";
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
                echo "<div class=\"span12 greenalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
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
              <a href="index.php"><?php echo $escaper->escapeHtml($lang['ConfigureRiskFormula']); ?></a> 
            </li>
            <li class="active">
              <a href="review_settings.php"><?php echo $escaper->escapeHtml($lang['ConfigureReviewSettings']); ?></a>
            </li>
            <li>
              <a href="add_remove_values.php"><?php echo $escaper->escapeHtml($lang['AddAndRemoveValues']); ?></a> 
            </li>
            <li>
              <a href="user_management.php"><?php echo $escaper->escapeHtml($lang['UserManagement']); ?></a> 
            </li>
            <li>
              <a href="custom_names.php"><?php echo $escaper->escapeHtml($lang['RedefineNamingConventions']); ?></a> 
            </li>
            <li>
              <a href="audit_trail.php"><?php echo $escaper->escapeHtml($lang['AuditTrail']); ?></a>
            </li>
            <li>
              <a href="extras.php"><?php echo $escaper->escapeHtml($lang['Extras']); ?></a>
            </li>
            <li>
              <a href="announcements.php"><?php echo $escaper->escapeHtml($lang['Announcements']); ?></a>
            </li>
            <li>
              <a href="about.php"><?php echo $escaper->escapeHtml($lang['About']); ?></a>        
            </li>
          </ul>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="review_settings" method="post" action="">

	        <?php $review_levels = get_review_levels(); ?>

                <p><?php echo $escaper->escapeHtml($lang['IWantToReviewHighRiskEvery']); ?> <input type="text" name="high" size="2" value="<?php echo $escaper->escapeHtml($review_levels[0]['value']); ?>" /> <?php echo $escaper->escapeHtml($lang['days']); ?>.</p>
                <p><?php echo $escaper->escapeHtml($lang['IWantToReviewMediumRiskEvery']); ?> <input type="text" name="medium" size="2" value="<?php echo $escaper->escapeHtml($review_levels[1]['value']); ?>" /> <?php echo $escaper->escapeHtml($lang['days']); ?>.</p>
                <p><?php echo $escaper->escapeHtml($lang['IWantToReviewLowRiskEvery']); ?> <input type="text" name="low" size="2" value="<?php echo $escaper->escapeHtml($review_levels[2]['value']); ?>" /> <?php echo $escaper->escapeHtml($lang['days']); ?>.</p>

                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_review_settings" />

                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
