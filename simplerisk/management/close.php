<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_riskmanagement" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

    // Check if the user has access to close risks
    if (!isset($_SESSION["close_risks"]) || $_SESSION["close_risks"] != 1)
    {
            $close_risks = false;

    // Display an alert
    set_alert(true, "bad", "You do not have permission to close risks.  Any attempts to close risks will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
    }
    else $close_risks = true;

    // Check if a risk ID was sent
    if (isset($_GET['id']) || isset($_POST['id']))
    {
            if (isset($_GET['id']))
            {
                    // Test that the ID is a numeric value
                    $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);
            }
            else if (isset($_POST['id']))
            {
                    // Test that the ID is a numeric value
                    $id = (is_numeric($_POST['id']) ? (int)$_POST['id'] : 0);
            }

            // If team separation is enabled
            if (team_separation_extra())
            {
                    //Include the team separation extra
                    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                    // If the user should not have access to the risk
                    if (!extra_grant_access($_SESSION['uid'], $id))
                    {
                            // Redirect back to the page the workflow started on
                            header("Location: " . $_SESSION["workflow_start"]);
                            exit(0);
                    }
            }

            // Get the details of the risk
            $risk = get_risk_by_id($id);

            // If the risk was found use the values for the risk
            if (count($risk) != 0)
            {
                $status = $risk[0]['status'];
                $subject = $risk[0]['subject'];
                $calculated_risk = $risk[0]['calculated_risk'];
            }
            // If the risk was not found use null values
            else
            {
                // If Risk ID exists.
                if(check_risk_by_id($id)){
                    $status = $lang["RiskDisplayPermission"];
                }
                // If Risk ID does not exist.
                else{
                    $status = $lang["RiskIdDoesNotExist"];
                }
                $subject = "N/A";
                $calculated_risk = "0.0";
            }
            
            // Get the mitigation for the risk
            $mitigation = get_mitigation_by_id($id);
            if ($mitigation == true){
                $mitigation_percent = isset($mitigation[0]['mitigation_percent']) ? $mitigation[0]['mitigation_percent'] : 0;
            }
            else
            {
                $mitigation_percent = 0;
            }

    }

    // Check if a risk closure was submitted and the user has permissions to close risks
    if ((isset($_POST['submit'])) && $close_risks)
    {
            $status = "Closed";
            $close_reason = $_POST['close_reason'];
            $note = $_POST['note'];

    // Submit a review
    submit_management_review($id, $status, "", "", $_SESSION['uid'], $note, "0000-00-00", true);

            // Close the risk
            close_risk($id, $_SESSION['uid'], $status, $close_reason, $note);

    // Display an alert
            set_alert(true, "good", "Your risk has now been marked as closed.");

            // Check that the id is a numeric value
            if (is_numeric($id))
            {
                    // Create the redirection location
        $url = "view.php?id=" . $id;

                    // Redirect to plan mitigations page
                    header("Location: " . $url);
            }
    }
?>

<!doctype html>
<html>

<head>
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

	display_bootstrap_javascript();
?>
<title>SimpleRisk: Enterprise Risk Management Simplified</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
</head>

<body>
<title>SimpleRisk: Enterprise Risk Management Simplified</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">

<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
<link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

<?php
view_top_menu("RiskManagement");
?>
<div class="container-fluid">
  <div class="row-fluid">
    <div class="span3">
      <?php view_risk_management_menu("ReviewRisksRegularly"); ?>
    </div>
    <div class="span9">
      <div class="row-fluid">
        <div class="well">
          <?php view_top_table($id, $calculated_risk, $subject, $status, false, $mitigation_percent); ?>
        </div>
      </div>
      
      <?php 
          include(realpath(__DIR__ . '/partials/close.php'));
      ?>
      
    </div>
  </div>
</div>
</body>

</html>
