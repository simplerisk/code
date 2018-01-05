<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2014 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * create custom risk assessment questionnaires.                    *
 ********************************************************************/

// Extra Version
define('ASSESSMENTS_EXTRA_VERSION', '20180104-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/services.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));

require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_assessment_extra_database();

/**************************************
 * FUNCTION: ENABLE ASSESSMENTS EXTRA *
 **************************************/
function enable_assessments_extra()
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'assessments', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Add default values
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ASSESSMENT_MINUTES_VALID', `value` = '1440'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE', `value` = '0'");
    $stmt->execute();

    // Create the table to track sent assessments
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `assessment_tracking` (`id` int(11) NOT NULL AUTO_INCREMENT, `assessment_id` int(11) NOT NULL, `email` varchar(200) NOT NULL, `sender` int(11) NOT NULL, `key` varchar(20) NOT NULL, `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id)) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: DISABLE ASSESSMENTS EXTRA *
 ***************************************/
function disable_assessments_extra()
{
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'assessments'");
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/*********************************
 * FUNCTION: ASSESSMENTS VERSION *
 *********************************/
function assessments_version()
{
    // Return the version
    return ASSESSMENTS_EXTRA_VERSION;
}

/***************************************
 * FUNCTION: GET ASSESSMENT SETTINGS *
 ***************************************/
function get_assessment_settings()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `settings` WHERE `name` = 'ASSESSMENT_MINUTES_VALID' or `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE';");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/*****************************
 * FUNCTION: UPDATE SETTINGS *
 *****************************/
if (!function_exists('update_settings')){
    function update_settings($configs)
    {
        // Open the database connection
        $db = db_open();

        // If ASSESSMENT_MINUTES_VALID is an integer
        if (is_numeric($configs['ASSESSMENT_MINUTES_VALID']))
        {
            // Update ASSESSMENT_MINUTES_VALID
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'ASSESSMENT_MINUTES_VALID'");
            $stmt->bindParam(":value", $configs['ASSESSMENT_MINUTES_VALID']);
            $stmt->execute();
        }

        // If ASSESSMENT_ASSET_SHOW_AVAILABLE is set
        if (isset($configs['ASSESSMENT_ASSET_SHOW_AVAILABLE']))
        {
            // Update ASSESSMENT_ASSET_SHOW_AVAILABLE
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE'");
            $stmt->bindParam(":value", $configs['ASSESSMENT_ASSET_SHOW_AVAILABLE']);
            $stmt->execute();
        }

        // Close the database connection
        db_close($db);

        // Return true;
        return true;
    }
}

/**************************************
 * FUNCTION: UPDATE ASSESSMENT CONFIG *
 **************************************/
function update_assessment_config()
{
    $configs['ASSESSMENT_MINUTES_VALID'] = $_POST['assessment_minutes_valid'];
    $configs['ASSESSMENT_ASSET_SHOW_AVAILABLE'] = isset($_POST['assessment_asset_show_available']) ? 1 : 0;

    // Update the settings
    update_settings($configs);
}

/*********************************
 * FUNCTION: DISPLAY ASSESSMENTS *
 *********************************/
function display_assessments()
{
    global $escaper;
    global $lang;

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . assessments_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

    // Get the assessment settings
    $configs = get_assessment_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    echo "<br /><br />\n";
    echo "<form name=\"assessment_extra\" method=\"post\" action=\"\">\n";
    echo "<table>\n";
    echo "<tr>\n";
    echo "<tr>\n";
    echo "<td>".$escaper->escapeHtml($lang['MinutesAssessmentsAreValid']).":&nbsp;</td>\n";
    echo "<td><input type=\"text\" name=\"assessment_minutes_valid\" id=\"assessment_minutes_valid\" value=\"" . $ASSESSMENT_MINUTES_VALID . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td>".$escaper->escapeHtml($lang['ShowAvailableAssetsOnAssessments']).":&nbsp;</td>\n";
    echo "<td><input type=\"checkbox\" ". ((isset($ASSESSMENT_ASSET_SHOW_AVAILABLE) && $ASSESSMENT_ASSET_SHOW_AVAILABLE) ? "checked" : "") ." name=\"assessment_asset_show_available\" id=\"assessment_asset_show_available\" value=\"1\" /></td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Submit']) . "</button>\n";
    echo "</div>\n";
    echo "</form>\n";
}

/*****************************************
 * FUNCTION: VIEW ASSESSMENTS EXTRA MENU *
 *****************************************/
function view_assessments_extra_menu($active)
{
    global $lang;
    global $escaper;

    echo ($active == "CreateAssessment" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php?action=create\"> " . $escaper->escapeHtml($lang['CreateAssessment']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "EditAssessment" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php?action=edit\"> " . $escaper->escapeHtml($lang['EditAssessment']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "SendAssessment" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php?action=send\"> " . $escaper->escapeHtml($lang['SendAssessment']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "AssessmentImportexport" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"importexport.php\"> " . $escaper->escapeHtml($lang['Import']."/".$lang['Export']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "AssessmentContacts" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"contacts.php\"> " . $escaper->escapeHtml($lang['AssessmentContacts']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireQuestions" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_questions.php?action=questions_list\"> " . $escaper->escapeHtml($lang['QuestionnaireQuestions']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireTemplates" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_templates.php?action=template_list\"> " . $escaper->escapeHtml($lang['QuestionnaireTemplates']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "Questionnaires" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaires.php?action=list\"> " . $escaper->escapeHtml($lang['Questionnaires']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireResults" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_results.php\"> " . $escaper->escapeHtml($lang['QuestionnaireResults']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireTrail" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_trail.php\"> " . $escaper->escapeHtml($lang['QuestionnaireAuditTrail']) . "</a>\n";
    echo "</li>\n";
}

/****************************************
 * FUNCTION: DISPLAY CREATE ASSESSMENTS *
 ****************************************/
function display_create_assessments()
{
    global $lang;
    global $escaper;

    // If the create assessment was not posted
    if (!isset($_POST['create_assessment']))
    {
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"assessment_name\" method=\"post\" action=\"\">\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"create\" />\n";
        echo "<p>Please give your assessment a name:</p>\n";
        echo "<p>Name:&nbsp;&nbsp;<input type=\"text\" name=\"assessment_name\" placeholder=\"Assessment Name\" />&nbsp;&nbsp;<input type=\"submit\" name=\"create_assessment\" value=\"" . $escaper->escapeHtml($lang['Next']) . "\" /></p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
    }
    else if (isset($_POST['assessment_name']))
    {
        // Set the assessment name
        $assessment_name = $_POST['assessment_name'];

        // If the assessment id was posted
        if (isset($_POST['assessment_id']))
        {
            // Set the id to the assessment id that was posted
            $id = (int)$_POST['assessment_id'];
        }
        // Otherwise, this is a new assessment
        else
        {
            // Create the assessment
            $id = (int)create_assessment($assessment_name);

            // Redirect to the edit page
            header("Location: index.php?action=edit&assessment_id=" . $id);
        }
    }
}

/*************************************
 * FUNCTION: DISPLAY RISK SCORE HTML *
 *************************************/
function display_risk_score($return, $scoring_method="5", $custom="10", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10"){
    global $lang;
    global $escaper;
    
    // If return HTML is required.
    if($return)
        ob_start();
    

    echo "<tr class=\"text-center\">\n";
    echo "<td >&nbsp;</td>";
    echo "<td><strong>".$escaper->escapeHtml($lang['RiskScore']).":</strong></td>\n";
    echo "<td colspan=\"3\" align=\"left\">\n";
        echo "<table class=\"risk-scoring-container\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">\n";
        echo "<tr>\n";
        echo "<td>\n";
            print_score_html_from_assessment($scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
    echo "</td>\n";
    echo "</tr>\n";
    
    // Return HTML 
    if($return){
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }
}

/*****************************************
 * FUNCTION: GET ASSESSMENT SCORES ARRAY *
 *****************************************/
function get_assessment_scores_array(){
    $results = array();
    if(is_array($_POST['scoring_method'])){
        foreach($_POST['scoring_method'] as $key => $scoring_method){
            $results[$key] = array(
                'scoring_method' => $_POST['scoring_method'][$key],

                // Classic Risk Scoring Inputs
                'CLASSIClikelihood' => $_POST['likelihood'][$key],
                'CLASSICimpact' =>  $_POST['impact'][$key],

                // CVSS Risk Scoring Inputs
                'CVSSAccessVector' => $_POST['AccessVector'][$key],
                'CVSSAccessComplexity' => $_POST['AccessComplexity'][$key],
                'CVSSAuthentication' => $_POST['Authentication'][$key],
                'CVSSConfImpact' => $_POST['ConfImpact'][$key],
                'CVSSIntegImpact' => $_POST['IntegImpact'][$key],
                'CVSSAvailImpact' => $_POST['AvailImpact'][$key],
                'CVSSExploitability' => $_POST['Exploitability'][$key],
                'CVSSRemediationLevel' => $_POST['RemediationLevel'][$key],
                'CVSSReportConfidence' => $_POST['ReportConfidence'][$key],
                'CVSSCollateralDamagePotential' => $_POST['CollateralDamagePotential'][$key],
                'CVSSTargetDistribution' => $_POST['TargetDistribution'][$key],
                'CVSSConfidentialityRequirement' => $_POST['ConfidentialityRequirement'][$key],
                'CVSSIntegrityRequirement' => $_POST['IntegrityRequirement'][$key],
                'CVSSAvailabilityRequirement' => $_POST['AvailabilityRequirement'][$key],
                // DREAD Risk Scoring Inputs
                'DREADDamage' => $_POST['DREADDamage'][$key],
                'DREADReproducibility' => $_POST['DREADReproducibility'][$key],
                'DREADExploitability' => $_POST['DREADExploitability'][$key],
                'DREADAffectedUsers' => $_POST['DREADAffectedUsers'][$key],
                'DREADDiscoverability' => $_POST['DREADDiscoverability'][$key],
                // OWASP Risk Scoring Inputs
                'OWASPSkillLevel' => $_POST['OWASPSkillLevel'][$key],
                'OWASPMotive' => $_POST['OWASPMotive'][$key],
                'OWASPOpportunity' => $_POST['OWASPOpportunity'][$key],
                'OWASPSize' => $_POST['OWASPSize'][$key],
                'OWASPEaseOfDiscovery' => $_POST['OWASPEaseOfDiscovery'][$key],
                'OWASPEaseOfExploit' => $_POST['OWASPEaseOfExploit'][$key],
                'OWASPAwareness' => $_POST['OWASPAwareness'][$key],
                'OWASPIntrusionDetection' => $_POST['OWASPIntrusionDetection'][$key],
                'OWASPLossOfConfidentiality' => $_POST['OWASPLossOfConfidentiality'][$key],
                'OWASPLossOfIntegrity' => $_POST['OWASPLossOfIntegrity'][$key],
                'OWASPLossOfAvailability' => $_POST['OWASPLossOfAvailability'][$key],
                'OWASPLossOfAccountability' => $_POST['OWASPLossOfAccountability'][$key],
                'OWASPFinancialDamage' => $_POST['OWASPFinancialDamage'][$key],
                'OWASPReputationDamage' => $_POST['OWASPReputationDamage'][$key],
                'OWASPNonCompliance' => $_POST['OWASPNonCompliance'][$key],
                'OWASPPrivacyViolation' => $_POST['OWASPPrivacyViolation'][$key],

                // Custom Risk Scoring
                'Custom' => $_POST['Custom'][$key],
                
            );
        }
    }
    return $results;
}

/****************************
 * FUNCTION: GET ASSESSMENT *
 ****************************/
function get_assessment_with_scoring($assessment_id)
{
        // Open the database connection
        $db = db_open();

        // Get the assessment questions and answers
        $stmt = $db->prepare("SELECT a.name AS assessment_name, b.question, b.id AS question_id, b.order AS question_order, c.answer, c.id AS answer_id, c.submit_risk, c.risk_subject, c.risk_score, c.risk_owner, c.assets, c.order AS answer_order, 
        d.id assessment_scoring_id, d.scoring_method, d.calculated_risk, d.CLASSIC_likelihood, d.CLASSIC_impact, d.CVSS_AccessVector, d.CVSS_AccessComplexity, d.CVSS_Authentication, d.CVSS_ConfImpact, d.CVSS_IntegImpact, d.CVSS_AvailImpact, d.CVSS_Exploitability, d.CVSS_RemediationLevel, d.CVSS_ReportConfidence, d.CVSS_CollateralDamagePotential, d.CVSS_TargetDistribution, d.CVSS_ConfidentialityRequirement, d.CVSS_IntegrityRequirement, d.CVSS_AvailabilityRequirement, d.DREAD_DamagePotential, d.DREAD_Reproducibility, d.DREAD_Exploitability, d.DREAD_AffectedUsers, d.DREAD_Discoverability, d.OWASP_SkillLevel, d.OWASP_Motive, d.OWASP_Opportunity, d.OWASP_Size, d.OWASP_EaseOfDiscovery, d.OWASP_EaseOfExploit, d.OWASP_Awareness, d.OWASP_IntrusionDetection, d.OWASP_LossOfConfidentiality, d.OWASP_LossOfIntegrity, d.OWASP_LossOfAvailability, d.OWASP_LossOfAccountability, d.OWASP_FinancialDamage, d.OWASP_ReputationDamage, d.OWASP_NonCompliance, d.OWASP_PrivacyViolation, d.Custom
        FROM `assessments` a LEFT JOIN `assessment_questions` b ON a.id=b.assessment_id JOIN `assessment_answers` c ON b.id=c.question_id 
            LEFT JOIN `assessment_scoring` d ON c.assessment_scoring_id=d.id
        WHERE a.id=:assessment_id ORDER BY question_order, b.id, answer_order, c.id;");
        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Return the assessment
    return $array;
}

/**************************************
 * FUNCTION: DISPLAY EDIT ASSESSMENTS *
 **************************************/
function display_edit_assessments()
{
    global $lang;
    global $escaper;

    // If no assessment id is specified
    if (!(isset($_GET['assessment_id']) || isset($_POST['assessment_id'])))
    {
        // Get the assessment names
        $assessments = get_assessment_names();

        echo "<ul class=\"nav nav-pills nav-stacked\">\n";

        // For each entry in the assessments array
        foreach ($assessments as $assessment)
        {
            // Display the assessment
            echo "<li style=\"text-align:center\"><a href=\"index.php?action=edit&assessment_id=" . $escaper->escapeHtml($assessment['id']) . "\">" . $escaper->escapeHtml($assessment['name']) . "</a></li>\n";
        }

        echo "</ul>\n";
    }
    // Otherwise
    else
    {
        // If the assessment id is in a GET request
        if (isset($_GET['assessment_id']))
        {
            // Set the assessment id value to the GET value
            $assessment_id = (int)$_GET['assessment_id'];
        }
        // If the assessment id is in a POST request
        else if (isset($_POST['assessment_id']))
        {
            // Set the assessment id value to the POST value
            $assessment_id = (int)$_POST['assessment_id'];
        }

        // If the delete assessment was submitted
        if (isset($_POST['delete_assessment']))
        {
            // Delete the assessment
            delete_assessment($assessment_id);

            // Set the alert
            set_alert(true, "good", "The assessment was deleted successfully.");

            // Redirect to the edit page
            header("Location: index.php?action=edit");
        }
        // If a new question was submitted
        else if (isset($_POST['add']))
        {
            // Get the posted values
            $question = isset($_POST['question']) ? $_POST['question'] : "";
            $answer = isset($_POST['answer']) ? $_POST['answer'] : "";
            $submit_risk = isset($_POST['submit_risk']) ? $_POST['submit_risk'] : null;
            $risk_subject = isset($_POST['risk_subject']) ? $_POST['risk_subject'] : "";
//            $risk_score = isset($_POST['risk_score']) ? $_POST['risk_score'] : 0;
            $risk_owner = isset($_POST['risk_owner']) ? $_POST['risk_owner'] : null;
            $assets = isset($_POST['assets']) ? $_POST['assets'] : "";
            
            // Get assessment score values as an array
            $assessment_scores = get_assessment_scores_array();
            
            // Add assessment risk score
            $assessment_scoring_ids = [];
            $risk_score = [];
            foreach($assessment_scores as $key => $assessment_score){
                list($assessment_scoring_ids[$key], $risk_score[$key]) = add_assessment_scoring($assessment_score);
            }

            // Add the question
            add_assessment_question($assessment_id, $question, $answer, $submit_risk, $risk_subject, $risk_score, $risk_owner, $assets, $assessment_scoring_ids);
        }

        // Get the assessment with that id
        $assessment = get_assessment_names($assessment_id);
        $assessment_name = $assessment['name'];

        // Script to display assets
        display_asset_autocomplete_script(get_entered_assets());

        // Add a question
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";

        echo "<table id=\"adding_row\" class=\"hide\">\n";
            echo "<tr>\n";
            echo "<td><input type=\"text\" name=\"answer[]\" size=\"200\" value=\"Yes\" placeholder=\"Answer\" /></td>\n";
            echo "<td align=\"middle\"><input type=\"checkbox\" name=\"submit_risk[]\" value=\"0\" checked /></td>\n";
            echo "<td><input type=\"text\" name=\"risk_subject[]\" size=\"200\" placeholder=\"Enter Risk Subject\" /></td>\n";
            echo "<td>\n";
            echo create_dropdown("user", NULL, "risk_owner[]");
            echo "</td>\n";
            echo "<td><div class=\"ui-widget\"><input type=\"text\" id=\"assets\" name=\"assets[]\" /></div></td>\n";
            echo "</tr>\n";
            display_risk_score(false, 5, 10);
        echo "</table>";

        echo "<form name=\"assessment_question\" method=\"post\" action=\"\">\n";
        echo "<h4>" . $escaper->escapeHtml($lang['NewAssessmentQuestion']) . "<button name=\"delete_assessment\" value=\"\" style=\"float: right;\">" . $escaper->escapeHtml($lang['DeleteAssessment']) . "</button><div class=\"clearfix\"></div></h4>\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"edit\" />\n";
        echo "<input type=\"hidden\" name=\"create_assessment\" value=\"true\" />\n";
        echo "<input type=\"hidden\" name=\"assessment_name\" value=\"" . $escaper->escapeHtml($assessment_name) . "\" />\n";
        echo "<input type=\"hidden\" name=\"assessment_id\" value=\"" . $escaper->escapeHtml($assessment_id) . "\" />\n";
        echo "<table name=\"question\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">\n";
        echo "<tr>\n";
        echo "<td>" . $escaper->escapeHtml($lang['Question']) . ":&nbsp;&nbsp;</td>\n";
        echo "<td width=\"100%\"><input type=\"text\" style=\"width: 99%;\" name=\"question\" placeholder=\"Enter Question Here\" /></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "<table id=\"dataTable\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">\n";
        echo "<tr>\n";
        echo "<th width=\"10%\">" . $escaper->escapeHtml($lang['Answer']) . "</th>\n";
        echo "<th width=\"10%\" align=\"middle\">" . $escaper->escapeHtml($lang['SubmitRisk']) . "</th>\n";
        echo "<th width=\"40%\" align=\"middle\">" . $escaper->escapeHtml($lang['Subject']) . "</th>\n";
//        echo "<th align=\"middle\">" . $escaper->escapeHtml($lang['RiskScore']) . "</th>\n";
        echo "<th width=\"10%\" align=\"middle\">" . $escaper->escapeHtml($lang['Owner']) . "</th>\n";
        echo "<th width=\"30%\" align=\"middle\">" . $escaper->escapeHtml($lang['AffectedAssets']) . "</th>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td><input type=\"text\" name=\"answer[]\" size=\"200\" value=\"Yes\" placeholder=\"Answer\" /></td>\n";
        echo "<td align=\"middle\"><input type=\"checkbox\" name=\"submit_risk[]\" value=\"0\" checked /></td>\n";
        echo "<td><input type=\"text\" name=\"risk_subject[]\" size=\"200\" placeholder=\"Enter Risk Subject\" /></td>\n";
//        echo "<td><input type=\"number\" min=\"0\" max=\"10\" name=\"risk_score[]\" value=\"10\" step=\"0.1\"/></td>\n";
        echo "<td>\n";
        echo create_dropdown("user", NULL, "risk_owner[]");
        echo "</td>\n";
        echo "<td><div class=\"ui-widget\"><input type=\"text\" id=\"assets\" name=\"assets[]\" /></div></td>\n";
        echo "</tr>\n";
        
        display_risk_score(false, 5, 10);

        echo "<tr>\n";
        echo "<td><input type=\"text\" name=\"answer[]\" size=\"200\" value=\"No\" placeholder=\"Answer\" /></td>\n";
        echo "<td align=\"middle\"><input type=\"checkbox\" name=\"submit_risk[]\" value=\"1\" /></td>\n";
        echo "<td><input type=\"text\" name=\"risk_subject[]\" size=\"200\" placeholder=\"Enter Risk Subject\" /></td>\n";
//        echo "<td><input type=\"number\" min=\"0\" max=\"10\" name=\"risk_score[]\" value=\"0\" step=\"0.1\" /></td>\n";
        echo "<td>\n";
        echo create_dropdown("user", NULL, "risk_owner[]");
        echo "</td>\n";
        echo "<td><div class=\"ui-widget\"><input type=\"text\" id=\"assets\" name=\"assets[]\" /></div></td>\n";
        echo "</tr>\n";

        display_risk_score(false, 5, 0);

        echo "</table>\n";
        echo "<img src=\"../images/plus.png\" onclick=\"addRow('dataTable')\" width=\"15px\" height=\"15px\" />&nbsp;&nbsp;<img src=\"../images/minus.png\" onclick=\"deleteRow('dataTable')\" width=\"15px\" height=\"15px\" /><br />\n";
        echo "<input type=\"submit\" name=\"add\" value=\"" . $escaper->escapeHtml($lang['AddQuestion']) . "\" />\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";

        // Get the assessment
        $assessment = get_assessment_with_scoring($assessment_id);

        // Print the assessment
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        if(count($assessment)){
            echo "<h4>" . $escaper->escapeHtml($assessment_name) . " <button class=\"update-all pull-right\">". $lang['UpdateAll'] ."</button><div class=\"clearfix\"></div></h4>\n";
        }else{
            echo "<h4>" . $escaper->escapeHtml($assessment_name) . "</h4>\n";
        }
        display_edit_assessment_questions($assessment_id, $assessment);
        if(count($assessment)){
            echo "<div class=\"text-right\"><button class=\"update-all\">". $lang['UpdateAll'] ."</button></div>";
        }
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
    }
}

/*******************************
 * FUNCTION: CREATE ASSESSMENT *
 *******************************/
function create_assessment($assessment_name)
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT INTO `assessments` (`name`) VALUES (:assessment_name);");
    $stmt->bindParam(":assessment_name", $assessment_name, PDO::PARAM_STR, 200);
        $stmt->execute();

    // Get the id of the last insert
    $id = $db->lastInsertId();

    // Close the database connection
    db_close($db);

    // Return the id
    return $id;
}

/*************************************
 * FUNCTION: ADD ASSESSMENT QUESTION *
 *************************************/
function add_assessment_question($id, $question, $answer, $submit_risk, $risk_subject, $risk_score, $risk_owner, $assets, $assessment_scoring_ids)
{
    // Open the database connection
    $db = db_open();

    // Get the questions in current order
    $stmt = $db->prepare("SELECT max(`order`) max_order FROM `assessment_questions` where assessment_id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Get the values
    $array = $stmt->fetch();
    $order = isset($array['max_order']) ? ($array['max_order']+1) : 0;

    // Add the question
    $stmt = $db->prepare("INSERT INTO `assessment_questions` (`assessment_id`, `question`, `order`) VALUES (:assessment_id, :question, :order);");
    $stmt->bindParam(":assessment_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":question", $question, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":order", $order, PDO::PARAM_STR, 1000);
    $stmt->execute();

    // Get the id of the last insert
    $question_id = $db->lastInsertId();

    // For each answer provided
    foreach ($answer as $key=>$value)
    {
        // If the key is in the submit_risk array
        if (in_array($key, $submit_risk))
        {
            $submit = 1;
        }
        else $submit = 0;

        // Add the answer
        $stmt = $db->prepare("INSERT INTO `assessment_answers` (`assessment_id`, `question_id`, `answer`, `submit_risk`, `risk_subject`, `risk_score`, `assessment_scoring_id`, `risk_owner`, `assets`, `order`) VALUES (:assessment_id, :question_id, :answer, :submit_risk, :risk_subject, :risk_score, :assessment_scoring_id, :risk_owner, :assets, :order);");
        $stmt->bindParam(":answer", $answer[$key], PDO::PARAM_STR, 200);
        $stmt->bindParam(":assessment_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":submit_risk", $submit, PDO::PARAM_INT);
        $stmt->bindParam(":risk_subject", $risk_subject[$key], PDO::PARAM_STR, 1000);
        $stmt->bindParam(":risk_score", $risk_score[$key], PDO::PARAM_STR, 200);
        $stmt->bindParam(":assessment_scoring_id", $assessment_scoring_ids[$key], PDO::PARAM_STR);
        $stmt->bindParam(":risk_owner", $risk_owner[$key], PDO::PARAM_INT);
        $stmt->bindParam(":assets", $assets[$key], PDO::PARAM_STR, 200);
        $stmt->bindParam(":order", $key, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: UPDATE ASSESSMENT QUESTION *
 ****************************************/
function update_assessment_question($assessment_id, $question_id, $question, $answer, $submit_risk, $answer_id, $risk_subject, $risk_score, $risk_owner, $assets, $assessment_scoring_ids = false)
{
        // Open the database connection
        $db = db_open();

        // Update the question
        $stmt = $db->prepare("UPDATE `assessment_questions` SET question=:question WHERE `assessment_id`=:assessment_id AND `id`=:question_id;");
        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->bindParam("question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":question", $question, PDO::PARAM_STR, 1000);
        $stmt->execute();

        // For each answer provided
        foreach ($answer_id as $key=>$value)
        {
            // If the answer_id is in the submit risk array
            if (in_array($value, $submit_risk))
            {
                // Set the submit risk value
                $submit = 1;
            }
            else $submit = 0;

            // Update the answer
            if($assessment_scoring_ids === false){
                $stmt = $db->prepare("UPDATE `assessment_answers` SET `answer`=:answer, `submit_risk`=:submit_risk, `risk_subject`=:risk_subject, `risk_score`=:risk_score, `risk_owner`=:risk_owner, `assets`=:assets WHERE `assessment_id`=:assessment_id AND `question_id`=:question_id AND `id`=:answer_id;");
            }else{
                $stmt = $db->prepare("UPDATE `assessment_answers` SET `answer`=:answer, `submit_risk`=:submit_risk, `risk_subject`=:risk_subject, `risk_score`=:risk_score, `risk_owner`=:risk_owner, `assets`=:assets, `assessment_scoring_id`=:assessment_scoring_id WHERE `assessment_id`=:assessment_id AND `question_id`=:question_id AND `id`=:answer_id;");
            }
            $stmt->bindParam(":answer", $answer[$key], PDO::PARAM_STR, 200);
            $stmt->bindParam(":answer_id", $value, PDO::PARAM_INT);
            $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
            $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $stmt->bindParam(":submit_risk", $submit, PDO::PARAM_INT);
            $stmt->bindParam(":risk_subject", $risk_subject[$key], PDO::PARAM_STR, 1000);
            $stmt->bindParam(":risk_score", $risk_score[$key], PDO::PARAM_STR);
            $stmt->bindParam(":risk_owner", $risk_owner[$key], PDO::PARAM_INT);
            $stmt->bindParam(":assets", $assets[$key], PDO::PARAM_STR, 200);
            if($assessment_scoring_ids !== false){
                $stmt->bindParam(":assessment_scoring_id", $assessment_scoring_ids[$key], PDO::PARAM_INT);
            }
            $stmt->execute();
        }

        // Close the database connection
        db_close($db);
}

/***********************************************
 * FUNCTION: DISPLAY EDIT ASSESSMENT QUESTIONS *
 ***********************************************/
function  display_edit_assessment_questions($assessment_id, $assessment)
{
    global $escaper;
    global $lang;

    // If the user posted to delete the question
    if (isset($_POST['delete_question']))
    {
        // Get the question id to delete
        $question_id = (int)$_POST['question_id'];

        // Delete the question
        delete_question($assessment_id, $question_id);
        
        set_alert(true, "good", $escaper->escapeHtml($lang["DeletedSuccess"]));
        
        // Refresh current page
        header('Location: '.$_SERVER['REQUEST_URI']);
        exit;
    }

    // If the user posted to save the question
    if (isset($_POST['save']))
    {
        // Get the posted values
        $question_id = (int)$_POST['question_id'];
        $question = $_POST['question'];
        $answer = $_POST['answer'];
        $answer_id = $_POST['answer_id'];
        $risk_subject = $_POST['risk_subject'];
//        $risk_score = $_POST['risk_score'];
        $risk_owner = $_POST['risk_owner'];
        $assets = $_POST['assets'];

        // If the submit_risk parameter was posted
        if (isset($_POST['submit_risk']))
        {
            // Set the submit_risk variable
            $submit_risk = $_POST['submit_risk'];
        }
        else $submit_risk = array();
        
        // Get assessment score values as an array
        $assessment_scores = get_assessment_scores_array();
        
        // Update assessment scoring
        foreach($assessment_scores as $key => $assessment_score){

            if($_POST['assessment_scoring_id'][$key]){
                $risk_score[$key] = update_assessment_scoring($_POST['assessment_scoring_id'][$key], $assessment_score);
                $assessment_scoring_ids[] = $_POST['assessment_scoring_id'][$key];
            }
            else{
                list($assessment_scoring_ids[], $risk_score[]) = add_assessment_scoring($assessment_score);
            }

        }

        // Update the question
        update_assessment_question($assessment_id, $question_id, $question, $answer, $submit_risk, $answer_id, $risk_subject, $risk_score, $risk_owner, $assets, $assessment_scoring_ids);
        
        set_alert(true, "good", $escaper->escapeHtml($lang["SavedSuccess"]));
        
        // Refresh current page
        header('Location: '.$_SERVER['REQUEST_URI']);
        exit;
    }

    // If the user posted to move the question up
    if (isset($_POST['move_up']))
    {
        // Get the question id
        $question_id = (int)$_POST['question_id'];

        // Move the question up
        change_question_order($assessment_id, $question_id, "up");
    }

    // If the user posted to move the question down
    if (isset($_POST['move_down']))
    {
        // Get the question id
        $question_id = (int)$_POST['question_id'];

        // Move the question down
        change_question_order($assessment_id, $question_id, "down");
    }

    // Set a variable to track the current question
    $current_question = "";
    $questionHtmlArr = array();

    // For each row in the array
    foreach ($assessment as $key=>$row)
    {
        $question = $row['question'];
        $question_id = $row['question_id'];
        if(empty($questionHtmlArr[$question_id])){
            $questionHtmlArr[$question_id] = array(
                'questionHtml' => "
                    <form name='question' method='POST' action=''>
                        <input type='hidden' name='action' value='edit' />
                        <input type='hidden' name='assessment_id' value='" . $escaper->escapeHtml($assessment_id) . "' />
                        <input type='hidden' name='question_id' value='" . $escaper->escapeHtml($question_id) . "' />

                        <table border='0' cellspacing='0' cellpadding='0' width='100%'>
                            <tr>
                                <td>" . $escaper->escapeHtml($lang['Question']) . ":&nbsp;&nbsp;</td>
                                <td width='100%'><input type='text' style='width: 99%; ' name='question' value='" . $escaper->escapeHtml($question) . "' /></td>
                            </tr>
                        </table>

                        <table class='answers-table' border='0' cellspacing='0' cellpadding='0' width='100%'>
                            <tr>
                                <th width=\"10%\">" . $escaper->escapeHtml($lang['Answer']) . "</th>
                                <th width=\"10%\" align='middle'>" . $escaper->escapeHtml($lang['SubmitRisk']) . "</th>
                                <th width=\"40%\" align='middle'>" . $escaper->escapeHtml($lang['Subject']) . "</th>
                                <!-- th align='middle'>" . $escaper->escapeHtml($lang['RiskScore']) . "</th -->
                                <th width=\"10%\" align='middle'>" . $escaper->escapeHtml($lang['Owner']) . "</th>
                                <th width=\"30%\" align='middle'>" . $escaper->escapeHtml($lang['AffectedAssets']) . "</th>
                            </tr>
                            ___Answers___
                         </table>
                        <button name='save' value='' title='Save' style='float: left;'><img src='../images/save.png' width='10' height='10' align='right' alt='Save' /></button>
                        <button name='move_up' title='Move Up' value='' style='float: left;'><img src='../images/arrow-up.png' width='10' height='10' align='right' alt='Move Up' /></button>
                        <button name='move_down' title='Move Down' value='' style='float: left;'><img src='../images/arrow-down.png' width='10' height='10' align='right' alt='Move Down' /></button>
                        <button name='delete_question' title='Delete Question' value='' style='float: left;'><img src='../images/X-100.png' width='10' height='10' align='right' alt='Delete Question' /></button>
                     </form>
                     <br>
                     <div class='clearfix'></div>
                     <hr />
                     <br>
                 ",
                'answerHtmlArr' => array(),
            );
        }
        

        // Set the answer values
        $answer = $row['answer'];
        $answer_id = $row['answer_id'];
        $submit_risk = $row['submit_risk'];
        $risk_subject = $row['risk_subject'];
        $risk_score = $row['risk_score'];
        $risk_owner = $row['risk_owner'];
        $assets = $row['assets'];
        
        $answerHtml = "
            <tbody>
            <tr>
                <td><input type='text' name='answer[]' size='200' value='" . $escaper->escapeHtml($answer) . "' /></td>
                <td align='middle'><input type='checkbox' name='submit_risk[]' value='" . $escaper->escapeHtml($answer_id) . "'" . (($submit_risk == 1) ? " checked" : "") . " /><input type='hidden' name='answer_id[]' value='" . $escaper->escapeHtml($answer_id) . "' /></td>
                <td><input type='text' name='risk_subject[]' size='200' value='" . $escaper->escapeHtml($risk_subject) . "' /></td>
                <!-- td><input type='number' min='0' max='10' name='risk_score[]' value='" . $escaper->escapeHtml($risk_score) . "' step='0.1' /></td -->
                <td>
                ".create_dropdown("user", $risk_owner, "risk_owner[]", true, false, true)."   
                </td>
                <td>
                    <div class='ui-widget'><input type='text' id='assets' class='assets' name='assets[]' value='" . $escaper->escapeHtml($assets) . "' /></div>
                    <input type='hidden' id='assessment_scoring_id' name='assessment_scoring_id[]' value='". $row['assessment_scoring_id'] ."'> 
                </td>
            </tr>
        ";
        
        // If this answer has assessment scoring record.
        if($row['assessment_scoring_id'])
        {
            $answerHtml .= display_risk_score(true, $row['scoring_method'], $row['Custom'], $row['CLASSIC_likelihood'], $row['CLASSIC_impact'], $row['CVSS_AccessVector'], $row['CVSS_AccessComplexity'], $row['CVSS_Authentication'], $row['CVSS_ConfImpact'], $row['CVSS_IntegImpact'], $row['CVSS_AvailImpact'], $row['CVSS_Exploitability'], $row['CVSS_RemediationLevel'], $row['CVSS_ReportConfidence'], $row['CVSS_CollateralDamagePotential'], $row['CVSS_TargetDistribution'], $row['CVSS_ConfidentialityRequirement'], $row['CVSS_IntegrityRequirement'], $row['CVSS_AvailabilityRequirement'], $row['DREAD_DamagePotential'], $row['DREAD_Reproducibility'], $row['DREAD_Exploitability'], $row['DREAD_AffectedUsers'], $row['DREAD_Discoverability'], $row['OWASP_SkillLevel'], $row['OWASP_Motive'], $row['OWASP_Opportunity'], $row['OWASP_Size'], $row['OWASP_EaseOfDiscovery'], $row['OWASP_EaseOfExploit'], $row['OWASP_Awareness'], $row['OWASP_IntrusionDetection'], $row['OWASP_LossOfConfidentiality'], $row['OWASP_LossOfIntegrity'], $row['OWASP_LossOfAvailability'], $row['OWASP_LossOfAccountability'], $row['OWASP_FinancialDamage'], $row['OWASP_ReputationDamage'], $row['OWASP_NonCompliance'], $row['OWASP_PrivacyViolation']);
        }
        // If this answer doesn't have assessment scoring record, set default scoring values.
        else
        {
            $answerHtml .= display_risk_score(true);
        }
        
        $answerHtml .= "</tbody>";
        
        array_push($questionHtmlArr[$question_id]['answerHtmlArr'], $answerHtml);
    }
    
    $questionHtmls = array();
    foreach($questionHtmlArr as $questionHtmlObj){
        $answerHtmls = implode("", $questionHtmlObj['answerHtmlArr']);
        $questionHtmls[] = str_replace("___Answers___", $answerHtmls, $questionHtmlObj['questionHtml']);
    }
    echo implode("", $questionHtmls);
    
}

/*****************************
 * FUNCTION: DELETE QUESTION *
 *****************************/
function delete_question($assessment_id, $question_id)
{
    // Open the database connection
    $db = db_open();

    // Delete answers for the question
    $stmt = $db->prepare("DELETE t1, t2 FROM `assessment_scoring` t1 INNER JOIN `assessment_answers` t2 on t1.id = t2.assessment_scoring_id  WHERE t2.assessment_id=:assessment_id AND t2.question_id=:question_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the question
    $stmt = $db->prepare("DELETE FROM `assessment_questions` WHERE assessment_id=:assessment_id AND id=:question_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();


    // Close the database connection
    db_close($db);
}

/***********************************
 * FUNCTION: CHANGE QUESTION ORDER *
 ***********************************/
function change_question_order($assessment_id, $question_id, $direction)
{
    // Open the database connection
    $db = db_open();

    // Get the questions in current order
    $stmt = $db->prepare("SELECT * FROM `assessment_questions` WHERE `assessment_id`=:assessment_id ORDER BY `order`, `id`;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

    // Get the values
    $array = $stmt->fetchAll();

    // For each row in the array
    foreach ($array as $key=>$row)
    {
        // Set the new order values
        $array[$key]['order'] = $key;

        // If this is the id we are looking for
        if ($row['id'] == $question_id)
        {
            // Capture it's key
            $question_key = $key;
        }
    }

    // If we are moving the question up
    if ($direction == "up")
    {
        // If the question key is not the first
        if ($question_key != 0)
        {
            // Get the values for the two rows
            $row1 = $array[$question_key-1];
            $row2 = $array[$question_key];

            // Swap the order of the two rows
            $array[$question_key-1]['order'] = $row2['order'];
            $array[$question_key]['order'] = $row1['order'];
        }
    }

    // If we are moving the question down
    if ($direction == "down")
    {
        // If the question key is not the last
        if ($question_key < count($array)-1)
        {
            // Get the values for the two rows
            $row1 = $array[$question_key];
            $row2 = $array[$question_key+1];

            // Swap the order of the two rows
            $array[$question_key]['order'] = $row2['order'];
            $array[$question_key+1]['order'] = $row1['order'];
        }
    }


    // For each row in the array
    foreach ($array as $row)
    {
        // Get the values
        $question_id = $row['id'];
        $order = $row['order'];

        // Update the order
            $stmt = $db->prepare("UPDATE `assessment_questions` SET `order`=:order WHERE `assessment_id`=:assessment_id AND `id`=:question_id;");
            $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":order", $order, PDO::PARAM_INT);
               $stmt->execute();
    }

        // Close the database connection
        db_close($db);

}

/*******************************
 * FUNCTION: DELETE ASSESSMENT *
 *******************************/
function delete_assessment($assessment_id)
{
        // Open the database connection
        $db = db_open();

        // Delete the assessment
        $stmt = $db->prepare("DELETE FROM `assessments` WHERE id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

    // Delete the assessment questions
    $stmt = $db->prepare("DELETE FROM `assessment_questions` WHERE assessment_id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the assessment answers
    $stmt = $db->prepare("DELETE FROM `assessment_answers` WHERE assessment_id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the pending risks
    $stmt = $db->prepare("DELETE FROM `pending_risks` WHERE assessment_id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************************
 * FUNCTION: DISPLAY SEND ASSESSMENT OPTIONS *
 *********************************************/
function display_send_assessment_options()
{
    global $escaper;
    global $lang;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span12\">\n";
    echo "<div class=\"hero-unit\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['SendAssessment']) . "</h4>\n";
    echo "<form name=\"assessment_question\" method=\"post\" action=\"\">\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"send\" />\n";
    echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">\n";
    echo "<tr>\n";
    echo "<td style=\"white-space:nowrap;\">" . $escaper->escapeHtml($lang['AssessmentName']) . ":&nbsp;&nbsp;</td>\n";
    echo "<td width=\"99%\">\n";
    echo "<select id=\"assessment\" name=\"assessment\">\n";

    // Get the assessment names
    $assessments = get_assessment_names();

    // For each assessment
    foreach ($assessments as $assessment)
    {
        echo "<option value=\"" . $escaper->escapeHtml($assessment['id']) . "\">" . $escaper->escapeHtml($assessment['name']) . "</option>\n";
    }

    echo "</select>\n";
    echo "</td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td style=\"white-space:nowrap;\">" . $escaper->escapeHtml($lang['SendTo']) . ":&nbsp;&nbsp;</td>\n";
    echo "<td width=\"99%\"><input type=\"text\" title=\"". $escaper->escapeHtml($lang['UseCommasToSeperateMultipleEmails']) ."\" name=\"email\" placeholer=\"" . $escaper->escapeHtml($lang['EmailAddress']) . "\" /></td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    echo "<input type=\"submit\" name=\"send_assessment\" value=\"" . $escaper->escapeHtml($lang['Send']) . "\" />\n";
    echo "</form>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*************************************
 * FUNCTION: PROCESS SENT ASSESSMENT *
 *************************************/
function process_sent_assessment()
{
    global $escaper;
    global $lang;

    // Get the assessment id
    $assessment_id = (int)$_POST['assessment'];

    // Get the assessment with that id
    $assessment = get_assessment_names($assessment_id);
    $assessment_name = $assessment['name'];

    // Get the email to send to
    $email = $_POST['email'];

    // Get who sent this assessment
    $sender = (int)$_SESSION['uid'];

    // Create a random key to access this assessment
    $key = generate_token(20);

    // Open the database connection
    $db = db_open();

    // Add the assessment tracking
    $stmt = $db->prepare("INSERT INTO `assessment_tracking` (`assessment_id`, `email`, `sender`, `key`) VALUES (:assessment_id, :email, :sender, :key);");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
    $stmt->bindParam(":sender", $sender, PDO::PARAM_INT);
    $stmt->bindParam(":key", $key, PDO::PARAM_STR, 20);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Create the message subject
    $subject = "[SIMPLERISK] ".$escaper->escapeHtml($lang['RiskAssessmentQuestionnaire']);

    // Get the assessment URL
    $url = get_current_url();
    $pieces = explode("index.php", $url);
    $url = $pieces[0];

    // Create the message body
    $body = get_string_from_template($lang['EmailTemplateSendingAssessment'], array(
        'username' => $escaper->escapeHtml($_SESSION['name']),
        'assessment_name' => $assessment_name,
        'assessment_link' => $url . "assessment.php?key=" . $key,
    ));

    // Require the mail functions
    require_once(realpath(__DIR__ . '/../../includes/mail.php'));
    
    // Get multiple emails
    $emails = explode(",", $email);
    
    foreach($emails as $val){
        $val = trim($val);
        // Send the e-mail
        send_email($val, $val, $subject, $body);
    }

    // Display a message that the assessment was sent successfully
    set_alert(true, "good", "Assessment was sent to \"" . $email . "\".");
}

/*************************************
 * FUNCTION: IS VALID ASSESSMENT KEY *
 *************************************/
function is_valid_assessment_key($key)
{
    // Remove old assessments
    remove_old_assessments();

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assessment_tracking` WHERE `key`=:key;");
    $stmt->bindParam(":key", $key, PDO::PARAM_STR, 20);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the query returned a value
    if (!empty($array))
    {
        return true;
    }
    else return false;
}

/************************************
 * FUNCTION: GET ACTIVE ASSESSMENTS *
 ************************************/
function get_active_assessments()
{
        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("SELECT * FROM `assessment_tracking`;");
        $stmt->bindParam(":key", $key, PDO::PARAM_STR, 20);
        $stmt->execute();

        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        return $array;
}

/***********************************
 * FUNCTION: GET ASSESSMENT BY KEY *
 ***********************************/
function get_assessment_by_key($key)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assessment_tracking` WHERE `key`=:key;");
    $stmt->bindParam(":key", $key, PDO::PARAM_STR, 20);
    $stmt->execute();

    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return $array;
}

/***********************************
 * FUNCTION: DELETE ASSESSMENT KEY *
 ***********************************/
function delete_assessment_key($key)
{
        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("DELETE FROM `assessment_tracking` WHERE `key`=:key;");
        $stmt->bindParam(":key", $key, PDO::PARAM_STR, 20);
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/************************************
 * FUNCTION: REMOVE OLD ASSESSMENTS *
 ************************************/
function remove_old_assessments()
{
        // Get the assessment settings
        $configs = get_assessment_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DELETE FROM `assessment_tracking` WHERE timestamp < (NOW() - INTERVAL :minutes MINUTE)");
    $stmt->bindParam(":minutes", $ASSESSMENT_MINUTES_VALID, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: DISPLAY ACTIVE ASSESSMENTS *
 ****************************************/
function display_active_assessments()
{
    global $escaper;
    global $lang;

    // Remove any old assessments
    remove_old_assessments();

    // Get the list of active assessments
    $assessments = get_active_assessments();

    // Display the active assessments
    echo "<form name=\"delete_active_assessments\" method=\"post\" action=\"\">\n";
    echo "<p><h4>" . $escaper->escapeHtml($lang['ActiveAssessments']) . "</h4></p>\n";
    echo "<p><button type=\"submit\" name=\"delete_active_assessments\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Delete']) . "</button></p>\n";
    echo "<table class=\"table table-bordered table-condensed sortable\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" width=\"75\"><input type=\"checkbox\" onclick=\"checkAll(this)\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['Delete']) . "</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['AssessmentName']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['SentTo']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['From']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Key']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

    // For each assessment
    foreach ($assessments as $assessment)
    {
        $id = (int)$assessment['id'];
        $assessment_id = (int)$assessment['assessment_id'];
        $assessment_details = get_assessment_names($assessment_id);
                $assessment_name = $assessment_details['name'];
        $sent_to = $assessment['email'];
        $sent_by = $assessment['sender'];
        $sent_by = get_user_by_id($sent_by);
        $key = $assessment['key'];
            $url = get_current_url();
            $pieces = explode("admin/active_assessments.php", $url);
            $url = $pieces[0];
        $timestamp = $assessment['timestamp'];

                echo "<tr>\n";
                echo "<td align=\"center\">\n";
                echo "<input type=\"checkbox\" name=\"assessments[]\" value=\"" . $escaper->escapeHtml($key) . "\" />\n";
                echo "</td>\n";
                echo "<td align=\"left\" width=\"200px\">" . $escaper->escapeHtml($assessment_name) . "</td>\n";
                echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($sent_to) . "</td>\n";
                echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($sent_by['name']) . "</td>\n";
        echo "<td align=\"left\" width=\"300px\"><a target=\"_blank\" href=\"" . $url . "assessments/assessment.php?key=" . $key . "\">" . $escaper->escapeHtml($key) . "</a></td>\n";
        echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($timestamp) . "</td>\n";
                echo "</tr>\n";
    }

        echo "</tbody>\n";
        echo "</table>\n";
    echo "<p><button type=\"submit\" name=\"delete_active_assessments\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Delete']) . "</button></p>\n";
    echo "</form>\n";
}

/***************************************
 * FUNCTION: DELETE ACTIVE ASSESSMENTS *
 ***************************************/
function delete_active_assessments($assessments)
{
        // For each assessment
        foreach ($assessments as $assessment)
        {
                $key = $assessment;

                // Delete the assessment
        delete_assessment_key($key);
        }
}


/*********************************
 * FUNCTION: SUBMIT RISK SCORING *
 *********************************/
function add_assessment_scoring($data)
{
    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$data['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIC_likelihood = (int)$data['CLASSIClikelihood'];
    $CLASSIC_impact =(int) $data['CLASSICimpact'];

    // CVSS Risk Scoring Inputs
    $AccessVector = $data['CVSSAccessVector'];
    $AccessComplexity = $data['CVSSAccessComplexity'];
    $Authentication = $data['CVSSAuthentication'];
    $ConfImpact = $data['CVSSConfImpact'];
    $IntegImpact = $data['CVSSIntegImpact'];
    $AvailImpact = $data['CVSSAvailImpact'];
    $Exploitability = $data['CVSSExploitability'];
    $RemediationLevel = $data['CVSSRemediationLevel'];
    $ReportConfidence = $data['CVSSReportConfidence'];
    $CollateralDamagePotential = $data['CVSSCollateralDamagePotential'];
    $TargetDistribution = $data['CVSSTargetDistribution'];
    $ConfidentialityRequirement = $data['CVSSConfidentialityRequirement'];
    $IntegrityRequirement = $data['CVSSIntegrityRequirement'];
    $AvailabilityRequirement = $data['CVSSAvailabilityRequirement'];

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)$data['DREADDamage'];
    $DREADReproducibility = (int)$data['DREADReproducibility'];
    $DREADExploitability = (int)$data['DREADExploitability'];
    $DREADAffectedUsers = (int)$data['DREADAffectedUsers'];
    $DREADDiscoverability = (int)$data['DREADDiscoverability'];

    // OWASP Risk Scoring Inputs
    $OWASPSkill = (int)$data['OWASPSkillLevel'];
    $OWASPMotive = (int)$data['OWASPMotive'];
    $OWASPOpportunity = (int)$data['OWASPOpportunity'];
    $OWASPSize = (int)$data['OWASPSize'];
    $OWASPDiscovery = (int)$data['OWASPEaseOfDiscovery'];
    $OWASPExploit = (int)$data['OWASPEaseOfExploit'];
    $OWASPAwareness = (int)$data['OWASPAwareness'];
    $OWASPIntrusionDetection = (int)$data['OWASPIntrusionDetection'];
    $OWASPLossOfConfidentiality = (int)$data['OWASPLossOfConfidentiality'];
    $OWASPLossOfIntegrity = (int)$data['OWASPLossOfIntegrity'];
    $OWASPLossOfAvailability = (int)$data['OWASPLossOfAvailability'];
    $OWASPLossOfAccountability = (int)$data['OWASPLossOfAccountability'];
    $OWASPFinancialDamage = (int)$data['OWASPFinancialDamage'];
    $OWASPReputationDamage = (int)$data['OWASPReputationDamage'];
    $OWASPNonCompliance = (int)$data['OWASPNonCompliance'];
    $OWASPPrivacyViolation = (int)$data['OWASPPrivacyViolation'];

    // Custom Risk Scoring
    $custom = (float)$data['Custom'];

    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
        // Calculate the risk via classic method
        $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact`) VALUES (:scoring_method, :calculated_risk, :CLASSIC_likelihood, :CLASSIC_impact)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
        $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
    }
    // If the scoring method is CVSS (2)
    else if ($scoring_method == 2)
    {
        // Get the numeric values for the CVSS submission
        $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
        $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
        $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
        $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
        $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
        $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
        $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
        $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
        $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
        $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
        $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
        $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
        $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
        $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

        // Calculate the risk via CVSS method
        $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `CVSS_AccessVector`, `CVSS_AccessComplexity`, `CVSS_Authentication`, `CVSS_ConfImpact`, `CVSS_IntegImpact`, `CVSS_AvailImpact`, `CVSS_Exploitability`, `CVSS_RemediationLevel`, `CVSS_ReportConfidence`, `CVSS_CollateralDamagePotential`, `CVSS_TargetDistribution`, `CVSS_ConfidentialityRequirement`, `CVSS_IntegrityRequirement`, `CVSS_AvailabilityRequirement`) VALUES (:scoring_method, :calculated_risk, :CVSS_AccessVector, :CVSS_AccessComplexity, :CVSS_Authentication, :CVSS_ConfImpact, :CVSS_IntegImpact, :CVSS_AvailImpact, :CVSS_Exploitability, :CVSS_RemediationLevel, :CVSS_ReportConfidence, :CVSS_CollateralDamagePotential, :CVSS_TargetDistribution, :CVSS_ConfidentialityRequirement, :CVSS_IntegrityRequirement, :CVSS_AvailabilityRequirement)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
    }
    // If the scoring method is DREAD (3)
    else if ($scoring_method == 3)
    {
        // Calculate the risk via DREAD method
        $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `DREAD_DamagePotential`, `DREAD_Reproducibility`, `DREAD_Exploitability`, `DREAD_AffectedUsers`, `DREAD_Discoverability`) VALUES (:scoring_method, :calculated_risk, :DREAD_DamagePotential, :DREAD_Reproducibility, :DREAD_Exploitability, :DREAD_AffectedUsers, :DREAD_Discoverability)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4){
        $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
        $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

        // Average the threat agent and vulnerability factors to get the likelihood
        $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

        $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
        $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

        // Average the technical and business impacts to get the impact
        $OWASP_impact = ($technical_impact + $business_impact)/2;

        // Calculate the overall OWASP risk score
        $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `OWASP_SkillLevel`, `OWASP_Motive`, `OWASP_Opportunity`, `OWASP_Size`, `OWASP_EaseOfDiscovery`, `OWASP_EaseOfExploit`, `OWASP_Awareness`, `OWASP_IntrusionDetection`, `OWASP_LossOfConfidentiality`, `OWASP_LossOfIntegrity`, `OWASP_LossOfAvailability`, `OWASP_LossOfAccountability`, `OWASP_FinancialDamage`, `OWASP_ReputationDamage`, `OWASP_NonCompliance`, `OWASP_PrivacyViolation`) VALUES (:scoring_method, :calculated_risk, :OWASP_SkillLevel, :OWASP_Motive, :OWASP_Opportunity, :OWASP_Size, :OWASP_EaseOfDiscovery, :OWASP_EaseOfExploit, :OWASP_Awareness, :OWASP_IntrusionDetection, :OWASP_LossOfConfidentiality, :OWASP_LossOfIntegrity, :OWASP_LossOfAvailability, :OWASP_LossOfAccountability, :OWASP_FinancialDamage, :OWASP_ReputationDamage, :OWASP_NonCompliance, :OWASP_PrivacyViolation)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5){
        // If the custom value is not between 0 and 10
        if (!(($custom >= 0) && ($custom <= 10)))
        {
            // Set the custom value to 10
            $custom = get_setting('default_risk_score');
        }

        // Calculated risk is the custom value
        $calculated_risk = $custom;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `Custom`) VALUES (:scoring_method, :calculated_risk, :Custom)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
    }
    // Otherwise
    else
    {
        return false;
    }

    // Add the risk score
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    $last_insert_id = $db->lastInsertId();

    return array($last_insert_id, $calculated_risk);
}

/*********************************
 * FUNCTION: UPDATE RISK SCORING *
 *********************************/
function update_assessment_scoring($id, $data)
{
    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$data['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIC_likelihood = (int)$data['CLASSIClikelihood'];
    $CLASSIC_impact =(int) $data['CLASSICimpact'];

    // CVSS Risk Scoring Inputs
    $AccessVector = $data['CVSSAccessVector'];
    $AccessComplexity = $data['CVSSAccessComplexity'];
    $Authentication = $data['CVSSAuthentication'];
    $ConfImpact = $data['CVSSConfImpact'];
    $IntegImpact = $data['CVSSIntegImpact'];
    $AvailImpact = $data['CVSSAvailImpact'];
    $Exploitability = $data['CVSSExploitability'];
    $RemediationLevel = $data['CVSSRemediationLevel'];
    $ReportConfidence = $data['CVSSReportConfidence'];
    $CollateralDamagePotential = $data['CVSSCollateralDamagePotential'];
    $TargetDistribution = $data['CVSSTargetDistribution'];
    $ConfidentialityRequirement = $data['CVSSConfidentialityRequirement'];
    $IntegrityRequirement = $data['CVSSIntegrityRequirement'];
    $AvailabilityRequirement = $data['CVSSAvailabilityRequirement'];

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)$data['DREADDamage'];
    $DREADReproducibility = (int)$data['DREADReproducibility'];
    $DREADExploitability = (int)$data['DREADExploitability'];
    $DREADAffectedUsers = (int)$data['DREADAffectedUsers'];
    $DREADDiscoverability = (int)$data['DREADDiscoverability'];

    // OWASP Risk Scoring Inputs
    $OWASPSkill = (int)$data['OWASPSkillLevel'];
    $OWASPMotive = (int)$data['OWASPMotive'];
    $OWASPOpportunity = (int)$data['OWASPOpportunity'];
    $OWASPSize = (int)$data['OWASPSize'];
    $OWASPDiscovery = (int)$data['OWASPEaseOfDiscovery'];
    $OWASPExploit = (int)$data['OWASPEaseOfExploit'];
    $OWASPAwareness = (int)$data['OWASPAwareness'];
    $OWASPIntrusionDetection = (int)$data['OWASPIntrusionDetection'];
    $OWASPLossOfConfidentiality = (int)$data['OWASPLossOfConfidentiality'];
    $OWASPLossOfIntegrity = (int)$data['OWASPLossOfIntegrity'];
    $OWASPLossOfAvailability = (int)$data['OWASPLossOfAvailability'];
    $OWASPLossOfAccountability = (int)$data['OWASPLossOfAccountability'];
    $OWASPFinancialDamage = (int)$data['OWASPFinancialDamage'];
    $OWASPReputationDamage = (int)$data['OWASPReputationDamage'];
    $OWASPNonCompliance = (int)$data['OWASPNonCompliance'];
    $OWASPPrivacyViolation = (int)$data['OWASPPrivacyViolation'];

    // Custom Risk Scoring
    $custom = (float)$data['Custom'];


    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
            // Calculate the risk via classic method
            $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CLASSIC_likelihood=:CLASSIC_likelihood, CLASSIC_impact=:CLASSIC_impact WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
            $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
    }
    // If the scoring method is CVSS (2)
    else if ($scoring_method == 2)
    {
            // Get the numeric values for the CVSS submission
            $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
            $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
            $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
            $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
            $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
            $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
            $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
            $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
            $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
            $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
            $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
            $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
            $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
            $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

            // Calculate the risk via CVSS method
            $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);
            

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CVSS_AccessVector=:CVSS_AccessVector, CVSS_AccessComplexity=:CVSS_AccessComplexity, CVSS_Authentication=:CVSS_Authentication, CVSS_ConfImpact=:CVSS_ConfImpact, CVSS_IntegImpact=:CVSS_IntegImpact, CVSS_AvailImpact=:CVSS_AvailImpact, CVSS_Exploitability=:CVSS_Exploitability, CVSS_RemediationLevel=:CVSS_RemediationLevel, CVSS_ReportConfidence=:CVSS_ReportConfidence, CVSS_CollateralDamagePotential=:CVSS_CollateralDamagePotential, CVSS_TargetDistribution=:CVSS_TargetDistribution, CVSS_ConfidentialityRequirement=:CVSS_ConfidentialityRequirement, CVSS_IntegrityRequirement=:CVSS_IntegrityRequirement, CVSS_AvailabilityRequirement=:CVSS_AvailabilityRequirement WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
    }
    // If the scoring method is DREAD (3)
    else if ($scoring_method == 3)
    {
            // Calculate the risk via DREAD method
            $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, DREAD_DamagePotential=:DREAD_DamagePotential, DREAD_Reproducibility=:DREAD_Reproducibility, DREAD_Exploitability=:DREAD_Exploitability, DREAD_AffectedUsers=:DREAD_AffectedUsers, DREAD_Discoverability=:DREAD_Discoverability WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4)
    {
            $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
            $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

            // Average the threat agent and vulnerability factors to get the likelihood
            $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

            $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
            $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

            // Average the technical and business impacts to get the impact
            $OWASP_impact = ($technical_impact + $business_impact)/2;

            // Calculate the overall OWASP risk score
            $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, OWASP_SkillLevel=:OWASP_SkillLevel, OWASP_Motive=:OWASP_Motive, OWASP_Opportunity=:OWASP_Opportunity, OWASP_Size=:OWASP_Size, OWASP_EaseOfDiscovery=:OWASP_EaseOfDiscovery, OWASP_EaseOfExploit=:OWASP_EaseOfExploit, OWASP_Awareness=:OWASP_Awareness, OWASP_IntrusionDetection=:OWASP_IntrusionDetection, OWASP_LossOfConfidentiality=:OWASP_LossOfConfidentiality, OWASP_LossOfIntegrity=:OWASP_LossOfIntegrity, OWASP_LossOfAvailability=:OWASP_LossOfAvailability, OWASP_LossOfAccountability=:OWASP_LossOfAccountability, OWASP_FinancialDamage=:OWASP_FinancialDamage, OWASP_ReputationDamage=:OWASP_ReputationDamage, OWASP_NonCompliance=:OWASP_NonCompliance, OWASP_PrivacyViolation=:OWASP_PrivacyViolation WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5)
    {
            // If the custom value is not between 0 and 10
            if (!(($custom >= 0) && ($custom <= 10)))
            {
                    // Set the custom value to 10
                    $custom = 10;
            }

            // Calculated risk is the custom value
            $calculated_risk = $custom;

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, Custom=:Custom WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
    }
    // Otherwise
    else
    {
            return false;
    }

    // Add the risk score
    $stmt->execute();

    // Close the database connection
    db_close($db);
    
    return $calculated_risk;
}

/***********************************************************
* FUNCTION: VIEW PRINT RISK SCORE FORMS IN EDIT ASSESSMENT *
************************************************************/
function print_score_html_from_assessment($scoring_method="5", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom=false){
    global $escaper;
    global $lang;
    
    if($custom === false){
        $custom = get_setting("default_risk_score");
    }
    
    if(!$scoring_method)
        $scoring_method = 5;
        
    $html = "
        <div class='row-fluid' >
            <div class='span5 text-right'>". $escaper->escapeHtml($lang['RiskScoringMethod']) .": &nbsp;</div>
            <div class='span7'>"
            .create_dropdown("scoring_methods", $scoring_method, "scoring_method[]", false, false, true).
            "
                <!-- select class='form-control' name='scoring_method' id='select' >
                    <option selected value='1'>Classic</option>
                    <option value='2'>CVSS</option>
                    <option value='3'>DREAD</option>
                    <option value='4'>OWASP</option>
                    <option value='5'>Custom</option>
                </select -->
            </div>
        </div>
        <div id='classic' class='classic-holder' style='display:". ($scoring_method == 1 ? "block" : "none") ."'>
            <div class='row-fluid'>
                <div class='span5 text-right'>". $escaper->escapeHtml($lang['CurrentLikelihood']) .":</div>
                <div class='span7'>". create_dropdown('likelihood', $CLASSIC_likelihood, 'likelihood[]', true, false, true) ."</div>
            </div>
            <div class='row-fluid'>
                <div class='span5 text-right'>". $escaper->escapeHtml($lang['CurrentImpact']) .":</div>
                <div class='span7'>". create_dropdown('impact', $CLASSIC_impact, 'impact[]', true, false, true) ."</div>
            </div>
        </div>
        <div id='cvss' style='display: ". ($scoring_method == 2 ? "block" : "none") .";' class='cvss-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>&nbsp;</div>
                <div class='span7'><p><input type='button' name='cvssSubmit' id='cvssSubmit' value='Score Using CVSS' /></p></div>
            </div>
            <input type='hidden' name='AccessVector[]' id='AccessVector' value='{$AccessVector}' />
            <input type='hidden' name='AccessComplexity[]' id='AccessComplexity' value='{$AccessComplexity}' />
            <input type='hidden' name='Authentication[]' id='Authentication' value='{$Authentication}' />
            <input type='hidden' name='ConfImpact[]' id='ConfImpact' value='{$ConfImpact}' />
            <input type='hidden' name='IntegImpact[]' id='IntegImpact' value='{$IntegImpact}' />
            <input type='hidden' name='AvailImpact[]' id='AvailImpact' value='{$AvailImpact}' />
            <input type='hidden' name='Exploitability[]' id='Exploitability' value='{$Exploitability}' />
            <input type='hidden' name='RemediationLevel[]' id='RemediationLevel' value='{$RemediationLevel}' />
            <input type='hidden' name='ReportConfidence[]' id='ReportConfidence' value='{$ReportConfidence}' />
            <input type='hidden' name='CollateralDamagePotential[]' id='CollateralDamagePotential' value='{$CollateralDamagePotential}' />
            <input type='hidden' name='TargetDistribution[]' id='TargetDistribution' value='{$TargetDistribution}' />
            <input type='hidden' name='ConfidentialityRequirement[]' id='ConfidentialityRequirement' value='{$ConfidentialityRequirement}' />
            <input type='hidden' name='IntegrityRequirement[]' id='IntegrityRequirement' value='{$IntegrityRequirement}' />
            <input type='hidden' name='AvailabilityRequirement[]' id='AvailabilityRequirement' value='{$AvailabilityRequirement}' />
        </div>
        <div id='dread' style='display: ". ($scoring_method == 3 ? "block" : "none") .";' class='dread-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>&nbsp;</div>
                <div class='span7'><p><input type='button' name='dreadSubmit' id='dreadSubmit' value='Score Using DREAD' onclick='javascript: popupdread();' /></p></div>
            </div>
            <input type='hidden' name='DREADDamage[]' id='DREADDamage' value='{$DREADDamagePotential}' />
            <input type='hidden' name='DREADReproducibility[]' id='DREADReproducibility' value='{$DREADReproducibility}' />
            <input type='hidden' name='DREADExploitability[]' id='DREADExploitability' value='{$DREADExploitability}' />
            <input type='hidden' name='DREADAffectedUsers[]' id='DREADAffectedUsers' value='{$DREADAffectedUsers}' />
            <input type='hidden' name='DREADDiscoverability[]' id='DREADDiscoverability' value='{$DREADDiscoverability}' />
        </div>
        <div id='owasp' style='display: ". ($scoring_method == 4 ? "block" : "none") .";' class='owasp-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>&nbsp;</div>
                <div class='span7'><p><input type='button' name='owaspSubmit' id='owaspSubmit' value='Score Using OWASP' onclick='javascript: popupowasp();' /></p></div>
            </div>
            <input type='hidden' name='OWASPSkillLevel[]' id='OWASPSkillLevel' value='{$OWASPSkillLevel}' />
            <input type='hidden' name='OWASPMotive[]' id='OWASPMotive' value='{$OWASPMotive}' />
            <input type='hidden' name='OWASPOpportunity[]' id='OWASPOpportunity' value='{$OWASPOpportunity}' />
            <input type='hidden' name='OWASPSize[]' id='OWASPSize' value='{$OWASPSize}' />
            <input type='hidden' name='OWASPEaseOfDiscovery[]' id='OWASPEaseOfDiscovery' value='{$OWASPEaseOfDiscovery}' />
            <input type='hidden' name='OWASPEaseOfExploit[]' id='OWASPEaseOfExploit' value='{$OWASPEaseOfExploit}' />
            <input type='hidden' name='OWASPAwareness[]' id='OWASPAwareness' value='{$OWASPAwareness}' />
            <input type='hidden' name='OWASPIntrusionDetection[]' id='OWASPIntrusionDetection' value='{$OWASPIntrusionDetection}' />
            <input type='hidden' name='OWASPLossOfConfidentiality[]' id='OWASPLossOfConfidentiality' value='{$OWASPLossOfConfidentiality}' />
            <input type='hidden' name='OWASPLossOfIntegrity[]' id='OWASPLossOfIntegrity' value='{$OWASPLossOfIntegrity}' />
            <input type='hidden' name='OWASPLossOfAvailability[]' id='OWASPLossOfAvailability' value='{$OWASPLossOfAvailability}' />
            <input type='hidden' name='OWASPLossOfAccountability[]' id='OWASPLossOfAccountability' value='{$OWASPLossOfAccountability}' />
            <input type='hidden' name='OWASPFinancialDamage[]' id='OWASPFinancialDamage' value='{$OWASPFinancialDamage}' />
            <input type='hidden' name='OWASPReputationDamage[]' id='OWASPReputationDamage' value='{$OWASPReputationDamage}' />
            <input type='hidden' name='OWASPNonCompliance[]' id='OWASPNonCompliance' value='{$OWASPNonCompliance}' />
            <input type='hidden' name='OWASPPrivacyViolation[]' id='OWASPPrivacyViolation' value='{$OWASPPrivacyViolation}' />
        </div>
        <div id='custom' style='display: ". ($scoring_method == 5 ? "block" : "none") .";' class='custom-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>
                    ". $escaper->escapeHtml($lang['CustomValue']) .":
                </div>
                <div class='span7'>
                    <input type='number' min='0' step='0.1' max='10' name='Custom[]' id='Custom' value='{$custom}' /> 
                    <small>(Must be a numeric value between 0 and 10)</small>
                </div>
            </div>
        </div>
    
    ";
    
    echo $html;
}

/****************************************
 * FUNCTION: DISPLAY EXPORT ASSESSMENTS *
 ****************************************/
function display_export_assessments()
{
    global $escaper;
    global $lang;

    // Show the import form
    echo "<div class=\"hero-unit\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['ExportAssessment']) . "</h4>\n";

    // Display import assessment form
    echo "<form name=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
    echo $escaper->escapeHtml($lang['SelectAssessmentToExport'])."<br />\n";
    echo "<div>";
    assessment_dropdown();
    echo "</div>";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"assessments_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportAssessment']) . "</button>\n";
    echo "</div>\n";
    echo "</form>\n";
    
    echo "</div>\n";
    
}

/*********************************
 * FUNCTION: ASSESSMENT DROPDOWN *
 *********************************/
function assessment_dropdown()
{
    global $escaper;
    global $lang;

    // Get the list of assessment
    $assessments = get_assessment_names();

    echo "<select name=\"assessment\" >\n";

    echo "<option value=\"\">--- " . $escaper->escapeHtml($lang['ALL']) . " ---</option>\n";
    // For each field
    foreach ($assessments as $assessment)
    {
        echo "<option value=\"" . $escaper->escapeHtml($assessment['id']) . "\">" . $escaper->escapeHtml($assessment['name']) . "</option>\n";
    }

    echo "</select>\n";
}

function display_import_assessment_form(){
    global $escaper;
    global $lang;
    
    echo "<form name=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
    echo "Import the following CSV file into SimpleRisk:<br />\n";
    echo "<input type=\"file\" name=\"file\" />\n";
        echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"import_assessment_csv\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
    echo "</div>\n";
    echo "</form>\n";
}

/***************************************
 * FUNCTION: DISPLAY IMPORT ASSESSMENT *
 ***************************************/
function display_import_assessments(){
    global $escaper;
    global $lang;

    // Show the import form
    echo "<div class=\"hero-unit\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['ImportAssessments']) . "</h4>\n";

    // If a file has not been imported or mapped
    if (!isset($_POST['import_assessment_csv']) && !isset($_POST['assessment_csv_mapped']))
    {
        // Display import assessment form
        display_import_assessment_form();
    }
    // If a file has been imported and mapped
    else if (isset($_POST['assessment_csv_mapped']))
    {
        // Copy posted values into a new array
        $mappings = $_POST;

        // Remove the first value in the array (CSRF Token)
        array_shift($mappings);

        // Remove the last value in the array (Submit Button)
        array_pop($mappings);

        // Import using the mapping
        import_assessments_with_mapping($mappings);
        
        // Refresh current page
        header('Location: '.$_SERVER['REQUEST_URI']);
    }
    // If a file has been imported
    else
    {
        // Import the file
        $display = import_csv($_FILES['file']);

        // If the file import was successful
        if ($display != 0)
        {
            // Print the remove selected javascript
            //remove_selected_js();

            echo "<form name=\"import\" id=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>Asset Column Mapping</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;

            // For each column in the file
            foreach ($display as $column)
            {
                    echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    assessment_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                    echo "</tr>\n";

                    // Increment the column counter
                    $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"assessment_csv_mapped\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
            echo "</form>\n";
        }
        // Otherwise, file import error
        else
        {
            // Get any alert messages
            //get_alert();
        }
    }

    echo "</div>\n";
}

/*********************************************
 * FUNCTION: ASSESSMENT COLUMN NAME DROPDOWN *
 *********************************************/
function assessment_column_name_dropdown($name)
{
    global $escaper;

    // Get the list of asset fields
    $fields = assessment_fields();

    echo "<select name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        echo "<option value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/*******************************
 * FUNCTION: ASSESSMENT FIELDS *
 *******************************/
function assessment_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'assessment_name'   =>$lang['AssessmentName'],
        'question'         =>$lang['Question'],
        'answer'           =>$lang['Answer'],
        'submit_risk'      =>$lang['SubmitRisk'],
        'risk_subject'     =>$lang['Subject'],
        'risks_owner'       =>$lang['Owner'],
        'assets'            =>$lang['AffectedAssets'],
        'riskscoring_scoring_method'                    =>$lang['RiskScoringMethod'],
        'riskscoring_calculated_risk'                   =>$lang['CalculatedRisk'],
        'riskscoring_CLASSIC_likelihood'                =>$lang['CurrentLikelihood'],
        'riskscoring_CLASSIC_impact'                    =>$lang['CurrentImpact'],
        'riskscoring_CVSS_AccessVector'                 =>'CVSS-'.$lang['AttackVector'],
        'riskscoring_CVSS_AccessComplexity'             =>'CVSS-'.$lang['AttackComplexity'],
        'riskscoring_CVSS_Authentication'               =>'CVSS-'.$lang['Authentication'],
        'riskscoring_CVSS_ConfImpact'                   =>'CVSS-'.$lang['ConfidentialityImpact'],
        'riskscoring_CVSS_IntegImpact'                  =>'CVSS-'.$lang['IntegrityImpact'],
        'riskscoring_CVSS_AvailImpact'                  =>'CVSS-'.$lang['AvailabilityImpact'],
        'riskscoring_CVSS_Exploitability'               =>'CVSS-'.$lang['Exploitability'],
        'riskscoring_CVSS_RemediationLevel'             =>'CVSS-'.$lang['RemediationLevel'],
        'riskscoring_CVSS_ReportConfidence'             =>'CVSS-'.$lang['ReportConfidence'],
        'riskscoring_CVSS_CollateralDamagePotential'    =>'CVSS-'.$lang['CollateralDamagePotential'],
        'riskscoring_CVSS_TargetDistribution'           =>'CVSS-'.$lang['TargetDistribution'],
        'riskscoring_CVSS_ConfidentialityRequirement'   =>'CVSS-'.$lang['ConfidentialityRequirement'],
        'riskscoring_CVSS_IntegrityRequirement'         =>'CVSS-'.$lang['IntegrityRequirement'],
        'riskscoring_CVSS_AvailabilityRequirement'      =>'CVSS-'.$lang['AvailabilityRequirement'],
        'riskscoring_DREAD_DamagePotential'             =>'DREAD-'.$lang['DamagePotential'],
        'riskscoring_DREAD_Reproducibility'             =>'DREAD-'.$lang['Reproducibility'],
        'riskscoring_DREAD_Exploitability'              =>'DREAD-'.$lang['Exploitability'],
        'riskscoring_DREAD_AffectedUsers'               =>'DREAD-'.$lang['AffectedUsers'],
        'riskscoring_DREAD_Discoverability'             =>'DREAD-'.$lang['Discoverability'],
        'riskscoring_OWASP_SkillLevel'                  =>'OWASP-'.$lang['SkillLevel'],
        'riskscoring_OWASP_Motive'                      =>'OWASP-'.$lang['Motive'],
        'riskscoring_OWASP_Opportunity'                 =>'OWASP-'.$lang['Opportunity'],
        'riskscoring_OWASP_Size'                        =>'OWASP-'.$lang['Size'],
        'riskscoring_OWASP_EaseOfDiscovery'             =>'OWASP-'.$lang['EaseOfDiscovery'],
        'riskscoring_OWASP_EaseOfExploit'               =>'OWASP-'.$lang['EaseOfExploit'],
        'riskscoring_OWASP_Awareness'                   =>'OWASP-'.$lang['Awareness'],
        'riskscoring_OWASP_IntrusionDetection'          =>'OWASP-'.$lang['IntrusionDetection'],
        'riskscoring_OWASP_LossOfConfidentiality'       =>'OWASP-'.$lang['LossOfConfidentiality'],
        'riskscoring_OWASP_LossOfIntegrity'             =>'OWASP-'.$lang['LossOfIntegrity'],
        'riskscoring_OWASP_LossOfAvailability'          =>'OWASP-'.$lang['LossOfAvailability'],
        'riskscoring_OWASP_LossOfAccountability'        =>'OWASP-'.$lang['LossOfAccountability'],
        'riskscoring_OWASP_FinancialDamage'             =>'OWASP-'.$lang['FinancialDamage'],
        'riskscoring_OWASP_ReputationDamage'            =>'OWASP-'.$lang['ReputationDamage'],
        'riskscoring_OWASP_NonCompliance'               =>'OWASP-'.$lang['NonCompliance'],
        'riskscoring_OWASP_PrivacyViolation'            =>'OWASP-'.$lang['PrivacyViolation'],
        'riskscoring_Custom'                            =>$lang['CustomValue'],
    );

    // Return the fields array
    return $fields;
}

/*********************************************
 * FUNCTION: IMPORT ASSESSMENTS WITH MAPPING *
 *********************************************/
function import_assessments_with_mapping($mappings)
{
    global $escaper;
    global $lang;

    // Open the temporary file for reading
    ini_set('auto_detect_line_endings', true);

    // Open the database connection
    $db = db_open();

    // Detect first line
    $first_line = true;
    // If we can read the temporary file
    if (($handle = fopen(sys_get_temp_dir() . '/import.csv', "r")) !== FALSE)
    {
        // Get assessment list with id and name
        $assessment_names = get_assessment_names();
        $assessment_list = array();
        $assessment_question_list = array();
        foreach($assessment_names as $assessment){
            $assessment_list[$assessment['id']] = $assessment['name'];
            // Get question list following assessment ID
            $assessment_question_list[$assessment['id']] = get_assessment_with_scoring($assessment['id']);
        }
        
        // While we have lines in the file to read
        while (($csv_line = fgetcsv($handle)) !== FALSE)
        {
            // If we can import the first line or this is not the first line
            if (isset($_POST['import_first']) || $first_line == false)
            {
                // Get the name
                $assessment_name = get_mapping_value("assessment_", "name", $mappings, $csv_line);
                
                // If Assessment is not new one.
                if(in_array($assessment_name, $assessment_list)){
                    $assessment_id = array_search($assessment_name, $assessment_list);
                }else{
                    require_once(realpath(__DIR__ . '/../assessments/index.php'));
                    $assessment_id = create_assessment($assessment_name);
                    
                    $assessment_list[$assessment_id] = $assessment_name;
                    $assessment_question_list[$assessment_id] = array();
                }
                
                
                /*****************
                 *** ADD ASSET ***
                 *****************/
                // If the name is not null (we don't want to add assets without a name)
                if (!is_null($assessment_id))
                {
                    // Get the asset values
                    $question       = get_mapping_value("question", "", $mappings, $csv_line);
                    $question_id = 0;
                    
                    // If this assessment is not new, get question_id from question text.
                    if(isset($assessment_question_list[$assessment_id]) && is_array($assessment_question_list[$assessment_id])){
                        foreach($assessment_question_list[$assessment_id] as $row){
                            if($row['question'] == $question){
                                $question_id = $row['question_id'];
                                break;
                            }
                        }
                    }
                    
                    if(!$question_id){
                        
                        // Add the question
                        $stmt = $db->prepare("INSERT INTO `assessment_questions` (`assessment_id`, `question`, `order`) 
                            SELECT :assessment_id, :question, max(`order`) + 1
                            FROM `assessment_questions`
                            WHERE `assessment_id`=:assessment_id;
                        ");
                        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
                        $stmt->bindParam(":question", $question, PDO::PARAM_STR, 1000);
                        $stmt->execute();
                        
                        // Get the id of the last insert
                        $question_id = $db->lastInsertId();
                    }
                    
                    $answer         = get_mapping_value("answer", "", $mappings, $csv_line);
                    $answer_id = 0;
                    // If this assessment is not new, get question_id from question text.
                    if(isset($assessment_question_list[$assessment_id]) && is_array($assessment_question_list[$assessment_id])){
                        foreach($assessment_question_list[$assessment_id] as $row){
                            if($row['question_id'] == $question_id && $row['answer'] == $answer){
                                $answer_id = $row['answer_id'];
                                break;
                            }
                        }
                    }

                    
                    $submit_risk    = get_mapping_value("submit_risk", "", $mappings, $csv_line);
                    $risk_subject   = get_mapping_value("risk_subject", "", $mappings, $csv_line);
                    $risk_owner     = get_or_add_user("owner", $mappings, $csv_line);
                    $assets         = get_mapping_value("assets", "", $mappings, $csv_line);
                    
                    if(!$answer_id){
                        /************ Save assessment scoring *************/
                        // Get the risk scoring method
                        $scoring_method = get_mapping_value("riskscoring_", "scoring_method", $mappings, $csv_line);
                        

                        // Get the scoring method id
                        $scoring_method_id = get_value_by_name("scoring_methods", $scoring_method);

                        // If the scoring method is null
                        if (is_null($scoring_method_id))
                        {
                            // Set the scoring method to Classic
                            $scoring_method_id = 5;
                        }

                            // Classic Risk Scoring Inputs
                            $CLASSIClikelihood = get_mapping_value("riskscoring_", "CLASSIC_likelihood", $mappings, $csv_line);
                            $CLASSIClikelihood = (int) get_value_by_name('likelihood', $CLASSIClikelihood);
                            
                            $CLASSICimpact = get_mapping_value("riskscoring_", "CLASSIC_impact", $mappings, $csv_line);
                            $CLASSICimpact = (int) get_value_by_name('impact', $CLASSICimpact);
    
                            // CVSS Risk Scoring Inputs
                            $CVSSAccessVector = get_mapping_value("riskscoring_", "CVSS_AccessVector", $mappings, $csv_line);
                            
                            $CVSSAccessComplexity = get_mapping_value("riskscoring_", "CVSS_AccessComplexity", $mappings, $csv_line);
                            $CVSSAuthentication = get_mapping_value("riskscoring_", "CVSS_Authentication", $mappings, $csv_line);
                            $CVSSConfImpact = get_mapping_value("riskscoring_", "CVSS_ConfImpact", $mappings, $csv_line);
                            $CVSSIntegImpact = get_mapping_value("riskscoring_", "CVSS_IntegImpact", $mappings, $csv_line);
                            $CVSSAvailImpact = get_mapping_value("riskscoring_", "CVSS_AvailImpact", $mappings, $csv_line);


                            $CVSSExploitability = get_mapping_value("riskscoring_", "CVSS_Exploitability", $mappings, $csv_line);
                            $CVSSRemediationLevel = get_mapping_value("riskscoring_", "CVSS_RemediationLevel", $mappings, $csv_line);
                            $CVSSReportConfidence = get_mapping_value("riskscoring_", "CVSS_ReportConfidence", $mappings, $csv_line);
                            $CVSSCollateralDamagePotential = get_mapping_value("riskscoring_", "CVSS_CollateralDamagePotential", $mappings, $csv_line);
                            $CVSSTargetDistribution = get_mapping_value("riskscoring_", "CVSS_TargetDistribution", $mappings, $csv_line);
                            $CVSSConfidentialityRequirement = get_mapping_value("riskscoring_", "CVSS_ConfidentialityRequirement", $mappings, $csv_line);
                            $CVSSIntegrityRequirement = get_mapping_value("riskscoring_", "CVSS_IntegrityRequirement", $mappings, $csv_line);
                            $CVSSAvailabilityRequirement = get_mapping_value("riskscoring_", "CVSS_AvailabilityRequirement", $mappings, $csv_line);

                            // DREAD Risk Scoring Inputs
                            $DREADDamage = (int) get_mapping_value("riskscoring_", "DREAD_DamagePotential", $mappings, $csv_line);
                            $DREADReproducibility = (int) get_mapping_value("riskscoring_", "DREAD_Reproducibility", $mappings, $csv_line);
                            $DREADExploitability = (int) get_mapping_value("riskscoring_", "DREAD_Exploitability", $mappings, $csv_line);
                            $DREADAffectedUsers = (int) get_mapping_value("riskscoring_", "DREAD_AffectedUsers", $mappings, $csv_line);
                            $DREADDiscoverability = (int) get_mapping_value("riskscoring_", "DREAD_Discoverability", $mappings, $csv_line);

                            // OWASP Risk Scoring Inputs
                            $OWASPSkillLevel = (int) get_mapping_value("riskscoring_", "OWASP_SkillLevel", $mappings, $csv_line);
                            $OWASPMotive = (int) get_mapping_value("riskscoring_", "OWASP_Motive", $mappings, $csv_line);
                            $OWASPOpportunity = (int) get_mapping_value("riskscoring_", "OWASP_Opportunity", $mappings, $csv_line);
                            $OWASPSize = (int) get_mapping_value("riskscoring_", "OWASP_Size", $mappings, $csv_line);
                            $OWASPEaseOfDiscovery = (int) get_mapping_value("riskscoring_", "OWASP_EaseOfDiscovery", $mappings, $csv_line);

                            $OWASPEaseOfExploit = (int) get_mapping_value("riskscoring_", "OWASP_EaseOfExploit", $mappings, $csv_line);
                            $OWASPAwareness = (int) get_mapping_value("riskscoring_", "OWASP_Awareness", $mappings, $csv_line);
                            $OWASPIntrusionDetection = (int) get_mapping_value("riskscoring_", "OWASP_IntrusionDetection", $mappings, $csv_line);
                            $OWASPLossOfConfidentiality = (int) get_mapping_value("riskscoring_", "OWASP_LossOfConfidentiality", $mappings, $csv_line);
                            $OWASPLossOfIntegrity = (int) get_mapping_value("riskscoring_", "OWASP_LossOfIntegrity", $mappings, $csv_line);
                            $OWASPLossOfAvailability = (int) get_mapping_value("riskscoring_", "OWASP_LossOfAvailability", $mappings, $csv_line);
                            $OWASPLossOfAccountability = (int) get_mapping_value("riskscoring_", "OWASP_LossOfAccountability", $mappings, $csv_line);
                            $OWASPFinancialDamage = (int) get_mapping_value("riskscoring_", "OWASP_FinancialDamage", $mappings, $csv_line);
                            $OWASPReputationDamage = (int) get_mapping_value("riskscoring_", "OWASP_ReputationDamage", $mappings, $csv_line);
                            $OWASPNonCompliance = (int) get_mapping_value("riskscoring_", "OWASP_NonCompliance", $mappings, $csv_line);
                            $OWASPPrivacyViolation = (int) get_mapping_value("riskscoring_", "OWASP_PrivacyViolation", $mappings, $csv_line);

                            // Custom Risk Scoring
                            $custom = (float) get_mapping_value("riskscoring_", "Custom", $mappings, $csv_line);

                                    // Set null values to default
                                    if (is_null($CLASSIClikelihood)) $CLASSIClikelihood = "";
                                    if (is_null($CLASSICimpact)) $CLASSICimpact = "";
                                    if (is_null($CVSSAccessVector)) $CVSSAccessVector = "N";
                                    if (is_null($CVSSAccessComplexity)) $CVSSAccessComplexity = "L";
                                    if (is_null($CVSSAuthentication)) $CVSSAuthentication = "N";
                                    if (is_null($CVSSConfImpact)) $CVSSConfImpact = "C";
                                    if (is_null($CVSSIntegImpact)) $CVSSIntegImpact = "C";
                                    if (is_null($CVSSAvailImpact)) $CVSSAvailImpact = "C";
                                    if (is_null($CVSSExploitability)) $CVSSExploitability = "ND";
                                    if (is_null($CVSSRemediationLevel)) $CVSSRemediationLevel = "ND";
                                    if (is_null($CVSSReportConfidence)) $CVSSReportConfidence = "ND";
                                    if (is_null($CVSSCollateralDamagePotential)) $CVSSCollateralDamagePotential = "ND";
                                    if (is_null($CVSSTargetDistribution)) $CVSSTargetDistribution = "ND";
                                    if (is_null($CVSSConfidentialityRequirement)) $CVSSConfidentialityRequirement = "ND";
                                    if (is_null($CVSSIntegrityRequirement)) $CVSSIntegrityRequirement = "ND";
                                    if (is_null($CVSSAvailabilityRequirement)) $CVSSAvailabilityRequirement = "ND";
                                    if (is_null($DREADDamage)) $DREADDamage = "10";
                                    if (is_null($DREADReproducibility)) $DREADReproducibility = "10";
                                    if (is_null($DREADExploitability)) $DREADExploitability = "10";
                                    if (is_null($DREADAffectedUsers)) $DREADAffectedUsers = "10";
                                    if (is_null($DREADDiscoverability)) $DREADDiscoverability = "10";
                                    if (is_null($OWASPSkillLevel)) $OWASPSkillLevel = "10";
                                    if (is_null($OWASPMotive)) $OWASPMotive = "10";
                                    if (is_null($OWASPOpportunity)) $OWASPOpportunity = "10";
                                    if (is_null($OWASPSize)) $OWASPSize = "10";
                                    if (is_null($OWASPEaseOfDiscovery)) $OWASPEaseOfDiscovery = "10";
                                    if (is_null($OWASPEaseOfExploit)) $OWASPEaseOfExploit = "10";
                                    if (is_null($OWASPAwareness)) $OWASPAwareness = "10";
                                    if (is_null($OWASPIntrusionDetection)) $OWASPIntrusionDetection = "10";
                                    if (is_null($OWASPLossOfConfidentiality)) $OWASPLossOfConfidentiality = "10";
                                    if (is_null($OWASPLossOfIntegrity)) $OWASPLossOfIntegrity = "10";
                                    if (is_null($OWASPLossOfAvailability)) $OWASPLossOfAvailability = "10";
                                    if (is_null($OWASPLossOfAccountability)) $OWASPLossOfAccountability = "10";
                                    if (is_null($OWASPFinancialDamage)) $OWASPFinancialDamage = "10";
                                    if (is_null($OWASPReputationDamage)) $OWASPReputationDamage = "10";
                                    if (is_null($OWASPNonCompliance)) $OWASPNonCompliance = "10";
                                    if (is_null($OWASPPrivacyViolation)) $OWASPPrivacyViolation = "10";
                                    if (is_null($custom)) $custom = false;
                            
                            $scoringData = array(
                                'scoring_method' => $scoring_method_id,

                                // Classic Risk Scoring Inputs
                                'CLASSIClikelihood' => $CLASSIClikelihood,
                                'CLASSICimpact' =>  $CLASSICimpact,

                                // CVSS Risk Scoring Inputs
                                'CVSSAccessVector' => $CVSSAccessVector,
                                'CVSSAccessComplexity' => $CVSSAccessComplexity,
                                'CVSSAuthentication' => $CVSSAuthentication,
                                'CVSSConfImpact' => $CVSSConfImpact,
                                'CVSSIntegImpact' => $CVSSIntegImpact,
                                'CVSSAvailImpact' => $CVSSAvailImpact,
                                'CVSSExploitability' => $CVSSExploitability,
                                'CVSSRemediationLevel' => $CVSSRemediationLevel,
                                'CVSSReportConfidence' => $CVSSReportConfidence,
                                'CVSSCollateralDamagePotential' => $CVSSCollateralDamagePotential,
                                'CVSSTargetDistribution' => $CVSSTargetDistribution,
                                'CVSSConfidentialityRequirement' => $CVSSConfidentialityRequirement,
                                'CVSSIntegrityRequirement' => $CVSSIntegrityRequirement,
                                'CVSSAvailabilityRequirement' => $CVSSAvailabilityRequirement,
                                // DREAD Risk Scoring Inputs
                                'DREADDamage' => $DREADDamage,
                                'DREADReproducibility' => $DREADReproducibility,
                                'DREADExploitability' => $DREADExploitability,
                                'DREADAffectedUsers' => $DREADAffectedUsers,
                                'DREADDiscoverability' => $DREADDiscoverability,
                                // OWASP Risk Scoring Inputs
                                'OWASPSkillLevel' => $OWASPSkillLevel,
                                'OWASPMotive' => $OWASPMotive,
                                'OWASPOpportunity' => $OWASPOpportunity,
                                'OWASPSize' => $OWASPSize,
                                'OWASPEaseOfDiscovery' => $OWASPEaseOfDiscovery,
                                'OWASPEaseOfExploit' => $OWASPEaseOfExploit,
                                'OWASPAwareness' => $OWASPAwareness,
                                'OWASPIntrusionDetection' => $OWASPIntrusionDetection,
                                'OWASPLossOfConfidentiality' => $OWASPLossOfConfidentiality,
                                'OWASPLossOfIntegrity' => $OWASPLossOfIntegrity,
                                'OWASPLossOfAvailability' => $OWASPLossOfAvailability,
                                'OWASPLossOfAccountability' => $OWASPLossOfAccountability,
                                'OWASPFinancialDamage' => $OWASPFinancialDamage,
                                'OWASPReputationDamage' => $OWASPReputationDamage,
                                'OWASPNonCompliance' => $OWASPNonCompliance,
                                'OWASPPrivacyViolation' => $OWASPPrivacyViolation,

                                // Custom Risk Scoring
                                'Custom' => $custom,
                            );
                            
                            list($assessment_scoring_id, $calculated_risk) = add_assessment_scoring($scoringData);
                        /************ End saving assessment scoring *************/
                        
                        
                        // Add the question
                        $stmt = $db->prepare("INSERT INTO `assessment_answers` (`assessment_id`, `question_id`, `answer`, `submit_risk`, `risk_subject`, `risk_score`, `assessment_scoring_id`, `risk_owner`, `assets`, `order`) 
                            SELECT :assessment_id, :question_id, :answer, :submit_risk, :risk_subject, :risk_score, :assessment_scoring_id, :risk_owner, :assets, max(`order`) + 1
                            FROM `assessment_answers`
                            WHERE `assessment_id`=:assessment_id and `question_id`=:question_id;
                        ");
                        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
                        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
                        $stmt->bindParam(":answer", $answer, PDO::PARAM_STR, 1000);
                        $stmt->bindParam(":submit_risk", $submit_risk, PDO::PARAM_STR, 1000);
                        $stmt->bindParam(":risk_subject", $risk_subject, PDO::PARAM_STR, 1000);
                        $stmt->bindParam(":risk_score", $calculated_risk, PDO::PARAM_STR, 1000);
                        $stmt->bindParam(":assessment_scoring_id", $assessment_scoring_id, PDO::PARAM_INT);
                        $stmt->bindParam(":risk_owner", $risk_owner, PDO::PARAM_INT);
                        $stmt->bindParam(":assets", $assets, PDO::PARAM_STR, 1000);
                        $stmt->execute();
                        
                        // Get the id of the last insert
                        $answer_id = $db->lastInsertId();
                        
                        $assessment_question_list[$assessment_id][] = array(
                            'question' => $question,
                            'question_id' => $question_id,
                            'answer' => $answer,
                            'answer_id' => $answer_id,
                        );

                    }
                    
                }
            }
            // Otherwise this is the first line
            else
            {
                // Set the first line to false
                $first_line = false;
            }
        }
        
        set_alert(true, "good", $lang['AssessmentSuccessImport']);

    }else{
    
        set_alert(true, "bad", $lang['AssessmentFileRequired']);
    
    }
    
    // Close the temporary file
    fclose($handle);
    
    // Close the database connection
    db_close($db);
}

/*************************************
 * FUNCTION: GET ASSESSMENT CONTACTS *
 *************************************/
function get_assessment_contacts(){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.*, t2.name as manager_name FROM `assessment_contacts` t1 LEFT JOIN `user` t2 on t1.manager=t2.value;");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/**********************************************
 * FUNCTION: DISPLAY ASSESSMENT CONTACTS HTML *
 **********************************************/
function display_assessment_contacts(){
    global $lang;
    global $escaper;

    $tableID = "assessment-contacts-table";
    
    echo "
        <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead >
                <tr>
                    <th>Company</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Contact Manager</th>
                    <th width=\"78px\">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            var pageLength = 10;
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/assessment/contacts',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
                
            })
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-contact--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisContact'])."</label>
              </div>

              <input type=\"hidden\" name=\"contact_id\" value=\"\" />
              <div class=\"form-group text-center control-delete-actions\">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_contact\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>

          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.contact-delete-btn', function(){
                \$('#aseessment-contact--delete [name=contact_id]').val(\$(this).data('id'));
            })
        </script>
    ";
}

/**************************************************
 * FUNCTION: DISPLAY ASSESSMENT CONTACTS ADD FORM *
 **************************************************/
function display_assessment_contacts_add(){
    global $lang;
    global $escaper;
    
    echo "
        <form name=\"add_user\" method=\"post\" action=\"\">
            <table cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                <tbody>
                <tr>
                    <td colspan=\"2\"><h4>". $escaper->escapeHtml($lang['AddNewAssessmentContact']) ."</h4></td></tr>
                <tr>
                    <td>Company:&nbsp;</td>
                    <td>
                        <input required name=\"company\" maxlength=\"255\" size=\"100\" value=\"\" type=\"text\">
                    </td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Name']) .":&nbsp;</td><td><input required name=\"name\" maxlength=\"255\" size=\"100\" value=\"\" type=\"text\"></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['EmailAddress']) .":&nbsp;</td><td><input name=\"email\" maxlength=\"200\" value=\"\" size=\"100\" type=\"email\" required></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Phone']) .":&nbsp;</td><td><input name=\"phone\" maxlength=\"200\" value=\"\" size=\"100\" type=\"text\" required></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['ContactManager']) .":&nbsp;</td>
                    <td>". create_dropdown("user", NULL, "manager", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) ."</td>
                </tr>
                </tbody>
            </table>
            <br>
            <input value=\"". $escaper->escapeHtml($lang['Add']) ."\" name=\"add_contact\" type=\"submit\">
        </form>    
    ";
    
}

/***************************************************
 * FUNCTION: DISPLAY ASSESSMENT CONTACTS EDIT FORM *
 ***************************************************/
function display_assessment_contacts_edit($id){
    global $lang;
    global $escaper;
    
    $id = (int)$id;
    
    $assessment_contact = get_assessment_contact($id);
    
    echo "
        <form name=\"add_user\" method=\"post\" action=\"\">
            <table cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                <tbody>
                <tr>
                    <td colspan=\"2\"><h4>". $escaper->escapeHtml($lang['UpdateAssessmentContact']) ."</h4></td></tr>
                <tr>
                    <td>Company:&nbsp;</td>
                    <td>
                        <input required name=\"company\" maxlength=\"255\" size=\"100\" value=\"".$escaper->escapeHtml($assessment_contact['company'])."\" type=\"text\">
                    </td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Name']) .":&nbsp;</td><td><input required name=\"name\" maxlength=\"255\" size=\"100\" value=\"".$escaper->escapeHtml($assessment_contact['name'])."\" type=\"text\"></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['EmailAddress']) .":&nbsp;</td><td><input name=\"email\" maxlength=\"200\" value=\"".$escaper->escapeHtml($assessment_contact['email'])."\" size=\"100\" type=\"email\" required></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Phone']) .":&nbsp;</td><td><input name=\"phone\" maxlength=\"200\" value=\"".$escaper->escapeHtml($assessment_contact['phone'])."\" size=\"100\" type=\"text\" required></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['ContactManager']) .":&nbsp;</td>
                    <td>". create_dropdown("user", (int)$assessment_contact['manager'], "manager", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) ."</td>
                </tr>
                </tbody>
            </table>
            <br>
            <input value=\"". $escaper->escapeHtml($lang['Update']) ."\" name=\"update_contact\" type=\"submit\">
        </form>    
    ";
    
}

/******************************************
 * FUNCTION: GET ASSESSMENT CONTACT BY ID *
 ******************************************/
function get_assessment_contact($id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assessment_contacts` WHERE `id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return $array;    
}

/*********************************************
 * FUNCTION: GET ASSESSMENT CONTACT BY EMAIL *
 *********************************************/
function get_assessment_contact_by_email($email){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assessment_contacts` WHERE `email`=:email;");
    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 100);
    $stmt->execute();

    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return $array;    
}

/******************************************
 * FUNCTION: CHECK IF EXIST CONTACT EMAIL *
 ******************************************/
function check_exist_contact_email($email, $contact_id=false)
{
    // Check for adding a contact
    if($contact_id === false){
        // Return true if contact email exists
        if(get_assessment_contact_by_email($email))
        {
            return true;
        }
        // Return true if contact email no exist
        else
        {
            return false;
        }
    }
    // Check for updating a contact
    else
    {
        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("SELECT id FROM `assessment_contacts` WHERE `email`=:email and id<>:contact_id;");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR, 100);
        $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
        $stmt->execute();

        $array = $stmt->fetch();

        // Close the database connection
        db_close($db);
        
        // Return true if contact email exists
        if($array)
        {
            return true;
        }
        // Return true if contact email no exist
        else
        {
            return false;
        }
        
    }
}

/******************************************
 * FUNCTION: ADD A NEW ASSESSMENT CONTACT *
 ******************************************/
function add_assessment_contact($company, $name, $email, $phone, $manager){
    
    
    if($company && $name && $email && $phone){
        // Create a unique salt for this contact
        $salt = generate_token(20);

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("INSERT IGNORE INTO `assessment_contacts` SET `company` = :company, `name` = :name, `email` = :email, `phone` = :phone, `manager`=:manager, `salt`=:salt;");
        $stmt->bindParam(":company", $company, PDO::PARAM_STR, 255);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, 255);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR, 255);
        $stmt->bindParam(":phone", $phone, PDO::PARAM_STR, 255);
        $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
        $stmt->bindParam(":salt", $salt, PDO::PARAM_STR);
        $stmt->execute();
        
        // Get the id of the last insert
        $contact_id = $db->lastInsertId();
        
        // Close the database connection
        db_close($db);

        if (encryption_extra())
        {
            // Include the encryption extra
            require_once(realpath(__DIR__ . '/../encryption/index.php'));

            // Add the new encrypted contact
            add_contact_enc($salt, $email, $contact_id, false);
        }
        
        $message = "A assessment contact was added for ID \"{$contact_id}\" by username \"" . $_SESSION['user']."\".";
        write_log($contact_id+1000, $_SESSION['uid'], $message, 'contact');
        
    }
    else{
        $contact_id = false;
    }

    return $contact_id;
}

/*****************************************
 * FUNCTION: UPDATE A ASSESSMENT CONTACT *
 *****************************************/
function update_assessment_contact($contact_id, $company, $name, $email, $phone, $manager){
    if($company && $name && $email && $phone){
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE `assessment_contacts` SET `company` = :company, `name` = :name, `email` = :email, `phone` = :phone, `manager`=:manager WHERE id=:id;");
        $stmt->bindParam(":company", $company, PDO::PARAM_STR, 255);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR, 255);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR, 255);
        $stmt->bindParam(":phone", $phone, PDO::PARAM_STR, 255);
        $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
        $stmt->bindParam(":id", $contact_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        $message = "A assessment contact was updated for ID \"{$contact_id}\" by username \"" . $_SESSION['user']."\".";
        write_log($contact_id+1000, $_SESSION['uid'], $message, 'contact');

        return true;
    }
    else{
        return false;
    }
}

/*********************************************
 * FUNCTION: DELETE ASSESSMENT CONTACT BY ID *
 *********************************************/
function delete_assessment_contact($id){
    // Open the database connection
    $db = db_open();

    // Delete answers for the question
    $stmt = $db->prepare("DELETE FROM `assessment_contacts` WHERE `id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // If encryption is enabled
    if(encryption_extra()){
        //Include the encryption extra
        require_once(realpath(__DIR__ . '/../encryption/index.php'));

        // Delete contact for the question
        delete_contact_enc($id);
    }
    
    // Delete templates with contact ID from questionnaires
    $stmt = $db->prepare("DELETE FROM `questionnaire_id_template` WHERE `contact_id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete questionnaire responses with contact ID 
    $stmt = $db->prepare("DELETE t1 FROM `questionnaire_responses` t1, `questionnaire_tracking` t2 WHERE t1.questionnaire_tracking_id=t2.id and t2.`contact_id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete sent questionnaires with contact ID 
    $stmt = $db->prepare("DELETE FROM `questionnaire_tracking` WHERE `contact_id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $message = "A assessment contact and related responses and tracking infos was deleted for contact ID \"{$id}\" by username \"" . $_SESSION['user']."\".";
    write_log($id+1000, $_SESSION['uid'], $message, 'contact');

    // Close the database connection
    db_close($db);
}

/*********************************************
 * FUNCTION: DISPLAY IMPORTING CONTACTS HTML *
 *********************************************/
function display_assessment_contacts_import(){
    global $escaper, $lang;
    
    echo "<h4>" . $escaper->escapeHtml($lang['ImportAssessmentContacts']) . "</h4>\n";
    
    // Check if a file not uploaded or failed to upload the file.
    if(!isset($_POST['import_assessment_contacts']) || !($filename = upload_assessment_import_file($_FILES['file']))){
        echo "<form method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
            echo $escaper->escapeHtml($lang['ImportCsvXlsFile']).":<br />\n";
            echo "<input type=\"file\" name=\"file\" />\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"import_assessment_contacts\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
    // Check if a file uploaded
    else{
        echo "<form method=\"post\" action=\"\" >\n";
            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed \">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>" . $escaper->escapeHtml($lang['SimpleRiskColumnMapping']) . "</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;
            
            $header_columns = get_assessment_column_headers(sys_get_temp_dir() . "/" . $filename);
            
            
            // For each column in the file
            foreach ($header_columns as $column)
            {
                echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    assessment_contact_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                echo "</tr>\n";

                // Increment the column counter
                $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div><input type=\"hidden\" name=\"filename\" value=\"{$filename}\"></div>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"mapping_assessment_contacts\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
}

/*************************************************
 * FUNCTION: DISPLAY IMPORING QUESTIONNAIRE HTML *
 *************************************************/
function display_assessment_questionnaire_import(){
    global $escaper, $lang;
    
    echo "<h4>" . $escaper->escapeHtml($lang['ImportAssessmentQuestionnaireQuestions']) . "</h4>\n";
    
    // Check if a file not uploaded or failed to upload the file.
    if(!isset($_POST['import_assessment_questionnaires']) || !($filename = upload_assessment_import_file($_FILES['file']))){
        echo "<form method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
            echo $escaper->escapeHtml($lang['ImportCsvXlsFile']).":<br />\n";
            echo "<input type=\"file\" name=\"file\" />\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"import_assessment_questionnaires\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
    // Check if a file uploaded
    else{
        echo "<form method=\"post\" action=\"\" >\n";
            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed \">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>" . $escaper->escapeHtml($lang['SimpleRiskColumnMapping']) . "</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;
            
            $header_columns = get_assessment_column_headers(sys_get_temp_dir() . "/" . $filename);
            
            
            // For each column in the file
            foreach ($header_columns as $column)
            {
                echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    assessment_questionnaire_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                echo "</tr>\n";

                // Increment the column counter
                $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div><input type=\"hidden\" name=\"filename\" value=\"{$filename}\"></div>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"mapping_assessment_questionnaires\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
}

/*********************************************************
 * FUNCTION: GET ASSESSMENT CONTACT COLUMN NAME DROPDOWN *
 *********************************************************/
function assessment_contact_column_name_dropdown($name){
    global $escaper;

    // Get the list of SimpleRisk fields
    $fields = assessment_contact_fields();

    echo "<select name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        if(isset($mappings[$name]) && $mappings[$name] == $key){
            $selected = "selected";
        }else{
            $selected = "";
        }
        echo "<option {$selected} value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/*********************************************************
 * FUNCTION: GET ASSESSMENT CONTACT COLUMN NAME DROPDOWN *
 *********************************************************/
function assessment_questionnaire_column_name_dropdown($name){
    global $escaper;

    // Get the list of SimpleRisk fields
    $fields = assessment_questionnaire_fields();

    echo "<select name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        if(isset($mappings[$name]) && $mappings[$name] == $key){
            $selected = "selected";
        }else{
            $selected = "";
        }
        echo "<option {$selected} value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/*******************************
 * FUNCTION: SIMPLERISK FIELDS *
 *******************************/
function assessment_contact_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'contact_company'      => $lang['Company'],
        'contact_name'  => $lang['Name'],
        'contact_email' => $lang['EmailAddress'],
        'contact_phone' => $lang['Phone'],
        'contact_manager' => $lang['ContactManager'],
    );

    // Return the fields array
    return $fields;
}

/*******************************
 * FUNCTION: SIMPLERISK FIELDS *
 *******************************/
function assessment_questionnaire_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'questionnaire_question'                        => $lang['Question'],
        'questionnaire_answers'                         => $lang['Answers'],
        'riskscoring_scoring_method'                    =>$lang['RiskScoringMethod'],
        'riskscoring_calculated_risk'                   =>$lang['CalculatedRisk'],
        'riskscoring_CLASSIC_likelihood'                =>$lang['CurrentLikelihood'],
        'riskscoring_CLASSIC_impact'                    =>$lang['CurrentImpact'],
        'riskscoring_CVSS_AccessVector'                 =>'CVSS-'.$lang['AttackVector'],
        'riskscoring_CVSS_AccessComplexity'             =>'CVSS-'.$lang['AttackComplexity'],
        'riskscoring_CVSS_Authentication'               =>'CVSS-'.$lang['Authentication'],
        'riskscoring_CVSS_ConfImpact'                   =>'CVSS-'.$lang['ConfidentialityImpact'],
        'riskscoring_CVSS_IntegImpact'                  =>'CVSS-'.$lang['IntegrityImpact'],
        'riskscoring_CVSS_AvailImpact'                  =>'CVSS-'.$lang['AvailabilityImpact'],
        'riskscoring_CVSS_Exploitability'               =>'CVSS-'.$lang['Exploitability'],
        'riskscoring_CVSS_RemediationLevel'             =>'CVSS-'.$lang['RemediationLevel'],
        'riskscoring_CVSS_ReportConfidence'             =>'CVSS-'.$lang['ReportConfidence'],
        'riskscoring_CVSS_CollateralDamagePotential'    =>'CVSS-'.$lang['CollateralDamagePotential'],
        'riskscoring_CVSS_TargetDistribution'           =>'CVSS-'.$lang['TargetDistribution'],
        'riskscoring_CVSS_ConfidentialityRequirement'   =>'CVSS-'.$lang['ConfidentialityRequirement'],
        'riskscoring_CVSS_IntegrityRequirement'         =>'CVSS-'.$lang['IntegrityRequirement'],
        'riskscoring_CVSS_AvailabilityRequirement'      =>'CVSS-'.$lang['AvailabilityRequirement'],
        'riskscoring_DREAD_DamagePotential'             =>'DREAD-'.$lang['DamagePotential'],
        'riskscoring_DREAD_Reproducibility'             =>'DREAD-'.$lang['Reproducibility'],
        'riskscoring_DREAD_Exploitability'              =>'DREAD-'.$lang['Exploitability'],
        'riskscoring_DREAD_AffectedUsers'               =>'DREAD-'.$lang['AffectedUsers'],
        'riskscoring_DREAD_Discoverability'             =>'DREAD-'.$lang['Discoverability'],
        'riskscoring_OWASP_SkillLevel'                  =>'OWASP-'.$lang['SkillLevel'],
        'riskscoring_OWASP_Motive'                      =>'OWASP-'.$lang['Motive'],
        'riskscoring_OWASP_Opportunity'                 =>'OWASP-'.$lang['Opportunity'],
        'riskscoring_OWASP_Size'                        =>'OWASP-'.$lang['Size'],
        'riskscoring_OWASP_EaseOfDiscovery'             =>'OWASP-'.$lang['EaseOfDiscovery'],
        'riskscoring_OWASP_EaseOfExploit'               =>'OWASP-'.$lang['EaseOfExploit'],
        'riskscoring_OWASP_Awareness'                   =>'OWASP-'.$lang['Awareness'],
        'riskscoring_OWASP_IntrusionDetection'          =>'OWASP-'.$lang['IntrusionDetection'],
        'riskscoring_OWASP_LossOfConfidentiality'       =>'OWASP-'.$lang['LossOfConfidentiality'],
        'riskscoring_OWASP_LossOfIntegrity'             =>'OWASP-'.$lang['LossOfIntegrity'],
        'riskscoring_OWASP_LossOfAvailability'          =>'OWASP-'.$lang['LossOfAvailability'],
        'riskscoring_OWASP_LossOfAccountability'        =>'OWASP-'.$lang['LossOfAccountability'],
        'riskscoring_OWASP_FinancialDamage'             =>'OWASP-'.$lang['FinancialDamage'],
        'riskscoring_OWASP_ReputationDamage'            =>'OWASP-'.$lang['ReputationDamage'],
        'riskscoring_OWASP_NonCompliance'               =>'OWASP-'.$lang['NonCompliance'],
        'riskscoring_OWASP_PrivacyViolation'            =>'OWASP-'.$lang['PrivacyViolation'],
        'riskscoring_Custom'                            =>$lang['CustomValue'],
    );

    // Return the fields array
    return $fields;
}

/**********************************************
 * FUNCTION: GET ASSESSMENT HEADERS FROM FILE *
 **********************************************/
function get_assessment_column_headers($filepath){
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Check if file format is csv.
    if($extension == "csv"){
        // If we can read the file
        if (($handle = fopen($filepath, 'rb')) !== FALSE)
        {
            // If we can get the first line in the file
            if (($headers = fgetcsv($handle, 0, ",")) !== FALSE)
            {
                // Close the file
                fclose($handle);
                return $headers;
            }

            // Close the file
            fclose($handle);
        }
    }
    
    // Check if file format is xls or xlsx.
    elseif($extension == "xls" || $extension == "xlsx"){
        require_once(realpath(__DIR__ . '/includes/PHPExcel/PHPExcel.php'));
        $objPHPExcel = PHPExcel_IOFactory::load($filepath);
        $sheetData = $objPHPExcel->setActiveSheetIndex()->toArray(null,true,true,true);
        return isset($sheetData[1]) ? array_values($sheetData[1]) : FALSE;
    }
    
    // Return false
    return FALSE;
}

/****************************************************
 * FUNCTION: UPLOAD A FILE FOR IMPORTING ASSESSMENT *
 ****************************************************/
function upload_assessment_import_file($file){
    
    if (isset($file) && $file['name'] != "")
    {
        // Allowed file types
        $allowed_types = get_file_types();
        
        // If the file type is appropriate
        if (in_array($file['type'], $allowed_types))
        {
            // Get the maximum upload file size
            $max_upload_size = get_setting("max_upload_size");

            // If the file size is less than the maximum
            if ($file['size'] < $max_upload_size)
            {
                // If there was no error with the upload
                if ($file['error'] == 0)
                {
                    // Read the file
                    $content = fopen($file['tmp_name'], 'rb');

                    // Create a unique file name
                    $unique_name = generate_token(20);
                    
                    $filename = $unique_name. "." .pathinfo($file['name'], PATHINFO_EXTENSION);
                    
                    $target_path = sys_get_temp_dir() . '/' . $filename;
                    
                    // Rename the file
                    move_uploaded_file($file['tmp_name'], $target_path);

                    // Return the CSV column headers
                    return $filename;
                }
                // Otherwise, file upload error
                else
                {
                    // Display an alert
                    set_alert(true, "bad", "There was an error with the file upload.");
                    return 0;
                }
            }
            // Otherwise, file too big
            else
            {
                // Display an alert
                set_alert(true, "bad", "The uploaded file was too big.");
                return 0;
            }
        }
        // Otherwise, file type not supported
        else
        {
            // Display an alert
            set_alert(true, "bad", "The file type of the uploaded file (" . $file['type'] . ") is not supported.");
            return 0;
        }
    }
    // Otherwise, upload error
    else
    {
        // Display an alert
        set_alert(true, "bad", "There was an error with the uploaded file.");
        return 0;
    }
    
}

/*******************************
 * FUNCTION: GET MAPPING VALUE *
 *******************************/
if(!function_exists('get_mapping_value')){
    function get_mapping_value($prefix, $type, $mappings, $csv_line)
    {
        // Create the search term
        $search_term = $prefix . $type;

        // Search mappings array for the search term
        $column = array_search($search_term, $mappings);

        // If the search term was mapped
        if ($column != false)
        {
            // Remove col_ to get the id value
            $key = (int)preg_replace("/^col_/", "", $column);

            // The value is located in that spot in the array
            $value = $csv_line[$key];

            // Return the value
            return $value;
        }
        else return null;
    }
}

/******************************************************
 * FUNCTION: IMPORT ASSESSMENT CONTACTS WITH MAPPINGS *
 ******************************************************/
function mapping_assessment_contacts(){
    $filename = $_POST['filename'];
    $filepath = sys_get_temp_dir() . "/" . $filename;
    
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Detect first line
    $first_line = isset($_POST['import_first']) ? true : false;
    
    // If we can read the temporary file
    if (($handle = fopen($filepath, "r")) !== FALSE)
    {
        $rows = array();
        if($extension == "csv"){
            while (($csv_line = fgetcsv($handle)) !== FALSE)
            {
                $rows[] = $csv_line;
            }
        }else{
            require_once(realpath(__DIR__ . '/includes/PHPExcel/PHPExcel.php'));
            $objPHPExcel = PHPExcel_IOFactory::load($filepath);
            $sheetData = $objPHPExcel->setActiveSheetIndex(0)->toArray(null,true,true,true);
            foreach($sheetData as $row){
                $rows[] = array_values($row);
            }
        }
        // If we don't import the first line
        if(!$first_line){
            // Remove first row
            array_shift($rows);
        }
        
        // Copy posted values into a new array
        $mappings = $_POST;
        
        // While we have lines in the file to read
        foreach ($rows as $csv_line)
        {
            // Get the name
            $company = get_mapping_value("contact_", "company", $mappings, $csv_line);
            $name = get_mapping_value("contact_", "name", $mappings, $csv_line);
            $email = get_mapping_value("contact_", "email", $mappings, $csv_line);
            $phone = get_mapping_value("contact_", "phone", $mappings, $csv_line);

            /*****************
             *** ADD ASSET ***
             *****************/
            // If the name is not null (we don't want to add assets without a name)
            if (!is_null($company) || !is_null($name) || !is_null($email) || !is_null($phone))
            {
                // If the contact email no exists
                if(!check_exist_contact_email($email)){
                    // Get the asset values
                    $contact_manager    = get_mapping_value("contact_", "manager", $mappings, $csv_line);
                    
                    $contact_manager_id = get_value_by_name("user", $contact_manager);
                    
                    add_assessment_contact($company, $name, $email, $phone, $contact_manager_id);
                }
            }
        }
    }

    // Close the temporary file
    fclose($handle);
}

/************************************************************
 * FUNCTION: IMPORT ASSESSMENT QUESTIONNAIRES WITH MAPPINGS *
 ************************************************************/
function mapping_assessment_questionnaires(){
    global $escaper, $lang;
    
    $filename = $_POST['filename'];
    $filepath = sys_get_temp_dir() . "/" . $filename;
    
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Detect first line
    $first_line = isset($_POST['import_first']) ? true : false;
    
    // If we can read the temporary file
    if (($handle = fopen($filepath, "r")) !== FALSE)
    {
        $rows = array();
        if($extension == "csv"){
            while (($csv_line = fgetcsv($handle)) !== FALSE)
            {
                $rows[] = $csv_line;
            }
        }else{
            require_once(realpath(__DIR__ . '/includes/PHPExcel/PHPExcel.php'));
            $objPHPExcel = PHPExcel_IOFactory::load($filepath);
            $sheetData = $objPHPExcel->setActiveSheetIndex(0)->toArray(null,true,true,true);
            foreach($sheetData as $row){
                $rows[] = array_values($row);
            }
        }
        // If we don't import the first line
        if(!$first_line){
            // Remove first row
            array_shift($rows);
        }
        
        // Copy posted values into a new array
        $mappings = $_POST;
        
        // While we have lines in the file to read
        foreach ($rows as $csv_line)
        {
            // Get the subject
            $question = get_mapping_value("questionnaire_", "question", $mappings, $csv_line);
//echo $question;exit;
            if(!$question){
//                continue;
            }
            
            /************ Save risk scoring *************/
                // Get the risk scoring method
                $scoring_method = get_mapping_value("riskscoring_", "scoring_method", $mappings, $csv_line);

                // Get the scoring method id
                $scoring_method_id = get_value_by_name("scoring_methods", $scoring_method);

                // If the scoring method is null
                if (is_null($scoring_method_id))
                {
                    // Set the scoring method to Classic
                    $scoring_method_id = 1;
                }

                // Classic Risk Scoring Inputs
                $CLASSIClikelihood = get_mapping_value("riskscoring_", "CLASSIC_likelihood", $mappings, $csv_line);
                $CLASSIClikelihood = (int) get_value_by_name('likelihood', $CLASSIClikelihood);

                $CLASSICimpact = get_mapping_value("riskscoring_", "CLASSIC_impact", $mappings, $csv_line);
                $CLASSICimpact = (int) get_value_by_name('impact', $CLASSICimpact);

                // CVSS Risk Scoring Inputs
                $CVSSAccessVector = get_mapping_value("riskscoring_", "CVSS_AccessVector", $mappings, $csv_line);
                $CVSSAccessComplexity = get_mapping_value("riskscoring_", "CVSS_AccessComplexity", $mappings, $csv_line);
                $CVSSAuthentication = get_mapping_value("riskscoring_", "CVSS_Authentication", $mappings, $csv_line);
                $CVSSConfImpact = get_mapping_value("riskscoring_", "CVSS_ConfImpact", $mappings, $csv_line);
                $CVSSIntegImpact = get_mapping_value("riskscoring_", "CVSS_IntegImpact", $mappings, $csv_line);
                $CVSSAvailImpact = get_mapping_value("riskscoring_", "CVSS_AvailImpact", $mappings, $csv_line);
                $CVSSExploitability = get_mapping_value("riskscoring_", "CVSS_Exploitability", $mappings, $csv_line);
                $CVSSRemediationLevel = get_mapping_value("riskscoring_", "CVSS_RemediationLevel", $mappings, $csv_line);
                $CVSSReportConfidence = get_mapping_value("riskscoring_", "CVSS_ReportConfidence", $mappings, $csv_line);
                $CVSSCollateralDamagePotential = get_mapping_value("riskscoring_", "CVSS_CollateralDamagePotential", $mappings, $csv_line);
                $CVSSTargetDistribution = get_mapping_value("riskscoring_", "CVSS_TargetDistribution", $mappings, $csv_line);
                $CVSSConfidentialityRequirement = get_mapping_value("riskscoring_", "CVSS_ConfidentialityRequirement", $mappings, $csv_line);
                $CVSSIntegrityRequirement = get_mapping_value("riskscoring_", "CVSS_IntegrityRequirement", $mappings, $csv_line);
                $CVSSAvailabilityRequirement = get_mapping_value("riskscoring_", "CVSS_AvailabilityRequirement", $mappings, $csv_line);

                // DREAD Risk Scoring Inputs
                $DREADDamage = (int) get_mapping_value("riskscoring_", "DREAD_DamagePotential", $mappings, $csv_line);
                $DREADReproducibility = (int) get_mapping_value("riskscoring_", "DREAD_Reproducibility", $mappings, $csv_line);
                $DREADExploitability = (int) get_mapping_value("riskscoring_", "DREAD_Exploitability", $mappings, $csv_line);
                $DREADAffectedUsers = (int) get_mapping_value("riskscoring_", "DREAD_AffectedUsers", $mappings, $csv_line);
                $DREADDiscoverability = (int) get_mapping_value("riskscoring_", "DREAD_Discoverability", $mappings, $csv_line);

                // OWASP Risk Scoring Inputs
                $OWASPSkillLevel = (int) get_mapping_value("riskscoring_", "OWASP_SkillLevel", $mappings, $csv_line);
                $OWASPMotive = (int) get_mapping_value("riskscoring_", "OWASP_Motive", $mappings, $csv_line);
                $OWASPOpportunity = (int) get_mapping_value("riskscoring_", "OWASP_Opportunity", $mappings, $csv_line);
                $OWASPSize = (int) get_mapping_value("riskscoring_", "OWASP_Size", $mappings, $csv_line);
                $OWASPEaseOfDiscovery = (int) get_mapping_value("riskscoring_", "OWASP_EaseOfDiscovery", $mappings, $csv_line);
                $OWASPEaseOfExploit = (int) get_mapping_value("riskscoring_", "OWASP_EaseOfExploit", $mappings, $csv_line);
                $OWASPAwareness = (int) get_mapping_value("riskscoring_", "OWASP_Awareness", $mappings, $csv_line);
                $OWASPIntrusionDetection = (int) get_mapping_value("riskscoring_", "OWASP_IntrusionDetection", $mappings, $csv_line);
                $OWASPLossOfConfidentiality = (int) get_mapping_value("riskscoring_", "OWASP_LossOfConfidentiality", $mappings, $csv_line);
                $OWASPLossOfIntegrity = (int) get_mapping_value("riskscoring_", "OWASP_LossOfIntegrity", $mappings, $csv_line);
                $OWASPLossOfAvailability = (int) get_mapping_value("riskscoring_", "OWASP_LossOfAvailability", $mappings, $csv_line);
                $OWASPLossOfAccountability = (int) get_mapping_value("riskscoring_", "OWASP_LossOfAccountability", $mappings, $csv_line);
                $OWASPFinancialDamage = (int) get_mapping_value("riskscoring_", "OWASP_FinancialDamage", $mappings, $csv_line);
                $OWASPReputationDamage = (int) get_mapping_value("riskscoring_", "OWASP_ReputationDamage", $mappings, $csv_line);
                $OWASPNonCompliance = (int) get_mapping_value("riskscoring_", "OWASP_NonCompliance", $mappings, $csv_line);
                $OWASPPrivacyViolation = (int) get_mapping_value("riskscoring_", "OWASP_PrivacyViolation", $mappings, $csv_line);

                // Custom Risk Scoring
                $custom = (float) get_mapping_value("riskscoring_", "Custom", $mappings, $csv_line);

                // Set null values to default
                if (is_null($CLASSIClikelihood)) $CLASSIClikelihood = "5";
                if (is_null($CLASSICimpact)) $CLASSICimpact = "5";
                if (is_null($CVSSAccessVector)) $CVSSAccessVector = "N";
                if (is_null($CVSSAccessComplexity)) $CVSSAccessComplexity = "L";
                if (is_null($CVSSAuthentication)) $CVSSAuthentication = "N";
                if (is_null($CVSSConfImpact)) $CVSSConfImpact = "C";
                if (is_null($CVSSIntegImpact)) $CVSSIntegImpact = "C";
                if (is_null($CVSSAvailImpact)) $CVSSAvailImpact = "C";
                if (is_null($CVSSExploitability)) $CVSSExploitability = "ND";
                if (is_null($CVSSRemediationLevel)) $CVSSRemediationLevel = "ND";
                if (is_null($CVSSReportConfidence)) $CVSSReportConfidence = "ND";
                if (is_null($CVSSCollateralDamagePotential)) $CVSSCollateralDamagePotential = "ND";
                if (is_null($CVSSTargetDistribution)) $CVSSTargetDistribution = "ND";
                if (is_null($CVSSConfidentialityRequirement)) $CVSSConfidentialityRequirement = "ND";
                if (is_null($CVSSIntegrityRequirement)) $CVSSIntegrityRequirement = "ND";
                if (is_null($CVSSAvailabilityRequirement)) $CVSSAvailabilityRequirement = "ND";
                if (is_null($DREADDamage)) $DREADDamage = "10";
                if (is_null($DREADReproducibility)) $DREADReproducibility = "10";
                if (is_null($DREADExploitability)) $DREADExploitability = "10";
                if (is_null($DREADAffectedUsers)) $DREADAffectedUsers = "10";
                if (is_null($DREADDiscoverability)) $DREADDiscoverability = "10";
                if (is_null($OWASPSkillLevel)) $OWASPSkillLevel = "10";
                if (is_null($OWASPMotive)) $OWASPMotive = "10";
                if (is_null($OWASPOpportunity)) $OWASPOpportunity = "10";
                if (is_null($OWASPSize)) $OWASPSize = "10";
                if (is_null($OWASPEaseOfDiscovery)) $OWASPEaseOfDiscovery = "10";
                if (is_null($OWASPEaseOfExploit)) $OWASPEaseOfExploit = "10";
                if (is_null($OWASPAwareness)) $OWASPAwareness = "10";
                if (is_null($OWASPIntrusionDetection)) $OWASPIntrusionDetection = "10";
                if (is_null($OWASPLossOfConfidentiality)) $OWASPLossOfConfidentiality = "10";
                if (is_null($OWASPLossOfIntegrity)) $OWASPLossOfIntegrity = "10";
                if (is_null($OWASPLossOfAvailability)) $OWASPLossOfAvailability = "10";
                if (is_null($OWASPLossOfAccountability)) $OWASPLossOfAccountability = "10";
                if (is_null($OWASPFinancialDamage)) $OWASPFinancialDamage = "10";
                if (is_null($OWASPReputationDamage)) $OWASPReputationDamage = "10";
                if (is_null($OWASPNonCompliance)) $OWASPNonCompliance = "10";
                if (is_null($OWASPPrivacyViolation)) $OWASPPrivacyViolation = "10";
                if (is_null($custom)) $custom = "";

                // Submit risk scoring
                list($questionnaire_scoring_id, $calculated_risk) = add_assessment_questionnaire_scoring(array(
                    'scoring_method' => $scoring_method_id,
                    'CLASSIClikelihood' => $CLASSIClikelihood,
                    'CLASSICimpact' => $CLASSICimpact,
                    
                    'CVSSAccessVector' => $CVSSAccessVector,
                    'CVSSAccessComplexity' => $CVSSAccessComplexity,
                    'CVSSAuthentication' => $CVSSAuthentication,
                    'CVSSConfImpact' => $CVSSConfImpact,
                    
                    'CVSSIntegImpact' => $CVSSIntegImpact,
                    'CVSSAvailImpact' => $CVSSAvailImpact,
                    'CVSSExploitability' => $CVSSExploitability,
                    'CVSSRemediationLevel' => $CVSSRemediationLevel,
                    'CVSSReportConfidence' => $CVSSReportConfidence,
                    'CVSSCollateralDamagePotential' => $CVSSCollateralDamagePotential,
                    'CVSSTargetDistribution' => $CVSSTargetDistribution,
                    'CVSSConfidentialityRequirement' => $CVSSConfidentialityRequirement,
                    'CVSSIntegrityRequirement' => $CVSSIntegrityRequirement,
                    'CVSSAvailabilityRequirement' => $CVSSAvailabilityRequirement,
                    
                    'DREADDamage' => $DREADDamage,
                    'DREADReproducibility' => $DREADReproducibility,
                    'DREADExploitability' => $DREADExploitability,
                    'DREADAffectedUsers' => $DREADAffectedUsers,
                    'DREADDiscoverability' => $DREADDiscoverability,
                    
                    'OWASPSkillLevel' => $OWASPSkillLevel,
                    'OWASPMotive' => $OWASPMotive,
                    'OWASPOpportunity' => $OWASPOpportunity,
                    'OWASPSize' => $OWASPSize,
                    'OWASPEaseOfDiscovery' => $OWASPEaseOfDiscovery,
                    'OWASPEaseOfExploit' => $OWASPEaseOfExploit,
                    'OWASPAwareness' => $OWASPAwareness,
                    'OWASPIntrusionDetection' => $OWASPIntrusionDetection,
                    'OWASPLossOfConfidentiality' => $OWASPLossOfConfidentiality,
                    'OWASPLossOfIntegrity' => $OWASPLossOfIntegrity,
                    'OWASPLossOfAvailability' => $OWASPLossOfAvailability,
                    'OWASPLossOfAccountability' => $OWASPLossOfAccountability,
                    'OWASPFinancialDamage' => $OWASPFinancialDamage,
                    'OWASPReputationDamage' => $OWASPReputationDamage,
                    'OWASPNonCompliance' => $OWASPNonCompliance,
                    'OWASPPrivacyViolation' => $OWASPPrivacyViolation,
                    'Custom' => $custom
                ));
                
            // Get the name
            $question = get_mapping_value("questionnaire_", "question", $mappings, $csv_line);
            $answers = get_mapping_value("questionnaire_", "answers", $mappings, $csv_line);
            
            // Split answers string by "::"
            if($answers){
                $answers_array = explode("::", $answers);
            }else{
                $answers_array = array();
            }

            $questionData = array(
                'question' => $question,
                'questionnaire_scoring_id' => $questionnaire_scoring_id,
            );

            add_questionnaire_question_answers($questionData, $answers_array);
            /************ End saving risk scoring *************/

        }
    }

    // Close the temporary file
    fclose($handle);
    
    return true;
}

/********************************************
 * FUNCTION: DISPLAY QUESTINNAIRE QUESTIONS *
 ********************************************/
function display_questionnaire_questions(){
    global $lang;
    global $escaper;

    $tableID = "assessment-questionnaire-questions-table";
    
    echo "
        <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead>
                <tr >
                    <th>". $escaper->escapeHtml($lang['QuestionnaireQuestions']) ."</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: false,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/assessment/questionnaire_questions',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
                
            })
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-questionnaire-question--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisQuestion'])."</label>
              </div>

              <input type=\"hidden\" name=\"question_id\" value=\"\" />
              <div class=\"form-group text-center \">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_questionnaire_question\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>
          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.delete-btn', function(){
                \$('#aseessment-questionnaire-question--delete [name=question_id]').val(\$(this).data('id'));
            })
        </script>
    ";
}

/***************************************************
 * FUNCTION: GET ASSESSMENT QUESTINNAIRE QUESTIONS *
 ***************************************************/
function get_assessment_questionnaire_questions($start=0, $length=-1){
    // Open the database connection
    $db = db_open();
    
    /*** Get questions by $start and $lengh ***/
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS t1.id, t1.question, t1.questionnaire_scoring_id, 
            t2.scoring_method, t2.calculated_risk, t2.CLASSIC_likelihood, t2.CLASSIC_impact, t2.CVSS_AccessVector, t2.CVSS_AccessComplexity, t2.CVSS_Authentication, t2.CVSS_ConfImpact, t2.CVSS_IntegImpact, t2.CVSS_AvailImpact, t2.CVSS_Exploitability, t2.CVSS_RemediationLevel, t2.CVSS_ReportConfidence, t2.CVSS_CollateralDamagePotential, t2.CVSS_TargetDistribution, t2.CVSS_ConfidentialityRequirement, t2.CVSS_IntegrityRequirement, t2.CVSS_AvailabilityRequirement, t2.DREAD_DamagePotential, t2.DREAD_Reproducibility, t2.DREAD_Exploitability, t2.DREAD_AffectedUsers, t2.DREAD_Discoverability, t2.OWASP_SkillLevel, t2.OWASP_Motive, t2.OWASP_Opportunity, t2.OWASP_Size, t2.OWASP_EaseOfDiscovery, t2.OWASP_EaseOfExploit, t2.OWASP_Awareness, t2.OWASP_IntrusionDetection, t2.OWASP_LossOfConfidentiality, t2.OWASP_LossOfIntegrity, t2.OWASP_LossOfAvailability, t2.OWASP_LossOfAccountability, t2.OWASP_FinancialDamage, t2.OWASP_ReputationDamage, t2.OWASP_NonCompliance, t2.OWASP_PrivacyViolation, t2.Custom              
        FROM `questionnaire_questions` t1
            LEFT JOIN `questionnaire_scoring` t2 on t1.questionnaire_scoring_id = t2.id
        ORDER BY 
            t1.question \n";
    if($length != -1){
        $sql .= " LIMIT {$start}, {$length}; ";
    }
    
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    /******************************************/

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetchColumn();

    $question_ids = array();
    $fullQuestions = array();
    foreach($questions as $question){
        $question_ids[] = $question['id'];
        $fullQuestions[$question['id']] = $question;
    }
    
    /*** Get answers by question IDs ***/
    $sql = "SELECT * FROM `questionnaire_answers`";
    if($question_ids){
        $sql .= " WHERE question_id in (". implode(",", $question_ids) .") ";
    }
    $sql .= " ORDER BY ordering; ";
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    /*****************************************/
    
    foreach($answers as $answer){
        $question_id = $answer['question_id'];
        if(isset($fullQuestions[$question_id]['answers'])){
            $fullQuestions[$question_id]['answers'][] = $answer;
        }else{
            $fullQuestions[$question_id]['answers'] = array($answer);
        }
    }
    
    // Close the database connection
    db_close($db);
    
    return array($recordsTotal, $fullQuestions);
}

/****************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE QUESTION ADD FORM *
 ****************************************************/
function display_questionnaire_question_add(){
    global $lang;
    global $escaper;

    echo "
        <form method='post' class='risk-scoring-container' autocomplete='off'>
            <h4>".$escaper->escapeHtml($lang['NewQuestionnaireQuestion'])." <button type='submit' name='add_questionnaire_question' class='btn pull-right'>". $escaper->escapeHtml($lang['Add']) ."</button></h4>
            <div class='clearfix'></div>
            <br>
            <div class='row-fluid'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Question']) .":&nbsp; </strong>
                <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='question' required class='form-control' style='max-width: none'></div>
            </div>";
            print_score_html_from_assessment();
        echo "
            <div id='questionnaire-answers-container'>
                <div class='row-fluid'>
                    <div class=\"span1\">
                        <strong>".$escaper->escapeHtml($lang['Answers']).":</strong>
                    </div>
                    <div class=\"span10\">
                        <input type='text' placeholder='".$escaper->escapeHtml($lang['Answer'])."' required name='answer[]' style='max-width: none'>
                    </div>
                    <div class=\"span1\">
                    </div>
                </div>
            </div>
            <div class='row-fluid'>
                <div class=\"span1\">
                </div>
                <div class=\"span10 \">
                </div>
                <div class=\"span1 text-center\">
                    <a href='#' class='add-row'><img src=\"../images/plus.png\" width=\"15px\" height=\"15px\" /></a>
                </div>
            </div>
        </form>
        <div class='hide' id='adding-row'>
            <div class='row-fluid answer-row'>
                <div class=\"span1\">
                </div>
                <div class=\"span10\">
                    <input type='text' placeholder='". $escaper->escapeHtml($lang['Answer']) ."' required name='answer[]' style='max-width: none'>
                </div>
                <div class=\"span1 text-center\">
                    <a href='#' class='delete-row'><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\" /></a>
                </div>
            </div>        
        </div>
    ";
    echo "
        <script>
            \$(document).ready(function(){
                \$('.add-row').click(function(){
                    \$('#questionnaire-answers-container').append(\$('#adding-row').html())
                })
                \$('body').on('click', '.delete-row', function(){
                    \$(this).parents('.answer-row').remove();
                })
            })
        </script>
    ";
}

/**************************************************
 * FUNCTION: ADD ASSESSMENT QUESTIONNAIRE SCORING *
 **************************************************/
function add_assessment_questionnaire_scoring($data){

    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$data['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIC_likelihood = (int)$data['CLASSIClikelihood'];
    $CLASSIC_impact =(int) $data['CLASSICimpact'];

    // CVSS Risk Scoring Inputs
    $AccessVector = $data['CVSSAccessVector'];
    $AccessComplexity = $data['CVSSAccessComplexity'];
    $Authentication = $data['CVSSAuthentication'];
    $ConfImpact = $data['CVSSConfImpact'];
    $IntegImpact = $data['CVSSIntegImpact'];
    $AvailImpact = $data['CVSSAvailImpact'];
    $Exploitability = $data['CVSSExploitability'];
    $RemediationLevel = $data['CVSSRemediationLevel'];
    $ReportConfidence = $data['CVSSReportConfidence'];
    $CollateralDamagePotential = $data['CVSSCollateralDamagePotential'];
    $TargetDistribution = $data['CVSSTargetDistribution'];
    $ConfidentialityRequirement = $data['CVSSConfidentialityRequirement'];
    $IntegrityRequirement = $data['CVSSIntegrityRequirement'];
    $AvailabilityRequirement = $data['CVSSAvailabilityRequirement'];

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)$data['DREADDamage'];
    $DREADReproducibility = (int)$data['DREADReproducibility'];
    $DREADExploitability = (int)$data['DREADExploitability'];
    $DREADAffectedUsers = (int)$data['DREADAffectedUsers'];
    $DREADDiscoverability = (int)$data['DREADDiscoverability'];

    // OWASP Risk Scoring Inputs
    $OWASPSkill = (int)$data['OWASPSkillLevel'];
    $OWASPMotive = (int)$data['OWASPMotive'];
    $OWASPOpportunity = (int)$data['OWASPOpportunity'];
    $OWASPSize = (int)$data['OWASPSize'];
    $OWASPDiscovery = (int)$data['OWASPEaseOfDiscovery'];
    $OWASPExploit = (int)$data['OWASPEaseOfExploit'];
    $OWASPAwareness = (int)$data['OWASPAwareness'];
    $OWASPIntrusionDetection = (int)$data['OWASPIntrusionDetection'];
    $OWASPLossOfConfidentiality = (int)$data['OWASPLossOfConfidentiality'];
    $OWASPLossOfIntegrity = (int)$data['OWASPLossOfIntegrity'];
    $OWASPLossOfAvailability = (int)$data['OWASPLossOfAvailability'];
    $OWASPLossOfAccountability = (int)$data['OWASPLossOfAccountability'];
    $OWASPFinancialDamage = (int)$data['OWASPFinancialDamage'];
    $OWASPReputationDamage = (int)$data['OWASPReputationDamage'];
    $OWASPNonCompliance = (int)$data['OWASPNonCompliance'];
    $OWASPPrivacyViolation = (int)$data['OWASPPrivacyViolation'];

    // Custom Risk Scoring
    $custom = (float)$data['Custom'];

    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
        // Calculate the risk via classic method
        $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact`) VALUES (:scoring_method, :calculated_risk, :CLASSIC_likelihood, :CLASSIC_impact)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
        $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
    }
    // If the scoring method is CVSS (2)
    else if ($scoring_method == 2)
    {
        // Get the numeric values for the CVSS submission
        $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
        $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
        $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
        $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
        $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
        $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
        $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
        $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
        $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
        $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
        $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
        $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
        $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
        $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

        // Calculate the risk via CVSS method
        $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `CVSS_AccessVector`, `CVSS_AccessComplexity`, `CVSS_Authentication`, `CVSS_ConfImpact`, `CVSS_IntegImpact`, `CVSS_AvailImpact`, `CVSS_Exploitability`, `CVSS_RemediationLevel`, `CVSS_ReportConfidence`, `CVSS_CollateralDamagePotential`, `CVSS_TargetDistribution`, `CVSS_ConfidentialityRequirement`, `CVSS_IntegrityRequirement`, `CVSS_AvailabilityRequirement`) VALUES (:scoring_method, :calculated_risk, :CVSS_AccessVector, :CVSS_AccessComplexity, :CVSS_Authentication, :CVSS_ConfImpact, :CVSS_IntegImpact, :CVSS_AvailImpact, :CVSS_Exploitability, :CVSS_RemediationLevel, :CVSS_ReportConfidence, :CVSS_CollateralDamagePotential, :CVSS_TargetDistribution, :CVSS_ConfidentialityRequirement, :CVSS_IntegrityRequirement, :CVSS_AvailabilityRequirement)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
    }
    // If the scoring method is DREAD (3)
    else if ($scoring_method == 3)
    {
        // Calculate the risk via DREAD method
        $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `DREAD_DamagePotential`, `DREAD_Reproducibility`, `DREAD_Exploitability`, `DREAD_AffectedUsers`, `DREAD_Discoverability`) VALUES (:scoring_method, :calculated_risk, :DREAD_DamagePotential, :DREAD_Reproducibility, :DREAD_Exploitability, :DREAD_AffectedUsers, :DREAD_Discoverability)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4){
        $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
        $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

        // Average the threat agent and vulnerability factors to get the likelihood
        $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

        $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
        $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

        // Average the technical and business impacts to get the impact
        $OWASP_impact = ($technical_impact + $business_impact)/2;

        // Calculate the overall OWASP risk score
        $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `OWASP_SkillLevel`, `OWASP_Motive`, `OWASP_Opportunity`, `OWASP_Size`, `OWASP_EaseOfDiscovery`, `OWASP_EaseOfExploit`, `OWASP_Awareness`, `OWASP_IntrusionDetection`, `OWASP_LossOfConfidentiality`, `OWASP_LossOfIntegrity`, `OWASP_LossOfAvailability`, `OWASP_LossOfAccountability`, `OWASP_FinancialDamage`, `OWASP_ReputationDamage`, `OWASP_NonCompliance`, `OWASP_PrivacyViolation`) VALUES (:scoring_method, :calculated_risk, :OWASP_SkillLevel, :OWASP_Motive, :OWASP_Opportunity, :OWASP_Size, :OWASP_EaseOfDiscovery, :OWASP_EaseOfExploit, :OWASP_Awareness, :OWASP_IntrusionDetection, :OWASP_LossOfConfidentiality, :OWASP_LossOfIntegrity, :OWASP_LossOfAvailability, :OWASP_LossOfAccountability, :OWASP_FinancialDamage, :OWASP_ReputationDamage, :OWASP_NonCompliance, :OWASP_PrivacyViolation)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5){
        // If the custom value is not between 0 and 10
        if (!(($custom >= 0) && ($custom <= 10)))
        {
            // Set the custom value to 10
            $custom = get_setting('default_risk_score');
        }

        // Calculated risk is the custom value
        $calculated_risk = $custom;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `Custom`) VALUES (:scoring_method, :calculated_risk, :Custom)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
    }
    // Otherwise
    else
    {
        return false;
    }

    // Add the risk score
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    $last_insert_id = $db->lastInsertId();

    return array($last_insert_id, $calculated_risk);
}

/*********************************
 * FUNCTION: UPDATE RISK SCORING *
 *********************************/
function update_assessment_questionnaire_scoring($id, $data)
{
    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$data['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIC_likelihood = (int)$data['CLASSIClikelihood'];
    $CLASSIC_impact =(int) $data['CLASSICimpact'];

    // CVSS Risk Scoring Inputs
    $AccessVector = $data['CVSSAccessVector'];
    $AccessComplexity = $data['CVSSAccessComplexity'];
    $Authentication = $data['CVSSAuthentication'];
    $ConfImpact = $data['CVSSConfImpact'];
    $IntegImpact = $data['CVSSIntegImpact'];
    $AvailImpact = $data['CVSSAvailImpact'];
    $Exploitability = $data['CVSSExploitability'];
    $RemediationLevel = $data['CVSSRemediationLevel'];
    $ReportConfidence = $data['CVSSReportConfidence'];
    $CollateralDamagePotential = $data['CVSSCollateralDamagePotential'];
    $TargetDistribution = $data['CVSSTargetDistribution'];
    $ConfidentialityRequirement = $data['CVSSConfidentialityRequirement'];
    $IntegrityRequirement = $data['CVSSIntegrityRequirement'];
    $AvailabilityRequirement = $data['CVSSAvailabilityRequirement'];

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)$data['DREADDamage'];
    $DREADReproducibility = (int)$data['DREADReproducibility'];
    $DREADExploitability = (int)$data['DREADExploitability'];
    $DREADAffectedUsers = (int)$data['DREADAffectedUsers'];
    $DREADDiscoverability = (int)$data['DREADDiscoverability'];

    // OWASP Risk Scoring Inputs
    $OWASPSkill = (int)$data['OWASPSkillLevel'];
    $OWASPMotive = (int)$data['OWASPMotive'];
    $OWASPOpportunity = (int)$data['OWASPOpportunity'];
    $OWASPSize = (int)$data['OWASPSize'];
    $OWASPDiscovery = (int)$data['OWASPEaseOfDiscovery'];
    $OWASPExploit = (int)$data['OWASPEaseOfExploit'];
    $OWASPAwareness = (int)$data['OWASPAwareness'];
    $OWASPIntrusionDetection = (int)$data['OWASPIntrusionDetection'];
    $OWASPLossOfConfidentiality = (int)$data['OWASPLossOfConfidentiality'];
    $OWASPLossOfIntegrity = (int)$data['OWASPLossOfIntegrity'];
    $OWASPLossOfAvailability = (int)$data['OWASPLossOfAvailability'];
    $OWASPLossOfAccountability = (int)$data['OWASPLossOfAccountability'];
    $OWASPFinancialDamage = (int)$data['OWASPFinancialDamage'];
    $OWASPReputationDamage = (int)$data['OWASPReputationDamage'];
    $OWASPNonCompliance = (int)$data['OWASPNonCompliance'];
    $OWASPPrivacyViolation = (int)$data['OWASPPrivacyViolation'];

    // Custom Risk Scoring
    $custom = (float)$data['Custom'];


    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
            // Calculate the risk via classic method
            $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CLASSIC_likelihood=:CLASSIC_likelihood, CLASSIC_impact=:CLASSIC_impact WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
            $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
    }
    // If the scoring method is CVSS (2)
    else if ($scoring_method == 2)
    {
            // Get the numeric values for the CVSS submission
            $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
            $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
            $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
            $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
            $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
            $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
            $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
            $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
            $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
            $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
            $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
            $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
            $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
            $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

            // Calculate the risk via CVSS method
            $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);
            

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CVSS_AccessVector=:CVSS_AccessVector, CVSS_AccessComplexity=:CVSS_AccessComplexity, CVSS_Authentication=:CVSS_Authentication, CVSS_ConfImpact=:CVSS_ConfImpact, CVSS_IntegImpact=:CVSS_IntegImpact, CVSS_AvailImpact=:CVSS_AvailImpact, CVSS_Exploitability=:CVSS_Exploitability, CVSS_RemediationLevel=:CVSS_RemediationLevel, CVSS_ReportConfidence=:CVSS_ReportConfidence, CVSS_CollateralDamagePotential=:CVSS_CollateralDamagePotential, CVSS_TargetDistribution=:CVSS_TargetDistribution, CVSS_ConfidentialityRequirement=:CVSS_ConfidentialityRequirement, CVSS_IntegrityRequirement=:CVSS_IntegrityRequirement, CVSS_AvailabilityRequirement=:CVSS_AvailabilityRequirement WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
    }
    // If the scoring method is DREAD (3)
    else if ($scoring_method == 3)
    {
            // Calculate the risk via DREAD method
            $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, DREAD_DamagePotential=:DREAD_DamagePotential, DREAD_Reproducibility=:DREAD_Reproducibility, DREAD_Exploitability=:DREAD_Exploitability, DREAD_AffectedUsers=:DREAD_AffectedUsers, DREAD_Discoverability=:DREAD_Discoverability WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4)
    {
            $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
            $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

            // Average the threat agent and vulnerability factors to get the likelihood
            $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

            $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
            $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

            // Average the technical and business impacts to get the impact
            $OWASP_impact = ($technical_impact + $business_impact)/2;

            // Calculate the overall OWASP risk score
            $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, OWASP_SkillLevel=:OWASP_SkillLevel, OWASP_Motive=:OWASP_Motive, OWASP_Opportunity=:OWASP_Opportunity, OWASP_Size=:OWASP_Size, OWASP_EaseOfDiscovery=:OWASP_EaseOfDiscovery, OWASP_EaseOfExploit=:OWASP_EaseOfExploit, OWASP_Awareness=:OWASP_Awareness, OWASP_IntrusionDetection=:OWASP_IntrusionDetection, OWASP_LossOfConfidentiality=:OWASP_LossOfConfidentiality, OWASP_LossOfIntegrity=:OWASP_LossOfIntegrity, OWASP_LossOfAvailability=:OWASP_LossOfAvailability, OWASP_LossOfAccountability=:OWASP_LossOfAccountability, OWASP_FinancialDamage=:OWASP_FinancialDamage, OWASP_ReputationDamage=:OWASP_ReputationDamage, OWASP_NonCompliance=:OWASP_NonCompliance, OWASP_PrivacyViolation=:OWASP_PrivacyViolation WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5)
    {
            // If the custom value is not between 0 and 10
            if (!(($custom >= 0) && ($custom <= 10)))
            {
                    // Set the custom value to 10
                    $custom = 10;
            }

            // Calculated risk is the custom value
            $calculated_risk = $custom;

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, Custom=:Custom WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
    }
    // Otherwise
    else
    {
            return false;
    }

    // Add the risk score
    $stmt->execute();

    // Close the database connection
    db_close($db);
    
    return $calculated_risk;
}

/****************************************************
 * FUNCTION: ADD QUESTIONNAIRE QUESTION AND ANSWERS *
 ****************************************************/
function add_questionnaire_question_answers($question, $answers){
    // Open the database connection
    $db = db_open();

    // Add a question
    $stmt = $db->prepare("INSERT INTO `questionnaire_questions` SET `question` = :question, `questionnaire_scoring_id` = :questionnaire_scoring_id ");
    $stmt->bindParam(":question", $question['question'], PDO::PARAM_STR);
    $stmt->bindParam(":questionnaire_scoring_id", $question['questionnaire_scoring_id'], PDO::PARAM_INT);
    $stmt->execute();
    $question_id = $db->lastInsertId();

    // Add answers
    foreach($answers as $answer){
        if(!$answer)
            continue;
        $stmt = $db->prepare("INSERT INTO `questionnaire_answers` SET `question_id` = :question_id, `answer` = :answer");
        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":answer", $answer, PDO::PARAM_STR);
        $stmt->execute();
    }
    
    $message = "A assessment questionnaire question was added for ID \"{$question_id}\" by username \"" . $_SESSION['user']."\".";
    write_log($question_id+1000, $_SESSION['uid'], $message, 'questionnaire_question');

}

/****************************************************
 * FUNCTION: ADD QUESTIONNAIRE QUESTION AND ANSWERS *
 ****************************************************/
function update_questionnaire_question_answers($question_id, $question, $answers){
    // Open the database connection
    $db = db_open();

    // Add a question
    $stmt = $db->prepare("UPDATE `questionnaire_questions` SET `question` = :question WHERE id=:question_id; ");
    $stmt->bindParam(":question", $question['question'], PDO::PARAM_STR);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete all current answers by question ID
    $stmt = $db->prepare("DELETE FROM `questionnaire_answers` WHERE question_id=:question_id; ");
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_STR);
    $stmt->execute();
    
    // Add answers
    foreach($answers as $key => $answer){
        $stmt = $db->prepare("INSERT INTO `questionnaire_answers` SET `question_id` = :question_id, `answer` = :answer, `ordering` = :ordering");
        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":answer", $answer, PDO::PARAM_STR);
        $stmt->bindParam(":ordering", $key, PDO::PARAM_INT);
        $stmt->execute();
    }

    $message = "A assessment questionnaire question was updated for ID \"{$question_id}\" by username \"" . $_SESSION['user']."\".";
    write_log($question_id+1000, $_SESSION['uid'], $message, 'questionnaire_question');
}

/*******************************************
 * FUNCTION: DELETE QUESTIONNAIRE QUESTION *
 *******************************************/
function delete_questionnaire_question($question_id){
    // Open the database connection
    $db = db_open();

    // Delete questionnaire answers that has question_id
    $stmt = $db->prepare("DELETE FROM `questionnaire_answers` WHERE question_id=:question_id;");
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete questionnaire answers that has question_id
    $stmt = $db->prepare("DELETE FROM `questionnaire_questions` WHERE id=:question_id;");
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    $message = "A assessment questionnaire question was deleted for ID \"{$question_id}\" by username \"" . $_SESSION['user']."\".";
    write_log($question_id+1000, $_SESSION['uid'], $message, 'questionnaire_question');
}

/*******************************************************
 * FUNCTION: GET QUESTIONNAIRE QUESTION BY QUESTION ID *
 *******************************************************/
function get_questionnaire_question($question_id){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id, t1.question, t1.questionnaire_scoring_id,
            t2.scoring_method, t2.calculated_risk, t2.CLASSIC_likelihood, t2.CLASSIC_impact, t2.CVSS_AccessVector, t2.CVSS_AccessComplexity, t2.CVSS_Authentication, t2.CVSS_ConfImpact, t2.CVSS_IntegImpact, t2.CVSS_AvailImpact, t2.CVSS_Exploitability, t2.CVSS_RemediationLevel, t2.CVSS_ReportConfidence, t2.CVSS_CollateralDamagePotential, t2.CVSS_TargetDistribution, t2.CVSS_ConfidentialityRequirement, t2.CVSS_IntegrityRequirement, t2.CVSS_AvailabilityRequirement, t2.DREAD_DamagePotential, t2.DREAD_Reproducibility, t2.DREAD_Exploitability, t2.DREAD_AffectedUsers, t2.DREAD_Discoverability, t2.OWASP_SkillLevel, t2.OWASP_Motive, t2.OWASP_Opportunity, t2.OWASP_Size, t2.OWASP_EaseOfDiscovery, t2.OWASP_EaseOfExploit, t2.OWASP_Awareness, t2.OWASP_IntrusionDetection, t2.OWASP_LossOfConfidentiality, t2.OWASP_LossOfIntegrity, t2.OWASP_LossOfAvailability, t2.OWASP_LossOfAccountability, t2.OWASP_FinancialDamage, t2.OWASP_ReputationDamage, t2.OWASP_NonCompliance, t2.OWASP_PrivacyViolation, t2.Custom    
        FROM `questionnaire_questions` t1 
            LEFT JOIN `questionnaire_scoring` t2 ON t1.questionnaire_scoring_id=t2.id
        WHERE 
            t1.id = :question_id
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "
        SELECT answer
        FROM `questionnaire_answers`
        WHERE 
            question_id = :question_id
        ORDER BY
            ordering
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    $question['answers'] = $answers;
    
    return $question;    
}

/******************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE QUESTION EDIT FORM *
 ******************************************************/
function display_questionnaire_question_edit($question_id){
    global $lang;
    global $escaper;
    
    // Get a question and answers by question ID
    $question = get_questionnaire_question($question_id);

    echo "
        <form method='post' class='risk-scoring-container' autocomplete='off'>
            <h4>".$escaper->escapeHtml($lang['EditQuestionnaireQuestion'])." <button type='submit' name='edit_questionnaire_question' class='btn pull-right'>". $escaper->escapeHtml($lang['Update']) ."</button></h4>
            <div class='clearfix'></div>
            <br>
            <input type='hidden' name='scoring_id' value='". $question['questionnaire_scoring_id'] ."'>
            <div class='row-fluid'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Question']) .":&nbsp; </strong>
                <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='question' required class='form-control' value='". $escaper->escapeHtml($question['question']) ."' style='max-width: none'></div>
            </div>";
            print_score_html_from_assessment($question['scoring_method'], $question['CLASSIC_likelihood'], $question['CLASSIC_impact'], $question['CVSS_AccessVector'], $question['CVSS_AccessComplexity'], $question['CVSS_Authentication'], $question['CVSS_ConfImpact'], $question['CVSS_IntegImpact'], $question['CVSS_AvailImpact'], $question['CVSS_Exploitability'], $question['CVSS_RemediationLevel'], $question['CVSS_ReportConfidence'], $question['CVSS_CollateralDamagePotential'], $question['CVSS_TargetDistribution'], $question['CVSS_ConfidentialityRequirement'], $question['CVSS_IntegrityRequirement'], $question['CVSS_AvailabilityRequirement'], $question['DREAD_DamagePotential'], $question['DREAD_Reproducibility'], $question['DREAD_Exploitability'], $question['DREAD_AffectedUsers'], $question['DREAD_Discoverability'], $question['OWASP_SkillLevel'], $question['OWASP_Motive'], $question['OWASP_Opportunity'], $question['OWASP_Size'], $question['OWASP_EaseOfDiscovery'], $question['OWASP_EaseOfExploit'], $question['OWASP_Awareness'], $question['OWASP_IntrusionDetection'], $question['OWASP_LossOfConfidentiality'], $question['OWASP_LossOfIntegrity'], $question['OWASP_LossOfAvailability'], $question['OWASP_LossOfAccountability'], $question['OWASP_FinancialDamage'], $question['OWASP_ReputationDamage'], $question['OWASP_NonCompliance'], $question['OWASP_PrivacyViolation'], $question['Custom']);
        echo "
            <div id='questionnaire-answers-container'>";
                foreach($question['answers'] as $key => $answer){
                    if($key == 0){
                        echo "<div class='row-fluid'>
                                <div class=\"span1\">
                                    <strong>".$escaper->escapeHtml($lang['Answers']).":</strong>
                                </div>
                                <div class=\"span10\">
                                    <input type='text' placeholder='".$escaper->escapeHtml($lang['Answer'])."' value='". $escaper->escapeHtml($answer['answer']) ."' required name='answer[]' style='max-width: none'>
                                </div>
                                <div class=\"span1\">
                                </div>
                            </div>";
                    }else{
                        echo "<div class='row-fluid answer-row'>
                                <div class=\"span1\">
                                </div>
                                <div class=\"span10\">
                                    <input type='text' placeholder='".$escaper->escapeHtml($lang['Answer'])."' value='". $escaper->escapeHtml($answer['answer']) ."' required name='answer[]' style='max-width: none'>
                                </div>
                                <div class=\"span1 text-center\">
                                    <a href='#' class='delete-row'><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\" /></a>
                                </div>
                            </div>";
                    }
                }
            echo "</div>
            <div class='row-fluid'>
                <div class=\"span1\">
                </div>
                <div class=\"span10 \">
                </div>
                <div class=\"span1 text-center\">
                    <a href='#' class='add-row'><img src=\"../images/plus.png\" width=\"15px\" height=\"15px\" /></a>
                </div>
            </div>
        </form>
        <div class='hide' id='adding-row'>
            <div class='row-fluid answer-row'>
                <div class=\"span1\">
                </div>
                <div class=\"span10\">
                    <input type='text' placeholder='". $escaper->escapeHtml($lang['Answer']) ."' required name='answer[]' style='max-width: none'>
                </div>
                <div class=\"span1 text-center\">
                    <a href='#' class='delete-row'><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\" /></a>
                </div>
            </div>        
        </div>
    ";
    echo "
        <script>
            \$(document).ready(function(){
                \$('.add-row').click(function(){
                    \$('#questionnaire-answers-container').append(\$('#adding-row').html())
                })
                \$('body').on('click', '.delete-row', function(){
                    \$(this).parents('.answer-row').remove();
                })
            })
        </script>
    ";
}

/*****************************************
 * FUNCTION: PROCESS ASSESSMENT CONTACTS *
 *****************************************/
function process_assessment_contact(){
    global $lang, $escaper;
    
    $process = false;
    
    // Check if new contact was sent
    if(isset($_POST['add_contact'])){
        $company = $escaper->escapeHtml($_POST['company']);
        $name = $escaper->escapeHtml($_POST['name']);
        $email = $escaper->escapeHtml($_POST['email']);
        $phone = $escaper->escapeHtml($_POST['phone']);
        $manager = (int)$_POST['manager'];
        
        // If contact email no exists in table
        if(!check_exist_contact_email($email)){
            // Check if success to add a contact
            if(add_assessment_contact($company, $name, $email, $phone, $manager)){
                set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentContactCreated']));
            }else{
                set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
            }
        }
        // If contact email exists in table
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['ContactEmailAlreadyInUse']));
        }
        $process = true;
    }
    // Check if a contact was edited
    elseif(isset($_POST['update_contact'])){
        $id = (int)$_GET['id'];
        $company = $escaper->escapeHtml($_POST['company']);
        $name = $escaper->escapeHtml($_POST['name']);
        $email = $escaper->escapeHtml($_POST['email']);
        $phone = $escaper->escapeHtml($_POST['phone']);
        $manager = (int)$_POST['manager'];
        
        // If contact email no exists in table
        if(!check_exist_contact_email($email, $id)){
            // Check if success to update a contact
            if(update_assessment_contact($id, $company, $name, $email, $phone, $manager)){
                set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentContactUpdated']));
            }else{
                set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
            }
        }
        // If contact email exists in table
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['ContactEmailAlreadyInUse']));
        }

        $process = true;
    }
    // Check if new contact was deleted
    elseif(isset($_POST['delete_contact'])){
        $contact_id = (int)$_POST['contact_id'];
        // Delete an assessment contact
        delete_assessment_contact($contact_id);
        set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        $process = true;
    }
    return $process;
}
 
/********************************************************
 * FUNCTION: PROCESS ASSESSMENT QUESTIONNAIRE QUESTIONS *
 ********************************************************/
function process_assessment_questionnaire_questions(){
    global $lang, $escaper;

    $process = false;
    
    // Check if add questionnaire question
    if(isset($_POST['add_questionnaire_question'])){
        $question_text = $_POST['question'];
        $answers = $_POST['answer'];
        
        // Check if a question and at least an answer exists.
        if($question_text && !empty($answers[0])){
            // Create a questionnaire scoring
            $data = get_assessment_scores_array()[0];
            list($questionnaire_scoring_id, $risk_score) = add_assessment_questionnaire_scoring($data);
            
            $questionData = array(
                'question' => $question_text,
                'questionnaire_scoring_id' => $questionnaire_scoring_id,
            );
            
            add_questionnaire_question_answers($questionData, $answers);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidQuestionOrAnswers']));
        }
        $process = true;
    }
    // Check if edit questionnaire question
    elseif(isset($_POST['edit_questionnaire_question'])){
        $querstion_id   = (int)$_GET['id'];
        $question_text  = $_POST['question'];
        $answers        = $_POST['answer'];
        $scoring_id     = (int)$_POST['scoring_id'];
        
        // Check if a question and at least an answer exists.
        if($question_text && !empty($answers[0])){
            // Update questionnaire question scoring
            $data = get_assessment_scores_array()[0];
            update_assessment_questionnaire_scoring($scoring_id, $data);
            
            // Update questionnaire question and answers
            $questionData = array(
                'question' => $question_text
            );
            
            update_questionnaire_question_answers($querstion_id, $questionData, $answers);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidQuestionOrAnswers']));
        }
        $process = true;
    }
    // Check if delete a questionnaire question
    elseif(isset($_POST['delete_questionnaire_question'])){
        // Check if a question and at least an answer exists.
        if($question_id = (int)$_POST['question_id']){
            delete_questionnaire_question($question_id);
            set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
        }
        $process = true;
    }
    
    return $process;
}

/***************************************
 * FUNCTION: PROCESS ASSESSMENT IMPORT *
 ***************************************/
function process_assessment_import(){
    global $lang, $escaper;
    
    $process = false;
    
    // Check if a file for assessment contact has been imported
    if(isset($_POST['mapping_assessment_contacts']))
    {
        mapping_assessment_contacts();

        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentContactsImported']));
        
        $process = true;
    }
    elseif(isset($_POST['mapping_assessment_questionnaires'])){
        mapping_assessment_questionnaires();
        
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentQuestionnaireQuestionsAndAnwersImported']));
        
        $process = true;
    }
    
    return $process;
}

/********************************************************
 * FUNCTION: PROCESS ASSESSMENT QUESTIONNAIRE TEMPLATES *
 ********************************************************/
function process_assessment_questionnaire_templates(){
    global $lang, $escaper;

    $process = false;
    
    // Check if add questionnaire template
    if(isset($_POST['add_questionnaire_template'])){
        $name               = $escaper->escapeHtml($_POST['name']);
        $template_questions = isset($_POST['template_questions']) ? $_POST['template_questions'] : array();
        
        // Check if a name exists.
        if($name){
            add_questionnaire_template($name, $template_questions);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['TemplateNameRequired']));
        }
        $process = true;
    }
    // Check if edit questionnaire question
    elseif(isset($_POST['edit_questionnaire_template'])){
        $template_id   = (int)$_GET['id'];
        $name          = $escaper->escapeHtml($_POST['name']);
        $questions     = empty($_POST['template_questions']) ? [] : $_POST['template_questions'];
        
        // Check if template name exists.
        if($name){
            // Update questionnaire template
            
            update_questionnaire_template($template_id, $name, $questions);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['TemplateNameRequired']));
        }
        $process = true;
    }
    // Check if delete a questionnaire template
    elseif(isset($_POST['delete_questionnaire_template'])){
        // Check if a question and at least an answer exists.
        if($template_id = (int)$_POST['template_id']){
            delete_questionnaire_template($template_id);
            set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
        }
        $process = true;
    }
    
    return $process;
}

/***************************************
 * FUNCTION: ADD QUESTINNAIRE TEMPLATE *
 ***************************************/
function add_questionnaire_template($name, $question_ids=array()){
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT INTO `questionnaire_templates` (`name`) VALUES (:name);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    
    // Create a template
    $stmt->execute();
    
    // Get the template id of the last insert
    $template_id = $db->lastInsertId();
    
    foreach($question_ids as $question_id){
        $question_id = (int)$question_id;
        // Query the database
        $stmt = $db->prepare("INSERT INTO `questionnaire_template_question` (`questionnaire_template_id`, `questionnaire_question_id`) VALUES (:questionnaire_template_id, :questionnaire_question_id);");
        $stmt->bindParam(":questionnaire_template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":questionnaire_question_id", $question_id, PDO::PARAM_INT);
        
        // Create a relation
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $message = "A assessment questionnaire template was added for ID \"{$template_id}\" by username \"" . $_SESSION['user']."\".";
    write_log($template_id+1000, $_SESSION['uid'], $message, 'questionnaire_template');

    // Return the template id
    return $template_id;
}

/******************************************
 * FUNCTION: UPDATE QUESTINNAIRE TEMPLATE *
 ******************************************/
function update_questionnaire_template($template_id, $name, $question_ids=array()){
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `questionnaire_templates` SET `name`=:name WHERE id=:template_id;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
    
    // Update a template
    $stmt->execute();
    
    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_template_question` WHERE questionnaire_template_id=:template_id;");
    $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
    
    // Delete all relations with template ID
    $stmt->execute();

    foreach($question_ids as $question_id){
        $question_id = (int)$question_id;
        // Query the database
        $stmt = $db->prepare("INSERT INTO `questionnaire_template_question` (`questionnaire_template_id`, `questionnaire_question_id`) VALUES (:questionnaire_template_id, :questionnaire_question_id);");
        $stmt->bindParam(":questionnaire_template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":questionnaire_question_id", $question_id, PDO::PARAM_INT);
        
        // Create a relation
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $message = "A assessment questionnaire template was updated for ID \"{$template_id}\" by username \"" . $_SESSION['user']."\".";
    write_log($template_id+1000, $_SESSION['uid'], $message, 'questionnaire_template');
    
    // Return 
    return true;
}

/******************************************
 * FUNCTION: DELETE QUESTINNAIRE TEMPLATE *
 ******************************************/
function delete_questionnaire_template($id){
    $id = (int)$id;
    
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_template_question` WHERE questionnaire_template_id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete a questionnaire template questions by template ID
    $stmt->execute();

    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_templates` WHERE id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete a questionnaire template
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    $message = "A assessment questionnaire template was deleted for ID \"{$id}\" by username \"" . $_SESSION['user']."\".";
    write_log($id+1000, $_SESSION['uid'], $message, 'questionnaire_template');
}

/********************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATES *
 ********************************************/
function display_questionnaire_templates(){
    global $lang;
    global $escaper;

    $tableID = "assessment-questionnaire-templates-table";
    
    echo "
        <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead>
                <tr >
                    <th>". $escaper->escapeHtml($lang['QuestionnaireTemplates']) ."</th>
                    <th width='75px'>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: false,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/assessment/questionnaire/template/dynamic',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
            })
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-questionnaire-template--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisTemplate'])."</label>
              </div>

              <input type=\"hidden\" name=\"template_id\" value=\"\" />
              <div class=\"form-group text-center \">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_questionnaire_template\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>
          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.delete-btn', function(){
                \$('#aseessment-questionnaire-template--delete [name=template_id]').val(\$(this).data('id'));
            })
        </script>
    ";
}

/***************************************************
 * FUNCTION: GET ASSESSMENT QUESTINNAIRE TEMPLATES *
 ***************************************************/
function get_assessment_questionnaire_templates($start=0, $length=-1){
    // Open the database connection
    $db = db_open();
    
    /*** Get questionnaire templates by $start and $lengh ***/
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS id, name
        FROM `questionnaire_templates` 
        ORDER BY name
    ";
    if($length != -1){
        $sql .= " LIMIT {$start}, {$length}; ";
    }
    
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    return array($recordsTotal, $templates);
}

/****************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATE ADD FORM *
 ****************************************************/
function display_questionnaire_template_add(){
    global $lang;
    global $escaper;
    
    // Get questionnaire questions
    list($recordsTotal, $questions) = get_assessment_questionnaire_questions();

    echo "
        <form method='post' class='risk-scoring-container' autocomplete='off'>
            <h4>".$escaper->escapeHtml($lang['NewQuestionnaireTemplate'])." <button type='submit' name='add_questionnaire_template' class='btn pull-right'>". $escaper->escapeHtml($lang['Add']) ."</button></h4>
            <div class='clearfix'></div>
            <br>
            <div class='row-fluid'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Name']) .":&nbsp; </strong>
                <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='name' required class='form-control' style='max-width: none'></div>
            </div>
            <div class='row-fluid'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Questions']) .":&nbsp; </strong>
                <div class='span11'>
                    <select id=\"template_questions\" name=\"template_questions[]\" multiple=\"multiple\" style='max-width: none;'>";
                    foreach($questions as $question){
                        echo "<option value='".$question['id']."'>".$escaper->escapeHtml($question['question'])."</option>";
                    }
                echo "</select>
                </div>
            </div>
        </form>
        <div class='hide' id='adding-row'>
            <div class='row-fluid answer-row'>
                <div class=\"span1\">
                </div>
                <div class=\"span10\">
                    <input type='text' placeholder='". $escaper->escapeHtml($lang['Answer']) ."' required name='answer[]' style='max-width: none'>
                </div>
                <div class=\"span1 text-center\">
                    <a href='#' class='delete-row'><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\" /></a>
                </div>
            </div>        
        </div>
    ";
    echo "
        <script>
            \$(document).ready(function(){
                $('#template_questions').multiselect({
                    enableFiltering: true,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                    filterPlaceholder: 'Search for question'
                });
                \$('body').on('click', '.delete-row', function(){
                    \$(this).parents('.answer-row').remove();
                })
            })
        </script>
    ";
}

/*************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATE BY ID *
 *************************************************/
function get_assessment_questionnaire_template_by_id($template_id){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id template_id, t1.name template_name, t3.id question_id, t3.question 
        FROM `questionnaire_templates` t1
            LEFT JOIN `questionnaire_template_question` t2 ON t1.id=t2.questionnaire_template_id
            LEFT JOIN `questionnaire_questions` t3 ON t2.questionnaire_question_id=t3.id
        WHERE
            t1.id=:template_id
        ORDER BY name;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":template_id", $template_id);
    
    $stmt->execute();

    $template_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    if(!$template_questions){
        return false;
    }else{
        $template = array(
            'id' => $template_questions[0]['template_id'],
            'name' => $template_questions[0]['template_name'],
            'questions' => $template_questions,
        );
        return $template;
    }
}

/*****************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATE EDIT FORM *
 *****************************************************/
function display_questionnaire_template_edit(){
    global $lang;
    global $escaper;
    
    // Template ID to edit
    $template_id = (int)$_GET['id'];
    
    // Get questionnaire questions
    list($recordsTotal, $questions) = get_assessment_questionnaire_questions();
    
    // Get questionnaire template by ID
    $template = get_assessment_questionnaire_template_by_id($template_id);

    // Create question ids array
    $question_ids = array();
    foreach($template['questions'] as $question){
        $question_ids[] = $question['question_id'];
    }

    echo "
        <form method='post' class='risk-scoring-container' autocomplete='off'>
            <h4>".$escaper->escapeHtml($lang['EditQuestionnaireTemplate'])." <button type='submit' name='edit_questionnaire_template' class='btn pull-right'>". $escaper->escapeHtml($lang['Update']) ."</button></h4>
            <div class='clearfix'></div>
            <br>
            <div class='row-fluid'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Name']) .":&nbsp; </strong>
                <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='name' required class='form-control' style='max-width: none' value='".$escaper->escapeHtml($template['name'])."'></div>
            </div>
            <div class='row-fluid'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Questions']) .":&nbsp; </strong>
                <div class='span11'>
                    <select id=\"template_questions\" name=\"template_questions[]\" multiple=\"multiple\" style='max-width: none;'>";
                    foreach($questions as $question){
                        if(in_array($question['id'], $question_ids)){
                            echo "<option value='".$question['id']."' selected>".$escaper->escapeHtml($question['question'])."</option>";
                        }else{
                            echo "<option value='".$question['id']."'>".$escaper->escapeHtml($question['question'])."</option>";
                        }
                    }
                echo "</select>
                </div>
            </div>
        </form>
        <div class='hide' id='adding-row'>
            <div class='row-fluid answer-row'>
                <div class=\"span1\">
                </div>
                <div class=\"span10\">
                    <input type='text' placeholder='". $escaper->escapeHtml($lang['Answer']) ."' required name='answer[]' style='max-width: none'>
                </div>
                <div class=\"span1 text-center\">
                    <a href='#' class='delete-row'><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\" /></a>
                </div>
            </div>        
        </div>
    ";
    echo "
        <script>
            \$(document).ready(function(){
                $('#template_questions').multiselect({
                    enableFiltering: true,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                    filterPlaceholder: 'Search for question'
                });
                \$('body').on('click', '.delete-row', function(){
                    \$(this).parents('.answer-row').remove();
                })
            })
        </script>
    ";
}

/***********************************************
 * FUNCTION: PROCESS ASSESSMENT QUESTIONNAIRES *
 ***********************************************/
function process_assessment_questionnaires(){
    global $lang, $escaper;

    $process = false;
    
    // Check if add questionnaire
    if(isset($_POST['add_questionnaire'])){
        $name                       = $escaper->escapeHtml($_POST['name']);
        if(get_questionnaire_by_name($name)){
            set_alert(true, "bad", $escaper->escapeHtml($lang['DuplicatedQuestionnaireName']));
            return false;
        }
        
        $questionnaire_templates    = isset($_POST['questionnaire_templates']) ? $_POST['questionnaire_templates'] : array();
        $assessment_contacts        = isset($_POST['assessment_contacts']) ? $_POST['assessment_contacts'] : array();
        
        // Check if a name exists.
        if($name){
            add_questionnaire($name, $questionnaire_templates, $assessment_contacts);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['QuestionnaireNameRequired']));
        }
        $process = true;
    }
    // Check if edit questionnaire
    elseif(isset($_POST['edit_questionnaire'])){
        $name               = $escaper->escapeHtml($_POST['name']);
        $questionnaire_id   = (int)$_GET['id'];
        
        // Check if the questionnaire name exists
        $questionnaires = get_questionnaire_by_name($name);
        foreach($questionnaires as $questionnaire){
            if($questionnaire['name'] == $name && $questionnaire['id'] != $questionnaire_id){
                set_alert(true, "bad", $escaper->escapeHtml($lang['DuplicatedQuestionnaireName']));
                return true;
            }
        }

        $name                       = $escaper->escapeHtml($_POST['name']);
        $questionnaire_templates    = empty($_POST['questionnaire_templates']) ? [] : $_POST['questionnaire_templates'];
        $assessment_contacts        = empty($_POST['assessment_contacts']) ? [] : $_POST['assessment_contacts'];
        
        // Check if questionnaire name exists.
        if($name){
            // Update questionnaire
            update_questionnaire($questionnaire_id, $name, $questionnaire_templates, $assessment_contacts);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['QuestionnaireNameRequired']));
        }
        $process = true;
    }
    // Check if delete a questionnaire
    elseif(isset($_POST['delete_questionnaire'])){
        // Check if a question and at least an answer exists.
        if($questionnaire_id = (int)$_POST['questionnaire_id']){
            delete_questionnaire($questionnaire_id);
            set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
        }
        $process = true;
    }
    // check if send a questionnaire
    elseif(isset($_GET['send_questionnaire'])){
        $questionnaire_id = (int)$_GET['id'];
        if(send_questionnaire($questionnaire_id)){
            set_alert(true, "good", $escaper->escapeHtml($lang['SentQuestionnaire']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['QuestionnaireHasNoContacts']));
        }
        $process = $_SESSION['base_url'] . "/assessments/questionnaires.php?action=list";

    }
    
    return $process;
}

/********************************
 * FUNCTION: SEND QUESTIONNAIRE *
 ********************************/
function send_questionnaire($questionnaire_id){
    global $lang, $escaper;
    
    $questionnaire = get_questionnaires_by_id($questionnaire_id);
    
    $contacts = array();
    foreach($questionnaire['templates'] as $template){
        if(!$template['contact_id']){
            continue;
        }

        $contacts[$template['contact_id']] = array(
            'id' => $template['contact_id'],
            'name' => $template['contact_name'],
            'email' => $template['contact_email'],
        );
    }
    
    // Check if contacts for this questionnaire exist
    if($contacts){
        // Open the database connection
        $db = db_open();
        
        // Require the mail functions
        require_once(realpath(__DIR__ . '/../../includes/mail.php'));
        
        // Create the message subject
        $subject = "[SIMPLERISK] ".$escaper->escapeHtml($lang['RiskAssessmentQuestionnaire']);

        foreach($contacts as $contact){
            // Generate token for unique link
            $token = generate_token(40);

            // Create the message body
            $body = get_string_from_template($lang['EmailTemplateSendingAssessment'], array(
                'username' => $escaper->escapeHtml($_SESSION['name']),
                'assessment_name' => $questionnaire['name'],
                'assessment_link' => $_SESSION['base_url'] . "/assessments/questionnaire.index.php?token=" . $token,
            ));
            
            send_email($contact['name'], $contact['email'], $subject, $body);

            // Query the database
            $stmt = $db->prepare("INSERT INTO `questionnaire_tracking`(questionnaire_id, contact_id, token, sent_at) VALUES(:questionnaire_id, :contact_id, :token, :sent_at); ");

            $stmt->bindParam(":questionnaire_id", $questionnaire_id, PDO::PARAM_INT);
            $stmt->bindParam(":contact_id", $contact['id'], PDO::PARAM_INT);
            $stmt->bindParam(":token", $token, PDO::PARAM_STR, 100);
            $stmt->bindParam(":sent_at", date("Y-m-d H:i:s"), PDO::PARAM_STR, 20);

            // Create a track for sending questionnaire
            $stmt->execute();
            
            $message = "A assessment questionnaire for ID \"{$questionnaire_id}\" was sent to contact for ID \"{$contact['id']}\" by username \"" . $_SESSION['user']."\".";
            write_log($questionnaire_id+1000, $_SESSION['uid'], $message, 'questionnaire');
        }
        // Close the database connection
        db_close($db);
        
        return true;
    }else{
        return false;
    }
}

/***********************************
 * FUNCTION: DISPLAY QUESTINNAIRES *
 ***********************************/
function display_questionnaires(){
    global $lang;
    global $escaper;

    $tableID = "assessment-questionnaires-table";
    
    echo "
        <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead>
                <tr >
                    <th>". $escaper->escapeHtml($lang['Questionnaires']) ."</th>
                    <th width='75px'>&nbsp;</th>
                    <th width='100px'>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: false,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/assessment/questionnaire/dynamic',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
            })
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-questionnaire--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisQestionnaire'])."</label>
              </div>

              <input type=\"hidden\" name=\"questionnaire_id\" value=\"\" />
              <div class=\"form-group text-center \">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_questionnaire\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>
          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.delete-btn', function(){
                \$('#aseessment-questionnaire--delete [name=questionnaire_id]').val(\$(this).data('id'));
            })
            \$('body').on('click', '.send-questionnaire', function(){
                var id = $(this).data('id')
                document.location.href = BASE_URL + '/assessments/questionnaires.php?action=list&send_questionnaire&id=' + id;
            })
        </script>
    ";
}

/******************************************
 * FUNCTION: GET ASSESSMENT QUESTINNAIRES *
 ******************************************/
function get_assessment_questionnaires($start=0, $length=-1){
    // Open the database connection
    $db = db_open();
    
    /*** Get questionnaires by $start and $lengh ***/
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS id, name
        FROM `questionnaires` 
        ORDER BY name
    ";
    if($length != -1){
        $sql .= " LIMIT {$start}, {$length}; ";
    }
    
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $questionnaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    return array($recordsTotal, $questionnaires);
}

/*********************************
 * FUNCTION: DELETE QUESTINNAIRE *
 *********************************/
function delete_questionnaire($id){
    $id = (int)$id;
    
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_id_template` WHERE questionnaire_id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete questionnaire and template relations by questionnaire ID
    $stmt->execute();

    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaires` WHERE id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete a questionnaire
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    $message = "A assessment questionnaire was deleted for ID \"{$id}\" by username \"" . $_SESSION['user']."\".";
    write_log($id+1000, $_SESSION['uid'], $message, 'questionnaire');
}

/********************************************
 * FUNCTION: DISPLAY QUESTINNAIRE EDIT FORM *
 ********************************************/
function display_questionnaire_edit(){
    global $lang;
    global $escaper;
    
    // Get questionnaire templates
    $templates = get_assessment_questionnaire_templates();

    // Get assessment contacts
    $contacts = get_assessment_contacts();
    
    // Get questionnaire and related templates by questionnaire ID
    $questionnaire_id = $_GET['id'];
    $questionnaire = get_questionnaires_by_id($questionnaire_id);
    if(!$questionnaire){
        echo "Invalid parameter";
        return;
    }
    
    echo "
        <div class='hide' id='contact-row-pattern'>
            <div class='row-fluid template-contact-row'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                <div class='span5'>
                    <select required name=\"questionnaire_templates[]\" style='max-width: none;'>
                        <option value=''>--</option>";
                    foreach($templates as $template){
                        echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                    }
                    echo "</select>
                </div>
                <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                <div class='span3'>
                    <select required name=\"assessment_contacts[]\" style='max-width: none;'>
                        <option value=''>--</option>";
                    foreach($contacts as $contact){
                        echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                    }
                    echo "</select>
                </div>
                <div class='span1 text-center'>
                    <a class='delete-row hide' href=''><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\"></a>
                </div>
            </div>
        </div>
    ";
    echo "
        <form method='post' class='' autocomplete='off'>
            <h4 class='content-header-height'>".$escaper->escapeHtml($lang['EditQuestionnaire'])." <button type='submit' name='edit_questionnaire' class='btn pull-right'>". $escaper->escapeHtml($lang['Save']) ."</button></h4>
            <div class='clearfix'></div>
            <div class='hero-unit'>
                <div class='row-fluid'>
                    <strong class='span1'>". $escaper->escapeHtml($lang['Name']) .":&nbsp; </strong>
                    <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='name' required class='form-control' style='max-width: none' value='".(isset($questionnaire['name']) ? $questionnaire['name'] : "")."'></div>
                </div>
                <div id='template-contacts-container'>";
                    if(is_array($questionnaire['templates'])){
                        foreach($questionnaire['templates'] as $key => $questionnaire_template){
                            $hideClass = ($key == 0 ? "hide" : "");
                            echo "
                                <div class='row-fluid template-contact-row'>
                                    <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                                    <div class='span5'>
                                        <select name=\"questionnaire_templates[]\" style='max-width: none;'>
                                            <option value=''>--</option>";
                                        foreach($templates as $template){
                                            if($questionnaire_template['template_id'] == $template['id']){
                                                echo "<option selected value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                            }else{
                                                echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                            }
                                        }
                                        echo "</select>
                                    </div>
                                    <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                                    <div class='span3'>
                                        <select required name=\"assessment_contacts[]\" style='max-width: none;'>
                                            <option value=''>--</option>";
                                        foreach($contacts as $contact){
                                            if($questionnaire_template['contact_id'] == $contact['id']){
                                                echo "<option selected value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                            }else{
                                                echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                            }
                                        }
                                        echo "</select>
                                    </div>
                                    <div class='span1 text-center'>
                                        <a class='delete-row {$hideClass}' href=''><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\"></a>
                                    </div>
                                </div>
                            ";
                        }
                    }else{
                        
                    }
                echo "</div>
                <div class='row-fluid'>
                    <div class='span11'>
                    </div>
                    <div class='span1 text-center'>
                        <a class='add-row' href=''><img src=\"../images/plus.png\" width=\"15px\" height=\"15px\"></a>
                    </div>
                </div>
            </div>
        </form>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.add-row', function(e){
                e.preventDefault();
                var contactRow = \$('#contact-row-pattern .template-contact-row').clone();
                if(\$(this).parents('form').find('.template-contact-row').length > 0){
                    contactRow.find('.delete-row').removeClass('hide');
                }
                contactRow.appendTo(\$('#template-contacts-container'))
            })
            \$('body').on('click', '.delete-row', function(e){
                e.preventDefault();
                \$(this).parents('.template-contact-row').remove();
            })
        </script>
    ";
}

/*******************************************
 * FUNCTION: DISPLAY QUESTINNAIRE ADD FORM *
 *******************************************/
function display_questionnaire_add(){
    global $lang;
    global $escaper;
    
    // Get questionnaire templates
    $templates = get_assessment_questionnaire_templates();

    // Get assessment contacts
    $contacts = get_assessment_contacts();

    echo "
        <form method='post' class='' autocomplete='off'>
            <h4 class='content-header-height'>".$escaper->escapeHtml($lang['NewQuestionnaire'])." <button type='submit' name='add_questionnaire' class='btn pull-right'>". $escaper->escapeHtml($lang['Save']) ."</button></h4>
            <div class='clearfix'></div>
            <div class='hero-unit'>
                <div class='row-fluid'>
                    <strong class='span1'>". $escaper->escapeHtml($lang['Name']) .":&nbsp; </strong>
                    <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='name' required class='form-control' style='max-width: none' value='".(isset($_POST['name']) ? $_POST['name'] : "")."'></div>
                </div>
                <div id='template-contacts-container'>";
                    if(isset($_POST['questionnaire_templates'])){
                        foreach($_POST['questionnaire_templates'] as $key => $questionnaire_template){
                            $hideClass = ($key == 0 ? "hide" : "");
                            echo "
                                <div class='row-fluid template-contact-row'>
                                    <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                                    <div class='span5'>
                                        <select name=\"questionnaire_templates[]\" style='max-width: none;'>
                                            <option value=''>--</option>";
                                        foreach($templates as $template){
                                            if($questionnaire_template == $template['id']){
                                                echo "<option selected value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                            }else{
                                                echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                            }
                                        }
                                        echo "</select>
                                    </div>
                                    <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                                    <div class='span3'>
                                        <select required name=\"assessment_contacts[]\" style='max-width: none;'>
                                            <option value=''>--</option>";
                                        foreach($contacts as $contact){
                                            if($_POST['assessment_contacts'][$key] == $contact['id']){
                                                echo "<option selected value='".$template['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                            }else{
                                                echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                            }
                                        }
                                        echo "</select>
                                    </div>
                                    <div class='span1 text-center'>
                                        <a class='delete-row {$hideClass}' href=''><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\"></a>
                                    </div>
                                </div>
                            ";
                        }
                    }else{
                        echo "
                            <div class='row-fluid template-contact-row'>
                                <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                                <div class='span5'>
                                    <select name=\"questionnaire_templates[]\" style='max-width: none;'>
                                        <option value=''>--</option>";
                                    foreach($templates as $template){
                                        echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                    }
                                    echo "</select>
                                </div>
                                <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                                <div class='span3'>
                                    <select required name=\"assessment_contacts[]\" style='max-width: none;'>
                                        <option value=''>--</option>";
                                    foreach($contacts as $contact){
                                        echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                    }
                                    echo "</select>
                                </div>
                                <div class='span1 text-center'>
                                    <a class='delete-row hide' href=''><img src=\"../images/minus.png\" width=\"15px\" height=\"15px\"></a>
                                </div>
                            </div>
                        ";
                        
                    }
                echo "</div>
                <div class='row-fluid'>
                    <div class='span11'>
                    </div>
                    <div class='span1 text-center'>
                        <a class='add-row' href=''><img src=\"../images/plus.png\" width=\"15px\" height=\"15px\"></a>
                    </div>
                </div>
            </div>
        </form>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.add-row', function(e){
                e.preventDefault();
                var contactRow = \$('.template-contact-row').first().clone();
                contactRow.find('.delete-row').removeClass('hide');
                contactRow.appendTo(\$('#template-contacts-container'))
            })
            \$('body').on('click', '.delete-row', function(e){
                e.preventDefault();
                \$(this).parents('.template-contact-row').remove();
            })
        </script>
    ";
}

/**************************************
 * FUNCTION: GET QUESTINNAIRE BY NAME *
 **************************************/
function get_questionnaire_by_name($name){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `questionnaires` WHERE `name`=:name;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/******************************
 * FUNCTION: ADD QUESTINNAIRE *
 ******************************/
function add_questionnaire($name, $template_ids, $contact_ids){
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT INTO `questionnaires` (`name`) VALUES (:name);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    
    // Create a template
    $stmt->execute();
    
    // Get the questionnaire id of the last insert
    $questionnaire_id = $db->lastInsertId();
    
    foreach($template_ids as $key=>$template_id){
        $template_id = (int)$template_id;
        $contact_id = (int)$contact_ids[$key];
        // Query the database
        $stmt = $db->prepare("INSERT INTO `questionnaire_id_template` (`questionnaire_id`, `template_id`, `contact_id`) VALUES (:questionnaire_id, :template_id, :contact_id);");
        $stmt->bindParam(":questionnaire_id", $questionnaire_id, PDO::PARAM_INT);
        $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
        
        // Create a relation
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $message = "A assessment questionnaire was added for ID \"{$questionnaire_id}\" by username \"" . $_SESSION['user']."\".";
    write_log($questionnaire_id+1000, $_SESSION['uid'], $message, 'questionnaire');
    
    // Return the questionnaire id
    return $questionnaire_id;
}

/******************************
 * FUNCTION: ADD QUESTINNAIRE *
 ******************************/
function update_questionnaire($questionnaire_id, $name, $template_ids, $contact_ids){
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `questionnaires` SET `name`=:name WHERE id=:id;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":id", $questionnaire_id, PDO::PARAM_INT);
    
    // Update a questionnaire by questionnaire ID
    $stmt->execute();
    
    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_id_template` WHERE questionnaire_id=:questionnaire_id;");
    $stmt->bindParam(":questionnaire_id", $questionnaire_id, PDO::PARAM_INT);
    
    // Delete all questionnaire and template relations by questionnaire ID
    $stmt->execute();
    
    foreach($template_ids as $key=>$template_id){
        $template_id = (int)$template_id;
        $contact_id = (int)$contact_ids[$key];
        // Query the database
        $stmt = $db->prepare("INSERT INTO `questionnaire_id_template` (`questionnaire_id`, `template_id`, `contact_id`) VALUES (:questionnaire_id, :template_id, :contact_id);");
        $stmt->bindParam(":questionnaire_id", $questionnaire_id, PDO::PARAM_INT);
        $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
        
        // Create a relation
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $message = "A assessment questionnaire was updated for ID \"{$questionnaire_id}\" by username \"" . $_SESSION['user']."\".";
    write_log($questionnaire_id+1000, $_SESSION['uid'], $message, 'questionnaire');
    
    // Return the questionnaire id
    return $questionnaire_id;
}

/*****************************************************************
 * FUNCTION: GET QUESTIONNAIRE AND TEMPLATES BY QUESTIONNAIRE ID *
 *****************************************************************/
function get_questionnaires_by_id($questionnaire_id){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id questionnaire_id, t1.name questionnaire_name, t2.template_id, t3.name template_name, t4.id contact_id, t4.name contact_name, t4.email contact_email
        FROM `questionnaires` t1
            LEFT JOIN `questionnaire_id_template` t2 ON t1.id=t2.questionnaire_id
            LEFT JOIN `questionnaire_templates` t3 ON t2.template_id=t3.id
            LEFT JOIN `assessment_contacts` t4 ON t2.contact_id=t4.id
        WHERE
            t1.id=:questionnaire_id
        ORDER BY t3.name;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":questionnaire_id", $questionnaire_id);
    
    $stmt->execute();

    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    if(!$templates){
        return false;
    }else{
        $array = [];
        foreach($templates as $template){
            if($template['template_id'])
                $array[] = $template;
        }
        $questionnaire = array(
            'id' => $templates[0]['questionnaire_id'],
            'name' => $templates[0]['questionnaire_name'],
            'templates' => $array,
        );
        return $questionnaire;
    }
}

/***************************************
 * FUNCTION: IS VALID ASSESSMENT TOKEN *
 ***************************************/
function is_valid_questionnaire_token($token)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `questionnaire_tracking` WHERE `token`=:token;");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR, 40);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the query returned a value
    if (!empty($array))
    {
        return true;
    }
    else return false;
}

/**********************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE QUESTIONS FOR CONTACTS *
 **********************************************************/
function display_questionnaire_index(){
    global $escaper;
    
    $token = $escaper->escapeHtml($_GET['token']);
    
    $questionnaire_contact = get_questionnaire_tracking_by_token($token);

    // If contact password is empty, shows set password form
    if(!$questionnaire_contact['contact_password']){
        display_set_contact_password($questionnaire_contact['contact_id']);
    }
    // If contacter is not authenticated, shows login form
    elseif(!check_contact_authentication()){
        display_contact_login($questionnaire_contact['contact_id']);
    }
    // Shows contents after login
    else{
        display_contact_questionnaire();
    }
}

/**********************************************************************
 * FUNCTION: CHECK IF ENCRYPTED_PASS IS VALID IN CONTACT LANDING PAGE *
 **********************************************************************/
function check_valid_encrypted_pass_in_contact()
{
    // Contact logged in
    if(!empty($_SESSION['contact_id'])){
        // Check if encrypted_pass is undefined
        if(encryption_extra() && empty($_SESSION['encrypted_pass'])){
            $result = false;
        }
        // If encryption_extra is disabled or encrypted_pass exists
        else
        {
            $result = true;
        }
    }
    else
    {
        $result = true;
    }
    return $result;
}

/*****************************************************************
 * FUNCTION: CHECK IF CONTACTER HAS PERMISSION FOR QUESTIONNAIRE *
 *****************************************************************/
function check_contact_permission_for_questionnaire($token){
    global $escaper, $lang;

    // Check if contact is authenticated
    if(!isset($_SESSION['contact_id'])){
        return false;
    }

    $contact_id = $_SESSION['contact_id'];

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `questionnaire_tracking` WHERE `token`=:token and `contact_id`=:contact_id;");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the query returned a value
    if (!empty($array))
    {
        return true;
    }
    else 
    {
        logout_contact();
    
        return false;
    }
    
}

/*****************************************
 * FUNCTION: GET QUESTIONNAIRE RESPONSES *
 *****************************************/
function get_questionnaire_responses($token){
    $db = db_open();

    $stmt = $db->prepare("
        SELECT t1.template_id, t1.question_id, t1.additional_information, t1.answer
        FROM `questionnaire_responses` t1 
            INNER JOIN `questionnaire_tracking` t2 ON t1.questionnaire_tracking_id=t2.id 
        WHERE t2.`token`=:token;
    ");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->execute();

    $array = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);
    
    // Two-dimensional array: [template_id][question_id]
    $responses = array();
    
    foreach($array as $row){
        $template_id = $row['template_id'];
        $question_id = $row['question_id'];
        if(!isset($responses[$template_id])){
            $responses[$template_id] = array();
        }
        $responses[$template_id][$question_id] = array(
            'additional_information' => try_decrypt($row['additional_information']),
            'answer' => try_decrypt($row['answer']),
        );
    }
    
    return $responses;
}

/**********************************
 * FUNCTION: GET ASSESSMENT FILES *
 **********************************/
function get_assessment_files($tracking_id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.* FROM `questionnaire_files` t1 WHERE t1.`tracking_id`=:tracking_id;");
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();

    $files = $stmt->fetchAll();

    // Close the database connection
    db_close($db);
    
    return $files;
}

/**************************************
 * FUNCTION: DISPLAY ASSESSMENT FILES *
 **************************************/
function display_assessment_files($onlyView=false){
    global $lang, $escaper;
    
    $token      = $escaper->escapeHtml($_GET['token']);
    
    // Get questionnaire tracking info from token
    $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);

    $files = get_assessment_files($questionnaire_tracking_info['tracking_id']);
    
    $html = "";
    
    foreach($files as $file){
        // If only view, show only file name
        if($onlyView){
            $html .= "
                <li>            
                    <div class=\"file-name\"><a href=\"".$_SESSION['base_url']."/assessments/download.php?id=".$file['unique_name']."\" >".$escaper->escapeHtml($file['name'])."</a></div>
                </li>            
            ";
        }
        else{
            $html .= "
                <li>            
                    <div class=\"file-name\"><a href=\"".$_SESSION['base_url']."/assessments/download.php?id=".$file['unique_name']."\" >".$escaper->escapeHtml($file['name'])."</a></div>
                    <a href=\"#\" class=\"remove-file\" data-id=\"file-upload-0\"><i class=\"fa fa-remove\"></i></a>
                    <input name=\"unique_names[]\" value=\"{$file['unique_name']}\" type=\"hidden\">
                </li>            
            ";
        }
    }
    
    echo $html;
}

/**********************************************
 * FUNCTION: DISPLAY QUESTIONNARE AFTER LOGIN *
 **********************************************/
function display_contact_questionnaire(){
    global $lang, $escaper;
    
    $token      = $escaper->escapeHtml($_GET['token']);
    
    // Check if contacter has a permission for this questionnaire
    if(!check_contact_permission_for_questionnaire($token))
    {
        echo "<br>";
        echo "<div class='alert alert-error'>".$escaper->escapeHtml($lang['NoPermissionForQuestionnaire'])."</div>";
    }
    else
    {

        $token = $escaper->escapeHtml($_GET['token']);
        $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);
        $questionnaire_status = $questionnaire_tracking_info['questionnaire_status'];
        echo "
            <h1>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_tracking_info['questionnaire_name'])."</h1>
        ";

        $db_responses = get_questionnaire_responses($token);
        
        $questionnaire_id = $questionnaire_tracking_info['questionnaire_id'];
        $questinnaire = get_questionnaires_by_id($questionnaire_id);

        if(isset($questinnaire['templates'])){
//            echo "<form method=\"POST\" enctype=\"multipart/form-data\" name=\"questionnaire_response_form\" class=\"".($questionnaire_status ? "disabled" : "")."\">";
            echo "<form method=\"POST\" enctype=\"multipart/form-data\" name=\"questionnaire_response_form\" >";
            $templateIndex = 1;

            foreach($questinnaire['templates'] as $key => $template){
                echo "<div><h3>".$templateIndex.".&nbsp;".$escaper->escapeHtml($template['template_name'])."</h3></div>";

                // Get questions of this template
                $template_id = $template['template_id'];
                $template_in_detail = get_assessment_questionnaire_template_by_id($template['template_id']);
                if(isset($template_in_detail['questions'])){
                    $questions = $template_in_detail['questions'];
                }else{
                    $questions = [];
                }
                
                foreach($questions as $questionIndex => &$question)
                {
                    $question_id = $question['question_id'];
                    $question = get_questionnaire_question($question_id);
                    echo "<div class='questionnaire-question'>";
                        echo "<p>".++$questionIndex.")&nbsp;&nbsp; ".$escaper->escapeHtml($question['question'])."</p>";
                        echo "<div class='questionnaire-answers'>";
                        foreach($question['answers'] as $answer){
                            // Check if this answer was already posted
//                            echo $db_responses[$template_id][$question_id]['answer']."___".$answer;
                            if(isset($db_responses[$template_id][$question_id]['answer']) && $db_responses[$template_id][$question_id]['answer'] == $answer['answer']){
                                echo "<label><input type='radio' required name='answer[{$template_id}][{$question_id}]' checked value='{$answer['answer']}'>&nbsp;&nbsp;<span class='answer-text'>{$answer['answer']}</span></label>";
                            }
                            else
                            {
                                echo "<label><input type='radio' required name='answer[{$template_id}][{$question_id}]' value='{$answer['answer']}'>&nbsp;&nbsp;<span class='answer-text'>{$answer['answer']}</span></label>";
                            }
                        }
                        echo "<textarea name='additional_information[{$template_id}][{$question_id}]' style='width: 100%' placeholder='".$escaper->escapeHtml($lang['AdditionalInformation'])."'>".(isset($db_responses[$template_id][$question_id]['additional_information']) ? $escaper->escapeHtml($db_responses[$template_id][$question_id]['additional_information']) : "")."</textarea>";
                        echo "</div>";
                    echo "</div>";
                    
                }
                
                $templateIndex++;
            }
            
            // Check if questionnaire completed
            if($questionnaire_status){
                echo "
                    <div class='row-fluid attachment-container'>
                        <div ><strong>".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </strong></div>
                        <div>
                            <div class=\"file-uploader\">
                                <ul class=\"exist-files\">
                                    ";
                                    display_assessment_files($questionnaire_status);
                                echo "
                                </ul>
                            </div>
                        </div>
                    </div>
                ";
            }else{
                echo "
                    <div class='row-fluid attachment-container'>
                        <div class='pull-left'><strong>".$escaper->escapeHtml($lang['Attachment']).":&nbsp;&nbsp; </strong></div>
                        <div class='pull-left'>
                            <div class=\"file-uploader\">
                                <label for=\"file-upload\" class=\"btn\">Choose File</label>
                                <span class=\"file-count-html\"> <span class=\"file-count\">0</span> File Added</span>
                                <p><font size=\"2\"><strong>Max 1 Mb</strong></font></p>
                                <ul class=\"exist-files\">
                                    ";
                                    display_assessment_files($questionnaire_status);
                                echo "
                                </ul>
                                <ul class=\"file-list\">
                                </ul>
                                <input type=\"file\" id=\"file-upload\" name=\"file[]\" class=\"hidden-file-upload active\" />
                            </div>
                        </div>
                    </div>
                ";
                
            }
            
            
            // Check if this questionnaire is not completed
            if(!$questionnaire_status){
                echo "<div class='button-container'>";
                    echo "<button type='reset' class='btn' >".$escaper->escapeHtml($lang['ClearForm'])."</button>";
                    echo "&nbsp;&nbsp;";
                    echo "<button class='btn' id='draft_questionnaire' name='draft_questionnaire' formnovalidate>".$escaper->escapeHtml($lang['Draft'])."</button>";
                    echo "&nbsp;&nbsp;";
                    echo "<button class='btn' id='complete_questionnaire' name='complete_questionnaire'>".$escaper->escapeHtml($lang['Complete'])."</button>";
                echo "</div>";
            }
            echo "</form>";
        }
        
    }
}


/*****************************************************************
 * FUNCTION: CHECK IF CONTACTER HAS PERMISSION FOR QUESTIONNAIRE *
 *****************************************************************/
function check_contact_permission_for_template($token , $template_id){
    global $escaper, $lang;
    $contact_id = (int)$_SESSION['contact_id'];

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT t1.* FROM `questionnaire_id_template` t1 INNER JOIN `questionnaire_tracking` t2 ON t1.questionnaire_id=t2.questionnaire_id AND t1.contact_id=t2.contact_id WHERE t2.`token`=:token AND t2.`contact_id`=:contact_id AND t1.template_id=:template_id;");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
    $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the query returned a value
    if (!empty($array))
    {
        return true;
    }
    else return false;
    
}

/**********************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE TEMPLATE FOR CONTACTER *
 **********************************************************/
function display_contact_questionnaire_template(){
    global $lang, $escaper;
    $token = $escaper->escapeHtml($_GET['token']);
    $tempalte_id = (int)$_GET['id'];
    
    // Check if contacer has a permission for this template and questionnaire
    if(!check_contact_permission_for_template($token, $tempalte_id))
    {
        echo "<br>";
        echo "<div class='alert alert-error'>".$escaper->escapeHtml($lang['NoPermissionForTemplate'])."</div>";
    }
    else
    {
        
        $questionnaire_and_contact = get_questionnaire_tracking_by_token($token);
        
        echo "
            <h1>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_and_contact['questionnaire_name'])."</h1>
            <br>
            <h4>".$escaper->escapeHtml($lang['CompleteYourQuestionnaireTemplate'])."</h4>
        ";
        
        $questinnaire = get_questionnaires_by_id($questionnaire_and_contact['questionnaire_id']);

        if(isset($questinnaire['templates'])){
            echo "<ul>";
            foreach($questinnaire['templates'] as $template){
                $link = $_SESSION['base_url']."/assessments/questionnaire.index.php?token=".$token."&page=template&id=".$template['template_id'];
                echo "<li><a href='{$link}'>".$escaper->escapeHtml($template['template_name'])."</a></li>";
            }
            echo "</ul>";
        }
    }
    
}

/********************************************************
 * FUNCTION: DISPLAY CONTACT QUESTIONNAIRE LANDING PAGE *
 ********************************************************/
function display_contact_questionnaire_index(){
    global $lang, $escaper;
    
    $token = $escaper->escapeHtml($_GET['token']);
    $questionnaire_and_contact = get_questionnaire_tracking_by_token($token);
    
    echo "
        <h1>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_and_contact['questionnaire_name'])."</h1>
        <br>
        <h4>".$escaper->escapeHtml($lang['CompleteYourQuestionnaireTemplate'])."</h4>
    ";
    
    $questinnaire = get_questionnaires_by_id($questionnaire_and_contact['questionnaire_id']);

    if(isset($questinnaire['templates'])){
        echo "<ul>";
        foreach($questinnaire['templates'] as $template){
            $link = $_SESSION['base_url']."/assessments/questionnaire.index.php?token=".$token."&page=template&id=".$template['template_id'];
            echo "<li><a href='{$link}'>".$escaper->escapeHtml($template['template_name'])."</a></li>";
        }
        echo "</ul>";
    }
}

/****************************************
 * FUNCTION: DISPLAY CONTACT LOGIN FORM *
 ****************************************/
function display_contact_login($contact_id){
    global $lang, $escaper;
    
    $contact = get_assessment_contact($contact_id);
    
    echo "
        <div class=\"login-wrapper clearfix\">
            <form method=\"post\" action=\"\" class=\"loginForm\">
                
                <table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                    <tbody>
                        <tr>
                            <td colspan=\"2\"><label class=\"login--label\">".$escaper->escapeHtml($lang['Login'])."</label></td>
                        </tr>
                        <tr>
                            <td width=\"30%\"><label for=\"\">Password:&nbsp;</label></td><td class=\"80%\"><input required class=\"form-control input-medium\" name=\"pass\" id=\"pass\" autocomplete=\"off\" type=\"password\"></td>
                        </tr>
                    </tbody>
                </table>
                <div class=\"form-actions\">
                    <button type=\"submit\" name=\"login_for_contact\" class=\"btn btn-primary pull-right\">".$escaper->escapeHtml($lang['Submit'])."</button>
                </div>
            </form>
        </div>    
    ";
}

/***********************************************
 * FUNCTION: DISPLAY SET CONTACT PASSWORD FORM *
 ***********************************************/
function display_set_contact_password($contact_id){
    global $lang, $escaper;
    
    $contact = get_assessment_contact($contact_id);
    echo "
        <div class=\"login-wrapper clearfix\">
            <form method=\"post\" action=\"\" class=\"loginForm\">
                
                <table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                    <tbody>
                        <tr>
                            <td colspan=\"2\"><label class=\"login--label\">".$escaper->escapeHtml($lang['SetPassword'])."</label></td>
                        </tr>
                        <tr>
                            <td width=\"30%\"><label for=\"\">Password:&nbsp;</label></td><td class=\"80%\"><input required class=\"form-control input-medium\" name=\"pass\" id=\"pass\" autocomplete=\"off\" type=\"password\"></td>
                        </tr>
                        <tr>
                            <td width=\"30%\"><label for=\"\">".$escaper->escapeHtml($lang['ConfirmPassword']).":&nbsp;</label></td><td class=\"80%\"><input required class=\"form-control input-medium\" name=\"confirmPass\" id=\"user\" type=\"password\"></td>
                        </tr>
                    </tbody>
                </table>
                <div class=\"form-actions\">
                    <button type=\"submit\" name=\"set_contact_password\" class=\"btn btn-primary pull-right\">".$escaper->escapeHtml($lang['Submit'])."</button>
                </div>
            </form>
        </div>    
    ";
}

/*****************************************
 * FUNCTION: UPDATE A ASSESSMENT CONTACT *
 *****************************************/
function set_assessment_contact_password($contact_id, $pass){
    // Get contact by contact ID
    $contact = get_assessment_contact($contact_id);
    
    // Check if salt exits
    if($contact['salt']){
        $salt = $contact['salt'];
    }
    else{
        // Create a unique salt for this contact
        $salt = generate_token(20);
    }
    
    // Hash the salt
    $salt_hash = oldGenerateSalt($salt);

    // Generate the password hash
    $hash = generateHash($salt_hash, $pass);

    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("UPDATE `assessment_contacts` SET `password` = :pass, `salt` = :salt WHERE id=:id;");
    $stmt->bindParam(":pass", $hash, PDO::PARAM_LOB);
    $stmt->bindParam(":salt", $salt, PDO::PARAM_STR);
    $stmt->bindParam(":id", $contact_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
    
    return true;
}

/*****************************************
 * FUNCTION: PROCESS QUESTIONNAIRE INDEX *
 *****************************************/
function process_questionnaire_index(){
    global $lang, $escaper;
    
    $process = false;
    
    $token =  $escaper->escapeHtml($_GET['token']);

    // Check if set contact password
    if(isset($_POST['set_contact_password'])){
        $pass           = $_POST['pass'];
        $confirmPass    = $_POST['confirmPass'];
        if($pass != $confirmPass){
            $process = false;
            set_alert(true, "bad", $lang['NoMatchPassword']);
        }else{
            
            $questionnaire_contact = get_questionnaire_tracking_by_token($token);
            
            
            // Create password for assessment contact
            set_assessment_contact_password($questionnaire_contact['contact_id'], $pass);
            
            $_SESSION['contact_id'] = $questionnaire_contact['contact_id'];

            // If encryption is enabled
            if(encryption_extra()){
                //Include the encryption extra
                require_once(realpath(__DIR__ . '/../encryption/index.php'));
                check_contact_enc($questionnaire_contact['contact_email'], $pass);
            }
            
            $process = true;
            set_alert(true, "good", $lang['SetPasswordSuccess']);
        }
        
    }
    // Check if submitted for login
    elseif(isset($_POST['login_for_contact'])){
        $pass   = $_POST['pass'];
        
        // Check if password is correct
        if(is_valid_contact_user($token, $pass)){
            $questionnaire_contact  = get_questionnaire_tracking_by_token($token);
            $_SESSION['contact_id'] = $questionnaire_contact['contact_id'];
            $_SESSION['token']      = $token;

            // Get base url
            $base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}";
            $base_url = htmlspecialchars( $base_url, ENT_QUOTES, 'UTF-8' );
            $base_url = pathinfo($base_url)['dirname'];
            $base_url = dirname($base_url);

            // Set the permissions
            $_SESSION['base_url'] = $base_url;
            
            // If encryption is enabled
            if(encryption_extra()){
                //Include the encryption extra
                require_once(realpath(__DIR__ . '/../encryption/index.php'));
                check_contact_enc($questionnaire_contact['contact_email'], $pass);
            }
        }else{
            set_alert(true, "bad", $lang['InvalidPassword']);
        }
        $process = true;
    }
    // Check if questionnaire form was submitt for draft
    elseif(isset($_POST['draft_questionnaire'])){
        if(!check_contact_permission_for_questionnaire($token)){
            set_alert(true, "bad", $lang['NoPermissionForQuestionnaire']);
        }else{
            $process = true;

            // Check if saved successfully
            if(save_questionnaire_response()){
                set_alert(true, "good", $escaper->escapeHtml($lang['QuestionnaireDraftSuccess']));
            }
        }
    }
    // Check if questionnaire form was submitt for complete
    elseif(isset($_POST['complete_questionnaire'])){
        if(!check_contact_permission_for_questionnaire($token)){
            set_alert(true, "bad", $lang['NoPermissionForQuestionnaire']);
        }else{
            $process = true;
            
            // Check if saved successfully
            if(save_questionnaire_response(true)){
                set_alert(true, "good", $escaper->escapeHtml($lang['QuestionnaireCompletedSuccess']));
            }
        }
    }
    
    return $process;
}

/************************************
 * FUNCTION: DELETE ASSESSMENT FILE *
 ************************************/
function delete_assessment_file($file_id){
    // Open the database connection
    $db = db_open();

    // Delete a file from questionnaire_files table
    $stmt = $db->prepare("DELETE FROM `questionnaire_files` WHERE id=:file_id; ");
    $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: SAVE QUESTIONNAIRE RESPONSE *
 *****************************************/
function save_questionnaire_response($complete=false){
    global $lang, $escaper;

    $token = $escaper->escapeHtml($_GET['token']);
    
    // Error variable
    $error = false;
    
    // Get tracking by token
    $tracking = get_questionnaire_tracking_by_token($token);
    
    // Check if questionnaire was already completed
    if($tracking['questionnaire_status']){
        return false;
    }
    
    $tracking_id = $tracking['tracking_id'];
    
    $answers = isset($_POST['answer']) ? $_POST['answer'] : array();
    $additional_informations = isset($_POST['additional_information']) ? $_POST['additional_information'] : array();

    // Open the database connection
    $db = db_open();

    // Delete all questionnaire responses by token
    $stmt = $db->prepare("DELETE FROM `questionnaire_responses` WHERE questionnaire_tracking_id=:questionnaire_tracking_id; ");
    $stmt->bindParam(":questionnaire_tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();
        
    foreach($additional_informations as $template_id => $response){
        foreach($response as $question_id => $additional_information){
            $answer = isset($answers[$template_id][$question_id]) ? try_encrypt($answers[$template_id][$question_id]) : "";
            $additional_information = try_encrypt($additional_information);
            
            $sql = "INSERT INTO `questionnaire_responses`(`questionnaire_tracking_id`, `template_id`, `question_id`, `additional_information`, `answer`) VALUES(:questionnaire_tracking_id, :template_id, :question_id, :additional_information, :answer);";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(":questionnaire_tracking_id", $tracking_id, PDO::PARAM_INT);
            $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
            $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $stmt->bindParam(":additional_information", $additional_information, PDO::PARAM_STR);
            $stmt->bindParam(":answer", $answer, PDO::PARAM_STR);
            
            $stmt->execute();
        }
    }
    
    // Get questionnaire response percent based on answers
    $percent = calc_questionnaire_response_percent($token, $answers);

    $sql = "UPDATE `questionnaire_tracking` SET `percent`=:percent WHERE id=:tracking_id;";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":percent", $percent, PDO::PARAM_INT);
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    
    // Set percent for this questionnaire
    $stmt->execute();
    
    // Check if user already attached files 
    if(isset($_POST['unique_names'])){
        $unique_names = $_POST['unique_names'];
        
        $files = get_assessment_files($tracking_id);
        
        foreach($files as $file){
            // Check if file is deleted
            if(!in_array($file['unique_name'], $unique_names)){
                delete_assessment_file($file['id']);
            }
        }
        
    }
    
    // Save files
    if(!empty($_FILES['file'])){
        $files = $_FILES['file'];
        $result = upload_questionnaire_files($tracking_id, $files);
        
        // Check if error was happened in uploading files
        if($result !== true && is_array($result)){
            $error = true;
            $error_string = implode(", ", $result);
            set_alert(true, "bad", $error_string);
        }
    }
    
    // Check if contact completed questionnaire
    if(!$error && $complete !== false && $percent == 100){
        $sql = "UPDATE `questionnaire_tracking` SET `status`=1 WHERE id=:tracking_id;";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
        
        // Set questionnaire as complete
        $stmt->execute();

        /********** Start sending a notification to contact manager ***********/
            $contact_manager = get_user_by_id($tracking['contact_manager']);
            
            // Create the message body
            $body = get_string_from_template($lang['EmailTemplateCompleteQuestionnaire'], array(
                'conact_name' => $escaper->escapeHtml($tracking['contact_name']),
                'questionnaire_name' => $escaper->escapeHtml($tracking['questionnaire_name']),
            ));
            
            $subject = "[SIMPLERISK] Notification of Completed Questionnaire";
            
            // Require the mail functions
            require_once(realpath(__DIR__ . '/../../includes/mail.php'));
            
            // Send the email
            send_email($contact_manager['name'], $contact_manager['email'], $subject, $body);
        /********** End sending a notification to contact manager ***********/
    }
    
    // Close the database connection
    db_close($db);
    
    // If process is success
    if(!$error)
        return true;
    // If error was happened
    else
        return false;
}

/****************************************
 * FUNCTION: UPLOAD QUESTIONNAIRE FILES *
 ****************************************/
function upload_questionnaire_files($tracking_id, $files){
    // Open the database connection
    $db = db_open();
    
    // Get the list of allowed file types
    $stmt = $db->prepare("SELECT `name` FROM `file_types`");
    $stmt->execute();

    // Get the result
    $result = $stmt->fetchAll();

    // Create an array of allowed types
    foreach ($result as $key => $row)
    {
        $allowed_types[] = $row['name'];
    }
    
    $errors = array();

    foreach($files['name'] as $key => $name){
        if(!$name)
            continue;
            
        $file = array(
            'name' => $files['name'][$key],
            'type' => $files['type'][$key],
            'tmp_name' => $files['tmp_name'][$key],
            'size' => $files['size'][$key],
            'error' => $files['error'][$key],
        );

        // If the file type is appropriate
        if (in_array($file['type'], $allowed_types))
        {
            // Get the maximum upload file size
            $max_upload_size = get_setting("max_upload_size");

            // If the file size is less than max size
            if ($file['size'] < $max_upload_size)
            {
                // If there was no error with the upload
                if ($file['error'] == 0)
                {
                    // Read the file
                    $content = fopen($file['tmp_name'], 'rb');

                    // Create a unique file name
                    $unique_name = generate_token(30);

                    // Store the file in the database
                    $stmt = $db->prepare("INSERT `questionnaire_files` (tracking_id, name, unique_name, type, size, content) VALUES (:tracking_id, :name, :unique_name, :type, :size, :content)");
                    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
                    $stmt->bindParam(":name", $file['name'], PDO::PARAM_STR, 30);
                    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                    $stmt->bindParam(":type", $file['type'], PDO::PARAM_STR, 30);
                    $stmt->bindParam(":size", $file['size'], PDO::PARAM_INT);
                    $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
                    $stmt->execute();
                }
                // Otherwise
                else
                {
                    switch ($file['error'])
                    {
                        case 1:
                            $errors[] = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                            break;
                        case 2:
                            $errors[] = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                            break;
                        case 3:
                            $errors[] = "The uploaded file was only partially uploaded.";
                            break;
                        case 4:
//                            $errors[] = "No file was uploaded.";
                            break;
                        case 6:
                            $errors[] = "Missing a temporary folder.";
                            break;
                        case 7:
                            $errors[] = "Failed to write file to disk.";
                            break;
                        case 8:
                            $errors[] = "A PHP extension stopped the file upload.";
                            break;
                        default:
                            $errors[] = "There was an error with the file upload.";
                    }
                }
            }
            else $errors[] = "The uploaded file was too big to store in the database.  A SimpleRisk administrator can modify the maximum file upload size under \"File Upload Settings\" under the \"Configure\" menu.  You may also need to modify the 'upload_max_filesize' and 'post_max_size' values in your php.ini file.";
        }
        else $errors[] = "The file type of the uploaded file (" . $file['type'] . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
    }

    // Close the database connection
    db_close($db);
    
    if($errors)
        return array_unique($errors);
    else
        return true;
}

/*************************************************
 * FUNCTION: CALC QUESTIONNAIRE RESPONSE PERCENT *
 *************************************************/
function calc_questionnaire_response_percent($token, $answers){
    // Total questions to this questionnaire
    $totalQuestions = 0;
    
    // Processed questions
    $processedQuestions = 0;
    
    $questionnaire_and_contact = get_questionnaire_tracking_by_token($token);

    $questionnaire_id = $questionnaire_and_contact['questionnaire_id'];
    $questinnaire = get_questionnaires_by_id($questionnaire_id);
    
    if(isset($questinnaire['templates'])){
        foreach($questinnaire['templates'] as $key => $template){

            // Get questions of this template
            $template_id = $template['template_id'];
            $template_in_detail = get_assessment_questionnaire_template_by_id($template['template_id']);
            if(isset($template_in_detail['questions'])){
                $questions = $template_in_detail['questions'];
            }else{
                $questions = [];
            }
            
            foreach($questions as $question){
                $question_id = $question['question_id'];
                if(isset($answers[$template_id][$question_id]) && $answers[$template_id][$question_id]){
                    $processedQuestions++;
                }
            }
            
            $totalQuestions += count($questions);
        }
    }
    
    if($totalQuestions == 0){
        $percent = 0;
    }else{
        $percent = round(($processedQuestions / $totalQuestions) * 100);
    }
    
    return $percent;
}

/***********************************************************
 * FUNCTION: GET QUESTIONNAIRE AND CONTACT BY UNIQUE TOKEN *
 ***********************************************************/
function get_questionnaire_tracking_by_token($token){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id tracking_id, t1.percent response_percent, t1.status questionnaire_status, t2.id questionnaire_id, t2.name questionnaire_name, t3.id contact_id, t3.name contact_name, t3.company contact_company, t3.email contact_email, t3.phone contact_phone, t3.salt contact_salt, t3.password contact_password, t3.manager contact_manager
        FROM 
            questionnaire_tracking t1
            LEFT JOIN  questionnaires t2 on t1.questionnaire_id=t2.id
            LEFT JOIN  assessment_contacts t3 on t1.contact_id=t3.id
        WHERE
            t1.token = :token
    ";
    
    $stmt = $db->prepare($sql);
    
    $stmt->bindParam(":token", $token, PDO::PARAM_STR, 40);
    $stmt->execute();

    // Get questionnaire and contact 
    $questionnaire_contact = $stmt->fetch();

    // Close the database connection
    db_close($db);
    
    return $questionnaire_contact;
}

/************************************************
 * FUNCTION: CHECK IF CONTATER IS AUTHENTICATED *
 ************************************************/
function check_contact_authentication(){
    if(isset($_SESSION['contact_id']) && $_SESSION['contact_id']){
        return true;
    }else{
        return false;
    }
}

/***********************************
 * FUNCTION: IS VALID CONTACT USER *
 ***********************************/
function is_valid_contact_user($token, $pass)
{
    $questionnaire_contact = get_questionnaire_tracking_by_token($token);
    $email  = $questionnaire_contact['contact_email'];
    $salt   = $questionnaire_contact['contact_salt'];

    // Hash the salt
    $salt_hash = oldGenerateSalt($salt);
    $providedPassword = generateHash($salt_hash, $pass);

    // Get the stored password
    $storedPassword = $questionnaire_contact['contact_password'];

    // If the passwords are equal
    if ( $providedPassword == $storedPassword)
    {
        return true;
    }
    else return false;
}

/*******************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULTS *
 *******************************************/
function display_questionnaire_results(){
    global $lang;
    global $escaper;

    $tableID = "questionnaire-results-table";
    echo "
        <div class='well' id='questionnaire_result_filter_form'>
            <form method='GET'>
                <div class='row-fluid'>
                    <div class='span3'>
                        <div class='well'>
                            <h4>".$escaper->escapeHtml($lang['Company']).":</h4>
                            <input type='text' id='company' >
                        </div>
                    </div>
                    <div class='span3'>
                        <div class='well'>
                            <h4>".$escaper->escapeHtml($lang['Contact']).":</h4>
                            <input type='text' id='contact' >
                        </div>
                    </div>
                    <div class='span3'>
                        <div class='well'>
                            <h4>".$escaper->escapeHtml($lang['DateSent']).":</h4>
                            <input type='text' id='date_sent' class='datepicker'>
                        </div>
                    </div>
                    <div class='span3'>
                        <div class='well'>
                            <h4>".$escaper->escapeHtml($lang['Status']).":</h4>
                            <select id='status'>
                                <option value='all'>".$escaper->escapeHtml($lang['ALL'])."</option>
                                <option value='0'>".$escaper->escapeHtml($lang['Incomplete'])."</option>
                                <option value='1'>".$escaper->escapeHtml($lang['Complete'])."</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    ";
    
    echo "
        <script>
            $(\".datepicker\").datepicker({dateFormat: \"yy-mm-dd\"});
            var form = $(\"#questionnaire_result_filter_form\");
            $(document).ready(function(){
                $(\"input, select\", form).change(function(){
                    $(\"#{$tableID}\").DataTable().draw();
                })
            })
        </script>
    ";
    
    echo "
        <table class=\"table risk-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead >
                <tr>
                    <th >".$escaper->escapeHtml($lang['QuestionnaireName'])."</th>
                    <th >".$escaper->escapeHtml($lang['Company'])."</th>
                    <th >".$escaper->escapeHtml($lang['Contact'])."</th>
                    <th width='150px'>".$escaper->escapeHtml($lang['PercentCompleted'])."</th>
                    <th width='100px'>".$escaper->escapeHtml($lang['DateSent'])."</th>
                    <th width='100px'>".$escaper->escapeHtml($lang['Status'])."</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            
            var pageLength = 10;
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/assessment/questionnaire/results/dynamic',
                    data: function(d){
                        d.company   = $('#company').val();
                        d.contact   = $('#contact').val();
                        d.date_sent = $('#date_sent').val();
                        d.status    = $('#status').val();
                    },
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
                
            })
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-contact--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisContact'])."</label>
              </div>

              <input type=\"hidden\" name=\"contact_id\" value=\"\" />
              <div class=\"form-group text-center control-delete-actions\">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_contact\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>

          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.contact-delete-btn', function(){
                \$('#aseessment-contact--delete [name=contact_id]').val(\$(this).data('id'));
            })
        </script>
    ";
}

/*****************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE TRAIL *
 *****************************************/
function display_questionnaire_trail(){
    global $lang;
    global $escaper;
    
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

    echo "
        <h4>".$escaper->escapeHtml($lang['QuestionnaireAuditTrail'])."</h4>
        <select name=\"days\" id=\"days\" onchange=\"javascript: submit()\">
            <option value=\"7\" ".(($days == 7) ? " selected" : "").">Past Week</option>
            <option value=\"30\" ".(($days == 30) ? " selected" : "").">Past Month</option>
            <option value=\"90\" ".(($days == 90) ? " selected" : "").">Past Quarter</option>
            <option value=\"180\" ".(($days == 180) ? " selected" : "").">Past 6 Months</option>
            <option value=\"365\" ".(($days == 365) ? " selected" : "").">Past Year</option>
            <option value=\"3650\" ".(($days == 36500) ? " selected" : "").">All Time</option>
        </select>
    ";
    
    echo "
        <script>
            \$('#days').change(function(){
                document.location.href = BASE_URL + '/assessments/questionnaire_trail.php?days=' + \$(this).val();
            })
        </script>
    ";
    
    get_audit_trail(NULL, $days, ['contact', 'questionnaire_question', 'questionnaire_template', 'questionnaire']);
}

/*******************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULTS *
 *******************************************/
function get_assessment_questionnaire_results($start=0, $length=-1, $filters=false, $columnName=false, $columnDir=false){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS t1.questionnaire_id, t1.token, t1.percent, t1.status tracking_status, t1.sent_at, t2.name questionnaire_name, t3.company contact_company, t3.name contact_name, t3.email contact_email
        FROM `questionnaire_tracking` t1 
            INNER JOIN `questionnaires` t2 ON t1.questionnaire_id=t2.id
            LEFT JOIN `assessment_contacts` t3 ON t1.contact_id=t3.id  
    ";
    if($filters !== false && is_array($filters)){
        $wheres = array();
        if($filters['company']){
            $wheres[] = "t3.company like :company";
        }
        if($filters['contact']){
            $wheres[] = "t3.name like :contact";
        }
        if($filters['date_sent']){
            $wheres[] = "t1.sent_at like :date_sent";
        }
        if($filters['status'] != "all"){
            $wheres[] = "t1.status=:status";
        }
        if($wheres){
            $sql .= " WHERE ". implode(" and ", $wheres) . " ";
        }
    }
    
    if($columnName == "questionnaire_name"){
        $sql .= " ORDER BY t2.name {$columnDir} ";
    }
    elseif($columnName == "company"){
        $sql .= " ORDER BY t3.company {$columnDir} ";
    }
    elseif($columnName == "contact"){
        $sql .= " ORDER BY t3.name {$columnDir} ";
    }
    elseif($columnName == "percent"){
        $sql .= " ORDER BY t1.percent {$columnDir} ";
    }
    elseif($columnName == "date_sent"){
        $sql .= " ORDER BY t1.sent_at {$columnDir} ";
    }
    elseif($columnName == "status"){
        $sql .= " ORDER BY t1.status {$columnDir} ";
    }
    else{
        $sql .= " ORDER BY t1.sent_at DESC ";
    }
    
    
    if($length != -1){
        $sql .= " LIMIT {$start}, {$length}; ";
    }

    $stmt = $db->prepare($sql);

    if($filters !== false && is_array($filters)){
        if($filters['company']){
            $filterCompany = "%".$filters['company']."%";
            $stmt->bindParam(":company", $filterCompany, PDO::PARAM_STR, 100);
        }
        if($filters['contact']){
            $filterContact = "%".$filters['contact']."%";
            $stmt->bindParam(":contact", $filterContact, PDO::PARAM_STR, 100);
        }
        if($filters['date_sent']){
            $filterDateSent = "%".$filters['date_sent']."%";
            $stmt->bindParam(":date_sent", $filterDateSent, PDO::PARAM_STR, 100);
        }
        if($filters['status'] != "all"){
            $filters['status'] = (int)$filters['status'];
            $stmt->bindParam(":status", $filters['status'], PDO::PARAM_INT);
        }
    }

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    return array($recordsTotal, $array);
}

/*******************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULTS *
 *******************************************/
function display_questionnaire_fullview(){
    global $escaper, $lang;
    
    $token = $escaper->escapeHtml($_GET['token']);
    $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);
    
    echo "
        <div class=\"well\">
            <h2>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_tracking_info['questionnaire_name'])."</h2>
    ";
    
    $db_responses = get_questionnaire_responses($token);
    
    $questionnaire_id = $questionnaire_tracking_info['questionnaire_id'];
    $questinnaire = get_questionnaires_by_id($questionnaire_id);

    if(isset($questinnaire['templates'])){
        $templateIndex = 1;
        foreach($questinnaire['templates'] as $key => $template){
            
            echo "<div><h3>".$templateIndex.".&nbsp;".$escaper->escapeHtml($template['template_name'])."</h3></div>";

            // Get questions of this template
            $template_id = $template['template_id'];
            $template_in_detail = get_assessment_questionnaire_template_by_id($template['template_id']);
            if(isset($template_in_detail['questions'])){
                $questions = $template_in_detail['questions'];
            }else{
                $questions = [];
            }
            
            foreach($questions as $questionIndex => &$question)
            {
                $question_id = $question['question_id'];
                $question = get_questionnaire_question($question_id);
                echo "<div class='questionnaire-question'>";
                    echo "<p>".++$questionIndex.")&nbsp;&nbsp; ".$escaper->escapeHtml($question['question'])."</p>";
                    echo "<div class='questionnaire-answers left-offset-30'>";
                    echo "<p>- Answers</p>";
                    echo "<ul>";
                    foreach($question['answers'] as $answer){
                        // Check if this answer was already posted
//                            echo $db_responses[$template_id][$question_id]['answer']."___".$answer;
                        if(isset($db_responses[$template_id][$question_id]['answer']) && $db_responses[$template_id][$question_id]['answer'] == $answer['answer']){
                            echo "<li><strong class='answer-text'>{$answer['answer']}</strong></li>";
                        }
                        else
                        {
                            echo "<li><span class='answer-text'>{$answer['answer']}</span></li>";
                        }
                    }
                    echo "</ul>";

                    if(!empty($db_responses[$template_id][$question_id]['additional_information'])){
                        echo "<p>- ".$escaper->escapeHtml($lang['AdditionalInformation']).":</p>";
                        
                        echo "<p>".(isset($db_responses[$template_id][$question_id]['additional_information']) ? $escaper->escapeHtml($db_responses[$template_id][$question_id]['additional_information']) : "")."</p>";
                    }
                    echo "</div>";
                echo "</div>";
                
            }
            $templateIndex++;
        }

        if(get_assessment_files($questionnaire_tracking_info['tracking_id'])){
            echo "
                <div class='row-fluid attachment-container'>
                    <div ><strong>".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </strong></div>
                    <div >
                        <div class=\"file-uploader\">
                            <ul class=\"exist-files\">
                                ";
                                display_assessment_files(true);
                            echo "
                            </ul>
                        </div>
                    </div>
                </div>
            ";
        }
        echo "</div>";
        // Comment
        display_questionnaire_result_comment($questionnaire_tracking_info['tracking_id']);
    }
}

/**************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULT COMMENT *
 **************************************************/
function display_questionnaire_result_comment($tracking_id)
{
    global $escaper, $lang;
    
    $tracking_id = (int)$tracking_id;
    
    echo "
        <div class=\"row-fluid comments--wrapper\" style='margin-top: 0px'>

            <div class=\"well\">
                <h4 class=\"collapsible--toggle clearfix\">
                    <span><i class=\"fa  fa-caret-right\"></i>".$escaper->escapeHtml($lang['Comments'])."</span>
                    <a href=\"#\" class=\"add-comments pull-right\"><i class=\"fa fa-plus\"></i></a>
                </h4>

                <div class=\"collapsible\" style='display:none'>
                    <div class=\"row-fluid\">
                        <div class=\"span12\">

                            <form id=\"comment\" class=\"comment-form\" name=\"add_comment\" method=\"post\" action=\"/management/comment.php?id={$tracking_id}\">
                                <input type='hidden' name='id' value='{$tracking_id}'>
                                <textarea style=\"width: 100%; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box;\" name=\"comment\" cols=\"50\" rows=\"3\" id=\"comment-text\" class=\"comment-text\"></textarea>
                                <div class=\"form-actions text-right\" id=\"comment-div\">
                                    <input class=\"btn\" id=\"rest-btn\" value=\"".$escaper->escapeHtml($lang['Reset'])."\" type=\"reset\" />
                                    <button id=\"comment-submit\" type=\"submit\" name=\"submit\" class=\"comment-submit btn btn-primary\" >".$escaper->escapeHtml($lang['Submit'])."</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class=\"row-fluid\">
                        <div class=\"span12\">
                            <div class=\"comments--list clearfix\">
                                ".get_questionnaire_result_comment_list($tracking_id)."
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.collapsible--toggle span', function(event) {
                event.preventDefault();
                var container = \$(this).parents('.comments--wrapper');
                \$(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
                \$(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                if($('.collapsible', container).is(':visible') && $('.add-comments', container).hasClass('rotate')){
                    $('.add-comments', container).click()
                }
            });

            $('body').on('click', '.add-comments', function(event) {
                event.preventDefault();
                var container = \$(this).parents('.comments--wrapper');
                if(!$('.collapsible', container).is(':visible')){
                    $(this).parents('.collapsible--toggle').next('.collapsible').slideDown('400');
                    $(this).parent().find('span i').removeClass('fa-caret-right');
                    $(this).parent().find('span i').addClass('fa-caret-down');
                }
                $(this).toggleClass('rotate');
                $('.comment-form', container).fadeToggle('100');
            });

            $('body').on('click', '.comment-submit', function(e){
                e.preventDefault();
                var container = $('.comments--wrapper');
                
                if(!$('.comment-text', container).val()){
                    $('.comment-text', container).focus();
                    return;
                }
                
                var risk_id = $('.large-text', container).html();
                
                var getForm = \$(this).parents('form', container);
                var form = new FormData($(getForm)[0]);

                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/assessment/questionnaire/save_result_comment',
                    data: form,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        $('.comments--list', container).html(data.data);
                        $('.comment-text', container).val('')
                        $('.comment-text', container).focus()
                    }
                })
            })
        
        </script>
    ";
      
    return true;
}

/*******************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULT COMMENT LIST *
 *******************************************************/
function get_questionnaire_result_comment_list($tracking_id)
{
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the comments
    $stmt = $db->prepare("SELECT a.date, a.comment, b.name FROM questionnaire_result_comments a LEFT JOIN user b ON a.user = b.value WHERE a.tracking_id=:tracking_id ORDER BY a.date DESC");

    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);

    $stmt->execute();

    // Store the list in the array
    $comments = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    $returnHTML = "";
    foreach ($comments as $comment)
    {
        $text = $comment['comment'];
        $date = date(DATETIME, strtotime($comment['date']));
        $user = $comment['name'];
        
        if($text != null){
            $returnHTML .= "<p class=\"comment-block\">\n";
            $returnHTML .= "<b>" . $escaper->escapeHtml($date) ." by ". $escaper->escapeHtml($user) ."</b><br />\n";
            $returnHTML .= $escaper->escapeHtml($text);
            $returnHTML .= "</p>\n";
        }
    }

    return $returnHTML;
    
}

/**********************************************
 * FUNCTION: SAVE QUESTIONNARE RESULT COMMENT *
 **********************************************/
function save_questionnaire_result_comment($tracking_id, $comment){
    $user    =  $_SESSION['uid'];
    
    // Open the database connection
    $db = db_open();
    
    $sql = "
        INSERT INTO `questionnaire_result_comments`(`tracking_id`, `user`, `comment`) VALUES(:tracking_id, :user, :comment);
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_STR);
    $stmt->bindParam(":comment", $comment, PDO::PARAM_INT);
    $stmt->bindParam(":user", $user, PDO::PARAM_INT);
    
    // Insert a test result
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
    
    $sql = "SELECT t1.token FROM questionnaire_tracking t1 WHERE t1.id = :tracking_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();
    $tracking = $stmt->fetch();
    
    $message = "A comment for questionnaire result was added for token \"{$tracking['token']}\" by username \"" . $_SESSION['user']."\".";
    write_log($tracking_id+1000, $_SESSION['uid'], $message, 'contact');
}

/*****************************************
 * FUNCTION: DOWNLOAD QUESTIONNAIRE FILE *
 *****************************************/
function download_questionnaire_file($unique_name){
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the file from the database
    $stmt = $db->prepare("SELECT * FROM questionnaire_files WHERE BINARY unique_name=:unique_name");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->execute();

    // Store the results in an array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array))
    {
        // Do nothing
        exit;
    }
    else
    {
        header("Content-length: " . $array['size']);
        header("Content-type: " . $array['type']);
        header("Content-Disposition: attachment; filename=" . $escaper->escapeUrl($array['name']));
        echo $array['content'];
        exit;
    }
}

/****************************
 * FUNCTION: LOGOUT CONTACT *
 ****************************/
function logout_contact()
{
    // Deny access
    unset($_SESSION["contact_id"]);

    // Reset the session data
    $_SESSION = array();

    // Send a Set-Cookie to invalidate the session cookie
    if (ini_get("session.use_cookies"))
    {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    }

    // Destroy the session
    session_destroy();    
}

/********************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT CONTACTS DATATABLE *
 ********************************************************/
function getAssessmentContacts(){
    global $lang;
    global $escaper;

    $draw = $escaper->escapeHtml($_GET['draw']);
    
    $assessment_contacts = get_assessment_contacts();
 
    $recordsTotal = count($assessment_contacts);
    
    $data = array();
    
    foreach ($assessment_contacts as $key=>$contact)
    {
        // If it is not requested to view all
        if($_GET['length'] != -1){
            if($key < $_GET['start']){
                continue;
            }
            if($key >= ($_GET['start'] + $_GET['length'])){
                break;
            }
        }
        
        $data[] = [
            $contact['company'],
            $contact['name'],
            $contact['email'],
            $contact['phone'],
            $contact['manager_name'],
            "<a href=\"#aseessment-contact--delete\" data-toggle=\"modal\" class=\"control-block--delete contact-delete-btn pull-right\" data-id=\"". $contact['id'] ."\"><i class=\"fa fa-trash\"></i></a><a href=\"contacts.php?action=edit&id=". $contact['id'] ."\" class=\"pull-right\" data-id=\"1\"><i class=\"fa fa-pencil-square-o\"></i></a>",
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );
    echo json_encode($result);
    exit;    
}

/**********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE QUESTIONS DATATABLE *
 **********************************************************************/
function getAssessmentQuestionnaireQuestions(){
    global $lang;
    global $escaper;

    $draw = $escaper->escapeHtml($_GET['draw']);
    
    list($recordsTotal, $questionnaire_questions)= get_assessment_questionnaire_questions($_GET['start'], $_GET['length']);
 
    $data = array();
    
    foreach ($questionnaire_questions as $key=>$questionnaire_question)
    {
        $answersHtml = "";
        foreach($questionnaire_question['answers'] as $answer){
            $answersHtml .= "<li>". $escaper->escapeHtml($answer['answer']) ."</li>";
        }
        
        $data[] = [
            "
            <div class='row-fluid'>
                <div class='span1'>&nbsp;</div>
                <div class='span11'>
                    <a href=\"#aseessment-questionnaire-question--delete\" data-toggle=\"modal\" class=\"control-block--delete delete-btn pull-right\" data-id=\"". $questionnaire_question['id'] ."\"><i class=\"fa fa-trash\"></i></a><a href=\"questionnaire_questions.php?action=edit_question&id=". $questionnaire_question['id'] ."\" class=\"pull-right\" data-id=\"1\"><i class=\"fa fa-pencil-square-o\"></i></a>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span1'><strong>".$escaper->escapeHtml($lang['Question']).":</strong> </div>
                <div class='span11'>". $escaper->escapeHtml($questionnaire_question['question']) ."</div>
            </div>
            <div class='row-fluid'>
                <div class='span1'><strong>".$escaper->escapeHtml($lang['RiskScore']).":</strong> </div>
                <div class='span11'>". $escaper->escapeHtml($questionnaire_question['calculated_risk']) ."</div>
            </div>
            <div class='row-fluid'>
                <div class='span1'><strong>".$escaper->escapeHtml($lang['Answers']).":</strong> </div>
                <div class='span11'>
                    <ul>
                        {$answersHtml}
                    </ul>
                        
                </div>
            </div>
            ",
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );
    echo json_encode($result);
    exit;    
}

/*********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE TEMPLATE DATATABLE *
 *********************************************************************/
function questionnaireTemplateDynamicAPI(){
    global $lang;
    global $escaper;

    $draw = $escaper->escapeHtml($_GET['draw']);
    
    list($recordsTotal, $questionnaire_templates) = get_assessment_questionnaire_templates($_GET['start'], $_GET['length']);
 
    $data = array();
    
    foreach ($questionnaire_templates as $key=>$questionnaire_template)
    {
        $data[] = [
            $escaper->escapeHtml($questionnaire_template['name']),
            "<a href=\"#aseessment-questionnaire-template--delete\" data-toggle=\"modal\" class=\"control-block--delete delete-btn pull-right\" data-id=\"". $questionnaire_template['id'] ."\"><i class=\"fa fa-trash\"></i></a><a href=\"questionnaire_templates.php?action=edit_template&id=". $questionnaire_template['id'] ."\" class=\"pull-right\" data-id=\"1\"><i class=\"fa fa-pencil-square-o\"></i></a>"
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );

    echo json_encode($result);
    exit;    
}

/************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE DATATABLE *
 ************************************************************/
function questionnaireDynamicAPI(){
    global $lang;
    global $escaper;

    $draw = $escaper->escapeHtml($_GET['draw']);
    
    list($recordsTotal, $questionnaires) = get_assessment_questionnaires($_GET['start'], $_GET['length']);
    
    $data = array();
    
    foreach ($questionnaires as $key=>$questionnaire)
    {
        $data[] = [
            $escaper->escapeHtml($questionnaire['name']),
            "<a href=\"#aseessment-questionnaire--delete\" data-toggle=\"modal\" class=\"control-block--delete delete-btn pull-right\" data-id=\"". $questionnaire['id'] ."\"><i class=\"fa fa-trash\"></i></a><a href=\"questionnaires.php?action=edit&id=". $questionnaire['id'] ."\" class=\"pull-right\" data-id=\"1\"><i class=\"fa fa-pencil-square-o\"></i></a>",
            "<div class='text-center'><button class='btn send-questionnaire' data-id='{$questionnaire['id']}' >".$escaper->escapeHtml($lang['Send'])."</button></div>",
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );

    echo json_encode($result);
    exit;    
}

/********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE RESULTS DATATABLE *
 ********************************************************************/
function questionnaireResultsDynamicAPI(){
    global $lang;
    global $escaper;

    $draw = $escaper->escapeHtml($_GET['draw']);
    
    $orderColumn = (int)$_GET['order'][0]['column'];
    $orderDir = $escaper->escapeHtml($_GET['order'][0]['dir']);
    
    $columnNames = array(
        "questionnaire_name",
        "company",
        "contact",
        "percent",
        "date_sent",
        "status",
    );
    
    // Filter params
    $filters = array(
        "company"   => $escaper->escapeHtml($_GET['company']),
        "contact"   => $escaper->escapeHtml($_GET['contact']),
        "date_sent" => $escaper->escapeHtml($_GET['date_sent']),
        "status"    => $escaper->escapeHtml($_GET['status']),
    );
    
    list($recordsTotal, $questionnaire_results) = get_assessment_questionnaire_results($_GET['start'], $_GET['length'], $filters, $columnNames[$orderColumn], $orderDir);
    
    $data = array();
    
    foreach ($questionnaire_results as $key=>$questionnaire_result)
    {
        $data[] = [
            "<a class='text-left' href='".$_SESSION['base_url']."/assessments/questionnaire_results.php?action=full_view&token=".$questionnaire_result['token']."'>".$escaper->escapeHtml($questionnaire_result['questionnaire_name'])."</a>",
            $escaper->escapeHtml($questionnaire_result['contact_company']),
            $escaper->escapeHtml($questionnaire_result['contact_name']),
            "<div class='text-right'>{$questionnaire_result['percent']}%</div>",
            "<div class='text-center'>".$questionnaire_result['sent_at']."</div>",
            $escaper->escapeHtml($questionnaire_result['tracking_status'] ? $lang['Completed'] : $lang['Incomplete'])
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );

    echo json_encode($result);
    exit;    
}

/**********************************************
 * FUNCTION: SAVE QUESTIONNARE RESULT COMMENT *
 **********************************************/
function saveQuestionnaireResultCommentAPI(){
    global $escaper, $lang;
    
    $tracking_id =  (int)$_POST['id'];
    $comment =  $escaper->escapeHtml($_POST['comment']);
    
    // Save comment
    save_questionnaire_result_comment($tracking_id, $comment);
    
    $commentList = get_questionnaire_result_comment_list($tracking_id);

    json_response(200, "Comment List", $commentList);
}


?>
