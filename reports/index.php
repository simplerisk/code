<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

	// Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/reporting.php'));
  include_once(realpath(__DIR__ . '/includes.php'));

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

        if(isset($_GET['page']) && ($_GET['page'] != '1')){
          // Record the page the workflow started from as a session variable
          $_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME']."?module=2&page=".$_GET['page'];
        }

?>

<!doctype html>
<html>
  
  <head>
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/sorttable.js"></script>
    <script src="js/highcharts.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.css"> 
  </head>
  
  <body>
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="index.php?module=0"><?php echo $lang['Home']; ?></a> 
              </li>
              <li>
                <a href="index.php?module=1"><?php echo $lang['RiskManagement']; ?></a> 
              </li>
              <li class="active">
                <a href="index.php?module=2"><?php echo $lang['Reporting']; ?></a> 
              </li>
<?php
if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
{
          echo "<li>\n";
          echo "<a href=\"index.php?module=3\">". $lang['Configure'] ."</a>\n";
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
          echo "<a href=\"index.php?module=4\">". $lang['MyProfile'] ."</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"logout.php\">". $lang['Logout'] ."</a>\n";
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
              <a href="index.php?module=2"><?php echo $lang['RiskDashboard']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '1') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=1"><?php echo $lang['RiskTrend']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '2') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=2"><?php echo $lang['AllOpenRisksAssignedToMeByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '3') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=3"><?php echo $lang['AllOpenRisksByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '4') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=4"><?php echo $lang['AllOpenRisksConsideredForProjectsByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '5') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=5"><?php echo $lang['AllOpenRisksAcceptedUntilNextReviewByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '6') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=6"><?php echo $lang['AllOpenRisksToSubmitAsAProductionIssueByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '7') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=7"><?php echo $lang['AllOpenRisksByTeam']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '8') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=8"><?php echo $lang['AllOpenRisksByTechnology']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '9') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=9"><?php echo $lang['AllOpenRisksByScoringMethod']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '10') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=10"><?php echo $lang['AllOpenRisksNeedingReview']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '11') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=11"><?php echo $lang['AllClosedRisksByRiskLevel']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '12') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=12"><?php echo $lang['HighRiskReport']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '13') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=13"><?php echo $lang['SubmittedRisksByDate']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '14') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=14"><?php echo $lang['MitigationsByDate']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '15') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=15"><?php echo $lang['ManagementReviewsByDate']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '16') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=16"><?php echo $lang['ClosedRisksByDate']; ?></a>
            </li>
            <li <?php if($_GET['page'] == '17') { ?>class="active"<? } ?>>
              <a href="index.php?module=2&page=17"><?php echo $lang['ProjectsAndRisksAssigned']; ?></a>
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
            } else {
                switch ($_GET['page']) {
                          case 1:
                            get_trend(); 
                            break;
                          case 2: 
                            get_my_open(); 
                            break;
                          case 3:
                            get_open();
                            break;
                          case 4:
                            get_myprojects();
                            break;
                          case 5:
                            get_next_review();
                            break;
                          case 6:
                            get_production_issues();
                            break;
                          case 7:
                            get_teams();
                            break;
                          case 8:
                            get_technologies();
                            break;
                          case 9:
                            get_risk_scoring();
                            break;
                          case 10:
                            get_review_needed();
                            break;
                          case 11:
                            get_closed();
                            break;
                          case 12:
                            get_high();
                            break;
                          case 13:
                            get_submitted_by_date();
                            break;
                          case 14:
                            get_mitigations_by_date();
                            break;
                          case 15:
                            get_mgmt_reviews_by_date();
                            break;
                          case 16:
                            get_closed_by_date();
                            break;
                          case 17:
                            get_projects_and_risks();
                            break;
                          default:
                            break;
                }
            }
          ?>
        </div>
      </div>
    </div>
  </body>

</html>
