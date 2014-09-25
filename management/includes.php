<?php

// Include the language file
require_once(language_file());

function get_plan_mitigations()
{
        global $lang;
	echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<p>". $lang['MitigationPlanningHelp'] ."</p>\n";
        get_risk_table(1);
        echo "</div>\n";
        echo "</div>\n";
}

function get_management_review()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<p>". $lang['ManagementReviewHelp'] ."</p>\n";
        get_risk_table(2);
        echo "</div>\n";
        echo "</div>\n";
}

function get_prioritize_planning()
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>1)". $lang['AddAndRemoveProjects'] ."</h4>\n";
        echo "<p>". $lang['AddAndRemoveProjectsHelp'] .".</p>\n";
        echo "<form name=\"project\" method=\"post\" action=\"\">\n";
        echo "<p>\n";
        echo $lang['AddNewProjectNamed'] ." <input name=\"new_project\" type=\"text\" maxlength=\"100\" size=\"20\" />&nbsp;&nbsp;<input type=\"submit\" value=\"". $lang['Add'] ."\" name=\"add_project\" /><br />\n";
        echo $lang['DeleteCurrentProjectNamed']." ";
        echo create_dropdown("projects") ."&nbsp;&nbsp; <input type=\"submit\" value=\"". $lang['Delete'] ."\" name=\"delete_project\" />\n";
        echo "</p>";
        echo "</form>";
        echo "</div>";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>2)". $lang['AddUnassignedRisksToProjects'] ."</h4>\n";
        echo "<p>". $lang['AddUnassignedRisksToProjectsHelp'] .".</p>\n";
        get_project_tabs();
        echo "</div>\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>3)". $lang['PrioritizeProjects'] ."</h4>\n";
        echo "<p>". $lang['PrioritizeProjectsHelp'] .".</p>\n";
        get_project_list();
        echo "</div>";
        echo "<div class=\"hero-unit\">\n";
        echo "<h4>4)". $lang['DetermineProjectStatus'] ."</h4>\n";
        echo "<p>". $lang['ProjectStatusHelp'] ."</p>\n";
        get_project_status();
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
}

function get_review_risks()
{
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        get_reviews_table(3);
        echo "</div>\n";
        echo "</div>\n";
}

function get_view($id, $calculated_risk, $subject, $status, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, 
        $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, 
        $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, 
        $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, 
        $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, 
        $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, 
        $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, 
        $OWASPPrivacyViolation, $custom, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, 
        $team, $technology, $owner, $manager, $assessment, $notes, $mitigation_date, $planning_strategy, $mitigation_effort, 
        $current_solution, $security_requirements, $security_recommendations, $review_date, $reviewer, $review, 
        $next_step, $next_review, $comments)
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        view_top_table($id, $calculated_risk, $subject, $status, true);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div id=\"scoredetails\" class=\"row-fluid\" style=\"display: none;\">\n";
        echo "<div class=\"well\">\n";
        // Scoring method is Classic
        if ($scoring_method == "1")
        {       
                classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2")
        {
                cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3")
        {
                dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4")
        {
                owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5")
        {
                custom_scoring_table($id, $custom);
        }
        echo "</div>\n";
        echo "</div>\n";
        echo "<div id=\"updatescore\" class=\"row-fluid\" style=\"display: none;\">\n";
        echo "<div class=\"well\">\n";  
        // Scoring method is Classic
        if ($scoring_method == "1")
        {
                edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2")
        {
                edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3")
        {
                edit_dread_score($DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4")
        {
                edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5")
        {
                edit_custom_score($custom);
        }     
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span4\">\n";
        echo "<div class=\"well\">\n";
        echo "<form name=\"details\" method=\"post\" action=\"\">\n";
        // If the user has selected to edit the risk
        if (isset($_POST['edit_details']))
        {
                edit_risk_details($submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $assessment, $notes);
        }
        // Otherwise we are just viewing the risk
        else
        {
                view_risk_details($submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);
        }
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"span4\">\n";
        echo "<div class=\"well\">\n";
        echo "<form name=\"mitigation\" method=\"post\" action=\"\">\n";
        // If the user has selected to edit the mitigation
        if (isset($_POST['edit_mitigation']))
        { 
                edit_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations);
        }
        // Otherwise we are just viewing the mitigation
        else
        {
                view_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations);
        }
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"span4\">\n";
        echo "<div class=\"well\">\n";
        echo "<form name=\"review\" method=\"post\" action=\"\">\n";
        view_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments);
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<h4>". $lang['Comments'] ."</h4>\n";
        get_comments($id);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<h4>". $lang['AuditTrail'] ."</h4>\n";
        get_audit_trail($id);
        echo "</div>\n";
        echo "</div>\n";
}


function get_mgmt_review($id, $calculated_risk, $subject, $status, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, 
        $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, 
        $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, 
        $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, 
        $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, 
        $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, 
        $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, 
        $OWASPPrivacyViolation, $custom, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, 
        $team, $technology, $owner, $manager, $assessment, $notes, $mitigation_date, $planning_strategy, $mitigation_effort, 
        $current_solution, $security_requirements, $security_recommendations, $review_date, $reviewer, $review, 
        $next_step, $next_review, $comments)
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        view_top_table($id, $calculated_risk, $subject, $status, true);
        echo "</div>\n";
        echo "<div id=\"scoredetails\" class=\"row-fluid\" style=\"display: none;\">\n";
        echo "<div class=\"well\">\n";
        // Scoring method is Classic
        if ($scoring_method == "1")
        {
                classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2")
        {
                cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3")
        {
                dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4")
        {
                owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5")
        {
                custom_scoring_table($id, $custom);
        }
        echo "</div>\n";
        echo "</div>\n";
        echo "<div id=\"updatescore\" class=\"row-fluid\" style=\"display: none;\">\n";
        echo "<div class=\"well\">\n";  
        // Scoring method is Classic
        if ($scoring_method == "1")
        {
                edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2")
        {
                edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3")
        {
                edit_dread_score($DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4")
        {
                edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5")
        {
                edit_custom_score($custom);
        }     
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"well\">\n";
        edit_review_submission($review, $next_step, $next_review, $comments);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span6\">\n";
        echo "<div class=\"well\">\n";
        view_risk_details($submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"span6\">\n";
        echo "<div class=\"well\">\n";
        view_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations);
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<h4>". $lang['Comments'] ."</h4>\n";
        get_comments($id);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<h4>". $lang['AuditTrail'] ."</h4>\n";
        get_audit_trail($id);
        echo "</div>\n";
        echo "</div>\n";
}


function get_mitigate($id, $calculated_risk, $subject, $status, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, 
        $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, 
        $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, 
        $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, 
        $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, 
        $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, 
        $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, 
        $OWASPPrivacyViolation, $custom, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, 
        $team, $technology, $owner, $manager, $assessment, $notes, $mitigation_date, $planning_strategy, $mitigation_effort, 
        $current_solution, $security_requirements, $security_recommendations, $review_date, $reviewer, $review, 
        $next_step, $next_review, $comments)
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        view_top_table($id, $calculated_risk, $subject, $status, true);
        echo "</div>\n";
        echo "<div id=\"scoredetails\" class=\"row-fluid\" style=\"display: none;\">\n";
        echo "<div class=\"well\">\n";
        // Scoring method is Classic
        if ($scoring_method == "1")
        {
                classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2")
        {
                cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3")
        {
                dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4")
        {
                owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5")
        {
                custom_scoring_table($id, $custom);
        }
        echo "</div>\n";
        echo "</div>\n";
        echo "<div id=\"updatescore\" class=\"row-fluid\" style=\"display: none;\">\n";
        echo "<div class=\"well\">\n";  
        // Scoring method is Classic
        if ($scoring_method == "1")
        {
                edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2")
        {
                edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3")
        {
                edit_dread_score($DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4")
        {
                edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5")
        {
                edit_custom_score($custom);
        }     
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"well\">\n";
        edit_mitigation_submission($planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span6\">\n";
        echo "<div class=\"well\">\n";
        view_risk_details($submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"span6\">\n";
        echo "<div class=\"well\">\n";
        view_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations);
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<h4>". $lang['Comments'] ."</h4>\n";
        get_comments($id);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<h4>". $lang['AuditTrail'] ."</h4>\n";
        get_audit_trail($id);
        echo "</div>\n";
        echo "</div>\n";
}


function get_close($id, $calculated_risk, $subject, $status)
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        view_top_table($id, $calculated_risk, $subject, $status, false);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<form name=\"close_risk\" method=\"post\" action=\"\">\n";
        echo "<h4>". $lang['CloseRisk'] ."</h4>\n";
        echo $lang['Reason']. ":" .create_dropdown("close_reason"). "<br />\n";
        echo "<label>". $lang['CloseOutInformation'] ."</label>\n";
        echo "<textarea name=\"note\" cols=\"50\" rows=\"3\" id=\"note\"></textarea>\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">". $lang['Submit'] ."</button>\n";
        echo "<input class=\"btn\" value=\"". $lang['Reset'] ."\" type=\"reset\">\n";
        echo "</div>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        
}


function get_comment($id, $calculated_risk, $subject, $status)
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        view_top_table($id, $calculated_risk, $subject, $status, false);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<form name=\"add_comment\" method=\"post\" action=\"\">\n";
        echo "<label>". $lang['Comment'] ."</label>\n";
        echo "<textarea name=\"comment\" cols=\"50\" rows=\"3\" id=\"comment\"></textarea>\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">". $lang['Submit'] ."</button>\n";
        echo "<input class=\"btn\" value=\"". $lang['Reset'] ."\" type=\"reset\">\n";
        echo "</div>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        
}


function get_allreviews($id, $calculated_risk, $subject, $status, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, 
        $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, 
        $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, 
        $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, 
        $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, 
        $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, 
        $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, 
        $OWASPPrivacyViolation, $custom)
{
        global $lang;
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        view_top_table($id, $calculated_risk, $subject, $status, true);
        echo "</div>\n";
        echo "</div>\n";
        echo "<div id=\"scoredetails\" class=\"row-fluid\" style=\"display: none;\">\n";
        echo "<div class=\"well\">\n";
        // Scoring method is Classic
        if ($scoring_method == "1")
        {
                classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2")
        {
                cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3")
        {
                dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4")
        {
                owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5")
        {
                custom_scoring_table($id, $custom);
        }
        echo "</div>\n";
        echo "</div>\n";
        echo "<div id=\"updatescore\" class=\"row-fluid\" style=\"display: none;\">\n";
        echo "<div class=\"well\">\n";  
        // Scoring method is Classic
        if ($scoring_method == "1")
        {
                edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2")
        {
                edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3")
        {
                edit_dread_score($DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4")
        {
                edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5")
        {
                edit_custom_score($custom);
        }     
        echo "</div>\n";
        echo "</div>\n";
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"well\">\n";
        echo "<h4>". $lang['ReviewHistory'] ."</h4>\n";
        get_reviews($id);
        echo "</div>\n";
        echo "</div>\n";
}


?>
