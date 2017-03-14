<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/epiphany/src/Epi.php'));
    require_once(realpath(__DIR__ . '/../includes/api.php'));

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

    // Check for session timeout or renegotiation
    session_check();
    
    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
      header("Location: ../index.php");
      exit(0);
    }
    
    $view_options = array(
        'url' => '',
        'method' => '',
        'params' => '',
        'response' => '',
    );
    if(!empty($_GET['option']) && function_exists("mock_".$_GET['option'])){
        call_user_func_array("mock_".$_GET['option'], array(&$view_options));
    }else{
        echo "Invalid API";
        die();
    }
    
    function formatJsonString($json){
        return json_encode(json_decode($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
    * Show add risk api infos
    * 
    */
    function mock_add_risk(&$results){
        $results = array();
        $results['url'] = "/management/risk/add";
        $results['method'] = "POST";
        $results['params'] = '{
          "subject": "Subject",
          "category": "1",
          "location": "6",
          "reference_id": "",
          "regulation": "3",
          "control_number": "",
          "assets": "credit card data, google-public-dns-a.google.com, ",
          "technology": "8",
          "team": "3",
          "owner": "16",
          "manager": "15",
          "source": "4",
          "scoring_method": "1",
          "likelihood": "2",
          "impact": "2",
          "AccessVector": "N",
          "AccessComplexity": "L",
          "Authentication": "N",
          "ConfImpact": "C",
          "IntegImpact": "C",
          "AvailImpact": "C",
          "Exploitability": "ND",
          "RemediationLevel": "ND",
          "ReportConfidence": "ND",
          "CollateralDamagePotential": "ND",
          "TargetDistribution": "ND",
          "ConfidentialityRequirement": "ND",
          "IntegrityRequirement": "ND",
          "AvailabilityRequirement": "ND",
          "DREADDamage": "10",
          "DREADReproducibility": "10",
          "DREADExploitability": "10",
          "DREADAffectedUsers": "10",
          "DREADDiscoverability": "10",
          "OWASPSkillLevel": "10",
          "OWASPMotive": "10",
          "OWASPOpportunity": "10",
          "OWASPSize": "10",
          "OWASPEaseOfDiscovery": "10",
          "OWASPEaseOfExploit": "10",
          "OWASPAwareness": "10",
          "OWASPIntrusionDetection": "10",
          "OWASPLossOfConfidentiality": "10",
          "OWASPLossOfIntegrity": "10",
          "OWASPLossOfAvailability": "10",
          "OWASPLossOfAccountability": "10",
          "OWASPFinancialDamage": "10",
          "OWASPReputationDamage": "10",
          "OWASPNonCompliance": "10",
          "OWASPPrivacyViolation": "10",
          "Custom": "",
          "assessment": "Assessment",
          "notes": "Additional notes"
        }';
        $results['response'] = '{
              "status": 200,
              "status_message": "Risk ID 3143 submitted successfully!",
              "data": {
                "risk_id": 3143
              }
            }';
            
        return;
    }
    
    function mock_save_mitigation(&$results){
        $results = array();
        $results['url'] = "/management/mitigation/add";
        $results['method'] = "POST";
        $results['params'] = '{
          "id": 2280,
          "planning_date": "03/02/2017",
          "tab_type": "1",
          "planning_strategy": "2",
          "mitigation_effort": "2",
          "mitigation_cost": "2",
          "mitigation_owner": "17",
          "mitigation_team": "2",
          "current_solution": "Current solution",
          "security_requirements": "Requirements",
          "security_recommendations": "Recommends"
        }';
        
        $results['response'] = '{
          "status": 200,
          "status_message": "Success",
          "data": {
            "risk_id": "2280",
            "mitigation_id": "916"
          }
        }';
        
        return;
    }
    
    function mock_save_review(&$results){
        $results = array();
        $results['url'] = "/management/review/add";
        $results['method'] = "POST";
        $results['params'] = '{
          "id": "2280",
          "review": "1",
          "next_step": "1",
          "comments": "This is a comment",
          "custom_date": "no",
          "next_review": "06/11/2017"
        }';
        
        $results['response'] = '{
          "status": 200,
          "status_message": "Success",
          "data": {
            "risk_id": "2280",
            "mitigation_id": "916"
          }
        }';
        
        
        return;
    }
?>

<!doctype html>
<html>

<head>
  <script src="../js/bootstrap.min.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  
  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">
</head>

<body>

    <br><br>
    <div class="container">
        <div class="row-fluid">
            <div class="span2">
                <label>URL:</label>
            </div>
            <div class="span10">
                <?php echo $view_options['url']; ?>
            </div>
        </div>
        <div class="row-fluid">
            <div class="span2">
                <label>Method:</label>
            </div>
            <div class="span10">
                <?php echo $view_options['method']; ?>
            </div>
        </div>
        <div class="row-fluid">
            <div class="span2">
                <label>Request params:</label>
            </div>
            <div class="span10">
                <textarea style="width:100%; min-height: 300px"><?php echo formatJsonString($view_options['params']); ?></textarea>
            </div>
        </div>
        <div class="row-fluid">
            <div class="span2">
                <label>Response:</label>
            </div>
            <div class="span10">
                <textarea style="width:100%; min-height: 200px"><?php echo formatJsonString($view_options['response']); ?></textarea>
            </div>
        </div>
    </div>
</body>

</html>

