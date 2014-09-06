<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));

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

	// Default is no alert
	$alert = false;

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

        if(isset($_GET['page']) && ($_GET['page'] == 'plan_mitigations' || $_GET['page'] == 'management_review' || $_GET['page'] == 'review_risks')){
          // Record the page the workflow started from as a session variable
          $_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];
        }


  if(!isset($_GET['page'])) {
    	// Check if the user has access to submit risks
    	if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1)
    	{
    		$submit_risks = false;
    		$alert = "bad";
    		$alert_message = "You do not have permission to submit new risks.  Any risks that you attempt to submit will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
    	}
    	else $submit_risks = true;

            // Check if a new risk was submitted and the user has permissions to submit new risks
            if ((isset($_POST['submit'])) && $submit_risks)
            {
                    $status = "New";
                    $subject = addslashes($_POST['subject']);
    		$reference_id = addslashes($_POST['reference_id']);
    		$regulation = (int)$_POST['regulation'];
    		$control_number = addslashes($_POST['control_number']);
    		$location = addslashes($_POST['location']);
                    $category = (int)$_POST['category'];
                    $team = (int)$_POST['team'];
                    $technology = (int)$_POST['technology'];
                    $owner = (int)$_POST['owner'];
                    $manager = (int)$_POST['manager'];
                    $assessment = addslashes($_POST['assessment']);
                    $notes = addslashes($_POST['notes']);

    		// Risk scoring method
    		// 1 = Classic
    		// 2 = CVSS
    		// 3 = DREAD
    		// 4 = OWASP
    		// 5 = Custom
    		$scoring_method = (int)$_POST['scoring_method'];

    		// Classic Risk Scoring Inputs
    		$CLASSIClikelihood = (int)$_POST['likelihood'];
                    $CLASSICimpact =(int) $_POST['impact'];

    		// CVSS Risk Scoring Inputs
    		$CVSSAccessVector = $_POST['AccessVector'];
    		$CVSSAccessComplexity = $_POST['AccessComplexity'];
    		$CVSSAuthentication = $_POST['Authentication'];
    		$CVSSConfImpact = $_POST['ConfImpact'];
    		$CVSSIntegImpact = $_POST['IntegImpact'];
    		$CVSSAvailImpact = $_POST['AvailImpact'];
    		$CVSSExploitability = $_POST['Exploitability'];
    		$CVSSRemediationLevel = $_POST['RemediationLevel'];
    		$CVSSReportConfidence = $_POST['ReportConfidence'];
    		$CVSSCollateralDamagePotential = $_POST['CollateralDamagePotential'];
    		$CVSSTargetDistribution = $_POST['TargetDistribution'];
    		$CVSSConfidentialityRequirement = $_POST['ConfidentialityRequirement'];
    		$CVSSIntegrityRequirement = $_POST['IntegrityRequirement'];
    		$CVSSAvailabilityRequirement = $_POST['AvailabilityRequirement'];

    		// DREAD Risk Scoring Inputs
    		$DREADDamage = (int)$_POST['DREADDamage'];
    		$DREADReproducibility = (int)$_POST['DREADReproducibility'];
    		$DREADExploitability = (int)$_POST['DREADExploitability'];
    		$DREADAffectedUsers = (int)$_POST['DREADAffectedUsers'];
    		$DREADDiscoverability = (int)$_POST['DREADDiscoverability'];

    		// OWASP Risk Scoring Inputs
    		$OWASPSkillLevel = (int)$_POST['OWASPSkillLevel'];
    		$OWASPMotive = (int)$_POST['OWASPMotive'];
    		$OWASPOpportunity = (int)$_POST['OWASPOpportunity'];
    		$OWASPSize = (int)$_POST['OWASPSize'];
    		$OWASPEaseOfDiscovery = (int)$_POST['OWASPEaseOfDiscovery'];
    		$OWASPEaseOfExploit = (int)$_POST['OWASPEaseOfExploit'];
    		$OWASPAwareness = (int)$_POST['OWASPAwareness'];
    		$OWASPIntrusionDetection = (int)$_POST['OWASPIntrusionDetection'];
    		$OWASPLossOfConfidentiality = (int)$_POST['OWASPLossOfConfidentiality'];
    		$OWASPLossOfIntegrity = (int)$_POST['OWASPLossOfIntegrity'];
    		$OWASPLossOfAvailability = (int)$_POST['OWASPLossOfAvailability'];
    		$OWASPLossOfAccountability = (int)$_POST['OWASPLossOfAccountability'];
    		$OWASPFinancialDamage = (int)$_POST['OWASPFinancialDamage'];
    		$OWASPReputationDamage = (int)$_POST['OWASPReputationDamage'];
    		$OWASPNonCompliance = (int)$_POST['OWASPNonCompliance'];
    		$OWASPPrivacyViolation = (int)$_POST['OWASPPrivacyViolation'];

    		// Custom Risk Scoring
    		$custom = $_POST['Custom'];

                    // Submit risk and get back the id
                    $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);

    		// Submit risk scoring
    		submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);

    		// If the notification extra is enabled
            	if (notification_extra())
            	{
                    	// Include the team separation extra
                    	require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

                    	// Send the notification
                    	notify_new_risk($last_insert_id, $subject);
            	}

    		// Audit log
    		$risk_id = $last_insert_id + 1000;
    		$message = "A new risk ID \"" . $risk_id . "\" was submitted by username \"" . $_SESSION['user'] . "\".";
    		write_log($risk_id, $_SESSION['uid'], $message);

    		// There is an alert message
    		$alert = "good";
    		$alert_message = "Risk submitted successfully!";
            }
    }

    else if($_GET['page'] == 'plan_mitigations'){
        // If mitigated was passed back to the page as a GET parameter
        if (isset($_GET['mitigated']))
        {
          // If its true
          if ($_GET['mitigated'] == true)
          {
            $alert = "good";
            $alert_message = "Mitigation submitted successfully!";
          }
        }

    }

    else if($_GET['page'] == 'management_review'){
        // If reviewed is passed via GET
        if (isset($_GET['reviewed']))
        {
                // If it's true
                if ($_GET['reviewed'] == true)
                {
                        $alert = "good";
                        $alert_message = "Management review submitted successfully!";
                }       
        }

    }

    else if($_GET['page'] == 'prioritize_planning'){
        // If the risks were saved to projects
          if (isset($_POST['update_projects']))
          {
            foreach ($_POST['ids'] as $risk_id)
                        {
                                $project_id = $_POST['risk_' . $risk_id];
                                update_risk_project($project_id, $risk_id);
                        }

            // There is an alert message
            $alert = "good";
            $alert_message = "The risks were saved successfully to the projects.";
                }


          // If the order was updated
          if (isset($_POST['update_order']))
          {
            foreach ($_POST['ids'] as $id)
            {
              $order = $_POST['order_' . $id];
              update_project_order($order, $id);
            }

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "The project order was updated successfully.";
          }

          // If the projects were saved to status
          if (isset($_POST['update_project_status']))
          {
            foreach ($_POST['projects'] as $project_id)
            {
              $status_id = $_POST['project_' . $project_id];
              update_project_status($status_id, $project_id);
            }

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "The project statuses were successfully updated.";
          }

                // Check if a new project was submitted
                if (isset($_POST['add_project']))
                {
                        $name = $_POST['new_project'];

                        // Insert a new project up to 100 chars
                        add_name("projects", $name, 100);

                        // Audit log
                        $risk_id = 1000;
                        $message = "A new project was added by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

            // There is an alert message
                        $alert = "good";
                        $alert_message = "A new project was added successfully.";
                }

                // Check if a project was deleted
                if (isset($_POST['delete_project']))
                {
                        $value = (int)$_POST['projects'];

                        // Verify value is an integer
                        if (is_int($value))
                        {
              // If the project ID is 0 (ie. Unassigned Risks)
              if ($value == 0)
              {
                // There is an alert message
                $alert = "bad";
                $alert_message = "You cannot delete the Unassigned Risks project or we will have no place to put unassigned risks.  Sorry.";
              }
              // If the project has risks associated with it
              else if (project_has_risks($value))
              {
                // There is an alert message
                $alert = "bad";
                $alert_message = "You cannot delete a project that has risks assigned to it.  Drag the risks back to the Unassigned Risks tab, save it, and try again.";
              }
              else
              {
                                  delete_value("projects", $value);

                                  // Audit log
                                  $risk_id = 1000;
                                  $message = "An existing project was removed by the \"" . $_SESSION['user'] . "\" user.";
                                  write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                            $alert_message = "An existing project was deleted successfully.";
              }
                        }
            // We should never get here as we bound the variable as an int
            else
            {
              // There is an alert message
              $alert = "bad";
              $alert_message = "The project ID was not a valid value.  Please try again.";
            }
                }

    }

    else if($_GET['page'] == 'review_risks') {
      // If reviewed is passed via GET
        if (isset($_GET['reviewed']))
        {
                // If it's true
                if ($_GET['reviewed'] == true)
                {
                        $alert = "good";
                        $alert_message = "Risk review submitted successfully!";
                }
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
    <script type="text/javascript">
      function popupcvss()
      {
        my_window = window.open('cvss_rating.php','popupwindow','width=850,height=680,menu=0,status=0');
      }

      function popupdread()
      {
        my_window = window.open('dread_rating.php','popupwindow','width=660,height=500,menu=0,status=0');
      }

      function popupowasp()
      {
        my_window = window.open('owasp_rating.php','popupwindow','width=665,height=570,menu=0,status=0');
      }

      function closepopup()
      {
        if(false == my_window.closed)
        {
          my_window.close ();
        }
        else
        {
          alert('Window already closed!');
        }
      }

      function handleSelection(choice) {
        if (choice=="1") {
	  document.getElementById("classic").style.display = "";
          document.getElementById("cvss").style.display = "none";
          document.getElementById("dread").style.display = "none";
          document.getElementById("owasp").style.display = "none";
          document.getElementById("custom").style.display = "none";
	}
        if (choice=="2") {
          document.getElementById("classic").style.display = "none";
          document.getElementById("cvss").style.display = "";
          document.getElementById("dread").style.display = "none";
          document.getElementById("owasp").style.display = "none";
          document.getElementById("custom").style.display = "none";
	}
        if (choice=="3") {
          document.getElementById("classic").style.display = "none";
          document.getElementById("cvss").style.display = "none";
          document.getElementById("dread").style.display = "";
          document.getElementById("owasp").style.display = "none";
          document.getElementById("custom").style.display = "none";
        }
        if (choice=="4") {
          document.getElementById("classic").style.display = "none";
          document.getElementById("cvss").style.display = "none";
          document.getElementById("dread").style.display = "none";
          document.getElementById("owasp").style.display = "";
          document.getElementById("custom").style.display = "none";
        }
        if (choice=="5") {
          document.getElementById("classic").style.display = "none";
          document.getElementById("cvss").style.display = "none";
          document.getElementById("dread").style.display = "none";
          document.getElementById("owasp").style.display = "none";
          document.getElementById("custom").style.display = "";
        }
      }
    </script>
    <?php 
      if($_GET['page'] == 'prioritize_planning'){
    ?>
        <style>
        <?php
          // Get the projects
                $projects = get_projects();

          // Get the total number of projects
          $count = count($projects);

          // Initialize the counter
          $counter = 1;

          // For each project created
                foreach ($projects as $project)
                {
            // Get the project ID
                        $id = $project['value'];

            echo "#sortable-" . $id . " li";

                        // If it's not the last one
                        if ($counter != $count)
                        {
                                echo ", ";
              $counter++;
                        }
                }

          echo ", #statussortable-1 li, #statussortable-2 li, #statussortable-3 li, #statussortable-4 li";
          echo " { margin: 0 5px 5px 5px; padding: 5px; font-size: 0.75em; width: 120px; }\n";
        ?>
            </style>
            <script>
              $(function() {
        <?php
          echo "$( \"";

                // Initialize the counter
                $counter = 1;

          // For each project created
          foreach ($projects as $project)
                {
            // Get the project ID
                        $id = $project['value'];

            echo "#sortable-" . $id;

                        // If it's not the last one
                        if ($counter != $count)
                        {
                                echo ", ";
                                $counter++;
                        }
          }

          echo ", #statussortable-1, #statussortable-2, #statussortable-3, #statussortable-4";
          echo "\" ).sortable().disableSelection();\n";
        ?>
                var $tabs = $( "#tabs" ).tabs();
                var $tab_items = $( "ul:first li", $tabs ).droppable({
                  accept: ".connectedSortable li",
                  hoverClass: "ui-state-hover",
                  drop: function( event, ui ) {
                    var $item = $( this );
                    var $list = $( $item.find( "a" ).attr( "href" ) )
                    .find( ".connectedSortable" );
                    ui.draggable.hide( "slow", function() {
                      $tabs.tabs( "option", "active", $tab_items.index( $item ) );
                      $( this ).appendTo( $list ).show( "slow" );
                    });
                    $list.each(function() {
                      // Get the project ID that was just dropped into
                      var id = $(this).attr("id");
                      var part = id.split("-");
                      var project_id = part[1];

                      // Get the risk ID that was just dropped
                      var dragged_risk_id = $(ui.draggable).attr("id");

                      // Risk name to update
                      var risk_name = "risk_" + dragged_risk_id;

                      // Update the risk input with the proper value
                      document.getElementsByName(risk_name)[0].value = project_id;
                    });
                  }
                });

                var $statustabs = $( "#statustabs" ).tabs();
                var $status_tab_items = $( "ul:first li", $statustabs ).droppable({
                  accept: ".connectedSortable li",
                  hoverClass: "ui-state-hover",
                  drop: function( event, ui ) {
                    var $item = $( this );
                    var $list = $( $item.find( "a" ).attr( "href" ) )
                    .find( ".connectedSortable" );
                    ui.draggable.hide( "slow", function() {
                      $statustabs.tabs( "option", "active", $status_tab_items.index( $item ) );
                      $( this ).appendTo( $list ).show( "slow" );
                    });
                    $list.each(function() {
                      // Get the status ID that was just dropped into
                      var id = $(this).attr("id");
                      var part = id.split("-");
                      var project_id = part[1];

                      // Get the project ID that was just dropped
                      var dragged_project_id = $(ui.draggable).attr("id");

                      // Project name to update
                      var project_name = "project_" + dragged_project_id;

                      // Update the risk input with the proper value
                      document.getElementsByName(project_name)[0].value = project_id;
                    });
                  }
                });

              });
            </script>
            <script>
              $(function() {
                $( "#prioritize" ).sortable({
                  update: function(event, ui)
                  {
                    // Create an array with the new order
                    var order = $( "#prioritize" ).sortable('toArray');

                    for(var key in order) {
                      var val = order[key];
                      var part = val.split("_");

                      // Update each hidden field used to store the list item position
                      document.getElementById("order"+part[1]).value = key;
                    }
                  }
                });

                $( "#prioritize" ).disableSelection();
              });
            </script>
    <?php
      }
    ?>
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
              <li class="active">
                <a href="index.php"><?php echo $lang['RiskManagement']; ?></a> 
              </li>
              <li>
                <a href="../reports/index.php"><?php echo $lang['Reporting']; ?></a> 
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
            <li <?php if(!isset($_GET['page'])) { ?>class="active"<? } ?>>
              <a href="index.php">I. <?php echo $lang['SubmitYourRisks']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == 'plan_mitigations') { ?>class="active"<? } ?>>
              <a href="index.php?page=plan_mitigations">II. <?php echo $lang['PlanYourMitigations']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == 'management_review') { ?>class="active"<? } ?>>
              <a href="index.php?page=management_review">III. <?php echo $lang['PerformManagementReviews']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == 'prioritize_planning') { ?>class="active"<? } ?>>
              <a href="index.php?page=prioritize_planning">IV. <?php echo $lang['PrioritizeForProjectPlanning']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == 'review_risks') { ?>class="active"<? } ?>>
              <a href="index.php?page=review_risks">V. <?php echo $lang['ReviewRisksRegularly']; ?></a> 
            </li>
          </ul>
        </div>
        <div class="span9">
          <?php 
            if(!isset($_GET['page'])) {
          ?>
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4><?php echo $lang['DocumentANewRisk']; ?></h4>
                <p><?php echo $lang['UseThisFormHelp']; ?>.</p>
                <form name="submit_risk" method="post" action="">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="200px"><?php echo $lang['Subject']; ?>:</td>
                  <td><input maxlength="100" name="subject" id="subject" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['ExternalReferenceId']; ?>:</td>
                  <td><input maxlength="20" size="20" name="reference_id" id="reference_id" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['ControlRegulation']; ?>:</td>
                  <td><?php create_dropdown("regulation"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['ControlNumber']; ?>:</td>
                  <td><input maxlength="20" name="control_number" id="control_number" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['SiteLocation']; ?>:</td>
                  <td><?php create_dropdown("location"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['Category']; ?>:</td>
                  <td><?php create_dropdown("category"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['Team']; ?>:</td>
                  <td><?php create_dropdown("team"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['Technology']; ?>:</td>
                  <td><?php create_dropdown("technology"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['Owner']; ?>:</td>
                  <td><?php create_dropdown("user", NULL, "owner"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['OwnersManager']; ?>:</td>
                  <td><?php create_dropdown("user", NULL, "manager"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['RiskScoringMethod']; ?>:</td>
                  <td>
		    <select name="scoring_method" id="select" onChange="handleSelection(value)">
		      <option selected value="1">Classic</option>
		      <option value="2">CVSS</option>
		      <option value="3">DREAD</option>
		      <option value="4">OWASP</option>
		      <option value="5">Custom</option>
		    </select>
                  </td>
                </tr>
                <tr><td colspan="2">
		  <div id="classic">
                    <table width="100%">
                      <tr>
                        <td width="197px"><?php echo $lang['CurrentLikelihood']; ?>:</td>
                        <td><?php create_dropdown("likelihood"); ?></td>
                      </tr>
                      <tr>
                        <td width="197px"><?php echo $lang['CurrentImpact']; ?>:</td>
                        <td><?php create_dropdown("impact"); ?></td>
                      </tr>
                    </table>
		  </div>
		  <div id="cvss" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="cvssSubmit" id="cvssSubmit" value="Score Using CVSS" onclick="javascript: popupcvss();" /></p></td>
                      </tr>
                    </table>
                    <input type="hidden" name="AccessVector" id="AccessVector" value="N" />
                    <input type="hidden" name="AccessComplexity" id="AccessComplexity" value="L" />
                    <input type="hidden" name="Authentication" id="Authentication" value="N" />
                    <input type="hidden" name="ConfImpact" id="ConfImpact" value="C" />
                    <input type="hidden" name="IntegImpact" id="IntegImpact" value="C" />
                    <input type="hidden" name="AvailImpact" id="AvailImpact" value="C" />
                    <input type="hidden" name="Exploitability" id="Exploitability" value="ND" />
                    <input type="hidden" name="RemediationLevel" id="RemediationLevel" value="ND" />
                    <input type="hidden" name="ReportConfidence" id="ReportConfidence" value="ND" />
                    <input type="hidden" name="CollateralDamagePotential" id="CollateralDamagePotential" value="ND" />
                    <input type="hidden" name="TargetDistribution" id="TargetDistribution" value="ND" />
                    <input type="hidden" name="ConfidentialityRequirement" id="ConfidentialityRequirement" value="ND" />
                    <input type="hidden" name="IntegrityRequirement" id="IntegrityRequirement" value="ND" />
                    <input type="hidden" name="AvailabilityRequirement" id="AvailabilityRequirement" value="ND" />
		  </div>
		  <div id="dread" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="dreadSubmit" id="dreadSubmit" value="Score Using DREAD" onclick="javascript: popupdread();" /></p></td>
                      </tr>
                    </table>
		    <input type="hidden" name="DREADDamage" id="DREADDamage" value="10" />
		    <input type="hidden" name="DREADReproducibility" id="DREADReproducibility" value="10" />
                    <input type="hidden" name="DREADExploitability" id="DREADExploitability" value="10" />
                    <input type="hidden" name="DREADAffectedUsers" id="DREADAffectedUsers" value="10" />
                    <input type="hidden" name="DREADDiscoverability" id="DREADDiscoverability" value="10" />
		  </div>
		  <div id="owasp" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="owaspSubmit" id="owaspSubmit" value="Score Using OWASP" onclick="javascript: popupowasp();" /></p></td>
                      </tr>
                    </table>
                    <input type="hidden" name="OWASPSkillLevel" id="OWASPSkillLevel" value="10" />
                    <input type="hidden" name="OWASPMotive" id="OWASPMotive" value="10" />
                    <input type="hidden" name="OWASPOpportunity" id="OWASPOpportunity" value="10" />
                    <input type="hidden" name="OWASPSize" id="OWASPSize" value="10" />
                    <input type="hidden" name="OWASPEaseOfDiscovery" id="OWASPEaseOfDiscovery" value="10" />
                    <input type="hidden" name="OWASPEaseOfExploit" id="OWASPEaseOfExploit" value="10" />
                    <input type="hidden" name="OWASPAwareness" id="OWASPAwareness" value="10" />
                    <input type="hidden" name="OWASPIntrusionDetection" id="OWASPIntrusionDetection" value="10" />
                    <input type="hidden" name="OWASPLossOfConfidentiality" id="OWASPLossOfConfidentiality" value="10" />
		    <input type="hidden" name="OWASPLossOfIntegrity" id="OWASPLossOfIntegrity" value="10" />
                    <input type="hidden" name="OWASPLossOfAvailability" id="OWASPLossOfAvailability" value="10" />
                    <input type="hidden" name="OWASPLossOfAccountability" id="OWASPLossOfAccountability" value="10" />
                    <input type="hidden" name="OWASPFinancialDamage" id="OWASPFinancialDamage" value="10" />
                    <input type="hidden" name="OWASPReputationDamage" id="OWASPReputationDamage" value="10" />
                    <input type="hidden" name="OWASPNonCompliance" id="OWASPNonCompliance" value="10" />
                    <input type="hidden" name="OWASPPrivacyViolation" id="OWASPPrivacyViolation" value="10" />
		  </div>
		  <div id="custom" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px"><?php echo $lang['CustomValue']; ?>:</td>
                        <td><input type="text" name="Custom" id="Custom" value="" /> (Must be a numeric value between 0 and 10)</td>
                      </tr>
                    </table>
		  </div>
                  <tr>
                    <td width="200px"><?php echo $lang['RiskAssessment']; ?></td>
                    <td><textarea name="assessment" cols="50" rows="3" id="assessment"></textarea></td>
                  </tr>
                  <tr>
                    <td width="200px"><?php echo $lang['AdditionalNotes']; ?></td>
                    <td><textarea name="notes" cols="50" rows="3" id="notes"></textarea></td>
                  </tr>
                </table>
                <div class="form-actions">
                  <button type="submit" name="submit" class="btn btn-primary"><?php echo $lang['Submit']; ?></button>
                  <input class="btn" value="<?php echo $lang['Reset']; ?>" type="reset"> 
                </div>
                </form>
              </div>
            </div>
          </div>
          <?php
            } else if($_GET['page'] == 'plan_mitigations') {
                include 'plan_mitigations.php'; 
            } else if($_GET['page'] == 'management_review') {
                include 'management_review.php';
            } else if($_GET['page'] == 'prioritize_planning') {
                include 'prioritize_planning.php'; 
            } else if($_GET['page'] == 'review_risks') {
                include 'review_risks.php'; 
            }
          ?>
        </div>
      </div>
    </div>
  </body>

</html>
