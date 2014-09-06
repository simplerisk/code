<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/reporting.php'));

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

        if(isset($_GET['page']) && ($_GET['page'] != 'trend')){
          // Record the page the workflow started from as a session variable
          $_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];
        }


?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/sorttable.js"></script>
    <script src="../js/highcharts.js"></script>
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
                <a href="../index.php"><?php echo $lang['Home']; ?></a> 
              </li>
              <li>
                <a href="../management/index.php"><?php echo $lang['RiskManagement']; ?></a> 
              </li>
              <li class="active">
                <a href="index.php"><?php echo $lang['Reporting']; ?></a> 
              </li>
<?php
if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
{
          echo "<li>\n";
          echo "<a href=\"../admin/index.php\">". $lang['Configure'] ."</a>\n";
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
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <ul class="nav  nav-pills nav-stacked">
            <li <?php if(!isset($_GET['page'])) { ?>class="active"<? } ?>>
              <a href="index.php"><?php echo $lang['RiskDashboard']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == 'trend') { ?>class="active"<? } ?>>
              <a href="index.php?page=trend"><?php echo $lang['RiskTrend']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'my_open') { ?>class="active"<? } ?>>
              <a href="index.php?page=my_open"><?php echo $lang['AllOpenRisksAssignedToMeByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'open') { ?>class="active"<? } ?>>
              <a href="index.php?page=open"><?php echo $lang['AllOpenRisksByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'projects') { ?>class="active"<? } ?>>
              <a href="index.php?page=projects"><?php echo $lang['AllOpenRisksConsideredForProjectsByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'next_review') { ?>class="active"<? } ?>>
              <a href="index.php?page=next_review"><?php echo $lang['AllOpenRisksAcceptedUntilNextReviewByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'production_issues') { ?>class="active"<? } ?>>
              <a href="index.php?page=production_issues"><?php echo $lang['AllOpenRisksToSubmitAsAProductionIssueByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'teams') { ?>class="active"<? } ?>>
              <a href="index.php?page=teams"><?php echo $lang['AllOpenRisksByTeam']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'technologies') { ?>class="active"<? } ?>>
              <a href="index.php?page=technologies"><?php echo $lang['AllOpenRisksByTechnology']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'risk_scoring') { ?>class="active"<? } ?>>
              <a href="index.php?page=risk_scoring"><?php echo $lang['AllOpenRisksByScoringMethod']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'review_needed') { ?>class="active"<? } ?>>
              <a href="index.php?page=review_needed"><?php echo $lang['AllOpenRisksNeedingReview']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'closed') { ?>class="active"<? } ?>>
              <a href="index.php?page=closed"><?php echo $lang['AllClosedRisksByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'high') { ?>class="active"<? } ?>>
              <a href="index.php?page=high"><?php echo $lang['HighRiskReport']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'submitted_by_date') { ?>class="active"<? } ?>>
              <a href="index.php?page=submitted_by_date"><?php echo $lang['SubmittedRisksByDate']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'mitigations_by_date') { ?>class="active"<? } ?>>
              <a href="index.php?page=mitigations_by_date"><?php echo $lang['MitigationsByDate']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'mgmt_reviews_by_date') { ?>class="active"<? } ?>>
              <a href="index.php?page=mgmt_reviews_by_date"><?php echo $lang['ManagementReviewsByDate']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'closed_by_date') { ?>class="active"<? } ?>>
              <a href="index.php?page=closed_by_date"><?php echo $lang['ClosedRisksByDate']; ?></a>
            </li>
            <li <?php if($_GET['page'] == 'projects_and_risks') { ?>class="active"<? } ?>>
              <a href="index.php?page=projects_and_risks"><?php echo $lang['ProjectsAndRisksAssigned']; ?></a>
            </li>
          </ul>
        </div>
        <div class="span9">
          <?php 
            if(!isset($_GET['page'])) {
          ?>
          <div class="row-fluid">
            <h3><?php echo $lang['OpenRisks']; ?> (<?php echo get_open_risks(); ?>)</h3>
          </div>
          <div class="row-fluid">
            <div class="span3">
              <div class="well">
                <?php open_risk_level_pie($lang['RiskLevel']); ?>
              </div>
            </div>
            <div class="span3">
              <div class="well">
                <?php open_risk_status_pie($lang['Status']); ?>
              </div>
            </div>
            <div class="span3">
              <div class="well">
                <?php open_risk_location_pie($lang['SiteLocation']); ?>
              </div>
            </div>
          </div>
          <div class="row-fluid">
            <div class="span3">
              <div class="well">
                <?php open_risk_category_pie($lang['Category']); ?>
              </div>
            </div>
            <div class="span3">
              <div class="well">
                <?php open_risk_team_pie($lang['Team']); ?>
              </div>
            </div>
            <div class="span3">
              <div class="well">
                <?php open_risk_technology_pie($lang['Technology']); ?>
              </div>
            </div>
          </div>
          <div class="row-fluid">
            <div class="span3">
              <div class="well">
                <?php open_risk_owner_pie($lang['Owner']); ?>
              </div>
            </div>
            <div class="span3">
              <div class="well">
                <?php open_risk_owners_manager_pie($lang['OwnersManager']); ?>
              </div>
            </div>
            <div class="span3">
              <div class="well">
                <?php open_risk_scoring_method_pie($lang['RiskScoringMethod']); ?>
              </div>
            </div>
          </div>
          <div class="row-fluid">
            <h3><?php echo $lang['ClosedRisks']; ?>: (<?php echo get_closed_risks(); ?>)</h3>
          </div>
          <div class="row-fluid">
            <div class="span3">
              <div class="well">
                <?php closed_risk_reason_pie($lang['Reason']); ?>
              </div>
            </div>
          </div>
          <?php 
            } else if($_GET['page'] == 'trend') {
                include 'trend.php';
            } else if($_GET['page'] == 'my_open') {
                include 'my_open.php';
            } else if($_GET['page'] == 'open') {
                include 'open.php';
            } else if($_GET['page'] == 'projects') {
                include 'projects.php';
            } else if($_GET['page'] == 'next_review') {
                include 'next_review.php';
            } else if($_GET['page'] == 'production_issues') {
                include 'production_issues.php';
            } else if($_GET['page'] == 'teams') {
                include 'teams.php'; 
            } else if($_GET['page'] == 'technologies') {
                include 'technologies.php';
            } else if($_GET['page'] == 'risk_scoring') {
                include 'risk_scoring.php'; 
            } else if($_GET['page'] == 'review_needed') {
                include 'review_needed.php'; 
            } else if($_GET['page'] == 'closed') {
                include 'closed.php'; 
            } else if($_GET['page'] == 'high') {
                include 'high.php';
            } else if($_GET['page'] == 'submitted_by_date') {
                include 'submitted_by_date.php'; 
            } else if($_GET['page'] == 'mitigations_by_date') {
                include 'mitigations_by_date.php';  
            } else if($_GET['page'] == 'mgmt_reviews_by_date') {
                include 'mgmt_reviews_by_date.php';  
            } else if($_GET['page'] == 'closed_by_date') {
                include 'closed_by_date.php'; 
            } else if($_GET['page'] == 'projects_and_risks') {
                include 'projects_and_risks.php';  
            }
          ?>
        </div>
      </div>
    </div>
  </body>

</html>
