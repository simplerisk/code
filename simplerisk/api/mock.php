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
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRisk');
        session_start();
    }

    // Check for session timeout or renegotiation
    session_check();
    
    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
      set_unauthenticated_redirect();
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
        if(!json_decode($json)){
            return $json;
        }
        $result = json_encode(json_decode($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $result;
    }
    
    /**
    * Show add risk api infos
    * 
    */
    function mock_add_risk(&$results){
        $results = array();
        $results['url'] = "/api/management/risk/add?key={key}";
        $results['method'] = "POST";
        $results['params'] = '{
          "subject": "Subject",
          "category": "1",
          "location": "6",
          "reference_id": "",
          "regulation": "3",
          "control_number": "",
          "affected_assets": "Asset Name 1,Asset Name 2,[Asset Group Name 1],Asset Name 3,[Asset Group Name 2]",
          "technology": "8",
          "team": [3],
          "additional_stakeholders": [1,17,16,15],
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
          "notes": "Additional notes",
          "tags": [Tag1,Tag2,Tag3],
          "custom_field": {
              "21":"Custom first",
              "41":"Custom value"
          },
          "ContributingLikelihood": "2",
          "ContributingImpacts": {
              "5": "2",
              "7": "3"
          }
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
        $results['url'] = "/api/management/mitigation/add?key={key}";
        $results['method'] = "POST";
        $results['params'] = '{
          "id": 2280,
          "planning_date": "03/02/2017",
          "tab_type": "1",
          "planning_strategy": "2",
          "mitigation_effort": "2",
          "mitigation_cost": "2",
          "mitigation_owner": "17",
          "mitigation_team": "2,3",
          "current_solution": "Current solution",
          "security_requirements": "Requirements",
          "security_recommendations": "Recommends",
          "mitigation_percent": "33",
          "custom_field": {
              "21": "Custom first",
              "41": "Custom value"
          }
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
        $results['url'] = "/api/management/review/add?key={key}";
        $results['method'] = "POST";
        $results['params'] = '{
          "id": "2280",
          "review": "1",
          "next_step": "1",
          "comments": "This is a comment",
          "custom_date": "no",
          "next_review": "06/11/2017",
          "custom_field": {
            "43": "3"
          }
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
    
    function mock_get_version(&$results){
        $results = array();
        $results['url'] = "/api/version?key={key}";
        $results['method'] = "GET";
        $results['params'] = '';
        
        $results['response'] = 'The version of this api is: 1.1';
        
        return;
    }
    function mock_get_whoami(&$results){
        $results = array();
        $results['url'] = "/api/whoami?key={key}";
        $results['method'] = "GET";
        $results['params'] = '';
        
        $results['response'] = '{
            "status": 200,
            "status_message": "whoami",
            "data": {
                "username": "admin",
                "uid": "1"
            }
        }';
        
        return;
    }
    
    function mock_get_scoring_history(&$results){
        $results = array();
        $results['url'] = "/api/management/risk/scoring_history?key={key}&id={risk_id}";
        $results['method'] = "GET";
        $results['params'] = 'id';

        $results['response'] = '{
            "status": 200,
            "status_message": "scoring_history",
            "data": [{
                "risk_id": "1001",
		            "calculated_risk": "10",
		            "last_update": "2017-03-05 17:55:57"
            }]
        }';

        return;
    }
    
    function mock_get_risk_view(&$results){
        $results = array();
        $results['url'] = "/api/management/risk/view?key={key}&id=2281";
        $results['method'] = "GET";
        $results['params'] = '';
        
        $results['response'] = '{
            "status": 200,
            "status_message": "viewrisk",
            "data": [
                {
                    "id": 1031,
                    "status": "New",
                    "subject": "test",
                    "reference_id": "",
                    "regulation": "",
                    "control_number": "",
                    "location": "",
                    "source": "",
                    "category": "",
                    "team": "Branch Management",
                    "technology": "",
                    "owner": "",
                    "manager": "",
                    "assessment": "",
                    "notes": "",
                    "affected_assets": [
                        {
                            "name": "Asset Group 1",
                            "type": "group"
                        },
                        {
                            "name": "Asset Group 2",
                            "type": "group"
                        },
                        {
                            "name": "Asset 1",
                            "type": "asset"
                        },
                        {
                            "name": "Asset 2",
                            "type": "asset"
                        }
                    ],
                    "submission_date": "2018-06-06 11:01:20",
                    "mitigation_id": "0",
                    "mgmt_review": "0",
                    "calculated_risk": "10",
                    "next_review": null,
                    "color": "#ff0505",
                    "scoring_method": "Custom",
                    "CLASSIC_likelihood": "Almost Certain",
                    "CLASSIC_impact": "Extreme/Catastrophic",
                    "CVSS_AccessVector": "N",
                    "CVSS_AccessComplexity": "L",
                    "CVSS_Authentication": "N",
                    "CVSS_ConfImpact": "C",
                    "CVSS_IntegImpact": "C",
                    "CVSS_AvailImpact": "C",
                    "CVSS_Exploitability": "ND",
                    "CVSS_RemediationLevel": "ND",
                    "CVSS_ReportConfidence": "ND",
                    "CVSS_CollateralDamagePotential": "ND",
                    "CVSS_TargetDistribution": "ND",
                    "CVSS_ConfidentialityRequirement": "ND",
                    "CVSS_IntegrityRequirement": "ND",
                    "CVSS_AvailabilityRequirement": "ND",
                    "DREAD_DamagePotential": "10",
                    "DREAD_Reproducibility": "10",
                    "DREAD_Exploitability": "10",
                    "DREAD_AffectedUsers": "10",
                    "DREAD_Discoverability": "10",
                    "OWASP_SkillLevel": "10",
                    "OWASP_Motive": "10",
                    "OWASP_Opportunity": "10",
                    "OWASP_Size": "10",
                    "OWASP_EaseOfDiscovery": "10",
                    "OWASP_EaseOfExploit": "10",
                    "OWASP_Awareness": "10",
                    "OWASP_IntrusionDetection": "10",
                    "OWASP_LossOfConfidentiality": "10",
                    "OWASP_LossOfIntegrity": "10",
                    "OWASP_LossOfAvailability": "10",
                    "OWASP_LossOfAccountability": "10",
                    "OWASP_FinancialDamage": "10",
                    "OWASP_ReputationDamage": "10",
                    "OWASP_NonCompliance": "10",
                    "OWASP_PrivacyViolation": "10",
                    "tags": "Tag1,Tag2,Tag3",
                    "Custom": "10",
                    "ContributingLikelihood": "2",
                    "ContributingImpacts": {
                        "5": "2",
                        "7": "3"
                    },
                    "closure_date": "2015-09-15 06:26:47",
                    "custom_values": [
                        {
                            "field_id": "41",
                            "value": "1",
                            "review_id": "0",
                            "field_name": "test",
                            "field_type": "longtext"
                        }
                    ]
                }
            ]
        }';
        
        return;
    }
    
    function mock_get_mitigation_view(&$results){
        $results = array();
        $results['url'] = "/api/management/mitigation/view?key={key}&id=2281";
        $results['method'] = "GET";
        $results['params'] = '';
        
        $results['response'] = '{
          "status": 200,
          "status_message": "Mitigation View",
          "data": {
            "submission_date": "2017-04-01 13:48:31",
            "planning_date": "2017-04-05",
            "planning_strategy": "2",
            "planning_strategy_name": "Accept",
            "mitigation_effort": "1",
            "mitigation_effort_name": "Trivial",
            "mitigation_cost": "2",
            "mitigation_min_cost": "100001",
            "mitigation_max_cost": "200000",
            "mitigation_owner": "17",
            "mitigation_owner_name": "Demo Director",
            "mitigation_team": "2,3",
            "mitigation_team_name": "IT Systems Management, Collaboration",
            "current_solution": "Current solution",
            "security_requirements": "System requirements",
            "security_recommendations": "Security recommendations",
            "submitted_by": "1",
            "submitted_by_name": "Admin",
            "supporting_files": [
              "http://demo.simplerisk.com/management/download.php?id=w7rtvQ1nmtOsPf0pGLSby5pqQ9ouAZ",
              "http://demo.simplerisk.com/management/download.php?id=IcyuIqRRoaG3ukaSk8IuVwc1HUGrgn"
            ],
            "custom_values": [
                {
                    "field_id": "21",
                    "value": "Custom first",
                    "review_id": "0",
                    "field_name": "PlanningStrategy",
                    "field_type": "",
                    "tab_index": "2"
                },
                {
                    "field_id": "41",
                    "value": "Custom value",
                    "review_id": "0",
                    "field_name": "test",
                    "field_type": "longtext",
                    "tab_index": "2"
                }
            ]
          }
        }';
        
        return;
    }
    
    function mock_get_review_view(&$results){
        $results = array();
        $results['url'] = "/api/management/review/view?key={key}&id=2281";
        $results['method'] = "GET";
        $results['params'] = '';

        $results['response'] = '{
          "status": 200,
          "status_message": "Review View",
          "data": {
            "submission_date": "2017-04-02 03:52:49",
            "reviewer": "1",
            "review": "1",
            "next_step": "1",
            "next_review": "2017-06-30",
            "comments": "This is a comment.",
            "custom_values": [
                {
                    "field_id": "43",
                    "value": "3",
                    "review_id": "30",
                    "field_name": "Review_test",
                    "field_type": "dropdown",
                    "tab_index": "3"
                }
            ]
          }
        }';
        
        return;
    }
    
    function mock_get_risk_levels(&$results){
        $results = array();
        $results['url'] = "/api/risk_levels?key={key}";
        $results['method'] = "GET";
        $results['params'] = '';

        $results['response'] = '{
          "status": 200,
          "status_message": "Success",
          "data": {
            "risk_levels": [
              {
                "value": "1.0",
                "name": "Low",
                "color": "#003cff"
              },
              {
                "value": "4.0",
                "name": "Medium",
                "color": "#30d156"
              },
              {
                "value": "7.0",
                "name": "High",
                "color": "#2ee5e8"
              },
              {
                "value": "9.0",
                "name": "Very High",
                "color": "#ff0000"
              }
            ]
          }
        }';
        
        return;
    }
    
    function mock_update_risk(&$results){
        $results = array();
        $results['url'] = "/api/management/risk/update?key={key}";
        $results['method'] = "POST";
        $results['params'] = '{
          "id": "2287",
          "subject": "blabla",
          "category": "1",
          "location": "6",
          "reference_id": "",
          "regulation": "3",
          "control_number": "",
          "affected_assets": "Asset Name 1,Asset Name 2,[Asset Group Name 1],Asset Name 3,[Asset Group Name 2]",
          "technology": "8",
          "team": "3",
          "additional_stakeholders": "1,17,16,15",
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
          "notes": "Additional notes",
          "tags": [Tag1, Tag2, Tag3],
          "custom_field": {
              "21":"Custom first",
              "41":"Custom value"
          },
          "ContributingLikelihood": "2",
          "ContributingImpacts": {
              "5": "2",
              "7": "3"
          }
        }';

        $results['response'] = '{
          "status": 200,
          "status_message": "Risk ID 2287 updated successfully!",
          "data": null
        }';
        
        return;
    }

    function mock_get_audit_logs(&$results){
        $results = array();
        $results['url'] = "/api/audit_logs?key={key}&days=7&log_type=risk, contact";
        $results['method'] = "GET";
        $results['params'] = '';

        $results['response'] = '{
          "status": 200,
          "status_message": "Success",
          "data":  [
            {
              "timestamp": "29/03/2019 2:26 AM CET",
              "username": "Admin",
              "message": "A management review was submitted for risk ID \"1088\" by username \"admin\"."
            },
            {
              "timestamp": "29/03/2019 2:26 AM CET",
              "username": "Admin",
              "message": "Risk(ID:1088) was assigned to project \"test project\" by user \"admin\"."
            }
          ]
        }';
        
        return;
    }
?>

<!doctype html>
<html>

<head>
  <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
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
                <label>Header:</label>
            </div>
            <div class="span10">
                <strong>Content-Type</strong>: <span>application/x-www-form-urlencoded</span>
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
        <?php if($view_options['method'] != "GET"){ ?>
        <div class="row-fluid">
            <div class="span2">
                <label>Request params:</label>
            </div>
            <div class="span10">
                <textarea style="width:100%; min-height: 300px"><?php echo formatJsonString($view_options['params']); ?></textarea>
            </div>
        </div>
        <?php } ?>
        <div class="row-fluid">
            <div class="span2">
                <label>Response:</label>
            </div>
            <div class="span10">
                <textarea style="width:100%; min-height: 300px"><?php echo formatJsonString($view_options['response']); ?></textarea>
            </div>
        </div>
    </div>
</body>

</html>

