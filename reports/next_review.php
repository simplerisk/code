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
                <a href="../index.php"><?php echo $escaper->escapeHtml($lang['Home']); ?></a> 
              </li>
              <li>
                <a href="../management/index.php"><?php echo $escaper->escapeHtml($lang['RiskManagement']); ?></a> 
              </li>
              <li class="active">
                <a href="index.php"><?php echo $escaper->escapeHtml($lang['Reporting']); ?></a> 
              </li>
<?php
if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
{
          echo "<li>\n";
          echo "<a href=\"../admin/index.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>\n";
          echo "</li>\n";
}
          echo "</ul>\n";
          echo "</div>\n";

if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
{
          echo "<div class=\"btn-group pull-right\">\n";
          echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($_SESSION['name']) . "<span class=\"caret\"></span></a>\n";
          echo "<ul class=\"dropdown-menu\">\n";
          echo "<li>\n";
          echo "<a href=\"../account/profile.php\">". $escaper->escapeHtml($lang['MyProfile']) ."</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"../logout.php\">". $escaper->escapeHtml($lang['Logout']) ."</a>\n";
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
              <a href="index.php"><?php echo $escaper->escapeHtml($lang['RiskDashboard']); ?></a>      
            </li>
            <li>
              <a href="trend.php"><?php echo $escaper->escapeHtml($lang['RiskTrend']); ?></a>
            </li>
            <li>
              <a href="my_open.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksAssignedToMeByRiskLevel']); ?></a>
            </li>
            <li>
              <a href="open.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksByRiskLevel']); ?></a>
            </li>
            <li>
              <a href="projects.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksConsideredForProjectsByRiskLevel']); ?></a>
            </li>
            <li class="active">
              <a href="next_review.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksAcceptedUntilNextReviewByRiskLevel']); ?></a>
            </li>
            <li>
              <a href="production_issues.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksToSubmitAsAProductionIssueByRiskLevel']); ?></a>
            </li>
            <li>
              <a href="teams.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksByTeam']); ?></a>
            </li>
            <li>
              <a href="technologies.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksByTechnology']); ?></a>
            </li>
            <li>
              <a href="risk_scoring.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksByScoringMethod']); ?></a>
            </li>
            <li>
              <a href="review_needed.php"><?php echo $escaper->escapeHtml($lang['AllOpenRisksNeedingReview']); ?></a>
            </li>
            <li>
              <a href="closed.php"><?php echo $escaper->escapeHtml($lang['AllClosedRisksByRiskLevel']); ?></a>
            </li>
            <li>
              <a href="high.php"><?php echo $escaper->escapeHtml($lang['HighRiskReport']); ?></a>
            </li>
            <li>
              <a href="submitted_by_date.php"><?php echo $escaper->escapeHtml($lang['SubmittedRisksByDate']); ?></a>
            </li>
            <li>
              <a href="mitigations_by_date.php"><?php echo $escaper->escapeHtml($lang['MitigationsByDate']); ?></a>
            </li>
            <li>
              <a href="mgmt_reviews_by_date.php"><?php echo $escaper->escapeHtml($lang['ManagementReviewsByDate']); ?></a>
            </li>
            <li>
              <a href="closed_by_date.php"><?php echo $escaper->escapeHtml($lang['ClosedRisksByDate']); ?></a>
            </li>
            <li>
              <a href="projects_and_risks.php"><?php echo $escaper->escapeHtml($lang['ProjectsAndRisksAssigned']); ?></a>
            </li>
          </ul>
        </div>
        <div class="span9">
          <div class="row-fluid"><p><?php echo $escaper->escapeHtml($lang['ReportNextReviewHelp']); ?>.</p></div>
	  <?php get_risk_table(6); ?>
        </div>
      </div>
    </div>
  </body>

</html>
