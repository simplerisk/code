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

        // Check if a new category was submitted
        if (isset($_POST['add_category']))
        {
                $name = $_POST['new_category'];

                // Insert a new category up to 50 chars
                add_name("category", $name, 50);

                // Audit log
                $risk_id = 1000;
                $message = "A new category was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

		// There is an alert message
		$alert = "good";
		$alert_message = "A new category was added successfully.";
        }

        // Check if a category was deleted
        if (isset($_POST['delete_category']))
        {
                $value = (int)$_POST['category'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("category", $value);

                	// Audit log
                	$risk_id = 1000;
                	$message = "An existing category was removed by the \"" . $_SESSION['user'] . "\" user.";
                	write_log($risk_id, $_SESSION['uid'], $message);

                	// There is an alert message
                	$alert = "good";
                	$alert_message = "An existing category was removed successfully.";
                }
        }

        // Check if a new team was submitted
        if (isset($_POST['add_team']))
        {
                $name = $_POST['new_team'];

                // Insert a new team up to 50 chars
                add_name("team", $name, 50);

                // Audit log
                $risk_id = 1000;
                $message = "A new team was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new team was added successfully.";
        }

        // Check if a team was deleted
        if (isset($_POST['delete_team']))
        {
                $value = (int)$_POST['team'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("team", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing team was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing team was removed successfully.";
                }
        }

        // Check if a new technology was submitted
        if (isset($_POST['add_technology']))
        {
                $name = $_POST['new_technology'];

                // Insert a new technology up to 50 chars
                add_name("technology", $name, 50);

                // Audit log
                $risk_id = 1000;
                $message = "A new technology was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new technology was added successfully.";
        }

        // Check if a technology was deleted
        if (isset($_POST['delete_technology']))
        {
                $value = (int)$_POST['technology'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("technology", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing technology was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing technology was removed successfully.";
                }
        }

        // Check if a new location was submitted
        if (isset($_POST['add_location']))
        {
                $name = $_POST['new_location'];

                // Insert a new location up to 100 chars
                add_name("location", $name, 100);

                // Audit log
                $risk_id = 1000;
                $message = "A new location was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new location was added successfully.";
        }

        // Check if a location was deleted
        if (isset($_POST['delete_location']))
        {
                $value = (int)$_POST['location'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("location", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing location was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing location was removed successfully.";
                }
        }

        // Check if a new control regulation was submitted
        if (isset($_POST['add_regulation']))
        {
                $name = $_POST['new_regulation'];

                // Insert a new regulation up to 50 chars
                add_name("regulation", $name, 50);

                // Audit log
                $risk_id = 1000;
                $message = "A new control regulation was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new control regulation was added successfully.";
        }

        // Check if a control regulation was deleted
        if (isset($_POST['delete_regulation']))
        {
                $value = (int)$_POST['regulation'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("regulation", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing control regulation was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing control regulation was removed successfully.";
                }
        }

        // Check if a new planning strategy was submitted
        if (isset($_POST['add_planning_strategy']))
        {
                $name = $_POST['new_planning_strategy'];

                // Insert a new planning strategy up to 20 chars
                add_name("planning_strategy", $name, 20);

                // Audit log
                $risk_id = 1000;
                $message = "A new planning strategy was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new planning strategy was added successfully.";
        }

        // Check if a planning strategy was deleted
        if (isset($_POST['delete_planning_strategy']))
        {
                $value = (int)$_POST['planning_strategy'];

                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("planning_strategy", $value);

                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing planning strategy was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing planning strategy was removed successfully.";
                }
        }

        // Check if a new close reason was submitted
        if (isset($_POST['add_close_reason']))
        {
                $name = $_POST['new_close_reason'];

                // Insert a new close reason up to 50 chars
                add_name("close_reason", $name, 50);
                
                // Audit log
                $risk_id = 1000;
                $message = "A new close reason was added by the \"" . $_SESSION['user'] . "\" user.";
                write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                $alert_message = "A new close reason was added successfully.";
        }
                        
        // Check if a close reason was deleted
        if (isset($_POST['delete_close_reason']))
        {
                $value = (int)$_POST['close_reason'];
        
                // Verify value is an integer
                if (is_int($value))
                {
                        delete_value("close_reason", $value);
                
                        // Audit log
                        $risk_id = 1000;
                        $message = "An existing close reason was removed by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "An existing close reason was removed successfully.";
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
            <li class="active">
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
            <li>
              <a href="about.php"><?php echo $lang['About']; ?></a>        
            </li>
          </ul>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="category" method="post" action="">
                <p>
                <h4><?php echo $lang['Category']; ?>:</h4>
                <?php echo $lang['AddNewCategoryNamed']; ?> <input name="new_category" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_category" /><br />
                <?php echo $lang['DeleteCurrentCategoryNamed']; ?> <?php create_dropdown("category"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_category" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="team" method="post" action="">
                <p>
                <h4><?php echo $lang['Team']; ?>:</h4>
                <?php echo $lang['AddNewTeamNamed']; ?> <input name="new_team" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_team" /><br />
                <?php echo $lang['DeleteCurrentTeamNamed']; ?> <?php create_dropdown("team"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_team" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="technology" method="post" action="">
                <p>
                <h4><?php echo $lang['Technology']; ?>:</h4>
                <?php echo $lang['AddNewTechnologyNamed']; ?> <input name="new_technology" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_technology" /><br />
                <?php echo $lang['DeleteCurrentTechnologyNamed']; ?> <?php create_dropdown("technology"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_technology" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="location" method="post" action="">
                <p>
                <h4><?php echo $lang['SiteLocation']; ?>:</h4>
                <?php echo $lang['AddNewSiteLocationNamed']; ?> <input name="new_location" type="text" maxlength="100" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_location" /><br />
                <?php echo $lang['DeleteCurrentSiteLocationNamed']; ?> <?php create_dropdown("location"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_location" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="regulation" method="post" action="">
                <p>
                <h4><?php echo $lang['ControlRegulation']; ?>:</h4>
                <?php echo $lang['AddNewControlRegulationNamed']; ?> <input name="new_regulation" type="text" maxlength="50" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_regulation" /><br />
                <?php echo $lang['DeleteCurrentControlRegulationNamed']; ?> <?php create_dropdown("regulation"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_regulation" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="planning_strategy" method="post" action="">
                <p>
                <h4><?php echo $lang['RiskPlanningStrategy']; ?>:</h4>
                <?php echo $lang['AddNewRiskPlanningStrategyNamed']; ?> <input name="new_planning_strategy" type="text" maxlength="20" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_planning_strategy" /><br />
                <?php echo $lang['DeleteCurrentRiskPlanningStrategyNamed']; ?> <?php create_dropdown("planning_strategy"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_planning_strategy" />
                </p>
                </form>
              </div>
              <div class="hero-unit">
                <form name="close_reason" method="post" action="">
                <p>
                <h4><?php echo $lang['CloseReason']; ?>:</h4>
                <?php echo $lang['AddNewCloseReasonNamed']; ?> <input name="new_close_reason" type="text" maxlength="20" size="20" />&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Add']; ?>" name="add_close_reason" /><br />
                <?php echo $lang['DeleteCurrentCloseReasonNamed']; ?> <?php create_dropdown("close_reason"); ?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang['Delete']; ?>" name="delete_close_reason" />
                </p>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
