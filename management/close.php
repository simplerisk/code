<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once('../includes/functions.php');
        require_once('../includes/authenticate.php');
	require_once('../includes/display.php');

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
        require_once('../includes/csrf-magic/csrf-magic.php');

        // Check for session timeout or renegotiation
        session_check();

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Check if the user has access to close risks
        if (!isset($_SESSION["close_risks"]) || $_SESSION["close_risks"] != 1)
        {
                $close_risks = false;
                $alert = "bad";
                $alert_message = "You do not have permission to close risks.  Any attempts to close risks will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
        }
        else $close_risks = true;

        // Check if a risk ID was sent
        if (isset($_GET['id']) || isset($_POST['id']))
        {
                if (isset($_GET['id']))
		{
			$id = htmlentities($_GET['id'], ENT_QUOTES);
		}
		else if (isset($_POST['id']))
		{
			$id = htmlentities($_POST['id'], ENT_QUOTES);
		}

                // Get the details of the risk
                $risk = get_risk_by_id($id);

                // If the risk was found use the values for the risk
                if (count($risk) != 0)
                {
                        $status = htmlentities($risk[0]['status'], ENT_QUOTES);
                        $subject = htmlentities($risk[0]['subject'], ENT_QUOTES);
                        $calculated_risk = htmlentities($risk[0]['calculated_risk'], ENT_QUOTES);
                }
                // If the risk was not found use null values
                else
                {
                        $status = "Risk ID Does Not Exist";
                        $subject = "N/A";
                        $calculated_risk = "0.0";
                }
        }

        // Check if a risk closure was submitted and the user has permissions to close risks
        if ((isset($_POST['submit'])) && $close_risks)
        {
                $status = "Closed";
                $close_reason = addslashes($_POST['close_reason']);
                $note = addslashes($_POST['note']);

                // Close the risk
                close_risk($id, $_SESSION['uid'], $status, $close_reason, $note);

                // Audit log
                $risk_id = $id;
                $message = "Risk ID \"" . $risk_id . "\" was marked as closed by username \"" . $_SESSION['user'] . "\".";
                write_log($risk_id, $_SESSION['uid'], $message);

                // Check that the id is a numeric value
                if (is_numeric($id))
                {
                        // Create the redirection location
			$url = "view.php?id=" . $id . "&closed=true";

                        // Redirect to plan mitigations page
                        header("Location: " . $url); 
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
                <a href="../index.php">Home</a> 
              </li>
              <li class="active">
                <a href="index.php">Risk Management</a> 
              </li>
              <li>
                <a href="../reports/index.php">Reporting</a> 
              </li>
<?php
if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
{
          echo "<li>\n";
          echo "<a href=\"../admin/index.php\">Configure</a>\n";
          echo "</li>\n";
}
          echo "</ul>\n";
          echo "</div>\n";

if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
{
          echo "<div class=\"btn-group pull-right\">\n";
          echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">".$_SESSION['name']."<span class=\"caret\"></span></a>\n";
          echo "<ul class=\"dropdown-menu\">\n";
          echo "<li>\n";
          echo "<a href=\"../account/profile.php\">My Profile</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"../logout.php\">Logout</a>\n";
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
              <a href="index.php">I. Submit Your Risks</a> 
            </li>
            <li>
              <a href="plan_mitigations.php">II. Plan Your Mitigations</a> 
            </li>
            <li>
              <a href="management_review.php">III. Perform Management Reviews</a> 
            </li>
            <li>
              <a href="prioritize_planning.php">IV. Prioritize for Project Planning</a> 
            </li>
            <li class="active">
              <a href="review_risks.php">V. Review Risks Regularly</a>
            </li>
          </ul>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="well">
              <?php view_top_table($id, $calculated_risk, $subject, $status, false); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <form name="close_risk" method="post" action="">
                <h4>Close Risk</h4>
                Reason: <?php create_dropdown("close_reason"); ?><br />
                <label>Close-Out Information</label>
                <textarea name="note" cols="50" rows="3" id="note"></textarea>
                <div class="form-actions">
                  <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                  <input class="btn" value="Reset" type="reset">
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
