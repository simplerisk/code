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

        // Check for session timeout or renegotiation
        session_check();

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Record the page the workflow started from as a session variable
        $_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];
?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/sorttable.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
  </head>
  
  <body>
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="../index.php">Home</a> 
              </li>
              <li>
                <a href="../management/index.php">Risk Management</a> 
              </li>
              <li class="active">
                <a href="index.php">Reporting</a> 
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
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <ul class="nav  nav-pills nav-stacked">
            <li>
              <a href="index.php">Risk Dashboard</a>      
            </li>
            <li>
              <a href="trend.php">Risk Trend</a>
            </li>
            <li>
              <a href="my_open.php">All Open Risks Assigned to Me by Risk Level</a>
            </li>
            <li>
              <a href="open.php">All Open Risks by Risk Level</a>
            </li>
            <li>
              <a href="projects.php">All Open Risks Considered for Projects by Risk Level</a>
            </li>
            <li>
              <a href="next_review.php">All Open Risks Accepted Until Next Review by Risk Level</a>
            </li>
            <li>
              <a href="production_issues.php">All Open Risks to Submit as a Production Issue by Risk Level</a>
            </li>
            <li>
              <a href="cvss_scored.php">All Open Risks Scored Using CVSS Scoring by Risk Level</a>
            </li>
            <li>
              <a href="classic_scored.php">All Open Risks Scored Using Classic Scoring by Risk Level</a>
            </li>
            <li>
              <a href="closed.php">All Closed Risks by Risk Level</a>
            </li>
            <li>
              <a href="submitted_by_date.php">Submitted Risks by Date</a>
            </li>
            <li class="active">
              <a href="mitigations_by_date.php">Mitigations By Date</a>
            </li>
            <li>
              <a href="mgmt_reviews_by_date.php">Management Reviews By Date</a>
            </li>
            <li>
              <a href="projects_and_risks.php">Projects and Risks Assigned</a>
            </li>
          </ul>
        </div>
        <div class="span9">
          <div class="row-fluid"><p>This report shows all mitigations planned ordered by mitigation date.</p></div>
	  <?php get_mitigations_table(); ?>
        </div>
      </div>
    </div>
  </body>

</html>
