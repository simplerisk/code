<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));

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
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
    }

    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    if (!isset($_SESSION))
    {
        session_name('SimpleRisk');
        session_start();
    }

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

    // Enforce that the user has access to risk management
    enforce_permission_riskmanagement();

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
    }

    // Check if the status was updated and the user has the ability to modify the risk
    if (isset($_POST['update_status']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
    {
        $status_id = (int)$_POST['status'];

        // Get the name associated with the status
        $status = get_name_by_value("status", $status_id);

        // Display an alert
        set_alert(true, "good", "Your risk status has been successfully changed.");
        // Check that the id is a numeric value
        if (is_numeric($id))
        {
            // Update the status of the risk
            update_risk_status($id, $status);

            // Create the redirection location
            $url = $_SESSION['base_url']."/management/view.php?id=" . $id;
            
            // Redirect to risk view page
            header("Location: " . $url);
            exit;
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
<!--    <link rel="stylesheet" type="text/css" href="../css/bootstrap.min.css" media="screen" />-->
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">

    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/style.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
  </head>

  <body>

    <?php view_top_menu("RiskManagement"); ?>
    <div class="tabs new-tabs">
        <div class="container-fluid">
          <div class="row-fluid">
            <div class="span3"> </div>
            <div class="span9">
              <div class="tab-append">
                <div class="tab selected form-tab tab-show" id="tab"><div><span><a href="plan_mitigations.php">Risk list</a></span></div>
                </div>
                <div class="tab selected form-tab tab-show" id="tab"><div><span><strong>ID: <?php echo $id.'</strong>  '.$escaper->escapeHtml(try_decrypt($subject)); ?></span></div>
                </div>
              </div>
            </div>
          </div>

        </div>
    </div>
  
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_risk_management_menu("ReviewRisksRegularly"); ?>
        </div>
        <div class="span9">
            <div class='tab-data' id="tab-container">
                <div class="score-overview-container">
                    <div class="overview-container">
                        <div class="row-fluid">
                            <div class="risk-session overview clearfix">
                                <div class="row-fluid">
                                    <?php view_top_table($id, $calculated_risk, $subject, $status, true, $mitigation_percent); ?>
                                </div>

                                <!-- Risk soring form -->
                                <div class="row-fluid">
                                    <?php
                                        include(realpath(__DIR__ . '/partials/score.php'));
                                    ?>
                                </div>

                                <!-- Show visualization of risk score -->
                                <div class="row-fluid">
                                    <?php
                                        include(realpath(__DIR__ . '/partials/score-overtime.php'));
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="content-container">
                    <?php 
                        include(realpath(__DIR__ . '/partials/changestatus.php'));
                    ?>
                </div>
            </div>
        </div>
      </div>
    </div>
  </body>
</html>
