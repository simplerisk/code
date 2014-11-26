<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/****************************
 * FUNCTION: VIEW TOP TABLE *
 ****************************/
function view_top_table($id, $calculated_risk, $subject, $status, $show_details = false)
{
	global $lang;
	global $escaper;

    $result = "";

    $result .= "<table width=\"100%\" cellpadding=\"10\" cellspacing=\"0\" style=\"border:none;\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td width=\"100\" valign=\"middle\" halign=\"center\">\n";

    $result .=  "<table width=\"100\" height=\"100\" border=\"10\" class=" . $escaper->escapeHtml(get_risk_color($calculated_risk)) . ">\n";
    $result .=  "<tr>\n";
    $result .=  "<td valign=\"middle\" halign=\"center\">\n";
    $result .=  "<center>\n";
    $result .=  "<font size=\"72\">" . $escaper->escapeHtml($calculated_risk) . "</font><br />\n";
    $result .=  "(". $escaper->escapeHtml(get_risk_level_name($calculated_risk)) . ")\n";
    $result .=  "</center>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";

    $result .=  "</td>\n";
    $result .=  "<td valign=\"left\" halign=\"center\">\n";

    $result .=  "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td width=\"100\"><h4>". $escaper->escapeHtml($lang['RiskId']) .":</h4></td>\n";
    $result .=  "<td><h4>" . $escaper->escapeHtml($id) . "</h4></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td width=\"100\"><h4>". $escaper->escapeHtml($lang['Subject']) .":</h4></td>\n";
    $result .=  "<td><h4>" . $escaper->escapeHtml($subject) . "</h4></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td width=\"100\"><h4>". $escaper->escapeHtml($lang['Status']) .":</h4></td>\n";
    $result .=  "<td><h4>" . $escaper->escapeHtml($status) . "</h4></td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";

    $result .=  "</td>\n";
    $result .=  "<td valign=\"top\">\n";
    $result .=  "<div class=\"btn-group pull-right\">\n";
    $result .=  "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">". $escaper->escapeHtml($lang['RiskActions']) ."<span class=\"caret\"></span></a>\n";
    $result .=  "<ul class=\"dropdown-menu\">\n";

        // If the risk is closed, offer to reopen
        if ($status == "Closed")
        {
            $result .=  "<li><a href=\"reopen.php?id=".$escaper->escapeHtml($id)."\">". $escaper->escapeHtml($lang['ReopenRisk']) ."</a></li>\n";
        }
        // Otherwise, offer to close
        else
        {
        	// If the user has permission to close risks
                if (isset($_SESSION["close_risks"]) && $_SESSION["close_risks"] == 1)
                {
                    $result .=  "<li><a href=\"close.php?id=".$escaper->escapeHtml($id)."\">". $escaper->escapeHtml($lang['CloseRisk']) ."</a></li>\n";
                }
        }

    $result .=  "<li><a href=\"view.php?id=" . $escaper->escapeHtml($id) . "\">". $escaper->escapeHtml($lang['EditRisk']) ."</a></li>\n";
    $result .=  "<li><a href=\"mitigate.php?id=" . $escaper->escapeHtml($id) . "\">". $escaper->escapeHtml($lang['PlanAMitigation']) ."</a></li>\n";
    $result .=  "<li><a href=\"mgmt_review.php?id=" . $escaper->escapeHtml($id) . "\">". $escaper->escapeHtml($lang['PerformAReview']) ."</a></li>\n";
    $result .=  "<li><a href=\"comment.php?id=" . $escaper->escapeHtml($id) . "\">". $escaper->escapeHtml($lang['AddAComment']) ."</a></li>\n";
    $result .=  "</ul>\n";
    $result .=  "</div>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";

	// If we want to show the details
	if ($show_details)
	{
        $result .=  "<tr>\n";
        $result .=  "<td colspan=\"3\">\n";
        $result .=  "<a href=\"#\" id=\"show\" onclick=\"javascript: showScoreDetails();\">". $escaper->escapeHtml($lang['ShowRiskScoringDetails']) ."</a>\n";
        $result .=  "<a href=\"#\" id=\"hide\" style=\"display: none;\" onclick=\"javascript: hideScoreDetails();\">". $escaper->escapeHtml($lang['HideRiskScoringDetails']) ."</a>\n";
        $result .=  "</td>\n";
        $result .=  "</tr>\n";
	}

    $result .=  "</table>\n";
    return $result;
}

/*******************************
 * FUNCTION: VIEW RISK DETAILS *
 *******************************/
function view_risk_details($id, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes)
{
	global $lang;
	global $escaper;

    $result = "";

    $result .=  "<h4>". $escaper->escapeHtml($lang['Details']) ."</h4>\n";
    $result .=  $escaper->escapeHtml($lang['SubmissionDate']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"submission_date\" id=\"submission_date\" size=\"50\" value=\"" . $escaper->escapeHtml($submission_date) . "\" title=\"" . $escaper->escapeHtml($submission_date) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Subject']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"subject\" id=\"subject\" size=\"50\" value=\"" . $escaper->escapeHtml($subject) . "\" title=\"" . $escaper->escapeHtml($subject) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['ExternalReferenceId']) .": \n";
    $result .=  "<br />\n";
    $result .=  " <input style=\"cursor: default;\" type=\"text\" name=\"reference_id\" id=\"reference_id\" size=\"20\" value=\"" . $escaper->escapeHtml($reference_id) . "\" title=\"" . $escaper->escapeHtml($reference_id) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['ControlRegulation']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"regulation\" id=\"regulation\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("regulation", $regulation)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("regulation", $regulation)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['ControlNumber']) .": \n";
    $result .=  "<br />\n";
    $result .=  " <input style=\"cursor: default;\" type=\"text\" name=\"control_number\" id=\"control_number\" size=\"20\" value=\"" . $escaper->escapeHtml($control_number) . "\" title=\"" . $escaper->escapeHtml($control_number) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['SiteLocation']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"location\" id=\"location\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("location", $location)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("location", $location)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Category']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"category\" id=\"category\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Team']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"team\" id=\"team\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("team", $team)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("team", $team)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Technology']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"technology\" id=\"technology\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("technology", $technology)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("technology", $technology)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Owner']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"owner\" id=\"owner\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['OwnersManager']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"manager\" id=\"manager\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['RiskAssessment']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea style=\"cursor: default;\" name=\"assessment\" cols=\"50\" rows=\"3\" id=\"assessment\" title=\"" . $escaper->escapeHtml($assessment) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($assessment) . "</textarea>\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['AdditionalNotes']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea style=\"cursor: default;\" name=\"notes\" cols=\"50\" rows=\"3\" id=\"notes\" title=\"" . $escaper->escapeHtml($notes) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($notes) . "</textarea>\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['SupportingDocumentation']) . ": \n";
    $result .=  "<br />\n";
    $result .= supporting_documentation($id, "view");

    // If the page is the view.php page
    if (basename($_SERVER['PHP_SELF']) == "view.php")
    {
        // Give the option to edit the risk details
        $result .=  "<div class=\"form-actions\">\n";
        $result .=  "<button type=\"submit\" name=\"edit_details\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['EditDetails']) ."</button>\n";
        $result .=  "</div>\n";
    }

    return $result;

}

/*******************************
 * FUNCTION: EDIT RISK DETAILS *
 *******************************/
function edit_risk_details($id, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $assessment, $notes)
{
	global $lang;
	global $escaper;
	
    $result = "";

    $result .=  "<h4>". $escaper->escapeHtml($lang['Details']) ."</h4>\n";
    $result .=  $escaper->escapeHtml($lang['SubmissionDate']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"submission_date\" id=\"submission_date\" size=\"50\" value=\"" . $escaper->escapeHtml($submission_date) . "\" title=\"" . $escaper->escapeHtml($submission_date) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Subject']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input type=\"text\" name=\"subject\" id=\"subject\" size=\"50\" value=\"" . $escaper->escapeHtml($subject) . "\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['ExternalReferenceId']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input type=\"text\" name=\"reference_id\" id=\"reference_id\" size=\"20\" value=\"" . $escaper->escapeHtml($reference_id) . "\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['ControlRegulation']) .": \n";
    $result .=  "<br />\n";
    $result .=  create_dropdown("regulation", $regulation);
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['ControlNumber']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input type=\"text\" name=\"control_number\" id=\"control_number\" size=\"20\" value=\"" . $escaper->escapeHtml($control_number) . "\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['SiteLocation']) .": \n";
    $result .=  "<br />\n";
    $result .=  create_dropdown("location", $location);
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Category']) .": \n";
    $result .=  "<br />\n";
    $result .=  create_dropdown("category", $category);
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Team']) .": \n";
    $result .=  "<br />\n";
    $result .=  create_dropdown("team", $team);
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Technology']) .": \n";
    $result .=  "<br />\n";
    $result .=  create_dropdown("technology", $technology);
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Owner']) .": \n";
    $result .=  "<br />\n";
    $result .=  create_dropdown("user", $owner, "owner");
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['OwnersManager']) .": \n";
    $result .=  "<br />\n";
    $result .=  create_dropdown("user", $manager, "manager");
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['RiskAssessment']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea name=\"assessment\" cols=\"50\" rows=\"3\" id=\"assessment\">" . $escaper->escapeHtml($assessment) . "</textarea>\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['AdditionalNotes']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea name=\"notes\" cols=\"50\" rows=\"3\" id=\"notes\">" . $escaper->escapeHtml($notes) . "</textarea>\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['SupportingDocumentation']) . ": \n";
    $result .=  "<br />\n";
    supporting_documentation($id, "edit");
    $result .=  "<div class=\"form-actions\">\n";
    $result .=  "<button type=\"submit\" name=\"update_details\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Update']) ."</button>\n";
    $result .=  "</div>\n";

    return $result;
}

/*************************************
 * FUNCTION: VIEW MITIGATION DETAILS *
 *************************************/
function view_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
	global $lang;
	global $escaper;
	
    $result = "";

    $result .=  "<h4>". $escaper->escapeHtml($lang['Mitigation']) ."</h4>\n";
    $result .=  $escaper->escapeHtml($lang['MitigationDate']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_date\" id=\"mitigation_date\" size=\"50\" value=\"" . $escaper->escapeHtml($mitigation_date) . "\" title=\"" . $escaper->escapeHtml($mitigation_date) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['PlanningStrategy']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"planning_strategy\" id=\"planning_strategy\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['MitigationEffort']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_effort\" id=\"mitigation_effort\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['CurrentSolution']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea style=\"cursor: default;\" name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\" title=\"" . $escaper->escapeHtml($current_solution) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($current_solution) . "</textarea>\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['SecurityRequirements']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea style=\"cursor: default;\" name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\" title=\"" . $escaper->escapeHtml($security_requirements) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($security_requirements) . "</textarea>\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['SecurityRecommendations']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea style=\"cursor: default;\" name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\" title=\"" . $escaper->escapeHtml($security_recommendations) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($security_recommendations) . "</textarea>\n";

    // If the page is the view.php page
    if (basename($_SERVER['PHP_SELF']) == "view.php")
    {
        // Give the option to edit the mitigation details
        $result .=  "<div class=\"form-actions\">\n";
        $result .=  "<button type=\"submit\" name=\"edit_mitigation\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['EditMitigation']) ."</button>\n";
        $result .=  "</div>\n";
    }

    return $result;
}

/*************************************
 * FUNCTION: EDIT MITIGATION DETAILS *
 *************************************/
function edit_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
	global $lang;
	global $escaper;

    $result = "";

    $result .=  "<h4>". $escaper->escapeHtml($lang['Mitigation']) ."</h4>\n";
    $result .=  $escaper->escapeHtml($lang['MitigationDate']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"mitigation_date\" id=\"mitigation_date\" size=\"50\" value=\"" . $escaper->escapeHtml($mitigation_date) . "\" title=\"" . $escaper->escapeHtml($mitigation_date) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['PlanningStrategy']) .": \n";
    $result .=  "<br />\n";
    $result .= create_dropdown("planning_strategy", $planning_strategy);
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['MitigationEffort']) .": \n";
    $result .=  "<br />\n";
    $result .= create_dropdown("mitigation_effort", $mitigation_effort);
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['CurrentSolution']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\">" . $escaper->escapeHtml($current_solution) . "</textarea>\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['SecurityRequirements']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\">" . $escaper->escapeHtml($security_requirements) . "</textarea>\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['SecurityRecommendations']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\">" . $escaper->escapeHtml($security_recommendations) . "</textarea>\n";
    $result .=  "<div class=\"form-actions\">\n";
    $result .=  "<button type=\"submit\" name=\"update_mitigation\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Update']) ."</button>\n";
    $result .=  "</div>\n";

    return $result;
}

/*********************************
 * FUNCTION: view_review_details *
 *********************************/
function view_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments)
{
	global $lang;
	global $escaper;
	
    $result = "";

    $result .=  "<h4>". $escaper->escapeHtml($lang['LastReview']) ."</h4>\n";
    $result .=  $escaper->escapeHtml($lang['ReviewDate']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"review_date\" id=\"review_date\" size=\"50\" value=\"" . $escaper->escapeHtml($review_date) . "\" title=\"" . $escaper->escapeHtml($review_date) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Reviewer']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"reviewer\" id=\"reviewer\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Review']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"review\" id=\"review\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("review", $review)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("review", $review)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['NextStep']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"next_step\" id=\"next_step\" size=\"50\" value=\"" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "\" title=\"" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['NextReviewDate']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<input style=\"cursor: default;\" type=\"text\" name=\"next_review\" id=\"next_review\" size=\"50\" value=\"" . $escaper->escapeHtml($next_review) . "\" title=\"" . $escaper->escapeHtml($next_review) . "\" disabled=\"disabled\" />\n";
    $result .=  "<br />\n";
    $result .=  $escaper->escapeHtml($lang['Comments']) .": \n";
    $result .=  "<br />\n";
    $result .=  "<textarea style=\"cursor: default;\" name=\"comments\" cols=\"50\" rows=\"3\" id=\"comments\" title=\"" . $escaper->escapeHtml($comments) . "\" disabled=\"disabled\">" . $escaper->escapeHtml($comments) . "</textarea>\n";
    $result .=  "<p><a href=\"reviews.php?id=". $escaper->escapeHtml($id) ."\">". $escaper->escapeHtml($lang['ViewAllReviews']) ."</a></p>";


    return $result;
}

/****************************************
 * FUNCTION: edit_mitigation_submission *
 ****************************************/
function edit_mitigation_submission($planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations)
{
	global $lang;
	global $escaper;
	
	echo "<h4>". $escaper->escapeHtml($lang['SubmitRiskMitigation']) ."</h4>\n";
        echo "<form name=\"submit_mitigation\" method=\"post\" action=\"\">\n";
	
        echo $escaper->escapeHtml($lang['PlanningStrategy']) .": \n";
        echo "<br />\n";
	create_dropdown("planning_strategy", $planning_strategy, NULL, true);
        echo "<br />\n";
        echo $escaper->escapeHtml($lang['MitigationEffort']) .": \n";
        echo "<br />\n";
	create_dropdown("mitigation_effort", $mitigation_effort, NULL, true);
        echo "<br />\n";
        echo $escaper->escapeHtml($lang['CurrentSolution']) .": \n";
        echo "<br />\n";
        echo "<textarea name=\"current_solution\" cols=\"50\" rows=\"3\" id=\"current_solution\">" . $escaper->escapeHtml($current_solution) . "</textarea>\n";
        echo "<br />\n";
        echo $escaper->escapeHtml($lang['SecurityRequirements']) .": \n";
        echo "<br />\n";
        echo "<textarea name=\"security_requirements\" cols=\"50\" rows=\"3\" id=\"security_requirements\">" . $escaper->escapeHtml($security_requirements) . "</textarea>\n";
        echo "<br />\n";
        echo $escaper->escapeHtml($lang['SecurityRecommendations']) .": \n";
        echo "<br />\n";
        echo "<textarea name=\"security_recommendations\" cols=\"50\" rows=\"3\" id=\"security_recommendations\">" . $escaper->escapeHtml($security_recommendations) . "</textarea>\n";
        echo "<br />\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Submit']) ."</button>\n";
        echo "<input class=\"btn\" value=\"". $lang['Reset'] ."\" type=\"reset\">\n";
        echo "</div>\n";
        echo "</form>\n";
}

/************************************
 * FUNCTION: edit_review_submission *
 ************************************/
function edit_review_submission($review, $next_step, $next_review, $comments)
{
	global $lang;
	global $escaper;
	
	echo "<h4>". $escaper->escapeHtml($lang['SubmitManagementReview']) ."</h4>\n";
        echo "<form name=\"submit_management_review\" method=\"post\" action=\"\">\n";
        echo $escaper->escapeHtml($lang['Review']) .": \n";
        echo "<br />\n";
	create_dropdown("review", $review, NULL, true);
        echo "<br />\n";
        echo $escaper->escapeHtml($lang['NextStep']) .": \n";
        echo "<br />\n";
	create_dropdown("next_step", $next_step, NULL, true);
	echo "<br />\n";
        echo $escaper->escapeHtml($lang['Comments']) .": \n";
        echo "<br />\n";
        echo "<textarea name=\"comments\" cols=\"50\" rows=\"3\" id=\"comments\">" . $escaper->escapeHtml($comments) . "</textarea>\n";
	echo "<br />\n";
	echo $escaper->escapeHtml($lang['BasedOnTheCurrentRiskScore']) . $escaper->escapeHtml($next_review) . "<br />\n";
	echo $escaper->escapeHtml($lang['WouldYouLikeToUseADifferentDate']) . "&nbsp;<input type=\"radio\" name=\"custom_date\" value=\"no\" onclick=\"hideNextReview()\" checked />&nbsp" . $escaper->escapeHtml($lang['No']) . "&nbsp;<input type=\"radio\" name=\"custom_date\" value=\"yes\" onclick=\"showNextReview()\" />&nbsp" . $escaper->escapeHtml($lang['Yes']) . "<br />\n";
	echo "<div id=\"nextreview\" style=\"display:none;\">\n";
	echo "<br />\n";
	echo $escaper->escapeHtml($lang['NextReviewDate']) .": \n";
	echo "<br />\n";
	echo "<input type=\"text\" name=\"next_review\" value=\"" . $escaper->escapeHtml($next_review) . "\" />\n";
	echo "<br />\n";
	echo "</div>\n";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Submit']) ."</button>\n";
        echo "<input class=\"btn\" value=\"". $escaper->escapeHtml($lang['Reset']) ."\" type=\"reset\">\n";
        echo "</div>\n";
        echo "</form>\n";
}

/********************************
 * FUNCTION: edit_classic_score *
 ********************************/
function edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact)
{
	global $lang;
	global $escaper;

    $result = "";

    $result .= "<h4>" . $escaper->escapeHtml($lang['UpdateClassicScore']) . "</h4>\n";
    $result .=  "<form name=\"update_classic\" method=\"post\" action=\"\">\n";
    $result .=  "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\" height=\"10\">" . $escaper->escapeHtml($lang['CurrentLikelihood']) . ":</td>\n";
    $result .=  "<td width=\"125\">\n";
    $result .= create_dropdown("likelihood", $CLASSIC_likelihood, NULL, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('likelihoodHelp');\"></td>\n";
    $result .=  "<td rowspan=\"3\" style=\"vertical-align:top;\">\n";
    $result .=  view_classic_help(); // #####
    $result .=  "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\" height=\"10\">" . $escaper->escapeHtml($lang['CurrentImpact']) . ":</td>\n";
    $result .=  "<td width=\"125\">\n";
    $result .= create_dropdown("impact", $CLASSIC_impact, NULL, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('impactHelp');\"></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr><td colspan=\"3\">&nbsp;</td></tr>\n";

    $result .=  "</table>\n";

    $result .=  "<div class=\"form-actions\">\n";
    $result .=  "<button type=\"submit\" name=\"update_classic\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
    $result .=  "</div>\n";
    $result .=  "</form>\n";
    return $result;
}

/*****************************
 * FUNCTION: edit_cvss_score *
 *****************************/
function edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement)
{
	global $lang;
	global $escaper;

    $result = "";
    $result .=  "<h4>" . $escaper->escapeHtml($lang['UpdateCVSSScore']) . "</h4>\n";
    $result .=  "<form name=\"update_cvss\" method=\"post\" action=\"\">\n";
    $result .=  "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['BaseScoreMetrics']) . "</u></b></td>\n";
    $result .=  "<td rowspan=\"19\" style=\"vertical-align:top;\">\n";
    $result .= view_cvss_help();
    $result .=  "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"200\">" . $escaper->escapeHtml($lang['AttackVector']) . ":</td>\n";
    $result .=  "<td width=\"125\">\n";
    $result .= create_cvss_dropdown("AccessVector", $AccessVector, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AccessVectorHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['AttackComplexity']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("AccessComplexity", $AccessComplexity, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AccessComplexityHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Authentication']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("Authentication", $Authentication, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AuthenticationHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['ConfidentialityImpact']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("ConfImpact", $ConfImpact, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ConfImpactHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['IntegrityImpact']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("IntegImpact", $IntegImpact, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('IntegImpactHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['AvailabilityImpact']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("AvailImpact", $AvailImpact, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AvailImpactHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\">&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['TemporalScoreMetrics']) . "</u></b></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("Exploitability", $Exploitability, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ExploitabilityHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['RemediationLevel']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("RemediationLevel", $RemediationLevel, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('RemediationLevelHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['ReportConfidence']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("ReportConfidence", $ReportConfidence, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ReportConfidenceHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\">&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['EnvironmentalScoreMetrics']) . "</u></b></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['CollateralDamagePotential']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("CollateralDamagePotential", $CollateralDamagePotential, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('CollateralDamagePotentialHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['TargetDistribution']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("TargetDistribution", $TargetDistribution, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('TargetDistributionHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['ConfidentialityRequirement']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("ConfidentialityRequirement", $ConfidentialityRequirement, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ConfidentialityRequirementHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['IntegrityRequirement']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("IntegrityRequirement", $IntegrityRequirement, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('IntegrityRequirementHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['AvailabilityRequirement']) . ":</td>\n";
    $result .=  "<td>\n";
    $result .= create_cvss_dropdown("AvailabilityRequirement", $AvailabilityRequirement, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AvailabilityRequirementHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "</table>\n";

    $result .=  "<div class=\"form-actions\">\n";
    $result .=  "<button type=\"submit\" name=\"update_cvss\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
    $result .=  "</div>\n";
    $result .=  "</form>\n";
    return $result;
}

/******************************
 * FUNCTION: edit_dread_score *
 ******************************/
function edit_dread_score($DamagePotential, $Reproducibility, $Exploitability, $AffectedUsers, $Discoverability)
{
	global $lang;
	global $escaper;

    $result = "";

    $result .=  "<h4>" . $escaper->escapeHtml($lang['UpdateDREADScore']) . "</h4>\n";
    $result .=  "<form name=\"update_dread\" method=\"post\" action=\"\">\n";
    $result .=  "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['DamagePotential']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("DamagePotential", $DamagePotential, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('DamagePotentialHelp');\"></td>\n";
    $result .=  "<td rowspan=\"5\" style=\"vertical-align:top;\">\n";
    $result .= view_dread_help();
    $result .=  "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Reproducibility']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("Reproducibility", $Reproducibility, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ReproducibilityHelp');\"></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("Exploitability", $Exploitability, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ExploitabilityHelp');\"></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['AffectedUsers']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("AffectedUsers", $AffectedUsers, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AffectedUsersHelp');\"></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Discoverability']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("Discoverability", $Discoverability, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('DiscoverabilityHelp');\"></td>\n";
    $result .=  "</tr>\n";

    $result .=  "</table>\n";

    $result .=  "<div class=\"form-actions\">\n";
    $result .=  "<button type=\"submit\" name=\"update_dread\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
    $result .=  "</div>\n";
    $result .=  "</form>\n";

    return $result;
}

/******************************
 * FUNCTION: edit_owasp_score *
 ******************************/
function edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation)
{
	global $lang;
	global $escaper;

    $result = "";

    $result .=  "<h4>" . $escaper->escapeHtml($lang['UpdateOWASPScore']) . "</h4>\n";
    $result .=  "<form name=\"update_owasp\" method=\"post\" action=\"\">\n";
    $result .=  "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . "</u></b></td>\n";
    $result .=  "<td rowspan=\"20\" style=\"vertical-align:top;\">\n";
    $result .= view_owasp_help();
    $result .=  "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['SkillLevel']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("SkillLevel", $OWASPSkillLevel, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('SkillLevelHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['Motive']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("Motive", $OWASPMotive, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('MotiveHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['Opportunity']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("Opportunity", $OWASPOpportunity, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('OpportunityHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['Size']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("Size", $OWASPSize, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('SizeHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\">&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . "</u></b></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['EaseOfDiscovery']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("EaseOfDiscovery", $OWASPEaseOfDiscovery, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('EaseOfDiscoveryHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['EaseOfExploit']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("EaseOfExploit", $OWASPEaseOfExploit, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('EaseOfExploitHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['Awareness']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("Awareness", $OWASPAwareness, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('AwarenessHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['IntrusionDetection']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("IntrusionDetection", $OWASPIntrusionDetection, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('IntrusionDetectionHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\">&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['TechnicalImpact']) . "</u></b></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfConfidentiality']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("LossOfConfidentiality", $OWASPLossOfConfidentiality, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('EaseOfDiscoveryHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfIntegrity']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("LossOfIntegrity", $OWASPLossOfIntegrity, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('LossOfIntegrityHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfAvailability']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("LossOfAvailability", $OWASPLossOfAvailability, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('LossOfAvailabilityHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfAccountability']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("LossOfAccountability", $OWASPLossOfAccountability, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('LossOfAccountabilityHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\">&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['BusinessImpact']) . "</u></b></td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['FinancialDamage']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("FinancialDamage", $OWASPFinancialDamage, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('EaseOfDiscoveryHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['ReputationDamage']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("ReputationDamage", $OWASPReputationDamage, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('ReputationDamageHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['NonCompliance']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("NonCompliance", $OWASPNonCompliance, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('NonComplianceHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['PrivacyViolation']) . ":</td>\n";
    $result .=  "<td width=\"75\">\n";
    $result .= create_numeric_dropdown("PrivacyViolation", $OWASPPrivacyViolation, false);
    $result .=  "</td>\n";
    $result .=  "<td width=\"50\"><img src=\"../images/helpicon.jpg\" width=\"25\" height=\"18\" align=\"absmiddle\" onClick=\"javascript:showHelp('PrivacyViolationHelp');\"></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "</table>\n";

    $result .=  "<div class=\"form-actions\">\n";
    $result .=  "<button type=\"submit\" name=\"update_owasp\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
    $result .=  "</div>\n";
    $result .=  "</form>\n";

    return $result;

}

/*******************************
 * FUNCTION: edit_custom_score *
 *******************************/
function edit_custom_score($custom)
{
	global $lang;
	global $escaper;

    $result = "";
    $result .=  "<h4>" . $escaper->escapeHtml($lang['UpdateCustomScore']) . "</h4>\n";
    $result .=  "<form name=\"update_custom\" method=\"post\" action=\"\">\n";
    $result .=  "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"165\" height=\"10\">" . $escaper->escapeHtml($lang['ManuallyEnteredValue']) . ":</td>\n";
    $result .=  "<td width=\"60\"><input type=\"text\" name=\"Custom\" id=\"Custom\" style=\"width:30px;\" value=\"" . $escaper->escapeHtml($custom) . "\"></td>\n";
    $result .=  "<td>(Must be a numeric value between 0 and 10)</td>\n";
    $result .=  "</tr>\n";

    $result .=  "</table>\n";

    $result .=  "<div class=\"form-actions\">\n";
    $result .=  "<button type=\"submit\" name=\"update_custom\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
    $result .=  "</div>\n";
    $result .=  "</form>\n";

    return $result;
}

/***********************************
 * FUNCTION: CLASSIC SCORING TABLE *
 ***********************************/
function classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact)
{
	global $lang;
	global $escaper;

    $result = "";

    $result .= "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"3\"><h4>". $escaper->escapeHtml($lang['ClassicRiskScoring']) ."</h4></td>\n";
    $result .=  "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
    $result .=  "<div class=\"btn-group pull-right\">\n";
    $result .=  "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">". $escaper->escapeHtml($lang['RiskScoringActions']) ."<span class=\"caret\"></span></a>\n";
    $result .=  "<ul class=\"dropdown-menu\">\n";
    $result .=  "<li><a href=\"#\" onclick=\"javascript:updateScore()\">". $escaper->escapeHtml($lang['UpdateClassicScore']) ."</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=2\">". $escaper->escapeHtml($lang['ScoreByCVSS']) ."</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=3\">". $escaper->escapeHtml($lang['ScoreByDREAD']) ."</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=4\">". $escaper->escapeHtml($lang['ScoreByOWASP']) ."</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=5\">". $escaper->escapeHtml($lang['ScoreByCustom']) ."</a></li>\n";
    $result .=  "</ul>\n";
    $result .=  "</div>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";


    $result .=  "<tr>\n";
    $result .=  "<td width=\"90\">". $escaper->escapeHtml($lang['Likelihood']) .":</td>\n";
    $result .=  "<td width=\"25\">[ " . $escaper->escapeHtml($CLASSIC_likelihood) . " ]</td>\n";
    $result .=  "<td>" . $escaper->escapeHtml(get_name_by_value("likelihood", $CLASSIC_likelihood)) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"90\">". $escaper->escapeHtml($lang['Impact']) .":</td>\n";
    $result .=  "<td width=\"25\">[ " . $escaper->escapeHtml($CLASSIC_impact) . " ]</td>\n";
    $result .=  "<td>" . $escaper->escapeHtml(get_name_by_value("impact", $CLASSIC_impact)) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr><td colspan=\"4\">&nbsp;</td></tr>\n";

        if (get_setting("risk_model") == 1)
        {
            $result .=  "<tr>\n";
            $result .=  "<td colspan=\"3\"><b>". $escaper->escapeHtml($lang['RISKClassicExp1']) ." x ( 10 / 35 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
            $result .=  "</tr>\n";
        }
        else if (get_setting("risk_model") == 2)
        {
            $result .=  "<tr>\n";
            $result .=  "<td colspan=\"3\"><b>". $escaper->escapeHtml($lang['RISKClassicExp2']) ." x ( 10 / 30 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
            $result .=  "</tr>\n";
        }
        else if (get_setting("risk_model") == 3)
        {
            $result .=  "<tr>\n";
            $result .=  "<td colspan=\"3\"><b>". $escaper->escapeHtml($lang['RISKClassicExp3']) ." x ( 10 / 25 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
            $result .=  "</tr>\n";
        }
        else if (get_setting("risk_model") == 4)
        {
            $result .=  "<tr>\n";
            $result .=  "<td colspan=\"3\"><b>". $escaper->escapeHtml($lang['RISKClassicExp4']) ." x ( 10 / 30 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
            $result .=  "</tr>\n";
        }
        else if (get_setting("risk_model") == 5)
        {
            $result .=  "<tr>\n";
            $result .=  "<td colspan=\"3\"><b>". $escaper->escapeHtml($lang['RISKClassicExp5']) ." x ( 10 / 35 ) = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
            $result .=  "</tr>\n";
        }

    $result .=  "</table>\n";
    return $result;
}

/********************************
 * FUNCTION: CVSS SCORING TABLE *
 ********************************/
function cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement)
{
	global $lang;
	global $escaper;
    $result = "";

        $result .= "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"4\"><h4>" . $escaper->escapeHtml($lang['CVSSRiskScoring']) . "</h4></td>\n";
    $result .=  "<td colspan=\"3\" style=\"vertical-align:top;\">\n";
    $result .=  "<div class=\"btn-group pull-right\">\n";
    $result .=  "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
    $result .=  "<ul class=\"dropdown-menu\">\n";
    $result .=  "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateCVSSScore']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=3\">" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=4\">" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=5\">" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>\n";
    $result .=  "</ul>\n";
    $result .=  "</div>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"7\">" . $escaper->escapeHtml($lang['BaseVector']) . ": AV:" . $escaper->escapeHtml($AccessVector) . "/AC:" . $escaper->escapeHtml($AccessComplexity) . "/Au:" . $escaper->escapeHtml($Authentication) . "/C:" . $escaper->escapeHtml($ConfImpact) . "/I:" . $escaper->escapeHtml($IntegImpact) . "/A:" . $escaper->escapeHtml($AvailImpact) . "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"7\">" . $escaper->escapeHtml($lang['TemporalVector']) . ": E:" . $escaper->escapeHtml($Exploitability) . "/RL:" . $escaper->escapeHtml($RemediationLevel) . "/RC:" . $escaper->escapeHtml($ReportConfidence) . "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"7\">" . $escaper->escapeHtml($lang['EnvironmentalVector']) . ": CDP:" . $escaper->escapeHtml($CollateralDamagePotential) . "/TD:" . $escaper->escapeHtml($TargetDistribution) . "/CR:" . $escaper->escapeHtml($ConfidentialityRequirement) . "/IR:" . $escaper->escapeHtml($IntegrityRequirement) . "/AR:" . $escaper->escapeHtml($AvailabilityRequirement) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr><td colspan=\"8\">&nbsp;</td></tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"2\"><b><u>" . $escaper->escapeHtml($lang['BaseScoreMetrics']) . "</u></b></td>\n";
    $result .=  "<td colspan=\"2\"><b><u>" . $escaper->escapeHtml($lang['TemporalScoreMetrics']) . "</u></b></td>\n";
    $result .=  "<td colspan=\"2\"><b><u>" . $escaper->escapeHtml($lang['EnvironmentalScoreMetrics']) . "</u></b></td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['AttackVector']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("AccessVector", $AccessVector)) . "</td>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("Exploitability", $Exploitability)) . "</td>\n";
    $result .=  "<td width=\"200\">" . $escaper->escapeHtml($lang['CollateralDamagePotential']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("CollateralDamagePotential", $CollateralDamagePotential)) . "</td>\n";
    $result .=  "<td>&nbsp</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['AttackComplexity']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("AccessComplexity", $AccessComplexity)) . "</td>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['RemediationLevel']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("RemediationLevel", $RemediationLevel)) . "</td>\n";
    $result .=  "<td width=\"200\">" . $escaper->escapeHtml($lang['TargetDistribution']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("TargetDistribution", $TargetDistribution)) . "</td>\n";
    $result .=  "<td>&nbsp</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['Authentication']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("Authentication", $Authentication)) . "</td>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['ReportConfidence']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("ReportConfidence", $ReportConfidence)) . "</td>\n";
    $result .=  "<td width=\"200\">" . $escaper->escapeHtml($lang['ConfidentialityRequirement']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("ConfidentialityRequirement", $ConfidentialityRequirement)) . "</td>\n";
    $result .=  "<td>&nbsp</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['ConfidentialityImpact']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("ConfImpact", $ConfImpact)) . "</td>\n";
    $result .=  "<td width=\"150\">&nbsp;</td>\n";
    $result .=  "<td width=\"100\">&nbsp</td>\n";
    $result .=  "<td width=\"200\">" . $escaper->escapeHtml($lang['IntegrityRequirement']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("IntegrityRequirement", $IntegrityRequirement)) . "</td>\n";
    $result .=  "<td>&nbsp</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">". $escaper->escapeHtml($lang['IntegrityImpact']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("IntegImpact", $IntegImpact)) . "</td>\n";
    $result .=  "<td width=\"150\">&nbsp;</td>\n";
    $result .=  "<td width=\"100\">&nbsp</td>\n";
    $result .=  "<td width=\"200\">" . $escaper->escapeHtml($lang['AvailabilityRequirement']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("AvailabilityRequirement", $AvailabilityRequirement)) . "</td>\n";
    $result .=  "<td>&nbsp</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['AvailabilityImpact']) . ":</td>\n";
    $result .=  "<td width=\"100\">" . $escaper->escapeHtml(get_cvss_name("AvailImpact", $AvailImpact)) . "</td>\n";
    $result .=  "<td width=\"150\">&nbsp;</td>\n";
    $result .=  "<td width=\"100\">&nbsp</td>\n";
    $result .=  "<td width=\"200\">&nbsp;</td>\n";
    $result .=  "<td width=\"100\">&nbsp</td>\n";
    $result .=  "<td>&nbsp</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"7\">&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"7\">Full details of CVSS Version 2.0 scoring can be found <a href=\"http://www.first.org/cvss/cvss-guide.html\" target=\"_blank\">here</a>.</td>\n";
    $result .=  "</tr>\n";

    $result .=  "</table>\n";
    return $result;
}

/*********************************
 * FUNCTION: DREAD SCORING TABLE *
 *********************************/
function dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability)
{
        global $lang;
        global $escaper;
        $result = "";

        $result .= "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"2\"><h4>" . $escaper->escapeHtml($lang['DREADRiskScoring']) . "</h4></td>\n";
    $result .=  "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
    $result .=  "<div class=\"btn-group pull-right\">\n";
    $result .=  "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
    $result .=  "<ul class=\"dropdown-menu\">\n";
    $result .=  "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateDREADScore']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=2\">" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=4\">" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=5\">" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>\n";
    $result .=  "</ul>\n";
    $result .=  "</div>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['DamagePotential']) . ":</td>\n";
    $result .=  "<td>" . $escaper->escapeHtml($DREADDamagePotential) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Reproducibility']) . ":</td>\n";
    $result .=  "<td>" . $escaper->escapeHtml($DREADReproducibility) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Exploitability']) . ":</td>\n";
    $result .=  "<td>" . $escaper->escapeHtml($DREADExploitability) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['AffectedUsers']) . ":</td>\n";
    $result .=  "<td>" . $escaper->escapeHtml($DREADAffectedUsers) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"150\">" . $escaper->escapeHtml($lang['Discoverability']) . ":</td>\n";
    $result .=  "<td>" . $escaper->escapeHtml($DREADDiscoverability) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"3\">&nbsp;</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"3\"><b>RISK = ( " . $escaper->escapeHtml($DREADDamagePotential) . " + " . $escaper->escapeHtml($DREADReproducibility) . " + " . $escaper->escapeHtml($DREADExploitability) . " + " . $escaper->escapeHtml($DREADAffectedUsers) . " + " . $escaper->escapeHtml($DREADDiscoverability) . " ) / 5 = " . $escaper->escapeHtml($calculated_risk) . "</b></td>\n";
    $result .=  "</tr>\n";

    $result .=  "</table>\n";
    return $result;
}

/*********************************
 * FUNCTION: OWASP SCORING TABLE *
 *********************************/
function owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation)
{
        global $lang;
        global $escaper;

        $result = "";

        $result .= "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .= "<tr>\n";
    $result .= "<td colspan=\"4\"><h4>" . $escaper->escapeHtml($lang['OWASPRiskScoring']) . "</h4></td>\n";
    $result .= "<td colspan=\"5\" style=\"vertical-align:top;\">\n";
    $result .= "<div class=\"btn-group pull-right\">\n";
    $result .= "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
    $result .= "<ul class=\"dropdown-menu\">\n";
    $result .= "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateOWASPScore']) . "</a></li>\n";
    $result .= "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
    $result .= "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=2\">" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>\n";
    $result .= "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=3\">" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>\n";
    $result .= "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=5\">" . $escaper->escapeHtml($lang['ScoreByCustom']) . "</a></li>\n";
    $result .= "</ul>\n";
    $result .= "</div>\n";
    $result .= "</td>\n";
    $result .= "</tr>\n";

    $result .= "<tr>\n";
    $result .= "<td colspan=\"2\"><b><u>" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . "</u></b></td>\n";
    $result .= "<td colspan=\"2\"><b><u>" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . "</u></b></td>\n";
    $result .= "<td colspan=\"2\"><b><u>" . $escaper->escapeHtml($lang['TechnicalImpact']) . "</u></b></td>\n";
    $result .= "<td colspan=\"2\"><b><u>" . $escaper->escapeHtml($lang['BusinessImpact']) . "</u></b></td>\n";
    $result .= "<td>&nbsp;</td>\n";
    $result .= "</tr>\n";

    $result .= "<tr>\n";
    $result .= "<td width=\"175\">" . $escaper->escapeHtml($lang['SkillLevel']) . ":</td>\n";
    $result .= "<td width=\"50\">" . $escaper->escapeHtml($OWASPSkillLevel) . "</td>\n";
    $result .= "<td width=\"175\">" . $escaper->escapeHtml($lang['EaseOfDiscovery']) . ":</td>\n";
    $result .= "<td width=\"50\">" . $escaper->escapeHtml($OWASPEaseOfDiscovery) . "</td>\n";
    $result .= "<td width=\"175\">" . $escaper->escapeHtml($lang['LossOfConfidentiality']) . ":</td>\n";
    $result .= "<td width=\"50\">" . $escaper->escapeHtml($OWASPLossOfConfidentiality) . "</td>\n";
    $result .= "<td width=\"175\">" . $escaper->escapeHtml($lang['FinancialDamage']) . ":</td>\n";
    $result .= "<td width=\"50\">" . $escaper->escapeHtml($OWASPFinancialDamage) . "</td>\n";
    $result .= "<td>&nbsp;</td>\n";
    $result .= "</tr>\n";

    $result .= "<tr>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['Motive']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPMotive) . "</td>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['EaseOfExploit']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPEaseOfExploit) . "</td>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['LossOfIntegrity']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPLossOfIntegrity) . "</td>\n";
    $result .= "<tr>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['Opportunity']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPOpportunity) . "</td>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['Awareness']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPAwareness) . "</td>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['LossOfAvailability']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPLossOfAvailability) . "</td>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['NonCompliance']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPNonCompliance) . "</td>\n";
    $result .= "<td>&nbsp;</td>\n";
    $result .= "</tr>\n";

    $result .= "<tr>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['Size']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPSize) . "</td>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['IntrusionDetection']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPIntrusionDetection) . "</td>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['LossOfAccountability']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPLossOfAccountability) . "</td>\n";
    $result .= "<td width=\"125\">" . $escaper->escapeHtml($lang['PrivacyViolation']) . ":</td>\n";
    $result .= "<td width=\"10\">" . $escaper->escapeHtml($OWASPPrivacyViolation) . "</td>\n";
    $result .= "<td>&nbsp;</td>\n";
    $result .= "</tr>\n";

    $result .= "<tr>\n";
    $result .= "<td colspan=\"9\">&nbsp;</td>\n";
    $result .= "<tr>\n";

    $result .= "<tr>\n";
    $result .= "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['Likelihood']) . "</u></b></td>\n";
    $result .= "<td colspan=\"4\"><b><u>" . $escaper->escapeHtml($lang['Impact']) . "</u></b></td>\n";
    $result .= "<td>&nbsp;</td>\n";
    $result .= "<tr>\n";

    $result .= "<tr>\n";
    $result .= "<td colspan=\"4\">" . $escaper->escapeHtml($lang['ThreatAgentFactors']) . " = ( " . $escaper->escapeHtml($OWASPSkillLevel) . " + " . $escaper->escapeHtml($OWASPMotive) . " + " . $escaper->escapeHtml($OWASPOpportunity) . " + " . $escaper->escapeHtml($OWASPSize) . " ) / 4</td>\n";
    $result .= "<td colspan=\"4\">" . $escaper->escapeHtml($lang['TechnicalImpact']) . " = ( " . $escaper->escapeHtml($OWASPLossOfConfidentiality) . " + " . $escaper->escapeHtml($OWASPLossOfIntegrity) . " + " . $escaper->escapeHtml($OWASPLossOfAvailability) . " + " . $escaper->escapeHtml($OWASPLossOfAccountability) . " ) / 4</td>\n";
    $result .= "<td>&nbsp;</td>\n";
    $result .= "<tr>\n";

    $result .= "<tr>\n";
    $result .= "<td colspan=\"4\">" . $escaper->escapeHtml($lang['VulnerabilityFactors']) . " = ( " . $escaper->escapeHtml($OWASPEaseOfDiscovery) . " + " . $escaper->escapeHtml($OWASPEaseOfExploit) . " + " . $escaper->escapeHtml($OWASPAwareness) . " + " . $escaper->escapeHtml($OWASPIntrusionDetection) . " ) / 4</td>\n";
    $result .= "<td colspan=\"4\">" . $escaper->escapeHtml($lang['BusinessImpact']) . " = ( " . $escaper->escapeHtml($OWASPFinancialDamage) . " + " . $escaper->escapeHtml($OWASPReputationDamage) . " + " . $escaper->escapeHtml($OWASPNonCompliance) . " + " . $escaper->escapeHtml($OWASPPrivacyViolation) . " ) / 4</td>\n";
    $result .= "<td>&nbsp;</td>\n";
    $result .= "<tr>\n";

    $result .= "<tr>\n";
    $result .= "<td colspan=\"9\">&nbsp;</td>\n";
    $result .= "</tr>\n";

    $result .= "<tr>\n";
    $result .= "<td colspan=\"9\">Full details of the OWASP Risk Rating Methodology can be found <a href=\"https://www.owasp.org/index.php/OWASP_Risk_Rating_Methodology\" target=\"_blank\">here</a>.</td>\n";
    $result .= "</tr>\n";

    $result .= "</table>\n";
    return $result;
}

/**********************************
 * FUNCTION: CUSTOM SCORING TABLE *
 **********************************/
function custom_scoring_table($id, $custom)
{
        global $lang;
        global $escaper;
        $result = "";

        $result .= "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:none;\">\n";

    $result .=  "<tr>\n";
    $result .=  "<td colspan=\"2\"><h4>" . $escaper->escapeHtml($lang['CustomRiskScoring']) . "</h4></td>\n";
    $result .=  "<td colspan=\"1\" style=\"vertical-align:top;\">\n";
    $result .=  "<div class=\"btn-group pull-right\">\n";
    $result .=  "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">" . $escaper->escapeHtml($lang['RiskScoringActions']) . "<span class=\"caret\"></span></a>\n";
    $result .=  "<ul class=\"dropdown-menu\">\n";
    $result .=  "<li><a href=\"#\" onclick=\"javascript:updateScore()\">" . $escaper->escapeHtml($lang['UpdateCustomScore']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=1\">" . $escaper->escapeHtml($lang['ScoreByClassic']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=2\">" . $escaper->escapeHtml($lang['ScoreByCVSS']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=3\">" . $escaper->escapeHtml($lang['ScoreByDREAD']) . "</a></li>\n";
    $result .=  "<li><a href=\"view.php?id=". $escaper->escapeHtml($id) ."&scoring_method=4\">" . $escaper->escapeHtml($lang['ScoreByOWASP']) . "</a></li>\n";
    $result .=  "</ul>\n";
    $result .=  "</div>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";

    $result .=  "<tr>\n";
    $result .=  "<td width=\"175\">" . $escaper->escapeHtml($lang['ManuallyEnteredValue']) . ":</td>\n";
    $result .=  "<td width=\"10\">" . $escaper->escapeHtml($custom) . "</td>\n";
    $result .=  "<td>&nbsp;</td>\n";
    $result .=  "<tr>\n";

    $result .=  "</table>\n";
    return $result;
}

/*******************************
 * FUNCTION: VIEW CLASSIC HELP *
 *******************************/
function view_classic_help()
{
    $result = "";
    $result .= "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

    $result .=  "<div id=\"likelihoodHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<p><b>Remote:</b> May only occur in exceptional circumstances.</p>\n";
    $result .=  "<p><b>Unlikely:</b> Expected to occur in a few circumstances.</p>\n";
    $result .=  "<p><b>Credible:</b> Expected to occur in some circumstances.</p>\n";
    $result .=  "<p><b>Likely:</b> Expected to occur in many circumstances.</p>\n";
    $result .=  "<p><b>Almost Certain:</b> Expected to occur frequently and in most circumstances.</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"impactHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<p><b>Insignificant:</b> No impact on service, no impact on reputation, complaint unlikely, or litigation risk remote.</p>\n";
    $result .=  "<p><b>Minor:</b> Slight impact on service, slight impact on reputation, complaint possible, or litigation possible.</p>\n";
    $result .=  "<p><b>Moderate:</b> Some service disruption, potential for adverse publicity (avoidable with careful handling), complaint probable, or litigation probably.</p>\n";
    $result .=  "<p><b>Major:</b> Service disrupted, adverse publicity not avoidable (local media), complaint probably, or litigation probable.</p>\n";
    $result .=  "<p><b>Extreme/Catastrophic:</b> Service interrupted for significant time, major adverse publicity not avoidable (national media), major litigation expected, resignation of senior management and board, or loss of benficiary confidence.</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<script language=\"javascript\">\n";
    $result .=  "function showHelp(divId) {\n";
    $result .=  "getRef(\"divHelp\").innerHTML=getRef(divId).innerHTML;\n";
    $result .=  "}\n";
    $result .=  "function hideHelp() {\n";
    $result .=  "getRef(\"divHelp\").innerHTML=\"\";\n";
    $result .=  "}\n";
    $result .=  "</script>\n";
    return $result;
}

/*****************************
 * FUNCTION: VIEW OWASP HELP *
 *****************************/
function view_owasp_help()
{
    $result = "";
    $result .= "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

    $result .=  "<div id=\"SkillLevelHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How technically skilled is this group of threat agents?</b></p>\n";
    $result .=  "<p>1 = Security Penetration Skills</p>\n";
    $result .=  "<p>4 = Network and Programming Skills</p>\n";
    $result .=  "<p>6 = Advanced Computer User</p>\n";
    $result .=  "<p>7 = Some Technical Skills</p>\n";
    $result .=  "<p>9 = No Technical Skills</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"MotiveHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How motivated is this group of threat agents to find and exploit this vulnerability?</b></p>\n";
    $result .=  "<p>1 = Low or No Reward</p>\n";
    $result .=  "<p>4 = Possible Reward</p>\n";
    $result .=  "<p>9 = High Reward</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"OpportunityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>What resources and opportunity are required for this group of threat agents to find and exploit this vulnerability?</b></p>\n";
    $result .=  "<p>0 = Full Access or Expensive Resources Required</p>\n";
    $result .=  "<p>4 = Special Access or Resources Required</p>\n";
    $result .=  "<p>7 = Some Access or Resources Required</p>\n";
    $result .=  "<p>9 = No Access or Resources Required</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"SizeHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How large is this group of threat agents?</b></p>\n";
    $result .=  "<p>2 = Developers</p>\n";
    $result .=  "<p>2 = System Administrators</p>\n";
    $result .=  "<p>4 = Intranet Users</p>\n";
    $result .=  "<p>5 = Partners</p>\n";
    $result .=  "<p>6 = Authenticated Users</p>\n";
    $result .=  "<p>9 = Anonymous Internet Users</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"EaseOfDiscoveryHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<br /><p><b>How easy is it for this group of threat agents to discover this vulnerability?</b></p>\n";
    $result .=  "<p>1 = Practically Impossible</p>\n";
    $result .=  "<p>3 = Difficult</p>\n";
    $result .=  "<p>7 = Easy</p>\n";
    $result .=  "<p>9 = Automated Tools Available</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"EaseOfExploitHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How easy is it for this group of threat agents to actually exploit this vulnerability?</b></p>\n";
    $result .=  "<p>1 = Theoretical</p>\n";
    $result .=  "<p>3 = Difficult</p>\n";
    $result .=  "<p>5 = Easy</p>\n";
    $result .=  "<p>9 = Automated Tools Available</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"AwarenessHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How well known is this vulnerability to this group of threat agents?</b></p>\n";
    $result .=  "<p>1 = Unknown</p>\n";
    $result .=  "<p>4 = Hidden</p>\n";
    $result .=  "<p>6 = Obvious</p>\n";
    $result .=  "<p>9 = Public Knowledge</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"IntrusionDetectionHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How likely is an exploit to be detected?</b></p>\n";
    $result .=  "<p>1 = Active Detection in Application</p>\n";
    $result .=  "<p>3 = Logged and Reviewed</p>\n";
    $result .=  "<p>8 = Logged Without Review</p>\n";
    $result .=  "<p>9 = Not Logged</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"LossOfConfidentialityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How much data could be disclosed and how sensitive is it?</b></p>\n";
    $result .=  "<p>2 = Minimal Non-Sensitive Data Disclosed</p>\n";
    $result .=  "<p>6 = Minimal Critical Data Disclosed</p>\n";
    $result .=  "<p>7 = Extensive Critical Data Disclosed</p>\n";
    $result .=  "<p>9 = All Data Disclosed</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"LossOfIntegrityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How much data could be corrupted and how damaged is it?</b></p>\n";
    $result .=  "<p>1 = Minimal Slightly Corrupt Data</p>\n";
    $result .=  "<p>3 = Minimal Seriously Corrupt Data</p>\n";
    $result .=  "<p>5 = Extensive Slightly Corrupt Data</p>\n";
    $result .=  "<p>7 = Extensive Seriously Corrupt Data</p>\n";
    $result .=  "<p>9 = All Data Totally Corrupt</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"LossOfAvailabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How much service could be lost and how vital is it?</b></p>\n";
    $result .=  "<p>1 = Minimal Secondary Services Interrupted</p>\n";
    $result .=  "<p>5 = Minimal Primary Services Interrupted</p>\n";
    $result .=  "<p>5 = Extensive Secondary Services Interrupted</p>\n";
    $result .=  "<p>7 = Extensive Primary Services Interrupted</p>\n";
    $result .=  "<p>9 = All Services Completely Lost</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"LossOfAccountabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>Are the threat agents' actions traceable to an individual?</b></p>\n";
    $result .=  "<p>1 = Fully Traceable</p>\n";
    $result .=  "<p>7 = Possibly Traceable</p>\n";
    $result .=  "<p>9 = Completely Anonymous</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"FinancialDamageHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How much financial damage will result from an exploit?</b></p>\n";
    $result .=  "<p>1 = Less than the Cost to Fix the Vulnerability</p>\n";
    $result .=  "<p>3 = Minor Effect on Annual Profit</p>\n";
    $result .=  "<p>7 = Significant Effect on Annual Profit</p>\n";
    $result .=  "<p>9 = Bankruptcy</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"ReputationDamageHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>Would an exploit result in reputation damage that would harm the business?</b></p>\n";
    $result .=  "<p>1 = Minimal Damage</p>\n";
    $result .=  "<p>4 = Loss of Major Accounts</p>\n";
    $result .=  "<p>5 = Loss of Goodwill</p>\n";
    $result .=  "<p>9 = Brand Damage</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"NonComplianceHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How much exposure does non-compliance introduce?</b></p>\n";
    $result .=  "<p>2 = Minor Violation</p>\n";
    $result .=  "<p>5 = Clear Violation</p>\n";
    $result .=  "<p>7 = High Profile Violation</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"PrivacyViolationHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How much personally identifiable information could be disclosed?</b></p>\n";
    $result .=  "<p>3 = One Individual</p>\n";
    $result .=  "<p>5 = Hundreds of People</p>\n";
    $result .=  "<p>7 = Thousands of People</p>\n";
    $result .=  "<p>9 = Millions of People</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<script language=\"javascript\">\n";
    $result .=  "function showHelp(divId) {\n";
    $result .=  "getRef(\"divHelp\").innerHTML=getRef(divId).innerHTML;\n";
    $result .=  "}\n";
    $result .=  "function hideHelp() {\n";
    $result .=  "getRef(\"divHelp\").innerHTML=\"\";\n";
    $result .=  "}\n";
    $result .=  "</script>\n";

    return $result;
}

/*****************************
 * FUNCTION: VIEW CVSS HELP *
 *****************************/
function view_cvss_help()
{
    $result = "";

    $result .=  "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

    $result .=  "<div id=\"AccessVectorHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Local</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">A vulnerability exploitable with only local access requires the attacker to have either physical access to the vulnerable system or a local (shell) account.  Examples of locally exploitable vulnerabilities are peripheral attacks such as Firewire/USB DMA attacks, and local privilege escalations (e.g., sudo).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Adjacent Network</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">A vulnerability exploitable with adjacent network access requires the attacker to have access to either the broadcast or collision domain of the vulnerable software.  Examples of local networks include local IP subnet, Bluetooth, IEEE 802.11, and local Ethernet segment.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Network</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">A vulnerability exploitable with network access means the vulnerable software is bound to the network stack and the attacker does not require local network access or local access.  Such a vulnerability is often termed \"remotely exploitable\".  An example of a network attack is an RPC buffer overflow.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"AccessComplexityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>High</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Specialized access conditions exist. For example:<ul><li>In most configurations, the attacking party must already have elevated privileges or spoof additional systems in addition to the attacking system (e.g., DNS hijacking).</li><li>The attack depends on social engineering methods that would be easily detected by knowledgeable people. For example, the victim must perform several suspicious or atypical actions.</li><li>The vulnerable configuration is seen very rarely in practice.</li><li>If a race condition exists, the window is very narrow.</li></ul></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Medium</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">The access conditions are somewhat specialized; the following are examples:<ul><li>The attacking party is limited to a group of systems or users at some level of authorization, possibly untrusted.</li><li>Some information must be gathered before a successful attack can be launched.</li><li>The affected configuration is non-default, and is not commonly configured (e.g., a vulnerability present when a server performs user account authentication via a specific scheme, but not present for another authentication scheme).</li><li>The attack requires a small amount of social engineering that might occasionally fool cautious users (e.g., phishing attacks that modify a web browsers status bar to show a false link, having to be on someones buddy list before sending an IM exploit).</li></ul></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Low</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Specialized access conditions or extenuating circumstances do not exist. The following are examples:<ul><li>The affected product typically requires access to a wide range of systems and users, possibly anonymous and untrusted (e.g., Internet-facing web or mail server).</li><li>The affected configuration is default or ubiquitous.</li><li>The attack can be performed manually and requires little skill or additional information gathering.</li><li>The race condition is a lazy one (i.e., it is technically a race but easily winnable).</li></ul></td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"AuthenticationHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>None</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Authentication is not required to exploit the vulnerability.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Single Instance</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">The vulnerability requires an attacker to be logged into the system (such as at a command line or via a desktop session or web interface).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Multiple Instances</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Exploiting the vulnerability requires that the attacker authenticate two or more times, even if the same credentials are used each time. An example is an attacker authenticating to an operating system in addition to providing credentials to access an application hosted on that system.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"ConfImpactHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>None</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is no impact to the confidentiality of the system.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Partial</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is considerable informational disclosure. Access to some system files is possible, but the attacker does not have control over what is obtained, or the scope of the loss is constrained. An example is a vulnerability that divulges only certain tables in a database.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Complete</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is total information disclosure, resulting in all system files being revealed. The attacker is able to read all of the system's data (memory, files, etc.)</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"IntegImpactHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>None</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is no impact to the integrity of the system.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Partial</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Modification of some system files or information is possible, but the attacker does not have control over what can be modified, or the scope of what the attacker can affect is limited. For example, system or application files may be overwritten or modified, but either the attacker has no control over which files are affected or the attacker can modify files within only a limited context or scope.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Complete</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is a total compromise of system integrity. There is a complete loss of system protection,resulting in the entire system being compromised. The attacker is able to modify any files on the target system.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"AvailImpactHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>None</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is no impact to the availability of the system.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Partial</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is reduced performance or interruptions in resource availability. An example is a network-based flood attack that permits a limited number of successful connections to an Internet service.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Complete</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is a total shutdown of the affected resource. The attacker can render the resource completely unavailable.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"ExploitabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Unproven that exploit exists</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">No exploit code is available, or an exploit is entirely theoretical.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Proof of concept code</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Proof-of-concept exploit code or an attack demonstration that is not practical for most systems is available. The code or technique is not functional in all situations and may require substantial modification by a skilled attacker.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Functional exploit exists</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Functional exploit code is available. The code works in most situations where the vulnerability exists.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Widespread</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Either the vulnerability is exploitable by functional mobile autonomous code, or no exploit is required (manual trigger) and details are widely available. The code works in every situation, or is actively being delivered via a mobile autonomous agent (such as a worm or virus).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"RemediationLevelHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Official Fix</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">A complete vendor solution is available. Either the vendor has issued an official patch, or an upgrade is available.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Temporary Fix</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is an official but temporary fix available. This includes instances where the vendor issues a temporary hotfix, tool, or workaround.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Workaround</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is an unofficial, non-vendor solution available. In some cases, users of the affected technology will create a patch of their own or provide steps to work around or otherwise mitigate the vulnerability.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Unavailable</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is either no solution available or it is impossible to apply.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"ReportConfidenceHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Not Confirmed</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is a single unconfirmed source or possibly multiple conflicting reports. There is little confidence in the validity of the reports. An example is a rumor that surfaces from the hacker underground.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Uncorroborated</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There are multiple non-official sources, possibly including independent security companies or research organizations. At this point there may be conflicting technical details or some other lingering ambiguity.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Confirmed</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">The vulnerability has been acknowledged by the vendor or author of the affected technology. The vulnerability may also be ?Confirmed? when its existence is confirmed from an external event such as publication of functional or proof-of-concept exploit code or widespread exploitation.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"CollateralDamagePotentialHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>None</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">There is no potential for loss of life, physical assets, productivity or revenue.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Low</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">A successful exploit of this vulnerability may result in slight physical or property damage. Or, there may be a slight loss of revenue or productivity to the organization.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Low-Medium</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">A successful exploit of this vulnerability may result in moderate physical or property damage. Or, there may be a moderate loss of revenue or productivity to the organization.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Medium-High</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">A successful exploit of this vulnerability may result in significant physical or property damage or loss. Or, there may be a significant loss of revenue or productivity.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>High</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">A successful exploit of this vulnerability may result in catastrophic physical or property damage and loss. Or, there may be a catastrophic loss of revenue or productivity.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"TargetDistributionHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>None</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">No target systems exist, or targets are so highly specialized that they only exist in a laboratory setting. Effectively 0% of the environment is at risk.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Low</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Targets exist inside the environment, but on a small scale. Between 1% - 25% of the total environment is at risk.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Medium</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Targets exist inside the environment, but on a medium scale. Between 26% - 75% of the total environment is at risk.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>High</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Targets exist inside the environment on a considerable scale. Between 76% - 100% of the total environment is considered at risk.</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"ConfidentialityRequirementHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Low</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of confidentiality is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Medium</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of confidentiality is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>High</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of confidentiality is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"IntegrityRequirementHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Low</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of integrity is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Medium</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of integrity is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>High</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of integrity is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"AvailabilityRequirementHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Low</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of availability is likely to have only a limited adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>Medium</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of availability is likely to have a serious adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr><td>&nbsp;</td></tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-head\"><b>High</b></td>\n";
    $result .=  "</tr>\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">Loss of availability is likely to have a catastrophic adverse effect on the organization or individuals associated with the organization (e.g., employees, customers).</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<script language=\"javascript\">\n";
    $result .=  "function showHelp(divId) {\n";
    $result .=  "getRef(\"divHelp\").innerHTML=getRef(divId).innerHTML;\n";
    $result .=  "}\n";
    $result .=  "function hideHelp() {\n";
    $result .=  "getRef(\"divHelp\").innerHTML=\"\";\n";
    $result .=  "}\n";
    $result .=  "</script>\n";

    return $result;
}

/*****************************
 * FUNCTION: VIEW DREAD HELP *
 *****************************/
function view_dread_help()
{
    $result = "";
    $result .=  "<div id=\"divHelp\" style=\"width:100%;overflow:auto\"></div>\n";

    $result .=  "<div id=\"DamagePotentialHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>If a threat exploit occurs, how much damage will be caused?</b></p>\n";
    $result .=  "<p>0 = Nothing</p>\n";
    $result .=  "<p>5 = Individual user data is compromised or affected.</p>\n";
    $result .=  "<p>10 = Complete system or data destruction</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"ReproducibilityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How easy is it to reproduce the threat exploit?</b></p>\n";
    $result .=  "<p>0 = Very hard or impossible, even for administrators of the application.</p>\n";
    $result .=  "<p>5 = One or two steps required, may need to be an authorized user.</p>\n";
    $result .=  "<p>10 = Just a web browser and the address bar is sufficient, without authentication.</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"ExploitabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>What is needed to exploit this threat?</b></p>\n";
    $result .=  "<p>0 = Advanced programming and networking knowledge, with custom or advanced attack tools.</p>\n";
    $result .=  "<p>5 = Malware exists on the Internet, or an exploit is easily performed, using available attack tools.</p>\n";
    $result .=  "<p>10 = Just a web browser</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"AffectedUsersHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How many users will be affected?</b></p>\n";
    $result .=  "<p>0 = None</p>\n";
    $result .=  "<p>5 = Some users, but not all</p>\n";
    $result .=  "<p>10 = All users</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<div id=\"DiscoverabilityHelp\"  style=\"display:none; visibility:hidden\">\n";
    $result .=  "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    $result .=  "<tr>\n";
    $result .=  "<td class=\"cal-text\">\n";
    $result .=  "<br /><p><b>How easy is it to discover this threat?</b></p>\n";
    $result .=  "<p>0 = Very hard to impossible; requires source code or administrative access.</p>\n";
    $result .=  "<p>5 = Can figure it out by guessing or by monitoring network traces.</p>\n";
    $result .=  "<p>9 = Details of faults like this are already in the public domain and can be easily discovered using a search engine.</p>\n";
    $result .=  "<p>10 = The information is visible in the web browser address bar or in a form.</p>\n";
    $result .=  "</td>\n";
    $result .=  "</tr>\n";
    $result .=  "</table>\n";
    $result .=  "</div>\n";

    $result .=  "<script language=\"javascript\">\n";
    $result .=  "function showHelp(divId) {\n";
    $result .=  "getRef(\"divHelp\").innerHTML=getRef(divId).innerHTML;\n";
    $result .=  "}\n";
    $result .=  "function hideHelp() {\n";
    $result .=  "getRef(\"divHelp\").innerHTML=\"\";\n";
    $result .=  "}\n";
    $result .=  "</script>\n";
    return $result;
}

/***************************************
 * FUNCTION: VIEW RISK MANAGEMENT MENU *
 ***************************************/
function view_risk_management_menu($active)
{
	global $lang;
	global $escaper;

        echo "<ul class=\"nav nav-pills nav-stacked\">\n";
        echo ($active == "SubmitYourRisks" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"index.php\">I. " . $escaper->escapeHtml($lang['SubmitYourRisks']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "PlanYourMitigations" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"plan_mitigations.php\">II. " . $escaper->escapeHtml($lang['PlanYourMitigations']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "PerformManagementReviews" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"management_review.php\">III. " . $escaper->escapeHtml($lang['PerformManagementReviews']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "PrioritizeForProjectPlanning" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"prioritize_planning.php\">IV. " . $escaper->escapeHtml($lang['PrioritizeForProjectPlanning']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "ReviewRisksRegularly" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"review_risks.php\">V. " . $escaper->escapeHtml($lang['ReviewRisksRegularly']) . "</a>\n";
        echo "</li>\n";
	echo "</ul>\n";
}

/*********************************
 * FUNCTION: VIEW REPORTING MENU *
 *********************************/
function view_reporting_menu($active)
{
	global $lang;
	global $escaper;

        echo "<ul class=\"nav nav-pills nav-stacked\">\n";
        echo ($active == "RiskDashboard" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"index.php\">" . $escaper->escapeHtml($lang['RiskDashboard']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "RiskTrend" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"trend.php\">" . $escaper->escapeHtml($lang['RiskTrend']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksAssignedToMeByRiskLevel" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"my_open.php\">" . $escaper->escapeHtml($lang['AllOpenRisksAssignedToMeByRiskLevel']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksByRiskLevel" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"open.php\">" . $escaper->escapeHtml($lang['AllOpenRisksByRiskLevel']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksConsideredForProjectsByRiskLevel" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"projects.php\">" . $escaper->escapeHtml($lang['AllOpenRisksConsideredForProjectsByRiskLevel']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksAcceptedUntilNextReviewByRiskLevel" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"next_review.php\">" . $escaper->escapeHtml($lang['AllOpenRisksAcceptedUntilNextReviewByRiskLevel']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksToSubmitAsAProductionIssueByRiskLevel" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"production_issues.php\">" . $escaper->escapeHtml($lang['AllOpenRisksToSubmitAsAProductionIssueByRiskLevel']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksByTeam" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"teams.php\">" . $escaper->escapeHtml($lang['AllOpenRisksByTeam']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksByTechnology" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"technologies.php\">" . $escaper->escapeHtml($lang['AllOpenRisksByTechnology']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksByScoringMethod" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"risk_scoring.php\">" . $escaper->escapeHtml($lang['AllOpenRisksByScoringMethod']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllOpenRisksNeedingReview" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"review_needed.php\">" . $escaper->escapeHtml($lang['AllOpenRisksNeedingReview']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "AllClosedRisksByRiskLevel" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"closed.php\">" . $escaper->escapeHtml($lang['AllClosedRisksByRiskLevel']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "HighRiskReport" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"high.php\">" . $escaper->escapeHtml($lang['HighRiskReport']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "SubmittedRisksByDate" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"submitted_by_date.php\">" . $escaper->escapeHtml($lang['SubmittedRisksByDate']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "MitigationsByDate" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"mitigations_by_date.php\">" . $escaper->escapeHtml($lang['MitigationsByDate']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "ManagementReviewsByDate" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"mgmt_reviews_by_date.php\">" . $escaper->escapeHtml($lang['ManagementReviewsByDate']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "ClosedRisksByDate" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"closed_by_date.php\">" . $escaper->escapeHtml($lang['ClosedRisksByDate']) . "</a>\n";
        echo "</li>\n";
        echo ($active == "ProjectsAndRisksAssigned" ? "<li class=\"active\">\n" : "<li>\n");
        echo "<a href=\"projects_and_risks.php\">" . $escaper->escapeHtml($lang['ProjectsAndRisksAssigned']) . "</a>\n";
        echo "</li>\n";
	echo "</ul>\n";
}

/*********************************
 * FUNCTION: VIEW CONFIGURE MENU *
 *********************************/
function view_configure_menu($active)
{
	global $lang;
	global $escaper;

    $result = "";

    $result .=  "<ul class=\"nav nav-pills nav-stacked\">\n";
    $result .=  ($active == "ConfigureRiskFormula" ? "<li class=\"active\">\n" : "<li>\n");
    $result .=  "<a href=\"index.php\">" . $escaper->escapeHtml($lang['ConfigureRiskFormula']) . "</a>\n";
    $result .=  "</li>\n";
    $result .=  ($active == "ConfigureReviewSettings" ? "<li class=\"active\">\n" : "<li>\n");
    $result .=  "<a href=\"review_settings.php\">" . $escaper->escapeHtml($lang['ConfigureReviewSettings']) . "</a>\n";
    $result .=  "</li>\n";
    $result .=  ($active == "AddAndRemoveValues" ? "<li class=\"active\">\n" : "<li>\n");
    $result .=  "<a href=\"add_remove_values.php\">" . $escaper->escapeHtml($lang['AddAndRemoveValues']) . "</a>\n";
    $result .=  "</li>\n";
    $result .=  ($active == "UserManagement" ? "<li class=\"active\">\n" : "<li>\n");
    $result .=  "<a href=\"user_management.php\">" . $escaper->escapeHtml($lang['UserManagement']) . "</a>\n";
    $result .=  "</li>\n";
    $result .=  ($active == "RedefineNamingConventions" ? "<li class=\"active\">\n" : "<li>\n");
    $result .=  "<a href=\"custom_names.php\">" . $escaper->escapeHtml($lang['RedefineNamingConventions']) . "</a>\n";
    $result .=  "</li>\n";
    $result .=  ($active == "AuditTrail" ? "<li class=\"active\">\n" : "<li>\n");
    $result .=  "<a href=\"audit_trail.php\">" . $escaper->escapeHtml($lang['AuditTrail']) . "</a>\n";
    $result .=  "</li>\n";
    $result .=  ($active == "Announcements" ? "<li class=\"active\">\n" : "<li>\n");
    $result .=  "<a href=\"announcements.php\">" . $escaper->escapeHtml($lang['Announcements']) . "</a>\n";
    $result .=  "</li>\n";
    $result .=  ($active == "About" ? "<li class=\"active\">\n" : "<li>\n");
    $result .=  "<a href=\"about.php\">" . $escaper->escapeHtml($lang['About']) . "</a>\n";
    $result .=  "</li>\n";
    $result .=  "</ul>\n";

    return $result;

}

?>
